<?php

use App\Models\Ingredient;
use App\Support\WholeFoodDietPolicy;

test('whole food policy bans protein powder oats dairy and soy', function (): void {
    expect(WholeFoodDietPolicy::isBannedIngredientName('Protein Powder (Isolate)'))->toBeTrue()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Oats (Rolled)'))->toBeTrue()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Parmesan'))->toBeTrue()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Soy Sauce'))->toBeTrue()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Salmon'))->toBeFalse();
});

test('whole food policy allows ghee olives house almond flour and vetted pantry staples', function (): void {
    expect(WholeFoodDietPolicy::isBannedIngredientName('Ghee'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Kalamata Olives'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Almond Flour (Base)'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Tamarind Paste'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Rice Vinegar'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Almond Butter'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Date Syrup'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredientName('Coconut Cream'))->toBeFalse()
        ->and(WholeFoodDietPolicy::isBannedIngredient(new Ingredient([
            'name' => 'Almond Flour',
            'usda_food_category' => 'Grains/Nuts',
        ])))->toBeTrue()
        ->and(WholeFoodDietPolicy::isHouseAlmondFlour(new Ingredient([
            'name' => 'Almond Flour (Base)',
            'usda_food_category' => 'Base Ingredient',
        ])))->toBeTrue();
});

test('whole food policy treats house base ingredients as allowed', function (): void {
    expect(WholeFoodDietPolicy::isHouseBaseIngredient(new Ingredient([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'usda_food_category' => 'Base Ingredient',
    ])))->toBeTrue();
});

test('whole food policy caps olive portions per meal', function (): void {
    expect(WholeFoodDietPolicy::MAX_OLIVE_GRAMS_PER_MEAL)->toBe(15.0);
});
