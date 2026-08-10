<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\MenuDevelopmentCsv;
use App\Support\StandardMeatPortion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Library-wide heal for primary beef/chicken/fish portions crushed below half of
 * {@see StandardMeatPortion::GRAMS} (e.g. sodium-refiner collapse to 1–2 g), and
 * restore missing primary protein lines from the master meals CSV when present.
 */
final class CollapsedPrimaryProteinHealer
{
    public const COLLAPSED_FRACTION = 0.5;

    /**
     * Heal collapsed portions, restore missing primary protein from CSV, and rewrite
     * known salad recipes that still lack primary meat.
     *
     * @return list<string> Updated meal names
     */
    public function healAll(): array
    {
        // Salads first: rewrite known recipes when chicken is collapsed or missing entirely
        // (e.g. Turmeric Chicken Kale Salad stripped to greens only).
        $updated = $this->refineSaladsMissingPrimaryMeat();
        $updated = array_values(array_unique(array_merge($updated, $this->heal())));
        $updated = array_values(array_unique(array_merge($updated, $this->restoreMissingPrimaryProteinFromMasterCsv())));

        return $updated;
    }

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

                $this->resyncMealNutrition($meal->fresh(['ingredients']));
                $updated[] = (string) $meal->name;
            }

            return $updated;
        });
    }

    /**
     * Attach missing primary meat/fish lines using grams from {@see MenuDevelopmentCsv::mealsPath()}.
     *
     * @return list<string>
     */
    public function restoreMissingPrimaryProteinFromMasterCsv(): array
    {
        $csvPortions = $this->primaryMeatPortionsByMealFromCsv();

        if ($csvPortions === []) {
            return [];
        }

        return DB::transaction(function () use ($csvPortions): array {
            $updated = [];

            foreach (Meal::queryForMealLibrary()->with('ingredients')->cursor() as $meal) {
                if (! MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($meal)) {
                    continue;
                }

                if ($this->collapsedLinesOnMeal($meal) !== []) {
                    continue;
                }

                $portions = $csvPortions[$meal->name] ?? null;

                if ($portions === null || $portions === []) {
                    continue;
                }

                $attached = false;

                foreach ($portions as $ingredientName => $grams) {
                    if ($meal->ingredients->contains(fn (Ingredient $ingredient): bool => $ingredient->name === $ingredientName)) {
                        continue;
                    }

                    $ingredient = Ingredient::query()->where('name', $ingredientName)->first();

                    if ($ingredient === null) {
                        continue;
                    }

                    $rounded = round((float) $grams, 2);
                    $meal->ingredients()->attach($ingredient->id, [
                        'amount_grams' => $rounded,
                        'amount' => $rounded,
                        'unit' => 'g',
                    ]);
                    $attached = true;
                }

                if (! $attached) {
                    continue;
                }

                $this->resyncMealNutrition($meal->fresh(['ingredients']));
                $updated[] = (string) $meal->name;
            }

            return $updated;
        });
    }

    /**
     * @return list<string>
     */
    public function refineSaladsMissingPrimaryMeat(): array
    {
        $updated = [];
        $refiner = app(SaladDressingMealRefiner::class);

        foreach (SaladDressingMealRefiner::refinedMealNames() as $mealName) {
            $meal = Meal::queryForMealLibrary()->with('ingredients')->where('name', $mealName)->first();

            if ($meal === null) {
                continue;
            }

            if (! MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($meal)) {
                continue;
            }

            try {
                $refined = $refiner->refine($mealName);
            } catch (\InvalidArgumentException) {
                // Incomplete ingredient libraries (tests / partial imports) skip full salad rewrite.
                continue;
            }

            if ($refined !== []) {
                $updated = array_merge($updated, $refined);
            }
        }

        return array_values(array_unique($updated));
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function primaryMeatPortionsByMealFromCsv(): array
    {
        $path = MenuDevelopmentCsv::mealsPath();

        if (! File::exists($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $nameIndex = array_search('meal_name', $headers, true);
        $ingredientsIndex = array_search('ingredients_string', $headers, true);

        if ($nameIndex === false || $ingredientsIndex === false) {
            fclose($handle);

            return [];
        }

        $byMeal = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (! isset($row[$nameIndex], $row[$ingredientsIndex])) {
                continue;
            }

            $mealName = trim((string) $row[$nameIndex]);
            $ingredientsString = trim((string) $row[$ingredientsIndex]);

            if ($mealName === '' || $ingredientsString === '') {
                continue;
            }

            foreach (explode('|', $ingredientsString) as $segment) {
                $segment = trim($segment);

                if ($segment === '' || ! str_contains($segment, ':')) {
                    continue;
                }

                [$ingredientName, $gramsRaw] = array_map('trim', explode(':', $segment, 2));
                $grams = (float) $gramsRaw;

                if ($grams <= 0.0) {
                    continue;
                }

                if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredientName, $mealName)) {
                    continue;
                }

                if (StandardMeatPortion::isLiverBlendIngredient($ingredientName, $mealName)) {
                    continue;
                }

                $byMeal[$mealName][$ingredientName] = max($byMeal[$mealName][$ingredientName] ?? 0.0, $grams);
            }
        }

        fclose($handle);

        return $byMeal;
    }

    private function resyncMealNutrition(Meal $meal): void
    {
        if ($meal->ingredients->isNotEmpty() && ! $meal->is_bulk) {
            $nutrition = RecipeNutritionCalculator::fromMeal($meal);
            $meal->update(array_merge(
                Meal::nutritionSummaryToPersistedAttributes($nutrition),
                ['nutrition_aggregates_synced' => true],
            ));
        }

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($meal->fresh(['ingredients']), false);
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
