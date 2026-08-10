<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
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
     * Day-by-day primary chicken portions for Balanced weekly Main 1 (plate) and Main 2 (salad).
     *
     * @return list<array{
     *     day: int,
     *     slot: string,
     *     meal: string,
     *     ingredient: string|null,
     *     grams: float|null,
     *     ok: bool,
     *     issue: string|null
     * }>
     */
    public function auditWeeklyChickenSlots(): array
    {
        $rows = [];

        foreach (range(1, 7) as $day) {
            foreach ([
                ['plate', BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 1)],
                ['salad', BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 2)],
            ] as [$slot, $mealName]) {
                $rows[] = $this->weeklyChickenSlotRow($day, $slot, $mealName);
            }
        }

        return $rows;
    }

    /**
     * @return array{
     *     day: int,
     *     slot: string,
     *     meal: string,
     *     ingredient: string|null,
     *     grams: float|null,
     *     ok: bool,
     *     issue: string|null
     * }
     */
    private function weeklyChickenSlotRow(int $day, string $slot, string $mealName): array
    {
        $meal = Meal::queryForMealLibrary()->with('ingredients')->where('name', $mealName)->first();

        if ($meal === null) {
            return [
                'day' => $day,
                'slot' => $slot,
                'meal' => $mealName,
                'ingredient' => null,
                'grams' => null,
                'ok' => false,
                'issue' => 'missing_meal',
            ];
        }

        $floor = StandardMeatPortion::GRAMS * self::COLLAPSED_FRACTION;
        $bestIngredient = null;
        $bestGrams = null;

        foreach ($meal->ingredients as $ingredient) {
            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            if (StandardMeatPortion::isLiverBlendIngredient($ingredient->name, $meal->name)) {
                continue;
            }

            if (! str_contains(strtolower($ingredient->name), 'chicken')) {
                continue;
            }

            $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);

            if ($grams <= 0.0) {
                continue;
            }

            if ($bestGrams === null || $grams > $bestGrams) {
                $bestIngredient = $ingredient->name;
                $bestGrams = $grams;
            }
        }

        if ($bestGrams === null) {
            return [
                'day' => $day,
                'slot' => $slot,
                'meal' => $mealName,
                'ingredient' => null,
                'grams' => null,
                'ok' => false,
                'issue' => 'missing_chicken',
            ];
        }

        $collapsed = $bestGrams < $floor;

        return [
            'day' => $day,
            'slot' => $slot,
            'meal' => $mealName,
            'ingredient' => $bestIngredient,
            'grams' => $bestGrams,
            'ok' => ! $collapsed,
            'issue' => $collapsed ? 'collapsed' : null,
        ];
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
