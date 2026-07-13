<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use InvalidArgumentException;

/**
 * Seven-day Balanced weekly plan: same slot roles every day, different meals per weekday.
 *
 * Breakfast — savory egg (rotates).
 * Main 1 — chicken + carbs/veg. Main 2 — chicken salad. Main 3 — fish (mixed; rotates daily).
 * Main 4 — plain beef meal (no liver; rotates daily). Main 5 — liver (dedicated or liver-blend; rotates daily).
 * Main 6 — vegan main (includes former legume-heavy side salads).
 * Salad 1 — legume-free vegan side (rotates). Salad 2 — Classic Garden Salad.
 * Dessert 1 — baked dessert (rotates). Dessert 2 — Fruit Salad Bowl. Dessert 3 — Greek yogurt chia pudding (rotates; dairy-free customers resolve to coconut chia).
 * Soup 1 — rotating soup. Soup 2 — Bone Broth Cup (fixed every day).
 */
final class BalancedWeeklyRotationSchedule
{
    /** @var list<string> */
    public const ROTATING_SOUPS = [
        'Vegan Mushroom Soup',
        'Butternut Squash Soup',
        'Tomato Basil Soup',
        'Red Lentil Turmeric Soup',
        'Cauliflower Ginger Soup',
        'Carrot Cumin Soup',
        'Sweet Potato Fennel Soup',
    ];

    /** @deprecated Use {@see ROTATING_SOUPS} */
    public const VEGAN_SOUP = 'Vegan Mushroom Soup';

    /** @var array<string, array<int, string>> */
    public const FIXED_SLOT_MEALS = [
        MealPlanSlotType::Salad->value => [
            2 => 'Classic Garden Salad',
        ],
        MealPlanSlotType::Dessert->value => [
            2 => 'Fruit Salad Bowl',
        ],
        MealPlanSlotType::Soup->value => [
            2 => BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME,
        ],
    ];

    /** @var list<string> */
    public const CHIA_DESSERTS = [
        'Blueberry Walnut Chia Pudding',
        'Mango Pumpkin Seed Chia Pudding',
        'Spiced Crunch Chia Pudding',
        'Strawberry Almond Chia Pudding',
        'Peach Pecan Chia Pudding',
        'Raspberry Cacao Chia Pudding',
        'Cacao & Almond Chia',
    ];

    /** @var list<string> */
    public const GREEK_YOGURT_CHIA_DESSERTS = [
        'Blueberry Walnut Greek Yogurt Chia Pudding',
        'Mango Pumpkin Seed Greek Yogurt Chia Pudding',
        'Spiced Crunch Greek Yogurt Chia Pudding',
        'Strawberry Almond Greek Yogurt Chia Pudding',
        'Peach Pecan Greek Yogurt Chia Pudding',
        'Raspberry Cacao Greek Yogurt Chia Pudding',
        'Cacao & Almond Greek Yogurt Chia',
    ];

    /** @var list<string> Dairy-free egg breakfasts when customer filters dairy. */
    public const EGG_BREAKFASTS = [
        'Mediterranean Omelet',
        'Deconstructed Shakshuka Skillet',
        'Hummus Egg Stack',
        'Kuku Sabzi Egg Muffins',
        'Sweet Potato Egg Hash',
        'Butternut Squash & Eggs',
        'Smashed Beans & Eggs',
    ];

    /** @var list<string> High-protein dairy + egg breakfasts when dairy is allowed. */
    public const DAIRY_FORWARD_EGG_BREAKFASTS = [
        'Gouda & Spinach Scramble',
        'Greek Yogurt & Parmesan Frittata',
        'Feta & Herb Open Omelet',
        'Brie & Mushroom Skillet Eggs',
        'Parmesan Shakshuka',
        'Butternut Squash Frittata',
        'Feta & Dill Egg Muffins',
    ];

    /** @var list<string> */
    public const CHICKEN_PLATE_MAINS = [
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
        'Rosemary Garlic Chicken w Pomegranate Glaze, Beetroot & Rocca',
        'Grilled Chicken Chimichurri',
        'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini',
        'Pepper Chicken in Creamy Cajun Sauce w Roasted Potato',
        'Grilled Sumac Chicken Skewers w Zereshk & Turmeric Rice & Roasted Mixed Vegetables',
    ];

    /** @var list<string> */
    public const CHICKEN_SALAD_MAINS = [
        'Rosemary Chicken Rocca Salad',
        'Turmeric Chicken Kale Salad',
        'Chicken Thai Mango Salad',
        'Tandoori Coconut Mint Salad',
        'Mediterranean Crunch Salad',
        'Tandoori Chicken Salad',
        'Blackened Chicken, Grilled Peppers & Onion Salad w Quinoa, Kale & Mustard Dressing',
    ];

    /** @var list<string> Salmon subset — still used by micronutrient refiners. */
    public const SALMON_MAINS = [
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        'Citrus Herb Salmon with Asparagus & Sweet Potato',
        'Grilled Salmon Mango Salsa',
    ];

    /** @var list<string> Fish daily — salmon, mackerel, sardine rotation (main slot 3). */
    public const FISH_MAINS = [
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        'Grilled Mackerel w Roasted Vegetables',
        NutrientDenseFermentedRecipeRefiner::SARDINE_MAIN_NAME,
        'Citrus Herb Salmon with Asparagus & Sweet Potato',
        'Salmon Cashew Cream & Roasted Mixed Vegetables',
        'Pan Seared Hamour',
        'Grilled Salmon Mango Salsa',
    ];

