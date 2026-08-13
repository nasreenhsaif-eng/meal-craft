<?php

use App\Enums\MealPlanLibraryCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $valid = array_map(
            static fn (MealPlanLibraryCategory $category): string => $category->value,
            MealPlanLibraryCategory::cases(),
        );

        DB::table('meal_plans')
            ->whereNotNull('plan_category')
            ->whereNotIn('plan_category', $valid)
            ->update(['plan_category' => MealPlanLibraryCategory::Balanced->value]);
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }
};
