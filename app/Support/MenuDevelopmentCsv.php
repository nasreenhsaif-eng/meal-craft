<?php

namespace App\Support;

use App\Http\Controllers\Admin\IngredientLibraryCsvExportController;
use App\Services\MealCraftMasterCsvExport;

/**
 * Paths and helpers for version-controlled menu master CSV files used by {@see Database\Seeders\MenuDevelopmentSeeder}.
 */
final class MenuDevelopmentCsv
{
    public const INGREDIENTS_RELATIVE_PATH = 'data/menu/ingredients.csv';

    public const MEALS_RELATIVE_PATH = 'data/menu/meals.csv';

    /**
     * Ingredient library columns (must match {@see IngredientLibraryCsvExportController}).
     *
     * @var list<string>
     */
    public const INGREDIENT_HEADERS = [
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
        'vitamin_k',
        'density',
        'is_base_recipe',
        'recipe_components',
        'description',
        'instructions',
        'finished_weight_grams',
        'g6pd_trigger',
    ];

    /**
     * Meal Craft master template columns (19 fields; must match {@see MealCraftMasterCsvExport::MEAL_CRAFT_CSV_TEMPLATE_HEADERS}).
     * Rows upsert by meal name ({@code Meal Name} / {@code Meal_Name}).
     *
     * @var list<string>
     */
    public const MEAL_HEADERS = MealCraftMasterCsvExport::MEAL_CRAFT_CSV_TEMPLATE_HEADERS;

    public static function ingredientsPath(): string
    {
        return database_path(self::INGREDIENTS_RELATIVE_PATH);
    }

    public static function mealsPath(): string
    {
        return database_path(self::MEALS_RELATIVE_PATH);
    }

    public static function hasDataRows(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }

        $headerRead = false;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                if (! $headerRead) {
                    $headerRead = true;

                    continue;
                }

                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') {
                        return true;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
