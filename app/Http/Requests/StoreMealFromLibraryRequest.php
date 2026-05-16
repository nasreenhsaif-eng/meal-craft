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

        $instructions = $this->input('instructions');
        if (($instructions === null || trim((string) $instructions) === '') && $this->filled('description')) {
            $instructions = $this->input('description');
        }

        $shortDescription = $this->input('short_description');
        if (($shortDescription === null || trim((string) $shortDescription) === '') && $this->filled('highlight')) {
            $shortDescription = $this->input('highlight');
        }

        $merge = [];
        if ($instructions !== null) {
            $merge['instructions'] = $instructions;
        }
        if ($shortDescription !== null) {
            $merge['short_description'] = $shortDescription;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }

        if ($this->has('meal_plan_tags') && is_array($this->input('meal_plan_tags'))) {
            $planTags = [];
            foreach ($this->input('meal_plan_tags') as $tag) {
                if (! is_string($tag) || trim($tag) === '') {
                    continue;
                }
                $canonical = MealLibraryTaxonomy::resolveMealPlanTagCanonical($tag);
                if ($canonical !== null) {
                    $planTags[] = $canonical;
                }
            }
            $this->merge(['meal_plan_tags' => array_values(array_unique($planTags))]);
        }

        if ($this->has('cycle_phases') && is_array($this->input('cycle_phases'))) {
            $phaseValues = [];
            foreach ($this->input('cycle_phases') as $phase) {
                if (! is_string($phase) || trim($phase) === '') {
                    continue;
                }
                $enum = CyclePhase::tryFrom($phase);
                if ($enum !== null) {
                    $phaseValues[] = $enum->value;
                }
            }
            $this->merge(['cycle_phases' => array_values(array_unique($phaseValues))]);
        }

        if ($this->has('diet_tags') && is_array($this->input('diet_tags'))) {
            $dietTags = [];
            foreach ($this->input('diet_tags') as $tag) {
                if (! is_string($tag) || trim($tag) === '') {
                    continue;
                }
                $canonical = MealLibraryTaxonomy::resolveDietaryTagCanonical($tag);
                if ($canonical !== null) {
                    $dietTags[] = $canonical;
                }
            }
            $this->merge(['diet_tags' => array_values(array_unique($dietTags))]);
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
            'instructions' => ['nullable', 'string', 'max:65535'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:65535'],
            'highlight' => ['nullable', 'string', 'max:2000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'ingredients.*.name' => ['nullable', 'string', 'max:255'],
            'ingredients.*.amount_grams' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:5120'],
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
