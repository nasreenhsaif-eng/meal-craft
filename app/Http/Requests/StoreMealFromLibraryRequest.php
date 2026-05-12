<?php

namespace App\Http\Requests;

use App\Enums\CyclePhase;
use App\Enums\RecipeCategory;
use App\Support\MealLibraryTaxonomy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealFromLibraryRequest extends FormRequest
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
        $categoryValues = array_map(
            static fn (RecipeCategory $c): string => $c->value,
            RecipeCategory::cases(),
        );

        $cycleValues = array_map(
            static fn (CyclePhase $p): string => $p->value,
            CyclePhase::cases(),
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'total_calories' => ['required', 'numeric', 'min:0'],
            'total_protein' => ['nullable', 'numeric', 'min:0'],
            'total_carbs' => ['nullable', 'numeric', 'min:0'],
            'total_fat' => ['nullable', 'numeric', 'min:0'],
            'category' => ['required', 'string', Rule::in($categoryValues)],
            'meal_plan_tag' => ['nullable', 'string', Rule::in(MealLibraryTaxonomy::MEAL_PLAN_TAGS)],
            'diet_tags' => ['nullable', 'array'],
            'diet_tags.*' => ['string', Rule::in(MealLibraryTaxonomy::DIETARY_TAGS)],
            'cycle_phase' => ['nullable', 'string', Rule::in($cycleValues)],
            'description' => ['nullable', 'string', 'max:65535'],
            'highlight' => ['nullable', 'string', 'max:2000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->where('is_verified', true)],
            'ingredients.*.name' => ['nullable', 'string', 'max:255'],
            'ingredients.*.amount_grams' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'use_as_base_ingredient' => ['sometimes', 'boolean'],
            'finished_weight_grams' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
