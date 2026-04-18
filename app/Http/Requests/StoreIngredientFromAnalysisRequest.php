<?php

namespace App\Http\Requests;

use App\Support\UsdaNutrientMath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientFromAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fdc = [];

        foreach (UsdaNutrientMath::fdcLibraryTrackedNutrientIds() as $id) {
            $fdc['fdc_key_nutrients.'.$id] = ['required', 'numeric', 'min:0'];
        }

        return array_merge([
            'standardized_name' => ['required', 'string', 'max:255'],
            'fdc_id' => ['required', 'integer', 'min:1'],
            'functional_tip' => ['nullable', 'string', 'max:5000'],
            'portion_grams' => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'sickle_cell_support_message' => ['nullable', 'string', 'max:5000'],
            'usda_description' => ['nullable', 'string', 'max:2000'],
            'usda_data_type' => ['nullable', 'string', 'max:128'],
            'usda_food_category' => ['nullable', 'string', 'max:255'],
            'per_100g' => ['required', 'array'],
            'per_100g.calories' => ['required', 'numeric', 'min:0'],
            'per_100g.protein_g' => ['required', 'numeric', 'min:0'],
            'per_100g.fat_g' => ['required', 'numeric', 'min:0'],
            'per_100g.carbs_g' => ['required', 'numeric', 'min:0'],
            // Listing keys keeps them in `validated()['per_100g']` (Laravel strips unvalidated nested keys).
            'per_100g.fiber_g' => ['nullable', 'numeric'],
            'per_100g.omega3_g' => ['nullable', 'numeric'],
            'per_100g.vitamin_a_rae_mcg' => ['nullable', 'numeric'],
            'per_100g.vitamin_b6_mg' => ['nullable', 'numeric'],
            'per_100g.vitamin_b12_mcg' => ['nullable', 'numeric'],
            'per_100g.folate_mcg' => ['nullable', 'numeric'],
            'per_100g.vitamin_c_mg' => ['nullable', 'numeric'],
            'per_100g.calcium_mg' => ['nullable', 'numeric'],
            'per_100g.iron_mg' => ['nullable', 'numeric'],
            'per_100g.potassium_mg' => ['nullable', 'numeric'],
            'per_100g.magnesium_mg' => ['nullable', 'numeric'],
            'per_100g.zinc_mg' => ['nullable', 'numeric'],
            'per_100g.vitamin_e_mg' => ['nullable', 'numeric'],
            'fdc_key_nutrients' => ['required', 'array'],
        ], $fdc);
    }
}
