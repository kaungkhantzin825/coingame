<?php

namespace App\Http\Controllers\Api;

use App\Models\WithdrawMethod;
use App\Models\Withdrawal;
use App\Services\LedgerService;
use App\Support\Cfg;
use Illuminate\Http\Request;

/**
 * Payout requests. Money is held (deducted) at request time and only released
 * by an admin decision in /admin — see WithdrawalService for the state machine.
 */
class WithdrawController extends ApiController
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /** GET /api/withdraw/methods */
    public function methods()
    {
        $methods = WithdrawMethod::where('active', true)->orderBy('sort')->get()->map(fn (WithdrawMethod $m) => [
            'key' => $m->key,
            'label' => $m->label,
            'type' => $m->type,
            'min_cents' => (int) $m->min_cents,
            'min_display' => Cfg::formatCents((int) $m->min_cents),
            'fee_cents' => (int) $m->fee_cents,
            'fee_percent' => (int) $m->fee_percent,
            'fields' => $m->fields,
        ]);

        return $this->ok([
            'methods' => $methods,
            'currency_code' => Cfg::string('currency_code'),
            'min_withdraw_cents' => Cfg::int('min_withdraw_cents'),
        ]);
    }

    /** POST /api/withdraw  {method_key, amount_cents, account{}} */
    public function store(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'method_key' => ['required', 'string', 'max:30'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'account' => ['required', 'array'],
        ]);

        $method = WithdrawMethod::where('key', $data['method_key'])->where('active', true)->first();

        if (! $method) {
            return $this->fail('Unknown payout method.', 404);
        }

        $amount = (int) $data['amount_cents'];
        $floor = max((int) $method->min_cents, Cfg::int('min_withdraw_cents'));

        if ($amount < $floor) {
            return $this->fail('Amount is below the minimum of '.Cfg::formatCents($floor).'.', 422, [
                'min_cents' => $floor,
            ]);
        }

        if ($amount > (int) $user->cash_cents) {
            return $this->fail('Not enough cash balance.', 422, ['cash_cents' => (int) $user->cash_cents]);
        }

        // Required payout fields must all be present (they are configured per method).
        $missing = collect($method->fields ?? [])
            ->filter(fn ($f) => ($f['required'] ?? false) && blank($data['account'][$f['key']] ?? null))
            ->pluck('key')
            ->all();

        if ($missing) {
            return $this->fail('Missing payout details: '.implode(', ', $missing).'.', 422, ['missing' => $missing]);
        }

        $fee = (int) $method->fee_cents + intdiv($amount * (int) $method->fee_percent, 100);
        $net = $amount - $fee;

        if ($net <= 0) {
            return $this->fail('Fee is larger than the amount.', 422);
        }

        // Hold the gross amount immediately so it cannot be spent twice.
        $this->ledger->apply(
            $user,
            'withdraw_hold',
            ['cash_cents' => -$amount],
            idempotencyKey: "withdraw:{$user->id}:".now()->timestamp.':'.$amount,
            source: 'withdraw',
            meta: ['method' => $method->key, 'amount_cents' => $amount, 'fee_cents' => $fee],
        );

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'method_key' => $method->key,
            'amount_cents' => $amount,
            'fee_cents' => $fee,
            'net_cents' => $net,
            'account' => $data['account'],
            'status' => 'pending',
            'ip' => $request->ip(),
        ]);

        return $this->ok([
            'withdrawal' => $this->summary($withdrawal),
            'wallet' => $user->fresh()->wallet(),
        ], 201);
    }

    /** GET /api/withdraw/history */
    public function history(Request $request)
    {
        $user = $this->user($request);

        $items = Withdrawal::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Withdrawal $w) => $this->summary($w));

        return $this->ok(['withdrawals' => $items]);
    }

    private function summary(Withdrawal $w): array
    {
        return [
            'id' => $w->id,
            'method_key' => $w->method_key,
            'amount_cents' => (int) $w->amount_cents,
            'amount_display' => Cfg::formatCents((int) $w->amount_cents),
            'fee_cents' => (int) $w->fee_cents,
            'net_cents' => (int) $w->net_cents,
            'net_display' => Cfg::formatCents((int) $w->net_cents),
            'status' => $w->status,
            'reference' => $w->reference,
            'admin_note' => $w->admin_note,
            'created_at' => $w->created_at?->toIso8601String(),
            'processed_at' => $w->processed_at?->toIso8601String(),
        ];
    }
}
