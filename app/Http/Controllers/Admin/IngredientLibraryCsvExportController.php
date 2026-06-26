<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\IngredientsImport;
use App\Models\Ingredient;
use App\Support\CsvSpreadsheetCellText;
use App\Support\IngredientG6pdSafety;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientLibraryCsvExportController extends Controller
{
    /**
     * Stream verified ingredients as CSV (same columns as {@see IngredientsImport} / public template).
     */
    public function __invoke(): StreamedResponse
    {
        $filename = 'ingredients-library-'.now()->format('Ymd_His').'.csv';

        $headers = [
            'name',
            'category',
            'fdc_id',
            'calories',
            'protein',
            'carbs',
            'fat',
            'b6',
            'b9_folate',
            'b12',
            'iron',
            'magnesium',
            'fiber',
            'sugar',
            'calcium',
            'potassium',
            'sodium',
            'zinc',
            'vitamin_c',
            'vitamin_a',
            'vitamin_e',
            'vitamin_d',
            'vitamin_k2',
            'density',
            'is_base_recipe',
            'recipe_components',
            'description',
            'instructions',
            'finished_weight_grams',
            'g6pd_trigger',
        ];

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            echo "\xEF\xBB\xBF";

            fputcsv($handle, $headers, ',', '"', '\\', "\r\n");

            Ingredient::query()
                ->where('is_verified', true)
                ->latest()
                ->with('components')
                ->each(function (Ingredient $ingredient) use ($handle): void {
                    $m = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];
                    $isBaseRecipe = $ingredient->isPreparedBaseIngredient();
                    $recipeComponents = '';
                    if ($isBaseRecipe) {
                        $recipeComponents = $ingredient->components
                            ->map(fn (Ingredient $child): string => sprintf(
                                '%d:%s',
                                (int) $child->id,
                                rtrim(rtrim(number_format((float) ($child->pivot->amount_grams ?? 0), 4, '.', ''), '0'), '.')
                            ))
                            ->implode(',');
                    }
                    $description = CsvSpreadsheetCellText::exportSingleLine($ingredient->description);
                    $instructions = CsvSpreadsheetCellText::exportMultilineAsEscapedNewlines($ingredient->instructions);
                    fputcsv($handle, [
                        $ingredient->name,
                        $ingredient->usda_food_category ?? '',
                        $ingredient->fdc_id ?? '',
                        $ingredient->calories,
                        $ingredient->protein,
                        $ingredient->carbs,
                        $ingredient->fat,
                        $ingredient->b6,
                        $ingredient->b9_folate,
                        $ingredient->b12,
                        $ingredient->iron,
                        $ingredient->magnesium,
                        $m['fiber'] ?? 0,
                        $m['sugar'] ?? 0,
                        $m['calcium'] ?? 0,
                        $m['potassium'] ?? 0,
                        $m['sodium'] ?? 0,
                        $m['zinc'] ?? 0,
                        $m['vitamin_c'] ?? 0,
                        $m['vitamin_a'] ?? 0,
                        $m['vitamin_e'] ?? 0,
                        $m['vitamin_d'] ?? 0,
                        $m['vitamin_k2'] ?? 0,
                        $ingredient->density ?? 1,
                        $isBaseRecipe ? 1 : 0,
                        $recipeComponents,
                        $description,
                        $instructions,
                        $ingredient->finished_weight_grams !== null && (float) $ingredient->finished_weight_grams > 0
                            ? $ingredient->finished_weight_grams
                            : '',
                        $ingredient->is_g6pd_trigger || IngredientG6pdSafety::canonicalNameIndicatesG6pdTrigger($ingredient->name) ? 1 : 0,
                    ], ',', '"', '\\', "\r\n");
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
