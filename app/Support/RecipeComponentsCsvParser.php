<?php

namespace App\Support;

use App\Enums\RecipeAmountUnit;
use App\Models\Ingredient;
use App\Services\MealCsvLibraryImportService;
use App\Services\RecipeIngredientUnitConverter;
use InvalidArgumentException;

/**
 * Parses ingredient-library CSV {@code recipe_components} cells.
 *
 * Supported segment shapes (comma- or pipe-separated):
 * - {@code ingredient_id:amount} — e.g. {@code 12:100}, {@code 12:100g}
 * - Meal-library style names — e.g. {@code Mango (2000g)}, {@code Mango:2000g}, {@code Mango 2000g}
 */
final class RecipeComponentsCsvParser
{
    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    public static function parseToComponentRows(string $cell): array
    {
        $cell = trim($cell);
        if ($cell === '') {
            return [];
        }

        $segments = preg_split('/[,|]/', $cell) ?: [];
        $rows = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $rows[] = self::parseSegment($segment);
        }

        return $rows;
    }

    /**
     * @return array{ingredient_id: int, amount_grams: float}
     */
    private static function parseSegment(string $segment): array
    {
        if (preg_match('/^(\d+)\s*:\s*(\d+(?:[.,]\d+)?)\s*([a-zA-Z]+)?\s*$/u', $segment, $matches)) {
            return self::componentRowFromIdAmount(
                (int) $matches[1],
                (float) str_replace(',', '.', (string) $matches[2]),
                isset($matches[3]) ? trim((string) $matches[3]) : '',
            );
        }

        $nameSegments = IngredientQuantityStringParser::parse($segment);

        if (count($nameSegments) !== 1) {
            throw new InvalidArgumentException(__('Invalid recipe component segment: :segment', ['segment' => $segment]));
        }

        $parsed = $nameSegments[0];
        $ingredient = self::findVerifiedComponentIngredient((string) $parsed['name']);

        if ($ingredient === null) {
            throw new InvalidArgumentException(__('Ingredient “:name” was not found in the verified library.', ['name' => $parsed['name']]));
        }

        return self::componentRowFromIngredientAmount(
            $ingredient,
            (float) $parsed['amount'],
            (string) $parsed['unit'],
        );
    }

    /**
     * @return array{ingredient_id: int, amount_grams: float}
     */
    private static function componentRowFromIdAmount(int $ingredientId, float $amount, string $unitRaw): array
    {
        if ($ingredientId <= 0 || $amount <= 0) {
            throw new InvalidArgumentException(__('Recipe components require positive ingredient ids and amounts.'));
        }

        $ingredient = Ingredient::query()->whereKey($ingredientId)->where('is_verified', true)->first();

        if ($ingredient === null) {
            throw new InvalidArgumentException(__('Ingredient :id was not found in the verified library.', ['id' => $ingredientId]));
        }

        return self::componentRowFromIngredientAmount(
            $ingredient,
            $amount,
            IngredientQuantityStringParser::normalizeUnit($unitRaw),
        );
    }

    /**
     * @return array{ingredient_id: int, amount_grams: float}
     */
    private static function componentRowFromIngredientAmount(Ingredient $ingredient, float $amount, string $unit): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Recipe component amounts must be positive.'));
        }

        $density = (float) ($ingredient->density ?? 0) > 0 ? (float) $ingredient->density : 1.0;
        $enum = RecipeAmountUnit::tryFrom($unit) ?? RecipeAmountUnit::Grams;
        $grams = RecipeIngredientUnitConverter::toGrams($amount, $enum, $density);

        if ($grams <= 0) {
            throw new InvalidArgumentException(__('Recipe component amounts must convert to a positive gram weight.'));
        }

        return [
            'ingredient_id' => (int) $ingredient->getKey(),
            'amount_grams' => round($grams, 4),
        ];
    }

    private static function findVerifiedComponentIngredient(string $name): ?Ingredient
    {
        $normalized = MealCsvLibraryImportService::normalizeMealNameKey($name);

        if ($normalized === '') {
            return null;
        }

        return Ingredient::query()
            ->where('is_verified', true)
            ->where(function ($query): void {
                $query->whereNull('usda_food_category')
                    ->orWhereNotIn('usda_food_category', IngredientLibraryCategory::preparedLabels());
            })
            ->whereRaw('lower(trim(name)) = ?', [$normalized])
            ->first();
    }
}
