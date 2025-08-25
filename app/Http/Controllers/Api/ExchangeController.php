<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Универсальный API контроллер для управления подключениями к биржам
 */
class ExchangeController extends Controller
{
    /**
     * Получает список всех подключенных бирж пользователя
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $exchanges = UserExchange::where('user_id', $user->id)
            ->get()
            ->map(function ($exchange) {
                return [
                    'id' => $exchange->id,
                    'exchange' => $exchange->exchange,
                    'display_name' => $exchange->display_name,
                    'is_active' => $exchange->is_active,
                    'has_valid_credentials' => $exchange->hasValidCredentials(),
                    'masked_api_key' => $exchange->masked_api_key,
                    'last_sync_at' => $exchange->last_sync_at?->toISOString(),
                    'needs_sync' => $exchange->needsSync(),
                    'sync_settings' => $exchange->sync_settings,
                    'created_at' => $exchange->created_at->toISOString(),
                    'updated_at' => $exchange->updated_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $exchanges
        ]);
    }

    /**
     * Получает информацию о конкретной бирже
     */
    public function show(string $exchange): JsonResponse
    {
        $validator = Validator::make(['exchange' => $exchange], [
            'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid exchange',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', $exchange)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => true,
                'data' => [
                    'exchange' => $exchange,
                    'is_connected' => false,
                    'is_active' => false,
                    'connection_info' => $this->getExchangeInfo($exchange)
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $userExchange->id,
                'exchange' => $userExchange->exchange,
                'display_name' => $userExchange->display_name,
                'is_connected' => true,
                'is_active' => $userExchange->is_active,
                'has_valid_credentials' => $userExchange->hasValidCredentials(),
                'masked_api_key' => $userExchange->masked_api_key,
                'last_sync_at' => $userExchange->last_sync_at?->toISOString(),
                'needs_sync' => $userExchange->needsSync(),
                'sync_settings' => $userExchange->sync_settings,
                'connection_info' => $this->getExchangeInfo($exchange),
                'created_at' => $userExchange->created_at->toISOString(),
                'updated_at' => $userExchange->updated_at->toISOString(),
            ]
        ]);
    }

