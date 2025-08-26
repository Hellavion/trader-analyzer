<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Симулируем авторизацию пользователя hellavion@gmail.com
$user = \App\Models\User::where('email', 'hellavion@gmail.com')->first();
if (!$user) {
    echo "User not found!\n";
    exit;
}

// Устанавливаем пользователя как авторизованного
\Illuminate\Support\Facades\Auth::login($user);

echo "=== Testing Dashboard Auto-Sync Functionality ===\n";
echo "User: {$user->email} (ID: {$user->id})\n\n";

echo "1. Testing Exchange Service syncAll method...\n";
try {
    $exchangeController = new \App\Http\Controllers\Api\ExchangeController();
    $exchanges = \App\Models\UserExchange::where('user_id', $user->id)->where('is_active', true)->get();
    
    echo "Found " . $exchanges->count() . " active exchange(s):\n";
    foreach ($exchanges as $exchange) {
        echo "  - {$exchange->display_name} (last sync: " . ($exchange->last_sync_at ? $exchange->last_sync_at->diffForHumans() : 'never') . ")\n";
    }
    
    if ($exchanges->count() > 0) {
        echo "\n2. Testing sync for first exchange...\n";
        $firstExchange = $exchanges->first();
        $request = new \Illuminate\Http\Request();
        $response = $exchangeController->sync($firstExchange->exchange, $request);
        $data = json_decode($response->getContent(), true);
        
        echo "Sync Response: " . ($data['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        echo "Message: " . $data['message'] . "\n";
        if ($data['success']) {
            echo "Status: " . $data['data']['sync_status'] . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error testing sync: " . $e->getMessage() . "\n";
}

echo "\n3. Testing Dashboard API after sync...\n";
sleep(2); // Wait a bit for sync to process

try {
    $dashboardController = new \App\Http\Controllers\Api\DashboardController();
    $request = new \Illuminate\Http\Request(['period' => '30d']);
    $response = $dashboardController->getOverview($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "Dashboard API: SUCCESS\n";
        echo "Total PnL: " . $data['data']['performance']['total_pnl'] . "\n";
        echo "Realized PnL: " . $data['data']['performance']['realized_pnl'] . "\n";
        echo "Unrealized PnL: " . $data['data']['performance']['unrealized_pnl'] . "\n";
        echo "Open positions: " . $data['data']['trades']['open'] . "\n";
        echo "Total trades: " . $data['data']['trades']['total'] . "\n";
    } else {
        echo "Dashboard API: FAILED - " . $data['message'] . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error testing dashboard: " . $e->getMessage() . "\n";
}

echo "\n=== Test Completed ===\n";