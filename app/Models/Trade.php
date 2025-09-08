<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Модель торговой сделки
 * 
 * @property int $id
 * @property int $user_id
 * @property string $exchange
 * @property string $symbol
 * @property string $side
 * @property float $size
 * @property float $entry_price
 * @property float $exit_price
 * @property \Carbon\Carbon $entry_time
 * @property \Carbon\Carbon $exit_time
 * @property string $external_id
 * @property float $pnl
 * @property float $unrealized_pnl
 * @property float $fee
 * @property string $status
 */
class Trade extends Model
{
    protected $fillable = [
        'user_id',
        'exchange',
        'symbol',
        'side',
        'size',
        'entry_price',
        'exit_price',
        'entry_time',
        'exit_time',
        'external_id',
        'pnl',
        'unrealized_pnl',
        'fee',
        'funding_fees',
        'status',
    ];

    protected $casts = [
        'size' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'pnl' => 'decimal:8',
        'unrealized_pnl' => 'decimal:8',
        'fee' => 'decimal:8',
        'funding_fees' => 'decimal:8',
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
    ];

    /**
     * Пользователь, которому принадлежит сделка
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Анализ сделки
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(TradeAnalysis::class);
    }

    /**
     * Проверяет, является ли сделка закрытой
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Проверяет, является ли сделка открытой
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

}
