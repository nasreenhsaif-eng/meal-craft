<?php

namespace App\Support;

use App\Enums\RecipeAmountUnit;

/**
 * Parses pipe-separated ingredient quantity strings for meal library CSV and tooling.
 *
 * Supported segment shapes (trimmed, split on {@see self::SEGMENT_DELIMITER}):
 * - {@code Name:123} or {@code Name:825g} — first colon separates label from amount; optional unit suffix (known tokens only).
 * - {@code Name (123g)} or {@code Name (710ml)} — amount and unit in parentheses after the label.
 * - {@code Name 123g} — space before amount; a recognized unit suffix is required.
 */
final class IngredientQuantityStringParser
{
    public const SEGMENT_DELIMITER = '|';

    /**
     * Longer tokens first so alternation matches correctly.
     */
    private const UNIT_SUFFIX_PATTERN = 'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|milliliter|millilitre|kilogram|teaspoon|tablespoon|liter|litre|cup|g|kg|ml|ltr|tsp|tbsp|\bl\b';

    /**
     * @return list<array{name: string, amount: float, unit: string}>
     */
    public static function parse(string $cell): array
    {
        $parts = preg_split('/\||\R/u', $cell) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $parsed = self::parseOneSegment($part);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @return array{name: string, amount: float, unit: string}|null
     */
    private static function parseOneSegment(string $part): ?array
    {
        $unit = self::UNIT_SUFFIX_PATTERN;
        $amount = '(\d+(?:[.,]\d+)?)';

        if (preg_match('/^(.+?):'.$amount.'\s*(?:('.$unit.'))?\s*$/iu', $part, $m)) {
            $name = trim($m[1]);
            $amountVal = (float) str_replace(',', '.', (string) $m[2]);
            $unitRaw = isset($m[3]) ? trim((string) $m[3]) : '';

            if ($name === '' || ! self::amountIsValid($amountVal)) {
                return null;
            }

            return [
                'name' => $name,
                'amount' => $amountVal,
                'unit' => self::normalizeUnit($unitRaw),
            ];
        }

        if (preg_match('/^(.+?)\s*\(\s*'.$amount.'\s*('.$unit.')\s*\)\s*$/iu', $part, $m)) {
            $name = trim($m[1]);
            $amountVal = (float) str_replace(',', '.', (string) $m[2]);
            $unitRaw = trim((string) $m[3]);

            if ($name === '' || ! self::amountIsValid($amountVal)) {
                return null;
            }

            return [
                'name' => $name,
                'amount' => $amountVal,
                'unit' => self::normalizeUnit($unitRaw),
            ];
        }

        if (preg_match('/^(.+?)\s+'.$amount.'\s*('.$unit.')\s*$/iu', $part, $m)) {
            $name = trim($m[1]);
            $amountVal = (float) str_replace(',', '.', (string) $m[2]);
            $unitRaw = trim((string) $m[3]);

            if ($name === '' || ! self::amountIsValid($amountVal)) {
                return null;
            }

            return [
                'name' => $name,
                'amount' => $amountVal,
                'unit' => self::normalizeUnit($unitRaw),
            ];
        }

        return null;
    }

    private static function amountIsValid(float $amount): bool
    {
        return $amount > 0 && is_finite($amount);
    }

    /**
     * Normalizes a unit token to values accepted by {@see RecipeAmountUnit}.
     */
    public static function normalizeUnit(string $raw): string
    {
        $t = strtolower(trim($raw));
        if ($t === '' || $t === 'g' || $t === 'gram' || $t === 'grams' || $t === 'gr') {
            return 'g';
        }

        if ($t === 'kg' || $t === 'kilogram' || $t === 'kilograms' || $t === 'kgs') {
            return 'kg';
        }

        if ($t === 'ml' || $t === 'milliliter' || $t === 'milliliters' || $t === 'millilitre' || $t === 'millilitres' || $t === 'mL') {
            return 'ml';
        }

        if ($t === 'ltr' || $t === 'l' || $t === 'liter' || $t === 'liters' || $t === 'litre' || $t === 'litres') {
            return 'ltr';
        }

        if ($t === 'tsp' || $t === 'teaspoon' || $t === 'teaspoons') {
            return 'tsp';
        }

        if ($t === 'tbsp' || $t === 'tablespoon' || $t === 'tablespoons') {
            return 'tbsp';
        }

        if ($t === 'cup' || $t === 'cups' || $t === 'c') {
            return 'cup';
        }

        return 'g';
    }
}
