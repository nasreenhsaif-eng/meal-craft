<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Support\MealLibraryEditGuard;
use App\Support\NutrientDailyRdi;
use Illuminate\Support\Facades\DB;

/**
 * Isocalorically adjusts rotation meal recipes so reference Full Craft days reach floor RDI at 1500+ kcal tiers.
 */
final class BalancedMicronutrientRecipeRefiner
{
    private const MAX_DAY_PASSES = 100;

    private const GRAM_STEP = 8.0;

    private const CALORIE_TOLERANCE = 0.5;

    /** @var list<string> */
    private const SIDE_SALAD_PRIORITY_KEYS = [
        'iron',
        'b9_folate',
        'potassium',
        'fiber',
        'vitamin_a',
        'vitamin_c',
        'magnesium',
        'calcium',
        'zinc',
    ];

    /** @var list<string> */
    private const VEGAN_MAIN_PRIORITY_KEYS = [
        'calcium',
        'magnesium',
        'iron',
        'potassium',
        'zinc',
        'b6',
        'vitamin_e',
    ];

    /** @var list<string> */
    private const ANIMAL_MAIN_PRIORITY_KEYS = [
        'b12',
        'iron',
        'potassium',
        'magnesium',
        'zinc',
        'b6',
        'vitamin_e',
    ];

