<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Execution extends Model
{
    protected $fillable = [
        'user_id',
        'exchange',
        'execution_id',
        'order_id',
        'symbol',
        'side',
        'quantity',
        'price',
        'closed_size',
        'exec_type',
        'fee',
        'fee_currency',
        'execution_time',
        'raw_data',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'price' => 'decimal:8',
        'closed_size' => 'decimal:8',
        'fee' => 'decimal:8',
        'execution_time' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Создать исполнение из данных Bybit API
     */
    public static function createFromBybitExecution(int $userId, array $executionData): self
    {
        return self::create([
            'user_id' => $userId,
            'exchange' => 'bybit',
            'execution_id' => $executionData['execId'],
            'order_id' => $executionData['orderId'],
            'symbol' => $executionData['symbol'],
            'side' => strtolower($executionData['side']),
            'quantity' => (float) $executionData['execQty'],
            'price' => (float) $executionData['execPrice'],
            'closed_size' => (float) ($executionData['closedSize'] ?? 0),
            'exec_type' => $executionData['execType'] ?? 'Trade',
            'fee' => (float) ($executionData['execFee'] ?? 0),
            'fee_currency' => $executionData['feeCurrency'] ?? null,
            'execution_time' => Carbon::createFromTimestampMs((int) $executionData['execTime']),
            'raw_data' => $executionData,
        ]);
    }

    /**
     * Получить все исполнения для конкретного ордера
     */
    public static function getExecutionsForOrder(int $userId, string $orderId, string $exchange = 'bybit')
    {
        return self::where('user_id', $userId)
            ->where('order_id', $orderId)
            ->where('exchange', $exchange)
            ->orderBy('execution_time')
            ->get();
    }
}
