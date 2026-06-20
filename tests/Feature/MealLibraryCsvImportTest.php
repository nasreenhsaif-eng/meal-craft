<?php

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealCsvImportPendingRow;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use App\Support\MealInstructionsText;
use App\Support\MenuDevelopmentCsv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

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
        ->assertJsonPath('unique_pending_ingredients', [])
        ->assertJsonPath('csv_unrecognized_headers', []);

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

test('meal library csv import parses ingredient quantities with explicit unit suffix', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);
    mealImportIngredient('Quinoa', ['calories' => 120, 'protein' => 4, 'carbs' => 22, 'fat' => 2, 'density' => 1.0]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nSuffix Bowl,Meal,Salmon:150g | Quinoa 50g,Cook it.,A nice bowl.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Suffix Bowl')->firstOrFail()->ingredients)->toHaveCount(2);
});

test('meal library csv does not save meal when an ingredient is missing', function () {
    mealImportIngredient('Salmon');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nBad Bowl,Meal,Tuna:100 | Salmon:50,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $user = User::factory()->create();
    $response = $this->actingAs($user)
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

    $pending = MealCsvImportPendingRow::query()->where('user_id', $user->id)->firstOrFail();
    expect($pending->meal_name)->toBe('Bad Bowl')
        ->and($pending->ingredient_quantities)->toBe('Tuna:100 | Salmon:50');
});

