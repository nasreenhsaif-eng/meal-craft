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

    /** Spreadsheet export placeholder when a meal has no image ({@see MealCraftMasterCsvExport}). */
    public const MISSING_PHOTO_PLACEHOLDER = 'NO_PHOTO_URL';

    /** Minimum loose-slug length for substring-based discovery (avoids false positives). */
    private const LOOSE_SLUG_SUBSTRING_MIN_LENGTH = 12;

    /** @var list<string> */
    private const FALLBACK_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Cached map of {@see looseMatchSlug()} => relative path under {@see PUBLIC_MEALS_DIRECTORY}.
     * Built once per bulk import / request to avoid repeated directory scans.
     *
     * @var array<string, string>|null
     */
    private static ?array $publicMealsLooseSlugIndex = null;

    public static function resetPublicMealsSlugIndex(): void
    {
        self::$publicMealsLooseSlugIndex = null;
    }

    public static function isMissingPhotoPlaceholder(?string $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        return strcasecmp(trim($raw), self::MISSING_PHOTO_PLACEHOLDER) === 0;
    }

    /**
     * Normalize a meal title or filename to a lowercase alphanumeric slug for loose matching.
     */
    public static function looseMatchSlug(string $text): string
    {
        $base = pathinfo($text, PATHINFO_FILENAME);
        if (! is_string($base) || $base === '' || $base === '.') {
            $base = $text;
        }

        $lower = mb_strtolower(trim($base), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/u', '', $lower);

        return is_string($slug) ? $slug : '';
    }

    /**
     * Tokenize a title or filename into lowercase words (minimum two characters).
     *
     * @return list<string>
     */
    public static function looseMatchWords(string $text): array
    {
        $base = pathinfo($text, PATHINFO_FILENAME);
        if (! is_string($base) || $base === '' || $base === '.') {
            $base = $text;
        }

        $parts = preg_split('/[^a-z0-9]+/u', mb_strtolower(trim($base), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }

        $words = [];
        foreach ($parts as $part) {
            if (strlen($part) >= 2) {
                $words[] = $part;
            }
        }

        return $words;
    }

    /**
     * Resolve a stored relative path for import: honors explicit cells, ignores export placeholders,
     * and auto-discovers under {@see PUBLIC_MEALS_DIRECTORY} when the cell is empty.
     *
     * @param  string|null  $photoUrlCell  Raw CSV {@code photo_url} value, or {@code null} when the column is absent.
     */
    public static function resolveImagePathForImport(?string $photoUrlCell, string $mealName): ?string
    {
        $mealName = trim($mealName);
        $normalized = $photoUrlCell !== null
            ? self::normalizeCsvPhotoCell($photoUrlCell)
            : null;

        if ($normalized !== null) {
            if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
                return $normalized;
            }

            $existing = self::findExistingRelativePath($normalized);
            if ($existing !== null) {
                return $existing;
            }

            if ($mealName !== '') {
                $discovered = self::discoverInPublicMealsDirectory($normalized, $mealName);
                if ($discovered !== null) {
                    return $discovered;
                }
            }

            return $normalized;
        }

        if ($mealName === '') {
            return null;
        }

        return self::discoverRelativePathForMealTitle($mealName);
    }

    /**
     * Normalize a CSV {@code photo_url} cell; returns {@code null} for blank cells and export placeholders.
     */
    public static function normalizeCsvPhotoCell(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = self::stripMarkdownLink(trim(str_replace('\\', '/', $raw)));
        if ($s === '' || self::isMissingPhotoPlaceholder($s)) {
            return null;
        }

        return self::normalizeForDatabase($s);
    }

    /**
     * Discover an existing file under {@see PUBLIC_MEALS_DIRECTORY} for a meal title (loose slug match).
     */
    public static function discoverRelativePathForMealTitle(string $mealTitle): ?string
    {
        $mealTitle = trim($mealTitle);
        if ($mealTitle === '') {
            return null;
        }

        return self::discoverInPublicMealsDirectory('', $mealTitle);
    }

    public static function normalizeForDatabase(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = self::stripMarkdownLink(trim(str_replace('\\', '/', $raw)));
        if ($s === '' || self::isMissingPhotoPlaceholder($s)) {
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
        $index = self::publicMealsLooseSlugIndex();
        if ($index === []) {
            return null;
        }

        $wantedBase = pathinfo($storedRelative, PATHINFO_FILENAME);
        /** @var list<string> $legacyCandidates */
        $legacyCandidates = [];
        if ($mealTitle !== null && trim($mealTitle) !== '') {
            $legacyCandidates[] = self::mealTitleToImageFileBase($mealTitle);
        }
        if (is_string($wantedBase) && $wantedBase !== '' && $wantedBase !== '.') {
            $legacyCandidates[] = $wantedBase;
        }

        foreach ($legacyCandidates as $candidate) {
            foreach ($index as $fileSlug => $relative) {
                if (strcasecmp(pathinfo($relative, PATHINFO_FILENAME), $candidate) === 0) {
                    return $relative;
                }
            }
        }

        /** @var list<string> $looseNeedles */
        $looseNeedles = [];
        if ($mealTitle !== null && trim($mealTitle) !== '') {
            $titleSlug = self::looseMatchSlug($mealTitle);
            if ($titleSlug !== '') {
                $looseNeedles[] = $titleSlug;
            }
        }
        if (is_string($wantedBase) && $wantedBase !== '' && $wantedBase !== '.') {
            $hintSlug = self::looseMatchSlug($wantedBase);
            if ($hintSlug !== '' && ! in_array($hintSlug, $looseNeedles, true)) {
                $looseNeedles[] = $hintSlug;
            }
        }

        foreach ($looseNeedles as $needle) {
            if (isset($index[$needle])) {
                return $index[$needle];
            }
        }

        $wantedBaseStr = is_string($wantedBase) ? $wantedBase : '';
        $wordMatch = self::discoverByLooseWordSubsequence($index, $mealTitle, $wantedBaseStr);
        if ($wordMatch !== null) {
            return $wordMatch;
        }

        return self::discoverByLooseSlugSubstring($index, $looseNeedles);
    }

    /**
     * Match when every significant word in the filename appears in the meal title in order
     * (e.g. {@code marinated_pineapple_peppers_salad} ↔ long salad title).
     *
     * @param  array<string, string>  $index
     */
    private static function discoverByLooseWordSubsequence(array $index, ?string $mealTitle, string $wantedBase): ?string
    {
        /** @var list<list<string>> $titleWordLists */
        $titleWordLists = [];
        if ($mealTitle !== null && trim($mealTitle) !== '') {
            $words = self::looseMatchWords($mealTitle);
            if ($words !== []) {
                $titleWordLists[] = $words;
            }
        }
        if (is_string($wantedBase) && $wantedBase !== '' && $wantedBase !== '.') {
            $hintWords = self::looseMatchWords($wantedBase);
            if ($hintWords !== [] && ! in_array($hintWords, $titleWordLists, true)) {
                $titleWordLists[] = $hintWords;
            }
        }

        if ($titleWordLists === []) {
            return null;
        }

        $bestPath = null;
        $bestScore = 0;

        foreach ($index as $relative) {
            $fileWords = self::looseMatchWords(pathinfo($relative, PATHINFO_FILENAME));
            if (count($fileWords) < 2) {
                continue;
            }

            foreach ($titleWordLists as $titleWords) {
                if (! self::wordsAreOrderedSubsequence($fileWords, $titleWords)) {
                    continue;
                }

                $score = count($fileWords);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPath = $relative;
                }
            }
        }

        return $bestPath;
    }

    /**
     * @param  list<string>  $needleWords
     * @param  list<string>  $haystackWords
     */
    private static function wordsAreOrderedSubsequence(array $needleWords, array $haystackWords): bool
    {
        $needleIndex = 0;
        $needleCount = count($needleWords);

        if ($needleCount === 0) {
            return false;
        }

        foreach ($haystackWords as $word) {
            if ($word === $needleWords[$needleIndex]) {
                $needleIndex++;
                if ($needleIndex === $needleCount) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $index
     * @param  list<string>  $needles
     */
    private static function discoverByLooseSlugSubstring(array $index, array $needles): ?string
    {
        $bestPath = null;
        $bestScore = 0;

        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }

            foreach ($index as $fileSlug => $relative) {
                if ($fileSlug === '' || $fileSlug === 'placeholder') {
                    continue;
                }

                $matches = false;
                $score = 0;

                if (str_contains($needle, $fileSlug) && strlen($fileSlug) >= self::LOOSE_SLUG_SUBSTRING_MIN_LENGTH) {
                    $matches = true;
                    $score = strlen($fileSlug);
                } elseif (str_contains($fileSlug, $needle) && strlen($needle) >= self::LOOSE_SLUG_SUBSTRING_MIN_LENGTH) {
                    $matches = true;
                    $score = strlen($needle);
                }

                if ($matches && $score > $bestScore) {
                    $bestScore = $score;
                    $bestPath = $relative;
                }
            }
        }

        return $bestPath;
    }

    /**
     * @return array<string, string>
     */
    private static function publicMealsLooseSlugIndex(): array
    {
        if (self::$publicMealsLooseSlugIndex !== null) {
            return self::$publicMealsLooseSlugIndex;
        }

        $index = [];
        $dirPath = public_path(self::PUBLIC_MEALS_DIRECTORY);
        if (! is_dir($dirPath)) {
            self::$publicMealsLooseSlugIndex = $index;

            return $index;
        }

        $files = scandir($dirPath);
        if ($files === false) {
            self::$publicMealsLooseSlugIndex = $index;

            return $index;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (! self::hasImageExtension($file)) {
                continue;
            }

            $slug = self::looseMatchSlug($file);
            if ($slug === '' || $slug === 'placeholder') {
                continue;
            }

            $relative = self::PUBLIC_MEALS_DIRECTORY.'/'.$file;
            if (! isset($index[$slug])) {
                $index[$slug] = $relative;
            }
        }

        self::$publicMealsLooseSlugIndex = $index;

        return $index;
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
