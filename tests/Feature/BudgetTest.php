<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class BudgetTest extends TestCase
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
        $posting->post(userId: $this->user->id, entryDate: '2026-06-01', legs: [
            ['account_id' => $this->cash->id, 'debit' => 2000],
            ['account_id' => $this->income->id, 'credit' => 2000],
        ]);
        $posting->post(userId: $this->user->id, entryDate: '2026-06-02', legs: [
            ['account_id' => $this->expense->id, 'debit' => 800],
            ['account_id' => $this->cash->id, 'credit' => 800],
        ]);
    }

    public function test_unbudgeted_year_shows_zero_budget_and_real_actuals(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/budgets?year=2026')
            ->assertOk()
            ->assertJsonPath('revenue.0.actual', '2000.0000')
            ->assertJsonPath('revenue.0.budget', '0.0000')      // unbudgeted → 0, not mirrored
            ->assertJsonPath('revenue.0.variance', '-2000.0000')
            ->assertJsonPath('expenses.0.actual', '800.0000')
            ->assertJsonPath('totals.net.actual', '1200.0000');
    }

    public function test_saving_budget_computes_variance_and_net(): void
    {
        $this->loginApi($this->user)
            ->postJson('/api/budgets', [
                'year' => 2026,
                'rows' => [
                    ['account_id' => $this->income->id, 'amount' => 2500],
                    ['account_id' => $this->expense->id, 'amount' => 1000],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('revenue.0.budget', '2500.0000')
            ->assertJsonPath('revenue.0.variance', '500.0000')
            ->assertJsonPath('expenses.0.budget', '1000.0000')
            ->assertJsonPath('expenses.0.variance', '200.0000')
            ->assertJsonPath('totals.net.budget', '1500.0000')
            ->assertJsonPath('totals.net.actual', '1200.0000')
            ->assertJsonPath('totals.net.variance', '300.0000');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id, 'year' => 2026, 'month' => 0,
            'account_id' => $this->income->id, 'amount' => '2500.0000',
        ]);
    }

    public function test_saving_is_idempotent_upsert(): void
    {
        $payload = ['year' => 2026, 'rows' => [['account_id' => $this->income->id, 'amount' => 2500]]];
        $this->loginApi($this->user)->postJson('/api/budgets', $payload)->assertOk();
        $this->loginApi($this->user)->postJson('/api/budgets', ['year' => 2026, 'rows' => [['account_id' => $this->income->id, 'amount' => 3000]]])->assertOk();

        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', ['account_id' => $this->income->id, 'amount' => '3000.0000']);
    }

    public function test_save_rejects_a_foreign_account(): void
    {
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Expense)
            ->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($this->user)
            ->postJson('/api/budgets', ['year' => 2026, 'rows' => [['account_id' => $foreign->id, 'amount' => 100]]])
            ->assertStatus(422);

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_year_defaults_to_current_year(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/budgets')
            ->assertOk()
            ->assertJsonPath('year', (int) now()->format('Y'));
    }
}
