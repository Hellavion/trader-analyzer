<?php

namespace App\Console\Commands;

use App\Models\UserExchange;
use App\Services\Exchange\BybitWebSocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BybitWebSocketCommand extends Command
{
    protected $signature = 'bybit:websocket {--user-id= : Specific user ID to sync}';
    protected $description = 'Start Bybit WebSocket listener for real-time trades';

    public function handle(): int
    {
        $this->info('🚀 Starting Bybit WebSocket listener...');

        $userId = $this->option('user-id');
        
        if ($userId) {
            $exchanges = UserExchange::where('user_id', $userId)
                ->where('exchange', 'bybit')
                ->where('is_active', true)
                ->get();
        } else {
            $exchanges = UserExchange::where('exchange', 'bybit')
                ->where('is_active', true)
                ->get();
        }

        if ($exchanges->isEmpty()) {
            $this->error('No active Bybit connections found');
            return self::FAILURE;
        }

        $this->info("Found {$exchanges->count()} active Bybit connections");

        foreach ($exchanges as $exchange) {
            $this->info("Starting WebSocket for user {$exchange->user_id}");
            
            try {
                $webSocketService = new BybitWebSocketService($exchange);
                $webSocketService->start();
            } catch (\Exception $e) {
                $this->error("Failed to start WebSocket for user {$exchange->user_id}: {$e->getMessage()}");
                Log::error('WebSocket failed to start', [
                    'user_id' => $exchange->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return self::SUCCESS;
    }
}
