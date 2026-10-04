<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            // Denormalized owner for tenant scope + report indexes.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')->restrictOnDelete();

            $table->decimal('debit', 19, 4)->nullable();
            $table->decimal('credit', 19, 4)->nullable();
            $table->string('line_description')->nullable();

            // Reserved for IVA / SAT (Phase 8). Null in v1.
            $table->string('tax_code')->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->decimal('tax_base', 19, 4)->nullable();

            $table->timestamps();

            $table->index('account_id');
            $table->index('journal_entry_id');
            $table->index(['user_id', 'account_id']);
        });

        // Line-level integrity: a line is purely debit OR purely credit, > 0.
        // MySQL/MariaDB/Postgres enforce this at the DB. (SQLite used in CI is
        // covered by the model-level guard in JournalEntryLine.)
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement('
                ALTER TABLE journal_entry_lines
                ADD CONSTRAINT chk_line_single_side CHECK (
                    (debit IS NOT NULL AND credit IS NULL AND debit > 0)
                    OR
                    (credit IS NOT NULL AND debit IS NULL AND credit > 0)
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
