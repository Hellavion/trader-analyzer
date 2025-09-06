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
        Schema::table('trades', function (Blueprint $table) {
            $table->string('order_id')->nullable()->after('external_id');
            $table->integer('sequence')->nullable()->after('order_id');
            $table->string('order_link_id')->nullable()->after('sequence');
            
            $table->index(['exchange', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['exchange', 'order_id']);
            $table->dropColumn(['order_id', 'sequence', 'order_link_id']);
        });
    }
};
