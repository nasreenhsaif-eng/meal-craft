<?php

namespace App\Support;

use App\Enums\CyclePhase;
use App\Enums\DietTag;
use App\Enums\RecipeCategory;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\MealCsvLibraryImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Global Inertia props for Meal Craft admin library workflows (CSV import/export, bulk actions, taxonomy).
 *
 * Shared from {@see HandleInertiaRequests} for authenticated users.
 */
final class MealCraftInertiaSharedData
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequest(Request $request): array
    {
        if ($request->user() === null) {
            return [];
        }

        return [
            'urls' => self::urls(),
            'constants' => self::constants(),
            'taxonomy' => self::taxonomy(),
            'csv' => self::csv(),
            'notices' => [
                'mealLibrarySchema' => self::mealLibrarySchemaNotice(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function urls(): array
    {
        return [
            'ingredientLibrary' => [
                'index' => route('admin.ingredient-library'),
                'store' => route('admin.ingredient-library.store'),
                'baseStore' => route('admin.ingredient-library.base-ingredient.store'),
                'importCsv' => route('admin.ingredient-library.import-csv'),
                'exportCsv' => route('admin.ingredient-library.export-csv'),
                'bulkDestroy' => route('admin.ingredient-library.bulk-destroy'),
                'template' => asset('templates/ingredients-library-template.csv'),
            ],
            'mealLibrary' => [
                'index' => route('admin.meal-library'),
                'store' => route('admin.meal-library.store'),
                'bulkDestroy' => route('admin.meal-library.bulk-destroy'),
                'reorder' => route('admin.meal-library.reorder'),
                'mealCraftTemplate' => route('admin.meal-library.csv-template'),
                'importCsv' => route('admin.meal-library.import-csv'),
                'exportCsv' => route('meals.library.export-csv'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function constants(): array
    {
        return [
            'missingPhotoPlaceholder' => MealImagePath::MISSING_PHOTO_PLACEHOLDER,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function taxonomy(): array
    {
        return [
            'mealCategories' => array_map(
                static fn (RecipeCategory $category): string => $category->value,
                MealCsvLibraryImportService::mealLibraryCsvAllowedCategories(),
            ),
            'mealPlanTags' => MealLibraryTaxonomy::MEAL_PLAN_TAGS,
            'dietaryTags' => MealLibraryTaxonomy::DIETARY_TAGS,
            'dietTags' => DietTag::toDropdownOptions(),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
            'preparedIngredientCategories' => IngredientLibraryCategory::preparedLabels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function csv(): array
    {
        return [
            'masterMealHeaders' => MealCsvHeaderCatalog::MASTER_HEADERS,
            'libraryMealHeaders' => MealCsvLibraryImportService::LIBRARY_CSV_HEADERS,
        ];
    }

    public static function mealLibrarySchemaNotice(): ?string
    {
        try {
            $ready = Schema::hasTable('meals')
                && Schema::hasTable('ingredients')
                && Schema::hasColumn('meals', 'library_sort_order')
                && Schema::hasColumn('meals', 'meal_plan_tags')
                && Schema::hasColumn('meals', 'cycle_phases')
                && Schema::hasColumn('meals', 'safety_alert_tags')
                && Schema::hasColumn('meals', 'nutrition_aggregates_synced')
                && Schema::hasColumn('ingredients', 'common_allergens')
                && Schema::hasColumn('ingredients', 'is_g6pd_trigger');
        } catch (\Throwable) {
            $ready = false;
        }

        if ($ready) {
            return null;
        }

        return (string) __('Database update required: run `php artisan migrate` in the project root, then refresh this page.');
    }
}
