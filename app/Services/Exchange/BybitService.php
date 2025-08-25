<?php

namespace App\Services\Exchange;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;

/**
 * Сервис для работы с Bybit API v5
 */
class BybitService
{
    private string $baseUrl = 'https://api.bybit.com';
    private string $apiKey;
    private string $secret;
    private int $recWindow = 5000;

    public function __construct(?string $apiKey = null, ?string $secret = null)
    {
        $this->apiKey = $apiKey ?? '';
        $this->secret = $secret ?? '';
    }

    /**
     * Устанавливает API ключи
     */
    public function setCredentials(string $apiKey, string $secret): self
    {
        $this->apiKey = $apiKey;
        $this->secret = $secret;
        return $this;
    }

    /**
     * Проверяет валидность API ключей
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makePrivateRequest('GET', '/v5/account/wallet-balance', [
                'accountType' => 'UNIFIED',
            ]);

            return [
                'success' => $response['retCode'] === 0,
                'message' => $response['retMsg'] ?? 'Connection successful',
                'data' => $response['result'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('Bybit connection test failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Получает историю сделок
     */
    public function getTradingHistory(string $category = 'linear', ?string $symbol = null, ?Carbon $startTime = null, ?Carbon $endTime = null, int $limit = 50): array
    {
        $params = [
            'category' => $category,
            'limit' => $limit,
        ];

        if ($symbol) {
            $params['symbol'] = $symbol;
        }

        if ($startTime) {
            $params['startTime'] = $startTime->getTimestampMs();
        }

        if ($endTime) {
            $params['endTime'] = $endTime->getTimestampMs();
        }

        $response = $this->makePrivateRequest('GET', '/v5/execution/list', $params);

        if ($response['retCode'] !== 0) {
            throw new \Exception('Failed to fetch trading history: ' . $response['retMsg']);
        }

        return $response['result']['list'] ?? [];
    }

    /**
     * Получает открытые позиции
     */
    public function getPositions(string $category = 'linear', ?string $symbol = null): array
    {
        $params = [
            'category' => $category,
        ];

        if ($symbol) {
            $params['symbol'] = $symbol;
        }

        $response = $this->makePrivateRequest('GET', '/v5/position/list', $params);

        if ($response['retCode'] !== 0) {
            throw new \Exception('Failed to fetch positions: ' . $response['retMsg']);
        }

        return $response['result']['list'] ?? [];
    }

    /**
     * Получает баланс кошелька
     */
    public function getWalletBalance(string $accountType = 'UNIFIED'): array
    {
        $response = $this->makePrivateRequest('GET', '/v5/account/wallet-balance', [
            'accountType' => $accountType,
        ]);

        if ($response['retCode'] !== 0) {
            throw new \Exception('Failed to fetch wallet balance: ' . $response['retMsg']);
        }

        return $response['result']['list'] ?? [];
    }

    /**
     * Получает данные свечей (OHLCV)
     */
    public function getKlineData(string $category, string $symbol, string $interval, ?Carbon $start = null, ?Carbon $end = null, int $limit = 200): array
    {
        $params = [
            'category' => $category,
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ];

        if ($start) {
            $params['start'] = $start->getTimestampMs();
        }

        if ($end) {
            $params['end'] = $end->getTimestampMs();
        }

        $response = $this->makePublicRequest('GET', '/v5/market/kline', $params);

        if ($response['retCode'] !== 0) {
            throw new \Exception('Failed to fetch kline data: ' . $response['retMsg']);
        }

        return $response['result']['list'] ?? [];
    }

    /**
     * Получает текущие цены символов
     */
    public function getTickers(string $category, ?string $symbol = null): array
    {
        $params = ['category' => $category];

        if ($symbol) {
            $params['symbol'] = $symbol;
        }

        $response = $this->makePublicRequest('GET', '/v5/market/tickers', $params);

        if ($response['retCode'] !== 0) {
            throw new \Exception('Failed to fetch tickers: ' . $response['retMsg']);
        }

        return $response['result']['list'] ?? [];
    }

    /**
     * Выполняет приватный запрос с подписью
     */
    private function makePrivateRequest(string $method, string $endpoint, array $params = []): array
    {
        $timestamp = (string) (microtime(true) * 1000);
        $queryString = http_build_query($params);
        
        $signature = $this->generateSignature($timestamp, $method, $endpoint, $queryString);

        $headers = [
            'X-BAPI-API-KEY' => $this->apiKey,
            'X-BAPI-SIGN' => $signature,
            'X-BAPI-SIGN-TYPE' => '2',
            'X-BAPI-TIMESTAMP' => $timestamp,
            'X-BAPI-RECV-WINDOW' => (string) $this->recWindow,
            'Content-Type' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;
        
        if ($method === 'GET' && !empty($queryString)) {
            $url .= '?' . $queryString;
        }

        $response = Http::withHeaders($headers)->timeout(30);

        if ($method === 'POST') {
            $response = $response->post($url, $params);
        } else {
            $response = $response->get($url);
        }

        $this->handleRateLimit($response);

        return $response->json();
    }

    /**
     * Выполняет публичный запрос
     */
    private function makePublicRequest(string $method, string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $queryString = http_build_query($params);

        if (!empty($queryString)) {
            $url .= '?' . $queryString;
        }

        $response = Http::timeout(30)->get($url);
        
        $this->handleRateLimit($response);

        return $response->json();
    }

    /**
     * Генерирует подпись для аутентификации
     */
    private function generateSignature(string $timestamp, string $method, string $endpoint, string $queryString): string
    {
        $payload = $timestamp . $this->apiKey . $this->recWindow;
        
        if ($method === 'POST') {
            $payload .= $queryString;
        } else {
            $payload .= $queryString;
        }

        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * Обрабатывает ограничения по частоте запросов
     */
    private function handleRateLimit(Response $response): void
    {
        $rateLimitRemaining = $response->header('X-Bapi-Limit-Status');
        
        if ($rateLimitRemaining && (int) $rateLimitRemaining < 10) {
            Log::warning('Bybit API rate limit approaching', [
                'remaining' => $rateLimitRemaining
            ]);
        }

        if ($response->status() === 429) {
            throw new \Exception('Bybit API rate limit exceeded');
        }
    }

    /**
     * Преобразует данные сделки Bybit в формат приложения
     */
    public function transformTradeData(array $bybitTrade): array
    {
        return [
            'external_id' => $bybitTrade['execId'],
            'symbol' => $bybitTrade['symbol'],
            'side' => strtolower($bybitTrade['side']),
            'size' => (float) $bybitTrade['execQty'],
            'entry_price' => (float) $bybitTrade['execPrice'],
            'entry_time' => Carbon::createFromTimestampMs($bybitTrade['execTime']),
            'fee' => (float) $bybitTrade['execFee'],
            'exchange' => 'bybit',
            'status' => 'closed', // Исполненные сделки всегда закрыты
        ];
    }

    /**
     * Преобразует данные позиции Bybit в формат приложения
     */
    public function transformPositionData(array $bybitPosition): array
    {
        return [
            'symbol' => $bybitPosition['symbol'],
            'side' => strtolower($bybitPosition['side']),
            'size' => (float) $bybitPosition['size'],
            'entry_price' => (float) $bybitPosition['avgPrice'],
            'unrealized_pnl' => (float) $bybitPosition['unrealisedPnl'],
            'exchange' => 'bybit',
            'status' => 'open',
        ];
    }
}