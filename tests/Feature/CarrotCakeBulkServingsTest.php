<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedMealInstructionRefiner;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealLibraryBulkNutrition;

test('carrot cake stores per-serving nutrition from a sixteen-serving batch', function () {
    $libraryMacros = [
        'Medjool Dates' => ['calories' => 277, 'protein' => 1.8, 'carbs' => 75, 'fat' => 0.2],
        'Almond Flour (Base)' => ['calories' => 579, 'protein' => 21.2, 'carbs' => 21.7, 'fat' => 50],
        'Carrots' => ['calories' => 41, 'protein' => 0.9, 'carbs' => 9.6, 'fat' => 0.2],
        'Water (Filtered)' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Cinnamon' => ['calories' => 247, 'protein' => 4, 'carbs' => 80.6, 'fat' => 1.2],
        'Walnuts' => ['calories' => 654, 'protein' => 15.2, 'carbs' => 13.7, 'fat' => 65.2],
        'Grass Fed Butter' => ['calories' => 717, 'protein' => 0.9, 'carbs' => 0.1, 'fat' => 81.1],
        'Pumpkin Puree' => ['calories' => 34, 'protein' => 1.1, 'carbs' => 8.1, 'fat' => 0.1],
        'Ground Ginger' => ['calories' => 335, 'protein' => 9, 'carbs' => 71.6, 'fat' => 4.2],
        'Nutmeg' => ['calories' => 525, 'protein' => 6, 'carbs' => 49.2, 'fat' => 36.3],
        'Eggs (Large)' => ['calories' => 143, 'protein' => 12.6, 'carbs' => 0.7, 'fat' => 9.5],
        'Baking Soda' => ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0],
        'Vanilla Pods' => ['calories' => 288, 'protein' => 0.1, 'carbs' => 12.7, 'fat' => 0.1],
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
        'name' => BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        'is_bulk' => true,
        'servings_count' => BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_SERVINGS_COUNT,
    ]);

    $meal->ingredients()->sync([
        $ingredients['Almond Flour (Base)']->id => ['amount_grams' => 30],
        $ingredients['Eggs (Large)']->id => ['amount_grams' => 19],
    ]);

    app(BalancedCanonicalMealRecipeRefiner::class)->refine(
        BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
    );
    app(BalancedMealInstructionRefiner::class)->refine();

    $meal->refresh()->load('ingredients');
    $batch = RecipeNutritionCalculator::fromMeal($meal);
    $display = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);

    expect(BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_SERVINGS_COUNT)->toBe(16)
        ->and($meal->is_bulk)->toBeTrue()
        ->and((float) $meal->servings_count)->toBe(16.0)
        ->and($meal->short_description)->toContain('16 slices')
        ->and((string) $meal->instructions)->toContain('cut into 16 equal slices')
        ->and(round((float) $display['calories'], 2))->toBe(round((float) $meal->total_calories, 2))
        ->and(round($batch['calories'] / BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_SERVINGS_COUNT, 2))
        ->toBe(round((float) $meal->total_calories, 2))
        ->and((float) $meal->ingredients->firstWhere('name', 'Almond Flour (Base)')->pivot->amount_grams)
        ->toBe(240.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Medjool Dates')->pivot->amount_grams)
        ->toBe(360.0)
        ->and((float) $meal->ingredients->firstWhere('name', 'Walnuts')->pivot->amount_grams)
        ->toBe(64.0);
});
