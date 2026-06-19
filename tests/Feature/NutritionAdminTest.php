<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Jobs\EnrichIngredientsNutritionJob;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Models\User;
use App\Support\UsdaNutrientMath;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Config::set('services.usda.api_key', '');
});

test('authenticated users can view nutrition admin pages', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('ingredients.index'))->assertOk();
    $this->get(route('meals.index'))->assertOk();
    $this->get(route('meal-plans.index'))->assertOk();
    $this->get(route('meal-plans.four-week'))->assertOk();
});

test('ingredients page is local-only (no analysis app mount)', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('ingredients.index'))
        ->assertOk()
        ->assertDontSee('ingredient-analyzer-root', false)
        ->assertDontSee('Meal Craft Analysis', false);
});

test('ingredients manager can create and edit an ingredient', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::ingredients')
        ->set('name', 'Banana')
        ->set('category', 'Fruits')
        ->set('calories', 89)
        ->set('protein', 1.1)
        ->set('carbs', 22.8)
        ->set('fat', 0.3)
        ->set('micronutrients', json_encode(['potassium' => 358]))
        ->call('saveIngredient')
        ->assertSet('error', null);

    $ingredient = Ingredient::query()->where('name', 'Banana')->firstOrFail();

    expect($ingredient->is_verified)->toBeTrue();

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->set('name', 'Banana (ripe)')
        ->call('saveIngredient')
        ->assertSet('error', null);

    expect($ingredient->refresh()->name)->toBe('Banana (ripe)');
});

