<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\PostingService;
use App\Services\Tax\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TaxService $tax;

    /** @var array<string,ChartOfAccount> */
    private array $acc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tax = app(TaxService::class);

        $this->acc['bank'] = $this->make(AccountType::Asset, '102');
        $this->acc['sales'] = $this->make(AccountType::Income, '400.1');
        $this->acc['expense'] = $this->make(AccountType::Expense, '500.1');
        $this->acc['iva_acred'] = $this->make(AccountType::Asset, '118');
        $this->acc['iva_tras'] = $this->make(AccountType::Liability, '213');

        $posting = app(PostingService::class);
        // Sale 1,000 + 160 IVA trasladado.
        $posting->post($this->user->id, '2026-02-10', [
            ['account_id' => $this->acc['bank']->id, 'debit' => 1160],
            ['account_id' => $this->acc['sales']->id, 'credit' => 1000],
            ['account_id' => $this->acc['iva_tras']->id, 'credit' => 160, 'tax_code' => 'IVA16', 'tax_rate' => 0.16, 'tax_base' => 1000],
        ]);
        // Purchase 500 + 80 IVA acreditable.
        $posting->post($this->user->id, '2026-02-12', [
            ['account_id' => $this->acc['expense']->id, 'debit' => 500],
            ['account_id' => $this->acc['iva_acred']->id, 'debit' => 80, 'tax_code' => 'IVA16', 'tax_rate' => 0.16, 'tax_base' => 500],
            ['account_id' => $this->acc['bank']->id, 'credit' => 580],
        ]);
    }

    private function make(AccountType $type, string $code): ChartOfAccount
    {
        return ChartOfAccount::factory()->ofType($type)->create(['user_id' => $this->user->id, 'code' => $code]);
    }

    public function test_it_resolves_the_iva_accounts(): void
    {
        $accounts = $this->tax->accounts($this->user->id);
        $this->assertSame('118', $accounts['acreditable']->code);
        $this->assertSame('213', $accounts['trasladado']->code);
    }

    public function test_iva_report_nets_trasladado_minus_acreditable(): void
    {
        $r = $this->tax->ivaReport($this->user->id, '2026-02-01', '2026-02-28');

        $this->assertSame('160.0000', $r['trasladado']);
        $this->assertSame('80.0000', $r['acreditable']);
        $this->assertSame('80.0000', $r['neto']);
        $this->assertSame('a_cargo', $r['resultado']);
        $this->assertTrue($r['accounts_configured']);
    }

    public function test_iva_can_be_a_favor(): void
    {
        // Extra purchase pushes acreditable above trasladado.
        app(PostingService::class)->post($this->user->id, '2026-02-20', [
            ['account_id' => $this->acc['expense']->id, 'debit' => 2000],
            ['account_id' => $this->acc['iva_acred']->id, 'debit' => 320, 'tax_code' => 'IVA16', 'tax_rate' => 0.16, 'tax_base' => 2000],
            ['account_id' => $this->acc['bank']->id, 'credit' => 2320],
        ]);

        $r = $this->tax->ivaReport($this->user->id, '2026-02-01', '2026-02-28');
        $this->assertSame('a_favor', $r['resultado']);
        $this->assertSame('-240.0000', $r['neto']); // 160 - 400
    }
}
