<?php

namespace Database\Seeders;

use App\Models\AdPlacement;
use App\Models\AppConfig;
use App\Models\DailyReward;
use App\Models\LiveopsEvent;
use App\Models\Task;
use App\Models\WithdrawMethod;
use App\Support\Cfg;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Remote config (defaults live in App\Support\Cfg::DEFAULTS) ---
        foreach (Cfg::DEFAULTS as $key => $value) {
            AppConfig::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'general']);
        }

        // --- 7-day check-in ladder ---
        $daily = [500, 800, 1200, 1800, 2500, 3500, 8000];
        foreach ($daily as $i => $coins) {
            DailyReward::updateOrCreate(
                ['day' => $i + 1],
                ['reward_coins' => $coins, 'is_bonus' => $i === 6],
            );
        }

        // --- Tasks ---
        $tasks = [
            ['key' => 'play_3', 'title' => 'Play 3 games', 'kind' => 'daily', 'goal' => 3, 'event' => 'play_game', 'reward_coins' => 1500, 'icon' => 'play', 'sort' => 1],
            ['key' => 'merge_50', 'title' => 'Merge 50 animals', 'kind' => 'daily', 'goal' => 50, 'event' => 'merge', 'reward_coins' => 2000, 'icon' => 'merge', 'sort' => 2],
            ['key' => 'score_500', 'title' => 'Score 500 in one game', 'kind' => 'daily', 'goal' => 500, 'event' => 'reach_score', 'reward_coins' => 2500, 'icon' => 'star', 'sort' => 3],
            ['key' => 'watch_5_ads', 'title' => 'Watch 5 videos', 'kind' => 'daily', 'goal' => 5, 'event' => 'watch_ad', 'reward_coins' => 3000, 'icon' => 'video', 'sort' => 4],
            ['key' => 'invite_1', 'title' => 'Invite a friend', 'kind' => 'one_time', 'goal' => 1, 'event' => 'invite', 'reward_coins' => 5000, 'reward_cash_cents' => 20, 'icon' => 'invite', 'sort' => 5],
        ];
        foreach ($tasks as $t) {
            Task::updateOrCreate(['key' => $t['key']], $t + ['active' => true]);
        }

        // --- Live Ops: 60-second tap-timer reward (the "Live Ops" popup) ---
        LiveopsEvent::updateOrCreate(
            ['key' => 'daily_timer'],
            [
                'title' => 'Live Ops',
                'kind' => 'tap_timer',
                'duration_seconds' => 60,
                'reward_coins' => 3000,
                'ad_multiplier' => 3,
                'cooldown_seconds' => 4 * 3600,
                'active' => true,
            ],
        );

        // --- Ad waterfall (AdMob TEST unit ids — swap for real ones in prod) ---
        $rewardedTest = 'ca-app-pub-3940256099942544/5224354917';
        $interstitialTest = 'ca-app-pub-3940256099942544/1033173712';
        $placements = [
            ['placement' => 'liveops', 'network' => 'admob', 'ad_unit_id' => $rewardedTest, 'priority' => 10, 'reward_coins' => 0],
            ['placement' => 'double_reward', 'network' => 'admob', 'ad_unit_id' => $rewardedTest, 'priority' => 10, 'reward_coins' => 0],
            ['placement' => 'free_booster', 'network' => 'admob', 'ad_unit_id' => $rewardedTest, 'priority' => 10, 'reward_coins' => 0, 'daily_cap' => 10],
            ['placement' => 'daily_boost', 'network' => 'admob', 'ad_unit_id' => $rewardedTest, 'priority' => 10, 'reward_coins' => 0],
            ['placement' => 'task_skip', 'network' => 'admob', 'ad_unit_id' => $rewardedTest, 'priority' => 10, 'reward_coins' => 0, 'daily_cap' => 5],
            ['placement' => 'coin_ad', 'network' => 'admob', 'ad_unit_id' => $rewardedTest, 'priority' => 10, 'reward_coins' => 500, 'daily_cap' => 20],
            ['placement' => 'interstitial', 'network' => 'admob', 'ad_unit_id' => $interstitialTest, 'format' => 'interstitial', 'priority' => 10, 'reward_coins' => 0],
        ];
        foreach ($placements as $p) {
            AdPlacement::updateOrCreate(
                ['placement' => $p['placement'], 'network' => $p['network']],
                $p + ['active' => true],
            );
        }

        // --- Withdraw methods (Malaysia-oriented; edit freely) ---
        $account = [
            ['key' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true],
            ['key' => 'phone', 'label' => 'Phone number', 'type' => 'phone', 'required' => true],
        ];
        WithdrawMethod::updateOrCreate(['key' => 'tng'], [
            'label' => "Touch 'n Go eWallet", 'type' => 'ewallet',
            'min_cents' => 500, 'fee_cents' => 0, 'fields' => $account, 'active' => true, 'sort' => 1,
        ]);
        WithdrawMethod::updateOrCreate(['key' => 'duitnow'], [
            'label' => 'Bank / DuitNow', 'type' => 'bank',
            'min_cents' => 1000, 'fee_cents' => 0, 'fields' => array_merge($account, [
                ['key' => 'bank', 'label' => 'Bank name', 'type' => 'text', 'required' => true],
                ['key' => 'account_no', 'label' => 'Account number', 'type' => 'text', 'required' => true],
            ]), 'active' => true, 'sort' => 2,
        ]);
    }
}
