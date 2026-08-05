<?php

namespace App\Services\Nutrition;

use App\Enums\DietProtocol;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\NutrientDenseWeeklyRotationSchedule;
use App\Support\ChiaDessertMeals;
use App\Support\NutrientDenseDessertMeals;
use App\Support\SavoryEggBreakfastMeals;

/**
 * Simulates a Full Craft day (1 breakfast, 2 mains, pick-2 fixed) for micronutrient coverage analysis.
 */
final class ReferenceFullCraftDaySimulator
{
    /**
     * @param  list<string>  $selectedFixedSlots  e.g. ['side_salad', 'dessert']
     * @return array{
     *     day_number: int,
     *     plan_tier: float,
     *     selected_fixed_slots: list<string>,
     *     adapted_meals: list<array<string, mixed>>,
     *     day_nutrition: array<string, float>,
     *     day_calories: float,
     * }
     */
    public static function simulate(
        CustomerProfile $profile,
        int $dayNumber,
        float $planTier,
        array $selectedFixedSlots,
        bool $withLiverSwap = false,
    ): array {
        $dayNumber = max(1, min(7, $dayNumber));
        $selectedFixedSlots = array_values(array_intersect(
            ['side_salad', 'dessert', 'soup'],
            $selectedFixedSlots,
        ));

        if (count($selectedFixedSlots) !== 2) {
            $selectedFixedSlots = ['side_salad', 'dessert'];
        }

        $mealsByRole = self::resolveMealsForDay($profile, $dayNumber, $selectedFixedSlots, $withLiverSwap);
        $fixedCalories = self::resolveFixedCalories($mealsByRole, $selectedFixedSlots);

        $buildOptions = [
            'plan_tier' => $planTier,
            'craft_key' => 'full',
            'selected_fixed_slots' => $selectedFixedSlots,
            'snap_to_tier' => false,
            'day_of_week' => $dayNumber,
        ];

        if (isset($fixedCalories['soup'])) {
            $buildOptions['soup_calories'] = $fixedCalories['soup'];
        }

        if (isset($fixedCalories['side_salad'])) {
            $buildOptions['side_salad_calories'] = $fixedCalories['side_salad'];
        }

        if (isset($fixedCalories['dessert'])) {
            $buildOptions['dessert_calories'] = $fixedCalories['dessert'];
        }

        $adaptedMeals = [];

        $breakfast = $mealsByRole['breakfast'];

        if ($breakfast instanceof Meal) {
            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $breakfast, $buildOptions);

            if (is_array($adapted)) {
                $adaptedMeals[] = $adapted;
            }
        }

        /** @var list<Meal> $mainMeals */
        $mainMeals = $mealsByRole['mains'];

        if ($mainMeals !== []) {
            $adaptedMeals = array_merge(
                $adaptedMeals,
                AdaptedMenuBuilder::adaptMainMealsForProfile($profile, $mainMeals, $buildOptions),
            );
        }

        $dayMenu = [
            'breakfasts' => array_values(array_filter($adaptedMeals, static fn (array $meal): bool => ($meal['slot'] ?? '') === 'breakfast')),
            'meals' => array_values(array_filter($adaptedMeals, static fn (array $meal): bool => ($meal['slot'] ?? '') === 'main')),
            'sideSalads' => [],
            'desserts' => [],
            'soup' => [],
        ];

        foreach (['side_salad', 'dessert', 'soup'] as $slot) {
            if (! in_array($slot, $selectedFixedSlots, true)) {
                continue;
            }

            $meal = $mealsByRole[$slot] ?? null;

            if (! $meal instanceof Meal) {
                continue;
            }

            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, $buildOptions);

            if (! is_array($adapted)) {
                continue;
            }

