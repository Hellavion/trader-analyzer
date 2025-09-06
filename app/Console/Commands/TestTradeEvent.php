<?php

namespace App\Console\Commands;

use App\Events\TradeExecuted;
use App\Models\Trade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TestTradeEvent extends Command
{
    protected $signature = 'test:trade-event {--user-id=2}';
    protected $description = 'Test trade broadcasting by creating fake trade and sending event';

    public function handle()
    {
        $userId = $this->option('user-id');
        
        $this->info("Creating test trade for user {$userId}...");
        
        // Создаем тестовую сделку
        $trade = Trade::create([
            'user_id' => $userId,
            'exchange' => 'bybit',
            'external_id' => 'test_' . time(),
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'size' => 0.01,
            'entry_price' => 43000,
            'exit_price' => 43500,
            'pnl' => 5.0,
            'fee' => 0.1,
            'entry_time' => Carbon::now()->subMinutes(5),
            'exit_time' => Carbon::now(),
            'status' => 'closed',
            'raw_data' => json_encode(['test' => true]),
        ]);

        $this->info("Test trade created with ID: {$trade->id}");
        
        Log::info('Test trade created', [
            'trade_id' => $trade->id,
            'user_id' => $userId,
            'symbol' => $trade->symbol,
        ]);
        
        // Отправляем broadcasting событие
        $this->info("Broadcasting TradeExecuted event...");
        Log::info('Broadcasting test TradeExecuted event', ['trade_id' => $trade->id]);
        
        broadcast(new TradeExecuted($trade));
        
        Log::info('Test TradeExecuted event broadcasted', ['trade_id' => $trade->id]);
        $this->info("Event broadcasted! Check frontend for updates.");
        
        return 0;
    }
}