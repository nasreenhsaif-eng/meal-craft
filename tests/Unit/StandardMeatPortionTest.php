<?php

use App\Support\StandardMeatPortion;

test('primary meat ingredients include beef chicken and fish', function () {
    expect(StandardMeatPortion::isPrimaryMeatIngredient('Chicken Breast', 'Rosemary Chicken Rocca Salad'))->toBeTrue()
        ->and(StandardMeatPortion::isPrimaryMeatIngredient('Beef Chuck Roast', 'Slow Cooked Beef and Mint Basil Koosa'))->toBeTrue()
        ->and(StandardMeatPortion::isPrimaryMeatIngredient('Salmon (Raw)', 'Salmon Turmeric Rice'))->toBeTrue()
        ->and(StandardMeatPortion::isPrimaryMeatIngredient('Tandoori Chicken (Base)', 'Tandoori Chicken Salad'))->toBeTrue();
});

test('liver blend ingredients are not primary unless the meal is a liver main', function () {
    expect(StandardMeatPortion::isPrimaryMeatIngredient('Beef Liver', 'Beef & Liver Kefta w Herb Salad & Tahini'))->toBeFalse()
        ->and(StandardMeatPortion::isPrimaryMeatIngredient('Beef Liver', 'Chili Beef Stuffed Peppers'))->toBeFalse()
        ->and(StandardMeatPortion::isPrimaryMeatIngredient('Beef Liver', 'Seared Beef Liver w Caramelized Onion, Spinach & Chimichurri'))->toBeTrue();
});

test('liver blend meals target beef grams that sum to 150 g with liver', function () {
    expect(StandardMeatPortion::beefGramsForLiverBlendMeal(22.0))->toBe(128.0)
        ->and(StandardMeatPortion::beefGramsForLiverBlendMeal(20.0))->toBe(130.0)
        ->and(StandardMeatPortion::isLiverBlendIngredient('Beef Liver', 'Beef & Liver Kefta w Herb Salad & Tahini'))->toBeTrue();
});

test('broth and fish sauce are excluded', function () {
    expect(StandardMeatPortion::isPrimaryMeatIngredient('Chicken Broth', 'Bone Broth Cup'))->toBeFalse()
        ->and(StandardMeatPortion::isPrimaryMeatIngredient('Fish Sauce', 'Thai Red Curry Chicken w Roasted Pumpkin'))->toBeFalse();
});
