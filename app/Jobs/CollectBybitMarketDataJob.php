<?php

namespace App\Jobs;

use App\Models\MarketStructure;
use App\Services\Exchange\BybitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Job для сбора рыночных данных с Bybit для анализа Smart Money
 */
class CollectBybitMarketDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    private array $symbols;
    private array $timeframes;
    private BybitService $bybitService;

    public function __construct(array $symbols = [], array $timeframes = [])
    {
        $this->symbols = $symbols ?: [
            'BTCUSDT', 'ETHUSDT', 'ADAUSDT', 'DOTUSDT', 'LINKUSDT',
            'BNBUSDT', 'XRPUSDT', 'SOLUSDT', 'MATICUSDT', 'AVAXUSDT'
        ];
        
        $this->timeframes = $timeframes ?: [
            '1' => '1m',
            '5' => '5m', 
            '15' => '15m',
            '60' => '1h',
            '240' => '4h',
            'D' => '1D'
        ];

        $this->bybitService = new BybitService();
    }

    public function handle(): void
    {
        Log::info('Starting Bybit market data collection', [
            'symbols_count' => count($this->symbols),
            'timeframes_count' => count($this->timeframes),
        ]);

        try {
            foreach ($this->symbols as $symbol) {
                foreach ($this->timeframes as $interval => $timeframeName) {
                    $this->collectMarketDataForSymbol($symbol, $interval, $timeframeName);
                    
                    // Пауза между запросами для соблюдения rate limit
                    usleep(200000); // 200ms
                }
            }

            Log::info('Bybit market data collection completed successfully');

        } catch (\Exception $e) {
            Log::error('Bybit market data collection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Собирает рыночные данные для конкретного символа и таймфрейма
     */
    private function collectMarketDataForSymbol(string $symbol, string $interval, string $timeframeName): void
    {
        try {
            // Получаем данные свечей за последние 200 периодов
            $klineData = $this->bybitService->getKlineData(
                category: 'linear',
                symbol: $symbol,
                interval: $interval,
                limit: 200
            );

            if (empty($klineData)) {
                Log::warning('No kline data received', ['symbol' => $symbol, 'timeframe' => $timeframeName]);
                return;
            }

            // Преобразуем данные Bybit в OHLCV массив
            $ohlcvData = $this->transformKlineData($klineData);
            
            // Анализируем структуру рынка
            $marketStructure = $this->analyzeMarketStructure($ohlcvData, $symbol, $timeframeName);
            
            // Сохраняем данные в базу
            $this->saveMarketStructure($marketStructure, $symbol, $timeframeName);

            Log::debug('Market data collected and analyzed', [
                'symbol' => $symbol,
                'timeframe' => $timeframeName,
                'candles_count' => count($ohlcvData),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to collect market data for symbol', [
                'symbol' => $symbol,
                'timeframe' => $timeframeName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Преобразует данные kline от Bybit в удобный формат OHLCV
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

        // Сортируем по времени (от старых к новым)
        usort($ohlcv, fn($a, $b) => $a['timestamp']->timestamp <=> $b['timestamp']->timestamp);

        return $ohlcv;
    }

    /**
     * Анализирует структуру рынка на основе OHLCV данных
     */
    private function analyzeMarketStructure(array $ohlcvData, string $symbol, string $timeframe): array
    {
        $structure = [
            'order_blocks' => $this->detectOrderBlocks($ohlcvData),
            'liquidity_levels' => $this->detectLiquidityLevels($ohlcvData),
            'fvg_zones' => $this->detectFairValueGaps($ohlcvData),
            'market_bias' => $this->determineMarketBias($ohlcvData),
            'high' => max(array_column($ohlcvData, 'high')),
            'low' => min(array_column($ohlcvData, 'low')),
        ];

        return $structure;
    }

    /**
     * Простая детекция Order Blocks
     */
    private function detectOrderBlocks(array $ohlcv): array
    {
        $blocks = [];
        $lookback = 10;

        for ($i = $lookback; $i < count($ohlcv) - $lookback; $i++) {
            $current = $ohlcv[$i];
            
            // Ищем импульсные свечи
            $bodySize = abs($current['close'] - $current['open']);
            $avgBodySize = $this->getAverageBodySize($ohlcv, $i, $lookback);
            
            // Если тело свечи в 2 раза больше среднего
            if ($bodySize > $avgBodySize * 2) {
                $isLastBearish = $this->isLastBearishBeforeMove($ohlcv, $i);
                $isLastBullish = $this->isLastBullishBeforeMove($ohlcv, $i);
                
                if ($isLastBearish || $isLastBullish) {
                    $blocks[] = [
                        'type' => $current['close'] > $current['open'] ? 'bullish' : 'bearish',
                        'high' => $current['high'],
                        'low' => $current['low'],
                        'timestamp' => $current['timestamp']->toISOString(),
                        'is_active' => true,
                        'strength' => min(10, $bodySize / $avgBodySize),
                    ];
                }
            }
        }

        return array_slice($blocks, -20); // Оставляем последние 20 блоков
    }

    /**
     * Детекция уровней ликвидности
     */
    private function detectLiquidityLevels(array $ohlcv): array
    {
        $levels = [];
        $tolerance = 0.002; // 0.2% допуск

        // Ищем равные максимумы и минимумы
        for ($i = 2; $i < count($ohlcv) - 2; $i++) {
            if ($this->isLocalHigh($ohlcv, $i)) {
                $level = $ohlcv[$i]['high'];
                $touches = $this->findTouchesAtLevel($ohlcv, $level, $tolerance, $i);
                
                if (count($touches) >= 2) {
                    $levels[] = [
                        'type' => 'resistance',
                        'level' => $level,
                        'touches' => count($touches),
                        'first_touch' => $ohlcv[$touches[0]]['timestamp']->toISOString(),
                        'last_touch' => $ohlcv[$touches[count($touches) - 1]]['timestamp']->toISOString(),
                        'strength' => min(10, count($touches) * 2),
                    ];
                }
            }

            if ($this->isLocalLow($ohlcv, $i)) {
                $level = $ohlcv[$i]['low'];
                $touches = $this->findTouchesAtLevel($ohlcv, $level, $tolerance, $i);
                
                if (count($touches) >= 2) {
                    $levels[] = [
                        'type' => 'support',
                        'level' => $level,
                        'touches' => count($touches),
                        'first_touch' => $ohlcv[$touches[0]]['timestamp']->toISOString(),
                        'last_touch' => $ohlcv[$touches[count($touches) - 1]]['timestamp']->toISOString(),
                        'strength' => min(10, count($touches) * 2),
                    ];
                }
            }
        }

        return array_slice($levels, -15); // Оставляем последние 15 уровней
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
                $gaps[] = [
                    'type' => 'bullish',
                    'gap_high' => $next['low'],
                    'gap_low' => $prev['high'],
                    'timestamp' => $current['timestamp']->toISOString(),
                    'is_filled' => $this->isGapFilled($ohlcv, $i + 1, $prev['high'], $next['low'], 'bullish'),
                    'size' => $next['low'] - $prev['high'],
                ];
            }

            // Медвежий FVG
            if ($prev['low'] > $next['high']) {
                $gaps[] = [
                    'type' => 'bearish',
                    'gap_high' => $prev['low'],
                    'gap_low' => $next['high'],
                    'timestamp' => $current['timestamp']->toISOString(),
                    'is_filled' => $this->isGapFilled($ohlcv, $i + 1, $next['high'], $prev['low'], 'bearish'),
                    'size' => $prev['low'] - $next['high'],
                ];
            }
        }

        return array_slice($gaps, -10); // Оставляем последние 10 гэпов
    }

    /**
     * Определяет биас рынка
     */
    private function determineMarketBias(array $ohlcv): array
    {
        $recentCandles = array_slice($ohlcv, -20);
        $highs = array_column($recentCandles, 'high');
        $lows = array_column($recentCandles, 'low');
        
        $recentHigh = max($highs);
        $recentLow = min($lows);
        $currentPrice = end($ohlcv)['close'];
        
        $bullishStrength = ($currentPrice - $recentLow) / ($recentHigh - $recentLow);
        
        if ($bullishStrength > 0.7) {
            $direction = 'bullish';
        } elseif ($bullishStrength < 0.3) {
            $direction = 'bearish';
        } else {
            $direction = 'neutral';
        }

        return [
            'direction' => $direction,
            'strength' => $bullishStrength,
            'current_price' => $currentPrice,
            'recent_high' => $recentHigh,
            'recent_low' => $recentLow,
        ];
    }

    /**
     * Сохраняет структуру рынка в базу данных
     */
    private function saveMarketStructure(array $structure, string $symbol, string $timeframe): void
    {
        MarketStructure::create([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'timestamp' => now(),
            'order_blocks' => $structure['order_blocks'],
            'liquidity_levels' => $structure['liquidity_levels'],
            'fvg_zones' => $structure['fvg_zones'],
            'market_bias' => $structure['market_bias'],
            'high' => $structure['high'],
            'low' => $structure['low'],
        ]);
    }

    // Вспомогательные методы для анализа

    private function getAverageBodySize(array $ohlcv, int $index, int $periods): float
    {
        $start = max(0, $index - $periods);
        $sum = 0;
        
        for ($i = $start; $i < $index; $i++) {
            $sum += abs($ohlcv[$i]['close'] - $ohlcv[$i]['open']);
        }
        
        return $sum / ($index - $start);
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

    private function findTouchesAtLevel(array $ohlcv, float $level, float $tolerance, int $startIndex): array
    {
        $touches = [];
        
        for ($i = $startIndex; $i < count($ohlcv); $i++) {
            if (abs($ohlcv[$i]['high'] - $level) <= $level * $tolerance ||
                abs($ohlcv[$i]['low'] - $level) <= $level * $tolerance) {
                $touches[] = $i;
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

    private function isLastBearishBeforeMove(array $ohlcv, int $impulseIndex): bool
    {
        for ($i = $impulseIndex - 1; $i >= max(0, $impulseIndex - 5); $i--) {
            if ($ohlcv[$i]['close'] < $ohlcv[$i]['open']) {
                return true;
            }
        }
        return false;
    }

    private function isLastBullishBeforeMove(array $ohlcv, int $impulseIndex): bool
    {
        for ($i = $impulseIndex - 1; $i >= max(0, $impulseIndex - 5); $i--) {
            if ($ohlcv[$i]['close'] > $ohlcv[$i]['open']) {
                return true;
            }
        }
        return false;
    }
}