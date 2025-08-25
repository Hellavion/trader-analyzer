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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('exchange'); // bybit, mexc
            $table->string('symbol'); // BTCUSDT, ETHUSDT
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('size', 20, 8); // размер позиции
            $table->decimal('entry_price', 20, 8);
            $table->decimal('exit_price', 20, 8)->nullable();
            $table->timestamp('entry_time');
            $table->timestamp('exit_time')->nullable();
            $table->string('external_id')->nullable(); // ID сделки на бирже
            $table->decimal('pnl', 20, 8)->nullable(); // прибыль/убыток
            $table->decimal('fee', 20, 8)->nullable(); // комиссия
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            
            // Индексы для оптимизации
            $table->index(['user_id', 'symbol', 'entry_time']);
            $table->index(['exchange', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
