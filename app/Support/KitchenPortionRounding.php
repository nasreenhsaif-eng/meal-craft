<?php

namespace App\Support;

use App\Enums\MealScalingRole as MealScalingRoleEnum;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Snaps ingredient grams to kitchen-realistic increments (no 2.42g ginger or 8.09ml vinegar).
 */
final class KitchenPortionRounding
{
    private const STEP_GRAMS = 5.0;

    /**
     * Oils and pourable fats: nearest 5 g, minimum 5 g when present.
     * Never returns 0 for a positive input — that left "Olive Oil" lines with no quantity.
     */
    public static function snapOilGrams(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;

        return max(self::STEP_GRAMS, $snapped);
    }

    public static function snapNutGrams(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;

        return max(self::STEP_GRAMS, $snapped);
    }

    public static function snapCheeseGrams(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;

        return max(self::STEP_GRAMS, $snapped);
    }

    /**
     * Nearest 5 g step. Tiny non-zero amounts bump to 5 g so they stay measurable.
     */
    public static function snapFiveGramSteps(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams / self::STEP_GRAMS) * self::STEP_GRAMS;

        return $snapped > 0 ? $snapped : self::STEP_GRAMS;
    }

    /**
     * Whole-gram pinches for fine dry spices (1 g salt, not 0.1 g cinnamon).
     */
    public static function snapFineSpiceGrams(float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        $snapped = round($grams);

        return max(1.0, $snapped);
    }

    public static function isOilIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        return (bool) preg_match('/\boil\b/', $name);
    }

    public static function isLiquidFatIngredient(Ingredient $ingredient): bool
    {
        if (self::isOilIngredient($ingredient)) {
            return true;
        }

        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        foreach (['tahini', 'peanut butter', 'almond butter', 'cashew butter', 'butter'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isPourableCondiment(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        foreach ([
            'vinegar',
            'honey',
            'syrup',
            'tamari',
            'soy sauce',
            'fish sauce',
            'tamarind paste',
            'miso',
            'paste',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isNutOrSeedIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        foreach ([
            'walnut',
            'almond',
            'cashew',
            'peanut',
            'pecan',
            'pistachio',
            'sunflower seed',
            'pumpkin seed',
            'sesame seed',
            'pine nut',
            'hazelnut',
            'macadamia',
        ] as $needle) {
            if (str_contains($name, $needle) && ! str_contains($name, 'butter')) {
                return true;
            }
        }

        return false;
    }

    public static function isCheeseIngredient(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        return (bool) preg_match(
            '/\b(cheese|parmesan|feta|halloumi|brie|cheddar|mozzarella|ricotta|paneer|yogurt)\b/',
            $name,
        );
    }

    /**
     * Potent woody/fresh sprigs — teaspoons at most, never five-gram vegetable steps.
     */
    public static function isWoodyFreshHerb(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        foreach (['rosemary', 'thyme', 'oregano', 'sage', 'bay leaf', 'bay leaves', 'tarragon'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Soft leafy herbs used as garnish/finish — whole grams, kitchen pinches.
     */
    public static function isSoftFreshHerb(Ingredient $ingredient): bool
    {
        if (self::isWoodyFreshHerb($ingredient)) {
            return false;
        }

        $name = strtolower(trim($ingredient->name));

        foreach (['coriander', 'cilantro', 'parsley', 'dill', 'mint', 'basil', 'chive'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dry powders / seasonings measured in pinches or teaspoons — whole grams only.
     * Fresh aromatics (garlic, ginger) use five-gram steps; fresh herbs use whole grams.
     */
    public static function isFineMeasureSpice(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, '(base)')) {
            return false;
        }

        if (self::isWoodyFreshHerb($ingredient) || self::isSoftFreshHerb($ingredient)) {
            return false;
        }

        if (
            str_contains($name, '(raw)')
            || str_contains($name, 'fresh')
            || str_contains($name, 'spring onion')
            || str_contains($name, 'shallot')
            || str_contains($name, 'bell pepper')
            || (str_contains($name, 'garlic') && ! str_contains($name, 'powder'))
            || (str_contains($name, 'ginger') && ! str_contains($name, 'ground'))
            || (str_contains($name, 'onion') && ! str_contains($name, 'powder'))
        ) {
            return false;
        }

        foreach ([
            'sea salt',
            'salt',
            'black pepper',
            'white pepper',
            'peppercorn',
            'cinnamon',
            'nutmeg',
            'paprika',
            'cumin',
            'oregano',
            'thyme',
            'rosemary',
            'turmeric',
            'chili flake',
            'chilli flake',
            'chili powder',
            'chilli powder',
            'clove',
            'cardamom',
            'saffron',
            'sumac',
            'za\'atar',
            'baking',
            'vanilla',
            'spice blend',
            'seasoning',
            'black seeds',
            'flaxseed',
            'flaxseeds',
            'mustard seed',
            'coriander seed',
            'fenugreek',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        // Bare "pepper" only when it is not a vegetable pepper.
        if (str_contains($name, 'pepper') && ! str_contains($name, 'bell') && ! str_contains($name, 'chili') && ! str_contains($name, 'chilli')) {
            return true;
        }

        return false;
    }

    public static function snapGramsForIngredient(Ingredient $ingredient, float $grams): float
    {
        if ($grams <= 0) {
            return 0.0;
        }

        if (self::isOilIngredient($ingredient) || self::isLiquidFatIngredient($ingredient)) {
            return self::snapOilGrams($grams);
        }

        if (self::isPourableCondiment($ingredient)) {
            // Keep small but real condiment portions (honey, vinegar) measurable — do not zero them.
            return self::snapFiveGramSteps($grams);
        }

        if (self::isNutOrSeedIngredient($ingredient)) {
            return self::snapNutGrams($grams);
        }

        if (self::isCheeseIngredient($ingredient)) {
            return self::snapCheeseGrams($grams);
        }

        if (self::isWoodyFreshHerb($ingredient)) {
            return self::snapFineSpiceGrams($grams);
        }

        if (self::isSoftFreshHerb($ingredient)) {
            return self::snapFineSpiceGrams($grams);
        }

        if (self::isFineMeasureSpice($ingredient)) {
            return self::snapFineSpiceGrams($grams);
        }

        return self::snapFiveGramSteps($grams);
    }

    /**
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function snapFatRoleGramsForMeal(Meal $meal, array $grams): array
    {
        $meal->loadMissing('ingredients');
        $adjusted = $grams;

        foreach ($meal->ingredients as $ingredient) {
            $role = MealScalingRole::roleForIngredient($ingredient, $meal);

            if (! in_array($role, [MealScalingRoleEnum::Fat, MealScalingRoleEnum::Sauce], true)) {
                continue;
            }

            if (! isset($adjusted[$ingredient->id])) {
                continue;
            }

            $baseline = (float) $adjusted[$ingredient->id];

            if ($baseline <= 0) {
                continue;
            }

            if (
                $role === MealScalingRoleEnum::Sauce
                && str_contains(strtolower($ingredient->name), '(base)')
            ) {
                continue;
            }

            $adjusted[$ingredient->id] = self::snapGramsForIngredient($ingredient, $baseline);
        }

        return $adjusted;
    }

    /**
     * Snap every ingredient amount on a meal to kitchen-realistic increments.
     *
     * @param  array<int, float>  $grams
     * @return array<int, float>
     */
    public static function snapAllGramsForMeal(Meal $meal, array $grams): array
    {
        $meal->loadMissing('ingredients');
        $adjusted = $grams;

        foreach ($meal->ingredients as $ingredient) {
            if (! isset($adjusted[$ingredient->id])) {
                continue;
            }

            $baseline = (float) $adjusted[$ingredient->id];

            if ($baseline <= 0) {
                continue;
            }

            $adjusted[$ingredient->id] = self::snapGramsForIngredient($ingredient, $baseline);
        }

        return $adjusted;
    }
}
