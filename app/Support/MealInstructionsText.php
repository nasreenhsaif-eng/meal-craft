<?php

namespace App\Support;

/**
 * Normalizes meal cooking instructions from CSV cells and UI forms into consistent
 * newline-separated storage, and structured step lines for the detail modal.
 */
final class MealInstructionsText
{
    /**
     * Persist instructions with normalized {@code \n} line breaks between steps.
     */
    public static function normalizeForStorage(?string $raw): ?string
    {
        $lines = self::linesFromRaw($raw);
        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public static function linesFromRaw(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $text = self::normalizeLineEndings(trim((string) $raw));
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split('/\n+/', $text) ?: [];
        $steps = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = self::trimTrailingSpacesPerLine($paragraph);
            if ($paragraph === '') {
                continue;
            }

            foreach (self::splitNumberedStepsInChunk($paragraph) as $step) {
                $steps[] = $step;
            }
        }

        return array_values($steps);
    }

    private static function normalizeLineEndings(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace('\\n', "\n", $text);
        $text = str_replace("\u{2028}", "\n", $text);

        return $text;
    }

    private static function trimTrailingSpacesPerLine(string $text): string
    {
        $lines = preg_split('/\n/', $text) ?: [];
        $trimmed = [];
        foreach ($lines as $line) {
            $trimmed[] = rtrim((string) $line);
        }

        return trim(implode("\n", $trimmed));
    }

    /**
     * Split inline numbered steps (e.g. {@code 1. … 2. …}) into separate lines.
     *
     * @return list<string>
     */
    private static function splitNumberedStepsInChunk(string $chunk): array
    {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return [];
        }

        $parts = preg_split('/\s+(?=\d{1,2}[\.\)]\s+)/u', $chunk) ?: [];
        if ($parts === []) {
            $parts = [$chunk];
        }

        $steps = [];
        foreach ($parts as $part) {
            $line = trim((string) $part);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^\d{1,2}[\.\)]\s*/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line !== '') {
                $steps[] = $line;
            }
        }

        return $steps !== [] ? $steps : [trim(preg_replace('/^\d{1,2}[\.\)]\s*/u', '', $chunk) ?? $chunk)];
    }
}
