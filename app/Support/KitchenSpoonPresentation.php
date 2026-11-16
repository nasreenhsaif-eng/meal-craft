<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Kitchen spoon/cup/pinch labels shown beside grams for spices, herbs, oils,
 * sauces, dressings, pastes, and other liquids.
 */
final class KitchenSpoonPresentation
{
    public const MLS_PER_TEASPOON = 5.0;

    public const MLS_PER_TABLESPOON = 15.0;

    public const MLS_PER_CUP = 240.0;

    /** Default grams per loosely packed teaspoon for unclassified dry spices. */
    private const DEFAULT_DRY_G_PER_TSP = 2.0;

    /** Thick paste / molasses-style scoop density (g per tsp). */
    private const PASTE_G_PER_TSP = 5.0;

    /** Soft chopped fresh herbs (g per tsp). */
    private const SOFT_HERB_G_PER_TSP = 1.7;

    /** Woody fresh herbs (g per tsp). */
    private const WOODY_HERB_G_PER_TSP = 0.7;

    /** Finely sliced fresh chillies (g per tsp). */
    private const FRESH_CHILLI_G_PER_TSP = 2.0;

    /**
     * Name-fragment overrides for dry spice / seed teaspoon densities (g per tsp).
     * Longer / more specific needles should appear before shorter ones.
     *
     * @var array<string, float>
     */
    private const DRY_G_PER_TSP_BY_NEEDLE = [
        'sea salt' => 6.0,
        'black seeds' => 5.0,
        'chili flake' => 1.8,
        'chilli flake' => 1.8,
        'chili powder' => 2.5,
        'chilli powder' => 2.5,
        'black pepper' => 2.3,
        'white pepper' => 2.3,
        'cumin' => 2.0,
        'coriander powder' => 1.8,
        'coriander seed' => 1.8,
        'turmeric' => 3.0,
        'paprika' => 2.3,
        'cinnamon' => 2.6,
        'nutmeg' => 2.2,
        'mustard seed' => 3.0,
        'sesame seed' => 3.0,
        'flaxseed' => 2.5,
        'saffron' => 0.2,
        'salt' => 6.0,
        'pepper' => 2.3,
    ];

    public static function appliesTo(Ingredient $ingredient): bool
    {
        if (KitchenPortionRounding::isFineMeasureSpice($ingredient)) {
            return true;
        }

        if (KitchenPortionRounding::isSoftFreshHerb($ingredient) || KitchenPortionRounding::isWoodyFreshHerb($ingredient)) {
            return true;
        }

        if (KitchenPortionRounding::isFreshChilli($ingredient)) {
            return true;
        }

        if (LiquidIngredientPresentation::isCookingOilIngredient($ingredient)) {
            return true;
        }

        if (LiquidIngredientPresentation::isLiquidIngredient($ingredient)) {
            return true;
        }

        return self::isSauceDressingOrPaste($ingredient);
    }

