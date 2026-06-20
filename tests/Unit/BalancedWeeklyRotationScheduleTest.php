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
            ->and(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Soup, 2))
            ->toBe('Bone Broth Cup');
    }
});

test('balanced weekly rotation assigns a different rotating soup in slot 1 each weekday', function (): void {
    $soups = [];

    foreach (range(1, 7) as $day) {
        $soups[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Soup, 1);
    }

    expect($soups[0])->toBe('Vegan Mushroom Soup')
        ->and($soups[1])->toBe('Butternut Squash Soup')
        ->and($soups[2])->toBe('Tomato Basil Soup')
        ->and($soups[3])->toBe('Red Lentil Turmeric Soup')
        ->and($soups[4])->toBe('Cauliflower Ginger Soup')
        ->and($soups[5])->toBe('Carrot Cumin Soup')
        ->and($soups[6])->toBe('Sweet Potato Fennel Soup')
        ->and(count(array_unique($soups)))->toBe(7);
});

test('balanced weekly rotation alternates salmon and beef mains each day', function (): void {
    expect(BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Main, 3))
        ->toBe(BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME)
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(2, MealPlanSlotType::Main, 3))
        ->toBe('Grilled Beef Steak Ratatouille & Saffron rice')
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(3, MealPlanSlotType::Main, 3))
        ->toBe('Citrus Herb Salmon')
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(4, MealPlanSlotType::Main, 3))
        ->toBe('Beef Bibimbap')
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(6, MealPlanSlotType::Main, 3))
        ->toBe('Persian Herb Beef Stew')
        ->and(BalancedWeeklyRotationSchedule::mealNameForDay(7, MealPlanSlotType::Main, 3))
        ->toBe(BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME);
});

test('balanced weekly rotation uses legume-free vegan side salads in slot one', function (): void {
    $salads = [];

    foreach (range(1, 7) as $day) {
        $salads[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Salad, 1);
    }

    expect($salads)->not->toContain('Vegan Curry Lentil Salad')
        ->and($salads)->not->toContain('Spiced Cauliflower Chickpea Salad')
        ->and($salads)->not->toContain('Thai Rainbow Peanut Salad')
        ->and($salads[6])->toBe('Coconut Grapefruit Salad');
});
