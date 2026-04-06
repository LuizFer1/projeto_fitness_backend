<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyActivityLimit extends Model
{
    use HasUuids;

    protected $table = 'daily_activity_limits';

    protected $fillable = [
        'user_id', 'date', 'workout_count', 'water_liters',
        'water_goal_reached', 'weight_logged', 'daily_xp_gained',
        'daily_xp_limit', 'login_xp_granted', 'meal_xp_granted',
    ];

    protected $casts = [
        'date' => 'date',
        'water_goal_reached' => 'boolean',
        'weight_logged' => 'boolean',
        'login_xp_granted' => 'boolean',
        'meal_xp_granted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
