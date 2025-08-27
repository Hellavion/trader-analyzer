<?php

namespace App\Jobs;

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
 * Быстрая джоба для ручной синхронизации последних сделок с Bybit
 * Синхронизирует только данные за последние 24 часа
 */
class QuickSyncBybitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60; // Сокращенный timeout
    public int $tries = 2;

    private UserExchange $userExchange;

    public function __construct(UserExchange $userExchange)
    {
        $this->userExchange = $userExchange;
    }

    public function handle(): void
    {
        $startTime = now()->subDay(); // Только последние 24 часа
        $endTime = now();

        Log::info('Starting quick Bybit sync', [
            'user_id' => $this->userExchange->user_id,
            'exchange_id' => $this->userExchange->id,
            'start_time' => $startTime->toISOString(),
            'end_time' => $endTime->toISOString(),
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

            // Синхронизируем только linear (основная категория для большинства трейдеров)
            $totalSynced = $this->syncRecentTrades($bybitService, $startTime, $endTime);

            Log::info('About to sync closed positions', ['user_id' => $this->userExchange->user_id]);
            
            // Синхронизируем закрытые позиции за последние 24 часа (для анализа Smart Money)
            $this->syncRecentClosedPositions($bybitService, $startTime, $endTime);
            
            Log::info('Closed positions sync method completed', ['user_id' => $this->userExchange->user_id]);

            // Синхронизируем только открытые позиции (быстро)
            $this->syncCurrentOpenPositions($bybitService);

            // Обновляем время последней синхронизации
            $this->userExchange->updateLastSync();

            Log::info('Quick Bybit sync completed successfully', [
                'user_id' => $this->userExchange->user_id,
                'exchange_id' => $this->userExchange->id,
                'total_synced' => $totalSynced,
            ]);

        } catch (\Exception $e) {
            Log::error('Quick Bybit sync failed', [
                'user_id' => $this->userExchange->user_id,
                'exchange_id' => $this->userExchange->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Синхронизирует последние сделки (только linear)
     */
    private function syncRecentTrades(BybitService $bybitService, Carbon $startTime, Carbon $endTime): int
    {
        $totalSynced = 0;
        
        try {
            $trades = $bybitService->getTradingHistory(
                category: 'linear',
                startTime: $startTime,
                endTime: $endTime,
                limit: 100 // Увеличиваем лимит для меньшего количества запросов
            );

            foreach ($trades as $bybitTrade) {
                // Пропускаем записи о фандинге и других не-торговых операциях
                if (($bybitTrade['execType'] ?? '') !== 'Trade') {
                    continue;
                }
                
                if ($this->syncSingleTrade($bybitTrade)) {
                    $totalSynced++;
                }
            }

            Log::info('Quick sync trades completed', [
                'user_id' => $this->userExchange->user_id,
                'total_synced' => $totalSynced,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to sync recent trades', [
                'user_id' => $this->userExchange->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $totalSynced;
    }

    /**
     * Синхронизирует недавние закрытые позиции (для анализа Smart Money)
     */
    private function syncRecentClosedPositions(BybitService $bybitService, Carbon $startTime, Carbon $endTime): int
    {
        $totalSynced = 0;
        
        Log::info('Starting sync of closed positions', [
            'user_id' => $this->userExchange->user_id,
            'start_time' => $startTime->toISOString(),
            'end_time' => $endTime->toISOString(),
        ]);
        
        try {
            $closedPnLs = $bybitService->getClosedPnL(
                category: 'linear',
                startTime: $startTime,
                endTime: $endTime,
                limit: 50
            );

            Log::info('Found closed PnLs from Bybit', [
                'user_id' => $this->userExchange->user_id,
                'count' => count($closedPnLs),
            ]);

            foreach ($closedPnLs as $bybitPnL) {
                Log::debug('Processing closed PnL', [
                    'user_id' => $this->userExchange->user_id,
                    'symbol' => $bybitPnL['symbol'],
                    'pnl' => $bybitPnL['closedPnl'],
                ]);
                
                if ($this->syncClosedPosition($bybitPnL)) {
                    $totalSynced++;
                }
            }

            Log::info('Quick sync closed positions completed', [
                'user_id' => $this->userExchange->user_id,
                'total_synced' => $totalSynced,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to sync recent closed positions', [
                'user_id' => $this->userExchange->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $totalSynced;
    }

    /**
     * Синхронизирует одну сделку (упрощенная версия)
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

        // Создаем новую сделку
        $tradeData = (new BybitService())->transformTradeData($bybitTrade);
        $tradeData['user_id'] = $this->userExchange->user_id;

        try {
            Trade::create($tradeData);
            
            Log::debug('Created new trade from quick sync', [
                'user_id' => $this->userExchange->user_id,
                'external_id' => $externalId,
                'symbol' => $tradeData['symbol'],
                'size' => $tradeData['size'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create trade from quick sync', [
                'user_id' => $this->userExchange->user_id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
                'bybit_trade' => $bybitTrade,
            ]);

            return false;
        }
    }

    /**
     * Синхронизирует открытые позиции (только linear)
     */
    private function syncCurrentOpenPositions(BybitService $bybitService): void
    {
        try {
            $positions = $bybitService->getPositions('linear');

            foreach ($positions as $bybitPosition) {
                // Пропускаем позиции с нулевым размером
                if (abs((float) $bybitPosition['size']) == 0) {
                    continue;
                }

                $this->syncSingleOpenPosition($bybitPosition);
            }

            Log::info('Quick sync open positions completed', [
                'user_id' => $this->userExchange->user_id,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to sync current open positions', [
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
                'entry_time' => now(),
                'status' => 'open',
                'unrealized_pnl' => (float) $bybitPosition['unrealisedPnl'],
                'fee' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create quick sync open position', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Синхронизирует одну закрытую позицию
     */
    private function syncClosedPosition(array $bybitPnL): bool
    {
        $symbol = $bybitPnL['symbol'];
        $orderId = $bybitPnL['orderId'] ?? null;
        $closeTime = Carbon::createFromTimestampMs((int) $bybitPnL['updatedTime']);
        
        // Ищем открытую позицию для этого символа
        $openPosition = Trade::where('user_id', $this->userExchange->user_id)
            ->where('exchange', 'bybit')
            ->where('symbol', $symbol)
            ->where('status', 'open')
            ->first();
            
        if (!$openPosition) {
            Log::debug('No open position found for closed PnL', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $symbol,
                'order_id' => $orderId,
            ]);
            return false;
        }
        
        try {
            // Обновляем позицию как закрытую с данными для Smart Money анализа
            $openPosition->update([
                'status' => 'closed',
                'exit_price' => (float) $bybitPnL['avgExitPrice'],
                'exit_time' => $closeTime,
                'realized_pnl' => (float) $bybitPnL['closedPnl'],
                'fee' => (float) ($bybitPnL['cumExecFee'] ?? 0),
                'updated_at' => now(),
            ]);
            
            Log::debug('Closed position synced', [
                'user_id' => $this->userExchange->user_id,
                'trade_id' => $openPosition->id,
                'symbol' => $symbol,
                'exit_price' => $bybitPnL['avgExitPrice'],
                'pnl' => $bybitPnL['closedPnl'],
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to sync closed position', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $symbol,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'bybit_pnl' => $bybitPnL,
            ]);
            
            return false;
        }
    }

    /**
     * Обработка неудачного выполнения job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('QuickSyncBybitJob failed permanently', [
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
        return [5, 15]; // 5 сек, 15 сек (быстрее чем в полной синхронизации)
    }
}