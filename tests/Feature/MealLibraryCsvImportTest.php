<?php

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * @return Ingredient
 */
function mealImportIngredient(string $name, array $overrides = []): Ingredient
{
    return Ingredient::query()->create(array_merge([
        'name' => $name,
        'usda_food_category' => 'Other',
        'calories' => 200,
        'protein' => 20,
        'carbs' => 10,
        'fat' => 10,
        'b6' => 0.1,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => ['fiber' => 5],
        'is_verified' => true,
    ], $overrides));
}

test('meal library csv route imports meals when all ingredients exist', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);
    mealImportIngredient('Quinoa', ['calories' => 120, 'protein' => 4, 'carbs' => 22, 'fat' => 2]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nTest Bowl,Meal,Salmon:150 | Quinoa:50,Cook it.,A nice bowl.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.duplicates_created', 0)
        ->assertJsonPath('summary.pending_ingredient_input', 0)
        ->assertJsonPath('summary.errors', 0)
        ->assertJsonPath('unique_pending_ingredients', []);

    $importedRow = collect($response->json('rows'))->firstWhere('status', 'imported');
    expect($importedRow)->not->toBeNull()
        ->and($importedRow['warnings'] ?? [])->toBeArray()->toBeEmpty();

    $meal = Meal::query()->where('name', 'Test Bowl')->firstOrFail();

    expect($meal->description)->toBe('Cook it.')
        ->and($meal->highlight)->toBe('A nice bowl.')
        ->and($meal->category)->toBe(RecipeCategory::Meal)
        ->and($meal->health_score)->toBeGreaterThan(0)
        ->and($meal->ingredients)->toHaveCount(2);
});

test('meal library csv does not save meal when an ingredient is missing', function () {
    mealImportIngredient('Salmon');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nBad Bowl,Meal,Tuna:100 | Salmon:50,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()
        ->assertJsonPath('summary.imported', 0)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.duplicates_created', 0)
        ->assertJsonPath('summary.pending_ingredient_input', 1)
        ->assertJsonPath('unique_pending_ingredients', ['Tuna']);

    expect(Meal::query()->where('name', 'Bad Bowl')->exists())->toBeFalse();

    $row = collect($response->json('rows'))->first();
    expect($row['status'])->toBe('pending_ingredient_input')
        ->and($row['pending_ingredients'])->toContain('Tuna')
        ->and($row['category'] ?? null)->toBe('Meal');
});

test('meal library csv aggregates unique pending ingredient names across rows', function () {
    mealImportIngredient('Salmon');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nFirst,Meal,Tuna:50 | Salmon:10,.,.\nSecond,Meal,Tuna:100,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.pending_ingredient_input', 2)
        ->assertJsonPath('unique_pending_ingredients', ['Tuna']);
});

test('MealCsvLibraryImportService aggregates duplicate ingredient lines', function () {
    mealImportIngredient('Egg', ['calories' => 140, 'protein' => 12, 'carbs' => 1, 'fat' => 10]);

    $service = new MealCsvLibraryImportService;
    $calc = $service->calculateMealNutritionFromSegments([
        ['name' => 'Egg', 'grams' => 50],
        ['name' => 'Egg', 'grams' => 50],
    ]);

    expect($calc['pending_ingredients'])->toBeEmpty()
        ->and($calc['resolved'])->toHaveCount(1)
        ->and((float) $calc['resolved'][0]['grams'])->toBe(100.0)
        ->and($calc['calorie_warnings'])->toBeArray()->toBeEmpty();
});

test('guest cannot post meal library csv import', function () {
    $file = UploadedFile::fake()->createWithContent('meals.csv', "Meal_Name,Category,Ingredient_Quantities\nX,Meal,A:1\n");

    $this->postJson(route('meals.library.import-csv'), ['file' => $file])->assertUnauthorized();
});

test('meal library csv import accepts utf-8 bom on header row', function () {
    mealImportIngredient('Rice');

    $csv = "\xEF\xBB\xBFMeal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nBowl,Meal,Rice:100,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.duplicates_created', 0)
        ->assertJsonPath('unique_pending_ingredients', []);

    expect(Meal::query()->where('name', 'Bowl')->exists())->toBeTrue();
});

