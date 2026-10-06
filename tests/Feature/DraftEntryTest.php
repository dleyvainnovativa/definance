<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\EntryStatus;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class DraftEntryTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    private ChartOfAccount $cash;

    private ChartOfAccount $income;

    private ChartOfAccount $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cash = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1000']);
        $this->income = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '4000']);
        $this->expense = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $this->user->id, 'code' => '5000']);
    }

    private function draft(int $amount = 100): int
    {
        return $this->loginApi($this->user)->postJson('/api/entries', [
            'entry_date' => '2026-05-10',
            'status' => 'draft',
            'legs' => [
                ['account_id' => $this->cash->id, 'debit' => $amount],
                ['account_id' => $this->income->id, 'credit' => $amount],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
    }

    public function test_a_draft_is_excluded_from_reports_until_posted(): void
    {
        $id = $this->draft(100);

        $this->loginApi($this->user)
            ->getJson('/api/reports/income-statement?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('totals.revenue', '0.0000'); // draft not counted

        $this->loginApi($this->user)->postJson("/api/entries/{$id}/post")->assertOk()->assertJsonPath('data.status', 'posted');

        $this->loginApi($this->user)
            ->getJson('/api/reports/income-statement?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('totals.revenue', '100.0000');
    }

    public function test_editing_a_draft_replaces_its_legs(): void
    {
        $id = $this->draft(100);

        $this->loginApi($this->user)->putJson("/api/entries/{$id}", [
            'entry_date' => '2026-05-11',
            'legs' => [
                ['account_id' => $this->cash->id, 'debit' => 250],
                ['account_id' => $this->income->id, 'credit' => 250],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.totals.debit', '250.0000')
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseCount('journal_entry_lines', 2); // old legs replaced, not appended
        $this->assertDatabaseHas('journal_entries', ['id' => $id, 'entry_date' => '2026-05-11']);
    }

    public function test_a_posted_entry_cannot_be_edited_or_posted(): void
    {
        $id = $this->loginApi($this->user)->postJson('/api/entries', [
            'entry_date' => '2026-05-10',
            'legs' => [
                ['account_id' => $this->cash->id, 'debit' => 100],
                ['account_id' => $this->income->id, 'credit' => 100],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'posted')->json('data.id');

        $this->loginApi($this->user)->putJson("/api/entries/{$id}", [
            'entry_date' => '2026-05-10',
            'legs' => [
                ['account_id' => $this->cash->id, 'debit' => 1],
                ['account_id' => $this->income->id, 'credit' => 1],
            ],
        ])->assertForbidden();

        $this->loginApi($this->user)->postJson("/api/entries/{$id}/post")->assertForbidden();
    }

    public function test_a_user_cannot_edit_another_users_draft(): void
    {
        $id = $this->draft(100);
        $other = User::factory()->create();

        // Route-model binding is user-scoped, so the entry is simply not found.
        $this->loginApi($other)->putJson("/api/entries/{$id}", [
            'entry_date' => '2026-05-10',
            'legs' => [['account_id' => $this->cash->id, 'debit' => 1], ['account_id' => $this->income->id, 'credit' => 1]],
        ])->assertNotFound();
    }

    public function test_advanced_filter_by_debit_account(): void
    {
        // A: cash on debit; B: cash on credit.
        $this->loginApi($this->user)->postJson('/api/entries', ['entry_date' => '2026-05-10', 'legs' => [
            ['account_id' => $this->cash->id, 'debit' => 100], ['account_id' => $this->income->id, 'credit' => 100],
        ]])->assertCreated();
        $this->loginApi($this->user)->postJson('/api/entries', ['entry_date' => '2026-05-11', 'legs' => [
            ['account_id' => $this->expense->id, 'debit' => 50], ['account_id' => $this->cash->id, 'credit' => 50],
        ]])->assertCreated();

        $this->loginApi($this->user)
            ->getJson("/api/entries?debit_account_id={$this->cash->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entry_date', '2026-05-10');
    }
}
