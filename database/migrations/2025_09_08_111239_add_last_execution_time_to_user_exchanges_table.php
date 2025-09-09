<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->timestamp('last_execution_time')->nullable()->after('last_sync_at')
                ->comment('Время последнего execution полученного от биржи');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->dropColumn('last_execution_time');
        });
    }
};
