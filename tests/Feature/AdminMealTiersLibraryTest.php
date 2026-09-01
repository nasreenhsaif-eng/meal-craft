<?php

use App\Enums\MealLibraryKey;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealCalorieTier;
use App\Models\User;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Support\ChickenKitchenPlateTargets;

test('the meal library index does not include meal tiers library meals', function () {
    $user = User::factory()->create();

    $classic = Meal::factory()->create(['name' => 'Classic Chicken Plate']);
    $tiers = Meal::factory()->create([
        'name' => 'Tiers Chicken Plate',
        'library_key' => MealLibraryKey::Tiers,
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-library'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MealLibrary')
            ->has('meals', 1)
            ->where('meals.0.title', 'Classic Chicken Plate')
            ->where('meals.0.browseTab', 'chicken')
            ->has('browseTabs')
            ->where('browseTabs.0.id', 'all')
            ->where('browseTabs.1.id', 'chicken'));
});

test('the meal tiers library index only includes tiers meals', function () {
    $user = User::factory()->create();

    Meal::factory()->create(['name' => 'Classic Stay Put']);
    $tiers = Meal::factory()->create([
        'name' => 'Tiers Chicken Plate',
        'library_key' => MealLibraryKey::Tiers,
    ]);

    $this->actingAs($user)
        ->get(route('admin.meal-tiers-library'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MealTiersLibrary')
            ->has('meals', 1)
            ->where('meals.0.title', $tiers->name)
            ->where('meals.0.browseTab', 'chicken')
            ->has('browseTabs')
            ->where('browseTabs.0.id', 'all')
            ->where('browseTabs.1.id', 'chicken'));
});

test('copying a classic chicken meal into the tiers library creates a new row with calorie tabs', function () {
    $user = User::factory()->create();

    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'is_verified' => true,
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
        'usda_food_category' => 'Proteins',
    ]);
    $rice = Ingredient::factory()->create([
        'name' => 'Brown Rice',
        'is_verified' => true,
        'calories' => 110,
        'protein' => 2.5,
        'carbs' => 23,
        'fat' => 0.9,
        'usda_food_category' => 'Grains',
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'is_verified' => true,
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
        'usda_food_category' => 'Fats',
    ]);
    $salt = Ingredient::factory()->create([
        'name' => 'Sea Salt',
        'is_verified' => true,
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0,
        'usda_food_category' => 'Spices and Herbs',
    ]);

    $classic = Meal::factory()->create([
        'name' => 'Rosemary Garlic Chicken',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'library_key' => MealLibraryKey::Classic,
    ]);
    $classic->ingredients()->attach([
        $chicken->id => ['amount_grams' => 150, 'amount' => 150, 'unit' => 'g'],
        $rice->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 14, 'amount' => 14, 'unit' => 'g'],
        $salt->id => ['amount_grams' => 2, 'amount' => 2, 'unit' => 'g'],
    ]);

    $originalChickenGrams = (float) $classic->ingredients()->where('ingredients.id', $chicken->id)->first()?->pivot->amount_grams;

    $this->actingAs($user)
        ->post(route('admin.meal-tiers-library.copy'), ['meal_id' => $classic->id])
        ->assertRedirect(route('admin.meal-tiers-library'));

    $classic->refresh();
    expect((float) $classic->ingredients()->where('ingredients.id', $chicken->id)->first()?->pivot->amount_grams)
        ->toBe($originalChickenGrams)
        ->and($classic->library_key)->toBe(MealLibraryKey::Classic);

    $copy = Meal::queryForMealTiersLibrary()->where('name', 'Rosemary Garlic Chicken')->first();
    expect($copy)->not->toBeNull()
        ->and($copy->id)->not->toBe($classic->id)
        ->and($copy->library_key)->toBe(MealLibraryKey::Tiers);

    $tiers = MealCalorieTier::query()->where('meal_id', $copy->id)->orderBy('calorie_tier')->get();
    expect($tiers->pluck('calorie_tier')->all())->toBe([400, 500, 550, 600, 700, 800]);

    $fourHundred = $tiers->firstWhere('calorie_tier', 400);
    $chickenOnFourHundred = $fourHundred?->ingredients()->where('ingredients.id', $chicken->id)->first();
    expect($chickenOnFourHundred)->not->toBeNull()
        ->and((float) $chickenOnFourHundred->pivot->amount_grams)->toBe(135.0);

    $this->actingAs($user)
        ->get(route('admin.meal-tiers-library'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MealTiersLibrary')
            ->where('meals.0.title', 'Rosemary Garlic Chicken')
            ->where('meals.0.detailView.shortDescription', '')
            ->where('meals.0.calorieTiers.0.ingredientSections.0.title', 'Protein')
            ->where('meals.0.calorieTiers.0.ingredientSections.0.items.0.ingredientId', fn ($id) => is_int($id) && $id > 0)
            ->where('meals.0.calorieTiers.0.ingredientSections.0.items.0.isBaseRecipe', false)
            ->where('meals.0.calorieTiers.0.ingredients.0.name', 'Chicken Breast')
            ->where('meals.0.calorieTiers.0.ingredients.0.is_base_recipe', false));
});

