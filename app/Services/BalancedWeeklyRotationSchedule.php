<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use InvalidArgumentException;

/**
 * Seven-day Balanced weekly plan: same slot roles every day, different meals per weekday.
 *
 * Breakfast 1 — vegan chia pudding (rotates). Breakfast 2 — egg-based savory (rotates).
 * Main 1 — chicken + carbs/veg. Main 2 — chicken salad. Main 3 — salmon and beef alternate daily.
 * Main 4 — vegan main (includes former legume-heavy side salads). Salad 1 — legume-free vegan side (rotates). Salad 2 — Classic Garden Salad.
 * Dessert 1 — dessert (rotates). Dessert 2 — Fruit Salad Bowl.
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
    public const CHIA_BREAKFASTS = [
        'Blueberry Walnut Chia Pudding',
        'Mango Pumpkin Seed Chia Pudding',
        'Spiced Crunch Chia Pudding',
        'Strawberry Almond Chia Pudding',
        'Peach Pecan Chia Pudding',
        'Raspberry Cacao Chia Pudding',
        'Cacao & Almond Chia',
    ];

    /** @var list<string> */
    public const EGG_BREAKFASTS = [
        'Mediterranean Omelet',
        'Deconstructed Shakshuka Skillet',
        'Hummus Egg Stack',
        'Kuku Sabzi Egg Muffins',
        'Sweet Potato Egg Hash',
        'Butternut Squash Fritters & Eggs',
        'Smashed Beans & Eggs',
    ];

    /** @var list<string> */
    public const CHICKEN_PLATE_MAINS = [
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
        'Grilled Chicken Chimichurri',
        'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini',
        'Pepper Chicken in Creamy Cajun Sauce w Roasted Potato',
        'Grilled Sumac Chicken Skewers w Zereshk & Turmeric Rice & Roasted Mixed Vegetables',
        'Grilled Chicken Tikka Salad w Quinoa & Cilantro Lime Dressing',
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

    /** @var list<string> */
    public const SALMON_MAINS = [
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        'Citrus Herb Salmon with Asparagus & Sweet Potato',
        'Grilled Salmon Mango Salsa',
    ];

    /** @var list<string> */
    public const BEEF_MAINS = [
        'Grilled Beef Steak Ratatouille & Saffron rice',
        'Beef Bibimbap',
        'Persian Herb Beef Stew',
        'Chili Beef Stuffed Peppers',
    ];

    /** @var list<string> Legume-free vegan side salads (slot 1). */
    public const VEGAN_SIDE_SALADS = [
        'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad',
        'Tomato Parsely Salad w Sumac Za’ater Dressing',
        'Citrus Beet Arugula Salad',
        'Shaved Fennel Rocca Salad',
        'Roasted Eggplant Rocca Salad',
        'Marinated Strawberry Beet Salad',
        'Coconut Grapefruit Salad',
    ];

    /** @var list<string> Vegan mains — includes legume-forward dishes moved from side rotation. */
    public const VEGAN_MAINS = [
        BalancedCanonicalMealRecipeRefiner::VEGAN_BUTTERNUT_PEANUT_STEW_NAME,
        'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini',
        'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread',
        'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'Vegan Curry Lentil Salad',
        'Spiced Cauliflower Chickpea Salad',
        'Thai Rainbow Peanut Salad',
    ];

    /** @var list<string> */
    public const DESSERTS = [
        BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        'Chocolate Orange Brownie',
        BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        'Apple Pie Balls',
        'Cinnamon Raisin Balls',
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
                1 => self::CHIA_BREAKFASTS[$index],
                2 => self::EGG_BREAKFASTS[$index],
                default => throw new InvalidArgumentException("Invalid breakfast slot index {$slotIndex}"),
            },
            MealPlanSlotType::Main => match ($slotIndex) {
                1 => self::CHICKEN_PLATE_MAINS[$index],
                2 => self::CHICKEN_SALAD_MAINS[$index],
                3 => self::alternatingFishOrBeefMainForDay($dayNumber),
                4 => self::VEGAN_MAINS[$index],
                default => throw new InvalidArgumentException("Invalid main slot index {$slotIndex}"),
            },
            MealPlanSlotType::Salad => match ($slotIndex) {
                1 => self::VEGAN_SIDE_SALADS[$index],
                default => throw new InvalidArgumentException("Invalid salad slot index {$slotIndex}"),
            },
            MealPlanSlotType::Dessert => match ($slotIndex) {
                1 => self::DESSERTS[$index],
                default => throw new InvalidArgumentException("Invalid dessert slot index {$slotIndex}"),
            },
            MealPlanSlotType::Soup => match ($slotIndex) {
                1 => self::ROTATING_SOUPS[$index],
                default => throw new InvalidArgumentException("Invalid soup slot index {$slotIndex}; slot 2 is fixed in FIXED_SLOT_MEALS"),
            },
        };
    }

    /**
     * Odd days salmon, even days beef — alternating through the week.
     */
    public static function alternatingFishOrBeefMainForDay(int $dayNumber): string
    {
        $pairIndex = intdiv($dayNumber - 1, 2);

        if ($dayNumber % 2 === 1) {
            return self::SALMON_MAINS[$pairIndex % count(self::SALMON_MAINS)];
        }

        return self::BEEF_MAINS[$pairIndex % count(self::BEEF_MAINS)];
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
            self::CHIA_BREAKFASTS,
            self::EGG_BREAKFASTS,
            self::CHICKEN_PLATE_MAINS,
            self::CHICKEN_SALAD_MAINS,
            self::SALMON_MAINS,
            self::BEEF_MAINS,
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
