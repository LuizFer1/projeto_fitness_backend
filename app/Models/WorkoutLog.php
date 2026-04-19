<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutLog extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'muscles_trained' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workoutLogExercises()
    {
        return $this->hasMany(WorkoutExerciseLog::class, 'workout_log_id');
    }
}
