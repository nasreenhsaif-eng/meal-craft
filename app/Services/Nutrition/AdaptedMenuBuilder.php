<?php

namespace App\Services\Nutrition;

use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedChiaDessertRecipeRefiner;
use App\Services\RecipeIngredientUnitConverter;
use App\Services\RecipeNutritionCalculator;
use App\Support\ChiaDessertMeals;
use App\Support\EggIngredientPresentation;
use App\Support\KitchenPortionRounding;
use App\Support\MealPlanSlotBasedDayNutrition;
use App\Support\SavoryEggBreakfastMeals;

/**
 * Builds the customer-facing menu with per-meal scaling for savory breakfasts and mains,
 * and standard fixed portions for side salads, desserts, and soup.
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

        $plan = self::planWithBreakfastFloorRebalance($profile, $plan, $options);

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

            if ($slot === 'breakfast' && ChiaDessertMeals::isChiaDessert($meal)) {
                continue;
            }

            if (
                ChiaDessertMeals::isChiaDessert($meal)
                && $meal->name !== ChiaDessertMeals::resolveMealNameForProfile((string) $meal->name, $profile)
            ) {
                continue;
            }

            if (
                $slot === 'breakfast'
                && SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal)
                && $meal->name !== SavoryEggBreakfastMeals::resolveMealNameForProfile((string) $meal->name, $profile)
            ) {
                continue;
            }

            $behavior = UserPlanCalculator::slotBehavior($slot);

            if ($behavior === 'fixed_portion') {
                $fixedPortionMeals[] = self::serializeStandardPortionMeal($meal, $slot, $profile);

                continue;
            }

            if ($behavior === 'scalable') {
                $scalableMeals[] = self::serializeScaledMeal($meal, $slot, $plan);
            }
        }

        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';
        $scheduleOptions = array_filter([
            'craft_key' => $craftKey !== '' ? $craftKey : null,
            'include_soup' => ($options['include_soup'] ?? false) ? true : null,
            'soup_calories' => isset($options['soup_calories']) ? (float) $options['soup_calories'] : null,
            'side_salad_calories' => isset($options['side_salad_calories']) ? (float) $options['side_salad_calories'] : null,
            'dessert_calories' => isset($options['dessert_calories']) ? (float) $options['dessert_calories'] : null,
            'plan_tier' => isset($options['plan_tier']) ? (float) $options['plan_tier'] : null,
            'selected_fixed_slots' => isset($options['selected_fixed_slots']) ? $options['selected_fixed_slots'] : null,
            'day_of_week' => isset($options['day_of_week']) ? (int) $options['day_of_week'] : null,
            'selected_main_meal_ids' => isset($options['selected_main_meal_ids']) ? $options['selected_main_meal_ids'] : null,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $macroWarningsByDay = [];
        $scheduledFullCraft = ProductionWeeklyMenuSchedule::scheduledFullCraftByWeekday(
            $profile,
            null,
            $scheduleOptions,
            $macroWarningsByDay,
        );

        $requestedDay = isset($options['day_of_week']) ? (int) $options['day_of_week'] : 0;
        $plan['macro_warnings'] = ($requestedDay >= 1 && $requestedDay <= 7)
            ? ($macroWarningsByDay[$requestedDay] ?? [])
            : [];

        return [
            'plan' => $plan,
            'fixed_portion_meals' => $fixedPortionMeals,
            'optional_add_on_meals' => $optionalAddOnMeals,
            'scalable_meals' => $scalableMeals,
            'fixed_meals' => $fixedPortionMeals,
            'scheduled_soups_by_weekday' => ProductionWeeklyMenuSchedule::scheduledSoupsByWeekday($profile, null, $scheduleOptions),
            'scheduled_full_craft_by_weekday' => $scheduledFullCraft,
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

        $plan = self::planWithBreakfastFloorRebalance($profile, $plan, $options);
        $behavior = UserPlanCalculator::slotBehavior($slot);

        if ($behavior === 'scalable') {
            return self::serializeScaledMeal($meal, $slot, $plan);
        }

        return self::serializeStandardPortionMeal($meal, $slot, $profile);
    }

    /**
     * Resolve the adapted meal payload for detail views, preferring reconciled weekday schedule data.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    public static function adaptedMealForDetailView(CustomerProfile $profile, Meal $meal, array $options = []): ?array
    {
        $dayOfWeek = isset($options['day_of_week']) ? (int) $options['day_of_week'] : 0;
        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';

        if ($dayOfWeek >= 1 && $dayOfWeek <= 7 && $craftKey !== '') {
            $scheduled = ProductionWeeklyMenuSchedule::adaptedMealFromScheduledDay(
                $profile,
                (int) $meal->id,
                $options,
            );

            if ($scheduled !== null) {
                return $scheduled;
            }
        }

        return self::adaptMealForProfile($profile, $meal, $options);
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

        $plan = self::planWithBreakfastFloorRebalance($profile, $plan, $options);

        $adapted = [];

        foreach ($meals as $meal) {
            $meal->loadMissing('ingredients');
            $adapted[] = self::serializeScaledMeal($meal, 'main', $plan);
        }

        return self::balanceMainMealProtein($adapted, $plan, $meals);
    }

    /**
     * When tier egg counts and side minimums push breakfast above its slot target, trim main targets
     * so a full craft day still lands on the plan tier.
     *
     * @param  array<string, mixed>  $plan
     * @param  array{
     *     craft_key?: string,
     *     day_of_week?: int,
     * }  $options
     * @return array<string, mixed>
     */
    private static function planWithBreakfastFloorRebalance(CustomerProfile $profile, array $plan, array $options): array
    {
        $day = isset($options['day_of_week']) ? (int) $options['day_of_week'] : 0;

        if ($day < 1 || $day > 7) {
            return $plan;
        }

        $craftKey = (string) ($options['craft_key'] ?? '');

        if (! in_array($craftKey, [CraftCaloriePlanner::CRAFT_FULL, CraftCaloriePlanner::CRAFT_DAY], true)) {
            return $plan;
        }

        $breakfastMeal = ProductionWeeklyMenuSchedule::resolveRotationBreakfastMeal($day, $profile);

        if (! $breakfastMeal instanceof Meal || ! SavoryEggBreakfastMeals::isSavoryEggBreakfast($breakfastMeal)) {
            return $plan;
        }

        $breakfastMeal->loadMissing('ingredients');
        $serialized = self::serializeScaledMeal($breakfastMeal, 'breakfast', $plan);
        $target = (float) ($plan['scalable_slot_targets']['breakfast']['calories'] ?? 0);
        $actual = (float) ($serialized['adapted_nutrition']['calories'] ?? 0);

        if ($target <= 0 || $actual <= $target + 0.5) {
            return $plan;
        }

        $excess = $actual - $target;
        $mainCount = $craftKey === CraftCaloriePlanner::CRAFT_DAY
            ? 1
            : max(1, (int) config('customer_nutrition.scalable_slots.main', 2));
        $currentMain = (float) ($plan['scalable_slot_targets']['main_each']['calories'] ?? 0);
        $newMain = max(0.0, round($currentMain - ($excess / $mainCount), 2));

        $plan['scalable_slot_targets']['main_each']['calories'] = $newMain;
        $plan['scalable_slot_targets']['main_each']['macros'] = UserPlanCalculator::mainEachMacroGrams($newMain, $profile);

        return $plan;
    }

    public static function planWithBreakfastFloorRebalanceForProfile(
        CustomerProfile $profile,
        array $plan,
        array $options = [],
    ): array {
        return self::planWithBreakfastFloorRebalance($profile, $plan, $options);
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

        $proteinTargetEach = (float) ($plan['scalable_slot_targets']['main_each']['macros']['protein_g'] ?? 0);
        $slotTargetCaloriesEach = (float) ($plan['scalable_slot_targets']['main_each']['calories'] ?? 0);

        if ($proteinTargetEach <= 0) {
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

        $hasVeganMain = false;

        foreach ($meals as $meal) {
            if ($meal->isVegan()) {
                $hasVeganMain = true;

                break;
            }
        }

        $balanced = $adaptedMains;

        if (! MacroFirstMainMealScaler::isEnabled()) {
            $balanced = self::boostCompensatorMainsTowardProtein(
                $adaptedMains,
                $meals,
                $plan,
                $compensatorIndexes,
                $proteinTargetEach,
                $slotTargetCaloriesEach,
            );
        } elseif ($hasVeganMain) {
            $balanced = self::boostCompensatorMainsTowardProtein(
                $adaptedMains,
                $meals,
                $plan,
                $compensatorIndexes,
                $proteinTargetEach,
                $slotTargetCaloriesEach,
            );
        }

        if (MacroFirstMainMealScaler::isEnabled() && ! $hasVeganMain) {
            return $balanced;
        }

        $mainCount = count($balanced);
        $proteinTargetTotal = round($proteinTargetEach * $mainCount, 2);
        $currentProteinTotal = 0.0;

        foreach ($balanced as $adapted) {
            $currentProteinTotal += (float) ($adapted['adapted_nutrition']['protein'] ?? 0);
        }

        $shortfall = round($proteinTargetTotal - $currentProteinTotal, 2);

        if ($shortfall <= 0.25) {
            return $balanced;
        }

        return self::distributeMainProteinShortfall(
            $balanced,
            $meals,
            $plan,
            $compensatorIndexes,
            $shortfall,
            $slotTargetCaloriesEach,
        );
    }

    /**
     * Day-level protein reconciliation — allows mains to use remaining day calorie budget.
     *
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @param  array{calories: float, protein_g: float, carbs_g: float, fat_g: float}  $scalableNonMainCalories
     * @return list<array<string, mixed>>
     */
    public static function balanceMainMealProteinForDayDeficit(
        array $adaptedMains,
        array $plan,
        array $meals,
        float $proteinDeficit,
        float $fixedPortionCalories,
        array $scalableNonMainCalories,
    ): array {
        if ($adaptedMains === [] || count($adaptedMains) !== count($meals) || $proteinDeficit <= 0.25) {
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

        $dayTargetCalories = (float) ($plan['craft_day_calories'] ?? $plan['plan_tier'] ?? 0);
        $dayTolerance = UserPlanCalculator::dayCalorieTolerance();
        $maxDayCalories = $dayTargetCalories + $dayTolerance;
        $nonMainCalories = round($fixedPortionCalories + (float) ($scalableNonMainCalories['calories'] ?? 0), 2);
        $currentMainCalories = self::sumAdaptedMainCalories($adaptedMains);
        $maxMainCalories = max(0.0, round($maxDayCalories - $nonMainCalories, 2));
        $remainingCalorieBudget = max(0.0, round($maxMainCalories - $currentMainCalories, 2));

        $compensatingProtein = 0.0;

        foreach ($compensatorIndexes as $index) {
            $compensatingProtein += (float) ($adaptedMains[$index]['adapted_nutrition']['protein'] ?? 0);
        }

        if ($compensatingProtein <= 0) {
            return $adaptedMains;
        }

        $balanced = $adaptedMains;
        $extraCaloriesPerCompensator = $remainingCalorieBudget / count($compensatorIndexes);

        foreach ($compensatorIndexes as $index) {
            $meal = $meals[$index];
            $adapted = $balanced[$index];
            $currentProtein = (float) ($adapted['adapted_nutrition']['protein'] ?? 0);
            $currentCalories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

            if ($currentProtein <= 0 || $currentCalories <= 0) {
                continue;
            }

            $proteinShare = $currentProtein / $compensatingProtein;
            $addedProtein = round($proteinDeficit * $proteinShare, 2);
            $boostMultiplier = ($currentProtein + $addedProtein) / $currentProtein;
            // Day calorie headroom is the only hard ceiling (fixed overshoot already shrank slot targets).
            $maxCalories = $currentCalories + $extraCaloriesPerCompensator;

            if ($boostMultiplier <= 1.0001) {
                continue;
            }

            $balanced[$index] = self::boostMainMealWithProteinMultiplier(
                $meal,
                $plan,
                $adapted,
                $boostMultiplier,
                $maxCalories,
            );
        }

        return $balanced;
    }

    /**
     * Day-level carb recovery — boosts the highest-carb main when protein/fat are on target but calories are short.
     *
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @return list<array<string, mixed>>
     */
    public static function balanceMainMealCarbsForDayDeficit(
        array $adaptedMains,
        array $plan,
        array $meals,
        float $carbDeficit,
        float $calorieDeficit,
    ): array {
        if ($adaptedMains === [] || count($adaptedMains) !== count($meals) || $carbDeficit <= 0.25 || $calorieDeficit <= 0.5) {
            return $adaptedMains;
        }

        $bestIndex = null;
        $bestCarbs = 0.0;

        foreach ($adaptedMains as $index => $adapted) {
            $carbs = (float) ($adapted['adapted_nutrition']['carbs'] ?? 0);

            if ($carbs > $bestCarbs) {
                $bestCarbs = $carbs;
                $bestIndex = $index;
            }
        }

        if ($bestIndex === null || $bestCarbs <= 0) {
            return $adaptedMains;
        }

        $meal = $meals[$bestIndex];
        $adapted = $adaptedMains[$bestIndex];
        $currentCalories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

        if ($currentCalories <= 0) {
            return $adaptedMains;
        }

        $carbMultiplier = ($bestCarbs + $carbDeficit) / $bestCarbs;
        $maxMealCalories = $currentCalories + $calorieDeficit;
        // Only carb roles scale — do not treat this like a whole-meal calorie multiplier.
        $effectiveBoost = $carbMultiplier;

        if ($effectiveBoost <= 1.0001) {
            return $adaptedMains;
        }

        $balanced = $adaptedMains;
        $balanced[$bestIndex] = self::boostMainMealWithCarbMultiplier(
            $meal,
            $plan,
            $adapted,
            $effectiveBoost,
            $maxMealCalories,
        );

        return $balanced;
    }

    /**
     * Trim protein roles on the highest-protein mains until day protein surplus is closed.
     *
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @return list<array<string, mixed>>
     */
    public static function trimMainMealsForProteinSurplus(
        array $adaptedMains,
        array $plan,
        array $meals,
        float $proteinSurplus,
    ): array {
        if ($adaptedMains === [] || count($adaptedMains) !== count($meals) || $proteinSurplus <= 0.25) {
            return $adaptedMains;
        }

        $balanced = $adaptedMains;
        $remainingProteinSurplus = $proteinSurplus;
        $maxPasses = 8;

        for ($pass = 0; $pass < $maxPasses && $remainingProteinSurplus > 0.25; $pass++) {
            $bestProteinIndex = null;
            $bestProtein = 0.0;

            foreach ($balanced as $index => $adapted) {
                $protein = (float) ($adapted['adapted_nutrition']['protein'] ?? 0);

                if ($protein > $bestProtein) {
                    $bestProtein = $protein;
                    $bestProteinIndex = $index;
                }
            }

            if ($bestProteinIndex === null || $bestProtein <= 0) {
                break;
            }

            $proteinReduction = min($remainingProteinSurplus, $bestProtein * 0.35);
            $proteinMultiplier = max(0.0, ($bestProtein - $proteinReduction) / $bestProtein);

            if ($proteinMultiplier >= 0.9999) {
                break;
            }

            $beforeProtein = $bestProtein;
            $balanced[$bestProteinIndex] = self::trimMainMealWithProteinMultiplier(
                $meals[$bestProteinIndex],
                $plan,
                $balanced[$bestProteinIndex],
                $proteinMultiplier,
            );
            $afterProtein = (float) ($balanced[$bestProteinIndex]['adapted_nutrition']['protein'] ?? 0);
            $removed = round($beforeProtein - $afterProtein, 2);

            if ($removed < 0.25) {
                break;
            }

            $remainingProteinSurplus = max(0.0, round($remainingProteinSurplus - $removed, 2));
        }

        return $balanced;
    }

    /**
     * Day-level trim — reduces starchy carbs (then protein when over target) on mains.
     * Cooking fat and vegetables are never trimmed.
     *
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @return list<array<string, mixed>>
     */
    public static function trimMainMealsForDaySurplus(
        array $adaptedMains,
        array $plan,
        array $meals,
        float $carbSurplus,
        float $proteinSurplus,
        float $calorieSurplus,
    ): array {
        if ($adaptedMains === [] || count($adaptedMains) !== count($meals) || $calorieSurplus <= 0.5) {
            return $adaptedMains;
        }

        $tolerance = UserPlanCalculator::dayCalorieTolerance();
        $balanced = $adaptedMains;
        $remainingCalorieSurplus = $calorieSurplus;
        $remainingCarbSurplus = max(0.0, $carbSurplus);
        $remainingProteinSurplus = max(0.0, $proteinSurplus);
        $maxPasses = 8;

        for ($pass = 0; $pass < $maxPasses && $remainingCalorieSurplus > $tolerance; $pass++) {
            $beforePassCalories = self::sumAdaptedMainCalories($balanced);

            if ($remainingCarbSurplus > 0.25) {
                $bestCarbIndex = null;
                $bestCarbs = 0.0;

                foreach ($balanced as $index => $adapted) {
                    $carbs = (float) ($adapted['adapted_nutrition']['carbs'] ?? 0);

                    if ($carbs > $bestCarbs) {
                        $bestCarbs = $carbs;
                        $bestCarbIndex = $index;
                    }
                }

                if ($bestCarbIndex !== null && $bestCarbs > 0) {
                    $carbReduction = min($remainingCarbSurplus, $remainingCalorieSurplus / 4);
                    $carbMultiplier = max(0.0, ($bestCarbs - $carbReduction) / $bestCarbs);

                    if ($carbMultiplier < 0.9999) {
                        $balanced[$bestCarbIndex] = self::trimMainMealWithCarbMultiplier(
                            $meals[$bestCarbIndex],
                            $plan,
                            $balanced[$bestCarbIndex],
                            $carbMultiplier,
                        );
                        $remainingCarbSurplus = max(0.0, round($remainingCarbSurplus - $carbReduction, 2));
                    }
                }
            }

            if ($remainingProteinSurplus > 0.25 && $remainingCalorieSurplus > $tolerance) {
                $bestProteinIndex = null;
                $bestProtein = 0.0;

                foreach ($balanced as $index => $adapted) {
                    $protein = (float) ($adapted['adapted_nutrition']['protein'] ?? 0);

                    if ($protein > $bestProtein) {
                        $bestProtein = $protein;
                        $bestProteinIndex = $index;
                    }
                }

                if ($bestProteinIndex !== null && $bestProtein > 0) {
                    $proteinReduction = min($remainingProteinSurplus, $remainingCalorieSurplus / 4);
                    $proteinMultiplier = max(0.0, ($bestProtein - $proteinReduction) / $bestProtein);

                    if ($proteinMultiplier < 0.9999) {
                        $balanced[$bestProteinIndex] = self::trimMainMealWithProteinMultiplier(
                            $meals[$bestProteinIndex],
                            $plan,
                            $balanced[$bestProteinIndex],
                            $proteinMultiplier,
                        );
                        $remainingProteinSurplus = max(0.0, round($remainingProteinSurplus - $proteinReduction, 2));
                    }
                }
            }

            if ($remainingCalorieSurplus > $tolerance) {
                $bestCalorieIndex = null;
                $bestCalories = 0.0;

                foreach ($balanced as $index => $adapted) {
                    $calories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

                    if ($calories > $bestCalories) {
                        $bestCalories = $calories;
                        $bestCalorieIndex = $index;
                    }
                }

                if ($bestCalorieIndex !== null && $bestCalories > 0) {
                    $targetCalories = max(0.0, $bestCalories - ($remainingCalorieSurplus - $tolerance));

                    if ($targetCalories < $bestCalories - 0.5) {
                        // Day calorie close uses starch only; protein surplus is handled above
                        // (bounded) so we never wipe plate protein to chase kcal.
                        $balanced[$bestCalorieIndex] = self::trimMainMealToCalorieTarget(
                            $meals[$bestCalorieIndex],
                            $plan,
                            $balanced[$bestCalorieIndex],
                            $targetCalories,
                            allowProteinTrim: false,
                        );
                    }
                }
            }

            $afterPassCalories = self::sumAdaptedMainCalories($balanced);
            $removed = round($beforePassCalories - $afterPassCalories, 2);

            if ($removed < 0.5) {
                break;
            }

            $remainingCalorieSurplus = max(0.0, round($remainingCalorieSurplus - $removed, 2));
        }

        return $balanced;
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @param  list<int>  $compensatorIndexes
     * @return list<array<string, mixed>>
     */
    private static function boostCompensatorMainsTowardProtein(
        array $adaptedMains,
        array $meals,
        array $plan,
        array $compensatorIndexes,
        float $proteinTargetEach,
        float $slotTargetCaloriesEach,
    ): array {
        $balanced = $adaptedMains;

        foreach ($compensatorIndexes as $index) {
            $meal = $meals[$index];
            $adapted = $balanced[$index];
            $currentProtein = (float) ($adapted['adapted_nutrition']['protein'] ?? 0);
            $currentCalories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

            if ($currentProtein <= 0 || $currentCalories <= 0 || $currentProtein >= $proteinTargetEach - 0.25) {
                continue;
            }

            $boostMultiplier = $proteinTargetEach / $currentProtein;

            if ($boostMultiplier <= 1.0001) {
                continue;
            }

            $maxCalories = $slotTargetCaloriesEach > 0
                ? $slotTargetCaloriesEach
                : $currentCalories * $boostMultiplier;

            $balanced[$index] = self::boostMainMealWithProteinMultiplier(
                $meal,
                $plan,
                $adapted,
                $boostMultiplier,
                $maxCalories,
            );
        }

        return $balanced;
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedMains
     * @param  list<Meal>  $meals
     * @param  list<int>  $compensatorIndexes
     * @return list<array<string, mixed>>
     */
    private static function distributeMainProteinShortfall(
        array $adaptedMains,
        array $meals,
        array $plan,
        array $compensatorIndexes,
        float $shortfall,
        float $slotTargetCaloriesEach,
    ): array {
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
            $currentCalories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

            if ($currentCalories <= 0 || $boostMultiplier <= 1.0001) {
                continue;
            }

            $maxCalories = $slotTargetCaloriesEach > 0
                ? $slotTargetCaloriesEach
                : $currentCalories * $boostMultiplier;

            $balanced[$index] = self::boostMainMealWithProteinMultiplier(
                $meal,
                $plan,
                $adapted,
                $boostMultiplier,
                $maxCalories,
            );
        }

        return $balanced;
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<int, float>
     */
    private static function adaptedGramsFromSerializedMain(array $adapted, Meal $meal): array
    {
        $grams = [];

        foreach ($adapted['ingredients'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ingredientId = (int) ($row['id'] ?? 0);

            if ($ingredientId <= 0) {
                continue;
            }

            $grams[$ingredientId] = (float) ($row['adapted_amount_grams'] ?? 0);
        }

        if ($grams !== []) {
            return $grams;
        }

        return self::baselineGramsByIngredientId($meal);
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<string, mixed>
     */
    private static function boostMainMealWithProteinMultiplier(
        Meal $meal,
        array $plan,
        array $adapted,
        float $effectiveBoost,
        float $maxCalories,
    ): array {
        if (MacroFirstMainMealScaler::isEnabled()) {
            $meal->loadMissing('ingredients');
            $grams = self::adaptedGramsFromSerializedMain($adapted, $meal);
            $grams = MacroFirstMainMealScaler::boostProteinRoleGrams($meal, $grams, $effectiveBoost);
            // Keep the protein boost; free starch (day-surplus floor) so protein can land under maxCalories.
            $grams = MacroFirstMainMealScaler::trimStarchRolesToCalorieTarget(
                $meal,
                $grams,
                $maxCalories,
                daySurplusFloor: true,
            );

            return self::serializeScaledMealFromGrams($meal, 'main', $plan, $grams, proteinBalanced: true);
        }

        $currentScale = (float) ($adapted['scaling_multiplier'] ?? 1.0);

        return self::serializeScaledMeal(
            $meal,
            'main',
            $plan,
            round($currentScale * $effectiveBoost, 4),
            proteinBalanced: true,
        );
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<string, mixed>
     */
    private static function boostMainMealWithCarbMultiplier(
        Meal $meal,
        array $plan,
        array $adapted,
        float $effectiveBoost,
        float $maxCalories,
    ): array {
        if (MacroFirstMainMealScaler::isEnabled()) {
            $meal->loadMissing('ingredients');
            $grams = self::adaptedGramsFromSerializedMain($adapted, $meal);
            $grams = MacroFirstMainMealScaler::boostCarbRoleGrams($meal, $grams, $effectiveBoost);
            // Cap with starch-only trim so protein already on the plate is preserved.
            $grams = MacroFirstMainMealScaler::trimStarchRolesToCalorieTarget(
                $meal,
                $grams,
                $maxCalories,
                daySurplusFloor: true,
            );
            $grams = MacroFirstMainMealScaler::syncHerbSpiceToDishScale($meal, $grams);

            return self::serializeScaledMealFromGrams($meal, 'main', $plan, $grams, proteinBalanced: false);
        }

        $currentScale = (float) ($adapted['scaling_multiplier'] ?? 1.0);

        return self::serializeScaledMeal(
            $meal,
            'main',
            $plan,
            round($currentScale * $effectiveBoost, 4),
            proteinBalanced: false,
        );
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<string, mixed>
     */
    private static function trimMainMealWithCarbMultiplier(
        Meal $meal,
        array $plan,
        array $adapted,
        float $carbMultiplier,
    ): array {
        if (MacroFirstMainMealScaler::isEnabled()) {
            $meal->loadMissing('ingredients');
            $grams = self::adaptedGramsFromSerializedMain($adapted, $meal);
            $grams = MacroFirstMainMealScaler::trimCarbRoleGrams($meal, $grams, $carbMultiplier);

            return self::serializeScaledMealFromGrams($meal, 'main', $plan, $grams, proteinBalanced: false);
        }

        $currentScale = (float) ($adapted['scaling_multiplier'] ?? 1.0);

        return self::serializeScaledMeal(
            $meal,
            'main',
            $plan,
            round($currentScale * $carbMultiplier, 4),
            proteinBalanced: false,
        );
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<string, mixed>
     */
    private static function trimMainMealWithProteinMultiplier(
        Meal $meal,
        array $plan,
        array $adapted,
        float $proteinMultiplier,
    ): array {
        if (MacroFirstMainMealScaler::isEnabled()) {
            $meal->loadMissing('ingredients');
            $grams = self::adaptedGramsFromSerializedMain($adapted, $meal);
            $grams = MacroFirstMainMealScaler::trimProteinRoleGrams($meal, $grams, $proteinMultiplier);

            return self::serializeScaledMealFromGrams($meal, 'main', $plan, $grams, proteinBalanced: false);
        }

        $currentScale = (float) ($adapted['scaling_multiplier'] ?? 1.0);

        return self::serializeScaledMeal(
            $meal,
            'main',
            $plan,
            round($currentScale * $proteinMultiplier, 4),
            proteinBalanced: false,
        );
    }

    /**
     * @param  array<string, mixed>  $adapted
     * @return array<string, mixed>
     */
    private static function trimMainMealToCalorieTarget(
        Meal $meal,
        array $plan,
        array $adapted,
        float $targetCalories,
        bool $allowProteinTrim = true,
    ): array {
        if (MacroFirstMainMealScaler::isEnabled()) {
            $meal->loadMissing('ingredients');
            $grams = self::adaptedGramsFromSerializedMain($adapted, $meal);
            $grams = $allowProteinTrim
                ? MacroFirstMainMealScaler::trimProteinAndStarchRolesToCalorieTarget(
                    $meal,
                    $grams,
                    $targetCalories,
                    daySurplusFloor: true,
                )
                : MacroFirstMainMealScaler::trimStarchRolesToCalorieTarget(
                    $meal,
                    $grams,
                    $targetCalories,
                    daySurplusFloor: true,
                );

            return self::serializeScaledMealFromGrams($meal, 'main', $plan, $grams, proteinBalanced: false);
        }

        $currentCalories = (float) ($adapted['adapted_nutrition']['calories'] ?? 0);

        if ($currentCalories <= 0 || $targetCalories >= $currentCalories - 0.5) {
            return $adapted;
        }

        $currentScale = (float) ($adapted['scaling_multiplier'] ?? 1.0);
        $scale = max(0.0, round($currentScale * ($targetCalories / $currentCalories), 4));

        return self::serializeScaledMeal(
            $meal,
            'main',
            $plan,
            $scale,
            proteinBalanced: false,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $adaptedMains
     */
    private static function sumAdaptedMainCalories(array $adaptedMains): float
    {
        $total = 0.0;

        foreach ($adaptedMains as $adapted) {
            $total += (float) ($adapted['adapted_nutrition']['calories'] ?? 0);
        }

        return round($total, 2);
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
     * @return array<string, mixed>
     */
    private static function serializeStandardPortionMeal(
        Meal $meal,
        string $slot,
        CustomerProfile $profile,
        bool $isOptionalAddOn = false,
    ): array {
        if (ChiaDessertMeals::isChiaDessert($meal)) {
            return self::serializeChiaDessertFixedPortionMeal($meal, $slot, $profile, $isOptionalAddOn);
        }

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
     * Chia rotation desserts always ship with a 120g coconut-chia base; toppings keep library grams.
     *
     * @return array<string, mixed>
     */
    private static function serializeChiaDessertFixedPortionMeal(
        Meal $meal,
        string $slot,
        CustomerProfile $profile,
        bool $isOptionalAddOn = false,
    ): array {
        $meal->loadMissing('ingredients');
        $adaptedGramsByIngredientId = self::chiaDessertAdaptedGramsByIngredientId($meal);
        $scaledRows = self::scaledIngredientRowsFromAdaptedGrams($meal, $adaptedGramsByIngredientId);
        $adaptedNutrition = RecipeNutritionCalculator::fromRows($scaledRows);
        $baseline = $meal->nutritionForDisplay();

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
            'baseline_nutrition' => self::normalizeNutritionKeys($baseline),
            'adapted_nutrition' => self::normalizeNutritionKeys($adaptedNutrition),
            'ingredients' => self::serializeScaledIngredientsFromAdaptedGrams($meal, $adaptedGramsByIngredientId),
            'planning_midpoint_calories' => UserPlanCalculator::slotPlanningMidpoint($slot),
            'macro_split' => [
                'protein_percentage' => (float) $profile->protein_percentage,
                'carb_percentage' => (float) $profile->carb_percentage,
                'fat_percentage' => (float) $profile->fat_percentage,
            ],
        ];
    }

    /**
     * @return array<int, float>
     */
    private static function chiaDessertAdaptedGramsByIngredientId(Meal $meal): array
    {
        $adaptedGramsByIngredientId = [];

        foreach ($meal->ingredients as $ingredient) {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $unitRaw = $pivot->unit ?? '';
            $baselineGrams = self::baselineGramsForPivot(
                $ingredient,
                $pivotAmount,
                $unitRaw,
                (float) ($pivot->amount_grams ?? 0),
            );

            if ($baselineGrams <= 0) {
                continue;
            }

            $canonicalBaseGrams = BalancedChiaDessertRecipeRefiner::canonicalBaseGramsForIngredientName($ingredient->name);

            if ($canonicalBaseGrams !== null) {
                $baselineGrams = $canonicalBaseGrams;
            }

            $adaptedGramsByIngredientId[$ingredient->id] = round($baselineGrams, 2);
        }

        return $adaptedGramsByIngredientId;
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
        $planTier = (float) ($plan['plan_tier'] ?? 0);

        if ($slot === 'breakfast' && SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal)) {
            $adaptedGramsByIngredientId = self::adaptedGramsForSavoryEggBreakfast($meal, $planTier);

            if ($targetCalories > 0) {
                $adaptedGramsByIngredientId = self::finalizeBreakfastGrams(
                    $meal,
                    $adaptedGramsByIngredientId,
                    $targetCalories,
                    $planTier,
                );
            }
        } elseif ($slot === 'main' && $overrideMultiplier !== null) {
            $adaptedGramsByIngredientId = self::adaptedGramsFromMultiplier($meal, $overrideMultiplier);
        } elseif ($slot === 'main' && MacroFirstMainMealScaler::isEnabled()) {
            $macroFirst = MacroFirstMainMealScaler::adapt($meal, $plan);
            $adaptedGramsByIngredientId = $macroFirst['grams'];
            $proteinBalanced = $macroFirst['protein_balanced'] || $proteinBalanced;
        } else {
            $adaptedGramsByIngredientId = self::adaptedGramsFromMultiplier($meal, $multiplier);

            if ($slot === 'breakfast' && $targetCalories > 0) {
                $adaptedGramsByIngredientId = self::normalizeAdaptedGramsToCalorieTarget(
                    $meal,
                    $adaptedGramsByIngredientId,
                    $targetCalories,
                    $planTier,
                );
            }
        }

        return self::serializeScaledMealFromGrams(
            $meal,
            $slot,
            $plan,
            $adaptedGramsByIngredientId,
            $proteinBalanced,
            $multiplier,
        );
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return array<string, mixed>
     */
    public static function serializeScaledMealFromGrams(
        Meal $meal,
        string $slot,
        array $plan,
        array $adaptedGramsByIngredientId,
        bool $proteinBalanced = false,
        ?float $fallbackMultiplier = null,
    ): array {
        $baseline = $meal->nutritionForDisplay();
        $scaledRows = self::scaledIngredientRowsFromAdaptedGrams($meal, $adaptedGramsByIngredientId);
        $adaptedNutrition = RecipeNutritionCalculator::fromRows($scaledRows);
        $baselineCalories = (float) ($baseline['calories'] ?? 0);
        $adaptedCalories = (float) ($adaptedNutrition['calories'] ?? 0);
        $overallMultiplier = $baselineCalories > 0
            ? round($adaptedCalories / $baselineCalories, 4)
            : ($fallbackMultiplier ?? 1.0);

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

        $trimmed = self::trimBreakfastFlexibleGramsToTarget($meal, $withMinimums, $targetCalories, $planTier);

        return KitchenPortionRounding::snapAllGramsForMeal($meal, $trimmed);
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
            if (
                SavoryEggBreakfastMeals::isSavoryEggBreakfast($meal)
                && EggIngredientPresentation::isEggIngredient($ingredient)
            ) {
                $fixedIngredientIds[] = $ingredient->id;

                continue;
            }

            if ($planTier > 0 && SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($ingredient, $planTier, $meal->name) !== null) {
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

            $minimum = SavoryEggBreakfastMeals::minimumSideGramsForPlanTier($ingredient, $planTier, $meal->name);

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
    private static function adaptedGramsForSavoryEggBreakfast(Meal $meal, float $planTier): array
    {
        $sideMultiplier = SavoryEggBreakfastMeals::sidePortionMultiplierForMeal($meal, $planTier);
        $targetEggGrams = SavoryEggBreakfastMeals::eggGramsForPlanTier($planTier);
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

            if (EggIngredientPresentation::isEggIngredient($ingredient)) {
                $gramsByIngredientId[$ingredient->id] = $targetEggGrams;

                continue;
            }

            $gramsByIngredientId[$ingredient->id] = SavoryEggBreakfastMeals::adaptedSideGrams(
                $ingredient,
                $baselineGrams,
                $sideMultiplier,
                $planTier,
                $meal->name,
            );
        }

        return $gramsByIngredientId;
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
            $adaptedGrams = KitchenPortionRounding::snapGramsForIngredient(
                $ingredient,
                (float) ($adaptedGramsByIngredientId[$ingredient->id] ?? $baselineGrams),
            );
            $adaptedGrams = round($adaptedGrams, 2);

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

    /**
     * @return array<int, float>
     */
    public static function baselineGramsByIngredientId(Meal $meal): array
    {
        $meal->loadMissing('ingredients');
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

            $canonicalBaseGrams = BalancedChiaDessertRecipeRefiner::canonicalBaseGramsForIngredientName($ingredient->name);

            if ($canonicalBaseGrams !== null) {
                $baselineGrams = $canonicalBaseGrams;
            }

            $gramsByIngredientId[$ingredient->id] = round($baselineGrams, 4);
        }

        return $gramsByIngredientId;
    }

    /**
     * @param  array<int, float>  $adaptedGramsByIngredientId
     * @return list<array<string, mixed>>
     */
    public static function scaledIngredientRowsFromAdaptedGramsPublic(Meal $meal, array $adaptedGramsByIngredientId): array
    {
        return self::scaledIngredientRowsFromAdaptedGrams($meal, $adaptedGramsByIngredientId);
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
