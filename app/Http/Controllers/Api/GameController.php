<?php

namespace App\Http\Controllers\Api;

use App\Models\GameSession;
use App\Services\LedgerService;
use App\Services\ReferralService;
use App\Services\TaskService;
use App\Support\Cfg;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameController extends ApiController
{
    public function __construct(
        private LedgerService $ledger,
        private TaskService $tasks,
        private ReferralService $referrals,
    ) {
    }

    /** POST /api/game/session/start */
    public function start(Request $request)
    {
        $user = $this->user($request);

        $today = now()->startOfDay();
        $todayCount = GameSession::where('user_id', $user->id)
            ->where('created_at', '>=', $today)->count();

        if ($todayCount >= Cfg::int('daily_session_cap')) {
            return $this->fail('Daily game limit reached. Come back tomorrow!', 429);
        }

        $session = GameSession::create([
            'user_id' => $user->id,
            'token' => Str::random(48),
            'started_at' => now(),
            'status' => 'open',
            'ip' => $request->ip(),
        ]);

        return $this->ok([
            'session_token' => $session->token,
            'started_at' => $session->started_at->toIso8601String(),
        ]);
    }

    /**
     * POST /api/game/session/end
     * The client reports {score, merges}; the server decides the reward.
     */
    public function end(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'session_token' => ['required', 'string'],
            'score' => ['required', 'integer', 'min:0', 'max:100000000'],
            'merges' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $session = GameSession::where('token', $data['session_token'])
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return $this->fail('Unknown session.', 404);
        }
        if ($session->status !== 'open') {
            // Idempotent: return what was already awarded.
            return $this->ok([
                'coins_awarded' => (int) $session->coins_awarded,
                'wallet' => $user->wallet(),
                'replayed' => true,
            ]);
        }

        $elapsed = max(1, now()->diffInSeconds($session->started_at));

        if ($elapsed > Cfg::int('session_ttl_seconds')) {
            $session->update(['status' => 'expired', 'ended_at' => now()]);

            return $this->fail('Session expired.', 410);
        }

        // Plausibility clamp: nobody scores faster than this.
        $maxPlausibleScore = $elapsed * Cfg::int('max_score_per_second');
        $score = min($data['score'], $maxPlausibleScore);

        $rejected = $data['score'] > $maxPlausibleScore * 3;

        // Reward = score * base * multiplier, capped per session and per day.
        $reward = (int) ($score * Cfg::int('merge_reward_base') * Cfg::int('merge_reward_multiplier') / 10);
        $reward = min($reward, Cfg::int('max_coins_per_session'));

        $creditedToday = $this->ledger->creditedSince(
            $user, 'merge_reward', now()->startOfDay()
        );
        $remaining = max(0, Cfg::int('daily_coin_cap') - $creditedToday);
        $reward = min($reward, $remaining);

        $session->update([
            'status' => $rejected ? 'rejected' : 'ended',
            'reject_reason' => $rejected ? 'implausible_score' : null,
            'ended_at' => now(),
            'score' => $data['score'],
            'merges' => $data['merges'],
            'coins_awarded' => $rejected ? 0 : $reward,
        ]);

        // Advance task progress from server-verified facts. Rejected runs do not
        // count, but a run that was merely capped still does.
        if (! $rejected) {
            $this->tasks->record($user, 'play_game', 1);
            $this->tasks->record($user, 'merge', (int) $data['merges']);
            $this->tasks->recordMax($user, 'reach_score', (int) $data['score']);

            // Pay the inviter once this player has finished enough runs.
            $this->referrals->qualifyIfEligible($user);
        }

        if ($rejected || $reward <= 0) {
            return $this->ok([
                'coins_awarded' => 0,
                'wallet' => $user->wallet(),
                'capped' => ! $rejected,
            ]);
        }

        $this->ledger->apply(
            $user, 'merge_reward',
            ['coins' => $reward],
            idempotencyKey: "session:{$session->id}",
            source: 'game',
            meta: ['score' => $data['score'], 'merges' => $data['merges'], 'clamped_score' => $score],
        );

        return $this->ok([
            'coins_awarded' => $reward,
            'wallet' => $user->fresh()->wallet(),
        ]);
    }
}
