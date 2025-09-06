<?php

namespace App\Services\Exchange;

use App\Models\UserExchange;
use App\Models\Trade;
use App\Events\RealTradeUpdate;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use ReactPHP\Socket\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector as WSConnector;

class BybitWebSocketService
{
    private UserExchange $userExchange;
    private array $credentials;
    private string $wsUrl = 'wss://stream.bybit.com/v5/private';
    private int $reconnectAttempts = 0;
    private int $maxReconnectAttempts = 5;

    public function __construct(UserExchange $userExchange)
    {
        $this->userExchange = $userExchange;
        $this->credentials = $userExchange->getApiCredentials();
    }

    public function start(): void
    {
        Log::info('Starting Bybit WebSocket connection', [
            'user_id' => $this->userExchange->user_id,
            'attempt' => $this->reconnectAttempts + 1
        ]);

        $connector = new WSConnector();
        
        $connector($this->wsUrl)
            ->then(function (WebSocket $conn) {
                Log::info('WebSocket connected successfully', [
                    'user_id' => $this->userExchange->user_id,
                ]);

                // Сброс счетчика при успешном подключении
                $this->resetReconnectAttempts();

                // Аутентификация
                $authMessage = $this->buildAuthMessage();
                $conn->send(json_encode($authMessage));

                // Подписка на события выполнения сделок (execution) - это то что нам нужно
                $subscribeMessage = [
                    'op' => 'subscribe', 
                    'args' => ['execution']
                ];
                $conn->send(json_encode($subscribeMessage));

                $conn->on('message', function ($msg) {
                    $this->handleMessage($msg->getPayload());
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    Log::warning('WebSocket connection closed, reconnecting', [
                        'user_id' => $this->userExchange->user_id,
                        'code' => $code,
                        'reason' => $reason,
                        'attempt' => $this->reconnectAttempts + 1
                    ]);
                    
                    $this->handleReconnect();
                });

            }, function (\Exception $e) {
                Log::error('WebSocket connection failed, retrying', [
                    'user_id' => $this->userExchange->user_id,
                    'error' => $e->getMessage(),
                    'attempt' => $this->reconnectAttempts + 1
                ]);
                
                $this->handleReconnect();
            });
    }

    private function buildAuthMessage(): array
    {
        $expires = (time() + 10) * 1000; // 10 секунд запас
        
        // Правильная подпись для Bybit WebSocket v5
        $param_str = "GET/realtime{$expires}";
        $signature = hash_hmac('sha256', $param_str, $this->credentials['secret']);

        return [
            'op' => 'auth',
            'args' => [
                $this->credentials['api_key'],
                $expires,
                $signature
            ]
        ];
    }

    private function handleMessage(string $message): void
    {
        $data = json_decode($message, true);

        if (!$data) {
            return;
        }

        // Подробное логирование всех сообщений
        Log::info('WebSocket raw message received', [
            'user_id' => $this->userExchange->user_id,
            'full_message' => $data,
            'topic' => $data['topic'] ?? 'no_topic',
            'op' => $data['op'] ?? 'no_op',
        ]);

        // Обработка выполненных сделок
        if (isset($data['topic']) && $data['topic'] === 'execution') {
            $this->handleExecutionUpdate($data);
        }
        
        // Обработка позиций (оставляем для отладки)
        if (isset($data['topic']) && $data['topic'] === 'position') {
            $this->handlePositionUpdate($data);
        }

        // Обработка ответов на аутентификацию
        if (isset($data['op']) && $data['op'] === 'auth') {
            if ($data['success'] ?? false) {
                Log::info('WebSocket authentication successful', [
                    'user_id' => $this->userExchange->user_id,
                ]);
            } else {
                Log::error('WebSocket authentication failed', [
                    'user_id' => $this->userExchange->user_id,
                    'message' => $data,
                ]);
            }
        }
    }

