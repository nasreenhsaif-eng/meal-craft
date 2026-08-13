<?php

use App\Support\ComplexCarbPortion;
use App\Support\KitchenProtectedFlavorPortion;
use App\Support\NonStarchyVegetablePortion;
use App\Support\StandardMeatPortion;

test('complex carb portion matches starches and excludes breads', function () {
    expect(ComplexCarbPortion::matches('Sweet Potato'))->toBeTrue()
        ->and(ComplexCarbPortion::matches('Cooked Quinoa (Base)'))->toBeTrue()
        ->and(ComplexCarbPortion::matches('Basmati Rice (Brown)'))->toBeTrue()
        ->and(ComplexCarbPortion::matches('Quinoa Flatbread (Base)'))->toBeFalse()
        ->and(ComplexCarbPortion::matches('Broccoli'))->toBeFalse();
});

test('non-starchy vegetable portion matches produce sides', function () {
    expect(NonStarchyVegetablePortion::matches('Broccoli'))->toBeTrue()
        ->and(NonStarchyVegetablePortion::matches('Spinach (Fresh)'))->toBeTrue()
        ->and(NonStarchyVegetablePortion::matches('Sweet Potato'))->toBeFalse()
        ->and(NonStarchyVegetablePortion::matches('Olive Oil (Extra Virgin)'))->toBeFalse();
});

test('protected flavor portion keeps spices oils and dressings', function () {
    expect(KitchenProtectedFlavorPortion::matches('Black Pepper'))->toBeTrue()
        ->and(KitchenProtectedFlavorPortion::matches('Garlic (Raw)'))->toBeTrue()
        ->and(KitchenProtectedFlavorPortion::matches('Rosemary (Fresh)'))->toBeTrue()
        ->and(KitchenProtectedFlavorPortion::matches('Olive Oil (Extra Virgin)'))->toBeTrue()
        ->and(KitchenProtectedFlavorPortion::matches('Turmeric Lemon Dressing (Base)'))->toBeTrue()
        ->and(KitchenProtectedFlavorPortion::matches('Chicken Breast'))->toBeFalse()
        ->and(KitchenProtectedFlavorPortion::matches('Broccoli'))->toBeFalse();
});

test('primary protein class detects chicken beef liver and fish', function () {
    expect(StandardMeatPortion::primaryProteinClass('Chicken Breast'))->toBe('chicken')
        ->and(StandardMeatPortion::primaryProteinClass('Beef Ribeye'))->toBe('beef')
        ->and(StandardMeatPortion::primaryProteinClass('Salmon'))->toBe('fish')
        ->and(StandardMeatPortion::primaryProteinClass('Chicken Liver', 'Sautéed Chicken Liver w Pomegranate'))->toBe('liver')
        ->and(StandardMeatPortion::primaryProteinClass('Beef Liver', 'Beef & Liver Kefta w Herb Salad'))->toBeNull();
});
