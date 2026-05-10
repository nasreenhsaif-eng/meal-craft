<?php

namespace App\Enums;

enum MealType: string
{
    case Breakfast = 'breakfast';
    case Main = 'main';
    case Soup = 'soup';
    case Salad = 'salad';
    case Dessert = 'dessert';
    case BaseRecipe = 'base_recipe';
    case Snack = 'snack';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => __('Breakfast'),
            self::Main => __('Meal'),
            self::Soup => __('Soup'),
            self::Salad => __('Side salad'),
            self::Dessert => __('Dessert'),
            self::BaseRecipe => __('Base recipe'),
            self::Snack => __('Snack'),
        };
    }

    /**
     * Meal types that should appear in end-user dropdowns.
     *
     * @return list<self>
     */
    public static function dropdownCases(): array
    {
        return [
            self::Breakfast,
            self::Main,
            self::Soup,
            self::Salad,
            self::Dessert,
        ];
    }

    /**
     * Batch recipes meant for the ingredient library (neutral “component” type).
     */
    public function isBaseRecipe(): bool
    {
        return $this === self::BaseRecipe;
    }

    public function toRecipeCategory(): RecipeCategory
    {
        return match ($this) {
            self::Breakfast => RecipeCategory::Breakfast,
            self::Main, self::Snack, self::BaseRecipe => RecipeCategory::Meal,
            self::Soup => RecipeCategory::Soup,
            self::Salad => RecipeCategory::SideSalad,
            self::Dessert => RecipeCategory::Dessert,
        };
    }

    public static function fromRecipeCategory(RecipeCategory $category): self
    {
        return match ($category) {
            RecipeCategory::Breakfast => self::Breakfast,
            RecipeCategory::Meal => self::Main,
            RecipeCategory::Soup => self::Soup,
            RecipeCategory::SideSalad, RecipeCategory::MainSalad => self::Salad,
            RecipeCategory::Dessert => self::Dessert,
        };
    }
}
