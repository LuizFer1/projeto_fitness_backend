<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExercisePersonalRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'exercise_id', 'workout_log_id',
        'one_rm_kg', 'weight_kg', 'reps', 'achieved_at',
    ];

    protected $casts = [
        'one_rm_kg' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'achieved_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
}