test('meal library csv aggregates unique pending ingredient names across rows', function () {
    mealImportIngredient('Salmon');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nFirst,Meal,Tuna:50 | Salmon:10,.,.\nSecond,Meal,Tuna:100,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.pending_ingredient_input', 2)
        ->assertJsonPath('unique_pending_ingredients', ['Tuna']);

    expect(MealCsvImportPendingRow::query()->where('user_id', $user->id)->count())->toBe(2);
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

test('admin meal library csv import redirects with session flash for inertia ui', function () {
    mealImportIngredient('Rice');

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nBowl,Meal,Rice:100,Cook.,A bowl.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->post(route('admin.meal-library.import-csv'), ['file' => $file])
        ->assertRedirect(route('admin.meal-library'))
        ->assertSessionHas('mealCsvImportResult');

    expect(Meal::query()->where('name', 'Bowl')->exists())->toBeTrue();
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

test('meal library csv rejects invalid category but defaults when category column is absent', function () {
    mealImportIngredient('Salmon');

    $invalidCsv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nX,Brunch,Salmon:100,.,.\n";
    $invalidFile = UploadedFile::fake()->createWithContent('meals-invalid.csv', $invalidCsv);

    $invalidResponse = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $invalidFile]);

    $invalidResponse->assertOk()
        ->assertJsonPath('summary.errors', 1);

    $row = collect($invalidResponse->json('rows'))->first();
    expect($row['message'])->toBe(__('Invalid Category or Meal Type.'));

    $mainSaladCsv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nSalad Bowl,Main Salad,Salmon:100,.,.\n";
    $mainSaladFile = UploadedFile::fake()->createWithContent('meals-main-salad.csv', $mainSaladCsv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $mainSaladFile])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Salad Bowl')->firstOrFail()->category)->toBe(RecipeCategory::MainSalad);

    $defaultCsv = "Meal_Name,Ingredient_Quantities\nDefaulted,Salmon:100\n";
    $defaultFile = UploadedFile::fake()->createWithContent('meals-default.csv', $defaultCsv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $defaultFile])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Defaulted')->firstOrFail()->category)->toBe(RecipeCategory::Meal);
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

test('ingredient library csv import finishes pending meal rows queued from meal csv', function () {
    mealImportIngredient('Salmon');
    $user = User::factory()->create();

    $csvMeal = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\nBad Bowl,Meal,Tuna:100 | Salmon:50,.,.\n";
    $this->actingAs($user)
        ->postJson(route('meals.library.import-csv'), ['file' => UploadedFile::fake()->createWithContent('meals.csv', $csvMeal)])
        ->assertOk()
        ->assertJsonPath('summary.pending_ingredient_input', 1);

    expect(MealCsvImportPendingRow::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(Meal::query()->where('name', 'Bad Bowl')->exists())->toBeFalse();

    $csvIng = "name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density\n";
    $csvIng .= "Tuna,Fish,,200,20,0,10,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csvIng),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    expect(Meal::query()->where('name', 'Bad Bowl')->exists())->toBeTrue()
        ->and(MealCsvImportPendingRow::query()->where('user_id', $user->id)->exists())->toBeFalse();
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

test('meal library csv import restores soft deleted meal instead of creating duplicate', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $meal = Meal::query()->create([
        'name' => 'Restore Me Bowl',
        'category' => RecipeCategory::Meal,
        'total_calories' => 100,
        'total_protein' => 10,
        'total_carbs' => 5,
        'total_fat' => 5,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k2' => 0,
    ]);
    $meal->delete();

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\n"
        .'Restore Me Bowl,Meal,Salmon:100g,.,.'."\n";
    $file = UploadedFile::fake()->createWithContent('meals-restore.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 0)
        ->assertJsonPath('summary.updated', 1);

    expect(Meal::queryForMealLibrary()->where('name', 'Restore Me Bowl')->count())->toBe(1)
        ->and(Meal::withTrashed()->where('name', 'Restore Me Bowl')->count())->toBe(1);
});

test('meal library master csv import auto discovers image when photo_url is NO_PHOTO_URL', function () {
    if (! is_file(public_path('images/meals/coconut-chicken-curry.png'))) {
        $this->markTestSkipped('coconut-chicken-curry.png not in public/images/meals.');
    }

    mealImportIngredient('Chicken', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = 'name,description,meal_tags,cycle_phase,dietary_tags,safety_alerts,ingredients,instructions,photo_url,target_cal,target_pro,target_fat,target_carbs,calc_cal,calc_pro,calc_fat,calc_carbs,variance_notes'."\n"
        .'Thai Red Curry Chicken w Roasted Pumpkin,Spicy curry.,,,,,Chicken (100g),Simmer.,NO_PHOTO_URL,,,,,,,,,'."\n";
    $file = UploadedFile::fake()->createWithContent('meals-photo-discover.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Thai Red Curry Chicken w Roasted Pumpkin')->firstOrFail()->image_path)
        ->toBe('images/meals/coconut-chicken-curry.png');
});

test('meal library csv import downloads photo_url from remote http into public images meals', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'https://images.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = 'name,ingredients,photo_url'."\n"
        .'Remote Photo Meal,Salmon:100g,https://images.example.com/meals/remote.png'."\n";
    $file = UploadedFile::fake()->createWithContent('meals-remote-photo.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Remote Photo Meal')->firstOrFail();

    expect($meal->image_path)->toMatch('#^images/meals/Remote-Photo-Meal(-[a-f0-9]{8})?\.png$#')
        ->and(is_file(public_path((string) $meal->image_path)))->toBeTrue();

    @unlink(public_path((string) $meal->image_path));
});

test('meal library csv import maps Image URL column to image_path with normalized public path', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight,Image_URL\n"
        .'Photo Meal,Meal,Salmon:100g,.,.,public/images/meals/spicy-stew.jpg'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Photo Meal')->firstOrFail();
    expect($meal->image_path)->toBe('images/meals/spicy-stew.jpg');
});

test('meal library csv import normalizes inline numbered instructions into newline separated storage', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $instructions = '1. Heat the pan. 2. Add salmon. 3. Serve hot.';
    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\n"
        .'Steps Meal,Meal,Salmon:100g,"'.$instructions.'",.'."\n";
    $file = UploadedFile::fake()->createWithContent('meals-steps.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Steps Meal')->firstOrFail();

    expect($meal->instructions)->toBe("Heat the pan.\nAdd salmon.\nServe hot.")
        ->and(MealInstructionsText::linesFromRaw($meal->instructions))->toHaveCount(3);
});

test('meal library csv import normalizes markdown and local app image urls', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $markdownUrl = '[http://meal-craft.test/images/meals/smoky-stew.png](https://www.google.com/search?q=test)';
    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight,Image_URL\n"
        .'Markdown Meal,Meal,Salmon:100g,.,.,"'.$markdownUrl.'"'."\n";
    $file = UploadedFile::fake()->createWithContent('meals-markdown-image.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Markdown Meal')->firstOrFail()->image_path)
        ->toBe('images/meals/smoky-stew.png');
});

