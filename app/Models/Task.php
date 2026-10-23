<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'key', 'title', 'description', 'kind', 'goal', 'event',
        'reward_coins', 'reward_cash_cents', 'reward_gems', 'icon', 'sort', 'active',
    ];
    protected $casts = ['active' => 'boolean'];
}
