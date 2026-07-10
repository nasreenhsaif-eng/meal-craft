<?php

namespace App\Services\Nutrition;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;
use App\Support\KitchenPortionRounding;
use App\Support\MealScalingRole;
use App\Support\StandardMeatPortion;

/**
 * Scales main-meal ingredients to slot macro targets before calorie trim.
 *
 * Vegan mains keep library protein portions; paired non-vegan mains absorb the protein gap.
 */
final class MacroFirstMainMealScaler
{
    /**
     * @param  array<string, mixed>  $plan
     * @return array{
     *     grams: array<int, float>,
     *     protein_balanced: bool,
     *     protein_multiplier: float,
     *     carb_multiplier: float,
     * }
     */
    public static function adapt(Meal $meal, array $plan): array
    {
        $meal->loadMissing('ingredients');

        $config = self::config();
        $slotMacros = is_array($plan['scalable_slot_targets']['main_each']['macros'] ?? null)
            ? $plan['scalable_slot_targets']['main_each']['macros']
            : [];
        $targetProtein = (float) ($slotMacros['protein_g'] ?? 0);
        $targetCarbs = (float) ($slotMacros['carbs_g'] ?? 0);
        $targetCalories = (float) ($plan['scalable_slot_targets']['main_each']['calories'] ?? 0);

        $baselineGrams = AdaptedMenuBuilder::baselineGramsByIngredientId($meal);
        $grams = $baselineGrams;

        // Vegan mains keep library protein portions; non-vegan mains cover the day protein gap.
        $proteinMultiplier = $meal->isVegan()
            ? 1.0
            : self::macroMultiplierForRole(
                $meal,
                $baselineGrams,
                MealScalingRoleEnum::Protein,
                'protein',
                $targetProtein,
            );
        $carbMultiplier = self::macroMultiplierForRole(
            $meal,
            $baselineGrams,
            MealScalingRoleEnum::Carb,
            'carbs',
            $targetCarbs,
            (float) ($config['carb_baseline_floor_ratio'] ?? 0.6),
        );

        $proteinBalanced = ! $meal->isVegan()
            && (abs($proteinMultiplier - 1.0) > 0.0001 || abs($carbMultiplier - 1.0) > 0.0001);

        foreach ($meal->ingredients as $ingredient) {
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);
            $baseline = (float) ($baselineGrams[$ingredient->id] ?? 0);

            if ($baseline <= 0) {
                continue;
            }

            $grams[$ingredient->id] = match ($role) {
                MealScalingRoleEnum::Protein => self::scaledProteinGrams(
                    $ingredient,
                    $meal,
                    $baseline,
                    $proteinMultiplier,
                    (float) ($config['max_primary_meat_grams'] ?? 200.0),
                ),
                MealScalingRoleEnum::Carb => self::scaledCarbGrams($baseline, $carbMultiplier),
                MealScalingRoleEnum::HerbSpice => round(
                    $baseline * self::flavorMultiplier($proteinMultiplier, $carbMultiplier, $config),
                    4,
                ),
                MealScalingRoleEnum::Vegetable => round($baseline, 4),
                default => round($baseline, 4),
            };
        }

        $grams = self::trimToCalorieTarget($meal, $grams, $targetCalories, $baselineGrams);
        $grams = self::recoverCarbTargetAfterTrim($meal, $grams, $targetCarbs, $targetCalories, $baselineGrams);
        $grams = KitchenPortionRounding::snapFatRoleGramsForMeal($meal, $grams);

