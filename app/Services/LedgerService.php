<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place balances are ever mutated. Every change is written to the
 * append-only `transactions` ledger inside a DB transaction with a row lock
 * on the user, and can be made idempotent with a key.
 */
class LedgerService
{
    /**
     * Apply a balance delta and record a ledger row.
     *
     * @param  array{coins?:int,cash_cents?:int,gems?:int}  $delta
     * @return Transaction  the ledger row (existing one if the key was already used)
     */
    public function apply(
        User $user,
        string $type,
        array $delta,
        ?string $idempotencyKey = null,
        ?string $source = null,
        array $meta = [],
        bool $allowNegative = false,
    ): Transaction {
        $coins = (int) ($delta['coins'] ?? 0);
        $cash = (int) ($delta['cash_cents'] ?? 0);
        $gems = (int) ($delta['gems'] ?? 0);

        return DB::transaction(function () use (
            $user, $type, $coins, $cash, $gems, $idempotencyKey, $source, $meta, $allowNegative
        ) {
            if ($idempotencyKey) {
                $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            /** @var User $locked */
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $newCoins = $locked->coins + $coins;
            $newCash = $locked->cash_cents + $cash;
            $newGems = $locked->gems + $gems;

            if (! $allowNegative && ($newCoins < 0 || $newCash < 0 || $newGems < 0)) {
                throw new RuntimeException('Insufficient balance.');
            }

            $locked->forceFill([
                'coins' => $newCoins,
                'cash_cents' => $newCash,
                'gems' => $newGems,
            ])->save();

            $txn = Transaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'coins_delta' => $coins,
                'cash_cents_delta' => $cash,
                'gems_delta' => $gems,
                'balance_coins_after' => $newCoins,
                'balance_cash_after' => $newCash,
                'balance_gems_after' => $newGems,
                'idempotency_key' => $idempotencyKey,
                'source' => $source,
                'meta' => $meta ?: null,
            ]);

            // Keep the caller's in-memory model fresh.
            $user->forceFill([
                'coins' => $newCoins,
                'cash_cents' => $newCash,
                'gems' => $newGems,
            ])->syncOriginal();

            return $txn;
        });
    }

    /** Total coins credited to a user for a given type since a timestamp (for daily caps). */
    public function creditedSince(User $user, string $type, \DateTimeInterface $since): int
    {
        return (int) Transaction::where('user_id', $user->id)
            ->where('type', $type)
            ->where('coins_delta', '>', 0)
            ->where('created_at', '>=', $since)
            ->sum('coins_delta');
    }
}
