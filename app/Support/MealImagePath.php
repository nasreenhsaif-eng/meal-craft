<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Normalizes persisted meal `image_path` values and resolves them to browser URLs.
 *
 * Supported stored shapes:
 * - Absolute URLs (`http://`, `https://`)
 * - Public web assets under `/images/...` (files in `public/images/...`)
 * - Files on the `public` storage disk (e.g. `meals/…` from uploads)
 */
final class MealImagePath
{
    public static function normalizeForDatabase(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = trim(str_replace('\\', '/', $raw));
        if ($s === '') {
            return null;
        }

        if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) {
            return $s;
        }

        if (
            (str_starts_with($s, '"') && str_ends_with($s, '"'))
            || (str_starts_with($s, "'") && str_ends_with($s, "'"))
        ) {
            $s = trim(substr($s, 1, -1));
        }

        if ($s === '') {
            return null;
        }

        $s = preg_replace('#^(public/)+#i', '', $s) ?? $s;
        $s = ltrim($s, '/');

        if (str_starts_with($s, 'storage/')) {
            $s = substr($s, strlen('storage/'));
        }

        return $s === '' ? null : $s;
    }

    public static function resolveUrl(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relative = ltrim($path, '/');

        if (str_starts_with($relative, 'images/')) {
            return asset($relative);
        }

        if (str_starts_with($relative, 'storage/')) {
            return asset($relative);
        }

        return Storage::disk('public')->url($relative);
    }

    /**
     * Whether {@see \Illuminate\Support\Facades\Storage::disk('public')::delete} should run for this path.
     */
    public static function shouldDeleteFromPublicDisk(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        $p = trim(str_replace('\\', '/', (string) $path));
        if ($p === '') {
            return false;
        }

        if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
            return false;
        }

        $relative = ltrim($p, '/');

        if (str_starts_with($relative, 'images/')) {
            return false;
        }

        return true;
    }
}
