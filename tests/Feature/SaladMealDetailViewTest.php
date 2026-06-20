<?php

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\SaladDressingMealRefiner;
use Inertia\Testing\AssertableInertia;

test('salad meal detail view separates salad and dressing ingredients and instructions', function (): void {
    $user = User::factory()->create();

    foreach ([
        'Romaine Lettuce',
        'Tomato (Raw)',
        'Cucumber',
        'Bell Pepper (Red)',
        'Cabbage (Purple)',
        'Red Onion',
        'Classic Lemon Garlic Dressing (Base)',
    ] as $ingredientName) {
        Ingredient::query()->create([
            'name' => $ingredientName,
            'usda_food_category' => 'Vegetables',
            'calories' => 30,
            'protein' => 1,
            'carbs' => 5,
            'fat' => 0.5,
            'b6' => 0,
            'b9_folate' => 0,
            'b12' => 0,
            'iron' => 0,
            'magnesium' => 0,
            'micronutrients' => [],
            'is_verified' => true,
            'is_base_recipe' => str_contains($ingredientName, '(Base)'),
            'instructions' => str_contains($ingredientName, '(Base)')
                ? "Step 1: Whisk olive oil and lemon juice.\nStep 2: Stir in herbs."
                : null,
        ]);
    }

    Meal::query()->create([
        'name' => 'Classic Garden Salad',
        'category' => RecipeCategory::SideSalad,
        'instructions' => "1. Chop vegetables.\n2. Toss with dressing.",
        'total_calories' => 150,
        'total_protein' => 2,
        'total_carbs' => 10,
        'total_fat' => 8,
        'nutrition_aggregates_synced' => false,
    ]);

    app(SaladDressingMealRefiner::class)->refine('Classic Garden Salad');

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealLibrary')
            ->has('meals', 1)
            ->where('meals.0.title', 'Classic Garden Salad')
            ->has('meals.0.detailView.ingredientSections', 2)
            ->where('meals.0.detailView.ingredientSections.0.title', 'Salad')
            ->where('meals.0.detailView.ingredientSections.1.title', 'Dressing')
            ->has('meals.0.detailView.instructionSections', 2)
            ->where('meals.0.detailView.instructionSections.0.title', 'Dressing')
            ->where('meals.0.detailView.instructionSections.1.title', 'Salad'));
});
