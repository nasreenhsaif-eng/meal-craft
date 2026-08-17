<?php

use App\Support\MealFoodFilterCatalog;

test('meal food filter catalog canonicalizes slugs and maps safety labels', function () {
    expect(MealFoodFilterCatalog::canonicalSlugsFromList(['Dairy', 'nuts', 'invalid', 'fish']))
        ->toBe(['dairy', 'fish', 'nuts'])
        ->and(MealFoodFilterCatalog::safetyLabelsFromSlugs(['shellfish', 'sesame', 'spicy']))
        ->toBe(['Sesame', 'Shellfish', 'Spicy']);
});
