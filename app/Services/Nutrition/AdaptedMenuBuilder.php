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
 * Builds the customer-facing menu: scales Breakfast + Main meals by the plan multiplier,
 * and returns fixed Soup / Side Salad / Dessert at 150 kcal each (unscaled).
 */
final class AdaptedMenuBuilder
{
    /**
     * @return array{
     *     plan: array<string, mixed>,
     *     fixed_meals: list<array<string, mixed>>,
     *     scalable_meals: list<array<string, mixed>>
     * }
     */
    public static function build(CustomerProfile $profile): array
    {
        $plan = UserPlanCalculator::calculateUserPlan($profile);
        $multiplier = (float) $plan['scaling_multiplier'];

        $meals = Meal::queryForMealLibrary()
            ->with('ingredients')
            ->orderBy('library_sort_order')
            ->orderBy('name')
            ->get();

        $fixedMeals = [];
        $scalableMeals = [];

        foreach ($meals as $meal) {
            $slot = self::resolveSlot($meal);

            if ($slot === null) {
                continue;
            }

            if (self::isFixedSlot($slot)) {
                $fixedMeals[] = self::serializeFixedMeal($meal, $slot, $profile);

                continue;
            }

            if (self::isScalableSlot($slot)) {
                $scalableMeals[] = self::serializeScaledMeal($meal, $slot, $multiplier, $plan);
            }
        }

        return [
            'plan' => $plan,
            'fixed_meals' => $fixedMeals,
            'scalable_meals' => $scalableMeals,
        ];
    }

    private static function isFixedSlot(string $slot): bool
    {
        return in_array($slot, ['soup', 'side_salad', 'dessert'], true);
    }

    private static function isScalableSlot(string $slot): bool
    {
        return in_array($slot, ['breakfast', 'main'], true);
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
    private static function serializeFixedMeal(Meal $meal, string $slot, CustomerProfile $profile): array
    {
        $targetCalories = UserPlanCalculator::fixedCaloriesPerMeal();
        $baseline = $meal->nutritionForDisplay();
        $ingredients = self::serializeIngredients($meal, 1.0);

        return [
            'id' => $meal->id,
            'name' => $meal->name,
            'slot' => $slot,
            'is_scaled' => false,
            'image_url' => $meal->imageUrl(),
            'instructions' => $meal->instructions,
            'short_description' => $meal->short_description,
            'baseline_nutrition' => self::normalizeNutritionKeys($baseline),
            'adapted_nutrition' => self::normalizeNutritionKeys(
                MealPlanSlotBasedDayNutrition::placeholderNutrition($targetCalories)
            ),
            'ingredients' => $ingredients,
            'fixed_calorie_budget' => $targetCalories,
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
    private static function serializeScaledMeal(Meal $meal, string $slot, float $multiplier, array $plan): array
    {
        $baseline = $meal->nutritionForDisplay();
        $scaledRows = self::scaledIngredientRows($meal, $multiplier);
        $adaptedNutrition = RecipeNutritionCalculator::fromRows($scaledRows);

        return [
            'id' => $meal->id,
            'name' => $meal->name,
            'slot' => $slot,
            'is_scaled' => true,
            'scaling_multiplier' => $multiplier,
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
    private static function serializeIngredients(Meal $meal, float $multiplier): array
    {
        return self::serializeScaledIngredients($meal, $multiplier);
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
