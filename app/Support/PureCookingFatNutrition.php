<?php

namespace App\Support;

use App\Enums\RecipeAmountUnit;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeIngredientUnitConverter;

/**
 * Hard boundaries for pure cooking oils and solid cooking fats so volume → mass
 * and fat/calorie rollups cannot undercount the true lipid payload.
 *
 * Vegetable oil: 1 ml ≈ 0.92 g ≈ 0.92 g fat ≈ 8.3 kcal (884 kcal / 100 g).
 */
final class PureCookingFatNutrition
{
    /** Typical vegetable-oil density (g/ml). */
    public const OIL_DENSITY_G_PER_ML = 0.92;

    /** Soft butter / ghee melted density (g/ml) when measured by volume. */
    public const SOLID_FAT_DENSITY_G_PER_ML = 0.91;

    /** Pure triglyceride energy density (kcal per g fat). */
    public const KCAL_PER_G_FAT = 9.0;

    /** Canonical oil macros per 100 g (USDA-style vegetable oil). */
    public const OIL_FAT_PER_100G = 100.0;

    public const OIL_CALORIES_PER_100G = 884.0;

    /** Per-serving sauté pour above this milliliters is treated as absurd for a skillet dish. */
    public const ABSURD_SAUTE_OIL_ML = 15.0;

    /** Target band after volumetric plausibility reduction (1–2 tsp). */
    public const REALISTIC_SAUTE_OIL_ML_MIN = 5.0;

    public const REALISTIC_SAUTE_OIL_ML_MAX = 10.0;

    /** Densities outside this band are treated as corrupt for pure cooking fats. */
    private const PLAUSIBLE_DENSITY_MIN = 0.85;

    private const PLAUSIBLE_DENSITY_MAX = 0.98;

    /**
     * Pure cooking oils and solid kitchen fats (not nut butters, coconut butter, or bases).
     */
    public static function isPureCookingFat(Ingredient|string $ingredient): bool
    {
        $name = strtolower(trim($ingredient instanceof Ingredient ? $ingredient->name : $ingredient));

        if ($name === '' || str_contains($name, '(base)')) {
            return false;
        }

        foreach ([
            'peanut butter',
            'almond butter',
            'cashew butter',
            'coconut butter',
            'butter bean',
            'butternut',
            'cocoa butter',
            'shea butter',
            'tahini',
        ] as $excluded) {
            if (str_contains($name, $excluded)) {
                return false;
            }
        }

        if (preg_match('/\boil\b/', $name) === 1) {
            return true;
        }

        if (str_contains($name, 'ghee')) {
            return true;
        }

        // Plain butter / grass-fed butter — not nut butters (excluded above).
        return (bool) preg_match('/\bbutter\b/', $name);
    }

    public static function isVegetableOil(Ingredient|string $ingredient): bool
    {
        if (! self::isPureCookingFat($ingredient)) {
            return false;
        }

        $name = strtolower(trim($ingredient instanceof Ingredient ? $ingredient->name : $ingredient));

        return (bool) preg_match('/\boil\b/', $name);
    }

    /**
     * Density (g/ml) for volume → mass. Pure fats never use corrupt library densities.
     */
    public static function densityGramsPerMl(Ingredient $ingredient): float
    {
        if (! self::isPureCookingFat($ingredient)) {
            $density = (float) ($ingredient->density ?? 0);

            return $density > 0 ? $density : 1.0;
        }

        $stored = (float) ($ingredient->density ?? 0);

        if ($stored >= self::PLAUSIBLE_DENSITY_MIN && $stored <= self::PLAUSIBLE_DENSITY_MAX) {
            return $stored;
        }

        return self::isVegetableOil($ingredient)
            ? self::OIL_DENSITY_G_PER_ML
            : self::SOLID_FAT_DENSITY_G_PER_ML;
    }

    /**
     * @return array{calories: float, protein: float, carbs: float, fat: float}
     */
    public static function canonicalPer100gMacros(Ingredient $ingredient): array
    {
        $storedFat = (float) ($ingredient->fat ?? 0);
        $storedCalories = (float) ($ingredient->calories ?? 0);
        $storedProtein = (float) ($ingredient->protein ?? 0);
        $storedCarbs = (float) ($ingredient->carbs ?? 0);

        if (! self::isPureCookingFat($ingredient)) {
            return [
                'calories' => $storedCalories,
                'protein' => $storedProtein,
                'carbs' => $storedCarbs,
                'fat' => $storedFat,
            ];
        }

        if (self::isVegetableOil($ingredient)) {
            return [
                'calories' => self::OIL_CALORIES_PER_100G,
                'protein' => 0.0,
                'carbs' => 0.0,
                'fat' => self::OIL_FAT_PER_100G,
            ];
        }

        // Butter / ghee: keep library macros when they look like real lipid foods; otherwise floor.
        $fat = $storedFat >= 80.0 ? $storedFat : (str_contains(strtolower($ingredient->name), 'ghee') ? 99.5 : 81.1);
        $calories = $storedCalories >= 700.0
            ? $storedCalories
            : round($fat * self::KCAL_PER_G_FAT, 1);

        return [
            'calories' => $calories,
            'protein' => max(0.0, $storedProtein),
            'carbs' => max(0.0, $storedCarbs),
            'fat' => $fat,
        ];
    }

    /**
     * Fat grams contributed by a pure cooking fat portion (never below mass × coefficient).
     */
    public static function fatGramsForMass(Ingredient $ingredient, float $grams): float
    {
        $grams = max(0.0, $grams);

        if ($grams <= 0 || ! self::isPureCookingFat($ingredient)) {
            return 0.0;
        }

        $per100 = self::canonicalPer100gMacros($ingredient);

        return ($grams / 100.0) * $per100['fat'];
    }

