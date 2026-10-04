<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    public function test_it_creates_an_account(): void
    {
        $user = User::factory()->create();

        $this->loginApi($user)
            ->postJson('/api/accounts', [
                'code' => '102',
                'name' => 'Bancos',
                'type' => 'asset',
            ])
            ->assertCreated()
            ->assertJsonPath('data.normal_balance', 'debit')
            ->assertJsonPath('data.nature_label', 'Deudora');

        $this->assertDatabaseHas('chart_of_accounts', [
            'user_id' => $user->id, 'code' => '102', 'normal_balance' => 'debit',
        ]);
    }

    public function test_listing_is_scoped_to_the_user(): void
    {
        $alice = User::factory()->create();
        ChartOfAccount::factory()->ofType(AccountType::Asset)->count(2)->create(['user_id' => $alice->id]);
        ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($alice)
            ->getJson('/api/accounts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $user = User::factory()->create();
        ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $user->id, 'code' => '101']);

        $this->loginApi($user)
            ->postJson('/api/accounts', ['code' => '101', 'name' => 'Caja 2', 'type' => 'asset'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('code');
    }

    public function test_cannot_view_or_update_another_users_account(): void
    {
        $alice = User::factory()->create();
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Asset)
            ->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($alice)->getJson("/api/accounts/{$foreign->id}")->assertNotFound();
        $this->loginApi($alice)->putJson("/api/accounts/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    }

    public function test_account_with_entries_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $user->id]);
        $entry = \App\Models\JournalEntry::factory()->create(['user_id' => $user->id]);
        \App\Models\JournalEntryLine::factory()->debit(10)->create([
            'user_id' => $user->id, 'journal_entry_id' => $entry->id, 'account_id' => $account->id,
        ]);

        $this->loginApi($user)->deleteJson("/api/accounts/{$account->id}")->assertStatus(409);
    }
}
