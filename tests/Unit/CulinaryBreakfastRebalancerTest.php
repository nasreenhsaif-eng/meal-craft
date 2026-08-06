<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Support\CulinaryBreakfastRebalancer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('breakfast rebalancer keeps potato floor instead of crushing veggies for calories', function (): void {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);
    $white = Ingredient::factory()->create([
        'name' => 'Egg White',
        'calories' => 52,
        'protein' => 10.9,
        'carbs' => 0.7,
        'fat' => 0.2,
    ]);
    $potato = Ingredient::factory()->create([
        'name' => 'Sweet Potato',
        'calories' => 86,
        'protein' => 1.6,
        'carbs' => 20.1,
        'fat' => 0.1,
    ]);
    $onion = Ingredient::factory()->create([
        'name' => 'White Onion',
        'calories' => 40,
        'protein' => 1.1,
        'carbs' => 9.3,
        'fat' => 0.1,
    ]);
    $rosemary = Ingredient::factory()->create([
        'name' => 'Rosemary (Fresh)',
        'calories' => 131,
        'protein' => 3.3,
        'carbs' => 20.7,
        'fat' => 5.9,
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Sweet Potato Egg Hash',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
    ]);
    $meal->setRelation('ingredients', collect([
        $egg->setRelation('pivot', (object) ['amount_grams' => 50]),
        $white->setRelation('pivot', (object) ['amount_grams' => 60]),
        $potato->setRelation('pivot', (object) ['amount_grams' => 120]),
        $onion->setRelation('pivot', (object) ['amount_grams' => 50]),
        $rosemary->setRelation('pivot', (object) ['amount_grams' => 1]),
        $oil->setRelation('pivot', (object) ['amount_grams' => 5]),
    ]));

    // Force an impossible calorie squeeze that previously crushed sides.
    $rebalanced = CulinaryBreakfastRebalancer::rebalance($meal, [
        $egg->id => 50.0,
        $white->id => 60.0,
        $potato->id => 20.0,
        $onion->id => 5.0,
        $rosemary->id => 8.0,
        $oil->id => 15.0,
    ], targetCalories: 180.0, planTier: 1000.0);

    expect($rebalanced[$potato->id])->toBeGreaterThanOrEqual(120.0)
        ->and($rebalanced[$onion->id])->toBeGreaterThanOrEqual(50.0)
        ->and($rebalanced[$rosemary->id])->toBeGreaterThanOrEqual(1.0)
        ->and($rebalanced[$rosemary->id])->toBeLessThanOrEqual(1.0)
        ->and($rebalanced[$oil->id])->toBeLessThanOrEqual(5.0);
});

