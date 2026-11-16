<?php

use App\Support\QuinoaFlatbreadBaseRecipe;

test('quinoa flatbread is the only quinoa bread base in menu source files', function () {
    $mealsCsv = file_get_contents(database_path('data/menu/meals.csv'));
    $ingredientsCsv = file_get_contents(database_path('data/menu/ingredients.csv'));
    $overrides = file_get_contents(database_path('data/menu/library_refiner_overrides.php'));
    $rotation = file_get_contents(app_path('Services/BalancedRotationMealRecipeRefiner.php'));
    $sodium = file_get_contents(app_path('Services/BalancedSodiumRecipeRefiner.php'));

    expect($mealsCsv)->not->toContain('Quinoa Bread (Base)')
        ->and($ingredientsCsv)->not->toContain('Quinoa Bread (Base)')
        ->and($overrides)->not->toContain('Quinoa Bread (Base)')
        ->and($rotation)->not->toContain('Quinoa Bread (Base)')
        ->and($sodium)->not->toContain('Quinoa Bread (Base)')
        ->and($mealsCsv)->toContain('Quinoa Flatbread (Base):97.5')
        ->and($ingredientsCsv)->toContain('Quinoa Flatbread (Base)')
        ->and($rotation)->toContain("'Quinoa Flatbread (Base)' => 97.5");
});

test('sri lankan dal and eggplant stew meals use one flatbread serving in meals csv', function () {
    $mealsCsv = file_get_contents(database_path('data/menu/meals.csv'));

    expect($mealsCsv)->toMatch('/Vegan Sri Lankan Red Lentil Dal w Quinoa Bread.*Quinoa Flatbread \(Base\):97\.5/s')
        ->and($mealsCsv)->toMatch('/Eggplant & Ground Beef Stew w Quinoa Bread.*Quinoa Flatbread \(Base\):97\.5/s')
        ->and($mealsCsv)->toMatch('/Eggplant Beef Stew Quinoa Bread.*Quinoa Flatbread \(Base\):97\.5/s')
        ->and($mealsCsv)->toMatch('/Hummus Belaham wit Beet Salad.*Quinoa Flatbread \(Base\):97\.5/s');
});

test('quinoa flatbread serving grams remain one bread', function () {
    expect(QuinoaFlatbreadBaseRecipe::NAME)->toBe('Quinoa Flatbread (Base)')
        ->and(QuinoaFlatbreadBaseRecipe::gramsForBreadCount(1))->toBe(97.5)
        ->and(QuinoaFlatbreadBaseRecipe::SERVING_COMPONENTS)->not->toHaveKey('Baking Powder');
});
