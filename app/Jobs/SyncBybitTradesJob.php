<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Trade;
use App\Models\Execution;
use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Job для синхронизации сделок с Bybit
 */
class SyncBybitTradesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    private UserExchange $userExchange;
    private ?Carbon $startTime;
    private ?Carbon $endTime;

    public function __construct(UserExchange $userExchange, ?Carbon $startTime = null, ?Carbon $endTime = null)
    {
        $this->userExchange = $userExchange;
        $this->startTime = $startTime;
        $this->endTime = $endTime ?? now();
    }

    public function handle(): void
    {
        Log::info('Starting Bybit trades sync', [
            'user_id' => $this->userExchange->user_id,
            'exchange_id' => $this->userExchange->id,
            'start_time' => $this->startTime?->toISOString(),
            'end_time' => $this->endTime?->toISOString(),
        ]);

        try {
            if (!$this->userExchange->isActive()) {
                Log::warning('Bybit exchange connection is not active');
                return;
            }

            $credentials = $this->userExchange->getApiCredentials();
            $bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

            // Получаем исполненные сделки из linear (фьючерсы)
            $executions = $bybitService->getExecutions(
                category: 'linear',
                startTime: $this->startTime,
                endTime: $this->endTime,
                limit: 50
            );

            // Сортируем executions по времени для правильного порядка обработки
            usort($executions, function($a, $b) {
                return $a['execTime'] <=> $b['execTime'];
            });

            $synced = 0;
            $executionsSaved = 0;
            $tradesAggregated = 0;
            
            foreach ($executions as $execution) {
                // 1. Сначала сохраняем raw execution (если его еще нет)
                $executionExists = Execution::where('execution_id', $execution['execId'])
                    ->where('exchange', 'bybit')
                    ->exists();

                if (!$executionExists) {
                    $savedExecution = Execution::createFromBybitExecution(
                        $this->userExchange->user_id, 
                        $execution
                    );
                    $executionsSaved++;
                    Log::debug('Saved execution', [
                        'execution_id' => $execution['execId'],
                        'time' => $savedExecution->execution_time->format('H:i:s'),
                        'symbol' => $execution['symbol'],
                        'side' => $execution['side'],
                        'closed_size' => $execution['closedSize'] ?? 0,
                        'exec_type' => $execution['execType'] ?? 'Trade',
                    ]);
                    
                    // 2. После сохранения execution - обрабатываем в зависимости от типа
                    if ($savedExecution->exec_type === 'Funding') {
                        $this->handleFundingPayment($savedExecution);
                    } else {
                        $this->aggregateTradeFromExecution($savedExecution);
                        $tradesAggregated++;
                    }
                }
                $synced++;
            }
            
            Log::info('Bybit synchronization completed', [
                'user_id' => $this->userExchange->user_id,
                'total_processed' => $synced,
                'executions_saved' => $executionsSaved,
                'trades_aggregated' => $tradesAggregated,
            ]);

        } catch (\Exception $e) {
            Log::error('Bybit trades sync failed', [
                'user_id' => $this->userExchange->user_id,
                'exchange_id' => $this->userExchange->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Если это критическая ошибка API ключей, деактивируем подключение
            if ($this->isCriticalApiError($e)) {
                $this->userExchange->deactivate();
                Log::warning('Deactivated Bybit connection due to critical API error', [
                    'user_id' => $this->userExchange->user_id,
                    'exchange_id' => $this->userExchange->id,
                ]);
            }

            throw $e;
        }
    }




    /**
     * Проверяет, является ли ошибка критической для API ключей
     */
    private function isCriticalApiError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        $criticalErrors = [
            'invalid api key',
            'signature verification failed',
            'api key expired',
            'insufficient permissions',
            'authentication failed',
        ];

        foreach ($criticalErrors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Обработка неудачного выполнения job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBybitTradesJob failed permanently', [
            'user_id' => $this->userExchange->user_id,
            'exchange_id' => $this->userExchange->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }



    /**
     * Определяет задержку между повторными попытками
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 сек, 1 мин, 2 мин
    }

    /**
     * Агрегирует trade из execution на основе closedSize
     */
    private function aggregateTradeFromExecution(Execution $execution): void
    {
        $closedSize = (float) $execution->closed_size;
        
        if ($closedSize > 0) {
            // Это закрытие позиции - ищем открытую сделку для обновления
            $this->handlePositionClose($execution, $closedSize);
        } else {
            // Это открытие позиции - создаем новую сделку или дополняем существующую открытую
            $this->handlePositionOpen($execution);
        }
    }

    /**
     * Обрабатывает закрытие позиции
     */
    private function handlePositionClose(Execution $execution, float $closedSize): void
    {
        // Ищем открытую сделку по символу и противоположной стороне
        $oppositeSide = $execution->side === 'buy' ? 'sell' : 'buy';
        
        $openTrade = Trade::where('user_id', $execution->user_id)
            ->where('symbol', $execution->symbol)
            ->where('side', $oppositeSide)
            ->where('status', 'open')
            ->orderBy('entry_time', 'asc') // FIFO - первая открытая позиция
            ->first();

        if ($openTrade) {
            // Обновляем открытую сделку данными о закрытии
            $openTrade->update([
                'exit_price' => $execution->price,
                'exit_time' => $execution->execution_time,
                'status' => 'closed',
                'pnl' => $this->calculatePnL($openTrade, $execution),
            ]);
            
            Log::debug('Closed position', [
                'trade_id' => $openTrade->id,
                'symbol' => $execution->symbol,
                'closed_size' => $closedSize,
                'exit_price' => $execution->price,
            ]);
        } else {
            Log::warning('No open trade found to close', [
                'symbol' => $execution->symbol,
                'side' => $execution->side,
                'closed_size' => $closedSize,
            ]);
        }
    }

    /**
     * Обрабатывает открытие позиции
     */
    private function handlePositionOpen(Execution $execution): void
    {
        // Ищем существующую незакрытую сделку с тем же символом и стороной для агрегации
        $existingTrade = Trade::where('user_id', $execution->user_id)
            ->where('symbol', $execution->symbol)
            ->where('side', $execution->side)
            ->where('status', 'open')
            ->orderBy('entry_time', 'desc') // Последняя открытая позиция
            ->first();

        // Проверяем временной интервал для агрегации (в пределах 5 минут)
        $canAggregate = $existingTrade && 
            $existingTrade->entry_time->diffInMinutes($execution->execution_time) <= 5;

        if ($canAggregate) {
            // Агрегируем в существующую сделку
            $totalSize = $existingTrade->size + $execution->quantity;
            $totalValue = ($existingTrade->size * $existingTrade->entry_price) + 
                         ($execution->quantity * $execution->price);
            $avgPrice = $totalValue / $totalSize;

            $existingTrade->update([
                'size' => $totalSize,
                'entry_price' => $avgPrice,
                'fee' => $existingTrade->fee + $execution->fee,
            ]);

            Log::debug('Aggregated into existing trade', [
                'trade_id' => $existingTrade->id,
                'new_size' => $totalSize,
                'new_price' => $avgPrice,
            ]);
        } else {
            // Создаем новую сделку
            $newTrade = Trade::create([
                'user_id' => $execution->user_id,
                'exchange' => $execution->exchange,
                'external_id' => $execution->execution_id,
                'order_id' => $execution->order_id,
                'symbol' => $execution->symbol,
                'side' => $execution->side,
                'size' => $execution->quantity,
                'entry_price' => $execution->price,
                'entry_time' => $execution->execution_time,
                'fee' => $execution->fee,
                'status' => 'open',
                'raw_data' => json_encode([
                    'created_from_execution' => $execution->execution_id,
                    'closed_size' => 0,
                ]),
            ]);

            Log::debug('Created new trade', [
                'trade_id' => $newTrade->id,
                'symbol' => $execution->symbol,
                'side' => $execution->side,
                'size' => $execution->quantity,
            ]);
        }
    }

    /**
     * Рассчитывает PnL для закрытой позиции
     */
    private function calculatePnL(Trade $openTrade, Execution $closeExecution): float
    {
        $entryValue = $openTrade->size * $openTrade->entry_price;
        $exitValue = $openTrade->size * $closeExecution->price;
        
        // Для long позиций (buy) PnL = exit_value - entry_value
        // Для short позиций (sell) PnL = entry_value - exit_value
        if ($openTrade->side === 'buy') {
            return $exitValue - $entryValue;
        } else {
            return $entryValue - $exitValue;
        }
    }

    /**
     * Обрабатывает фандинговый платеж
     */
    private function handleFundingPayment(Execution $execution): void
    {
        // Ищем открытую сделку по символу (фандинг применяется к открытым позициям)
        $openTrade = Trade::where('user_id', $execution->user_id)
            ->where('symbol', $execution->symbol)
            ->where('status', 'open')
            ->first();

        if ($openTrade) {
            // Добавляем фандинговый платеж к существующей сделке
            $currentFundingFees = (float) $openTrade->funding_fees;
            $newFundingFee = (float) $execution->fee; // В случае фандинга fee содержит сумму платежа
            
            $openTrade->update([
                'funding_fees' => $currentFundingFees + $newFundingFee,
            ]);

            Log::debug('Added funding fee to trade', [
                'trade_id' => $openTrade->id,
                'symbol' => $execution->symbol,
                'funding_fee' => $newFundingFee,
                'total_funding_fees' => $currentFundingFees + $newFundingFee,
            ]);
        } else {
            Log::warning('No open trade found for funding payment', [
                'symbol' => $execution->symbol,
                'funding_fee' => $execution->fee,
                'execution_time' => $execution->execution_time->format('H:i:s'),
            ]);
        }
    }

}