test('csv import marks ingredients verified immediately', function () {
    $this->actingAs(User::factory()->create());

    $csv = implode("\n", [
        'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,vitamin_a,vitamin_c',
        'Test Ingredient,Pantry,12345,10,1,2,3,0.1,0.2,0.3,1.1,2.2,5,6',
    ]);

    $file = UploadedFile::fake()->createWithContent('ingredients.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('error', null);

    $ingredient = Ingredient::query()->where('name', 'Test Ingredient')->firstOrFail();

    expect($ingredient->is_verified)->toBeTrue()
        ->and($ingredient->usda_food_category)->toBe('Pantry')
        ->and((float) $ingredient->b6)->toBe(0.1)
        ->and((float) $ingredient->b9_folate)->toBe(0.2)
        ->and((float) $ingredient->b12)->toBe(0.3);
});

test('ingredients manager can merge duplicate name rows keeping better sickle cell usda data', function () {
    $this->markTestSkipped('mergeDuplicateIngredientsKeepBest was removed from pages::ingredients; use Ingredient Library admin flows.');
    $this->actingAs(User::factory()->create());

    $weak = Ingredient::query()->create([
        'name' => 'Chicken',
        'calories' => 100,
        'protein' => 20,
        'carbs' => 0,
        'fat' => 2,
        'micronutrients' => ['vitamin_b12' => 0, 'vitamin_b9' => 0, 'vitamin_b6' => 0],
        'is_verified' => true,
        'fdc_id' => 2_646_170,
        'fdc_key_nutrients' => [UsdaNutrientMath::FDC_VITAMIN_B12 => 0.0, UsdaNutrientMath::FDC_FOLATE => 0.0],
        'usda_data_type' => 'Foundation',
    ]);

    $strong = Ingredient::query()->create([
        'name' => 'Chicken',
        'calories' => 120,
        'protein' => 22,
        'carbs' => 0,
        'fat' => 3,
        'micronutrients' => ['vitamin_b12' => 0.25, 'vitamin_b9' => 10.8, 'vitamin_b6' => 0.97],
        'is_verified' => true,
        'fdc_id' => 171_077,
        'fdc_key_nutrients' => [
            UsdaNutrientMath::FDC_VITAMIN_B12 => 0.25,
            UsdaNutrientMath::FDC_FOLATE => 10.8,
            UsdaNutrientMath::FDC_VITAMIN_B6 => 0.97,
        ],
        'usda_data_type' => 'SR Legacy',
    ]);

    Livewire::test('pages::ingredients')
        ->call('mergeDuplicateIngredientsKeepBest', $weak->id.','.$strong->id)
        ->assertSet('error', null);

    $this->assertDatabaseMissing('ingredients', ['id' => $weak->id]);
    $this->assertDatabaseHas('ingredients', [
        'id' => $strong->id,
        'fdc_id' => 171_077,
    ]);
});

test('ingredients manager can edit an ingredient name from the table', function () {
    $this->actingAs(User::factory()->create());

    $ingredient = Ingredient::query()->create([
        'name' => 'Milk (whole)',
        'calories' => 61,
        'protein' => 3.2,
        'carbs' => 4.8,
        'fat' => 3.3,
        'micronutrients' => ['fiber' => 0],
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->assertSet('editingIngredientId', $ingredient->id)
        ->assertSet('name', 'Milk (whole)')
        ->assertSet('status', 'Ingredient loaded for editing — the form is above.')
        ->set('name', 'Milk whole')
        ->call('saveIngredient')
        ->assertSet('editingIngredientId', null)
        ->assertSet('status', 'Ingredient updated.');

    $ingredient->refresh();
    expect($ingredient->name)->toBe('Milk whole');
});

test('ingredients manager can create an ingredient', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::ingredients')
        ->set('name', 'Banana')
        ->set('calories', 89)
        ->set('protein', 1.1)
        ->set('carbs', 22.8)
        ->set('fat', 0.3)
        ->set('micronutrients', json_encode(['potassium_mg' => 358]))
        ->call('saveIngredient');

    $this->assertDatabaseHas('ingredients', [
        'name' => 'Banana',
    ]);
});

test('meal hub builder can create a meal with ingredient amounts', function () {
    $this->markTestSkipped('Legacy pages::meals Livewire builder was replaced by Inertia Meal Library.');
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'Rice',
        'usda_food_category' => 'Other',
        'calories' => 130,
        'protein' => 2.7,
        'carbs' => 28,
        'fat' => 0.3,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Rice Bowl')
        ->set('category', RecipeCategory::Meal->value)
        ->set('instructions', 'Simple bowl')
        ->set('recipeIngredients', [
            ['ingredient_id' => $ingredient->id, 'amount' => 200, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('meals', [
        'name' => 'Rice Bowl',
    ]);

    $meal = Meal::query()->where('name', 'Rice Bowl')->firstOrFail();

    $this->assertDatabaseHas('ingredient_meal', [
        'meal_id' => $meal->id,
        'ingredient_id' => $ingredient->id,
        'amount_grams' => 200,
    ]);
});

test('meal plan builder can create a plan', function () {
    foreach (RecipeCategory::cases() as $category) {
        Meal::query()->create([
            'name' => 'Library '.$category->value,
            'category' => $category,
            'meal_type' => MealType::fromRecipeCategory($category)->value,
            'total_calories' => 120,
            'total_protein' => 8,
            'total_carbs' => 12,
            'total_fat' => 4,
            'total_folate' => 40,
            'total_iron' => 2,
        ]);
    }

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::meal-plans')
        ->set('name', 'Fat Loss Plan')
        ->set('goal', 'Calorie deficit')
        ->set('targetDailyCalories', '2000')
        ->call('createMealPlan')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('meal_plans', [
        'name' => 'Fat Loss Plan',
        'goal' => 'Calorie deficit',
        'schema_type' => MealPlanSchemaType::WeeklyStructured->value,
        'plan_category' => MealPlanLibraryCategory::Balanced->value,
    ]);

    $plan = MealPlan::query()->where('name', 'Fat Loss Plan')->firstOrFail();
    $expectedRows = 7 * MealPlanSlotType::slotsPerDayPerOption() * 2;
    expect(MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->count())->toBe($expectedRows);
});

test('ingredients csv can be exported', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::ingredients')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

test('ingredients csv template can be downloaded', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::ingredients')
        ->call('downloadCsvTemplate')
        ->assertFileDownloaded('ingredients-template.csv');
});

test('meals csv can be exported', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('meals.library.export-csv'))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv');
});

test('meal plans csv can be exported', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::meal-plans')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

test('ingredients fetch lists multiple usda fooddata central profiles to choose from', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', 'test-usda-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
        'https://api.nal.usda.gov/fdc/v1/foods/search*' => Http::response([
            'foods' => [
                ['fdcId' => 1001, 'description' => 'Beef, lean', 'dataType' => 'SR Legacy', 'foodNutrients' => []],
                ['fdcId' => 1002, 'description' => 'Beef, marbled', 'dataType' => 'SR Legacy', 'foodNutrients' => []],
            ],
        ], 200),
        'https://api.nal.usda.gov/fdc/v1/foods?*' => Http::response([
            [
                'fdcId' => 1001,
                'ndbNumber' => '13001',
                'foodCategory' => ['description' => 'Beef Products'],
                'foodNutrients' => [
                    ['nutrient' => ['number' => '208'], 'amount' => 150],
                    ['nutrient' => ['number' => '203'], 'amount' => 20],
                    ['nutrient' => ['number' => '205'], 'amount' => 0],
                    ['nutrient' => ['number' => '204'], 'amount' => 8],
                ],
            ],
            [
                'fdcId' => 1002,
                'ndbNumber' => '13002',
                'foodCategory' => ['description' => 'Beef Products'],
                'foodNutrients' => [
                    ['nutrient' => ['number' => '208'], 'amount' => 280],
                    ['nutrient' => ['number' => '203'], 'amount' => 18],
                    ['nutrient' => ['number' => '205'], 'amount' => 0],
                    ['nutrient' => ['number' => '204'], 'amount' => 22],
                ],
            ],
        ], 200),
    ]);

    $component = Livewire::test('pages::ingredients')
        ->set('name', 'beef')
        ->call('fetchNutrition')
        ->assertSet('error', null)
        ->assertSet('selectedNutritionFormIndex', 0);

    $options = $component->get('nutritionFormOptions');

    expect($options)->toHaveCount(2)
        ->and($options[0]['label'])->toContain('NDB 13001')
        ->and($options[0]['label'])->toContain('Beef Products')
        ->and((float) $component->get('calories'))->toBe(150.0);

    $component->set('selectedNutritionFormIndex', 1);

    expect((float) $component->get('calories'))->toBe(280.0);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredients fetch uses broad usda search when foundation sr legacy returns no foods', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', 'test-usda-key');

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'openfoodfacts.org')) {
            return Http::response(['products' => []], 200);
        }

        if (str_contains($url, 'foods/search')) {
            /** @var array<string, mixed> $data */
            $data = json_decode($request->body(), true) ?? [];

            if (isset($data['dataType'])) {
                return Http::response(['foods' => []], 200);
            }

            return Http::response([
                'foods' => [
                    [
                        'fdcId' => 777,
                        'description' => 'Rare brand only match',
                        'dataType' => 'Foundation',
                        'foodNutrients' => [
                            ['nutrient' => ['number' => '208'], 'amount' => 200],
                            ['nutrient' => ['number' => '203'], 'amount' => 10],
                            ['nutrient' => ['number' => '205'], 'amount' => 20],
                            ['nutrient' => ['number' => '204'], 'amount' => 5],
                        ],
                    ],
                ],
            ], 200);
        }

        if (str_contains($url, 'fdc/v1/foods') && ! str_contains($url, 'foods/search')) {
            return Http::response([
                'foods' => [
                    [
                        'fdcId' => 777,
                        'dataType' => 'Foundation',
                        'foodCategory' => ['description' => 'Vegetables and Vegetable Products'],
                        'foodNutrients' => [
                            ['nutrient' => ['number' => '208'], 'amount' => 200],
                            ['nutrient' => ['number' => '203'], 'amount' => 10],
                            ['nutrient' => ['number' => '205'], 'amount' => 20],
                            ['nutrient' => ['number' => '204'], 'amount' => 5],
                        ],
                    ],
                ],
            ], 200);
        }

        return Http::response(['unexpected' => true], 500);
    });

    Livewire::test('pages::ingredients')
        ->set('name', 'Rare brand only match')
        ->set('usdaAllowBroadDataTypeSearch', true)
        ->call('fetchNutrition')
        ->assertSet('error', null)
        ->assertSet('calories', 200.0);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredients fetch skips all data types usda search when broad search is disabled', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', 'test-usda-key');

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'openfoodfacts.org')) {
            return Http::response([
                'products' => [[
                    'product_name' => 'Fallback product',
                    'nutriments' => [
                        'energy-kcal_100g' => 50,
                        'proteins_100g' => 1,
                        'carbohydrates_100g' => 10,
                        'fat_100g' => 1,
                    ],
                ]],
            ], 200);
        }

        if (str_contains($url, 'foods/search')) {
            /** @var array<string, mixed> $data */
            $data = json_decode($request->body(), true) ?? [];

            if (isset($data['dataType'])) {
                return Http::response(['foods' => []], 200);
            }

            return Http::response(['forbidden_broad' => true], 500);
        }

        return Http::response(['unexpected' => true], 500);
    });

    Livewire::test('pages::ingredients')
        ->set('name', 'obscure ingredient xyz')
        ->set('usdaAllowBroadDataTypeSearch', false)
        ->call('fetchNutrition')
        ->assertSet('error', null)
        ->assertSet('calories', 50.0);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredients fetch usda category filter limits profiles', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', 'test-usda-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
        'https://api.nal.usda.gov/fdc/v1/foods/search*' => Http::response([
            'foods' => [
                ['fdcId' => 3001, 'description' => 'Beef, ground, raw', 'dataType' => 'SR Legacy', 'foodNutrients' => []],
                ['fdcId' => 3002, 'description' => 'Chicken, breast, raw', 'dataType' => 'Foundation', 'foodNutrients' => []],
            ],
        ], 200),
        'https://api.nal.usda.gov/fdc/v1/foods?*' => Http::response([
            'foods' => [
                [
                    'fdcId' => 3001,
                    'foodCategory' => ['description' => 'Beef Products'],
                    'foodNutrients' => [
                        ['nutrient' => ['number' => '208'], 'amount' => 250],
                        ['nutrient' => ['number' => '203'], 'amount' => 26],
                        ['nutrient' => ['number' => '205'], 'amount' => 0],
                        ['nutrient' => ['number' => '204'], 'amount' => 15],
                    ],
                ],
                [
                    'fdcId' => 3002,
                    'dataType' => 'Foundation',
                    'foodCategory' => ['description' => 'Poultry Products'],
                    'foodNutrients' => [
                        ['nutrient' => ['number' => '208'], 'amount' => 120],
                        ['nutrient' => ['number' => '203'], 'amount' => 22],
                        ['nutrient' => ['number' => '205'], 'amount' => 0],
                        ['nutrient' => ['number' => '204'], 'amount' => 3],
                        ['nutrient' => ['id' => 1178, 'number' => '418'], 'amount' => 0.4],
                        ['nutrient' => ['id' => 1177, 'number' => '417'], 'amount' => 8],
                    ],
                ],
            ],
        ], 200),
    ]);

    $component = Livewire::test('pages::ingredients')
        ->set('name', 'protein')
        ->set('usdaCategoryFilter', 'Poultry Products')
        ->call('fetchNutrition')
        ->assertSet('error', null);

    expect($component->get('nutritionFormOptions'))->toHaveCount(1)
        ->and((float) $component->get('calories'))->toBe(120.0);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredients fetch maps requested micronutrients', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', 'test-usda-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
        'https://api.nal.usda.gov/fdc/v1/foods/search*' => Http::response([
            'foods' => [
                ['fdcId' => 2001, 'description' => 'Bananas, raw', 'dataType' => 'Foundation', 'foodNutrients' => []],
            ],
        ], 200),
        'https://api.nal.usda.gov/fdc/v1/foods?*' => Http::response([
            [
                'fdcId' => 2001,
                'foodNutrients' => [
                    ['nutrient' => ['number' => '208'], 'amount' => 89],
                    ['nutrient' => ['number' => '203'], 'amount' => 1.1],
                    ['nutrient' => ['number' => '205'], 'amount' => 22.8],
                    ['nutrient' => ['number' => '204'], 'amount' => 0.3],
                    ['nutrient' => ['number' => '320'], 'amount' => 0.02],
                    ['nutrient' => ['number' => '415'], 'amount' => 0.4],
                    ['nutrient' => ['number' => '417'], 'amount' => 0.03],
                    ['nutrient' => ['number' => '418'], 'amount' => 0.001],
                    ['nutrient' => ['number' => '401'], 'amount' => 8.7],
                    ['nutrient' => ['number' => '328'], 'amount' => 0.001],
                    ['nutrient' => ['number' => '323'], 'amount' => 0.1],
                    ['nutrient' => ['number' => '291'], 'amount' => 2.6],
                    ['nutrient' => ['number' => '301'], 'amount' => 5],
                    ['nutrient' => ['number' => '303'], 'amount' => 0.26],
                    ['nutrient' => ['number' => '304'], 'amount' => 27],
                    ['nutrient' => ['number' => '306'], 'amount' => 358],
                ],
            ],
        ], 200),
    ]);

    $component = Livewire::test('pages::ingredients')
        ->set('name', 'Banana')
        ->call('fetchNutrition')
        ->assertSet('error', null);

    $micronutrients = json_decode((string) $component->get('micronutrients'), true);

    expect($micronutrients)->toBeArray();
    expect($micronutrients)->toHaveKeys([
        'vitamin_a',
        'vitamin_b6',
        'vitamin_b9',
        'vitamin_b12',
        'vitamin_c',
        'vitamin_d',
        'vitamin_e',
        'fiber',
        'calcium',
        'iron',
        'magnesium',
        'potassium',
    ]);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredients fetch falls back to local nutrition library when api has no results', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', '');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->set('name', 'banana')
        ->call('fetchNutrition')
        ->assertSet('calories', 89.0)
        ->assertSet('protein', 1.1)
        ->assertSet('error', null);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredient aliases map to fallback nutrition', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', '');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->set('name', 'capsicum')
        ->call('fetchNutrition')
        ->assertSet('calories', 31.0)
        ->assertSet('error', null);
})->skip('External API fetching removed (CSV/manual only).');

