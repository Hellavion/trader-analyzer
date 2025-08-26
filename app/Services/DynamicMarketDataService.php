<?php

namespace App\Services;

use App\Models\MarketStructure;
use App\Models\Trade;
use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Динамический сервис для сбора рыночных данных
 * Собирает данные только по символам, которыми торгуют пользователи
 */
class DynamicMarketDataService
{
    private BybitService $bybitService;
    private array $timeframes;
    
    public function __construct()
    {
        $this->bybitService = new BybitService();
        $this->timeframes = [
            '5' => '5m', 
            '15' => '15m',
            '60' => '1h',
            '240' => '4h',
            'D' => '1D'
        ];
    }

    /**
     * Собирает данные для активных символов пользователей
     */
    public function collectDataForActiveSymbols(): array
    {
        $activeSymbols = $this->getActiveUserSymbols();
        
        if (empty($activeSymbols)) {
            Log::info('No active symbols found for market data collection');
            return [];
        }

        Log::info('Starting dynamic market data collection', [
            'symbols_count' => count($activeSymbols),
            'symbols' => $activeSymbols
        ]);

        $results = [];
        $processedCount = 0;

        foreach ($activeSymbols as $symbol) {
            try {
                $symbolResults = $this->collectDataForSymbol($symbol);
                if ($symbolResults) {
                    $results[$symbol] = $symbolResults;
                    $processedCount++;
                }

                // Пауза между символами для rate limit
                usleep(250000); // 250ms

            } catch (\Exception $e) {
                Log::error("Failed to collect data for symbol {$symbol}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Dynamic market data collection completed', [
            'processed_symbols' => $processedCount,
            'total_active_symbols' => count($activeSymbols)
        ]);

        return $results;
    }

    /**
     * Собирает данные для конкретного символа по всем таймфреймам
     */
    public function collectDataForSymbol(string $symbol): ?array
    {
        $symbolResults = [];

        foreach ($this->timeframes as $interval => $timeframeName) {
            try {
                $result = $this->collectDataForSymbolTimeframe($symbol, $interval, $timeframeName);
                if ($result) {
                    $symbolResults[$timeframeName] = $result;
                }

                // Пауза между запросами
                usleep(150000); // 150ms

            } catch (\Exception $e) {
                Log::warning("Failed to collect data for {$symbol} {$timeframeName}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return !empty($symbolResults) ? $symbolResults : null;
    }

    /**
     * Собирает данные для символа и таймфрейма с кэшированием
     */
    private function collectDataForSymbolTimeframe(string $symbol, string $interval, string $timeframeName): ?array
    {
        // Проверяем, нужно ли обновлять данные
        if (!$this->shouldUpdateData($symbol, $timeframeName)) {
            return null;
        }

        try {
            // Кэш для избежания дублированных запросов
            $cacheKey = "kline_{$symbol}_{$interval}_" . now()->format('Y-m-d-H');
            
            $klineData = Cache::remember($cacheKey, 600, function () use ($symbol, $interval) {
                return $this->bybitService->getKlineData(
                    category: 'linear',
                    symbol: $symbol,
                    interval: $interval,
                    limit: 200
                );
            });

            if (empty($klineData)) {
                return null;
            }

            // Преобразуем и анализируем данные
            $ohlcvData = $this->transformKlineData($klineData);
            $analysis = $this->performSmartMoneyAnalysis($ohlcvData);

            // Сохраняем в базу данных
            $marketStructure = MarketStructure::create([
                'symbol' => $symbol,
                'timeframe' => $timeframeName,
                'timestamp' => now(),
                'order_blocks' => $analysis['order_blocks'],
                'liquidity_levels' => $analysis['liquidity_levels'],
                'fvg_zones' => $analysis['fvg_zones'],
                'market_bias' => $analysis['market_bias'],
                'high' => $analysis['high'],
                'low' => $analysis['low'],
            ]);

            // Очищаем старые данные для этого символа/таймфрейма
            $this->cleanupOldData($symbol, $timeframeName);

            return [
                'id' => $marketStructure->id,
                'analysis_summary' => [
                    'order_blocks_count' => count($analysis['order_blocks']),
                    'liquidity_levels_count' => count($analysis['liquidity_levels']),
                    'fvg_count' => count($analysis['fvg_zones']),
                    'market_bias' => $analysis['market_bias']['direction'] ?? 'neutral'
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Failed to collect market data", [
                'symbol' => $symbol,
                'timeframe' => $timeframeName,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Получает список активных символов из торговой истории пользователей
     */
    private function getActiveUserSymbols(): array
    {
        // Символы из сделок за последние 30 дней
        $recentSymbols = Trade::where('entry_time', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('symbol')
            ->toArray();

        // Символы из активных позиций пользователей (если есть)
        $activeSymbols = $this->getActiveSymbolsFromExchanges();

        // Объединяем и убираем дубликаты
        $allSymbols = array_unique(array_merge($recentSymbols, $activeSymbols));

        // Фильтруем по активности (минимум 5 сделок за 30 дней)
        $filteredSymbols = [];
        foreach ($allSymbols as $symbol) {
            $tradeCount = Trade::where('symbol', $symbol)
                ->where('entry_time', '>=', now()->subDays(30))
                ->count();

            if ($tradeCount >= 3) { // Минимум 3 сделки для включения в анализ
                $filteredSymbols[] = $symbol;
            }
        }

        return $filteredSymbols;
    }

    /**
     * Получает активные символы из подключенных бирж пользователей
     */
    private function getActiveSymbolsFromExchanges(): array
    {
        $symbols = [];

        $activeExchanges = UserExchange::where('is_active', true)
            ->where('exchange', 'bybit')
            ->get();

        foreach ($activeExchanges as $exchange) {
            try {
                $credentials = $exchange->getApiCredentials();
                $bybitService = new BybitService($credentials['api_key'], $credentials['secret']);
                
                $userSymbols = $bybitService->getActiveSymbols('linear');
                $symbols = array_merge($symbols, $userSymbols);

                // Пауза между запросами к разным аккаунтам
                usleep(200000);

            } catch (\Exception $e) {
                Log::warning('Failed to get active symbols from exchange', [
                    'exchange_id' => $exchange->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return array_unique($symbols);
    }

    /**
     * Проверяет, нужно ли обновлять данные для символа/таймфрейма
     */
    private function shouldUpdateData(string $symbol, string $timeframe): bool
    {
        $lastUpdate = MarketStructure::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->latest('timestamp')
            ->first();

        if (!$lastUpdate) {
            return true; // Данных нет, нужно собрать
        }

        // Интервалы обновления в зависимости от таймфрейма
        $updateIntervals = [
            '5m' => 15,   // Обновляем каждые 15 минут
            '15m' => 30,  // Обновляем каждые 30 минут
            '1h' => 60,   // Обновляем каждый час
            '4h' => 240,  // Обновляем каждые 4 часа
            '1D' => 1440, // Обновляем раз в сутки
        ];

        $interval = $updateIntervals[$timeframe] ?? 60;
        
        return $lastUpdate->timestamp->diffInMinutes(now()) >= $interval;
    }

    /**
     * Очищает старые данные для экономии места
     */
    private function cleanupOldData(string $symbol, string $timeframe): void
    {
        $keepDays = match ($timeframe) {
            '5m' => 7,    // Храним 5m данные неделю
            '15m' => 14,  // Храним 15m данные 2 недели
            '1h' => 30,   // Храним 1h данные месяц
            '4h' => 60,   // Храним 4h данные 2 месяца
            '1D' => 180,  // Храним дневные данные полгода
            default => 30
        };

        MarketStructure::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->where('timestamp', '<', now()->subDays($keepDays))
            ->delete();
    }

    /**
     * Преобразует kline данные в OHLCV массив
     */
    private function transformKlineData(array $klineData): array
    {
        $ohlcv = [];

        foreach ($klineData as $candle) {
            $ohlcv[] = [
                'timestamp' => Carbon::createFromTimestampMs($candle[0]),
                'open' => (float) $candle[1],
                'high' => (float) $candle[2],
                'low' => (float) $candle[3],
                'close' => (float) $candle[4],
                'volume' => (float) $candle[5],
            ];
        }

        // Сортируем по времени
        usort($ohlcv, fn($a, $b) => $a['timestamp']->timestamp <=> $b['timestamp']->timestamp);

        return $ohlcv;
    }

    /**
     * Выполняет Smart Money анализ рыночных данных
     */
    private function performSmartMoneyAnalysis(array $ohlcv): array
    {
        return [
            'order_blocks' => $this->detectOrderBlocks($ohlcv),
            'liquidity_levels' => $this->detectLiquidityLevels($ohlcv),
            'fvg_zones' => $this->detectFairValueGaps($ohlcv),
            'market_bias' => $this->determineMarketBias($ohlcv),
            'high' => max(array_column($ohlcv, 'high')),
            'low' => min(array_column($ohlcv, 'low')),
        ];
    }

    /**
     * Детекция Order Blocks
     */
    private function detectOrderBlocks(array $ohlcv): array
    {
        $blocks = [];
        $lookback = 15;

        for ($i = $lookback; $i < count($ohlcv) - 5; $i++) {
            $current = $ohlcv[$i];
            
            $bodySize = abs($current['close'] - $current['open']);
            $avgBodySize = $this->getAverageBodySize($ohlcv, $i, $lookback);
            $avgVolume = $this->getAverageVolume($ohlcv, $i, $lookback);
            
            // Критерии импульсной свечи
            if ($bodySize > $avgBodySize * 1.5 && $current['volume'] > $avgVolume * 1.2) {
                $isBullish = $current['close'] > $current['open'];
                
                // Ищем консолидацию перед импульсом
                $consolidation = $this->findConsolidation($ohlcv, $i, $lookback);
                
                if ($consolidation) {
                    $blocks[] = [
                        'type' => $isBullish ? 'bullish' : 'bearish',
                        'high' => $consolidation['high'],
                        'low' => $consolidation['low'],
                        'timestamp' => $current['timestamp']->toISOString(),
                        'strength' => min(10, $bodySize / $avgBodySize * 2),
                        'is_active' => true,
                    ];
                }
            }
        }

        return array_slice($blocks, -10); // Последние 10 блоков
    }

    /**
     * Детекция уровней ликвидности
     */
    private function detectLiquidityLevels(array $ohlcv): array
    {
        $levels = [];
        $tolerance = 0.002; // 0.2%

        for ($i = 3; $i < count($ohlcv) - 3; $i++) {
            if ($this->isLocalHigh($ohlcv, $i)) {
                $level = $ohlcv[$i]['high'];
                $touches = $this->countLevelTouches($ohlcv, $level, $tolerance, $i);
                
                if ($touches >= 2) {
                    $levels[] = [
                        'type' => 'resistance',
                        'level' => $level,
                        'touches' => $touches,
                        'timestamp' => $ohlcv[$i]['timestamp']->toISOString(),
                        'strength' => min(10, $touches * 2),
                    ];
                }
            }

            if ($this->isLocalLow($ohlcv, $i)) {
                $level = $ohlcv[$i]['low'];
                $touches = $this->countLevelTouches($ohlcv, $level, $tolerance, $i);
                
                if ($touches >= 2) {
                    $levels[] = [
                        'type' => 'support',
                        'level' => $level,
                        'touches' => $touches,
                        'timestamp' => $ohlcv[$i]['timestamp']->toISOString(),
                        'strength' => min(10, $touches * 2),
                    ];
                }
            }
        }

        // Сортируем по силе
        usort($levels, fn($a, $b) => $b['strength'] <=> $a['strength']);
        
        return array_slice($levels, 0, 8);
    }

    /**
     * Детекция Fair Value Gaps
     */
    private function detectFairValueGaps(array $ohlcv): array
    {
        $gaps = [];

        for ($i = 1; $i < count($ohlcv) - 1; $i++) {
            $prev = $ohlcv[$i - 1];
            $current = $ohlcv[$i];
            $next = $ohlcv[$i + 1];

            // Бычий FVG
            if ($prev['high'] < $next['low']) {
                $gapSize = $next['low'] - $prev['high'];
                $gaps[] = [
                    'type' => 'bullish',
                    'gap_high' => $next['low'],
                    'gap_low' => $prev['high'],
                    'size' => $gapSize,
                    'timestamp' => $current['timestamp']->toISOString(),
                    'is_filled' => $this->isGapFilled($ohlcv, $i + 1, $prev['high'], $next['low'], 'bullish'),
                ];
            }

            // Медвежий FVG
            if ($prev['low'] > $next['high']) {
                $gapSize = $prev['low'] - $next['high'];
                $gaps[] = [
                    'type' => 'bearish',
                    'gap_high' => $prev['low'],
                    'gap_low' => $next['high'],
                    'size' => $gapSize,
                    'timestamp' => $current['timestamp']->toISOString(),
                    'is_filled' => $this->isGapFilled($ohlcv, $i + 1, $next['high'], $prev['low'], 'bearish'),
                ];
            }
        }

        return array_slice($gaps, -6); // Последние 6 гэпов
    }

    /**
     * Определяет биас рынка
     */
    private function determineMarketBias(array $ohlcv): array
    {
        $recentCandles = array_slice($ohlcv, -30);
        $highs = array_column($recentCandles, 'high');
        $lows = array_column($recentCandles, 'low');
        
        $currentPrice = end($ohlcv)['close'];
        $recentHigh = max($highs);
        $recentLow = min($lows);
        
        $pricePosition = ($currentPrice - $recentLow) / ($recentHigh - $recentLow);
        
        if ($pricePosition > 0.7) {
            $direction = 'bullish';
        } elseif ($pricePosition < 0.3) {
            $direction = 'bearish';
        } else {
            $direction = 'neutral';
        }

        return [
            'direction' => $direction,
            'strength' => abs($pricePosition - 0.5) * 2,
            'current_price' => $currentPrice,
            'recent_high' => $recentHigh,
            'recent_low' => $recentLow,
        ];
    }

    // Вспомогательные методы

    private function getAverageBodySize(array $ohlcv, int $index, int $periods): float
    {
        $start = max(0, $index - $periods);
        $sum = 0;
        
        for ($i = $start; $i < $index; $i++) {
            $sum += abs($ohlcv[$i]['close'] - $ohlcv[$i]['open']);
        }
        
        return $sum > 0 ? $sum / ($index - $start) : 1;
    }

    private function getAverageVolume(array $ohlcv, int $index, int $periods): float
    {
        $start = max(0, $index - $periods);
        $sum = 0;
        
        for ($i = $start; $i < $index; $i++) {
            $sum += $ohlcv[$i]['volume'];
        }
        
        return $sum > 0 ? $sum / ($index - $start) : 1;
    }

    private function findConsolidation(array $ohlcv, int $impulseIndex, int $lookback): ?array
    {
        $start = max(0, $impulseIndex - $lookback);
        $consolidationCandles = [];
        
        for ($i = $start; $i < $impulseIndex; $i++) {
            $bodySize = abs($ohlcv[$i]['close'] - $ohlcv[$i]['open']);
            $rangeSize = $ohlcv[$i]['high'] - $ohlcv[$i]['low'];
            
            if ($rangeSize > 0 && $bodySize / $rangeSize < 0.6) {
                $consolidationCandles[] = $ohlcv[$i];
            }
        }

        if (count($consolidationCandles) >= 2) {
            return [
                'high' => max(array_column($consolidationCandles, 'high')),
                'low' => min(array_column($consolidationCandles, 'low')),
                'candle_count' => count($consolidationCandles),
            ];
        }

        return null;
    }

    private function isLocalHigh(array $ohlcv, int $index): bool
    {
        $lookback = 2;
        $current = $ohlcv[$index]['high'];
        
        for ($i = max(0, $index - $lookback); $i <= min(count($ohlcv) - 1, $index + $lookback); $i++) {
            if ($i !== $index && $ohlcv[$i]['high'] >= $current) {
                return false;
            }
        }
        
        return true;
    }

    private function isLocalLow(array $ohlcv, int $index): bool
    {
        $lookback = 2;
        $current = $ohlcv[$index]['low'];
        
        for ($i = max(0, $index - $lookback); $i <= min(count($ohlcv) - 1, $index + $lookback); $i++) {
            if ($i !== $index && $ohlcv[$i]['low'] <= $current) {
                return false;
            }
        }
        
        return true;
    }

    private function countLevelTouches(array $ohlcv, float $level, float $tolerance, int $startIndex): int
    {
        $touches = 0;
        
        for ($i = $startIndex; $i < count($ohlcv); $i++) {
            if (abs($ohlcv[$i]['high'] - $level) <= $level * $tolerance ||
                abs($ohlcv[$i]['low'] - $level) <= $level * $tolerance) {
                $touches++;
            }
        }
        
        return $touches;
    }

    private function isGapFilled(array $ohlcv, int $startIndex, float $gapLow, float $gapHigh, string $type): bool
    {
        for ($i = $startIndex; $i < count($ohlcv); $i++) {
            if ($type === 'bullish' && $ohlcv[$i]['low'] <= $gapHigh) {
                return true;
            }
            if ($type === 'bearish' && $ohlcv[$i]['high'] >= $gapLow) {
                return true;
            }
        }
        
        return false;
    }
}