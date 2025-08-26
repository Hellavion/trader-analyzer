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

echo "=== Testing Dashboard API ===\n";
echo "User: {$user->email} (ID: {$user->id})\n\n";

// Создаем контроллер и вызываем метод
$controller = new \App\Http\Controllers\Api\DashboardController();
$request = new \Illuminate\Http\Request(['period' => '30d']);

try {
    $response = $controller->getOverview($request);
    $data = json_decode($response->getContent(), true);
    
    echo "=== API Response ===\n";
    echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    
    if ($data['success']) {
        echo "\n=== Performance Data ===\n";
        $performance = $data['data']['performance'];
        foreach ($performance as $key => $value) {
            echo "{$key}: {$value}\n";
        }
        
        echo "\n=== Trades Data ===\n";
        $trades = $data['data']['trades'];
        foreach ($trades as $key => $value) {
            echo "{$key}: {$value}\n";
        }
        
        echo "\n=== Connections Data ===\n";
        $connections = $data['data']['connections'];
        foreach ($connections as $key => $value) {
            if (is_array($value)) {
                echo "{$key}: " . json_encode($value) . "\n";
            } else {
                echo "{$key}: {$value}\n";
            }
        }
    } else {
        echo "Error: " . $data['message'] . "\n";
    }
    
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}