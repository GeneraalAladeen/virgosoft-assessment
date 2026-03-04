<?php

namespace Tests\Feature\Api;

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderMatchedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_matched_event_is_broadcast_on_successful_match(): void
    {
        Event::fake([OrderMatched::class]);

        $seller = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '1.00000000',
            'locked_amount' => '0.10000000',
        ]);
        Order::factory()->open()->create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '90000.00000000',
            'amount' => '0.10000000',
        ]);

        $buyer = User::factory()->create(['balance' => '10000.00000000']);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '95000', 'amount' => '0.1',
        ]);

        Event::assertDispatched(OrderMatched::class, function (OrderMatched $event) use ($buyer, $seller) {
            return $event->buyOrder->user_id === $buyer->id
                && $event->sellOrder->user_id === $seller->id
                && $event->matchedPrice === '90000.00000000';
        });
    }

    public function test_order_matched_event_is_not_broadcast_when_no_match(): void
    {
        Event::fake([OrderMatched::class]);

        $seller = User::factory()->create();
        Asset::factory()->create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '1.00000000',
            'locked_amount' => '0.10000000',
        ]);
        Order::factory()->open()->create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '95000.00000000',
            'amount' => '0.10000000',
        ]);

        $buyer = User::factory()->create(['balance' => '10000.00000000']);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => 'BTC', 'side' => 'buy', 'price' => '90000', 'amount' => '0.1',
        ]);

        Event::assertNotDispatched(OrderMatched::class);
    }

    public function test_order_matched_event_broadcasts_on_correct_private_channels(): void
    {
        $buyer = User::factory()->create(['balance' => '10000.00000000']);
        $seller = User::factory()->create();

        $buyOrder = Order::factory()->open()->create([
            'user_id' => $buyer->id, 'symbol' => 'BTC',
            'side' => 'buy', 'price' => '95000.00000000', 'amount' => '0.10000000',
        ]);
        $sellOrder = Order::factory()->open()->create([
            'user_id' => $seller->id, 'symbol' => 'BTC',
            'side' => 'sell', 'price' => '90000.00000000', 'amount' => '0.10000000',
        ]);

        $event = new OrderMatched($buyOrder, $sellOrder, '90000.00000000', '9000.00000000', '135.00000000');
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals("private-user.{$buyer->id}", $channels[0]->name);
        $this->assertEquals("private-user.{$seller->id}", $channels[1]->name);
    }
}
