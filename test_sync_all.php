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

echo "=== Testing SyncAll Functionality ===\n";
echo "User: {$user->email} (ID: {$user->id})\n\n";

// Создаем контроллер и тестируем синхронизацию
$controller = new \App\Http\Controllers\Api\ExchangeController();

try {
    echo "=== Testing Bybit Sync ===\n";
    $request = new \Illuminate\Http\Request();
    $response = $controller->sync('bybit', $request);
    $data = json_decode($response->getContent(), true);
    
    echo "Sync Response: " . json_encode($data) . "\n\n";
    
    if ($data['success']) {
        echo "Sync initiated successfully!\n";
        echo "Status: " . $data['data']['sync_status'] . "\n";
        echo "Estimated completion: " . $data['data']['estimated_completion'] . "\n";
    } else {
        echo "Sync failed: " . $data['message'] . "\n";
    }
    
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Testing Dashboard Data After Sync ===\n";
sleep(3); // Ждем немного для завершения синхронизации

try {
    $dashboardController = new \App\Http\Controllers\Api\DashboardController();
    $response = $dashboardController->getOverview(new \Illuminate\Http\Request(['period' => '30d']));
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "Performance Data:\n";
        foreach ($data['data']['performance'] as $key => $value) {
            echo "{$key}: {$value}\n";
        }
    }
} catch (\Exception $e) {
    echo "Dashboard test failed: " . $e->getMessage() . "\n";
}