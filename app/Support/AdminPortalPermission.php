<?php

namespace App\Support;

/**
 * Canonical admin portal section keys for Craft Master General checklists.
 */
final class AdminPortalPermission
{
    public const IngredientDb = 'ingredient-db';

    public const MealHub = 'meal-hub';

    public const MealTiers = 'meal-tiers';

    public const MealPlans = 'meal-plans';

    public const KitchenLogistics = 'kitchen-logistics';

    public const Pricing = 'pricing';

    public const CustomerProfiles = 'customer-profiles';

    public const DiscoveryInsights = 'discovery-insights';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::IngredientDb,
            self::MealHub,
            self::MealTiers,
            self::MealPlans,
            self::KitchenLogistics,
            self::Pricing,
            self::CustomerProfiles,
            self::DiscoveryInsights,
        ];
    }

    /**
     * Grouped labels for CMG checklist UI.
     *
     * @return list<array{group: string, items: list<array{key: string, label: string}>}>
     */
    public static function checklist(): array
    {
        return [
            [
                'group' => 'Kitchen engine',
                'items' => [
                    ['key' => self::IngredientDb, 'label' => 'Ingredient Library'],
                    ['key' => self::MealHub, 'label' => 'Meal Library'],
                    ['key' => self::MealTiers, 'label' => 'Meal Tiers Library'],
                    ['key' => self::MealPlans, 'label' => 'Meal Plan Library'],
                    ['key' => self::KitchenLogistics, 'label' => 'Kitchen Production'],
                    ['key' => self::Pricing, 'label' => 'Subscription Pricing Matrix'],
                ],
            ],
            [
                'group' => 'User intelligence',
                'items' => [
                    ['key' => self::CustomerProfiles, 'label' => 'Customer Profiles'],
                    ['key' => self::DiscoveryInsights, 'label' => 'Discovery Insights'],
                ],
            ],
        ];
    }

    /**
     * Map admin route name prefixes / path segments to permission keys.
     */
    public static function keyForRoute(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        return match (true) {
            str_starts_with($routeName, 'admin.ingredient-library') => self::IngredientDb,
            str_starts_with($routeName, 'admin.meal-library') => self::MealHub,
            str_starts_with($routeName, 'admin.meal-tiers-library') => self::MealTiers,
            str_starts_with($routeName, 'admin.meal-plan-library') => self::MealPlans,
            str_starts_with($routeName, 'admin.kitchen-logistics') => self::KitchenLogistics,
            str_starts_with($routeName, 'admin.partner-kitchen-pricing') => self::Pricing,
            str_starts_with($routeName, 'admin.customers') => self::CustomerProfiles,
            default => null,
        };
    }

    /**
     * @param  list<string>|null  $permissions
     * @return list<string>
     */
    public static function sanitize(?array $permissions): array
    {
        if ($permissions === null || $permissions === []) {
            return [];
        }

        $allowed = array_flip(self::keys());

        return array_values(array_unique(array_filter(
            $permissions,
            fn (mixed $key): bool => is_string($key) && isset($allowed[$key]),
        )));
    }
}
