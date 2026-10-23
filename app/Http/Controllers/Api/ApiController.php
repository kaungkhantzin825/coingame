<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Cfg;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($user->isBanned(), 403, 'Account suspended.');

        return $user;
    }

    protected function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'referral_code' => $user->referral_code,
            'wallet' => $user->wallet(),
            'daily_streak' => $user->daily_streak,
            'status' => $user->status,
            'cash_display' => number_format($user->cash_cents / 100, 2),
        ];
    }

    protected function ok(array $data = [], int $status = 200)
    {
        return response()->json(['ok' => true] + $data, $status);
    }

    protected function fail(string $message, int $status = 422, array $extra = [])
    {
        return response()->json(['ok' => false, 'message' => $message] + $extra, $status);
    }

    protected function clientConfig(): array
    {
        return Cfg::clientBundle();
    }
}
