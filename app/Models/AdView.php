<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdView extends Model
{
    protected $fillable = [
        'user_id', 'placement', 'network', 'idempotency_key', 'reward_coins',
        'ecpm_cents', 'provisional_at', 'verified', 'verified_at', 'revoked', 'ssv_payload', 'ip',
    ];
    protected $casts = [
        'provisional_at' => 'datetime', 'verified' => 'boolean', 'verified_at' => 'datetime',
        'revoked' => 'boolean', 'ssv_payload' => 'array',
    ];
}
