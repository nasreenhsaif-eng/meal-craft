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

test('groups bell pepper with carbs and vegetables not seasonings', function () {
    $chicken = Ingredient::factory()->make(['name' => 'Chicken Breast', 'usda_food_category' => 'Proteins']);
    $bell = Ingredient::factory()->make(['name' => 'Bell Pepper (Red)', 'usda_food_category' => 'Vegetables']);
    $black = Ingredient::factory()->make(['name' => 'Black Pepper', 'usda_food_category' => 'Spices']);
    $chicken->setRelation('pivot', (object) ['amount_grams' => 150]);
    $bell->setRelation('pivot', (object) ['amount_grams' => 35]);
    $black->setRelation('pivot', (object) ['amount_grams' => 1]);

    $sections = MealTiersLibraryPresentation::ingredientSectionsFromIngredients([
        $black,
        $bell,
        $chicken,
    ]);

    expect(MealTiersLibraryPresentation::tiersLibraryGroupKey($bell))->toBe('carbs_veg')
        ->and(MealTiersLibraryPresentation::tiersLibraryGroupKey($black))->toBe('seasoning')
        ->and(array_column($sections, 'title'))->toBe([
            'Protein',
            'Carbs and vegetables',
            'Seasonings',
        ]);
});

test('groups tiers library ingredients into protein carbs-veg fats-sauces and seasonings', function () {
    $chicken = Ingredient::factory()->make(['name' => 'Chicken Breast', 'usda_food_category' => 'Proteins']);
    $rice = Ingredient::factory()->make(['name' => 'Brown Rice', 'usda_food_category' => 'Grains', 'calories' => 360]);
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
    ])
        ->and($sections[0]['items'][0]['line'])->toContain('raw, before cooking')
        ->and($sections[0]['items'][0]['ingredientId'])->toBe((int) $chicken->id)
        ->and($sections[0]['items'][0]['isBaseRecipe'])->toBeFalse()
        ->and($sections[1]['items'][0]['line'])->toContain('dry weight');
});

test('structured tier sections mark prepared base ingredients as clickable base recipes', function () {
    $base = Ingredient::factory()->make([
        'id' => 901,
        'name' => 'Beef Shawarma (Base)',
        'usda_food_category' => 'Base Ingredient',
    ]);
    $base->setRelation('pivot', (object) ['amount_grams' => 112]);
    $hummus = Ingredient::factory()->make([
        'id' => 902,
        'name' => 'Creamy Cumin Hummus (Base)',
        'usda_food_category' => 'Base Ingredient',
    ]);
    $hummus->setRelation('pivot', (object) ['amount_grams' => 70]);

    $sections = MealTiersLibraryPresentation::ingredientSectionsFromIngredients([$hummus, $base]);

    expect($sections[0]['items'][0])->toMatchArray([
        'line' => '112g Beef Shawarma (cooked plated portion)',
        'ingredientId' => 901,
        'isBaseRecipe' => true,
    ])
        ->and($sections[1]['items'][0])->toMatchArray([
            'line' => '70g Creamy Cumin Hummus (cooked plated portion)',
            'ingredientId' => 902,
            'isBaseRecipe' => true,
        ]);
});

test('labels pre-cooked chicken bases on tiers ingredient lines', function () {
    $base = Ingredient::factory()->make([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'usda_food_category' => 'Base Ingredient',
        'is_base_recipe' => true,
    ]);
    $base->setRelation('pivot', (object) ['amount_grams' => 100]);

    expect(MealTiersLibraryPresentation::ingredientAmountLine($base, 100.0))
        ->toBe('100g Rosemary Garlic Chicken (cooked plated portion)');
});

test('formats egg ingredients as raw egg counts on tiers ingredient lines', function () {
    $egg = Ingredient::factory()->make([
        'name' => 'Egg',
        'usda_food_category' => 'Protein',
        'calories' => 155,
    ]);

    expect(MealTiersLibraryPresentation::ingredientAmountLine($egg, 55.0))
        ->toBe('1 egg raw')
        ->and(MealTiersLibraryPresentation::ingredientAmountLine($egg, 100.0))
        ->toBe('2 eggs raw');
});
