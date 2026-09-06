<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedRotationMealRecipeRefiner;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealLibraryBulkNutrition;

test('saffron pumpkin muffin stores per-serving nutrition from a ten-serving batch', function () {
    $libraryMacros = [
        'Eggs (Large)' => ['calories' => 143, 'protein' => 12.6, 'carbs' => 0.7, 'fat' => 9.5],
        'Almond Flour (Base)' => ['calories' => 579, 'protein' => 21.2, 'carbs' => 21.7, 'fat' => 50],
        'Pumpkin Puree' => ['calories' => 34, 'protein' => 1, 'carbs' => 8, 'fat' => 0.1],
        'Honey (Raw)' => ['calories' => 304, 'protein' => 0.3, 'carbs' => 82.4, 'fat' => 0],
        'Saffron Threads' => ['calories' => 310, 'protein' => 11.4, 'carbs' => 65.4, 'fat' => 5.9],
        'Water (Filtered)' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Cinnamon' => ['calories' => 247, 'protein' => 4, 'carbs' => 80.6, 'fat' => 1.2],
        'Baking Powder' => ['calories' => 53, 'protein' => 0, 'carbs' => 27.7, 'fat' => 0],
        'Sea Salt' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
    ];

    $ingredients = [];

    foreach ($libraryMacros as $name => $macros) {
        $ingredients[$name] = Ingredient::factory()->create(array_merge(
            ['name' => $name, 'usda_food_category' => 'Pantry'],
            $macros,
        ));
    }

    $meal = Meal::factory()->create([
        'name' => 'Saffron Pumpkin Muffin',
        'is_bulk' => true,
        'servings_count' => BalancedRotationMealRecipeRefiner::SAFFRON_PUMPKIN_MUFFIN_BATCH_SERVINGS_COUNT,
    ]);

    $meal->ingredients()->sync([
        $ingredients['Almond Flour (Base)']->id => ['amount_grams' => 25],
        $ingredients['Eggs (Large)']->id => ['amount_grams' => 55],
    ]);

    app(BalancedRotationMealRecipeRefiner::class)->refine('Saffron Pumpkin Muffin');

    $meal->refresh()->load('ingredients');
    $batch = RecipeNutritionCalculator::fromMeal($meal);
    $display = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);

    expect($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe((float) BalancedRotationMealRecipeRefiner::SAFFRON_PUMPKIN_MUFFIN_BATCH_SERVINGS_COUNT)
        ->and($meal->nutrition_aggregates_synced)->toBeFalse()
        ->and((float) $meal->total_calories)->toBeGreaterThan(210.0)
        ->and((float) $meal->total_calories)->toBeLessThan(240.0)
        ->and(round((float) $display['calories'], 2))->toBe(round((float) $meal->total_calories, 2))
        ->and(abs(
            ($batch['calories'] / BalancedRotationMealRecipeRefiner::SAFFRON_PUMPKIN_MUFFIN_BATCH_SERVINGS_COUNT)
            - (float) $meal->total_calories
        ))->toBeLessThan(1.5)
        ->and((float) $meal->ingredients->firstWhere('name', 'Almond Flour (Base)')->pivot->amount_grams)
        ->toBe(250.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Pumpkin Puree')->pivot->amount_grams)
        ->toBe(350.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Eggs (Large)')->pivot->amount_grams)
        ->toBe(200.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Honey (Raw)')->pivot->amount_grams)
        ->toBe(120.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Baking Powder')->pivot->amount_grams)
        ->toBe(7.0);
});
