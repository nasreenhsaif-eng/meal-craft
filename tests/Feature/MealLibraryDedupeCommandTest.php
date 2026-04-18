<?php

use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('meals deduplicate library command keeps most recently updated meal', function () {
    $older = Meal::query()->create([
        'name' => 'Dup Meal',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 1.0,
    ]);
    DB::table('meals')->where('id', $older->id)->update(['updated_at' => now()->subDays(3)]);

    $newer = Meal::query()->create([
        'name' => 'dup  meal',
        'category' => RecipeCategory::Breakfast,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 2.0,
    ]);

    expect(Meal::query()->count())->toBe(2);

    Artisan::call('meals:deduplicate-library');

    expect(Meal::query()->count())->toBe(1);
    $kept = Meal::query()->firstOrFail();
    expect((int) $kept->id)->toBe((int) $newer->id);
});

test('meals deduplicate library command reassigns meal plan pivots to keeper', function () {
    $older = Meal::query()->create([
        'name' => 'Plan Dup',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 1.0,
    ]);
    DB::table('meals')->where('id', $older->id)->update(['updated_at' => now()->subDay()]);

    $newer = Meal::query()->create([
        'name' => 'plan dup',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 2.0,
    ]);

    $plan = MealPlan::query()->create([
        'name' => 'Test Plan',
        'goal' => 'Test goal',
    ]);

    $older->refresh();
    $older->mealPlans()->attach($plan->id, [
        'day_of_week' => 'monday',
        'meal_type' => 'breakfast',
    ]);

    Artisan::call('meals:deduplicate-library');

    expect(DB::table('meal_meal_plan')->count())->toBe(1)
        ->and((int) DB::table('meal_meal_plan')->value('meal_id'))->toBe((int) $newer->id);
});

test('meals deduplicate library dry run does not remove meals', function () {
    $older = Meal::query()->create([
        'name' => 'Dry Dup',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 1.0,
    ]);
    DB::table('meals')->where('id', $older->id)->update(['updated_at' => now()->subDay()]);

    Meal::query()->create([
        'name' => 'dry dup',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 2.0,
    ]);

    Artisan::call('meals:deduplicate-library', ['--dry-run' => true]);

    expect(Meal::query()->count())->toBe(2);
});
