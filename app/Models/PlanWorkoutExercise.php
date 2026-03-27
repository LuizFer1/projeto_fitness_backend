<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanWorkoutExercise extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    public $timestamps = false;

    public function planWorkout()
    {
        return $this->belongsTo(PlanWorkout::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
