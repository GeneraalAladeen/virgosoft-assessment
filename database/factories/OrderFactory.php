<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement(['BTC', 'ETH']),
            'side' => fake()->randomElement(['buy', 'sell']),
            'price' => fake()->randomFloat(8, 1000, 100000),
            'amount' => fake()->randomFloat(8, 0.001, 10),
            'status' => OrderStatus::Open,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => OrderStatus::Open]);
    }

    public function filled(): static
    {
        return $this->state(['status' => OrderStatus::Filled]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }
}
