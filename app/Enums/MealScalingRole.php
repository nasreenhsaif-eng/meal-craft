<?php

namespace App\Enums;

enum MealScalingRole: string
{
    case Protein = 'protein';
    case Carb = 'carb';
    case HerbSpice = 'herb_spice';
    case Vegetable = 'vegetable';
    case Fat = 'fat';
    case Sauce = 'sauce';
    case Other = 'other';

    public function isTrimEligible(): bool
    {
        return match ($this) {
            self::Fat, self::Carb, self::Sauce => true,
            default => false,
        };
    }

    public function isFixedAtBaseline(): bool
    {
        return $this === self::Vegetable;
    }
}
