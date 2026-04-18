<?php

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Livewire\Livewire;

test('meal library can switch to list view and show meals in table', function () {
    Meal::query()->create([
        'name' => 'List View Meal',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::meals')
        ->assertSet('libraryViewMode', 'grid')
        ->set('libraryViewMode', 'list')
        ->assertSet('libraryViewMode', 'list')
        ->assertSee('List View Meal')
        ->assertSee(__('Meal name'));
});

test('meal library select all on page toggles selected meals', function () {
    Meal::query()->create([
        'name' => 'Bulk One',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
    ]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::meals');

    $component->call('toggleLibrarySelectAllVisible');
    expect($component->get('selectedMeals'))->not->toBeEmpty();

    $component->call('toggleLibrarySelectAllVisible');
    expect($component->get('selectedMeals'))->toBeEmpty();
});

test('meal library search filters by meal name and ingredient name', function () {
    $spinach = Ingredient::query()->create([
        'name' => 'Spinach',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
        'density' => 1.0,
        'is_verified' => true,
        'micronutrients' => [],
    ]);

    $spinachMeal = Meal::query()->create([
        'name' => 'Salmon Bowl',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
    ]);
    $spinachMeal->ingredients()->attach($spinach->id, ['amount_grams' => 50]);

    Meal::query()->create([
        'name' => 'Beef Stir Fry',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::meals')
        ->set('search', 'Spinach')
        ->assertSee('Salmon Bowl')
        ->assertDontSee('Beef Stir Fry')
        ->set('search', 'Salmon')
        ->assertSee('Salmon Bowl')
        ->assertDontSee('Beef Stir Fry');
});

test('meal builder ingredient combobox search uses contains matching', function () {
    Ingredient::query()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'density' => 0.92,
        'is_verified' => true,
        'micronutrients' => [],
    ]);
    Ingredient::query()->create([
        'name' => 'Coconut Oil',
        'calories' => 892,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'density' => 0.92,
        'is_verified' => true,
        'micronutrients' => [],
    ]);
    Ingredient::query()->create([
        'name' => 'Oilive',
        'calories' => 10,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'density' => 1.0,
        'is_verified' => true,
        'micronutrients' => [],
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::meals')
        ->set('recipeIngredientSearch.0', 'oil')
        ->assertSee('Olive Oil')
        ->assertSee('Coconut Oil')
        ->assertSee('Oilive');
});
