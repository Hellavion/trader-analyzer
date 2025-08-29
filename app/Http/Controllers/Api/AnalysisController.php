<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketStructure;
use App\Models\Trade;
use App\Models\TradeAnalysis;
use App\Models\UserExchange;
use App\Jobs\CollectBybitMarketDataJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * API контроллер для управления аналитикой и рыночными данными
 */
class AnalysisController extends Controller
{
    /**
     * Получает аналитические данные по рыночной структуре
     */
    public function getMarketStructure(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'timeframe' => 'sometimes|string|in:1m,5m,15m,1h,4h,1D',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = MarketStructure::where('symbol', $request->input('symbol'));

            if ($request->has('timeframe')) {
                $query->where('timeframe', $request->input('timeframe'));
            }

            if ($request->has('start_date')) {
                $query->where('timestamp', '>=', Carbon::parse($request->input('start_date')));
            }

            if ($request->has('end_date')) {
                $query->where('timestamp', '<=', Carbon::parse($request->input('end_date'))->endOfDay());
            }

            $limit = $request->input('limit', 50);
            $structures = $query->orderBy('timestamp', 'desc')->limit($limit)->get();

            $formattedStructures = $structures->map(function ($structure) {
                return [
                    'id' => $structure->id,
                    'symbol' => $structure->symbol,
                    'timeframe' => $structure->timeframe,
                    'timestamp' => $structure->timestamp->toISOString(),
                    'order_blocks' => $structure->order_blocks,
                    'liquidity_levels' => $structure->liquidity_levels,
                    'fvg_zones' => $structure->fvg_zones,
                    'market_bias' => $structure->market_bias,
                    'high' => (float) $structure->high,
                    'low' => (float) $structure->low,
                    'created_at' => $structure->created_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedStructures,
                'meta' => [
                    'symbol' => $request->input('symbol'),
                    'timeframe' => $request->input('timeframe'),
                    'count' => $structures->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch market structure', [
                'symbol' => $request->input('symbol'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch market structure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает последние Order Blocks для символа
     */
    public function getOrderBlocks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'timeframe' => 'sometimes|string|in:1m,5m,15m,1h,4h,1D',
            'active_only' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = MarketStructure::where('symbol', $request->input('symbol'));

            if ($request->has('timeframe')) {
                $query->where('timeframe', $request->input('timeframe'));
            }

            $latestStructure = $query->orderBy('timestamp', 'desc')->first();

            if (!$latestStructure) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No market structure data found for this symbol'
                ]);
            }

            $orderBlocks = $latestStructure->order_blocks ?: [];

            // Фильтруем только активные блоки если запрашивается
            if ($request->boolean('active_only', false)) {
                $orderBlocks = array_filter($orderBlocks, function ($block) {
                    return $block['is_active'] ?? false;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $orderBlocks,
                'meta' => [
                    'symbol' => $request->input('symbol'),
                    'timeframe' => $latestStructure->timeframe,
                    'timestamp' => $latestStructure->timestamp->toISOString(),
                    'total_blocks' => count($orderBlocks),
                    'active_blocks' => count(array_filter($orderBlocks, fn($b) => $b['is_active'] ?? false)),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch order blocks', [
                'symbol' => $request->input('symbol'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order blocks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает Fair Value Gaps для символа
     */
    public function getFairValueGaps(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'timeframe' => 'sometimes|string|in:1m,5m,15m,1h,4h,1D',
            'unfilled_only' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = MarketStructure::where('symbol', $request->input('symbol'));

            if ($request->has('timeframe')) {
                $query->where('timeframe', $request->input('timeframe'));
            }

            $latestStructure = $query->orderBy('timestamp', 'desc')->first();

            if (!$latestStructure) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No market structure data found for this symbol'
                ]);
            }

            $fvgZones = $latestStructure->fvg_zones ?: [];

            // Фильтруем только незаполненные гэпы если запрашивается
            if ($request->boolean('unfilled_only', false)) {
                $fvgZones = array_filter($fvgZones, function ($gap) {
                    return !($gap['is_filled'] ?? true);
                });
            }

            return response()->json([
                'success' => true,
                'data' => $fvgZones,
                'meta' => [
                    'symbol' => $request->input('symbol'),
                    'timeframe' => $latestStructure->timeframe,
                    'timestamp' => $latestStructure->timestamp->toISOString(),
                    'total_gaps' => count($fvgZones),
                    'unfilled_gaps' => count(array_filter($fvgZones, fn($g) => !($g['is_filled'] ?? true))),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch FVG zones', [
                'symbol' => $request->input('symbol'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch FVG zones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает уровни ликвидности для символа
     */
    public function getLiquidityLevels(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'timeframe' => 'sometimes|string|in:1m,5m,15m,1h,4h,1D',
            'min_strength' => 'sometimes|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = MarketStructure::where('symbol', $request->input('symbol'));

            if ($request->has('timeframe')) {
                $query->where('timeframe', $request->input('timeframe'));
            }

            $latestStructure = $query->orderBy('timestamp', 'desc')->first();

            if (!$latestStructure) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No market structure data found for this symbol'
                ]);
            }

            $liquidityLevels = $latestStructure->liquidity_levels ?: [];

            // Фильтруем по минимальной силе уровня если указано
            if ($request->has('min_strength')) {
                $minStrength = $request->input('min_strength');
                $liquidityLevels = array_filter($liquidityLevels, function ($level) use ($minStrength) {
                    return ($level['strength'] ?? 0) >= $minStrength;
                });
            }

            // Сортируем по силе уровня
            usort($liquidityLevels, function ($a, $b) {
                return ($b['strength'] ?? 0) <=> ($a['strength'] ?? 0);
            });

            return response()->json([
                'success' => true,
                'data' => $liquidityLevels,
                'meta' => [
                    'symbol' => $request->input('symbol'),
                    'timeframe' => $latestStructure->timeframe,
                    'timestamp' => $latestStructure->timestamp->toISOString(),
                    'total_levels' => count($liquidityLevels),
                    'strong_levels' => count(array_filter($liquidityLevels, fn($l) => ($l['strength'] ?? 0) >= 7)),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch liquidity levels', [
                'symbol' => $request->input('symbol'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch liquidity levels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Запускает сбор рыночных данных для анализа
     */
    public function collectMarketData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbols' => 'sometimes|array',
            'symbols.*' => 'string',
            'timeframes' => 'sometimes|array',
            'force' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $symbols = $request->input('symbols', []);
            $timeframes = $request->input('timeframes', []);

            // Запускаем job сбора данных
            CollectBybitMarketDataJob::dispatch($symbols, $timeframes)->onQueue('low');

            Log::info('Market data collection initiated', [
                'user_id' => Auth::id(),
                'symbols' => $symbols,
                'timeframes' => $timeframes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Market data collection started',
                'data' => [
                    'symbols' => $symbols ?: 'default symbols',
                    'timeframes' => $timeframes ?: 'default timeframes',
                    'job_queued' => true,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start market data collection', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start market data collection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает аналитический отчет по сделкам пользователя
     */
    public function getTradeAnalysisReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|string|in:7d,30d,90d,1y,all',
            'exchange' => 'sometimes|string|in:bybit,mexc',
            'symbol' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            $query = Trade::where('user_id', $user->id)
                ->with(['analysis']);

            // Применяем фильтры
            if ($request->has('exchange')) {
                $query->where('exchange', $request->input('exchange'));
            }

            if ($request->has('symbol')) {
                $query->where('symbol', $request->input('symbol'));
            }

            // Применяем фильтр по периоду
            if ($request->has('period')) {
                $period = $request->input('period');
                if ($period !== 'all') {
                    $query->where('entry_time', '>=', $this->getPeriodStartDate($period));
                }
            } else {
                $query->where('entry_time', '>=', now()->subDays(30));
            }

            $trades = $query->get();
            $tradesWithAnalysis = $trades->filter(fn($t) => $t->analysis !== null);

            $report = [
                'overview' => [
                    'total_trades' => $trades->count(),
                    'analyzed_trades' => $tradesWithAnalysis->count(),
                    'analysis_coverage' => $trades->count() > 0 
                        ? round(($tradesWithAnalysis->count() / $trades->count()) * 100, 1) 
                        : 0,
                ],
                'smart_money_analysis' => $this->getSmartMoneyAnalysis($tradesWithAnalysis),
                'pattern_analysis' => $this->getPatternAnalysis($tradesWithAnalysis),
                'quality_analysis' => $this->getQualityAnalysis($tradesWithAnalysis),
                'recommendations' => $this->getRecommendations($tradesWithAnalysis),
                'market_structure_insights' => $this->getMarketStructureInsights($tradesWithAnalysis),
                'performance_correlation' => $this->getPerformanceCorrelation($tradesWithAnalysis),
                'pnl_timeline' => $this->getPnlTimeline($trades, $request->input('period', '30d')),
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
                'meta' => [
                    'period' => $request->input('period', '30d'),
                    'generated_at' => now()->toISOString(),
                    'filters' => [
                        'exchange' => $request->input('exchange'),
                        'symbol' => $request->input('symbol'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate trade analysis report', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analysis report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает список доступных символов для анализа
     */
    public function getAvailableSymbols(): JsonResponse
    {
        try {
            $symbols = MarketStructure::select('symbol')
                ->distinct()
                ->orderBy('symbol')
                ->pluck('symbol');

            $symbolsWithInfo = $symbols->map(function ($symbol) {
                $latestData = MarketStructure::where('symbol', $symbol)
                    ->orderBy('timestamp', 'desc')
                    ->first();

                return [
                    'symbol' => $symbol,
                    'last_updated' => $latestData?->timestamp?->toISOString(),
                    'timeframes' => MarketStructure::where('symbol', $symbol)
                        ->distinct()
                        ->pluck('timeframe')
                        ->toArray(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $symbolsWithInfo,
                'meta' => [
                    'total_symbols' => $symbols->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch available symbols', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available symbols: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает начальную дату для периода
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };
    }

    /**
     * Анализирует Smart Money показатели
     */
    private function getSmartMoneyAnalysis($trades): array
    {
        if ($trades->isEmpty()) {
            return [
                'average_score' => 0,
                'score_distribution' => [],
                'trends' => [],
            ];
        }

        $scores = $trades->pluck('analysis.smart_money_score')->filter();

        return [
            'average_score' => round($scores->avg(), 2),
            'max_score' => $scores->max(),
            'min_score' => $scores->min(),
            'score_distribution' => [
                'excellent' => $scores->filter(fn($s) => $s >= 8)->count(),
                'good' => $scores->filter(fn($s) => $s >= 6 && $s < 8)->count(),
                'average' => $scores->filter(fn($s) => $s >= 4 && $s < 6)->count(),
                'poor' => $scores->filter(fn($s) => $s < 4)->count(),
            ],
            'score_vs_pnl_correlation' => $this->calculateCorrelation(
                $trades->pluck('analysis.smart_money_score')->toArray(),
                $trades->pluck('pnl')->toArray()
            ),
        ];
    }

    /**
     * Анализирует паттерны в сделках
     */
    private function getPatternAnalysis($trades): array
    {
        if ($trades->isEmpty()) {
            return ['patterns' => [], 'insights' => []];
        }

        $patterns = [];
        foreach ($trades as $trade) {
            if ($trade->analysis && $trade->analysis->patterns) {
                foreach ($trade->analysis->patterns as $pattern) {
                    $patterns[] = $pattern;
                }
            }
        }

        $patternCounts = array_count_values($patterns);
        arsort($patternCounts);

        return [
            'most_common_patterns' => array_slice($patternCounts, 0, 10, true),
            'total_patterns_detected' => count($patterns),
            'unique_patterns' => count($patternCounts),
        ];
    }

    /**
     * Анализирует качество входов и выходов
     */
    private function getQualityAnalysis($trades): array
    {
        if ($trades->isEmpty()) {
            return [];
        }

        $entryQualities = $trades->pluck('analysis.entry_quality')->filter();
        $exitQualities = $trades->pluck('analysis.exit_quality')->filter();

        return [
            'entry_analysis' => [
                'average_quality' => round($entryQualities->avg(), 2),
                'distribution' => $this->getQualityDistribution($entryQualities),
            ],
            'exit_analysis' => [
                'average_quality' => round($exitQualities->avg(), 2),
                'distribution' => $this->getQualityDistribution($exitQualities),
            ],
        ];
    }

    /**
     * Генерирует рекомендации на основе анализа
     */
    private function getRecommendations($trades): array
    {
        if ($trades->isEmpty()) {
            return [];
        }

        $recommendations = [];

        // Анализ качества входов
        $lowQualityEntries = $trades->filter(fn($t) => ($t->analysis->entry_quality ?? 0) < 5)->count();
        if ($lowQualityEntries > $trades->count() * 0.3) {
            $recommendations[] = [
                'type' => 'entry_improvement',
                'title' => 'Улучшите качество входов',
                'description' => 'Более 30% ваших сделок имеют низкое качество входа. Рассмотрите ожидание лучших сетапов.',
                'priority' => 'high',
            ];
        }

        // Анализ Smart Money score
        $avgScore = $trades->avg('analysis.smart_money_score');
        if ($avgScore < 5) {
            $recommendations[] = [
                'type' => 'smart_money_approach',
                'title' => 'Изучите концепции Smart Money',
                'description' => 'Ваш средний Smart Money score низкий. Изучите Order Blocks и FVG для улучшения торговли.',
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }

    /**
     * Получает инсайты по рыночной структуре
     */
    private function getMarketStructureInsights($trades): array
    {
        if ($trades->isEmpty()) {
            return [];
        }

        $biasData = [];
        foreach ($trades as $trade) {
            if ($trade->analysis && $trade->analysis->market_structure_bias) {
                $biasData[] = $trade->analysis->market_structure_bias;
            }
        }

        return [
            'bias_distribution' => array_count_values($biasData),
            'structure_alignment' => [
                'total_analyzed' => count($biasData),
                'aligned_trades' => 0, // TODO: Calculate based on actual alignment
            ],
        ];
    }

    /**
     * Анализирует корреляцию между качеством анализа и производительностью
     */
    private function getPerformanceCorrelation($trades): array
    {
        if ($trades->isEmpty()) {
            return [];
        }

        $scores = $trades->pluck('analysis.smart_money_score')->toArray();
        $pnls = $trades->pluck('pnl')->toArray();
        $entryQualities = $trades->pluck('analysis.entry_quality')->toArray();

        return [
            'score_vs_pnl' => $this->calculateCorrelation($scores, $pnls),
            'entry_quality_vs_pnl' => $this->calculateCorrelation($entryQualities, $pnls),
        ];
    }

    /**
     * Получает распределение качества
     */
    private function getQualityDistribution($qualities): array
    {
        return [
            'excellent' => $qualities->filter(fn($q) => $q >= 8)->count(),
            'good' => $qualities->filter(fn($q) => $q >= 6 && $q < 8)->count(),
            'average' => $qualities->filter(fn($q) => $q >= 4 && $q < 6)->count(),
            'poor' => $qualities->filter(fn($q) => $q < 4)->count(),
        ];
    }

    /**
     * Получает временную линию P&L для графика
     */
    private function getPnlTimeline($trades, string $period): array
    {
        if ($trades->isEmpty()) {
            return [];
        }

        // Сортируем сделки по времени входа
        $sortedTrades = $trades->sortBy('entry_time');
        
        // Определяем формат периодов на основе выбранного периода
        $format = match ($period) {
            '7d' => 'Y-m-d', // По дням для 7 дней
            '30d' => 'Y-m-d', // По дням для 30 дней  
            '90d' => 'Y-W', // По неделям для 90 дней
            '1y' => 'Y-m', // По месяцам для года
            'all' => 'Y-m', // По месяцам для всего времени
            default => 'Y-m-d'
        };

        $labelFormat = match ($period) {
            '7d' => 'M j', // "Jan 15"
            '30d' => 'M j', // "Jan 15"
            '90d' => '\WW Y', // "W15 2024"
            '1y' => 'M Y', // "Jan 2024"
            'all' => 'M Y', // "Jan 2024"
            default => 'M j'
        };

        // Группируем сделки по периодам и суммируем P&L
        $groupedData = [];
        $runningPnl = 0;

        foreach ($sortedTrades as $trade) {
            $periodKey = $trade->entry_time->format($format);
            $label = $trade->entry_time->format($labelFormat);
            
            if (!isset($groupedData[$periodKey])) {
                $groupedData[$periodKey] = [
                    'period' => $label,
                    'pnl' => 0,
                    'trades' => 0,
                    'date' => $trade->entry_time->format('Y-m-d')
                ];
            }
            
            $groupedData[$periodKey]['pnl'] += (float) $trade->pnl;
            $groupedData[$periodKey]['trades']++;
        }

        // Конвертируем в массив и сортируем по дате
        $timeline = array_values($groupedData);
        usort($timeline, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        // Если данных мало, дополняем моковыми для лучшего отображения
        if (count($timeline) < 5 && $period === '7d') {
            $timeline = $this->fillMissingDays($timeline, 7);
        } elseif (count($timeline) < 4 && $period === '30d') {
            $timeline = $this->fillMissingDays($timeline, 30);
        }

        return $timeline;
    }

    /**
     * Заполняет недостающие дни для лучшего отображения графика
     */
    private function fillMissingDays(array $existingData, int $days): array
    {
        $result = [];
        $startDate = now()->subDays($days);
        
        for ($i = 0; $i < $days; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dateKey = $currentDate->format('Y-m-d');
            
            // Ищем существующие данные для этой даты
            $existingDay = collect($existingData)->firstWhere('date', $dateKey);
            
            if ($existingDay) {
                $result[] = $existingDay;
            } else {
                $result[] = [
                    'period' => $currentDate->format('M j'),
                    'pnl' => 0,
                    'trades' => 0,
                    'date' => $dateKey
                ];
            }
        }
        
        return $result;
    }

    /**
     * Вычисляет корреляцию между двумя массивами
     */
    private function calculateCorrelation(array $x, array $y): float
    {
        if (count($x) !== count($y) || count($x) < 2) {
            return 0;
        }

        $x = array_filter($x, 'is_numeric');
        $y = array_filter($y, 'is_numeric');

        if (count($x) !== count($y) || count($x) < 2) {
            return 0;
        }

        $meanX = array_sum($x) / count($x);
        $meanY = array_sum($y) / count($y);

        $numerator = 0;
        $sumXSquare = 0;
        $sumYSquare = 0;

        for ($i = 0; $i < count($x); $i++) {
            $numerator += ($x[$i] - $meanX) * ($y[$i] - $meanY);
            $sumXSquare += pow($x[$i] - $meanX, 2);
            $sumYSquare += pow($y[$i] - $meanY, 2);
        }

        $denominator = sqrt($sumXSquare * $sumYSquare);

        return $denominator == 0 ? 0 : round($numerator / $denominator, 3);
    }
}