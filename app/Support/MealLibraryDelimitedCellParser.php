<?php

namespace App\Support;

/**
 * Splits CSV cell values that may list multiple tokens using {@code ,} or {@code |}.
 */
final class MealLibraryDelimitedCellParser
{
    /**
     * @return list<string>
     */
    public static function split(string $cell): array
    {
        $cell = trim($cell);
        if ($cell === '') {
            return [];
        }

        $parts = preg_split('/[|,]/u', $cell) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $t = trim((string) $part);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }
}
