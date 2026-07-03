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
        'Okra',
        'Green Beans',
        'Zucchini',
        'Pumpkin',
        'Butternut Squash',
    ];

    /** Chia dessert enrichments only: nuts, seeds, herbs, and spices — never legumes, greens, or psyllium. */
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
     * Per-nutrient priority for chia desserts (subset of {@see CHIA_ALLOWED_BOOSTS}).
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
    ];

    /** Baked dessert enrichments: psyllium for fiber plus nuts, seeds, and warm spices. */
    /** @var list<string> */
    public const BAKED_DESSERT_ALLOWED_BOOSTS = [
        'Psyllium Husks',
        'Walnuts',
        'Almond whole',
        'Pumpkin Seeds',
        'Sesame Seeds',
        'Tahini',
        'Pecans',
        'Cinnamon',
        'Cocoa Powder',
        'Cacao Nibs',
    ];

    /**
     * Per-nutrient priority for baked desserts (subset of {@see BAKED_DESSERT_ALLOWED_BOOSTS}).
     *
     * @var array<string, list<string>>
     */
    public const BAKED_DESSERT_BOOST_BY_NUTRITION_KEY = [
        'fiber' => ['Psyllium Husks', 'Walnuts', 'Pumpkin Seeds', 'Almond whole'],
        'calcium' => ['Tahini', 'Sesame Seeds', 'Almond whole'],
        'magnesium' => ['Pumpkin Seeds', 'Tahini', 'Walnuts', 'Almond whole', 'Sesame Seeds'],
        'iron' => ['Pumpkin Seeds', 'Tahini', 'Sesame Seeds', 'Walnuts'],
        'zinc' => ['Pumpkin Seeds', 'Sesame Seeds', 'Walnuts'],
        'vitamin_e' => ['Walnuts', 'Almond whole', 'Pumpkin Seeds'],
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
        'Okra',
        'Green Beans',
        'Zucchini',
        'Pumpkin',
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
        'fiber' => ['Psyllium Husks', 'Okra', 'Green Beans', 'French Lentils', 'Lentils (Red)', 'Chickpeas', 'Purslane', 'Chard', 'Bok Choy', 'Beetroot', 'Kale', 'Broccoli', 'Zucchini', 'Pumpkin', 'Spinach (Fresh)'],
        'magnesium' => ['Purslane', 'Pumpkin Seeds', 'Chard', 'Bok Choy', 'Kale', 'Walnuts', 'Chickpeas', 'Spinach (Fresh)'],
        'zinc' => ['Pumpkin Seeds', 'Chickpeas', 'Lentils (Red)', 'French Lentils'],
        'vitamin_e' => ['Walnuts', 'Purslane', 'Pumpkin Seeds', 'Spinach (Fresh)'],
        'vitamin_k2' => ['Beef Liver', 'Chicken Liver', 'Egg', 'Eggs (Large)', 'Ghee', 'Ghee (Clarified)', 'Salmon', 'Sardines (Canned)', 'Mackerel', 'Beef Chuck Roast', 'Beef Ground Lean'],
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

    /**
     * @return list<string>
     */
    public static function bakedDessertBoostIngredientsForKey(string $nutritionKey): array
    {
        $candidates = self::BAKED_DESSERT_BOOST_BY_NUTRITION_KEY[$nutritionKey] ?? self::BAKED_DESSERT_ALLOWED_BOOSTS;

        return array_values(array_filter(
            $candidates,
            fn (string $name): bool => in_array($name, self::BAKED_DESSERT_ALLOWED_BOOSTS, true),
        ));
    }

    public static function isBakedDessertAllowedBoost(string $ingredientName): bool
    {
        return in_array($ingredientName, self::BAKED_DESSERT_ALLOWED_BOOSTS, true);
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

    /**
     * Pick the best boost ingredient already present in the recipe — never introduce new lines.
     *
     * @param  list<string>  $candidates
     * @param  array<string, float>  $ingredientGrams
     */
    public static function selectBestBoostCandidate(array $candidates, array $ingredientGrams): ?string
    {
        if ($candidates === []) {
            return null;
        }

        $greenCandidates = array_values(array_filter(
            $candidates,
            fn (string $candidate): bool => self::isGreenBoostIngredient($candidate)
                && ($ingredientGrams[$candidate] ?? 0) > 0,
        ));

        if ($greenCandidates !== []) {
            usort(
                $greenCandidates,
                fn (string $a, string $b): int => ($ingredientGrams[$a] ?? 0) <=> ($ingredientGrams[$b] ?? 0),
            );

            return $greenCandidates[0];
        }

        foreach ($candidates as $candidate) {
            if (($ingredientGrams[$candidate] ?? 0) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    /** @var list<string> */
    public const NUTRIENT_DENSE_FERMENTED_BOOSTS = [
        'Miso Paste',
        'Kimchi',
        'Sauerkraut',
        'Kefir',
    ];

    /** @var array<string, list<string>> */
    public const NUTRIENT_DENSE_FERMENTED_BOOST_BY_NUTRITION_KEY = [
        'b9_folate' => ['Miso Paste', 'Kimchi', 'Sauerkraut'],
        'vitamin_c' => ['Kimchi', 'Sauerkraut'],
        'fiber' => ['Kimchi', 'Sauerkraut', 'Miso Paste', 'Okra', 'Green Beans'],
        'magnesium' => ['Miso Paste', 'Kefir'],
        'calcium' => ['Kefir'],
        'b12' => ['Kefir'],
        'iron' => ['Miso Paste'],
    ];

    public const NUTRIENT_DENSE_DESSERT_BOOSTS = self::BAKED_DESSERT_ALLOWED_BOOSTS;

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
