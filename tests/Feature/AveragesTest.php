<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class AveragesTest extends TestCase
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
        foreach ([['2026-01-10', 1000], ['2026-02-10', 2000], ['2026-03-10', 3000]] as [$date, $amt]) {
            $posting->post(userId: $this->user->id, entryDate: $date, legs: [
                ['account_id' => $this->cash->id, 'debit' => $amt],
                ['account_id' => $this->income->id, 'credit' => $amt],
            ]);
        }
        $posting->post(userId: $this->user->id, entryDate: '2026-02-15', legs: [
            ['account_id' => $this->expense->id, 'debit' => 600],
            ['account_id' => $this->cash->id, 'credit' => 600],
        ]);
    }

    public function test_averages_divide_totals_by_months_in_range(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/reports/averages?from=2026-01-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('months', 3)
            ->assertJsonPath('revenue.0.total', '6000.0000')
            ->assertJsonPath('revenue.0.average', '2000.0000')
            ->assertJsonPath('expenses.0.total', '600.0000')
            ->assertJsonPath('expenses.0.average', '200.0000')
            ->assertJsonPath('totals.net.total', '5400.0000')
            ->assertJsonPath('totals.net.average', '1800.0000');
    }

    public function test_single_month_average_equals_total(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/reports/averages?from=2026-02-01&to=2026-02-28')
            ->assertOk()
            ->assertJsonPath('months', 1)
            ->assertJsonPath('revenue.0.total', '2000.0000')
            ->assertJsonPath('revenue.0.average', '2000.0000');
    }

    public function test_averages_require_a_valid_period(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/reports/averages?from=2026-03-01&to=2026-01-01')
            ->assertStatus(422); // to before from
    }

    public function test_averages_are_user_scoped(): void
    {
        $other = User::factory()->create();

        $this->loginApi($other)
            ->getJson('/api/reports/averages?from=2026-01-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('revenue', [])
            ->assertJsonPath('expenses', []);
    }
}
