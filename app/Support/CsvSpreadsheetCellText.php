<?php

namespace App\Support;

/**
 * Normalizes text for CSV downloads so Excel/Numbers open rows reliably.
 *
 * Multiline cells are valid RFC 4180 but often make desktop spreadsheets report
 * a corrupt file; we flatten line breaks for export and preserve step text via
 * literal {@code \n} sequences (see {@see MealInstructionsText::normalizeLineEndings}).
 */
final class CsvSpreadsheetCellText
{
    public static function exportSingleLine(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $collapsed = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($collapsed);
    }

    public static function exportMultilineAsEscapedNewlines(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = str_replace('\\n', "\n", $normalized);

        return str_replace("\n", '\n', trim($normalized));
    }
}
