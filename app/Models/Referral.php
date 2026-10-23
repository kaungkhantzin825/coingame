<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id', 'referee_id', 'bonus_coins', 'bonus_cash_cents', 'status', 'qualified_at',
    ];
    protected $casts = ['qualified_at' => 'datetime'];
}
