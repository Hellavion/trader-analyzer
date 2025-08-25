<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель анализа торговой сделки по Smart Money концепциям
 * 
 * @property int $id
 * @property int $trade_id
 * @property float $smart_money_score
 * @property array $entry_context
 * @property array $exit_context
 * @property array $patterns
 * @property array $order_blocks
 * @property array $liquidity_zones
 * @property array $fvg_zones
 * @property string $recommendations
 */
class TradeAnalysis extends Model
{
    protected $fillable = [
        'trade_id',
        'smart_money_score',
        'entry_context',
        'exit_context',
        'patterns',
        'order_blocks',
        'liquidity_zones',
        'fvg_zones',
        'recommendations',
    ];

    protected $casts = [
        'smart_money_score' => 'decimal:1',
        'entry_context' => 'array',
        'exit_context' => 'array',
        'patterns' => 'array',
        'order_blocks' => 'array',
        'liquidity_zones' => 'array',
        'fvg_zones' => 'array',
    ];

    /**
     * Сделка, к которой относится анализ
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * Проверяет, является ли анализ положительным (оценка >= 7)
     */
    public function isPositive(): bool
    {
        return $this->smart_money_score >= 7.0;
    }

    /**
     * Проверяет, является ли анализ отрицательным (оценка <= 3)
     */
    public function isNegative(): bool
    {
        return $this->smart_money_score <= 3.0;
    }

    /**
     * Возвращает качество сделки текстом
     */
    public function getQualityLabelAttribute(): string
    {
        return match(true) {
            $this->smart_money_score >= 8.0 => 'Отличная',
            $this->smart_money_score >= 7.0 => 'Хорошая',
            $this->smart_money_score >= 5.0 => 'Средняя',
            $this->smart_money_score >= 3.0 => 'Слабая',
            default => 'Плохая'
        };
    }

    /**
     * Проверяет наличие определенного паттерна
     */
    public function hasPattern(string $pattern): bool
    {
        return in_array($pattern, $this->patterns ?? []);
    }

    /**
     * Количество найденных Order Blocks
     */
    public function getOrderBlocksCountAttribute(): int
    {
        return count($this->order_blocks ?? []);
    }
}
