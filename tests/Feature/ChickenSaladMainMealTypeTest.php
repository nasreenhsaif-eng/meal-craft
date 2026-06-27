<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;

test('migration reclassifies chicken salad rotation mains as Meal not Side Salad', function (): void {
    foreach (BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS as $name) {
        Meal::factory()->create([
            'name' => $name,
            'meal_type' => MealType::Salad,
            'category' => RecipeCategory::SideSalad,
        ]);
    }

    $migration = require base_path('database/migrations/2026_06_27_093853_reclassify_chicken_salad_mains_from_side_salad_to_meal.php');
    $migration->up();

    foreach (BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS as $name) {
        $meal = Meal::query()->where('name', $name)->firstOrFail();

        expect($meal->meal_type)->toBe(MealType::Main)
            ->and($meal->category)->toBe(RecipeCategory::Meal);
    }
});
