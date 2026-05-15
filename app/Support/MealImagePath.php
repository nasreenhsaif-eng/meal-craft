<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Normalizes persisted meal {@code image_path} values and resolves them to browser URLs.
 *
 * Supported stored shapes:
 * - Absolute URLs ({@code http://}, {@code https://})
 * - Public web assets under {@code /images/...} (files in {@code public/images/...})
 * - Files on the {@code public} storage disk (e.g. {@code meals/…} from uploads)
 */
final class MealImagePath
{
    public const PLACEHOLDER_RELATIVE = 'images/meals/placeholder.svg';

    /** @var list<string> */
    private const FALLBACK_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

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

        if (preg_match('#^//([^/]+)(/.*)$#', $s, $matches) === 1) {
            $s = ltrim((string) $matches[2], '/');
        } elseif (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/.*)$#i', $s, $matches) === 1) {
            $s = ltrim((string) $matches[1], '/');
        } elseif (str_contains($s, '/images/')) {
            $pos = strpos($s, '/images/');
            if ($pos !== false) {
                $s = ltrim(substr($s, $pos + 1), '/');
            }
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

        $relative = self::findExistingRelativePath(ltrim($path, '/'));

        if ($relative === null) {
            return self::placeholderUrl();
        }

        if (str_starts_with($relative, 'images/')) {
            return asset($relative);
        }

        if (str_starts_with($relative, 'storage/')) {
            return asset($relative);
        }

        return Storage::disk('public')->url($relative);
    }

    public static function placeholderUrl(): string
    {
        return asset(self::PLACEHOLDER_RELATIVE);
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

    private static function findExistingRelativePath(string $relative): ?string
    {
        if ($relative === '' || $relative === self::PLACEHOLDER_RELATIVE) {
            return null;
        }

        if (self::fileExistsForRelativePath($relative)) {
            return $relative;
        }

        $directory = pathinfo($relative, PATHINFO_DIRNAME);
        $filename = pathinfo($relative, PATHINFO_FILENAME);
        if ($filename === '') {
            return null;
        }

        $directory = $directory === '.' ? '' : $directory.'/';

        foreach (self::FALLBACK_EXTENSIONS as $extension) {
            $candidate = $directory.$filename.'.'.$extension;
            if ($candidate !== $relative && self::fileExistsForRelativePath($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function fileExistsForRelativePath(string $relative): bool
    {
        $absolute = self::absolutePathForRelative($relative);

        return $absolute !== null && is_file($absolute);
    }

    private static function absolutePathForRelative(string $relative): ?string
    {
        $relative = ltrim($relative, '/');

        if (str_starts_with($relative, 'images/')) {
            return public_path($relative);
        }

        if (str_starts_with($relative, 'storage/')) {
            return public_path($relative);
        }

        return storage_path('app/public/'.$relative);
    }
}
