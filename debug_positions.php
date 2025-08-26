<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== All Trades ===\n";
foreach (\App\Models\Trade::orderBy('created_at', 'desc')->get() as $trade) {
    echo sprintf(
        "Trade: %s %s %s - Status: %s - PnL: %s - Entry: %s - Exit: %s - Created: %s\n",
        $trade->side,
        $trade->size,
        $trade->symbol,
        $trade->status,
        $trade->pnl ? number_format($trade->pnl, 2) : 'N/A',
        $trade->entry_time ? $trade->entry_time->format('Y-m-d H:i:s') : 'N/A',
        $trade->exit_time ? $trade->exit_time->format('Y-m-d H:i:s') : 'N/A',
        $trade->created_at->format('Y-m-d H:i:s')
    );
}

echo "\n=== Open Positions ===\n";
$openTrades = \App\Models\Trade::where('status', 'open')->get();
if ($openTrades->isEmpty()) {
    echo "No open positions found.\n";
} else {
    foreach ($openTrades as $trade) {
        echo sprintf(
            "Open: %s %s %s - Entry Price: %s - Entry Time: %s\n",
            $trade->side,
            $trade->size,
            $trade->symbol,
            $trade->entry_price,
            $trade->entry_time ? $trade->entry_time->format('Y-m-d H:i:s') : 'N/A'
        );
    }
}

echo "\n=== Statistics ===\n";
echo "Total trades: " . \App\Models\Trade::count() . "\n";
echo "Open trades: " . \App\Models\Trade::where('status', 'open')->count() . "\n";
echo "Closed trades: " . \App\Models\Trade::where('status', 'closed')->count() . "\n";
echo "Total PnL: " . number_format(\App\Models\Trade::where('status', 'closed')->sum('pnl'), 2) . "\n";