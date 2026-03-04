<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create(['balance' => '1000.00000000']);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'balance',
                    'assets',
                ],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.balance', '1000.00000000');
    }

    public function test_profile_includes_user_assets(): void
    {
        $user = User::factory()->create(['balance' => '5000.00000000']);
        Asset::factory()->create(['user_id' => $user->id, 'symbol' => 'BTC', 'amount' => '0.50000000']);
        Asset::factory()->create(['user_id' => $user->id, 'symbol' => 'ETH', 'amount' => '2.00000000']);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonCount(2, 'data.assets')
            ->assertJsonPath('data.assets.0.symbol', 'BTC')
            ->assertJsonPath('data.assets.0.amount', '0.50000000')
            ->assertJsonPath('data.assets.1.symbol', 'ETH');
    }

    public function test_profile_returns_empty_assets_when_user_has_none(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonCount(0, 'data.assets');
    }
}