test('ingredients can be imported from csv', function () {
    $this->actingAs(User::factory()->create());

    $csv = <<<'CSV'
name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2
Lentils,Legumes,123,116,9,20,0.4,0.178,181,0,3.3,36,7.9,1.8,19,369,6,1.3,1.5,0.001,0.11,0,0
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', [
        'name' => 'Lentils',
        'calories' => 116,
    ]);

    $ingredient = Ingredient::query()->where('name', 'Lentils')->firstOrFail();

    expect($ingredient->is_verified)->toBeTrue()
        ->and((float) $ingredient->b9_folate)->toBe(181.0)
        ->and((float) $ingredient->b12)->toBe(0.0)
        ->and((float) $ingredient->iron)->toBe(3.3)
        ->and((float) $ingredient->magnesium)->toBe(36.0)
        ->and($ingredient->usda_food_category)->toBe('Legumes')
        ->and($ingredient->micronutrients)->toBeArray()
        ->and((float) $ingredient->micronutrients['fiber'])->toBe(7.9)
        ->and((float) $ingredient->micronutrients['sodium'])->toBe(6.0);
});

test('ingredients import supports name only csv rows', function () {
    $this->actingAs(User::factory()->create());
    // Bulk CSV import does not enrich from external APIs.

    $csv = <<<'CSV'
name
Pumpkin
Capsicum
Quinoa
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-name-only.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', ['name' => 'Pumpkin']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Capsicum']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Quinoa']);
});

