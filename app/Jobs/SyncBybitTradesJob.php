<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Trade;
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
                Log::warning('Bybit exchange connection is not active', [
                    'user_id' => $this->userExchange->user_id,
                    'exchange_id' => $this->userExchange->id,
                ]);
                return;
            }

            $credentials = $this->userExchange->getApiCredentials();
            $bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

            // Синхронизируем исполнения сделок для разных категорий
            $this->syncTradesForCategory($bybitService, 'linear');
            $this->syncTradesForCategory($bybitService, 'spot');
            $this->syncTradesForCategory($bybitService, 'inverse');

            // Синхронизируем закрытые позиции для более точного анализа
            $this->syncClosedPositions($bybitService, 'linear');
            $this->syncClosedPositions($bybitService, 'spot');
            $this->syncClosedPositions($bybitService, 'inverse');

            // Синхронизируем открытые позиции
            $this->syncOpenPositions($bybitService, 'linear');
            $this->syncOpenPositions($bybitService, 'spot');
            $this->syncOpenPositions($bybitService, 'inverse');

            // Запускаем сбор рыночных данных для новых символов
            $this->triggerMarketDataCollection($bybitService);

            // Обновляем время последней синхронизации
            $this->userExchange->updateLastSync();

            Log::info('Bybit trades sync completed successfully', [
                'user_id' => $this->userExchange->user_id,
                'exchange_id' => $this->userExchange->id,
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
     * Синхронизирует сделки для определенной категории
     */
    private function syncTradesForCategory(BybitService $bybitService, string $category): void
    {
        $cursor = null;
        $totalSynced = 0;

        do {
            $trades = $bybitService->getTradingHistory(
                category: $category,
                startTime: $this->startTime,
                endTime: $this->endTime,
                limit: 50
            );

            if (empty($trades)) {
                break;
            }

            foreach ($trades as $bybitTrade) {
                // Пропускаем записи о фандинге и других не-торговых операциях
                if (($bybitTrade['execType'] ?? '') !== 'Trade') {
                    continue;
                }
                
                if ($this->syncSingleTrade($bybitTrade)) {
                    $totalSynced++;
                }
            }

            // Делаем паузу между запросами для соблюдения rate limit
            usleep(100000); // 100ms

        } while (!empty($trades) && count($trades) >= 50);

        Log::info("Synced trades for category {$category}", [
            'user_id' => $this->userExchange->user_id,
            'category' => $category,
            'total_synced' => $totalSynced,
        ]);
    }

    /**
     * Синхронизирует одну сделку
     */
    private function syncSingleTrade(array $bybitTrade): bool
    {
        $externalId = $bybitTrade['execId'];

        // Проверяем, не существует ли уже такая сделка
        $existingTrade = Trade::where('user_id', $this->userExchange->user_id)
            ->where('exchange', 'bybit')
            ->where('external_id', $externalId)
            ->first();

        if ($existingTrade) {
            return false; // Сделка уже существует
        }

        // Группируем исполнения по orderId для формирования полных сделок
        $orderId = $bybitTrade['orderId'];
        
        // Проверяем, есть ли уже сделка с этим orderId
        $existingOrderTrade = Trade::where('user_id', $this->userExchange->user_id)
            ->where('exchange', 'bybit')
            ->where('external_id', 'LIKE', $orderId . '%')
            ->first();

        if ($existingOrderTrade) {
            // Обновляем существующую сделку (добавляем к размеру и пересчитываем среднюю цену)
            $this->updateExistingTrade($existingOrderTrade, $bybitTrade);
            return true;
        }

        // Создаем новую сделку
        $tradeData = (new BybitService())->transformTradeData($bybitTrade);
        $tradeData['user_id'] = $this->userExchange->user_id;

        try {
            Trade::create($tradeData);
            
            Log::debug('Created new trade from Bybit', [
                'user_id' => $this->userExchange->user_id,
                'external_id' => $externalId,
                'symbol' => $tradeData['symbol'],
                'size' => $tradeData['size'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create trade from Bybit data', [
                'user_id' => $this->userExchange->user_id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
                'bybit_trade' => $bybitTrade,
            ]);

            return false;
        }
    }

    /**
     * Обновляет существующую сделку новыми данными исполнения
     */
    private function updateExistingTrade(Trade $trade, array $bybitTrade): void
    {
        $newSize = (float) $bybitTrade['execQty'];
        $newPrice = (float) $bybitTrade['execPrice'];
        $newFee = (float) $bybitTrade['execFee'];

        // Рассчитываем новую среднюю цену
        $totalSize = $trade->size + $newSize;
        $weightedPrice = (($trade->entry_price * $trade->size) + ($newPrice * $newSize)) / $totalSize;

        $trade->update([
            'size' => $totalSize,
            'entry_price' => $weightedPrice,
            'fee' => $trade->fee + $newFee,
        ]);

        Log::debug('Updated existing trade with new execution', [
            'user_id' => $this->userExchange->user_id,
            'trade_id' => $trade->id,
            'new_size' => $totalSize,
            'new_avg_price' => $weightedPrice,
        ]);
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
     * Синхронизирует закрытые позиции для более точного анализа
     */
    private function syncClosedPositions(BybitService $bybitService, string $category): void
    {
        try {
            $closedPnLs = $bybitService->getClosedPnL(
                category: $category,
                startTime: $this->startTime,
                endTime: $this->endTime,
                limit: 50
            );

            $totalSynced = 0;

            foreach ($closedPnLs as $bybitPnL) {
                if ($this->syncClosedPosition($bybitService, $bybitPnL)) {
                    $totalSynced++;
                }
            }

            Log::info("Synced closed positions for category {$category}", [
                'user_id' => $this->userExchange->user_id,
                'category' => $category,
                'total_synced' => $totalSynced,
            ]);

        } catch (\Exception $e) {
            Log::warning("Failed to sync closed positions for category {$category}", [
                'user_id' => $this->userExchange->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Синхронизирует одну закрытую позицию
     */
    private function syncClosedPosition(BybitService $bybitService, array $bybitPnL): bool
    {
        $orderId = $bybitPnL['orderId'];

        // Проверяем, не существует ли уже такая позиция
        $existingTrade = Trade::where('user_id', $this->userExchange->user_id)
            ->where('exchange', 'bybit')
            ->where('external_id', $orderId)
            ->first();

        if ($existingTrade) {
            // Обновляем существующую сделку данными о закрытии, если они отсутствуют
            if (!$existingTrade->exit_price && $bybitPnL['avgExitPrice'] > 0) {
                $existingTrade->update([
                    'exit_price' => (float) $bybitPnL['avgExitPrice'],
                    'exit_time' => Carbon::createFromTimestampMs($bybitPnL['updatedTime']),
                    'pnl' => (float) $bybitPnL['closedPnl'],
                    'status' => 'closed',
                ]);

                Log::debug('Updated trade with closing data', [
                    'user_id' => $this->userExchange->user_id,
                    'trade_id' => $existingTrade->id,
                    'pnl' => $bybitPnL['closedPnl'],
                ]);

                return true;
            }
            
            return false;
        }

        // Создаем новую сделку из закрытой позиции
        $tradeData = $bybitService->transformClosedPnLData($bybitPnL);
        $tradeData['user_id'] = $this->userExchange->user_id;

        try {
            Trade::create($tradeData);
            
            Log::debug('Created new trade from closed position', [
                'user_id' => $this->userExchange->user_id,
                'external_id' => $orderId,
                'symbol' => $tradeData['symbol'],
                'pnl' => $tradeData['pnl'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create trade from closed position', [
                'user_id' => $this->userExchange->user_id,
                'external_id' => $orderId,
                'error' => $e->getMessage(),
                'bybit_pnl' => $bybitPnL,
            ]);

            return false;
        }
    }

    /**
     * Определяет задержку между повторными попытками
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 сек, 1 мин, 2 мин
    }

    /**
     * Синхронизирует открытые позиции для определенной категории
     */
    private function syncOpenPositions(BybitService $bybitService, string $category): void
    {
        try {
            $positions = $bybitService->getPositions($category);

            $totalSynced = 0;

            foreach ($positions as $bybitPosition) {
                // Пропускаем позиции с нулевым размером
                if (abs((float) $bybitPosition['size']) == 0) {
                    continue;
                }

                if ($this->syncSingleOpenPosition($bybitPosition)) {
                    $totalSynced++;
                }
            }

            Log::info("Synced open positions for category {$category}", [
                'user_id' => $this->userExchange->user_id,
                'category' => $category,
                'total_synced' => $totalSynced,
            ]);

        } catch (\Exception $e) {
            Log::warning("Failed to sync open positions for category {$category}", [
                'user_id' => $this->userExchange->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Синхронизирует одну открытую позицию
     */
    private function syncSingleOpenPosition(array $bybitPosition): bool
    {
        $symbol = $bybitPosition['symbol'];
        $size = abs((float) $bybitPosition['size']);
        $side = (float) $bybitPosition['size'] > 0 ? 'buy' : 'sell';

        // Проверяем, не существует ли уже такая открытая позиция
        $existingPosition = Trade::where('user_id', $this->userExchange->user_id)
            ->where('exchange', 'bybit')
            ->where('symbol', $symbol)
            ->where('status', 'open')
            ->first();

        if ($existingPosition) {
            // Обновляем существующую позицию
            $existingPosition->update([
                'size' => $size,
                'side' => $side,
                'entry_price' => (float) $bybitPosition['avgPrice'],
                'unrealized_pnl' => (float) $bybitPosition['unrealisedPnl'],
                'updated_at' => now(),
            ]);

            Log::debug('Updated existing open position', [
                'user_id' => $this->userExchange->user_id,
                'trade_id' => $existingPosition->id,
                'symbol' => $symbol,
                'size' => $size,
                'unrealized_pnl' => $bybitPosition['unrealisedPnl'],
            ]);

            return true;
        }

        // Создаем новую открытую позицию
        try {
            Trade::create([
                'user_id' => $this->userExchange->user_id,
                'exchange' => 'bybit',
                'external_id' => 'open_' . $symbol . '_' . time(),
                'symbol' => $symbol,
                'side' => $side,
                'size' => $size,
                'entry_price' => (float) $bybitPosition['avgPrice'],
                'entry_time' => now(), // Приблизительное время, т.к. Bybit не возвращает точное время открытия
                'status' => 'open',
                'unrealized_pnl' => (float) $bybitPosition['unrealisedPnl'],
                'fee' => 0, // Комиссия будет учтена при закрытии
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::debug('Created new open position', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $symbol,
                'side' => $side,
                'size' => $size,
                'entry_price' => $bybitPosition['avgPrice'],
                'unrealized_pnl' => $bybitPosition['unrealisedPnl'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create open position', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $symbol,
                'error' => $e->getMessage(),
                'bybit_position' => $bybitPosition,
            ]);

            return false;
        }
    }

    /**
     * Запускает сбор рыночных данных для торгуемых пользователем символов
     */
    private function triggerMarketDataCollection(BybitService $bybitService): void
    {
        try {
            // Получаем уникальные символы из синхронизированных сделок
            $userSymbols = Trade::where('user_id', $this->userExchange->user_id)
                ->where('entry_time', '>=', now()->subDays(7)) // Символы за последнюю неделю
                ->distinct()
                ->pluck('symbol')
                ->toArray();

            if (!empty($userSymbols)) {
                // Запускаем job для сбора данных по символам пользователя с задержкой
                CollectBybitMarketDataJob::dispatch($userSymbols)
                    ->delay(now()->addMinutes(2)); // Задержка чтобы не перегружать API

                Log::info('Market data collection triggered for user symbols', [
                    'user_id' => $this->userExchange->user_id,
                    'symbols' => $userSymbols
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to trigger market data collection', [
                'user_id' => $this->userExchange->user_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}