<?php

namespace App\Console\Commands;

use App\Jobs\CollectBybitMarketDataJob;
use App\Jobs\SyncBybitTradesJob;
use App\Models\UserExchange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Команда для автоматической синхронизации данных с Bybit
 */
class SyncBybitDataCommand extends Command
{
    protected $signature = 'bybit:sync 
                           {--trades : Sync trades only}
                           {--market-data : Sync market data only}
                           {--user-id=* : Sync for specific user IDs}';

    protected $description = 'Synchronize trades and market data from Bybit exchange';

    public function handle(): int
    {
        $this->info('Starting Bybit data synchronization...');

        $syncTrades = !$this->option('market-data');
        $syncMarketData = !$this->option('trades');
        $userIds = $this->option('user-id');

        try {
            if ($syncTrades) {
                $this->syncTrades($userIds);
            }

            if ($syncMarketData) {
                $this->syncMarketData();
            }

            $this->info('Bybit data synchronization completed successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Bybit data synchronization failed: ' . $e->getMessage());
            Log::error('SyncBybitDataCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Синхронизирует сделки пользователей
     */
    private function syncTrades(array $userIds = []): void
    {
        $this->info('Syncing trades...');

        $query = UserExchange::where('exchange', 'bybit')
            ->where('is_active', true);

        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        $exchanges = $query->get();

        if ($exchanges->isEmpty()) {
            $this->warn('No active Bybit exchanges found.');
            return;
        }

        $this->info("Found {$exchanges->count()} active Bybit connections.");

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($exchanges as $exchange) {
            // Проверяем, нужна ли синхронизация
            $syncIntervalHours = $exchange->sync_settings['sync_interval_hours'] ?? 1;
            
            if (!$exchange->needsSync($syncIntervalHours)) {
                $skippedCount++;
                $this->line("Skipped user {$exchange->user_id} (recently synced)");
                continue;
            }

            // Запускаем синхронизацию
            SyncBybitTradesJob::dispatch($exchange)->onQueue('high');
            $syncedCount++;
            
            $this->line("Queued trades sync for user {$exchange->user_id}");
        }

        $this->info("Trades sync: {$syncedCount} queued, {$skippedCount} skipped.");
    }

    /**
     * Синхронизирует рыночные данные
     */
    private function syncMarketData(): void
    {
        $this->info('Syncing market data...');

        // Запускаем job для сбора рыночных данных
        CollectBybitMarketDataJob::dispatch()->onQueue('low');

        $this->info('Market data collection job queued.');
    }
}
