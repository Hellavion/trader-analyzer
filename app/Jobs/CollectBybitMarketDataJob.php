<?php

namespace App\Jobs;

use App\Services\DynamicMarketDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для динамического сбора рыночных данных с Bybit
 * Собирает данные только по активным символам пользователей
 */
class CollectBybitMarketDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 минут
    public int $tries = 2;

    private ?array $specificSymbols;
    private DynamicMarketDataService $marketDataService;

    public function __construct(?array $specificSymbols = null)
    {
        $this->specificSymbols = $specificSymbols;
        $this->marketDataService = new DynamicMarketDataService();
    }

    public function handle(): void
    {
        try {
            if ($this->specificSymbols) {
                // Сбор данных для конкретных символов
                Log::info('Starting targeted market data collection', [
                    'symbols' => $this->specificSymbols
                ]);

                $results = [];
                foreach ($this->specificSymbols as $symbol) {
                    $result = $this->marketDataService->collectDataForSymbol($symbol);
                    if ($result) {
                        $results[$symbol] = $result;
                    }
                }

                Log::info('Targeted market data collection completed', [
                    'processed_symbols' => count($results)
                ]);

            } else {
                // Динамический сбор данных для всех активных символов
                Log::info('Starting dynamic market data collection');

                $results = $this->marketDataService->collectDataForActiveSymbols();

                Log::info('Dynamic market data collection completed', [
                    'processed_symbols' => count($results)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Market data collection failed', [
                'specific_symbols' => $this->specificSymbols,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Обработка неудачного выполнения job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CollectBybitMarketDataJob failed permanently', [
            'specific_symbols' => $this->specificSymbols,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Определяет задержку между повторными попытками
     */
    public function backoff(): array
    {
        return [60, 180]; // 1 мин, 3 мин
    }

}