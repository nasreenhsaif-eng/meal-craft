<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Normalizes persisted meal {@code image_path} values and resolves them to browser URLs.
 *
 * Supported stored shapes:
 * - Absolute URLs ({@code http://}, {@code https://}) — same-app {@code /images/…} and {@code /storage/…} paths are stored relatively
 * - Public web assets under {@code /images/...} (files in {@code public/images/...})
 * - Bare filenames ({@code stew.png}) under {@code public/images/meals/}
 * - Files on the {@code public} storage disk (e.g. {@code meals/…} from uploads)
 */
final class MealImagePath
{
    public const PLACEHOLDER_RELATIVE = 'images/meals/placeholder.svg';

    public const PUBLIC_MEALS_DIRECTORY = 'images/meals';

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

        $s = self::ensurePrefixedRelativePath($s);

        return $s === '' ? null : $s;
    }

    public static function resolveUrl(?string $path, ?string $mealTitle = null): string
    {
        $mealTitle = $mealTitle !== null ? trim($mealTitle) : null;
        if ($mealTitle === '') {
            $mealTitle = null;
        }

        if ($path === null || trim(str_replace('\\', '/', (string) $path)) === '') {
            $discovered = $mealTitle !== null
                ? self::discoverInPublicMealsDirectory('', $mealTitle)
                : null;

            return $discovered !== null
                ? self::publicUrlForRelativePath($discovered)
                : self::placeholderUrl();
        }

        $path = trim(str_replace('\\', '/', (string) $path));

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $fromUrl = self::relativePathFromHttpUrl($path);

            return $fromUrl !== null
                ? self::resolveUrl($fromUrl, $mealTitle)
                : self::encodeAbsoluteUrlPath($path);
        }

        $normalized = self::normalizeForDatabase($path) ?? self::ensurePrefixedRelativePath(ltrim($path, '/'));
        if ($normalized === '') {
            return self::placeholderUrl();
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return self::encodeAbsoluteUrlPath($normalized);
        }

        $relative = self::resolveRelativePathForDisplay(ltrim($normalized, '/'), $mealTitle);
        if ($relative === '') {
            return self::placeholderUrl();
        }

        return self::publicUrlForRelativePath($relative);
    }

    public static function placeholderUrl(): string
    {
        return self::publicUrlForRelativePath(self::PLACEHOLDER_RELATIVE);
    }

    /**
     * Convert a meal title to the filename base used under {@see PUBLIC_MEALS_DIRECTORY}.
     */
    public static function mealTitleToImageFileBase(string $title): string
    {
        $s = trim(preg_replace('/\s+/u', '-', $title) ?? $title);
        $s = preg_replace('/-+/u', '-', $s) ?? $s;

        return trim($s, '-');
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
                || self::hasImageExtension($value);
        }

        return self::hasImageExtension($value);
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
        $path = rawurldecode($path);

        if (str_starts_with($path, 'images/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            $trimmed = substr($path, strlen('storage/'));

            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }

    private static function ensurePrefixedRelativePath(string $path): string
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'images/') || str_starts_with($path, 'meals/')) {
            return $path;
        }

        if (self::hasImageExtension($path)) {
            return self::PUBLIC_MEALS_DIRECTORY.'/'.basename($path);
        }

        return $path;
    }

    private static function hasImageExtension(string $path): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $path);
    }

    private static function publicUrlForRelativePath(string $relative): string
    {
        if (str_starts_with($relative, 'images/') || str_starts_with($relative, 'storage/')) {
            return self::assetUrlForRelativePath($relative);
        }

        return self::encodeAbsoluteUrlPath(Storage::disk('public')->url($relative));
    }

    private static function assetUrlForRelativePath(string $relative): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::encodeRelativePathSegments($relative);
    }

    private static function encodeRelativePathSegments(string $relative): string
    {
        $parts = explode('/', ltrim($relative, '/'));

        return implode('/', array_map(rawurlencode(...), $parts));
    }

    private static function encodeAbsoluteUrlPath(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['path'])) {
            return $url;
        }

        $segments = explode('/', trim((string) $parts['path'], '/'));
        $encodedPath = '/'.implode('/', array_map(rawurlencode(...), $segments));
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$encodedPath.$query.$fragment;
    }

    /**
     * Prefer an existing file (with extension fallback), then discover by meal title / prefix.
     */
    private static function resolveRelativePathForDisplay(string $relative, ?string $mealTitle): string
    {
        if ($relative === '' || $relative === self::PLACEHOLDER_RELATIVE) {
            $relative = '';
        } else {
            $relative = self::ensurePrefixedRelativePath($relative);
        }

        $existing = $relative !== '' ? self::findExistingRelativePath($relative) : null;
        if ($existing !== null) {
            return $existing;
        }

        $discovered = self::discoverInPublicMealsDirectory($relative, $mealTitle);
        if ($discovered !== null) {
            return $discovered;
        }

        return $relative;
    }

    private static function discoverInPublicMealsDirectory(string $storedRelative, ?string $mealTitle): ?string
    {
        $dirPath = public_path(self::PUBLIC_MEALS_DIRECTORY);
        if (! is_dir($dirPath)) {
            return null;
        }

        $wantedBase = pathinfo($storedRelative, PATHINFO_FILENAME);
        /** @var list<string> $candidates */
        $candidates = [];
        if ($mealTitle !== null && $mealTitle !== '') {
            $candidates[] = self::mealTitleToImageFileBase($mealTitle);
        }
        if ($wantedBase !== '' && $wantedBase !== '.') {
            $candidates[] = $wantedBase;
        }

        $files = scandir($dirPath);
        if ($files === false) {
            return null;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $base = pathinfo($file, PATHINFO_FILENAME);
            foreach ($candidates as $candidate) {
                if (strcasecmp($base, $candidate) === 0) {
                    return self::PUBLIC_MEALS_DIRECTORY.'/'.$file;
                }
            }
        }

        if ($wantedBase !== '' && $wantedBase !== '.') {
            $best = null;
            $bestLen = 0;
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $base = pathinfo($file, PATHINFO_FILENAME);
                if (
                    (str_starts_with($base, $wantedBase) || str_starts_with($wantedBase, $base))
                    && strlen($base) > $bestLen
                ) {
                    $best = self::PUBLIC_MEALS_DIRECTORY.'/'.$file;
                    $bestLen = strlen($base);
                }
            }

            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    private static function findExistingRelativePath(string $relative): ?string
    {
        if ($relative === '' || $relative === self::PLACEHOLDER_RELATIVE) {
            return null;
        }

        $relative = self::ensurePrefixedRelativePath($relative);

        if (self::fileExistsForRelativePath($relative)) {
            return $relative;
        }

        $directory = pathinfo($relative, PATHINFO_DIRNAME);
        $filename = pathinfo($relative, PATHINFO_FILENAME);
        if ($filename === '' || $filename === '.') {
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