test('admins can store a one-size chia pudding in the meal tiers library', function () {
    $user = User::factory()->create();
    $chia = Ingredient::factory()->create([
        'name' => 'Chia Seeds',
        'is_verified' => true,
        'calories' => 486,
        'protein' => 17,
        'carbs' => 42,
        'fat' => 31,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-tiers-library.store'), [
            'name' => 'Blueberry Walnut Chia Pudding',
            'category' => RecipeCategory::Dessert->value,
            'ingredients' => [
                ['ingredient_id' => $chia->id, 'amount_grams' => 40],
            ],
        ])
        ->assertRedirect(route('admin.meal-tiers-library'));

    $meal = Meal::queryForMealTiersLibrary()->where('name', 'Blueberry Walnut Chia Pudding')->firstOrFail();
    expect($meal->library_key)->toBe(MealLibraryKey::Tiers)
        ->and($meal->calorieTiers)->toHaveCount(0)
        ->and($meal->ingredients)->toHaveCount(1);
});

test('queryForMealLibrary still returns classic meals after a tiers copy', function () {
    $classic = Meal::factory()->create(['name' => 'Keep Classic']);
    Meal::factory()->create([
        'name' => 'Tiers Hidden From Classic Query',
        'library_key' => MealLibraryKey::Tiers,
    ]);

    expect(Meal::queryForMealLibrary()->whereKey($classic->id)->exists())->toBeTrue()
        ->and(Meal::queryForMealLibrary()->where('name', 'Tiers Hidden From Classic Query')->exists())->toBeFalse();
});

