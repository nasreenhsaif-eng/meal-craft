<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\ChickenKitchenPlateTargets;
use App\Support\IngredientCookingYield;
use App\Support\KitchenPortionRounding;

test('chicken kitchen targets expose the shared protein curve and packaging constants', function () {
    expect(ChickenKitchenPlateTargets::cookedProteinGramsForTier(400))->toBe(100.0)
        ->and(ChickenKitchenPlateTargets::cookedProteinGramsForTier(500))->toBe(130.0)
        ->and(ChickenKitchenPlateTargets::cookedProteinGramsForTier(800))->toBe(225.0)
        ->and(ChickenKitchenPlateTargets::containerMl())->toBe(1000.0)
        ->and(ChickenKitchenPlateTargets::platePrepOilGrams())->toBe(5.0)
        ->and(ChickenKitchenPlateTargets::saladDressingGrams())->toBe(20.0)
        ->and(ChickenKitchenPlateTargets::designedCaloriesForTier(500))->toBe(500.0);
});

test('designed calories follow the meal protein family', function () {
    $fishMeal = new Meal(['name' => 'Grilled Salmon Mango Salsa']);
    $beefMeal = new Meal(['name' => 'Chili Beef Stuffed Peppers']);

    expect(ChickenKitchenPlateTargets::designedCaloriesForTier(500, $fishMeal))->toBe(506.0)
        ->and(ChickenKitchenPlateTargets::designedCaloriesForTier(500, $beefMeal))->toBe(506.0)
        ->and(ChickenKitchenPlateTargets::designedCaloriesForTier(500))->toBe(500.0);
});

test('raw salmon hamour shrimp and beef store raw grams that yield the cooked protein curve', function () {
    $salmon = new Ingredient(['name' => 'Salmon (Raw)']);
    $hamour = new Ingredient(['name' => 'Hamour Fillet']);
    $shrimp = new Ingredient(['name' => 'Shrimp (Raw)']);
    $beef = new Ingredient(['name' => 'Beef Ground Lean']);
    $cooked500 = ChickenKitchenPlateTargets::cookedProteinGramsForTier(500);
    $fishRaw500 = KitchenPortionRounding::snapFiveGramSteps(130.0 / IngredientCookingYield::FISH_RAW_TO_COOKED_YIELD);

    expect($cooked500)->toBe(130.0)
        ->and(ChickenKitchenPlateTargets::storedProteinGramsForIngredient($salmon, 500))->toBe($fishRaw500)
        ->and(ChickenKitchenPlateTargets::storedProteinGramsForIngredient($hamour, 500))->toBe($fishRaw500)
        ->and(ChickenKitchenPlateTargets::storedProteinGramsForIngredient($shrimp, 500))->toBe($fishRaw500)
        ->and(ChickenKitchenPlateTargets::storedProteinGramsForIngredient($beef, 500))
        ->toBe(KitchenPortionRounding::snapFiveGramSteps(130.0 / 0.75))
        ->and($fishRaw500)->toBeGreaterThan(130.0);
});

test('plated rice bases have a 100 g kitchen minimum at 400 kcal', function () {
    expect(ChickenKitchenPlateTargets::platedRiceBaseGramsForTier(400))->toBe(100.0)
        ->and(ChickenKitchenPlateTargets::platedRiceBaseGramsForTier(500))->toBe(130.0)
        ->and(ChickenKitchenPlateTargets::platedRiceBaseGramsForTier(800))->toBe(225.0)
        ->and(ChickenKitchenPlateTargets::isPlatedRiceBaseIngredient(new Ingredient(['name' => 'Steamed Basmati Rice (Base)', 'usda_food_category' => 'Base Ingredient'])))->toBeTrue()
        ->and(ChickenKitchenPlateTargets::isPlatedRiceBaseIngredient(new Ingredient(['name' => 'Roasted Mixed Vegetables (Base)', 'usda_food_category' => 'Base Ingredient'])))->toBeFalse();
});

test('beef meals use a lower plated rice minimum curve', function () {
    $beefMeal = new Meal(['name' => 'Persian Herb Beef Stew']);

    expect(ChickenKitchenPlateTargets::platedRiceBaseGramsForTier(400, $beefMeal))->toBe(100.0)
        ->and(ChickenKitchenPlateTargets::platedRiceBaseGramsForTier(500, $beefMeal))->toBe(100.0)
        ->and(ChickenKitchenPlateTargets::platedRiceBaseGramsForTier(800, $beefMeal))->toBe(125.0);
});

test('cherry tomatoes are capped near eight pieces for kitchen plates', function () {
    $tomato = new Ingredient(['name' => 'Cherry Tomatoes']);

    expect(ChickenKitchenPlateTargets::isCherryTomatoIngredient($tomato))->toBeTrue()
        ->and(ChickenKitchenPlateTargets::cherryTomatoCapGrams())->toBe(80.0);
});

test('zucchini is treated as loose noodle vegetables with a hard plate cap', function () {
    $zucchini = new Ingredient(['name' => 'Zucchini']);

    expect(ChickenKitchenPlateTargets::densityBandForIngredient($zucchini, new Meal(['name' => 'Pesto Chicken Koosa Noodles'])))
        ->toBe('noodle_veg')
        ->and(ChickenKitchenPlateTargets::noodleVegCapGrams())->toBe(250.0)
        ->and(ChickenKitchenPlateTargets::mlPerGram('noodle_veg'))->toBe(3.5);
});
