<?php

namespace App\Support;

use App\Enums\RecipeAmountUnit;
use App\Models\Ingredient;
use App\Services\RecipeIngredientUnitConverter;
use InvalidArgumentException;

/**
 * Parses ingredient-library CSV {@code recipe_components} cells.
 *
 * Supported segment shapes:
 * - {@code ingredient_id:amount} — comma- or pipe-separated, e.g. {@code 12:100}, {@code 12:100g}
 * - {@code Ingredient Name (Weightg)} — pipe-separated only (commas may appear in ingredient names),
 *   e.g. {@code Carrots, raw (100g) | Onion (50g)}
 */
final class RecipeComponentsCsvParser
{
    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    public static function parseToComponentRows(
        string $cell,
        ?int $csvRowNumber = null,
        ?string $baseRecipeName = null,
    ): array {
        $cell = trim($cell);
        if ($cell === '') {
            return [];
        }

        if (self::cellUsesIdAmountFormat($cell)) {
            return self::parseIdAmountCell($cell, $csvRowNumber, $baseRecipeName);
        }

        return self::parseNameWeightCell($cell, $csvRowNumber, $baseRecipeName);
    }

    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    private static function parseIdAmountCell(
        string $cell,
        ?int $csvRowNumber,
        ?string $baseRecipeName,
    ): array {
        $segments = preg_split('/[,|]/u', $cell) ?: [];
        $rows = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $rows[] = self::parseIdAmountSegment($segment, $csvRowNumber, $baseRecipeName);
        }

        return $rows;
    }

    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    private static function parseNameWeightCell(
        string $cell,
        ?int $csvRowNumber,
        ?string $baseRecipeName,
    ): array {
        $segments = preg_split('/\||\R/u', $cell) ?: [];
        $rows = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $rows[] = self::parseNameWeightSegment($segment, $csvRowNumber, $baseRecipeName);
        }

        return $rows;
    }

    private static function cellUsesIdAmountFormat(string $cell): bool
    {
        return (bool) preg_match('/(?:^|[|,])\s*\d+\s*:\s*\d/u', $cell);
    }

    /**
     * @return array{ingredient_id: int, amount_grams: float}
     */
    private static function parseIdAmountSegment(
        string $segment,
        ?int $csvRowNumber,
        ?string $baseRecipeName,
    ): array {
        if (! preg_match('/^(\d+)\s*:\s*(\d+(?:[.,]\d+)?)\s*([a-zA-Z]+)?\s*$/u', $segment, $matches)) {
            self::throwMalformedSegment(
                $segment,
                __('Expected “ingredient_id:amount”, e.g. “12:100” or “12:100g”.'),
                $csvRowNumber,
                $baseRecipeName,
            );
        }

        return self::componentRowFromIdAmount(
            (int) $matches[1],
            (float) str_replace(',', '.', (string) $matches[2]),
            isset($matches[3]) ? trim((string) $matches[3]) : '',
        );
    }

    /**
     * @return array{ingredient_id: int, amount_grams: float}
     */
    private static function parseNameWeightSegment(
        string $segment,
        ?int $csvRowNumber,
        ?string $baseRecipeName,
    ): array {
        $amountPattern = '(\d+(?:[.,]\d+)?)';
        $unitPattern = 'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|milliliter|millilitre|kilogram|teaspoon|tablespoon|liter|litre|cup|g|kg|ml|ltr|tsp|tbsp|\bl\b';

        if (! preg_match('/^(.+?)\s*\(\s*'.$amountPattern.'\s*('.$unitPattern.')\s*\)\s*$/iu', $segment, $matches)) {
            self::throwMalformedSegment(
                $segment,
                __('Expected “Ingredient Name (Weightg)”, e.g. “Carrots, raw (100g)”. Separate components with |.'),
                $csvRowNumber,
                $baseRecipeName,
            );
        }

        if (! isset($matches[1], $matches[2], $matches[3])) {
            self::throwMalformedSegment(
                $segment,
                __('Expected “Ingredient Name (Weightg)”, e.g. “Carrots, raw (100g)”. Separate components with |.'),
                $csvRowNumber,
                $baseRecipeName,
            );
        }

        $name = trim((string) $matches[1]);
        $amount = (float) str_replace(',', '.', (string) $matches[2]);
        $unitRaw = trim((string) $matches[3]);

        if ($name === '' || $amount <= 0 || ! is_finite($amount)) {
            self::throwMalformedSegment(
                $segment,
                __('Ingredient name and weight must be positive.'),
                $csvRowNumber,
                $baseRecipeName,
            );
        }

        $ingredient = self::findVerifiedComponentIngredient($name);

        if ($ingredient === null) {
            $message = IngredientLibraryNameMatcher::labelIndicatesBaseRecipe($name)
                ? __(
                    'Nested base recipe “:name” is not in the verified ingredient library yet. Import that base recipe in its own CSV row first (with is_base_recipe = TRUE), then import “:parent”.',
                    ['name' => $name, 'parent' => (string) $baseRecipeName],
                )
                : __('Ingredient “:name” was not found in the verified library.', ['name' => $name]);

            throw new InvalidArgumentException(self::formatContextMessage(
                $message,
                $csvRowNumber,
                $baseRecipeName,
                $segment,
            ));
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
    private static function componentRowFromIdAmount(int $ingredientId, float $amount, string $unitRaw): array
    {
        if ($ingredientId <= 0 || $amount <= 0) {
            throw new InvalidArgumentException(__('Recipe components require positive ingredient ids and amounts.'));
        }

        $ingredient = Ingredient::query()->whereKey($ingredientId)->where('is_verified', true)->first();

        if ($ingredient === null) {
            $legacyName = LegacyMenuIngredientIdMap::nameForLegacyId($ingredientId);
            if ($legacyName !== null) {
                $ingredient = IngredientLibraryNameMatcher::resolveForImportLabel($legacyName);
            }
        }

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
        $match = IngredientLibraryNameMatcher::resolveForImportLabel($name);

        if ($match === null || ! $match->is_verified) {
            return null;
        }

        return $match;
    }

    /**
     * @return never
     */
    private static function throwMalformedSegment(
        string $segment,
        string $reason,
        ?int $csvRowNumber,
        ?string $baseRecipeName,
    ): void {
        throw new InvalidArgumentException(self::formatContextMessage(
            __('Malformed recipe component segment “:segment”. :reason', [
                'segment' => $segment,
                'reason' => $reason,
            ]),
            $csvRowNumber,
            $baseRecipeName,
            $segment,
        ));
    }

    private static function formatContextMessage(
        string $message,
        ?int $csvRowNumber,
        ?string $baseRecipeName,
        ?string $segment,
    ): string {
        $parts = [$message];

        if ($csvRowNumber !== null && $csvRowNumber > 0) {
            $parts[] = __('CSV row :row.', ['row' => $csvRowNumber]);
        }

        if ($baseRecipeName !== null && trim($baseRecipeName) !== '') {
            $parts[] = __('Base recipe “:name”.', ['name' => trim($baseRecipeName)]);
        }

        if ($segment !== null && trim($segment) !== '') {
            $parts[] = __('Segment: “:segment”.', ['segment' => trim($segment)]);
        }

        return implode(' ', $parts);
    }
}
