<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class ManagedCashFlowTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    private ChartOfAccount $cash;

    private ChartOfAccount $income;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cash = ChartOfAccount::factory()->ofType(AccountType::Asset)
            ->create(['user_id' => $this->user->id, 'code' => '1020', 'name' => 'Bancos', 'is_cash' => true]);
        $this->income = ChartOfAccount::factory()->ofType(AccountType::Income)
            ->create(['user_id' => $this->user->id, 'code' => '4000', 'name' => 'Ventas']);

        // +1,000 cash movement in March 2026.
        app(PostingService::class)->post(
            userId: $this->user->id,
            entryDate: '2026-03-15',
            legs: [
                ['account_id' => $this->cash->id, 'debit' => 1000],
                ['account_id' => $this->income->id, 'credit' => 1000],
            ],
        );
    }

    public function test_untouched_month_mirrors_actuals(): void
    {
        $this->loginApi($this->user)
            ->getJson('/api/managed-cash-flow?period=2026-03')
            ->assertOk()
            ->assertJsonPath('rows.0.opening', '0.0000')
            ->assertJsonPath('rows.0.actual', '1000.0000')
            ->assertJsonPath('rows.0.planned', '1000.0000')   // defaults to actual
            ->assertJsonPath('rows.0.variance', '0.0000')
            ->assertJsonPath('rows.0.closing', '1000.0000');
    }

    public function test_saving_a_plan_updates_variance_and_closing(): void
    {
        $this->loginApi($this->user)
            ->postJson('/api/managed-cash-flow', [
                'period' => '2026-03',
                'rows' => [['account_id' => $this->cash->id, 'planned_amount' => 1500]],
            ])
            ->assertOk()
            ->assertJsonPath('rows.0.planned', '1500.0000')
            ->assertJsonPath('rows.0.variance', '500.0000')
            ->assertJsonPath('rows.0.closing', '1500.0000');

        $this->assertDatabaseHas('cash_flow_adjustments', [
            'user_id' => $this->user->id, 'period' => '2026-03',
            'account_id' => $this->cash->id, 'planned_amount' => '1500.0000',
        ]);
    }

    public function test_adjusted_closing_carries_to_next_month_opening(): void
    {
        $this->loginApi($this->user)->postJson('/api/managed-cash-flow', [
            'period' => '2026-03',
            'rows' => [['account_id' => $this->cash->id, 'planned_amount' => 1500]],
        ])->assertOk();

        // April opening must equal March's adjusted closing (1500), even though
        // the real ledger balance entering April is only 1000.
        $this->loginApi($this->user)
            ->getJson('/api/managed-cash-flow?period=2026-04')
            ->assertOk()
            ->assertJsonPath('rows.0.opening', '1500.0000')
            ->assertJsonPath('rows.0.actual', '0.0000')
            ->assertJsonPath('rows.0.closing', '1500.0000');
    }

    public function test_save_rejects_a_foreign_account(): void
    {
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Asset)
            ->create(['user_id' => User::factory()->create()->id, 'is_cash' => true]);

        $this->loginApi($this->user)
            ->postJson('/api/managed-cash-flow', [
                'period' => '2026-03',
                'rows' => [['account_id' => $foreign->id, 'planned_amount' => 100]],
            ])
            ->assertStatus(422);
    }

    public function test_reports_when_user_has_no_cash_accounts(): void
    {
        $other = User::factory()->create();

        $this->loginApi($other)
            ->getJson('/api/managed-cash-flow?period=2026-03')
            ->assertOk()
            ->assertJsonPath('has_cash_accounts', false)
            ->assertJsonPath('rows', []);
    }
}
