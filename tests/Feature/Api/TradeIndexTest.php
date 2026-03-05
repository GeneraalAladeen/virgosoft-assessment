<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_trades(): void
    {
        $response = $this->getJson('/api/trades');

        $response->assertUnauthorized();
    }

    public function test_returns_all_trades(): void
    {
        $buyer  = User::factory()->create();
        $seller = User::factory()->create();

        $buyOrder  = Order::factory()->filled()->create(['user_id' => $buyer->id,  'symbol' => 'BTC', 'side' => 'buy']);
        $sellOrder = Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);

        Trade::factory()->create([
            'buy_order_id'  => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'commission'    => '135.00000000',
        ]);

        $response = $this->actingAs($buyer)->getJson('/api/trades');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_trade_resource_has_expected_fields(): void
    {
        $buyer  = User::factory()->create();
        $seller = User::factory()->create();

        $buyOrder  = Order::factory()->filled()->create(['user_id' => $buyer->id,  'symbol' => 'BTC', 'side' => 'buy']);
        $sellOrder = Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);

        Trade::factory()->create([
            'buy_order_id'  => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'commission'    => '135.00000000',
        ]);

        $response = $this->actingAs($buyer)->getJson('/api/trades');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'symbol', 'price', 'amount', 'commission',
                    'buy_order_id', 'sell_order_id',
                    'buyer', 'seller', 'my_role', 'executed_at',
                ]],
            ]);
    }

    public function test_my_role_is_buyer_when_authenticated_user_is_buyer(): void
    {
        $buyer  = User::factory()->create();
        $seller = User::factory()->create();

        $buyOrder  = Order::factory()->filled()->create(['user_id' => $buyer->id,  'symbol' => 'BTC', 'side' => 'buy']);
        $sellOrder = Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);

        Trade::factory()->create([
            'buy_order_id'  => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'commission'    => '135.00000000',
        ]);

        $response = $this->actingAs($buyer)->getJson('/api/trades');

        $response->assertOk()
            ->assertJsonPath('data.0.my_role', 'buyer');
    }

    public function test_my_role_is_seller_when_authenticated_user_is_seller(): void
    {
        $buyer  = User::factory()->create();
        $seller = User::factory()->create();

        $buyOrder  = Order::factory()->filled()->create(['user_id' => $buyer->id,  'symbol' => 'BTC', 'side' => 'buy']);
        $sellOrder = Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);

        Trade::factory()->create([
            'buy_order_id'  => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'commission'    => '135.00000000',
        ]);

        $response = $this->actingAs($seller)->getJson('/api/trades');

        $response->assertOk()
            ->assertJsonPath('data.0.my_role', 'seller');
    }

    public function test_filters_trades_by_symbol(): void
    {
        $buyer  = User::factory()->create();
        $seller = User::factory()->create();

        $btcBuy   = Order::factory()->filled()->create(['user_id' => $buyer->id,  'symbol' => 'BTC', 'side' => 'buy']);
        $btcSell  = Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'BTC', 'side' => 'sell']);
        $ethBuy   = Order::factory()->filled()->create(['user_id' => $buyer->id,  'symbol' => 'ETH', 'side' => 'buy']);
        $ethSell  = Order::factory()->filled()->create(['user_id' => $seller->id, 'symbol' => 'ETH', 'side' => 'sell']);

        Trade::factory()->create([
            'buy_order_id' => $btcBuy->id, 'sell_order_id' => $btcSell->id,
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'commission' => '135.00000000',
        ]);
        Trade::factory()->create([
            'buy_order_id' => $ethBuy->id, 'sell_order_id' => $ethSell->id,
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'commission' => '10.00000000',
        ]);

        $response = $this->actingAs($buyer)->getJson('/api/trades?symbol=BTC');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.symbol', 'BTC');
    }

    public function test_rejects_invalid_symbol(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/trades?symbol=XRP');

        $response->assertUnprocessable();
    }

    public function test_trade_created_via_matching_has_correct_data(): void
    {
        $seller = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $seller->id, 'symbol' => 'BTC',
            'amount' => '1.00000000', 'locked_amount' => '0.10000000',
        ]);
        $sellOrder = Order::factory()->open()->create([
            'user_id' => $seller->id, 'symbol' => 'BTC',
            'side' => 'sell', 'price' => '90000.00000000', 'amount' => '0.10000000',
        ]);

        $buyer = User::factory()->create(['balance' => '10000.00000000']);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '95000', 'amount' => '0.1',
        ]);

        $response = $this->actingAs($buyer)->getJson('/api/trades');

        // volume = 0.1 * 90000 = 9000, commission = 135
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.symbol', 'BTC')
            ->assertJsonPath('data.0.commission', '135.00000000')
            ->assertJsonPath('data.0.my_role', 'buyer');
    }
}
