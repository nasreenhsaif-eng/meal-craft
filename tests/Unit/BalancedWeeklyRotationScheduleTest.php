<?php

use App\Enums\MealPlanSlotType;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedWeeklyRotationSchedule;

test('balanced weekly rotation assigns different chia breakfasts each day', function (): void {
    $dayOne = BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Breakfast, 1);
    $dayTwo = BalancedWeeklyRotationSchedule::mealNameForDay(2, MealPlanSlotType::Breakfast, 1);

    expect($dayOne)->not->toBe($dayTwo)
        ->and($dayOne)->toBe('Blueberry Walnut Chia Pudding');
});

test('balanced weekly rotation keeps fixed second choices per slot pattern', function (): void {
    foreach (range(1, 7) as $day) {
        expect(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Salad, 2))
            ->toBe('Classic Garden Salad')
            ->and(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Dessert, 2))
            ->toBe('Fruit Salad Bowl')
            ->and(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Soup, 1))
            ->toBe(BalancedWeeklyRotationSchedule::VEGAN_SOUP)
            ->and(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Soup, 2))
            ->toBe('Bone Broth Cup');
    }
});

test('balanced weekly rotation uses salmon mains sun through tue and beef wed through sat', function (): void {
    expect(BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Main, 3))
        ->toBe(BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME)
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(3, MealPlanSlotType::Main, 3))
        ->toBe('Grilled Salmon Mango Salsa')
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(4, MealPlanSlotType::Main, 3))
        ->toBe('Grilled Beef Steak Ratatouille & Saffron rice')
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(7, MealPlanSlotType::Main, 3))
        ->toBe('Chili Beef Stuffed Peppers');
});
