<?php

use App\Support\MealTiersLibraryExclusions;

test('excluded classic meals are blocked from the meal tiers library', function () {
    expect(MealTiersLibraryExclusions::isExcluded('Salmon Plate'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Blueberry Walnut Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('High Protein Miso Crunch Salad'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Shaved Fennel Rocca Salad'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Spiced Crunch Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Vegan Curry Lentil Salad'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato'))->toBeFalse()
        ->and(MealTiersLibraryExclusions::names())->toContain('Blueberry Walnut Chia Pudding');
});
