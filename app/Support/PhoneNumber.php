<?php

namespace App\Support;

/**
 * Normalizes customer mobile numbers to E.164.
 *
 * Bare 8-digit numbers are treated as Bahrain (+973), matching the signup field placeholder.
 */
final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        if ($digits !== '' && ! str_starts_with($digits, '+')) {
            if (str_starts_with($digits, '973') && strlen($digits) === 11) {
                $digits = '+'.$digits;
            } elseif (strlen($digits) === 8) {
                $digits = '+973'.$digits;
            } else {
                $digits = '+'.$digits;
            }
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    public static function mask(?string $phone): string
    {
        $normalized = self::normalize($phone) ?? trim((string) $phone);

        if ($normalized === '') {
            return '';
        }

        $digits = preg_replace('/\D/', '', $normalized) ?? '';

        if (strlen($digits) < 6) {
            return $normalized;
        }

        return '+'.substr($digits, 0, 3).' •••• '.substr($digits, -4);
    }
}
