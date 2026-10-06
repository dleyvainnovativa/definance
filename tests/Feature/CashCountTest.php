<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Ledger\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class CashCountTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    private ChartOfAccount $cashA;

    private ChartOfAccount $cashB;

    private ChartOfAccount $income;

    private ChartOfAccount $diff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cashA = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1010', 'name' => 'Caja A', 'is_cash' => true]);
        $this->cashB = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1020', 'name' => 'Caja B', 'is_cash' => true]);
        $this->income = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '4000', 'name' => 'Ventas']);
        $this->diff = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '4900', 'name' => 'Diferencia en Arqueo']);

        $posting = app(PostingService::class);
        $posting->post(userId: $this->user->id, entryDate: '2026-05-01', legs: [
            ['account_id' => $this->cashA->id, 'debit' => 1000],
            ['account_id' => $this->income->id, 'credit' => 1000],
        ]);
        $posting->post(userId: $this->user->id, entryDate: '2026-05-01', legs: [
            ['account_id' => $this->cashB->id, 'debit' => 500],
            ['account_id' => $this->income->id, 'credit' => 500],
        ]);
    }

    private function configure(): void
    {
        $this->loginApi($this->user)
            ->putJson('/api/cash-count/settings', ['difference_account_id' => $this->diff->id])
            ->assertOk()
            ->assertJsonPath('difference_account.id', $this->diff->id);
    }

    public function test_preview_shows_book_balances(): void
    {
        $this->configure();

        $this->loginApi($this->user)
            ->getJson('/api/cash-count?date=2026-05-31')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('rows.0.book', '1000.0000')   // Caja A (code 1010)
            ->assertJsonPath('rows.1.book', '500.0000');   // Caja B (code 1020)
    }

    public function test_save_posts_a_balanced_adjusting_entry_and_moves_balances(): void
    {
        $this->configure();

        $res = $this->loginApi($this->user)->postJson('/api/cash-count', [
            'date' => '2026-05-31',
            'counts' => [
                ['account_id' => $this->cashA->id, 'counted' => 1200], // +200 overage
                ['account_id' => $this->cashB->id, 'counted' => 450],  // -50 shortage
            ],
        ])->assertStatus(201)
            ->assertJsonPath('posted', true)
            ->assertJsonPath('total_difference', '150.0000');

        $entryId = $res->json('entry_id');
        $this->assertNotNull($entryId);

        // Adjusting legs: debit Caja A 200, credit Caja B 50, credit diff 200, debit diff 50.
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entryId, 'account_id' => $this->cashA->id, 'debit' => '200.0000']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entryId, 'account_id' => $this->cashB->id, 'credit' => '50.0000']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entryId, 'account_id' => $this->diff->id, 'credit' => '200.0000']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entryId, 'account_id' => $this->diff->id, 'debit' => '50.0000']);

        // Balances now equal the counted amounts.
        $ledger = app(LedgerService::class);
        $this->assertSame('1200.0000', $ledger->balance($this->user->id, $this->cashA->id, '2026-05-31'));
        $this->assertSame('450.0000', $ledger->balance($this->user->id, $this->cashB->id, '2026-05-31'));

        $this->assertDatabaseHas('cash_counts', ['user_id' => $this->user->id, 'journal_entry_id' => $entryId, 'total_difference' => '150.0000']);
    }

    public function test_no_difference_records_count_without_posting(): void
    {
        $this->configure();

        $this->loginApi($this->user)->postJson('/api/cash-count', [
            'date' => '2026-05-31',
            'counts' => [
                ['account_id' => $this->cashA->id, 'counted' => 1000],
                ['account_id' => $this->cashB->id, 'counted' => 500],
            ],
        ])->assertStatus(200)
            ->assertJsonPath('posted', false)
            ->assertJsonPath('total_difference', '0.0000');

        $this->assertDatabaseCount('journal_entries', 2); // only the two setup entries
        $this->assertDatabaseHas('cash_counts', ['user_id' => $this->user->id, 'journal_entry_id' => null]);
    }

    public function test_save_requires_a_configured_difference_account(): void
    {
        $this->loginApi($this->user)->postJson('/api/cash-count', [
            'date' => '2026-05-31',
            'counts' => [['account_id' => $this->cashA->id, 'counted' => 1200]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('cash_counts', 0);
    }

    public function test_settings_rejects_a_foreign_account(): void
    {
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($this->user)
            ->putJson('/api/cash-count/settings', ['difference_account_id' => $foreign->id])
            ->assertStatus(422);
    }
}
