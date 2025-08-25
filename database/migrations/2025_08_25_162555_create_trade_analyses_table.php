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
        Schema::create('trade_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained()->onDelete('cascade');
            $table->decimal('smart_money_score', 3, 1); // от 1.0 до 10.0
            $table->json('entry_context'); // контекст входа в сделку
            $table->json('exit_context')->nullable(); // контекст выхода из сделки
            $table->json('patterns'); // найденные паттерны
            $table->json('order_blocks')->nullable(); // найденные OB
            $table->json('liquidity_zones')->nullable(); // зоны ликвидности
            $table->json('fvg_zones')->nullable(); // Fair Value Gaps
            $table->text('recommendations')->nullable(); // рекомендации
            $table->timestamps();
            
            // Индекс для быстрого поиска по оценкам
            $table->index('smart_money_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_analyses');
    }
};
