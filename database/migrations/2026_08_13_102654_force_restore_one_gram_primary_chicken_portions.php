<?php

use App\Models\Meal;
use App\Services\CollapsedPrimaryProteinHealer;
use App\Services\RecipeNutritionCalculator;
use App\Support\StandardMeatPortion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hard restore: every library meal still showing 1g rosemary/tandoori/breast chicken
 * (sodium-refiner collapse across the weekly rotation) → 150g, then resync macros.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PRIMARY_CHICKEN_NAMES = [
        'Rosemary Garlic Chicken (Base)',
        'Tandoori Chicken (Base)',
        'Chicken Breast',
        'Chicken thigh',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ingredient_meal') || ! Schema::hasTable('ingredients')) {
            app(CollapsedPrimaryProteinHealer::class)->healAll();

            return;
        }

        $floor = StandardMeatPortion::GRAMS * CollapsedPrimaryProteinHealer::COLLAPSED_FRACTION;
        $target = StandardMeatPortion::GRAMS;

        $ingredientIds = DB::table('ingredients')
            ->whereIn('name', self::PRIMARY_CHICKEN_NAMES)
            ->pluck('id');

        if ($ingredientIds->isEmpty()) {
            app(CollapsedPrimaryProteinHealer::class)->healAll();

            return;
        }

        $affectedMealIds = DB::table('ingredient_meal')
            ->whereIn('ingredient_id', $ingredientIds)
            ->whereRaw('COALESCE(amount_grams, amount, 0) > 0')
            ->whereRaw('COALESCE(amount_grams, amount, 0) < ?', [$floor])
            ->distinct()
            ->pluck('meal_id');

        DB::table('ingredient_meal')
            ->whereIn('ingredient_id', $ingredientIds)
            ->whereRaw('COALESCE(amount_grams, amount, 0) > 0')
            ->whereRaw('COALESCE(amount_grams, amount, 0) < ?', [$floor])
            ->update([
                'amount_grams' => $target,
                'amount' => $target,
                'unit' => 'g',
            ]);

        app(CollapsedPrimaryProteinHealer::class)->healAll();

        foreach ($affectedMealIds as $mealId) {
            $meal = Meal::query()->with('ingredients')->find($mealId);

            if ($meal === null || $meal->ingredients->isEmpty() || $meal->is_bulk) {
                continue;
            }

            $nutrition = RecipeNutritionCalculator::fromMeal($meal);
            $meal->update(array_merge(
                Meal::nutritionSummaryToPersistedAttributes($nutrition),
                ['nutrition_aggregates_synced' => true],
            ));
        }
    }

    public function down(): void
    {
        // Data repair — not reversible.
    }
};
