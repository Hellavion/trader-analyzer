<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Модель структуры рынка для определенного символа и таймфрейма
 * 
 * @property int $id
 * @property string $symbol
 * @property string $timeframe
 * @property Carbon $timestamp
 * @property array $order_blocks
 * @property array $liquidity_levels
 * @property array $fvg_zones
 * @property array $market_bias
 * @property float $high
 * @property float $low
 */
class MarketStructure extends Model
{
    protected $fillable = [
        'symbol',
        'timeframe',
        'timestamp',
        'order_blocks',
        'liquidity_levels',
        'fvg_zones',
        'market_bias',
        'high',
        'low',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'order_blocks' => 'array',
        'liquidity_levels' => 'array',
        'fvg_zones' => 'array',
        'market_bias' => 'array',
        'high' => 'decimal:8',
        'low' => 'decimal:8',
    ];

    /**
     * Получает последнюю структуру рынка для символа и таймфрейма
     */
    public static function getLatest(string $symbol, string $timeframe): ?self
    {
        return self::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->orderBy('timestamp', 'desc')
            ->first();
    }

    /**
     * Получает структуру рынка на определенное время
     */
    public static function getAt(string $symbol, string $timeframe, Carbon $timestamp): ?self
    {
        return self::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->where('timestamp', '<=', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();
    }

    /**
     * Количество активных Order Blocks
     */
    public function getActiveOrderBlocksCountAttribute(): int
    {
        $blocks = $this->order_blocks ?? [];
        return count(array_filter($blocks, fn($block) => $block['is_active'] ?? true));
    }

    /**
     * Количество зон ликвидности
     */
    public function getLiquidityZonesCountAttribute(): int
    {
        return count($this->liquidity_levels ?? []);
    }

    /**
     * Количество незаполненных FVG
     */
    public function getUnfilledFvgCountAttribute(): int
    {
        $fvgs = $this->fvg_zones ?? [];
        return count(array_filter($fvgs, fn($fvg) => !($fvg['is_filled'] ?? false)));
    }

    /**
     * Определяет текущий биас рынка
     */
    public function getCurrentBias(): string
    {
        $bias = $this->market_bias;
        return $bias['direction'] ?? 'neutral';
    }

    /**
     * Проверяет, является ли рынок бычьим
     */
    public function isBullish(): bool
    {
        return $this->getCurrentBias() === 'bullish';
    }

    /**
     * Проверяет, является ли рынок медвежьим
     */
    public function isBearish(): bool
    {
        return $this->getCurrentBias() === 'bearish';
    }
}
