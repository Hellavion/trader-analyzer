<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestTradeUpdate implements ShouldBroadcast
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
            new \Illuminate\Broadcasting\Channel('test-trades'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TestTradeUpdate';
    }

    public function broadcastWith(): array
    {
        return [
            'trade' => $this->trade
        ];
    }
}