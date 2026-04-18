<?php

namespace App\Services;

use App\Models\Meal;
use Illuminate\Support\Facades\DB;

/**
 * One-time / maintenance: collapse duplicate meals that share the same normalized Meal_Name.
 */
final class MealLibraryDedupeService
{
    /**
     * @return array{groups_resolved: int, meals_removed: int}
     */
    public function deduplicate(bool $dryRun = false): array
    {
        $groupsResolved = 0;
        $mealsRemoved = 0;

        /** @var array<string, list<Meal>> $byKey */
        $byKey = [];
        foreach (Meal::query()->orderByDesc('updated_at')->orderByDesc('id')->cursor() as $meal) {
            $k = MealCsvLibraryImportService::normalizeMealNameKey($meal->name);
            $byKey[$k][] = $meal;
        }

        foreach ($byKey as $meals) {
            if (count($meals) < 2) {
                continue;
            }

            $groupsResolved++;
            $keeper = $meals[0];

            foreach (array_slice($meals, 1) as $duplicate) {
                if (! $dryRun) {
                    $this->reassignMealPlanPivotsToKeeper($duplicate, $keeper);
                    $duplicate->delete();
                }
                $mealsRemoved++;
            }
        }

        return [
            'groups_resolved' => $groupsResolved,
            'meals_removed' => $mealsRemoved,
        ];
    }

    private function reassignMealPlanPivotsToKeeper(Meal $duplicate, Meal $keeper): void
    {
        $pivots = DB::table('meal_meal_plan')->where('meal_id', $duplicate->id)->get();

        foreach ($pivots as $p) {
            $keeperHasSlot = DB::table('meal_meal_plan')
                ->where('meal_plan_id', $p->meal_plan_id)
                ->where('day_of_week', $p->day_of_week)
                ->where('meal_type', $p->meal_type)
                ->where('meal_id', $keeper->id)
                ->exists();

            if ($keeperHasSlot) {
                DB::table('meal_meal_plan')->where('id', $p->id)->delete();
            } else {
                DB::table('meal_meal_plan')->where('id', $p->id)->update([
                    'meal_id' => $keeper->id,
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
