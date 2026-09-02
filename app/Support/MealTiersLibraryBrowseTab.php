<?php

namespace App\Support;

use App\Enums\RecipeCategory;
use App\Models\Meal;

/**
 * Browse filters for Meal Library and Meal Tiers Library.
 */
final class MealTiersLibraryBrowseTab
{
    public const Chicken = 'chicken';

    public const Liver = 'liver';

    public const Salmon = 'salmon';

    public const Dessert = 'dessert';

    public const SideSalad = 'side_salad';

    public const Beef = 'beef';

    public const Soup = 'soup';

    public const Breakfast = 'breakfast';

    public const MainSalad = 'main_salad';

    public const BaseRecipe = 'base_recipe';

    public const Vegan = 'vegan';

    /** @deprecated Orphan mains without a protein family; not shown as a browse tab. */
    public const Meal = 'meal';

    public const All = 'all';

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function tabs(): array
    {
        return [
            ['id' => self::Breakfast, 'label' => 'Breakfast'],
            ['id' => self::Chicken, 'label' => 'Chicken'],
            ['id' => self::Beef, 'label' => 'Beef'],
            ['id' => self::Salmon, 'label' => 'Fish'],
            ['id' => self::Liver, 'label' => 'Liver'],
            ['id' => self::Vegan, 'label' => 'Vegan'],
            ['id' => self::SideSalad, 'label' => 'Side salad'],
            ['id' => self::Dessert, 'label' => 'Dessert'],
            ['id' => self::Soup, 'label' => 'Soup'],
            ['id' => self::MainSalad, 'label' => 'Main salad'],
            ['id' => self::BaseRecipe, 'label' => 'Base recipe'],
        ];
    }

    /**
     * Browse tabs that currently have meals, plus an All tab.
     *
     * @param  iterable<int, Meal|array{browseTab?: string}>  $meals
     * @return list<array{id: string, label: string, count: int}>
     */
    public static function tabsForMeals(iterable $meals): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($meals as $meal) {
            $tabId = is_array($meal)
                ? (string) ($meal['browseTab'] ?? '')
                : self::forMeal($meal);

            if ($tabId === '') {
                continue;
            }

            $counts[$tabId] = ($counts[$tabId] ?? 0) + 1;
        }

        $total = array_sum($counts);
        $tabs = [
            [
                'id' => self::All,
                'label' => 'All',
                'count' => $total,
            ],
        ];

        foreach (self::tabs() as $tab) {
            $count = $counts[$tab['id']] ?? 0;

            if ($count <= 0) {
                continue;
            }

            $tabs[] = [
                'id' => $tab['id'],
                'label' => $tab['label'],
                'count' => $count,
            ];
        }

        return $tabs;
    }

    public static function forMeal(Meal $meal): string
    {
        $category = $meal->category;

        if ($category === RecipeCategory::Soup) {
            return self::Soup;
        }

        if ($category === RecipeCategory::Dessert) {
            return self::Dessert;
        }

        if ($category === RecipeCategory::SideSalad) {
            return self::SideSalad;
        }

        if ($category === RecipeCategory::Breakfast) {
            return self::Breakfast;
        }

        if ($category === RecipeCategory::BaseRecipe) {
            return self::BaseRecipe;
        }

        if (str_contains(strtolower(trim((string) $meal->name)), 'liver')) {
            return self::Liver;
        }

        $family = self::proteinFamilyFor($meal);

        if ($family === MealTiersProteinFamily::Chicken && self::ingredientMentionsLiver($meal)) {
            return self::Liver;
        }

        $proteinTab = match ($family) {
            MealTiersProteinFamily::Fish => self::Salmon,
            MealTiersProteinFamily::Beef => self::Beef,
            MealTiersProteinFamily::Chicken => self::Chicken,
            default => null,
        };

        if ($proteinTab !== null) {
            return $proteinTab;
        }

        if ($category === RecipeCategory::MainSalad) {
            return self::MainSalad;
        }

        if ($meal->isVegan()) {
            return self::Vegan;
        }

        return self::Meal;
    }

    /**
     * @return MealTiersProteinFamily::Chicken|MealTiersProteinFamily::Fish|MealTiersProteinFamily::Beef|null
     */
    private static function proteinFamilyFor(Meal $meal): ?string
    {
        if ($meal->exists || $meal->relationLoaded('ingredients')) {
            return MealTiersProteinFamily::forMeal($meal);
        }

        return MealTiersProteinFamily::fromMealName((string) $meal->name);
    }

    private static function ingredientMentionsLiver(Meal $meal): bool
    {
        if (! $meal->exists && ! $meal->relationLoaded('ingredients')) {
            return false;
        }

        $meal->loadMissing('ingredients');

        foreach ($meal->ingredients as $ingredient) {
            if (str_contains(strtolower(trim($ingredient->name)), 'liver')) {
                return true;
            }
        }

        return false;
    }
}
