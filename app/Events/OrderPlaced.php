<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("orders.{$this->order->symbol}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'symbol' => $this->order->symbol,
            'side' => $this->order->side,
            'price' => $this->order->price,
            'amount' => $this->order->amount,
            'status' => $this->order->status,
        ];
    }
}
