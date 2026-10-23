<?php

namespace Tests\Feature;

use App\Models\AdPlacement;
use App\Models\AdView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ad waterfall: ordered units per placement, provisional rewards, per-placement
 * and global daily caps, and idempotent claims.
 */
class AdWaterfallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function player(int $coins = 0): User
    {
        $user = User::factory()->create([
            'device_id' => 'ad-device-'.uniqid(),
            'referral_code' => strtoupper(substr(uniqid(), -7)),
            'coins' => $coins,
            'cash_cents' => 0,
            'gems' => 0,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_waterfall_returns_ordered_units_with_reward_and_cap(): void
    {
        $this->player();

        // Two networks on the same placement, deliberately inserted out of order.
        AdPlacement::create([
            'placement' => 'coin_ad', 'network' => 'applovin', 'ad_unit_id' => 'unit-b',
            'priority' => 20, 'reward_coins' => 500, 'daily_cap' => 20, 'active' => true,
        ]);

        $response = $this->getJson('/api/ads/waterfall?placement=coin_ad');

        $response->assertOk()
            ->assertJsonPath('placement', 'coin_ad')
            ->assertJsonPath('reward_coins', 500)
            ->assertJsonPath('capped', false)
            ->assertJsonPath('used_today', 0);

        $units = $response->json('units');
        $this->assertCount(2, $units);
        $this->assertSame('admob', $units[0]['network'], 'lower priority must come first');
        $this->assertSame('applovin', $units[1]['network']);
        $this->assertNotEmpty($units[0]['ad_unit_id']);
    }

    public function test_waterfall_rejects_unknown_placement(): void
    {
        $this->player();

        $this->getJson('/api/ads/waterfall?placement=does_not_exist')->assertStatus(404);
    }

    public function test_inactive_placement_is_not_served(): void
    {
        $this->player();
        AdPlacement::where('placement', 'coin_ad')->update(['active' => false]);

        $this->getJson('/api/ads/waterfall?placement=coin_ad')->assertStatus(404);
    }

    public function test_reward_credits_provisional_coins_and_records_the_view(): void
    {
        $user = $this->player();

        $response = $this->postJson('/api/ads/reward', [
            'placement' => 'coin_ad',
            'network' => 'admob',
            'idempotency_key' => 'abc-123',
        ]);

        $response->assertOk()
            ->assertJsonPath('coins_awarded', 500)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('wallet.coins', 500);

        $this->assertSame(500, (int) $user->fresh()->coins);

        $view = AdView::firstOrFail();
        $this->assertSame(500, (int) $view->reward_coins);
        $this->assertFalse((bool) $view->verified);
        $this->assertNotNull($view->provisional_at);
    }

    public function test_reward_is_idempotent_for_the_same_key(): void
    {
        $user = $this->player();

        $payload = [
            'placement' => 'coin_ad',
            'network' => 'admob',
            'idempotency_key' => 'same-key',
        ];

        $this->postJson('/api/ads/reward', $payload)->assertOk();
        $second = $this->postJson('/api/ads/reward', $payload);

        $second->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('coins_awarded', 500);

        $this->assertSame(500, (int) $user->fresh()->coins, 'coins must only be paid once');
        $this->assertSame(1, AdView::count());
    }

    public function test_per_placement_daily_cap_is_enforced(): void
    {
        $this->player();
        AdPlacement::where('placement', 'coin_ad')->update(['daily_cap' => 1]);

        $this->postJson('/api/ads/reward', [
            'placement' => 'coin_ad', 'network' => 'admob', 'idempotency_key' => 'one',
        ])->assertOk();

        $this->postJson('/api/ads/reward', [
            'placement' => 'coin_ad', 'network' => 'admob', 'idempotency_key' => 'two',
        ])->assertStatus(429);

        $this->assertSame(1, AdView::count());
    }

    public function test_waterfall_reports_capped_after_the_limit(): void
    {
        $this->player();
        AdPlacement::where('placement', 'coin_ad')->update(['daily_cap' => 1]);

        $this->postJson('/api/ads/reward', [
            'placement' => 'coin_ad', 'network' => 'admob', 'idempotency_key' => 'only',
        ])->assertOk();

        $this->getJson('/api/ads/waterfall?placement=coin_ad')
            ->assertOk()
            ->assertJsonPath('capped', true)
            ->assertJsonPath('remaining', 0)
            ->assertJsonPath('used_today', 1);
    }

    public function test_unknown_network_is_rejected_but_omitting_it_uses_the_placement(): void
    {
        $this->player();

        // A stale network id from an old app build must not dead-end the reward.
        $this->postJson('/api/ads/reward', [
            'placement' => 'coin_ad',
            'network' => 'removed_network',
            'idempotency_key' => 'fallback',
        ])->assertStatus(404);

        $this->postJson('/api/ads/reward', [
            'placement' => 'coin_ad',
            'idempotency_key' => 'no-network',
        ])->assertOk()->assertJsonPath('coins_awarded', 500);
    }
}
