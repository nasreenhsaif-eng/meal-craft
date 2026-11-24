<?php

namespace App\Support;

/**
 * Canonical subscription plans, eligible calorie tiers, and integer baseline prices.
 */
final class SubscriptionPlanCatalog
{
    /** @var list<int> */
    public const DAYS = [5, 6, 7, 20, 24, 28];

    /**
     * @return array<int, array<int, int>>
     */
    public static function baseline(): array
    {
        return [
            650 => [5 => 41, 6 => 47, 7 => 54, 20 => 144, 24 => 167, 28 => 188],
            850 => [5 => 47, 6 => 54, 7 => 61, 20 => 163, 24 => 190, 28 => 213],
            950 => [5 => 50, 6 => 57, 7 => 65, 20 => 174, 24 => 202, 28 => 227],
            1200 => [5 => 57, 6 => 66, 7 => 75, 20 => 200, 24 => 232, 28 => 260],
            1300 => [5 => 59, 6 => 69, 7 => 78, 20 => 208, 24 => 241, 28 => 272],
            1400 => [5 => 62, 6 => 71, 7 => 81, 20 => 216, 24 => 251, 28 => 281],
            1500 => [5 => 64, 6 => 74, 7 => 84, 20 => 225, 24 => 260, 28 => 293],
            1800 => [5 => 71, 6 => 83, 7 => 94, 20 => 250, 24 => 290, 28 => 326],
            2000 => [5 => 77, 6 => 89, 7 => 101, 20 => 270, 24 => 313, 28 => 353],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     slug: string,
     *     name: string,
     *     min_calories: int,
     *     calorie_tiers: list<int>,
     *     meal_structure: array{breakfast: bool, soup: bool, meal_count: int, max_snack_count: int}
     * }>
     */
    public static function plans(): array
    {
        return [
            [
                'key' => 'business',
                'slug' => 'business-craft',
                'name' => 'Business Craft',
                'min_calories' => 650,
                'calorie_tiers' => [650],
                'meal_structure' => [
                    'breakfast' => false,
                    'soup' => false,
                    'meal_count' => 1,
                    'max_snack_count' => 1,
                ],
            ],
            [
                'key' => 'full',
                'slug' => 'full-craft',
                'name' => 'Full Craft',
                'min_calories' => 1200,
                'calorie_tiers' => [1200, 1300, 1400, 1500, 1800, 2000],
                'meal_structure' => [
                    'breakfast' => true,
                    'soup' => false,
                    'meal_count' => 2,
                    'max_snack_count' => 2,
                ],
            ],
            [
                'key' => 'afternoon',
                'slug' => 'afternoon-craft',
                'name' => 'Afternoon Craft',
                'min_calories' => 950,
                'calorie_tiers' => [950, 1200, 1300, 1400, 1500, 1800, 2000],
                'meal_structure' => [
                    'breakfast' => false,
                    'soup' => false,
                    'meal_count' => 2,
                    'max_snack_count' => 2,
                ],
            ],
            [
                'key' => 'day',
                'slug' => 'day-craft',
                'name' => 'Day Craft',
                'min_calories' => 850,
                'calorie_tiers' => [850, 950, 1200, 1300, 1400, 1500],
                'meal_structure' => [
                    'breakfast' => true,
                    'soup' => false,
                    'meal_count' => 1,
                    'max_snack_count' => 2,
                ],
            ],
            [
                'key' => 'intermittent',
                'slug' => 'intermittent-fasting',
                'name' => 'Intermittent Fasting',
                'min_calories' => 850,
                'calorie_tiers' => [850, 950, 1200, 1300, 1400, 1500],
                'meal_structure' => [
                    'breakfast' => false,
                    'soup' => true,
                    'meal_count' => 1,
                    'max_snack_count' => 2,
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     slug: string,
     *     name: string,
     *     min_calories: int,
     *     calorie_tiers: list<int>,
     *     meal_structure: array{breakfast: bool, soup: bool, meal_count: int, max_snack_count: int}
     * }|null
     */
    public static function plan(string $key): ?array
    {
        foreach (self::plans() as $plan) {
            if ($plan['key'] === $key) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public static function tiersFor(string $key): array
    {
        return self::plan($key)['calorie_tiers'] ?? [1200, 1500, 1800, 2000];
    }

    public static function basePrice(int $calorieTier, int $daysCount): int
    {
        return (int) (self::baseline()[$calorieTier][$daysCount] ?? 0);
    }

    public static function dailyRate(int $totalPrice, int $daysCount): int
    {
        $daysCount = max(1, $daysCount);

        return (int) round($totalPrice / $daysCount);
    }
}
