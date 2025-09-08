<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Execution;
use App\Models\Trade;

echo "=== СТАТИСТИКА ===" . PHP_EOL;
echo "Executions: " . Execution::count() . PHP_EOL;
echo "Trades: " . Trade::count() . PHP_EOL;
echo PHP_EOL;

echo "=== EXECUTIONS (все символы) ===" . PHP_EOL;
$executions = Execution::orderBy('execution_time', 'desc')->get(['symbol', 'side', 'quantity', 'price', 'closed_size', 'exec_type', 'execution_time']);
foreach ($executions as $exec) {
    echo $exec->execution_time->format('H:i:s') . ' | ' . $exec->symbol . ' | ' . $exec->side . ' | ' . $exec->quantity . ' | ' . $exec->price . ' | closed: ' . $exec->closed_size . ' | type: ' . $exec->exec_type . PHP_EOL;
}

echo PHP_EOL . "=== TRADES (все) ===" . PHP_EOL;
$trades = Trade::orderBy('entry_time', 'desc')->get(['symbol', 'side', 'size', 'entry_price', 'exit_price', 'status', 'entry_time', 'exit_time', 'pnl', 'funding_fees']);
foreach ($trades as $trade) {
    $exitInfo = $trade->exit_time ? $trade->exit_time->format('H:i:s') : 'открыта';
    $pnlInfo = $trade->pnl ? round($trade->pnl, 2) : '0';
    echo $trade->entry_time->format('H:i:s') . ' | ' . $trade->symbol . ' | ' . $trade->side . ' | ' . $trade->size . ' | ' . $trade->entry_price . ' -> ' . ($trade->exit_price ?: 'открыта') . ' | ' . $trade->status . ' | PnL: ' . $pnlInfo . ' | Funding: ' . $trade->funding_fees . PHP_EOL;
}

echo PHP_EOL . "=== ПРОВЕРКА KUSDT ===" . PHP_EOL;
$kusdtExecutions = Execution::where('symbol', 'KUSDTUSDT')->orderBy('execution_time')->get(['side', 'quantity', 'price', 'closed_size', 'exec_type', 'execution_time']);
echo "KUSDT executions найдено: " . $kusdtExecutions->count() . PHP_EOL;
foreach ($kusdtExecutions as $exec) {
    echo $exec->execution_time->format('H:i:s') . ' | ' . $exec->side . ' | ' . $exec->quantity . ' | ' . $exec->price . ' | closed: ' . $exec->closed_size . ' | type: ' . $exec->exec_type . PHP_EOL;
}

$kusdtTrades = Trade::where('symbol', 'KUSDTUSDT')->orderBy('entry_time')->get();
echo PHP_EOL . "KUSDT trades найдено: " . $kusdtTrades->count() . PHP_EOL;
foreach ($kusdtTrades as $trade) {
    echo $trade->entry_time->format('H:i:s') . ' | ' . $trade->side . ' | ' . $trade->size . ' | ' . $trade->entry_price . ' -> ' . ($trade->exit_price ?: 'открыта') . ' | ' . $trade->status . PHP_EOL;
}