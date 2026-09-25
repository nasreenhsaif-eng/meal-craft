<?php

use App\Support\CraftLibraryTierMap;

test('full craft 2000 uses a 500 breakfast and 600 mains', function () {
    $row = CraftLibraryTierMap::row('full', 2000);

    expect($row['breakfast'])->toBe(500)
        ->and($row['main_each'])->toBe(600)
        ->and($row['main_count'])->toBe(2)
        ->and($row['include_side_salad'])->toBeTrue()
        ->and($row['include_dessert'])->toBeTrue()
        ->and($row['include_soup'])->toBeFalse();
});

test('full craft omits dessert at 1250', function () {
    $row = CraftLibraryTierMap::row('full', 1250);

    expect($row['breakfast'])->toBe(300)
        ->and($row['main_each'])->toBe(400)
        ->and($row['include_dessert'])->toBeFalse()
        ->and($row['include_side_salad'])->toBeTrue();
});

test('full craft keeps 1200–1391 needs on the 1250 row without dessert', function () {
    expect(CraftLibraryTierMap::snapToCraftTotal(1375, 'full'))->toBe(1250)
        ->and(CraftLibraryTierMap::row('full', 1375)['include_dessert'])->toBeFalse()
        ->and(CraftLibraryTierMap::snapToCraftTotal(1400, 'full'))->toBe(1500)
        ->and(CraftLibraryTierMap::row('full', 1500)['include_dessert'])->toBeTrue();
});

test('afternoon 1800 uses 700 kcal mains and no breakfast', function () {
    $row = CraftLibraryTierMap::row('afternoon', 1800);

    expect($row['breakfast'])->toBe(0)
        ->and($row['main_each'])->toBe(700)
        ->and($row['main_count'])->toBe(2);
});

test('afternoon below 1500 omits dessert and stays off the 1500 plate', function () {
    expect(CraftLibraryTierMap::snapToCraftTotal(1000, 'afternoon'))->toBe(950)
        ->and(CraftLibraryTierMap::row('afternoon', 1000)['include_dessert'])->toBeFalse()
        ->and(CraftLibraryTierMap::snapToCraftTotal(1250, 'afternoon'))->toBe(1200)
        ->and(CraftLibraryTierMap::row('afternoon', 1250)['include_dessert'])->toBeFalse()
        ->and(CraftLibraryTierMap::snapToCraftTotal(1391, 'afternoon'))->toBe(1200)
        ->and(CraftLibraryTierMap::row('afternoon', 1500)['include_dessert'])->toBeTrue();
});

test('day craft snaps 1500 needs to 1400', function () {
    expect(CraftLibraryTierMap::snapToCraftTotal(1500, 'day'))->toBe(1400)
        ->and(CraftLibraryTierMap::breakfastTab('day', 1500))->toBe(500)
        ->and(CraftLibraryTierMap::mainEachTab('day', 1500))->toBe(500);
});

test('business 550 includes a side and omits dessert', function () {
    $row = CraftLibraryTierMap::row('business', 560);

    expect($row['main_each'])->toBe(400)
        ->and($row['include_side_salad'])->toBeTrue()
        ->and($row['include_dessert'])->toBeFalse();
});

test('intermittent includes soup on every total', function (int $total, int $mainEach) {
    $row = CraftLibraryTierMap::row('intermittent', $total);

    expect($row['include_soup'])->toBeTrue()
        ->and($row['include_side_salad'])->toBeTrue()
        ->and($row['include_dessert'])->toBeTrue()
        ->and($row['main_each'])->toBe($mainEach)
        ->and($row['breakfast'])->toBe(0);
})->with([
    [950, 400],
    [1050, 500],
    [1100, 550],
    [1150, 600],
]);

test('onboarding plan tiers are full craft totals', function () {
    expect(CraftLibraryTierMap::planTiers())->toBe([1250, 1500, 1800, 2000]);
});

test('calorie tab for breakfast and main slots', function () {
    expect(CraftLibraryTierMap::calorieTabForSlot('full', 1800, 'breakfast'))->toBe(400)
        ->and(CraftLibraryTierMap::calorieTabForSlot('full', 1800, 'main'))->toBe(500)
        ->and(CraftLibraryTierMap::calorieTabForSlot('full', 1800, 'dessert'))->toBe(0);
});
