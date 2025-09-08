<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Carbon\Carbon;

echo "=== ПОИСК ПРОПУЩЕННОГО EXECUTION 01:42:25 ===" . PHP_EOL;

$userExchange = UserExchange::where('exchange', 'bybit')->first();
$credentials = $userExchange->getApiCredentials();
$bybitService = new BybitService($credentials['api_key'], $credentials['secret']);

// Проверяем разные временные диапазоны вокруг 01:42:25
$targetTime = Carbon::today()->setHour(1)->setMinute(42)->setSecond(25);
echo "Ищем execution около: " . $targetTime->format('d.m.Y H:i:s') . PHP_EOL;

// Диапазон ±30 минут вокруг целевого времени
$startTime = $targetTime->copy()->subMinutes(30);
$endTime = $targetTime->copy()->addMinutes(30);

echo "Диапазон поиска: " . $startTime->format('H:i:s') . " - " . $endTime->format('H:i:s') . PHP_EOL;
echo "Временные метки: " . $startTime->getTimestampMs() . " - " . $endTime->getTimestampMs() . PHP_EOL;

try {
    $executions = $bybitService->getExecutions(
        category: 'linear',
        startTime: $startTime,
        endTime: $endTime,
        limit: 100
    );
    
    echo "Получено executions: " . count($executions) . PHP_EOL;
    
    // Все KUSDT в этом диапазоне
    $kusdtExecutions = array_filter($executions, function($exec) {
        return $exec['symbol'] === 'KUSDT';
    });
    
    echo "KUSDT executions в диапазоне: " . count($kusdtExecutions) . PHP_EOL;
    
    foreach ($kusdtExecutions as $exec) {
        $time = Carbon::createFromTimestampMs((int) $exec['execTime']);
        echo $time->format('H:i:s') . " | " . $exec['side'] . " | " . $exec['execQty'] . 
             " | " . $exec['execPrice'] . " | closed: " . ($exec['closedSize'] ?? '0') . 
             " | execId: " . $exec['execId'] . PHP_EOL;
    }
    
    // Также проверим весь день еще раз, может пропустили что-то
    echo PHP_EOL . "=== ПРОВЕРКА ВСЕГО ДНЯ ЕЩЕ РАЗ ===" . PHP_EOL;
    
    $dayStart = Carbon::today();
    $dayEnd = Carbon::today()->endOfDay();
    
    $allExecutions = $bybitService->getExecutions(
        category: 'linear',
        startTime: $dayStart,
        endTime: $dayEnd,
        limit: 200 // Увеличиваем лимит
    );
    
    $allKusdtExecutions = array_filter($allExecutions, function($exec) {
        return $exec['symbol'] === 'KUSDT';
    });
    
    echo "Всего KUSDT executions за день: " . count($allKusdtExecutions) . PHP_EOL;
    
    // Сортируем по времени для хронологического порядка
    usort($allKusdtExecutions, function($a, $b) {
        return $a['execTime'] <=> $b['execTime'];
    });
    
    foreach ($allKusdtExecutions as $exec) {
        $time = Carbon::createFromTimestampMs((int) $exec['execTime']);
        echo $time->format('H:i:s') . " | " . $exec['side'] . " | " . $exec['execQty'] . 
             " | " . $exec['execPrice'] . " | closed: " . ($exec['closedSize'] ?? '0') . 
             " | type: " . ($exec['execType'] ?? 'Trade') . PHP_EOL;
             
        // Показываем детали для SELL executions
        if (strtolower($exec['side']) === 'sell' && ($exec['execType'] ?? 'Trade') === 'Trade') {
            echo "   *** ВОЗМОЖНЫЙ ОТКРЫВАЮЩИЙ SELL ***" . PHP_EOL;
            echo "   Полные данные: " . json_encode($exec, JSON_PRETTY_PRINT) . PHP_EOL;
        }
    }
    
} catch (\Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . PHP_EOL;
}