    /**
     * @return list<string>
     */
    public function refine(): array
    {
        return DB::transaction(function (): array {
            $updated = [];

            $updated = array_merge($updated, $this->refineMealList(
                BalancedWeeklyRotationSchedule::VEGAN_SIDE_SALADS,
                self::SIDE_SALAD_PRIORITY_KEYS,
            ));

            $updated = array_merge($updated, $this->refineMealList(
                array_merge(
                    BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS,
                    BalancedWeeklyRotationSchedule::VEGAN_MAINS,
                ),
                self::VEGAN_MAIN_PRIORITY_KEYS,
            ));

            $updated = array_merge($updated, $this->refineMealList(
                BalancedWeeklyRotationSchedule::CHICKEN_PLATE_MAINS,
                ['iron', 'potassium', 'magnesium', 'zinc', 'b6', 'vitamin_e'],
            ));

            $updated = array_merge($updated, $this->refineMealList(
                BalancedWeeklyRotationSchedule::SALMON_MAINS,
                self::ANIMAL_MAIN_PRIORITY_KEYS,
            ));

            $updated = array_merge($updated, $this->refineMealList(
                BalancedWeeklyRotationSchedule::BEEF_MAINS,
                self::ANIMAL_MAIN_PRIORITY_KEYS,
            ));

            $updated = array_merge($updated, $this->refineMealList(
                BalancedWeeklyRotationSchedule::EGG_BREAKFASTS,
                ['b9_folate', 'vitamin_a', 'iron', 'b12', 'vitamin_k2'],
            ));

            $profile = $this->referenceProfile();

            for ($pass = 0; $pass < self::MAX_DAY_PASSES; $pass++) {
                $worst = $this->worstCoverageGap($profile);

                if ($worst === null) {
                    break;
                }

                $meal = $this->mealForDayRole($worst['day_number'], $worst['meal_role']);

                if ($meal === null || MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $nutritionKey = MicronutrientBoostCatalog::nutritionKeyForLabel($worst['label']);

                if ($nutritionKey === null) {
                    continue;
                }

                if ($this->applyIsocaloricBoost($meal, $nutritionKey)) {
                    $updated[] = $meal->name;
                }
            }

            return array_values(array_unique($updated));
        });
    }

    /**
     * @param  list<string>  $mealNames
     * @param  list<string>  $nutritionKeys
     * @return list<string>
     */
    private function refineMealList(array $mealNames, array $nutritionKeys): array
    {
        $updated = [];

        foreach ($mealNames as $mealName) {
            /** @var Meal|null $meal */
            $meal = Meal::queryForMealLibrary()->where('name', $mealName)->with('ingredients')->first();

            if ($meal === null || MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                continue;
            }

            $changed = false;

            foreach ($nutritionKeys as $key) {
                for ($attempt = 0; $attempt < 6; $attempt++) {
                    if (! $this->applyIsocaloricBoost($meal->fresh(['ingredients']), $key)) {
                        break;
                    }

                    $changed = true;
                }
            }

            if ($changed) {
                $updated[] = $mealName;
            }
        }

        return $updated;
    }

    /**
     * @return array{day_number: int, label: string, percent: float, meal_role: string}|null
     */
    private function worstCoverageGap(CustomerProfile $profile): ?array
    {
        $worst = null;

        foreach (NutrientDailyRdi::enforcedTiers() as $tier) {
            foreach (NutrientDailyRdi::fixedSlotCombinations() as $combination) {
                $slots = NutrientDailyRdi::parseFixedSlotCombination($combination);

                foreach (range(1, 7) as $dayNumber) {
                    $report = DayMicronutrientCoverageAnalyzer::simulateFullCraftDay(
                        $profile,
                        $dayNumber,
                        (float) $tier,
                        $slots,
                    );

                    foreach ($report['nutrients'] as $row) {
                        if ($row['status'] !== 'floor' || $row['meets_target']) {
                            continue;
                        }

                        $gap = NutrientDailyRdi::FLOOR_TARGET_PERCENT - $row['percent'];

                        if ($worst === null || $gap > $worst['gap']) {
                            $worst = [
                                'day_number' => $dayNumber,
                                'label' => $row['label'],
                                'percent' => $row['percent'],
                                'gap' => $gap,
                                'meal_role' => $this->preferredMealRoleForNutrient($row['key']),
                            ];
                        }
                    }
                }
            }
        }

        if ($worst === null) {
            return null;
        }

        unset($worst['gap']);

        return $worst;
    }

    private function preferredMealRoleForNutrient(string $nutritionKey): string
    {
        return match ($nutritionKey) {
            'iron', 'b9_folate', 'vitamin_a', 'vitamin_c', 'fiber', 'potassium' => 'side_salad',
            'calcium', 'magnesium', 'zinc', 'vitamin_e', 'b6' => 'side_salad',
            'b12', 'vitamin_k2' => 'fish_beef',
            default => 'main',
        };
    }

    private function mealForDayRole(int $dayNumber, string $role): ?Meal
    {
        if ($role === 'fish_beef') {
            return $this->findMealByName(
                BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 3),
            );
        }

        if ($role === 'breakfast') {
            return $this->findMealByName(
                BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Breakfast, 2),
            );
        }

