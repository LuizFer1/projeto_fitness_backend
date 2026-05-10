<?php

namespace App\Http\Requests\Nutrition;

use App\Support\MealTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeMealTextRequest extends FormRequest
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
            'text_description' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
