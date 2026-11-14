<?php

namespace App\Support;

use App\Models\Meal;

/**
 * Kitchen-realistic minimum grams for visible plate vegetables on main meals.
 */
final class MainMealVegetablePortionFloor
{
    /**
     * @param  array<string, float>  $ingredientGrams
     * @return array<string, float>
     */
    public static function applyFloors(Meal $meal, array $ingredientGrams): array
    {
        $adjusted = $ingredientGrams;

        foreach ($ingredientGrams as $ingredientName => $grams) {
            $minimum = self::minimumGrams($meal, $ingredientName);

            if ($minimum === null) {
                continue;
            }

            if ($grams < $minimum) {
                $adjusted[$ingredientName] = $minimum;
            }
        }

        return $adjusted;
    }

    public static function minimumGrams(Meal $meal, string $ingredientName): ?float
    {
        if (! self::isNamedPlateComponent($meal, $ingredientName)) {
            return null;
        }

        $default = (float) config('customer_nutrition.main_meal_plate_vegetable_minimum_grams', 40.0);
        $canonical = self::canonicalPlateVegetableGrams($meal->name, $ingredientName);

        if ($canonical !== null && $canonical > 0) {
            return max($default, $canonical);
        }

        return $default;
    }

    public static function isNamedPlateComponent(Meal $meal, string $ingredientName): bool
    {
        if (! self::isPlateVegetableIngredient($ingredientName)) {
            return false;
        }

        $instructions = strtolower(trim((string) ($meal->instructions ?? '')));

        if ($instructions === '') {
            return self::canonicalPlateVegetableGrams($meal->name, $ingredientName) !== null;
        }

        foreach (self::searchTermsForIngredient($ingredientName) as $term) {
            if (str_contains($instructions, strtolower($term))) {
                return true;
            }
        }

        return false;
    }

    public static function isPlateVegetableIngredient(string $ingredientName): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('customer_nutrition.main_meal_plate_vegetable_ingredients', []);

        return in_array($ingredientName, $allowed, true);
    }

    public static function canonicalPlateVegetableGrams(string $mealName, string $ingredientName): ?float
    {
        /** @var array<string, array<string, float>> $configured */
        $configured = config('customer_nutrition.main_meal_plate_vegetable_canonical_grams', []);

        if (isset($configured[$mealName][$ingredientName])) {
            return (float) $configured[$mealName][$ingredientName];
        }

        $override = MealLibraryRefinerOverrides::all()[$mealName]['ingredients'][$ingredientName] ?? null;

        if (is_numeric($override) && (float) $override >= (float) config('customer_nutrition.main_meal_plate_vegetable_minimum_grams', 40.0)) {
            return (float) $override;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function searchTermsForIngredient(string $ingredientName): array
    {
        return match ($ingredientName) {
            'Broccoli' => ['broccoli'],
            'Bok Choy' => ['bok choy', 'pak choi'],
            'Green Beans' => ['green beans', 'green bean'],
            'Sweet Potato' => ['sweet potato'],
            'Bell Pepper (Red)' => ['bell pepper', 'pepper halves', 'stuffed pepper'],
            default => [strtolower(explode(' ', $ingredientName)[0])],
        };
    }
}
