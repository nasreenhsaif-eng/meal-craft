<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\IngredientsImport;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use App\Services\SaladDressingMealRefiner;
use App\Support\MealLibraryEditGuard;
use Illuminate\Http\UploadedFile;

test('meal library save marks meal as protected from automated overwrites', function () {
    $meal = Meal::factory()->create([
        'name' => 'Protected Meal '.uniqid(),
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'library_edited_at' => null,
    ]);

    MealLibraryEditGuard::markMealEditedFromLibrary($meal->fresh());

    expect($meal->fresh()->library_edited_at)->not->toBeNull();
});

test('salad dressing refiner skips meals edited in the library ui', function () {
    $meal = Meal::factory()->create([
        'name' => 'Classic Garden Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
        'instructions' => 'Custom UI instructions only.',
        'library_edited_at' => now(),
    ]);

    $carrot = Ingredient::factory()->create(['name' => 'Carrots']);
    $meal->ingredients()->attach($carrot->id, ['amount_grams' => 99, 'amount' => 99, 'unit' => 'g']);

    app(SaladDressingMealRefiner::class)->refine('Classic Garden Salad');

    expect((float) $meal->fresh()->ingredients->first()->pivot->amount_grams)->toBe(99.0)
        ->and($meal->fresh()->instructions)->toBe('Custom UI instructions only.');
});

test('meal csv import does not overwrite library edited meals', function () {
    $user = User::factory()->create();

    $meal = Meal::factory()->create([
        'name' => 'Locked Import Meal '.uniqid(),
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'instructions' => 'Keep my UI version.',
        'library_edited_at' => now(),
    ]);

    $ingredient = Ingredient::factory()->create(['name' => 'Locked Import Ingredient '.uniqid()]);
    $meal->ingredients()->attach($ingredient->id, ['amount_grams' => 42, 'amount' => 42, 'unit' => 'g']);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions\n"
        .'"'.$meal->name.'",Meal,"'.$ingredient->name.':100","CSV overwrite attempt"'."\n";

    $path = tempnam(sys_get_temp_dir(), 'locked-meal-import-');
    file_put_contents($path, $csv);

    $result = app(MealCsvLibraryImportService::class)->processPath($path, $user);

    expect($result['summary']['updated'] ?? 0)->toBe(0)
        ->and($meal->fresh()->instructions)->toBe('Keep my UI version.')
        ->and((float) $meal->fresh()->ingredients->first()->pivot->amount_grams)->toBe(42.0);
});

test('ingredients csv import does not overwrite library edited ingredients', function () {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Locked Pantry Ingredient '.uniqid(),
        'calories' => 42,
        'protein' => 4,
        'carbs' => 5,
        'fat' => 2,
        'library_edited_at' => now(),
    ]);

    $csv = "name,calories,protein,carbs,fat\n"
        .'"'.$ingredient->name.'",999,99,99,99'."\n";

    $file = UploadedFile::fake()->createWithContent('ingredients-locked.csv', $csv);

    expect(app(IngredientsImport::class)->import($file))->toBe(0)
        ->and((float) $ingredient->fresh()->calories)->toBe(42.0);
});

test('ingredients csv import does not overwrite library edited base recipes', function () {
    $child = Ingredient::factory()->create(['name' => 'Locked Base Child '.uniqid()]);
    $base = Ingredient::factory()->create([
        'name' => 'Locked Base Recipe '.uniqid(),
        'usda_food_category' => 'Base Ingredient',
        'calories' => 50,
        'protein' => 5,
        'carbs' => 6,
        'fat' => 3,
        'instructions' => 'Keep my UI base steps.',
        'library_edited_at' => now(),
    ]);
    $base->components()->sync([
        $child->id => ['amount_grams' => 10],
    ]);

    $csv = 'name,category,is_base_recipe,recipe_components,calories,protein,carbs,fat,instructions'."\n"
        .'"'.$base->name.'","Base Ingredient",1,"'.$child->id.':999",200,20,20,20,"CSV overwrite attempt"'."\n";

    $file = UploadedFile::fake()->createWithContent('base-ingredients-locked.csv', $csv);

    expect(app(IngredientsImport::class)->import($file))->toBe(0)
        ->and((float) $base->fresh()->calories)->toBe(50.0)
        ->and($base->fresh()->instructions)->toBe('Keep my UI base steps.')
        ->and((float) $base->fresh()->components->first()->pivot->amount_grams)->toBe(10.0);
});
