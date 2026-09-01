<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\RecipeNutritionCalculator;

/**
 * Scales chicken / fish / beef meals into the shared 1000 ml kitchen pack-out.
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
        $designedCalories = ChickenKitchenPlateTargets::designedCaloriesForTier($calorieTier, $meal);

        if ($cookedProteinTarget === null) {
            return $baseline;
        }

        $isSalad = ChickenKitchenPlateTargets::isSaladMeal($meal);
        $proteinIds = [];
        $platedBaseIds = [];
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

            if (ChickenKitchenPlateTargets::isSideBreadIngredient($ingredient)) {
                continue;
            }

            if (KitchenPortionRounding::isOilIngredient($ingredient)) {
                $oilIds[] = $id;

                continue;
            }

            if (ChickenKitchenPlateTargets::isPlatedBaseIngredient($ingredient, $meal)) {
                $platedBaseIds[] = $id;

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
        self::applyPlatedBaseTargets($meal, $grams, $baseline, $platedBaseIds, $calorieTier);

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
            self::applyBaselineExtraCaps($meal, $grams, $baseline, $extraIds);
            self::applyNutSeedCaps($meal, $grams, $extraIds);
            self::applyDenseVegCaps($meal, $grams, $extraIds);
            self::applyLeafyCaps($meal, $grams, $extraIds);
            self::applyNoodleVegCaps($meal, $grams, $extraIds);
            self::applyCherryTomatoCaps($meal, $grams, $extraIds);
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
                $baseline,
                $extraIds,
                $designedCalories,
                ChickenKitchenPlateTargets::calorieTolerance(),
            );
        }

        if ($extraIds !== []) {
            self::applyBaselineExtraCaps($meal, $grams, $baseline, $extraIds);
            self::fillRemainingContainerVolume(
                $meal,
                $grams,
                $extraIds,
                $proteinIds,
                $oilIds,
                $dressingIds,
                $seasoningIds,
                $otherSauceIds,
                $designedCalories,
            );
            self::applyBaselineExtraCaps($meal, $grams, $baseline, $extraIds);
            self::applyDenseVegCaps($meal, $grams, $extraIds);
            self::applyLeafyCaps($meal, $grams, $extraIds);
            self::applyNoodleVegCaps($meal, $grams, $extraIds);
            self::applyCherryTomatoCaps($meal, $grams, $extraIds);
        }

        if ($designedCalories !== null) {
            $grams = self::capToDesignedCalories(
                $meal,
                $grams,
                $proteinIds,
                $platedBaseIds,
                $calorieTier,
                $designedCalories,
                ChickenKitchenPlateTargets::calorieTolerance(),
            );
        }

        $grams = KitchenPortionRounding::snapAllGramsForMeal($meal, $grams);

        if ($designedCalories !== null) {
            $grams = self::capToDesignedCalories(
                $meal,
                $grams,
                $proteinIds,
                $platedBaseIds,
                $calorieTier,
                $designedCalories,
                ChickenKitchenPlateTargets::calorieTolerance(),
            );
            $grams = KitchenPortionRounding::snapAllGramsForMeal($meal, $grams);
        }

        return $grams;
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
     * @param  list<int>  $platedBaseIds
     */
    private static function applyPlatedBaseTargets(
        Meal $meal,
        array &$grams,
        array $baseline,
        array $platedBaseIds,
        int $calorieTier,
    ): void {
        if ($platedBaseIds === []) {
            return;
        }

        $referenceTier = 500;
        $referenceProtein = ChickenKitchenPlateTargets::cookedProteinGramsForTier($referenceTier);
        $tierProtein = ChickenKitchenPlateTargets::cookedProteinGramsForTier($calorieTier);

        if ($referenceProtein === null || $tierProtein === null || $referenceProtein <= 0.0) {
            return;
        }

        $ratio = $tierProtein / $referenceProtein;

        foreach ($platedBaseIds as $id) {
            $ingredient = $meal->ingredients->firstWhere('id', $id);

            if ($ingredient === null) {
                continue;
            }

            $baselineGrams = max(0.0, (float) ($baseline[$id] ?? 0.0));

            if ($baselineGrams <= 0.0) {
                continue;
            }

            $target = KitchenPortionRounding::snapFiveGramSteps($baselineGrams * $ratio);
            $floor = KitchenPortionRounding::snapFiveGramSteps($baselineGrams * 0.65);
            $grams[$id] = max($target, $floor);

            if (ChickenKitchenPlateTargets::isPlatedRiceBaseIngredient($ingredient)) {
                $riceTarget = ChickenKitchenPlateTargets::platedRiceBaseGramsForTier($calorieTier, $meal);

                if ($riceTarget !== null) {
                    if (MealTiersProteinFamily::forMeal($meal) === MealTiersProteinFamily::Beef) {
                        $grams[$id] = $riceTarget;
                    } else {
                        $grams[$id] = max((float) $grams[$id], $riceTarget);
                    }
                }
            }
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
     * @param  array<int, float>  $baseline
     * @param  list<int>  $extraIds
     */
    private static function applyBaselineExtraCaps(Meal $meal, array &$grams, array $baseline, array $extraIds): void
    {
        foreach ($extraIds as $id) {
            $reference = max(0.0, (float) ($baseline[$id] ?? 0.0));

            if ($reference <= 0.0) {
                continue;
            }

            $grams[$id] = min((float) ($grams[$id] ?? 0.0), round($reference * 2.5, 2));
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
        array $baseline,
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
                    'nut_seed', 'grain' => $delta > 0 ? min($factor, 0.8) : max($factor, 1.1),
                    'dense_veg' => $delta > 0 ? min($factor, 0.88) : max($factor, 1.12),
                    'noodle_veg' => $delta > 0 ? max($factor, 0.9) : min($factor, 1.2),
                    'leafy' => $delta > 0 ? max($factor, 0.92) : min($factor, 1.35),
                    default => $factor,
                };

                $grams[$id] = max(0.0, round((float) ($grams[$id] ?? 0.0) * $bandFactor, 2));
            }

            self::applyBaselineExtraCaps($meal, $grams, $baseline, $extraIds);
            self::applyNutSeedCaps($meal, $grams, $extraIds);
            self::applyDenseVegCaps($meal, $grams, $extraIds);
            self::applyLeafyCaps($meal, $grams, $extraIds);
            self::applyNoodleVegCaps($meal, $grams, $extraIds);
            self::applyCherryTomatoCaps($meal, $grams, $extraIds);
        }

        return $grams;
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $extraIds
     */
    private static function applyLeafyCaps(Meal $meal, array &$grams, array $extraIds): void
    {
        $cap = ChickenKitchenPlateTargets::leafyCapGrams();

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            if (ChickenKitchenPlateTargets::densityBandForIngredient($ingredient, $meal) !== 'leafy') {
                continue;
            }

            $grams[$ingredient->id] = min((float) ($grams[$ingredient->id] ?? 0.0), $cap);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $extraIds
     */
    private static function applyNoodleVegCaps(Meal $meal, array &$grams, array $extraIds): void
    {
        $cap = ChickenKitchenPlateTargets::noodleVegCapGrams();

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            if (ChickenKitchenPlateTargets::densityBandForIngredient($ingredient, $meal) !== 'noodle_veg') {
                continue;
            }

            $grams[$ingredient->id] = min((float) ($grams[$ingredient->id] ?? 0.0), $cap);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $extraIds
     */
    private static function applyCherryTomatoCaps(Meal $meal, array &$grams, array $extraIds): void
    {
        $cap = ChickenKitchenPlateTargets::cherryTomatoCapGrams();

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            if (! ChickenKitchenPlateTargets::isCherryTomatoIngredient($ingredient)) {
                continue;
            }

            $grams[$ingredient->id] = min((float) ($grams[$ingredient->id] ?? 0.0), $cap);
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $extraIds
     */
    private static function applyDenseVegCaps(Meal $meal, array &$grams, array $extraIds): void
    {
        $cap = ChickenKitchenPlateTargets::denseVegCapGrams();

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            if (ChickenKitchenPlateTargets::densityBandForIngredient($ingredient, $meal) !== 'dense_veg') {
                continue;
            }

            $grams[$ingredient->id] = min((float) ($grams[$ingredient->id] ?? 0.0), $cap);
        }
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
     * @param  list<int>  $extraIds
     * @param  list<int>  $proteinIds
     * @param  list<int>  $oilIds
     * @param  list<int>  $dressingIds
     * @param  list<int>  $seasoningIds
     * @param  list<int>  $otherSauceIds
     */
    private static function fillRemainingContainerVolume(
        Meal $meal,
        array &$grams,
        array $extraIds,
        array $proteinIds,
        array $oilIds,
        array $dressingIds,
        array $seasoningIds,
        array $otherSauceIds,
        ?float $designedCalories,
    ): void {
        $targetMl = ChickenKitchenPlateTargets::containerMl();
        $tolerance = ChickenKitchenPlateTargets::calorieTolerance();
        $trackedIds = array_merge($proteinIds, $extraIds, $oilIds, $dressingIds, $seasoningIds, $otherSauceIds);

        $fillOrder = [];
        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $extraIds, true)) {
                continue;
            }

            if (KitchenPortionRounding::isNutOrSeedIngredient($ingredient)) {
                continue;
            }

            $fillOrder[] = [
                'id' => $ingredient->id,
                'band' => ChickenKitchenPlateTargets::densityBandForIngredient($ingredient, $meal),
            ];
        }

        usort($fillOrder, static function (array $a, array $b): int {
            $priority = ['leafy' => 0, 'noodle_veg' => 1, 'default_extra' => 2, 'dense_veg' => 3, 'grain' => 4, 'nut_seed' => 5, 'oil' => 6, 'protein' => 7];

            return ($priority[$a['band']] ?? 9) <=> ($priority[$b['band']] ?? 9);
        });

        if ($fillOrder === []) {
            return;
        }

        for ($step = 0; $step < 240; $step++) {
            if (self::estimatedPackedMl($meal, $grams, $trackedIds) >= $targetMl * 0.98) {
                break;
            }

            if ($designedCalories !== null && self::totalCalories($meal, $grams) >= $designedCalories + $tolerance) {
                break;
            }

            $added = false;

            foreach ($fillOrder as $entry) {
                $id = $entry['id'];
                $candidate = (float) ($grams[$id] ?? 0.0) + 5.0;
                $testGrams = $grams;
                $testGrams[$id] = $candidate;

                if ($designedCalories !== null && self::totalCalories($meal, $testGrams) > $designedCalories + $tolerance) {
                    continue;
                }

                $grams[$id] = $candidate;
                $added = true;

                break;
            }

            if (! $added) {
                break;
            }
        }
    }

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $ingredientIds
     */
    private static function estimatedPackedMl(Meal $meal, array $grams, array $ingredientIds): float
    {
        $total = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->id, $ingredientIds, true)) {
                continue;
            }

            $total += ChickenKitchenPlateTargets::estimatedMl(
                $ingredient,
                $meal,
                (float) ($grams[$ingredient->id] ?? 0.0),
            );
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

    /**
     * @param  array<int, float>  $grams
     * @param  list<int>  $proteinIds
     * @param  list<int>  $platedBaseIds
     */
    private static function capToDesignedCalories(
        Meal $meal,
        array $grams,
        array $proteinIds,
        array $platedBaseIds,
        int $calorieTier,
        float $designedCalories,
        float $tolerance,
    ): array {
        for ($step = 0; $step < 80; $step++) {
            if (self::totalCalories($meal, $grams) <= $designedCalories + $tolerance) {
                break;
            }

            $reduced = false;

            foreach ($proteinIds as $id) {
                $ingredient = $meal->ingredients->firstWhere('id', $id);

                if ($ingredient === null) {
                    continue;
                }

                $target = ChickenKitchenPlateTargets::storedProteinGramsForIngredient($ingredient, $calorieTier);
                $floor = $target !== null
                    ? KitchenPortionRounding::snapFiveGramSteps($target * 0.75)
                    : 100.0;
                $current = (float) ($grams[$id] ?? 0.0);

                if ($current > $floor + 4.99) {
                    $grams[$id] = max($floor, $current - 5.0);
                    $reduced = true;
                    break;
                }
            }

            if ($reduced) {
                continue;
            }

            foreach ($platedBaseIds as $id) {
                $ingredient = $meal->ingredients->firstWhere('id', $id);

                if ($ingredient === null) {
                    continue;
                }

                $current = (float) ($grams[$id] ?? 0.0);

                if (ChickenKitchenPlateTargets::isPlatedRiceBaseIngredient($ingredient)) {
                    $minimum = ChickenKitchenPlateTargets::platedRiceBaseGramsForTier($calorieTier, $meal) ?? 0.0;

                    if ($current > $minimum + 4.99) {
                        $grams[$id] = max($minimum, $current - 5.0);
                        $reduced = true;
                        break;
                    }

                    continue;
                }

                if ($current > 5.0) {
                    $grams[$id] = max(5.0, $current - 5.0);
                    $reduced = true;
                    break;
                }
            }

            if ($reduced) {
                continue;
            }

            foreach ($meal->ingredients as $ingredient) {
                if (! KitchenPortionRounding::isOilIngredient($ingredient)) {
                    continue;
                }

                $current = (float) ($grams[$ingredient->id] ?? 0.0);

                if ($current > 5.0) {
                    $grams[$ingredient->id] = max(5.0, $current - 5.0);
                    $reduced = true;
                    break;
                }
            }

            if (! $reduced) {
                break;
            }
        }

        return $grams;
    }
}
