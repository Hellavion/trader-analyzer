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

echo "=== Testing Dashboard Widgets API ===\n";
echo "User: {$user->email} (ID: {$user->id})\n\n";

// Создаем контроллер и вызываем метод
$controller = new \App\Http\Controllers\Api\DashboardController();

try {
    $response = $controller->getWidgets();
    $data = json_decode($response->getContent(), true);
    
    echo "=== API Response ===\n";
    echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    
    if ($data['success']) {
        echo "\n=== Exchange Breakdown Widget ===\n";
        $exchangeBreakdown = $data['data']['exchange_breakdown'];
        if (empty($exchangeBreakdown)) {
            echo "Empty exchange breakdown data\n";
        } else {
            foreach ($exchangeBreakdown as $exchange) {
                echo "Exchange: {$exchange['exchange']}, Trades: {$exchange['trades']}, PnL: {$exchange['pnl']}\n";
            }
        }
        
        echo "\n=== All Widgets Data ===\n";
        foreach ($data['data'] as $key => $value) {
            echo "{$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    } else {
        echo "Error: " . $data['message'] . "\n";
    }
    
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}