test('savory egg hash adaptation keeps cookable sides at 1000 tier', function (): void {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);
    $white = Ingredient::factory()->create([
        'name' => 'Egg White',
        'calories' => 52,
        'protein' => 10.9,
        'carbs' => 0.7,
        'fat' => 0.2,
    ]);
    $potato = Ingredient::factory()->create([
        'name' => 'Sweet Potato',
        'calories' => 86,
        'protein' => 1.6,
        'carbs' => 20.1,
        'fat' => 0.1,
    ]);
    $onion = Ingredient::factory()->create([
        'name' => 'White Onion',
        'calories' => 40,
        'protein' => 1.1,
        'carbs' => 9.3,
        'fat' => 0.1,
    ]);
    $pepper = Ingredient::factory()->create([
        'name' => 'Bell Pepper (Red)',
        'calories' => 31,
        'protein' => 1,
        'carbs' => 6,
        'fat' => 0.3,
    ]);
    $spinach = Ingredient::factory()->create([
        'name' => 'Spinach (Fresh)',
        'calories' => 23,
        'protein' => 2.9,
        'carbs' => 3.6,
        'fat' => 0.4,
    ]);
    $rosemary = Ingredient::factory()->create([
        'name' => 'Rosemary (Fresh)',
        'calories' => 131,
        'protein' => 3.3,
        'carbs' => 20.7,
        'fat' => 5.9,
    ]);
    $thyme = Ingredient::factory()->create([
        'name' => 'Thyme (Fresh)',
        'calories' => 101,
        'protein' => 5.6,
        'carbs' => 24.5,
        'fat' => 1.7,
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);
    $flax = Ingredient::factory()->create([
        'name' => 'Flaxseeds',
        'calories' => 534,
        'protein' => 18.3,
        'carbs' => 28.9,
        'fat' => 42.2,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Sweet Potato Egg Hash',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 315,
        'total_protein' => 17,
        'total_carbs' => 36,
        'total_fat' => 12,
    ]);

    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g'],
        $white->id => ['amount_grams' => 60, 'amount' => 60, 'unit' => 'g'],
        $potato->id => ['amount_grams' => 120, 'amount' => 120, 'unit' => 'g'],
        $onion->id => ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g'],
        $pepper->id => ['amount_grams' => 50, 'amount' => 50, 'unit' => 'g'],
        $spinach->id => ['amount_grams' => 30, 'amount' => 30, 'unit' => 'g'],
        $rosemary->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
        $thyme->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $flax->id => ['amount_grams' => 2, 'amount' => 2, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1000,
        'protein_percentage' => 40,
        'carb_percentage' => 30,
        'fat_percentage' => 30,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1000,
        'craft_key' => 'full',
    ]);

    $byName = collect($adapted['ingredients'])->keyBy('name');

    expect($adapted['savory_egg_count'])->toBe(2)
        ->and((float) $byName['Sweet Potato']['adapted_amount_grams'])->toBeGreaterThanOrEqual(120.0)
        ->and((float) $byName['White Onion']['adapted_amount_grams'])->toBeGreaterThanOrEqual(50.0)
        ->and((float) $byName['Bell Pepper (Red)']['adapted_amount_grams'])->toBeGreaterThanOrEqual(50.0)
        ->and((float) $byName['Rosemary (Fresh)']['adapted_amount_grams'])->toBeGreaterThanOrEqual(1.0)
        ->and((float) $byName['Rosemary (Fresh)']['adapted_amount_grams'])->toBeLessThanOrEqual(1.0)
        ->and((float) $byName['Thyme (Fresh)']['adapted_amount_grams'])->toBeGreaterThanOrEqual(1.0)
        ->and((float) $byName['Thyme (Fresh)']['adapted_amount_grams'])->toBeLessThanOrEqual(1.0)
        ->and((float) $byName['Olive Oil']['adapted_amount_grams'])->toBe(5.0);
});

test('breakfast adaptation never wipes herbs or spices to zero grams', function (): void {
    $egg = Ingredient::factory()->create([
        'name' => 'Egg',
        'calories' => 155,
        'protein' => 12.6,
        'carbs' => 1.1,
        'fat' => 10.6,
    ]);
    $pepper = Ingredient::factory()->create([
        'name' => 'Black Pepper',
        'calories' => 251,
        'protein' => 10,
        'carbs' => 64,
        'fat' => 3,
    ]);
    $basil = Ingredient::factory()->create([
        'name' => 'Basil',
        'calories' => 23,
        'protein' => 3,
        'carbs' => 2.7,
        'fat' => 0.6,
    ]);
    $oil = Ingredient::factory()->create([
        'name' => 'Olive Oil',
        'calories' => 884,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 100,
    ]);
    $avocado = Ingredient::factory()->create([
        'name' => 'Avocado',
        'calories' => 160,
        'protein' => 2,
        'carbs' => 9,
        'fat' => 15,
    ]);

    $meal = Meal::factory()->create([
        'name' => 'Mediterranean Omelet',
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
    ]);
    $meal->ingredients()->attach([
        $egg->id => ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g'],
        $pepper->id => ['amount_grams' => 1, 'amount' => 1, 'unit' => 'g'],
        $basil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $oil->id => ['amount_grams' => 5, 'amount' => 5, 'unit' => 'g'],
        $avocado->id => ['amount_grams' => 30, 'amount' => 30, 'unit' => 'g'],
    ]);

    $profile = CustomerProfile::factory()->create([
        'daily_calorie_target' => 1500,
        'protein_percentage' => 32,
        'carb_percentage' => 28,
        'fat_percentage' => 40,
    ]);

    $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal->fresh(['ingredients']), [
        'plan_tier' => 1500,
        'craft_key' => 'full',
        'schedule_slot' => 'breakfast',
    ]);

    $byName = collect($adapted['ingredients'])->keyBy('name');

    expect((float) $byName['Black Pepper']['adapted_amount_grams'])->toBeGreaterThanOrEqual(1.0)
        ->and((float) $byName['Basil']['adapted_amount_grams'])->toBeGreaterThanOrEqual(1.0)
        ->and((float) $byName['Olive Oil']['adapted_amount_grams'])->toBe(5.0);
});
