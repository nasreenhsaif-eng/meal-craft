<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Meal Craft customer plans are dairy-free, gluten-free, soy-free, oat-free, and whole-food only.
 * No supplements, canned goods, or jarred pantry shortcuts — house-made (Base) prep is allowed.
 *
 * Exceptions:
 * - Ghee (clarified butter) is permitted.
 * - Almond flour is permitted only as house-made {@see isHouseAlmondFlour()}.
 * - Olives are permitted in small portions (histamine / brine sensitivity).
 * - Tamarind paste, rice vinegar, and unsweetened almond butter (no preservatives) are permitted pantry staples.
 */
final class WholeFoodDietPolicy
{
    /**
     * Library ingredients vetted as single-ingredient pantry staples (no added sugar or preservatives).
     *
     * @var list<string>
     */
    public const ALLOWED_PANTRY_NAMES = [
        'Tamarind Paste',
        'Rice Vinegar',
        'Almond Butter',
        'Date Syrup',
        'Coconut Cream',
    ];

    /** @var list<string> */
    public const BANNED_INGREDIENT_NAMES = [
        'Protein Powder (Isolate)',
        'Oats (Rolled)',
        'Parmesan',
        'Cheddar Cheese',
        'Goat Cheese',
        'Greek Yogurt',
        'Heavy Cream',
        'Milk',
        'Almond Milk (Unsweetened)',
        'Coconut Milk',
        'Coconut Milk (Full Fat)',
        'Soy Sauce',
        'Tamari',
        'Tamari Sauce',
        'Tofu (Firm)',
        'Tempeh',
        'Miso',
        'Miso Paste',
        'Edamame',
        'Wheat Flour',
        'Nutritional Yeast',
        'Tomato Paste',
        'Wasabi (Paste)',
        'Sardines (Canned)',
        'Tuna (Canned)',
        'Vanilla Extract',
        'Dark Chocolate Chips',
        'Baking Powder',
        'Chicken Broth',
    ];

    /** @var list<string> */
    public const ALLOWED_DAIRY_EXCEPTION_NAMES = [
        'Ghee',
        'Ghee (Clarified)',
    ];

    /** @var list<string> */
    public const OLIVE_INGREDIENT_NAMES = [
        'Kalamata Olives',
    ];

    public const MAX_OLIVE_GRAMS_PER_MEAL = 15.0;

    /** @var list<string> */
    public const REQUIRED_MEAL_DIET_TAGS = [
        'Dairy-free',
        'Gluten-free',
    ];

    public static function isBannedIngredientName(string $name): bool
    {
        $name = trim($name);

        if (in_array($name, self::ALLOWED_DAIRY_EXCEPTION_NAMES, true)) {
            return false;
        }

        if (self::isHouseAlmondFlourName($name)) {
            return false;
        }

        if (in_array($name, self::ALLOWED_PANTRY_NAMES, true)) {
            return false;
        }

        return in_array($name, self::BANNED_INGREDIENT_NAMES, true);
    }

    public static function isHouseAlmondFlourName(string $name): bool
    {
        return $name === 'Almond Flour (Base)';
    }

    public static function isHouseAlmondFlour(Ingredient $ingredient): bool
    {
        return self::isHouseAlmondFlourName($ingredient->name)
            && self::isHouseBaseIngredient($ingredient);
    }

    public static function isStoreBoughtAlmondFlour(Ingredient $ingredient): bool
    {
        return $ingredient->name === 'Almond Flour';
    }

    public static function isBannedIngredient(Ingredient $ingredient): bool
    {
        if (in_array($ingredient->name, self::ALLOWED_DAIRY_EXCEPTION_NAMES, true)) {
            return false;
        }

        if (self::isHouseBaseIngredient($ingredient)) {
            return false;
        }

        if (self::isStoreBoughtAlmondFlour($ingredient)) {
            return true;
        }

        if (self::isBannedIngredientName($ingredient->name)) {
            return true;
        }

        $category = strtolower(trim((string) $ingredient->usda_food_category));

        return in_array($category, ['dairy', 'supplements'], true);
    }

    public static function isHouseBaseIngredient(Ingredient $ingredient): bool
    {
        return str_ends_with($ingredient->name, '(Base)')
            && strcasecmp(trim((string) $ingredient->usda_food_category), 'Base Ingredient') === 0;
    }

    public static function oliveGramsInMeal(Meal $meal): float
    {
        $meal->loadMissing('ingredients');

        $grams = 0.0;

        foreach ($meal->ingredients as $ingredient) {
            if (! in_array($ingredient->name, self::OLIVE_INGREDIENT_NAMES, true)) {
                continue;
            }

            $grams += (float) ($ingredient->pivot->amount_grams ?? 0);
        }

        return $grams;
    }

    /**
     * @return list<string>
     */
    public static function violationsForMeal(Meal $meal): array
    {
        $meal->loadMissing('ingredients');

        $violations = [];

        foreach ($meal->ingredients as $ingredient) {
            if (self::isBannedIngredient($ingredient)) {
                $violations[] = "{$meal->name}: banned ingredient «{$ingredient->name}»";
            }
        }

        $oliveGrams = self::oliveGramsInMeal($meal);

        if ($oliveGrams > self::MAX_OLIVE_GRAMS_PER_MEAL) {
            $violations[] = "{$meal->name}: olive portion too high ({$oliveGrams}g; max ".self::MAX_OLIVE_GRAMS_PER_MEAL.'g per meal)';
        }

        $tags = is_array($meal->diet_tags) ? $meal->diet_tags : [];

        foreach (self::REQUIRED_MEAL_DIET_TAGS as $required) {
            if (! in_array($required, $tags, true)) {
                $violations[] = "{$meal->name}: missing diet tag «{$required}»";
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function violationsForMeals(iterable $meals): array
    {
        $violations = [];

        foreach ($meals as $meal) {
            if (! $meal instanceof Meal) {
                continue;
            }

            array_push($violations, ...self::violationsForMeal($meal));
        }

        return $violations;
    }
}
