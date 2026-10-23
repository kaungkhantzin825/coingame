<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Referral;
use App\Models\User;
use App\Support\Cfg;
use Illuminate\Support\Facades\DB;

/**
 * The referral program.
 *
 * Two distinct money movements, both admin-priced:
 *
 *  1. **Invitee bonus** — paid immediately when a code is redeemed.
 *  2. **Inviter bonus** — held until the invitee has actually played
 *     `referral_qualify_sessions` runs, so a throwaway account cannot farm
 *     coins for the inviter.
 *
 * Every credit goes through LedgerService with an idempotency key, so replaying
 * a request never pays twice.
 */
class ReferralService
{
    public function __construct(private LedgerService $ledger)
    {
    }

    public function enabled(): bool
    {
        return Cfg::bool('referral_enabled');
    }

    /**
     * Attach an invite code to a user.
     *
     * @return array{ok:bool,message?:string,referral?:Referral}
     */
    public function redeem(User $user, string $rawCode): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'message' => 'The invite program is paused right now.'];
        }

        $code = strtoupper(trim($rawCode));

        if ($code === '') {
            return ['ok' => false, 'message' => 'Enter an invite code.'];
        }

        if ($user->referral_code && strtoupper($user->referral_code) === $code) {
            return ['ok' => false, 'message' => 'That is your own code.'];
        }

        if ($user->referred_by) {
            return ['ok' => false, 'message' => 'You have already used an invite code.'];
        }

        $referrer = User::where('referral_code', $code)->first();

        if (! $referrer) {
            return ['ok' => false, 'message' => 'Invite code not found.'];
        }

        if ($referrer->id === $user->id) {
            return ['ok' => false, 'message' => 'That is your own code.'];
        }

        $bonus = max(0, Cfg::int('referral_referee_bonus_coins'));

        $referral = DB::transaction(function () use ($user, $referrer, $bonus) {
            /** @var User $locked */
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            // Re-check under the lock: two parallel redeems must not both win.
            if ($locked->referred_by) {
                return null;
            }

            $locked->update(['referred_by' => $referrer->id]);

            $referral = Referral::firstOrCreate(
                ['referee_id' => $locked->id],
                [
                    'referrer_id' => $referrer->id,
                    'bonus_coins' => max(0, Cfg::int('referral_bonus_coins')),
                    'status' => 'pending',
                ],
            );

            if ($bonus > 0) {
                $this->ledger->apply(
                    $locked, 'referral_bonus',
                    ['coins' => $bonus],
                    idempotencyKey: "referral_redeem:{$locked->id}",
                    source: 'referral',
                    meta: ['referrer_id' => $referrer->id, 'code' => $referrer->referral_code],
                );
            }

            return $referral;
        });

        if (! $referral) {
            return ['ok' => false, 'message' => 'You have already used an invite code.'];
        }

        return [
            'ok' => true,
            'referral' => $referral,
            'bonus_coins' => $bonus,
            'message' => $bonus > 0
                ? 'Code accepted — '.number_format($bonus).' coins added.'
                : 'Code accepted.',
        ];
    }

    /**
     * Called after every finished run. Promotes a pending referral to
     * `qualified` once the invitee has played enough, and pays the inviter.
     */
    public function qualifyIfEligible(User $user): ?Referral
    {
        if (! $this->enabled()) {
            return null;
        }

        $referral = Referral::where('referee_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            return null;
        }

        $needed = max(1, Cfg::int('referral_qualify_sessions'));

        $played = GameSession::where('user_id', $user->id)
            ->whereIn('status', ['ended'])
            ->count();

        if ($played < $needed) {
            return null;
        }

        $referrer = User::find($referral->referrer_id);
        if (! $referrer) {
            return null;
        }

        $bonus = max(0, Cfg::int('referral_bonus_coins'));

        DB::transaction(function () use ($referral, $referrer, $bonus) {
            $referral->forceFill([
                'status' => 'qualified',
                'qualified_at' => now(),
                'bonus_coins' => $bonus,
            ])->save();

            if ($bonus > 0) {
                $this->ledger->apply(
                    $referrer, 'referral_bonus',
                    ['coins' => $bonus],
                    idempotencyKey: "referral_bonus:{$referral->id}",
                    source: 'referral',
                    meta: ['referee_id' => $referral->referee_id],
                );
            }
        });

        return $referral->fresh();
    }

    /** Invite screen payload. */
    public function stats(User $user): array
    {
        $base = Referral::where('referrer_id', $user->id);
        $needed = max(1, Cfg::int('referral_qualify_sessions'));

        $referredBy = null;
        if ($user->referred_by) {
            $referrer = User::find($user->referred_by);
            $referredBy = $referrer ? [
                'name' => $referrer->name,
                'code' => $referrer->referral_code,
            ] : null;
        }

        return [
            'enabled' => $this->enabled(),
            'code' => $user->referral_code,
            'invited' => (clone $base)->count(),
            'qualified' => (clone $base)->where('status', 'qualified')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'earned_coins' => (int) (clone $base)->where('status', 'qualified')->sum('bonus_coins'),
            'bonus_coins' => max(0, Cfg::int('referral_bonus_coins')),
            'referee_bonus_coins' => max(0, Cfg::int('referral_referee_bonus_coins')),
            'qualify_sessions' => $needed,
            // The client uses this to decide whether to show the "enter a code"
            // box at all.
            'can_redeem' => $this->enabled() && ! $user->referred_by,
            'referred_by' => $referredBy,
        ];
    }
}
