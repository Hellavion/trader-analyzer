<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== UserExchanges ===\n";
foreach (\App\Models\UserExchange::all() as $ex) {
    echo sprintf(
        "Exchange: %s - Active: %s - Last Sync: %s\n",
        $ex->exchange,
        $ex->is_active ? 'Yes' : 'No',
        $ex->last_sync_at ? $ex->last_sync_at->format('Y-m-d H:i:s') : 'Never'
    );
}

echo "\n=== Recent Trades ===\n";
foreach (\App\Models\Trade::orderBy('created_at', 'desc')->limit(5)->get() as $trade) {
    echo sprintf(
        "Trade: %s %s %s - Status: %s - Created: %s\n",
        $trade->side,
        $trade->size,
        $trade->symbol,
        $trade->status,
        $trade->created_at->format('Y-m-d H:i:s')
    );
}