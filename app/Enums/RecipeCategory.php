<?php

namespace App\Enums;

enum RecipeCategory: string
{
    case Breakfast = 'Breakfast';
    case Soup = 'Soup';
    case SideSalad = 'Side Salad';
    case MainSalad = 'Main Salad';
    case Meal = 'Meal';
    case Dessert = 'Dessert';
    case BaseRecipe = 'Base Recipe';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Breakfast => 'orange',
            self::Soup => 'blue',
            self::SideSalad => 'lime',
            self::MainSalad => 'emerald',
            self::Meal => 'green',
            self::Dessert => 'pink',
            self::BaseRecipe => 'stone',
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
