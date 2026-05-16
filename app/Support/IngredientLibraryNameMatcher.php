<?php

namespace App\Support;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealCsvLibraryImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resolves CSV / meal-ingredient labels to verified library rows using normalized and fuzzy name matching.
 */
final class IngredientLibraryNameMatcher
{
    /**
     * Trim, lowercase, collapse whitespace, and strip decorative trailing punctuation.
     */
    public static function normalizeLookupKey(string $name): string
    {
        $n = MealCsvLibraryImportService::normalizeMealNameKey($name);
        $n = preg_replace('/[.,;:!?\'"`]+$/u', '', $n) ?? $n;
        $n = preg_replace('/^[.,;:!?\'"`]+/u', '', $n) ?? $n;

        return trim($n);
    }

    /**
     * @return list<string>
     */
    public static function lookupNeedlesFromLabel(string $label): array
    {
        $needles = [];
        foreach ([$label, self::stripLeadingMeasureTokens($label), self::stripBaseRecipeSuffix($label)] as $candidate) {
            $key = self::normalizeLookupKey($candidate);
            if ($key !== '' && ! in_array($key, $needles, true)) {
                $needles[] = $key;
            }
        }

        return $needles;
    }

    public static function labelIndicatesBaseRecipe(string $label): bool
    {
        return (bool) preg_match('/\(\s*base(?:\s+recipe)?\s*\)\s*$/iu', trim($label));
    }

    public static function stripBaseRecipeSuffix(string $label): string
    {
        $trimmed = trim($label);
        $stripped = preg_replace('/\s*\(\s*base(?:\s+recipe)?\s*\)\s*$/iu', '', $trimmed) ?? $trimmed;
        $stripped = preg_replace('/\s*-\s*base(?:\s+recipe)?\s*$/iu', '', $stripped) ?? $stripped;

        return trim($stripped);
    }

    /**
     * @param  list<string>  $normalizedKeys  Keys from {@see normalizeLookupKey} on segment labels
     * @return Collection<string, Ingredient> keyed by normalized lookup key
     */
    public static function resolveByNormalizedKeys(array $normalizedKeys): Collection
    {
        $unique = array_values(array_unique(array_filter($normalizedKeys, static fn (string $k): bool => $k !== '')));
        if ($unique === []) {
            return collect();
        }

        $resolved = collect();

        $exact = self::queryMealImportLibrary()
            ->where(function ($q) use ($unique): void {
                foreach ($unique as $norm) {
                    $q->orWhereRaw('lower(trim(name)) = ?', [$norm])
                        ->orWhereRaw('lower(trim(COALESCE(standardized_name, ""))) = ?', [$norm]);
                }
            })
            ->get();

        foreach ($exact as $ingredient) {
            $key = self::normalizeLookupKey((string) $ingredient->name);
            $resolved->put($key, $ingredient);
        }

        $stillMissing = array_values(array_filter(
            $unique,
            static fn (string $key): bool => ! $resolved->has($key),
        ));

        foreach ($stillMissing as $needle) {
            $match = self::fuzzyResolveNeedle($needle);
            if ($match !== null) {
                $resolved->put($needle, $match);
            }
        }

        return $resolved;
    }

    /**
     * Resolve one import label, with punctuation-stripped fuzzy fallback before giving up.
     */
    public static function resolveForImportLabel(string $label): ?Ingredient
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        if (self::labelIndicatesBaseRecipe($label)) {
            $baseFirst = self::resolvePreparedOrMealLinkedBaseIngredient($label);
            if ($baseFirst !== null) {
                return $baseFirst;
            }
        }

        $primaryKey = self::normalizeLookupKey($label);
        $resolved = self::resolveByLabels([$label]);
        $ingredient = $resolved->get($primaryKey);

        if ($ingredient !== null) {
            return $ingredient;
        }

        foreach (self::fuzzyLookupNeedlesFromLabel($label) as $needle) {
            if ($needle === '' || $needle === $primaryKey) {
                continue;
            }

            $ingredient = self::fuzzyResolveNeedle($needle);
            if ($ingredient !== null) {
                Log::info('Meal CSV import: resolved ingredient via fuzzy fallback.', [
                    'label' => $label,
                    'needle' => $needle,
                    'matched_name' => $ingredient->name,
                ]);

                return $ingredient;
            }
        }

        $baseResolved = self::resolvePreparedOrMealLinkedBaseIngredient($label);
        if ($baseResolved !== null) {
            Log::info('Meal CSV import: resolved base recipe ingredient.', [
                'label' => $label,
                'matched_name' => $baseResolved->name,
                'matched_id' => $baseResolved->id,
            ]);

            return $baseResolved;
        }

        Log::warning('Meal CSV import: could not map ingredient label to library item.', [
            'label' => $label,
            'normalized' => $primaryKey,
            'fuzzy_needles_tried' => self::fuzzyLookupNeedlesFromLabel($label),
        ]);

