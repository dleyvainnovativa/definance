<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, AccountType $type, string $code): ChartOfAccount
    {
        return ChartOfAccount::factory()->ofType($type)->create([
            'user_id' => $user->id,
            'code' => $code,
        ]);
    }

    public function test_a_balanced_multi_leg_entry_persists(): void
    {
        $user = User::factory()->create();
        $bank = $this->account($user, AccountType::Asset, '102');
        $sales = $this->account($user, AccountType::Income, '400.1');
        $ivaOut = $this->account($user, AccountType::Liability, '213');

        $entry = JournalEntry::factory()->create(['user_id' => $user->id]);

        // Sale of 1000 + 160 IVA collected into the bank (3 legs).
        JournalEntryLine::factory()->debit(1160.00)->create([
            'user_id' => $user->id, 'journal_entry_id' => $entry->id, 'account_id' => $bank->id,
        ]);
        JournalEntryLine::factory()->credit(1000.00)->create([
            'user_id' => $user->id, 'journal_entry_id' => $entry->id, 'account_id' => $sales->id,
        ]);
        JournalEntryLine::factory()->credit(160.00)->create([
            'user_id' => $user->id, 'journal_entry_id' => $entry->id, 'account_id' => $ivaOut->id,
        ]);

        $this->assertSame(3, $entry->lines()->count());
        $this->assertTrue($entry->fresh()->load('lines')->isBalanced());
    }

    public function test_a_line_with_both_sides_is_rejected(): void
    {
        $this->expectException(\DomainException::class);

        $user = User::factory()->create();
        $acc = $this->account($user, AccountType::Asset, '101');
        $entry = JournalEntry::factory()->create(['user_id' => $user->id]);

        JournalEntryLine::create([
            'user_id' => $user->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $acc->id,
            'debit' => 50,
            'credit' => 50, // both sides -> invalid
        ]);
    }

    public function test_a_line_with_no_side_is_rejected(): void
    {
        $this->expectException(\DomainException::class);

        $user = User::factory()->create();
        $acc = $this->account($user, AccountType::Asset, '101');
        $entry = JournalEntry::factory()->create(['user_id' => $user->id]);

        JournalEntryLine::create([
            'user_id' => $user->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $acc->id,
            'debit' => null,
            'credit' => null, // neither side -> invalid
        ]);
    }

    public function test_normal_balance_is_derived_from_type(): void
    {
        $user = User::factory()->create();
        $asset = $this->account($user, AccountType::Asset, '150');
        $income = $this->account($user, AccountType::Income, '410');

        $this->assertSame('debit', $asset->normal_balance->value);
        $this->assertSame('credit', $income->normal_balance->value);
    }
}
