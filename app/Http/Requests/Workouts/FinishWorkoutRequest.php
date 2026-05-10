<?php

namespace App\Http\Requests\Workouts;

use Illuminate\Foundation\Http\FormRequest;

class FinishWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i:s'],
            'time_end' => ['required', 'date_format:H:i:s'],
            'plan_workout_id' => ['nullable', 'uuid', 'exists:plan_workouts,id'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.exercise_id' => ['required', 'uuid', 'exists:exercises,id'],
            'exercises.*.sets' => ['required', 'integer', 'min:1', 'max:50'],
            'exercises.*.reps' => ['required', 'integer', 'min:1', 'max:200'],
            'exercises.*.weight_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }
}