        return null;
    }

    /**
     * @return list<string>
     */
    public static function fuzzyLookupNeedlesFromLabel(string $label): array
    {
        $needles = [];
        $candidates = [
            $label,
            self::stripFormattingArtifacts($label),
            self::alphanumericNeedle($label),
        ];

        $candidates = array_merge(
            $candidates,
            self::namePartNeedlesFromQuantityLabel($label),
            self::descriptorNeedlesFromLabel($label),
            self::tokenNeedlesFromLabel($label),
        );

        foreach ($candidates as $candidate) {
            $key = self::normalizeLookupKey($candidate);
            if ($key !== '' && ! in_array($key, $needles, true)) {
                $needles[] = $key;
            }
        }

        return $needles;
    }

    /**
     * @return list<string>
     */
    public static function namePartNeedlesFromQuantityLabel(string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            return [];
        }

        $unit = 'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|g|kg|ml|ltr|tsp|tbsp|\bl\b';
        if (! preg_match('/^(.*)\s*\(\s*(\d+(?:[.,]\d+)?)\s*('.$unit.')\s*\)\s*$/iu', $label, $m)) {
            return [];
        }

        $namePart = trim((string) $m[1]);
        if ($namePart === '') {
            return [];
        }

        $out = [$namePart];
        $withoutParens = trim((string) (preg_replace('/\s*\([^)]*\)\s*/u', ' ', $namePart) ?? $namePart));
        if ($withoutParens !== '' && $withoutParens !== $namePart) {
            $out[] = $withoutParens;
        }

        return $out;
    }

    public static function stripFormattingArtifacts(string $label): string
    {
        $s = preg_replace('/\*+|_+|~+|`+/u', '', $label) ?? $label;
        $s = preg_replace('/[“”‘’"\'`]+/u', '', $s) ?? $s;
        $s = preg_replace('/[.,;:!?]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    public static function alphanumericNeedle(string $label): string
    {
        $withoutMeasure = self::stripLeadingMeasureTokens($label);
        $s = preg_replace('/[^a-z0-9\s]/iu', ' ', $withoutMeasure) ?? $withoutMeasure;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * Needles from descriptive parentheses without a weight, e.g. {@code Olive Oil (Extra Virgin)}.
     *
     * @return list<string>
     */
    public static function descriptorNeedlesFromLabel(string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            return [];
        }

        $unit = 'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|g|kg|ml|ltr|tsp|tbsp|\bl\b';
        if (preg_match('/\(\s*\d+(?:[.,]\d+)?\s*(?:'.$unit.')\s*\)/iu', $label)) {
            return [];
        }

        if (! preg_match_all('/\(([^()]+)\)/u', $label, $matches)) {
            return [];
        }

        $needles = [];
        $withoutParens = trim((string) (preg_replace('/\s*\([^)]*\)\s*/u', ' ', $label) ?? $label));
        if ($withoutParens !== '') {
            $needles[] = $withoutParens;
        }

        foreach ($matches[1] as $inner) {
            $inner = trim((string) $inner);
            if ($inner === '' || preg_match('/^base(?:\s+recipe)?$/iu', $inner)) {
                continue;
            }
            if (! preg_match('/^\d+(?:[.,]\d+)?\s*(?:'.$unit.')?$/iu', $inner)) {
                $needles[] = $inner;
            }
        }

        return $needles;
    }

    /**
     * @return list<string>
     */
    public static function tokenNeedlesFromLabel(string $label): array
    {
        $normalized = self::normalizeLookupKey(self::alphanumericNeedle($label));
        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 3,
        ));

        if (count($tokens) < 2) {
            return [];
        }

        return [$normalized];
    }

    /**
     * @param  list<string>  $labels  Raw ingredient labels from CSV segments
     * @return Collection<string, Ingredient> keyed by {@see normalizeLookupKey($label)}
     */
    public static function resolveByLabels(array $labels): Collection
    {
        $labelToKey = [];
        $keys = [];

        foreach ($labels as $label) {
            $label = trim($label);
            if ($label === '') {
                continue;
            }
            foreach (self::lookupNeedlesFromLabel($label) as $needle) {
                $labelToKey[$needle] = self::normalizeLookupKey($label);
                $keys[] = $needle;
            }
        }

        $byNeedle = self::resolveByNormalizedKeys($keys);
        $out = collect();

        foreach ($labelToKey as $needle => $labelKey) {
            $ingredient = $byNeedle->get($needle);
            if ($ingredient !== null && ! $out->has($labelKey)) {
                $out->put($labelKey, $ingredient);
            }
        }

        return $out;
    }

    private static function stripLeadingMeasureTokens(string $label): string
    {
        $trimmed = trim($label);
        $stripped = preg_replace(
            '/^\d+(?:[.,]\d+)?\s*(?:g|gram|grams|kg|kilograms?|oz|ounce|ounces|lb|lbs|pounds?|ml|milliliters?|l|liters?)\b\.?\s*/iu',
            '',
            $trimmed,
        );

        return is_string($stripped) ? trim($stripped) : $trimmed;
    }

    public static function resolvePreparedOrMealLinkedBaseIngredient(string $label): ?Ingredient
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $label,
            self::stripBaseRecipeSuffix($label),
        ], static fn (string $c): bool => trim($c) !== '')));

        foreach ($candidates as $candidate) {
            $prepared = self::resolvePreparedBaseIngredientByName($candidate);
            if ($prepared !== null) {
                return $prepared;
            }
        }

        if (self::labelIndicatesBaseRecipe($label) || self::labelIndicatesBaseRecipe(self::stripBaseRecipeSuffix($label))) {
            foreach ($candidates as $candidate) {
                $linked = self::resolveMealLinkedBaseIngredient($candidate);
                if ($linked !== null) {
                    return $linked;
                }
            }
        }

        return null;
    }

    private static function resolvePreparedBaseIngredientByName(string $name): ?Ingredient
    {
        $name = trim(self::stripBaseRecipeSuffix($name));
        if ($name === '') {
            return null;
        }

        $norm = self::normalizeLookupKey($name);

        return Ingredient::query()
            ->where('is_verified', true)
            ->whereIn('usda_food_category', IngredientLibraryCategory::preparedLabels())
            ->where(function ($q) use ($norm): void {
                $q->whereRaw('lower(trim(name)) = ?', [$norm])
                    ->orWhereRaw('lower(trim(COALESCE(standardized_name, ""))) = ?', [$norm]);
            })
            ->orderBy('id')
            ->first();
    }

    private static function resolveMealLinkedBaseIngredient(string $name): ?Ingredient
    {
        $name = trim(self::stripBaseRecipeSuffix($name));
        if ($name === '') {
            return null;
        }

        $norm = self::normalizeLookupKey($name);

        $meal = Meal::query()
            ->where(function ($query): void {
                $query->where('meal_type', MealType::BaseRecipe->value)
                    ->orWhere('category', RecipeCategory::BaseRecipe->value);
            })
            ->whereRaw('lower(trim(name)) = ?', [$norm])
            ->orderByDesc('updated_at')
            ->first();

        if ($meal === null) {
            return null;
        }

        return Ingredient::query()
            ->where('is_verified', true)
            ->where('source_meal_id', $meal->id)
            ->orderBy('id')
            ->first();
    }

    private static function fuzzyResolveNeedle(string $needle): ?Ingredient
    {
        if ($needle === '') {
            return null;
        }

        $base = self::queryMealImportLibrary();

        if (mb_strlen($needle) >= 3) {
            $prefix = (clone $base)
                ->where(function ($q) use ($needle): void {
                    $q->whereRaw('lower(trim(name)) LIKE ?', [$needle.'%'])
                        ->orWhereRaw('lower(trim(COALESCE(standardized_name, ""))) LIKE ?', [$needle.'%']);
                })
                ->orderByRaw('CASE WHEN lower(trim(name)) = ? THEN 0 WHEN lower(trim(name)) LIKE ? THEN 1 ELSE 2 END', [$needle, $needle.'%'])
                ->orderByRaw('LENGTH(trim(name)) ASC')
                ->orderBy('id')
                ->first();

            if ($prefix !== null) {
                return $prefix;
            }
        }

        if (mb_strlen($needle) >= 4) {
            $contains = (clone $base)
                ->where(function ($q) use ($needle): void {
                    $like = '%'.$needle.'%';
                    $q->whereRaw('lower(trim(name)) LIKE ?', [$like])
                        ->orWhereRaw('lower(trim(COALESCE(standardized_name, ""))) LIKE ?', [$like]);
                })
                ->orderByRaw('LENGTH(trim(name)) ASC')
                ->orderBy('id')
                ->first();

            if ($contains !== null) {
                return $contains;
            }
        }

        return self::fuzzyResolveByAllTokens($needle, $base);
    }

    /**
     * @param  Builder<Ingredient>  $base
     */
    private static function fuzzyResolveByAllTokens(string $needle, Builder $base): ?Ingredient
    {
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $needle) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 3,
        ));

        if (count($tokens) < 2) {
            return null;
        }

        $query = clone $base;

        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $query->where(function ($q) use ($like): void {
                $q->whereRaw('lower(trim(name)) LIKE ?', [$like])
                    ->orWhereRaw('lower(trim(COALESCE(standardized_name, ""))) LIKE ?', [$like]);
            });
        }

        return $query
            ->orderByRaw('LENGTH(trim(name)) ASC')
            ->orderBy('id')
            ->first();
    }

    /**
     * Same visibility as the ingredient library list: any verified row may be referenced by meal CSV.
     *
     * @return Builder<Ingredient>
     */
    private static function queryMealImportLibrary()
    {
        return Ingredient::query()->where('is_verified', true);
    }
}
