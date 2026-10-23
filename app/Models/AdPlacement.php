<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacement extends Model
{
    protected $fillable = [
        'placement', 'network', 'ad_unit_id', 'format', 'priority',
        'floor_ecpm_cents', 'reward_coins', 'daily_cap', 'active',
    ];
    protected $casts = ['active' => 'boolean'];
}
