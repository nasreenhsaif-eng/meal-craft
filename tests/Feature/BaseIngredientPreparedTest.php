<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\BaseIngredientService;
use App\Services\MealCsvLibraryImportService;
use App\Support\IngredientLibraryCategory;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

function verifiedIngredient(string $name, array $overrides = []): Ingredient
{
    return Ingredient::query()->create(array_merge([
        'name' => $name,
        'usda_food_category' => 'Vegetables',
        'calories' => 100,
        'protein' => 5,
        'carbs' => 10,
        'fat' => 2,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ], $overrides));
}

test('base recipe store requires finished cooked weight', function () {
    $user = User::factory()->create();
    $child = verifiedIngredient('Honey');

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.store'), [
            'name' => 'Honey Glaze',
            'is_base_recipe' => true,
            'components' => [
                ['ingredient_id' => $child->id, 'amount_grams' => 100],
            ],
        ])
        ->assertSessionHasErrors('finished_weight_grams');
});

test('admin can store a base recipe via unified ingredient library store route', function () {
    $user = User::factory()->create();
    $child = verifiedIngredient('Honey', ['calories' => 300, 'protein' => 0, 'carbs' => 80, 'fat' => 0]);

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.store'), [
            'name' => 'Honey Glaze',
            'is_base_recipe' => true,
            'finished_weight_grams' => 100,
            'components' => [
                ['ingredient_id' => $child->id, 'amount_grams' => 100],
            ],
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Honey Glaze')->firstOrFail();

    expect($base->isPreparedBaseIngredient())->toBeTrue()
        ->and((float) $base->calories)->toBe(300.0);
});

test('admin can update a base recipe via base-ingredient update route', function () {
    $user = User::factory()->create();
    $child = verifiedIngredient('Salt');
    $base = app(BaseIngredientService::class)->upsert(
        null,
        'Update Test Base',
        [['ingredient_id' => $child->id, 'amount_grams' => 10]],
        100,
    );

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.base-ingredient.update', $base), [
            'name' => 'Update Test Base Renamed',
            'finished_weight_grams' => 100,
            'components' => [
                ['ingredient_id' => $child->id, 'amount_grams' => 25],
            ],
            'description' => 'Updated description',
            'instructions' => 'Step 1: Mix.',
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base->refresh();

    expect($base->name)->toBe('Update Test Base Renamed')
        ->and($base->description)->toBe('Updated description')
        ->and((float) $base->components->first()->pivot->amount_grams)->toBe(25.0);
});

test('admin can store a prepared base ingredient with component pivot and per-100g macros', function () {
    $user = User::factory()->create();
    $tomato = verifiedIngredient('Tomato Paste', ['calories' => 80, 'protein' => 4, 'carbs' => 16, 'fat' => 0]);
    $oil = verifiedIngredient('Olive Oil', ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100]);

    $response = $this->actingAs($user)
        ->post(route('admin.ingredient-library.base-ingredient.store'), [
            'name' => 'Marinara Base',
            'finished_weight_grams' => 200,
            'components' => [
                ['ingredient_id' => $tomato->id, 'amount_grams' => 150],
                ['ingredient_id' => $oil->id, 'amount_grams' => 50],
            ],
        ]);

    $response->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Marinara Base')->firstOrFail();

    expect($base->usda_food_category)->toBe(IngredientLibraryCategory::BaseIngredient)
        ->and($base->isPreparedBaseIngredient())->toBeTrue()
        ->and($base->source_meal_id)->toBeNull()
        ->and($base->is_verified)->toBeTrue();

    expect($base->components()->pluck('ingredients.id')->all())
        ->toEqualCanonicalizing([$tomato->id, $oil->id]);

    // Batch: 150g tomato (80 kcal/100g) + 50g oil (884 kcal/100g) => 562 kcal / 200g finished => 281 kcal/100g
    expect((float) $base->calories)->toBe(281.0);
});

test('base ingredient service upsert uses sum of components when finished weight is omitted', function () {
    $child = verifiedIngredient('Sugar', ['calories' => 400, 'protein' => 0, 'carbs' => 100, 'fat' => 0]);

    $base = app(BaseIngredientService::class)->upsert(
        null,
        'Simple Syrup',
        [['ingredient_id' => $child->id, 'amount_grams' => 100]],
        null,
    );

    expect((float) $base->calories)->toBe(400.0)
        ->and($base->components)->toHaveCount(1);
});

test('ingredient library csv import classifies (Base) suffix rows as base ingredients even without recipe_components', function () {
    $user = User::factory()->create();

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
    $row = 'Spiced Aleppo Ground Beef (Base),Proteins,,248,21.4,4.2,16.5,0.28,12,1.9,2.4,25,1.1,1.8,24,312,285,4.8,2.1,38,0.6,0,2.4,0.96,0,,Warm description,Step 1: Cook.,,0';
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Spiced Aleppo Ground Beef (Base)')->firstOrFail();

    expect($base->isPreparedBaseIngredient())->toBeTrue()
        ->and($base->usda_food_category)->toBe(IngredientLibraryCategory::BaseIngredient);
});

test('ingredient library csv import resolves pipe-separated name weight recipe components', function () {
    $user = User::factory()->create();
    $carrots = verifiedIngredient('Carrots, raw', ['calories' => 40, 'protein' => 1, 'carbs' => 9, 'fat' => 0]);

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
    $row = 'Carrot Paste,,,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,"Carrots, raw (150g)",,,200,';
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Carrot Paste')->firstOrFail();

    expect($base->isPreparedBaseIngredient())->toBeTrue()
        ->and($base->components)->toHaveCount(1)
        ->and($base->components->first()->id)->toBe($carrots->id);
});

