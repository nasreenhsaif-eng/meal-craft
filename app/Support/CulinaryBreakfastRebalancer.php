<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;

/**
 * When macro targets fight cookability, prefer egg-white swaps and accent trims
 * over crushing foundational vegetables into uncookable pinches.
 */
final class CulinaryBreakfastRebalancer
{
    /**
     * @param  array<int, float>  $gramsByIngredientId
     * @return array<int, float>
     */
    public static function rebalance(
        Meal $meal,
        array $gramsByIngredientId,
        float $targetCalories,
        float $planTier = 0.0,
    ): array {
        $meal->loadMissing('ingredients');

        $grams = CulinaryPortionConstraints::apply($meal, $gramsByIngredientId, $planTier);

        if ($targetCalories <= 0) {
            return $grams;
        }

        $grams = self::trimAccentsTowardTarget($meal, $grams, $targetCalories, $planTier);
        $grams = CulinaryPortionConstraints::apply($meal, $grams, $planTier);

        if (self::caloriesFor($meal, $grams) <= $targetCalories + 1.0) {
            return $grams;
        }

        $grams = self::swapWholeEggsTowardWhites($meal, $grams, $targetCalories);
        $grams = CulinaryPortionConstraints::apply($meal, $grams, $planTier);

        // Prefer a slightly high calorie plate over absurd micro-portions.
        return $grams;
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    private static function trimAccentsTowardTarget(
        Meal $meal,
        array $grams,
        float $targetCalories,
        float $planTier,
    ): array {
        $adjusted = $grams;

        for ($pass = 0; $pass < 8; $pass++) {
            if (self::caloriesFor($meal, $adjusted) <= $targetCalories + 0.5) {
                break;
            }

            $trimId = self::nextTrimCandidateId($meal, $adjusted, $planTier);

            if ($trimId === null) {
                break;
            }

            $current = (float) ($adjusted[$trimId] ?? 0);
            $ingredient = $meal->ingredients->firstWhere('id', $trimId);

            if (! $ingredient instanceof Ingredient || $current <= 0) {
                break;
            }

            $floor = CulinaryPortionConstraints::minimumGrams($meal, $ingredient, $planTier)
                ?? CulinaryPortionConstraints::kitchenPresentFloorGrams($ingredient, $current);
            $step = KitchenPortionRounding::isWoodyFreshHerb($ingredient)
                || KitchenPortionRounding::isFineMeasureSpice($ingredient)
                ? 0.5
                : 5.0;
            $next = max($floor, round($current - $step, 4));

            if ($next >= $current - 0.001) {
                break;
            }

            $adjusted[$trimId] = $next;
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $grams
     */
    private static function nextTrimCandidateId(Meal $meal, array $grams, float $planTier): ?int
    {
        $bestId = null;
        $bestScore = -INF;

        foreach ($meal->ingredients as $ingredient) {
            $current = (float) ($grams[$ingredient->id] ?? 0);

            if ($current <= 0) {
                continue;
            }

            if (EggIngredientPresentation::isEggFamilyIngredient($ingredient)) {
                continue;
            }

            $floor = CulinaryPortionConstraints::minimumGrams($meal, $ingredient, $planTier)
                ?? CulinaryPortionConstraints::kitchenPresentFloorGrams($ingredient, $current);

            if ($current <= $floor + 0.05) {
                continue;
            }

            $score = 0.0;

            if (KitchenPortionRounding::isWoodyFreshHerb($ingredient)) {
                $score = 100.0;
            } elseif (KitchenPortionRounding::isSoftFreshHerb($ingredient)) {
                $score = 80.0;
            } elseif (KitchenPortionRounding::isFineMeasureSpice($ingredient)) {
                $score = 70.0;
            } elseif (KitchenPortionRounding::isNutOrSeedIngredient($ingredient) || str_contains(strtolower($ingredient->name), 'flax')) {
                $score = 60.0;
            } elseif (KitchenPortionRounding::isOilIngredient($ingredient)) {
                $score = 40.0;
            } elseif (CulinaryPortionConstraints::isStructuralIngredient($meal, $ingredient)) {
                $score = 5.0;
            } else {
                $score = 20.0;
            }

            // Prefer trimming larger surplus above floor.
            $score += min(20.0, ($current - $floor) / 5.0);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int) $ingredient->id;
            }
        }

        return $bestId;
    }

    /**
     * Convert whole-egg grams into egg whites to free fat/calorie room while keeping protein.
     *
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    private static function swapWholeEggsTowardWhites(Meal $meal, array $grams, float $targetCalories): array
    {
        $whole = null;
        $white = null;

        foreach ($meal->ingredients as $ingredient) {
            if (EggIngredientPresentation::isWholeEggIngredient($ingredient)) {
                $whole = $ingredient;
            }

            if (EggIngredientPresentation::isEggWhiteIngredient($ingredient)) {
                $white = $ingredient;
            }
        }

        if (! $whole instanceof Ingredient || ! $white instanceof Ingredient) {
            return $grams;
        }

        $adjusted = $grams;
        $wholeGrams = (float) ($adjusted[$whole->id] ?? 0);

        if ($wholeGrams <= EggIngredientPresentation::LARGE_EGG_GRAMS) {
            return $adjusted;
        }

        for ($i = 0; $i < 4; $i++) {
            if (self::caloriesFor($meal, $adjusted) <= $targetCalories + 1.0) {
                break;
            }

            $wholeGrams = (float) ($adjusted[$whole->id] ?? 0);

            if ($wholeGrams <= EggIngredientPresentation::LARGE_EGG_GRAMS) {
                break;
            }

            $move = EggIngredientPresentation::LARGE_EGG_GRAMS;
            $adjusted[$whole->id] = round($wholeGrams - $move, 4);
            $adjusted[$white->id] = round(((float) ($adjusted[$white->id] ?? 0)) + $move, 4);
        }

        return $adjusted;
    }

    /**
     * @param  array<int, float>  $grams
     */
    private static function caloriesFor(Meal $meal, array $grams): float
    {
        $rows = [];

        foreach ($meal->ingredients as $ingredient) {
            $amount = (float) ($grams[$ingredient->id] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $rows[] = [
                'ingredient_id' => $ingredient->id,
                'amount_grams' => $amount,
            ];
        }

        return (float) (RecipeNutritionCalculator::fromRows($rows, applyMealCookingYield: true)['calories'] ?? 0);
    }
}
