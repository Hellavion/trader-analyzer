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
    private int $recWindow = 20000; // Увеличиваем recv_window до 20 секунд

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
     * Получает закрытые PnL записи (для анализа завершенных позиций)
     */
    public function getClosedPnL(string $category = 'linear', ?string $symbol = null, ?Carbon $startTime = null, ?Carbon $endTime = null, int $limit = 50): array
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

        $response = $this->makePrivateRequest('GET', '/v5/position/closed-pnl', $params);

        if ($response['retCode'] !== 0) {
            throw new \Exception('Failed to fetch closed PnL: ' . $response['retMsg']);
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
        $timestamp = (string) round(microtime(true) * 1000);
        $queryString = http_build_query($params);
        
        $signature = $this->generateSignature($timestamp, $method, $endpoint, $queryString);

        $headers = [
            'X-BAPI-API-KEY: ' . $this->apiKey,
            'X-BAPI-SIGN: ' . $signature,
            'X-BAPI-SIGN-TYPE: 2',
            'X-BAPI-TIMESTAMP: ' . $timestamp,
            'X-BAPI-RECV-WINDOW: ' . $this->recWindow,
            'Content-Type: application/json',
        ];

        $url = $this->baseUrl . $endpoint;
        
        if ($method === 'GET' && !empty($queryString)) {
            $url .= '?' . $queryString;
        }

        return $this->makeCurlRequest($method, $url, $headers, $params);
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

        $headers = ['Content-Type: application/json'];
        
        return $this->makeCurlRequest($method, $url, $headers, []);
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
     * Выполняет cURL запрос
     */
    private function makeCurlRequest(string $method, string $url, array $headers, array $params = []): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false, // Временно для тестирования
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'TraderAnalyzer/1.0',
            CURLOPT_HEADER => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 300,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
        }

        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Обрабатываем rate limiting
        $this->handleRateLimitFromHeaders($headerString, $httpCode);

        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Обрабатывает ограничения по частоте запросов из заголовков
     */
    private function handleRateLimitFromHeaders(string $headers, int $httpCode): void
    {
        if (preg_match('/X-Bapi-Limit-Status:\s*(\d+)/i', $headers, $matches)) {
            $rateLimitRemaining = (int) $matches[1];
            
            if ($rateLimitRemaining < 10) {
                Log::warning('Bybit API rate limit approaching', [
                    'remaining' => $rateLimitRemaining
                ]);
            }
        }

        if ($httpCode === 429) {
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

    /**
     * Преобразует данные закрытого PnL в формат приложения
     */
    public function transformClosedPnLData(array $bybitPnL): array
    {
        return [
            'external_id' => $bybitPnL['orderId'],
            'symbol' => $bybitPnL['symbol'],
            'side' => strtolower($bybitPnL['side']),
            'size' => (float) $bybitPnL['qty'],
            'entry_price' => (float) $bybitPnL['avgEntryPrice'],
            'exit_price' => (float) $bybitPnL['avgExitPrice'],
            'entry_time' => Carbon::createFromTimestampMs($bybitPnL['createdTime']),
            'exit_time' => Carbon::createFromTimestampMs($bybitPnL['updatedTime']),
            'pnl' => (float) $bybitPnL['closedPnl'],
            'fee' => abs((float) $bybitPnL['totalFee']),
            'exchange' => 'bybit',
            'status' => 'closed',
        ];
    }

    /**
     * Получает активные торговые символы из позиций и истории
     */
    public function getActiveSymbols(string $category = 'linear'): array
    {
        $symbols = [];

        try {
            // Получаем символы из открытых позиций
            $positions = $this->getPositions($category);
            foreach ($positions as $position) {
                if ((float) $position['size'] > 0) {
                    $symbols[] = $position['symbol'];
                }
            }

            // Получаем символы из недавней торговой истории
            $recentTrades = $this->getTradingHistory($category, null, Carbon::now()->subDays(7), null, 100);
            foreach ($recentTrades as $trade) {
                $symbols[] = $trade['symbol'];
            }

            return array_unique($symbols);

        } catch (\Exception $e) {
            Log::warning('Failed to get active symbols', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Проверяет статус API соединения
     */
    public function getApiStatus(): array
    {
        try {
            $response = $this->makePublicRequest('GET', '/v5/market/time', []);
            
            return [
                'is_connected' => $response['retCode'] === 0,
                'server_time' => $response['result']['timeSecond'] ?? null,
                'message' => $response['retMsg'] ?? 'API accessible'
            ];
        } catch (\Exception $e) {
            return [
                'is_connected' => false,
                'server_time' => null,
                'message' => $e->getMessage()
            ];
        }
    }
}