test('ingredients import supports plain ingredient list without header', function () {
    $this->markTestSkipped('Plain list import without a header was removed in favor of analysis-style CSV bulk imports.');

    $csv = <<<'CSV'
Chicken breast
Apple
Aleppo Pepper
Olive oil
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-plain-list.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('importSummary.created', 4)
        ->assertSet('importSummary.updated', 0)
        ->assertSet('importSummary.skipped', 0)
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', ['name' => 'Chicken breast']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Apple']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Aleppo Pepper']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Olive oil']);
});

test('ingredients import summary counts updates and skipped rows', function () {
    $this->markTestSkipped('Bulk CSV import no longer produces row-level skipped/summary metadata.');

    Ingredient::query()->create([
        'name' => 'Apple',
        'calories' => 52,
        'protein' => 0.3,
        'carbs' => 14,
        'fat' => 0.2,
        'micronutrients' => [],
    ]);

    $csv = <<<'CSV'
name,calories,protein,carbs,fat,micronutrients
Apple,53,0.4,13.9,0.2,"{}"
,10,1,1,1,"{}"
Pear,57,0.4,15,0.1,"{}"
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-summary.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('importSummary.created', 1)
        ->assertSet('importSummary.updated', 1)
        ->assertSet('importSummary.skipped', 1)
        ->assertCount('importSkippedRows', 1)
        ->assertSet('error', null);
});

