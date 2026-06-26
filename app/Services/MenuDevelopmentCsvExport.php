<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\CsvSpreadsheetCellText;
use App\Support\IngredientG6pdSafety;
use App\Support\MealImagePath;
use App\Support\MenuDevelopmentCsv;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * Exports live meal and ingredient library rows into version-controlled menu master CSV files.
 */
final class MenuDevelopmentCsvExport
{
    public function __construct(
        private MealLibrarySynchronizedCsvExport $mealLibrarySynchronizedCsvExport,
    ) {}

    /**
     * @return array{meals: int, ingredients: int}
     */
    public function exportToDefaultPaths(): array
    {
        return [
            'ingredients' => $this->exportIngredientsToPath(MenuDevelopmentCsv::ingredientsPath()),
            'meals' => $this->exportMealsToPath(MenuDevelopmentCsv::mealsPath()),
        ];
    }

    public function exportIngredientsToPath(string $path): int
    {
        $this->ensureParentDirectoryExists($path);

        return $this->writeCsvAtomically($path, function ($handle): int {
            fputcsv($handle, MenuDevelopmentCsv::INGREDIENT_HEADERS, ',', '"', '\\');

            $count = 0;

            Ingredient::query()
                ->orderBy('name')
                ->with('components')
                ->each(function (Ingredient $ingredient) use ($handle, &$count): void {
                    fputcsv($handle, $this->ingredientRow($ingredient), ',', '"', '\\');
                    $count++;
                });

            return $count;
        });
    }

    public function exportMealsToPath(string $path): int
    {
        $this->ensureParentDirectoryExists($path);

        return $this->writeCsvAtomically($path, function ($handle): int {
            fputcsv($handle, MenuDevelopmentCsv::MEAL_HEADERS, ',', '"', '\\');

            $count = 0;

            Meal::queryForMealLibrary()
                ->with(['ingredients' => function (BelongsToMany $query): void {
                    $query->orderBy('ingredients.name');
                }])
                ->each(function (Meal $meal) use ($handle, &$count): void {
                    fputcsv($handle, $this->mealRow($meal), ',', '"', '\\');
                    $count++;
                });

            return $count;
        });
    }

    /**
     * @return list<string|int|float>
     */
    private function ingredientRow(Ingredient $ingredient): array
    {
        $micronutrients = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];
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

