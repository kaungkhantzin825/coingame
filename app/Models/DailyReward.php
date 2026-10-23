<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReward extends Model
{
    public $timestamps = true;
    protected $fillable = ['day', 'reward_coins', 'reward_cash_cents', 'reward_gems', 'is_bonus'];
    protected $casts = ['is_bonus' => 'boolean'];
}
