<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Jobs\SyncBybitTradesJob;
use Carbon\Carbon;

class SyncUserTrades extends Command
{
    protected $signature = 'sync:user-trades {user_id=2} {--days=7}';
    protected $description = 'Sync trades for a specific user from their connected exchanges';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $days = $this->option('days');
        
        // Bybit API ограничивает период до 7 дней
        $maxDays = min($days, 7);
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Syncing trades for user: {$user->email} (ID: {$user->id})");
        $this->info("Period: last {$maxDays} days (Bybit API limit)");

        $exchanges = UserExchange::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($exchanges->isEmpty()) {
            $this->error("No active exchanges found for user {$userId}");
            return 1;
        }

        $startTime = Carbon::now()->subDays($maxDays)->startOfDay();
        $endTime = Carbon::now()->subMinutes(10);

        foreach ($exchanges as $exchange) {
            $this->info("Syncing {$exchange->exchange} exchange...");
            
            if ($exchange->exchange === 'bybit') {
                SyncBybitTradesJob::dispatch($exchange, $startTime, $endTime);
                $this->info("Bybit sync job dispatched");
            }
        }

        $this->info("Sync jobs have been dispatched. Check logs for progress.");
        return 0;
    }
}
