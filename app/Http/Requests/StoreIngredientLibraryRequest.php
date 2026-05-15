<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredientLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->routeIs('admin.ingredient-library.base-ingredient.store', 'admin.ingredient-library.base-ingredient.update')) {
            $this->merge(['is_base_recipe' => true]);

            return;
        }

        if ($this->has('is_base_recipe')) {
            $this->merge([
                'is_base_recipe' => filter_var($this->input('is_base_recipe'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isBaseRecipe = $this->boolean('is_base_recipe')
            || $this->routeIs('admin.ingredient-library.base-ingredient.store', 'admin.ingredient-library.base-ingredient.update');

        if ($isBaseRecipe) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'is_base_recipe' => ['sometimes', 'boolean'],
                'finished_weight_grams' => ['nullable', 'numeric', 'gt:0'],
                'components' => ['required', 'array', 'min:1'],
                'components.*.ingredient_id' => [
                    'required',
                    'integer',
                    Rule::exists('ingredients', 'id')->where('is_verified', true),
                ],
                'components.*.amount_grams' => ['required', 'numeric', 'gt:0'],
                'description' => ['nullable', 'string'],
                'instructions' => ['nullable', 'string'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'is_base_recipe' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
            'fdc_id' => ['nullable', 'integer', 'min:1'],
            'calories' => ['nullable', 'numeric', 'min:0'],
            'protein' => ['nullable', 'numeric', 'min:0'],
            'carbs' => ['nullable', 'numeric', 'min:0'],
            'fat' => ['nullable', 'numeric', 'min:0'],
            'density' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
