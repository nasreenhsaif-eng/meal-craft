<?php

use App\Models\CustomerProfile;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Support\ChiaDessertMeals;

test('coconut and greek chia dessert names pair and round-trip', function (): void {
    foreach (BalancedWeeklyRotationSchedule::CHIA_DESSERTS as $coconutName) {
        $greekName = ChiaDessertMeals::greekVariantMealName($coconutName);

        expect($greekName)->not->toBeNull()
            ->and(ChiaDessertMeals::coconutVariantMealName($greekName))->toBe($coconutName);
    }

    $smoothieGreek = ChiaDessertMeals::greekVariantMealName('Chia Pudding Smoothie');

    expect($smoothieGreek)->toBe('Greek Yogurt Chia Pudding Smoothie')
        ->and(ChiaDessertMeals::coconutVariantMealName($smoothieGreek))->toBe('Chia Pudding Smoothie');
});

test('resolveMealNameForProfile returns greek variant when dairy is not filtered', function (): void {
    $profile = new CustomerProfile([
        'food_filters' => [],
        'allergies' => [],
    ]);

    $resolved = ChiaDessertMeals::resolveMealNameForProfile(
        'Blueberry Walnut Chia Pudding',
        $profile,
    );

    expect($resolved)->toBe('Blueberry Walnut Greek Yogurt Chia Pudding');
});

test('resolveMealNameForProfile returns coconut variant when dairy is filtered', function (): void {
    $profile = new CustomerProfile([
        'food_filters' => ['dairy'],
        'allergies' => ['dairy'],
    ]);

    $resolved = ChiaDessertMeals::resolveMealNameForProfile(
        'Blueberry Walnut Greek Yogurt Chia Pudding',
        $profile,
    );

    expect($resolved)->toBe('Blueberry Walnut Chia Pudding');
});

test('resolveMealNameForProfile leaves non-chia meals unchanged', function (): void {
    $profile = new CustomerProfile([
        'food_filters' => ['dairy'],
        'allergies' => ['dairy'],
    ]);

    expect(ChiaDessertMeals::resolveMealNameForProfile('Fruit Salad Bowl', $profile))
        ->toBe('Fruit Salad Bowl');
});
