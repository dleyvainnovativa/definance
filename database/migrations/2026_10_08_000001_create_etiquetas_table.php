<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas — cross-cutting labels tagged onto accounts. A label rolls up every
 * movement through its tagged accounts (see LabelReportService). Many-to-many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('account_etiqueta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['etiqueta_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_etiqueta');
        Schema::dropIfExists('etiquetas');
    }
};
