<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPlan extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'content_json' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function planWorkouts()
    {
        return $this->hasMany(PlanWorkout::class, 'ai_plan_id');
    }

    public function planMeals()
    {
        return $this->hasMany(PlanMeal::class, 'ai_plan_id');
    }
}
