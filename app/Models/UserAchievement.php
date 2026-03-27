<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    use HasUuids;

    protected $table = 'user_achievements';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'achievement_id', 'unlocked_at', 'xp_received', 'is_notified',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'is_notified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }
}
