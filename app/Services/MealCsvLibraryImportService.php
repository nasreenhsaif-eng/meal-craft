<?php

namespace App\Services;

use App\Enums\CyclePhase;
use App\Enums\MealType;
use App\Enums\RecipeAmountUnit;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealCsvImportPendingRow;
use App\Models\User;
use App\Support\IngredientLibraryCategory;
use App\Support\IngredientLibraryNameMatcher;
use App\Support\IngredientQuantityStringParser;
use App\Support\MealCsvHeaderCatalog;
use App\Support\MealImagePath;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryBulkNutrition;
use App\Support\MealLibraryDelimitedCellParser;
use App\Support\MealLibraryTaxonomy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Imports meal rows from CSV using the ingredient library (per-100 g nutrition).
 *
 * CSV columns (headers matched case-insensitively):
 * - name / Meal_Name / Meal Name
 * - Category **or** Meal Type (optional; defaults to Meal when omitted or blank)
 * - Ingredient_Quantities, Ingredients String, or Ingredients (pipe-separated quantities)
 * - Instructions (optional)
 * - Short_Description / Description_Highlight / Description (optional; one-line meal summary)
 * - Instructions (optional; cooking steps)
 * - Meal_Plan_Tags / Meal Plan Tag (optional)
 * - Cycle_Phase / Cycle phase (optional)
 * - Total_Calories (optional)
 * - Target Calories / Target Protein / Target Carbs / Target Fat (optional)
 * - Batch Calories / Batch Protein / Batch Carbs / Batch Fat (optional; used when Is Bulk is true)
 * - Is Bulk / Servings Count (optional; servings required when bulk is true)
 * - Safety Alerts (optional; comma or pipe separated labels)
 * - Image_URL (optional)
 */
final class MealCsvLibraryImportService
{
    /**
     * Bulk library import / synchronized export columns (must match {@see MealLibrarySynchronizedCsvExport}).
     *
     * @var list<string>
     */
    public const LIBRARY_CSV_HEADERS = [
        'Meal_Name',
        'Category',
        'Ingredient_Quantities',
        'Instructions',
        'Description_Highlight',
        'Meal_Plan_Tags',
        'Cycle_Phase',
        'Total_Calories',
        'Image_URL',
    ];

    private const STATUS_IMPORTED = 'imported';

    private const STATUS_UPDATED = 'updated';

    private const STATUS_PENDING_INGREDIENTS = 'pending_ingredient_input';

    private const STATUS_ERROR = 'error';

    /**
     * @return list<RecipeCategory>
     */
    public static function mealLibraryCsvAllowedCategories(): array
    {
        return [
            RecipeCategory::Breakfast,
            RecipeCategory::Meal,
            RecipeCategory::MainSalad,
            RecipeCategory::SideSalad,
            RecipeCategory::Soup,
            RecipeCategory::Dessert,
        ];
    }

    /**
     * Common spreadsheet labels mapped to library categories (case-insensitive).
     *
     * @return array<string, RecipeCategory>
     */
    private static function mealLibraryCategoryAliases(): array
    {
        return [
            'lunch' => RecipeCategory::Meal,
            'dinner' => RecipeCategory::Meal,
            'main' => RecipeCategory::Meal,
            'main course' => RecipeCategory::Meal,
            'entree' => RecipeCategory::Meal,
            'entrée' => RecipeCategory::Meal,
            'salad' => RecipeCategory::SideSalad,
            'snack' => RecipeCategory::Dessert,
            'treat' => RecipeCategory::Dessert,
            'treats' => RecipeCategory::Dessert,
            'sweet' => RecipeCategory::Dessert,
            'sweets' => RecipeCategory::Dessert,
        ];
    }

