<?php

namespace App\Support;

/**
 * Canonical meal Craft master CSV column headers and import alias resolution.
 *
 * {@see MASTER_HEADERS} are the preferred short export/import labels. Legacy long headers
 * remain accepted via {@see MealCsvLibraryImportService} fuzzy matching.
 */
final class MealCsvHeaderCatalog
{
    /**
     * Preferred concise headers for master meal CSV export, templates, and imports.
     *
     * @var list<string>
     */
    public const MASTER_HEADERS = [
        'name',
        'description',
        'meal_tags',
        'cycle_phase',
        'dietary_tags',
        'safety_alerts',
        'ingredients',
        'instructions',
        'photo_url',
        'target_cal',
        'target_pro',
        'target_fat',
        'target_carbs',
        'calc_cal',
        'calc_pro',
        'calc_fat',
        'calc_carbs',
        'variance_notes',
    ];

    /**
     * Normalized header token (lowercase, underscores as spaces) → internal import field key.
     *
     * @var array<string, string>
     */
    private const SHORT_CANONICAL_KEYS = [
        'name' => 'meal_name',
        'meal name' => 'meal_name',
        'meal_name' => 'meal_name',
        'description' => 'short_description',
        'short description' => 'short_description',
        'meal tags' => 'meal_plan_tags',
        'meal plan tag' => 'meal_plan_tags',
        'cycle phase' => 'cycle_phases',
        'dietary tags' => 'dietary_tags',
        'safety alerts' => 'safety_alerts',
        'ingredients' => 'ingredient_quantities',
        'ingredients string' => 'ingredient_quantities',
        'meal type' => 'category',
        'instructions' => 'instructions',
        'photo url' => 'meal_image_path',
        'image url' => 'meal_image_path',
        'target cal' => 'target_calories',
        'target calories' => 'target_calories',
        'target pro' => 'target_protein',
        'target protein' => 'target_protein',
        'target fat' => 'target_fat',
        'target carbs' => 'target_carbs',
        'batch calories' => 'batch_calories',
        'batch protein' => 'batch_protein',
        'batch carbs' => 'batch_carbs',
        'batch fat' => 'batch_fat',
        'is bulk' => 'is_bulk',
        'is_bulk' => 'is_bulk',
        'servings count' => 'servings_count',
        'servings_count' => 'servings_count',
        'calc cal' => 'calculated_calories',
        'calc pro' => 'calculated_protein',
        'calc fat' => 'calculated_fat',
        'calc carbs' => 'calculated_carbs',
        'variance notes' => 'variance_notes',
    ];

    /**
     * @param  list<string|null>  $headerLine
     * @return list<string>
     */
    public static function sanitizeHeaderLine(array $headerLine): array
    {
        $out = [];
        foreach ($headerLine as $label) {
            $sanitized = self::sanitizeHeaderLabel((string) $label);
            if ($sanitized !== '') {
                $out[] = $sanitized;
            }
        }

        return $out;
    }

    public static function sanitizeHeaderLabel(string $label): string
    {
        if (str_starts_with($label, "\xEF\xBB\xBF")) {
            $label = substr($label, 3);
        }

        $label = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $label) ?? $label;
        $label = trim($label);
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;

        return trim($label);
    }

    public static function normalizeHeaderToken(string $label): string
    {
        $t = strtolower(self::sanitizeHeaderLabel($label));
        $t = str_replace(['_', '-'], ' ', $t);
        $t = str_replace(['/', '\\'], ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return trim($t);
    }

    public static function shortCanonicalKey(string $normalizedToken): ?string
    {
        if ($normalizedToken === '') {
            return null;
        }

        return self::SHORT_CANONICAL_KEYS[$normalizedToken] ?? null;
    }

    /**
     * Whether the header row matches the production meal CSV column order
     * ({@see MenuDevelopmentCsv::MEAL_HEADERS}), including bulk columns at indices 11 and 12.
     *
     * @param  list<string|null>  $headerLine
     */
    public static function matchesProductionMealHeaderRow(array $headerLine): bool
    {
        if (count($headerLine) < MenuDevelopmentCsv::MEAL_SERVINGS_COUNT_COLUMN_INDEX + 1) {
            return false;
        }

        foreach (MenuDevelopmentCsv::MEAL_HEADERS as $index => $expectedHeader) {
            if (! isset($headerLine[$index])) {
                return false;
            }

            $actualToken = self::normalizeHeaderToken((string) $headerLine[$index]);
            $expectedToken = self::normalizeHeaderToken($expectedHeader);

            if ($actualToken === '' || $expectedToken === '') {
                return false;
            }

            $actualKey = self::shortCanonicalKey($actualToken);
            $expectedKey = self::shortCanonicalKey($expectedToken);

            if ($actualKey === null || $expectedKey === null || $actualKey !== $expectedKey) {
                return false;
            }
        }

        return true;
    }

    /**
     * Human-readable labels for required-column validation errors.
     *
     * @return list<string>
     */
    public static function mealNameHeaderHints(): array
    {
        return ['meal_name', 'name', 'Meal_Name', 'Meal Name'];
    }

    /**
     * @return list<string>
     */
    public static function ingredientQuantitiesHeaderHints(): array
    {
        return ['ingredients_string', 'ingredients', 'Ingredient_Quantities', 'Ingredients String'];
    }
}
