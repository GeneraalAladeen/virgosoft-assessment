<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trade>
 */
class TradeFactory extends Factory
{
    public function definition(): array
    {
        $buyer  = User::factory()->create();
        $seller = User::factory()->create();

        return [
            'buy_order_id'  => Order::factory()->filled()->create(['user_id' => $buyer->id,  'side' => 'buy']),
            'sell_order_id' => Order::factory()->filled()->create(['user_id' => $seller->id, 'side' => 'sell']),
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'commission'    => fake()->randomFloat(8, 1, 500),
        ];
    }
}
