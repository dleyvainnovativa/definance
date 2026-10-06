<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user arqueo (cash count) settings: the single "Diferencia en Arqueo"
 * account (decision R5) and, optionally, which accounts are counted
 * (null = all is_cash accounts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_count_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('difference_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->json('counted_account_ids')->nullable(); // null = all is_cash
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_count_settings');
    }
};
