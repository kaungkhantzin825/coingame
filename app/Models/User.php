<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'device_id', 'referral_code', 'referred_by',
        'coins', 'cash_cents', 'gems',
        'daily_streak', 'last_daily_claim_on', 'total_withdrawn_cents',
        'status', 'is_admin', 'banned_at', 'meta',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_daily_claim_on' => 'date',
        'banned_at' => 'datetime',
        'meta' => 'array',
        'is_admin' => 'boolean',
        'coins' => 'integer',
        'cash_cents' => 'integer',
        'gems' => 'integer',
        'daily_streak' => 'integer',
        'total_withdrawn_cents' => 'integer',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    /** Public wallet snapshot for API responses. */
    public function wallet(): array
    {
        return [
            'coins' => (int) $this->coins,
            'cash_cents' => (int) $this->cash_cents,
            'gems' => (int) $this->gems,
        ];
    }
}
