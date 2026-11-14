<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Chicken / fish / beef family for Meal Tiers Library plate structures. Liver uses chicken buckets.
 */
final class MealTiersProteinFamily
{
    public const Chicken = 'chicken';

    public const Fish = 'fish';

    public const Beef = 'beef';

    /**
     * @return self::Chicken|self::Fish|self::Beef|null
     */
    public static function forMeal(Meal $meal): ?string
    {
        if (! $meal->exists) {
            return self::fromMealName((string) $meal->name);
        }

        $meal->loadMissing('ingredients');

        foreach ($meal->ingredients as $ingredient) {
            $family = self::forIngredient($ingredient, $meal);

            if ($family !== null) {
                return $family;
            }
        }

        return self::fromMealName((string) $meal->name);
    }

    /**
     * @return self::Chicken|self::Fish|self::Beef|null
     */
    public static function forIngredient(Ingredient $ingredient, ?Meal $meal = null): ?string
    {
        $name = strtolower(trim($ingredient->name));
        $mealName = strtolower(trim((string) ($meal?->name ?? '')));

        if ($name === '' || ! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal?->name)) {
            return self::fromLooseName($name, $mealName);
        }

        if (str_contains($name, 'liver') || str_contains($mealName, 'liver')) {
            return self::Chicken;
        }

        return self::fromLooseName($name, $mealName);
    }

    /**
     * Chicken / fish / beef mains share the 1000 ml kitchen pack-out scaler.
     *
     * @param  self::Chicken|self::Fish|self::Beef|null  $family
     */
    public static function usesKitchenPackout(?string $family): bool
    {
        return in_array($family, [self::Chicken, self::Fish, self::Beef], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function bucketsForMeal(Meal $meal, int $calorieTier): ?array
    {
        $family = self::forMeal($meal);

        if ($family === null) {
            return null;
        }

        $families = config('meal_tiers_library.families', []);
        $row = $families[$family][$calorieTier] ?? null;

        return is_array($row) ? $row : null;
    }

    public static function proteinGramsForTier(int $calorieTier): ?float
    {
        $grams = config('meal_tiers_library.protein_grams', []);

        if (! is_array($grams) || ! array_key_exists($calorieTier, $grams)) {
            return null;
        }

        return (float) $grams[$calorieTier];
    }

    /**
     * @return self::Chicken|self::Fish|self::Beef|null
     */
    public static function fromMealName(string $mealName): ?string
    {
        return self::fromLooseName('', strtolower(trim($mealName)));
    }

    /**
     * @return self::Chicken|self::Fish|self::Beef|null
     */
    private static function fromLooseName(string $ingredientName, string $mealName): ?string
    {
        $haystack = trim($ingredientName.' '.$mealName);

        if ($haystack === '') {
            return null;
        }

        if (str_contains($haystack, 'liver')) {
            return self::Chicken;
        }

        foreach (['salmon', 'hamour', 'shrimp', 'prawn', 'tuna', 'sardine', 'mackerel', 'fish'] as $needle) {
            if (str_contains($haystack, $needle) && $needle !== 'fish sauce') {
                if (str_contains($haystack, 'fish sauce')) {
                    continue;
                }

                return self::Fish;
            }
        }

        foreach (['chicken'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return self::Chicken;
            }
        }

        foreach (['beef', 'brisket', 'ribeye', 'sirloin', 'topside', 'meatball'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return self::Beef;
            }
        }

        return null;
    }

    public static function roleBucket(MealScalingRoleEnum $role): string
    {
        return match ($role) {
            MealScalingRoleEnum::Protein => 'protein',
            MealScalingRoleEnum::Carb, MealScalingRoleEnum::Vegetable => 'carbs_veg',
            MealScalingRoleEnum::Fat, MealScalingRoleEnum::Sauce => 'sauce_fat',
            MealScalingRoleEnum::HerbSpice => 'seasoning',
            default => 'other',
        };
    }
}
