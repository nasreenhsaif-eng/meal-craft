<?php

namespace App\Support;

/**
 * Cooked starch sides scaled by plan tier (potato, rice, sweet potato, quinoa, etc.).
 */
final class ComplexCarbPortion
{
    public static function matches(string $ingredientName): bool
    {
        $name = strtolower(trim($ingredientName));

        if ($name === '' || self::isExcluded($name)) {
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
            'sweet potato',
            'potato',
            'basmati',
            'wild rice',
            'brown rice',
            'white rice',
            'turmeric rice',
            'saffron rice',
            'steamed basmati',
            'cooked quinoa',
            'quinoa (white)',
            'quinoa',
            'couscous',
        ];
    }

    private static function isExcluded(string $name): bool
    {
        foreach (['bread', 'flatbread', 'muffin', 'flour', 'starch', 'chip'] as $excluded) {
            if (str_contains($name, $excluded)) {
                return true;
            }
        }

        return false;
    }
}
