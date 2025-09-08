<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Carbon\Carbon;

echo "=== ОТЛАДОЧНАЯ СИНХРОНИЗАЦИЯ KUSDT ===" . PHP_EOL;

$userExchange = UserExchange::where('exchange', 'bybit')->first();
if (!$userExchange) {
    echo "UserExchange не найден" . PHP_EOL;
    exit;
}

$credentials = $userExchange->getApiCredentials();
$bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

$startTime = Carbon::today(); // 00:00:00 сегодня  
$endTime = Carbon::now(); // текущее время

echo "Запрашиваем executions за " . $startTime->format('d.m.Y H:i') . " - " . $endTime->format('d.m.Y H:i') . PHP_EOL;
echo "Временные метки: " . $startTime->getTimestampMs() . " - " . $endTime->getTimestampMs() . PHP_EOL;

try {
    // Получаем все executions
    $executions = $bybitService->getExecutions(
        category: 'linear',
        startTime: $startTime,
        endTime: $endTime,
        limit: 100
    );
    
    echo "Получено executions от Bybit: " . count($executions) . PHP_EOL;
    echo PHP_EOL;
    
    // Ищем KUSDT executions
    $kusdtExecutions = array_filter($executions, function($exec) {
        return $exec['symbol'] === 'KUSDTUSDT';
    });
    
    echo "=== KUSDT EXECUTIONS ОТ BYBIT ===" . PHP_EOL;
    echo "Найдено KUSDTUSDT executions: " . count($kusdtExecutions) . PHP_EOL;
    
    foreach ($kusdtExecutions as $exec) {
        $time = Carbon::createFromTimestampMs((int) $exec['execTime']);
        echo $time->format('d.m.Y H:i:s') . " | " . $exec['symbol'] . " | " . $exec['side'] . " | " . 
             $exec['execQty'] . " | " . $exec['execPrice'] . " | closed: " . ($exec['closedSize'] ?? '0') . 
             " | type: " . ($exec['execType'] ?? 'Trade') . " | execId: " . $exec['execId'] . PHP_EOL;
        
        // Показываем полные данные для первого execution
        if (count($kusdtExecutions) <= 3) {
            echo "--- Полные данные ---" . PHP_EOL;
            echo json_encode($exec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            echo "--- Конец полных данных ---" . PHP_EOL . PHP_EOL;
        }
    }
    
    // Также проверим просто KUSDT (возможно проблема в символе)
    $kusdtShortExecutions = array_filter($executions, function($exec) {
        return $exec['symbol'] === 'KUSDT';
    });
    
    echo PHP_EOL . "=== KUSDT (короткий) EXECUTIONS ОТ BYBIT ===" . PHP_EOL;
    echo "Найдено KUSDT executions: " . count($kusdtShortExecutions) . PHP_EOL;
    
    foreach ($kusdtShortExecutions as $exec) {
        $time = Carbon::createFromTimestampMs((int) $exec['execTime']);
        echo $time->format('d.m.Y H:i:s') . " | " . $exec['symbol'] . " | " . $exec['side'] . " | " . 
             $exec['execQty'] . " | " . $exec['execPrice'] . " | closed: " . ($exec['closedSize'] ?? '0') . 
             " | type: " . ($exec['execType'] ?? 'Trade') . " | execId: " . $exec['execId'] . PHP_EOL;
    }
    
    // Показываем все уникальные символы
    $allSymbols = array_unique(array_column($executions, 'symbol'));
    sort($allSymbols);
    echo PHP_EOL . "=== ВСЕ СИМВОЛЫ ОТ BYBIT ===" . PHP_EOL;
    foreach ($allSymbols as $symbol) {
        $count = count(array_filter($executions, fn($e) => $e['symbol'] === $symbol));
        echo $symbol . " (" . $count . " executions)" . PHP_EOL;
    }
    
} catch (\Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . PHP_EOL;
    echo "Трейс: " . $e->getTraceAsString() . PHP_EOL;
}