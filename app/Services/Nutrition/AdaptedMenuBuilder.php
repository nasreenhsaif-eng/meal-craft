<?php

namespace App\Services\Nutrition;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\CustomerProfile;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeIngredientUnitConverter;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealPlanSlotBasedDayNutrition;

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
     *     craft_key?: string
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

            if ($behavior === 'optional_add_on') {
                $optionalAddOnMeals[] = self::serializeStandardPortionMeal($meal, $slot, $profile, isOptionalAddOn: true);

                continue;
            }

            if ($behavior === 'scalable') {
                $scalableMeals[] = self::serializeScaledMeal($meal, $slot, $plan);
            }
        }

        $craftKey = isset($options['craft_key']) ? (string) $options['craft_key'] : '';
        $scheduleOptions = $craftKey !== '' ? ['craft_key' => $craftKey] : [];

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
     *     craft_key?: string
     * }  $options
     * @return array<string, mixed>|null
     */
    public static function adaptMealForProfile(CustomerProfile $profile, Meal $meal, array $options = []): ?array
    {
        $meal->loadMissing('ingredients');
        $slot = self::resolveSlot($meal);

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
            return self::serializeScaledMeal($meal, $slot, $plan);
        }

        return self::serializeStandardPortionMeal(
            $meal,
            $slot,
            $profile,
            isOptionalAddOn: $behavior === 'optional_add_on',
        );
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
    private static function serializeScaledMeal(Meal $meal, string $slot, array $plan): array
    {
        $multiplier = self::mealScalingMultiplier($meal, $slot, $plan);
        $baseline = $meal->nutritionForDisplay();
        $scaledRows = self::scaledIngredientRows($meal, $multiplier);
        $adaptedNutrition = RecipeNutritionCalculator::fromRows($scaledRows);

        return [
            'id' => $meal->id,
            'name' => $meal->name,
            'slot' => $slot,
            'portion_behavior' => UserPlanCalculator::slotBehavior($slot),
            'is_scaled' => $multiplier !== 1.0,
            'scaling_multiplier' => $multiplier,
            'counts_toward_core_tier' => true,
            'image_url' => $meal->imageUrl(),
            'instructions' => $meal->instructions,
            'short_description' => $meal->short_description,
            'baseline_nutrition' => self::normalizeNutritionKeys($baseline),
            'adapted_nutrition' => self::normalizeNutritionKeys($adaptedNutrition),
            'ingredients' => self::serializeScaledIngredients($meal, $multiplier),
            'slot_target' => $slot === 'breakfast'
                ? $plan['scalable_slot_targets']['breakfast']
                : $plan['scalable_slot_targets']['main_each'],
        ];
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
