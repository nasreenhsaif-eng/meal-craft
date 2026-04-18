<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIngredientFromAnalysisRequest;
use App\Models\Ingredient;
use App\Support\UsdaNutrientMath;
use Illuminate\Http\JsonResponse;

class StoreIngredientFromAnalysisController extends Controller
{
    public function __invoke(StoreIngredientFromAnalysisRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $per100 = $validated['per_100g'];
        $micronutrients = Ingredient::micronutrientsFromUsdaPer100g($per100);

        $fdcNutrients = [];

        foreach (UsdaNutrientMath::fdcLibraryTrackedNutrientIds() as $id) {
            $fdcNutrients[$id] = (float) $validated['fdc_key_nutrients'][$id];
        }

        $standardizedName = $validated['standardized_name'];

        $payload = array_merge(
            [
                'name' => $standardizedName,
                'standardized_name' => $standardizedName,
                'portion_grams' => array_key_exists('portion_grams', $validated) && $validated['portion_grams'] !== null
                    ? (float) $validated['portion_grams']
                    : null,
                'calories' => (float) ($per100['calories'] ?? 0),
                'protein' => (float) ($per100['protein_g'] ?? 0),
                'carbs' => (float) ($per100['carbs_g'] ?? 0),
                'fat' => (float) ($per100['fat_g'] ?? 0),
            ],
            Ingredient::sickleMicroColumnsFromUsdaPer100g($per100),
            [
                'functional_tip' => filled($validated['functional_tip'] ?? null) ? $validated['functional_tip'] : null,
                'sickle_cell_support_message' => filled($validated['sickle_cell_support_message'] ?? null)
                    ? $validated['sickle_cell_support_message']
                    : null,
                'usda_description' => filled($validated['usda_description'] ?? null)
                    ? $validated['usda_description']
                    : null,
                'usda_data_type' => filled($validated['usda_data_type'] ?? null)
                    ? $validated['usda_data_type']
                    : null,
                'usda_food_category' => filled($validated['usda_food_category'] ?? null)
                    ? $validated['usda_food_category']
                    : null,
                'micronutrients' => $micronutrients,
                'fdc_key_nutrients' => $fdcNutrients,
                'fdc_id' => (int) $validated['fdc_id'],
                'is_verified' => true,
            ],
        );

        $ingredient = Ingredient::query()->updateOrCreate(
            ['fdc_id' => $payload['fdc_id']],
            $payload
        );

        return response()->json([
            'success' => true,
            'ingredient_id' => $ingredient->id,
            'message' => 'Saved to Meal Craft Library.',
        ], 201);
    }
}