    /**
     * Тестирует подключение к бирже без сохранения данных
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
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

        $exchange = $request->input('exchange');
        $apiKey = $request->input('api_key');
        $secret = $request->input('secret');

        try {
            $result = $this->testExchangeConnection($exchange, $apiKey, $secret);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'exchange_info' => $this->getExchangeInfo($exchange)
            ]);

        } catch (\Exception $e) {
            Log::error('Exchange connection test failed', [
                'exchange' => $exchange,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Подключает пользователя к бирже
     */
    public function connect(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
            'api_key' => 'required|string',
            'secret' => 'required|string',
            'sync_settings' => 'sometimes|array',
            'sync_settings.auto_sync' => 'boolean',
            'sync_settings.sync_interval_hours' => 'integer|min:1|max:24',
            'sync_settings.symbols_filter' => 'sometimes|array',
            'sync_settings.symbols_filter.*' => 'string',
            'sync_settings.categories' => 'sometimes|array',
            'sync_settings.categories.*' => Rule::in(['spot', 'linear', 'inverse', 'option']),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $exchange = $request->input('exchange');
        $apiKey = $request->input('api_key');
        $secret = $request->input('secret');

        // Сначала проверяем подключение
        try {
            $connectionTest = $this->testExchangeConnection($exchange, $apiKey, $secret);

            if (!$connectionTest['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to ' . ucfirst($exchange) . ' API: ' . $connectionTest['message']
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }

        // Создаем или обновляем подключение
        try {
            $defaultSettings = $this->getDefaultSyncSettings($exchange);
            $syncSettings = array_merge($defaultSettings, $request->input('sync_settings', []));

            $userExchange = UserExchange::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'exchange' => $exchange,
                ],
                [
                    'is_active' => true,
                    'sync_settings' => $syncSettings,
                ]
            );

            // Устанавливаем API ключи с шифрованием
            $userExchange->setApiCredentials([
                'api_key' => $apiKey,
                'secret' => $secret,
            ]);
            $userExchange->save();

            // Запускаем первоначальную синхронизацию
            $this->startInitialSync($userExchange);

            Log::info('Exchange connected successfully', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'exchange_id' => $userExchange->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($exchange) . ' exchange connected successfully',
                'data' => [
                    'id' => $userExchange->id,
                    'exchange' => $userExchange->exchange,
                    'display_name' => $userExchange->display_name,
                    'is_active' => $userExchange->is_active,
                    'masked_api_key' => $userExchange->masked_api_key,
                    'sync_settings' => $userExchange->sync_settings,
                    'created_at' => $userExchange->created_at->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to connect exchange', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save exchange connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отключает пользователя от биржи
     */
    public function disconnect(string $exchange): JsonResponse
    {
        $validator = Validator::make(['exchange' => $exchange], [
            'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid exchange',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', $exchange)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($exchange) . ' exchange not found'
            ], 404);
        }

        try {
            $userExchange->deactivate();

            Log::info('Exchange disconnected', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'exchange_id' => $userExchange->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($exchange) . ' exchange disconnected successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect exchange', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect exchange: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаляет подключение к бирже полностью
     */
    public function delete(string $exchange): JsonResponse
    {
        $validator = Validator::make(['exchange' => $exchange], [
            'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid exchange',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', $exchange)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($exchange) . ' exchange not found'
            ], 404);
        }

        try {
            $exchangeId = $userExchange->id;
            $userExchange->delete();

            Log::info('Exchange deleted', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'exchange_id' => $exchangeId,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($exchange) . ' exchange deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete exchange', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exchange: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновляет настройки синхронизации для биржи
     */
    public function updateSyncSettings(string $exchange, Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge($request->all(), ['exchange' => $exchange]),
            [
                'exchange' => ['required', Rule::in(['bybit', 'mexc'])],
                'auto_sync' => 'sometimes|boolean',
                'sync_interval_hours' => 'sometimes|integer|min:1|max:24',
                'symbols_filter' => 'sometimes|array',
                'symbols_filter.*' => 'string',
                'categories' => 'sometimes|array',
                'categories.*' => Rule::in(['spot', 'linear', 'inverse', 'option']),
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

        $userExchange = UserExchange::where('user_id', $user->id)
            ->where('exchange', $exchange)
            ->first();

        if (!$userExchange) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($exchange) . ' exchange not found'
            ], 404);
        }

        try {
            $currentSettings = $userExchange->sync_settings ?: [];
            $newSettings = array_merge($currentSettings, $request->only([
                'auto_sync',
                'sync_interval_hours',
                'symbols_filter',
                'categories',
            ]));

            $userExchange->update(['sync_settings' => $newSettings]);

            Log::info('Exchange sync settings updated', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'settings' => $newSettings,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sync settings updated successfully',
                'data' => $newSettings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update sync settings', [
                'user_id' => $user->id,
                'exchange' => $exchange,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update sync settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает список поддерживаемых бирж
     */
    public function getSupportedExchanges(): JsonResponse
    {
        $exchanges = [
            'bybit' => [
                'name' => 'Bybit',
                'description' => 'One of the world\'s fastest crypto exchanges',
                'website' => 'https://www.bybit.com',
                'api_docs' => 'https://bybit-exchange.github.io/docs/',
                'supported_categories' => ['spot', 'linear', 'inverse', 'option'],
                'features' => [
                    'spot_trading' => true,
                    'futures_trading' => true,
                    'options_trading' => true,
                    'margin_trading' => true,
                    'copy_trading' => true,
                ],
                'requirements' => [
                    'api_key' => 'API Key from account settings',
                    'secret' => 'Secret Key from account settings',
                    'permissions' => ['Read', 'Contract Trade', 'Spot & Margin Trade'],
                ],
            ],
            'mexc' => [
                'name' => 'MEXC',
                'description' => 'Global digital asset trading platform',
                'website' => 'https://www.mexc.com',
                'api_docs' => 'https://mexcdevelop.github.io/apidocs/',
                'supported_categories' => ['spot', 'futures'],
                'features' => [
                    'spot_trading' => true,
                    'futures_trading' => true,
                    'options_trading' => false,
                    'margin_trading' => true,
                    'copy_trading' => false,
                ],
                'requirements' => [
                    'api_key' => 'API Key from account settings',
                    'secret' => 'Secret Key from account settings',
                    'permissions' => ['Read', 'Spot Trading', 'Futures Trading'],
                ],
                'status' => 'coming_soon',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $exchanges
        ]);
    }

    /**
     * Тестирует подключение к конкретной бирже
     */
    private function testExchangeConnection(string $exchange, string $apiKey, string $secret): array
    {
        switch ($exchange) {
            case 'bybit':
                $service = new BybitService($apiKey, $secret);
                return $service->testConnection();

            case 'mexc':
                // TODO: Implement MEXC service when ready
                throw new \Exception('MEXC integration is not yet implemented');

            default:
                throw new \Exception('Unsupported exchange: ' . $exchange);
        }
    }

    /**
     * Запускает первоначальную синхронизацию данных
     */
    private function startInitialSync(UserExchange $userExchange): void
    {
        switch ($userExchange->exchange) {
            case 'bybit':
                \App\Jobs\SyncBybitTradesJob::dispatch($userExchange)->onQueue('high');
                break;

            case 'mexc':
                // TODO: Add MEXC sync job when implemented
                break;
        }
    }

    /**
     * Получает настройки синхронизации по умолчанию для биржи
     */
    private function getDefaultSyncSettings(string $exchange): array
    {
        $defaults = [
            'auto_sync' => true,
            'sync_interval_hours' => 1,
            'categories' => ['spot', 'linear'],
        ];

        switch ($exchange) {
            case 'bybit':
                $defaults['categories'] = ['spot', 'linear', 'inverse'];
                break;

            case 'mexc':
                $defaults['categories'] = ['spot', 'futures'];
                break;
        }

        return $defaults;
    }

    /**
     * Получает информацию о бирже
     */
    private function getExchangeInfo(string $exchange): array
    {
        $supportedExchanges = $this->getSupportedExchanges()->getData()->data;
        return (array) ($supportedExchanges->{$exchange} ?? []);
    }
}