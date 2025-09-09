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

            // Синхронизируем только закрытые позиции через closed-pnl API
            $totalSynced = $this->syncRecentClosedPositions($bybitService, $startTime, $endTime);

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
     * Создает закрытую сделку из closed-pnl данных
     */
    private function syncClosedPosition(array $bybitPnL): bool
    {
        $symbol = $bybitPnL['symbol'];
        $orderId = $bybitPnL['orderId'];
        
        // Проверяем, не существует ли уже такая закрытая сделка
        $existingTrade = Trade::where('user_id', $this->userExchange->user_id)
            ->where('exchange', 'bybit')
            ->where('external_id', $orderId)
            ->first();
            
        if ($existingTrade) {
            Log::debug('Trade already exists for order', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $symbol,
                'order_id' => $orderId,
            ]);
            return false;
        }
        
        try {
            // Создаем закрытую сделку с полными данными из closed-pnl
            $trade = Trade::create([
                'user_id' => $this->userExchange->user_id,
                'exchange' => 'bybit',
                'external_id' => $orderId,
                'symbol' => $symbol,
                'side' => strtolower($bybitPnL['side']),
                'size' => abs((float) $bybitPnL['closedSize']),
                'entry_price' => (float) $bybitPnL['avgEntryPrice'],
                'entry_time' => Carbon::createFromTimestampMs((int) $bybitPnL['createdTime']),
                'exit_price' => (float) $bybitPnL['avgExitPrice'],
                'exit_time' => Carbon::createFromTimestampMs((int) $bybitPnL['updatedTime']),
                'status' => 'closed',
                'pnl' => (float) $bybitPnL['closedPnl'],
                'fee' => (float) $bybitPnL['openFee'] + (float) $bybitPnL['closeFee'],
            ]);
            
            // Отправляем real-time событие на фронт
            \App\Events\RealTradeUpdate::dispatch($trade->toArray());
            
            Log::debug('Created closed trade from closed-pnl', [
                'user_id' => $this->userExchange->user_id,
                'trade_id' => $trade->id,
                'symbol' => $symbol,
                'entry_price' => $bybitPnL['avgEntryPrice'],
                'exit_price' => $bybitPnL['avgExitPrice'],
                'pnl' => $bybitPnL['closedPnl'],
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to create closed trade from closed-pnl', [
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