test('skipped rows report can be downloaded after import', function () {
    $this->markTestSkipped('Bulk CSV import no longer produces skipped-rows report downloads.');

    $csv = <<<'CSV'
name,calories,protein,carbs,fat,micronutrients
,10,1,1,1,"{}"
Pear,57,0.4,15,0.1,"{}"
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-skipped-report.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('importSummary.skipped', 1)
        ->call('downloadSkippedRowsCsv')
        ->assertFileDownloaded('ingredients-import-skipped-rows.csv');
});

test('ingredients import splits multiline names in a single cell', function () {
    $this->markTestSkipped('Bulk CSV import expects one ingredient per row (analysis export).');

    $csv = <<<'CSV'
name,calories,protein,carbs,fat,micronutrients
"Chicken breast
Apple
Rice",0,0,0,0,"{}"
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-multiline-cell.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('importSummary.created', 3)
        ->assertSet('importSummary.updated', 0)
        ->assertSet('importSummary.skipped', 0)
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', ['name' => 'Chicken breast']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Apple']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Rice']);
});

test('ingredients import supports semicolon separated plain list in a single line', function () {
    $this->markTestSkipped('Plain-list import was removed in favor of analysis-style CSV bulk imports.');

    $csv = 'Chicken breast;Apple;Rice;Olive oil';

    $file = UploadedFile::fake()->createWithContent('ingredients-semicolon-line.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('importSummary.created', 4)
        ->assertSet('importSummary.updated', 0)
        ->assertSet('importSummary.skipped', 0)
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', ['name' => 'Chicken breast']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Apple']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Rice']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Olive oil']);
});

test('ingredients import fallback parses many header rows when csv parser under-processes', function () {
    $this->markTestSkipped('Bulk CSV import does not implement the legacy parsing fallbacks.');

    // Mirrors the user-provided structure (header + many name rows).
    $csv = <<<'CSV'
name,calories,protein,carbs,fat,micronutrients
Chicken breast,89,1.1,22.8,0.3,"{""vitamin_c"":8.7,""potassium"":358}"
apple,,,,,
rice,,,,,
Aleppo Pepper,,,,,
Allspice,,,,,
Almond Butter,,,,,
Almond Flour,,,,,
Almonds,,,,,
Anchovies,,,,,
Apple Green,,,,,
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-many-rows.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', ['name' => 'Chicken breast']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Aleppo Pepper']);
    $this->assertDatabaseHas('ingredients', ['name' => 'Apple Green']);
});

test('ingredients import does not enrich missing macros', function () {
    $this->actingAs(User::factory()->create());

    $csv = <<<'CSV'
name,calories,protein_g,carbs_g,fat_g,folate_mcg,vitamin_b12_mcg
apple,,,,,,
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-missing-macros.csv', $csv);

    Livewire::test('pages::ingredients')
        ->set('importCsvFile', $file)
        ->call('importCsv')
        ->assertSet('error', null);

    $this->assertDatabaseHas('ingredients', ['name' => 'apple', 'calories' => 0]);
});

