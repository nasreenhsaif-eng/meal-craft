<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedRotationMealRecipeRefiner;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealLibraryBulkNutrition;

test('chocolate orange brownie stores per-serving nutrition from a twenty-four-serving batch', function () {
    $libraryMacros = [
        'Almond Flour (Base)' => ['calories' => 579, 'protein' => 21.2, 'carbs' => 21.7, 'fat' => 50],
        'Egg' => ['calories' => 155, 'protein' => 12.6, 'carbs' => 1.1, 'fat' => 10.6],
        'Cocoa Powder' => ['calories' => 228, 'protein' => 19.6, 'carbs' => 57.9, 'fat' => 13.7],
        'Honey (Raw)' => ['calories' => 304, 'protein' => 0.3, 'carbs' => 82.4, 'fat' => 0],
        'Orange Juice' => ['calories' => 45, 'protein' => 0.7, 'carbs' => 10.4, 'fat' => 0.2],
        'Orange Zest' => ['calories' => 97, 'protein' => 1.5, 'carbs' => 25, 'fat' => 0.2],
        'Olive Oil' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
        'Walnuts' => ['calories' => 654, 'protein' => 15.2, 'carbs' => 13.7, 'fat' => 65.2],
    ];

    $ingredients = [];

    foreach ($libraryMacros as $name => $macros) {
        $ingredients[$name] = Ingredient::factory()->create(array_merge(
            ['name' => $name, 'usda_food_category' => 'Pantry'],
            $macros,
        ));
    }

    $meal = Meal::factory()->create([
        'name' => BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_NAME,
        'is_bulk' => true,
        'servings_count' => BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT,
    ]);

    $meal->ingredients()->sync([
        $ingredients['Almond Flour (Base)']->id => ['amount_grams' => 45],
        $ingredients['Egg']->id => ['amount_grams' => 55],
    ]);

    app(BalancedRotationMealRecipeRefiner::class)->refine(
        BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_NAME,
    );

    $meal->refresh()->load('ingredients');
    $batch = RecipeNutritionCalculator::fromMeal($meal);
    $display = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);

    expect($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe((float) BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT)
        ->and($meal->nutrition_aggregates_synced)->toBeFalse()
        ->and((float) $meal->total_calories)->toBeGreaterThan(220.0)
        ->and((float) $meal->total_calories)->toBeLessThan(270.0)
        ->and(round((float) $display['calories'], 2))->toBe(round((float) $meal->total_calories, 2))
        ->and(round($batch['calories'] / BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT, 2))
        ->toBe(round((float) $meal->total_calories, 2))
        ->and((float) $meal->ingredients->firstWhere('name', 'Almond Flour (Base)')->pivot->amount_grams)
        ->toBe(444.0);
});
