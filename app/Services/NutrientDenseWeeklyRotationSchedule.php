<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use InvalidArgumentException;

/**
 * Seven-day Nutrient Density weekly plan: micro-first rotation with daily fish, fermented anchors, and whole-egg breakfasts.
 *
 * Main 1 — chicken plate. Main 2 — chicken/beef salad. Main 3 — fish (mixed).
 * Main 4 — vegan. Main 5 — liver (dedicated or blend). Main 6 — plain beef meal.
 */
final class NutrientDenseWeeklyRotationSchedule
{
    public const PROTOCOL_SLUG = 'nutrient_dense';

    /** @var list<string> Mains with egg garnish or binder — max one per day, ≤3 days/week in rotation. */
    public const EGG_MAIN_NAMES = [
        'Beef Bibimbap',
        'Beef & Liver Kefta w Herb Salad & Tahini',
        'Spiced Beef & Liver Meatballs w Roasted Tomato Couscous',
    ];

    /** @var list<string> */
    public const ROTATING_SOUPS = [
        'Miso Mushroom Soup',
        'Red Lentil Turmeric Soup',
        'Miso Carrot Ginger Soup',
        'Sweet Potato Fennel Soup',
        'Miso Mushroom Soup',
        'Carrot Cumin Soup',
        'Miso Carrot Ginger Soup',
    ];

    /** @var array<string, array<int, string>> */
    public const FIXED_SLOT_MEALS = [
        MealPlanSlotType::Salad->value => [
            2 => 'Classic Garden Salad',
        ],
        MealPlanSlotType::Dessert->value => [
            2 => 'Fruit Salad Bowl',
        ],
        MealPlanSlotType::Soup->value => [
            2 => NutrientDenseMealLibraryConfigurator::BONE_BROTH_MEAL_NAME,
        ],
    ];

    /** @var list<string> Daily rotating baked desserts (slot 1) — one unique meal per weekday. */
    public const NUTRIENT_DENSE_DESSERTS = [
        'Chocolate Orange Brownie',
        BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'Saffron Pumpkin Muffin',
        'Chocolate PB Banana Muffin',
        'Apple Pie Balls',
        'Banana Blueberry Balls',
    ];

    /** @var list<string> Greek yogurt chia desserts — dessert slot 3 (one per weekday). */
    public const CHIA_DESSERTS = [
        'Blueberry Walnut Greek Yogurt Chia Pudding',
        'Mango Pumpkin Seed Greek Yogurt Chia Pudding',
        'Spiced Crunch Greek Yogurt Chia Pudding',
        'Strawberry Almond Greek Yogurt Chia Pudding',
        'Peach Pecan Greek Yogurt Chia Pudding',
        'Raspberry Cacao Greek Yogurt Chia Pudding',
        'Cacao & Almond Greek Yogurt Chia',
    ];

    /** @var list<string> Baked desserts — psyllium husk fiber enrichment allowed. */
    public const BAKED_DESSERTS = [
        'Chocolate Orange Brownie',
        BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'Saffron Pumpkin Muffin',
        'Chocolate PB Banana Muffin',
        'Apple Pie Balls',
        'Banana Blueberry Balls',
    ];

