<?php

namespace App\Http\Requests\Nutrition;

use App\Support\MealTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeMealImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'meal_type' => ['required', 'string', Rule::in(MealTypes::ALL)],
            'image_base64' => ['required', 'string'],
        ];
    }
}
