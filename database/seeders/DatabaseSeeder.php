<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\OrderbookSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'balance' => '100000.00000000',
        ]);

        Asset::create([
            'user_id' => $alice->id,
            'symbol' => 'BTC',
            'amount' => '1.00000000',
            'locked_amount' => '0.00000000',
        ]);

        Asset::create([
            'user_id' => $alice->id,
            'symbol' => 'ETH',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
            'balance' => '100000.00000000',
        ]);

        Asset::create([
            'user_id' => $bob->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.00000000',
        ]);

        Asset::create([
            'user_id' => $bob->id,
            'symbol' => 'ETH',
            'amount' => '5.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $this->call(OrderbookSeeder::class);
        $this->call(TradeSeeder::class);
    }
}
