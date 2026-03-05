<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;

        return [
            'id'           => $this->id,
            'symbol'       => $this->whenLoaded('buyOrder', fn () => $this->buyOrder->symbol),
            'price'        => $this->whenLoaded('sellOrder', fn () => $this->sellOrder->price),
            'amount'       => $this->whenLoaded('buyOrder', fn () => $this->buyOrder->amount),
            'commission'   => $this->commission,
            'buy_order_id' => $this->buy_order_id,
            'sell_order_id'=> $this->sell_order_id,
            'buyer'        => $this->whenLoaded('buyer', fn () => [
                'id'   => $this->buyer->id,
                'name' => $this->buyer->name,
            ]),
            'seller'       => $this->whenLoaded('seller', fn () => [
                'id'   => $this->seller->id,
                'name' => $this->seller->name,
            ]),
            'my_role'      => match (true) {
                $authId === $this->buyer_id  => 'buyer',
                $authId === $this->seller_id => 'seller',
                default                      => null,
            },
            'executed_at'  => $this->created_at,
        ];
    }
}
