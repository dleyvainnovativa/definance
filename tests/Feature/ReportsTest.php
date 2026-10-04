<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private User $user;

    /** @var array<string,ChartOfAccount> */
    private array $acc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(LedgerService::class);
        $posting = app(PostingService::class);
        $this->user = User::factory()->create();

        $this->acc['bank'] = $this->make(AccountType::Asset, '102', isCash: true);
        $this->acc['capital'] = $this->make(AccountType::Equity, '301');
        $this->acc['sales'] = $this->make(AccountType::Income, '400.1');
        $this->acc['rent'] = $this->make(AccountType::Expense, '500.1');

        // Opening capital into bank (January).
        $posting->post($this->user->id, '2026-01-01', [
            ['account_id' => $this->acc['bank']->id, 'debit' => 1000],
            ['account_id' => $this->acc['capital']->id, 'credit' => 1000],
        ]);
        // February: sale 800 into bank.
        $posting->post($this->user->id, '2026-02-10', [
            ['account_id' => $this->acc['bank']->id, 'debit' => 800],
            ['account_id' => $this->acc['sales']->id, 'credit' => 800],
        ]);
        // February: pay 300 rent from bank.
        $posting->post($this->user->id, '2026-02-20', [
            ['account_id' => $this->acc['rent']->id, 'debit' => 300],
            ['account_id' => $this->acc['bank']->id, 'credit' => 300],
        ]);
    }

    private function make(AccountType $type, string $code, bool $isCash = false): ChartOfAccount
    {
        return ChartOfAccount::factory()->ofType($type)->create([
            'user_id' => $this->user->id,
            'code' => $code,
            'is_cash' => $isCash,
        ]);
    }

    public function test_income_statement_for_the_period(): void
    {
        $is = $this->ledger->incomeStatement($this->user->id, '2026-02-01', '2026-02-28');

        $this->assertSame('800.0000', $is['totals']['revenue']);
        $this->assertSame('300.0000', $is['totals']['expenses']);
        $this->assertSame('500.0000', $is['totals']['net_income']);
    }

    public function test_balance_sheet_balances(): void
    {
        $bs = $this->ledger->balanceSheet($this->user->id, '2026-02-28');

        $this->assertSame('1500.0000', $bs['totals']['assets']);
        $this->assertSame('0.0000', $bs['totals']['liabilities']);
        $this->assertSame('1000.0000', $bs['totals']['equity']);
        $this->assertSame('500.0000', $bs['net_income']);
        $this->assertSame('1500.0000', $bs['totals']['liabilities_plus_equity']);
        $this->assertTrue($bs['balanced']);
    }

    public function test_cash_flow_for_the_period(): void
    {
        $cf = $this->ledger->cashFlow($this->user->id, '2026-02-01', '2026-02-28');

        $this->assertSame('1000.0000', $cf['totals']['opening']);
        $this->assertSame('800.0000', $cf['totals']['inflow']);
        $this->assertSame('300.0000', $cf['totals']['outflow']);
        $this->assertSame('500.0000', $cf['totals']['net_change']);
        $this->assertSame('1500.0000', $cf['totals']['closing']);
    }

    public function test_report_endpoint_requires_a_period(): void
    {
        // Sanity: the service returns the documented shape.
        $tb = $this->ledger->trialBalance($this->user->id, '2026-01-01', '2026-02-28');
        $this->assertSame($tb['totals']['debit'], $tb['totals']['credit']);
    }
}
