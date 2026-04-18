<?php

namespace App\Enums;

enum RecipeAmountUnit: string
{
    case Grams = 'g';
    case Kilograms = 'kg';
    case Milliliters = 'ml';
    case Liters = 'ltr';
    case Teaspoon = 'tsp';
    case Tablespoon = 'tbsp';
    case Cup = 'cup';

    public function usesDensity(): bool
    {
        return match ($this) {
            self::Grams, self::Kilograms => false,
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
