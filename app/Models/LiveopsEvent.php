<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveopsEvent extends Model
{
    protected $fillable = [
        'key', 'title', 'kind', 'duration_seconds', 'reward_coins', 'reward_cash_cents',
        'ad_multiplier', 'cooldown_seconds', 'active', 'starts_at', 'ends_at',
    ];
    protected $casts = ['active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