test('meal library csv import maps Image URL header with spaces to image_path', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Image URL\n"
        ."SpaceImg,Meal,Salmon:100g,/images/meals/x.jpg\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'SpaceImg')->firstOrFail()->image_path)->toBe('images/meals/x.jpg');
});

test('meal library csv import returns csv_unrecognized_headers for unknown columns', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Mystery_Column\nTagged,Meal,Salmon:100g,oops\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('csv_unrecognized_headers', ['Mystery_Column']);
});

test('meal library csv import accepts master template headers with spaces', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = '"Meal Name","Meal Type","Ingredients String","Target Calories","Target Carbs","Is Bulk","Servings Count","Safety Alerts"'."\n"
        .'Master Bowl,Breakfast,Salmon:100g,400,45,true,2,"Peanuts|Dairy"'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Master Bowl')->firstOrFail();
    expect($meal->category)->toBe(RecipeCategory::Breakfast)
        ->and($meal->target_calories)->toBe(400.0)
        ->and($meal->target_carbs)->toBe(45.0)
        ->and($meal->is_bulk)->toBeTrue()
        ->and($meal->servings_count)->toBe(2.0)
        ->and($meal->safety_alert_tags)->toEqualCanonicalizing(['Peanuts', 'Dairy']);
});

test('meal library csv import parses production snake_case is_bulk FALSE and servings_count from column indices', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $headers = implode(',', MenuDevelopmentCsv::MEAL_HEADERS);
    $csv = $headers."\n"
        .'Bulk Index Bowl,Meal,Salmon:100g,400,48,42,22,800,48,80,32,TRUE,4,Balanced,Follicular,,,Short.,Cook.'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Bulk Index Bowl')->firstOrFail();

    expect($meal->is_bulk)->toBeTrue()
        ->and($meal->servings_count)->toBe(4.0)
        ->and((float) $meal->total_calories)->toBe(50.0);
});

test('meal library csv import parses uppercase FALSE for is_bulk via filter_var', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $headers = implode(',', MenuDevelopmentCsv::MEAL_HEADERS);
    $csv = $headers."\n"
        .'Solo Index Bowl,Meal,Salmon:50g,,,,,,,,,FALSE,,,,,,,,'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Solo Index Bowl')->firstOrFail()->is_bulk)->toBeFalse();
});

test('meal library csv import applies production column indices when trailing csv cells are omitted', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $headers = implode(',', MenuDevelopmentCsv::MEAL_HEADERS);
    $csv = $headers."\n"
        .'Trailing Omit Bowl,Meal,Salmon:100g,400,48,42,22,800,48,80,32,true,6'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Trailing Omit Bowl')->firstOrFail();

    expect($meal->is_bulk)->toBeTrue()
        ->and($meal->servings_count)->toBe(6.0);
});

test('meal library csv import parses is_bulk false from string false', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = '"Meal Name","Meal Type","Ingredients String","Is Bulk"'."\n"
        .'Solo Meal,Meal,Salmon:50g,false'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Solo Meal')->firstOrFail()->is_bulk)->toBeFalse();
});

test('meal library csv import persists per serving totals for bulk meals from ingredient rollup', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = '"Meal Name","Meal Type","Ingredients String","Is Bulk","Servings Count"'."\n"
        .'Bulk Rollup Bowl,Meal,Salmon:100g,true,2'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Bulk Rollup Bowl')->firstOrFail();

    expect($meal->is_bulk)->toBeTrue()
        ->and($meal->servings_count)->toBe(2.0)
        ->and($meal->nutrition_aggregates_synced)->toBeFalse()
        ->and((float) $meal->total_calories)->toBe(100.0)
        ->and((float) $meal->total_protein)->toBe(12.5);
});

