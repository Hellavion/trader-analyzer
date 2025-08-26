<?php

namespace App\Console\Commands;

use App\Models\MarketStructure;
use App\Models\Trade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Команда для очистки старых данных
 */
class CleanupOldDataCommand extends Command
{
    protected $signature = 'cleanup:old-data {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Очищает старые рыночные данные и неиспользуемые записи';

    public function handle(): int
    {
        $this->info('Starting data cleanup...');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will actually be deleted');
        }

        $totalDeleted = 0;

        // 1. Очистка старых рыночных данных по таймфреймам
        $totalDeleted += $this->cleanupMarketStructures($isDryRun);

        // 2. Очистка данных для неактивных символов
        $totalDeleted += $this->cleanupInactiveSymbols($isDryRun);

        // 3. Очистка очень старых сделок (опционально)
        $totalDeleted += $this->cleanupOldTrades($isDryRun);

        // 4. Оптимизация базы данных
        if (!$isDryRun && $totalDeleted > 0) {
            $this->optimizeDatabase();
        }

        $this->info("\nCleanup completed:");
        $this->info("Total records processed: {$totalDeleted}");

        return Command::SUCCESS;
    }

    /**
     * Очищает старые данные рыночной структуры
     */
    private function cleanupMarketStructures(bool $isDryRun): int
    {
        $this->line('Cleaning market structures...');

        $deletionRules = [
            '5m' => 7,     // Данные 5m таймфрейма старше 7 дней
            '15m' => 14,   // Данные 15m таймфрейма старше 14 дней  
            '1h' => 30,    // Данные 1h таймфрейма старше 30 дней
            '4h' => 60,    // Данные 4h таймфрейма старше 60 дней
            '1D' => 180,   // Данные 1D таймфрейма старше 180 дней
        ];

        $totalDeleted = 0;

        foreach ($deletionRules as $timeframe => $days) {
            $cutoffDate = now()->subDays($days);
            
            $query = MarketStructure::where('timeframe', $timeframe)
                ->where('timestamp', '<', $cutoffDate);

            $count = $query->count();
            
            if ($count > 0) {
                $this->line("  {$timeframe}: {$count} records older than {$days} days");
                
                if (!$isDryRun) {
                    $query->delete();
                }
                
                $totalDeleted += $count;
            }
        }

        return $totalDeleted;
    }

    /**
     * Очищает данные для символов, которые больше не торгуются
     */
    private function cleanupInactiveSymbols(bool $isDryRun): int
    {
        $this->line('Cleaning inactive symbols...');

        // Находим символы, по которым не было сделок последние 60 дней
        $activeSymbols = Trade::where('entry_time', '>=', now()->subDays(60))
            ->distinct()
            ->pluck('symbol')
            ->toArray();

        if (empty($activeSymbols)) {
            $this->line('  No active symbols found, skipping cleanup');
            return 0;
        }

        $query = MarketStructure::whereNotIn('symbol', $activeSymbols)
            ->where('timestamp', '<', now()->subDays(30)); // Дополнительная защита

        $count = $query->count();

        if ($count > 0) {
            // Получаем список символов для удаления
            $symbolsToDelete = $query->distinct()
                ->pluck('symbol')
                ->toArray();

            $this->line("  Inactive symbols: " . implode(', ', array_slice($symbolsToDelete, 0, 10)) . 
                      (count($symbolsToDelete) > 10 ? '... and ' . (count($symbolsToDelete) - 10) . ' more' : ''));
            $this->line("  Records to delete: {$count}");

            if (!$isDryRun) {
                $query->delete();
            }
        } else {
            $this->line('  No inactive symbols found');
        }

        return $count;
    }

    /**
     * Очищает очень старые сделки (только если много данных)
     */
    private function cleanupOldTrades(bool $isDryRun): int
    {
        $this->line('Checking old trades...');

        $totalTrades = Trade::count();
        
        // Очищаем старые сделки только если их очень много (>100k)
        if ($totalTrades < 100000) {
            $this->line('  Trade count is reasonable, skipping old trades cleanup');
            return 0;
        }

        // Удаляем сделки старше 2 лет
        $query = Trade::where('entry_time', '<', now()->subYears(2));
        $count = $query->count();

        if ($count > 0) {
            $this->line("  Very old trades (>2 years): {$count}");
            
            if (!$isDryRun) {
                // Удаляем батчами для производительности
                $query->chunk(1000, function ($trades) {
                    Trade::whereIn('id', $trades->pluck('id'))->delete();
                });
            }
        } else {
            $this->line('  No very old trades found');
        }

        return $count;
    }

    /**
     * Оптимизирует базу данных после очистки
     */
    private function optimizeDatabase(): void
    {
        $this->line('Optimizing database...');

        try {
            // Для SQLite - VACUUM
            if (config('database.default') === 'sqlite') {
                DB::statement('VACUUM');
                $this->line('  SQLite database optimized');
            }
            
            // Для других БД можно добавить соответствующие команды
            // PostgreSQL: VACUUM ANALYZE
            // MySQL: OPTIMIZE TABLE

        } catch (\Exception $e) {
            $this->warn('  Database optimization failed: ' . $e->getMessage());
        }
    }
}