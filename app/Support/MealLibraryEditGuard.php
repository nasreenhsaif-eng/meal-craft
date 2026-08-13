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

    /**
     * True when a meal still has a primary meat/fish line below half the standard portion
     * (e.g. sodium refiner collapse to 1–2 g). Allows automated heal even if the meal is UI-locked.
     */
    public static function mealHasCollapsedPrimaryMeat(?Meal $meal): bool
    {
        if ($meal === null) {
            return false;
        }

        $meal->loadMissing('ingredients');

        $floor = StandardMeatPortion::GRAMS * 0.5;

        foreach ($meal->ingredients as $ingredient) {
            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            if (StandardMeatPortion::isLiverBlendIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);

            if ($grams > 0.0 && $grams < $floor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collapsed primary meat/fish, or a meat-named meal with no primary protein line at all.
     * Use for recipe restorers that may rewrite locked meals; prefer
     * {@see mealHasCollapsedPrimaryMeat()} when only portion heal is intended.
     */
    public static function mealHasCollapsedOrMissingPrimaryMeat(?Meal $meal): bool
    {
        if ($meal === null) {
            return false;
        }

        if (self::mealHasCollapsedPrimaryMeat($meal)) {
            return true;
        }

        $meal->loadMissing('ingredients');

        foreach ($meal->ingredients as $ingredient) {
            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            if (StandardMeatPortion::isLiverBlendIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);

            if ($grams > 0.0) {
                return false;
            }
        }

        return self::mealNameExpectsPrimaryMeat((string) $meal->name);
    }

    public static function mealNameExpectsPrimaryMeat(string $mealName): bool
    {
        $name = strtolower(trim($mealName));

        foreach (['chicken', 'beef', 'salmon', 'shrimp', 'prawn', 'tuna', 'sardine', 'liver', 'tandoori', 'meatball'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
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
