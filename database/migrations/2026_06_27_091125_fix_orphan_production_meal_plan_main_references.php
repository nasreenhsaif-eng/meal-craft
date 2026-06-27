<?php

use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Models\MealPlanDayMeal;
use App\Services\BalancedWeeklyRotationSchedule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $validMealIds = Meal::query()->pluck('id');

        $orphans = MealPlanDayMeal::query()
            ->whereNotIn('meal_id', $validMealIds)
            ->get();

        foreach ($orphans as $row) {
            $slotType = $row->slot_type instanceof MealPlanSlotType
                ? $row->slot_type
                : MealPlanSlotType::from((string) $row->slot_type);

            $mealName = BalancedWeeklyRotationSchedule::mealNameForDay(
                (int) $row->day_number,
                $slotType,
                (int) $row->slot_index,
            );

            $mealId = Meal::query()->where('name', $mealName)->value('id');

            if ($mealId === null) {
                continue;
            }

            MealPlanDayMeal::query()
                ->whereKey($row->id)
                ->update(['meal_id' => $mealId]);
        }
    }

    public function down(): void
    {
        // Data repair — not reversible.
    }
};
