<?php

namespace App\Support;

/**
 * Non-starchy vegetables used to add fiber/volume when fatty protein cuts starch.
 */
final class NonStarchyVegetablePortion
{
    public static function matches(string $ingredientName): bool
    {
        $name = strtolower(trim($ingredientName));

        if ($name === '' || ComplexCarbPortion::matches($ingredientName) || self::isExcluded($name)) {
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
            'broccoli',
            'asparagus',
            'spinach',
            'zucchini',
            'courgette',
            'bell pepper',
            'pepper (red)',
            'pepper (green)',
            'pepper (yellow)',
            'mushroom',
            'rocca',
            'arugula',
            'rocket',
            'kale',
            'romaine',
            'lettuce',
            'cucumber',
            'eggplant',
            'aubergine',
            'green bean',
            'garlicky green beans',
            'cabbage',
            'cauliflower',
            'tomato',
            'onion',
            'celery',
            'carrot',
            'beetroot',
            'beet',
            'fennel',
            'radish',
            'chard',
            'bok choy',
        ];
    }

    private static function isExcluded(string $name): bool
    {
        foreach (['sauce', 'dressing', 'paste', 'oil', 'juice', 'pickle', 'powder'] as $excluded) {
            if (str_contains($name, $excluded)) {
                return true;
            }
        }

        return false;
    }
}
