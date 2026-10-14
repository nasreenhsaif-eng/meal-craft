<?php

use App\Support\MealTiersProteinFamily;

test('liver and chicken names use the chicken calorie buckets', function () {
    expect(MealTiersProteinFamily::fromMealName('Sautéed Chicken Liver with Onions'))->toBe(MealTiersProteinFamily::Chicken)
        ->and(MealTiersProteinFamily::fromMealName('Rosemary Garlic Chicken'))->toBe(MealTiersProteinFamily::Chicken);
});

test('fish names use the salmon calorie buckets', function () {
    expect(MealTiersProteinFamily::fromMealName('Baked Salmon with Vegetables'))->toBe(MealTiersProteinFamily::Fish)
        ->and(MealTiersProteinFamily::fromMealName('Grilled Hamour'))->toBe(MealTiersProteinFamily::Fish)
        ->and(MealTiersProteinFamily::fromMealName('Grilled Mackerel w Roasted Vegetables'))->toBe(MealTiersProteinFamily::Fish);
});

test('beef names use the beef calorie buckets', function () {
    expect(MealTiersProteinFamily::fromMealName('Italian Meatballs'))->toBe(MealTiersProteinFamily::Beef)
        ->and(MealTiersProteinFamily::fromMealName('Beef Sirloin with Broccoli'))->toBe(MealTiersProteinFamily::Beef);
});

test('chicken fish and beef families use the shared kitchen pack-out', function () {
    expect(MealTiersProteinFamily::usesKitchenPackout(MealTiersProteinFamily::Chicken))->toBeTrue()
        ->and(MealTiersProteinFamily::usesKitchenPackout(MealTiersProteinFamily::Fish))->toBeTrue()
        ->and(MealTiersProteinFamily::usesKitchenPackout(MealTiersProteinFamily::Beef))->toBeTrue()
        ->and(MealTiersProteinFamily::usesKitchenPackout(null))->toBeFalse();
});
