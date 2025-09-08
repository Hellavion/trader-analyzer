<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Carbon\Carbon;

echo "=== ПРОВЕРКА АЛЬТЕРНАТИВНЫХ API ===" . PHP_EOL;

$userExchange = UserExchange::where('exchange', 'bybit')->first();
$credentials = $userExchange->getApiCredentials();
$bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

$dayStart = Carbon::today();
$dayEnd = Carbon::now();

try {
    // 1. Проверяем Closed PnL API
    echo "=== 1. CLOSED PNL API ===" . PHP_EOL;
    $closedPnl = $bybitService->getClosedPnL(
        category: 'linear',
        startTime: $dayStart,
        endTime: $dayEnd,
        limit: 50
    );
    
    echo "Closed PnL записей: " . count($closedPnl) . PHP_EOL;
    
    foreach ($closedPnl as $pnl) {
        if ($pnl['symbol'] === 'KUSDTUSDT' || $pnl['symbol'] === 'KUSDT') {
            echo "KUSDT PnL найден!" . PHP_EOL;
            echo json_encode($pnl, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    }
    
    // 2. Проверяем Trading History (это тот же execution/list но проверим еще раз)
    echo PHP_EOL . "=== 2. TRADING HISTORY с большим лимитом ===" . PHP_EOL;
    $tradingHistory = $bybitService->getTradingHistory(
        category: 'linear',
        startTime: $dayStart,
        endTime: $dayEnd,
        limit: 200 // Максимальный лимит
    );
    
    echo "Trading History записей: " . count($tradingHistory) . PHP_EOL;
    
    $kusdtTrades = array_filter($tradingHistory, function($trade) {
        return $trade['symbol'] === 'KUSDT';
    });
    
    echo "KUSDT в Trading History: " . count($kusdtTrades) . PHP_EOL;
    
    // 3. Попробуем расширенный диапазон времени (может быть сдвиг)
    echo PHP_EOL . "=== 3. РАСШИРЕННЫЙ ВРЕМЕННОЙ ДИАПАЗОН ===" . PHP_EOL;
    
    $extendedStart = Carbon::yesterday();
    $extendedEnd = Carbon::now();
    
    echo "Проверяем с " . $extendedStart->format('d.m.Y H:i') . " по " . $extendedEnd->format('d.m.Y H:i') . PHP_EOL;
    
    $extendedExecutions = $bybitService->getExecutions(
        category: 'linear', 
        startTime: $extendedStart,
        endTime: $extendedEnd,
        limit: 200
    );
    
    $extendedKusdt = array_filter($extendedExecutions, function($exec) {
        return $exec['symbol'] === 'KUSDT';
    });
    
    echo "KUSDT executions в расширенном диапазоне: " . count($extendedKusdt) . PHP_EOL;
    
    // Сортируем по времени
    usort($extendedKusdt, function($a, $b) {
        return $a['execTime'] <=> $b['execTime'];
    });
    
    foreach ($extendedKusdt as $exec) {
        $time = Carbon::createFromTimestampMs((int) $exec['execTime']);
        echo $time->format('d.m.Y H:i:s') . " | " . $exec['side'] . " | " . $exec['execQty'] . 
             " | " . $exec['execPrice'] . " | closed: " . ($exec['closedSize'] ?? '0') . 
             " | type: " . ($exec['execType'] ?? 'Trade') . PHP_EOL;
    }
    
} catch (\Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . PHP_EOL;
    echo "Трейс: " . $e->getTraceAsString() . PHP_EOL;
}