test('unresolved ingredients enrichment job can be queued', function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();

    Ingredient::query()->create([
        'name' => 'Unknown Spice',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Livewire::test('pages::ingredients')
        ->call('queueUnresolvedEnrichment')
        ->assertSet('error', null)
        ->assertSet('status', 'Queued nutrition enrichment for unresolved ingredients.');

    Queue::assertPushed(EnrichIngredientsNutritionJob::class);
})->skip('External API enrichment removed (CSV/manual only).');

test('ingredients enrich button replaces foundation chicken with SR legacy 171077 and writes enriched json export', function () {
    $this->actingAs(User::factory()->create());

    Config::set('services.usda.api_key', 'fake-usda');

    $ingredient = Ingredient::query()->create([
        'name' => 'Chicken, breast, meat only, raw',
        'calories' => 100,
        'protein' => 18,
        'carbs' => 0,
        'fat' => 2,
        'fdc_id' => 2_646_170,
        'usda_data_type' => 'Foundation',
        'micronutrients' => [
            'vitamin_b6' => 0,
            'vitamin_b9' => 0,
            'vitamin_b12' => 0,
        ],
        'fdc_key_nutrients' => [
            UsdaNutrientMath::FDC_VITAMIN_B6 => 0,
            UsdaNutrientMath::FDC_FOLATE => 0,
            UsdaNutrientMath::FDC_VITAMIN_B12 => 0,
        ],
        'is_verified' => true,
    ]);

    Http::preventStrayRequests();

    $foundationBreastBad = [
        'fdcId' => 2_646_170,
        'description' => 'Chicken, breast, meat only, raw',
        'dataType' => 'Foundation',
        'foodCategory' => ['description' => 'Poultry Products'],
        'foodNutrients' => [
            ['nutrient' => ['number' => '208', 'id' => 1008], 'amount' => 100],
            ['nutrient' => ['number' => '203', 'id' => 1003], 'amount' => 18],
            ['nutrient' => ['id' => 1178, 'number' => '418'], 'amount' => 0],
            ['nutrient' => ['id' => 1177, 'number' => '417'], 'amount' => 0],
            ['nutrient' => ['id' => 1175, 'number' => '415'], 'amount' => 0],
        ],
    ];

    $srBreastGood = [
        'fdcId' => 171_077,
        'description' => 'Chicken, broilers or fryers, breast, meat only, raw',
        'dataType' => 'SR Legacy',
        'foodCategory' => ['description' => 'Poultry Products'],
        'foodNutrients' => [
            ['nutrient' => ['number' => '208', 'id' => 1008], 'amount' => 120],
            ['nutrient' => ['number' => '203', 'id' => 1003], 'amount' => 22.5],
            ['nutrient' => ['number' => '204', 'id' => 1004], 'amount' => 2.6],
            ['nutrient' => ['number' => '205', 'id' => 1005], 'amount' => 0],
            ['nutrient' => ['number' => '291'], 'amount' => 0],
            ['nutrient' => ['id' => 1178, 'number' => '418'], 'amount' => 0.34],
            ['nutrient' => ['id' => 1177, 'number' => '417'], 'amount' => 9],
            ['nutrient' => ['id' => 1175, 'number' => '415'], 'amount' => 0.81],
            ['nutrient' => ['id' => 1106, 'number' => '320'], 'amount' => 5],
            ['nutrient' => ['id' => 1087, 'number' => '301'], 'amount' => 10],
        ],
    ];

    Http::fake(function (Request $request) use ($foundationBreastBad, $srBreastGood) {
        $url = $request->url();

        if (str_contains($url, '/fdc/v1/foods/search')) {
            return Http::response([
                'foods' => [
                    [
                        'fdcId' => 2_646_170,
                        'description' => 'Chicken, breast, meat only, raw',
                        'dataType' => 'Foundation',
                    ],
                    [
                        'fdcId' => 171_077,
                        'description' => 'Chicken, broilers or fryers, breast, meat only, raw',
                        'dataType' => 'SR Legacy',
                    ],
                ],
            ], 200);
        }

        if (str_contains($url, '/fdc/v1/foods') && $request->method() === 'POST') {
            /** @var array<string, mixed> $body */
            $body = json_decode($request->body(), true) ?? [];
            /** @var list<int> $fdcIds */
            $fdcIds = $body['fdcIds'] ?? [];
            $out = [];

            foreach ($fdcIds as $id) {
                $id = (int) $id;

                if ($id === 2_646_170) {
                    $out[] = $foundationBreastBad;
                } elseif ($id === 171_077) {
                    $out[] = $srBreastGood;
                }
            }

            return Http::response(['foods' => $out], 200);
        }

        return Http::response(['unexpected' => true], 500);
    });

    Livewire::test('pages::ingredients')
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();

    expect($ingredient->fdc_id)->toBe(171_077)
        ->and((float) $ingredient->calories)->toBe(120.0)
        ->and((float) $ingredient->protein)->toBe(22.5)
        ->and((float) ($ingredient->micronutrients['vitamin_b6'] ?? 0))->toBe(0.81)
        ->and((float) ($ingredient->micronutrients['vitamin_b9'] ?? 0))->toBe(9.0)
        ->and((float) ($ingredient->micronutrients['vitamin_b12'] ?? 0))->toBe(0.34)
        ->and($ingredient->isSickleCellApproved())->toBeTrue();

    $exportPath = storage_path('app/Enriched_Meal_Craft_Ingredients.json');

    expect(File::exists($exportPath))->toBeTrue();

    /** @var list<array<string, mixed>> $rows */
    $rows = json_decode(File::get($exportPath), true, 512, JSON_THROW_ON_ERROR);

    $row = collect($rows)->firstWhere('id', $ingredient->id);

    expect($row)->toBeArray()
        ->and($row['fdc_id'])->toBe(171_077)
        ->and((float) $row['calories'])->toBe(120.0)
        ->and((float) $row['protein'])->toBe(22.5);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient can be enriched using stored name without opening the editor', function () {
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'capsicum',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect($ingredient->calories)->toBe(31.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient can be enriched with selected lookup term', function () {
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'My Capsicum Variant',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->set('enrichLookupPhrase', 'capsicum')
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect($ingredient->calories)->toBe(31.0);
    expect((float) ($ingredient->micronutrients['vitamin_c'] ?? 0))->toBeGreaterThan(0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment uses selected lookup suggestion first', function () {
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'Capsicum (Fresh)',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->set('enrichLookupPhrase', 'capsicum')
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect($ingredient->calories)->toBe(31.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment stores a complete micronutrient set', function () {
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'Test Ingredient',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [[
                'nutriments' => [
                    'energy-kcal_100g' => 50,
                    'proteins_100g' => 1.2,
                    'carbohydrates_100g' => 10,
                    'fat_100g' => 0.5,
                    'vitamin-c_100g' => 4.2,
                ],
            ]],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect($ingredient->micronutrients)->toHaveKeys([
        'vitamin_a',
        'vitamin_b6',
        'vitamin_b9',
        'vitamin_b12',
        'vitamin_c',
        'vitamin_d',
        'vitamin_e',
        'fiber',
        'calcium',
        'iron',
        'magnesium',
        'potassium',
    ]);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment supplements missing micronutrients from usda', function () {
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'Custom Unknown Herb',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Config::set('services.usda.api_key', 'test-usda-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [[
                'nutriments' => [
                    'energy-kcal_100g' => 45,
                    'proteins_100g' => 1.0,
                    'carbohydrates_100g' => 8,
                    'fat_100g' => 0.4,
                    'vitamin-c_100g' => 2.0,
                ],
            ]],
        ], 200),
        'https://api.nal.usda.gov/fdc/v1/foods/search*' => Http::response([
            'foods' => [[
                'foodNutrients' => [
                    ['nutrientNumber' => '1008', 'value' => 45],
                    ['nutrientNumber' => '1003', 'value' => 1.0],
                    ['nutrientNumber' => '1005', 'value' => 8],
                    ['nutrientNumber' => '1004', 'value' => 0.4],
                    ['nutrientNumber' => '418', 'value' => 0.9],
                    ['nutrientNumber' => '303', 'value' => 1.2],
                ],
            ]],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect((float) ($ingredient->micronutrients['vitamin_b12'] ?? 0))->toBe(0.9);
    expect((float) ($ingredient->micronutrients['iron'] ?? 0))->toBe(1.2);
    expect((float) ($ingredient->micronutrients['vitamin_c'] ?? 0))->toBe(2.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment supplements missing micronutrients from calorieking', function () {
    $this->actingAs(User::factory()->create());
    $ingredient = Ingredient::query()->create([
        'name' => 'CalorieKing Test Ingredient',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Config::set('services.calorieking.token', 'test-calorieking-token');
    Config::set('services.calorieking.base_url', 'https://foodapi.calorieking.com/v1');
    Config::set('services.usda.api_key', '');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [[
                'nutriments' => [
                    'energy-kcal_100g' => 80,
                    'proteins_100g' => 2,
                    'carbohydrates_100g' => 15,
                    'fat_100g' => 1,
                    'vitamin-c_100g' => 1.5,
                ],
            ]],
        ], 200),
        'https://foodapi.calorieking.com/v1/foods*' => Http::response([
            'foods' => [[
                'name' => 'CalorieKing Test Ingredient',
                'nutrients' => [
                    'calories' => 80,
                    'protein' => 2,
                    'carbohydrates' => 15,
                    'fat' => 1,
                    'vitamin_d' => 1.1,
                    'magnesium' => 12,
                ],
            ]],
        ], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect((float) ($ingredient->micronutrients['vitamin_d'] ?? 0))->toBe(1.1);
    expect((float) ($ingredient->micronutrients['magnesium'] ?? 0))->toBe(12.0);
    expect((float) ($ingredient->micronutrients['vitamin_c'] ?? 0))->toBe(1.5);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment maps common name variants to fallback library', function () {
    $this->actingAs(User::factory()->create());

    $oliveOil = Ingredient::query()->create([
        'name' => 'Olive Oil (Extra Virgin)',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    $appleVariant = Ingredient::query()->create([
        'name' => 'Apple Red',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $oliveOil->id)
        ->call('enrichIngredient', $oliveOil->id)
        ->call('editIngredient', $appleVariant->id)
        ->call('enrichIngredient', $appleVariant->id)
        ->assertSet('error', null);

    $oliveOil->refresh();
    $appleVariant->refresh();

    expect($oliveOil->calories)->toBe(884.0);
    expect($appleVariant->calories)->toBe(52.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment maps basmati rice variant to rice fallback', function () {
    $this->actingAs(User::factory()->create());

    $ingredient = Ingredient::query()->create([
        'name' => 'Basmati Rice Brown',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect($ingredient->calories)->toBe(130.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment maps butter variants from dropdown suggestion', function () {
    $this->actingAs(User::factory()->create());

    $ingredient = Ingredient::query()->create([
        'name' => 'Butter (Ghee, Salted, Unsalted)',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $ingredient->id)
        ->set('enrichLookupPhrase', 'Butter Salted')
        ->call('enrichIngredient', $ingredient->id)
        ->assertSet('error', null);

    $ingredient->refresh();
    expect($ingredient->calories)->toBe(717.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('single ingredient enrichment maps flour and vinegar variants to fallback library', function () {
    $this->actingAs(User::factory()->create());

    $flour = Ingredient::query()->create([
        'name' => 'Gluten-Free Flour (Blend)',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    $vinegar = Ingredient::query()->create([
        'name' => 'Balsamic Vinegar',
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'micronutrients' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
    ]);

    Livewire::test('pages::ingredients')
        ->call('editIngredient', $flour->id)
        ->call('enrichIngredient', $flour->id)
        ->call('editIngredient', $vinegar->id)
        ->call('enrichIngredient', $vinegar->id)
        ->assertSet('error', null);

    $flour->refresh();
    $vinegar->refresh();

    expect($flour->calories)->toBe(364.0);
    expect($vinegar->calories)->toBe(18.0);
})->skip('External API enrichment removed (CSV/manual only).');

test('ingredient lookup drops bare first word when a longer suggestion extends it', function () {
    $this->actingAs(User::factory()->create());
    Config::set('services.calorieking.token', '');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
    ]);

    $suggestions = Livewire::test('pages::ingredients')
        ->instance()
        ->lookupSuggestionsFor('chia seeds');

    $lower = collect($suggestions)->map(fn (string $s): string => Str::lower($s));

    expect($lower)->toContain('chia seeds')
        ->and($lower)->not->toContain('chia');
});

test('ingredient lookup suggestions are unique case insensitively', function () {
    $this->actingAs(User::factory()->create());
    Config::set('services.calorieking.token', '');

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
    ]);

    $suggestions = Livewire::test('pages::ingredients')
        ->instance()
        ->lookupSuggestionsFor('Butter (salted)');

    $lower = collect($suggestions)->map(fn (string $s): string => mb_strtolower($s));

    expect($lower->count())->toBe($lower->unique()->count());
});

test('ingredient lookup filters out calorieking results that share no words with the ingredient', function () {
    $this->markTestSkipped('lookupSuggestionsFor is local-only; external CalorieKing merge is no longer called from this method.');

    $this->actingAs(User::factory()->create());
    Config::set('services.calorieking.token', 'test-calorieking-token');
    Config::set('services.calorieking.base_url', 'https://foodapi.calorieking.com/v1');

    Cache::flush();

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
        'https://foodapi.calorieking.com/v1/foods*' => Http::response([
            'foods' => [
                ['name' => 'Chicken breast raw'],
                ['name' => 'Beef brisket trimmed'],
            ],
        ], 200),
    ]);

    $suggestions = Livewire::test('pages::ingredients')
        ->instance()
        ->lookupSuggestionsFor('Chicken breast');

    expect($suggestions)->toContain('Chicken breast raw')
        ->and($suggestions)->not->toContain('Beef brisket trimmed');
});

test('ingredient lookup suggestions merge calorieking food names when token is configured', function () {
    $this->markTestSkipped('lookupSuggestionsFor is local-only; external CalorieKing merge is no longer called from this method.');
    $this->actingAs(User::factory()->create());
    Config::set('services.calorieking.token', 'test-calorieking-token');
    Config::set('services.calorieking.base_url', 'https://foodapi.calorieking.com/v1');

    Cache::flush();

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []], 200),
        'https://foodapi.calorieking.com/v1/foods*' => Http::response([
            'foods' => [
                ['name' => 'Milk 2% lowfat'],
                ['name' => 'Milk whole'],
                ['name' => 'milk whole'],
            ],
        ], 200),
    ]);

    $suggestions = Livewire::test('pages::ingredients')
        ->instance()
        ->lookupSuggestionsFor('Milk (1%)');

    expect($suggestions)->toContain('Milk 2% lowfat')
        ->and($suggestions)->toContain('Milk whole')
        ->and(
            collect($suggestions)->filter(fn (string $s): bool => mb_strtolower($s) === 'milk whole')->count()
        )->toBe(1);
});

test('ingredient datalist suggestions are local-only and do not issue http requests', function () {
    $this->actingAs(User::factory()->create());

    Http::preventStrayRequests();

    $suggestions = Livewire::test('pages::ingredients')
        ->instance()
        ->lookupSuggestionsForDatalist('chia seeds');

    $lower = collect($suggestions)->map(fn (string $s): string => Str::lower($s));

    expect($lower)->toContain('chia seeds')
        ->and($lower)->not->toContain('chia');
});

test('ingredient lookup merges open food facts product names for varieties', function () {
    $this->markTestSkipped('lookupSuggestionsFor is local-only; Open Food Facts merge is no longer called from this method.');
    $this->actingAs(User::factory()->create());
    Config::set('services.calorieking.token', '');

    Cache::flush();

    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
            'products' => [
                ['code' => '1', 'product_name' => 'Coconut milk canned'],
                ['code' => '2', 'product_name' => 'Coconut water'],
                ['code' => '3', 'product_name' => 'Dried coconut'],
                ['code' => '4', 'product_name' => 'Cola soft drink'],
            ],
        ], 200),
    ]);

    $suggestions = Livewire::test('pages::ingredients')
        ->instance()
        ->lookupSuggestionsFor('coconut');

    expect($suggestions)->toContain('Coconut milk canned')
        ->and($suggestions)->toContain('Coconut water')
        ->and($suggestions)->toContain('Dried coconut')
        ->and($suggestions)->toContain('Coconut cream')
        ->and($suggestions)->not->toContain('Cola soft drink');
});
