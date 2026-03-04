<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_cancel_order(): void
    {
        $order = Order::factory()->open()->create();

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertUnauthorized();
    }

    public function test_user_cannot_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->open()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertForbidden();
    }

    public function test_user_cannot_cancel_filled_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->filled()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertForbidden();
    }

    public function test_user_cannot_cancel_already_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->cancelled()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertForbidden();
    }

    public function test_cancelling_buy_order_refunds_balance(): void
    {
        $user = User::factory()->create(['balance' => '500.00000000']);
        $order = Order::factory()->open()->create([
            'user_id' => $user->id,
            'side' => 'buy',
            'symbol' => 'BTC',
            'price' => '90000.00000000',
            'amount' => '0.10000000',
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        // refund = 0.1 * 90000 = 9000
        $this->assertEquals('9500.00000000', $user->fresh()->balance);
    }

    public function test_cancelling_sell_order_releases_locked_asset(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '0.90000000',
            'locked_amount' => '0.10000000',
        ]);
        $order = Order::factory()->open()->create([
            'user_id' => $user->id,
            'side' => 'sell',
            'symbol' => 'BTC',
            'price' => '95000.00000000',
            'amount' => '0.10000000',
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertEquals('0.00000000', $asset->fresh()->locked_amount);
    }
}