    /** @var list<string> Whole-egg breakfasts — min 2 eggs per serving. */
    public const EGG_BREAKFASTS = [
        'Mediterranean Omelet',
        'Deconstructed Shakshuka Skillet',
        'Hummus Egg Stack',
        NutrientDenseFermentedRecipeRefiner::KEFIR_TURKISH_EGGS_NAME,
        'Sweet Potato Egg Hash',
        'Butternut Squash & Eggs',
        'Smashed Beans & Eggs',
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

    /** @var list<string> Fish daily — salmon, mackerel, sardine rotation. */
    public const FISH_MAINS = [
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        'Grilled Mackerel w Roasted Vegetables',
        NutrientDenseFermentedRecipeRefiner::SARDINE_MAIN_NAME,
        'Citrus Herb Salmon with Asparagus & Sweet Potato',
        'Salmon Cashew Cream & Roasted Mixed Vegetables',
        'Pan Seared Hamour',
        'Grilled Salmon Mango Salsa',
    ];

    /** @var list<string> */
    public const LIVER_MAINS = [
        'Seared Beef Liver w Roasted Beetroot, Chard & Chimichurri',
        NutrientDenseLiverMealRecipeRefiner::SAUTEED_CHICKEN_LIVER_NAME,
        'Beef & Liver Kefta w Herb Salad & Tahini',
        'Chili Beef Stuffed Peppers',
        'Eggplant & Ground Beef Stew w Quinoa Bread',
        'Spiced Beef & Liver Meatballs w Roasted Tomato Couscous',
        'Beef & Liver Stuffed Zucchini w Marinara & Basil',
    ];

    /** @var list<string> Plain beef mains (no liver) — main slot 6, one per weekday. */
    public const BEEF_MAINS = [
        'Grilled Beef Steak Ratatouille & Saffron rice',
        'Beef Bibimbap',
        'Persian Herb Beef Stew',
        'Beef Shawarma Platter',
        'Sumac Beef Baba Ghanoush',
        'Eggplant Beef Stew Quinoa Bread',
        'Okra Beef Curry',
    ];

    /** @var list<string> Micro-dense side salads — tahini + purslane/rocco + pepper. */
    public const MICRO_DENSE_SIDE_SALADS = [
        'Kimchi Purslane Side Salad',
        'Tahini Purslane Pepper Salad',
        'Sauerkraut & Rocca Salad',
        'Shaved Fennel Rocca Salad',
        'Citrus Beet Arugula Salad',
        'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad',
        'Roasted Eggplant Rocca Salad',
    ];

    /** @var list<string> Legume + green vegan mains. */
    public const VEGAN_MAINS = [
        BalancedCanonicalMealRecipeRefiner::VEGAN_BUTTERNUT_PEANUT_STEW_NAME,
        'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini',
        'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread',
        'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'Vegan Curry Lentil Salad',
        'Spiced Cauliflower Chickpea Salad',
        'Thai Rainbow Peanut Salad',
    ];

    /**
     * Fermented anchor component per weekday (for audit / documentation).
     *
     * @var array<int, string>
     */
    public const FERMENTED_ANCHOR_BY_DAY = [
        1 => 'fermented_chimichurri',
        2 => 'kimchi',
        3 => 'miso_soup',
        4 => 'kefir',
        5 => 'sauerkraut',
        6 => 'kimchi',
        7 => 'miso_tahini_dressing',
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
                1 => self::EGG_BREAKFASTS[$index],
                default => throw new InvalidArgumentException("Invalid breakfast slot index {$slotIndex}"),
            },
            MealPlanSlotType::Main => match ($slotIndex) {
                1 => self::CHICKEN_PLATE_MAINS[$index],
                2 => self::CHICKEN_SALAD_MAINS[$index],
                3 => self::FISH_MAINS[$index],
                4 => self::VEGAN_MAINS[$index],
                5 => self::LIVER_MAINS[$index],
                6 => self::BEEF_MAINS[$index],
                default => throw new InvalidArgumentException("Invalid main slot index {$slotIndex}"),
            },
            MealPlanSlotType::Salad => match ($slotIndex) {
                1 => self::MICRO_DENSE_SIDE_SALADS[$index],
                default => throw new InvalidArgumentException("Invalid salad slot index {$slotIndex}"),
            },
            MealPlanSlotType::Dessert => match ($slotIndex) {
                1 => self::NUTRIENT_DENSE_DESSERTS[$index],
                3 => self::CHIA_DESSERTS[$index],
                default => throw new InvalidArgumentException("Invalid dessert slot index {$slotIndex}"),
            },
            MealPlanSlotType::Soup => match ($slotIndex) {
                1 => self::ROTATING_SOUPS[$index],
                default => throw new InvalidArgumentException("Invalid soup slot index {$slotIndex}; slot 2 is fixed in FIXED_SLOT_MEALS"),
            },
        };
    }

    public static function fermentedAnchorForDay(int $dayNumber): string
    {
        return self::FERMENTED_ANCHOR_BY_DAY[max(1, min(7, $dayNumber))] ?? 'kimchi';
    }

    public static function mainContainsEgg(string $mainName): bool
    {
        return in_array($mainName, self::EGG_MAIN_NAMES, true);
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
            self::NUTRIENT_DENSE_DESSERTS,
            self::CHIA_DESSERTS,
            self::EGG_BREAKFASTS,
            self::CHICKEN_PLATE_MAINS,
            self::CHICKEN_SALAD_MAINS,
            self::FISH_MAINS,
            self::LIVER_MAINS,
            self::BEEF_MAINS,
            self::VEGAN_MAINS,
            self::MICRO_DENSE_SIDE_SALADS,
        ] as $list) {
            foreach ($list as $mealName) {
                $names[] = $mealName;
            }
        }

        return array_values(array_unique($names));
    }
}
