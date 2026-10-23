<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyReward;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\Request;

/**
 * 7-day check-in ladder. The reward per day is admin-editable via the
 * `daily_rewards` table, so the client only ever displays what the server sends.
 */
class DailyController extends ApiController
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /** GET /api/daily */
    public function index(Request $request)
    {
        $user = $this->user($request);

        return $this->ok([
            'rewards' => $this->ladder(),
            'streak' => (int) $user->daily_streak,
            'claimed_today' => $this->claimedToday($user),
            'next_day' => $this->nextDay($user),
            'last_claim_on' => $user->last_daily_claim_on?->toDateString(),
        ]);
    }

    /** POST /api/daily/claim */
    public function claim(Request $request)
    {
        $user = $this->user($request);

        if ($this->claimedToday($user)) {
            return $this->fail('Already claimed today. Come back tomorrow!', 409);
        }

        // Streak continues only if the last claim was yesterday. It is an
        // unbounded day counter; the ladder day wraps every 7 (streak 8 -> day 1).
        $streak = ($user->last_daily_claim_on && $user->last_daily_claim_on->isYesterday())
            ? (int) $user->daily_streak + 1
            : 1;

        $day = (($streak - 1) % 7) + 1;
        $reward = DailyReward::where('day', $day)->first();

        if (! $reward) {
            return $this->fail('No reward configured for day '.$day.'.', 503);
        }

        $this->ledger->apply(
            $user,
            'daily_reward',
            [
                'coins' => (int) $reward->reward_coins,
                'cash_cents' => (int) $reward->reward_cash_cents,
                'gems' => (int) $reward->reward_gems,
            ],
            idempotencyKey: "daily:{$user->id}:".now()->toDateString(),
            source: 'daily',
            meta: ['day' => $day, 'streak' => $streak],
        );

        $user->forceFill([
            'daily_streak' => $streak,
            'last_daily_claim_on' => now()->toDateString(),
        ])->save();

        return $this->ok([
            'day' => $day,
            'streak' => $streak,
            'reward' => [
                'coins' => (int) $reward->reward_coins,
                'cash_cents' => (int) $reward->reward_cash_cents,
                'gems' => (int) $reward->reward_gems,
            ],
            'wallet' => $user->fresh()->wallet(),
        ]);
    }

    private function ladder(): array
    {
        return DailyReward::orderBy('day')->get()->map(fn (DailyReward $r) => [
            'day' => (int) $r->day,
            'coins' => (int) $r->reward_coins,
            'cash_cents' => (int) $r->reward_cash_cents,
            'gems' => (int) $r->reward_gems,
            'is_bonus' => (bool) $r->is_bonus,
        ])->all();
    }

    private function claimedToday(User $user): bool
    {
        return $user->last_daily_claim_on !== null
            && $user->last_daily_claim_on->isToday();
    }

    /** Which ladder day the *next* claim pays out. */
    private function nextDay(User $user): int
    {
        $continuing = $user->last_daily_claim_on !== null
            && ($user->last_daily_claim_on->isToday() || $user->last_daily_claim_on->isYesterday());

        if (! $continuing) {
            return 1;
        }

        // Whether today is already claimed or still pending, the next payout is
        // always the day after the current streak.
        return ((int) $user->daily_streak % 7) + 1;
    }
}
