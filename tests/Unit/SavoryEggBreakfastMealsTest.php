<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\SavoryEggBreakfastMeals;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('savory egg breakfast tier counts follow plan tiers', function () {
    expect(SavoryEggBreakfastMeals::eggCountForPlanTier(1000))->toBe(2)
        ->and(SavoryEggBreakfastMeals::eggCountForPlanTier(1200))->toBe(2)
        ->and(SavoryEggBreakfastMeals::eggCountForPlanTier(1500))->toBe(4)
        ->and(SavoryEggBreakfastMeals::eggCountForPlanTier(1800))->toBe(4)
        ->and(SavoryEggBreakfastMeals::eggCountForPlanTier(2000))->toBe(5)
        ->and(SavoryEggBreakfastMeals::eggGramsForPlanTier(2000))->toBe(250.0);
});

test('savory egg breakfast side multiplier tracks egg count', function () {
    $meal = Meal::factory()->create();
    $egg = Ingredient::factory()->create(['name' => 'Egg']);
    $meal->ingredients()->attach($egg->id, ['amount_grams' => 100, 'amount' => 100, 'unit' => 'g']);
    $meal = $meal->fresh(['ingredients']);

    expect(SavoryEggBreakfastMeals::sidePortionMultiplierForMeal($meal, 1000))->toBe(1.0)
        ->and(SavoryEggBreakfastMeals::sidePortionMultiplierForMeal($meal, 2000))->toBe(2.5);
});

test('savory egg breakfast enforces minimum avocado portion scaled by plan tier', function () {
    $avocado = Ingredient::factory()->create(['name' => 'Avocado']);

    expect(SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($avocado, 1000))->toBe(50.0)
        ->and(SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($avocado, 1500))->toBe(75.0)
        ->and(SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($avocado, 2000))->toBe(112.5)
        ->and(SavoryEggBreakfastMeals::adaptedSideGrams($avocado, 20.0, 1.0, 1000))->toBe(50.0)
        ->and(SavoryEggBreakfastMeals::adaptedSideGrams($avocado, 20.0, 2.5, 2000))->toBe(112.5);
});

test('savory egg breakfasts include balanced rotation meals', function () {
    expect(SavoryEggBreakfastMeals::isSavoryEggBreakfast('Hummus Egg Stack'))->toBeTrue()
        ->and(SavoryEggBreakfastMeals::isSavoryEggBreakfast('Blueberry Walnut Chia Pudding'))->toBeFalse();
});