test('ingredient library csv import normalizes base recipe instructions into Step lines', function () {
    $user = User::factory()->create();
    $child = verifiedIngredient('Coriander', ['calories' => 20, 'protein' => 2, 'carbs' => 3, 'fat' => 0]);

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger,image_url';
    $instructions = 'Chop coriander. Mince garlic. Whisk oil and lime.';
    $row = "Dressing Base,,,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,{$child->id}:50,,{$instructions},100,0,http://example.com/photo.jpg";
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Dressing Base')->firstOrFail();

    expect($base->instructions)->toBe(
        "Step 1: Chop coriander.\nStep 2: Mince garlic.\nStep 3: Whisk oil and lime.",
    );
});

test('ingredient library csv import creates base recipe from is_base_recipe and recipe_components', function () {
    $user = User::factory()->create();
    $child = verifiedIngredient('Tomato', ['calories' => 80, 'protein' => 4, 'carbs' => 16, 'fat' => 0]);

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
    $row = "Paste Base,,,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,{$child->id}:150,,,200,";
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Paste Base')->firstOrFail();

    expect($base->isPreparedBaseIngredient())->toBeTrue()
        ->and((float) $base->calories)->toBe(60.0);
});

test('ingredient library csv import explains when nested base recipe component is missing', function () {
    $user = User::factory()->create();
    verifiedIngredient('Chicken Breast', ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6]);

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
    $row = 'Tandoori Chicken (Base),Base Ingredient,,150,28,1.5,3.2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,"Chicken Breast (500g) | Tandoori Spice Mix (Base) (45g)",,,400,';
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Nested base recipe')
            && str_contains($message, 'Tandoori Spice Mix (Base)')
            && str_contains($message, 'Import that base recipe'));
});

test('ingredient library csv import allows nested base recipe components', function () {
    $user = User::factory()->create();
    $spice = verifiedIngredient('Tandoori Spice Mix (Base)', [
        'usda_food_category' => IngredientLibraryCategory::BaseIngredient,
        'calories' => 200,
        'protein' => 5,
        'carbs' => 10,
        'fat' => 8,
    ]);
    verifiedIngredient('Chicken Breast', ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6]);

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
    $row = 'Tandoori Chicken (Base),Base Ingredient,,150,28,1.5,3.2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,"Chicken Breast (500g) | Tandoori Spice Mix (Base) (45g)",,,400,';
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success');

    $base = Ingredient::query()->where('name', 'Tandoori Chicken (Base)')->firstOrFail();

    expect($base->isPreparedBaseIngredient())->toBeTrue()
        ->and($base->components->pluck('id')->all())->toContain($spice->id);
});

test('ingredient library csv import accepts meal_name header and pipe-separated recipe components', function () {
    $user = User::factory()->create();
    verifiedIngredient('Eggplant', ['calories' => 25, 'protein' => 1, 'carbs' => 6, 'fat' => 0]);
    verifiedIngredient('Olive Oil (Extra Virgin)', ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100]);

    $header = 'Meal_Name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k2,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
    $row = 'Roasted Vegetables (Base),Base Ingredient,,62,1.3,6.4,3.8,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,"Eggplant (200g) | Olive Oil (Extra Virgin) (14g)",Short desc.,Step 1: Roast.,400,';
    $csv = $header."\n".$row."\n";

    $this->actingAs($user)
        ->post(route('admin.ingredient-library.import-csv'), [
            'file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv),
        ])
        ->assertRedirect(route('admin.ingredient-library'))
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 ingredient'));

    $base = Ingredient::query()->where('name', 'Roasted Vegetables (Base)')->firstOrFail();

    expect($base->isPreparedBaseIngredient())->toBeTrue()
        ->and($base->components)->toHaveCount(2);
});

test('meal library csv import maps recipe_components column for base recipe category', function () {
    $user = User::factory()->create();
    verifiedIngredient('Eggplant', ['calories' => 25, 'protein' => 1, 'carbs' => 6, 'fat' => 0]);

    $csv = "name,category,recipe_components\nRoasted Veg (Base),Bases,Eggplant (200g)\n";

    $result = app(MealCsvLibraryImportService::class)->processPath(
        (function () use ($csv): string {
            $path = tempnam(sys_get_temp_dir(), 'meal-csv-');
            file_put_contents($path, $csv);

            return $path;
        })(),
        $user,
    );

    expect($result['summary']['ingredient_library_imported'])->toBe(1)
        ->and($result['rows'][0]['status'] ?? '')->toBe('imported')
        ->and($result['rows'][0]['saved_to'] ?? '')->toBe('ingredient_library');
});

test('base recipe meals are excluded from meal library inertia index', function () {
    $user = User::factory()->create();

    Meal::query()->create([
        'name' => 'Visible Main',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'description' => null,
        'total_calories' => 500,
        'total_protein' => 30,
        'total_carbs' => 40,
        'total_fat' => 15,
    ]);

    Meal::query()->create([
        'name' => 'Hidden Base Recipe',
        'category' => RecipeCategory::BaseRecipe,
        'meal_type' => MealType::BaseRecipe,
        'description' => null,
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 10,
        'total_fat' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/MealLibrary')
            ->has('meals', 1)
            ->where('meals.0.title', 'Visible Main'));
});
