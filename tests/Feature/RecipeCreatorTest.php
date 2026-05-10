<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\RecipeNutritionCalculator;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

test('authenticated users can view meals hub', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('meals.index'))->assertOk();
});

test('meal type dropdown shows only five types', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('meals.index'));

    $response->assertOk();

    $response->assertSee(__('Breakfast'), escape: false);
    $response->assertSee(__('Meal'), escape: false);
    $response->assertSee(__('Soup'), escape: false);
    $response->assertSee(__('Side salad'), escape: false);
    $response->assertSee(__('Dessert'), escape: false);

    $response->assertDontSee(__('Snack'), escape: false);
    $response->assertDontSee(__('Base recipe'), escape: false);
});

test('authenticated users can view meal edit route on the hub', function () {
    $this->actingAs(User::factory()->create());

    $meal = Meal::query()->create([
        'name' => 'Editable',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 10,
        'total_fat' => 3,
    ]);

    $this->get(route('meals.edit', $meal))->assertOk();
});

test('legacy recipe URLs redirect to the meals hub', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/recipes')->assertRedirect(route('meals.index'));
    $this->get('/recipes/create')->assertRedirect(route('meals.index'));
});

test('legacy recipe edit URL redirects to meal edit', function () {
    $this->actingAs(User::factory()->create());

    $meal = Meal::query()->create([
        'name' => 'Migrated',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'total_calories' => 50,
        'total_protein' => 2,
        'total_carbs' => 5,
        'total_fat' => 1,
    ]);

    $this->get('/recipes/'.$meal->id.'/edit')->assertRedirect(route('meals.edit', $meal));
});

