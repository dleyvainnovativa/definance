<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateLegacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $this->buildLegacySchema();
        $this->seedLegacy();
    }

    private function legacy()
    {
        return DB::connection('legacy_test');
    }

    private function buildLegacySchema(): void
    {
        $s = Schema::connection('legacy_test');
        $s->create('users', function ($t) {
            $t->integer('id')->primary();
            $t->string('firebase_uid');
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->timestamps();
        });
        $s->create('chart_of_accounts', function ($t) {
            $t->integer('id')->primary();
            $t->integer('user_id');
            $t->integer('parent_id')->nullable();
            $t->string('code');
            $t->string('name');
            $t->string('type');
            $t->boolean('is_editable')->default(false);
            $t->boolean('is_deletable')->default(false);
            $t->boolean('allows_children')->default(false);
            $t->timestamps();
        });
        $s->create('journal_entries', function ($t) {
            $t->integer('id')->primary();
            $t->integer('user_id');
            $t->string('entry_type');
            $t->date('entry_date');
            $t->string('reference')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });
        $s->create('journal_entry_lines', function ($t) {
            $t->integer('id')->primary();
            $t->integer('journal_entry_id');
            $t->integer('chart_of_account_id');
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->timestamps();
        });
    }

    private function seedLegacy(): void
    {
        $now = '2026-02-01 00:00:00';
        $this->legacy()->table('users')->insert([
            ['id' => 1, 'firebase_uid' => 'uid-1', 'name' => 'Ana', 'email' => 'ana@x.mx', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->legacy()->table('chart_of_accounts')->insert([
            ['id' => 1, 'user_id' => 1, 'parent_id' => null, 'code' => '100', 'name' => 'ACTIVOS', 'type' => 'asset', 'is_editable' => 0, 'is_deletable' => 0, 'allows_children' => 0],
            ['id' => 11, 'user_id' => 1, 'parent_id' => 1, 'code' => '100.1', 'name' => 'EFECTIVO', 'type' => 'asset', 'is_editable' => 0, 'is_deletable' => 0, 'allows_children' => 1],
            ['id' => 31, 'user_id' => 1, 'parent_id' => 11, 'code' => '100.1.1', 'name' => 'CARTERA', 'type' => 'asset', 'is_editable' => 1, 'is_deletable' => 0, 'allows_children' => 0],
            ['id' => 4, 'user_id' => 1, 'parent_id' => null, 'code' => '300', 'name' => 'PATRIMONIO', 'type' => 'equity', 'is_editable' => 0, 'is_deletable' => 0, 'allows_children' => 0],
            ['id' => 5, 'user_id' => 1, 'parent_id' => null, 'code' => '400', 'name' => 'INGRESOS', 'type' => 'income', 'is_editable' => 0, 'is_deletable' => 0, 'allows_children' => 1],
            ['id' => 51, 'user_id' => 1, 'parent_id' => 5, 'code' => '400.1', 'name' => 'VENTAS', 'type' => 'income', 'is_editable' => 1, 'is_deletable' => 0, 'allows_children' => 0],
        ]);
        $this->legacy()->table('journal_entries')->insert([
            ['id' => 100, 'user_id' => 1, 'entry_type' => 'income', 'entry_date' => '2026-02-10', 'reference' => 'Ref', 'description' => 'Venta', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 101, 'user_id' => 1, 'entry_type' => 'opening_balance', 'entry_date' => '2026-03-01', 'reference' => 'automatic', 'description' => 'Saldo Inicial Update', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 102, 'user_id' => 1, 'entry_type' => 'opening_balance', 'entry_date' => '2026-01-01', 'reference' => null, 'description' => 'SALDO INICIAL', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 103, 'user_id' => 1, 'entry_type' => 'income', 'entry_date' => '2026-02-11', 'reference' => null, 'description' => 'vacia', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->legacy()->table('journal_entry_lines')->insert([
            ['id' => 1, 'journal_entry_id' => 100, 'chart_of_account_id' => 31, 'debit' => 500, 'credit' => null],
            ['id' => 2, 'journal_entry_id' => 100, 'chart_of_account_id' => 51, 'debit' => null, 'credit' => 500],
            ['id' => 3, 'journal_entry_id' => 101, 'chart_of_account_id' => 31, 'debit' => 999, 'credit' => null], // auto -> drop
            ['id' => 4, 'journal_entry_id' => 102, 'chart_of_account_id' => 31, 'debit' => 1000, 'credit' => null], // genuine opening
            ['id' => 5, 'journal_entry_id' => 103, 'chart_of_account_id' => 31, 'debit' => 0, 'credit' => null], // zero -> skip
            ['id' => 6, 'journal_entry_id' => 103, 'chart_of_account_id' => 51, 'debit' => null, 'credit' => 0],
        ]);
    }

    public function test_it_migrates_accounts_transactions_and_rebalances_openings(): void
    {
        $this->artisan('definance:migrate-legacy', ['--connection' => 'legacy_test'])->assertSuccessful();

        $user = User::where('firebase_uid', 'uid-1')->firstOrFail();

        // 6 accounts + Apertura.
        $this->assertSame(7, ChartOfAccount::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('chart_of_accounts', ['user_id' => $user->id, 'code' => '399']);

        // Leaf / group / cash detection.
        $cartera = ChartOfAccount::where('user_id', $user->id)->where('code', '100.1.1')->first();
        $efectivo = ChartOfAccount::where('user_id', $user->id)->where('code', '100.1')->first();
        $this->assertTrue($cartera->is_postable);
        $this->assertTrue($cartera->is_cash);
        $this->assertFalse($efectivo->is_postable);

        // 1 transaction + 1 converted opening; auto + zero dropped.
        $this->assertSame(2, JournalEntry::where('user_id', $user->id)->count());
        $this->assertSame(4, JournalEntryLine::where('user_id', $user->id)->count());

        // The whole migrated ledger balances.
        $sums = DB::table('journal_entry_lines')->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
        $this->assertEquals($sums->d, $sums->c);
        $this->assertEquals(1500, (float) $sums->d);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('definance:migrate-legacy', ['--connection' => 'legacy_test', '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('chart_of_accounts', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
