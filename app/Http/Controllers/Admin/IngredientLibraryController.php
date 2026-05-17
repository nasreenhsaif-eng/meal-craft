<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DietTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyIngredientsFromLibraryRequest;
use App\Http\Requests\StoreIngredientLibraryRequest;
use App\Models\Ingredient;
use App\Services\BaseIngredientService;
use App\Support\BaseRecipeInstructionsText;
use App\Support\IngredientAllergenCatalog;
use App\Support\IngredientG6pdSafety;
use App\Support\IngredientLibraryCategory;
use App\Support\SickleCellNutrientRdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Admin ingredient library. Inertia renders `Admin/IngredientsLibrary`, which re-exports
 * `resources/js/Pages/IngredientsLibrary/IngredientsLibraryPage.jsx` (same layout as Storybook).
 * Returned props map to that page component.
 */
class IngredientLibraryController extends Controller
{
    public function index(): Response
    {
        $ingredients = Ingredient::query()
            ->where('is_verified', 1)
            ->with(['components' => function ($query): void {
                $query->orderBy('ingredients.name');
            }])
            ->latest()
            ->get()
            ->map(fn (Ingredient $ingredient): array => $this->toLibraryRow($ingredient))
            ->values()
            ->all();

        $componentPickerProfiles = Ingredient::query()
            ->where('is_verified', true)
            ->where(function ($query): void {
                $query->whereNull('usda_food_category')
                    ->orWhereNotIn('usda_food_category', IngredientLibraryCategory::preparedLabels());
            })
            ->orderBy('name')
            ->get(['id', 'name', 'calories', 'protein', 'carbs', 'fat', 'density', 'micronutrients', 'b6', 'b9_folate', 'b12', 'iron', 'magnesium'])
            ->map(fn (Ingredient $ingredient): array => [
                'id' => (int) $ingredient->id,
                'name' => $ingredient->name,
                'calories' => (float) $ingredient->calories,
                'protein' => (float) $ingredient->protein,
                'carbs' => (float) $ingredient->carbs,
                'fat' => (float) $ingredient->fat,
                'density' => (float) ($ingredient->density ?? 1),
                'micronutrients' => is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [],
                'b6' => (float) $ingredient->b6,
                'b9_folate' => (float) $ingredient->b9_folate,
                'b12' => (float) $ingredient->b12,
                'iron' => (float) $ingredient->iron,
                'magnesium' => (float) $ingredient->magnesium,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/IngredientsLibrary', [
            'dietTags' => DietTag::toDropdownOptions(),
            'ingredients' => $ingredients,
            'componentPickerProfiles' => $componentPickerProfiles,
            'ingredientStoreUrl' => route('admin.ingredient-library.store'),
            'csvTemplateUrl' => asset('templates/ingredients-library-template.csv'),
            'csvExportUrl' => route('admin.ingredient-library.export-csv'),
            'csvImportUrl' => route('admin.ingredient-library.import-csv'),
            'ingredientBulkDestroyUrl' => route('admin.ingredient-library.bulk-destroy'),
        ]);
    }

