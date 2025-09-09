<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Exchange\BybitService;
use App\Models\Trade;

$trade = Trade::first();
if (!$trade) {
    echo "No trade found\n";
    exit;
}

echo "Testing chart data for trade ID: {$trade->id}\n";
echo "Symbol: {$trade->symbol}\n";
echo "Entry time: {$trade->entry_time}\n";

try {
    $bybitService = new BybitService();
    $startTime = $trade->entry_time->copy()->subHours(6);
    $endTime = ($trade->exit_time ?? now())->copy()->addHours(2);
    
    echo "Requesting kline data from {$startTime} to {$endTime}\n";
    
    $klineData = $bybitService->getKlineData(
        category: 'linear',
        symbol: $trade->symbol,
        interval: '15',
        start: $startTime,
        end: $endTime,
        limit: 200
    );
    
    echo "Retrieved " . count($klineData) . " candles\n";
    
    if (count($klineData) > 0) {
        echo "First candle: " . json_encode($klineData[0]) . "\n";
        echo "Last candle: " . json_encode($klineData[array_key_last($klineData)]) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}