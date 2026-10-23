<?php

namespace App\Http\Controllers\Api;

use App\Models\AdPlacement;
use App\Models\AdView;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\Cfg;
use Illuminate\Http\Request;

/**
 * Ad waterfall.
 *
 * `/ads/waterfall` returns the ordered list of networks for a placement so
 * floors and unit ids can change from the admin panel without an app release.
 * The client tries them in order and calls `/ads/reward` once a rewarded video
 * has actually been watched.
 *
 * Rewards are credited as *provisional* (`ad_views.verified = false`). The
 * AdMob SSV callback that flips `verified` is still to be built — until then
 * every ad reward is provisional, which the admin panel can see.
 */
class AdController extends ApiController
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /** GET /api/ads/waterfall?placement=free_coins */
    public function waterfall(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'placement' => ['required', 'string', 'max:40'],
        ]);

        $units = AdPlacement::where('placement', $data['placement'])
            ->where('active', true)
            ->orderBy('priority')
            ->orderByDesc('floor_ecpm_cents')
            ->get();

        if ($units->isEmpty()) {
            return $this->fail('No ad is configured for this placement.', 404);
        }

        $first = $units->first();
        $used = $this->usedToday($user, $data['placement']);
        $cap = (int) $first->daily_cap;
        $remaining = $cap > 0 ? max(0, $cap - $used) : null;

        return $this->ok([
            'placement' => $data['placement'],
            'format' => $first->format,
            'reward_coins' => $this->rewardFor($first),
            'used_today' => $used,
            'remaining' => $remaining,
            'capped' => $remaining !== null && $remaining <= 0,
            'units' => $units->map(fn (AdPlacement $p) => [
                'network' => $p->network,
                'ad_unit_id' => $p->ad_unit_id,
                'format' => $p->format,
                'priority' => (int) $p->priority,
            ])->values(),
        ]);
    }

    /** POST /api/ads/reward  {placement, network, idempotency_key} */
    public function reward(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'placement' => ['required', 'string', 'max:40'],
            'network' => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        // Scope the key to the user: two devices can generate the same string.
        $key = $user->id.':'.$data['idempotency_key'];

        $existing = AdView::where('idempotency_key', $key)->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return $this->fail('That key belongs to another account.', 409);
            }

            return $this->ok([
                'coins_awarded' => (int) $existing->reward_coins,
                'wallet' => $user->wallet(),
                'verified' => (bool) $existing->verified,
                'replayed' => true,
            ]);
        }

        $placement = AdPlacement::where('placement', $data['placement'])
            ->where('active', true)
            ->when(
                $data['network'] ?? null,
                fn ($q, $network) => $q->where('network', $network),
            )
            ->orderBy('priority')
            ->first();

        if (! $placement) {
            return $this->fail('Unknown ad placement.', 404);
        }

        $used = $this->usedToday($user, $data['placement']);

        if ((int) $placement->daily_cap > 0 && $used >= (int) $placement->daily_cap) {
            return $this->fail('Daily ad limit reached for this placement.', 429, [
                'daily_cap' => (int) $placement->daily_cap,
            ]);
        }

        $globalCap = Cfg::int('daily_ad_reward_cap');

        if ($globalCap > 0 && $used >= $globalCap) {
            return $this->fail('Daily ad limit reached. Come back tomorrow!', 429, [
                'daily_cap' => $globalCap,
            ]);
        }

        $reward = $this->rewardFor($placement);

        $view = AdView::create([
            'user_id' => $user->id,
            'placement' => $placement->placement,
            'network' => $placement->network,
            'idempotency_key' => $key,
            'reward_coins' => $reward,
            'provisional_at' => now(),
            'verified' => false,
            'ip' => $request->ip(),
        ]);

        if ($reward > 0) {
            $this->ledger->apply(
                $user,
                'ad_reward',
                ['coins' => $reward],
                idempotencyKey: 'ad:'.$key,
                source: 'ads',
                meta: [
                    'placement' => $placement->placement,
                    'network' => $placement->network,
                    'ad_view_id' => $view->id,
                ],
            );
        }

        return $this->ok([
            'coins_awarded' => $reward,
            'wallet' => $user->fresh()->wallet(),
            'verified' => false,
            'replayed' => false,
        ]);
    }

    private function rewardFor(AdPlacement $placement): int
    {
        $configured = (int) $placement->reward_coins;

        return $configured > 0 ? $configured : Cfg::int('ad_reward_coins');
    }

    private function usedToday(User $user, string $placement): int
    {
        return AdView::where('user_id', $user->id)
            ->where('placement', $placement)
            ->where('revoked', false)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }
}
