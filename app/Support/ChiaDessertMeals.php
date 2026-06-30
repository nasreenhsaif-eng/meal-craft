<?php

namespace App\Support;

use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\BalancedChiaDessertRecipeRefiner;

/**
 * Chia dessert deck meals (coconut or Greek yogurt base) — fixed kitchen portion; tier rebalance via dessert_calories.
 */
final class ChiaDessertMeals
{
    /**
     * @return list<string>
     */
    public static function mealNames(): array
    {
        return BalancedChiaDessertRecipeRefiner::refinedMealNames();
    }

    public static function isChiaDessert(Meal|string $meal): bool
    {
        $name = $meal instanceof Meal ? (string) $meal->name : $meal;

        return in_array($name, self::mealNames(), true);
    }

    public static function profileAvoidsDairy(CustomerProfile $profile): bool
    {
        $filters = MealFoodFilterCatalog::canonicalSlugsFromList(
            $profile->food_filters ?? $profile->allergies ?? [],
        );

        return in_array(MealFoodFilterCatalog::DAIRY, $filters, true);
    }

    public static function greekVariantMealName(string $coconutMealName): ?string
    {
        if (! self::isChiaDessert($coconutMealName)) {
            return null;
        }

        if (BalancedChiaDessertRecipeRefiner::isGreekYogurtVariantMealName($coconutMealName)) {
            return $coconutMealName;
        }

        if ($coconutMealName === 'Chia Pudding Smoothie') {
            return 'Greek Yogurt Chia Pudding Smoothie';
        }

        if (str_contains($coconutMealName, 'Chia Pudding')) {
            return str_replace('Chia Pudding', 'Greek Yogurt Chia Pudding', $coconutMealName);
        }

        return str_replace(' Chia', ' Greek Yogurt Chia', $coconutMealName);
    }

    public static function coconutVariantMealName(string $greekMealName): ?string
    {
        if (! self::isChiaDessert($greekMealName)) {
            return null;
        }

        if (! BalancedChiaDessertRecipeRefiner::isGreekYogurtVariantMealName($greekMealName)) {
            return $greekMealName;
        }

        if ($greekMealName === 'Greek Yogurt Chia Pudding Smoothie') {
            return 'Chia Pudding Smoothie';
        }

        if (str_contains($greekMealName, 'Greek Yogurt Chia Pudding')) {
            return str_replace('Greek Yogurt Chia Pudding', 'Chia Pudding', $greekMealName);
        }

        return str_replace(' Greek Yogurt Chia', ' Chia', $greekMealName);
    }

    public static function resolveMealNameForProfile(string $mealName, CustomerProfile $profile): string
    {
        if (! self::isChiaDessert($mealName)) {
            return $mealName;
        }

        if (self::profileAvoidsDairy($profile)) {
            return self::coconutVariantMealName($mealName) ?? $mealName;
        }

        return self::greekVariantMealName($mealName) ?? $mealName;
    }

    public static function resolveMealForProfile(Meal $meal, CustomerProfile $profile): Meal
    {
        $resolvedName = self::resolveMealNameForProfile((string) $meal->name, $profile);

        if ($resolvedName === $meal->name) {
            return $meal;
        }

        $resolved = Meal::queryForMealLibrary()
            ->where('name', $resolvedName)
            ->with('ingredients')
            ->first();

        return $resolved instanceof Meal ? $resolved : $meal;
    }
}
