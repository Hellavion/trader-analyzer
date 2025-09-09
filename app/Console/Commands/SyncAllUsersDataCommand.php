<?php

namespace App\Console\Commands;

use App\Models\UserExchange;
use App\Jobs\QuickSyncBybitJob;
use App\Jobs\CollectBybitMarketDataJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Команда для массовой синхронизации данных всех пользователей
 */
class SyncAllUsersDataCommand extends Command
{
    protected $signature = 'sync:all-users {--force : Force sync even if recently synced}';
    protected $description = 'Синхронизация торговых данных всех активных пользователей';

    public function handle(): int
    {
        $this->info('Starting mass users data synchronization...');

        $activeExchanges = $this->getActiveExchanges();

        if ($activeExchanges->isEmpty()) {
            $this->info('No active exchange connections found');
            return Command::SUCCESS;
        }

        $this->info("Found {$activeExchanges->count()} active exchange connections");

        $syncedCount = 0;
        $failedCount = 0;

        foreach ($activeExchanges as $exchange) {
            try {
                if ($this->shouldSync($exchange)) {
                    $this->syncUserExchange($exchange);
                    $syncedCount++;
                    
                    $this->line("✓ Synced user {$exchange->user_id} ({$exchange->exchange})");
                    
                    // Пауза между пользователями для rate limit
                    sleep(1);
                } else {
                    $this->line("- Skipped user {$exchange->user_id} (recently synced)");
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Failed to sync user {$exchange->user_id}: " . $e->getMessage());
                
                Log::error('Mass sync failed for user', [
                    'user_id' => $exchange->user_id,
                    'exchange_id' => $exchange->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Запускаем сбор рыночных данных после всех синхронизаций
        $this->triggerMarketDataCollection();

        $this->info("\nSynchronization completed:");
        $this->info("- Synced: {$syncedCount}");
        $this->info("- Failed: {$failedCount}");
        $this->info("- Skipped: " . ($activeExchanges->count() - $syncedCount - $failedCount));

        return Command::SUCCESS;
    }

    /**
     * Получает активные подключения к биржам
     */
    private function getActiveExchanges()
    {
        return UserExchange::where('is_active', true)
            ->whereHas('user', function ($query) {
                // Только активные пользователи (можно добавить дополнительные условия)
                $query->whereNotNull('email_verified_at');
            })
            ->with('user')
            ->get();
    }

    /**
     * Проверяет, нужна ли синхронизация для подключения
     */
    private function shouldSync(UserExchange $exchange): bool
    {
        if ($this->option('force')) {
            return true;
        }

        // Не синхронизируем если последняя синхронизация была менее 30 минут назад
        if ($exchange->last_sync_at && $exchange->last_sync_at->diffInMinutes(now()) < 30) {
            return false;
        }

        return true;
    }

    /**
     * Синхронизирует данные для конкретного подключения к бирже
     */
    private function syncUserExchange(UserExchange $exchange): void
    {
        switch ($exchange->exchange) {
            case 'bybit':
                QuickSyncBybitJob::dispatch($exchange)
                    ->onQueue('sync');
                break;
                
            case 'mexc':
                // Placeholder для будущей интеграции с MEXC
                $this->line("MEXC integration not implemented yet");
                break;
                
            default:
                throw new \Exception("Unknown exchange: {$exchange->exchange}");
        }
    }


    /**
     * Запускает сбор рыночных данных после синхронизации
     */
    private function triggerMarketDataCollection(): void
    {
        try {
            // Запускаем сбор рыночных данных с задержкой, чтобы дать время завершиться синхронизации
            CollectBybitMarketDataJob::dispatch()
                ->onQueue('market-data')
                ->delay(now()->addMinutes(5));

            $this->info('Market data collection job scheduled');

        } catch (\Exception $e) {
            $this->error('Failed to schedule market data collection: ' . $e->getMessage());
        }
    }
}