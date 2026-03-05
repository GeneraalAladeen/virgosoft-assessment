<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrderbookSeeder extends Seeder
{
    public function run(): void
    {
        // BTC bids/asks must not overlap so no matching occurs.
        // BTC mid ~$45,000 | ETH mid ~$2,500
        $orders = [
            // --- BTC bids (buy) ---
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '44900.00', 'amount' => '0.25000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '44750.00', 'amount' => '0.50000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '44600.00', 'amount' => '0.10000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '44400.00', 'amount' => '1.00000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '44200.00', 'amount' => '0.30000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '44000.00', 'amount' => '0.75000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '43800.00', 'amount' => '0.20000000'],
            ['symbol' => 'BTC', 'side' => 'buy',  'price' => '43500.00', 'amount' => '2.00000000'],

            // --- BTC asks (sell) ---
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '45100.00', 'amount' => '0.15000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '45250.00', 'amount' => '0.40000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '45400.00', 'amount' => '0.60000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '45600.00', 'amount' => '0.25000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '45800.00', 'amount' => '1.00000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '46000.00', 'amount' => '0.50000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '46200.00', 'amount' => '0.35000000'],
            ['symbol' => 'BTC', 'side' => 'sell', 'price' => '46500.00', 'amount' => '0.80000000'],

            // --- ETH bids (buy) ---
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2490.00',  'amount' => '2.00000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2475.00',  'amount' => '5.00000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2460.00',  'amount' => '3.50000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2440.00',  'amount' => '10.00000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2420.00',  'amount' => '7.00000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2400.00',  'amount' => '4.00000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2380.00',  'amount' => '8.00000000'],
            ['symbol' => 'ETH', 'side' => 'buy',  'price' => '2350.00',  'amount' => '15.00000000'],

            // --- ETH asks (sell) ---
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2510.00',  'amount' => '1.50000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2525.00',  'amount' => '4.00000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2540.00',  'amount' => '6.00000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2560.00',  'amount' => '3.00000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2580.00',  'amount' => '9.00000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2600.00',  'amount' => '5.00000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2625.00',  'amount' => '2.50000000'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2650.00',  'amount' => '12.00000000'],
        ];

        // Spread orders across 8 market-maker users
        $makerProfiles = [
            ['name' => 'James Whitfield',  'email' => 'james.whitfield@example.com'],
            ['name' => 'Priya Nair',       'email' => 'priya.nair@example.com'],
            ['name' => 'Carlos Reyes',     'email' => 'carlos.reyes@example.com'],
            ['name' => 'Sophie Laurent',   'email' => 'sophie.laurent@example.com'],
            ['name' => 'David Okonkwo',    'email' => 'david.okonkwo@example.com'],
            ['name' => 'Mia Johansson',    'email' => 'mia.johansson@example.com'],
            ['name' => 'Tariq Hassan',     'email' => 'tariq.hassan@example.com'],
            ['name' => 'Elena Voronova',   'email' => 'elena.voronova@example.com'],
        ];

        $makers = collect($makerProfiles)->map(fn ($p) => User::create([
            'name'     => $p['name'],
            'email'    => $p['email'],
            'password' => Hash::make('password'),
            'balance'  => '500000.00000000',
        ]));

        // Give every maker enough BTC and ETH to cover sell orders
        foreach ($makers as $maker) {
            Asset::create(['user_id' => $maker->id, 'symbol' => 'BTC', 'amount' => '10.00000000', 'locked_amount' => '0.00000000']);
            Asset::create(['user_id' => $maker->id, 'symbol' => 'ETH', 'amount' => '200.00000000', 'locked_amount' => '0.00000000']);
        }

        foreach ($orders as $i => $spec) {
            $maker = $makers[$i % $makers->count()];

            Order::create([
                'user_id' => $maker->id,
                'symbol'  => $spec['symbol'],
                'side'    => $spec['side'],
                'price'   => $spec['price'],
                'amount'  => $spec['amount'],
                'status'  => OrderStatus::Open,
            ]);

            // Mirror the balance/asset locking the service would do
            if ($spec['side'] === 'buy') {
                $cost = bcmul($spec['amount'], $spec['price'], 8);
                $maker->balance = bcsub((string) $maker->balance, $cost, 8);
                $maker->save();
            } else {
                $asset = Asset::where('user_id', $maker->id)->where('symbol', $spec['symbol'])->first();
                $asset->locked_amount = bcadd((string) $asset->locked_amount, $spec['amount'], 8);
                $asset->save();
            }
        }
    }
}
