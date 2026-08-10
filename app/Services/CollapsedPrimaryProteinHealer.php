<?php

namespace App\Services;

use App\Models\Meal;
use App\Support\StandardMeatPortion;
use Illuminate\Support\Facades\DB;

/**
 * Library-wide heal for primary beef/chicken/fish portions crushed below half of
 * {@see StandardMeatPortion::GRAMS} (e.g. sodium-refiner collapse to 1–2 g).
 *
 * Does not invent missing protein lines — meals that lost chicken entirely are restored
 * by recipe refiners once {@see MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat()}
 * bypasses the UI edit lock.
 */
final class CollapsedPrimaryProteinHealer
{
    public const COLLAPSED_FRACTION = 0.5;

    /**
     * @return list<array{meal: string, ingredient: string, from: float, to: float}>
     */
    public function audit(): array
    {
        $findings = [];

        foreach (Meal::queryForMealLibrary()->with('ingredients')->cursor() as $meal) {
            foreach ($this->collapsedLinesOnMeal($meal) as $line) {
                $findings[] = [
                    'meal' => (string) $meal->name,
                    'ingredient' => $line['ingredient'],
                    'from' => $line['from'],
                    'to' => $line['to'],
                ];
            }
        }

        return $findings;
    }

    /**
     * @return list<string> Updated meal names
     */
    public function heal(): array
    {
        return DB::transaction(function (): array {
            $updated = [];

            foreach (Meal::queryForMealLibrary()->with('ingredients')->cursor() as $meal) {
                $changes = $this->collapsedLinesOnMeal($meal);

                if ($changes === []) {
                    continue;
                }

                foreach ($changes as $change) {
                    $ingredient = $meal->ingredients->firstWhere('name', $change['ingredient']);

                    if ($ingredient === null) {
                        continue;
                    }

                    $grams = round((float) $change['to'], 2);
                    $meal->ingredients()->updateExistingPivot($ingredient->id, [
                        'amount_grams' => $grams,
                        'amount' => $grams,
                        'unit' => 'g',
                    ]);
                }

                $fresh = $meal->fresh(['ingredients']);

                if ($fresh->ingredients->isNotEmpty() && ! $fresh->is_bulk) {
                    $nutrition = RecipeNutritionCalculator::fromMeal($fresh);
                    $fresh->update(array_merge(
                        Meal::nutritionSummaryToPersistedAttributes($nutrition),
                        ['nutrition_aggregates_synced' => true],
                    ));
                }

                MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
                $updated[] = (string) $meal->name;
            }

            return $updated;
        });
    }

    /**
     * @return list<array{ingredient: string, from: float, to: float}>
     */
    private function collapsedLinesOnMeal(Meal $meal): array
    {
        $floor = StandardMeatPortion::GRAMS * self::COLLAPSED_FRACTION;
        $changes = [];

        foreach ($meal->ingredients as $ingredient) {
            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            if (StandardMeatPortion::isLiverBlendIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);

            if ($grams <= 0.0 || $grams >= $floor) {
                continue;
            }

            $target = str_contains(strtolower($ingredient->name), 'beef')
                ? StandardMeatPortion::targetPrimaryBeefGrams($meal->ingredients, $meal->name)
                : StandardMeatPortion::GRAMS;

            $changes[] = [
                'ingredient' => $ingredient->name,
                'from' => $grams,
                'to' => $target,
            ];
        }

        return $changes;
    }
}
