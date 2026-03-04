<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $buyOrder,
        public readonly Order $sellOrder,
        public readonly string $matchedPrice,
        public readonly string $volume,
        public readonly string $commission,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->buyOrder->user_id}"),
            new PrivateChannel("user.{$this->sellOrder->user_id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'buy_order_id' => $this->buyOrder->id,
            'sell_order_id' => $this->sellOrder->id,
            'symbol' => $this->buyOrder->symbol,
            'amount' => $this->buyOrder->amount,
            'matched_price' => $this->matchedPrice,
            'volume' => $this->volume,
            'commission' => $this->commission,
        ];
    }
}
