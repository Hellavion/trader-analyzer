<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\AnalysisController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestAnalysisApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:analysis-api {user_id=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test analysis API endpoint';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Analyzing trades for user: {$user->email} (ID: {$user->id})");
        
        // Get recent trades
        $recentTrades = \App\Models\Trade::where('user_id', $userId)
            ->where('entry_time', '>=', now()->subDays(7))
            ->orderBy('entry_time', 'desc')
            ->get();
            
        $this->info("Recent trades (last 7 days): " . $recentTrades->count());
        
        foreach ($recentTrades as $trade) {
            $this->line("ID: {$trade->id} | {$trade->symbol} | {$trade->side}");
            $this->line("  Size: {$trade->size} | Entry: {$trade->entry_price}");
            $this->line("  Exit: " . ($trade->exit_price ?? 'NULL') . " | P&L: " . ($trade->pnl ?? 'NULL'));
            $this->line("  Entry Time: {$trade->entry_time}");
            $this->line("  Exit Time: " . ($trade->exit_time ?? 'NULL'));
            $this->line("  Status: {$trade->status} | External: {$trade->external_id}");
            $this->line("---");
        }
        
        // Also check raw P&L calculation
        $this->info("\nRaw P&L Analysis:");
        foreach ($recentTrades as $trade) {
            if ($trade->exit_price) {
                $calculatedPnl = ($trade->side === 'buy') 
                    ? ($trade->exit_price - $trade->entry_price) * $trade->size
                    : ($trade->entry_price - $trade->exit_price) * $trade->size;
                    
                $storedPnl = $trade->pnl;
                $difference = abs($calculatedPnl - $storedPnl);
                
                $this->line("Trade {$trade->id}: Stored={$storedPnl}, Calculated={$calculatedPnl}, Diff={$difference}");
            }
        }
        
        return 0;
    }
}
