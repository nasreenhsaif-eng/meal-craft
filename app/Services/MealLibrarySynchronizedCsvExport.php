<?php

namespace App\Services;

use App\Enums\RecipeCategory;
use App\Models\Meal;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Full meal-library CSV export aligned with {@see MealCsvLibraryImportService} bulk import columns.
 */
final class MealLibrarySynchronizedCsvExport
{
    /**
     * Category string for CSV rows allowed by bulk import ({@see MealCsvLibraryImportService::mealLibraryCsvAllowedCategories()}).
     */
    public static function categoryForBulkImport(?RecipeCategory $category): string
    {
        if ($category === null) {
            return RecipeCategory::Meal->value;
        }

        if ($category === RecipeCategory::MainSalad) {
            return RecipeCategory::Meal->value;
        }

        return $category->value;
    }

    /**
     * @param  resource  $handle
     */
    public function writeFullLibraryToStream($handle): void
    {
        fputcsv($handle, MealCsvLibraryImportService::LIBRARY_CSV_HEADERS, ',', '"', '\\');

        Meal::queryForMealLibrary()
            ->with(['ingredients' => function (BelongsToMany $query): void {
                $query->orderBy('ingredients.name');
            }])
            ->orderBy('name')
            ->each(function (Meal $meal) use ($handle): void {
                $nutrition = $meal->ingredients->isEmpty()
                    ? null
                    : RecipeNutritionCalculator::fromMeal($meal);

                $totalCalories = $nutrition !== null
                    ? (float) ($nutrition['calories'] ?? 0)
                    : (float) ($meal->total_calories ?? 0);

                fputcsv($handle, [
                    $meal->name,
                    self::categoryForBulkImport($meal->category),
                    $this->ingredientQuantitiesCell($meal),
                    $this->syncInstructionsCell($meal),
                    $this->syncShortDescriptionCell($meal),
                    $this->mealPlanTagsForLibraryCell($meal),
                    $this->cyclePhasesForLibraryCell($meal),
                    round($totalCalories, 1),
                    (string) ($meal->image_path ?? ''),
                ], ',', '"', '\\');
            });
    }

    public function ingredientQuantitiesCell(Meal $meal): string
    {
        if ($meal->ingredients->isEmpty()) {
            return '';
        }

        $parts = [];
        foreach ($meal->ingredients as $ingredient) {
            $grams = $this->pivotGrams($ingredient->pivot);
            if ($grams <= 0) {
                continue;
            }

            $parts[] = trim($ingredient->name).':'.$this->formatGrams($grams);
        }

        return implode(' | ', $parts);
    }

    private function mealPlanTagsForLibraryCell(Meal $meal): string
    {
        $labels = [];
        if (is_array($meal->meal_plan_tags)) {
            foreach ($meal->meal_plan_tags as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $labels[] = trim($t);
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

    private function cyclePhasesForLibraryCell(Meal $meal): string
    {
        return MealCraftMasterCsvExport::canonicalCyclePhaseLabels($meal);
    }

    private function syncInstructionsCell(Meal $meal): string
    {
        $preferred = trim((string) ($meal->instructions ?? ''));

        return $preferred !== '' ? $preferred : trim((string) ($meal->description ?? ''));
    }

    private function syncShortDescriptionCell(Meal $meal): string
    {
        $preferred = trim((string) ($meal->short_description ?? ''));

        return $preferred !== '' ? $preferred : trim((string) ($meal->highlight ?? ''));
    }

    /**
     * @param  Pivot|null  $pivot
     */
    private function pivotGrams(?object $pivot): float
    {
        if ($pivot === null) {
            return 0.0;
        }

        $gramsRaw = $pivot->amount_grams ?? null;
        if ($gramsRaw !== null && $gramsRaw !== '' && is_numeric($gramsRaw) && (float) $gramsRaw > 0) {
            return (float) $gramsRaw;
        }

        $amount = $pivot->amount ?? null;

        return ($amount !== null && $amount !== '' && is_numeric($amount)) ? (float) $amount : 0.0;
    }

    private function formatGrams(float $grams): string
    {
        $formatted = rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
