<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;

class CheckTradeData extends Command
{
    protected $signature = 'check:trade-data {user_id=2}';
    protected $description = 'Check trade data structure and grouping';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info("Checking trade data for user {$userId}");
        
        $trades = Trade::where('user_id', $userId)
            ->orderBy('exec_time', 'desc')
            ->get();
            
        $this->info("Total executions: " . $trades->count());
        
        $orderGroups = $trades->groupBy('order_id');
        $this->info("Unique orders: " . $orderGroups->count());
        
        foreach ($orderGroups as $orderId => $executions) {
            $this->line("Order ID: '{$orderId}' - Executions: " . $executions->count());
            
            foreach ($executions as $exec) {
                $this->line("  ID:{$exec->id} Ord:'{$exec->order_id}' Ext:'{$exec->external_id}' {$exec->symbol} {$exec->side} Qty:{$exec->exec_qty} Price:{$exec->exec_price} Status:{$exec->status}");
            }
            $this->line("");
        }
        
        return 0;
    }
}
