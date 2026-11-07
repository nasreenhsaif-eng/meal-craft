<?php

use App\Models\Ingredient;
use App\Support\MealIngredientDisplayOrder;
use App\Support\MealTiersLibraryPresentation;

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

test('classifies bell peppers as vegetables not seasonings', function () {
    $bell = Ingredient::factory()->make(['name' => 'Bell Pepper (Red)', 'usda_food_category' => 'Vegetables']);
    $roasted = Ingredient::factory()->make(['name' => 'Roasted Red Bell Peppers (Base)', 'usda_food_category' => 'Base Ingredient']);
    $black = Ingredient::factory()->make(['name' => 'Black Pepper', 'usda_food_category' => 'Spices']);
    $cayenne = Ingredient::factory()->make(['name' => 'Cayenne Pepper', 'usda_food_category' => 'Spices']);
    $dressing = Ingredient::factory()->make(['name' => 'Red Pepper Dressing (Base)', 'usda_food_category' => 'Base Ingredient']);

    expect(MealIngredientDisplayOrder::groupRank($bell))->toBe(MealIngredientDisplayOrder::GROUP_VEGETABLES)
        ->and(MealIngredientDisplayOrder::groupRank($roasted))->toBe(MealIngredientDisplayOrder::GROUP_VEGETABLES)
        ->and(MealIngredientDisplayOrder::groupRank($black))->toBe(MealIngredientDisplayOrder::GROUP_HERBS_SPICES)
        ->and(MealIngredientDisplayOrder::groupRank($cayenne))->toBe(MealIngredientDisplayOrder::GROUP_HERBS_SPICES)
        ->and(MealIngredientDisplayOrder::groupRank($dressing))->toBe(MealIngredientDisplayOrder::GROUP_SAUCES);
});

test('classifies butternut squash as a vegetable not a fat', function () {
    $squash = Ingredient::factory()->make(['name' => 'Butternut Squash', 'usda_food_category' => 'Vegetables']);
    $butter = Ingredient::factory()->make(['name' => 'Grass Fed Butter', 'usda_food_category' => 'Fats']);
    $seeds = Ingredient::factory()->make(['name' => 'Pumpkin Seeds', 'usda_food_category' => 'Nuts']);

    expect(MealIngredientDisplayOrder::groupRank($squash))->toBe(MealIngredientDisplayOrder::GROUP_VEGETABLES)
        ->and(MealTiersLibraryPresentation::tiersLibraryGroupKey($squash))->toBe('carbs_veg')
        ->and(MealIngredientDisplayOrder::groupRank($butter))->toBe(MealIngredientDisplayOrder::GROUP_FATS)
        ->and(MealIngredientDisplayOrder::groupRank($seeds))->toBe(MealIngredientDisplayOrder::GROUP_FATS);
});
