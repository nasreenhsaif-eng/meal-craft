<?php

namespace App\Enums;

enum MealPlanSlotType: string
{
    case Breakfast = 'breakfast';
    case Main = 'main';
    case Salad = 'salad';
    case Dessert = 'dessert';
    case Soup = 'soup';

    public function recipeCategory(): RecipeCategory
    {
        return $this->mealType()->toRecipeCategory();
    }

    public function mealType(): MealType
    {
        return match ($this) {
            self::Breakfast => MealType::Breakfast,
            self::Main => MealType::Main,
            self::Salad => MealType::Salad,
            self::Dessert => MealType::Dessert,
            self::Soup => MealType::Soup,
        };
    }

    /**
     * Slots per calendar day for one option path (A or B).
     *
     * @return list<array{0: self, 1: int}>
     */
    public static function daySlotTemplate(): array
    {
        $out = [];
        for ($i = 1; $i <= 2; $i++) {
            $out[] = [self::Breakfast, $i];
        }
        for ($i = 1; $i <= 4; $i++) {
            $out[] = [self::Main, $i];
        }
        for ($i = 1; $i <= 2; $i++) {
            $out[] = [self::Salad, $i];
        }
        for ($i = 1; $i <= 2; $i++) {
            $out[] = [self::Dessert, $i];
        }
        for ($i = 1; $i <= 2; $i++) {
            $out[] = [self::Soup, $i];
        }

        return $out;
    }

    /** Number of meal slots per day per option path */
    public static function slotsPerDayPerOption(): int
    {
        return count(self::daySlotTemplate());
    }
}
