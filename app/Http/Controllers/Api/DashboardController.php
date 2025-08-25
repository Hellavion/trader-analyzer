<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\UserExchange;
use App\Models\MarketStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * API контроллер для данных дашборда
 */
class DashboardController extends Controller
{
    /**
     * Получает общую статистику для дашборда
     */
    public function getOverview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|string|in:7d,30d,90d,1y,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $period = $request->input('period', '30d');

        try {
            $data = [
                'connections' => $this->getConnectionsOverview($user),
                'trades' => $this->getTradesOverview($user, $period),
                'performance' => $this->getPerformanceOverview($user, $period),
                'analysis' => $this->getAnalysisOverview($user, $period),
                'market_data' => $this->getMarketDataOverview(),
                'recent_activity' => $this->getRecentActivity($user),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'period' => $period,
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate dashboard overview', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate dashboard overview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает метрики производительности для дашборда
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|string|in:1d,7d,30d,90d,1y',
            'exchange' => 'sometimes|string|in:bybit,mexc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $period = $request->input('period', '30d');

        try {
            $query = Trade::where('user_id', $user->id);

            if ($request->has('exchange')) {
                $query->where('exchange', $request->input('exchange'));
            }

            // Применяем фильтр по периоду
            if ($period !== 'all') {
                $query->where('entry_time', '>=', $this->getPeriodStartDate($period));
            }

            $trades = $query->get();
            $closedTrades = $trades->where('status', 'closed');

            $metrics = [
                'total_trades' => $trades->count(),
                'open_positions' => $trades->where('status', 'open')->count(),
                'closed_trades' => $closedTrades->count(),
                'total_pnl' => round($closedTrades->sum('pnl'), 2),
                'total_fees' => round($trades->sum('fee'), 2),
                'net_pnl' => round($closedTrades->sum('pnl') - $trades->sum('fee'), 2),
                'win_rate' => $closedTrades->count() > 0 
                    ? round(($closedTrades->where('pnl', '>', 0)->count() / $closedTrades->count()) * 100, 1)
                    : 0,
                'profit_factor' => $this->calculateProfitFactor($closedTrades),
                'sharpe_ratio' => $this->calculateSharpeRatio($closedTrades),
                'max_drawdown' => $this->calculateMaxDrawdown($closedTrades),
                'average_win' => $closedTrades->where('pnl', '>', 0)->count() > 0
                    ? round($closedTrades->where('pnl', '>', 0)->avg('pnl'), 2)
                    : 0,
                'average_loss' => $closedTrades->where('pnl', '<', 0)->count() > 0
                    ? round($closedTrades->where('pnl', '<', 0)->avg('pnl'), 2)
                    : 0,
                'largest_win' => round($closedTrades->max('pnl') ?? 0, 2),
                'largest_loss' => round($closedTrades->min('pnl') ?? 0, 2),
                'trading_volume' => round($trades->sum(function ($trade) {
                    return $trade->size * $trade->entry_price;
                }), 2),
            ];

            // Добавляем данные по периодам для сравнения
            $previousPeriodTrades = $this->getPreviousPeriodTrades($user, $period, $request->input('exchange'));
            $metrics['period_comparison'] = $this->comparePeriods($trades, $previousPeriodTrades);

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'meta' => [
                    'period' => $period,
                    'exchange' => $request->input('exchange'),
                    'trades_analyzed' => $trades->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate dashboard metrics', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает данные для виджетов дашборда
     */
    public function getWidgets(): JsonResponse
    {
        $user = Auth::user();

        try {
            $widgets = [
                'quick_stats' => $this->getQuickStats($user),
                'recent_trades' => $this->getRecentTradesWidget($user),
                'top_symbols' => $this->getTopSymbolsWidget($user),
                'exchange_breakdown' => $this->getExchangeBreakdownWidget($user),
                'smart_money_score' => $this->getSmartMoneyScoreWidget($user),
                'alerts' => $this->getAlertsWidget($user),
            ];

            return response()->json([
                'success' => true,
                'data' => $widgets
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate dashboard widgets', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate widgets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает обзор подключений к биржам
     */
    private function getConnectionsOverview($user): array
    {
        $exchanges = UserExchange::where('user_id', $user->id)->get();

        return [
            'total' => $exchanges->count(),
            'active' => $exchanges->where('is_active', true)->count(),
            'needs_sync' => $exchanges->filter(fn($e) => $e->needsSync())->count(),
            'exchanges' => $exchanges->map(function ($exchange) {
                return [
                    'name' => $exchange->exchange,
                    'display_name' => $exchange->display_name,
                    'is_active' => $exchange->is_active,
                    'last_sync' => $exchange->last_sync_at?->diffForHumans(),
                    'needs_sync' => $exchange->needsSync(),
                ];
            }),
        ];
    }

    /**
     * Получает обзор сделок
     */
    private function getTradesOverview($user, string $period): array
    {
        $query = Trade::where('user_id', $user->id);

        if ($period !== 'all') {
            $query->where('entry_time', '>=', $this->getPeriodStartDate($period));
        }

        $trades = $query->get();

        return [
            'total' => $trades->count(),
            'open' => $trades->where('status', 'open')->count(),
            'closed' => $trades->where('status', 'closed')->count(),
            'today' => $trades->where('entry_time', '>=', now()->startOfDay())->count(),
            'this_week' => $trades->where('entry_time', '>=', now()->startOfWeek())->count(),
        ];
    }

    /**
     * Получает обзор производительности
     */
    private function getPerformanceOverview($user, string $period): array
    {
        $query = Trade::where('user_id', $user->id);

        if ($period !== 'all') {
            $query->where('entry_time', '>=', $this->getPeriodStartDate($period));
        }

        $trades = $query->get();
        $closedTrades = $trades->where('status', 'closed');

        return [
            'total_pnl' => round($closedTrades->sum('pnl'), 2),
            'total_fees' => round($trades->sum('fee'), 2),
            'net_pnl' => round($closedTrades->sum('pnl') - $trades->sum('fee'), 2),
            'win_rate' => $closedTrades->count() > 0 
                ? round(($closedTrades->where('pnl', '>', 0)->count() / $closedTrades->count()) * 100, 1)
                : 0,
            'winning_trades' => $closedTrades->where('pnl', '>', 0)->count(),
            'losing_trades' => $closedTrades->where('pnl', '<', 0)->count(),
        ];
    }

    /**
     * Получает обзор анализа
     */
    private function getAnalysisOverview($user, string $period): array
    {
        $query = Trade::where('user_id', $user->id)->with('analysis');

        if ($period !== 'all') {
            $query->where('entry_time', '>=', $this->getPeriodStartDate($period));
        }

        $trades = $query->get();
        $tradesWithAnalysis = $trades->filter(fn($t) => $t->analysis !== null);

        if ($tradesWithAnalysis->isEmpty()) {
            return [
                'analyzed_trades' => 0,
                'average_score' => 0,
                'coverage' => 0,
            ];
        }

        return [
            'analyzed_trades' => $tradesWithAnalysis->count(),
            'coverage' => round(($tradesWithAnalysis->count() / $trades->count()) * 100, 1),
            'average_score' => round($tradesWithAnalysis->avg('analysis.smart_money_score'), 2),
            'high_quality_trades' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 7)->count(),
        ];
    }

    /**
     * Получает обзор рыночных данных
     */
    private function getMarketDataOverview(): array
    {
        $totalSymbols = MarketStructure::distinct('symbol')->count('symbol');
        $recentUpdate = MarketStructure::orderBy('timestamp', 'desc')->first();

        return [
            'symbols_tracked' => $totalSymbols,
            'last_update' => $recentUpdate?->timestamp?->diffForHumans(),
            'data_points' => MarketStructure::count(),
        ];
    }

    /**
     * Получает недавнюю активность
     */
    private function getRecentActivity($user): array
    {
        $recentTrades = Trade::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $activities = $recentTrades->map(function ($trade) {
            return [
                'type' => 'trade',
                'description' => ucfirst($trade->side) . ' ' . $trade->size . ' ' . $trade->symbol,
                'time' => $trade->created_at->diffForHumans(),
                'status' => $trade->status,
                'pnl' => $trade->pnl,
            ];
        })->toArray();

        // Добавляем активность синхронизации
        $recentSyncs = UserExchange::where('user_id', $user->id)
            ->whereNotNull('last_sync_at')
            ->orderBy('last_sync_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentSyncs as $sync) {
            $activities[] = [
                'type' => 'sync',
                'description' => 'Synchronized ' . ucfirst($sync->exchange) . ' data',
                'time' => $sync->last_sync_at->diffForHumans(),
                'status' => 'completed',
                'pnl' => null,
            ];
        }

        // Сортируем по времени
        usort($activities, function ($a, $b) {
            return strtotime($b['time']) <=> strtotime($a['time']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Получает быструю статистику
     */
    private function getQuickStats($user): array
    {
        $todayTrades = Trade::where('user_id', $user->id)
            ->where('entry_time', '>=', now()->startOfDay())
            ->get();

        return [
            'today_trades' => $todayTrades->count(),
            'today_pnl' => round($todayTrades->sum('pnl'), 2),
            'open_positions' => Trade::where('user_id', $user->id)
                ->where('status', 'open')
                ->count(),
            'active_exchanges' => UserExchange::where('user_id', $user->id)
                ->where('is_active', true)
                ->count(),
        ];
    }

    /**
     * Получает виджет недавних сделок
     */
    private function getRecentTradesWidget($user): array
    {
        $trades = Trade::where('user_id', $user->id)
            ->orderBy('entry_time', 'desc')
            ->limit(10)
            ->get();

        return $trades->map(function ($trade) {
            return [
                'id' => $trade->id,
                'symbol' => $trade->symbol,
                'side' => $trade->side,
                'size' => (float) $trade->size,
                'entry_price' => (float) $trade->entry_price,
                'pnl' => $trade->pnl ? (float) $trade->pnl : null,
                'status' => $trade->status,
                'entry_time' => $trade->entry_time->format('M j, H:i'),
            ];
        })->toArray();
    }

    /**
     * Получает виджет топ символов
     */
    private function getTopSymbolsWidget($user): array
    {
        $symbols = Trade::where('user_id', $user->id)
            ->where('entry_time', '>=', now()->subDays(30))
            ->select('symbol')
            ->selectRaw('COUNT(*) as trades_count')
            ->selectRaw('SUM(pnl) as total_pnl')
            ->selectRaw('SUM(size * entry_price) as volume')
            ->groupBy('symbol')
            ->orderBy('trades_count', 'desc')
            ->limit(5)
            ->get();

        return $symbols->map(function ($symbol) {
            return [
                'symbol' => $symbol->symbol,
                'trades' => $symbol->trades_count,
                'pnl' => round($symbol->total_pnl, 2),
                'volume' => round($symbol->volume, 2),
            ];
        })->toArray();
    }

    /**
     * Получает виджет распределения по биржам
     */
    private function getExchangeBreakdownWidget($user): array
    {
        $exchanges = Trade::where('user_id', $user->id)
            ->where('entry_time', '>=', now()->subDays(30))
            ->select('exchange')
            ->selectRaw('COUNT(*) as trades_count')
            ->selectRaw('SUM(pnl) as total_pnl')
            ->groupBy('exchange')
            ->get();

        return $exchanges->map(function ($exchange) {
            return [
                'exchange' => $exchange->exchange,
                'trades' => $exchange->trades_count,
                'pnl' => round($exchange->total_pnl, 2),
            ];
        })->toArray();
    }

    /**
     * Получает виджет Smart Money score
     */
    private function getSmartMoneyScoreWidget($user): array
    {
        $trades = Trade::where('user_id', $user->id)
            ->with('analysis')
            ->where('entry_time', '>=', now()->subDays(30))
            ->get();

        $tradesWithAnalysis = $trades->filter(fn($t) => $t->analysis !== null);

        if ($tradesWithAnalysis->isEmpty()) {
            return [
                'average_score' => 0,
                'trend' => 'neutral',
                'distribution' => [],
            ];
        }

        $averageScore = $tradesWithAnalysis->avg('analysis.smart_money_score');
        
        return [
            'average_score' => round($averageScore, 1),
            'trend' => $this->calculateScoreTrend($tradesWithAnalysis),
            'distribution' => [
                'excellent' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 8)->count(),
                'good' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 6 && $t->analysis->smart_money_score < 8)->count(),
                'average' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 4 && $t->analysis->smart_money_score < 6)->count(),
                'poor' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score < 4)->count(),
            ],
        ];
    }

    /**
     * Получает виджет алертов
     */
    private function getAlertsWidget($user): array
    {
        $alerts = [];

        // Проверяем подключения, которые нуждаются в синхронизации
        $needsSyncCount = UserExchange::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn($e) => $e->needsSync())
            ->count();

        if ($needsSyncCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$needsSyncCount} exchange(s) need synchronization",
                'action' => 'sync_exchanges',
            ];
        }

        // Проверяем неактивные подключения
        $inactiveCount = UserExchange::where('user_id', $user->id)
            ->where('is_active', false)
            ->count();

        if ($inactiveCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$inactiveCount} inactive exchange connection(s)",
                'action' => 'check_connections',
            ];
        }

        return $alerts;
    }

    /**
     * Вспомогательные методы
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        return match ($period) {
            '1d' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };
    }

    private function getPreviousPeriodTrades($user, string $period, ?string $exchange = null)
    {
        $periodDays = match ($period) {
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };

        $query = Trade::where('user_id', $user->id)
            ->whereBetween('entry_time', [
                now()->subDays($periodDays * 2),
                now()->subDays($periodDays)
            ]);

        if ($exchange) {
            $query->where('exchange', $exchange);
        }

        return $query->get();
    }

    private function comparePeriods($currentTrades, $previousTrades): array
    {
        $currentPnl = $currentTrades->sum('pnl');
        $previousPnl = $previousTrades->sum('pnl');
        
        $pnlChange = $previousPnl != 0 ? (($currentPnl - $previousPnl) / abs($previousPnl)) * 100 : 0;

        return [
            'trades_change' => $currentTrades->count() - $previousTrades->count(),
            'pnl_change' => round($pnlChange, 1),
            'win_rate_change' => $this->calculateWinRateChange($currentTrades, $previousTrades),
        ];
    }

    private function calculateWinRateChange($currentTrades, $previousTrades): float
    {
        $currentWinRate = $currentTrades->where('status', 'closed')->count() > 0
            ? ($currentTrades->where('pnl', '>', 0)->count() / $currentTrades->where('status', 'closed')->count()) * 100
            : 0;

        $previousWinRate = $previousTrades->where('status', 'closed')->count() > 0
            ? ($previousTrades->where('pnl', '>', 0)->count() / $previousTrades->where('status', 'closed')->count()) * 100
            : 0;

        return round($currentWinRate - $previousWinRate, 1);
    }

    private function calculateProfitFactor($trades): float
    {
        $grossProfit = $trades->where('pnl', '>', 0)->sum('pnl');
        $grossLoss = abs($trades->where('pnl', '<', 0)->sum('pnl'));

        return $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : 0;
    }

    private function calculateSharpeRatio($trades): float
    {
        if ($trades->count() < 2) return 0;

        $returns = $trades->pluck('pnl')->toArray();
        $avgReturn = array_sum($returns) / count($returns);
        
        $variance = 0;
        foreach ($returns as $return) {
            $variance += pow($return - $avgReturn, 2);
        }
        $variance /= count($returns) - 1;
        $stdDev = sqrt($variance);

        return $stdDev > 0 ? round($avgReturn / $stdDev, 2) : 0;
    }

    private function calculateMaxDrawdown($trades): float
    {
        if ($trades->isEmpty()) return 0;

        $sortedTrades = $trades->sortBy('entry_time');
        $runningPnl = 0;
        $peak = 0;
        $maxDrawdown = 0;

        foreach ($sortedTrades as $trade) {
            $runningPnl += $trade->pnl;
            if ($runningPnl > $peak) {
                $peak = $runningPnl;
            }
            $drawdown = $peak - $runningPnl;
            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
            }
        }

        return round($maxDrawdown, 2);
    }

    private function calculateScoreTrend($trades): string
    {
        $recentTrades = $trades->sortBy('entry_time')->values();
        if ($recentTrades->count() < 10) return 'neutral';

        $firstHalf = $recentTrades->take($recentTrades->count() / 2);
        $secondHalf = $recentTrades->skip($recentTrades->count() / 2);

        $firstAvg = $firstHalf->avg('analysis.smart_money_score');
        $secondAvg = $secondHalf->avg('analysis.smart_money_score');

        $difference = $secondAvg - $firstAvg;

        if ($difference > 0.5) return 'improving';
        if ($difference < -0.5) return 'declining';
        return 'stable';
    }
}