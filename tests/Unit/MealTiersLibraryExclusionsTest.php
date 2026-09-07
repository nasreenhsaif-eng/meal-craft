<?php

use App\Support\MealTiersLibraryExclusions;

test('excluded classic meals are blocked from the meal tiers library', function () {
    expect(MealTiersLibraryExclusions::isExcluded('Salmon Plate'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Blueberry Walnut Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Cacao & Almond Chia'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Carrot Cumin Soup'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Chia Pudding Smoothie'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('High Protein Miso Crunch Salad'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Mango Pumpkin Seed Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Peach Pecan Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Raspberry Cacao Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Shaved Fennel Rocca Salad'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Spiced Crunch Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Strawberry Almond Chia Pudding'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Sweet Potato Fennel Soup'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Vegan Curry Lentil Salad'))->toBeTrue()
        ->and(MealTiersLibraryExclusions::isExcluded('Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato'))->toBeFalse()
        ->and(MealTiersLibraryExclusions::names())->toContain('Chia Pudding Smoothie');
});
