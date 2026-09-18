<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('daily_transactions')->where('type', 'daily')->delete();
        DB::table('account_plans')->where('type', 'daily')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * Purging the legacy Diário data is irreversible: the deleted transactions
     * and plans cannot be restored.
     */
    public function down(): void
    {
        //
    }
};
