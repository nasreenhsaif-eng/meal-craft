<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyIngredientsFromLibraryRequest;
use App\Http\Requests\StoreIngredientLibraryRequest;
use App\Models\Ingredient;
use App\Services\BaseIngredientService;
use App\Services\MenuDevelopmentCsvSync;
use App\Support\BaseIngredientDetailViewPresenter;
use App\Support\BaseRecipeInstructionsText;
use App\Support\IngredientLibraryCategory;
use App\Support\MealLibraryEditGuard;
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
    public function __construct(
        private MenuDevelopmentCsvSync $menuDevelopmentCsvSync,
        private BaseIngredientDetailViewPresenter $baseIngredientDetailViewPresenter,
    ) {}

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
            'ingredients' => $ingredients,
            'componentPickerProfiles' => $componentPickerProfiles,
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

        $this->menuDevelopmentCsvSync->syncAllFromDatabase();

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

        $this->menuDevelopmentCsvSync->syncAllFromDatabase();

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
            'library_edited_at' => now(),
        ]);

        $this->menuDevelopmentCsvSync->syncAllFromDatabase();

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

            $ingredient = $service->upsert(
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

        MealLibraryEditGuard::markIngredientEditedFromLibrary($ingredient);
        $this->menuDevelopmentCsvSync->syncAllFromDatabase();

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
            'vitK2' => (float) ($micros['vitamin_k2'] ?? 0),
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
                ? $this->baseIngredientDetailViewPresenter->build($ingredient)
                : null,
            'baseRecipeEdit' => $ingredient->isPreparedBaseIngredient()
                ? $this->buildBaseRecipeEditPayload($ingredient)
                : null,
        ];
    }

    /**
     * Editable composition rows for the ingredient-library base recipe editor.
     *
     * @return array<string, mixed>
     */
    private function buildBaseRecipeEditPayload(Ingredient $ingredient): array
    {
        $rows = [];
        $totalGrams = 0.0;

        foreach ($ingredient->components as $child) {
            $grams = (float) ($child->pivot->amount_grams ?? 0);
            $totalGrams += $grams;
            $rows[] = [
                'ingredientId' => (int) $child->id,
                'selectedName' => $child->name,
                'nameQuery' => $child->name,
                'amount' => $this->formatTrimmedDecimal($grams, 2),
                'unit' => 'g',
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'ingredientId' => null,
                'selectedName' => '',
                'nameQuery' => '',
                'amount' => '100',
                'unit' => 'g',
            ];
        }

        $storedFinished = $ingredient->finished_weight_grams;
        $finishedForEditor = ($storedFinished !== null && (float) $storedFinished > 0)
            ? (float) $storedFinished
            : $totalGrams;

        return [
            'finishedWeightGrams' => $finishedForEditor > 0 ? $this->formatTrimmedDecimal($finishedForEditor, 2) : '',
            'description' => trim((string) ($ingredient->description ?? '')),
            'instructions' => trim((string) ($ingredient->instructions ?? '')),
            'compositionRows' => $rows,
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

    private function formatTrimmedDecimal(float $value, int $decimals): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        $formatted = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
