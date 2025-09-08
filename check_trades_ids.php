<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Trade;

echo "Trade IDs в базе:" . PHP_EOL;
$trades = Trade::all(['id', 'symbol', 'user_id']);

foreach ($trades as $trade) {
    echo "ID: {$trade->id} | Symbol: {$trade->symbol} | User: {$trade->user_id}" . PHP_EOL;
}

echo PHP_EOL . "Всего сделок: " . $trades->count() . PHP_EOL;