<?php

namespace App\Services;

use App\Enums\RecipeCategory;
use App\Models\Meal;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Full meal-library CSV export aligned with {@see MealCsvLibraryImportService} bulk import columns.
 */
final class MealLibrarySynchronizedCsvExport
{
    public const HEADERS = [
        'Meal_Name',
        'Category',
        'Ingredient_Quantities',
        'Instructions',
        'Description_Highlight',
    ];

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
        fputcsv($handle, self::HEADERS, ',', '"', '\\');

        Meal::query()
            ->with(['ingredients' => function (BelongsToMany $query): void {
                $query->orderBy('ingredients.name');
            }])
            ->orderBy('name')
            ->each(function (Meal $meal) use ($handle): void {
                fputcsv($handle, [
                    $meal->name,
                    self::categoryForBulkImport($meal->category),
                    $this->ingredientQuantitiesCell($meal),
                    $meal->description ?? '',
                    $meal->highlight ?? '',
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

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Pivot|null  $pivot
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
