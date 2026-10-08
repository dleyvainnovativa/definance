<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de ejercicio (year-end close).
 *
 * `closing_settings` — one row per user: the equity account that receives each
 * year's result (default resolved to code 300.2 in the service).
 *
 * `period_closes` — one row per closed fiscal year: the posted closing entry,
 * the result folded, and when it was closed. A year present here is LOCKED:
 * PostingService rejects any entry dated on/before its Dec-31.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('period_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('result_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('net_result', 19, 4)->default(0); // income − expense for the year
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_closes');
        Schema::dropIfExists('closing_settings');
    }
};
