<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-exercise comment used to drive AI plan refinement.
 *
 * `type` semantics (FE-driven):
 *  - pain   — dor/lesão sentida no exercício
 *  - broken — equipamento indisponível ou quebrado
 *  - heavy  — carga pesada demais; pedir para diminuir
 *  - custom — texto livre (campo `text` obrigatório no UX)
 */
class PlanWorkoutExerciseComment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_workout_exercise_id',
        'type',
        'text',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function planWorkoutExercise()
    {
        return $this->belongsTo(PlanWorkoutExercise::class, 'plan_workout_exercise_id');
    }
}
