<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTask extends Model
{
    protected $fillable = ['user_id', 'task_id', 'period', 'progress', 'completed_at', 'claimed_at'];
    protected $casts = ['period' => 'date', 'completed_at' => 'datetime', 'claimed_at' => 'datetime'];

    public function task() { return $this->belongsTo(Task::class); }
}
