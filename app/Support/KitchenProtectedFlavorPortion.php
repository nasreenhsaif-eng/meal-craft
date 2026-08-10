<?php

namespace App\Support;

/**
 * Seasonings, spices, herbs, oils, dressings, and sauces that must stay at library
 * grams during tier plating — never dropped or calorie-squeezed.
 */
final class KitchenProtectedFlavorPortion
{
    public static function matches(string $ingredientName): bool
    {
        $name = strtolower(trim($ingredientName));

        if ($name === '') {
            return false;
        }

        if (StandardMeatPortion::isPrimaryMeatIngredient($ingredientName)) {
            return false;
        }

        if (ComplexCarbPortion::matches($ingredientName) || NonStarchyVegetablePortion::matches($ingredientName)) {
            return false;
        }

        foreach (self::patterns() as $pattern) {
            if ($name === $pattern || str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function patterns(): array
    {
        return [
            'salt',
            'pepper',
            'garlic',
            'ginger',
            'turmeric',
            'cumin',
            'paprika',
            'sumac',
            'za\'atar',
            'zaatar',
            'oregano',
            'thyme',
            'rosemary',
            'basil',
            'parsley',
            'coriander',
            'cilantro',
            'mint',
            'dill',
            'cinnamon',
            'nutmeg',
            'chili',
            'chilli',
            'harissa',
            'oil',
            'vinegar',
            'lemon juice',
            'lime juice',
            'dressing',
            'sauce',
            'molasses',
            'tamari',
            'miso',
            'mustard',
            'tahini',
            'chimichurri',
            'pesto',
            'sesame',
            'seed',
            'walnut',
            'almond',
            'cashew',
            'peanut',
            'olive',
        ];
    }
}