test('meal library livewire import surfaces first csv error in status', function () {
    $this->actingAs(User::factory()->create());

    $csv = "fdc_id,description,data_type\n1,Apple,Foundation\n";
    $file = UploadedFile::fake()->createWithContent('fdc_style.csv', $csv);

    $status = Livewire::test('pages::meals')
        ->set('mealLibraryImportCsv', $file)
        ->call('importMealLibraryCsv')
        ->get('status');

    expect($status)->toContain('Meal_Name');
});

test('meal library livewire import exposes unique pending ingredients for download csv', function () {
    $this->actingAs(User::factory()->create());

    mealImportIngredient('Salmon');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nBad Bowl,Meal,Tuna:100 | Salmon:50,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    Livewire::test('pages::meals')
        ->set('mealLibraryImportCsv', $file)
        ->call('importMealLibraryCsv')
        ->assertSet('mealLibraryImportPendingIngredients', ['Tuna'])
        ->assertSee(__('Download missing ingredients'));
});

test('meal library csv rejects invalid or missing category', function () {
    mealImportIngredient('Salmon');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nX,Main Salad,Salmon:100,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()
        ->assertJsonPath('summary.errors', 1)
        ->assertJsonPath('summary.imported', 0)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.duplicates_created', 0);

    $row = collect($response->json('rows'))->first();
    expect($row['message'])->toBe(__('Invalid or Missing Category.'));
});

test('meal library csv flags calorie warning for breakfast over 250 kcal but still imports', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nHeavy,Breakfast,Salmon:500,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.duplicates_created', 0)
        ->assertJsonPath('summary.errors', 0);

    $row = collect($response->json('rows'))->firstWhere('status', 'imported');
    expect($row)->not->toBeNull()
        ->and($row['warnings'])->not->toBeEmpty();

    $meal = Meal::query()->where('name', 'Heavy')->firstOrFail();
    expect($meal->category)->toBe(RecipeCategory::Breakfast);
});

test('meal library csv upserts existing meal when normalized name matches', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);
    mealImportIngredient('Quinoa', ['calories' => 120, 'protein' => 4, 'carbs' => 22, 'fat' => 2]);

    $user = User::factory()->create();

    $csv1 = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nTest Bowl,Meal,Salmon:150 | Quinoa:50,First.,Old highlight.\n";
    $this->actingAs($user)
        ->postJson(route('meals.library.import-csv'), ['file' => UploadedFile::fake()->createWithContent('meals.csv', $csv1)])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.duplicates_created', 0);

    $meal = Meal::query()->where('name', 'Test Bowl')->firstOrFail();
    $mealId = (int) $meal->id;

    $csv2 = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\n  test bowl  ,Breakfast,Salmon:100 | Quinoa:100,Second pass.,New highlight.\n";
    $response = $this->actingAs($user)
        ->postJson(route('meals.library.import-csv'), ['file' => UploadedFile::fake()->createWithContent('meals2.csv', $csv2)]);

    $response->assertOk()
        ->assertJsonPath('summary.imported', 0)
        ->assertJsonPath('summary.updated', 1)
        ->assertJsonPath('summary.duplicates_created', 0);

    $updatedRow = collect($response->json('rows'))->firstWhere('status', 'updated');
    expect($updatedRow)->not->toBeNull()
        ->and((int) $updatedRow['meal_id'])->toBe($mealId);

    $meal->refresh();
    $salmon = Ingredient::query()->where('name', 'Salmon')->firstOrFail();
    $meal->load('ingredients');

    expect((int) $meal->id)->toBe($mealId)
        ->and($meal->name)->toBe('test bowl')
        ->and($meal->description)->toBe('Second pass.')
        ->and($meal->highlight)->toBe('New highlight.')
        ->and($meal->category)->toBe(RecipeCategory::Breakfast)
        ->and(Meal::query()->count())->toBe(1)
        ->and((float) $meal->ingredients->firstWhere('id', $salmon->id)->pivot->amount_grams)->toBe(100.0);
});

test('calculateMealNutritionForCsvRow attaches calorie warnings when category is valid', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $service = new MealCsvLibraryImportService;
    $calc = $service->calculateMealNutritionForCsvRow([
        'ingredient_quantities' => 'Salmon:500',
        'category' => 'Breakfast',
    ]);

    expect($calc['calorie_warnings'])->not->toBeEmpty();
});
