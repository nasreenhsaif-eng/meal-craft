<?php

namespace App\Services;

use App\Enums\MealPlanSlotType;
use InvalidArgumentException;

/**
 * Seven-day Balanced weekly plan: same slot roles every day, different meals per weekday.
 *
 * Breakfast 1 — vegan chia pudding (rotates). Breakfast 2 — egg-based savory (rotates).
 * Main 1 — chicken + carbs/veg. Main 2 — chicken salad. Main 3 — salmon (Sun–Tue) or beef (Wed–Sat).
 * Main 4 — vegan main. Salad 1 — vegan side salad (rotates). Salad 2 — Classic Garden Salad.
 * Dessert 1 — dessert (rotates). Dessert 2 — Fruit Salad Bowl.
 * Soup 1 — vegan soup (Vegan Mushroom Soup). Soup 2 — bone broth (Bone Broth Cup). Same both every day.
 */
final class BalancedWeeklyRotationSchedule
{
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
            1 => self::VEGAN_SOUP,
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
        'Butternut Squash Fritters Eggs Marinara',
        'Smashed Beans & Eggs',
    ];

    /** @var list<string> */
    public const CHICKEN_PLATE_MAINS = [
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
        'Grilled Chicken Chimichurri',
        'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini',
        'Pepper Chicken in Creamy Cajun Sauce w Roasted Potato',
        'Roasted Chicken in Pomegranate & Sumac Sauce w Turmeric Rice',
        'Crispy Chicken Tikka bowl w Quinoa & Mint Sauce',
        'Cajun Chicken, Grilled Peppers & Onion Salad w Quinoa, Kale & Mustard Dressing',
    ];

    /** @var list<string> */
    public const CHICKEN_SALAD_MAINS = [
        'Grilled Rosemary Garlic Chicken Salad w Rocca & Red Pepper Dressing',
        'Rosemary Chicken Rocca Salad',
        'Turmeric Chicken Kale Salad',
        'Chicken Thai Mango Salad',
        'Tandoori Coconut Mint Salad',
        'Mediterranean Crunch Salad',
        'Tandoori Chicken Salad',
    ];

    /** @var list<string> */
    public const SALMON_MAINS = [
        'Baked Salmon with Fermented Chimichurri & Quinoa',
        'Citrus Herb Salmon',
        'Grilled Salmon Mango Salsa',
    ];

    /** @var list<string> */
    public const BEEF_MAINS = [
        'Grilled Beef Steak Ratatouille & Saffron rice',
        'Beef Bibimbap',
        'Persian Herb Beef Stew',
        'Chili Beef Stuffed Peppers',
    ];

    /** @var list<string> */
    public const VEGAN_MAINS = [
        'Vegan Butternut Squash, Lentil & Nut Stew w Brown Rice',
        'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini',
        'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread',
        'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing',
        'Vegan Mushroom Bowl',
        'Baked Eggplant Lentils Hummus',
        'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini',
    ];

    /** @var list<string> */
    public const VEGAN_SIDE_SALADS = [
        'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad',
        'Tomato Parsely Salad w Sumac Za’ater Dressing',
        'Vegan Curry Lentil Salad',
        'Spiced Cauliflower Chickpea Salad',
        'Citrus Beet Arugula Salad',
        'Shaved Fennel Rocca Salad',
        'Thai Rainbow Peanut Salad',
    ];

    /** @var list<string> */
    public const DESSERTS = [
        BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        'Chocolate Orange Brownie (N)',
        'Salted Caramel Chocolate Bar',
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
                3 => $dayNumber <= 3
                    ? self::SALMON_MAINS[$index]
                    : self::BEEF_MAINS[$dayNumber - 4],
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
            MealPlanSlotType::Soup => throw new InvalidArgumentException('Soup slots are fixed; use FIXED_SLOT_MEALS'),
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
