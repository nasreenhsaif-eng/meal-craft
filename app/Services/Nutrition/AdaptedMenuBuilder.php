<?php

namespace App\Services\Nutrition;

use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeIngredientUnitConverter;
use App\Services\RecipeNutritionCalculator;
use App\Support\ChiaBreakfastMeals;
use App\Support\MealPlanSlotBasedDayNutrition;
use App\Support\SavoryEggBreakfastMeals;

/**
 * Builds the customer-facing menu with per-meal scaling for breakfast and mains,
 * and standard fixed portions for side salads and desserts. Soup is an optional
 * add-on at standard portion (no scaling).
 */
final class AdaptedMenuBuilder
{
    /**
     * @param  array{
     *     include_soup?: bool,
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     snap_to_tier?: bool,
     *     craft_key?: string,
     *     fixed_chia_breakfast?: bool,
     *     schedule_slot?: string,
     * }  $options
     * @return array{
     *     plan: array<string, mixed>,
     *     fixed_portion_meals: list<array<string, mixed>>,
     *     optional_add_on_meals: list<array<string, mixed>>,
     *     scalable_meals: list<array<string, mixed>>,
     *     fixed_meals: list<array<string, mixed>>
     * }
     */
    public static function build(CustomerProfile $profile, array $options = []): array
    {
        $plan = UserPlanCalculator::calculateUserPlan($profile, $options);

        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';

        if ($craftKey !== '' && in_array($craftKey, CraftCaloriePlanner::keys(), true)) {
            $plan = CraftCaloriePlanner::applyCraftToPlan($plan, $craftKey);
        }

        $meals = Meal::queryForMealLibrary()
            ->with('ingredients')
            ->orderBy('library_sort_order')
            ->orderBy('name')
            ->get();

        $fixedPortionMeals = [];
        $optionalAddOnMeals = [];
        $scalableMeals = [];

        foreach ($meals as $meal) {
            $slot = self::resolveSlot($meal);

            if ($slot === null) {
                continue;
            }

            $behavior = UserPlanCalculator::slotBehavior($slot);

            if ($behavior === 'fixed_portion') {
                $fixedPortionMeals[] = self::serializeStandardPortionMeal($meal, $slot, $profile);

                continue;
            }

            if ($behavior === 'scalable') {
                if ($slot === 'breakfast' && ChiaBreakfastMeals::isChiaBreakfast($meal)) {
                    $scalableMeals[] = self::serializeChiaBreakfastMeal($meal, $plan);

                    continue;
                }

                $scalableMeals[] = self::serializeScaledMeal($meal, $slot, $plan);
            }
        }

        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';
        $scheduleOptions = array_filter([
            'craft_key' => $craftKey !== '' ? $craftKey : null,
            'include_soup' => ($options['include_soup'] ?? false) ? true : null,
            'soup_calories' => isset($options['soup_calories']) ? (float) $options['soup_calories'] : null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return [
            'plan' => $plan,
            'fixed_portion_meals' => $fixedPortionMeals,
            'optional_add_on_meals' => $optionalAddOnMeals,
            'scalable_meals' => $scalableMeals,
            'fixed_meals' => $fixedPortionMeals,
            'scheduled_soups_by_weekday' => ProductionWeeklyMenuSchedule::scheduledSoupsByWeekday($profile, null, $scheduleOptions),
            'scheduled_full_craft_by_weekday' => ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday($profile, null, $scheduleOptions),
            'production_meal_plan_id' => ProductionWeeklyMenuSchedule::resolveProductionMealPlan()?->id,
        ];
    }

    /**
     * @param  array{
     *     include_soup?: bool,
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     snap_to_tier?: bool,
     *     craft_key?: string,
     *     fixed_chia_breakfast?: bool,
     *     schedule_slot?: string,
     * }  $options
     * @return array<string, mixed>|null
     */
    public static function adaptMealForProfile(CustomerProfile $profile, Meal $meal, array $options = []): ?array
    {
        $meal->loadMissing('ingredients');
        $slot = self::resolveAdaptationSlot($meal, $options);

        if ($slot === null) {
            return null;
        }

        $plan = UserPlanCalculator::calculateUserPlan($profile, $options);

        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';

        if ($craftKey !== '' && in_array($craftKey, CraftCaloriePlanner::keys(), true)) {
            $plan = CraftCaloriePlanner::applyCraftToPlan($plan, $craftKey);
        }
        $behavior = UserPlanCalculator::slotBehavior($slot);

        if ($behavior === 'scalable') {
            if ($slot === 'breakfast' && ChiaBreakfastMeals::isChiaBreakfast($meal)) {
                return self::serializeChiaBreakfastMeal($meal, $plan);
            }

            return self::serializeScaledMeal($meal, $slot, $plan);
        }

        return self::serializeStandardPortionMeal($meal, $slot, $profile);
    }

    /**
     * Calorie-scale each main, then boost non-vegan mains when a vegan choice lowers combined protein.
     *
     * @param  list<Meal>  $meals
     * @param  array{
     *     include_soup?: bool,
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     snap_to_tier?: bool,
     *     craft_key?: string,
     *     fixed_chia_breakfast?: bool,
     *     schedule_slot?: string,
     * }  $options
     * @return list<array<string, mixed>>
     */
    public static function adaptMainMealsForProfile(CustomerProfile $profile, array $meals, array $options = []): array
    {
        if ($meals === []) {
            return [];
        }

        $plan = UserPlanCalculator::calculateUserPlan($profile, $options);

        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';

        if ($craftKey !== '' && in_array($craftKey, CraftCaloriePlanner::keys(), true)) {
            $plan = CraftCaloriePlanner::applyCraftToPlan($plan, $craftKey);
        }

        $adapted = [];

        foreach ($meals as $meal) {
            $meal->loadMissing('ingredients');
            $adapted[] = self::serializeScaledMeal($meal, 'main', $plan);
        }

        return self::balanceMainMealProtein($adapted, $plan, $meals);
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @return list<array<string, mixed>>
     */
    public static function balanceMainMealProtein(array $adaptedMains, array $plan, array $meals): array
    {
        if ($adaptedMains === [] || count($adaptedMains) !== count($meals)) {
            return $adaptedMains;
        }

        $mainCount = count($adaptedMains);
        $proteinTargetEach = (float) ($plan['scalable_slot_targets']['main_each']['macros']['protein_g'] ?? 0);
        $slotTargetCaloriesEach = (float) ($plan['scalable_slot_targets']['main_each']['calories'] ?? 0);

        if ($proteinTargetEach <= 0) {
            return $adaptedMains;
        }

        $proteinTargetTotal = round($proteinTargetEach * $mainCount, 2);
        $currentProteinTotal = 0.0;

        foreach ($adaptedMains as $adapted) {
            $currentProteinTotal += (float) ($adapted['adapted_nutrition']['protein'] ?? 0);
        }

        $shortfall = round($proteinTargetTotal - $currentProteinTotal, 2);

        if ($shortfall <= 0.25) {
            return $adaptedMains;
        }

        $compensatorIndexes = [];

        foreach ($meals as $index => $meal) {
            if (! $meal->isVegan()) {
                $compensatorIndexes[] = $index;
            }
        }

        if ($compensatorIndexes === []) {
            return $adaptedMains;
        }

        $compensatingProtein = 0.0;

        foreach ($compensatorIndexes as $index) {
            $compensatingProtein += (float) ($adaptedMains[$index]['adapted_nutrition']['protein'] ?? 0);
        }

        if ($compensatingProtein <= 0) {
            return $adaptedMains;
        }

        $balanced = $adaptedMains;

        foreach ($compensatorIndexes as $index) {
            $meal = $meals[$index];
            $adapted = $balanced[$index];
            $currentProtein = (float) ($adapted['adapted_nutrition']['protein'] ?? 0);

            if ($currentProtein <= 0) {
                continue;
            }

            $proteinShare = $currentProtein / $compensatingProtein;
            $addedProtein = round($shortfall * $proteinShare, 2);
            $targetProtein = $currentProtein + $addedProtein;
            $boostMultiplier = $targetProtein / $currentProtein;
            $currentScale = (float) ($adapted['scaling_multiplier'] ?? 1.0);
            $currentCalories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

            if ($currentCalories <= 0) {
                continue;
            }

            $maxBoostFromCalories = $slotTargetCaloriesEach > 0
                ? $slotTargetCaloriesEach / $currentCalories
                : $boostMultiplier;
            $effectiveBoost = min($boostMultiplier, $maxBoostFromCalories);

            if ($effectiveBoost <= 1.0001) {
                continue;
            }

            $balanced[$index] = self::serializeScaledMeal(
                $meal,
                'main',
                $plan,
                round($currentScale * $effectiveBoost, 4),
                proteinBalanced: true,
            );
        }

        return $balanced;
    }

    public static function mealScalingMultiplier(Meal $meal, string $slot, array $plan): float
    {
        $baselineCalories = (float) ($meal->nutritionForDisplay()['calories'] ?? 0);

        if ($baselineCalories <= 0) {
            return 1.0;
        }

        $slotTarget = $slot === 'breakfast'
            ? (float) $plan['scalable_slot_targets']['breakfast']['calories']
            : (float) $plan['scalable_slot_targets']['main_each']['calories'];

        if ($slot === 'main' && ($plan['craft_key'] ?? '') === CraftCaloriePlanner::CRAFT_BUSINESS) {
            $business = UserPlanCalculator::businessCraftConfig();
            $slotTarget = max($business['main_min'], min($business['main_max'], $slotTarget));
        }

        return max(0.0, round($slotTarget / $baselineCalories, 4));
    }

    private static function resolveSlot(Meal $meal): ?string
    {
        if ($meal->meal_type instanceof MealType) {
            return match ($meal->meal_type) {
                MealType::Breakfast => 'breakfast',
                MealType::Main => 'main',
                MealType::Soup => 'soup',
                MealType::Salad => 'side_salad',
                MealType::Dessert => 'dessert',
                default => null,
            };
        }

        if ($meal->category instanceof RecipeCategory) {
            return match ($meal->category) {
                RecipeCategory::Breakfast => 'breakfast',
                RecipeCategory::Meal => 'main',
                RecipeCategory::Soup => 'soup',
                RecipeCategory::SideSalad, RecipeCategory::MainSalad => 'side_salad',
                RecipeCategory::Dessert => 'dessert',
                default => null,
            };
        }

        return null;
    }

    /**
     * Production schedule slot wins over library meal type (e.g. chicken salad mains stored as Side Salad).
     *
     * @param  array{schedule_slot?: string}  $options
     */
    private static function resolveAdaptationSlot(Meal $meal, array $options = []): ?string
    {
        if (isset($options['schedule_slot'])) {
            $scheduled = strtolower(trim((string) $options['schedule_slot']));

            if (in_array($scheduled, ['breakfast', 'main', 'side_salad', 'dessert', 'soup'], true)) {
                return $scheduled;
            }
        }

        return self::resolveSlot($meal);
    }

    public static function adaptationSlotForMealPlanSlot(MealPlanSlotType $slotType): string
    {
        return match ($slotType) {
            MealPlanSlotType::Breakfast => 'breakfast',
            MealPlanSlotType::Main => 'main',
            MealPlanSlotType::Salad => 'side_salad',
            MealPlanSlotType::Dessert => 'dessert',
            MealPlanSlotType::Soup => 'soup',
        };
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private static function serializeChiaBreakfastMeal(Meal $meal, array $plan): array
    {
        $serialized = self::serializeScaledMeal($meal, 'breakfast', $plan);
        $serialized['fixed_chia_breakfast'] = true;
        $serialized['kitchen_portion_calories'] = ChiaBreakfastMeals::fixedCalories();

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeStandardPortionMeal(
        Meal $meal,
        string $slot,
        CustomerProfile $profile,
        bool $isOptionalAddOn = false,
    ): array {
        $baseline = $meal->nutritionForDisplay();
        $normalizedBaseline = self::normalizeNutritionKeys($baseline);

        return [
            'id' => $meal->id,
            'name' => $meal->name,
            'slot' => $slot,
            'portion_behavior' => UserPlanCalculator::slotBehavior($slot),
            'is_scaled' => false,
            'scaling_multiplier' => 1.0,
            'counts_toward_core_tier' => ! $isOptionalAddOn,
            'image_url' => $meal->imageUrl(),
            'instructions' => $meal->instructions,
            'short_description' => $meal->short_description,
            'baseline_nutrition' => $normalizedBaseline,
            'adapted_nutrition' => $normalizedBaseline,
            'ingredients' => self::serializeScaledIngredients($meal, 1.0),
            'planning_midpoint_calories' => UserPlanCalculator::slotPlanningMidpoint($slot),
            'macro_split' => [
                'protein_percentage' => (float) $profile->protein_percentage,
                'carb_percentage' => (float) $profile->carb_percentage,
                'fat_percentage' => (float) $profile->fat_percentage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private static function serializeScaledMeal(
        Meal $meal,
        string $slot,
        array $plan,
        ?float $overrideMultiplier = null,
        bool $proteinBalanced = false,
    ): array {
        $multiplier = $overrideMultiplier ?? self::mealScalingMultiplier($meal, $slot, $plan);
        $baseline = $meal->nutritionForDisplay();
        $targetCalories = self::slotTargetCalories($slot, $plan);
        $adaptedGramsByIngredientId = self::adaptedGramsFromMultiplier($meal, $multiplier);

        if ($slot === 'breakfast' && $targetCalories > 0) {
            $adaptedGramsByIngredientId = self::normalizeAdaptedGramsToCalorieTarget(
                $meal,
                $adaptedGramsByIngredientId,
                $targetCalories,
                (float) ($plan['plan_tier'] ?? 0),
            );
        }

        $scaledRows = self::scaledIngredientRowsFromAdaptedGrams($meal, $adaptedGramsByIngredientId);
        $adaptedNutrition = RecipeNutritionCalculator::fromRows($scaledRows);
        $baselineCalories = (float) ($baseline['calories'] ?? 0);
        $adaptedCalories = (float) ($adaptedNutrition['calories'] ?? 0);
        $overallMultiplier = $baselineCalories > 0
            ? round($adaptedCalories / $baselineCalories, 4)
            : $multiplier;

        $serialized = [
            'id' => $meal->id,
            'name' => $meal->name,
            'slot' => $slot,
            'portion_behavior' => UserPlanCalculator::slotBehavior($slot),
            'is_scaled' => $overallMultiplier !== 1.0,
            'scaling_multiplier' => $overallMultiplier,
            'protein_balanced' => $proteinBalanced,
            'is_vegan' => $meal->isVegan(),
            'counts_toward_core_tier' => true,
            'image_url' => $meal->imageUrl(),
            'instructions' => $meal->instructions,
            'short_description' => $meal->short_description,
            'baseline_nutrition' => self::normalizeNutritionKeys($baseline),
            'adapted_nutrition' => self::normalizeNutritionKeys($adaptedNutrition),
            'ingredients' => self::serializeScaledIngredientsFromAdaptedGrams($meal, $adaptedGramsByIngredientId),
            'slot_target' => $slot === 'breakfast'
                ? $plan['scalable_slot_targets']['breakfast']
                : $plan['scalable_slot_targets']['main_each'],
        ];

        if ($slot === 'breakfast' && SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal)) {
            $serialized['savory_egg_count'] = SavoryEggBreakfastMeals::eggCountForPlanTier(
                (float) ($plan['plan_tier'] ?? 0),
            );
        }

        return $serialized;
    }

    /**
     * Scale adapted ingredient grams so total calories land on the slot target.
     *
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return array<int, float>
     */
    private static function normalizeAdaptedGramsToCalorieTarget(
        Meal $meal,
        array $adaptedGramsByIngredientId,
        float $targetCalories,
        float $planTier = 0.0,
    ): array {
        if ($targetCalories <= 0 || $adaptedGramsByIngredientId === []) {
            return $adaptedGramsByIngredientId;
        }

        $normalized = $adaptedGramsByIngredientId;

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $scaledRows = self::scaledIngredientRowsFromAdaptedGrams($meal, $normalized);
            $nutrition = RecipeNutritionCalculator::fromRows($scaledRows);
            $adaptedCalories = (float) ($nutrition['calories'] ?? 0);

            if ($adaptedCalories <= 0) {
                return self::finalizeBreakfastGrams($meal, $normalized, $targetCalories, $planTier);
            }

            if (abs($adaptedCalories - $targetCalories) <= 0.5) {
                break;
            }

            $ratio = round($targetCalories / $adaptedCalories, 4);
            $next = [];

            foreach ($meal->ingredients as $ingredient) {
                $grams = (float) ($normalized[$ingredient->id] ?? 0);

                if ($grams <= 0) {
                    continue;
                }

                $next[$ingredient->id] = round($grams * $ratio, 4);
            }

            $normalized = $next;
        }

        return self::finalizeBreakfastGrams($meal, $normalized, $targetCalories, $planTier);
    }

    /**
     * Apply tier side minimums, then trim flexible ingredients if minimums pushed calories over target.
     *
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return array<int, float>
     */
    private static function finalizeBreakfastGrams(
        Meal $meal,
        array $adaptedGramsByIngredientId,
        float $targetCalories,
        float $planTier,
    ): array {
        $withMinimums = self::applyBreakfastSideMinimums($meal, $adaptedGramsByIngredientId, $planTier);

        if ($targetCalories <= 0) {
            return $withMinimums;
        }

        return self::trimBreakfastFlexibleGramsToTarget($meal, $withMinimums, $targetCalories, $planTier);
    }

    /**
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return array<int, float>
     */
    private static function trimBreakfastFlexibleGramsToTarget(
        Meal $meal,
        array $adaptedGramsByIngredientId,
        float $targetCalories,
        float $planTier,
    ): array {
        $scaledRows = self::scaledIngredientRowsFromAdaptedGrams($meal, $adaptedGramsByIngredientId);
        $adaptedCalories = (float) (RecipeNutritionCalculator::fromRows($scaledRows)['calories'] ?? 0);

        if ($adaptedCalories <= $targetCalories + 0.5) {
            return $adaptedGramsByIngredientId;
        }

        /** @var list<int> $fixedIngredientIds */
        $fixedIngredientIds = [];

        foreach ($meal->ingredients as $ingredient) {
            if ($planTier > 0 && SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($ingredient, $planTier) !== null) {
                $fixedIngredientIds[] = $ingredient->id;
            }
        }

        $fixedCalories = 0.0;
        $flexibleCalories = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($adaptedGramsByIngredientId[$ingredient->id] ?? 0);

            if ($grams <= 0) {
                continue;
            }

            $rowCalories = self::ingredientCaloriesForGrams($ingredient, $grams);

            if (in_array($ingredient->id, $fixedIngredientIds, true)) {
                $fixedCalories += $rowCalories;
            } else {
                $flexibleCalories += $rowCalories;
            }
        }

        $flexibleBudget = max(0.0, $targetCalories - $fixedCalories);

        if ($flexibleCalories <= 0 || $flexibleBudget <= 0) {
            return $adaptedGramsByIngredientId;
        }

        $flexRatio = round($flexibleBudget / $flexibleCalories, 4);
        $adjusted = $adaptedGramsByIngredientId;

        foreach ($meal->ingredients as $ingredient) {
            if (in_array($ingredient->id, $fixedIngredientIds, true)) {
                continue;
            }

            $grams = (float) ($adaptedGramsByIngredientId[$ingredient->id] ?? 0);

            if ($grams <= 0) {
                continue;
            }

            $adjusted[$ingredient->id] = round($grams * $flexRatio, 4);
        }

        return $adjusted;
    }

    private static function ingredientCaloriesForGrams(Ingredient $ingredient, float $grams): float
    {
        $per100 = (float) ($ingredient->calories ?? 0);

        return $per100 > 0 ? ($per100 / 100.0) * $grams : 0.0;
    }

    /**
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return array<int, float>
     */
    private static function applyBreakfastSideMinimums(
        Meal $meal,
        array $adaptedGramsByIngredientId,
        float $planTier,
    ): array {
        if ($planTier <= 0) {
            return $adaptedGramsByIngredientId;
        }

        foreach ($meal->ingredients as $ingredient) {
            if (! isset($adaptedGramsByIngredientId[$ingredient->id])) {
                continue;
            }

            $minimum = SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($ingredient, $planTier);

            if ($minimum !== null) {
                $adaptedGramsByIngredientId[$ingredient->id] = max(
                    $minimum,
                    (float) $adaptedGramsByIngredientId[$ingredient->id],
                );
            }
        }

        return $adaptedGramsByIngredientId;
    }

    /**
     * @return array<int, float>
     */
    private static function adaptedGramsFromMultiplier(Meal $meal, float $multiplier): array
    {
        $gramsByIngredientId = [];

        foreach ($meal->ingredients as $ingredient) {
            $pivot = $ingredient->pivot;
            $baselineGrams = self::baselineGramsForPivot(
                $ingredient,
                $pivot->amount,
                $pivot->unit ?? '',
                (float) ($pivot->amount_grams ?? 0),
            );

            if ($baselineGrams <= 0) {
                continue;
            }

            $gramsByIngredientId[$ingredient->id] = round($baselineGrams * $multiplier, 4);
        }

        return $gramsByIngredientId;
    }

    private static function slotTargetCalories(string $slot, array $plan): float
    {
        if ($slot === 'breakfast') {
            return (float) ($plan['scalable_slot_targets']['breakfast']['calories'] ?? 0);
        }

        return (float) ($plan['scalable_slot_targets']['main_each']['calories'] ?? 0);
    }

    /**
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return list<array<string, mixed>>
     */
    private static function scaledIngredientRowsFromAdaptedGrams(Meal $meal, array $adaptedGramsByIngredientId): array
    {
        $rows = [];

        foreach ($meal->ingredients as $ingredient) {
            $adaptedGrams = (float) ($adaptedGramsByIngredientId[$ingredient->id] ?? 0);

            if ($adaptedGrams <= 0) {
                continue;
            }

            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;
            $unitRaw = $pivot->unit ?? '';

            if ($hasDisplayAmount && is_string($unitRaw) && $unitRaw !== '') {
                $baselineGrams = self::baselineGramsForPivot($ingredient, $pivotAmount, $unitRaw, (float) ($pivot->amount_grams ?? 0));
                $amountMultiplier = $baselineGrams > 0 ? $adaptedGrams / $baselineGrams : 1.0;
                $rows[] = [
                    'ingredient_id' => $ingredient->id,
                    'amount' => round((float) $pivotAmount * $amountMultiplier, 4),
                    'unit' => $unitRaw,
                ];

                continue;
            }

            $rows[] = [
                'ingredient_id' => $ingredient->id,
                'amount_grams' => round($adaptedGrams, 4),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return list<array<string, mixed>>
     */
    private static function serializeScaledIngredientsFromAdaptedGrams(Meal $meal, array $adaptedGramsByIngredientId): array
    {
        $out = [];

        foreach ($meal->ingredients as $ingredient) {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;
            $unitRaw = $pivot->unit ?? '';

            $baselineGrams = self::baselineGramsForPivot(
                $ingredient,
                $pivotAmount,
                $unitRaw,
                (float) ($pivot->amount_grams ?? 0),
            );
            $adaptedGrams = round((float) ($adaptedGramsByIngredientId[$ingredient->id] ?? $baselineGrams), 2);

            $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($ingredient);
            $factor = $adaptedGrams / 100.0;

            $out[] = [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'baseline_amount_grams' => round($baselineGrams, 2),
                'adapted_amount_grams' => $adaptedGrams,
                'baseline_amount' => $hasDisplayAmount ? (float) $pivotAmount : null,
                'adapted_amount' => $hasDisplayAmount && $baselineGrams > 0
                    ? round((float) $pivotAmount * ($adaptedGrams / $baselineGrams), 4)
                    : null,
                'unit' => $hasDisplayAmount ? (string) $unitRaw : 'g',
                'adapted_macros' => [
                    'calories' => round(((float) ($per100['calories'] ?? 0)) * $factor, 2),
                    'protein' => round(((float) ($per100['protein'] ?? 0)) * $factor, 2),
                    'carbs' => round(((float) ($per100['carbs'] ?? 0)) * $factor, 2),
                    'fat' => round(((float) ($per100['fat'] ?? 0)) * $factor, 2),
                ],
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function scaledIngredientRows(Meal $meal, float $multiplier): array
    {
        $rows = [];

        foreach ($meal->ingredients as $ingredient) {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;
            $unitRaw = $pivot->unit ?? '';

            if ($hasDisplayAmount && is_string($unitRaw) && $unitRaw !== '') {
                $rows[] = [
                    'ingredient_id' => $ingredient->id,
                    'amount' => round((float) $pivotAmount * $multiplier, 4),
                    'unit' => $unitRaw,
                ];

                continue;
            }

            $grams = (float) ($pivot->amount_grams ?? 0);
            if ($grams <= 0) {
                continue;
            }

            $rows[] = [
                'ingredient_id' => $ingredient->id,
                'amount_grams' => round($grams * $multiplier, 4),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function serializeScaledIngredients(Meal $meal, float $multiplier): array
    {
        $out = [];

        foreach ($meal->ingredients as $ingredient) {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;
            $unitRaw = $pivot->unit ?? '';

            $baselineGrams = self::baselineGramsForPivot($ingredient, $pivotAmount, $unitRaw, (float) ($pivot->amount_grams ?? 0));
            $adaptedGrams = round($baselineGrams * $multiplier, 2);

            $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($ingredient);
            $factor = $adaptedGrams / 100.0;

            $out[] = [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'baseline_amount_grams' => round($baselineGrams, 2),
                'adapted_amount_grams' => $adaptedGrams,
                'baseline_amount' => $hasDisplayAmount ? (float) $pivotAmount : null,
                'adapted_amount' => $hasDisplayAmount ? round((float) $pivotAmount * $multiplier, 4) : null,
                'unit' => $hasDisplayAmount ? (string) $unitRaw : 'g',
                'adapted_macros' => [
                    'calories' => round(((float) ($per100['calories'] ?? 0)) * $factor, 2),
                    'protein' => round(((float) ($per100['protein'] ?? 0)) * $factor, 2),
                    'carbs' => round(((float) ($per100['carbs'] ?? 0)) * $factor, 2),
                    'fat' => round(((float) ($per100['fat'] ?? 0)) * $factor, 2),
                ],
            ];
        }

        return $out;
    }

    /**
     * Overlay profile-adapted nutrition onto a meal library UI row (consultation / plan summary).
     *
     * @param  array<string, mixed>  $baseRow  from {@see MealLibraryController::presentMealRowForUi()}
     * @param  array<string, mixed>  $adapted  from {@see serializeScaledMeal()}
     * @return array<string, mixed>
     */
    public static function overlayAdaptedNutritionOnMealRow(array $baseRow, array $adapted): array
    {
        /** @var array<string, float|int|string> $nutrition */
        $nutrition = is_array($adapted['adapted_nutrition'] ?? null) ? $adapted['adapted_nutrition'] : [];

        $macros = [
            'calories' => (int) round((float) ($nutrition['calories'] ?? 0)),
            'protein' => round((float) ($nutrition['protein'] ?? 0), 1),
            'carbs' => round((float) ($nutrition['carbs'] ?? 0), 1),
            'fat' => round((float) ($nutrition['fat'] ?? 0), 1),
        ];

        $baseRow['macros'] = $macros;
        $baseRow['caloriesNumber'] = $macros['calories'];
        $baseRow['isScaled'] = (bool) ($adapted['is_scaled'] ?? false);
        $baseRow['scalingMultiplier'] = (float) ($adapted['scaling_multiplier'] ?? 1);
        $baseRow['proteinBalanced'] = (bool) ($adapted['protein_balanced'] ?? false);
        $baseRow['isVegan'] = (bool) ($adapted['is_vegan'] ?? false);
        $baseRow['slot'] = (string) ($adapted['slot'] ?? '');

        if (isset($baseRow['detailView']) && is_array($baseRow['detailView'])) {
            $detailView = $baseRow['detailView'];
            $detailView['macros'] = $macros;
            $baseRow['detailView'] = $detailView;
        }

        return $baseRow;
    }

    private static function baselineGramsForPivot(
        Ingredient $ingredient,
        mixed $pivotAmount,
        mixed $unitRaw,
        float $amountGrams,
    ): float {
        $hasDisplayAmount = $pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0;

        if ($hasDisplayAmount && is_string($unitRaw) && $unitRaw !== '') {
            return RecipeIngredientUnitConverter::toGrams(
                max(0.0, (float) $pivotAmount),
                (string) $unitRaw,
                (float) ($ingredient->density ?? 1.0),
            );
        }

        return max(0.0, $amountGrams);
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, float>
     */
    private static function normalizeNutritionKeys(array $nutrition): array
    {
        $keys = MealPlanSlotBasedDayNutrition::nutritionKeys();
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = round((float) ($nutrition[$key] ?? 0), 2);
        }

        return $out;
    }
}
