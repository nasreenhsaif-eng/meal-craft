<?php

namespace App\Services;

use App\Support\NutrientDailyRdi;

/**
 * Micro-dense ingredients and flexible swap targets for isocaloric recipe refinement.
 */
final class MicronutrientBoostCatalog
{
    /** @var list<string> */
    public const GREEN_BOOST_INGREDIENTS = [
        'Purslane',
        'Rocca',
        'Chard',
        'Bok Choy',
        'Beetroot',
        'Kale',
        'Spinach (Fresh)',
    ];

    /** Sweet-breakfast chia enrichments only: nuts, seeds, herbs, and spices — never legumes or greens. */
    /** @var list<string> */
    public const CHIA_ALLOWED_BOOSTS = [
        'Tahini',
        'Sesame Seeds',
        'Pumpkin Seeds',
        'Black Seeds',
        'Walnuts',
        'Almond whole',
        'Pecans',
        'Almond Butter',
        'Fresh Mint',
        'Cinnamon',
        'Ground Ginger',
        'Clove',
        'Cocoa Powder',
        'Cacao Nibs',
    ];

    /**
     * Per-nutrient priority for chia breakfasts (subset of {@see CHIA_ALLOWED_BOOSTS}).
     *
     * @var array<string, list<string>>
     */
    public const CHIA_BOOST_BY_NUTRITION_KEY = [
        'calcium' => ['Tahini', 'Sesame Seeds', 'Almond whole', 'Almond Butter'],
        'magnesium' => ['Pumpkin Seeds', 'Tahini', 'Walnuts', 'Almond whole', 'Sesame Seeds'],
        'iron' => ['Pumpkin Seeds', 'Tahini', 'Sesame Seeds', 'Walnuts', 'Almond whole'],
        'fiber' => ['Pumpkin Seeds', 'Walnuts', 'Almond whole', 'Sesame Seeds'],
        'zinc' => ['Pumpkin Seeds', 'Sesame Seeds', 'Walnuts'],
        'vitamin_e' => ['Walnuts', 'Almond whole', 'Pumpkin Seeds'],
        'vitamin_k2' => ['Tahini', 'Sesame Seeds'],
    ];

    public const SPINACH_BOOST_CAP_GRAMS = 40.0;

    /** @var list<string> */
    public const BOOST_INGREDIENTS = [
        'Purslane',
        'Rocca',
        'Chard',
        'Bok Choy',
        'Beetroot',
        'Kale',
        'Spinach (Fresh)',
        'Tahini',
        'Sesame Seeds',
        'Pumpkin Seeds',
        'Chickpeas',
        'Broccoli',
        'Sweet Potato',
        'Carrots',
        'Bell Pepper (Red)',
        'Walnuts',
        'French Lentils',
        'Lentils (Red)',
    ];

