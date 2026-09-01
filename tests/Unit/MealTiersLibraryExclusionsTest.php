<?php

use App\Support\MealTiersLibraryExclusions;

test('excluded classic meals are blocked from the meal tiers library', function () {
    expect(MealTiersLibraryExclusions::isExcluded('Salmon Plate'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato'))->toBeFalse();
});
