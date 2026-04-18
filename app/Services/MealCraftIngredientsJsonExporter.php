<?php

namespace App\Services;

use App\Models\Ingredient;
use Illuminate\Support\Facades\File;
use JsonException;

/**
 * Exports the ingredient library to {@see storage_path('app/Enriched_Meal_Craft_Ingredients.json')} (same shape as {@see \App\Console\Commands\EnrichMealCraftIngredientsJsonCommand}).
 */
final class MealCraftIngredientsJsonExporter
{
    public static function defaultPath(): string
    {
        return storage_path('app/Enriched_Meal_Craft_Ingredients.json');
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    public static function buildPayload(): array
    {
        return Ingredient::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'standardized_name' => $i->standardized_name,
                'portion_grams' => $i->portion_grams,
                'fdc_id' => $i->fdc_id,
                'usda_description' => $i->usda_description,
                'usda_data_type' => $i->usda_data_type,
                'usda_food_category' => $i->usda_food_category,
                'usda_match_summary' => $i->usdaMatchSummary(),
                'calories' => $i->calories,
                'protein' => $i->protein,
                'carbs' => $i->carbs,
                'fat' => $i->fat,
                'b6' => $i->b6,
                'b9_folate' => $i->b9_folate,
                'b12' => $i->b12,
                'iron' => $i->iron,
                'magnesium' => $i->magnesium,
                'functional_tip' => $i->functional_tip,
                'sickle_cell_support_message' => $i->sickle_cell_support_message,
                'is_verified' => $i->is_verified,
                'fdc_key_nutrients' => $i->fdc_key_nutrients,
                'micronutrients' => $i->micronutrients,
                'created_at' => $i->created_at?->toIso8601String(),
                'updated_at' => $i->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @throws JsonException
     */
    public static function export(?string $absolutePath = null): int
    {
        $path = $absolutePath ?? self::defaultPath();
        $payload = self::buildPayload();
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return count($payload);
    }
}