    public function bulkDestroy(BulkDestroyIngredientsFromLibraryRequest $request): RedirectResponse|JsonResponse
    {
        $ids = $request->validated('ids');

        $deletedCount = 0;

        Ingredient::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->each(function (Ingredient $ingredient) use (&$deletedCount): void {
                $ingredient->delete();
                $deletedCount++;
            });

        if ($deletedCount === 0) {
            $message = __('No ingredients were removed. They may already be deleted.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'deleted' => 0], 422);
            }

            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', $message);
        }

        $message = $deletedCount === 1
            ? __('1 ingredient removed from the library.')
            : __(':count ingredients removed from the library.', ['count' => $deletedCount]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'deleted' => $deletedCount,
                'deleted_ids' => $ids,
            ]);
        }

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', $message);
    }

    public function store(StoreIngredientLibraryRequest $request, BaseIngredientService $service): RedirectResponse
    {
        $data = $request->validated();

        if ($this->requestIsBaseRecipe($request)) {
            return $this->storeBaseRecipe($service, $data);
        }

        Ingredient::query()->create([
            'name' => $data['name'],
            'usda_food_category' => filled($data['category'] ?? null) ? (string) $data['category'] : null,
            'fdc_id' => isset($data['fdc_id']) ? (int) $data['fdc_id'] : null,
            'calories' => (float) ($data['calories'] ?? 0),
            'protein' => (float) ($data['protein'] ?? 0),
            'carbs' => (float) ($data['carbs'] ?? 0),
            'fat' => (float) ($data['fat'] ?? 0),
            'b6' => 0,
            'b9_folate' => 0,
            'b12' => 0,
            'iron' => 0,
            'magnesium' => 0,
            'density' => (float) ($data['density'] ?? 1),
            'is_verified' => true,
            'micronutrients' => [],
        ]);

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', __('Ingredient saved to the library.'));
    }

    public function update(
        StoreIngredientLibraryRequest $request,
        Ingredient $ingredient,
        BaseIngredientService $service,
    ): RedirectResponse {
        if (! $ingredient->isPreparedBaseIngredient() && $this->requestIsBaseRecipe($request)) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', __('This ingredient cannot be converted to a base recipe here. Create a new base recipe instead.'));
        }

        if ($this->requestIsBaseRecipe($request) || $ingredient->isPreparedBaseIngredient()) {
            if (! $ingredient->isPreparedBaseIngredient()) {
                return redirect()
                    ->route('admin.ingredient-library')
                    ->with('error', __('Only base recipe ingredients can be updated with composition rows.'));
            }

            return $this->storeBaseRecipe($service, $request->validated(), $ingredient);
        }

        $data = $request->validated();
        $ingredient->update([
            'name' => $data['name'],
            'usda_food_category' => filled($data['category'] ?? null) ? (string) $data['category'] : $ingredient->usda_food_category,
            'fdc_id' => isset($data['fdc_id']) ? (int) $data['fdc_id'] : $ingredient->fdc_id,
            'calories' => (float) ($data['calories'] ?? $ingredient->calories),
            'protein' => (float) ($data['protein'] ?? $ingredient->protein),
            'carbs' => (float) ($data['carbs'] ?? $ingredient->carbs),
            'fat' => (float) ($data['fat'] ?? $ingredient->fat),
            'density' => (float) ($data['density'] ?? $ingredient->density ?? 1),
            'is_verified' => true,
        ]);

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', __('Ingredient updated.'));
    }

    private function requestIsBaseRecipe(StoreIngredientLibraryRequest $request): bool
    {
        return $request->boolean('is_base_recipe')
            || $request->routeIs('admin.ingredient-library.base-ingredient.store', 'admin.ingredient-library.base-ingredient.update');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeBaseRecipe(BaseIngredientService $service, array $data, ?Ingredient $existing = null): RedirectResponse
    {
        $componentRows = [];
        foreach ($data['components'] as $row) {
            $componentRows[] = [
                'ingredient_id' => (int) $row['ingredient_id'],
                'amount_grams' => (float) $row['amount_grams'],
            ];
        }

        try {
            $libraryText = $this->optionalLibraryTextPatchFromData($data);

            $service->upsert(
                $existing,
                $data['name'],
                $componentRows,
                isset($data['finished_weight_grams']) ? (float) $data['finished_weight_grams'] : null,
                $libraryText,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', $existing === null
                ? __('Base recipe saved to the ingredient library.')
                : __('Base recipe updated.'));
    }

    /**
     * Flatten DB + micronutrients JSON into the shape expected by the Ingredients Library table.
     *
     * @return array<string, mixed>
     */
    private function toLibraryRow(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];
        $highlights = $ingredient->highlights;

        $badgeLabels = [];
        if ($highlights['folate'] ?? false) {
            $badgeLabels[] = 'Folate';
        }
        if ($highlights['b12'] ?? false) {
            $badgeLabels[] = 'B12';
        }
        if ($highlights['magnesium'] ?? false) {
            $badgeLabels[] = 'Magnesium';
        }
        if ($highlights['iron'] ?? false) {
            $badgeLabels[] = 'Iron';
        }
        if ($highlights['zinc'] ?? false) {
            $badgeLabels[] = 'Zinc';
        }

        return [
            'id' => (string) $ingredient->id,
            'name' => $ingredient->name,
            'isBaseRecipe' => $ingredient->isPreparedBaseIngredient(),
            'category' => $ingredient->usda_food_category ?? '',
            'fdc' => $ingredient->fdc_id !== null ? (string) $ingredient->fdc_id : '—',
            'highlights' => $badgeLabels,
            'calories' => (float) $ingredient->calories,
            'protein' => (float) $ingredient->protein,
            'carbs' => (float) $ingredient->carbs,
            'fat' => (float) $ingredient->fat,
            'vitA' => (float) ($micros['vitamin_a'] ?? 0),
            'vitB6' => (float) $ingredient->b6,
            'vitB9' => (float) $ingredient->b9_folate,
            'vitB12' => (float) $ingredient->b12,
            'vitC' => (float) ($micros['vitamin_c'] ?? 0),
            'vitD' => (float) ($micros['vitamin_d'] ?? 0),
            'vitE' => (float) ($micros['vitamin_e'] ?? 0),
            'vitK' => (float) ($micros['vitamin_k'] ?? 0),
            'calcium' => (float) ($micros['calcium'] ?? 0),
            'iron' => (float) $ingredient->iron,
            'magnesium' => (float) $ingredient->magnesium,
            'potassium' => (float) ($micros['potassium'] ?? 0),
            'zinc' => (float) ($micros['zinc'] ?? 0),
            'sodium' => (float) ($micros['sodium'] ?? 0),
            'sugar' => (float) ($micros['sugar'] ?? 0),
            'fiber' => (float) ($micros['fiber'] ?? 0),
            /** Raw per-100 g JSON (same keys as import CSV) so the UI can fall back if flat fields are missing. */
            'micronutrients' => $micros,
            'detailView' => $ingredient->isPreparedBaseIngredient()
                ? $this->buildBaseIngredientDetailViewPayload($ingredient)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>|null
     */
    private function optionalLibraryTextPatchFromData(array $data): ?array
    {
        $out = [];

        if (array_key_exists('description', $data)) {
            $out['description'] = is_string($data['description']) ? $data['description'] : ($data['description'] === null ? null : (string) $data['description']);
        }
        if (array_key_exists('instructions', $data)) {
            $raw = is_string($data['instructions']) ? $data['instructions'] : ($data['instructions'] === null ? null : (string) $data['instructions']);
            $out['instructions'] = BaseRecipeInstructionsText::normalizeForStorage($raw);
        }

        return $out === [] ? null : $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBaseIngredientDetailViewPayload(Ingredient $ingredient): array
    {
        $nutrition = $this->nutritionPer100GramsCalculatorShape($ingredient);

        $childIds = [];
        $ingredientLines = [];
        foreach ($ingredient->components as $child) {
            $childIds[] = (int) $child->id;
            $grams = (float) ($child->pivot->amount_grams ?? 0);
            if ($grams > 0.0) {
                $ingredientLines[] = sprintf('%sg %s', $this->formatTrimmedDecimal($grams, 2), $child->name);
            } else {
                $ingredientLines[] = $child->name;
            }
        }
        if ($ingredientLines === []) {
            $ingredientLines = [__('No component ingredients on file.')];
        }

        $hasG6pdTrigger = IngredientG6pdSafety::ingredientHasEffectiveG6pdTrigger($ingredient)
            || ($childIds !== [] && IngredientG6pdSafety::mealContainsG6pdTrigger($childIds));

        $safetyAlertTags = $childIds !== []
            ? $this->safetyAlertTagsForIngredientIds($childIds)
            : [];
        $safetyAlerts = $this->safetyAlertsForDetailView($safetyAlertTags);
        if ($hasG6pdTrigger) {
            $safetyAlerts = array_values(array_filter(
                $safetyAlerts,
                static fn (array $a): bool => ! str_contains(strtoupper($a['label']), 'G6PD'),
            ));
        }

        $sickleCellHighlights = SickleCellNutrientRdi::highlightBadgeLabels($nutrition);

        $instructionsRaw = trim((string) ($ingredient->instructions ?? ''));

        $dietaryTags = [];
        if (is_array($ingredient->diet_tags)) {
            foreach ($ingredient->diet_tags as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $dietaryTags[] = trim($t);
                }
            }
        }
        $dietaryTags = array_values(array_unique($dietaryTags));

        return [
            'shortDescription' => trim((string) ($ingredient->description ?? '')),
            'cyclePhases' => [],
            'dietaryTags' => $dietaryTags,
            'hasG6pdTrigger' => $hasG6pdTrigger,
            'safetyAlerts' => $safetyAlerts,
            'sickleCellHighlights' => $sickleCellHighlights,
            'nutritionalData' => $this->nutritionalDataPer100gSidebar($nutrition),
            'ingredients' => $ingredientLines,
            'instructions' => $this->instructionsLinesFromText($instructionsRaw),
            'imageUrl' => null,
            'imageAlt' => $ingredient->name,
            'nutritionSubheading' => __('Per 100 g totals'),
            'sickleRdiFootnote' => __('High Source: ≥20%% of daily RDI per 100 g'),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function nutritionPer100GramsCalculatorShape(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

        return [
            'calories' => (float) $ingredient->calories,
            'protein' => (float) $ingredient->protein,
            'carbs' => (float) $ingredient->carbs,
            'fat' => (float) $ingredient->fat,
            'fiber' => (float) ($micros['fiber'] ?? 0),
            'sugar' => (float) ($micros['sugar'] ?? 0),
            'vitamin_a' => (float) ($micros['vitamin_a'] ?? 0),
            'vitamin_c' => (float) ($micros['vitamin_c'] ?? 0),
            'vitamin_d' => (float) ($micros['vitamin_d'] ?? 0),
            'vitamin_e' => (float) ($micros['vitamin_e'] ?? 0),
            'vitamin_k' => (float) ($micros['vitamin_k'] ?? 0),
            'b9_folate' => (float) $ingredient->b9_folate,
            'b12' => (float) $ingredient->b12,
            'b6' => (float) $ingredient->b6,
            'calcium' => (float) ($micros['calcium'] ?? 0),
            'iron' => (float) $ingredient->iron,
            'magnesium' => (float) $ingredient->magnesium,
            'potassium' => (float) ($micros['potassium'] ?? 0),
            'zinc' => (float) ($micros['zinc'] ?? 0),
            'sodium' => (float) ($micros['sodium'] ?? 0),
        ];
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, mixed>
     */
    private function nutritionalDataPer100gSidebar(array $nutrition): array
    {
        $calories = (float) ($nutrition['calories'] ?? 0);
        $protein = (float) ($nutrition['protein'] ?? 0);
        $carbs = (float) ($nutrition['carbs'] ?? 0);
        $fat = (float) ($nutrition['fat'] ?? 0);
        $fiber = (float) ($nutrition['fiber'] ?? 0);
        $sugar = (float) ($nutrition['sugar'] ?? 0);
        $netCarbs = max(0.0, $carbs - $fiber);

        $macroRows = [
            ['label' => __('Total calories'), 'value' => (string) (int) round($calories)],
            ['label' => __('Protein (g)'), 'value' => $this->formatTrimmedDecimal($protein, 1), 'valueClass' => 'text-[#916A00]'],
            ['label' => __('Fats (g)'), 'value' => $this->formatTrimmedDecimal($fat, 1), 'valueClass' => 'text-[#2F4C9B]'],
            ['label' => __('Net carbs (g)'), 'value' => $this->formatTrimmedDecimal($netCarbs, 1), 'valueClass' => 'text-[#8F55A8]'],
            ['label' => __('Fiber (g)'), 'value' => $this->formatTrimmedDecimal($fiber, 1)],
            ['label' => __('Sugar (g)'), 'value' => $this->formatTrimmedDecimal($sugar, 1)],
        ];

        $vitaminRows = [
            ['label' => __('Vitamin A (mcg RAE)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_a'] ?? 0), 1)],
            ['label' => __('Vitamin C (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_c'] ?? 0), 1)],
            ['label' => __('Vitamin D (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_d'] ?? 0), 1)],
            ['label' => __('Vitamin E (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_e'] ?? 0), 1)],
            ['label' => __('Vitamin K (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_k'] ?? 0), 1)],
            ['label' => __('Folate B9 (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['b9_folate'] ?? 0), 1)],
            ['label' => __('Vitamin B12 (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['b12'] ?? 0), 1)],
            ['label' => __('Vitamin B6 (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['b6'] ?? 0), 1)],
        ];

        $mineralRows = [
            ['label' => __('Calcium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['calcium'] ?? 0), 1)],
            ['label' => __('Iron (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['iron'] ?? 0), 1)],
            ['label' => __('Magnesium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['magnesium'] ?? 0), 1)],
            ['label' => __('Potassium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['potassium'] ?? 0), 1)],
            ['label' => __('Zinc (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['zinc'] ?? 0), 1)],
            ['label' => __('Sodium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['sodium'] ?? 0), 1)],
        ];

        return [
            'valueColumnLabel' => __('Per 100 g'),
            'sections' => [
                ['title' => __('Macros'), 'rows' => $macroRows],
                ['title' => __('Vitamins'), 'rows' => $vitaminRows],
                ['title' => __('Minerals'), 'rows' => $mineralRows],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function instructionsLinesFromText(string $instructionsRaw): array
    {
        if ($instructionsRaw === '') {
            return [__('No written instructions on file.')];
        }

        $parts = preg_split('/\r\n|\r|\n/', $instructionsRaw) ?: [];
        $steps = [];
        foreach ($parts as $part) {
            $line = trim((string) $part);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^Step\s+\d{1,2}\s*:\s*/iu', '', $line) ?? $line;
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line) ?? $line;
            $steps[] = trim($line);
        }

        if ($steps === []) {
            return [$instructionsRaw];
        }

        return array_values($steps);
    }

    /**
     * @param  list<string>  $safetyAlertTags
     * @return list<array{label: string, variant: string}>
     */
    private function safetyAlertsForDetailView(array $safetyAlertTags): array
    {
        $out = [];
        foreach ($safetyAlertTags as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $variant = str_contains(strtoupper($label), 'G6PD') ? 'g6pd' : 'allergy';
            $out[] = ['label' => $label, 'variant' => $variant];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ingredientIds
     * @return list<string>
     */
    private function safetyAlertTagsForIngredientIds(array $ingredientIds): array
    {
        if ($ingredientIds === []) {
            return [];
        }

        $labels = [];
        $rows = Ingredient::query()
            ->whereIn('id', $ingredientIds)
            ->get(['id', 'common_allergens', 'is_g6pd_trigger']);

        foreach ($rows as $row) {
            foreach (IngredientAllergenCatalog::labelsFromSlugs(
                is_array($row->common_allergens) ? $row->common_allergens : [],
            ) as $label) {
                $labels[$label] = true;
            }
        }

        return IngredientG6pdSafety::mergeTriggerIntoSafetyLabels(
            array_keys($labels),
            IngredientG6pdSafety::mealContainsG6pdTrigger($ingredientIds),
        );
    }

    private function formatTrimmedDecimal(float $value, int $decimals): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        $formatted = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
