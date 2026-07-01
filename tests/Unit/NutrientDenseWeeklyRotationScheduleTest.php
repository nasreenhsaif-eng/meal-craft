<?php

use App\Enums\MealPlanSlotType;
use App\Services\NutrientDenseFermentedRecipeRefiner;
use App\Services\NutrientDenseWeeklyRotationSchedule;

test('nutrient dense rotation assigns fish daily on main slot 3', function (): void {
    foreach (range(1, 7) as $day) {
        $main = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 3);

        expect($main)->toBeIn(NutrientDenseWeeklyRotationSchedule::FISH_MAINS);
    }
});

test('nutrient dense rotation includes fermented anchor every day', function (): void {
    foreach (range(1, 7) as $day) {
        expect(NutrientDenseWeeklyRotationSchedule::fermentedAnchorForDay($day))->not->toBe('');
    }
});

test('nutrient dense dessert rotation uses greek yogurt chia and baked goods', function (): void {
    expect(NutrientDenseWeeklyRotationSchedule::NUTRIENT_DENSE_DESSERTS[0])
        ->toContain('Greek Yogurt Chia')
        ->and(NutrientDenseWeeklyRotationSchedule::NUTRIENT_DENSE_DESSERTS[1])
        ->toBe('Chocolate Orange Brownie');
});

test('egg mains appear at most three days per week in rotation', function (): void {
    $eggMainDays = 0;

    foreach (range(1, 7) as $day) {
        foreach (range(1, 5) as $slot) {
            $name = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, $slot);

            if (NutrientDenseWeeklyRotationSchedule::mainContainsEgg($name)) {
                $eggMainDays++;
            }
        }
    }

    expect($eggMainDays)->toBeLessThanOrEqual(3);
});

test('sardine main is in fish rotation', function (): void {
    expect(NutrientDenseWeeklyRotationSchedule::FISH_MAINS)
        ->toContain(NutrientDenseFermentedRecipeRefiner::SARDINE_MAIN_NAME);
});

test('micro-dense side salads rotate daily without repeating within the week', function (): void {
    $salads = [];

    foreach (range(1, 7) as $day) {
        $salads[] = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Salad, 1);
    }

    expect($salads)->toHaveCount(7)
        ->and(array_unique($salads))->toHaveCount(7)
        ->and($salads[0])->toBe('Kimchi Purslane Side Salad')
        ->and($salads[1])->toBe('Tahini Purslane Pepper Salad');
});
