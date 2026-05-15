<?php

use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Livewire\Livewire;

test('base recipe meal type maps to Base Recipe recipe category', function () {
    expect(MealType::BaseRecipe->toRecipeCategory())->toBe(RecipeCategory::BaseRecipe);
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

test('livewire meal builder rejects base recipe meal type with ingredient library guidance', function () {
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

    Livewire::test('pages::meals')
        ->set('name', 'Hollandaise Base')
        ->set('mealType', MealType::BaseRecipe->value)
        ->set('recipeIngredients', [
            ['ingredient_id' => $egg->id, 'amount' => 50, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasErrors(['mealType']);

    expect(Meal::query()->where('name', 'Hollandaise Base')->exists())->toBeFalse();
});
