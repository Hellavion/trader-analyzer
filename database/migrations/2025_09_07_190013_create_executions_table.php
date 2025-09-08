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
        Schema::create('executions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('exchange', 50); // bybit, mexc
            $table->string('execution_id', 100); // execId from API
            $table->string('order_id', 100); // orderId from API
            $table->string('symbol', 50); // BTCUSDT, etc
            $table->enum('side', ['buy', 'sell']); // Buy, Sell from API -> lowercase
            $table->decimal('quantity', 20, 8); // execQty from API
            $table->decimal('price', 20, 8); // execPrice from API 
            $table->decimal('closed_size', 20, 8)->default(0); // closedSize from API - amount that closed position 
            $table->decimal('fee', 20, 8)->default(0); // execFee from API
            $table->string('fee_currency', 20)->nullable(); // feeCurrency from API
            $table->timestamp('execution_time'); // execTime from API (converted from ms)
            $table->json('raw_data'); // Full API response for debugging
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'symbol', 'execution_time']);
            $table->index(['user_id', 'order_id']);
            $table->unique(['execution_id', 'exchange']); // Prevent duplicates
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
