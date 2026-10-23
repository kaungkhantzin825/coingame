<?php

namespace App\Support;

use App\Models\AppConfig;

/**
 * Typed access to `app_configs` with sane fallbacks, so a missing row never
 * breaks the game.
 */
class Cfg
{
    public const DEFAULTS = [
        // Economy — every value here is editable from /admin/app-configs.
        'currency_code' => 'USD',            // payout currency shown to players
        'coins_per_cent' => 1000,           // 1000 coins == 1 cent  (100k coins == 1 unit)
        'min_withdraw_cents' => 500,        // 5.00
        'min_convert_coins' => 10000,
        'signup_bonus_coins' => 2000,

        // Referral program — all four are editable at /admin/referral-settings.
        'referral_enabled' => true,
        'referral_bonus_coins' => 5000,          // paid to the player who invited
        'referral_referee_bonus_coins' => 1000,  // paid to the new player
        'referral_qualify_sessions' => 3,        // runs the invitee must finish first

        // Game rewards
        'merge_reward_base' => 1,           // coins per merge score point
        'merge_reward_multiplier' => 5,
        'max_coins_per_session' => 3000,
        'max_score_per_second' => 40,       // plausibility clamp
        'session_ttl_seconds' => 1800,

        // Daily caps
        'daily_coin_cap' => 20000,
        'daily_ad_reward_cap' => 20,
        'daily_session_cap' => 60,

        // Ads
        'ad_reward_coins' => 500,
        'double_reward_ad_multiplier' => 2,
        'interstitial_enabled' => true,
        'interstitial_every_seconds' => 50,  // show one interstitial per N seconds of play

        // App
        'min_app_version' => '1.0.0',
        'maintenance' => false,
        'maintenance_message' => 'We are doing quick maintenance. Back soon!',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = $default ?? self::DEFAULTS[$key] ?? null;

        return AppConfig::get($key, $fallback);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function string(string $key): string
    {
        return (string) self::get($key);
    }

    /** coins -> cents (floor). */
    public static function coinsToCents(int $coins): int
    {
        $rate = max(1, self::int('coins_per_cent'));

        return intdiv($coins, $rate);
    }

    /** cents -> display string, e.g. 1234 -> "12.34". */
    public static function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Public config bundle for the client. */
    public static function clientBundle(): array
    {
        $perCent = max(1, self::int('coins_per_cent'));

        return [
            'currency_code' => self::string('currency_code'),
            'coins_per_cent' => $perCent,
            'min_withdraw_cents' => self::int('min_withdraw_cents'),
            'min_convert_coins' => self::int('min_convert_coins'),
            'ad_reward_coins' => self::int('ad_reward_coins'),
            'daily_ad_reward_cap' => self::int('daily_ad_reward_cap'),
            'interstitial_enabled' => self::bool('interstitial_enabled'),
            'interstitial_every_seconds' => self::int('interstitial_every_seconds'),
            'min_app_version' => self::string('min_app_version'),
            'maintenance' => self::bool('maintenance'),
            'maintenance_message' => self::string('maintenance_message'),
            // Referral program — amounts are decided by the admin, the client
            // only displays them.
            'referral_enabled' => self::bool('referral_enabled'),
            'referral_bonus_coins' => self::int('referral_bonus_coins'),
            'referral_referee_bonus_coins' => self::int('referral_referee_bonus_coins'),
            'referral_qualify_sessions' => self::int('referral_qualify_sessions'),
            // Derived helpers so the client never has to do the maths.
            'coins_per_unit' => $perCent * 100,          // coins per 1.00 of currency
            'min_withdraw_display' => self::formatCents(self::int('min_withdraw_cents')),
        ];
    }
}
