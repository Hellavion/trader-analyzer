<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealTradeUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $trade;

    public function __construct(array $trade)
    {
        $this->trade = $trade;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('live-trades'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'RealTradeUpdate';
    }

    public function broadcastWith(): array
    {
        return [
            'trade' => $this->trade
        ];
    }
}