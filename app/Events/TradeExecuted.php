<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeExecuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $buyOrder,
        public readonly Order $sellOrder,
        public readonly string $matchedPrice,
        public readonly string $volume,
        public readonly string $commission,
        public readonly User $buyer,
        public readonly User $seller,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("orders.{$this->buyOrder->symbol}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'buy_order_id'  => $this->buyOrder->id,
            'sell_order_id' => $this->sellOrder->id,
            'symbol'        => $this->buyOrder->symbol,
            'amount'        => $this->buyOrder->amount,
            'matched_price' => $this->matchedPrice,
            'volume'        => $this->volume,
            'commission'    => $this->commission,
            'buyer'  => ['id' => $this->buyer->id,  'name' => $this->buyer->name],
            'seller' => ['id' => $this->seller->id, 'name' => $this->seller->name],
        ];
    }
}
