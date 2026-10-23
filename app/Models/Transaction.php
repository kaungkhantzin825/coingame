<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'type', 'coins_delta', 'cash_cents_delta', 'gems_delta',
        'balance_coins_after', 'balance_cash_after', 'balance_gems_after',
        'idempotency_key', 'source', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'coins_delta' => 'integer',
        'cash_cents_delta' => 'integer',
        'gems_delta' => 'integer',
        'balance_coins_after' => 'integer',
        'balance_cash_after' => 'integer',
        'balance_gems_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
