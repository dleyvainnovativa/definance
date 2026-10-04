<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\EntryStatus;
use App\Exceptions\PostingException;
use App\Exceptions\UnbalancedEntryException;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\Leg;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostingService::class);
        $this->user = User::factory()->create();
    }

    private function account(AccountType $type, string $code, bool $postable = true): ChartOfAccount
    {
        return ChartOfAccount::factory()->ofType($type)->create([
            'user_id' => $this->user->id,
            'code' => $code,
            'is_postable' => $postable,
        ]);
    }

    public function test_posts_a_balanced_multi_leg_entry(): void
    {
        $bank = $this->account(AccountType::Asset, '102');
        $sales = $this->account(AccountType::Income, '400.1');
        $iva = $this->account(AccountType::Liability, '213');

        $entry = $this->service->post($this->user->id, '2026-02-15', [
            ['account_id' => $bank->id, 'debit' => 1160],
            ['account_id' => $sales->id, 'credit' => 1000],
            ['account_id' => $iva->id, 'credit' => 160],
        ], 'Venta con IVA');

        $this->assertSame(EntryStatus::Posted, $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertCount(3, $entry->lines);
        $this->assertTrue($entry->isBalanced());
    }

    public function test_rejects_an_unbalanced_entry(): void
    {
        $bank = $this->account(AccountType::Asset, '102');
        $sales = $this->account(AccountType::Income, '400.1');

        $this->expectException(UnbalancedEntryException::class);

        $this->service->post($this->user->id, '2026-02-15', [
            ['account_id' => $bank->id, 'debit' => 1000],
            ['account_id' => $sales->id, 'credit' => 999],
        ]);
    }

    public function test_rejects_fewer_than_two_legs(): void
    {
        $bank = $this->account(AccountType::Asset, '102');

        $this->expectException(PostingException::class);

        $this->service->post($this->user->id, '2026-02-15', [
            ['account_id' => $bank->id, 'debit' => 10],
        ]);
    }

    public function test_rejects_a_non_postable_account(): void
    {
        $group = $this->account(AccountType::Asset, '100', postable: false);
        $sales = $this->account(AccountType::Income, '400.1');

        $this->expectException(PostingException::class);

        $this->service->post($this->user->id, '2026-02-15', [
            ['account_id' => $group->id, 'debit' => 100],
            ['account_id' => $sales->id, 'credit' => 100],
        ]);
    }

    public function test_rejects_an_account_from_another_user(): void
    {
        $mine = $this->account(AccountType::Asset, '102');
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Income)->create([
            'user_id' => User::factory()->create()->id,
            'code' => '400.1',
        ]);

        $this->expectException(PostingException::class);

        $this->service->post($this->user->id, '2026-02-15', [
            ['account_id' => $mine->id, 'debit' => 100],
            ['account_id' => $foreign->id, 'credit' => 100],
        ]);
    }

    public function test_nothing_is_written_when_posting_fails(): void
    {
        $bank = $this->account(AccountType::Asset, '102');
        $sales = $this->account(AccountType::Income, '400.1');

        try {
            $this->service->post($this->user->id, '2026-02-15', [
                ['account_id' => $bank->id, 'debit' => 1000],
                ['account_id' => $sales->id, 'credit' => 1],
            ]);
        } catch (UnbalancedEntryException) {
            // expected
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_void_posts_a_reversing_entry_and_marks_original(): void
    {
        $bank = $this->account(AccountType::Asset, '102');
        $sales = $this->account(AccountType::Income, '400.1');

        $entry = $this->service->post($this->user->id, '2026-02-15', [
            ['account_id' => $bank->id, 'debit' => 500],
            ['account_id' => $sales->id, 'credit' => 500],
        ]);

        $reversing = $this->service->void($entry);

        $entry->refresh();
        $this->assertSame(EntryStatus::Void, $entry->status);
        $this->assertSame($reversing->id, $entry->reversed_entry_id);

        // Reversing legs are the mirror image.
        $revBankLine = $reversing->lines->firstWhere('account_id', $bank->id);
        $revSalesLine = $reversing->lines->firstWhere('account_id', $sales->id);
        $this->assertTrue((float) $revBankLine->credit === 500.0);
        $this->assertTrue((float) $revSalesLine->debit === 500.0);
    }
}
