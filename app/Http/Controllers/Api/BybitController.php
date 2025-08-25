<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CollectBybitMarketDataJob;
use App\Jobs\SyncBybitTradesJob;
use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * API контроллер для интеграции с Bybit
 */
class BybitController extends Controller
{
    /**
     * Тестирует подключение к Bybit API
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
            'secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $bybitService = new BybitService(
            $request->input('api_key'),
            $request->input('secret')
        );

        $result = $bybitService->testConnection();

        return response()->json($result);
    }

    /**
     * Подключает пользователя к Bybit
     */
    public function connectExchange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
            'secret' => 'required|string',
            'sync_settings' => 'sometimes|array',
            'sync_settings.auto_sync' => 'boolean',
            'sync_settings.sync_interval_hours' => 'integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Проверяем подключение к API
        $bybitService = new BybitService(
            $request->input('api_key'),
            $request->input('secret')
        );

        $connectionTest = $bybitService->testConnection();

        if (!$connectionTest['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Bybit API: ' . $connectionTest['message']
            ], 400);
        }

        // Создаем или обновляем подключение
        $userExchange = UserExchange::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange' => 'bybit',
            ],
            [
                'is_active' => true,
                'sync_settings' => array_merge([
                    'auto_sync' => true,
                    'sync_interval_hours' => 1,
                ], $request->input('sync_settings', [])),
            ]
        );

        // Устанавливаем API ключи с шифрованием
        $userExchange->setApiCredentials([
            'api_key' => $request->input('api_key'),
            'secret' => $request->input('secret'),
        ]);
        $userExchange->save();

        // Запускаем первоначальную синхронизацию
        SyncBybitTradesJob::dispatch($userExchange)->onQueue('high');

        return response()->json([
            'success' => true,
            'message' => 'Bybit exchange connected successfully',
            'data' => [
                'exchange_id' => $userExchange->id,
                'is_active' => $userExchange->is_active,
                'sync_settings' => $userExchange->sync_settings,
            ]
        ]);
    }

    /**
     * Отключает пользователя от Bybit
     */
    public function disconnectExchange(): JsonResponse
    {
        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', 'bybit')
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => 'Bybit exchange not found'
            ], 404);
        }

        $userExchange->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'Bybit exchange disconnected successfully'
        ]);
    }

    /**
     * Получает статус подключения к Bybit
     */
    public function getConnectionStatus(): JsonResponse
    {
        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', 'bybit')
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_connected' => false,
                    'is_active' => false,
                    'last_sync_at' => null,
                    'sync_settings' => null,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_connected' => true,
                'is_active' => $userExchange->is_active,
                'has_valid_credentials' => $userExchange->hasValidCredentials(),
                'last_sync_at' => $userExchange->last_sync_at?->toISOString(),
                'needs_sync' => $userExchange->needsSync(),
                'sync_settings' => $userExchange->sync_settings,
            ]
        ]);
    }

    /**
     * Запускает синхронизацию сделок вручную
     */
    public function syncTrades(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
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

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', 'bybit')
            ->where('is_active', true)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => 'Active Bybit connection not found'
            ], 404);
        }

        $startTime = $request->has('start_date') 
            ? \Carbon\Carbon::parse($request->input('start_date'))
            : now()->subDays(7);

        $endTime = $request->has('end_date')
            ? \Carbon\Carbon::parse($request->input('end_date'))
            : now();

        // Запускаем job синхронизации
        SyncBybitTradesJob::dispatch($userExchange, $startTime, $endTime)
            ->onQueue('high');

        return response()->json([
            'success' => true,
            'message' => 'Trades synchronization started',
            'data' => [
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
            ]
        ]);
    }

    /**
     * Получает баланс кошелька с Bybit
     */
    public function getWalletBalance(): JsonResponse
    {
        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', 'bybit')
            ->where('is_active', true)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => 'Active Bybit connection not found'
            ], 404);
        }

        try {
            $credentials = $userExchange->getApiCredentials();
            $bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

            $balance = $bybitService->getWalletBalance();

            return response()->json([
                'success' => true,
                'data' => $balance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает открытые позиции с Bybit
     */
    public function getPositions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => ['sometimes', Rule::in(['linear', 'inverse', 'spot'])],
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

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', 'bybit')
            ->where('is_active', true)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => 'Active Bybit connection not found'
            ], 404);
        }

        try {
            $credentials = $userExchange->getApiCredentials();
            $bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

            $positions = $bybitService->getPositions(
                $request->input('category', 'linear'),
                $request->input('symbol')
            );

            return response()->json([
                'success' => true,
                'data' => $positions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch positions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновляет настройки синхронизации
     */
    public function updateSyncSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'auto_sync' => 'sometimes|boolean',
            'sync_interval_hours' => 'sometimes|integer|min:1|max:24',
            'symbols_filter' => 'sometimes|array',
            'symbols_filter.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', 'bybit')
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => 'Bybit connection not found'
            ], 404);
        }

        $currentSettings = $userExchange->sync_settings ?: [];
        $newSettings = array_merge($currentSettings, $request->only([
            'auto_sync',
            'sync_interval_hours',
            'symbols_filter',
        ]));

        $userExchange->update(['sync_settings' => $newSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Sync settings updated successfully',
            'data' => $newSettings
        ]);
    }

    /**
     * Запускает сбор рыночных данных
     */
    public function collectMarketData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbols' => 'sometimes|array',
            'symbols.*' => 'string',
            'timeframes' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $symbols = $request->input('symbols', []);
        $timeframes = $request->input('timeframes', []);

        // Запускаем job сбора данных
        CollectBybitMarketDataJob::dispatch($symbols, $timeframes)
            ->onQueue('low');

        return response()->json([
            'success' => true,
            'message' => 'Market data collection started',
            'data' => [
                'symbols' => $symbols ?: 'default symbols',
                'timeframes' => $timeframes ?: 'default timeframes',
            ]
        ]);
    }
}