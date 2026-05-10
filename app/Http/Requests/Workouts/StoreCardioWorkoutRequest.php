<?php

namespace App\Http\Requests\Workouts;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardioWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'duration_min' => ['nullable', 'integer', 'min:1'],
            'calories_burned' => ['nullable', 'numeric', 'min:0'],
            'distance_m' => ['nullable', 'integer', 'min:0'],
            'pace_seconds_per_km' => ['nullable', 'integer', 'min:0'],
            'avg_hr' => ['nullable', 'integer', 'min:30', 'max:300'],
            'max_hr' => ['nullable', 'integer', 'min:30', 'max:300'],
            'elevation_gain_m' => ['nullable', 'integer'],
            'route_polyline' => ['nullable', 'string'],
            'mood' => ['nullable', 'in:great,good,neutral,tired,bad'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'external_source' => ['nullable', 'in:manual,healthkit,googlefit,garmin'],
            'external_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}