test('meal hub loads full ingredient list ordered by name', function () {
    $this->actingAs(User::factory()->create());

    Ingredient::query()->create([
        'name' => 'Zebra Mussel Freekeh',
        'usda_food_category' => 'Other',
        'calories' => 100,
        'protein' => 5,
        'carbs' => 20,
        'fat' => 2,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Ingredient::query()->create([
        'name' => 'Apple Rings',
        'usda_food_category' => 'Other',
        'calories' => 50,
        'protein' => 0,
        'carbs' => 12,
        'fat' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $component = Livewire::test('pages::meals');

    $options = $component->instance()->ingredientSearchResults(0);

    expect($options)->toHaveCount(2)
        ->and($options->first()->name)->toBe('Apple Rings')
        ->and($options->last()->name)->toBe('Zebra Mussel Freekeh');
});

test('meals hub handles ingredientsImported listener', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::meals')
        ->dispatch('ingredientsImported')
        ->assertSee(__('Meal management hub'));
});

test('meal hub rejects meal types outside the allowed list', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::meals')
        ->set('name', 'Invalid Type')
        ->set('mealType', 'dinner')
        ->set('recipeIngredients', [
            ['ingredient_id' => null, 'amount' => 100, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasErrors(['mealType']);
});

test('meal list filter limits saved meals by meal type', function () {
    $this->actingAs(User::factory()->create());

    Meal::query()->create([
        'name' => 'Tomato Soup',
        'category' => RecipeCategory::Soup,
        'meal_type' => MealType::Soup,
        'description' => null,
        'total_calories' => 100,
        'total_protein' => 2,
        'total_carbs' => 10,
        'total_fat' => 3,
    ]);

    Meal::query()->create([
        'name' => 'Power Bowl',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
        'description' => null,
        'total_calories' => 500,
        'total_protein' => 30,
        'total_carbs' => 40,
        'total_fat' => 15,
    ]);

    $component = Livewire::test('pages::meals')
        ->set('selectedMealType', MealType::Soup->value);

    $paginator = $component->get('filteredMeals');

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(1)
        ->and(collect($paginator->items())->first()->name)->toBe('Tomato Soup');
});

test('meals hub paginates twenty four meals per page', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 25) as $i) {
        Meal::query()->create([
            'name' => "Meal {$i}",
            'category' => RecipeCategory::Meal,
            'meal_type' => MealType::Main,
            'description' => null,
            'total_calories' => 100,
            'total_protein' => 5,
            'total_carbs' => 10,
            'total_fat' => 3,
        ]);
    }

    $page1 = Livewire::test('pages::meals');
    $p1 = $page1->get('filteredMeals');

    expect($p1->perPage())->toBe(24)
        ->and($p1->count())->toBe(24)
        ->and($p1->total())->toBe(25);

    $page2 = Livewire::test('pages::meals')->call('gotoPage', 2);
    expect($page2->get('filteredMeals')->count())->toBe(1);
});

test('meal save syncs pivot when ingredient_id is a string like an HTML select', function () {
    $this->actingAs(User::factory()->create());

    $ingredient = Ingredient::query()->create([
        'name' => 'Test Ingredient',
        'usda_food_category' => 'Other',
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 2,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'String Id Meal')
        ->set('mealType', MealType::Main->value)
        ->set('recipeIngredients', [
            ['ingredient_id' => (string) $ingredient->id, 'amount' => 100, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors()
        ->assertSet('status', __('Meal saved.'));

    $meal = Meal::query()->where('name', 'String Id Meal')->with('ingredients')->firstOrFail();

    expect($meal->ingredients)->toHaveCount(1)
        ->and((float) $meal->ingredients->first()->pivot->amount_grams)->toBe(100.0);
});

test('meal hub calculates totals and saves meal with pivot rows', function () {
    $this->actingAs(User::factory()->create());

    $chicken = Ingredient::query()->create([
        'name' => 'Chicken Breast',
        'usda_food_category' => 'Proteins',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
        'b9_folate' => 4,
        'b12' => 0.3,
        'iron' => 1,
        'magnesium' => 29,
        'micronutrients' => ['zinc' => 1.0],
        'is_verified' => true,
    ]);

    $spinach = Ingredient::query()->create([
        'name' => 'Spinach',
        'usda_food_category' => 'Vegetables',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
        'b9_folate' => 194,
        'b12' => 0,
        'iron' => 2.7,
        'magnesium' => 79,
        'micronutrients' => ['zinc' => 0.5],
        'is_verified' => true,
    ]);

    Livewire::test('pages::meals')
        ->set('name', 'Chicken + Spinach Bowl')
        ->set('mealType', MealType::Main->value)
        ->set('instructions', 'Cook chicken. Add spinach.')
        ->set('recipeIngredients', [
            ['ingredient_id' => $chicken->id, 'amount' => 100, 'unit' => 'g'],
            ['ingredient_id' => $spinach->id, 'amount' => 50, 'unit' => 'g'],
        ])
        ->assertSet('calculatedNutrition.zinc', 1.25)
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors()
        ->assertSet('status', __('Meal saved.'));

    $this->assertDatabaseHas('meals', [
        'name' => 'Chicken + Spinach Bowl',
        'category' => RecipeCategory::Meal->value,
        'total_zinc' => 1.25,
    ]);

    $meal = Meal::query()->where('name', 'Chicken + Spinach Bowl')->firstOrFail();

    expect((float) $meal->total_calories)->toBeGreaterThan(0)
        ->and((float) $meal->total_protein)->toBeGreaterThan(0);

    $this->assertDatabaseHas('ingredient_meal', [
        'meal_id' => $meal->id,
        'ingredient_id' => $chicken->id,
        'unit' => 'g',
        'amount' => 100,
    ]);
    $this->assertDatabaseHas('ingredient_meal', [
        'meal_id' => $meal->id,
        'ingredient_id' => $spinach->id,
        'unit' => 'g',
        'amount' => 50,
    ]);

    expect($meal->fresh()->load('ingredients')->nutritionForDisplay()['zinc'])->toBe(1.25);
});

test('user can open meal details modal and delete a meal', function () {
    $this->actingAs(User::factory()->create());

    $meal = Meal::query()->create([
        'name' => 'To Delete',
        'category' => RecipeCategory::Breakfast,
        'description' => 'Mix and serve.',
        'total_calories' => 200,
        'total_protein' => 10,
        'total_carbs' => 20,
        'total_fat' => 8,
    ]);

    Livewire::test('pages::meals')
        ->call('openMealDetails', $meal->id)
        ->assertSet('showMealDetailsModal', true)
        ->assertSet('detailsMealId', $meal->id)
        ->call('deleteMeal', $meal->id)
        ->assertSet('showMealDetailsModal', false)
        ->assertSet('detailsMealId', null);

    expect(Meal::query()->find($meal->id))->toBeNull();
});

test('user can edit and update an existing meal from the hub', function () {
    $this->actingAs(User::factory()->create());

    $egg = Ingredient::query()->create([
        'name' => 'Egg',
        'usda_food_category' => 'Proteins',
        'calories' => 140,
        'protein' => 12,
        'carbs' => 1,
        'fat' => 10,
        'b9_folate' => 50,
        'b12' => 1,
        'iron' => 2,
        'magnesium' => 20,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Old Name',
        'category' => RecipeCategory::Breakfast,
        'meal_type' => MealType::Breakfast,
        'description' => 'Old',
        'total_calories' => 50,
        'total_protein' => 5,
        'total_carbs' => 5,
        'total_fat' => 2,
    ]);

    $meal->ingredients()->sync([
        $egg->id => [
            'amount' => 50,
            'unit' => 'g',
            'amount_grams' => 50,
        ],
    ]);

    Livewire::test('pages::meals', ['meal' => $meal])
        ->assertSet('editingMealId', $meal->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'Scrambled Update')
        ->set('mealType', MealType::Main->value)
        ->set('instructions', 'Whisk and cook.')
        ->set('recipeIngredients', [
            ['ingredient_id' => $egg->id, 'amount' => 100, 'unit' => 'g'],
        ])
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors()
        ->assertSet('status', __('Meal updated.'));

    $meal->refresh();

    expect($meal->name)->toBe('Scrambled Update')
        ->and($meal->category)->toBe(RecipeCategory::Meal)
        ->and($meal->description)->toBe('Whisk and cook.');

    $this->assertDatabaseHas('ingredient_meal', [
        'meal_id' => $meal->id,
        'ingredient_id' => $egg->id,
    ]);

    $pivotGrams = (float) $meal->ingredients()->where('ingredients.id', $egg->id)->first()->pivot->amount_grams;
    expect($pivotGrams)->toBe(100.0);
});

test('meal hub cancel clears form and redirects to meals index', function () {
    $this->actingAs(User::factory()->create());

    $meal = Meal::query()->create([
        'name' => 'To Cancel',
        'category' => RecipeCategory::Breakfast,
        'description' => 'Note',
        'total_calories' => 10,
        'total_protein' => 1,
        'total_carbs' => 1,
        'total_fat' => 1,
    ]);

    Livewire::test('pages::meals', ['meal' => $meal])
        ->assertSet('editingMealId', $meal->id)
        ->assertSet('name', 'To Cancel')
        ->call('cancelMealEdit')
        ->assertRedirect(route('meals.index'));

    $fresh = Livewire::test('pages::meals');

    expect($fresh->get('editingMealId'))->toBeNull()
        ->and($fresh->get('name'))->toBe('');
});

test('meals hub edit meal redirects to meal editor route', function () {
    $this->actingAs(User::factory()->create());

    $meal = Meal::query()->create([
        'name' => 'Editable',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 10,
        'total_fat' => 3,
    ]);

    Livewire::test('pages::meals')
        ->call('editMeal', $meal->id)
        ->assertRedirect(route('meals.edit', $meal));
});

test('recipe nutrition calculator sickle cell highlights use whole recipe totals', function () {
    $nutrition = [
        'b9_folate' => 150.0,
        'b12' => 3.0,
        'magnesium' => 120.0,
        'iron' => 6.0,
    ];

    $h = RecipeNutritionCalculator::sickleCellHighlights($nutrition);

    expect($h['folate'])->toBeTrue()
        ->and($h['b12'])->toBeTrue()
        ->and($h['magnesium'])->toBeTrue()
        ->and($h['iron'])->toBeTrue();
});
