<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Withdrawal state machine. Money is *held* (deducted) the moment the request
 * is created (see the API), so approving just moves paperwork and rejecting
 * refunds the hold.
 *
 *   pending → approved → processing → paid
 *   pending / approved → rejected  (refunds the held balance)
 */
class WithdrawalService
{
    public function __construct(private LedgerService $ledger)
    {
    }

    public function approve(Withdrawal $w, int $adminId, ?string $note = null): Withdrawal
    {
        $this->assertStatus($w, ['pending']);
        $w->update(['status' => 'approved', 'processed_by' => $adminId, 'admin_note' => $note]);

        return $w;
    }

    public function markProcessing(Withdrawal $w, int $adminId): Withdrawal
    {
        $this->assertStatus($w, ['pending', 'approved']);
        $w->update(['status' => 'processing', 'processed_by' => $adminId]);

        return $w;
    }

    public function markPaid(Withdrawal $w, int $adminId, ?string $reference = null, ?string $note = null): Withdrawal
    {
        $this->assertStatus($w, ['pending', 'approved', 'processing']);

        return DB::transaction(function () use ($w, $adminId, $reference, $note) {
            // The hold already left the wallet; just record the finalisation and
            // bump lifetime totals + device payout count for fraud limits.
            $this->ledger->apply(
                $w->user, 'withdraw_paid',
                ['cash_cents' => 0],
                idempotencyKey: "withdraw_paid:{$w->id}",
                source: 'admin',
                meta: ['withdrawal_id' => $w->id, 'reference' => $reference],
            );

            $w->user->increment('total_withdrawn_cents', $w->net_cents);
            Device::where('user_id', $w->user_id)->increment('payout_count');

            $w->update([
                'status' => 'paid',
                'reference' => $reference,
                'admin_note' => $note ?? $w->admin_note,
                'processed_by' => $adminId,
                'processed_at' => now(),
            ]);

            return $w->fresh();
        });
    }

    public function reject(Withdrawal $w, int $adminId, string $reason): Withdrawal
    {
        $this->assertStatus($w, ['pending', 'approved', 'processing']);

        return DB::transaction(function () use ($w, $adminId, $reason) {
            // Refund the held amount (gross) back to the wallet.
            $this->ledger->apply(
                $w->user, 'withdraw_refund',
                ['cash_cents' => $w->amount_cents],
                idempotencyKey: "withdraw_refund:{$w->id}",
                source: 'admin',
                meta: ['withdrawal_id' => $w->id, 'reason' => $reason],
            );

            $w->update([
                'status' => 'rejected',
                'admin_note' => $reason,
                'processed_by' => $adminId,
                'processed_at' => now(),
            ]);

            return $w->fresh();
        });
    }

    private function assertStatus(Withdrawal $w, array $allowed): void
    {
        if (! in_array($w->status, $allowed, true)) {
            throw new RuntimeException("Cannot do that from status '{$w->status}'.");
        }
    }
}
