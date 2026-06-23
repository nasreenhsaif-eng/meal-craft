<?php

use App\Enums\CyclePhase;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Support\MealImagePath;
use Inertia\Testing\AssertableInertia;

test('meal library index includes detailView on each meal for the detail modal', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'Detail Payload Oats',
        'is_verified' => true,
        'calories' => 100,
        'protein' => 3,
        'carbs' => 18,
        'fat' => 2,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Detail Payload Meal',
        'instructions' => "Step one\nStep two",
        'short_description' => 'Great for mornings.',
        'description' => "Step one\nStep two",
        'highlight' => 'Great for mornings.',
        'meal_plan_tags' => ['Balanced', 'Ketogenic'],
        'meal_plan_tag' => 'Balanced',
        'diet_tags' => ['Vegan'],
        'cycle_phases' => ['follicular', 'ovulatory'],
        'cycle_phase' => CyclePhase::Follicular,
        'total_calories' => 200,
        'image_path' => 'images/meals/placeholder.svg',
        'total_protein' => 6,
        'total_carbs' => 36,
        'total_fat' => 4,
        'nutrition_aggregates_synced' => false,
    ]);

    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 50]);

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealLibrary')
            ->has('meals', 1)
            ->where('meals.0.title', 'Detail Payload Meal')
            ->where('meals.0.macros.calories', 50)
            ->where('meals.0.detailView.nutritionalData.sections.0.rows.0.value', '50')
            ->has('meals.0.detailView')
            ->where('meals.0.detailView.shortDescription', 'Great for mornings.')
            ->has('meals.0.detailView.sickleCellHighlights')
            ->where('meals.0.detailView.hasG6pdTrigger', false)
            ->where('meals.0.detailView.imageAlt', 'Detail Payload Meal')
            ->where('meals.0.detailView.imageUrl', MealImagePath::resolveUrl('images/meals/placeholder.svg'))
            ->where('meals.0.detailView.cyclePhases', ['Follicular', 'Ovulatory'])
            ->where('meals.0.detailView.dietaryTags', ['Balanced', 'Ketogenic', 'Vegan'])
            ->has('meals.0.detailView.ingredients', 1)
            ->has('meals.0.detailView.instructions', 2)
            ->has('meals.0.detailView.nutritionalData.sections', 3)
            ->has('meals.0.editForm')
            ->where('meals.0.editForm.name', 'Detail Payload Meal')
            ->where('meals.0.editForm.mealPlanTags', ['Balanced', 'Ketogenic'])
            ->where('meals.0.editForm.cyclePhaseValues', ['follicular', 'ovulatory'])
            ->where('meals.0.editForm.isBulk', false)
            ->where('meals.0.editForm.servingsCount', ''));
});

test('meal library meal card macros use stored per-serving totals when meal is bulk', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'name' => 'High Cal Bulk Ingredient',
        'is_verified' => true,
        'calories' => 500,
        'protein' => 10,
        'carbs' => 50,
        'fat' => 30,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Bulk Macro Card Meal',
        'description' => null,
        'highlight' => null,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::fromRecipeCategory(RecipeCategory::Meal),
        'total_calories' => 167,
        'total_protein' => 3.8,
        'total_carbs' => 21.5,
        'total_fat' => 9.2,
        'is_bulk' => true,
        'servings_count' => 16,
        'nutrition_aggregates_synced' => false,
    ]);

    $meal->ingredients()->attach($ingredient->id, [
        'amount_grams' => 5000,
        'amount' => 5000,
        'unit' => 'g',
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealLibrary')
            ->has('meals', 1)
            ->where('meals.0.title', 'Bulk Macro Card Meal')
            ->where('meals.0.macros.calories', 167)
            ->where('meals.0.macros.protein', 3.8)
            ->where('meals.0.macros.carbs', 21.5)
            ->where('meals.0.macros.fat', 9.2));
});
