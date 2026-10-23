<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id', 'method_key', 'amount_cents', 'fee_cents', 'net_cents',
        'account', 'status', 'admin_note', 'reference', 'processed_by', 'processed_at', 'ip',
    ];
    protected $casts = ['processed_at' => 'datetime'];

    // Store the payout account details encrypted at rest.
    protected function account(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? json_decode(decrypt($v), true) : null,
            set: fn ($v) => encrypt(json_encode($v)),
        );
    }

    public function user() { return $this->belongsTo(User::class); }
}