test('copying the rosemary garlic chicken plate authors the kitchen oil plate', function () {
    $user = User::factory()->create();

    $base = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'is_verified' => true,
        'usda_food_category' => 'Base Ingredient',
    ]);
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'is_verified' => true,
        'calories' => 120,
        'protein' => 23,
        'carbs' => 0,
        'fat' => 2.6,
        'usda_food_category' => 'Proteins',
    ]);
    $potato = Ingredient::factory()->create(['name' => 'Sweet Potato', 'is_verified' => true, 'usda_food_category' => 'Vegetables']);
    $spinach = Ingredient::factory()->create(['name' => 'Spinach (Fresh)', 'is_verified' => true, 'usda_food_category' => 'Vegetables']);
    $mushrooms = Ingredient::factory()->create(['name' => 'Mushrooms', 'is_verified' => true, 'usda_food_category' => 'Vegetables']);
    $oil = Ingredient::factory()->create(['name' => 'Olive Oil (Extra Virgin)', 'is_verified' => true, 'usda_food_category' => 'Fats']);
    $garlic = Ingredient::factory()->create(['name' => 'Garlic (Raw)', 'is_verified' => true]);
    $rosemary = Ingredient::factory()->create(['name' => 'Rosemary (Fresh)', 'is_verified' => true]);
    $pepper = Ingredient::factory()->create(['name' => 'Black Pepper', 'is_verified' => true]);

    $classic = Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'library_key' => MealLibraryKey::Classic,
    ]);
    $classic->ingredients()->attach([
        $base->id => ['amount_grams' => 115, 'amount' => 115, 'unit' => 'g'],
        $potato->id => ['amount_grams' => 85, 'amount' => 85, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 55, 'amount' => 55, 'unit' => 'g'],
        $mushrooms->id => ['amount_grams' => 45, 'amount' => 45, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $garlic->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $rosemary->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $pepper->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-tiers-library.copy'), ['meal_id' => $classic->id])
        ->assertRedirect(route('admin.meal-tiers-library'));

    $copy = Meal::queryForMealTiersLibrary()
        ->where('name', BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME)
        ->first();

    expect($copy)->not->toBeNull();

    $fiveHundred = MealCalorieTier::query()
        ->where('meal_id', $copy->id)
        ->where('calorie_tier', 500)
        ->first();

    $fourHundred = MealCalorieTier::query()
        ->where('meal_id', $copy->id)
        ->where('calorie_tier', 400)
        ->first();
    $eightHundred = MealCalorieTier::query()
        ->where('meal_id', $copy->id)
        ->where('calorie_tier', 800)
        ->first();

    expect($fiveHundred)->not->toBeNull()
        ->and((float) $fiveHundred->ingredients()->where('ingredients.id', $base->id)->first()?->pivot->amount_grams)->toBe(130.0)
        ->and((float) $fiveHundred->ingredients()->where('ingredients.id', $oil->id)->first()?->pivot->amount_grams)->toBe(5.0)
        ->and($fiveHundred->ingredients()->where('ingredients.id', $chicken->id)->exists())->toBeFalse()
        ->and((float) $fourHundred?->ingredients()->where('ingredients.id', $base->id)->first()?->pivot->amount_grams)->toBe(100.0)
        ->and((float) $fourHundred?->ingredients()->where('ingredients.id', $oil->id)->first()?->pivot->amount_grams)->toBe(5.0)
        ->and((float) $eightHundred?->ingredients()->where('ingredients.id', $base->id)->first()?->pivot->amount_grams)->toBe(225.0)
        ->and((float) $eightHundred?->ingredients()->where('ingredients.id', $oil->id)->first()?->pivot->amount_grams)->toBe(5.0)
        ->and(ChickenKitchenPlateTargets::containerMl())->toBe(1000.0);

    expect((float) $classic->ingredients()->where('ingredients.id', $base->id)->first()?->pivot->amount_grams)->toBe(115.0);
});

test('copy all missing classic meals into the meal tiers library', function () {
    $user = User::factory()->create();

    Meal::factory()->create(['name' => 'Classic Alpha Bowl']);
    Meal::factory()->create(['name' => 'Classic Beta Bowl']);
    Meal::factory()->create([
        'name' => 'Classic Alpha Bowl',
        'library_key' => MealLibraryKey::Tiers,
    ]);

    $this->actingAs($user)
        ->post(route('admin.meal-tiers-library.copy-all'))
        ->assertRedirect(route('admin.meal-tiers-library'));

    expect(Meal::queryForMealTiersLibrary()->where('name', 'Classic Alpha Bowl')->count())->toBe(1)
        ->and(Meal::queryForMealTiersLibrary()->where('name', 'Classic Beta Bowl')->count())->toBe(1);
});

test('copy all skips classic meals excluded from the meal tiers library', function () {
    $user = User::factory()->create();

    Meal::factory()->create(['name' => 'Salmon Plate']);
    Meal::factory()->create(['name' => 'Classic Gamma Bowl']);

    $this->actingAs($user)
        ->post(route('admin.meal-tiers-library.copy-all'))
        ->assertRedirect(route('admin.meal-tiers-library'));

    expect(Meal::queryForMealTiersLibrary()->where('name', 'Salmon Plate')->count())->toBe(0)
        ->and(Meal::queryForMealTiersLibrary()->where('name', 'Classic Gamma Bowl')->count())->toBe(1);
});
