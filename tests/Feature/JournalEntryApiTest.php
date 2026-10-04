<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\EntryStatus;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class JournalEntryApiTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    private ChartOfAccount $bank;

    private ChartOfAccount $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->bank = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '102']);
        $this->sales = ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '400.1']);
    }

    public function test_it_posts_a_balanced_entry(): void
    {
        $this->loginApi($this->user)
            ->postJson('/api/entries', [
                'entry_date' => '2026-02-15',
                'description' => 'Venta',
                'legs' => [
                    ['account_id' => $this->bank->id, 'debit' => 500],
                    ['account_id' => $this->sales->id, 'credit' => 500],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_it_rejects_an_unbalanced_entry(): void
    {
        $this->loginApi($this->user)
            ->postJson('/api/entries', [
                'entry_date' => '2026-02-15',
                'legs' => [
                    ['account_id' => $this->bank->id, 'debit' => 500],
                    ['account_id' => $this->sales->id, 'credit' => 499],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('legs');
    }

    public function test_it_rejects_a_foreign_account(): void
    {
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Income)
            ->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($this->user)
            ->postJson('/api/entries', [
                'entry_date' => '2026-02-15',
                'legs' => [
                    ['account_id' => $this->bank->id, 'debit' => 100],
                    ['account_id' => $foreign->id, 'credit' => 100],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_it_voids_an_entry(): void
    {
        $post = $this->loginApi($this->user)->postJson('/api/entries', [
            'entry_date' => '2026-02-15',
            'legs' => [
                ['account_id' => $this->bank->id, 'debit' => 500],
                ['account_id' => $this->sales->id, 'credit' => 500],
            ],
        ])->json('data.id');

        $this->loginApi($this->user)->postJson("/api/entries/{$post}/void")->assertOk();

        $this->assertDatabaseHas('journal_entries', ['id' => $post, 'status' => EntryStatus::Void->value]);
        $this->assertDatabaseCount('journal_entries', 2); // original + reversing
    }

    public function test_index_is_paginated_and_scoped(): void
    {
        $this->loginApi($this->user)->postJson('/api/entries', [
            'entry_date' => '2026-02-15',
            'legs' => [
                ['account_id' => $this->bank->id, 'debit' => 10],
                ['account_id' => $this->sales->id, 'credit' => 10],
            ],
        ])->assertCreated();

        $this->loginApi($this->user)
            ->getJson('/api/entries?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data');
    }
}
