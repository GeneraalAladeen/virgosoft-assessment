<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '90000', 'amount' => '0.01',
        ]);

        $response->assertUnauthorized();
    }

    public function test_order_requires_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol', 'side', 'price', 'amount']);
    }

    public function test_rejects_invalid_symbol(): void
    {
        $user = User::factory()->create(['balance' => '100000.00000000']);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'DOGE', 'side' => 'buy', 'price' => '1', 'amount' => '10',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol']);
    }

    // ── Buy order ────────────────────────────────────────────────────────────

    public function test_buy_order_deducts_balance_and_creates_open_order(): void
    {
        $user = User::factory()->create(['balance' => '10000.00000000']);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '90000', 'amount' => '0.1',
        ]);

        // cost = 0.1 * 90000 = 9000
        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Open->value);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'status' => OrderStatus::Open->value,
        ]);

        $this->assertEquals('1000.00000000', $user->fresh()->balance);
    }

    public function test_buy_order_fails_when_balance_is_insufficient(): void
    {
        $user = User::factory()->create(['balance' => '500.00000000']);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '90000', 'amount' => '0.1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['balance']);
    }

    // ── Sell order ───────────────────────────────────────────────────────────

    public function test_sell_order_locks_asset_and_creates_open_order(): void
    {
        $user = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '1.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'sell', 'price' => '95000', 'amount' => '0.5',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Open->value);

        $asset = Asset::where('user_id', $user->id)->where('symbol', 'BTC')->first();
        $this->assertEquals('0.50000000', $asset->locked_amount);
    }

    public function test_sell_order_fails_when_asset_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '0.10000000',
            'locked_amount' => '0.00000000',
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'sell', 'price' => '95000', 'amount' => '0.5',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // ── Matching ─────────────────────────────────────────────────────────────

    public function test_buy_order_matches_with_cheaper_sell(): void
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

        // Both orders should be filled
        $this->assertEquals(OrderStatus::Filled, $sellOrder->fresh()->status);
        $buyOrder = Order::where('user_id', $buyer->id)->first();
        $this->assertEquals(OrderStatus::Filled, $buyOrder->status);

        // Buyer gets the BTC asset
        $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
        $this->assertNotNull($buyerAsset);
        $this->assertEquals('0.10000000', $buyerAsset->amount);

        // buyer paid 0.1 * 95000 = 9500 (locked), matched at 90000
        // volume = 9000, commission = 135, price improvement = 500
        // buyer balance = 10000 - 9500 + (500 - 135) = 865
        $this->assertEquals('865.00000000', $buyer->fresh()->balance);

        // Seller gets USD: 0.1 * 90000 = 9000
        $this->assertEquals('9000.00000000', $seller->fresh()->balance);
    }

    public function test_sell_order_matches_with_higher_buy(): void
    {
        $buyer = User::factory()->create(['balance' => '0.00000000']);
        Asset::factory()->create([
            'user_id' => $buyer->id, 'symbol' => 'BTC', 'amount' => '0.00000000', 'locked_amount' => '0.00000000',
        ]);
        $buyOrder = Order::factory()->open()->create([
            'user_id' => $buyer->id, 'symbol' => 'BTC',
            'side' => 'buy', 'price' => '95000.00000000', 'amount' => '0.10000000',
        ]);

        $seller = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $seller->id, 'symbol' => 'BTC',
            'amount' => '1.00000000', 'locked_amount' => '0.00000000',
        ]);

        $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'sell', 'price' => '90000', 'amount' => '0.1',
        ]);

        // Both orders should be filled
        $this->assertEquals(OrderStatus::Filled, $buyOrder->fresh()->status);
        $sellOrder = Order::where('user_id', $seller->id)->first();
        $this->assertEquals(OrderStatus::Filled, $sellOrder->status);

        // Buyer gets BTC
        $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
        $this->assertEquals('0.10000000', $buyerAsset->fresh()->amount);

        // Matched at sell price = 90000. volume = 0.1 * 90000 = 9000
        // commission = 135. buyer balance: 0 + (95000 - 90000) * 0.1 - 135 = 500 - 135 = 365
        $this->assertEquals('365.00000000', $buyer->fresh()->balance);

        // Seller gets: 9000 USD
        $this->assertEquals('9000.00000000', $seller->fresh()->balance);
    }

    public function test_no_match_when_prices_do_not_cross(): void
    {
        $seller = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $seller->id, 'symbol' => 'BTC',
            'amount' => '1.00000000', 'locked_amount' => '0.10000000',
        ]);
        Order::factory()->open()->create([
            'user_id' => $seller->id, 'symbol' => 'BTC',
            'side' => 'sell', 'price' => '95000.00000000', 'amount' => '0.10000000',
        ]);

        $buyer = User::factory()->create(['balance' => '10000.00000000']);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '90000', 'amount' => '0.1',
        ]);

        // No match — buy order stays open
        $buyOrder = Order::where('user_id', $buyer->id)->first();
        $this->assertEquals(OrderStatus::Open, $buyOrder->status);
    }
}
