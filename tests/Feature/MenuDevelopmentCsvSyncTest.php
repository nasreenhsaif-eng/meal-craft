<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Support\MenuDevelopmentCsv;
use Livewire\Livewire;
use Tests\Support\IsolatesMenuDevelopmentCsv;

uses(IsolatesMenuDevelopmentCsv::class);

beforeEach(function (): void {
    $this->setUpIsolatedMenuDevelopmentCsvPaths();
});

test('livewire meal builder syncs meals csv after save', function () {
    $user = User::factory()->create();

    $ingredient = Ingredient::factory()->create([
        'name' => 'Csv Sync Test Ingredient '.uniqid(),
        'is_verified' => true,
    ]);

    $mealName = 'Csv Sync Test Meal '.uniqid();

    Livewire::actingAs($user)
        ->test('pages::meals')
        ->set('name', $mealName)
        ->set('mealType', MealType::Main->value)
        ->set('recipeIngredients', [
            [
                'ingredient_id' => $ingredient->id,
                'amount' => 100,
                'unit' => 'g',
            ],
        ])
        ->set('instructions', 'Cook and serve.')
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $csv = file_get_contents(MenuDevelopmentCsv::mealsPath()) ?: '';

    expect($csv)->toContain($mealName);
});

test('livewire meal delete syncs meals csv so deleted meals are not restored on seed', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Csv Sync Delete Meal '.uniqid(),
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 200,
        'total_protein' => 10,
        'total_carbs' => 20,
        'total_fat' => 5,
        'nutrition_aggregates_synced' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::meals')
        ->call('deleteMeal', $meal->id)
        ->assertHasNoErrors();

    $csv = file_get_contents(MenuDevelopmentCsv::mealsPath()) ?: '';

    expect($csv)->not->toContain($meal->name);
});

test('menu backup git command exports csv without committing when tree is clean', function () {
    $meal = Meal::query()->create([
        'name' => 'Backup Command Meal '.uniqid(),
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'total_calories' => 200,
        'total_protein' => 10,
        'total_carbs' => 20,
        'total_fat' => 5,
        'nutrition_aggregates_synced' => false,
    ]);

    $this->artisan('menu:export-csv')->assertSuccessful();

    $csv = file_get_contents(MenuDevelopmentCsv::mealsPath()) ?: '';
    expect($csv)->toContain($meal->name);
});
