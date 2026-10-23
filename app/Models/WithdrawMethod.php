<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawMethod extends Model
{
    protected $fillable = [
        'key', 'label', 'type', 'min_cents', 'fee_cents', 'fee_percent', 'fields', 'active', 'sort',
    ];
    protected $casts = ['fields' => 'array', 'active' => 'boolean'];
}
