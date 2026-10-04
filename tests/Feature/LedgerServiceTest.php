<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostingService $posting;

    private LedgerService $ledger;

    private User $user;

    /** @var array<string,ChartOfAccount> */
    private array $acc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->posting = app(PostingService::class);
        $this->ledger = app(LedgerService::class);
        $this->user = User::factory()->create();

        $this->acc['bank'] = $this->make(AccountType::Asset, '102');
        $this->acc['capital'] = $this->make(AccountType::Equity, '301');
        $this->acc['sales'] = $this->make(AccountType::Income, '400.1');

        // Opening: inject 1,000 of capital into the bank (Jan).
        $this->posting->post($this->user->id, '2026-01-01', [
            ['account_id' => $this->acc['bank']->id, 'debit' => 1000],
            ['account_id' => $this->acc['capital']->id, 'credit' => 1000],
        ]);

        // February sale of 500 collected in the bank.
        $this->posting->post($this->user->id, '2026-02-15', [
            ['account_id' => $this->acc['bank']->id, 'debit' => 500],
            ['account_id' => $this->acc['sales']->id, 'credit' => 500],
        ]);
    }

    private function make(AccountType $type, string $code): ChartOfAccount
    {
        return ChartOfAccount::factory()->ofType($type)->create([
            'user_id' => $this->user->id,
            'code' => $code,
        ]);
    }

    public function test_account_balance_is_computed_as_of_a_date(): void
    {
        // Bank is debit-nature: 1000 + 500 debits.
        $this->assertSame('1500.0000', $this->ledger->balance($this->user->id, $this->acc['bank']->id, '2026-02-28'));

        // As of January, only the opening has happened.
        $this->assertSame('1000.0000', $this->ledger->balance($this->user->id, $this->acc['bank']->id, '2026-01-31'));

        // Sales is credit-nature: positive 500.
        $this->assertSame('500.0000', $this->ledger->balance($this->user->id, $this->acc['sales']->id, '2026-02-28'));
    }

    public function test_trial_balance_balances_and_carries_opening(): void
    {
        $tb = $this->ledger->trialBalance($this->user->id, '2026-02-01', '2026-02-28');

        // The period debit and credit columns must be equal.
        $this->assertSame($tb['totals']['debit'], $tb['totals']['credit']);
        $this->assertSame('500.0000', $tb['totals']['debit']);

        $bankRow = collect($tb['rows'])->firstWhere('account_id', $this->acc['bank']->id);
        $this->assertSame('1000.0000', $bankRow['opening']); // carried from January
        $this->assertSame('500.0000', $bankRow['debit']);
        $this->assertSame('1500.0000', $bankRow['closing']);

        // Capital had no February movement, so it is excluded from the period rows... unless opening shows it.
        $capitalRow = collect($tb['rows'])->firstWhere('account_id', $this->acc['capital']->id);
        $this->assertSame('1000.0000', $capitalRow['opening']);
    }

    public function test_voiding_an_entry_removes_its_effect_on_balances(): void
    {
        $entry = $this->posting->post($this->user->id, '2026-03-01', [
            ['account_id' => $this->acc['bank']->id, 'debit' => 250],
            ['account_id' => $this->acc['sales']->id, 'credit' => 250],
        ]);

        $this->assertSame('1750.0000', $this->ledger->balance($this->user->id, $this->acc['bank']->id, '2026-03-31'));

        $this->posting->void($entry, '2026-03-01');

        // Original (void) + reversing cancel out; net back to 1500.
        $this->assertSame('1500.0000', $this->ledger->balance($this->user->id, $this->acc['bank']->id, '2026-03-31'));
    }
}
