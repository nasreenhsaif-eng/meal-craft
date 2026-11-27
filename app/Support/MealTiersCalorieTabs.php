<?php

namespace App\Support;

use App\Enums\RecipeCategory;
use App\Models\Meal;

/**
 * Which calorie tabs a Meal Tiers Library meal uses.
 */
final class MealTiersCalorieTabs
{
    /**
     * @return list<int>
     */
    public static function mainTiers(): array
    {
        /** @var list<int> $tiers */
        $tiers = config('meal_tiers_library.main_calorie_tiers', [400, 500, 550, 600, 700, 800]);

        return array_values(array_map(intval(...), $tiers));
    }

    /**
     * @return list<int>
     */
    public static function breakfastTiers(): array
    {
        /** @var list<int> $tiers */
        $tiers = config('meal_tiers_library.breakfast_calorie_tiers', [300, 400, 500]);

        return array_values(array_map(intval(...), $tiers));
    }

    public static function isChiaPudding(Meal|string $meal): bool
    {
        $name = strtolower(trim($meal instanceof Meal ? (string) $meal->name : $meal));

        if ($name === '') {
            return false;
        }

        if (ChiaDessertMeals::isChiaDessert($meal)) {
            return true;
        }

        return str_contains($name, 'chia');
    }

    /**
     * @return list<int>
     */
    public static function forMeal(Meal $meal): array
    {
        if (self::isChiaPudding($meal)) {
            return [];
        }

        $category = $meal->category;

        if ($category === RecipeCategory::Breakfast) {
            return self::breakfastTiers();
        }

        if ($category === RecipeCategory::Meal || $category === RecipeCategory::MainSalad) {
            return self::mainTiers();
        }

        return [];
    }

    public static function usesTabs(Meal $meal): bool
    {
        return self::forMeal($meal) !== [];
    }

    public static function eggCountForBreakfastTier(int $calorieTier): ?int
    {
        $counts = config('meal_tiers_library.savory_egg_counts', []);

        if (! is_array($counts) || ! array_key_exists($calorieTier, $counts)) {
            return null;
        }

        return (int) $counts[$calorieTier];
    }

    public static function savoryEggMinimumForMeal(Meal $meal, int $calorieTier): ?int
    {
        if ($meal->category !== RecipeCategory::Breakfast || self::isChiaPudding($meal)) {
            return null;
        }

        if (! SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal) && ! self::nameLooksLikeEggBreakfast($meal)) {
            return self::eggCountForBreakfastTier($calorieTier);
        }

        return self::eggCountForBreakfastTier($calorieTier);
    }

    private static function nameLooksLikeEggBreakfast(Meal $meal): bool
    {
        $name = strtolower((string) $meal->name);

        return str_contains($name, 'egg')
            || str_contains($name, 'omelette')
            || str_contains($name, 'omelet')
            || str_contains($name, 'scramble')
            || str_contains($name, 'shakshuka');
    }
}
