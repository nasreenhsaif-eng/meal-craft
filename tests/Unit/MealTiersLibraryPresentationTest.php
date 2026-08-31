<?php

use App\Models\Ingredient;
use App\Support\MealTiersLibraryPresentation;

test('orders tiers library ingredients protein then carbs and vegetables then fats and sauces then seasonings', function () {
    $pepper = Ingredient::factory()->make(['name' => 'Black Pepper', 'usda_food_category' => 'Spices and Herbs']);
    $oil = Ingredient::factory()->make(['name' => 'Olive Oil', 'usda_food_category' => 'Fats']);
    $spinach = Ingredient::factory()->make(['name' => 'Spinach', 'usda_food_category' => 'Vegetables']);
    $rice = Ingredient::factory()->make(['name' => 'Brown Rice', 'usda_food_category' => 'Grains']);
    $chicken = Ingredient::factory()->make(['name' => 'Chicken Breast', 'usda_food_category' => 'Proteins']);
    $sauce = Ingredient::factory()->make(['name' => 'Garlic Sauce', 'usda_food_category' => 'Condiments']);

    $sorted = MealTiersLibraryPresentation::sortedIngredients([
        $pepper,
        $oil,
        $spinach,
        $rice,
        $chicken,
        $sauce,
    ]);

    expect(array_map(fn (Ingredient $ingredient): string => $ingredient->name, $sorted))->toBe([
        'Chicken Breast',
        'Brown Rice',
        'Spinach',
        'Olive Oil',
        'Garlic Sauce',
        'Black Pepper',
    ]);
});

test('keeps rosemary garlic chicken base in the protein group', function () {
    $chicken = Ingredient::factory()->make([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'usda_food_category' => 'Base Ingredient',
    ]);
    $garlic = Ingredient::factory()->make(['name' => 'Garlic (Raw)', 'usda_food_category' => 'Vegetables']);
    $pepper = Ingredient::factory()->make(['name' => 'Black Pepper', 'usda_food_category' => 'Spices and Herbs']);

    $sorted = MealTiersLibraryPresentation::sortedIngredients([$pepper, $garlic, $chicken]);

    expect(MealTiersLibraryPresentation::tiersLibraryGroupKey($chicken))->toBe('protein')
        ->and(MealTiersLibraryPresentation::tiersLibraryGroupKey($garlic))->toBe('seasoning')
        ->and(array_map(fn (Ingredient $ingredient): string => $ingredient->name, $sorted))->toBe([
            'Rosemary Garlic Chicken (Base)',
            'Black Pepper',
            'Garlic (Raw)',
        ]);
});

test('groups tiers library ingredients into protein carbs-veg fats-sauces and seasonings', function () {
    $chicken = Ingredient::factory()->make(['name' => 'Chicken Breast', 'usda_food_category' => 'Proteins']);
    $rice = Ingredient::factory()->make(['name' => 'Brown Rice', 'usda_food_category' => 'Grains']);
    $pepper = Ingredient::factory()->make(['name' => 'Black Pepper', 'usda_food_category' => 'Spices and Herbs']);
    $chicken->setRelation('pivot', (object) ['amount_grams' => 150]);
    $rice->setRelation('pivot', (object) ['amount_grams' => 100]);
    $pepper->setRelation('pivot', (object) ['amount_grams' => 1]);

    $sections = MealTiersLibraryPresentation::ingredientSectionsFromIngredients([
        $pepper,
        $rice,
        $chicken,
    ]);

    expect(array_column($sections, 'title'))->toBe([
        'Protein',
        'Carbs and vegetables',
        'Seasonings',
    ]);
});
