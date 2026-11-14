<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryBulkNutrition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bulk meal nutritionForDisplay returns per-serving macros even when persisted totals are batch-sized', function () {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Test Flour',
        'calories' => 400,
        'protein' => 10,
        'carbs' => 80,
        'fat' => 2,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Batch Dessert Test',
        'is_bulk' => true,
        'servings_count' => 16,
        'total_calories' => 3200,
        'total_protein' => 160,
        'total_carbs' => 1280,
        'total_fat' => 32,
    ]);

    $meal->ingredients()->sync([
        $ingredient->id => ['amount_grams' => 100],
    ]);

    $meal->refresh()->load('ingredients');

    $display = $meal->nutritionForDisplay();
    $expected = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);

    expect(round((float) $display['calories'], 2))->toBe(round((float) $expected['calories'], 2))
        ->and((float) $display['calories'])->toBeLessThan(300.0);
});
