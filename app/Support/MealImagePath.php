<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Normalizes persisted meal {@code image_path} values and resolves them to browser URLs.
 *
 * Supported stored shapes:
 * - Absolute URLs ({@code http://}, {@code https://}) — same-app {@code /images/…} and {@code /storage/…} paths are stored relatively
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

        $s = self::stripMarkdownLink(trim(str_replace('\\', '/', $raw)));
        if ($s === '') {
            return null;
        }

        if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) {
            $fromUrl = self::relativePathFromHttpUrl($s);
            $s = $fromUrl ?? $s;
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
            $fromUrl = self::relativePathFromHttpUrl($path);

            return $fromUrl !== null ? self::resolveUrl($fromUrl) : $path;
        }

        $relative = self::resolveRelativePathForDisplay(ltrim($path, '/'));
        if ($relative === '') {
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

    /**
     * Spreadsheet cells sometimes contain markdown links: {@code [url](url)}.
     */
    public static function stripMarkdownLink(string $raw): string
    {
        if (preg_match('/\[([^\]]*)\]\(([^)]+)\)/', $raw, $matches) !== 1) {
            return $raw;
        }

        $label = trim((string) $matches[1]);
        $href = trim((string) $matches[2]);

        if (self::cellLooksLikeImageReference($label)) {
            return $label;
        }

        if (self::cellLooksLikeImageReference($href)) {
            return $href;
        }

        return $label !== '' ? $label : $href;
    }

    public static function cellLooksLikeImageReference(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (str_contains($value, '/images/') || str_starts_with($value, 'images/')) {
            return true;
        }

        if (str_contains($value, '/storage/') || str_starts_with($value, 'meals/')) {
            return true;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return self::relativePathFromHttpUrl($value) !== null
                || (bool) preg_match('/\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i', $value);
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i', $value);
    }

    /**
     * Extract a storable relative path from an absolute app URL ({@code /images/…} or {@code /storage/…}).
     */
    public static function relativePathFromHttpUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'images/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            $trimmed = substr($path, strlen('storage/'));

            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }

    /**
     * Prefer an existing file (with extension fallback); otherwise keep the normalized CSV path for URL building.
     */
    private static function resolveRelativePathForDisplay(string $relative): string
    {
        if ($relative === '' || $relative === self::PLACEHOLDER_RELATIVE) {
            return '';
        }

        $existing = self::findExistingRelativePath($relative);

        return $existing ?? $relative;
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
