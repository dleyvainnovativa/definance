<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $this->user->id, 'code' => '5000', 'name' => 'Gastos']);
        ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1180', 'name' => 'IVA acreditable']);
        ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $this->user->id, 'code' => '1020', 'name' => 'Bancos']);
        ChartOfAccount::factory()->ofType(AccountType::Income)->create(['user_id' => $this->user->id, 'code' => '4000', 'name' => 'Ventas']);
        ChartOfAccount::factory()->ofType(AccountType::Liability)->create(['user_id' => $this->user->id, 'code' => '2130', 'name' => 'IVA trasladado']);
    }

    /** @return array<int,array<string,mixed>> one valid 3-leg purchase entry */
    private function validGroup(string $group = '1', string $date = '2026-10-01'): array
    {
        return [
            ['group' => $group, 'entry_date' => $date, 'description' => 'Compra', 'account_code' => '5000', 'debit' => '116'],
            ['group' => $group, 'account_code' => '1180', 'debit' => '16'],
            ['group' => $group, 'account_code' => '1020', 'credit' => '132'],
        ];
    }

    public function test_preview_groups_and_validates_without_writing(): void
    {
        $rows = array_merge(
            $this->validGroup('1'),
            // unbalanced group
            [
                ['group' => '2', 'entry_date' => '2026-10-02', 'account_code' => '1020', 'debit' => '100'],
                ['group' => '2', 'account_code' => '4000', 'credit' => '90'],
            ],
            // unknown account code
            [
                ['group' => '3', 'entry_date' => '2026-10-03', 'account_code' => '9999', 'debit' => '50'],
                ['group' => '3', 'account_code' => '4000', 'credit' => '50'],
            ],
        );

        $this->loginApi($this->user)
            ->postJson('/api/entries/import/preview', ['rows' => $rows])
            ->assertOk()
            ->assertJsonPath('total_groups', 3)
            ->assertJsonPath('valid_count', 1)
            ->assertJsonPath('invalid_count', 2)
            ->assertJsonPath('can_import', true);

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_all_or_nothing_rejects_when_any_group_invalid(): void
    {
        $rows = array_merge($this->validGroup('1'), [
            ['group' => '2', 'entry_date' => '2026-10-02', 'account_code' => '1020', 'debit' => '100'],
            ['group' => '2', 'account_code' => '4000', 'credit' => '90'],
        ]);

        $this->loginApi($this->user)
            ->postJson('/api/entries/import', ['mode' => 'all_or_nothing', 'rows' => $rows])
            ->assertStatus(422)
            ->assertJsonPath('posted', 0);

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_all_or_nothing_posts_everything_when_all_valid(): void
    {
        $rows = array_merge($this->validGroup('1', '2026-10-01'), $this->validGroup('2', '2026-10-02'));

        $this->loginApi($this->user)
            ->postJson('/api/entries/import', ['mode' => 'all_or_nothing', 'rows' => $rows])
            ->assertStatus(201)
            ->assertJsonPath('posted', 2)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_entry_lines', 6);
    }

    public function test_skip_invalid_posts_valid_only(): void
    {
        $rows = array_merge($this->validGroup('1'), [
            ['group' => '2', 'entry_date' => '2026-10-02', 'account_code' => '1020', 'debit' => '100'],
            ['group' => '2', 'account_code' => '4000', 'credit' => '90'], // unbalanced
        ]);

        $this->loginApi($this->user)
            ->postJson('/api/entries/import', ['mode' => 'skip_invalid', 'rows' => $rows])
            ->assertStatus(201)
            ->assertJsonPath('posted', 1)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_entry_lines', 3);
    }

    public function test_foreign_account_code_is_unknown_to_this_user(): void
    {
        $other = User::factory()->create();
        ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $other->id, 'code' => '7777']);

        $rows = [
            ['group' => '1', 'entry_date' => '2026-10-01', 'account_code' => '7777', 'debit' => '10'],
            ['group' => '1', 'account_code' => '4000', 'credit' => '10'],
        ];

        $this->loginApi($this->user)
            ->postJson('/api/entries/import/preview', ['rows' => $rows])
            ->assertOk()
            ->assertJsonPath('valid_count', 0)
            ->assertJsonPath('invalid_count', 1);

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_posted_import_entries_balance_and_are_scoped(): void
    {
        $this->loginApi($this->user)
            ->postJson('/api/entries/import', ['mode' => 'all_or_nothing', 'rows' => $this->validGroup('1')])
            ->assertStatus(201);

        $this->assertDatabaseCount('journal_entry_lines', 3);
        $this->assertDatabaseHas('journal_entry_lines', ['user_id' => $this->user->id, 'debit' => '116.0000']);
        $this->assertDatabaseHas('journal_entry_lines', ['user_id' => $this->user->id, 'credit' => '132.0000']);
    }
}