            $bucket = match ($slot) {
                'side_salad' => 'sideSalads',
                'dessert' => 'desserts',
                default => 'soup',
            };
            $dayMenu[$bucket][] = $adapted;
            $adaptedMeals[] = $adapted;
        }

        $reconciled = DayMacroReconciliation::reconcile($profile, $dayMenu, $mainMeals, $buildOptions);
        $adaptedMeals = [
            ...$dayMenu['breakfasts'],
            ...$reconciled['dayMenu']['meals'],
            ...$reconciled['dayMenu']['sideSalads'],
            ...$reconciled['dayMenu']['desserts'],
            ...$reconciled['dayMenu']['soup'],
        ];

        $dayNutrition = DayMicronutrientCoverageAnalyzer::aggregateAdaptedNutrition($adaptedMeals);

        return [
            'day_number' => $dayNumber,
            'plan_tier' => $planTier,
            'selected_fixed_slots' => $selectedFixedSlots,
            'adapted_meals' => $adaptedMeals,
            'day_nutrition' => $dayNutrition,
            'day_calories' => (float) ($dayNutrition['calories'] ?? 0),
        ];
    }

    /**
     * @param  list<string>  $selectedFixedSlots
     * @return array{
     *     breakfast: Meal|null,
     *     mains: list<Meal>,
     *     side_salad: Meal|null,
     *     dessert: Meal|null,
     *     soup: Meal|null,
     * }
     */
    private static function resolveMealsForDay(
        CustomerProfile $profile,
        int $dayNumber,
        array $selectedFixedSlots,
        bool $withLiverSwap,
    ): array {
        $breakfastName = SavoryEggBreakfastMeals::scheduledBreakfastNameForDay($dayNumber, $profile);

        $dessertMeal = null;

        if (in_array('dessert', $selectedFixedSlots, true)) {
            $schedule = self::rotationScheduleForProfile($profile);
            $scheduledDessert = self::findMealByName(
                $schedule::mealNameForDay($dayNumber, MealPlanSlotType::Dessert, 1),
            );

            if ($scheduledDessert instanceof Meal) {
                $dessertMeal = self::isNutrientDenseProfile($profile)
                    ? NutrientDenseDessertMeals::resolveMealForProfile($scheduledDessert, $profile)
                    : ChiaDessertMeals::resolveMealForProfile($scheduledDessert, $profile);
            }
        }

        $secondMainSlot = $withLiverSwap || self::isNutrientDenseProfile($profile) ? 5 : 3;

        $schedule = self::rotationScheduleForProfile($profile);

        return [
            'breakfast' => self::findMealByName($breakfastName),
            'mains' => array_values(array_filter([
                self::findMealByName(
                    $schedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, 1),
                ),
                self::findMealByName(
                    $schedule::mealNameForDay($dayNumber, MealPlanSlotType::Main, $secondMainSlot),
                ),
            ])),
            'side_salad' => in_array('side_salad', $selectedFixedSlots, true)
                ? self::findMealByName(
                    $schedule::mealNameForDay($dayNumber, MealPlanSlotType::Salad, 1),
                )
                : null,
            'dessert' => $dessertMeal,
            'soup' => in_array('soup', $selectedFixedSlots, true)
                ? self::findMealByName(
                    $schedule::mealNameForDay($dayNumber, MealPlanSlotType::Soup, 1),
                )
                : null,
        ];
    }

    private static function isNutrientDenseProfile(CustomerProfile $profile): bool
    {
        return DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense;
    }

    /**
     * @return class-string
     */
    private static function rotationScheduleForProfile(CustomerProfile $profile): string
    {
        return self::isNutrientDenseProfile($profile)
            ? NutrientDenseWeeklyRotationSchedule::class
            : BalancedWeeklyRotationSchedule::class;
    }

    /**
     * @param  array<string, Meal|null>  $mealsByRole
     * @param  list<string>  $selectedFixedSlots
     * @return array<string, float>
     */
    private static function resolveFixedCalories(array $mealsByRole, array $selectedFixedSlots): array
    {
        $out = [];

        foreach ($selectedFixedSlots as $slot) {
            $meal = $mealsByRole[$slot] ?? null;

            if (! $meal instanceof Meal) {
                continue;
            }

            $calories = (float) $meal->nutritionForDisplay()['calories'];

            if ($calories > 0) {
                $out[$slot] = round($calories, 2);
            }
        }

        return $out;
    }

    private static function findMealByName(string $name): ?Meal
    {
        return Meal::queryForMealLibrary()
            ->where('name', $name)
            ->with('ingredients')
            ->first();
    }
}
