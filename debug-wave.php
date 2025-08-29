<?php
// Debug script для выявления проблемы с Wave

echo "1. Testing basic Laravel boot...\n";
require_once 'bootstrap/app.php';

echo "2. Laravel booted OK\n";

$app = \Illuminate\Foundation\Application::getInstance();
echo "3. App instance OK\n";

echo "4. Testing Redis config...\n";
$redisConfig = config('database.redis.default');
echo "Redis default config: " . json_encode($redisConfig) . "\n";

echo "5. Testing broadcasting config...\n";  
$broadcastConfig = config('broadcasting.connections.redis.connection');
echo "Broadcasting redis connection: $broadcastConfig\n";

echo "6. Creating subscription config...\n";
$subscriptionKey = "database.redis.{$broadcastConfig}-subscription";
echo "Subscription key: $subscriptionKey\n";

echo "7. Setting subscription config...\n";
config()->set($subscriptionKey, $redisConfig);
echo "Subscription config set OK\n";

echo "8. All tests passed!\n";