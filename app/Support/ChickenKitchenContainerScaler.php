<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;

/**
 * Scales chicken-family meals into the shared 500 ml kitchen pack-out.
 */
final class ChickenKitchenContainerScaler
{
    /**
     * @param  array<int, float>  $baseline
     * @return array<int, float>
     */
    public static function gramsForTier(Meal $meal, array $baseline, int $calorieTier): array
    {
        $meal->loadMissing('ingredients');

        $cookedProteinTarget = ChickenKitchenPlateTargets::cookedProteinGramsForTier($calorieTier);
        $designedCalories = ChickenKitchenPlateTargets::designedCaloriesForTier($calorieTier);

        if ($cookedProteinTarget === null) {
            return $baseline;
        }

        $isSalad = ChickenKitchenPlateTargets::isSaladMeal($meal);
        $proteinIds = [];
        $extraIds = [];
        $dressingIds = [];
        $oilIds = [];
        $seasoningIds = [];
        $otherSauceIds = [];

        foreach ($meal->ingredients as $ingredient) {
            $id = $ingredient->id;
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);
            $baselineGrams = (float) ($baseline[$id] ?? 0.0);

            if ($baselineGrams <= 0.0) {
                continue;
            }

            if (
                $role === MealScalingRoleEnum::Protein
                && StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)
            ) {
                $proteinIds[] = $id;

                continue;
            }

            if (ChickenKitchenPlateTargets::isDressingIngredient($ingredient)) {
                $dressingIds[] = $id;

                continue;
            }

            if (KitchenPortionRounding::isOilIngredient($ingredient)) {
                $oilIds[] = $id;

                continue;
            }

            if ($role === MealScalingRoleEnum::HerbSpice) {
                $seasoningIds[] = $id;

                continue;
            }

            if ($role === MealScalingRoleEnum::Sauce || $role === MealScalingRoleEnum::Fat) {
                $otherSauceIds[] = $id;

                continue;
            }

