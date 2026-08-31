<?php

namespace App\Http\Requests;

use App\Enums\RecipeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealTiersLibraryMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_values(array_filter(
                RecipeCategory::values(),
                static fn (string $value): bool => $value !== RecipeCategory::BaseRecipe->value,
            )))],
            'description' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['required_with:ingredients', 'integer', 'exists:ingredients,id'],
            'ingredients.*.amount_grams' => ['required_with:ingredients', 'numeric', 'min:0'],
            'calorie_tiers' => ['nullable', 'array'],
            'calorie_tiers.*.calorie_tier' => ['required_with:calorie_tiers', 'integer', 'min:300', 'max:800'],
            'calorie_tiers.*.ingredients' => ['nullable', 'array'],
            'calorie_tiers.*.ingredients.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'calorie_tiers.*.ingredients.*.amount_grams' => ['required', 'numeric', 'min:0'],
        ];
    }
}
