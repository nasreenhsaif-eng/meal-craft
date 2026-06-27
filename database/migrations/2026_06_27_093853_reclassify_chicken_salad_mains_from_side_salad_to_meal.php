<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Meal::query()
            ->whereIn('name', BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS)
            ->update([
                'meal_type' => MealType::Main->value,
                'category' => RecipeCategory::Meal->value,
            ]);
    }

    public function down(): void
    {
        Meal::query()
            ->whereIn('name', BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS)
            ->update([
                'meal_type' => MealType::Salad->value,
                'category' => RecipeCategory::SideSalad->value,
            ]);
    }
};
