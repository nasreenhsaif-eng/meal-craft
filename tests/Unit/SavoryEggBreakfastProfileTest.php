<?php

use App\Models\CustomerProfile;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Support\SavoryEggBreakfastMeals;

test('dairy-forward and dairy-free breakfast names pair by day index', function (): void {
    foreach (BalancedWeeklyRotationSchedule::DAIRY_FORWARD_EGG_BREAKFASTS as $index => $dairyForwardName) {
        $dairyFreeName = SavoryEggBreakfastMeals::dairyFreeVariantMealName($dairyForwardName);

        expect($dairyFreeName)->toBe(BalancedWeeklyRotationSchedule::EGG_BREAKFASTS[$index])
            ->and(SavoryEggBreakfastMeals::dairyForwardVariantMealName($dairyFreeName))->toBe($dairyForwardName);
    }
});

test('resolveMealNameForProfile returns dairy-forward breakfast when dairy is not filtered', function (): void {
    $profile = new CustomerProfile([
        'food_filters' => [],
        'allergies' => [],
    ]);

    expect(SavoryEggBreakfastMeals::resolveMealNameForProfile('Mediterranean Omelet', $profile))
        ->toBe('Halloumi & Spinach Scramble');
});

test('resolveMealNameForProfile returns dairy-free breakfast when dairy is filtered', function (): void {
    $profile = new CustomerProfile([
        'food_filters' => ['dairy'],
        'allergies' => ['dairy'],
    ]);

    expect(SavoryEggBreakfastMeals::resolveMealNameForProfile('Halloumi & Spinach Scramble', $profile))
        ->toBe('Mediterranean Omelet');
});

test('scheduledBreakfastNameForDay respects dairy filter', function (): void {
    $openProfile = new CustomerProfile(['food_filters' => [], 'allergies' => []]);
    $dairyFreeProfile = new CustomerProfile(['food_filters' => ['dairy'], 'allergies' => ['dairy']]);

    expect(SavoryEggBreakfastMeals::scheduledBreakfastNameForDay(1, $openProfile))
        ->toBe('Halloumi & Spinach Scramble')
        ->and(SavoryEggBreakfastMeals::scheduledBreakfastNameForDay(1, $dairyFreeProfile))
        ->toBe('Mediterranean Omelet');
});
