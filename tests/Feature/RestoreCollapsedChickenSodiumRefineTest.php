<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\BalancedSodiumRecipeRefiner;
use App\Support\StandardMeatPortion;

test('sodium refine restores collapsed chicken even when the meal is UI-edit locked', function () {
    $chicken = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $potato = Ingredient::factory()->create([
        'name' => 'Sweet Potato',
        'calories' => 86,
        'protein' => 1.6,
        'carbs' => 20,
        'fat' => 0.1,
    ]);

    $meal = Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
        'library_edited_at' => now(),
    ]);

    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 2, 'amount' => 2, 'unit' => 'g'],
        $potato->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
    ]);

    $updated = app(BalancedSodiumRecipeRefiner::class)->refine();

    $meal->refresh()->load('ingredients');

    expect($updated)->toContain(BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME)
        ->and((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS);
});
