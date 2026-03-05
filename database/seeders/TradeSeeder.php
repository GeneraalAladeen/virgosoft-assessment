<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TradeSeeder extends Seeder
{
    private const COMMISSION_RATE = '0.015';

    public function run(): void
    {
        $alice  = User::where('email', 'alice@example.com')->firstOrFail();
        $bob    = User::where('email', 'bob@example.com')->firstOrFail();
        $maker1 = User::where('email', 'james.whitfield@example.com')->firstOrFail();
        $maker2 = User::where('email', 'priya.nair@example.com')->firstOrFail();

        // Historical trades: [buyer, seller, symbol, price, amount, minutes_ago]
        $trades = [
            // Pure market-maker trades (background market activity, no Alice/Bob involved)
            [$maker1, $maker2, 'BTC', '44800.00', '0.30000000', 180],
            [$maker2, $maker1, 'ETH', '2490.00',  '8.00000000', 165],
            [$maker1, $maker2, 'BTC', '44850.00', '0.60000000', 150],
            [$maker2, $maker1, 'ETH', '2495.00',  '4.00000000', 135],

            // Alice trades
            [$alice,  $maker1, 'BTC', '44950.00', '0.10000000', 120],
            [$maker2, $alice,  'ETH', '2498.00',  '5.00000000',  60],
            [$alice,  $maker1, 'BTC', '45050.00', '0.50000000',  45],
            [$alice,  $maker2, 'ETH', '2515.00',  '3.00000000',  20],
            [$alice,  $maker1, 'BTC', '45100.00', '0.20000000',   5],

            // Bob trades
            [$bob,    $maker2, 'BTC', '45000.00', '0.25000000',  95],
            [$maker1, $bob,    'ETH', '2505.00',  '2.00000000',  80],
            [$bob,    $maker1, 'BTC', '44980.00', '0.15000000',  30],
            [$maker2, $bob,    'ETH', '2520.00',  '1.00000000',  10],
        ];

        foreach ($trades as [$buyer, $seller, $symbol, $price, $amount, $minutesAgo]) {
            $executedAt = Carbon::now()->subMinutes($minutesAgo);

            $buyOrder = Order::create([
                'user_id'    => $buyer->id,
                'symbol'     => $symbol,
                'side'       => 'buy',
                'price'      => $price,
                'amount'     => $amount,
                'status'     => OrderStatus::Filled,
                'created_at' => $executedAt,
                'updated_at' => $executedAt,
            ]);

            $sellOrder = Order::create([
                'user_id'    => $seller->id,
                'symbol'     => $symbol,
                'side'       => 'sell',
                'price'      => $price,
                'amount'     => $amount,
                'status'     => OrderStatus::Filled,
                'created_at' => $executedAt,
                'updated_at' => $executedAt,
            ]);

            $volume     = bcmul($amount, $price, 8);
            $commission = bcmul($volume, self::COMMISSION_RATE, 8);

            Trade::create([
                'buy_order_id'  => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'buyer_id'      => $buyer->id,
                'seller_id'     => $seller->id,
                'commission'    => $commission,
                'created_at'    => $executedAt,
                'updated_at'    => $executedAt,
            ]);
        }
    }
}
