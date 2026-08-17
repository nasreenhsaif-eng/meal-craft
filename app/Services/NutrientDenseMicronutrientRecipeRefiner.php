<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Support\MainMealVegetablePortionFloor;
use App\Support\MealLibraryEditGuard;
use App\Support\NutrientDailyRdi;
use Illuminate\Support\Facades\DB;

/**
 * Isocalorically adjusts rotation meal recipes so reference Full Craft days reach floor RDI at 1500+ kcal tiers.
 */
final class NutrientDenseMicronutrientRecipeRefiner
{
    private const MAX_DAY_PASSES = 100;

    private const GRAM_STEP = 8.0;

    private const CALORIE_TOLERANCE = 0.5;

    /** @var list<string> */
    private const SIDE_SALAD_PRIORITY_KEYS = [
        'vitamin_k2',
        'calcium',
        'magnesium',
        'iron',
        'b12',
        'b9_folate',
        'vitamin_a',
        'fiber',
        'potassium',
        'zinc',
        'vitamin_c',
        'vitamin_e',
        'b6',
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
                NutrientDenseWeeklyRotationSchedule::MICRO_DENSE_SIDE_SALADS,
                self::SIDE_SALAD_PRIORITY_KEYS,
            ));

            $updated = array_merge($updated, $this->refineMealList(
                array_merge(
                    NutrientDenseWeeklyRotationSchedule::CHICKEN_SALAD_MAINS,
                    NutrientDenseWeeklyRotationSchedule::VEGAN_MAINS,
                ),
                self::VEGAN_MAIN_PRIORITY_KEYS,
            ));

            $updated = array_merge($updated, $this->refineMealList(
                NutrientDenseWeeklyRotationSchedule::CHICKEN_PLATE_MAINS,
                ['iron', 'potassium', 'magnesium', 'zinc', 'b6', 'vitamin_e'],
            ));

            $updated = array_merge($updated, $this->refineMealList(
                NutrientDenseWeeklyRotationSchedule::FISH_MAINS,
                ['b12', 'vitamin_d', 'iron', 'potassium', 'magnesium', 'zinc', 'b6', 'vitamin_e'],
            ));

            $updated = array_merge($updated, $this->refineMealList(
                NutrientDenseWeeklyRotationSchedule::LIVER_MAINS,
                ['b12', 'vitamin_k2', 'iron', 'b9_folate', 'vitamin_a', 'fiber', 'potassium', 'magnesium', 'zinc', 'b6', 'vitamin_e'],
            ));

            $updated = array_merge($updated, $this->refineMealList(
                NutrientDenseWeeklyRotationSchedule::EGG_BREAKFASTS,
                ['b9_folate', 'vitamin_a', 'iron', 'b12', 'vitamin_k2'],
            ));

            $updated = array_merge($updated, $this->refineMealList(
                NutrientDenseWeeklyRotationSchedule::CHIA_DESSERTS,
                ['calcium', 'magnesium', 'zinc', 'vitamin_e'],
            ));

            $updated = array_merge($updated, $this->refineMealList(
                NutrientDenseWeeklyRotationSchedule::BAKED_DESSERTS,
                ['fiber', 'calcium', 'magnesium', 'zinc', 'vitamin_e'],
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
            'iron', 'b9_folate', 'vitamin_a', 'vitamin_c', 'potassium' => 'side_salad',
            'calcium', 'magnesium', 'zinc', 'vitamin_e', 'b6' => 'side_salad',
            'fiber' => 'baked_dessert',
            'b12', 'vitamin_k2' => 'liver',
            default => 'main',
        };
    }

    private function mealForDayRole(int $dayNumber, string $role): ?Meal
    {
        if ($role === 'liver') {
            return $this->findMealByName(
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 5),
            );
        }

        if ($role === 'fish_beef') {
            return $this->findMealByName(
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 3),
            );
        }

        if ($role === 'breakfast') {
            return $this->findMealByName(
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Breakfast, 1),
            );
        }

        return match ($role) {
            'side_salad' => $this->findMealByName(
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Salad, 1),
            ),
            'baked_dessert' => $this->bakedDessertMealForDay($dayNumber),
            'main' => $this->findMealByName(
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 1),
            ),
            default => $this->findMealByName(
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 2),
            ),
        };
    }

    private function bakedDessertMealForDay(int $dayNumber): ?Meal
    {
        $dessertName = NutrientDenseWeeklyRotationSchedule::mealNameForDay(
            $dayNumber,
            MealPlanSlotType::Dessert,
            1,
        );

        if (! in_array($dessertName, NutrientDenseWeeklyRotationSchedule::BAKED_DESSERTS, true)) {
            return null;
        }

        return $this->findMealByName($dessertName);
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

        $reduceName = $this->resolveFlexibleReduceTarget($ingredientGrams, $nutritionKey, $meal);

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

        $minimumRemaining = max(
            1.0,
            MainMealVegetablePortionFloor::minimumGrams($meal, $reduceName) ?? 1.0,
        );

        if (($ingredientGrams[$reduceName] ?? 0) - $gramsToRemove < $minimumRemaining) {
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

        return RecipeNutritionCalculator::fromRows($rows, applyMealCookingYield: true);
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    private function resolveBoostIngredientName(string $nutritionKey, array $ingredientGrams, ?string $mealName = null): ?string
    {
        $isChia = $mealName !== null
            && in_array($mealName, NutrientDenseWeeklyRotationSchedule::CHIA_DESSERTS, true);

        $isBakedDessert = $mealName !== null
            && in_array($mealName, NutrientDenseWeeklyRotationSchedule::BAKED_DESSERTS, true);

        $primaryPool = match (true) {
            $isBakedDessert => MicronutrientBoostCatalog::bakedDessertBoostIngredientsForKey($nutritionKey),
            $isChia => MicronutrientBoostCatalog::chiaBoostIngredientsForKey($nutritionKey),
            default => MicronutrientBoostCatalog::boostIngredientsForKey($nutritionKey),
        };

        $candidates = $this->filterBoostCandidates(
            $primaryPool,
            $ingredientGrams,
            $mealName,
        );

        $selected = MicronutrientBoostCatalog::selectBestBoostCandidate($candidates, $ingredientGrams);

        if ($selected !== null) {
            return $selected;
        }

        $fallbackPool = match (true) {
            $isBakedDessert => MicronutrientBoostCatalog::BAKED_DESSERT_ALLOWED_BOOSTS,
            $isChia => MicronutrientBoostCatalog::CHIA_ALLOWED_BOOSTS,
            default => MicronutrientBoostCatalog::BOOST_INGREDIENTS,
        };

        $fallback = $this->filterBoostCandidates(
            $fallbackPool,
            $ingredientGrams,
            $mealName,
        );

        return MicronutrientBoostCatalog::selectBestBoostCandidate($fallback, $ingredientGrams);
    }

    /**
     * @param  list<string>  $candidates
     * @param  array<string, float>  $ingredientGrams
     * @return list<string>
     */
    private function filterBoostCandidates(array $candidates, array $ingredientGrams, ?string $mealName): array
    {
        $isChia = $mealName !== null
            && in_array($mealName, NutrientDenseWeeklyRotationSchedule::CHIA_DESSERTS, true);

        $isBakedDessert = $mealName !== null
            && in_array($mealName, NutrientDenseWeeklyRotationSchedule::BAKED_DESSERTS, true);

        return array_values(array_filter($candidates, function (string $candidate) use ($ingredientGrams, $isChia, $isBakedDessert, $mealName): bool {
            if ($isBakedDessert && ! MicronutrientBoostCatalog::isBakedDessertAllowedBoost($candidate)) {
                return false;
            }

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
     * @param  array<string, float>  $ingredientGrams
     */
    private function resolveFlexibleReduceTarget(array $ingredientGrams, string $nutritionKey, Meal $meal): ?string
    {
        $bestName = null;
        $bestScore = null;

        foreach ($ingredientGrams as $name => $grams) {
            if ($grams <= self::GRAM_STEP || MicronutrientBoostCatalog::isAnchorIngredient($name)) {
                continue;
            }

            if (MainMealVegetablePortionFloor::minimumGrams($meal, $name) !== null) {
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
        $ingredientGrams = MainMealVegetablePortionFloor::applyFloors($meal, $ingredientGrams);

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
            $existing->forceFill([
                'diet_protocol' => 'nutrient_dense',
                'protein_percentage' => 32.0,
                'carb_percentage' => 28.0,
                'fat_percentage' => 40.0,
            ]);

            return $existing;
        }

        $user = User::factory()->create();

        return CustomerProfile::factory()->for($user)->create([
            'daily_calorie_target' => 1500,
            'diet_protocol' => 'nutrient_dense',
            'protein_percentage' => 32.0,
            'carb_percentage' => 28.0,
            'fat_percentage' => 40.0,
        ]);
    }
}
