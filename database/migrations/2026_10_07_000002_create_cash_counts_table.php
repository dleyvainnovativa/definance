<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit history of each cash count (decision R6). The adjusting entry, if any,
 * is posted through PostingService and linked here; `breakdown` keeps the
 * per-account book / counted / difference snapshot for the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('count_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('total_difference', 19, 4)->default(0);
            $table->json('breakdown');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'count_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
