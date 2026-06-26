<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Facades\Schema;

/**
 * Protects meal-library and ingredient-library rows saved through the admin UI
 * from being overwritten by automated refiners or menu CSV seed imports.
 */
final class MealLibraryEditGuard
{
    public static function shouldSkipMealRefinement(?Meal $meal): bool
    {
        return $meal !== null
            && Schema::hasColumn('meals', 'library_edited_at')
            && $meal->library_edited_at !== null;
    }

    public static function shouldSkipIngredientCsvImport(?Ingredient $ingredient): bool
    {
        return $ingredient !== null
            && Schema::hasColumn('ingredients', 'library_edited_at')
            && $ingredient->library_edited_at !== null;
    }

    public static function markMealEditedFromLibrary(Meal $meal): void
    {
        if (! Schema::hasColumn('meals', 'library_edited_at')) {
            return;
        }

        $meal->forceFill(['library_edited_at' => now()])->saveQuietly();
    }

    public static function markIngredientEditedFromLibrary(Ingredient $ingredient): void
    {
        if (! Schema::hasColumn('ingredients', 'library_edited_at')) {
            return;
        }

        $ingredient->forceFill(['library_edited_at' => now()])->saveQuietly();
    }
}
