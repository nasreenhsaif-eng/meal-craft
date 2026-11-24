<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Services\MealPlanDefaultDaySelections;
use App\Support\ScheduledTiersMealResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('forMeal returns the same-name Meal Tiers Library copy', function (): void {
    $classic = Meal::factory()->create([
        'name' => 'Herb Chicken Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $tiers = Meal::factory()->tiers()->create([
        'name' => 'Herb Chicken Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    expect(ScheduledTiersMealResolver::forMeal($classic)->is($tiers))->toBeTrue()
        ->and(ScheduledTiersMealResolver::forMeal($tiers)->is($tiers))->toBeTrue();
});

test('remapPlan points stored slots and saved defaults at the tiers copies', function (): void {
    $classic = Meal::factory()->create([
        'name' => 'Herb Chicken Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);
    $tiers = Meal::factory()->tiers()->create([
        'name' => 'Herb Chicken Plate',
        'category' => RecipeCategory::Meal,
        'meal_type' => MealType::Main,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Classic Slot Plan',
        'goal' => 'Needs remapping.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::Balanced,
        'target_total_calories' => 10500,
        'default_day_selections' => [
            1 => [
                'meals' => [$classic->id],
            ],
        ],
    ]);

    $plan->dayMeals()->create([
        'meal_id' => $classic->id,
        'day_number' => 1,
        'slot_type' => MealPlanSlotType::Main,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $changed = ScheduledTiersMealResolver::remapPlan($plan->fresh(['dayMeals.meal']));

    expect($changed)->toBe(1)
        ->and((int) $plan->fresh()->dayMeals()->first()?->meal_id)->toBe((int) $tiers->id)
        ->and(MealPlanDefaultDaySelections::mealIdsForCategory($plan->fresh(), 1, 'meals'))->toBe([(int) $tiers->id]);
});

test('forMeal maps an excluded classic meal onto its Meal Tiers Library replacement', function (): void {
    $classic = Meal::factory()->create([
        'name' => 'Shaved Fennel Rocca Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
    ]);
    $replacement = Meal::factory()->tiers()->create([
        'name' => 'Coconut Grapefruit Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
    ]);

    expect(ScheduledTiersMealResolver::forMeal($classic)->is($replacement))->toBeTrue();
});

test('remapPlan replaces excluded classic slots with the current rotation meal', function (): void {
    $classic = Meal::factory()->create([
        'name' => 'Shaved Fennel Rocca Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
    ]);
    $replacement = Meal::factory()->tiers()->create([
        'name' => 'Coconut Grapefruit Salad',
        'category' => RecipeCategory::SideSalad,
        'meal_type' => MealType::Salad,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Excluded Salad Plan',
        'goal' => 'Needs a tiers replacement.',
        'schema_type' => MealPlanSchemaType::WeeklyStructured,
        'plan_category' => MealPlanLibraryCategory::NutrientDense,
        'target_total_calories' => 10500,
        'default_day_selections' => [
            4 => [
                'sideSalads' => [$classic->id],
            ],
        ],
    ]);

    $plan->dayMeals()->create([
        'meal_id' => $classic->id,
        'day_number' => 4,
        'slot_type' => MealPlanSlotType::Salad,
        'slot_index' => 1,
        'is_option_b' => false,
    ]);

    $changed = ScheduledTiersMealResolver::remapPlan($plan->fresh(['dayMeals.meal']));

    expect($changed)->toBe(1)
        ->and((int) $plan->fresh()->dayMeals()->first()?->meal_id)->toBe((int) $replacement->id)
        ->and(MealPlanDefaultDaySelections::mealIdsForCategory($plan->fresh(), 4, 'sideSalads'))->toBe([(int) $replacement->id]);
});
