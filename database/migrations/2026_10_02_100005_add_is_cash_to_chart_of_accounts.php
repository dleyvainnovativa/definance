<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags accounts that represent cash/equivalents (Caja, Bancos) so the cash
 * flow report knows which accounts to track, without hard-coding codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false)->after('is_active');
            $table->index(['user_id', 'is_cash']);
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_cash']);
            $table->dropColumn('is_cash');
        });
    }
};
