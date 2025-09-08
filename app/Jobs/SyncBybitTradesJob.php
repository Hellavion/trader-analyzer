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
                Log::warning('Bybit exchange connection is not active');
                return;
            }

            $credentials = $this->userExchange->getApiCredentials();
            $bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

            // Получаем закрытые позиции только из linear (фьючерсы)
            $closedPositions = $bybitService->getClosedPnL(
                category: 'linear',
                startTime: $this->startTime,
                endTime: $this->endTime,
                limit: 50
            );

            $synced = 0;
            foreach ($closedPositions as $position) {
                // Простое сохранение сырых данных
                $tradeData = [
                    'user_id' => $this->userExchange->user_id,
                    'exchange' => 'bybit',
                    'external_id' => $position['orderId'],
                    'order_id' => $position['orderId'],
                    'symbol' => $position['symbol'],
                    'side' => strtolower($position['side']),
                    'size' => (float) $position['qty'],
                    'entry_price' => (float) $position['avgEntryPrice'],
                    'exit_price' => (float) $position['avgExitPrice'],
                    'entry_time' => Carbon::createFromTimestampMs($position['createdTime']),
                    'exit_time' => Carbon::createFromTimestampMs($position['updatedTime']),
                    'pnl' => (float) $position['closedPnl'],
                    'fee' => abs((float) ($position['totalFee'] ?? 0)),
                    'status' => 'closed',
                    'raw_data' => json_encode($position),
                ];

                // Проверяем что такой позиции еще нет
                $exists = Trade::where('user_id', $this->userExchange->user_id)
                    ->where('external_id', $position['orderId'])
                    ->exists();

                if (!$exists) {
                    Log::debug('Creating trade with data:', $tradeData);
                    Trade::create($tradeData);
                    $synced++;
                }
            }

            Log::info('Bybit trades synced', [
                'user_id' => $this->userExchange->user_id,
                'synced' => $synced,
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

}