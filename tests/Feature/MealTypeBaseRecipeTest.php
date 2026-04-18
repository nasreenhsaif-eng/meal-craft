<?php

use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Livewire\Livewire;

test('base recipe meal type maps to Meal recipe category', function () {
    expect(MealType::BaseRecipe->toRecipeCategory())->toBe(RecipeCategory::Meal);
});

test('selecting base recipe in meal creator checks use as base ingredient', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::meals')
        ->set('useRecipeAsIngredient', false)
        ->set('mealType', MealType::BaseRecipe->value)
        ->assertSet('useRecipeAsIngredient', true);
});

test('meal plan slot picker excludes base recipe meals even if mis-tagged', function () {
    $this->actingAs(User::factory()->create());

    Meal::query()->create([
        'name' => 'Misassigned Base',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::BaseRecipe,
        'description' => null,
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 10,
        'total_fat' => 3,
    ]);

    Meal::query()->create([
        'name' => 'Real Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'description' => null,
        'total_calories' => 400,
        'total_protein' => 30,
        'total_carbs' => 40,
        'total_fat' => 15,
    ]);

    $names = Livewire::test('pages::meal-plans')
        ->set('editSlotType', MealPlanSlotType::Main->value)
        ->set('editMealSearch', '')
        ->get('editSlotMeals')
        ->pluck('name')
        ->all();

    expect($names)->toContain('Real Main')
        ->not->toContain('Misassigned Base');
});

test('editing a base recipe meal pre-checks use as base ingredient without derived row yet', function () {
    $this->actingAs(User::factory()->create());

    $egg = Ingredient::query()->create([
        'name' => 'Egg BR',
        'usda_food_category' => 'Other',
        'calories' => 140,
        'protein' => 12,
        'carbs' => 1,
        'fat' => 10,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Hollandaise Base',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::BaseRecipe,
        'description' => null,
        'total_calories' => 50,
        'total_protein' => 5,
        'total_carbs' => 5,
        'total_fat' => 2,
    ]);

    $meal->ingredients()->sync([
        $egg->id => ['amount' => 50, 'unit' => 'g', 'amount_grams' => 50],
    ]);

    Livewire::test('pages::meals', ['meal' => $meal->fresh()])
        ->assertSet('useRecipeAsIngredient', true)
        ->assertSet('mealType', MealType::BaseRecipe->value);
});
