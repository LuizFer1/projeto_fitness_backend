<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutExerciseLog extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function workoutLog()
    {
        return $this->belongsTo(WorkoutLog::class);
    }
}
