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

            // Синхронизируем сделки для разных категорий
            $this->syncTradesForCategory($bybitService, 'linear');
            $this->syncTradesForCategory($bybitService, 'spot');
            $this->syncTradesForCategory($bybitService, 'inverse');

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
     * Определяет задержку между повторными попытками
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 сек, 1 мин, 2 мин
    }
}