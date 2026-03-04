<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertUnauthorized();
    }

    public function test_returns_user_own_orders_when_no_symbol_given(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(3)->create(['user_id' => $user->id, 'symbol' => 'BTC']);
        Order::factory()->count(2)->create(['user_id' => $other->id, 'symbol' => 'BTC']);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_returns_all_open_orders_for_symbol(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        Order::factory()->open()->create(['user_id' => $buyer->id, 'symbol' => 'BTC', 'side' => 'buy']);
        Order::factory()->open()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);
        Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);
        Order::factory()->open()->create(['user_id' => $buyer->id, 'symbol' => 'ETH', 'side' => 'buy']);

        $response = $this->actingAs($buyer)->getJson('/api/orders?symbol=BTC');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rejects_invalid_symbol(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/orders?symbol=XRP');

        $response->assertUnprocessable();
    }

    public function test_order_resource_has_expected_fields(): void
    {
        $user = User::factory()->create();
        Order::factory()->open()->create(['user_id' => $user->id, 'symbol' => 'BTC']);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'symbol', 'side', 'price', 'amount', 'status', 'created_at']],
            ]);
    }

    public function test_orderbook_orders_are_sorted_by_price_ascending(): void
    {
        $user = User::factory()->create();

        Order::factory()->open()->create(['user_id' => $user->id, 'symbol' => 'BTC', 'side' => 'sell', 'price' => '95000.00000000']);
        Order::factory()->open()->create(['user_id' => $user->id, 'symbol' => 'BTC', 'side' => 'sell', 'price' => '90000.00000000']);

        $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

        $response->assertOk()
            ->assertJsonPath('data.0.price', '90000.00000000');
    }
}