    /**
     * Nutrition keys ranked by preferred boost ingredients (first = highest priority).
     *
     * @var array<string, list<string>>
     */
    public const BOOST_BY_NUTRITION_KEY = [
        'iron' => ['Beef Liver', 'Chicken Liver', 'Purslane', 'Chard', 'Bok Choy', 'Rocca', 'Beetroot', 'Kale', 'Pumpkin Seeds', 'Chickpeas', 'French Lentils', 'Lentils (Red)', 'Spinach (Fresh)'],
        'potassium' => ['Purslane', 'Beetroot', 'Bok Choy', 'Chard', 'Sweet Potato', 'Kale', 'Broccoli', 'Carrots', 'Chickpeas', 'Spinach (Fresh)'],
        'calcium' => ['Tahini', 'Sesame Seeds', 'Purslane', 'Rocca', 'Chard', 'Bok Choy', 'Kale', 'Chickpeas', 'Spinach (Fresh)'],
        'b9_folate' => ['Beef Liver', 'Chicken Liver', 'Purslane', 'Chard', 'Rocca', 'Kale', 'Chickpeas', 'French Lentils', 'Lentils (Red)', 'Spinach (Fresh)'],
        'vitamin_c' => ['Bell Pepper (Red)', 'Broccoli', 'Purslane', 'Bok Choy', 'Kale', 'Spinach (Fresh)'],
        'vitamin_a' => ['Beef Liver', 'Chicken Liver', 'Purslane', 'Chard', 'Beetroot', 'Sweet Potato', 'Carrots', 'Kale', 'Spinach (Fresh)'],
        'fiber' => ['Chickpeas', 'Purslane', 'Chard', 'Bok Choy', 'Beetroot', 'Kale', 'Broccoli', 'French Lentils', 'Spinach (Fresh)'],
        'magnesium' => ['Purslane', 'Pumpkin Seeds', 'Chard', 'Bok Choy', 'Kale', 'Walnuts', 'Chickpeas', 'Spinach (Fresh)'],
        'zinc' => ['Pumpkin Seeds', 'Chickpeas', 'Lentils (Red)', 'French Lentils'],
        'vitamin_e' => ['Walnuts', 'Purslane', 'Pumpkin Seeds', 'Spinach (Fresh)'],
        'vitamin_k2' => ['Beef Liver', 'Chicken Liver', 'Egg', 'Tahini', 'Sesame Seeds', 'Salmon', 'Beef Chuck Roast', 'Beef Ground Lean'],
        'b6' => ['Chickpeas', 'Purslane', 'Chard', 'Sweet Potato', 'French Lentils', 'Spinach (Fresh)'],
        'b12' => ['Beef Liver', 'Chicken Liver', 'Salmon', 'Beef Chuck Roast', 'Beef Ground Lean', 'Sardines (Canned)', 'Mackerel', 'Egg'],
    ];

    /**
     * Ingredient name substrings treated as anchors (not reduced during isocaloric swaps).
     *
     * @var list<string>
     */
    public const ANCHOR_NAME_PATTERNS = [
        'Chicken',
        'Salmon',
        'Beef',
        'Liver',
        'Shrimp',
        'Egg',
        'Chia',
        'Tofu',
        'Tempeh',
        '(Base)',
        'Dressing (Base)',
        'Broth Cup',
        'Brownie',
        'Chocolate Bar',
        'Muffin',
        'Balls',
    ];

    /**
     * @return list<string>
     */
    public static function boostIngredientsForKey(string $nutritionKey): array
    {
        return self::BOOST_BY_NUTRITION_KEY[$nutritionKey] ?? self::BOOST_INGREDIENTS;
    }

    /**
     * @return list<string>
     */
    public static function chiaBoostIngredientsForKey(string $nutritionKey): array
    {
        $candidates = self::CHIA_BOOST_BY_NUTRITION_KEY[$nutritionKey] ?? self::CHIA_ALLOWED_BOOSTS;

        return array_values(array_filter(
            $candidates,
            fn (string $name): bool => in_array($name, self::CHIA_ALLOWED_BOOSTS, true),
        ));
    }

    public static function isChiaAllowedBoost(string $ingredientName): bool
    {
        return in_array($ingredientName, self::CHIA_ALLOWED_BOOSTS, true);
    }

    /**
     * Liver enrichment: dedicated liver dishes, or minced/blended into ground beef only.
     *
     * @param  array<string, float>  $ingredientGrams
     */
    public static function allowsLiverBoost(?string $mealName, array $ingredientGrams): bool
    {
        if ($mealName !== null && str_contains($mealName, 'Liver')) {
            return true;
        }

        foreach (array_keys($ingredientGrams) as $ingredientName) {
            if (str_contains($ingredientName, 'Beef Ground')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    public static function allowsBeefLiverBoost(?string $mealName, array $ingredientGrams): bool
    {
        return self::allowsLiverBoost($mealName, $ingredientGrams);
    }

    public static function isGreenBoostIngredient(string $ingredientName): bool
    {
        return in_array($ingredientName, self::GREEN_BOOST_INGREDIENTS, true);
    }

    public static function isAnchorIngredient(string $ingredientName): bool
    {
        foreach (self::ANCHOR_NAME_PATTERNS as $pattern) {
            if (str_contains($ingredientName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function nutritionKeyForLabel(string $label): ?string
    {
        foreach (NutrientDailyRdi::NUTRITION_KEY_TO_LABEL as $key => $mappedLabel) {
            if ($mappedLabel === $label) {
                return $key;
            }
        }

        return null;
    }
}
