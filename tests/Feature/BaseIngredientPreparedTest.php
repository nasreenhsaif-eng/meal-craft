<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\BaseIngredientService;
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

test('ingredient library csv import creates base recipe from is_base_recipe and recipe_components', function () {
    $user = User::factory()->create();
    $child = verifiedIngredient('Tomato', ['calories' => 80, 'protein' => 4, 'carbs' => 16, 'fat' => 0]);

    $header = 'name,category,fdc_id,calories,protein,carbs,fat,b6,b9_folate,b12,iron,magnesium,fiber,sugar,calcium,potassium,sodium,zinc,vitamin_c,vitamin_a,vitamin_e,vitamin_d,vitamin_k,density,is_base_recipe,recipe_components,description,instructions,finished_weight_grams,g6pd_trigger';
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
