<?php

namespace App\Support;

use App\Http\Controllers\Admin\IngredientLibraryCsvExportController;

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
        'vitamin_k2',
        'density',
        'is_base_recipe',
        'recipe_components',
        'description',
        'instructions',
        'finished_weight_grams',
        'g6pd_trigger',
    ];

    /**
     * Production meal master CSV columns (snake_case; 19 fields).
     * Rows upsert by {@code meal_name}. Legacy Title Case headers remain accepted by the importer.
     *
     * @var list<string>
     */
    public const MEAL_HEADERS = [
        'meal_name',
        'meal_type',
        'ingredients_string',
        'target_calories',
        'target_protein',
        'target_carbs',
        'target_fat',
        'batch_calories',
        'batch_protein',
        'batch_carbs',
        'batch_fat',
        'is_bulk',
        'servings_count',
        'meal_plan_tag',
        'cycle_phase',
        'safety_alerts',
        'image_url',
        'short_description',
        'instructions',
    ];

    /** Column index for {@code is_bulk} in {@see self::MEAL_HEADERS} (0-based). */
    public const MEAL_IS_BULK_COLUMN_INDEX = 11;

    /** Column index for {@code servings_count} in {@see self::MEAL_HEADERS} (0-based). */
    public const MEAL_SERVINGS_COUNT_COLUMN_INDEX = 12;

    public static function ingredientsPath(): string
    {
        $configured = config('menu-development.ingredients_csv_path');

        if (is_string($configured) && $configured !== '') {
            return self::resolveCsvPath($configured);
        }

        return database_path(self::INGREDIENTS_RELATIVE_PATH);
    }

    public static function mealsPath(): string
    {
        $configured = config('menu-development.meals_csv_path');

        if (is_string($configured) && $configured !== '') {
            return self::resolveCsvPath($configured);
        }

        return database_path(self::MEALS_RELATIVE_PATH);
    }

    private static function resolveCsvPath(string $path): string
    {
        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        return base_path($path);
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
