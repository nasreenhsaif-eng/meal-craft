<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use App\Services\RecipeNutritionCalculator;
use App\Support\IngredientLibraryNameMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('ingredient matcher resolves vegetable broth base suffix to prepared base ingredient', function () {
    $broth = Ingredient::factory()->create([
        'name' => 'Vegetable Broth',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 12,
        'protein' => 0.5,
        'carbs' => 1,
        'fat' => 0.2,
    ]);

    $resolved = IngredientLibraryNameMatcher::resolveForImportLabel('Vegetable Broth (Base)');

    expect($resolved?->id)->toBe($broth->id);
});

test('recipe nutrition calculator rolls up prepared base ingredient from component formulation', function () {
    $carrot = Ingredient::factory()->create([
        'name' => 'Carrot, raw',
        'is_verified' => true,
        'calories' => 41,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $broth = Ingredient::factory()->create([
        'name' => 'Vegetable Broth',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
    ]);

    $broth->components()->sync([
        (int) $carrot->id => ['amount_grams' => 100],
    ]);

    $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($broth->fresh(['components']));

    expect($per100['calories'])->toBe(41.0)
        ->and($per100['protein'])->toBe(1.0);

    $batch = RecipeNutritionCalculator::fromRows([
        ['ingredient_id' => $broth->id, 'amount_grams' => 200],
    ]);

    expect($batch['calories'])->toBe(82.0)
        ->and($batch['protein'])->toBe(2.0);
});

test('meal csv import includes base recipe macros in calculated meal totals', function () {
    $carrot = Ingredient::factory()->create([
        'name' => 'Carrot, raw',
        'is_verified' => true,
        'calories' => 41,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $broth = Ingredient::factory()->create([
        'name' => 'Vegetable Broth',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
    ]);
    $broth->components()->sync([
        (int) $carrot->id => ['amount_grams' => 100],
    ]);

    Ingredient::factory()->create([
        'name' => 'Salmon',
        'is_verified' => true,
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
    ]);

    $csv = 'name,ingredients,target_cal'."\n"
        .'Broth Salmon Bowl,"Salmon (120g), Vegetable Broth (Base) (200g)",500'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Broth Salmon Bowl')->firstOrFail();

    expect((float) $meal->total_calories)->toBeGreaterThan(200)
        ->and($meal->ingredients)->toHaveCount(2);
});

test('ingredient matcher resolves meal linked base recipe ingredient', function () {
    $carrot = Ingredient::factory()->create([
        'name' => 'Carrot, raw',
        'is_verified' => true,
        'calories' => 41,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Vegetable Broth',
        'category' => RecipeCategory::BaseRecipe->value,
        'meal_type' => MealType::BaseRecipe->value,
        'total_calories' => 41,
        'total_protein' => 1,
        'total_carbs' => 10,
        'total_fat' => 0,
    ]);
    $meal->ingredients()->sync([
        (int) $carrot->id => [
            'amount_grams' => 100,
            'amount' => 100,
            'unit' => 'g',
        ],
    ]);

    $linked = Ingredient::factory()->create([
        'name' => 'Vegetable Broth',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'source_meal_id' => $meal->id,
        'calories' => 41,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $resolved = IngredientLibraryNameMatcher::resolveForImportLabel('Vegetable Broth (Base)');

    expect($resolved?->id)->toBe($linked->id);
});

test('calculateMealNutritionFromSegments sums base and standard ingredients', function () {
    $carrot = Ingredient::factory()->create([
        'name' => 'Carrot, raw',
        'is_verified' => true,
        'calories' => 41,
        'protein' => 1,
        'carbs' => 10,
        'fat' => 0,
    ]);

    $broth = Ingredient::factory()->create([
        'name' => 'Vegetable Broth',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
    ]);
    $broth->components()->sync([
        (int) $carrot->id => ['amount_grams' => 100],
    ]);

    Ingredient::factory()->create([
        'name' => 'Salmon',
        'is_verified' => true,
        'calories' => 200,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 10,
    ]);

    $service = app(MealCsvLibraryImportService::class);
    $calc = $service->calculateMealNutritionFromSegments([
        ['name' => 'Salmon', 'amount' => 100, 'unit' => 'g'],
        ['name' => 'Vegetable Broth (Base)', 'amount' => 100, 'unit' => 'g'],
    ]);

    expect($calc['pending_ingredients'])->toBeEmpty()
        ->and($calc['nutrition']['calories'])->toBeGreaterThan(230);
});
