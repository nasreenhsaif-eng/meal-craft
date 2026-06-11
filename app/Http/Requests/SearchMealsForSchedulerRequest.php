<?php

namespace App\Http\Requests;

use App\Enums\RecipeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchMealsForSchedulerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedCategories = array_map(
            static fn (RecipeCategory $category): string => $category->value,
            array_filter(
                RecipeCategory::cases(),
                static fn (RecipeCategory $category): bool => $category !== RecipeCategory::BaseRecipe,
            ),
        );

        return [
            'q' => ['nullable', 'string', 'max:120'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', Rule::in($allowedCategories)],
        ];
    }
}
