<?php

namespace App\Support;

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;

/**
 * Splits salad meals into salad-body vs dressing for customer-facing detail views.
 */
final class SaladMealPresentation
{
    public static function isSaladMeal(Meal $meal): bool
    {
        if ($meal->category instanceof RecipeCategory) {
            if (in_array($meal->category, [RecipeCategory::SideSalad, RecipeCategory::MainSalad], true)) {
                return true;
            }
        }

        return str_contains(strtolower($meal->name), ' salad');
    }

    public static function isDressingIngredient(Ingredient $ingredient): bool
    {
        $name = $ingredient->name;

        if (str_contains($name, 'Dressing (Base)')) {
            return true;
        }

        if (str_contains($name, 'Marinade (Base)')) {
            return true;
        }

        return $ingredient->isPreparedBaseIngredient()
            && str_contains(strtolower($name), 'dressing');
    }

    /**
     * @param  callable(Ingredient, float): string  $formatLine
     * @return list<array{title: string, items: list<string>}>
     */
    public static function ingredientSectionsForMeal(Meal $meal, callable $formatLine): array
    {
        if (! self::isSaladMeal($meal)) {
            return [];
        }

        $saladItems = [];
        $dressingItems = [];

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
            $line = $formatLine($ingredient, $grams);

            if (self::isDressingIngredient($ingredient)) {
                $dressingItems[] = $line;

                continue;
            }

            $saladItems[] = $line;
        }

        $sections = [];

        if ($saladItems !== []) {
            $sections[] = [
                'title' => __('Salad'),
                'items' => $saladItems,
            ];
        }

        if ($dressingItems !== []) {
            $sections[] = [
                'title' => __('Dressing'),
                'items' => $dressingItems,
            ];
        }

        return $sections;
    }

    /**
     * Flat ingredient lines with salad components first and dressing last.
     *
     * @param  callable(Ingredient, float): string  $formatLine
     * @return list<string>
     */
    public static function orderedIngredientLinesForMeal(Meal $meal, callable $formatLine): array
    {
        if (! self::isSaladMeal($meal)) {
            $lines = [];

            foreach ($meal->ingredients as $ingredient) {
                $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
                $lines[] = $formatLine($ingredient, $grams);
            }

            return $lines;
        }

        $sections = self::ingredientSectionsForMeal($meal, $formatLine);
        $lines = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<array{title: string, steps: list<string>}>
     */
    public static function instructionSectionsForMeal(Meal $meal): array
    {
        if (! self::isSaladMeal($meal)) {
            return [];
        }

        $sections = [];

        $dressingSteps = self::dressingInstructionSteps($meal);

        if ($dressingSteps !== []) {
            $sections[] = [
                'title' => __('Dressing'),
                'steps' => $dressingSteps,
            ];
        }

        $saladRaw = trim((string) ($meal->instructions ?? ''));

        if ($saladRaw === '') {
            $saladRaw = trim((string) ($meal->description ?? ''));
        }

        $saladSteps = MealInstructionsText::linesFromRaw($saladRaw);

        if ($saladSteps !== []) {
            $sections[] = [
                'title' => __('Salad'),
                'steps' => $saladSteps,
            ];
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private static function dressingInstructionSteps(Meal $meal): array
    {
        $steps = [];

        foreach ($meal->ingredients as $ingredient) {
            if (! self::isDressingIngredient($ingredient)) {
                continue;
            }

            $raw = trim((string) ($ingredient->instructions ?? ''));

            if ($raw === '') {
                continue;
            }

            foreach (MealInstructionsText::linesFromRaw($raw) as $step) {
                $steps[] = $step;
            }
        }

        return $steps;
    }
}
