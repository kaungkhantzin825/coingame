<?php

namespace App\Filament\Widgets;

use App\Models\GameSession;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();
        $pendingCents = (int) Withdrawal::where('status', 'pending')->sum('net_cents');
        $paidCents = (int) Withdrawal::where('status', 'paid')->sum('net_cents');

        $coinsInCirculation = (int) User::sum('coins');
        $cashOwed = (int) User::sum('cash_cents');

        $newUsersToday = User::where('created_at', '>=', $today)->count();
        $sessionsToday = GameSession::where('created_at', '>=', $today)->count();
        $coinsAwardedToday = (int) Transaction::where('created_at', '>=', $today)
            ->where('coins_delta', '>', 0)->sum('coins_delta');

        return [
            Stat::make('Pending withdrawals', $pendingWithdrawals)
                ->description('RM '.number_format($pendingCents / 100, 2).' to review')
                ->color($pendingWithdrawals ? 'warning' : 'success')
                ->icon('heroicon-m-banknotes'),

            Stat::make('Paid out (all time)', 'RM '.number_format($paidCents / 100, 2))
                ->icon('heroicon-m-check-badge'),

            Stat::make('New players today', $newUsersToday)
                ->description($sessionsToday.' games played today')
                ->icon('heroicon-m-user-plus'),

            Stat::make('Coins in circulation', number_format($coinsInCirculation))
                ->description(number_format($coinsAwardedToday).' awarded today')
                ->icon('heroicon-m-circle-stack'),

            Stat::make('Cash owed to players', 'RM '.number_format($cashOwed / 100, 2))
                ->description('Un-withdrawn balances')
                ->color('gray')
                ->icon('heroicon-m-wallet'),
        ];
    }
}
