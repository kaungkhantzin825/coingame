<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppConfig extends Model
{
    protected $fillable = ['key', 'value', 'group', 'description'];
    protected $casts = ['value' => 'array'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('app_configs'));
        static::deleted(fn () => Cache::forget('app_configs'));
    }

    /** All config as a flat key => value map, cached. */
    public static function all_map(): array
    {
        return Cache::rememberForever('app_configs', function () {
            return static::query()->pluck('value', 'key')->toArray();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_map()[$key] ?? $default;
    }
}
