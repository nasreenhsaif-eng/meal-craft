<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBaseIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'finished_weight_grams' => ['required', 'numeric', 'gt:0'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.ingredient_id' => [
                'required',
                'integer',
                Rule::exists('ingredients', 'id')->where('is_verified', true),
            ],
            'components.*.amount_grams' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
