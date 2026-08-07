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

test('sodium refine restores one-gram chicken on rosemary rocca salad', function () {
    $chicken = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $rocca = Ingredient::factory()->create([
        'name' => 'Rocca',
        'calories' => 25,
        'protein' => 2.6,
        'carbs' => 3.7,
        'fat' => 0.4,
    ]);
    $cucumber = Ingredient::factory()->create([
        'name' => 'Cucumber',
        'calories' => 15,
        'protein' => 0.7,
        'carbs' => 3.6,
        'fat' => 0.1,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Rosemary Chicken Rocca Salad',
        'library_edited_at' => now(),
    ]);

    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
        $rocca->id => ['amount_grams' => 40, 'amount' => 40, 'unit' => 'g'],
        $cucumber->id => ['amount_grams' => 55, 'amount' => 55, 'unit' => 'g'],
    ]);

    app(BalancedSodiumRecipeRefiner::class)->refine();

    $meal->refresh()->load('ingredients');

    expect((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $meal->ingredients->firstWhere('name', 'Cucumber')->pivot->amount_grams)
        ->toBe(55.0);
});