test('meal library csv import maps batch macro columns when is bulk is true', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = '"Meal Name","Meal Type","Ingredients String","Batch Calories","Batch Protein","Batch Carbs","Batch Fat","Is Bulk","Servings Count"'."\n"
        .'Bulk Batch Columns,Meal,Salmon:50g,800,48,80,32,true,4'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    $meal = Meal::query()->where('name', 'Bulk Batch Columns')->firstOrFail();

    // Ingredient rollup (100 kcal batch) wins over CSV batch columns, divided by 4 servings.
    expect($meal->is_bulk)->toBeTrue()
        ->and($meal->servings_count)->toBe(4.0)
        ->and((float) $meal->total_calories)->toBe(25.0);
});

test('meal library csv import errors when is_bulk is true without servings count', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = '"Meal Name","Meal Type","Ingredients String","Is Bulk","Servings Count"'."\n"
        .'Bulk Fail,Meal,Salmon:50g,true,'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.errors', 1);

    $errorLines = $response->json('import_error_lines');
    expect($errorLines)->toBeArray()->toHaveCount(1)
        ->and($errorLines[0])->toBeString()->toContain('Servings Count');

    $row = collect($response->json('rows'))->firstWhere('status', 'error');
    expect($row)->not->toBeNull()
        ->and($row['message'])->toBe(__('Servings Count is required when Is Bulk is true.'));
});

test('meal library csv import returns a non-empty message string for each failed row in a multi-row file', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight\n"
        ."Ok Row,Meal,Salmon:50,.,.\n"
        ."Bad Row,NotARealCategory,Salmon:50,.,.\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('summary.errors', 1);

    $rows = $response->json('rows');
    expect($rows)->toHaveCount(2);

    expect($response->json('import_error_lines'))->toBeArray()->toHaveCount(1)
        ->and($response->json('import_error_lines.0'))->toBeString()->toContain('Bad Row');

    $err = collect($rows)->firstWhere('status', 'error');
    expect($err)->not->toBeNull()
        ->and($err)->toHaveKey('message')
        ->and($err['message'])->toBeString()->not->toBeEmpty();
});

test('meal library csv import missing required column message lists missing fields', function () {
    $csv = "Category,Ingredient_Quantities\nMeal,Salmon:100\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.errors', 1);

    $message = (string) collect($response->json('rows'))->firstWhere('status', 'error')['message'];
    expect($message)->toContain('Meal_Name');
});

test('meal library csv import accepts concise master export headers without category column', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);
    mealImportIngredient('Quinoa', ['calories' => 120, 'protein' => 4, 'carbs' => 22, 'fat' => 2]);

    $csv = 'name,description,meal_tags,cycle_phase,dietary_tags,safety_alerts,ingredients,instructions,photo_url,target_cal,target_pro,target_fat,target_carbs,calc_cal,calc_pro,calc_fat,calc_carbs,variance_notes'."\n"
        .'Example Salmon Bowl,Bright bowl.,,Follicular,"Low GI, High protein",Shellfish,Salmon (120g) | Quinoa (100g),Grill salmon.,,600,45,20,35,588,43,21,28,kcal: 12'."\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('csv_unrecognized_headers', []);

    $meal = Meal::query()->where('name', 'Example Salmon Bowl')->firstOrFail();
    expect($meal->category)->toBe(RecipeCategory::Meal)
        ->and($meal->diet_tags)->toEqualCanonicalizing(['Low GI', 'High protein']);
});

test('meal library csv import still accepts legacy long master export headers', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = 'Meal Name,Description,Ingredients,Target Calories (kcal)'."\n"
        .'Legacy Header Bowl,Notes.,Salmon:100g,400'."\n";
    $file = UploadedFile::fake()->createWithContent('meals-legacy.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::query()->where('name', 'Legacy Header Bowl')->exists())->toBeTrue();
});

test('meal library csv import maps Ingredients header alias', function () {
    mealImportIngredient('Salmon', ['calories' => 200, 'protein' => 25, 'carbs' => 0, 'fat' => 12]);

    $csv = "name,ingredients\nAlias Bowl,Salmon:100g\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);
});