        return [
            'grams' => $grams,
            'protein_balanced' => $proteinBalanced,
            'protein_multiplier' => round($proteinMultiplier, 4),
            'carb_multiplier' => round($carbMultiplier, 4),
        ];
    }

    public static function isEnabled(): bool
    {
        return (bool) (self::config()['enabled'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('customer_nutrition.macro_first_main_scaling', []);

        return $config;
    }

    /**
     * @param  array<int, float>  $baselineGrams
     */
    private static function macroMultiplierForRole(
        Meal $meal,
        array $baselineGrams,
        MealScalingRoleEnum $role,
        string $macroKey,
        float $targetMacro,
        float $floorRatio = 0.0,
    ): float {
        if ($targetMacro <= 0) {
            return 1.0;
        }

        $baselineMacro = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (MealScalingRole::roleForIngredient($ingredient, $meal) !== $role) {
                continue;
            }

            $grams = (float) ($baselineGrams[$ingredient->id] ?? 0);

            if ($grams <= 0) {
                continue;
            }

            $baselineMacro += self::macroForGrams($ingredient, $grams, $macroKey);
        }

        if ($baselineMacro <= 0) {
            return 1.0;
        }

        $multiplier = $targetMacro / $baselineMacro;

        if ($floorRatio > 0 && $multiplier < $floorRatio) {
            return $floorRatio;
        }

        return max(0.0, $multiplier);
    }

    private static function scaledProteinGrams(
        Ingredient $ingredient,
        Meal $meal,
        float $baseline,
        float $multiplier,
        float $maxPrimaryMeatGrams,
    ): float {
        $scaled = round($baseline * $multiplier, 4);

        if (StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
            $ceiling = max(
                StandardMeatPortion::targetPrimaryBeefGrams($meal->ingredients, $meal->name),
                min($maxPrimaryMeatGrams, $scaled),
            );

            if ($scaled > $ceiling) {
                return round($ceiling, 4);
            }
        }

        if ($multiplier >= 1.0) {
            return max($baseline, $scaled);
        }

        return $scaled;
    }

    private static function scaledCarbGrams(float $baseline, float $multiplier): float
    {
        return round(max(0.0, $baseline * $multiplier), 4);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function flavorMultiplier(float $proteinMultiplier, float $carbMultiplier, array $config): float
    {
        $raw = sqrt(max(0.0, $proteinMultiplier) * max(0.0, $carbMultiplier));
        $min = (float) ($config['herb_flavor_multiplier_min'] ?? 0.5);
        $max = (float) ($config['herb_flavor_multiplier_max'] ?? 2.0);

        return max($min, min($max, $raw));
    }

    /**
     * @param  array<int, float>  $grams
     * @param  array<int, float>  $baselineGrams
     * @return array<int, float>
     */
    private static function trimToCalorieTarget(
        Meal $meal,
        array $grams,
        float $targetCalories,
        array $baselineGrams = [],
    ): array {
        if ($targetCalories <= 0) {
            return $grams;
        }

        $adjusted = $grams;

        foreach ([
            [MealScalingRoleEnum::Fat, MealScalingRoleEnum::Sauce],
            [MealScalingRoleEnum::Carb],
            [MealScalingRoleEnum::Protein],
        ] as $roles) {
            if (self::totalCalories($meal, $adjusted) <= $targetCalories + 0.5) {
                break;
            }

            $adjusted = self::trimRoleCalories($meal, $adjusted, $targetCalories, $roles, $baselineGrams);
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<MealScalingRoleEnum>  $roles
     * @param  array<int, float>  $baselineGrams
     * @return array<int, float>
     */
    private static function trimRoleCalories(
        Meal $meal,
        array $grams,
        float $targetCalories,
        array $roles,
        array $baselineGrams = [],
    ): array {
        $currentCalories = self::totalCalories($meal, $grams);

        if ($currentCalories <= $targetCalories + 0.5) {
            return $grams;
        }

        $fixedCalories = 0.0;
        $trimCalories = 0.0;
        /** @var list<int> $trimIds */
        $trimIds = [];
        /** @var array<int, float> $floors */
        $floors = [];
        $config = self::config();
        $carbFloorRatio = (float) ($config['carb_baseline_floor_ratio'] ?? 0.6);

        foreach ($meal->ingredients as $ingredient) {
            $ingredientGrams = (float) ($grams[$ingredient->id] ?? 0);

            if ($ingredientGrams <= 0) {
                continue;
            }

            $rowCalories = self::macroForGrams($ingredient, $ingredientGrams, 'calories');
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);

            if (in_array($role, $roles, true)) {
                $floorGrams = self::trimFloorGramsForRole(
                    $meal,
                    $ingredient,
                    $role,
                    $baselineGrams,
                    $carbFloorRatio,
                );
                $floorGrams = min($ingredientGrams, $floorGrams);
                $floors[$ingredient->id] = $floorGrams;

                if ($floorGrams >= $ingredientGrams - 0.0001) {
                    $fixedCalories += $rowCalories;

                    continue;
                }

                if ($floorGrams > 0.0) {
                    $floorCalories = self::macroForGrams($ingredient, $floorGrams, 'calories');
                    $fixedCalories += $floorCalories;
                    $trimCalories += max(0.0, $rowCalories - $floorCalories);
                } else {
                    $trimCalories += $rowCalories;
                }

                $trimIds[] = $ingredient->id;
            } else {
                $fixedCalories += $rowCalories;
            }
        }

        if ($trimCalories <= 0 || $trimIds === []) {
            return $grams;
        }

        $trimBudget = max(0.0, $targetCalories - $fixedCalories);
        $adjusted = $grams;

        if ($trimBudget <= 0) {
            foreach ($trimIds as $ingredientId) {
                $adjusted[$ingredientId] = round($floors[$ingredientId] ?? 0.0, 4);
            }

            return $adjusted;
        }

        $ratio = round($trimBudget / $trimCalories, 4);

        foreach ($trimIds as $ingredientId) {
            $current = (float) ($grams[$ingredientId] ?? 0);
            $floor = (float) ($floors[$ingredientId] ?? 0.0);
            $trimmable = max(0.0, $current - $floor);
            $adjusted[$ingredientId] = round($floor + ($trimmable * $ratio), 4);
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $baselineGrams
     */
    private static function trimFloorGramsForRole(
        Meal $meal,
        Ingredient $ingredient,
        MealScalingRoleEnum $role,
        array $baselineGrams,
        float $carbFloorRatio,
    ): float {
        if ($role === MealScalingRoleEnum::Protein) {
            return self::primaryMeatTrimFloorGrams($meal, $ingredient);
        }

        if ($role === MealScalingRoleEnum::Carb && $carbFloorRatio > 0) {
            return round(max(0.0, (float) ($baselineGrams[$ingredient->id] ?? 0) * $carbFloorRatio), 4);
        }

        return 0.0;
    }

    /**
     * Primary meat must not be calorie-trimmed below the standard portion.
     */
    private static function primaryMeatTrimFloorGrams(Meal $meal, Ingredient $ingredient): float
    {
        if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
            return 0.0;
        }

        return StandardMeatPortion::targetPrimaryBeefGrams($meal->ingredients, $meal->name);
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function boostProteinRoleGrams(Meal $meal, array $grams, float $multiplier): array
    {
        if ($multiplier <= 1.0001) {
            return $grams;
        }

        $boosted = $grams;

        foreach ($meal->ingredients as $ingredient) {
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);

            if (! in_array($role, [MealScalingRoleEnum::Protein, MealScalingRoleEnum::HerbSpice], true)) {
                continue;
            }

            $baseline = (float) ($grams[$ingredient->id] ?? 0);

            if ($baseline <= 0) {
                continue;
            }

            $boosted[$ingredient->id] = round($baseline * $multiplier, 4);
        }

        return $boosted;
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function boostCarbRoleGrams(Meal $meal, array $grams, float $multiplier): array
    {
        if ($multiplier <= 1.0001) {
            return $grams;
        }

        $boosted = $grams;

        foreach ($meal->ingredients as $ingredient) {
            if (MealScalingRole::roleForIngredient($ingredient, $meal) !== MealScalingRoleEnum::Carb) {
                continue;
            }

            $baseline = (float) ($grams[$ingredient->id] ?? 0);

            if ($baseline <= 0) {
                continue;
            }

            $boosted[$ingredient->id] = round($baseline * $multiplier, 4);
        }

        return $boosted;
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function trimCarbRoleGrams(Meal $meal, array $grams, float $multiplier): array
    {
        if ($multiplier >= 0.9999) {
            return $grams;
        }

        $multiplier = max(0.0, $multiplier);
        $trimmed = $grams;

        foreach ($meal->ingredients as $ingredient) {
            if (MealScalingRole::roleForIngredient($ingredient, $meal) !== MealScalingRoleEnum::Carb) {
                continue;
            }

            $baseline = (float) ($grams[$ingredient->id] ?? 0);

            if ($baseline <= 0) {
                continue;
            }

            $trimmed[$ingredient->id] = round($baseline * $multiplier, 4);
        }

        return $trimmed;
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function trimFatRoleGrams(Meal $meal, array $grams, float $multiplier): array
    {
        if ($multiplier >= 0.9999) {
            return $grams;
        }

        $multiplier = max(0.0, $multiplier);
        $trimmed = $grams;

        foreach ($meal->ingredients as $ingredient) {
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);

            if (! in_array($role, [MealScalingRoleEnum::Fat, MealScalingRoleEnum::Sauce], true)) {
                continue;
            }

            $baseline = (float) ($grams[$ingredient->id] ?? 0);

            if ($baseline <= 0) {
                continue;
            }

            $trimmed[$ingredient->id] = round($baseline * $multiplier, 4);
        }

        return KitchenPortionRounding::snapFatRoleGramsForMeal($meal, $trimmed);
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function trimNonProteinRolesToCalorieTarget(Meal $meal, array $grams, float $targetCalories): array
    {
        if ($targetCalories <= 0) {
            return $grams;
        }

        $baselineGrams = AdaptedMenuBuilder::baselineGramsByIngredientId($meal);
        $adjusted = $grams;

        foreach ([
            [MealScalingRoleEnum::Fat, MealScalingRoleEnum::Sauce],
            [MealScalingRoleEnum::Carb],
            [MealScalingRoleEnum::Vegetable, MealScalingRoleEnum::Other],
        ] as $roles) {
            if (self::totalCalories($meal, $adjusted) <= $targetCalories + 0.5) {
                break;
            }

            $adjusted = self::trimRoleCalories($meal, $adjusted, $targetCalories, $roles, $baselineGrams);
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $grams
     * @param  array<int, float>  $baselineGrams
     * @return array<int, float>
     */
    private static function recoverCarbTargetAfterTrim(
        Meal $meal,
        array $grams,
        float $targetCarbs,
        float $targetCalories,
        array $baselineGrams = [],
    ): array {
        if ($targetCarbs <= 0) {
            return $grams;
        }

        $currentCarbs = self::sumRoleMacro($meal, $grams, MealScalingRoleEnum::Carb, 'carbs');

        if ($currentCarbs >= $targetCarbs - 2) {
            return $grams;
        }

        $totalCarbs = self::sumAllMacro($meal, $grams, 'carbs');
        $nonCarbRoleCarbs = max(0.0, $totalCarbs - $currentCarbs);
        $carbRoleTarget = max(0.0, $targetCarbs - $nonCarbRoleCarbs);
        $carbMultiplier = $carbRoleTarget / max(0.01, $currentCarbs);
        $adjusted = $grams;

        foreach ($meal->ingredients as $ingredient) {
            if (MealScalingRole::roleForIngredient($ingredient, $meal) !== MealScalingRoleEnum::Carb) {
                continue;
            }

            $baseline = (float) ($grams[$ingredient->id] ?? 0);

            if ($baseline <= 0) {
                continue;
            }

            $adjusted[$ingredient->id] = round($baseline * $carbMultiplier, 4);
        }

        if (self::totalCalories($meal, $adjusted) > $targetCalories + 0.5) {
            return self::trimToCalorieTarget($meal, $adjusted, $targetCalories, $baselineGrams);
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function capToCalorieTarget(Meal $meal, array $grams, float $targetCalories): array
    {
        if ($targetCalories <= 0) {
            return $grams;
        }

        $baselineGrams = AdaptedMenuBuilder::baselineGramsByIngredientId($meal);
        $trimmed = self::trimToCalorieTarget($meal, $grams, $targetCalories, $baselineGrams);

        return KitchenPortionRounding::snapFatRoleGramsForMeal($meal, $trimmed);
    }

    /**
     * @param  array<int, float>  $grams
     */
    private static function sumRoleMacro(
        Meal $meal,
        array $grams,
        MealScalingRoleEnum $role,
        string $macroKey,
    ): float {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (MealScalingRole::roleForIngredient($ingredient, $meal) !== $role) {
                continue;
            }

            $ingredientGrams = (float) ($grams[$ingredient->id] ?? 0);

            if ($ingredientGrams <= 0) {
                continue;
            }

            $total += self::macroForGrams($ingredient, $ingredientGrams, $macroKey);
        }

        return round($total, 2);
    }

    /**
     * @param  array<int, float>  $grams
     */
    private static function sumAllMacro(Meal $meal, array $grams, string $macroKey): float
    {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            $ingredientGrams = (float) ($grams[$ingredient->id] ?? 0);

            if ($ingredientGrams <= 0) {
                continue;
            }

            $total += self::macroForGrams($ingredient, $ingredientGrams, $macroKey);
        }

        return round($total, 2);
    }

    private static function macroForGrams(Ingredient $ingredient, float $grams, string $macroKey): float
    {
        $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($ingredient);
        $factor = $grams / 100.0;

        return ((float) ($per100[$macroKey] ?? 0)) * $factor;
    }

    /**
     * @param  array<int, float>  $grams
     */
    private static function totalCalories(Meal $meal, array $grams): float
    {
        $rows = AdaptedMenuBuilder::scaledIngredientRowsFromAdaptedGramsPublic($meal, $grams);
        $nutrition = RecipeNutritionCalculator::fromRows($rows);

        return (float) ($nutrition['calories'] ?? 0);
    }
}
