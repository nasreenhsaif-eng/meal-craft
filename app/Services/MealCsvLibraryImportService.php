<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports meal rows from CSV using the ingredient library (per-100 g nutrition).
 *
 * CSV columns (headers matched case-insensitively):
 * - Meal_Name
 * - Category (Breakfast, Meal, Side Salad, Soup, Dessert — required)
 * - Ingredient_Quantities (cells like "Salmon:120 | Quinoa:100")
 * - Instructions (stored on meal.description)
 * - Description_Highlight (stored on meal.highlight)
 */
final class MealCsvLibraryImportService
{
    public const LIBRARY_CSV_HEADERS = MealLibrarySynchronizedCsvExport::HEADERS;

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
            RecipeCategory::SideSalad,
            RecipeCategory::Soup,
            RecipeCategory::Dessert,
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
    public function processUploadedFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => __('The uploaded file could not be read.'),
                ]],
            ];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => __('The uploaded file could not be opened.'),
                ]],
            ];
        }

        $headerLine = fgetcsv($handle);
        if ($headerLine === false) {
            fclose($handle);

            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'rows' => [[
                    'line' => 0,
                    'status' => self::STATUS_ERROR,
                    'message' => __('The CSV is empty.'),
                ]],
            ];
        }

        $headerMap = $this->buildHeaderMap($headerLine);
        if (! isset($headerMap['meal_name'], $headerMap['ingredient_quantities'], $headerMap['category'])) {
            fclose($handle);

            return [
                'summary' => $this->emptySummaryWithErrors(1),
                'unique_pending_ingredients' => [],
                'rows' => [[
                    'line' => 1,
                    'status' => self::STATUS_ERROR,
                    'message' => __('CSV must include Meal_Name, Category, and Ingredient_Quantities columns.'),
                ]],
            ];
        }

        $lineNumber = 1;
        $rowsOut = [];
        $imported = 0;
        $updated = 0;
        $pending = 0;
        $errors = 0;

        /** @var array<string, Meal> $mealsByNormalizedName */
        $mealsByNormalizedName = $this->indexMealsByNormalizedName();

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($this->rowIsBlank($data)) {
                continue;
            }

            $assoc = $this->associateRow($headerMap, $data);
            $mealName = trim((string) ($assoc['meal_name'] ?? ''));
            if ($mealName === '') {
                $rowsOut[] = [
                    'line' => $lineNumber,
                    'status' => self::STATUS_ERROR,
                    'message' => __('Meal_Name is required.'),
                ];
                $errors++;

                continue;
            }

            $qtyCell = trim((string) ($assoc['ingredient_quantities'] ?? ''));
            if ($qtyCell === '') {
                $rowsOut[] = [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'status' => self::STATUS_ERROR,
                    'message' => __('Ingredient_Quantities is required.'),
                ];
                $errors++;

                continue;
            }

            $mealCategory = $this->resolveMealLibraryCategory((string) ($assoc['category'] ?? ''));
            if ($mealCategory === null) {
                $rowsOut[] = [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'status' => self::STATUS_ERROR,
                    'message' => __('Invalid or Missing Category.'),
                ];
                $errors++;

                continue;
            }

            $segments = $this->parseIngredientQuantitySegments($qtyCell);
            if ($segments === []) {
                $rowsOut[] = [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'status' => self::STATUS_ERROR,
                    'message' => __('Could not parse ingredient quantities. Use Name:Grams separated by |.'),
                ];
                $errors++;

                continue;
            }

            $instructions = isset($assoc['instructions']) ? trim((string) $assoc['instructions']) : '';
            $highlight = isset($assoc['highlight']) ? trim((string) $assoc['highlight']) : '';

            $calc = $this->calculateMealNutritionFromSegments($segments, $mealCategory);

            if ($calc['pending_ingredients'] !== []) {
                $rowsOut[] = [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'category' => $mealCategory->value,
                    'status' => self::STATUS_PENDING_INGREDIENTS,
                    'pending_ingredients' => $calc['pending_ingredients'],
                    'message' => __('Pending Ingredient Input — add these ingredients to the library before importing this meal.'),
                ];
                $pending++;

                continue;
            }

            try {
                $mealCategoryValue = $mealCategory->value;
                $normKey = self::normalizeMealNameKey($mealName);
                $existingMeal = $mealsByNormalizedName[$normKey] ?? null;

                $result = DB::transaction(function () use ($existingMeal, $mealName, $mealCategoryValue, $instructions, $highlight, $calc): array {
                    $nutritionPayload = Meal::nutritionSummaryToPersistedAttributes($calc['nutrition']);

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

                    $basePayload = array_merge([
                        'name' => $mealName,
                        'category' => $mealCategoryValue,
                        'meal_type' => MealType::fromRecipeCategory($mealCategory)->value,
                        'description' => $instructions !== '' ? $instructions : null,
                        'highlight' => $highlight !== '' ? $highlight : null,
                        'health_score' => $calc['health_score'],
                    ], $nutritionPayload);

                    if ($existingMeal !== null) {
                        $existingMeal->refresh();
                        $existingMeal->update($basePayload);
                        $existingMeal->ingredients()->sync($sync);

                        return ['id' => (int) $existingMeal->id, 'was_update' => true];
                    }

                    $meal = Meal::query()->create(array_merge($basePayload, [
                        'image_path' => null,
                    ]));
                    $meal->ingredients()->sync($sync);

                    return ['id' => (int) $meal->id, 'was_update' => false];
                });

                $mealId = $result['id'];
                $wasUpdate = $result['was_update'];

                $freshMeal = Meal::query()->find($mealId);
                if ($freshMeal !== null) {
                    $mealsByNormalizedName[self::normalizeMealNameKey($freshMeal->name)] = $freshMeal;
                }

                $rowsOut[] = [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'category' => $mealCategory->value,
                    'status' => $wasUpdate ? self::STATUS_UPDATED : self::STATUS_IMPORTED,
                    'meal_id' => $mealId,
                    'health_score' => $calc['health_score'],
                    'warnings' => $calc['calorie_warnings'],
                ];

                if ($wasUpdate) {
                    $updated++;
                } else {
                    $imported++;
                }
            } catch (\Throwable $e) {
                $rowsOut[] = [
                    'line' => $lineNumber,
                    'meal_name' => $mealName,
                    'status' => self::STATUS_ERROR,
                    'message' => __('Could not save meal: :msg', ['msg' => $e->getMessage()]),
                ];
                $errors++;
            }
        }

        fclose($handle);

        return [
            'summary' => [
                'imported' => $imported,
                'updated' => $updated,
                'duplicates_created' => 0,
                'pending_ingredient_input' => $pending,
                'errors' => $errors,
            ],
            'unique_pending_ingredients' => $this->uniquePendingIngredientNamesFromRows($rowsOut),
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
        foreach (Meal::query()->orderByDesc('updated_at')->orderByDesc('id')->cursor() as $meal) {
            $key = self::normalizeMealNameKey($meal->name);
            if (! isset($map[$key])) {
                $map[$key] = $meal;
            }
        }

        return $map;
    }

    /**
     * @return array{imported: int, updated: int, duplicates_created: int, pending_ingredient_input: int, errors: int}
     */
    private function emptySummaryWithErrors(int $errors): array
    {
        return [
            'imported' => 0,
            'updated' => 0,
            'duplicates_created' => 0,
            'pending_ingredient_input' => 0,
            'errors' => $errors,
        ];
    }

    /**
     * Pure calculation for one logical CSV row (used by tests and mirrors client-side TS).
     *
     * @param  list<array{name: string, grams: float}>  $segments
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
        $normalizedNames = [];
        foreach ($segments as $seg) {
            $normalizedNames[] = $this->normalizeIngredientName($seg['name']);
        }

        $unique = array_values(array_unique($normalizedNames));

        $ingredients = $this->loadIngredientsByNormalizedNames($unique);

        $pending = [];
        /** @var array<string, true> $pendingNormSeen */
        $pendingNormSeen = [];
        /** @var array<int, float> $gramsByIngredientId */
        $gramsByIngredientId = [];

        foreach ($segments as $seg) {
            $key = $this->normalizeIngredientName($seg['name']);
            $ing = $ingredients->get($key);
            if ($ing === null) {
                if (! isset($pendingNormSeen[$key])) {
                    $pendingNormSeen[$key] = true;
                    $pending[] = $seg['name'];
                }

                continue;
            }

            $id = (int) $ing->id;
            $gramsByIngredientId[$id] = ($gramsByIngredientId[$id] ?? 0.0) + max(0.0, (float) $seg['grams']);
        }

        $resolved = [];
        foreach ($gramsByIngredientId as $ingredientId => $grams) {
            $ing = $ingredients->first(fn (Ingredient $i): bool => (int) $i->id === $ingredientId);
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

        $norm = $this->normalizeCategoryLabel($raw);

        foreach (self::mealLibraryCsvAllowedCategories() as $case) {
            if ($norm === $this->normalizeCategoryLabel($case->value)) {
                return $case;
            }
        }

        return null;
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
            $key = $this->canonicalHeaderKey($this->stripUtf8Bom((string) $label));
            if ($key !== null) {
                $map[$key] = (int) $i;
            }
        }

        return $map;
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
        $t = strtolower(trim($label));
        $t = str_replace(['/', '\\'], ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        if ($t === 'category') {
            return 'category';
        }

        if (str_contains($t, 'meal') && str_contains($t, 'name')) {
            return 'meal_name';
        }
        if (str_contains($t, 'ingredient') && (str_contains($t, 'quantit') || str_contains($t, 'qty'))) {
            return 'ingredient_quantities';
        }
        if (str_contains($t, 'instruction')) {
            return 'instructions';
        }
        if (str_contains($t, 'description') || str_contains($t, 'highlight')) {
            return 'highlight';
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
     * @return list<array{name: string, grams: float}>
     */
    private function parseIngredientQuantitySegments(string $cell): array
    {
        $parts = preg_split('/\|/', $cell) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (! preg_match('/^(.+?):(\d+(?:\.\d+)?)\s*$/u', $part, $m)) {
                continue;
            }
            $out[] = [
                'name' => trim($m[1]),
                'grams' => (float) $m[2],
            ];
        }

        return $out;
    }

    private function normalizeIngredientName(string $name): string
    {
        return self::normalizeMealNameKey($name);
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

                $norm = $this->normalizeIngredientName($name);
                if (isset($seenNorm[$norm])) {
                    continue;
                }

                $seenNorm[$norm] = true;
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $normalizedNames
     * @return Collection<string, Ingredient>
     */
    private function loadIngredientsByNormalizedNames(array $normalizedNames): Collection
    {
        if ($normalizedNames === []) {
            return collect();
        }

        $unique = array_values(array_unique($normalizedNames));

        $query = Ingredient::query();
        $query->where(function ($q) use ($unique): void {
            foreach ($unique as $norm) {
                $q->orWhereRaw('lower(trim(name)) = ?', [$norm]);
            }
        });

        /** @var Collection<int, Ingredient> $matched */
        $matched = $query->get([
            'id', 'name', 'calories', 'protein', 'carbs', 'fat', 'b6', 'b9_folate', 'b12', 'iron', 'magnesium', 'density', 'micronutrients',
        ]);

        return $matched->keyBy(fn (Ingredient $ing): string => $this->normalizeIngredientName((string) $ing->name));
    }
}
