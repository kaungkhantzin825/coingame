<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLiveop extends Model
{
    protected $table = 'user_liveops';
    protected $fillable = ['user_id', 'event_id', 'started_at', 'claimed_at', 'boosted', 'coins_awarded'];
    protected $casts = ['started_at' => 'datetime', 'claimed_at' => 'datetime', 'boosted' => 'boolean'];

    public function event() { return $this->belongsTo(LiveopsEvent::class, 'event_id'); }
}
