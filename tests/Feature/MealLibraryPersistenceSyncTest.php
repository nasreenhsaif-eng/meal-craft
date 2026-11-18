<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\BalancedEggBreakfastRecipeRefiner;
use App\Services\MealLibraryPersistenceSync;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\MenuDevelopmentCsv;
use Tests\Support\IsolatesMenuDevelopmentCsv;

uses(IsolatesMenuDevelopmentCsv::class);

beforeEach(function (): void {
    $this->setUpIsolatedMenuDevelopmentCsvPaths();
});

test('meal library update writes csv and refiner override snapshots', function () {
    $user = User::factory()->create();

    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'is_verified' => true,
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);

    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'is_verified' => true,
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Smashed Beans & Eggs',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'instructions' => 'Heat olive oil and fry eggs until crisp.',
        'total_calories' => 250,
        'total_protein' => 20,
        'total_carbs' => 18,
        'total_fat' => 12,
        'nutrition_aggregates_synced' => false,
        'library_edited_at' => now(),
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-library.update', $meal), [
            'name' => 'Smashed Beans & Eggs',
            'total_calories' => 260,
            'total_protein' => 21,
            'total_carbs' => 18,
            'total_fat' => 13,
            'category' => 'Breakfast',
            'description' => 'Heat olive oil and fry eggs until crisp.',
            'ingredients' => [
                [
                    'ingredient_id' => $egg->id,
                    'name' => $egg->name,
                    'amount_grams' => 110,
                ],
                [
                    'ingredient_id' => $oil->id,
                    'name' => $oil->name,
                    'amount_grams' => 10,
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-library'));

    $csv = file_get_contents(MenuDevelopmentCsv::mealsPath()) ?: '';
    expect($csv)->toContain('Smashed Beans & Eggs')
        ->and($csv)->toContain('Olive Oil:10');

    $overrides = MealLibraryRefinerOverrides::all();
    expect($overrides)->toHaveKey('Smashed Beans & Eggs')
        ->and($overrides['Smashed Beans & Eggs']['ingredients']['Olive Oil'])->toBe(10.0)
        ->and($overrides['Smashed Beans & Eggs']['instructions'])->toContain('Heat olive oil');
});

test('egg breakfast refiner merges library override snapshots over built-in definitions', function () {
    MealLibraryRefinerOverrides::put('Smashed Beans & Eggs', [
        'ingredients' => [
            'Smashed White Beans (Base)' => 80,
            'Egg' => 110,
            'Tomato (Raw)' => 50,
            'Fresh Coriander' => 4,
            'Olive Oil' => 10,
        ],
        'instructions' => 'Heat olive oil and fry eggs until crisp.',
        'synced_at' => now()->toIso8601String(),
    ]);

    $definitions = (new ReflectionClass(BalancedEggBreakfastRecipeRefiner::class))
        ->getMethod('recipeDefinitions')
        ->invoke(new BalancedEggBreakfastRecipeRefiner);

    expect($definitions['Smashed Beans & Eggs']['ingredients']['Olive Oil'])->toBe(10.0)
        ->and($definitions['Smashed Beans & Eggs']['ingredients']['Egg'])->toBe(110.0);
});

test('meal library persistence sync exports managed meals only to refiner overrides', function () {
    $meal = Meal::factory()->create([
        'name' => 'Non Rotation Custom Meal '.uniqid(),
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    app(MealLibraryPersistenceSync::class)->afterMealSaved($meal);

    expect(MealLibraryRefinerOverrides::all())->not->toHaveKey($meal->name);
});
