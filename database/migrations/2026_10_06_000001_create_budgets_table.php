<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budgets per income/expense account.
 *
 * `month` is 0 for the annual total (Phase 13) and 1–12 for a monthly figure
 * (Phase 14). Using 0 rather than NULL keeps the unique key usable — NULLs are
 * treated as distinct in unique indexes, which would break upserts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->default(0); // 0 = annual total, 1–12 = monthly
            $table->decimal('amount', 19, 4)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month', 'account_id']);
            $table->index(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
