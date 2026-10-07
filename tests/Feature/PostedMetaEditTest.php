<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class PostedMetaEditTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    private ChartOfAccount $cash;

    private ChartOfAccount $income;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cash = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1000']);
        $this->income = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '4000']);
    }

    private function postedEntry(): int
    {
        return $this->loginApi($this->user)->postJson('/api/entries', [
            'entry_date' => '2026-05-10',
            'reference' => 'OLD-REF',
            'description' => 'Venta inicial',
            'legs' => [
                ['account_id' => $this->cash->id, 'debit' => 100],
                ['account_id' => $this->income->id, 'credit' => 100],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'posted')->json('data.id');
    }

    public function test_metadata_of_a_posted_entry_can_be_edited(): void
    {
        $id = $this->postedEntry();

        $this->loginApi($this->user)->patchJson("/api/entries/{$id}/meta", [
            'entry_date' => '2026-06-01',
            'reference' => 'NEW-REF',
            'description' => 'Fecha corregida',
        ])->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.entry_date', '2026-06-01')
            ->assertJsonPath('data.reference', 'NEW-REF')
            ->assertJsonPath('data.description', 'Fecha corregida');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $id, 'entry_date' => '2026-06-01', 'reference' => 'NEW-REF', 'status' => 'posted',
        ]);
    }

    public function test_editing_metadata_never_touches_the_amounts(): void
    {
        $id = $this->postedEntry();

        $this->loginApi($this->user)->patchJson("/api/entries/{$id}/meta", [
            'entry_date' => '2026-06-01',
        ])->assertOk()
            ->assertJsonPath('data.totals.debit', '100.0000')
            ->assertJsonPath('data.totals.credit', '100.0000')
            ->assertJsonCount(2, 'data.lines');

        // exactly the two original legs, unchanged
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_the_date_move_does_not_create_a_descuadre(): void
    {
        $id = $this->postedEntry(); // 2026-05-10

        // Move it into June, then the income statement for June shows it and May does not.
        $this->loginApi($this->user)->patchJson("/api/entries/{$id}/meta", ['entry_date' => '2026-06-15'])->assertOk();

        $this->loginApi($this->user)->getJson('/api/reports/income-statement?from=2026-05-01&to=2026-05-31')
            ->assertOk()->assertJsonPath('totals.revenue', '0.0000');
        $this->loginApi($this->user)->getJson('/api/reports/income-statement?from=2026-06-01&to=2026-06-30')
            ->assertOk()->assertJsonPath('totals.revenue', '100.0000');
    }

    public function test_a_draft_cannot_use_the_meta_edit(): void
    {
        $id = $this->loginApi($this->user)->postJson('/api/entries', [
            'entry_date' => '2026-05-10',
            'status' => 'draft',
            'legs' => [
                ['account_id' => $this->cash->id, 'debit' => 100],
                ['account_id' => $this->income->id, 'credit' => 100],
            ],
        ])->assertCreated()->json('data.id');

        $this->loginApi($this->user)->patchJson("/api/entries/{$id}/meta", ['entry_date' => '2026-06-01'])
            ->assertForbidden();
    }

    public function test_entry_date_is_required(): void
    {
        $id = $this->postedEntry();

        $this->loginApi($this->user)->patchJson("/api/entries/{$id}/meta", ['description' => 'sin fecha'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('entry_date');
    }

    public function test_another_users_posted_entry_is_not_found(): void
    {
        $id = $this->postedEntry();
        $other = User::factory()->create();

        $this->loginApi($other)->patchJson("/api/entries/{$id}/meta", ['entry_date' => '2026-06-01'])
            ->assertNotFound();
    }
}