        return match ($role) {
            'breakfast' => $this->findMealByName(
                BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Breakfast, 2),
            ),
            'side_salad' => $this->findMealByName(
                BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Salad, 1),
            ),
            'main' => $this->findMealByName(
                BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 1),
            ),
            default => $this->findMealByName(
                BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 2),
            ),
        };
    }

    private function findMealByName(string $name): ?Meal
    {
        return Meal::queryForMealLibrary()
            ->where('name', $name)
            ->with('ingredients')
            ->first();
    }

    public function applyIsocaloricBoost(Meal $meal, string $nutritionKey, float $gramStep = self::GRAM_STEP): bool
    {
        $meal->loadMissing('ingredients');

        /** @var array<string, float> $ingredientGrams */
        $ingredientGrams = [];

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);

            if ($grams <= 0) {
                continue;
            }

            $ingredientGrams[$ingredient->name] = ($ingredientGrams[$ingredient->name] ?? 0) + $grams;
        }

        if ($ingredientGrams === []) {
            return false;
        }

        $baselineNutrition = RecipeNutritionCalculator::fromMeal($meal);
        $baselineCalories = (float) $baselineNutrition['calories'];
        $boostName = $this->resolveBoostIngredientName($nutritionKey, $ingredientGrams, $meal->name);

        if ($boostName === null) {
            return false;
        }

        $reduceName = $this->resolveFlexibleReduceTarget($ingredientGrams, $nutritionKey);

        if ($reduceName === null) {
            return false;
        }

        /** @var Ingredient|null $boostIngredient */
        $boostIngredient = Ingredient::query()->where('name', $boostName)->first();
        /** @var Ingredient|null $reduceIngredient */
        $reduceIngredient = Ingredient::query()->where('name', $reduceName)->first();

        if ($boostIngredient === null || $reduceIngredient === null) {
            return false;
        }

        $boostCaloriesPerGram = ((float) $boostIngredient->calories) / 100.0;
        $reduceCaloriesPerGram = ((float) $reduceIngredient->calories) / 100.0;

        if ($boostCaloriesPerGram <= 0 || $reduceCaloriesPerGram <= 0) {
            return false;
        }

        $addedCalories = $gramStep * $boostCaloriesPerGram;
        $gramsToRemove = $addedCalories / $reduceCaloriesPerGram;

        if (($ingredientGrams[$reduceName] ?? 0) - $gramsToRemove < 1.0) {
            return false;
        }

        $ingredientGrams[$boostName] = ($ingredientGrams[$boostName] ?? 0) + $gramStep;
        $ingredientGrams[$reduceName] = round($ingredientGrams[$reduceName] - $gramsToRemove, 4);

        if ($ingredientGrams[$reduceName] <= 0) {
            unset($ingredientGrams[$reduceName]);
        }

        $adjustedNutrition = $this->nutritionFromGramMap($ingredientGrams);
        $adjustedCalories = (float) $adjustedNutrition['calories'];

        if (abs($adjustedCalories - $baselineCalories) > self::CALORIE_TOLERANCE) {
            return false;
        }

        if ((float) ($adjustedNutrition['sodium'] ?? 0) > (float) ($baselineNutrition['sodium'] ?? 0) + 75.0) {
            return false;
        }

        $this->syncMeal($meal, $ingredientGrams);

        return true;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @return array<string, float>
     */
    private function nutritionFromGramMap(array $ingredientGrams): array
    {
        $rows = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            if ($grams <= 0) {
                continue;
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->where('name', $ingredientName)->first();

            if ($ingredient === null) {
                continue;
            }

            $rows[] = [
                'ingredient_id' => $ingredient->id,
                'amount_grams' => $grams,
            ];
        }

        return RecipeNutritionCalculator::fromRows($rows);
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    private function resolveBoostIngredientName(string $nutritionKey, array $ingredientGrams, ?string $mealName = null): ?string
    {
        $isChia = $mealName !== null
            && in_array($mealName, BalancedWeeklyRotationSchedule::CHIA_BREAKFASTS, true);

        $primaryPool = $isChia
            ? MicronutrientBoostCatalog::chiaBoostIngredientsForKey($nutritionKey)
            : MicronutrientBoostCatalog::boostIngredientsForKey($nutritionKey);

        $candidates = $this->filterBoostCandidates(
            $primaryPool,
            $ingredientGrams,
            $mealName,
        );

        $selected = $this->selectBestBoostCandidate($candidates, $ingredientGrams);

        if ($selected !== null) {
            return $selected;
        }

        $fallbackPool = $isChia
            ? MicronutrientBoostCatalog::CHIA_ALLOWED_BOOSTS
            : MicronutrientBoostCatalog::BOOST_INGREDIENTS;

        $fallback = $this->filterBoostCandidates(
            $fallbackPool,
            $ingredientGrams,
            $mealName,
        );

        return $this->selectBestBoostCandidate($fallback, $ingredientGrams);
    }

    /**
     * @param  list<string>  $candidates
     * @param  array<string, float>  $ingredientGrams
     * @return list<string>
     */
    private function filterBoostCandidates(array $candidates, array $ingredientGrams, ?string $mealName): array
    {
        $isChia = $mealName !== null
            && in_array($mealName, BalancedWeeklyRotationSchedule::CHIA_BREAKFASTS, true);

        return array_values(array_filter($candidates, function (string $candidate) use ($ingredientGrams, $isChia, $mealName): bool {
            if ($isChia && ! MicronutrientBoostCatalog::isChiaAllowedBoost($candidate)) {
                return false;
            }

            if (in_array($candidate, ['Beef Liver', 'Chicken Liver'], true)
                && ! MicronutrientBoostCatalog::allowsLiverBoost($mealName, $ingredientGrams)) {
                return false;
            }

            if ($candidate === 'Spinach (Fresh)'
                && ($ingredientGrams[$candidate] ?? 0) > MicronutrientBoostCatalog::SPINACH_BOOST_CAP_GRAMS) {
                return false;
            }

            return Ingredient::query()->where('name', $candidate)->exists();
        }));
    }

    /**
     * @param  list<string>  $candidates
     * @param  array<string, float>  $ingredientGrams
     */
    private function selectBestBoostCandidate(array $candidates, array $ingredientGrams): ?string
    {
        if ($candidates === []) {
            return null;
        }

        $greenCandidates = array_values(array_filter(
            $candidates,
            fn (string $candidate): bool => MicronutrientBoostCatalog::isGreenBoostIngredient($candidate),
        ));

        if ($greenCandidates !== []) {
            usort(
                $greenCandidates,
                fn (string $a, string $b): int => ($ingredientGrams[$a] ?? 0) <=> ($ingredientGrams[$b] ?? 0),
            );

            return $greenCandidates[0];
        }

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $ingredientGrams)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    private function resolveFlexibleReduceTarget(array $ingredientGrams, string $nutritionKey): ?string
    {
        $bestName = null;
        $bestScore = null;

        foreach ($ingredientGrams as $name => $grams) {
            if ($grams <= self::GRAM_STEP || MicronutrientBoostCatalog::isAnchorIngredient($name)) {
                continue;
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->where('name', $name)->first();

            if ($ingredient === null) {
                continue;
            }

            $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($ingredient);
            $microPerCalorie = ((float) ($per100[$nutritionKey] ?? 0)) / max(1.0, (float) $ingredient->calories);
            $score = $microPerCalorie;

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $bestName = $name;
            }
        }

        return $bestName;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    private function syncMeal(Meal $meal, array $ingredientGrams): void
    {
        $sync = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            if ($grams <= 0) {
                continue;
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->where('name', $ingredientName)->first();

            if ($ingredient === null) {
                continue;
            }

            $sync[$ingredient->id] = [
                'amount_grams' => round((float) $grams, 4),
                'amount' => round((float) $grams, 4),
                'unit' => 'g',
            ];
        }

        $meal->ingredients()->sync($sync);

        $fresh = $meal->fresh(['ingredients']);
        $nutrition = RecipeNutritionCalculator::fromMeal($fresh);

        $meal->update(array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            ['nutrition_aggregates_synced' => true],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    public function syncMealFromGramMap(Meal $meal, array $ingredientGrams): bool
    {
        if ($ingredientGrams === []) {
            return false;
        }

        $this->syncMeal($meal, $ingredientGrams);

        return true;
    }

    private function referenceProfile(): CustomerProfile
    {
        $existing = CustomerProfile::query()
            ->whereNotNull('daily_calorie_target')
            ->orderBy('id')
            ->first();

        if ($existing instanceof CustomerProfile) {
            return $existing;
        }

        $user = User::factory()->create();

        return CustomerProfile::factory()->for($user)->create([
            'daily_calorie_target' => 1500,
            'protein_percentage' => 40.0,
            'carb_percentage' => 30.0,
            'fat_percentage' => 30.0,
        ]);
    }
}
