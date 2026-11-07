<?php

use App\Models\CustomerCraftPlanDayMeal;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Support\ScheduledTiersMealResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_NAME = 'Butternut Squash & Eggs';

    private const CANONICAL_NAME = 'Butternut Squash Frittata';

    public function up(): void
    {
        $idMap = $this->legacyToCanonicalIdMap();

        if ($idMap !== []) {
            $this->remapMealPlanDayMeals($idMap);
            $this->remapCustomerCraftPlanDayMeals($idMap);
            $this->remapMealMealPlanPivots($idMap);
            $this->remapDefaultDaySelections($idMap);
            $this->clearIngredientSources(array_keys($idMap));
        }

        Meal::query()->where('name', self::LEGACY_NAME)->each(function (Meal $meal): void {
            $meal->delete();
        });

        MealPlan::query()
            ->whereIn('name', [
                'TBD Weekly Protocol',
                'Balanced Weekly Protocol',
            ])
            ->each(function (MealPlan $plan): void {
                ScheduledTiersMealResolver::remapPlan($plan);
            });
    }

    public function down(): void
    {
        // Data repair — not reversible.
    }

    /**
     * @return array<int, int>
     */
    private function legacyToCanonicalIdMap(): array
    {
        /** @var array<int, int> $map */
        $map = [];

        $legacyMeals = Meal::query()->where('name', self::LEGACY_NAME)->get();

        foreach ($legacyMeals as $legacy) {
            $canonical = Meal::query()
                ->where('name', self::CANONICAL_NAME)
                ->where('library_key', $legacy->library_key)
                ->first()
                ?? Meal::query()->where('name', self::CANONICAL_NAME)->first();

            if ($canonical instanceof Meal) {
                $map[(int) $legacy->id] = (int) $canonical->id;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function remapMealPlanDayMeals(array $idMap): void
    {
        foreach ($idMap as $fromId => $toId) {
            MealPlanDayMeal::query()->where('meal_id', $fromId)->update(['meal_id' => $toId]);
        }
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function remapCustomerCraftPlanDayMeals(array $idMap): void
    {
        if (! Schema::hasTable('customer_craft_plan_day_meals')) {
            return;
        }

        foreach ($idMap as $fromId => $toId) {
            CustomerCraftPlanDayMeal::query()->where('meal_id', $fromId)->update(['meal_id' => $toId]);
        }
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function remapMealMealPlanPivots(array $idMap): void
    {
        if (! Schema::hasTable('meal_meal_plan')) {
            return;
        }

        foreach ($idMap as $fromId => $toId) {
            $pivots = DB::table('meal_meal_plan')->where('meal_id', $fromId)->get();

            foreach ($pivots as $pivot) {
                $keeperHasSlot = DB::table('meal_meal_plan')
                    ->where('meal_plan_id', $pivot->meal_plan_id)
                    ->where('day_of_week', $pivot->day_of_week)
                    ->where('meal_type', $pivot->meal_type)
                    ->where('meal_id', $toId)
                    ->exists();

                if ($keeperHasSlot) {
                    DB::table('meal_meal_plan')->where('id', $pivot->id)->delete();

                    continue;
                }

                DB::table('meal_meal_plan')->where('id', $pivot->id)->update([
                    'meal_id' => $toId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function remapDefaultDaySelections(array $idMap): void
    {
        MealPlan::query()
            ->whereNotNull('default_day_selections')
            ->each(function (MealPlan $plan) use ($idMap): void {
                $defaults = $plan->default_day_selections;

                if (! is_array($defaults) || $defaults === []) {
                    return;
                }

                $plan->default_day_selections = $this->replaceIdsInSelections($defaults, $idMap);
                $plan->save();
            });
    }

    /**
     * @param  array<int|string, mixed>  $defaults
     * @param  array<int, int>  $idMap
     * @return array<int|string, mixed>
     */
    private function replaceIdsInSelections(array $defaults, array $idMap): array
    {
        foreach ($defaults as $dayNumber => $categories) {
            if (! is_array($categories)) {
                continue;
            }

            foreach ($categories as $categoryKey => $mealIds) {
                if (! is_array($mealIds)) {
                    continue;
                }

                $defaults[$dayNumber][$categoryKey] = array_values(array_map(
                    static fn (mixed $id): int => $idMap[(int) $id] ?? (int) $id,
                    $mealIds,
                ));
            }
        }

        return $defaults;
    }

    /**
     * @param  list<int>  $legacyIds
     */
    private function clearIngredientSources(array $legacyIds): void
    {
        if ($legacyIds === []) {
            return;
        }

        Ingredient::query()->whereIn('source_meal_id', $legacyIds)->update(['source_meal_id' => null]);
    }
};
