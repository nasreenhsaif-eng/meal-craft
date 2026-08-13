<?php

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('legacy nutrient_dense plan category is cast to balanced without crashing', function (): void {
    $planId = DB::table('meal_plans')->insertGetId([
        'name' => 'Legacy Nutrient Dense Plan',
        'goal' => 'Legacy category crash repro',
        'schema_type' => MealPlanSchemaType::WeeklyStructured->value,
        'plan_category' => 'nutrient_dense',
        'target_total_calories' => 14000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $plan = MealPlan::query()->findOrFail($planId);

    expect($plan->plan_category)->toBe(MealPlanLibraryCategory::Balanced);
});

test('meal plan library index tolerates legacy nutrient_dense categories', function (): void {
    DB::table('meal_plans')->insert([
        'name' => 'Nutrient Dense Weekly',
        'goal' => 'Should not 500 the index',
        'schema_type' => MealPlanSchemaType::WeeklyStructured->value,
        'plan_category' => 'nutrient_dense',
        'target_total_calories' => 14000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.meal-plan-library'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MealPlanLibrary')
            ->has('mealPlans')
            ->where('mealPlans.0.category', __('Balanced')));
});

test('migration remaps invalid plan categories to balanced', function (): void {
    $planId = DB::table('meal_plans')->insertGetId([
        'name' => 'Needs Category Remap',
        'goal' => 'Migration remap',
        'schema_type' => MealPlanSchemaType::WeeklyStructured->value,
        'plan_category' => 'nutrient_dense',
        'target_total_calories' => 14000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('migrations')
        ->where('migration', '2026_08_13_124559_normalize_invalid_meal_plan_library_categories')
        ->delete();

    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_08_13_124559_normalize_invalid_meal_plan_library_categories.php',
        '--force' => true,
    ])->assertSuccessful();

    expect(DB::table('meal_plans')->where('id', $planId)->value('plan_category'))
        ->toBe(MealPlanLibraryCategory::Balanced->value);
});