    /**
     * @return array{
     *     summary: array{
     *         imported: int,
     *         updated: int,
     *         duplicates_created: int,
     *         pending_ingredient_input: int,
     *         errors: int
     *     },
     *     unique_pending_ingredients: list<string>,
     *     csv_unrecognized_headers: list<string>,
     *     rows: list<array{
     *         line: int,
     *         status: string,
     *         meal_name?: string,
     *         meal_id?: int,
     *         category?: string,
     *         pending_ingredients?: list<string>,
     *         message?: string,
     *         health_score?: float,
     *         warnings?: list<string>
     *     }>
     * }
     */
    public function processPath(string $path, ?User $user = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => (string) __('The CSV file could not be read.'),
                ]],
            ];
        }

        return $this->processUploadedFile(new UploadedFile(
            $path,
            basename($path),
            'text/csv',
            null,
            true,
        ), $user);
    }

    public function processUploadedFile(UploadedFile $file, ?User $user = null): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            Log::error('Meal library CSV import failed: uploaded file could not be read.', [
                'original_name' => $file->getClientOriginalName(),
            ]);

            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => (string) __('The uploaded file could not be read.'),
                ]],
            ];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            Log::error('Meal library CSV import failed: fopen.', [
                'original_name' => $file->getClientOriginalName(),
            ]);

            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => (string) __('The uploaded file could not be opened.'),
                ]],
            ];
        }

        $headerLine = fgetcsv($handle);
        if ($headerLine === false) {
            fclose($handle);

            Log::error('Meal library CSV import failed: empty file or unreadable header row.');

            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => (string) __('The CSV is empty.'),
                ]],
            ];
        }

        $sanitizedHeaders = MealCsvHeaderCatalog::sanitizeHeaderLine($headerLine);
        $headerMap = $this->buildHeaderMap($headerLine);
        $csvUnrecognizedHeaders = $this->unrecognizedCsvHeaderLabels($headerLine);
        if (! isset($headerMap['meal_name'], $headerMap['ingredient_quantities'])) {
            fclose($handle);

            $missing = [];
            if (! isset($headerMap['meal_name'])) {
                $missing[] = implode(' / ', MealCsvHeaderCatalog::mealNameHeaderHints());
            }
            if (! isset($headerMap['ingredient_quantities'])) {
                $missing[] = implode(' / ', MealCsvHeaderCatalog::ingredientQuantitiesHeaderHints());
            }

            $message = __('CSV must include these required columns: :cols.', [
                'cols' => implode(', ', $missing),
            ]);

            Log::error('Meal library CSV import failed: missing required header columns.', [
                'sanitized_headers' => $sanitizedHeaders,
                'header_row' => $headerLine,
                'mapped_keys' => array_keys($headerMap),
                'missing' => $missing,
                'csv_unrecognized_headers' => $csvUnrecognizedHeaders,
            ]);

            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => $csvUnrecognizedHeaders,
                'rows' => [[
                    'line' => 1,
                    'status' => self::STATUS_ERROR,
                    'message' => (string) $message,
                ]],
            ];
        }

        if ($csvUnrecognizedHeaders !== []) {
            Log::warning('Meal library CSV import: unrecognized header labels (import continues).', [
                'sanitized_headers' => $sanitizedHeaders,
                'csv_unrecognized_headers' => $csvUnrecognizedHeaders,
                'mapped_keys' => array_keys($headerMap),
            ]);
        }

        MealImagePath::resetPublicMealsSlugIndex();

        $lineNumber = 1;
        $rowsOut = [];
        $imported = 0;
        $updated = 0;
        $ingredientLibraryImported = 0;
        $ingredientLibraryUpdated = 0;
        $pending = 0;
        $errors = 0;

        /** @var array<string, Meal> $mealsByNormalizedName */
        $mealsByNormalizedName = $this->indexMealsByNormalizedName();
        $authenticatedUserId = $user?->id;

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($this->rowIsBlank($data)) {
                continue;
            }

            $assoc = $this->associateRow($headerMap, $data);
            $result = $this->importMealCsvAssocRow(
                $lineNumber,
                $assoc,
                $data,
                $mealsByNormalizedName,
                $authenticatedUserId,
                $authenticatedUserId !== null,
            );

            $rowsOut[] = $result['row'];

            match ($result['outcome']) {
                'imported' => $imported++,
                'updated' => $updated++,
                'ingredient_imported' => $ingredientLibraryImported++,
                'ingredient_updated' => $ingredientLibraryUpdated++,
                'pending' => $pending++,
                'error' => $errors++,
            };
        }

        fclose($handle);

        return [
            'summary' => [
                'imported' => $imported,
                'updated' => $updated,
                'ingredient_library_imported' => $ingredientLibraryImported,
                'ingredient_library_updated' => $ingredientLibraryUpdated,
                'duplicates_created' => 0,
                'pending_ingredient_input' => $pending,
                'errors' => $errors,
            ],
            'unique_pending_ingredients' => $this->uniquePendingIngredientNamesFromRows($rowsOut),
            'csv_unrecognized_headers' => $csvUnrecognizedHeaders,
            'rows' => $rowsOut,
        ];
    }

    /**
     * Trim, lowercase, and collapse internal whitespace for meal identity matching.
     */
    public static function normalizeMealNameKey(string $name): string
    {
        $n = strtolower(trim($name));

        return preg_replace('/\s+/', ' ', $n) ?? $n;
    }

    /**
     * @return array<string, Meal>
     */
    private function indexMealsByNormalizedName(): array
    {
        $map = [];
        foreach (Meal::withTrashed()->orderByDesc('updated_at')->orderByDesc('id')->cursor() as $meal) {
            $key = self::normalizeMealNameKey($meal->name);
            if (! isset($map[$key])) {
                $map[$key] = $meal;

                continue;
            }

            $existing = $map[$key];
            if ($existing->trashed() && ! $meal->trashed()) {
                $map[$key] = $meal;
            }
        }

        return $map;
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     ingredient_library_imported: int,
     *     ingredient_library_updated: int,
     *     duplicates_created: int,
     *     pending_ingredient_input: int,
     *     errors: int
     * }
     */
    private function emptySummaryWithErrors(int $errors): array
    {
        return [
            'imported' => 0,
            'updated' => 0,
            'ingredient_library_imported' => 0,
            'ingredient_library_updated' => 0,
            'duplicates_created' => 0,
            'pending_ingredient_input' => 0,
            'errors' => $errors,
        ];
    }

    /**
     * Pure calculation for one logical CSV row (used by tests and mirrors client-side TS).
     *
     * @param  list<array{name: string, grams?: float, amount?: float, unit?: string}>  $segments
     * @return array{
     *     nutrition: array<string, float>,
     *     health_score: float,
     *     pending_ingredients: list<string>,
     *     resolved: list<array{ingredient: Ingredient, grams: float}>,
     *     calorie_warnings: list<string>
     * }
     */
    public function calculateMealNutritionFromSegments(array $segments, ?RecipeCategory $category = null): array
    {
        $pending = [];
        /** @var array<string, true> $pendingNormSeen */
        $pendingNormSeen = [];
        /** @var array<int, float> $gramsByIngredientId */
        $gramsByIngredientId = [];
        /** @var array<int, Ingredient> $ingredientsById */
        $ingredientsById = [];

        foreach ($segments as $seg) {
            $label = (string) ($seg['name'] ?? '');
            $key = IngredientLibraryNameMatcher::normalizeLookupKey($label);
            $ing = IngredientLibraryNameMatcher::resolveForImportLabel($label);
            if ($ing === null) {
                if (! isset($pendingNormSeen[$key])) {
                    $pendingNormSeen[$key] = true;
                    $pending[] = $label;
                }

                continue;
            }

            $id = (int) $ing->id;
            $ingredientsById[$id] = $ing;

            if (isset($seg['grams']) && ! isset($seg['amount'])) {
                $grams = max(0.0, (float) $seg['grams']);
            } else {
                $amount = (float) ($seg['amount'] ?? 0);
                $unit = (string) ($seg['unit'] ?? 'g');
                $grams = $this->gramsForIngredientAmountUnit($ing, $amount, $unit);
            }

            $gramsByIngredientId[$id] = ($gramsByIngredientId[$id] ?? 0.0) + $grams;
        }

        $resolved = [];
        foreach ($gramsByIngredientId as $ingredientId => $grams) {
            $ing = $ingredientsById[$ingredientId] ?? null;
            if ($ing !== null) {
                $resolved[] = ['ingredient' => $ing, 'grams' => $grams];
            }
        }

        if ($pending !== []) {
            return [
                'nutrition' => [],
                'health_score' => 0.0,
                'pending_ingredients' => $pending,
                'resolved' => [],
                'calorie_warnings' => [],
            ];
        }

        $rows = [];
        foreach ($resolved as $item) {
            $rows[] = [
                'ingredient_id' => $item['ingredient']->id,
                'amount_grams' => $item['grams'],
            ];
        }

        $nutrition = RecipeNutritionCalculator::fromRows($rows);
        $healthScore = $this->computeMealHealthScore($nutrition);

        $calorieWarnings = [];
        if ($category !== null) {
            $calorieWarnings = $this->calorieWarningsForCategory(
                $category,
                (float) ($nutrition['calories'] ?? 0.0)
            );
        }

        return [
            'nutrition' => $nutrition,
            'health_score' => $healthScore,
            'pending_ingredients' => [],
            'resolved' => $resolved,
            'calorie_warnings' => $calorieWarnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $csvRow  Keys: meal_name, category?, ingredient_quantities, instructions?, highlight?
     * @return array{
     *     nutrition: array<string, float>,
     *     health_score: float,
     *     pending_ingredients: list<string>,
     *     resolved: list<array{ingredient: Ingredient, grams: float}>,
     *     calorie_warnings: list<string>
     * }
     */
    public function calculateMealNutritionForCsvRow(array $csvRow): array
    {
        $cell = trim((string) ($csvRow['ingredient_quantities'] ?? ''));
        $segments = $this->parseIngredientQuantitySegments($cell);
        $category = $this->resolveMealLibraryCategory(trim((string) ($csvRow['category'] ?? '')));

        return $this->calculateMealNutritionFromSegments($segments, $category);
    }

    /**
     * @return list<string>
     */
    public function calorieWarningsForCategory(RecipeCategory $category, float $totalCalories): array
    {
        $warnings = [];
        $cal = round($totalCalories, 1);

        if ($category === RecipeCategory::Breakfast && $totalCalories > 250.0) {
            $warnings[] = __('Breakfast meals are typically ≤250 kcal (this meal is :cal kcal).', ['cal' => $cal]);
        }

        if ($category === RecipeCategory::Meal && ($totalCalories < 300.0 || $totalCalories > 400.0)) {
            $warnings[] = __('“Meal” category targets are typically 300–400 kcal (this meal is :cal kcal).', ['cal' => $cal]);
        }

        if ($category === RecipeCategory::BaseRecipe && ($totalCalories < 300.0 || $totalCalories > 400.0)) {
            $warnings[] = __('“Base Recipe” batches are often planned in the same 300–400 kcal band as mains (this meal is :cal kcal).', ['cal' => $cal]);
        }

        if (
            in_array($category, [RecipeCategory::SideSalad, RecipeCategory::Soup, RecipeCategory::Dessert], true)
            && $totalCalories > 175.0
        ) {
            $warnings[] = __(
                ':category items are typically ≤175 kcal (this meal is :cal kcal).',
                ['category' => $category->value, 'cal' => $cal]
            );
        }

        return $warnings;
    }

    private function resolveMealLibraryCategory(string $raw): ?RecipeCategory
    {
        if (trim($raw) === '') {
            return null;
        }

        if (BaseIngredientService::isBaseIngredientCategoryInput($raw)) {
            return RecipeCategory::BaseRecipe;
        }

        $norm = $this->normalizeCategoryLabel($raw);

        $alias = self::mealLibraryCategoryAliases()[$norm] ?? null;
        if ($alias instanceof RecipeCategory) {
            return $alias;
        }

        foreach (self::mealLibraryCsvAllowedCategories() as $case) {
            if ($norm === $this->normalizeCategoryLabel($case->value)) {
                return $case;
            }
        }

        return null;
    }

    private function resolveMealLibraryCategoryWithDefault(string $raw): RecipeCategory
    {
        return $this->resolveMealLibraryCategory($raw) ?? RecipeCategory::Meal;
    }

    private function normalizeCategoryLabel(string $raw): string
    {
        $t = strtolower(trim($raw));

        return preg_replace('/\s+/', ' ', $t) ?? $t;
    }

    /**
     * Weighted composite (0–100) from whole-meal micronutrient totals (anti-inflammatory / density proxy).
     *
     * @param  array<string, float>  $nutrition
     */
    public function computeMealHealthScore(array $nutrition): float
    {
        $fiber = (float) ($nutrition['fiber'] ?? 0);
        $vitC = (float) ($nutrition['vitamin_c'] ?? 0);
        $folate = (float) ($nutrition['b9_folate'] ?? 0);
        $mag = (float) ($nutrition['magnesium'] ?? 0);
        $iron = (float) ($nutrition['iron'] ?? 0);
        $zinc = (float) ($nutrition['zinc'] ?? 0);
        $vitE = (float) ($nutrition['vitamin_e'] ?? 0);
        $potassium = (float) ($nutrition['potassium'] ?? 0);

        $raw =
            $fiber * 1.2
            + $vitC * 0.03
            + $folate * 0.008
            + $mag * 0.012
            + $iron * 0.15
            + $zinc * 0.25
            + $vitE * 0.4
            + $potassium * 0.002;

        return round(min(100.0, max(0.0, $raw)), 2);
    }

    /**
     * @param  list<string>  $headerLine
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerLine): array
    {
        $map = [];
        foreach ($headerLine as $i => $label) {
            $key = $this->canonicalHeaderKey(MealCsvHeaderCatalog::sanitizeHeaderLabel((string) $label));
            if ($key !== null) {
                $map[$key] = (int) $i;
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $headerLine
     * @return list<string>
     */
    private function unrecognizedCsvHeaderLabels(array $headerLine): array
    {
        $out = [];
        foreach ($headerLine as $label) {
            $sanitized = MealCsvHeaderCatalog::sanitizeHeaderLabel((string) $label);
            if ($sanitized === '') {
                continue;
            }
            if ($this->canonicalHeaderKey($sanitized) === null) {
                $out[] = $sanitized;
            }
        }

        return array_values(array_unique($out));
    }

    private function stripUtf8Bom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }

    private function canonicalHeaderKey(string $label): ?string
    {
        $t = MealCsvHeaderCatalog::normalizeHeaderToken($label);
        if ($t === '') {
            return null;
        }

        $shortKey = MealCsvHeaderCatalog::shortCanonicalKey($t);
        if ($shortKey !== null) {
            return $shortKey;
        }

        if ($t === 'category' || $t === 'meal category') {
            return 'category';
        }
        if ($t === 'meal type' || (str_contains($t, 'meal') && str_contains($t, 'type') && ! str_contains($t, 'plan'))) {
            return 'category';
        }

        if ($t === 'meal name' || (str_contains($t, 'meal') && str_contains($t, 'name'))) {
            return 'meal_name';
        }

        if (
            $t === 'ingredients'
            || $t === 'ingredients string'
            || $t === 'ingredient quantities'
            || $t === 'ingredient quantities string'
            || (str_contains($t, 'ingredients') && str_contains($t, 'string'))
            || (str_contains($t, 'ingredient') && (str_contains($t, 'quantit') || str_contains($t, 'qty')))
        ) {
            return 'ingredient_quantities';
        }

        if (str_contains($t, 'meal') && str_contains($t, 'plan') && str_contains($t, 'tag')) {
            return 'meal_plan_tags';
        }

        if (str_contains($t, 'dietary') && str_contains($t, 'tag')) {
            return 'dietary_tags';
        }

        if (str_contains($t, 'cycle') && str_contains($t, 'phase')) {
            return 'cycle_phases';
        }

        if (str_contains($t, 'instruction')) {
            return 'instructions';
        }

        if (str_contains($t, 'short') && str_contains($t, 'description')) {
            return 'short_description';
        }

        if (str_contains($t, 'description') && str_contains($t, 'highlight')) {
            return 'short_description';
        }
        if ($t === 'description') {
            return 'short_description';
        }
        if (str_contains($t, 'highlight')) {
            return 'short_description';
        }

        if (str_contains($t, 'target') && (str_contains($t, 'calor') || str_contains($t, 'kcal'))) {
            return 'target_calories';
        }
        if (str_contains($t, 'target') && str_contains($t, 'protein')) {
            return 'target_protein';
        }
        if (str_contains($t, 'target') && str_contains($t, 'net') && str_contains($t, 'carb')) {
            return 'target_carbs';
        }
        if (str_contains($t, 'target') && str_contains($t, 'carb')) {
            return 'target_carbs';
        }
        if (str_contains($t, 'target') && str_contains($t, 'fat')) {
            return 'target_fat';
        }

        if (str_contains($t, 'batch') && (str_contains($t, 'calor') || str_contains($t, 'kcal'))) {
            return 'batch_calories';
        }
        if (str_contains($t, 'batch') && str_contains($t, 'protein')) {
            return 'batch_protein';
        }
        if (str_contains($t, 'batch') && str_contains($t, 'carb')) {
            return 'batch_carbs';
        }
        if (str_contains($t, 'batch') && str_contains($t, 'fat')) {
            return 'batch_fat';
        }

        if (str_contains($t, 'total') && (str_contains($t, 'calor') || str_contains($t, 'kcal'))) {
            return 'total_calories';
        }

        if (str_contains($t, 'calculated') && (str_contains($t, 'calor') || str_contains($t, 'kcal'))) {
            return 'calculated_calories';
        }
        if (str_contains($t, 'calculated') && str_contains($t, 'protein')) {
            return 'calculated_protein';
        }
        if (str_contains($t, 'calculated') && str_contains($t, 'fat')) {
            return 'calculated_fat';
        }
        if (str_contains($t, 'calculated') && str_contains($t, 'carb')) {
            return 'calculated_carbs';
        }

        if (str_contains($t, 'variance') && str_contains($t, 'note')) {
            return 'variance_notes';
        }

        if ($t === 'is bulk') {
            return 'is_bulk';
        }

        if ($t === 'servings count' || (str_contains($t, 'serving') && str_contains($t, 'count'))) {
            return 'servings_count';
        }

        if (str_contains($t, 'safety') && str_contains($t, 'alert')) {
            return 'safety_alerts';
        }

        if (
            $t === 'image url'
            || $t === 'photo url'
            || (str_contains($t, 'image') && str_contains($t, 'url'))
            || (str_contains($t, 'photo') && str_contains($t, 'url'))
        ) {
            return 'meal_image_path';
        }

        return null;
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  list<string|null>  $data
     * @return array<string, string>
     */
    private function associateRow(array $headerMap, array $data): array
    {
        $out = [];
        foreach ($headerMap as $key => $index) {
            $out[$key] = (string) ($data[$index] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $assoc
     * @return array{valid: bool, message: string, attributes: array<string, mixed>}
     */
    private function parseOptionalMealFieldsFromAssoc(array $assoc): array
    {
        $attributes = [];

        foreach (['target_calories', 'target_protein', 'target_carbs', 'target_fat'] as $key) {
            if (! array_key_exists($key, $assoc)) {
                continue;
            }
            $cell = trim((string) $assoc[$key]);
            if ($cell === '') {
                continue;
            }
            if (! is_numeric($cell)) {
                return [
                    'valid' => false,
                    'message' => __('The :field column must be a number.', ['field' => str_replace('_', ' ', $key)]),
                    'attributes' => [],
                ];
            }
            $attributes[$key] = (float) $cell;
        }

        foreach (['batch_calories', 'batch_protein', 'batch_carbs', 'batch_fat'] as $key) {
            if (! array_key_exists($key, $assoc)) {
                continue;
            }
            $cell = trim((string) $assoc[$key]);
            if ($cell === '') {
                continue;
            }
            if (! is_numeric($cell) || (float) $cell < 0) {
                return [
                    'valid' => false,
                    'message' => __('The :field column must be a non-negative number.', ['field' => str_replace('_', ' ', $key)]),
                    'attributes' => [],
                ];
            }
            $attributes[$key] = (float) $cell;
        }

        $isBulkEffective = false;
        if (array_key_exists('is_bulk', $assoc)) {
            $cell = trim((string) $assoc['is_bulk']);
            if ($cell === '') {
                $attributes['is_bulk'] = false;
            } else {
                $parsed = $this->parseCsvStrictBoolean($cell);
                if ($parsed === null) {
                    return [
                        'valid' => false,
                        'message' => __('Is Bulk must be true or false.'),
                        'attributes' => [],
                    ];
                }
                $attributes['is_bulk'] = $parsed;
            }
            $isBulkEffective = (bool) ($attributes['is_bulk'] ?? false);
        }

        if (array_key_exists('servings_count', $assoc)) {
            $cell = trim((string) $assoc['servings_count']);
            if ($cell !== '') {
                if (! is_numeric($cell) || (float) $cell <= 0) {
                    return [
                        'valid' => false,
                        'message' => __('Servings Count must be a positive number.'),
                        'attributes' => [],
                    ];
                }
                $attributes['servings_count'] = (float) $cell;
            }
        }

        if ($isBulkEffective) {
            $servings = $attributes['servings_count'] ?? null;
            if ($servings === null || $servings <= 0) {
                return [
                    'valid' => false,
                    'message' => __('Servings Count is required when Is Bulk is true.'),
                    'attributes' => [],
                ];
            }
        }

        if (array_key_exists('safety_alerts', $assoc)) {
            $cell = trim((string) $assoc['safety_alerts']);
            if ($cell !== '') {
                $attributes['safety_alert_tags'] = $this->safetyAlertLabelsFromCsvCell($cell);
            }
        }

        if (array_key_exists('dietary_tags', $assoc)) {
            $cell = trim((string) $assoc['dietary_tags']);
            if ($cell !== '') {
                $attributes['diet_tags'] = $this->dietaryTagLabelsFromCsvCell($cell);
            }
        }

        return [
            'valid' => true,
            'message' => '',
            'attributes' => $attributes,
        ];
    }

    private function parseCsvStrictBoolean(string $cell): ?bool
    {
        $s = strtolower(trim($cell));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['true', '1', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($s, ['false', '0', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function safetyAlertLabelsFromCsvCell(string $cell): array
    {
        $out = [];
        foreach (MealLibraryDelimitedCellParser::split($cell) as $token) {
            $label = trim($token);
            if ($label !== '') {
                $out[] = $label;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function dietaryTagLabelsFromCsvCell(string $cell): array
    {
        $out = [];
        foreach (MealLibraryDelimitedCellParser::split($cell) as $token) {
            $label = trim($token);
            if ($label !== '') {
                $out[] = $label;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function mealPlanTagsAcceptedFromCsvCell(string $cell): array
    {
        $out = [];
        foreach (MealLibraryDelimitedCellParser::split($cell) as $token) {
            $canonical = MealLibraryTaxonomy::resolveMealPlanTagCanonical($token);
            if ($canonical !== null) {
                $out[] = $canonical;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function cyclePhaseEnumValuesFromCsvCell(string $cell): array
    {
        $out = [];
        foreach (MealLibraryDelimitedCellParser::split($cell) as $token) {
            $phase = CyclePhase::tryFromCsvToken($token);
            if ($phase !== null) {
                $out[] = $phase->value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string|null>  $data
     */
    private function rowIsBlank(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{name: string, amount: float, unit: string}>
     */
    /**
     * @return list<array{name: string, amount?: float, unit?: string, grams?: float}>
     */
    private function parseIngredientQuantitySegments(string $cell): array
    {
        $sanitized = IngredientQuantityStringParser::sanitizeCell($cell);
        $structured = IngredientQuantityStringParser::parse($sanitized);

        if (count($structured) <= 1 && IngredientQuantityStringParser::cellLooksLikeCommaSeparatedIngredientList($sanitized)) {
            Log::warning('Meal CSV import: ingredients cell looks like a comma-separated list but parsed as a single segment; retrying with weight-group split.', [
                'segment_count' => count($structured),
                'weight_groups' => preg_match_all(
                    '/\(\s*\d+(?:[.,]\d+)?\s*(?:g|kg|ml|ltr|tsp|tbsp|grams?|milliliters?|litres?|liters?)\s*\)/iu',
                    $sanitized,
                ),
            ]);

            $structured = [];
            foreach (IngredientQuantityStringParser::splitSegments($sanitized) as $part) {
                $partParsed = IngredientQuantityStringParser::parse($part);
                if ($partParsed !== []) {
                    $structured[] = $partParsed[0];
                }
            }
        }

        return $structured;
    }

    /**
     * @param  list<string>  $pendingLabels
     * @return list<string>
     */
    private function expandPendingIngredientLabels(string $qtyCell, array $pendingLabels): array
    {
        if ($pendingLabels === []) {
            return [];
        }

        if (IngredientQuantityStringParser::cellLooksLikeCommaSeparatedIngredientList($qtyCell)) {
            $actuallyMissing = $this->unresolvedIngredientNamesFromCell($qtyCell);
            if ($actuallyMissing !== []) {
                return $actuallyMissing;
            }
        }

        $expanded = [];
        foreach ($pendingLabels as $label) {
            if (IngredientQuantityStringParser::cellLooksLikeCommaSeparatedIngredientList($label)) {
                $fromLabel = $this->unresolvedIngredientNamesFromCell($label);
                if ($fromLabel !== []) {
                    array_push($expanded, ...$fromLabel);

                    continue;
                }
            }
            $expanded[] = $label;
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Ingredient labels from a multi-segment cell that still do not resolve in the verified library.
     *
     * @return list<string>
     */
    private function unresolvedIngredientNamesFromCell(string $cell): array
    {
        $missing = [];

        foreach (IngredientQuantityStringParser::ingredientNamesFromCell($cell) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (IngredientLibraryNameMatcher::resolveForImportLabel($name) === null) {
                $missing[] = $name;
            }
        }

        return array_values(array_unique($missing));
    }

    private function gramsForIngredientAmountUnit(Ingredient $ingredient, float $amount, string $unit): float
    {
        $density = (float) ($ingredient->density ?? 0) > 0 ? (float) $ingredient->density : 1.0;
        $normalizedUnit = IngredientQuantityStringParser::normalizeUnit($unit);
        $enum = RecipeAmountUnit::tryFrom($normalizedUnit);

        if ($enum === null) {
            return max(0.0, $amount);
        }

        return RecipeIngredientUnitConverter::toGrams($amount, $enum, $density);
    }

    /**
     * @return list<string>
     */
    private function targetCalorieShortfallWarnings(float $targetCalories, float $calculatedCalories, string $mealName): array
    {
        if ($targetCalories <= 0 || $calculatedCalories <= 0) {
            return [];
        }

        if ($calculatedCalories >= $targetCalories * 0.5) {
            return [];
        }

        Log::warning('Meal CSV import: calculated calories far below Target Calories.', [
            'meal_name' => $mealName,
            'target_calories' => $targetCalories,
            'calculated_calories' => $calculatedCalories,
            'ratio' => round($calculatedCalories / $targetCalories, 3),
        ]);

        return [
            (string) __(
                'Calculated calories (:cal kcal) are less than half of Target Calories (:target kcal). Verify ingredient names resolved to the correct library entries.',
                [
                    'cal' => (int) round($calculatedCalories),
                    'target' => (int) round($targetCalories),
                ],
            ),
        ];
    }

    /**
     * Retry queued meal-library CSV rows after missing ingredients were added to the library.
     *
     * @return array{
     *     imported: int,
     *     updated: int,
     *     still_pending: int,
     *     errors: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function processPendingMealImportsForUser(User $user): array
    {
        $mealsByNormalizedName = $this->indexMealsByNormalizedName();
        $imported = 0;
        $updated = 0;
        $stillPending = 0;
        $errors = 0;
        $rowsOut = [];
        $userId = (int) $user->id;

        foreach (MealCsvImportPendingRow::query()->where('user_id', $userId)->orderBy('id')->get() as $pending) {
            $assoc = [
                'meal_name' => $pending->meal_name,
                'category' => $pending->category,
                'ingredient_quantities' => $pending->ingredient_quantities,
                'instructions' => (string) ($pending->instructions ?? ''),
                'highlight' => (string) ($pending->description_highlight ?? ''),
            ];

            $rawRow = [
                (string) $pending->id,
                $pending->meal_name,
                $pending->category,
                $pending->ingredient_quantities,
            ];

            $result = $this->importMealCsvAssocRow(
                0,
                $assoc,
                $rawRow,
                $mealsByNormalizedName,
                $userId,
                false,
            );

            $rowsOut[] = $result['row'];

            match ($result['outcome']) {
                'imported' => $imported++,
                'updated' => $updated++,
                'ingredient_imported', 'ingredient_updated' => null,
                'pending' => $stillPending++,
                'error' => $errors++,
            };
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'still_pending' => $stillPending,
            'errors' => $errors,
            'rows' => $rowsOut,
        ];
    }

    /**
     * @return array{row: array<string, mixed>, outcome: 'error'}
     */
    private function mealCsvImportAssocErrorResult(int $lineNumber, string $message, ?string $mealName = null): array
    {
        $row = [
            'line' => $lineNumber,
            'status' => self::STATUS_ERROR,
            'message' => $message,
        ];
        if ($mealName !== null && $mealName !== '') {
            $row['meal_name'] = $mealName;
        }

        return ['row' => $row, 'outcome' => 'error'];
    }

    /**
     * Import one CSV data row (assoc keys: meal_name, category, ingredient_quantities, instructions?, highlight?).
     *
     * @param  array<string, string>  $assoc
     * @param  list<string|null>  $rawRow
     * @param  array<string, Meal>  $mealsByNormalizedName
     * @return array{row: array<string, mixed>, outcome: 'imported'|'updated'|'pending'|'error'}
     */
    private function importMealCsvAssocRow(
        int $lineNumber,
        array $assoc,
        array $rawRow,
        array &$mealsByNormalizedName,
        ?int $authenticatedUserId,
        bool $queuePendingWhenMissing,
    ): array {
        $mealName = trim((string) ($assoc['meal_name'] ?? ''));
        if ($mealName === '') {
            $this->logMealLibraryImportRowFailure($lineNumber, $rawRow, $assoc, 'Meal_Name is required.');

            return $this->mealCsvImportAssocErrorResult(
                $lineNumber,
                (string) __('Meal_Name is required.'),
            );
        }

        $qtyCell = trim((string) ($assoc['ingredient_quantities'] ?? ''));
        if ($qtyCell === '') {
            $this->logMealLibraryImportRowFailure($lineNumber, $rawRow, $assoc, 'Ingredient_Quantities or Ingredients String is required.');

            return $this->mealCsvImportAssocErrorResult(
                $lineNumber,
                (string) __('Ingredient_Quantities or Ingredients String is required.'),
                $mealName,
            );
        }

        $categoryRaw = (string) ($assoc['category'] ?? '');
        $mealCategory = $this->resolveMealLibraryCategoryWithDefault($categoryRaw);
        if (trim($categoryRaw) !== '' && $this->resolveMealLibraryCategory($categoryRaw) === null) {
            $this->logMealLibraryImportRowFailure($lineNumber, $rawRow, $assoc, 'Invalid Category or Meal Type.');

            return $this->mealCsvImportAssocErrorResult(
                $lineNumber,
                (string) __('Invalid Category or Meal Type.'),
                $mealName,
            );
        }

        $optionalFields = $this->parseOptionalMealFieldsFromAssoc($assoc);
        if (! $optionalFields['valid']) {
            $this->logMealLibraryImportRowFailure($lineNumber, $rawRow, $assoc, (string) $optionalFields['message']);

            return $this->mealCsvImportAssocErrorResult(
                $lineNumber,
                (string) $optionalFields['message'],
                $mealName,
            );
        }
        $optionalMealAttrs = $optionalFields['attributes'];

        $segments = $this->parseIngredientQuantitySegments($qtyCell);
        if ($segments === []) {
            $this->logMealLibraryImportRowFailure($lineNumber, $rawRow, $assoc, 'Could not parse ingredient quantities.');

            return $this->mealCsvImportAssocErrorResult(
                $lineNumber,
                (string) __(
                    'Could not parse ingredient quantities. Use Name:amount with optional unit (g, kg, ml, …), or Name amount unit, separated by |.',
                ),
                $mealName,
            );
        }

        $instructions = MealInstructionsText::normalizeForStorage($assoc['instructions'] ?? null) ?? '';
        $shortDescription = isset($assoc['short_description']) ? trim((string) $assoc['short_description']) : '';

        $mealPlanTags = $this->mealPlanTagsAcceptedFromCsvCell((string) ($assoc['meal_plan_tags'] ?? ''));
        $cyclePhaseStrings = $this->cyclePhaseEnumValuesFromCsvCell((string) ($assoc['cycle_phases'] ?? ''));

        $hasMealImageColumn = array_key_exists('meal_image_path', $assoc);
        $resolvedImagePath = MealImagePath::resolveImagePathForImport(
            $hasMealImageColumn ? (string) ($assoc['meal_image_path'] ?? '') : null,
            $mealName,
        );

        $calc = $this->calculateMealNutritionFromSegments($segments, $mealCategory);

        if ($calc['pending_ingredients'] !== []) {
            $pendingLabels = $this->expandPendingIngredientLabels($qtyCell, $calc['pending_ingredients']);

            if ($queuePendingWhenMissing && $authenticatedUserId !== null) {
                $this->upsertPendingMealRow(
                    $authenticatedUserId,
                    $mealName,
                    $mealCategory->value,
                    $qtyCell,
                    $instructions,
                    $shortDescription,
                );
            }

            return [
                'row' => [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'category' => $mealCategory->value,
                    'status' => self::STATUS_PENDING_INGREDIENTS,
                    'pending_ingredients' => $pendingLabels,
                    'message' => __('Pending Ingredient Input — add these ingredients to the library before importing this meal.'),
                ],
                'outcome' => 'pending',
            ];
        }

        if ($mealCategory === RecipeCategory::BaseRecipe) {
            return $this->importBaseIngredientCsvRow($lineNumber, $mealName, $calc, $optionalMealAttrs);
        }

        try {
            $mealCategoryValue = $mealCategory->value;
            $normKey = self::normalizeMealNameKey($mealName);
            $existingMeal = $mealsByNormalizedName[$normKey] ?? null;

            $isBulk = (bool) ($optionalMealAttrs['is_bulk'] ?? false);
            $servingsCount = isset($optionalMealAttrs['servings_count'])
                ? (float) $optionalMealAttrs['servings_count']
                : null;
            $csvBatchMacros = MealLibraryBulkNutrition::batchMacrosFromOptionalAttributes($optionalMealAttrs);
            $mealOptionalAttrs = MealLibraryBulkNutrition::withoutBatchMacroKeys($optionalMealAttrs);
            $nutritionResolution = MealLibraryBulkNutrition::resolvePersistedNutrition(
                $calc['nutrition'],
                $isBulk,
                $servingsCount,
                $csvBatchMacros,
                $calc['resolved'] !== [],
            );

            $result = DB::transaction(function () use (
                $existingMeal,
                $mealName,
                $mealCategory,
                $mealCategoryValue,
                $instructions,
                $shortDescription,
                $calc,
                $mealPlanTags,
                $cyclePhaseStrings,
                $hasMealImageColumn,
                $resolvedImagePath,
                $mealOptionalAttrs,
                $nutritionResolution,
            ): array {
                $sync = [];
                foreach ($calc['resolved'] as $item) {
                    /** @var Ingredient $ing */
                    $ing = $item['ingredient'];
                    $grams = (float) $item['grams'];
                    $sync[$ing->id] = [
                        'amount_grams' => round($grams, 4),
                        'amount' => round($grams, 4),
                        'unit' => 'g',
                    ];
                }

                $firstPlan = $mealPlanTags[0] ?? null;
                $firstPhaseValue = $cyclePhaseStrings[0] ?? null;

                $planPhasePayload = [
                    'meal_plan_tags' => $mealPlanTags === [] ? null : $mealPlanTags,
                    'cycle_phases' => $cyclePhaseStrings === [] ? null : $cyclePhaseStrings,
                    'meal_plan_tag' => $firstPlan,
                    'cycle_phase' => $firstPhaseValue !== null ? CyclePhase::from($firstPhaseValue) : null,
                ];

                $basePayload = array_merge([
                    'name' => $mealName,
                    'category' => $mealCategoryValue,
                    'meal_type' => MealType::fromRecipeCategory($mealCategory)->value,
                    'instructions' => $instructions !== '' ? $instructions : null,
                    'short_description' => $shortDescription !== '' ? $shortDescription : null,
                    'description' => $instructions !== '' ? $instructions : null,
                    'highlight' => $shortDescription !== '' ? $shortDescription : null,
                    'health_score' => $calc['health_score'],
                    'nutrition_aggregates_synced' => $nutritionResolution['nutrition_aggregates_synced'],
                    'sickle_cell_program_highlight' => $nutritionResolution['sickle_cell_program_highlight'],
                ], $nutritionResolution['attributes'], $planPhasePayload, $mealOptionalAttrs);

                if ($hasMealImageColumn || $resolvedImagePath !== null) {
                    $basePayload['image_path'] = $resolvedImagePath;
                }

                if ($existingMeal !== null) {
                    if ($existingMeal->trashed()) {
                        $existingMeal->restore();
                    }
                    $existingMeal->refresh();
                    $existingMeal->update($basePayload);
                    $existingMeal->ingredients()->sync($sync);

                    return ['id' => (int) $existingMeal->id, 'was_update' => true];
                }

                $createPayload = array_merge($basePayload, [
                    'image_path' => $resolvedImagePath,
                ]);
                if (Schema::hasColumn('meals', 'library_sort_order')) {
                    $createPayload['library_sort_order'] = Meal::nextLibrarySortOrder();
                }
                $meal = Meal::query()->create($createPayload);
                $meal->ingredients()->sync($sync);

                return ['id' => (int) $meal->id, 'was_update' => false];
            });

            $mealId = $result['id'];
            $wasUpdate = $result['was_update'];

            $freshMeal = Meal::query()->find($mealId);
            if ($freshMeal !== null) {
                $mealsByNormalizedName[self::normalizeMealNameKey($freshMeal->name)] = $freshMeal;
                $this->clearMistakenStandaloneBaseIngredientForMealName($freshMeal->name);
                MealRecipeAsIngredientSyncService::syncFromPersistedMeal($freshMeal, false);
            }

            if ($authenticatedUserId !== null) {
                $this->clearPendingMealRowForUserAndMeal($authenticatedUserId, $mealName);
            }

            $importWarnings = array_merge(
                $calc['calorie_warnings'],
                $this->targetCalorieShortfallWarnings(
                    (float) ($optionalMealAttrs['target_calories'] ?? 0),
                    (float) ($calc['nutrition']['calories'] ?? 0),
                    $mealName,
                ),
            );

            return [
                'row' => [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'category' => $mealCategory->value,
                    'status' => $wasUpdate ? self::STATUS_UPDATED : self::STATUS_IMPORTED,
                    'meal_id' => $mealId,
                    'health_score' => $calc['health_score'],
                    'warnings' => $importWarnings,
                ],
                'outcome' => $wasUpdate ? 'updated' : 'imported',
            ];
        } catch (\Throwable $e) {
            $this->logMealLibraryImportRowFailure(
                $lineNumber,
                $rawRow,
                $assoc,
                'Exception while saving meal: '.$e->getMessage(),
                $e,
            );

            return $this->mealCsvImportAssocErrorResult(
                $lineNumber,
                (string) __('Could not save meal: :msg', ['msg' => $e->getMessage()]),
                $mealName,
            );
        }
    }

    /**
     * @param  array{
     *     nutrition: array<string, float>,
     *     resolved: list<array{ingredient: Ingredient, grams: float}>,
     *     health_score: float,
     *     calorie_warnings: list<string>,
     *     pending_ingredients: list<string>
     * }  $calc
     * @param  array<string, mixed>  $optionalMealAttrs
     * @return array{row: array<string, mixed>, outcome: 'ingredient_imported'|'ingredient_updated'|'error'}
     */
    private function importBaseIngredientCsvRow(
        int $lineNumber,
        string $name,
        array $calc,
        array $optionalMealAttrs,
    ): array {
        $finished = isset($optionalMealAttrs['finished_weight_grams'])
            ? (float) $optionalMealAttrs['finished_weight_grams']
            : null;

        $existing = Ingredient::query()
            ->where('name', $name)
            ->whereIn('usda_food_category', IngredientLibraryCategory::preparedLabels())
            ->first();

        $componentRows = [];
        foreach ($calc['resolved'] as $item) {
            $componentRows[] = [
                'ingredient_id' => (int) $item['ingredient']->getKey(),
                'amount_grams' => (float) $item['grams'],
            ];
        }

        try {
            $ingredient = app(BaseIngredientService::class)->upsert(
                $existing,
                $name,
                $componentRows,
                $finished,
            );

            Meal::query()
                ->where('name', $name)
                ->where(function ($query): void {
                    $query->where('meal_type', MealType::BaseRecipe->value)
                        ->orWhere('category', RecipeCategory::BaseRecipe->value);
                })
                ->each(function (Meal $legacyMeal): void {
                    Ingredient::query()
                        ->where('source_meal_id', $legacyMeal->id)
                        ->update(['source_meal_id' => null]);
                    $legacyMeal->delete();
                });

            $wasUpdate = $existing !== null;

            return [
                'row' => [
                    'line' => $lineNumber,
                    'meal_name' => $name,
                    'category' => IngredientLibraryCategory::BaseIngredient,
                    'status' => $wasUpdate ? self::STATUS_UPDATED : self::STATUS_IMPORTED,
                    'saved_to' => 'ingredient_library',
                    'ingredient_id' => (int) $ingredient->getKey(),
                    'message' => __('Saved as base ingredient in the Ingredient Library (not the Meal Library).'),
                ],
                'outcome' => $wasUpdate ? 'ingredient_updated' : 'ingredient_imported',
            ];
        } catch (\InvalidArgumentException $e) {
            return $this->mealCsvImportAssocErrorResult($lineNumber, $e->getMessage(), $name);
        }
    }

    /**
     * @param  list<string|null>  $rawRow
     * @param  array<string, string>  $assoc
     */
    private function logMealLibraryImportRowFailure(int $lineNumber, array $rawRow, array $assoc, string $message, ?\Throwable $exception = null): void
    {
        $payload = [
            'line' => $lineNumber,
            'message' => $message,
            'raw_row' => $rawRow,
            'assoc_snapshot' => $assoc,
        ];
        if ($exception !== null) {
            $payload['exception_class'] = $exception::class;
            $payload['exception_message'] = $exception->getMessage();
        }

        Log::error('Meal library CSV import row failed.', $payload);
    }

    private function upsertPendingMealRow(
        int $userId,
        string $mealName,
        string $categoryValue,
        string $ingredientQuantities,
        string $instructions,
        string $shortDescription,
    ): void {
        MealCsvImportPendingRow::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'meal_name_key' => self::normalizeMealNameKey($mealName),
            ],
            [
                'meal_name' => $mealName,
                'category' => $categoryValue,
                'ingredient_quantities' => $ingredientQuantities,
                'instructions' => $instructions !== '' ? $instructions : null,
                'description_highlight' => $shortDescription !== '' ? $shortDescription : null,
            ],
        );
    }

    /**
     * Remove a duplicate base-ingredient row created when a meal was mistakenly imported with
     * Category "Base Ingredient" — keeps Meal Library and Ingredient Library in sync for names like
     * consumer meals (e.g. "Apple Pie Balls") that belong in the meal list only.
     */
    private function clearMistakenStandaloneBaseIngredientForMealName(string $mealName): void
    {
        $normalized = strtolower(trim($mealName));
        if ($normalized === '') {
            return;
        }

        $mistaken = Ingredient::query()
            ->whereRaw('lower(trim(name)) = ?', [$normalized])
            ->whereIn('usda_food_category', IngredientLibraryCategory::preparedLabels())
            ->whereNull('source_meal_id')
            ->get();

        foreach ($mistaken as $ingredient) {
            $usedAsComponent = DB::table('ingredient_component')
                ->where('child_ingredient_id', $ingredient->id)
                ->exists();

            if ($usedAsComponent) {
                continue;
            }

            $ingredient->components()->detach();
            $ingredient->delete();
        }
    }

    private function clearPendingMealRowForUserAndMeal(int $userId, string $mealName): void
    {
        MealCsvImportPendingRow::query()
            ->where('user_id', $userId)
            ->where('meal_name_key', self::normalizeMealNameKey($mealName))
            ->delete();
    }

    /**
     * Unique missing ingredient labels across all pending rows (first spelling wins per normalized name).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function uniquePendingIngredientNamesFromRows(array $rows): array
    {
        $seenNorm = [];
        $out = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== self::STATUS_PENDING_INGREDIENTS) {
                continue;
            }

            foreach ($row['pending_ingredients'] ?? [] as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }

                $labels = IngredientQuantityStringParser::cellLooksLikeCommaSeparatedIngredientList($name)
                    ? IngredientQuantityStringParser::ingredientNamesFromCell($name)
                    : [$name];

                foreach ($labels as $label) {
                    $norm = IngredientLibraryNameMatcher::normalizeLookupKey($label);
                    if (isset($seenNorm[$norm])) {
                        continue;
                    }

                    $seenNorm[$norm] = true;
                    $out[] = $label;
                }
            }
        }

        return $out;
    }
}
