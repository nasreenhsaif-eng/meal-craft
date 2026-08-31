<?php

namespace App\Enums;

enum MealLibraryKey: string
{
    case Classic = 'classic';
    case Tiers = 'tiers';

    public function label(): string
    {
        return match ($this) {
            self::Classic => __('Meal Library'),
            self::Tiers => __('Meal Tiers Library'),
        };
    }
}
