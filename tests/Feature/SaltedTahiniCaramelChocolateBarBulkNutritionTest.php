<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedRotationMealRecipeRefiner;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealLibraryBulkNutrition;

test('salted tahini caramel chocolate bar stores kitchen recipe batch for sixteen squares', function () {
    $libraryMacros = [
        'Almond Flour (Base)' => ['calories' => 579, 'protein' => 21.2, 'carbs' => 21.7, 'fat' => 50],
        'Coconut Oil' => ['calories' => 862, 'protein' => 0, 'carbs' => 0, 'fat' => 100],
        'Date Syrup' => ['calories' => 300, 'protein' => 1, 'carbs' => 75, 'fat' => 0],
        'Tahini' => ['calories' => 595, 'protein' => 17, 'carbs' => 21, 'fat' => 54],
        'Dark Chocolate' => ['calories' => 546, 'protein' => 4.9, 'carbs' => 61.1, 'fat' => 31.3],
        'Vanilla Pods' => ['calories' => 288, 'protein' => 0.1, 'carbs' => 12.7, 'fat' => 0.1],
        'Sea Salt' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Cocoa Powder' => ['calories' => 228, 'protein' => 19.6, 'carbs' => 57.9, 'fat' => 13.7],
    ];

    $ingredients = [];

    foreach ($libraryMacros as $name => $macros) {
        $ingredients[$name] = Ingredient::factory()->create(array_merge(
            ['name' => $name, 'usda_food_category' => 'Pantry'],
            $macros,
        ));
    }

    $meal = Meal::factory()->create([
        'name' => BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'is_bulk' => true,
        'servings_count' => BalancedRotationMealRecipeRefiner::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT,
    ]);

    $meal->ingredients()->sync([
        $ingredients['Cocoa Powder']->id => ['amount_grams' => 60],
        $ingredients['Almond Flour (Base)']->id => ['amount_grams' => 145],
    ]);

    app(BalancedRotationMealRecipeRefiner::class)->refine(
        BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
    );

    $meal->refresh()->load('ingredients');
    $batch = RecipeNutritionCalculator::fromMeal($meal);
    $display = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);

    expect($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe((float) BalancedRotationMealRecipeRefiner::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT)
        ->and($meal->ingredients->firstWhere('name', 'Cocoa Powder'))->toBeNull()
        ->and((float) $meal->ingredients->firstWhere('name', 'Almond Flour (Base)')->pivot->amount_grams)->toBe(168.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Coconut Oil')->pivot->amount_grams)->toBe(140.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Date Syrup')->pivot->amount_grams)->toBe(143.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Tahini')->pivot->amount_grams)->toBe(120.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Dark Chocolate')->pivot->amount_grams)->toBe(180.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Vanilla Pods')->pivot->amount_grams)->toBe(4.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Sea Salt')->pivot->amount_grams)->toBe(9.5)
        ->and(round((float) $display['calories'], 2))->toBe(round((float) $meal->total_calories, 2))
        ->and(abs(
            ($batch['calories'] / BalancedRotationMealRecipeRefiner::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT)
            - (float) $meal->total_calories
        ))->toBeLessThan(1.5);
});
