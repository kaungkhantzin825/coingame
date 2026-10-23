<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end economy: earn coins by playing, claim the daily ladder, complete a
 * task, convert coins to cash, then request a payout that an admin would
 * approve. Every number is driven by `app_configs`.
 */
class EconomyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function player(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'device_id' => 'test-device-'.uniqid(),
            'referral_code' => strtoupper(substr(uniqid(), -7)),
            'coins' => 0,
            'cash_cents' => 0,
            'gems' => 0,
        ]);
    }

    private function asPlayer(array $attributes = []): User
    {
        $user = $this->player($attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_device_registration_creates_user_with_signup_bonus(): void
    {
        $response = $this->postJson('/api/auth/device', [
            'device_id' => 'device-abc-123',
            'platform' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('new_user', true)
            ->assertJsonPath('user.wallet.coins', 2000);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('devices', ['device_id' => 'device-abc-123']);
        $this->assertDatabaseHas('transactions', ['type' => 'admin_adjust', 'coins_delta' => 2000]);
    }

    public function test_repeat_device_login_returns_same_user_and_does_not_pay_bonus_again(): void
    {
        $first = $this->postJson('/api/auth/device', ['device_id' => 'device-same'])->json('user.id');
        $second = $this->postJson('/api/auth/device', ['device_id' => 'device-same']);

        $second->assertOk()->assertJsonPath('new_user', false)->assertJsonPath('user.id', $first);
        $this->assertSame(1, \App\Models\Transaction::where('type', 'admin_adjust')->count());
    }

    public function test_game_session_awards_coins_and_advances_tasks(): void
    {
        $user = $this->asPlayer();

        $token = $this->postJson('/api/game/session/start')->json('session_token');
        $this->assertNotEmpty($token);

        // Let the session look plausible in time so the clamp does not zero it.
        $this->travel(10)->seconds();

        $response = $this->postJson('/api/game/session/end', [
            'session_token' => $token,
            'score' => 100,
            'merges' => 50,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $awarded = $response->json('coins_awarded');

        // reward = score * base(1) * multiplier(5) / 10 = 50, capped at 3000.
        $this->assertSame(50, $awarded);
        $this->assertSame(50, $user->fresh()->coins);

        // "merge_50" (daily, goal 50) should now be completed by 50 merges.
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonFragment(['key' => 'merge_50', 'progress' => 50, 'completed' => true]);
    }

    public function test_session_end_is_idempotent(): void
    {
        $user = $this->asPlayer();
        $token = $this->postJson('/api/game/session/start')->json('session_token');

        $payload = ['session_token' => $token, 'score' => 100, 'merges' => 1];

        $first = $this->postJson('/api/game/session/end', $payload)->json('coins_awarded');
        $second = $this->postJson('/api/game/session/end', $payload);

        $second->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame($first, $second->json('coins_awarded'));
        $this->assertSame($first, $user->fresh()->coins);
    }

    public function test_implausible_score_is_rejected_and_pays_nothing(): void
    {
        $user = $this->asPlayer();
        $token = $this->postJson('/api/game/session/start')->json('session_token');

        // 1s elapsed allows ~40 score; 100000 is far beyond the 3x tolerance.
        $response = $this->postJson('/api/game/session/end', [
            'session_token' => $token,
            'score' => 100000,
            'merges' => 5,
        ]);

        $response->assertOk()->assertJsonPath('coins_awarded', 0);
        $this->assertSame(0, $user->fresh()->coins);
    }

    public function test_daily_reward_can_only_be_claimed_once_per_day(): void
    {
        $user = $this->asPlayer();

        $this->getJson('/api/daily')
            ->assertOk()
            ->assertJsonPath('claimed_today', false)
            ->assertJsonPath('next_day', 1);

        $claim = $this->postJson('/api/daily/claim');
        $claim->assertOk()->assertJsonPath('day', 1)->assertJsonPath('streak', 1);

        // Day 1 of the seeded ladder pays 500 coins.
        $this->assertSame(500, $user->fresh()->coins);

        $this->postJson('/api/daily/claim')->assertStatus(409);
        $this->assertSame(500, $user->fresh()->coins);
    }

    public function test_daily_streak_advances_on_consecutive_days_and_wraps_after_day_seven(): void
    {
        $user = $this->asPlayer(['daily_streak' => 7, 'last_daily_claim_on' => now()->subDay()->toDateString()]);

        $this->postJson('/api/daily/claim')
            ->assertOk()
            ->assertJsonPath('streak', 8)
            ->assertJsonPath('day', 1); // ladder wraps back to day 1

        $this->assertSame(8, $user->fresh()->daily_streak);
    }

    public function test_task_claim_requires_completion_then_pays_once(): void
    {
        $user = $this->asPlayer();

        $this->postJson('/api/tasks/play_3/claim')->assertStatus(422);

        // Complete "play 3 games" by finishing three sessions.
        for ($i = 0; $i < 3; $i++) {
            $token = $this->postJson('/api/game/session/start')->json('session_token');
            $this->travel(5)->seconds();
            $this->postJson('/api/game/session/end', [
                'session_token' => $token, 'score' => 10, 'merges' => 1,
            ])->assertOk();
        }

        $before = (int) $user->fresh()->coins;

        $this->postJson('/api/tasks/play_3/claim')
            ->assertOk()
            ->assertJsonPath('task', 'play_3');

        $this->assertSame($before + 1500, (int) $user->fresh()->coins);

        $this->postJson('/api/tasks/play_3/claim')->assertStatus(409);
    }

    public function test_convert_moves_coins_to_cash_at_the_configured_rate(): void
    {
        // 100000 coins == 1.00 at the default 1000 coins per cent.
        $user = $this->asPlayer(['coins' => 250000]);

        $this->postJson('/api/wallet/convert', ['coins' => 100000])
            ->assertOk()
            ->assertJsonPath('cash_cents', 100)
            ->assertJsonPath('wallet.coins', 150000)
            ->assertJsonPath('wallet.cash_cents', 100);

        $this->assertSame(150000, (int) $user->fresh()->coins);
    }

    public function test_convert_rejects_below_minimum_and_above_balance(): void
    {
        $this->asPlayer(['coins' => 5000]);

        $this->postJson('/api/wallet/convert', ['coins' => 5000])->assertStatus(422);
        $this->postJson('/api/wallet/convert', ['coins' => 999999])->assertStatus(422);
    }

    public function test_withdraw_holds_cash_and_appears_in_history(): void
    {
        $user = $this->asPlayer(['cash_cents' => 1000]);

        $this->getJson('/api/withdraw/methods')
            ->assertOk()
            ->assertJsonFragment(['key' => 'tng']);

        $this->postJson('/api/withdraw', [
            'method_key' => 'tng',
            'amount_cents' => 800,
            'account' => ['name' => 'Test Player', 'phone' => '0123456789'],
        ])->assertStatus(201)->assertJsonPath('withdrawal.status', 'pending');

        // Money is held immediately.
        $this->assertSame(200, (int) $user->fresh()->cash_cents);

        $this->getJson('/api/withdraw/history')
            ->assertOk()
            ->assertJsonPath('withdrawals.0.amount_cents', 800)
            ->assertJsonPath('withdrawals.0.status', 'pending');

        $this->assertSame(1, Withdrawal::count());
    }

    public function test_withdraw_rejects_missing_account_fields_and_insufficient_balance(): void
    {
        $this->asPlayer(['cash_cents' => 1000]);

        $this->postJson('/api/withdraw', [
            'method_key' => 'tng',
            'amount_cents' => 800,
            'account' => ['name' => 'No Phone'],
        ])->assertStatus(422);

        $this->postJson('/api/withdraw', [
            'method_key' => 'tng',
            'amount_cents' => 9000,
            'account' => ['name' => 'A', 'phone' => '1'],
        ])->assertStatus(422);

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_referral_stats_endpoint_returns_code(): void
    {
        $user = $this->asPlayer();

        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('code', $user->referral_code)
            ->assertJsonPath('invited', 0);
    }
}