    private function handlePositionUpdate(array $data): void
    {
        if (!isset($data['data'])) {
            return;
        }

        foreach ($data['data'] as $position) {
            // Логируем все позиции для отладки
            Log::info('Position update received', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $position['symbol'] ?? 'unknown',
                'side' => $position['side'] ?? 'empty',
                'size' => $position['size'] ?? '0',
                'cum_realised_pnl' => $position['cumRealisedPnl'] ?? 'missing',
                'cur_realised_pnl' => $position['curRealisedPnl'] ?? 'missing',
            ]);

            // Обрабатываем закрытые позиции: size=0 И есть изменение в PnL
            if (($position['size'] ?? '0') === '0' && !empty($position['curRealisedPnl']) && $position['curRealisedPnl'] !== '0') {
                $this->processClosedPosition($position);
            }
        }
    }

    private function handleExecutionUpdate(array $data): void
    {
        if (!isset($data['data'])) {
            return;
        }

        foreach ($data['data'] as $execution) {
            Log::info('Execution received', [
                'user_id' => $this->userExchange->user_id,
                'symbol' => $execution['symbol'] ?? 'unknown',
                'side' => $execution['side'] ?? 'unknown', 
                'exec_type' => $execution['execType'] ?? 'unknown',
                'exec_qty' => $execution['execQty'] ?? '0',
                'exec_price' => $execution['execPrice'] ?? '0',
                'is_close' => $execution['isClose'] ?? 'unknown',
            ]);

            // Обрабатываем только закрывающие сделки (когда closedSize > 0)
            if (!empty($execution['closedSize']) && (float)$execution['closedSize'] > 0) {
                $this->processExecutionTrade($execution);
            }
        }
    }

    private function processExecutionTrade(array $execution): void
    {
        $tradeData = [
            'user_id' => $this->userExchange->user_id,
            'exchange' => 'bybit',
            'external_id' => 'exec_' . ($execution['execId'] ?? 'ws_' . time()),
            'symbol' => $execution['symbol'],
            'side' => strtolower($execution['side']),
            'size' => (float) $execution['closedSize'], // Используем closedSize
            'entry_price' => 0,
            'exit_price' => (float) $execution['execPrice'],
            'pnl' => (float) ($execution['execPnl'] ?? 0), // Используем execPnl
            'fee' => (float) ($execution['execFee'] ?? 0),
            'entry_time' => Carbon::now()->subMinutes(1), // Примерно
            'exit_time' => Carbon::createFromTimestampMs($execution['execTime']),
            'status' => 'closed',
            'raw_data' => json_encode($execution),
        ];

        $trade = Trade::create($tradeData);
        
        Log::info('New execution trade saved', [
            'user_id' => $this->userExchange->user_id,
            'trade_id' => $trade->id,
            'symbol' => $trade->symbol,
            'side' => $trade->side,
            'size' => $trade->size,
        ]);

        Log::info('Broadcasting RealTradeUpdate event', ['trade_id' => $trade->id]);
        \App\Events\RealTradeUpdate::dispatch($trade->toArray());
        Log::info('RealTradeUpdate event dispatched', ['trade_id' => $trade->id]);
    }

    private function processClosedPosition(array $position): void
    {
        $pnl = (float) ($position['curRealisedPnl'] ?? 0);
        $updatedTime = $position['updatedTime'] ?? time() * 1000;
        
        $tradeData = [
            'user_id' => $this->userExchange->user_id,
            'exchange' => 'bybit',
            'external_id' => 'ws_' . $position['symbol'] . '_' . $updatedTime,
            'symbol' => $position['symbol'],
            'side' => 'closed', // WebSocket не всегда передает side для закрытых
            'size' => 1, // Размер неизвестен из WebSocket, ставим 1
            'entry_price' => (float) ($position['entryPrice'] ?? 0),
            'exit_price' => (float) ($position['markPrice'] ?? 0),
            'pnl' => $pnl,
            'entry_time' => Carbon::createFromTimestampMs($position['createdTime'] ?? $updatedTime),
            'exit_time' => Carbon::createFromTimestampMs($updatedTime),
            'status' => 'closed',
            'raw_data' => json_encode($position),
        ];

        Log::info('Processing closed position from WebSocket', [
            'user_id' => $this->userExchange->user_id,
            'symbol' => $position['symbol'],
            'pnl' => $pnl,
            'external_id' => $tradeData['external_id'],
        ]);

        // Проверяем дубликаты
        $exists = Trade::where('user_id', $this->userExchange->user_id)
            ->where('external_id', $tradeData['external_id'])
            ->exists();

        if (!$exists) {
            $trade = Trade::create($tradeData);
            
            Log::info('New closed trade saved from WebSocket', [
                'user_id' => $this->userExchange->user_id,
                'trade_id' => $trade->id,
                'symbol' => $trade->symbol,
                'pnl' => $trade->pnl,
            ]);

            // Отправляем real-time уведомление
            \App\Events\RealTradeUpdate::dispatch($trade->toArray());
        } else {
            Log::debug('Duplicate trade skipped', [
                'external_id' => $tradeData['external_id']
            ]);
        }
    }

    private function handleReconnect(): void
    {
        $this->reconnectAttempts++;
        
        if ($this->reconnectAttempts > $this->maxReconnectAttempts) {
            Log::critical('WebSocket max reconnect attempts exceeded', [
                'user_id' => $this->userExchange->user_id,
                'attempts' => $this->reconnectAttempts
            ]);
            return;
        }

        // Exponential backoff: 0, 1, 2, 4, 8 секунд
        $delay = $this->reconnectAttempts === 1 ? 0 : pow(2, $this->reconnectAttempts - 2);
        
        Log::info('Reconnecting WebSocket', [
            'user_id' => $this->userExchange->user_id,
            'attempt' => $this->reconnectAttempts,
            'delay' => $delay
        ]);

        if ($delay > 0) {
            sleep($delay);
        }
        
        $this->start();
    }

    public function resetReconnectAttempts(): void
    {
        $this->reconnectAttempts = 0;
    }
}