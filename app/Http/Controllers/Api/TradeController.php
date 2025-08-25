<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\TradeAnalysis;
use App\Models\UserExchange;
use App\Jobs\SyncBybitTradesJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * API контроллер для управления сделками и торговыми данными
 */
class TradeController extends Controller
{
    /**
     * Получает список сделок пользователя с фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exchange' => 'sometimes|string|in:bybit,mexc',
            'symbol' => 'sometimes|string',
            'side' => 'sometimes|string|in:buy,sell',
            'status' => 'sometimes|string|in:open,closed',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'limit' => 'sometimes|integer|min:1|max:200',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|string|in:entry_time,exit_time,pnl,size,created_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $limit = $request->input('limit', 50);

        try {
            $query = Trade::where('user_id', $user->id)
                ->with(['analysis']);

            // Применяем фильтры
            if ($request->has('exchange')) {
                $query->where('exchange', $request->input('exchange'));
            }

            if ($request->has('symbol')) {
                $query->where('symbol', 'like', '%' . $request->input('symbol') . '%');
            }

            if ($request->has('side')) {
                $query->where('side', $request->input('side'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('start_date')) {
                $query->where('entry_time', '>=', Carbon::parse($request->input('start_date')));
            }

            if ($request->has('end_date')) {
                $query->where('entry_time', '<=', Carbon::parse($request->input('end_date'))->endOfDay());
            }

            // Сортировка
            $sortBy = $request->input('sort_by', 'entry_time');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $trades = $query->paginate($limit);

            // Форматируем данные
            $formattedTrades = $trades->map(function ($trade) {
                return [
                    'id' => $trade->id,
                    'external_id' => $trade->external_id,
                    'exchange' => $trade->exchange,
                    'symbol' => $trade->symbol,
                    'side' => $trade->side,
                    'size' => (float) $trade->size,
                    'entry_price' => (float) $trade->entry_price,
                    'exit_price' => $trade->exit_price ? (float) $trade->exit_price : null,
                    'entry_time' => $trade->entry_time->toISOString(),
                    'exit_time' => $trade->exit_time?->toISOString(),
                    'pnl' => $trade->pnl ? (float) $trade->pnl : null,
                    'pnl_percent' => $trade->pnl_percent,
                    'fee' => (float) $trade->fee,
                    'status' => $trade->status,
                    'analysis' => $trade->analysis ? [
                        'smart_money_score' => $trade->analysis->smart_money_score,
                        'entry_quality' => $trade->analysis->entry_quality,
                        'exit_quality' => $trade->analysis->exit_quality,
                        'patterns' => $trade->analysis->patterns,
                        'recommendations' => $trade->analysis->recommendations,
                    ] : null,
                    'created_at' => $trade->created_at->toISOString(),
                    'updated_at' => $trade->updated_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTrades,
                'pagination' => [
                    'current_page' => $trades->currentPage(),
                    'last_page' => $trades->lastPage(),
                    'per_page' => $trades->perPage(),
                    'total' => $trades->total(),
                    'from' => $trades->firstItem(),
                    'to' => $trades->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch trades', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает детальную информацию о конкретной сделке
     */
    public function show(int $tradeId): JsonResponse
    {
        $user = Auth::user();

        try {
            $trade = Trade::where('user_id', $user->id)
                ->where('id', $tradeId)
                ->with(['analysis'])
                ->first();

            if (!$trade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trade not found'
                ], 404);
            }