    public static function caloriesForMass(Ingredient $ingredient, float $grams): float
    {
        $grams = max(0.0, $grams);

        if ($grams <= 0 || ! self::isPureCookingFat($ingredient)) {
            return 0.0;
        }

        $per100 = self::canonicalPer100gMacros($ingredient);

        return ($grams / 100.0) * $per100['calories'];
    }

    public static function millilitersFromGrams(Ingredient $ingredient, float $grams): float
    {
        $grams = max(0.0, $grams);
        $density = self::densityGramsPerMl($ingredient);

        return $density > 0 ? $grams / $density : $grams;
    }

    public static function gramsFromMilliliters(Ingredient $ingredient, float $milliliters): float
    {
        return max(0.0, $milliliters) * self::densityGramsPerMl($ingredient);
    }

    /**
     * Resolve mass for a recipe row: volume uses pure-fat density; prefer stored grams when present
     * so corrupt densities cannot erase an intentional lipid payload.
     *
     * @param  array{amount?: float|int|string|null, unit?: string|null, amount_grams?: float|int|string|null}  $row
     */
    public static function resolvedGramsForRow(array $row, Ingredient $ingredient): float
    {
        $amountGrams = isset($row['amount_grams']) && is_numeric($row['amount_grams'])
            ? max(0.0, (float) $row['amount_grams'])
            : 0.0;

        $hasAmountUnit = array_key_exists('amount', $row)
            && array_key_exists('unit', $row)
            && $row['unit'] !== null
            && (string) $row['unit'] !== ''
            && is_numeric($row['amount'] ?? null);

        if (! $hasAmountUnit) {
            return $amountGrams;
        }

        $amount = max(0.0, (float) $row['amount']);
        $unit = (string) $row['unit'];
        $density = self::densityGramsPerMl($ingredient);
        $fromVolumeOrMass = RecipeIngredientUnitConverter::toGrams($amount, $unit, $density);

        if (! self::isPureCookingFat($ingredient)) {
            return $fromVolumeOrMass;
        }

        $enum = RecipeAmountUnit::tryFrom(strtolower(trim($unit)));

        // Mass units: trust the explicit amount (and amount_grams when higher / already snapped).
        if ($enum !== null && ! $enum->usesDensity()) {
            return max($fromVolumeOrMass, $amountGrams);
        }

        // Volume units: never undercount vs stored grams; enforce oil density math.
        if ($amountGrams > 0) {
            return max($fromVolumeOrMass, $amountGrams);
        }

        return $fromVolumeOrMass;
    }

    /**
     * Scale absurd per-serving sauté oil down to a cookable pour (5–10 ml).
     * Bulk batch grams are scaled proportionally. Intentional high-fat payloads are left alone.
     */
    public static function applyVolumetricPlausibility(
        Meal $meal,
        Ingredient $ingredient,
        float $grams,
        bool $intentionalCalorieTarget = false,
    ): float {
        $grams = max(0.0, $grams);

        if ($grams <= 0 || $intentionalCalorieTarget || ! self::isVegetableOil($ingredient)) {
            return $grams;
        }

        $servings = 1.0;
        if ($meal->is_bulk && (float) ($meal->servings_count ?? 0) > 0) {
            $servings = (float) $meal->servings_count;
        }

        $perServingGrams = $grams / $servings;
        $perServingMl = self::millilitersFromGrams($ingredient, $perServingGrams);

        if ($perServingMl <= self::ABSURD_SAUTE_OIL_ML) {
            return round($grams, 4);
        }

        $targetMl = self::REALISTIC_SAUTE_OIL_ML_MAX;
        // Reduction loop: step down toward the realistic band.
        while ($perServingMl > self::REALISTIC_SAUTE_OIL_ML_MAX + 0.05) {
            $perServingMl = max(self::REALISTIC_SAUTE_OIL_ML_MIN, $perServingMl - 5.0);
        }

        $targetMl = min(self::REALISTIC_SAUTE_OIL_ML_MAX, max(self::REALISTIC_SAUTE_OIL_ML_MIN, $perServingMl));
        $targetPerServingGrams = self::gramsFromMilliliters($ingredient, $targetMl);

        return round($targetPerServingGrams * $servings, 4);
    }

    /**
     * Ensure meal fat/calories are never below the sum of pure-fat ingredient contributions.
     *
     * @param  array<string, float>  $nutrition
     * @param  list<array{ingredient: Ingredient, grams: float}>  $pureFatPortions
     * @return array<string, float>
     */
    public static function enforceFatFloor(array $nutrition, array $pureFatPortions): array
    {
        $minFat = 0.0;
        $minCaloriesFromFat = 0.0;

        foreach ($pureFatPortions as $portion) {
            $ingredient = $portion['ingredient'];
            $grams = (float) ($portion['grams'] ?? 0);

            if (! $ingredient instanceof Ingredient || $grams <= 0) {
                continue;
            }

            $minFat += self::fatGramsForMass($ingredient, $grams);
            $minCaloriesFromFat += self::caloriesForMass($ingredient, $grams);
        }

        if ($minFat <= 0) {
            return $nutrition;
        }

        $nutrition['fat'] = max((float) ($nutrition['fat'] ?? 0), $minFat);

        // Calories must cover at least the pure-fat energy payload (still unrounded).
        $nutrition['calories'] = max((float) ($nutrition['calories'] ?? 0), $minCaloriesFromFat);

        return $nutrition;
    }
}
