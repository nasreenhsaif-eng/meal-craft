<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedMealInstructionRefiner;
use App\Services\BalancedRotationMealRecipeRefiner;
use App\Services\RecipeNutritionCalculator;
use App\Support\LiquidIngredientPresentation;
use App\Support\MealLibraryBulkNutrition;

test('salted tahini caramel chocolate bar stores realistic batch grams and per-serving macros', function () {
    $libraryMacros = [
        'Almond Flour (Base)' => ['calories' => 579, 'protein' => 21.2, 'carbs' => 21.7, 'fat' => 50, 'density' => 1],
        'Cocoa Powder' => ['calories' => 228, 'protein' => 19.6, 'carbs' => 57.9, 'fat' => 13.7, 'density' => 1],
        'Coconut Oil' => ['calories' => 862, 'protein' => 0, 'carbs' => 0, 'fat' => 100, 'density' => 0.92, 'usda_food_category' => 'Fats'],
        'Date Syrup' => ['calories' => 300, 'protein' => 1, 'carbs' => 75, 'fat' => 0.1, 'density' => 1.3, 'usda_food_category' => 'Sweeteners'],
        'Tahini' => ['calories' => 595, 'protein' => 17, 'carbs' => 21.2, 'fat' => 53.8, 'density' => 1, 'usda_food_category' => 'Fats/Nuts'],
        'Sea Salt' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'density' => 1],
        'Vanilla Pods' => ['calories' => 288, 'protein' => 0.06, 'carbs' => 12.65, 'fat' => 0.06, 'density' => 1],
    ];

    $ingredients = [];

    foreach ($libraryMacros as $name => $macros) {
        $ingredients[$name] = Ingredient::factory()->create(array_merge(
            ['name' => $name, 'usda_food_category' => $macros['usda_food_category'] ?? 'Pantry'],
            $macros,
        ));
    }

    $meal = Meal::factory()->create([
        'name' => BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'is_bulk' => true,
        'servings_count' => BalancedRotationMealRecipeRefiner::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT,
        'target_calories' => 300,
        'target_protein' => 6,
        'target_carbs' => 32,
        'target_fat' => 16,
        'short_description' => 'Three-layer 8x8 no-bake bar (16 squares).',
    ]);

    $meal->ingredients()->sync([
        $ingredients['Almond Flour (Base)']->id => ['amount_grams' => 10],
        $ingredients['Coconut Oil']->id => ['amount_grams' => 5],
    ]);

    app(BalancedRotationMealRecipeRefiner::class)->refine(
        BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
    );

    $meal->refresh()->load('ingredients');
    $batch = RecipeNutritionCalculator::fromMeal($meal);
    $display = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);
    $servings = BalancedRotationMealRecipeRefiner::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT;

    $oilGrams = (float) $meal->ingredients->firstWhere('name', 'Coconut Oil')->pivot->amount_grams;
    $syrupGrams = (float) $meal->ingredients->firstWhere('name', 'Date Syrup')->pivot->amount_grams;
    $saltGrams = (float) $meal->ingredients->firstWhere('name', 'Sea Salt')->pivot->amount_grams;

    expect($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe((float) $servings)
        ->and($meal->nutrition_aggregates_synced)->toBeFalse()
        ->and($meal->short_description)->not->toContain('no-bake')
        ->and($meal->short_description)->toContain('baked almond crust')
        ->and($oilGrams)->toBe(110.4)
        ->and($syrupGrams)->toBe(136.5)
        ->and($saltGrams)->toBe(4.0)
        ->and(round($oilGrams / 0.92 / 15, 4))->toBe(8.0)
        ->and(round($syrupGrams / 1.3 / 15, 4))->toBe(7.0)
        ->and((float) $meal->total_calories)->toBeGreaterThan(180.0)
        ->and((float) $meal->total_calories)->toBeLessThan(200.0)
        ->and(round((float) $display['calories'], 2))->toBe(round((float) $meal->total_calories, 2))
        ->and(round($batch['calories'] / $servings, 2))->toBe(round((float) $meal->total_calories, 2))
        ->and(round($batch['protein'] / $servings, 2))->toBe(round((float) $meal->total_protein, 2))
        ->and(round($batch['carbs'] / $servings, 2))->toBe(round((float) $meal->total_carbs, 2))
        ->and(round($batch['fat'] / $servings, 2))->toBe(round((float) $meal->total_fat, 2));

    $oil = $ingredients['Coconut Oil']->fresh();
    expect(round(LiquidIngredientPresentation::millilitersFromGrams($oilGrams / $servings, $oil), 1))
        ->toBe(round(($oilGrams / $servings) / 0.92, 1));
});

test('salted tahini caramel chocolate bar instructions allocate exact oil syrup and salt splits', function () {
    $meal = Meal::factory()->create([
        'name' => BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'instructions' => 'old instructions',
    ]);

    app(BalancedMealInstructionRefiner::class)->refine();

    $meal->refresh();

    expect($meal->instructions)
        ->toContain('3 tablespoons coconut oil')
        ->toContain('2 tablespoons date syrup')
        ->toContain('4 tablespoons date syrup')
        ->toContain('2 tablespoons coconut oil')
        ->toContain('1 tablespoon date syrup')
        ->toContain('1/2 teaspoon sea salt')
        ->not->toContain('a little date syrup')
        ->not->toContain('remaining coconut oil');
});
