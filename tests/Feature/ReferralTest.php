<?php

namespace Tests\Feature;

use App\Models\AppConfig;
use App\Models\GameSession;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Referral program: admin-priced rewards, code redemption, and the delayed
 * payout to the inviter once the invitee has actually played.
 */
class ReferralTest extends TestCase
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
            'device_id' => 'dev-'.uniqid('', true),
            'referral_code' => strtoupper(substr(md5(uniqid('', true)), 0, 7)),
            'coins' => 0,
            'cash_cents' => 0,
            'gems' => 0,
        ]);
    }

    private function setConfig(string $key, mixed $value): void
    {
        AppConfig::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'referral']);
        AppConfig::query()->get(); // no-op; cache is invalidated by the model
    }

    /** Play one full server session and let it end cleanly. */
    private function playRun(User $user, int $score = 100): void
    {
        $session = GameSession::create([
            'user_id' => $user->id,
            'token' => uniqid('tok', true),
            'started_at' => now()->subSeconds(30),
            'status' => 'open',
        ]);

        $this->actingAs($user, 'sanctum');
        Sanctum::actingAs($user);

        $this->postJson('/api/game/session/end', [
            'session_token' => $session->token,
            'score' => $score,
            'merges' => 5,
        ])->assertOk();
    }

    public function test_redeeming_a_code_pays_the_invitee_the_admin_amount(): void
    {
        $this->setConfig('referral_referee_bonus_coins', 1234);

        $inviter = $this->player();
        $friend = $this->player();

        Sanctum::actingAs($friend);

        $this->postJson('/api/referral/redeem', ['code' => $inviter->referral_code])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('bonus_coins', 1234)
            ->assertJsonPath('wallet.coins', 1234);

        $this->assertSame($inviter->id, $friend->fresh()->referred_by);
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $inviter->id,
            'referee_id' => $friend->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_player_cannot_redeem_twice(): void
    {
        $inviter = $this->player();
        $other = $this->player();
        $friend = $this->player();

        Sanctum::actingAs($friend);

        $this->postJson('/api/referral/redeem', ['code' => $inviter->referral_code])->assertOk();

        $this->postJson('/api/referral/redeem', ['code' => $other->referral_code])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame($inviter->id, $friend->fresh()->referred_by);
    }

    public function test_a_player_cannot_redeem_their_own_code(): void
    {
        $user = $this->player();
        Sanctum::actingAs($user);

        $this->postJson('/api/referral/redeem', ['code' => $user->referral_code])
            ->assertStatus(422);
    }

    public function test_unknown_code_is_rejected(): void
    {
        Sanctum::actingAs($this->player());

        $this->postJson('/api/referral/redeem', ['code' => 'NOPE123'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invite code not found.');
    }

    public function test_code_is_case_insensitive(): void
    {
        $inviter = $this->player(['referral_code' => 'ABC1234']);
        $friend = $this->player();

        Sanctum::actingAs($friend);

        $this->postJson('/api/referral/redeem', ['code' => 'abc1234'])->assertOk();
        $this->assertSame($inviter->id, $friend->fresh()->referred_by);
    }

    public function test_inviter_is_paid_only_after_the_qualifying_number_of_runs(): void
    {
        $this->setConfig('referral_bonus_coins', 5000);
        $this->setConfig('referral_qualify_sessions', 2);

        $inviter = $this->player();
        $friend = $this->player();

        Sanctum::actingAs($friend);
        $this->postJson('/api/referral/redeem', ['code' => $inviter->referral_code])->assertOk();

        // Run 1 — not enough.
        $this->playRun($friend);
        $this->assertSame(0, (int) $inviter->fresh()->coins);
        $this->assertSame('pending', Referral::first()->status);

        // Run 2 — qualifies.
        $this->playRun($friend);
        $this->assertSame(5000, (int) $inviter->fresh()->coins);
        $this->assertSame('qualified', Referral::first()->status);
        $this->assertNotNull(Referral::first()->qualified_at);
    }

    public function test_inviter_is_never_paid_twice(): void
    {
        $this->setConfig('referral_qualify_sessions', 1);

        $inviter = $this->player();
        $friend = $this->player();

        Sanctum::actingAs($friend);
        $this->postJson('/api/referral/redeem', ['code' => $inviter->referral_code])->assertOk();

        $this->playRun($friend);
        $this->playRun($friend);
        $this->playRun($friend);

        $this->assertSame(5000, (int) $inviter->fresh()->coins);
    }

    public function test_disabled_program_blocks_redemption(): void
    {
        $this->setConfig('referral_enabled', false);

        $inviter = $this->player();
        $friend = $this->player();

        Sanctum::actingAs($friend);

        $this->postJson('/api/referral/redeem', ['code' => $inviter->referral_code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The invite program is paused right now.');
    }

    public function test_stats_endpoint_reports_admin_amounts_and_can_redeem(): void
    {
        $this->setConfig('referral_bonus_coins', 7777);
        $this->setConfig('referral_referee_bonus_coins', 888);

        $user = $this->player();
        Sanctum::actingAs($user);

        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', $user->referral_code)
            ->assertJsonPath('bonus_coins', 7777)
            ->assertJsonPath('referee_bonus_coins', 888)
            ->assertJsonPath('can_redeem', true)
            ->assertJsonPath('invited', 0);
    }

    public function test_stats_endpoint_reflects_qualified_invites(): void
    {
        $this->setConfig('referral_qualify_sessions', 1);

        $inviter = $this->player();
        $friend = $this->player();

        Sanctum::actingAs($friend);
        $this->postJson('/api/referral/redeem', ['code' => $inviter->referral_code])->assertOk();
        $this->playRun($friend);

        Sanctum::actingAs($inviter);

        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('invited', 1)
            ->assertJsonPath('qualified', 1)
            ->assertJsonPath('earned_coins', 5000);
    }
}
