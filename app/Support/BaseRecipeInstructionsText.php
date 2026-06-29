<?php

namespace App\Support;

/**
 * Normalizes base-recipe ingredient instructions for DB storage: explicit
 * {@code Step N: …} lines joined with literal newline characters.
 */
final class BaseRecipeInstructionsText
{
    /** Shared rationale appended to overnight-soak step 1 on pulse, rice, and quinoa base recipes. */
    public const OVERNIGHT_SOAK_RATIONALE_BEANS = '(Soak overnight for better nutrient absorption by deactivating phytic acid, to improve digestibility, and to reduce cooking time.)';

    public const OVERNIGHT_SOAK_RATIONALE_RICE = '(Soak overnight for better nutrient absorption by deactivating phytic acid, to improve digestibility, remove bitterness, and to reduce cooking time.)';

    public const OVERNIGHT_SOAK_RATIONALE_QUINOA = '(Soak overnight for better nutrient absorption by deactivating phytic acid, to improve digestibility, remove bitterness, and to make it faster and fluffier when cooking.)';

    /**
     * @return list<string>
     */
    private const IMAGE_CSV_KEYS = [
        'image',
        'image_url',
        'image_path',
        'imageurl',
        'photo',
        'photo_url',
        'photo_path',
        'photourl',
        'picture',
        'picture_url',
        'thumbnail',
        'thumbnail_url',
    ];

    /**
     * Persist instructions as {@code Step 1: …\nStep 2: …} (meals-table multi-line rule).
     */
    public static function normalizeForStorage(?string $raw): ?string
    {
        $lines = self::instructionLinesForBaseRecipe(self::unwrapCsvQuotedCell($raw));
        if ($lines === []) {
            return null;
        }

        $steps = [];
        foreach ($lines as $index => $line) {
            $body = self::stripStepPrefix(trim($line));
            if ($body === '') {
                continue;
            }
            $steps[] = 'Step '.($index + 1).': '.$body;
        }

        if ($steps === []) {
            return null;
        }

        return implode("\n", $steps);
    }

    /**
     * Drop image/photo URL columns from a CSV row so base recipes stay clean.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function stripImageFieldsFromCsvRecord(array $record): array
    {
        foreach (self::IMAGE_CSV_KEYS as $key) {
            unset($record[$key]);
        }

        return $record;
    }

    /**
     * Remove wrapping quotes Excel/CSV tools add around multi-line cells.
     */
    public static function unwrapCsvQuotedCell(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return $text;
        }

        if (strlen($text) >= 2 && $text[0] === '"' && $text[strlen($text) - 1] === '"') {
            $inner = substr($text, 1, -1);
            $inner = str_replace('""', '"', $inner);

            return trim($inner);
        }

        return $text;
    }

    private static function stripStepPrefix(string $line): string
    {
        $stripped = preg_replace('/^Step\s+\d{1,2}\s*:\s*/iu', '', $line);

        return trim($stripped ?? $line);
    }

    /**
     * @return list<string>
     */
    private static function instructionLinesForBaseRecipe(?string $raw): array
    {
        $lines = MealInstructionsText::linesFromRaw($raw);
        if (count($lines) !== 1) {
            return $lines;
        }

        $single = trim($lines[0]);
        if ($single === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z"\'(])/u', $single) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), static fn (string $s): bool => $s !== ''));

        return count($sentences) > 1 ? $sentences : $lines;
    }
}
