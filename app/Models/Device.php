<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = [
        'device_id', 'user_id', 'platform', 'model', 'os_version', 'app_version',
        'is_emulator', 'last_ip', 'last_seen_at', 'payout_count', 'fingerprint',
    ];

    protected $casts = [
        'is_emulator' => 'boolean',
        'last_seen_at' => 'datetime',
        'fingerprint' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
