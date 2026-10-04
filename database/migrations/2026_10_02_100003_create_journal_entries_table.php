<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('entry_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            // Lifecycle. No entry_type — meaning comes from the legs.
            $table->enum('status', ['draft', 'posted', 'void'])->default('posted');
            $table->timestamp('posted_at')->nullable();

            // A void entry links to the reversing entry that cancels it.
            $table->foreignId('reversed_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
