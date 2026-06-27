<?php

use App\Enums\MealPlanSlotType;
use App\Services\BalancedWeeklyRotationSchedule;

test('chicken salad rotation mains never appear in vegan side salad slot', function (): void {
    $veganSideSalads = [];

    foreach (range(1, 7) as $day) {
        $veganSideSalads[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Salad, 1);
    }

    foreach (BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS as $chickenSaladMain) {
        expect($veganSideSalads)->not->toContain($chickenSaladMain);
    }

    expect(BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Main, 2))
        ->toBe('Rosemary Chicken Rocca Salad');
});
