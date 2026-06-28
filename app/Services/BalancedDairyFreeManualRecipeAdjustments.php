<?php

namespace App\Services;

use App\Models\Meal;
use App\Support\MealLibraryEditGuard;

/**
 * Targeted dairy-free calcium / B12 / K2 gram boosts on rotation meals after automated refinement.
 */
final class BalancedDairyFreeManualRecipeAdjustments
{
    /** Typical liver blend: ~18–22 g minced liver mixed into ground beef only — not cubes, steaks, or chuck. */
    private const LIVER_BLEND_GRAMS = 20.0;

    /**
     * Meal name => ingredient grams (full replacement map for that meal's recipe).
     *
     * @var array<string, array<string, float>>
     */
    private const MEAL_GRAM_MAPS = [
        'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad' => [
            'Bell Pepper (Red)' => 20.0,
            'Cabbage (Purple)' => 32.0,
            'Cucumber' => 28.0,
            'Fresh Coriander' => 4.0,
            'Pineapple' => 35.0,
            'Red Onion' => 10.0,
            'Red Thai Chillies' => 2.0,
            'Rocca' => 28.0,
            'Sesame Seeds' => 6.0,
            'Tahini' => 6.0,
            'Zesty Lime Chili Salad Dressing (Base)' => 10.0,
        ],
        'Shaved Fennel Rocca Salad' => [
            'Classic Lemon Garlic Dressing (Base)' => 12.0,
            'Fennel Bulb' => 55.0,
            'Orange Sections' => 35.0,
            'Pomegranate Seeds' => 12.0,
            'Rocca' => 65.0,
            'Sesame Seeds' => 8.0,
            'Walnuts' => 6.0,
        ],
        'Roasted Eggplant Rocca Salad' => [
            'Cherry Tomatoes' => 30.0,
            'Classic Lemon Garlic Dressing (Base)' => 12.0,
            'Eggplant' => 115.0,
            'Pomegranate Seeds' => 8.0,
            'Rocca' => 70.0,
            'Sesame Seeds' => 6.0,
        ],
        'Citrus Beet Arugula Salad' => [
            'Arugula' => 65.0,
            'Beetroot' => 70.0,
            'Classic Lemon Garlic Dressing (Base)' => 12.0,
            'Cucumber' => 25.0,
            'Fresh Mint' => 3.0,
            'Orange Sections' => 40.0,
            'Sesame Seeds' => 8.0,
            'Walnuts' => 8.0,
        ],
        'Beef Bibimbap' => [
            'Chard' => 15.0,
            'Beef Ground Lean' => 85.0,
            'Beef Liver' => self::LIVER_BLEND_GRAMS,
            'Carrots' => 40.0,
            'Cooked Quinoa (Base)' => 84.0,
            'Egg' => 55.0,
            'Garlic (Raw)' => 3.0,
            'Sesame Seeds' => 12.0,
            'Spinach (Fresh)' => 50.0,
            'Spring Onion' => 18.0,
            'Zucchini' => 40.0,
        ],
        'Chili Beef Stuffed Peppers' => [
            'Beef Ground Lean' => 92.0,
            'Beef Liver' => self::LIVER_BLEND_GRAMS,
            'Bell Pepper (Red)' => 105.0,
            'Chili Powder' => 2.0,
            'Cooked Brown Basmati Rice (Base)' => 138.0,
            'Garlic (Raw)' => 4.0,
            'Olive Oil' => 2.0,
            'Spinach (Fresh)' => 35.0,
            'Tomato (Raw)' => 55.0,
            'White Onion' => 28.0,
        ],
        'Persian Herb Beef Stew' => [
            'Beef Chuck Roast' => 124.0,
            'Cannellini Beans' => 70.0,
            'Dill (Fresh)' => 4.0,
            'Fresh Coriander' => 8.0,
            'Lemon Juice' => 8.0,
            'Olive Oil' => 2.0,
            'Quinoa Bread (Base)' => 32.5,
            'Spinach (Fresh)' => 35.0,
            'White Onion' => 28.0,
        ],
        'Spiced Crunch Chia Pudding' => [
            'Almond whole' => 3.0,
            'Black Seeds' => 2.0,
            'Cinnamon' => 1.5,
            'Clove' => 0.5,
            'Coconut Chia Pudding (Base)' => 75.0,
            'Ground Ginger' => 1.0,
            'Pumpkin Seeds' => 4.0,
            'Sesame Seeds' => 12.0,
        ],
        'Blueberry Walnut Chia Pudding' => [
            'Blueberries' => 7.0876,
            'Cinnamon' => 1.0,
            'Coconut Chia Pudding (Base)' => 75.0,
            'Fresh Mint' => 1.0,
            'Sesame Seeds' => 8.0,
            'Walnuts' => 5.0,
        ],
        'Mango Pumpkin Seed Chia Pudding' => [
            'Coconut Chia Pudding (Base)' => 75.0,
            'Fresh Mint' => 1.0,
            'Mango' => 13.1333,
            'Pumpkin Seeds' => 8.0,
            'Sesame Seeds' => 6.0,
        ],
        'Strawberry Almond Chia Pudding' => [
            'Almond whole' => 5.0,
            'Coconut Chia Pudding (Base)' => 75.0,
            'Sesame Seeds' => 10.0,
            'Strawberries' => 4.0,
        ],
        'Peach Pecan Chia Pudding' => [
            'Cinnamon' => 0.5,
            'Coconut Chia Pudding (Base)' => 75.0,
            'Fresh Mint' => 2.0,
            'Peach' => 6.6926,
            'Pecans' => 5.0,
            'Sesame Seeds' => 8.0,
        ],
        'Raspberry Cacao Chia Pudding' => [
            'Cacao Nibs' => 4.0,
            'Cocoa Powder' => 2.0,
            'Coconut Chia Pudding (Base)' => 75.0,
            'Pumpkin Seeds' => 4.0,
            'Raspberries' => 6.692,
            'Sesame Seeds' => 8.0,
        ],
        'Cacao & Almond Chia' => [
            'Almond Butter' => 2.2476,
            'Almond whole' => 5.0,
            'Cocoa Powder' => 2.0,
            'Coconut Chia Pudding (Base)' => 75.0,
            'Sesame Seeds' => 8.0,
        ],
        'Cinnamon Raisin Balls' => [
            'Almond Butter' => 22.0,
            'Almond whole' => 8.0,
            'Cinnamon' => 3.0,
            'Medjool Dates' => 14.0,
            'Raisins' => 8.0,
            'Walnuts' => 18.0,
        ],
        'Kuku Sabzi Egg Muffins' => [
            'Barberries' => 5.0,
            'Black Pepper' => 1.0,
            'Dill (Fresh)' => 4.0,
            'Egg' => 110.0,
            'Fresh Coriander' => 8.0,
            'Olive Oil' => 4.0,
            'Purslane' => 15.0,
            'Rocca' => 10.0,
            'Sea Salt' => 1.0,
            'Spinach (Fresh)' => 30.0,
            'Spring Onion' => 15.0,
            'Walnuts' => 8.0,
        ],
        'Hummus Egg Stack' => [
            'Black Pepper' => 1.0,
            'Cherry Tomatoes' => 45.0,
            'Creamy Cumin Hummus (Base)' => 100.0,
            'Cucumber' => 40.0,
            'Egg' => 100.0,
            'Olive Oil' => 3.0,
            'Spinach (Fresh)' => 45.0,
        ],
        'Sweet Potato Egg Hash' => [
            'Bell Pepper (Red)' => 30.0,
            'Black Pepper' => 1.0,
            'Egg' => 100.0,
            'Fresh Coriander' => 3.0,
            'Olive Oil' => 4.0,
            'Rosemary (Fresh)' => 2.0,
            'Sea Salt' => 1.0,
            'Sweet Potato' => 90.0,
            'Thyme (Fresh)' => 2.0,
            'White Onion' => 25.0,
        ],
        'Vegan Butternut Squash, Lentil & Peanut Stew w Brown Rice' => [
            'Chard' => 20.0,
            'Bell Pepper (Red)' => 30.0,
            'Black Pepper' => 0.5,
            'Butternut Squash' => 60.0,
            'Cabbage (Purple)' => 16.0,
            'Cherry Tomatoes' => 10.0,
            'Chili Flakes' => 0.5,
            'Cooked Brown Basmati Rice (Base)' => 113.0,
            'Fresh Coriander' => 4.0,
            'Garlic (Raw)' => 2.0,
            'Lentils (Red)' => 40.0,
            'Lime Juice' => 3.0,
            'Mushrooms' => 30.0,
            'Olive Oil' => 3.0,
            'Peanut Butter' => 10.0,
            'Peanuts (Crushed)' => 15.0,
            'Purslane' => 10.0,
            'Red Onion' => 30.0,
            'Sea Salt' => 0.5,
            'Spinach (Fresh)' => 16.0,
            'Tomato (Raw)' => 80.0,
            'Vegetable Stock' => 48.0,
            'Water (Filtered)' => 144.0,
            'Zucchini' => 30.0,
        ],
        'Seared Beef Liver w Caramelized Onion, Spinach & Chimichurri' => [
            'Beef Liver' => 50.0,
            'Black Pepper' => 1.0,
            'Fermented Chimichurri (Base)' => 20.0,
            'Garlic (Raw)' => 4.0,
            'Lemon Juice' => 10.0,
            'Olive Oil (Extra Virgin)' => 5.0,
            'Spinach (Fresh)' => 90.0,
            'Steamed Basmati Rice (Base)' => 65.0,
            'White Onion' => 45.0,
        ],
        'Beef & Liver Kefta w Herb Salad & Tahini' => [
            'Beef Ground Lean' => 70.0,
            'Beef Liver' => 22.0,
            'Cucumber' => 40.0,
            'Cumin Seeds' => 2.0,
            'Dill (Fresh)' => 5.0,
            'Fresh Coriander' => 10.0,
            'Garlic (Raw)' => 4.0,
            'Lemon Juice' => 10.0,
            'Olive Oil (Extra Virgin)' => 4.0,
            'Rocca' => 45.0,
            'Tahini' => 12.0,
            'Tomato (Raw)' => 45.0,
            'White Onion' => 28.0,
        ],
        'Rosemary Garlic Chicken w Pomegranate Glaze, Beetroot & Rocca' => [
            'Rosemary Garlic Chicken (Base)' => 88.0,
            'Red Onion' => 32.0,
            'Bell Pepper (Red)' => 38.0,
            'Garlic (Raw)' => 3.0,
            'Olive Oil (Extra Virgin)' => 4.0,
            'Oregano' => 1.0,
            'Pomegranate Molasses' => 10.0,
            'Black Pepper' => 0.5,
            'Nutmeg' => 0.2,
            'Beetroot' => 70.0,
            'Rocca' => 48.0,
            'Quinoa Flatbread (Base)' => 32.0,
            'Tomato (Raw)' => 48.0,
        ],
    ];

    public function __construct(
        private BalancedMicronutrientRecipeRefiner $refiner,
    ) {}

    /**
     * @return list<string>
     */
    public function apply(): array
    {
        $updated = [];

        foreach (self::MEAL_GRAM_MAPS as $mealName => $gramMap) {
            /** @var Meal|null $meal */
            $meal = Meal::query()
                ->where('name', $mealName)
                ->with('ingredients')
                ->first();

            if ($meal === null) {
                continue;
            }

            if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                continue;
            }

            if ($this->refiner->syncMealFromGramMap($meal, $gramMap)) {
                $updated[] = $mealName;
            }
        }

        return $updated;
    }
}
