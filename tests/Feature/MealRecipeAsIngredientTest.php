<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;

test('meal library store does not create a derived ingredient when saving a meal', function () {
    $user = User::factory()->create();
    $base = Ingredient::query()->create([
        'name' => 'Sauce Base',
        'usda_food_category' => 'Other',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 4,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => ['fiber' => 2.0],
        'is_verified' => true,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.store'), [
            'name' => 'Bulk Sauce',
            'category' => 'Meal',
            'total_calories' => 100,
            'total_protein' => 10,
            'total_carbs' => 5,
            'total_fat' => 4,
            'ingredients' => [
                ['ingredient_id' => $base->id, 'amount_grams' => 1000],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'));

    $meal = Meal::query()->where('name', 'Bulk Sauce')->firstOrFail();

    expect(Ingredient::query()->where('source_meal_id', $meal->id)->exists())->toBeFalse();
});

test('meal library update does not sync a derived ingredient row', function () {
    $user = User::factory()->create();
    $base = Ingredient::query()->create([
        'name' => 'Oil',
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
        'name' => 'Dressing',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'description' => null,
        'total_calories' => 50,
        'total_protein' => 1,
        'total_carbs' => 2,
        'total_fat' => 4,
    ]);

    $meal->ingredients()->attach($base->id, [
        'amount_grams' => 50,
        'amount' => 50,
        'unit' => 'g',
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Dressing v2',
            'category' => 'Meal',
            'total_calories' => 60,
            'total_protein' => 2,
            'total_carbs' => 3,
            'total_fat' => 5,
            'ingredients' => [
                ['ingredient_id' => $base->id, 'amount_grams' => 60],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'));

    expect(Ingredient::query()->where('source_meal_id', $meal->id)->exists())->toBeFalse()
        ->and($meal->fresh()->name)->toBe('Dressing v2');
});
