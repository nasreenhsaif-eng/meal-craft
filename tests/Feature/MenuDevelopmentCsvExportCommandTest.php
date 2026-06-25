<?php

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MenuDevelopmentCsv;
use Database\Seeders\MenuDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\IsolatesMenuDevelopmentCsv;

uses(RefreshDatabase::class, IsolatesMenuDevelopmentCsv::class);

beforeEach(function (): void {
    $this->setUpIsolatedMenuDevelopmentCsvPaths();
});

test('menu export csv command writes production schema master files from live database', function (): void {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Export Test Ingredient',
        'is_verified' => true,
        'usda_food_category' => 'Produce',
        'calories' => 50,
        'protein' => 2,
        'carbs' => 10,
        'fat' => 1,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Export Test Meal',
        'category' => RecipeCategory::Meal,
        'target_calories' => 400,
        'target_protein' => 30,
        'target_carbs' => 35,
        'target_fat' => 12,
        'short_description' => 'Short summary for export.',
        'instructions' => 'Cook and serve.',
        'image_path' => 'images/meals/export_test_meal.png',
        'meal_plan_tag' => 'Balanced',
        'safety_alert_tags' => ['Peanuts'],
    ]);

    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 100]);

    $ingredientsPath = MenuDevelopmentCsv::ingredientsPath();
    $mealsPath = MenuDevelopmentCsv::mealsPath();

    $this->artisan('menu:export-csv')->assertSuccessful();

    expect($ingredientsPath)->toBeReadableFile()
        ->and($mealsPath)->toBeReadableFile();

    $ingredientsCsv = file_get_contents($ingredientsPath) ?: '';
    $mealsCsv = file_get_contents($mealsPath) ?: '';

    expect($ingredientsCsv)->toContain(implode(',', MenuDevelopmentCsv::INGREDIENT_HEADERS))
        ->and($ingredientsCsv)->toContain('Export Test Ingredient')
        ->and($mealsCsv)->toContain(implode(',', MenuDevelopmentCsv::MEAL_HEADERS))
        ->and($mealsCsv)->toContain('Export Test Meal')
        ->and($mealsCsv)->toContain('Export Test Ingredient:100')
        ->and($mealsCsv)->toContain('images/meals/export_test_meal.png')
        ->and($mealsCsv)->toContain('Short summary for export.')
        ->and($mealsCsv)->toContain('Cook and serve.')
        ->and($mealsCsv)->toContain('Balanced')
        ->and($mealsCsv)->toContain('Peanuts');
});

test('exported menu csv files round trip through menu development seeder', function (): void {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Round Trip Ingredient',
        'is_verified' => true,
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 2,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Round Trip Meal',
        'category' => RecipeCategory::Breakfast,
        'target_calories' => 350,
        'short_description' => 'Morning bowl.',
        'instructions' => 'Mix and eat.',
    ]);

    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 50]);

    $this->artisan('menu:export-csv')->assertSuccessful();

    Meal::query()->forceDelete();
    Ingredient::query()->delete();

    $this->seed(MenuDevelopmentSeeder::class);

    $restoredMeal = Meal::query()->where('name', 'Round Trip Meal')->first();
    $restoredIngredient = Ingredient::query()->where('name', 'Round Trip Ingredient')->first();

    expect($restoredMeal)->not->toBeNull()
        ->and($restoredIngredient)->not->toBeNull()
        ->and($restoredMeal->category)->toBe(RecipeCategory::Breakfast)
        ->and($restoredMeal->target_calories)->toBe(350.0)
        ->and($restoredMeal->short_description)->toBe('Morning bowl.')
        ->and($restoredMeal->ingredients)->toHaveCount(1);
});
