<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class BudgetMonthlyTest extends TestCase
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
        $this->cash = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1000', 'name' => 'Caja']);
        $this->income = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '4000', 'name' => 'Ventas']);
        $this->expense = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $this->user->id, 'code' => '5000', 'name' => 'Gastos']);

        $posting = app(PostingService::class);
        // Jan: +1000 income, -400 expense; Feb: +1500 income.
        $posting->post(userId: $this->user->id, entryDate: '2026-01-10', legs: [
            ['account_id' => $this->cash->id, 'debit' => 1000],
            ['account_id' => $this->income->id, 'credit' => 1000],
        ]);
        $posting->post(userId: $this->user->id, entryDate: '2026-01-15', legs: [
            ['account_id' => $this->expense->id, 'debit' => 400],
            ['account_id' => $this->cash->id, 'credit' => 400],
        ]);
        $posting->post(userId: $this->user->id, entryDate: '2026-02-10', legs: [
            ['account_id' => $this->cash->id, 'debit' => 1500],
            ['account_id' => $this->income->id, 'credit' => 1500],
        ]);
    }

    public function test_monthly_matrix_reflects_actuals_per_month(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/budgets/monthly?year=2026')
            ->assertOk()
            ->assertJsonPath('revenue.0.actual.0', '1000.0000')   // Jan
            ->assertJsonPath('revenue.0.actual.1', '1500.0000')   // Feb
            ->assertJsonPath('revenue.0.totals.actual', '2500.0000')
            ->assertJsonPath('revenue.0.budget.0', '0.0000')      // unbudgeted
            ->assertJsonPath('expenses.0.actual.0', '400.0000')
            ->assertJsonPath('totals.net.total.actual', '2100.0000');
    }

    public function test_saving_monthly_budget_computes_variance(): void
    {
        $this->loginApi($this->user)
            ->postJson('/api/budgets/monthly', [
                'year' => 2026,
                'rows' => [
                    ['account_id' => $this->income->id, 'month' => 1, 'amount' => 1200],
                    ['account_id' => $this->income->id, 'month' => 2, 'amount' => 1400],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('revenue.0.budget.0', '1200.0000')
            ->assertJsonPath('revenue.0.budget.1', '1400.0000')
            ->assertJsonPath('revenue.0.variance.0', '200.0000')    // 1200 − 1000
            ->assertJsonPath('revenue.0.variance.1', '-100.0000')   // 1400 − 1500
            ->assertJsonPath('revenue.0.totals.budget', '2600.0000');

        $this->assertDatabaseHas('budgets', ['user_id' => $this->user->id, 'year' => 2026, 'month' => 1, 'account_id' => $this->income->id, 'amount' => '1200.0000']);
        $this->assertDatabaseHas('budgets', ['user_id' => $this->user->id, 'year' => 2026, 'month' => 2, 'account_id' => $this->income->id, 'amount' => '1400.0000']);
    }

    public function test_monthly_save_is_idempotent_upsert(): void
    {
        $row = fn ($amt) => ['year' => 2026, 'rows' => [['account_id' => $this->income->id, 'month' => 3, 'amount' => $amt]]];
        $this->loginApi($this->user)->postJson('/api/budgets/monthly', $row(500))->assertOk();
        $this->loginApi($this->user)->postJson('/api/budgets/monthly', $row(700))->assertOk();

        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', ['month' => 3, 'account_id' => $this->income->id, 'amount' => '700.0000']);
    }

    public function test_monthly_save_rejects_foreign_account_and_bad_month(): void
    {
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($this->user)->postJson('/api/budgets/monthly', [
            'year' => 2026, 'rows' => [['account_id' => $foreign->id, 'month' => 1, 'amount' => 100]],
        ])->assertStatus(422);

        $this->loginApi($this->user)->postJson('/api/budgets/monthly', [
            'year' => 2026, 'rows' => [['account_id' => $this->income->id, 'month' => 13, 'amount' => 100]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_year_defaults_to_current_year(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/budgets/monthly')
            ->assertOk()
            ->assertJsonPath('year', (int) now()->format('Y'))
            ->assertJsonCount(12, 'months');
    }
}
