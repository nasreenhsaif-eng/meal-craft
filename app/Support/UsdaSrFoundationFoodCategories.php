<?php

namespace App\Support;

/**
 * SR/Foundation Food Category labels aligned with FoodData Central Food Search (Foundation & SR Legacy).
 *
 * @see https://fdc.nal.usda.gov/food-search
 */
final class UsdaSrFoundationFoodCategories
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'American Indian/Alaska Native Foods',
            'Baby Foods',
            'Baked Products',
            'Beef Products',
            'Beverages',
            'Breakfast Cereals',
            'Cereal Grains and Pasta',
            'Dairy and Egg Products',
            'Fast Foods',
            'Fats and Oils',
            'Finfish and Shellfish Products',
            'Fruits and Fruit Juices',
            'Lamb, Veal, and Game Products',
            'Legumes and Legume Products',
            'Meals, Entrees, and Side Dishes',
            'Nut and Seed Products',
            'Pork Products',
            'Poultry Products',
            'Restaurant Foods',
            'Sausages and Luncheon Meats',
            'Snacks',
            'Soups, Sauces, and Gravies',
            'Spices and Herbs',
            'Sweets',
            'Vegetables and Vegetable Products',
        ];
    }
}
