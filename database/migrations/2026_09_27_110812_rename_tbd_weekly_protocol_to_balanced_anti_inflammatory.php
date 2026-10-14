<?php

use App\Enums\MealPlanLibraryCategory;
use App\Services\NutrientDenseWeeklyMealPlanBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('meal_plans')
            ->where('name', 'TBD Weekly Protocol')
            ->orWhere('name', 'like', '%TBD Weekly Protocol%')
            ->update([
                'name' => NutrientDenseWeeklyMealPlanBuilder::PLAN_NAME,
                'goal' => NutrientDenseWeeklyMealPlanBuilder::PLAN_GOAL,
                'description' => NutrientDenseWeeklyMealPlanBuilder::PLAN_GOAL,
                'plan_category' => MealPlanLibraryCategory::NutrientDense->value,
            ]);
    }

    public function down(): void
    {
        DB::table('meal_plans')
            ->where('name', NutrientDenseWeeklyMealPlanBuilder::PLAN_NAME)
            ->update([
                'name' => 'TBD Weekly Protocol',
                'goal' => 'TBD — micronutrient-optimized whole foods within your calorie tier.',
                'description' => null,
                'plan_category' => MealPlanLibraryCategory::Balanced->value,
            ]);
    }
};
