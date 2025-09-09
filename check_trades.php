<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Current trades:\n";
foreach(App\Models\Trade::all() as $trade) {
    echo "ID:{$trade->id} {$trade->symbol} {$trade->side} {$trade->status} size:{$trade->size}\n";
    echo "  Entry: {$trade->entry_price} at " . $trade->entry_time->format('Y-m-d H:i:s') . "\n";
    if ($trade->exit_price) {
        echo "  Exit: {$trade->exit_price} at " . $trade->exit_time->format('Y-m-d H:i:s') . "\n";
    }
    echo "  PnL: {$trade->pnl} | Fee: {$trade->fee}\n";
    echo "  External ID: {$trade->external_id}\n\n";
}

echo "\nRecent executions:\n";
foreach(App\Models\Execution::orderBy('execution_time', 'desc')->take(10)->get() as $exec) {
    echo "ID:{$exec->execution_id} {$exec->symbol} {$exec->side} closed:{$exec->closed_size} time:" . $exec->execution_time->format('Y-m-d H:i:s') . "\n";
}