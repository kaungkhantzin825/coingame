<?php

namespace App\Http\Controllers\Api;

use App\Services\ReferralService;
use Illuminate\Http\Request;

/**
 * Invite screen data and code redemption.
 *
 * Codes are also accepted at registration time (`POST /api/auth/device`), but
 * most players only think about inviting a friend later, so `redeem` lets them
 * enter a code after the fact. A player can only ever be referred once.
 */
class ReferralController extends ApiController
{
    public function __construct(private ReferralService $referrals)
    {
    }

    /** GET /api/referral */
    public function show(Request $request)
    {
        $user = $this->user($request);

        return $this->ok($this->referrals->stats($user) + [
            'wallet' => $user->wallet(),
        ]);
    }

    /** POST /api/referral/redeem { code } */
    public function redeem(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        $result = $this->referrals->redeem($user, $data['code']);

        if (! $result['ok']) {
            return $this->fail($result['message'], 422);
        }

        return $this->ok([
            'message' => $result['message'],
            'bonus_coins' => $result['bonus_coins'],
            'wallet' => $user->fresh()->wallet(),
        ] + $this->referrals->stats($user->fresh()));
    }
}
