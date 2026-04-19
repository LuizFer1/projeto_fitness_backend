<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGoal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_goals';

    protected $fillable = [
        'user_id',
        'main_goal',
        'goal_calories_day',
        'goal_steps_day',
        'goal_weight_kg',
        'goal_protein_g',
        'goal_carbs_g',
        'goal_fat_g',
        'goal_workouts_week',
        'goal_water_liters',
        'deadline',
        'is_active',
    ];

    protected $casts = [
        'deadline' => 'date',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