        return [
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
            $micronutrients['fiber'] ?? 0,
            $micronutrients['sugar'] ?? 0,
            $micronutrients['calcium'] ?? 0,
            $micronutrients['potassium'] ?? 0,
            $micronutrients['sodium'] ?? 0,
            $micronutrients['zinc'] ?? 0,
            $micronutrients['vitamin_c'] ?? 0,
            $micronutrients['vitamin_a'] ?? 0,
            $micronutrients['vitamin_e'] ?? 0,
            $micronutrients['vitamin_d'] ?? 0,
            $micronutrients['vitamin_k2'] ?? 0,
            $ingredient->density ?? 1,
            $isBaseRecipe ? 1 : 0,
            $recipeComponents,
            CsvSpreadsheetCellText::exportSingleLine($ingredient->description),
            CsvSpreadsheetCellText::exportMultilineAsEscapedNewlines($ingredient->instructions),
            $ingredient->finished_weight_grams !== null && (float) $ingredient->finished_weight_grams > 0
                ? rtrim(rtrim(number_format((float) $ingredient->finished_weight_grams, 4, '.', ''), '0'), '.')
                : '',
            $ingredient->is_g6pd_trigger || IngredientG6pdSafety::canonicalNameIndicatesG6pdTrigger($ingredient->name) ? 1 : 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function mealRow(Meal $meal): array
    {
        $batchNutrition = $this->batchNutritionForMeal($meal);

        return [
            $meal->name,
            MealLibrarySynchronizedCsvExport::categoryForBulkImport($meal->category),
            $this->mealLibrarySynchronizedCsvExport->ingredientQuantitiesCell($meal),
            $this->formatOptionalFloat($meal->target_calories),
            $this->formatOptionalFloat($meal->target_protein),
            $this->formatOptionalFloat($meal->target_carbs),
            $this->formatOptionalFloat($meal->target_fat),
            $this->formatOptionalFloat($batchNutrition['calories'] ?? null),
            $this->formatOptionalFloat($batchNutrition['protein'] ?? null),
            $this->formatOptionalFloat($batchNutrition['carbs'] ?? null),
            $this->formatOptionalFloat($batchNutrition['fat'] ?? null),
            $meal->is_bulk ? 'true' : 'false',
            $this->formatOptionalFloat($meal->servings_count),
            $this->mealPlanTagCell($meal),
            MealCraftMasterCsvExport::canonicalCyclePhaseLabels($meal),
            $this->safetyAlertsCell($meal),
            $this->imageUrlCell($meal),
            $this->shortDescriptionCell($meal),
            $this->instructionsCell($meal),
        ];
    }

    /**
     * @return array{calories?: float, protein?: float, carbs?: float, fat?: float}
     */
    private function batchNutritionForMeal(Meal $meal): array
    {
        if (! $meal->is_bulk) {
            return [];
        }

        if ($meal->ingredients->isNotEmpty()) {
            $nutrition = RecipeNutritionCalculator::fromMeal($meal);

            return [
                'calories' => (float) ($nutrition['calories'] ?? 0),
                'protein' => (float) ($nutrition['protein'] ?? 0),
                'carbs' => (float) ($nutrition['carbs'] ?? 0),
                'fat' => (float) ($nutrition['fat'] ?? 0),
            ];
        }

        $servings = (float) ($meal->servings_count ?? 0);
        if ($servings <= 0) {
            return [];
        }

        return [
            'calories' => (float) ($meal->total_calories ?? 0) * $servings,
            'protein' => (float) ($meal->total_protein ?? 0) * $servings,
            'carbs' => (float) ($meal->total_carbs ?? 0) * $servings,
            'fat' => (float) ($meal->total_fat ?? 0) * $servings,
        ];
    }

    private function mealPlanTagCell(Meal $meal): string
    {
        $labels = [];
        if (is_array($meal->meal_plan_tags)) {
            foreach ($meal->meal_plan_tags as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $labels[] = trim($tag);
                }
            }
        }

        if ($labels === []) {
            $single = is_string($meal->meal_plan_tag ?? null) ? trim((string) $meal->meal_plan_tag) : '';
            if ($single !== '') {
                $labels[] = $single;
            }
        }

        $labels = array_values(array_unique($labels));
        sort($labels);

        return implode(', ', $labels);
    }

    private function safetyAlertsCell(Meal $meal): string
    {
        $tags = is_array($meal->safety_alert_tags) ? $meal->safety_alert_tags : [];
        $labels = [];

        foreach ($tags as $tag) {
            $label = is_string($tag) ? trim($tag) : '';
            if ($label !== '') {
                $labels[$label] = true;
            }
        }

        $out = array_keys($labels);
        sort($out);

        return implode(', ', $out);
    }

    private function imageUrlCell(Meal $meal): string
    {
        return MealImagePath::normalizeForDatabase($meal->image_path) ?? '';
    }

    private function shortDescriptionCell(Meal $meal): string
    {
        $preferred = trim((string) ($meal->short_description ?? ''));

        return $preferred !== '' ? $preferred : trim((string) ($meal->highlight ?? ''));
    }

    private function instructionsCell(Meal $meal): string
    {
        $preferred = trim((string) ($meal->instructions ?? ''));

        return $preferred !== '' ? $preferred : trim((string) ($meal->description ?? ''));
    }

    private function formatOptionalFloat(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function ensureParentDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if ($directory === '' || is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("Could not create directory: {$directory}");
        }
    }

    /**
     * @param  callable(resource): int  $writer
     */
    private function writeCsvAtomically(string $path, callable $writer): int
    {
        $temporaryPath = $path.'.tmp';

        $handle = fopen($temporaryPath, 'wb');
        if ($handle === false) {
            throw new InvalidArgumentException("Could not open temporary CSV for writing: {$temporaryPath}");
        }

        try {
            $count = $writer($handle);
        } finally {
            fclose($handle);
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new InvalidArgumentException("Could not write CSV to: {$path}");
        }

        return $count;
    }
}
