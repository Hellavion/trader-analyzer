<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeExecuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;

    public function __construct(Trade $trade)
    {
        $this->trade = $trade;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->trade->user_id}.trades"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TradeExecuted';
    }

    public function broadcastWith(): array
    {
        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'side' => $this->trade->side,
                'size' => $this->trade->size,
                'entry_price' => $this->trade->entry_price,
                'exit_price' => $this->trade->exit_price,
                'pnl' => $this->trade->pnl,
                'entry_time' => $this->trade->entry_time?->toISOString(),
                'exit_time' => $this->trade->exit_time?->toISOString(),
            ]
        ];
    }
}
