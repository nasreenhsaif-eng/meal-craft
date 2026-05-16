<?php

namespace App\Support;

use App\Enums\RecipeAmountUnit;

/**
 * Parses ingredient quantity strings for meal library CSV and tooling.
 *
 * Segment delimiters: {@see self::SEGMENT_DELIMITER} (preferred), newlines, or commas after {@code (Weightg)} groups.
 *
 * Supported segment shapes (trimmed):
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
    private const UNIT_SUFFIX_PATTERN = 'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|milliliter|millilitre|kilogram|teaspoon|tablespoon|liter|litre|cup|gr|kg|ml|ltr|tsp|tbsp|g|\bl\b';

    private const AMOUNT_PATTERN = '\d+(?:[.,]\d+)?';

    /**
     * @return list<array{name: string, amount: float, unit: string}>
     */
    public static function parse(string $cell): array
    {
        $out = [];

        foreach (self::splitSegments($cell) as $part) {
            $parsed = self::parseOneSegment($part);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * Ingredient display names extracted from a quantity cell (for pending-ingredient CSV expansion).
     *
     * @return list<string>
     */
    public static function ingredientNamesFromCell(string $cell): array
    {
        $names = [];

        foreach (self::splitSegments($cell) as $part) {
            $parsed = self::parseOneSegment($part);
            if ($parsed !== null) {
                $name = trim((string) ($parsed['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }

                continue;
            }

            $fallback = self::trimSegment($part);
            if ($fallback !== '') {
                $names[] = $fallback;
            }
        }

        return array_values(array_unique($names));
    }

    public static function cellLooksLikeCommaSeparatedIngredientList(string $cell): bool
    {
        $cell = self::sanitizeCell($cell);

        if ($cell === '') {
            return false;
        }

        if (self::countWeightGroupsInLine($cell) >= 2) {
            return true;
        }

        return (bool) preg_match('/[,，、;﹔；\t]/u', $cell);
    }

    /**
     * Normalize delimiters and invisible characters before splitting ingredient segments.
     */
    public static function sanitizeCell(string $cell): string
    {
        $cell = str_replace(["\r\n", "\r"], "\n", $cell);

        $cell = str_replace(
            ['\\|', '&#124;', '&vert;', '&#x7C;'],
            self::SEGMENT_DELIMITER,
            $cell,
        );

        $cell = preg_replace('/[│┃┆┇┊┋╎╏▕▏▐║‖∣｜¦]/u', self::SEGMENT_DELIMITER, $cell) ?? $cell;

        $cell = str_replace(['（', '）'], ['(', ')'], $cell);

        $cell = preg_replace('/[，、﹔；\x{FF0C}\x{3001}\x{FE50}\x{FE51}\x{FE54}]/u', ',', $cell) ?? $cell;

        $cell = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $cell) ?? $cell;

        // Spreadsheet typo: (1Ng) or (1ng) → (1g)
        $cell = preg_replace('/\(\s*(\d+(?:[.,]\d+)?)\s*ng\s*\)/iu', '($1g)', $cell) ?? $cell;

        return trim($cell);
    }

    /**
     * @return list<string>
     */
    public static function splitSegments(string $cell): array
    {
        $cell = self::sanitizeCell($cell);
        if ($cell === '') {
            return [];
        }

        $segments = [];

        foreach (preg_split('/\|/u', $cell) ?: [] as $pipePart) {
            foreach (preg_split('/\R/u', (string) $pipePart) ?: [] as $line) {
                $line = self::trimSegment((string) $line);
                if ($line === '') {
                    continue;
                }

                foreach (self::splitCommaSeparatedIngredientLine($line) as $piece) {
                    $trimmed = self::trimSegment($piece);
                    if ($trimmed !== '') {
                        $segments[] = $trimmed;
                    }
                }
            }
        }

        return $segments;
    }

    /**
     * Spreadsheet exports often use commas between {@code Name (115g)} groups instead of pipes.
     *
     * @return list<string>
     */
    private static function splitCommaSeparatedIngredientLine(string $line): array
    {
        if (! preg_match('/[,，、;﹔；\t]/u', $line) && self::countWeightGroupsInLine($line) < 2) {
            return [$line];
        }

        if (preg_match('/\t/u', $line) && self::countWeightGroupsInLine($line) >= 2) {
            $tabParts = preg_split('/\t+/u', $line) ?: [];
            $tabParts = array_values(array_filter(array_map(
                static fn (string $p): string => self::trimSegment($p),
                $tabParts,
            ), static fn (string $p): bool => $p !== ''));

            if (count($tabParts) > 1) {
                return $tabParts;
            }
        }

        $byWeight = self::extractSegmentsByWeightParentheses($line);
        if (count($byWeight) > 1) {
            return $byWeight;
        }

        if (! preg_match('/[,，、;﹔；]/u', $line)) {
            return [$line];
        }

        $amount = self::AMOUNT_PATTERN;
        $unit = self::UNIT_SUFFIX_PATTERN;
        $segmentPattern = '/([^,，、;﹔；]*\(\s*'.$amount.'\s*(?:'.$unit.')\s*\))/iu';

        if (self::countWeightGroupsInLine($line) < 1) {
            return [$line];
        }

        if (preg_match_all($segmentPattern, $line, $matches) < 1) {
            return [$line];
        }

        $parts = array_values(array_filter(array_map(
            static fn (string $p): string => trim($p),
            $matches[1],
        ), static fn (string $p): bool => $p !== ''));

        return count($parts) > 1 ? $parts : [$line];
    }

    /**
     * @return list<string>
     */
    private static function extractSegmentsByWeightParentheses(string $line): array
    {
        $amount = self::AMOUNT_PATTERN;
        $unit = self::UNIT_SUFFIX_PATTERN;
        $pattern = '/\(\s*'.$amount.'\s*(?:'.$unit.')\s*\)/iu';

        if (! preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $parts = [];
        $offset = 0;

        foreach ($matches[0] as $match) {
            $end = $match[1] + strlen($match[0]);
            $segment = trim(substr($line, $offset, $end - $offset));
            $segment = self::trimSegment($segment);

            if ($segment !== '') {
                $parts[] = $segment;
            }

            $offset = $end;
            $remainder = substr($line, $offset);
            if (is_string($remainder) && preg_match('/^\s*[,，、;﹔；]\s*/u', $remainder, $delimiter)) {
                $offset += strlen($delimiter[0]);
            }
        }

        $tail = self::trimSegment((string) substr($line, $offset));
        if ($tail !== '' && ! preg_match('/^\(\s*'.self::AMOUNT_PATTERN.'/iu', $tail)) {
            $parts[] = $tail;
        }

        return $parts;
    }

    private static function countWeightGroupsInLine(string $line): int
    {
        $amount = self::AMOUNT_PATTERN;
        $unit = self::UNIT_SUFFIX_PATTERN;

        return preg_match_all('/\(\s*'.$amount.'\s*(?:'.$unit.')\s*\)/iu', $line);
    }

    private static function trimSegment(string $part): string
    {
        $part = trim($part);
        $part = preg_replace('/\s+/u', ' ', $part) ?? $part;

        return trim($part, " \t\n\r\0\x0B,，、;﹔；");
    }

    /**
     * @return array{name: string, amount: float, unit: string}|null
     */
    private static function parseOneSegment(string $part): ?array
    {
        $part = self::trimSegment($part);
        if ($part === '') {
            return null;
        }

        $unit = self::UNIT_SUFFIX_PATTERN;
        $amount = self::AMOUNT_PATTERN;

        if (preg_match('/^(.+?):('.$amount.')\s*(?:('.$unit.'))?\s*$/iu', $part, $m)) {
            if (! isset($m[1], $m[2])) {
                return null;
            }

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

        if (preg_match('/^(.+?)\s*\(\s*('.$amount.')\s*('.$unit.')\s*\)\s*$/iu', $part, $m)) {
            if (! isset($m[1], $m[2], $m[3])) {
                return null;
            }

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

        if (preg_match('/^(.+?)\s+('.$amount.')\s*('.$unit.')\s*$/iu', $part, $m)) {
            if (! isset($m[1], $m[2], $m[3])) {
                return null;
            }

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
