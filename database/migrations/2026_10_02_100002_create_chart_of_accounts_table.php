<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('chart_of_accounts')->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            // Derived from type, stored for query speed and report integrity.
            $table->enum('normal_balance', ['debit', 'credit']);

            // Only postable (leaf) accounts can receive journal lines.
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            // Permission flags carried from the old system.
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_deletable')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'parent_id']);
            $table->index(['user_id', 'is_postable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
