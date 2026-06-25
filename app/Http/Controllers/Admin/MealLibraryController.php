<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CyclePhase;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyMealsFromLibraryRequest;
use App\Http\Requests\ReorderMealsFromLibraryRequest;
use App\Http\Requests\StoreMealFromLibraryRequest;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealCsvImportPendingRow;
use App\Models\User;
use App\Services\BaseIngredientService;
use App\Services\MealCraftMasterCsvExport;
use App\Services\MenuDevelopmentCsvExport;
use App\Services\MenuDevelopmentCsvSync;
use App\Services\RecipeNutritionCalculator;
use App\Support\EggIngredientPresentation;
use App\Support\IngredientAllergenCatalog;
use App\Support\IngredientG6pdSafety;
use App\Support\IngredientLibraryNameMatcher;
use App\Support\MealImagePath;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryBulkNutrition;
use App\Support\MealLibraryTaxonomy;
use App\Support\SaladMealPresentation;
use App\Support\SickleCellNutrientRdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MealLibraryController extends Controller
{
    public function __construct(
        private MenuDevelopmentCsvExport $menuDevelopmentCsvExport,
        private MenuDevelopmentCsvSync $menuDevelopmentCsvSync,
    ) {}

    public function downloadMealCraftCsvTemplate(): SymfonyResponse
    {
        $csv = MealCraftMasterCsvExport::mealCraftCsvTemplateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="meal-craft-csv-template.csv"',
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $this->mealLibrarySchemaReady()) {
            return Inertia::render('Admin/MealLibrary', $this->mealLibraryIndexPayload([], [], $user));
        }

        $meals = Meal::queryForMealLibrary()
            ->with(['ingredients' => function ($query): void {
                $query->orderBy('ingredients.name');
            }])
            ->get()
            ->map(fn (Meal $meal): array => $this->toMealRow($meal))
            ->values()
            ->all();

        $ingredientProfiles = Ingredient::query()
            ->where('is_verified', true)
            ->with(['components'])
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient): array => $this->toIngredientProfile($ingredient))
            ->values()
            ->all();

        return Inertia::render('Admin/MealLibrary', $this->mealLibraryIndexPayload($meals, $ingredientProfiles, $user));
    }

    public function bulkDestroy(BulkDestroyMealsFromLibraryRequest $request): RedirectResponse|JsonResponse
    {
        if (! $this->mealLibrarySchemaReady()) {
            $message = __('Run `php artisan migrate` to update the database, then try again.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return redirect()
                ->route('admin.meal-library')
                ->with('error', $message);
        }

        $ids = $request->validated('ids');

        $deletedCount = 0;

        Meal::queryForMealLibrary()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->each(function (Meal $meal) use (&$deletedCount): void {
                $nameKey = strtolower(trim($meal->name));

                Meal::withTrashed()
                    ->visibleInMealLibrary()
                    ->whereRaw('lower(trim(name)) = ?', [$nameKey])
                    ->orderBy('id')
                    ->each(function (Meal $row) use (&$deletedCount): void {
                        $row->forceDelete();
                        $deletedCount++;
                    });
            });

        if ($deletedCount === 0) {
            $message = __('No meals were removed. Selected items may already be deleted or are not part of the Meal Library.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'deleted' => 0], 422);
            }

            return redirect()
                ->route('admin.meal-library')
                ->with('error', $message);
        }

        $message = $deletedCount === 1
            ? __('1 meal removed from the library.')
            : __(':count meals removed from the library.', ['count' => $deletedCount]);

        $this->syncMealMasterCsvFromDatabase();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'deleted' => $deletedCount,
                'deleted_ids' => $ids,
            ]);
        }

        return redirect()
            ->route('admin.meal-library')
            ->with('success', $message);
    }

    public function reorder(ReorderMealsFromLibraryRequest $request): RedirectResponse|JsonResponse
    {
        if (! $this->mealLibrarySchemaReady() || ! Schema::hasColumn('meals', 'library_sort_order')) {
            $message = __('Run `php artisan migrate` to update the database, then try again.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return redirect()
                ->route('admin.meal-library')
                ->with('error', $message);
        }

        $ids = $request->validated('ids');

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Meal::queryForMealLibrary()
                    ->where('id', $id)
                    ->update(['library_sort_order' => (int) $position]);
            }
        });

        $this->syncMealMasterCsvFromDatabase();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Meal order saved.')]);
        }

        return redirect()
            ->route('admin.meal-library')
            ->with('success', __('Meal order saved.'));
    }

    public function store(StoreMealFromLibraryRequest $request): RedirectResponse
    {
        if (! $this->mealLibrarySchemaReady()) {
            return redirect()
                ->route('admin.meal-library')
                ->with('error', __('Run `php artisan migrate` to update the database, then try saving again.'));
        }

        $data = $request->validated();

        if (BaseIngredientService::isBaseIngredientCategoryInput((string) $data['category'])) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', __('Prepared base ingredients belong in the Ingredient Library. Use “Create Base Ingredient” there.'));
        }

        $category = RecipeCategory::from($data['category']);
        $mealType = MealType::fromRecipeCategory($category);

        $dietTags = array_values(array_unique(array_filter($data['diet_tags'] ?? [])));
        $planPhaseBundle = $this->mealPlanTagsAndCyclePhasesForPersistence($data);

        DB::transaction(function () use ($request, $data, $category, $mealType, $planPhaseBundle, $dietTags): void {
            $createData = [
                'name' => $data['name'],
                'category' => $category,
                'meal_type' => $mealType,
                'instructions' => $this->instructionsFromValidated($data),
                'short_description' => $this->shortDescriptionFromValidated($data),
                'description' => $this->instructionsFromValidated($data),
                'highlight' => $this->shortDescriptionFromValidated($data),
                'meal_plan_tag' => $planPhaseBundle['meal_plan_tag'],
                'meal_plan_tags' => $planPhaseBundle['meal_plan_tags'],
                'total_calories' => (float) $data['total_calories'],
                'total_protein' => (float) ($data['total_protein'] ?? 0),
                'total_carbs' => (float) ($data['total_carbs'] ?? 0),
                'total_fat' => (float) ($data['total_fat'] ?? 0),
                'diet_tags' => $dietTags,
                'diet_type' => null,
                'cycle_phase' => $planPhaseBundle['cycle_phase'],
                'cycle_phases' => $planPhaseBundle['cycle_phases'],
                'finished_weight_grams' => isset($data['finished_weight_grams']) ? (float) $data['finished_weight_grams'] : null,
                'is_bulk' => (bool) ($data['is_bulk'] ?? false),
                'servings_count' => ($data['is_bulk'] ?? false) && isset($data['servings_count'])
                    ? (float) $data['servings_count']
                    : null,
            ];
            $nutritionTargets = $this->nutritionTargetsFromValidated($data);
            if ($nutritionTargets !== null) {
                $createData = array_merge($createData, $nutritionTargets);
            }
            if (Schema::hasColumn('meals', 'library_sort_order')) {
                $createData['library_sort_order'] = Meal::nextLibrarySortOrder();
            }
            $meal = Meal::query()->create($createData);

            $this->syncLibraryMealIngredientsPhotoAndAggregates($request, $meal, $data);
        });

        $this->syncMealMasterCsvFromDatabase();

        $successMessage = ($data['submission_context'] ?? null) === 'duplicate'
            ? __('New meal version saved successfully.')
            : __('Meal created successfully.');

        return redirect()
            ->route('admin.meal-library')
            ->with('success', $successMessage);
    }

    public function update(StoreMealFromLibraryRequest $request, Meal $meal): RedirectResponse
    {
        if (! $this->mealLibrarySchemaReady()) {
            return redirect()
                ->route('admin.meal-library')
                ->with('error', __('Run `php artisan migrate` to update the database, then try saving again.'));
        }

        $data = $request->validated();

        if (BaseIngredientService::isBaseIngredientCategoryInput((string) $data['category'])) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', __('Prepared base ingredients belong in the Ingredient Library. Use “Create Base Ingredient” there.'));
        }

        $category = RecipeCategory::from($data['category']);
        $mealType = MealType::fromRecipeCategory($category);

        $dietTags = array_values(array_unique(array_filter($data['diet_tags'] ?? [])));
        $planPhaseBundle = $this->mealPlanTagsAndCyclePhasesForPersistence($data);

        $ingredientRows = is_array($data['ingredients'] ?? null) ? $data['ingredients'] : [];
        if ($ingredientRows !== []) {
            $selfDerivedIngredientId = Ingredient::query()
                ->where('source_meal_id', $meal->id)
                ->value('id');

            if ($selfDerivedIngredientId !== null) {
                foreach ($ingredientRows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $rowIngredientId = $row['ingredient_id'] ?? null;
                    if ($rowIngredientId !== null && (int) $rowIngredientId === (int) $selfDerivedIngredientId) {
                        return redirect()
                            ->route('admin.meal-library')
                            ->with('error', __('This meal cannot include its own linked library ingredient row. Remove that ingredient line and try again.'));
                    }
                }
            }

            $byIngredientGrams = $this->aggregateIngredientGramsFromLibraryRows($ingredientRows);
            if ($byIngredientGrams === []) {
                return redirect()
                    ->route('admin.meal-library')
                    ->with('error', __('Could not match any ingredients to your library. Check names (e.g. “Vegetable Broth (Base)” must exist as a base ingredient) and try again.'));
            }
        }

        DB::transaction(function () use ($request, $data, $meal, $category, $mealType, $planPhaseBundle, $dietTags): void {
            $updateData = [
                'name' => $data['name'],
                'category' => $category,
                'meal_type' => $mealType,
                'instructions' => $this->instructionsFromValidated($data),
                'short_description' => $this->shortDescriptionFromValidated($data),
                'description' => $this->instructionsFromValidated($data),
                'highlight' => $this->shortDescriptionFromValidated($data),
                'meal_plan_tag' => $planPhaseBundle['meal_plan_tag'],
                'meal_plan_tags' => $planPhaseBundle['meal_plan_tags'],
                'total_calories' => (float) $data['total_calories'],
                'total_protein' => (float) ($data['total_protein'] ?? 0),
                'total_carbs' => (float) ($data['total_carbs'] ?? 0),
                'total_fat' => (float) ($data['total_fat'] ?? 0),
                'diet_tags' => $dietTags,
                'diet_type' => null,
                'cycle_phase' => $planPhaseBundle['cycle_phase'],
                'cycle_phases' => $planPhaseBundle['cycle_phases'],
                'finished_weight_grams' => isset($data['finished_weight_grams']) ? (float) $data['finished_weight_grams'] : null,
                'is_bulk' => (bool) ($data['is_bulk'] ?? false),
                'servings_count' => ($data['is_bulk'] ?? false) && isset($data['servings_count'])
                    ? (float) $data['servings_count']
                    : null,
            ];
            $nutritionTargets = $this->nutritionTargetsFromValidated($data);
            if ($nutritionTargets !== null) {
                $updateData = array_merge($updateData, $nutritionTargets);
            }
            $meal->update($updateData);

            $this->syncLibraryMealIngredientsPhotoAndAggregates($request, $meal, $data);
        });

        $this->syncMealMasterCsvFromDatabase();

        return redirect()
            ->route('admin.meal-library')
            ->with('success', __('Meal updated successfully.'));
    }

    private function syncMealMasterCsvFromDatabase(): void
    {
        $this->menuDevelopmentCsvSync->syncMealsFromDatabase();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncLibraryMealIngredientsPhotoAndAggregates(StoreMealFromLibraryRequest $request, Meal $meal, array $data): void
    {
        $byIngredientGrams = $this->aggregateIngredientGramsFromLibraryRows($data['ingredients'] ?? []);

        $meal->ingredients()->detach();

        foreach ($byIngredientGrams as $ingredientId => $grams) {
            $rounded = round($grams, 2);
            $meal->ingredients()->attach($ingredientId, [
                'amount_grams' => $rounded,
                'amount' => $rounded,
                'unit' => 'g',
            ]);
        }

        if ($request->hasFile('photo')) {
            $oldPath = $meal->image_path;
            if (is_string($oldPath) && $oldPath !== '' && MealImagePath::shouldDeleteFromPublicDisk($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            // Relative path on the `public` disk (e.g. `meals/…`); served via `storage` symlink.
            $path = $request->file('photo')->store('meals', 'public');
            $meal->image_path = $path;
        }

        $meal->load('ingredients');

        $ingredientIdsForSafety = array_map(intval(...), array_keys($byIngredientGrams));
        $meal->safety_alert_tags = $this->safetyAlertTagsForIngredientIds($ingredientIdsForSafety);

        $isBulk = (bool) ($data['is_bulk'] ?? false);

        if ($meal->ingredients->isNotEmpty() && ! $isBulk) {
            $nutrition = RecipeNutritionCalculator::fromMeal($meal);
            $meal->fill(Meal::nutritionSummaryToPersistedAttributes($nutrition));
            $meal->sickle_cell_program_highlight = RecipeNutritionCalculator::sickleCellProgramMealHighlight($nutrition);
            $meal->nutrition_aggregates_synced = true;
        } elseif ($meal->ingredients->isNotEmpty() && $isBulk) {
            $meal->sickle_cell_program_highlight = RecipeNutritionCalculator::sickleCellProgramMealHighlight(
                $meal->persistedNutritionAsCalculatorShape()
            );
            $meal->nutrition_aggregates_synced = false;
        } else {
            $meal->sickle_cell_program_highlight = RecipeNutritionCalculator::sickleCellProgramMealHighlight(
                $meal->persistedNutritionAsCalculatorShape()
            );
            $meal->nutrition_aggregates_synced = false;
        }

        $meal->save();

        $meal->refresh();
        $meal->load('ingredients');
    }

    /**
     * @param  list<array<string, mixed>>  $ingredientRows
     * @return array<int, float>
     */
    private function aggregateIngredientGramsFromLibraryRows(array $ingredientRows): array
    {
        $byIngredientGrams = [];
        foreach ($ingredientRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $grams = (float) ($row['amount_grams'] ?? 0);
            if ($grams <= 0) {
                continue;
            }

            $ingredient = $this->resolveLibraryIngredientFromMealFormRow($row);
            if ($ingredient === null) {
                continue;
            }

            $id = (int) $ingredient->getKey();
            $byIngredientGrams[$id] = ($byIngredientGrams[$id] ?? 0) + $grams;
        }

        return $byIngredientGrams;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveLibraryIngredientFromMealFormRow(array $row): ?Ingredient
    {
        $ingredientId = $row['ingredient_id'] ?? null;
        if ($ingredientId !== null && $ingredientId !== '') {
            $ingredient = Ingredient::query()->whereKey((int) $ingredientId)->first();

            if ($ingredient !== null) {
                return $ingredient;
            }
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return IngredientLibraryNameMatcher::resolveForImportLabel($name);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     meal_plan_tags: list<string>|null,
     *     cycle_phases: list<string>|null,
     *     meal_plan_tag: string|null,
     *     cycle_phase: ?CyclePhase
     * }
     */
    private function mealPlanTagsAndCyclePhasesForPersistence(array $data): array
    {
        $planTags = [];
        foreach ($data['meal_plan_tags'] ?? [] as $t) {
            if (! is_string($t) || $t === '') {
                continue;
            }
            $canonical = MealLibraryTaxonomy::resolveMealPlanTagCanonical($t);
            if ($canonical !== null) {
                $planTags[] = $canonical;
            }
        }
        $planTags = array_values(array_unique($planTags));

        $phaseStrings = [];
        foreach ($data['cycle_phases'] ?? [] as $p) {
            if (! is_string($p) || $p === '') {
                continue;
            }
            $enum = CyclePhase::tryFrom($p);
            if ($enum !== null) {
                $phaseStrings[] = $enum->value;
            }
        }
        $phaseStrings = array_values(array_unique($phaseStrings));

        $firstTag = $planTags[0] ?? null;
        $firstPhase = isset($phaseStrings[0]) ? CyclePhase::from($phaseStrings[0]) : null;

        return [
            'meal_plan_tags' => $planTags === [] ? null : $planTags,
            'cycle_phases' => $phaseStrings === [] ? null : $phaseStrings,
            'meal_plan_tag' => $firstTag,
            'cycle_phase' => $firstPhase,
        ];
    }

    /**
     * English UI labels for {@see MealDetailView} cycle phase chips (matches TS {@code CyclePhase} union).
     *
     * @return list<string>
     */
    private function cyclePhaseEnglishLabelsForDetailView(Meal $meal): array
    {
        $labels = [];
        $raw = is_array($meal->cycle_phases) ? $meal->cycle_phases : [];
        foreach ($raw as $v) {
            if (! is_string($v) || $v === '') {
                continue;
            }
            $enum = CyclePhase::tryFrom($v);
            if ($enum === null) {
                continue;
            }
            $labels[] = match ($enum) {
                CyclePhase::Menstrual => 'Menstrual',
                CyclePhase::Follicular => 'Follicular',
                CyclePhase::Ovulatory => 'Ovulatory',
                CyclePhase::Luteal => 'Luteal',
            };
        }
        $labels = array_values(array_unique($labels));
        if ($labels === [] && $meal->cycle_phase instanceof CyclePhase) {
            $labels[] = match ($meal->cycle_phase) {
                CyclePhase::Menstrual => 'Menstrual',
                CyclePhase::Follicular => 'Follicular',
                CyclePhase::Ovulatory => 'Ovulatory',
                CyclePhase::Luteal => 'Luteal',
            };
        }

        return $labels;
    }

    /**
     * @return array<string, mixed>
     */
    private function mealEditFormSnapshot(Meal $meal): array
    {
        $meal->loadMissing('ingredients');

        $ingredientRows = [];
        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
            $gramsStr = $grams > 0 ? (string) (round($grams * 10000) / 10000) : '100';
            $ingredientRows[] = [
                'ingredientId' => (int) $ingredient->id,
                'selectedName' => $ingredient->name,
                'nameQuery' => $ingredient->name,
                'amount' => $gramsStr,
                'unit' => 'g',
            ];
        }

        if ($ingredientRows === []) {
            $ingredientRows[] = [
                'ingredientId' => null,
                'selectedName' => '',
                'nameQuery' => '',
                'amount' => '100',
                'unit' => 'g',
            ];
        }

        $mealPlanTagsArr = [];
        if (is_array($meal->meal_plan_tags)) {
            foreach ($meal->meal_plan_tags as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $mealPlanTagsArr[] = trim($t);
                }
            }
        }
        if ($mealPlanTagsArr === [] && is_string($meal->meal_plan_tag ?? null) && trim((string) $meal->meal_plan_tag) !== '') {
            $mealPlanTagsArr[] = trim((string) $meal->meal_plan_tag);
        }
        $mealPlanTagsArr = array_values(array_unique($mealPlanTagsArr));

        $cyclePhaseValues = [];
        if (is_array($meal->cycle_phases)) {
            foreach ($meal->cycle_phases as $p) {
                if (is_string($p) && CyclePhase::tryFrom($p) !== null) {
                    $cyclePhaseValues[] = CyclePhase::from($p)->value;
                }
            }
        }
        if ($cyclePhaseValues === [] && $meal->cycle_phase instanceof CyclePhase) {
            $cyclePhaseValues[] = $meal->cycle_phase->value;
        }
        $cyclePhaseValues = array_values(array_unique($cyclePhaseValues));

        return [
            'id' => (string) $meal->id,
            'name' => $meal->name,
            'category' => ($meal->category ?? RecipeCategory::Meal)->value,
            'mealPlanTags' => $mealPlanTagsArr,
            'dietTags' => is_array($meal->diet_tags) ? array_values(array_filter($meal->diet_tags, static fn ($t): bool => is_string($t) && trim($t) !== '')) : [],
            'cyclePhaseValues' => $cyclePhaseValues,
            'description' => $this->mealInstructionsText($meal),
            'highlight' => $this->mealShortDescriptionText($meal),
            'instructions' => $this->mealInstructionsText($meal),
            'shortDescription' => $this->mealShortDescriptionText($meal),
            'totalCalories' => (string) (int) round((float) ($meal->total_calories ?? 0)),
            'totalProtein' => $this->macroDecimalStringForForm((float) ($meal->total_protein ?? 0)),
            'totalCarbs' => $this->macroDecimalStringForForm((float) ($meal->total_carbs ?? 0)),
            'totalFat' => $this->macroDecimalStringForForm((float) ($meal->total_fat ?? 0)),
            'finishedWeightGrams' => $meal->finished_weight_grams !== null ? (string) $meal->finished_weight_grams : '',
            'ingredientRows' => $ingredientRows,
            'imageUrl' => $this->mealImageUrl($meal),
            'isBulk' => (bool) ($meal->is_bulk ?? false),
            'servingsCount' => $this->servingsCountStringForEditForm($meal),
            'targetCalories' => $meal->target_calories !== null ? (string) (int) round((float) $meal->target_calories) : '',
            'targetProtein' => $meal->target_protein !== null ? $this->macroDecimalStringForForm((float) $meal->target_protein) : '',
            'targetCarbs' => $meal->target_carbs !== null ? $this->macroDecimalStringForForm((float) $meal->target_carbs) : '',
            'targetFat' => $meal->target_fat !== null ? $this->macroDecimalStringForForm((float) $meal->target_fat) : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float|null>|null
     */
    private function nutritionTargetsFromValidated(array $data): ?array
    {
        $keys = ['target_calories', 'target_protein', 'target_carbs', 'target_fat'];
        $any = false;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $any = true;
                break;
            }
        }
        if (! $any) {
            return null;
        }

        return [
            'target_calories' => $this->nullableFloatFromValidated($data, 'target_calories'),
            'target_protein' => $this->nullableFloatFromValidated($data, 'target_protein'),
            'target_carbs' => $this->nullableFloatFromValidated($data, 'target_carbs'),
            'target_fat' => $this->nullableFloatFromValidated($data, 'target_fat'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nullableFloatFromValidated(array $data, string $key): ?float
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function servingsCountStringForEditForm(Meal $meal): string
    {
        if (! ($meal->is_bulk ?? false) || $meal->servings_count === null) {
            return '';
        }

        $n = (float) $meal->servings_count;
        if (! is_finite($n) || $n <= 0) {
            return '';
        }

        if (abs($n - round($n)) < 0.0001) {
            return (string) (int) round($n);
        }

        $s = number_format($n, 2, '.', '');

        return rtrim(rtrim($s, '0'), '.') ?: '';
    }

    private function macroDecimalStringForForm(float $value): string
    {
        if (! is_finite($value)) {
            return '';
        }
        $rounded = round($value, 1);
        $s = number_format($rounded, 1, '.', '');

        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }

    private function mealLibrarySchemaReady(): bool
    {
        try {
            return Schema::hasColumn('meals', 'deleted_at')
                && Schema::hasColumn('meals', 'safety_alert_tags')
                && Schema::hasColumn('meals', 'nutrition_aggregates_synced')
                && Schema::hasColumn('meals', 'meal_plan_tags')
                && Schema::hasColumn('meals', 'cycle_phases')
                && Schema::hasColumn('ingredients', 'common_allergens')
                && Schema::hasColumn('ingredients', 'is_g6pd_trigger');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $meals
     * @param  list<array<string, mixed>>  $ingredientProfiles
     * @return array<string, mixed>
     */
    /**
     * @return array{meals: list<array<string, mixed>>, ingredientProfiles: list<array<string, mixed>>, pendingMealImports: list<string>}
     */
    private function mealLibraryIndexPayload(array $meals, array $ingredientProfiles, ?User $user = null): array
    {
        $pendingMealImports = [];

        if ($user !== null) {
            $pendingMealImports = MealCsvImportPendingRow::query()
                ->where('user_id', $user->getKey())
                ->orderBy('meal_name')
                ->pluck('meal_name')
                ->values()
                ->all();
        }

        return [
            'meals' => $meals,
            'ingredientProfiles' => $ingredientProfiles,
            'pendingMealImports' => $pendingMealImports,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Meal library row + consultation deck card shape (includes `detailView` for modals).
     *
     * @return array<string, mixed>
     */
    public function presentMealRowForUi(Meal $meal): array
    {
        return $this->toMealRow($meal);
    }

    /**
     * Verified ingredient profiles for meal editor comboboxes and live nutrition rollups.
     *
     * @return list<array<string, mixed>>
     */
    public function verifiedIngredientProfilesForUi(): array
    {
        return Ingredient::query()
            ->where('is_verified', true)
            ->with(['components'])
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient): array => $this->toIngredientProfile($ingredient))
            ->values()
            ->all();
    }

    private function toMealRow(Meal $meal): array
    {
        $meal->loadMissing('ingredients');

        if ($meal->is_bulk) {
            $nutrition = $this->nutritionFromStoredTotals($meal);
        } elseif ($meal->ingredients->isNotEmpty() && $meal->nutrition_aggregates_synced) {
            $nutrition = $meal->persistedNutritionAsCalculatorShape();
        } elseif ($meal->ingredients->isNotEmpty()) {
            $nutrition = RecipeNutritionCalculator::fromMeal($meal);
        } else {
            $nutrition = $this->nutritionFromStoredTotals($meal);
        }

        $nutritionForDetailView = MealLibraryBulkNutrition::perServingNutritionForMealDisplay($meal);

        $nutrientHighlights = $this->nutrientHighlightsForUi($nutrition);
        $ingredientIds = $meal->ingredients->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (IngredientG6pdSafety::mealContainsG6pdTrigger($ingredientIds)) {
            $nutrientHighlights[] = IngredientG6pdSafety::SAFETY_ALERT_BADGE_LABEL;
        }
        $nutrientHighlights = array_values(array_unique($nutrientHighlights));

        $storedSafety = is_array($meal->safety_alert_tags) ? $meal->safety_alert_tags : [];
        $safetyAlertTags = $ingredientIds !== []
            ? $this->safetyAlertTagsForIngredientIds($ingredientIds)
            : array_values($storedSafety);

        return [
            'id' => (string) $meal->id,
            'title' => $meal->name,
            'imageUrl' => $this->mealImageUrl($meal),
            'mealType' => ($meal->category ?? RecipeCategory::Meal)->value,
            'category' => ($meal->category ?? RecipeCategory::Meal)->value,
            'prepMinutes' => 0,
            'macros' => [
                'calories' => (int) round((float) ($nutrition['calories'] ?? 0)),
                'protein' => round((float) ($nutrition['protein'] ?? 0), 1),
                'carbs' => round((float) ($nutrition['carbs'] ?? 0), 1),
                'fat' => round((float) ($nutrition['fat'] ?? 0), 1),
            ],
            'tags' => $this->tagsForMealCard($meal),
            'nutrientHighlights' => $nutrientHighlights,
            'safetyAlertTags' => array_values($safetyAlertTags),
            'detailView' => $this->buildMealDetailViewPayload($meal, $nutritionForDetailView, $safetyAlertTags),
            'editForm' => $this->mealEditFormSnapshot($meal),
        ];
    }

    /**
     * Shape matches the meal library {@code MealDetailView} modal `meal` prop.
     *
     * @param  array<string, float>  $nutrition
     * @param  list<string>  $safetyAlertTags
     * @return array<string, mixed>
     */
    private function buildMealDetailViewPayload(Meal $meal, array $nutrition, array $safetyAlertTags): array
    {
        $cyclePhases = $this->cyclePhaseEnglishLabelsForDetailView($meal);

        $dietaryTags = [];
        if (is_array($meal->meal_plan_tags)) {
            foreach ($meal->meal_plan_tags as $mpt) {
                if (is_string($mpt) && trim($mpt) !== '') {
                    $dietaryTags[] = trim($mpt);
                }
            }
        }
        if ($dietaryTags === []) {
            $mealPlanTagSingle = trim((string) ($meal->meal_plan_tag ?? ''));
            if ($mealPlanTagSingle !== '') {
                $dietaryTags[] = $mealPlanTagSingle;
            }
        }
        foreach (is_array($meal->diet_tags) ? $meal->diet_tags : [] as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $dietaryTags[] = trim($tag);
            }
        }
        $dietaryTags = array_values(array_unique($dietaryTags));

        $instructionsRaw = $this->mealInstructionsText($meal);
        $instructions = $this->instructionsLinesFromText($instructionsRaw);

        $shortDescription = $this->mealShortDescriptionText($meal);

        $ingredientIds = $meal->ingredients->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hasG6pdTrigger = IngredientG6pdSafety::mealContainsG6pdTrigger($ingredientIds);
        $sickleCellHighlights = SickleCellNutrientRdi::highlightBadgeLabels($nutrition);

        $safetyAlerts = $this->safetyAlertsForDetailView($safetyAlertTags);
        if ($hasG6pdTrigger) {
            $safetyAlerts = array_values(array_filter(
                $safetyAlerts,
                static fn (array $a): bool => ! str_contains(strtoupper($a['label']), 'G6PD'),
            ));
        }

        $formatLine = function (Ingredient $ingredient, float $grams) use ($meal): string {
            $grams = MealLibraryBulkNutrition::perServingGramsForMealDisplay($meal, $grams);
            $formattedGrams = $this->formatTrimmedDecimal($grams, 2);

            if (EggIngredientPresentation::isEggIngredient($ingredient)) {
                return EggIngredientPresentation::formatLine($grams, $formattedGrams);
            }

            if ($grams > 0) {
                return $formattedGrams.'g '.$ingredient->name;
            }

            return $ingredient->name;
        };

        $ingredientLines = SaladMealPresentation::orderedIngredientLinesForMeal($meal, $formatLine);

        if ($ingredientLines === []) {
            $ingredientLines = [__('No ingredients on file.')];
        }

        $payload = [
            'shortDescription' => $shortDescription,
            'cyclePhases' => $cyclePhases,
            'dietaryTags' => $dietaryTags,
            'hasG6pdTrigger' => $hasG6pdTrigger,
            'safetyAlerts' => $safetyAlerts,
            'sickleCellHighlights' => $sickleCellHighlights,
            'nutritionalData' => $this->nutritionalDataForDetailView($nutrition),
            'ingredients' => $ingredientLines,
            'instructions' => $instructions,
            'imageUrl' => $this->mealImageUrl($meal),
            'imageAlt' => $meal->name,
        ];

        if (SaladMealPresentation::isSaladMeal($meal)) {
            $ingredientSections = SaladMealPresentation::ingredientSectionsForMeal($meal, $formatLine);

            if ($ingredientSections !== []) {
                $payload['ingredientSections'] = $ingredientSections;
            }

            $instructionSections = SaladMealPresentation::instructionSectionsForMeal($meal);

            if ($instructionSections !== []) {
                $payload['instructionSections'] = $instructionSections;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function instructionsFromValidated(array $data): ?string
    {
        $raw = ($data['instructions'] ?? null) !== null && trim((string) $data['instructions']) !== ''
            ? (string) $data['instructions']
            : (string) ($data['description'] ?? '');

        return MealInstructionsText::normalizeForStorage($raw);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shortDescriptionFromValidated(array $data): ?string
    {
        $text = trim((string) ($data['short_description'] ?? $data['highlight'] ?? ''));

        return $text !== '' ? $text : null;
    }

    private function mealInstructionsText(Meal $meal): string
    {
        $text = trim((string) ($meal->instructions ?? ''));
        if ($text !== '') {
            return $text;
        }

        return trim((string) ($meal->description ?? ''));
    }

    private function mealShortDescriptionText(Meal $meal): string
    {
        $text = trim((string) ($meal->short_description ?? ''));
        if ($text !== '') {
            return $text;
        }

        return trim((string) ($meal->highlight ?? ''));
    }

    /**
     * @return list<string>
     */
    private function instructionsLinesFromText(string $instructionsRaw): array
    {
        $steps = MealInstructionsText::linesFromRaw($instructionsRaw);

        if ($steps === []) {
            return [__('No written instructions on file.')];
        }

        return $steps;
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
     * @param  array<string, float>  $nutrition
     * @return array<string, mixed>
     */
    private function nutritionalDataForDetailView(array $nutrition): array
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
            ['label' => __('Vitamin K2 (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_k2'] ?? 0), 1)],
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
            'valueColumnLabel' => __('Per serving'),
            'sections' => [
                ['title' => __('Macros'), 'rows' => $macroRows],
                ['title' => __('Vitamins'), 'rows' => $vitaminRows],
                ['title' => __('Minerals'), 'rows' => $mineralRows],
            ],
        ];
    }

    private function formatTrimmedDecimal(float $value, int $decimals): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        $formatted = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
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

        foreach ($rows as $ingredient) {
            foreach (IngredientAllergenCatalog::labelsFromSlugs(
                is_array($ingredient->common_allergens) ? $ingredient->common_allergens : [],
            ) as $label) {
                $labels[$label] = true;
            }
        }

        return IngredientG6pdSafety::mergeTriggerIntoSafetyLabels(
            array_keys($labels),
            IngredientG6pdSafety::mealContainsG6pdTrigger($ingredientIds),
        );
    }

    /**
     * @return array<string, float>
     */
    private function nutritionFromStoredTotals(Meal $meal): array
    {
        return [
            'calories' => (float) ($meal->total_calories ?? 0),
            'protein' => (float) ($meal->total_protein ?? 0),
            'carbs' => (float) ($meal->total_carbs ?? 0),
            'fat' => (float) ($meal->total_fat ?? 0),
            'b6' => (float) ($meal->total_b6 ?? 0),
            'b9_folate' => (float) ($meal->total_folate ?? 0),
            'b12' => (float) ($meal->total_b12 ?? 0),
            'iron' => (float) ($meal->total_iron ?? 0),
            'magnesium' => (float) ($meal->total_magnesium ?? 0),
            'fiber' => (float) ($meal->total_fiber ?? 0),
            'sugar' => (float) ($meal->total_sugar ?? 0),
            'calcium' => (float) ($meal->total_calcium ?? 0),
            'potassium' => (float) ($meal->total_potassium ?? 0),
            'sodium' => (float) ($meal->total_sodium ?? 0),
            'zinc' => (float) ($meal->total_zinc ?? 0),
            'vitamin_c' => (float) ($meal->total_vitamin_c ?? 0),
            'vitamin_a' => (float) ($meal->total_vitamin_a ?? 0),
            'vitamin_e' => (float) ($meal->total_vitamin_e ?? 0),
            'vitamin_d' => (float) ($meal->total_vitamin_d ?? 0),
            'vitamin_k2' => (float) ($meal->total_vitamin_k2 ?? 0),
        ];
    }

    /**
     * Smart Kitchen highlight chips (aligned with Meal Library create preview).
     *
     * @param  array<string, float>  $nutrition
     * @return list<string>
     */
    private function nutrientHighlightsForUi(array $nutrition): array
    {
        return SickleCellNutrientRdi::highlightBadgeLabels($nutrition);
    }

    /**
     * @return list<array{label: string, type: string}>
     */
    private function tagsForMealCard(Meal $meal): array
    {
        $tags = [];
        $category = $meal->category;
        if ($category !== null) {
            $tags[] = ['label' => $category->value, 'type' => 'category'];
        }
        if (is_array($meal->meal_plan_tags)) {
            foreach ($meal->meal_plan_tags as $mpt) {
                $label = is_string($mpt) ? trim($mpt) : '';
                if ($label !== '') {
                    $tags[] = ['label' => $label, 'type' => 'dietary'];
                }
            }
        } else {
            $mealPlanTag = is_string($meal->meal_plan_tag ?? null) ? trim((string) $meal->meal_plan_tag) : '';
            if ($mealPlanTag !== '') {
                $tags[] = ['label' => $mealPlanTag, 'type' => 'dietary'];
            }
        }
        $dietTags = is_array($meal->diet_tags) ? $meal->diet_tags : [];
        foreach ($dietTags as $tag) {
            $label = is_string($tag) ? trim($tag) : '';
            if ($label !== '') {
                $tags[] = ['label' => $label, 'type' => 'dietary'];
            }
        }

        return $tags;
    }

    private function mealImageUrl(Meal $meal): string
    {
        return MealImagePath::resolveUrl($meal->image_path, $meal->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function toIngredientProfile(Ingredient $ingredient): array
    {
        $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($ingredient);
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

        return [
            'id' => (int) $ingredient->getKey(),
            'name' => $ingredient->name,
            'calories' => (float) ($per100['calories'] ?? 0),
            'protein' => (float) ($per100['protein'] ?? 0),
            'carbs' => (float) ($per100['carbs'] ?? 0),
            'fat' => (float) ($per100['fat'] ?? 0),
            'b6' => (float) ($per100['b6'] ?? 0),
            'b9_folate' => (float) ($per100['b9_folate'] ?? 0),
            'b12' => (float) ($per100['b12'] ?? 0),
            'iron' => (float) ($per100['iron'] ?? 0),
            'magnesium' => (float) ($per100['magnesium'] ?? 0),
            'micronutrients' => array_merge($micros, [
                'fiber' => (float) ($per100['fiber'] ?? $micros['fiber'] ?? 0),
                'sugar' => (float) ($per100['sugar'] ?? $micros['sugar'] ?? 0),
                'calcium' => (float) ($per100['calcium'] ?? $micros['calcium'] ?? 0),
                'potassium' => (float) ($per100['potassium'] ?? $micros['potassium'] ?? 0),
                'sodium' => (float) ($per100['sodium'] ?? $micros['sodium'] ?? 0),
                'zinc' => (float) ($per100['zinc'] ?? $micros['zinc'] ?? 0),
                'vitamin_c' => (float) ($per100['vitamin_c'] ?? $micros['vitamin_c'] ?? 0),
                'vitamin_a' => (float) ($per100['vitamin_a'] ?? $micros['vitamin_a'] ?? 0),
                'vitamin_e' => (float) ($per100['vitamin_e'] ?? $micros['vitamin_e'] ?? 0),
                'vitamin_d' => (float) ($per100['vitamin_d'] ?? $micros['vitamin_d'] ?? 0),
                'vitamin_k2' => (float) ($per100['vitamin_k2'] ?? $micros['vitamin_k2'] ?? 0),
            ]),
            'density' => (float) ($ingredient->density ?? 0) > 0 ? (float) $ingredient->density : 1.0,
            'common_allergens' => array_values(array_filter(
                is_array($ingredient->common_allergens) ? $ingredient->common_allergens : [],
                static fn ($v): bool => is_string($v) && $v !== '',
            )),
            'is_g6pd_trigger' => IngredientG6pdSafety::ingredientHasEffectiveG6pdTrigger($ingredient),
            'is_prepared_base' => $ingredient->isPreparedBaseIngredient(),
        ];
    }
}
