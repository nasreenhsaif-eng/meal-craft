<?php

use App\Models\CustomerProfile;
use App\Services\NutrientDenseWeeklyRotationSchedule;
use App\Support\NutrientDenseBreakfastOptions;

test('nutrient dense breakfast options apply only to nutrient dense profiles', function (): void {
    $balanced = new CustomerProfile(['diet_protocol' => 'balanced']);
    $dense = new CustomerProfile(['diet_protocol' => 'nutrient_dense']);

    expect(NutrientDenseBreakfastOptions::appliesTo($balanced))->toBeFalse()
        ->and(NutrientDenseBreakfastOptions::appliesTo($dense))->toBeTrue();
});

test('chia breakfast name rotates by weekday for nutrient dense', function (): void {
    $profile = new CustomerProfile(['diet_protocol' => 'nutrient_dense']);

    expect(NutrientDenseBreakfastOptions::chiaMealNameForDay(1, $profile))
        ->toBe('Blueberry Walnut Greek Yogurt Chia Pudding')
        ->and(NutrientDenseBreakfastOptions::chiaMealNameForDay(2, $profile))
        ->toBe('Mango Pumpkin Seed Greek Yogurt Chia Pudding');
});

test('egg breakfast name rotates by weekday for nutrient dense', function (): void {
    $profile = new CustomerProfile(['diet_protocol' => 'nutrient_dense']);

    expect(NutrientDenseBreakfastOptions::eggBreakfastMealNameForDay(1, $profile))
        ->toBe(NutrientDenseWeeklyRotationSchedule::EGG_BREAKFASTS[0])
        ->and(NutrientDenseBreakfastOptions::eggBreakfastMealNameForDay(2, $profile))
        ->toBe(NutrientDenseWeeklyRotationSchedule::EGG_BREAKFASTS[1])
        ->and(NutrientDenseBreakfastOptions::eggBreakfastMealNameForDay(1, $profile))
        ->not->toBe(NutrientDenseBreakfastOptions::eggBreakfastMealNameForDay(2, $profile));
});

test('dairy avoid profiles resolve chia breakfast to coconut variant', function (): void {
    $profile = new CustomerProfile([
        'diet_protocol' => 'nutrient_dense',
        'food_filters' => ['dairy'],
    ]);

    expect(NutrientDenseBreakfastOptions::chiaMealNameForDay(1, $profile))
        ->toBe('Blueberry Walnut Chia Pudding');
});

test('day one egg breakfast is mediterranean omelet and slots stay stable', function (): void {
    $profile = new CustomerProfile(['diet_protocol' => 'nutrient_dense']);

    expect(NutrientDenseBreakfastOptions::eggBreakfastMealNameForDay(1, $profile))
        ->toBe(NutrientDenseBreakfastOptions::OMELETTE_NAME)
        ->and(NutrientDenseBreakfastOptions::EGG_SLOT_INDEX)->toBe(1)
        ->and(NutrientDenseBreakfastOptions::OMELETTE_SLOT_INDEX)->toBe(1)
        ->and(NutrientDenseBreakfastOptions::CHIA_SLOT_INDEX)->toBe(2);
});
