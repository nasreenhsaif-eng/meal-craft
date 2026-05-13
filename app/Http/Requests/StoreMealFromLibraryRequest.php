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

    protected function prepareForValidation(): void
    {
        if (! $this->has('meal_plan_tags') && $this->filled('meal_plan_tag')) {
            $this->merge([
                'meal_plan_tags' => [trim((string) $this->input('meal_plan_tag'))],
            ]);
        }

        if (! $this->has('cycle_phases') && $this->filled('cycle_phase')) {
            $this->merge([
                'cycle_phases' => [trim((string) $this->input('cycle_phase'))],
            ]);
        }
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
            'meal_plan_tags' => ['nullable', 'array'],
            'meal_plan_tags.*' => ['string', Rule::in(MealLibraryTaxonomy::MEAL_PLAN_TAGS)],
            'meal_plan_tag' => ['nullable', 'string', Rule::in(MealLibraryTaxonomy::MEAL_PLAN_TAGS)],
            'diet_tags' => ['nullable', 'array'],
            'diet_tags.*' => ['string', Rule::in(MealLibraryTaxonomy::DIETARY_TAGS)],
            'cycle_phases' => ['nullable', 'array'],
            'cycle_phases.*' => ['string', Rule::in($cycleValues)],
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
            'is_bulk' => ['sometimes', 'boolean'],
            'servings_count' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:10000',
                Rule::requiredIf(fn (): bool => $this->boolean('is_bulk')),
            ],
            'target_calories' => ['nullable', 'numeric', 'min:0'],
            'target_protein' => ['nullable', 'numeric', 'min:0'],
            'target_carbs' => ['nullable', 'numeric', 'min:0'],
            'target_fat' => ['nullable', 'numeric', 'min:0'],
            'submission_context' => ['nullable', 'string', Rule::in(['duplicate', 'create'])],
        ];
    }
}
