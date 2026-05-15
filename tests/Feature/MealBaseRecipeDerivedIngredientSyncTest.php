<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealRecipeAsIngredientSyncService;

test('base recipe category syncs derived ingredient with Base Recipe library category and per-100g macros', function () {
    $base = Ingredient::query()->create([
        'name' => 'Tomato Puree BR',
        'usda_food_category' => 'Vegetables',
        'calories' => 40,
        'protein' => 2,
        'carbs' => 8,
        'fat' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Marinara Base Sync',
        'category' => RecipeCategory::BaseRecipe,
        'meal_type' => MealType::BaseRecipe,
        'description' => null,
        'finished_weight_grams' => 500,
        'total_calories' => 200,
        'total_protein' => 10,
        'total_carbs' => 40,
        'total_fat' => 0,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 500,
        'amount' => 500,
        'unit' => 'g',
    ]);

    MealRecipeAsIngredientSyncService::syncFromPersistedMeal($meal->fresh(), false);

    $derived = Ingredient::query()->where('source_meal_id', $meal->id)->firstOrFail();

    expect($derived->usda_food_category)->toBe('Base Ingredient')
        ->and((float) $derived->calories)->toBe(40.0)
        ->and((float) $derived->protein)->toBe(2.0);
});

test('leaving base recipe category clears derived link when use as base is not requested', function () {
    $base = Ingredient::query()->create([
        'name' => 'Oil BR',
        'usda_food_category' => 'Fats',
        'calories' => 900,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Oil Blend',
        'category' => RecipeCategory::BaseRecipe,
        'meal_type' => MealType::BaseRecipe,
        'description' => null,
        'finished_weight_grams' => 100,
        'total_calories' => 900,
        'total_protein' => 0,
        'total_carbs' => 0,
        'total_fat' => 100,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 100,
        'amount' => 100,
        'unit' => 'g',
    ]);

    MealRecipeAsIngredientSyncService::syncFromPersistedMeal($meal->fresh(), false);
    $derivedId = Ingredient::query()->where('source_meal_id', $meal->id)->value('id');
    expect($derivedId)->not->toBeNull();

    $meal->update([
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    MealRecipeAsIngredientSyncService::syncFromPersistedMeal($meal->fresh(), false);

    expect(Ingredient::query()->find($derivedId)?->source_meal_id)->toBeNull();
});
