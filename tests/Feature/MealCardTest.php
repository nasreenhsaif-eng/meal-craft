<?php

use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\RecipeNutritionCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeIngredientForMealCard(array $overrides = []): Ingredient
{
    return Ingredient::query()->create(array_merge([
        'name' => 'Test Ingredient '.fake()->unique()->word(),
        'usda_food_category' => 'Other',
        'calories' => 200,
        'protein' => 10,
        'carbs' => 20,
        'fat' => 5,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ], $overrides));
}

test('fromMeal computes macros from pivot grams', function () {
    $ing = makeIngredientForMealCard([
        'calories' => 100,
        'protein' => 10,
        'carbs' => 5,
        'fat' => 2,
    ]);
    $meal = Meal::query()->create([
        'name' => 'Lunch',
        'description' => 'Test meal',
    ]);
    $meal->ingredients()->attach($ing->id, ['amount_grams' => 100]);

    $meal->load('ingredients');
    $nut = RecipeNutritionCalculator::fromMeal($meal);

    expect((float) $nut['calories'])->toBe(100.0)
        ->and((float) $nut['protein'])->toBe(10.0)
        ->and((float) $nut['carbs'])->toBe(5.0)
        ->and((float) $nut['fat'])->toBe(2.0);

    expect((float) $meal->fresh()->load('ingredients')->nutritionForDisplay()['calories'])->toBe(100.0);
});

test('authenticated user sees saved meal on meals page', function () {
    $this->actingAs(User::factory()->create());
    $ing = makeIngredientForMealCard();
    $meal = Meal::query()->create(['name' => 'Power Lunch', 'description' => 'High protein']);
    $meal->ingredients()->attach($ing->id, ['amount_grams' => 50]);

    $this->get(route('meals.index'))
        ->assertOk()
        ->assertSee('Power Lunch')
        ->assertSee(__('View details'))
        ->assertSee(__('Meal library'));
});

test('meal details modal shows ingredients instructions and nutrition heading', function () {
    $this->actingAs(User::factory()->create());
    $ing = makeIngredientForMealCard(['name' => 'Oats']);
    $meal = Meal::query()->create(['name' => 'Breakfast', 'description' => 'Mix and serve']);
    $meal->ingredients()->attach($ing->id, ['amount_grams' => 40]);

    Livewire::test('pages::meals')
        ->call('openMealDetails', $meal->id)
        ->assertSet('showMealDetailsModal', true)
        ->assertSet('detailsMealId', $meal->id)
        ->assertSee('Oats')
        ->assertSee('Mix and serve')
        ->assertSee(__('Nutrition summary'));
});

test('save meal stores optional image on public disk', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
    $ing = makeIngredientForMealCard();
    $file = UploadedFile::fake()->image('meal.jpg', 200, 200);

    Livewire::test('pages::meals')
        ->set('name', 'Photo Meal')
        ->set('mealType', MealType::Main->value)
        ->set('recipeIngredients', [
            ['ingredient_id' => $ing->id, 'amount' => 100, 'unit' => 'g'],
        ])
        ->set('mealImage', $file)
        ->call('saveMealFromBuilder')
        ->assertHasNoErrors();

    $meal = Meal::query()->where('name', 'Photo Meal')->first();
    expect($meal)->not->toBeNull()
        ->and($meal->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($meal->image_path);
});
