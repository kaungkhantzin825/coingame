<?php

namespace App\Http\Controllers\Api;

use App\Models\Referral;
use App\Support\Cfg;
use Illuminate\Http\Request;

/**
 * Invite screen data. Codes are attached at registration time by
 * DeviceAuthController; there is no separate "claim code" endpoint.
 */
class ReferralController extends ApiController
{
    /** GET /api/referral */
    public function show(Request $request)
    {
        $user = $this->user($request);

        $base = Referral::where('referrer_id', $user->id);

        return $this->ok([
            'code' => $user->referral_code,
            'invited' => (clone $base)->count(),
            'qualified' => (clone $base)->where('status', 'qualified')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'earned_coins' => (int) (clone $base)->where('status', 'qualified')->sum('bonus_coins'),
            'bonus_coins' => Cfg::int('referral_bonus_coins'),
            'referee_bonus_coins' => Cfg::int('referral_referee_bonus_coins'),
        ]);
    }
}
