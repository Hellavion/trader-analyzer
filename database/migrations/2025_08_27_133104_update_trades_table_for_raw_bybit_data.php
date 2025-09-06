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
            $table->string('exec_type')->nullable()->after('order_link_id');
            $table->decimal('exec_qty', 20, 8)->nullable()->after('exec_type');
            $table->decimal('exec_price', 20, 8)->nullable()->after('exec_qty');
            $table->decimal('exec_value', 20, 8)->nullable()->after('exec_price');
            $table->decimal('exec_fee', 20, 8)->nullable()->after('exec_value');
            $table->string('fee_currency')->nullable()->after('exec_fee');
            $table->timestamp('exec_time')->nullable()->after('fee_currency');
            $table->boolean('is_maker')->default(false)->after('exec_time');
            $table->json('raw_data')->nullable()->after('is_maker');
            
            $table->index(['order_id', 'sequence']);
            $table->index(['exec_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'sequence']);
            $table->dropIndex(['exec_time']);
            $table->dropColumn([
                'exec_type', 'exec_qty', 'exec_price', 'exec_value', 
                'exec_fee', 'fee_currency', 'exec_time', 'is_maker', 'raw_data'
            ]);
        });
    }
};
