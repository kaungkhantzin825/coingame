<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\ReferralService;
use App\Support\Cfg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceAuthController extends ApiController
{
    public function __construct(
        private LedgerService $ledger,
        private ReferralService $referrals,
    ) {
    }

    /** POST /api/auth/device — register or log in by device id, return a token. */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string', 'max:120'],
            'os_version' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'is_emulator' => ['nullable', 'boolean'],
            'referral_code' => ['nullable', 'string', 'max:12'],
        ]);

        $isNew = false;

        $user = DB::transaction(function () use ($data, &$isNew) {
            $user = User::where('device_id', $data['device_id'])->lockForUpdate()->first();

            if (! $user) {
                $isNew = true;
                $user = User::create([
                    'device_id' => $data['device_id'],
                    'name' => 'Player'.random_int(1000, 9999),
                    'email' => 'd_'.Str::random(24).'@device.local',
                    'password' => bcrypt(Str::random(40)),
                    'referral_code' => $this->uniqueReferralCode(),
                ]);
            }

            return $user;
        });

        if ($isNew) {
            // One-time signup bonus (idempotent).
            $this->ledger->apply(
                $user, 'admin_adjust',
                ['coins' => Cfg::int('signup_bonus_coins')],
                idempotencyKey: "signup:{$user->id}",
                source: 'system',
                meta: ['reason' => 'signup_bonus'],
            );

            // Same code path as the in-app "enter a friend's code" screen, so
            // both routes obey identical rules and idempotency keys.
            if (! empty($data['referral_code'])) {
                $this->referrals->redeem($user, $data['referral_code']);
            }
        }

        Device::updateOrCreate(
            ['device_id' => $data['device_id']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'model' => $data['model'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'is_emulator' => (bool) ($data['is_emulator'] ?? false),
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
            ],
        );

        $token = $user->createToken($data['device_id'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'new_user' => $isNew,
            'user' => $this->profile($user->fresh()),
            'config' => $this->clientConfig(),
        ]);
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(7));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
