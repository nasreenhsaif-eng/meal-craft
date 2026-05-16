<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use App\Support\IngredientLibraryNameMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('ingredient library name matcher resolves salmon label to usda style library name', function () {
    Ingredient::factory()->create([
        'name' => 'Fish, salmon, Atlantic, farmed, raw',
        'is_verified' => true,
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
    ]);

    $resolved = IngredientLibraryNameMatcher::resolveByNormalizedKeys(['salmon']);

    expect($resolved)->toHaveCount(1)
        ->and($resolved->get('salmon')?->name)->toContain('salmon');
});

test('meal csv import parses comma separated parenthesis ingredients from spreadsheet', function () {
    $ingredients = [
        'Salmon' => ['calories' => 208, 'protein' => 20],
        'Potato' => ['calories' => 77, 'protein' => 2],
        'Leeks' => ['calories' => 61, 'protein' => 1.5],
        'Olive Oil (Extra Virgin)' => ['calories' => 884, 'protein' => 0],
        'Capers' => ['calories' => 23, 'protein' => 2],
    ];

    foreach ($ingredients as $name => $macros) {
        Ingredient::factory()->create(array_merge([
            'name' => $name,
            'is_verified' => true,
            'carbs' => 0,
            'fat' => 0,
        ], $macros));
    }

    $csv = 'name,ingredients'."\n"
        .'Comma Bowl,"Salmon (115g), Potato (80g), Leeks (60g), Olive Oil (Extra Virgin) (15g), Capers (4g)"'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Comma Bowl')->firstOrFail()->ingredients)->toHaveCount(5);
});

test('meal csv import parses ingredients string with escaped pipe delimiters', function () {
    Ingredient::factory()->create([
        'name' => 'Fish, salmon, Atlantic, farmed, raw',
        'is_verified' => true,
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
    ]);
    Ingredient::factory()->create([
        'name' => 'Quinoa, cooked',
        'is_verified' => true,
        'calories' => 120,
        'protein' => 4,
        'carbs' => 22,
        'fat' => 2,
    ]);

    $csv = 'name,ingredients'."\n"
        .'Escaped Pipe Bowl,Salmon (120g) \\| Quinoa (100g)'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Escaped Pipe Bowl')->with('ingredients')->firstOrFail();

    expect($meal->ingredients)->toHaveCount(2);
});

test('meal csv import resolves short salmon label to long usda ingredient name', function () {
    Ingredient::factory()->create([
        'name' => 'Fish, salmon, Atlantic, farmed, raw',
        'is_verified' => true,
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
    ]);
    Ingredient::factory()->create([
        'name' => 'Quinoa, cooked',
        'is_verified' => true,
        'calories' => 120,
        'protein' => 4,
        'carbs' => 22,
        'fat' => 2,
    ]);

    $csv = 'name,ingredients,target_cal'."\n"
        .'Salmon Bowl,Salmon (120g) | Quinoa (100g),350'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Salmon Bowl')->with('ingredients')->firstOrFail();

    expect($meal->ingredients)->toHaveCount(2)
        ->and((float) $meal->total_calories)->toBeGreaterThan(300);

    $warnings = collect($response->json('rows'))->firstWhere('status', 'imported')['warnings'] ?? [];
    expect($warnings)->toBeArray();
});

test('meal csv import lists individual pending ingredient names for comma separated cells', function () {
    $cell = 'Salmon (115g), Potato (80g), Leeks (60g), Olive Oil (Extra Virgin) (15g), Capers (4g)';

    $csv = 'name,ingredients'."\n"
        .'Pending Bowl,"'.$cell.'"'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file]);

    $response->assertOk()
        ->assertJsonPath('summary.pending_ingredient_input', 1);

    $pending = $response->json('unique_pending_ingredients');

    expect($pending)->toBeArray()
        ->and(count($pending))->toBeGreaterThanOrEqual(4)
        ->and($pending)->toContain('Salmon')
        ->and($pending)->toContain('Potato')
        ->and($pending)->toContain('Leeks')
        ->and($pending)->not->toContain($cell);
});

test('meal csv import resolves olive oil extra virgin label to usda style library name', function () {
    Ingredient::factory()->create([
        'name' => 'Oil, olive, extra virgin',
        'is_verified' => true,
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);

    $resolved = IngredientLibraryNameMatcher::resolveForImportLabel('Olive Oil (Extra Virgin)');

    expect($resolved)->not->toBeNull()
        ->and($resolved?->name)->toContain('olive');
});

test('meal csv import matches verified library row with zero calories', function () {
    Ingredient::factory()->create([
        'name' => 'Sea Salt',
        'is_verified' => true,
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
    ]);

    expect(IngredientLibraryNameMatcher::resolveForImportLabel('Sea Salt'))->not->toBeNull();
});

test('meal csv import matches base ingredient category rows shown in library', function () {
    Ingredient::factory()->create([
        'name' => 'House Salmon Blend',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 200,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 10,
    ]);

    $csv = 'name,ingredients'."\n"
        .'House Bowl,House Salmon Blend (120g)'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);
});

test('meal csv import resolves label via standardized_name exact match', function () {
    Ingredient::factory()->create([
        'name' => 'USDA entry #12345',
        'standardized_name' => 'Salmon, Atlantic, farmed',
        'is_verified' => true,
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
    ]);

    $resolved = IngredientLibraryNameMatcher::resolveForImportLabel('Salmon, Atlantic, farmed');

    expect($resolved)->not->toBeNull()
        ->and($resolved?->name)->toBe('USDA entry #12345');
});

test('meal csv import parses unicode comma separated ingredients', function () {
    Ingredient::factory()->create([
        'name' => 'Salmon',
        'is_verified' => true,
        'calories' => 208,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 13,
    ]);
    Ingredient::factory()->create([
        'name' => 'Potato',
        'is_verified' => true,
        'calories' => 77,
        'protein' => 2,
        'carbs' => 17,
        'fat' => 0,
    ]);

    $csv = 'name,ingredients'."\n"
        .'Unicode Bowl,"Salmon (115g)，Potato (80g)"'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Unicode Bowl')->firstOrFail()->ingredients)->toHaveCount(2);
});

test('meal csv import logs target calorie shortfall warning when rollup is far below target', function () {
    Ingredient::factory()->create([
        'name' => 'Lettuce, raw',
        'is_verified' => true,
        'calories' => 15,
        'protein' => 1,
        'carbs' => 3,
        'fat' => 0,
    ]);

    $service = app(MealCsvLibraryImportService::class);
    $calc = $service->calculateMealNutritionFromSegments([
        ['name' => 'Lettuce, raw', 'amount' => 50, 'unit' => 'g'],
    ]);

    $warnings = (new ReflectionClass($service))
        ->getMethod('targetCalorieShortfallWarnings')
        ->invoke($service, 350.0, (float) ($calc['nutrition']['calories'] ?? 0), 'Light Salad');

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0])->toContain('Target Calories');
});