            if (in_array($role, [
                MealScalingRoleEnum::Carb,
                MealScalingRoleEnum::Vegetable,
                MealScalingRoleEnum::Other,
            ], true) || KitchenPortionRounding::isNutOrSeedIngredient($ingredient)) {
                $extraIds[] = $id;
            }
        }

        $grams = $baseline;

        self::applyProteinTargets($meal, $grams, $proteinIds, $calorieTier);

        if ($isSalad && $dressingIds !== []) {
            self::scaleIdsToTotalGrams($grams, $baseline, $dressingIds, ChickenKitchenPlateTargets::saladDressingGrams());
        } elseif (! $isSalad && $oilIds !== []) {
            foreach ($oilIds as $id) {
                $grams[$id] = ChickenKitchenPlateTargets::platePrepOilGrams();
            }
        } elseif (! $isSalad && $oilIds === [] && $otherSauceIds === []) {
            // keep baseline oils empty
        } elseif (! $isSalad) {
            foreach ($oilIds as $id) {
                $grams[$id] = ChickenKitchenPlateTargets::platePrepOilGrams();
            }
        }

        if ($isSalad) {
            foreach ($oilIds as $id) {
                // Salads: dressing on the side; leave only tiny cook oils if already present, capped at prep oil.
                $grams[$id] = min(
                    (float) ($baseline[$id] ?? 0.0),
                    ChickenKitchenPlateTargets::platePrepOilGrams(),
                );
                if ($grams[$id] > 0.0 && $grams[$id] < 5.0) {
                    $grams[$id] = ChickenKitchenPlateTargets::platePrepOilGrams();
                }
            }
        }

        if ($extraIds !== []) {
            self::scaleExtrasToContainer($meal, $grams, $baseline, $proteinIds, $extraIds);
            self::applyNutSeedCaps($meal, $grams, $extraIds);
        }

        foreach ($seasoningIds as $id) {
            $grams[$id] = (float) ($baseline[$id] ?? 0.0);
        }

        if (! $isSalad && $otherSauceIds !== []) {
            foreach ($otherSauceIds as $id) {
                $grams[$id] = KitchenPortionRounding::snapFiveGramSteps((float) ($baseline[$id] ?? 0.0));
            }
        }

        if ($designedCalories !== null) {
            $grams = self::rebalanceExtrasToCalories(
                $meal,
                $grams,
                $extraIds,
                $designedCalories,
                ChickenKitchenPlateTargets::calorieTolerance(),
            );
        }

        return KitchenPortionRounding::snapAllGramsForMeal($meal, $grams);
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $proteinIds
     */
    private static function applyProteinTargets(Meal $meal, array &$grams, array $proteinIds, int $calorieTier): void
    {
        if ($proteinIds === []) {
            return;
        }

        $targets = [];
        $totalTarget = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $proteinIds, true)) {
                continue;
            }

            $stored = ChickenKitchenPlateTargets::storedProteinGramsForIngredient($ingredient, $calorieTier);

            if ($stored === null) {
                continue;
            }

            $targets[$ingredient->id] = $stored;
            $totalTarget += $stored;
        }

        if ($targets === []) {
            return;
        }

        if (count($targets) === 1) {
            foreach ($targets as $id => $target) {
                $grams[$id] = $target;
            }

            return;
        }

        $baselineTotal = 0.0;
        foreach ($proteinIds as $id) {
            $baselineTotal += max(0.0, (float) ($grams[$id] ?? 0.0));
        }

        if ($baselineTotal <= 0.0) {
            $share = $totalTarget / count($targets);
            foreach ($targets as $id => $_) {
                $grams[$id] = KitchenPortionRounding::snapFiveGramSteps($share);
            }

            return;
        }

        foreach ($proteinIds as $id) {
            $share = max(0.0, (float) ($grams[$id] ?? 0.0)) / $baselineTotal;
            $grams[$id] = KitchenPortionRounding::snapFiveGramSteps($totalTarget * $share);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  array<int, float>  $baseline
     * @param  list<int>  $ids
     */
    private static function scaleIdsToTotalGrams(array &$grams, array $baseline, array $ids, float $targetTotal): void
    {
        $current = 0.0;
        foreach ($ids as $id) {
            $current += max(0.0, (float) ($baseline[$id] ?? 0.0));
        }

        if ($current <= 0.0) {
            $share = $targetTotal / max(1, count($ids));
            foreach ($ids as $id) {
                $grams[$id] = $share;
            }

            return;
        }

        $multiplier = $targetTotal / $current;
        foreach ($ids as $id) {
            $grams[$id] = round(max(0.0, (float) ($baseline[$id] ?? 0.0)) * $multiplier, 2);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  array<int, float>  $baseline
     * @param  list<int>  $proteinIds
     * @param  list<int>  $extraIds
     */
    private static function scaleExtrasToContainer(
        Meal $meal,
        array &$grams,
        array $baseline,
        array $proteinIds,
        array $extraIds,
    ): void {
        $proteinMl = 0.0;
        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $proteinIds, true)) {
                continue;
            }

            $proteinMl += ChickenKitchenPlateTargets::estimatedMl(
                $ingredient,
                $meal,
                (float) ($grams[$ingredient->id] ?? 0.0),
            );
        }

        $targetExtrasMl = max(50.0, ChickenKitchenPlateTargets::containerMl() - $proteinMl);

        $baselineExtrasMl = 0.0;
        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            $baselineExtrasMl += ChickenKitchenPlateTargets::estimatedMl(
                $ingredient,
                $meal,
                max(0.0, (float) ($baseline[$ingredient->id] ?? 0.0)),
            );
        }

        if ($baselineExtrasMl <= 0.0) {
            return;
        }

        $multiplier = $targetExtrasMl / $baselineExtrasMl;
        foreach ($extraIds as $id) {
            $grams[$id] = round(max(0.0, (float) ($baseline[$id] ?? 0.0)) * $multiplier, 2);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $extraIds
     */
    private static function applyNutSeedCaps(Meal $meal, array &$grams, array $extraIds): void
    {
        $cap = ChickenKitchenPlateTargets::nutSeedCapGrams();

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            if (! KitchenPortionRounding::isNutOrSeedIngredient($ingredient)) {
                continue;
            }

            $grams[$ingredient->id] = min((float) ($grams[$ingredient->id] ?? 0.0), $cap);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $extraIds
     * @return array<int, float>
     */
    private static function rebalanceExtrasToCalories(
        Meal $meal,
        array $grams,
        array $extraIds,
        float $designedCalories,
        float $tolerance,
    ): array {
        if ($extraIds === []) {
            return $grams;
        }

        for ($i = 0; $i < 12; $i++) {
            $calories = self::totalCalories($meal, $grams);
            $delta = $calories - $designedCalories;

            if (abs($delta) <= $tolerance) {
                break;
            }

            $extraCalories = self::caloriesForIds($meal, $grams, $extraIds);

            if ($extraCalories <= 0.0) {
                break;
            }

            // Prefer trimming/growing denser extras when over/under.
            $factor = $delta > 0
                ? max(0.55, 1.0 - min(0.35, $delta / max($extraCalories, 1.0)))
                : min(1.45, 1.0 + min(0.35, abs($delta) / max($extraCalories, 1.0)));

            foreach ($extraIds as $id) {
                $ingredient = $meal->ingredients->firstWhere('id', $id);
                if ($ingredient === null) {
                    continue;
                }

                $band = ChickenKitchenPlateTargets::densityBandForIngredient($ingredient, $meal);
                $bandFactor = match ($band) {
                    'nut_seed', 'grain' => $delta > 0 ? min($factor, 0.85) : max($factor, 1.15),
                    'leafy' => $delta > 0 ? max($factor, 0.9) : min($factor, 1.1),
                    default => $factor,
                };

                $grams[$id] = max(0.0, round((float) ($grams[$id] ?? 0.0) * $bandFactor, 2));
            }

            self::applyNutSeedCaps($meal, $grams, $extraIds);
        }

        return $grams;
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $ids
     */
    private static function caloriesForIds(Meal $meal, array $grams, array $ids): float
    {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $ids, true)) {
                continue;
            }

            $total += self::caloriesForIngredientGrams($ingredient, (float) ($grams[$ingredient->id] ?? 0.0));
        }

        return $total;
    }

    /**
     * @param  array<int, float>  $grams
     */
    private static function totalCalories(Meal $meal, array $grams): float
    {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            $total += self::caloriesForIngredientGrams($ingredient, (float) ($grams[$ingredient->id] ?? 0.0));
        }

        return $total;
    }

    private static function caloriesForIngredientGrams(Ingredient $ingredient, float $grams): float
    {
        if ($grams <= 0.0) {
            return 0.0;
        }

        $perGram = RecipeNutritionCalculator::unroundedNutrientsPerGram($ingredient);

        return (float) ($perGram['calories'] ?? 0) * $grams;
    }
}