    /** @var list<string> Plain beef mains — main slot 4, one per weekday. */
    public const BEEF_MAINS = [
        'Grilled Beef Steak Ratatouille & Saffron rice',
        'Beef Bibimbap',
        'Persian Herb Beef Stew',
        'Beef Shawarma Platter',
        'Sumac Beef Baba Ghanoush',
        'Eggplant Beef Stew Quinoa Bread',
        'Okra Beef Curry',
    ];

    /** @var list<string> Liver mains — slot 5, one per weekday. */
    public const LIVER_MAINS = [
        'Seared Beef Liver w Roasted Beetroot, Chard & Chimichurri',
        NutrientDenseLiverMealRecipeRefiner::SAUTEED_CHICKEN_LIVER_NAME,
        'Beef & Liver Kefta w Herb Salad & Tahini',
        'Chili Beef Stuffed Peppers',
        'Eggplant & Ground Beef Stew w Quinoa Bread',
        NutrientDenseLiverMealRecipeRefiner::PERI_PERI_CHICKEN_LIVER_NAME,
        'Spiced Beef & Liver Meatballs w Roasted Tomato Couscous',
    ];

    /** @var list<string> Legume-free vegan side salads (slot 1). */
    public const VEGAN_SIDE_SALADS = [
        'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad',
        'Tomato Parsely Salad w Sumac Za’ater Dressing',
        'Citrus Beet Arugula Salad',
        'Shaved Fennel Rocca Salad',
        'Roasted Eggplant Rocca Salad',
        'Marinated Strawberry Beet Salad',
        'Thai Rainbow Peanut Salad',
    ];

    /** @var list<string> Vegan mains — legume-forward plant meals. */
    public const VEGAN_MAINS = [
        BalancedCanonicalMealRecipeRefiner::VEGAN_BUTTERNUT_PEANUT_STEW_NAME,
        'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini',
        'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread',
        'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'Vegan Curry Lentil Salad',
        'Spiced Cauliflower Chickpea Salad',
        'Vegan Mushroom Bowl',
    ];

    /** @var list<string> Daily rotating baked desserts (slot 1) — one unique meal per weekday. */
    public const DESSERTS = [
        BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        'Chocolate Orange Brownie',
        BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'Apple Pie Balls',
        'Banana Blueberry Balls',
        'Saffron Pumpkin Muffin',
        'Chocolate PB Banana Muffin',
    ];

    public static function mealNameForDay(int $dayNumber, MealPlanSlotType $slotType, int $slotIndex): string
    {
        if ($dayNumber < 1 || $dayNumber > 7) {
            throw new InvalidArgumentException("Day number must be 1–7, got {$dayNumber}");
        }

        $fixed = self::FIXED_SLOT_MEALS[$slotType->value][$slotIndex] ?? null;

        if ($fixed !== null) {
            return $fixed;
        }

        $index = $dayNumber - 1;

        return match ($slotType) {
            MealPlanSlotType::Breakfast => match ($slotIndex) {
                1 => self::DAIRY_FORWARD_EGG_BREAKFASTS[$index],
                default => throw new InvalidArgumentException("Invalid breakfast slot index {$slotIndex}"),
            },
            MealPlanSlotType::Main => match ($slotIndex) {
                1 => self::CHICKEN_PLATE_MAINS[$index],
                2 => self::CHICKEN_SALAD_MAINS[$index],
                3 => self::FISH_MAINS[$index],
                4 => self::BEEF_MAINS[$index],
                5 => self::LIVER_MAINS[$index],
                6 => self::VEGAN_MAINS[$index],
                default => throw new InvalidArgumentException("Invalid main slot index {$slotIndex}"),
            },
            MealPlanSlotType::Salad => match ($slotIndex) {
                1 => self::VEGAN_SIDE_SALADS[$index],
                default => throw new InvalidArgumentException("Invalid salad slot index {$slotIndex}"),
            },
            MealPlanSlotType::Dessert => match ($slotIndex) {
                1 => self::DESSERTS[$index],
                3 => self::GREEK_YOGURT_CHIA_DESSERTS[$index],
                default => throw new InvalidArgumentException("Invalid dessert slot index {$slotIndex}"),
            },
            MealPlanSlotType::Soup => match ($slotIndex) {
                1 => self::ROTATING_SOUPS[$index],
                default => throw new InvalidArgumentException("Invalid soup slot index {$slotIndex}; slot 2 is fixed in FIXED_SLOT_MEALS"),
            },
        };
    }

    /**
     * @return list<string>
     */
    public static function allScheduledMealNames(): array
    {
        $names = [];

        foreach (self::FIXED_SLOT_MEALS as $byIndex) {
            foreach ($byIndex as $mealName) {
                $names[] = $mealName;
            }
        }

        foreach ([
            self::ROTATING_SOUPS,
            self::CHIA_DESSERTS,
            self::GREEK_YOGURT_CHIA_DESSERTS,
            self::DAIRY_FORWARD_EGG_BREAKFASTS,
            self::EGG_BREAKFASTS,
            self::CHICKEN_PLATE_MAINS,
            self::CHICKEN_SALAD_MAINS,
            self::FISH_MAINS,
            self::SALMON_MAINS,
            self::BEEF_MAINS,
            self::LIVER_MAINS,
            self::VEGAN_MAINS,
            self::VEGAN_SIDE_SALADS,
            self::DESSERTS,
        ] as $list) {
            foreach ($list as $mealName) {
                $names[] = $mealName;
            }
        }

        return array_values(array_unique($names));
    }
}