            $tradeData = [
                'id' => $trade->id,
                'external_id' => $trade->external_id,
                'exchange' => $trade->exchange,
                'symbol' => $trade->symbol,
                'side' => $trade->side,
                'size' => (float) $trade->size,
                'entry_price' => (float) $trade->entry_price,
                'exit_price' => $trade->exit_price ? (float) $trade->exit_price : null,
                'entry_time' => $trade->entry_time->toISOString(),
                'exit_time' => $trade->exit_time?->toISOString(),
                'pnl' => $trade->pnl ? (float) $trade->pnl : null,
                'pnl_percent' => $trade->pnl_percent,
                'fee' => (float) $trade->fee,
                'status' => $trade->status,
                'is_closed' => $trade->isClosed(),
                'is_open' => $trade->isOpen(),
                'analysis' => $trade->analysis ? [
                    'id' => $trade->analysis->id,
                    'smart_money_score' => $trade->analysis->smart_money_score,
                    'entry_quality' => $trade->analysis->entry_quality,
                    'exit_quality' => $trade->analysis->exit_quality,
                    'market_structure_bias' => $trade->analysis->market_structure_bias,
                    'order_block_analysis' => $trade->analysis->order_block_analysis,
                    'liquidity_analysis' => $trade->analysis->liquidity_analysis,
                    'fvg_analysis' => $trade->analysis->fvg_analysis,
                    'patterns' => $trade->analysis->patterns,
                    'recommendations' => $trade->analysis->recommendations,
                    'created_at' => $trade->analysis->created_at->toISOString(),
                    'updated_at' => $trade->analysis->updated_at->toISOString(),
                ] : null,
                'created_at' => $trade->created_at->toISOString(),
                'updated_at' => $trade->updated_at->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $tradeData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch trade details', [
                'user_id' => $user->id,
                'trade_id' => $tradeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trade details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Запускает синхронизацию сделок для всех активных бирж пользователя
     */
    public function syncAll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'force' => 'sometimes|boolean',
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
            $exchanges = UserExchange::where('user_id', $user->id)
                ->where('is_active', true)
                ->get();

            if ($exchanges->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active exchange connections found'
                ], 404);
            }

            $startTime = $request->has('start_date') 
                ? Carbon::parse($request->input('start_date'))
                : now()->subDays(7);

            $endTime = $request->has('end_date')
                ? Carbon::parse($request->input('end_date'))
                : now();

            $force = $request->boolean('force', false);
            $syncedCount = 0;
            $skippedCount = 0;

            foreach ($exchanges as $exchange) {
                // Проверяем, нужна ли синхронизация
                if (!$force && !$exchange->needsSync()) {
                    $skippedCount++;
                    continue;
                }

                $this->dispatchSyncJob($exchange, $startTime, $endTime);
                $syncedCount++;
            }

            Log::info('Bulk trades synchronization initiated', [
                'user_id' => $user->id,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trades synchronization started',
                'data' => [
                    'exchanges_synced' => $syncedCount,
                    'exchanges_skipped' => $skippedCount,
                    'start_time' => $startTime->toISOString(),
                    'end_time' => $endTime->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start trades synchronization', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start synchronization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Запускает синхронизацию сделок для конкретной биржи
     */
    public function syncExchange(string $exchange, Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge($request->all(), ['exchange' => $exchange]),
            [
                'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'force' => 'sometimes|boolean',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            $userExchange = UserExchange::where('user_id', $user->id)
                ->where('exchange', $exchange)
                ->where('is_active', true)
                ->first();

            if (!$userExchange) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active ' . ucfirst($exchange) . ' connection not found'
                ], 404);
            }

            $startTime = $request->has('start_date') 
                ? Carbon::parse($request->input('start_date'))
                : now()->subDays(7);

            $endTime = $request->has('end_date')
                ? Carbon::parse($request->input('end_date'))
                : now();

            $force = $request->boolean('force', false);

            // Проверяем, нужна ли синхронизация
            if (!$force && !$userExchange->needsSync()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Synchronization not needed (recently synced)',
                    'data' => [
                        'last_sync_at' => $userExchange->last_sync_at->toISOString(),
                        'needs_sync' => false,
                    ]
                ]);
            }

            $this->dispatchSyncJob($userExchange, $startTime, $endTime);

            Log::info('Exchange trades synchronization initiated', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'exchange_id' => $userExchange->id,
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($exchange) . ' trades synchronization started',
                'data' => [
                    'exchange' => $exchange,
                    'start_time' => $startTime->toISOString(),
                    'end_time' => $endTime->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start exchange synchronization', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start synchronization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает статистику по сделкам пользователя
     */
    public function getStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exchange' => 'sometimes|string|in:bybit,mexc',
            'period' => 'sometimes|string|in:7d,30d,90d,1y,all',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
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
            $query = Trade::where('user_id', $user->id);

            // Применяем фильтр по бирже
            if ($request->has('exchange')) {
                $query->where('exchange', $request->input('exchange'));
            }

            // Применяем фильтр по периоду
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('entry_time', [
                    Carbon::parse($request->input('start_date')),
                    Carbon::parse($request->input('end_date'))->endOfDay()
                ]);
            } elseif ($request->has('period')) {
                $period = $request->input('period');
                if ($period !== 'all') {
                    $query->where('entry_time', '>=', $this->getPeriodStartDate($period));
                }
            } else {
                // По умолчанию показываем последние 30 дней
                $query->where('entry_time', '>=', now()->subDays(30));
            }

            $trades = $query->get();

            $stats = [
                'total_trades' => $trades->count(),
                'open_trades' => $trades->where('status', 'open')->count(),
                'closed_trades' => $trades->where('status', 'closed')->count(),
                'winning_trades' => $trades->where('pnl', '>', 0)->count(),
                'losing_trades' => $trades->where('pnl', '<', 0)->count(),
                'breakeven_trades' => $trades->where('pnl', 0)->count(),
                'total_pnl' => $trades->sum('pnl'),
                'total_fees' => $trades->sum('fee'),
                'win_rate' => $trades->where('status', 'closed')->count() > 0 
                    ? round(($trades->where('pnl', '>', 0)->count() / $trades->where('status', 'closed')->count()) * 100, 2)
                    : 0,
                'average_trade_size' => $trades->count() > 0 ? round($trades->avg('size'), 8) : 0,
                'largest_win' => $trades->max('pnl'),
                'largest_loss' => $trades->min('pnl'),
                'exchanges' => $trades->groupBy('exchange')->map->count(),
                'symbols' => $trades->groupBy('symbol')->map->count(),
                'analysis_stats' => $this->getAnalysisStats($trades),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch trade statistics', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает данные для графиков P&L
     */
    public function getPnlChart(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exchange' => 'sometimes|string|in:bybit,mexc',
            'period' => 'sometimes|string|in:7d,30d,90d,1y',
            'interval' => 'sometimes|string|in:day,week,month',
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
        $interval = $request->input('interval', 'day');

        try {
            $query = Trade::where('user_id', $user->id)
                ->where('status', 'closed')
                ->where('entry_time', '>=', $this->getPeriodStartDate($period))
                ->orderBy('entry_time');

            if ($request->has('exchange')) {
                $query->where('exchange', $request->input('exchange'));
            }

            $trades = $query->get();

            $chartData = $this->generatePnlChartData($trades, $interval);

            return response()->json([
                'success' => true,
                'data' => $chartData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate P&L chart data', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate chart data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Запускает задачу синхронизации для биржи
     */
    private function dispatchSyncJob(UserExchange $exchange, Carbon $startTime, Carbon $endTime): void
    {
        switch ($exchange->exchange) {
            case 'bybit':
                SyncBybitTradesJob::dispatch($exchange, $startTime, $endTime)->onQueue('high');
                break;

            case 'mexc':
                // TODO: Add MEXC sync job when implemented
                break;
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
     * Получает статистику по анализу сделок
     */
    private function getAnalysisStats($trades): array
    {
        $tradesWithAnalysis = $trades->whereNotNull('analysis');
        
        if ($tradesWithAnalysis->isEmpty()) {
            return [
                'analyzed_trades' => 0,
                'average_smart_money_score' => 0,
                'quality_distribution' => [],
            ];
        }

        return [
            'analyzed_trades' => $tradesWithAnalysis->count(),
            'average_smart_money_score' => round($tradesWithAnalysis->avg('analysis.smart_money_score'), 2),
            'quality_distribution' => [
                'excellent' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 8)->count(),
                'good' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 6 && $t->analysis->smart_money_score < 8)->count(),
                'average' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score >= 4 && $t->analysis->smart_money_score < 6)->count(),
                'poor' => $tradesWithAnalysis->filter(fn($t) => $t->analysis->smart_money_score < 4)->count(),
            ],
        ];
    }

    /**
     * Генерирует данные для графика P&L
     */
    private function generatePnlChartData($trades, string $interval): array
    {
        $data = [];
        $cumulativePnl = 0;

        $groupedTrades = $trades->groupBy(function ($trade) use ($interval) {
            return match ($interval) {
                'day' => $trade->entry_time->format('Y-m-d'),
                'week' => $trade->entry_time->startOfWeek()->format('Y-m-d'),
                'month' => $trade->entry_time->format('Y-m'),
                default => $trade->entry_time->format('Y-m-d'),
            };
        });

        foreach ($groupedTrades as $date => $dayTrades) {
            $dayPnl = $dayTrades->sum('pnl');
            $cumulativePnl += $dayPnl;

            $data[] = [
                'date' => $date,
                'pnl' => round($dayPnl, 2),
                'cumulative_pnl' => round($cumulativePnl, 2),
                'trades_count' => $dayTrades->count(),
                'winning_trades' => $dayTrades->where('pnl', '>', 0)->count(),
                'losing_trades' => $dayTrades->where('pnl', '<', 0)->count(),
            ];
        }

        return $data;
    }
}