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

test('nutrient dense dessert rotation uses baked goods daily', function (): void {
    foreach (range(1, 7) as $day) {
        $dessert = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Dessert, 1);

        expect($dessert)->toBeIn(NutrientDenseWeeklyRotationSchedule::BAKED_DESSERTS)
            ->and($dessert)->not->toContain('Chia');
    }

    expect(NutrientDenseWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Dessert, 1))
        ->toBe('Chocolate Orange Brownie');
});

test('nutrient dense baked desserts are unique once per week', function (): void {
    $desserts = NutrientDenseWeeklyRotationSchedule::NUTRIENT_DENSE_DESSERTS;
    $weekdayBaked = [];

    foreach (range(1, 7) as $day) {
        $weekdayBaked[] = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Dessert, 1);
    }

    expect($desserts)->toHaveCount(7)
        ->and(array_unique($desserts))->toHaveCount(7)
        ->and($weekdayBaked)->toHaveCount(7)
        ->and(array_unique($weekdayBaked))->toHaveCount(7)
        ->and($weekdayBaked)->toBe($desserts)
        ->and($weekdayBaked[5])->toBe('Apple Pie Balls')
        ->and($weekdayBaked[6])->toBe('Banana Blueberry Balls');
});

test('nutrient dense dessert slot three rotates greek yogurt chia puddings', function (): void {
    foreach (range(1, 7) as $day) {
        $dessert = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Dessert, 3);

        expect($dessert)->toBeIn(NutrientDenseWeeklyRotationSchedule::CHIA_DESSERTS)
            ->and($dessert)->toContain('Greek Yogurt');
    }

    expect(NutrientDenseWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Dessert, 3))
        ->toBe('Blueberry Walnut Greek Yogurt Chia Pudding');
});

test('egg mains appear at most three days per week in rotation', function (): void {
    $eggMainDays = 0;

    foreach (range(1, 7) as $day) {
        foreach (range(1, 6) as $slot) {
            $name = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, $slot);

            if (NutrientDenseWeeklyRotationSchedule::mainContainsEgg($name)) {
                $eggMainDays++;
            }
        }
    }

    expect($eggMainDays)->toBeLessThanOrEqual(3);
});

test('day slot template includes six main meal slots', function (): void {
    $mainSlots = array_values(array_filter(
        MealPlanSlotType::daySlotTemplate(),
        static fn (array $slot): bool => $slot[0] === MealPlanSlotType::Main,
    ));

    expect($mainSlots)->toHaveCount(6)
        ->and(array_column($mainSlots, 1))->toBe([1, 2, 3, 4, 5, 6]);
});

test('nutrient dense beef mains are unique once per week on slot six', function (): void {
    $beefMains = [];

    foreach (range(1, 7) as $day) {
        $beefMains[] = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 6);
    }

    expect($beefMains)->toHaveCount(7)
        ->and(array_unique($beefMains))->toHaveCount(7)
        ->and($beefMains)->toBe(NutrientDenseWeeklyRotationSchedule::BEEF_MAINS)
        ->and($beefMains[0])->toBe('Grilled Beef Steak Ratatouille & Saffron rice');
});

test('nutrient dense main slot six stays liver-free while slot five keeps liver rotation', function (): void {
    foreach (range(1, 7) as $day) {
        $liver = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 5);
        $beef = NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 6);

        expect($liver)->toBeIn(NutrientDenseWeeklyRotationSchedule::LIVER_MAINS)
            ->and($beef)->toBeIn(NutrientDenseWeeklyRotationSchedule::BEEF_MAINS)
            ->and(str_contains(strtolower($beef), 'liver'))->toBeFalse();
    }
});

test('sardine main is in fish rotation', function (): void {
    expect(NutrientDenseWeeklyRotationSchedule::FISH_MAINS)
        ->toContain(NutrientDenseFermentedRecipeRefiner::SARDINE_MAIN_NAME);
});

test('friday fish main is pan seared hamour', function (): void {
    expect(NutrientDenseWeeklyRotationSchedule::mealNameForDay(6, MealPlanSlotType::Main, 3))
        ->toBe('Pan Seared Hamour');
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
