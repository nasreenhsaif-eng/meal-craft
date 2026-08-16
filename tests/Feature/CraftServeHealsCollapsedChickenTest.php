<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\BalancedCanonicalMealRecipeRefiner;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Support\StandardMeatPortion;

test('craft adapt restores one-gram rosemary chicken before macros are shown', function (): void {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $potato = Ingredient::factory()->create([
        'name' => 'Sweet Potato',
        'calories' => 86,
        'protein' => 1.6,
        'carbs' => 20,
        'fat' => 0.1,
    ]);

    $meal = Meal::factory()->create([
        'name' => BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 147,
        'total_protein' => 5.2,
        'total_carbs' => 20,
        'total_fat' => 4,
        'library_edited_at' => now(),
    ]);
    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 1.69, 'amount' => 1.69, 'unit' => 'g'],
        $potato->id => ['amount_grams' => 85, 'amount' => 85, 'unit' => 'g'],
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'craft_key' => 'full',
    ]);

    expect($adapted)->not->toBeNull()
        ->and((float) $adapted['adapted_nutrition']['protein'])->toBeGreaterThan(20.0);

    $meal->refresh()->load('ingredients');
    expect((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS)
        ->and((float) $meal->total_protein)->toBeGreaterThan(20.0);
});

test('craft menu build heals collapsed mediterranean crunch salad chicken', function (): void {
    $user = User::factory()->create();
    $profile = CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $chicken = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $rocca = Ingredient::factory()->create([
        'name' => 'Rocca',
        'calories' => 25,
        'protein' => 2.6,
        'carbs' => 3.7,
        'fat' => 0.4,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Mediterranean Crunch Salad',
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 80,
        'total_protein' => 8,
        'library_edited_at' => now(),
    ]);
    $meal->ingredients()->sync([
        $chicken->id => ['amount_grams' => 1.0, 'amount' => 1.0, 'unit' => 'g'],
        $rocca->id => ['amount_grams' => 45, 'amount' => 45, 'unit' => 'g'],
    ]);

    $menu = AdaptedMenuBuilder::build($profile, ['craft_key' => 'full']);
    $card = collect($menu['scalable_meals'])->firstWhere('name', 'Mediterranean Crunch Salad');

    expect($card)->not->toBeNull()
        ->and((float) $card['adapted_nutrition']['protein'])->toBeGreaterThan(20.0);

    $meal->refresh()->load('ingredients');
    expect((float) $meal->ingredients->firstWhere('name', 'Rosemary Garlic Chicken (Base)')->pivot->amount_grams)
        ->toBe(StandardMeatPortion::GRAMS);
});
