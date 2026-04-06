<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanWorkout extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    public $timestamps = false;

    public function aiPlan()
    {
        return $this->belongsTo(AiPlan::class, 'ai_plan_id');
    }

    public function exercises()
    {
        return $this->hasMany(PlanWorkoutExercise::class, 'plan_workout_id')->orderBy('order');
    }
}
