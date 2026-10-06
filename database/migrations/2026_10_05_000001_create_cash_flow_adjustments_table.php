<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEA — Flujo de Efectivo Ajustado.
 *
 * One saved planned (adjusted) net movement per cash account per month. Actuals
 * are always recomputed by LedgerService; only the user's plan lives here.
 * Replaces the old JSON blobs in storage/app/fea/{userId}/fea_{month}_{year}.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->decimal('planned_amount', 19, 4); // absolute planned NET movement (may be negative)
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period', 'account_id']);
            $table->index(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_adjustments');
    }
};