test('meal library csv import saves apple pie balls as dessert in meal library not ingredient library', function () {
    mealImportIngredient('Rolled Oats', ['calories' => 380, 'protein' => 13, 'carbs' => 68, 'fat' => 7]);
    mealImportIngredient('Almond Butter', ['calories' => 614, 'protein' => 21, 'carbs' => 19, 'fat' => 55]);

    $csv = "Meal_Name,Category,Ingredient_Quantities\n"
        ."Apple Pie Balls,Dessert,Rolled Oats:40 | Almond Butter:15\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1)
        ->assertJsonPath('summary.ingredient_library_imported', 0);

    $meal = Meal::queryForMealLibrary()->where('name', 'Apple Pie Balls')->first();
    expect($meal)->not->toBeNull()
        ->and($meal->category)->toBe(RecipeCategory::Dessert)
        ->and(Ingredient::query()
            ->where('name', 'Apple Pie Balls')
            ->whereIn('usda_food_category', ['Base Ingredient', 'Base Recipe'])
            ->exists())->toBeFalse();
});

test('meal library csv import removes mistaken base ingredient when same name is imported as meal', function () {
    mealImportIngredient('Rolled Oats', ['calories' => 380, 'protein' => 13, 'carbs' => 68, 'fat' => 7]);

    $mistaken = Ingredient::factory()->create([
        'name' => 'Apple Pie Balls',
        'usda_food_category' => 'Base Ingredient',
        'is_verified' => true,
        'calories' => 200,
        'protein' => 5,
        'carbs' => 30,
        'fat' => 8,
    ]);
    $mistaken->components()->attach([
        mealImportIngredient('Rolled Oats')->id => ['amount_grams' => 40],
    ]);

    $csv = "Meal_Name,Category,Ingredient_Quantities\n"
        ."Apple Pie Balls,Dessert,Rolled Oats:40\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 1);

    expect(Meal::queryForMealLibrary()->where('name', 'Apple Pie Balls')->exists())->toBeTrue()
        ->and(Ingredient::query()->whereKey($mistaken->id)->exists())->toBeFalse();
});

test('meal library csv import saves base ingredient category rows to ingredient library not meal library', function () {
    $child = mealImportIngredient('Tomato Paste', ['calories' => 80, 'protein' => 4, 'carbs' => 16, 'fat' => 0]);

    $csv = "Meal_Name,Category,Ingredient_Quantities\nHouse Sauce,Base Ingredient,Tomato Paste:100g\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.imported', 0)
        ->assertJsonPath('summary.updated', 0)
        ->assertJsonPath('summary.ingredient_library_imported', 1)
        ->assertJsonPath('summary.ingredient_library_updated', 0);

    expect(Meal::queryForMealLibrary()->where('name', 'House Sauce')->exists())->toBeFalse()
        ->and(Ingredient::query()->where('name', 'House Sauce')->exists())->toBeTrue();
});

test('meal library csv import downloads photo_url for base ingredient category into ingredient library', function () {
    mealImportIngredient('Butternut Squash', ['calories' => 45, 'protein' => 1, 'carbs' => 12, 'fat' => 0]);

    Http::fake([
        'https://cdn.example.com/*' => Http::response(
            file_get_contents(public_path('images/meals/placeholder.svg')),
            200,
            ['Content-Type' => 'image/svg+xml'],
        ),
    ]);

    $csv = "Meal_Name,Category,Ingredient_Quantities,photo_url\n"
        ."Pumpkin Puree (Base),Base Ingredient,Butternut Squash:1000g,https://cdn.example.com/pumpkin.png\n";
    $file = UploadedFile::fake()->createWithContent('meals.csv', $csv);

    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.ingredient_library_imported', 1);

    $ingredient = Ingredient::query()->where('name', 'Pumpkin Puree (Base)')->firstOrFail();

    expect($ingredient->image_path)->toMatch('#^images/meals/Pumpkin-Puree-\(Base\)(-[a-f0-9]{8})?\.svg$#')
        ->and(is_file(public_path((string) $ingredient->image_path)))->toBeTrue();

    @unlink(public_path((string) $ingredient->image_path));
});

test('meal library csv import returns 422 when file is missing', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('meals.library.import-csv'), [])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors', 'csv_unrecognized_headers', 'rows']);
});
