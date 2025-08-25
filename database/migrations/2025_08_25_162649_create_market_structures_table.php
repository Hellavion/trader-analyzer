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
        Schema::create('market_structures', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // BTCUSDT, ETHUSDT
            $table->string('timeframe'); // 15m, 1h, 4h, 1d
            $table->timestamp('timestamp'); // время анализа
            $table->json('order_blocks'); // найденные Order Blocks
            $table->json('liquidity_levels'); // уровни ликвидности
            $table->json('fvg_zones'); // Fair Value Gap зоны
            $table->json('market_bias')->nullable(); // текущее направление рынка
            $table->decimal('high', 20, 8)->nullable(); // максимум периода
            $table->decimal('low', 20, 8)->nullable(); // минимум периода
            $table->timestamps();
            
            // Составной индекс для быстрого поиска
            $table->unique(['symbol', 'timeframe', 'timestamp']);
            $table->index(['symbol', 'timeframe']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_structures');
    }
};