    public static function isSauceDressingOrPaste(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if ($name === '') {
            return false;
        }

        // Solid nut butters / plain hummus stay scoopable solids, not spoon-labeled liquids.
        foreach (['peanut butter', 'almond butter', 'cashew butter', 'hummus'] as $solid) {
            if (str_contains($name, $solid) && ! str_contains($name, 'dressing')) {
                return false;
            }
        }

        foreach (['sauce', 'dressing', 'paste', 'molasses', 'chutney', 'marinade', 'vinaigrette'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kitchen volume label only, e.g. "½ tsp", or null when not applicable.
     */
    public static function labelForGrams(Ingredient $ingredient, float $grams): ?string
    {
        if ($grams <= 0 || ! self::appliesTo($ingredient)) {
            return null;
        }

        if (self::isDrySpoonIngredient($ingredient)) {
            return self::labelForDryGrams($ingredient, $grams);
        }

        return self::labelForLiquidOrPasteGrams($ingredient, $grams);
    }

    /**
     * Append a spoon/cup/pinch label after a grams fragment: "3g (½ tsp)".
     */
    public static function appendToGrams(string $gramsPart, Ingredient $ingredient, float $grams): string
    {
        $label = self::labelForGrams($ingredient, $grams);

        if ($label === null || $label === '') {
            return $gramsPart;
        }

        return $gramsPart.' ('.$label.')';
    }

    /**
     * Meal Tiers style: "Black Seeds — 3g (½ tsp)".
     */
    public static function formatTierLine(Ingredient $ingredient, float $grams, string $formattedGrams): string
    {
        $gramsPart = self::appendToGrams($formattedGrams.'g', $ingredient, $grams);

        return $ingredient->name.' — '.$gramsPart;
    }

    /**
     * Meal Library style: "3g (½ tsp) Black Seeds".
     */
    public static function formatLibraryLine(Ingredient $ingredient, float $grams, string $formattedGrams): string
    {
        $gramsPart = self::appendToGrams($formattedGrams.'g', $ingredient, $grams);

        return $gramsPart.' '.$ingredient->name;
    }

    private static function isDrySpoonIngredient(Ingredient $ingredient): bool
    {
        return KitchenPortionRounding::isFineMeasureSpice($ingredient)
            || KitchenPortionRounding::isSoftFreshHerb($ingredient)
            || KitchenPortionRounding::isWoodyFreshHerb($ingredient)
            || KitchenPortionRounding::isFreshChilli($ingredient);
    }

    private static function labelForDryGrams(Ingredient $ingredient, float $grams): string
    {
        $gPerTsp = self::gramsPerTeaspoonForDry($ingredient);

        if ($gPerTsp <= 0) {
            $gPerTsp = self::DEFAULT_DRY_G_PER_TSP;
        }

        // Very small salt/pepper pinches.
        if ($grams <= 0.5 && self::isPinchFriendlySpice($ingredient)) {
            return 'pinch';
        }

        $teaspoons = $grams / $gPerTsp;

        return self::formatFromTeaspoons($teaspoons, allowPinch: true);
    }

    private static function labelForLiquidOrPasteGrams(Ingredient $ingredient, float $grams): string
    {
        if (self::isSauceDressingOrPaste($ingredient) && ! LiquidIngredientPresentation::isLiquidIngredient($ingredient)
            && ! LiquidIngredientPresentation::isCookingOilIngredient($ingredient)) {
            $teaspoons = $grams / self::PASTE_G_PER_TSP;

            return self::formatFromTeaspoons(max(0.25, $teaspoons), allowPinch: false);
        }

        $ml = LiquidIngredientPresentation::millilitersFromGrams($grams, $ingredient);

        if ($ml <= 0) {
            return 'pinch';
        }

        // Prefer cups for larger liquid volumes.
        if ($ml >= self::MLS_PER_CUP * 0.2) {
            $cups = $ml / self::MLS_PER_CUP;
            $snappedCups = self::snapKitchenCups($cups);

            if ($snappedCups >= 0.25) {
                return self::formatCupLabel($snappedCups);
            }
        }

        $teaspoons = $ml / self::MLS_PER_TEASPOON;

        // Oils always show at least a half teaspoon when present.
        if (LiquidIngredientPresentation::isCookingOilIngredient($ingredient)) {
            return self::formatFromTeaspoons(max(0.5, $teaspoons), allowPinch: false);
        }

        if ($ml < 1.25) {
            return 'pinch';
        }

        return self::formatFromTeaspoons(max(0.25, $teaspoons), allowPinch: false);
    }

    private static function gramsPerTeaspoonForDry(Ingredient $ingredient): float
    {
        if (KitchenPortionRounding::isWoodyFreshHerb($ingredient)) {
            return self::WOODY_HERB_G_PER_TSP;
        }

        if (KitchenPortionRounding::isSoftFreshHerb($ingredient)) {
            return self::SOFT_HERB_G_PER_TSP;
        }

        if (KitchenPortionRounding::isFreshChilli($ingredient)) {
            return self::FRESH_CHILLI_G_PER_TSP;
        }

        $name = strtolower(trim($ingredient->name));

        foreach (self::DRY_G_PER_TSP_BY_NEEDLE as $needle => $gramsPerTsp) {
            if (str_contains($name, $needle)) {
                // Avoid matching "bell pepper" as pepper spice.
                if ($needle === 'pepper' && (str_contains($name, 'bell') || str_contains($name, 'chili') || str_contains($name, 'chilli'))) {
                    continue;
                }

                return $gramsPerTsp;
            }
        }

        return self::DEFAULT_DRY_G_PER_TSP;
    }

    private static function isPinchFriendlySpice(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        foreach (['salt', 'pepper', 'chili flake', 'chilli flake', 'saffron', 'nutmeg'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return KitchenPortionRounding::isFineMeasureSpice($ingredient);
    }

    private static function formatFromTeaspoons(float $teaspoons, bool $allowPinch): string
    {
        if ($teaspoons <= 0) {
            return $allowPinch ? 'pinch' : '¼ tsp';
        }

        if ($allowPinch && $teaspoons < 0.125) {
            return 'pinch';
        }

        // Promote to tablespoons when at least ~2½ tsp.
        if ($teaspoons >= 2.5) {
            $tablespoons = $teaspoons / 3.0;
            $snappedTbsp = LiquidIngredientPresentation::snapKitchenTablespoons($tablespoons);

            // Prefer cups when large.
            if ($snappedTbsp >= 8.0) {
                return self::formatCupLabel(self::snapKitchenCups($snappedTbsp / 16.0));
            }

            return LiquidIngredientPresentation::formatTablespoonLabel($snappedTbsp);
        }

        $snappedTsp = self::snapKitchenTeaspoons($teaspoons);

        return self::formatTeaspoonLabel($snappedTsp);
    }

    /**
     * Snap to kitchen teaspoon steps: ¼, ½, 1, 1½, 2…
     */
    public static function snapKitchenTeaspoons(float $teaspoons): float
    {
        if ($teaspoons <= 0) {
            return 0.0;
        }

        if ($teaspoons < 0.375) {
            return 0.25;
        }

        if ($teaspoons < 0.75) {
            return 0.5;
        }

        $snapped = round($teaspoons * 2) / 2;

        return max(1.0, $snapped);
    }

    public static function formatTeaspoonLabel(float $teaspoons): string
    {
        if ($teaspoons <= 0) {
            return '0 tsp';
        }

        if (abs($teaspoons - 0.25) < 0.01) {
            return '¼ tsp';
        }

        $whole = (int) floor($teaspoons + 1e-9);
        $fraction = round($teaspoons - $whole, 2);

        if ($fraction < 0.01) {
            return $whole === 1 ? '1 tsp' : $whole.' tsp';
        }

        if (abs($fraction - 0.5) < 0.01) {
            return $whole === 0 ? '½ tsp' : $whole.'½ tsp';
        }

        if (abs($fraction - 0.25) < 0.01) {
            return $whole === 0 ? '¼ tsp' : $whole.'¼ tsp';
        }

        return LiquidIngredientPresentation::formatTrimmedDecimal($teaspoons, 1).' tsp';
    }

    public static function snapKitchenCups(float $cups): float
    {
        if ($cups <= 0) {
            return 0.0;
        }

        $steps = [0.25, 0.33, 0.5, 0.67, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0, 2.5, 3.0, 4.0];
        $best = $steps[0];
        $bestDelta = abs($cups - $best);

        foreach ($steps as $step) {
            $delta = abs($cups - $step);
            if ($delta < $bestDelta) {
                $best = $step;
                $bestDelta = $delta;
            }
        }

        if ($cups > 4.0) {
            return round($cups * 2) / 2;
        }

        return $best;
    }

    public static function formatCupLabel(float $cups): string
    {
        if ($cups <= 0) {
            return '0 cup';
        }

        $labels = [
            0.25 => '¼ cup',
            0.33 => '⅓ cup',
            0.5 => '½ cup',
            0.67 => '⅔ cup',
            0.75 => '¾ cup',
        ];

        foreach ($labels as $value => $label) {
            if (abs($cups - $value) < 0.02) {
                return $label;
            }
        }

        $whole = (int) floor($cups + 1e-9);
        $fraction = round($cups - $whole, 2);

        if ($fraction < 0.01) {
            return $whole === 1 ? '1 cup' : $whole.' cups';
        }

        if (abs($fraction - 0.5) < 0.01) {
            return $whole === 0 ? '½ cup' : $whole.'½ cups';
        }

        return LiquidIngredientPresentation::formatTrimmedDecimal($cups, 2).' cups';
    }
}
