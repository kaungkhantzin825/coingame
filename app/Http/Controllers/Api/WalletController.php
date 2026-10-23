<?php

namespace App\Http\Controllers\Api;

use App\Services\LedgerService;
use App\Support\Cfg;
use Illuminate\Http\Request;

/**
 * Coin -> cash conversion. The rate and minimums come from `app_configs`, so
 * an admin changing `coins_per_cent` changes the economy for every player
 * without an app release.
 */
class WalletController extends ApiController
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /** POST /api/wallet/convert  {coins, idempotency_key?} */
    public function convert(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'coins' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        $coins = (int) $data['coins'];
        $minCoins = Cfg::int('min_convert_coins');

        if ($coins < $minCoins) {
            return $this->fail("You need at least {$minCoins} coins to convert.", 422, [
                'min_convert_coins' => $minCoins,
            ]);
        }

        if ($coins > (int) $user->coins) {
            return $this->fail('Not enough coins.', 422, ['coins' => (int) $user->coins]);
        }

        $cents = Cfg::coinsToCents($coins);

        if ($cents < 1) {
            return $this->fail('That amount rounds down to zero. Convert more coins.', 422);
        }

        $key = isset($data['idempotency_key'])
            ? "convert:{$user->id}:{$data['idempotency_key']}"
            : null;

        $txn = $this->ledger->apply(
            $user,
            'convert',
            ['coins' => -$coins, 'cash_cents' => $cents],
            idempotencyKey: $key,
            source: 'wallet',
            meta: ['coins' => $coins, 'cash_cents' => $cents, 'rate' => Cfg::int('coins_per_cent')],
        );

        return $this->ok([
            'converted_coins' => $coins,
            'cash_cents' => $cents,
            'cash_display' => Cfg::formatCents($cents),
            'wallet' => $user->fresh()->wallet(),
            'transaction_id' => $txn->id,
        ]);
    }
}
