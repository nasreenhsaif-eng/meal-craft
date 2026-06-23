<?php

use App\Models\Ingredient;
use App\Support\MealIngredientDisplayOrder;

test('sorts ingredients protein carbs vegetables herbs sauces fats', function () {
    $protein = Ingredient::factory()->make(['name' => 'Beef Ground Lean', 'usda_food_category' => 'Proteins']);
    $carb = Ingredient::factory()->make(['name' => 'Buckwheat Spaghetti (Cooked)', 'usda_food_category' => 'Grains']);
    $vegetable = Ingredient::factory()->make(['name' => 'Tomato (Raw)', 'usda_food_category' => 'Vegetables']);
    $herb = Ingredient::factory()->make(['name' => 'Fresh Parsley', 'usda_food_category' => 'Herbs']);
    $sauce = Ingredient::factory()->make(['name' => 'Marinara Sauce (Base)', 'usda_food_category' => 'Base Ingredient']);
    $fat = Ingredient::factory()->make(['name' => 'Olive Oil', 'usda_food_category' => 'Pantry']);

    $sorted = MealIngredientDisplayOrder::sortedIngredients([
        $fat,
        $herb,
        $sauce,
        $vegetable,
        $carb,
        $protein,
    ]);

    expect(array_map(fn (Ingredient $i): string => $i->name, $sorted))
        ->toBe([
            'Beef Ground Lean',
            'Buckwheat Spaghetti (Cooked)',
            'Tomato (Raw)',
            'Fresh Parsley',
            'Marinara Sauce (Base)',
            'Olive Oil',
        ]);
});

test('classifies eggs as protein and green beans as vegetables', function () {
    $egg = Ingredient::factory()->make(['name' => 'Egg', 'usda_food_category' => 'Protein']);
    $beans = Ingredient::factory()->make(['name' => 'Green Beans', 'usda_food_category' => 'Vegetables']);

    expect(MealIngredientDisplayOrder::groupRank($egg))->toBe(MealIngredientDisplayOrder::GROUP_PROTEIN)
        ->and(MealIngredientDisplayOrder::groupRank($beans))->toBe(MealIngredientDisplayOrder::GROUP_VEGETABLES);
});
