<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGamification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_gamification';

    public const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'xp_total',
        'current_level',
        'xp_to_next',
        'current_streak',
        'max_streak',
        'last_activity',
        'current_week_xp',
        'current_month_xp',
        'total_workouts',
        'total_water_days',
        'last_week_safety_day_used',
        'last_processed_date',
    ];

    protected $casts = [
        'last_activity' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
