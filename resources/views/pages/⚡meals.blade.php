<?php

use App\Enums\MealType;
use App\Enums\RecipeAmountUnit;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealCsvLibraryImportService;
use App\Services\MealCyclePhaseTaggingService;
use App\Services\MealRecipeAsIngredientSyncService;
use App\Services\RecipeIngredientUnitConverter;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealImagePath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Meals')] class extends Component {
    use WithFileUploads;
    use WithPagination;

    /**
     * @var array<string, string>
     */
    protected $listeners = ['ingredientsImported' => '$refresh'];

    /** Grid filter: empty string means all types (matches HTML select “All”). */
    public string $selectedMealType = '';

    public string $search = '';

    /** @var list<int|string> Meal IDs selected for bulk actions (checkbox values are strings). */
    public array $selectedMeals = [];

    /** `grid` or `list` */
    public string $libraryViewMode = 'grid';

    public bool $showMealDetailsModal = false;

    public ?int $detailsMealId = null;

    public string $name = '';

    /** @var value-of<MealType> */
    public string $mealType = '';

    public string $instructions = '';

    /** @var mixed */
    public $mealImage;

    /** @var mixed */
    public $mealLibraryImportCsv;

    /** @var list<string> */
    public array $mealLibraryImportPendingIngredients = [];

    public ?int $editingMealId = null;

    /** @var array<int, array{ingredient_id: int|null, amount: float, unit: string}> */
    public array $recipeIngredients = [];

    /** @var array<int, string> */
    public array $recipeIngredientSearch = [];

    /** User-editable finished batch weight (g); auto-tracks raw sum until manually edited. */
    public string $finishedWeightGrams = '';

    public bool $finishedWeightManual = false;

    private bool $isSyncingFinishedWeight = false;

    public ?string $status = null;

    public ?string $error = null;

    public function mount(?Meal $meal = null): void
    {
        $this->mealType = MealType::Main->value;
        $this->recipeIngredients = [$this->emptyRecipeIngredientRow(forNewRecipe: true)];
        $this->recipeIngredientSearch = [''];

        if ($meal instanceof Meal) {
            $this->fillFormFromMeal($meal);
        } else {
            $this->syncFinishedWeightFromRawTotal();
        }

        if (session()->has('flash_meal_status')) {
            $this->status = (string) session()->pull('flash_meal_status');
        }
    }

    /**
     * @return array{ingredient_id: int|null, amount: float, unit: string}
     */
    private function emptyRecipeIngredientRow(bool $forNewRecipe = false): array
    {
        return [
            'ingredient_id' => null,
            'amount' => $forNewRecipe ? 100.0 : 0.0,
            'unit' => RecipeAmountUnit::Grams->value,
        ];
    }

    private function fillFormFromMeal(Meal $meal): void
    {
        $meal->loadMissing('ingredients');

        $this->editingMealId = $meal->id;
        $this->name = $meal->name;
        $this->mealType = $meal->meal_type instanceof MealType
            ? $meal->meal_type->value
            : MealType::fromRecipeCategory($meal->category ?? RecipeCategory::Meal)->value;
        $this->instructions = $meal->description ?? '';
        $derivedIngredientId = Ingredient::query()->where('source_meal_id', $meal->id)->value('id');

        $this->recipeIngredients = $meal->ingredients
            ->filter(function (Ingredient $ingredient) use ($derivedIngredientId): bool {
                if ($derivedIngredientId === null) {
                    return true;
                }

                return (int) $ingredient->id !== (int) $derivedIngredientId;
            })
            ->map(function (Ingredient $ingredient): array {
            $pivot = $ingredient->pivot;
            $pivotAmount = $pivot->amount;
            $displayAmount = ($pivotAmount !== null && $pivotAmount !== '' && (float) $pivotAmount > 0)
                ? (float) $pivotAmount
                : (float) $pivot->amount_grams;

            $unitRaw = $pivot->unit ?? '';
            $unit = (is_string($unitRaw) && $unitRaw !== '' && in_array($unitRaw, RecipeAmountUnit::values(), true))
                ? $unitRaw
                : RecipeAmountUnit::Grams->value;

            return [
                'ingredient_id' => (int) $ingredient->id,
                'amount' => $displayAmount,
                'unit' => $unit,
            ];
        })->values()->all();

        if ($this->recipeIngredients === []) {
            $this->recipeIngredients = [$this->emptyRecipeIngredientRow(forNewRecipe: true)];
        }

        $this->recipeIngredientSearch = collect($this->recipeIngredients)
            ->map(function (array $row): string {
                $ingredientIdRaw = $row['ingredient_id'] ?? null;
                $ingredientId = is_numeric($ingredientIdRaw) ? (int) $ingredientIdRaw : 0;

                if ($ingredientId <= 0) {
                    return '';
                }

                return (string) (Ingredient::query()->whereKey($ingredientId)->value('name') ?? '');
            })
            ->values()
            ->all();

        $storedFinished = $meal->finished_weight_grams;
        if ($storedFinished !== null && (float) $storedFinished > 0) {
            $this->isSyncingFinishedWeight = true;
            $this->finishedWeightGrams = $this->formatGramsInput((float) $storedFinished);
            $this->isSyncingFinishedWeight = false;
            $this->finishedWeightManual = true;
        } else {
            $this->finishedWeightManual = false;
            $this->syncFinishedWeightFromRawTotal();
        }

        $this->mealImage = null;
        $this->error = null;
        $this->resetValidation();
    }

    private function resetFormToCreateMode(): void
    {
        $this->editingMealId = null;
        $this->name = '';
        $this->mealType = MealType::Main->value;
        $this->instructions = '';
        $this->finishedWeightManual = false;
        $this->recipeIngredients = [$this->emptyRecipeIngredientRow(forNewRecipe: true)];
        $this->recipeIngredientSearch = [''];
        $this->syncFinishedWeightFromRawTotal();
        $this->mealImage = null;
        $this->error = null;
        $this->status = null;
        $this->resetValidation();
    }

    public function requiresFinishedWeightContext(): bool
    {
        return false;
    }

    private function formatGramsInput(float $grams): string
    {
        $s = rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    private function syncFinishedWeightFromRawTotal(): void
    {
        $this->isSyncingFinishedWeight = true;
        try {
            $raw = MealRecipeAsIngredientSyncService::totalGramsFromSync(
                $this->buildSyncPayloadFromIngredientRows($this->recipeIngredients)
            );
            $this->finishedWeightGrams = $raw > 0 ? $this->formatGramsInput($raw) : '';
        } finally {
            $this->isSyncingFinishedWeight = false;
        }
    }

    public function updatedFinishedWeightGrams(): void
    {
        if (! $this->isSyncingFinishedWeight) {
            $this->finishedWeightManual = true;
        }
    }

    public function updated($name): void
    {
        if (str_starts_with((string) $name, 'recipeIngredients') && ! $this->finishedWeightManual) {
            $this->syncFinishedWeightFromRawTotal();
        }
    }

    /**
     * @param  list<array{ingredient_id?: mixed, amount?: mixed, unit?: mixed}>  $rows
     * @return array<int, array{amount: float, unit: string, amount_grams: float}>
     */
    private function buildSyncPayloadFromIngredientRows(array $rows): array
    {
        $sync = [];
        foreach ($rows as $row) {
            $ingredientIdRaw = $row['ingredient_id'] ?? null;
            $ingredientId = is_numeric($ingredientIdRaw) ? (int) $ingredientIdRaw : 0;
            $amount = (float) ($row['amount'] ?? 0);
            $unit = (string) ($row['unit'] ?? RecipeAmountUnit::Grams->value);

            if ($ingredientId <= 0 || $amount <= 0) {
                continue;
            }

            $ingredient = Ingredient::query()->find($ingredientId, ['id', 'density']);

            if ($ingredient === null) {
                continue;
            }

            $density = (float) ($ingredient->density ?? 1.0);
            $grams = RecipeIngredientUnitConverter::toGrams($amount, $unit, $density);

            if ($grams <= 0) {
                continue;
            }

            $sync[$ingredientId] = [
                'amount' => round($amount, 4),
                'unit' => $unit,
                'amount_grams' => round($grams, 4),
            ];
        }

        return $sync;
    }

    public function getRawBatchGramsProperty(): float
    {
        return MealRecipeAsIngredientSyncService::totalGramsFromSync(
            $this->buildSyncPayloadFromIngredientRows($this->recipeIngredients)
        );
    }

    private function parsedFinishedWeightInput(): float
    {
        $s = trim(str_replace(',', '.', $this->finishedWeightGrams));

        if ($s === '') {
            return 0.0;
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /**
     * Yield of finished weight vs raw ingredient sum (e.g. 800 g / 1000 g → 80%).
     */
    public function getFinishedWeightYieldPercentProperty(): ?float
    {
        $raw = $this->rawBatchGrams;
        $fin = $this->parsedFinishedWeightInput();

        if ($raw <= 0 || $fin <= 0) {
            return null;
        }

        return round(($fin / $raw) * 100, 1);
    }

    public function paginationView(): string
    {
        return 'pagination.livewire-meal-craft';
    }

    public function updatedSelectedMealType(): void
    {
        if ($this->selectedMealType !== '' && MealType::tryFrom($this->selectedMealType) === null) {
            $this->selectedMealType = '';
        }

        $this->selectedMeals = [];
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->selectedMeals = [];
        $this->resetPage();
    }

    public function updatedMealType(string $value): void
    {
        if (! $this->finishedWeightManual && $this->requiresFinishedWeightContext()) {
            $this->syncFinishedWeightFromRawTotal();
        }
    }

    public function toggleLibrarySelectAllVisible(): void
    {
        $pageIds = $this->filteredMeals->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($pageIds === []) {
            return;
        }

        $selected = array_map('strval', $this->selectedMeals);
        $allOnPageSelected = count($pageIds) === count(array_intersect($pageIds, $selected));

        if ($allOnPageSelected) {
            $this->selectedMeals = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedMeals = array_values(array_unique(array_merge($selected, $pageIds)));
        }
    }

    public function getLibraryAllVisibleSelectedProperty(): bool
    {
        $pageIds = $this->filteredMeals->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('strval', $this->selectedMeals);

        return count($pageIds) === count(array_intersect($pageIds, $selected));
    }

    public function deleteSelectedMeals(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->selectedMeals)));
        $count = 0;

        foreach ($ids as $id) {
            if ($id > 0 && $this->performMealDeletion($id)) {
                $count++;
            }
        }

        $this->selectedMeals = [];

        if ($count > 0) {
            $this->status = __(':count meal(s) deleted.', ['count' => $count]);
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Meal>
     */
    public function getFilteredMealsProperty(): LengthAwarePaginator
    {
        $query = Meal::query()
            ->visibleInMealLibrary()
            ->orderBy('meal_type')
            ->orderByDesc('id');

        if ($this->selectedMealType !== '') {
            $query->where('meal_type', $this->selectedMealType);
        }

        if (filled($this->search)) {
            $needle = '%'.trim($this->search).'%';

            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'like', $needle)
                    ->orWhereHas('ingredients', function ($ingredientQuery) use ($needle): void {
                        $ingredientQuery->where('name', 'like', $needle);
                    });
            });
        }

        return $query->with('ingredients')->paginate(24);
    }

    public function getSavedMealsExistProperty(): bool
    {
        return Meal::query()->exists();
    }

    /**
     * @return array{line1: string, line2: string|null}
     */
    public function rowWeightExplanation(int $index): array
    {
        if (! isset($this->recipeIngredients[$index])) {
            return ['line1' => '', 'line2' => null];
        }

        $row = $this->recipeIngredients[$index];
        $ingredientId = isset($row['ingredient_id']) && is_numeric($row['ingredient_id']) ? (int) $row['ingredient_id'] : null;
        $amount = isset($row['amount']) && is_numeric($row['amount']) ? (float) $row['amount'] : 0.0;
        $unit = (string) ($row['unit'] ?? RecipeAmountUnit::Grams->value);

        if ($ingredientId === null || $ingredientId <= 0) {
            return [
                'line1' => __('Select an ingredient to see calculated weight.'),
                'line2' => null,
            ];
        }

        $density = (float) (Ingredient::query()->whereKey($ingredientId)->value('density') ?? 1.0);

        return RecipeIngredientUnitConverter::explain($amount, $unit, $density);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Ingredient>
     */
    public function ingredientSearchResults(int $index)
    {
        $term = trim((string) ($this->recipeIngredientSearch[$index] ?? ''));
        $query = Ingredient::query()->orderBy('name', 'asc');

        if ($this->editingMealId !== null) {
            $mealId = $this->editingMealId;
            $query->where(function ($q) use ($mealId): void {
                $q->whereNull('source_meal_id')
                    ->orWhere('source_meal_id', '<>', $mealId);
            });
        }

        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        return $query->limit(25)->get(['id', 'name', 'density', 'source_meal_id']);
    }

    public function chooseRecipeIngredient(int $index, int $ingredientId): void
    {
        if (! isset($this->recipeIngredients[$index])) {
            return;
        }

        $name = Ingredient::query()->whereKey($ingredientId)->value('name');

        if ($name === null) {
            return;
        }

        $this->recipeIngredients[$index]['ingredient_id'] = $ingredientId;
        $this->recipeIngredientSearch[$index] = (string) $name;
    }

    public function addRow(): void
    {
        $this->recipeIngredients[] = $this->emptyRecipeIngredientRow(forNewRecipe: false);
        $this->recipeIngredientSearch[] = '';
    }

    public function removeRow(int $index): void
    {
        if (! isset($this->recipeIngredients[$index])) {
            return;
        }

        array_splice($this->recipeIngredients, $index, 1);
        if (isset($this->recipeIngredientSearch[$index])) {
            array_splice($this->recipeIngredientSearch, $index, 1);
        }

        if ($this->recipeIngredients === []) {
            $this->recipeIngredients = [$this->emptyRecipeIngredientRow(forNewRecipe: false)];
            $this->recipeIngredientSearch = [''];
        }
    }

    /**
     * @return array<string, float>
     */
    public function getCalculatedNutritionProperty(): array
    {
        return RecipeNutritionCalculator::fromRows($this->recipeIngredients);
    }

    public function formatNutritionValue(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    /**
     * @return array{folate: bool, b12: bool, magnesium: bool, iron: bool}
     */
    public function getHighlightsProperty(): array
    {
        return RecipeNutritionCalculator::sickleCellHighlights($this->calculatedNutrition);
    }

    public function cancelMealEdit(): void
    {
        $this->resetFormToCreateMode();

        $this->redirect(route('meals.index'), navigate: true);
    }

    public function saveMealFromBuilder(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'mealType' => ['required', 'string', Rule::enum(MealType::class)],
            'instructions' => ['nullable', 'string'],
            'mealImage' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic', 'heif', 'avif'])->max(5120),
            ],
            'recipeIngredients' => ['array', 'min:1'],
            'recipeIngredients.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'recipeIngredients.*.amount' => ['required', 'numeric', 'min:0'],
            'recipeIngredients.*.unit' => ['required', 'string', Rule::enum(RecipeAmountUnit::class)],
        ];

        $validated = $this->validate($rules);

        $nutrition = $this->calculatedNutrition;

        $sync = $this->buildSyncPayloadFromIngredientRows($validated['recipeIngredients']);

        $nutritionPayload = Meal::nutritionSummaryToPersistedAttributes($nutrition);

        if ($sync === []) {
            $this->addError('recipeIngredients', __('Add at least one ingredient with a positive amount.'));

            return;
        }

        $selfDerivedIngredientId = $this->editingMealId !== null
            ? Ingredient::query()->where('source_meal_id', $this->editingMealId)->value('id')
            : null;

        if ($selfDerivedIngredientId !== null && array_key_exists((int) $selfDerivedIngredientId, $sync)) {
            $this->addError(
                'recipeIngredients',
                __('This meal cannot include its own linked library ingredient row. Remove that ingredient line.')
            );

            return;
        }

        $finishedForStorage = null;

        $imagePath = null;
        if ($this->mealImage instanceof TemporaryUploadedFile) {
            try {
                $stored = $this->mealImage->store('meals', 'public');
                if ($stored === false || $stored === '') {
                    throw new \RuntimeException('Meal image store() returned empty path.');
                }
                $imagePath = $stored;
            } catch (\Throwable $e) {
                report($e);
                $this->addError(
                    'mealImage',
                    __('Could not save the photo. Try a JPG or PNG under 5 MB, or check that storage/app/public is writable.')
                );

                return;
            }
        }

        $statusMessage = '';
        $mealTypeEnum = MealType::from($validated['mealType']);
        $recipeCategory = $mealTypeEnum->toRecipeCategory();

        if ($mealTypeEnum === MealType::BaseRecipe) {
            $this->addError(
                'mealType',
                __('Prepared base ingredients belong in the Ingredient Library. Use “Create Base Ingredient” there.'),
            );

            return;
        }

        if ($this->editingMealId !== null) {
            $meal = Meal::query()->find($this->editingMealId);

            if ($meal === null) {
                $this->error = __('Meal could not be found.');

                $this->redirect(route('meals.index'), navigate: true);

                return;
            }

            if ($imagePath === null) {
                $imagePath = $meal->image_path;
            } elseif (filled($meal->image_path) && MealImagePath::shouldDeleteFromPublicDisk($meal->image_path)) {
                Storage::disk('public')->delete($meal->image_path);
            }

            $meal->update(array_merge([
                'name' => $validated['name'],
                'meal_type' => $mealTypeEnum->value,
                'category' => $recipeCategory->value,
                'finished_weight_grams' => $finishedForStorage,
                'description' => filled($validated['instructions'] ?? null) ? $validated['instructions'] : null,
                'image_path' => $imagePath,
            ], $nutritionPayload));

            $meal->ingredients()->sync($sync);

            $this->error = null;
            $statusMessage = __('Meal updated.');
        } else {
            $meal = Meal::query()->create(array_merge([
                'name' => $validated['name'],
                'meal_type' => $mealTypeEnum->value,
                'category' => $recipeCategory->value,
                'finished_weight_grams' => $finishedForStorage,
                'description' => filled($validated['instructions'] ?? null) ? $validated['instructions'] : null,
                'image_path' => $imagePath,
            ], $nutritionPayload));

            $meal->ingredients()->sync($sync);

            $this->error = null;
            $statusMessage = __('Meal saved.');
        }

        $this->resetFormToCreateMode();
        $this->resetPage();
        $this->status = $statusMessage;

        $this->dispatch('ingredientsImported');

        app(MealCyclePhaseTaggingService::class)->refreshAutoTagsForEntireLibrary();

        if (request()->routeIs('meals.edit')) {
            $this->redirect(route('meals.index'), navigate: true);
        }
    }

    public function importMealLibraryCsv(MealCsvLibraryImportService $mealCsvLibraryImportService): void
    {
        $this->validate([
            'mealLibraryImportCsv' => ['required', File::types(['csv', 'txt'])->max(10240)],
        ]);

        $file = $this->mealLibraryImportCsv;

        if (! $file instanceof UploadedFile) {
            $this->addError('mealLibraryImportCsv', __('Wait for the file to finish uploading, then click Import again.'));

            return;
        }

        $result = $mealCsvLibraryImportService->processUploadedFile($file, auth()->user());

        $this->mealLibraryImportCsv = null;
        $this->resetPage();

        $s = $result['summary'];
        $this->status = __(
            'Meal library CSV: :updated meals updated, :imported new meals added, 0 duplicates created. :pending pending ingredients, :errors errors.',
            [
                'updated' => $s['updated'] ?? 0,
                'imported' => $s['imported'],
                'pending' => $s['pending_ingredient_input'],
                'errors' => $s['errors'],
            ]
        );

        $firstErrorDetail = collect($result['rows'])
            ->first(fn (array $row): bool => ($row['status'] ?? '') === 'error' && filled($row['message'] ?? null));

        if ($firstErrorDetail !== null) {
            $this->status .= ' '.$firstErrorDetail['message'];
        }

        $calorieWarningRows = collect($result['rows'] ?? [])
            ->filter(fn (array $row): bool => in_array($row['status'] ?? '', ['imported', 'updated'], true) && ($row['warnings'] ?? []) !== [])
            ->count();

        if ($calorieWarningRows > 0) {
            $this->status .= ' '.__(':count meal(s) have calorie totals outside the usual range for their category.', ['count' => $calorieWarningRows]);
        }

        $this->mealLibraryImportPendingIngredients = $result['unique_pending_ingredients'] ?? [];

        $this->error = null;
    }

    public function downloadMealLibraryImportTemplate()
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, MealCsvLibraryImportService::LIBRARY_CSV_HEADERS, ',', '"', '\\');
            fputcsv($handle, [
                'Salmon Bowl',
                'Meal',
                'Salmon:120 | Quinoa:100 | Spinach:50 | Olive Oil:10',
                'Grill salmon; mix with quinoa and greens.',
                'High in Omega-3 for hormone balance.',
                '520',
            ], ',', '"', '\\');
            fputcsv($handle, [
                'Beef Stir Fry',
                'Meal',
                'Sirloin:150 | Broccoli:100 | Garlic:5 | Ginger:5',
                'Sauté beef and veg in a hot pan.',
                'Zinc-rich for immune support.',
                '480',
            ], ',', '"', '\\');

            fclose($handle);
        }, 'meal-library-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function getDetailsMealProperty(): ?Meal
    {
        if ($this->detailsMealId === null) {
            return null;
        }

        return Meal::query()->with('ingredients')->find($this->detailsMealId);
    }

    /**
     * @return array<string, float>
     */
    public function getDetailsMealNutritionProperty(): array
    {
        $meal = $this->detailsMeal;

        if ($meal === null) {
            return [];
        }

        return $meal->nutritionForDisplay();
    }

    public function updatedShowMealDetailsModal(bool $value): void
    {
        if (! $value) {
            $this->detailsMealId = null;
        }
    }

    public function openMealDetails(int $mealId): void
    {
        $this->detailsMealId = $mealId;
        $this->showMealDetailsModal = true;
    }

    public function deleteMeal(int $mealId): void
    {
        if ($this->performMealDeletion($mealId)) {
            $this->selectedMeals = array_values(array_filter(
                $this->selectedMeals,
                fn ($id): bool => (int) $id !== $mealId
            ));
            $this->status = __('Meal deleted.');
            $this->resetPage();
        }
    }

    private function performMealDeletion(int $mealId): bool
    {
        $meal = Meal::query()->find($mealId);

        if ($meal === null) {
            return false;
        }

        if ($this->detailsMealId === $mealId) {
            $this->showMealDetailsModal = false;
            $this->detailsMealId = null;
        }

        if ($this->editingMealId === $mealId) {
            $this->resetFormToCreateMode();
        }

        if (filled($meal->image_path) && MealImagePath::shouldDeleteFromPublicDisk($meal->image_path)) {
            Storage::disk('public')->delete($meal->image_path);
        }

        $meal->delete();

        return true;
    }

    public function editMeal(int $mealId): void
    {
        $meal = Meal::query()->whereKey($mealId)->first();

        if ($meal === null) {
            $this->status = __('Meal could not be found.');

            return;
        }

        $this->redirect(route('meals.edit', $meal), navigate: true);
    }
}; ?>

<section class="w-full space-y-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Meal management hub') }}</flux:heading>
            <flux:text class="text-stone-600 dark:text-stone-300">
                {{ __('Build meals with live nutrition, then browse your library below.') }}
            </flux:text>
        </div>
        <flux:badge class="border-mc-gold-border/40 bg-mc-gold/15 text-mc-gold-deep dark:text-amber-100/90">{{ __('Meal Craft') }}</flux:badge>
    </div>

    <div id="meal-creator" class="scroll-mt-8 rounded-2xl border border-mc-gold-border/35 bg-mc-cream/90 p-6 shadow-sm dark:border-mc-gold/25 dark:bg-stone-900/90">
        <div class="mb-6 flex flex-col gap-2 border-b border-mc-gold-border/20 pb-4 dark:border-mc-gold/20">
            <flux:heading size="lg" class="text-stone-800 dark:text-stone-100">
                {{ $editingMealId !== null ? __('Edit meal') : __('Create meal') }}
            </flux:heading>
            <flux:text class="text-sm text-stone-500 dark:text-stone-400">
                {{ __('Add ingredients with amounts and units; totals use per-100 g data from your library.') }}
            </flux:text>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row lg:items-stretch">
            <div class="flex w-full flex-col space-y-4 rounded-xl border border-stone-200/90 bg-white/80 p-6 shadow-sm dark:border-stone-700 dark:bg-stone-950/60 lg:min-h-0 lg:w-2/3">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model.blur="name" :label="__('Meal name')" type="text" />
                    <flux:select wire:model.live="mealType" :label="__('Meal type')">
                        @foreach (MealType::dropdownCases() as $typeCase)
                            <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="w-full min-w-0">
                    <flux:textarea
                        class="w-full min-w-0"
                        wire:model.blur="instructions"
                        :label="__('Instructions')"
                        rows="5"
                    />
                </div>

                <div class="max-w-md">
                    {{-- Native file input: Flux file UI uses wire:ignore on a wrapper, which breaks Livewire temporary uploads. --}}
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300" for="meal-photo-upload">
                        {{ __('Meal photo (optional)') }}
                    </label>
                    <flux:text class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">
                        {{ __('JPG, PNG, WebP, HEIC, or AVIF — max 5 MB.') }}
                    </flux:text>
                    <input
                        id="meal-photo-upload"
                        type="file"
                        wire:model="mealImage"
                        wire:loading.attr="disabled"
                        wire:target="mealImage"
                        accept="image/*"
                        class="mt-1 block w-full cursor-pointer text-sm text-stone-600 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-stone-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-stone-700 hover:file:bg-stone-200 dark:text-stone-400 dark:file:bg-stone-800 dark:file:text-stone-200"
                    />
                    <flux:text class="mt-1 text-xs text-stone-500 dark:text-stone-400" wire:loading wire:target="mealImage">
                        {{ __('Uploading photo…') }}
                    </flux:text>
                    @error('mealImage')
                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="rounded-xl border border-stone-200/90 p-4 dark:border-stone-700">
                    <flux:heading size="sm" class="text-stone-800 dark:text-stone-100">{{ __('Ingredients') }}</flux:heading>

                    <div class="mt-3 space-y-4">
                        @foreach ($recipeIngredients as $index => $row)
                            @php
                                $weightHint = $this->rowWeightExplanation($index);
                            @endphp
                            <div
                                wire:key="meal-row-{{ $index }}"
                                class="flex flex-col gap-4 border-b border-stone-100 pb-4 last:border-b-0 last:pb-0 dark:border-stone-800 lg:flex-row lg:flex-nowrap lg:items-end lg:gap-4"
                            >
                                <div
                                    class="min-w-0 flex-1"
                                    x-data="{
                                        open: false,
                                        activeIndex: -1,
                                        move(delta, count) {
                                            if (count <= 0) return;
                                            if (!this.open) this.open = true;
                                            this.activeIndex = (this.activeIndex + delta + count) % count;
                                        },
                                        selectActive() {
                                            const btn = this.$refs.listbox?.querySelectorAll('[data-option]')?.[this.activeIndex];
                                            btn?.click();
                                        }
                                    }"
                                    @click.away="open = false"
                                >
                                    <flux:text class="mb-1 text-xs font-medium text-stone-700 dark:text-stone-300">{{ __('Ingredient') }}</flux:text>
                                    <div class="relative">
                                        <input
                                            type="search"
                                            inputmode="search"
                                            autocomplete="off"
                                            wire:model.live.debounce.300ms="recipeIngredientSearch.{{ $index }}"
                                            @focus="open = true; activeIndex = -1"
                                            @keydown.arrow-down.prevent="move(1, {{ $this->ingredientSearchResults($index)->count() }})"
                                            @keydown.arrow-up.prevent="move(-1, {{ $this->ingredientSearchResults($index)->count() }})"
                                            @keydown.enter.prevent="selectActive()"
                                            @keydown.escape.prevent="open = false"
                                            placeholder="{{ __('Type to search…') }}"
                                            class="block w-full rounded-xl border border-mc-gold-border/35 bg-mc-cream/90 px-3 py-2 text-sm text-stone-800 shadow-sm placeholder:text-stone-400 focus:border-mc-gold-border/60 focus:outline-none focus:ring-2 focus:ring-mc-gold-border/30 dark:border-mc-gold/25 dark:bg-stone-950/50 dark:text-stone-100 dark:placeholder:text-stone-500"
                                        />

                                        <div
                                            x-cloak
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-mc-gold-border/35 bg-mc-cream shadow-lg dark:border-mc-gold/25 dark:bg-stone-900"
                                            role="listbox"
                                            x-ref="listbox"
                                        >
                                            @php
                                                $matches = $this->ingredientSearchResults($index);
                                            @endphp
                                            @if ($matches->isEmpty())
                                                <div class="px-3 py-2 text-sm text-stone-600 dark:text-stone-300">
                                                    {{ __('No matches') }}
                                                </div>
                                            @else
                                                <div class="max-h-64 overflow-y-auto overscroll-contain py-1">
                                                    @foreach ($matches as $mi => $opt)
                                                        <button
                                                            type="button"
                                                            data-option
                                                            wire:click="chooseRecipeIngredient({{ $index }}, {{ (int) $opt->id }})"
                                                            @click="open = false"
                                                            @mouseenter="activeIndex = {{ $mi }}"
                                                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-stone-800 hover:bg-mc-gold/10 focus:bg-mc-gold/10 focus:outline-none dark:text-stone-100 dark:hover:bg-mc-gold/15 dark:focus:bg-mc-gold/15"
                                                            :class="{ 'bg-mc-gold/10 dark:bg-mc-gold/15': activeIndex === {{ $mi }} }"
                                                        >
                                                            <span class="min-w-0 truncate">
                                                                @if (filled($opt->source_meal_id))
                                                                    <span class="me-1 text-amber-700 dark:text-amber-200">⭐</span>
                                                                @endif
                                                                {{ $opt->name }}
                                                            </span>
                                                            @if (filled($opt->source_meal_id))
                                                                <span class="shrink-0 text-xs text-stone-600 dark:text-stone-300">
                                                                    {{ __('(Recipe)') }}
                                                                </span>
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full shrink-0 lg:w-28">
                                    <flux:input
                                        wire:model.live="recipeIngredients.{{ $index }}.amount"
                                        :label="__('Amount')"
                                        type="number"
                                        min="0"
                                        step="any"
                                    />
                                </div>

                                <div class="w-full shrink-0 lg:w-32">
                                    <flux:select wire:model.live="recipeIngredients.{{ $index }}.unit" :label="__('Unit')">
                                        @foreach (\App\Enums\RecipeAmountUnit::cases() as $unitOption)
                                            <option value="{{ $unitOption->value }}">{{ $unitOption->value }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="min-w-0 flex-1 lg:max-w-[14rem]">
                                    <flux:text class="text-xs font-medium text-stone-500 dark:text-stone-400">{{ __('Calculated weight') }}</flux:text>
                                    <flux:text class="text-sm font-medium text-stone-800 dark:text-stone-100">{{ $weightHint['line1'] }}</flux:text>
                                    @if (filled($weightHint['line2'] ?? null))
                                        <flux:text class="text-xs text-stone-500 dark:text-stone-400">{{ $weightHint['line2'] }}</flux:text>
                                    @endif
                                </div>

                                <div class="flex shrink-0 justify-end lg:justify-start lg:pb-0.5">
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="removeRow({{ $index }})">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @error('recipeIngredients')
                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @error('recipeIngredients.*.ingredient_id')
                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @error('recipeIngredients.*.amount')
                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @error('recipeIngredients.*.unit')
                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                    @enderror

                    <div
                        class="mt-4 flex flex-col gap-4 border-t border-stone-100 pt-4 dark:border-stone-800 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <flux:text class="text-xs font-medium text-stone-500 dark:text-stone-400">{{ __('Total raw weight') }}</flux:text>
                            <flux:text class="text-sm font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                                {{ $this->rawBatchGrams > 0 ? number_format($this->rawBatchGrams, 2).' g' : '—' }}
                            </flux:text>
                        </div>

                        @if ($this->requiresFinishedWeightContext())
                            <div class="flex min-w-0 flex-1 flex-col gap-2 sm:max-w-md lg:flex-row lg:items-end lg:gap-4">
                                <div class="min-w-0 flex-1">
                                    <flux:input
                                        wire:model.live="finishedWeightGrams"
                                        :label="__('Finished weight (g)')"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                    />
                                    <flux:text class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                                        {{ __('Enter the final weight after cooking (accounts for evaporation/reduction).') }}
                                    </flux:text>
                                    @error('finishedWeightGrams')
                                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                                    @enderror
                                </div>
                                @if ($this->finishedWeightYieldPercent !== null)
                                    <flux:text class="shrink-0 text-sm font-medium tabular-nums text-stone-600 dark:text-stone-300">
                                        {{ __(':pct% yield', ['pct' => $this->finishedWeightYieldPercent]) }}
                                    </flux:text>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 gap-y-2">
                    <div class="flex min-w-0 flex-wrap items-center gap-3">
                        <flux:button
                            type="button"
                            variant="primary"
                            wire:click="saveMealFromBuilder"
                            class="!border-transparent !bg-mc-gold !text-white hover:!bg-[#b08d4f] focus-visible:!ring-mc-gold-border/60"
                        >
                            {{ $editingMealId !== null ? __('Update meal') : __('Save meal') }}
                        </flux:button>
                        @if ($editingMealId !== null)
                            <flux:button type="button" variant="ghost" wire:click="cancelMealEdit">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="subtle" wire:click="downloadMealLibraryImportTemplate">
                            {{ __('Meal library CSV template') }}
                        </flux:button>
                        @if ($status)
                            <flux:text class="font-medium !text-green-700 !dark:text-green-400">{{ $status }}</flux:text>
                        @endif
                        @if ($error)
                            <flux:text class="font-medium !text-red-700 !dark:text-red-400">{{ __($error) }}</flux:text>
                        @endif
                    </div>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="outline"
                        icon="plus"
                        wire:click="addRow"
                        class="shrink-0 border-stone-300 text-stone-800 dark:border-stone-600 dark:text-stone-100"
                    >
                        {{ __('Add ingredient') }}
                    </flux:button>
                </div>

                <div class="mt-4 w-full border-t border-stone-200/90 pt-4 dark:border-stone-700">
                    <flux:heading size="sm" class="text-stone-800 dark:text-stone-100">{{ __('Bulk import (auto-calculate from ingredient library)') }}</flux:heading>
                    <flux:text class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                        {{ __('Required columns: Meal_Name, Category (:allowed), Ingredient_Quantities, Instructions, Description_Highlight. Use pipe-separated segments like Name:amount with optional unit (g, kg, ml, …), or Name amount unit when a space is used. Unknown ingredients block that row until they exist in your library.', ['allowed' => implode(', ', array_map(fn ($c) => $c->value, \App\Services\MealCsvLibraryImportService::mealLibraryCsvAllowedCategories()))]) }}
                    </flux:text>
                    <div class="mt-3 flex max-w-xl flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="min-w-0 flex-1">
                            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300" for="meal-library-csv-input">
                                {{ __('Meal library CSV') }}
                            </label>
                            <input
                                id="meal-library-csv-input"
                                type="file"
                                wire:model="mealLibraryImportCsv"
                                wire:loading.attr="disabled"
                                accept=".csv,.txt,text/csv,text/plain"
                                class="mt-1 block w-full cursor-pointer text-sm text-stone-600 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-stone-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-stone-700 hover:file:bg-stone-200 dark:text-stone-400 dark:file:bg-stone-800 dark:file:text-stone-200"
                            />
                            <flux:text class="mt-1 text-xs text-stone-500 dark:text-stone-400" wire:loading wire:target="mealLibraryImportCsv">
                                {{ __('Finishing upload…') }}
                            </flux:text>
                        </div>
                        <flux:button
                            type="button"
                            variant="primary"
                            wire:click="importMealLibraryCsv"
                            wire:loading.attr="disabled"
                            wire:target="importMealLibraryCsv,mealLibraryImportCsv"
                            class="!border-transparent !bg-stone-800 !text-white hover:!bg-stone-700 dark:!bg-stone-200 dark:!text-stone-900 dark:hover:!bg-white"
                        >
                            <span wire:loading.remove wire:target="importMealLibraryCsv,mealLibraryImportCsv">{{ __('Import to library') }}</span>
                            <span wire:loading wire:target="importMealLibraryCsv,mealLibraryImportCsv">{{ __('Importing…') }}</span>
                        </flux:button>
                    </div>
                    @error('mealLibraryImportCsv')
                        <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @if (count($mealLibraryImportPendingIngredients) > 0)
                        <div
                            class="mt-3 flex flex-wrap items-center gap-3"
                            x-data="{ names: @js($mealLibraryImportPendingIngredients) }"
                        >
                            <flux:text class="text-sm text-stone-600 dark:text-stone-300">
                                {{ __(':count unique ingredient(s) are missing from your library. Fill the CSV and bulk-import on Ingredients, then re-import meals.', ['count' => count($mealLibraryImportPendingIngredients)]) }}
                            </flux:text>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                @click="window.downloadMissingIngredientsCSV(names)"
                            >
                                {{ __('Download missing ingredients') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @php
                $n = $this->calculatedNutrition;
            @endphp
            <div class="flex w-full flex-col space-y-4 rounded-xl border border-mc-gold-border/30 bg-white/90 p-6 shadow-sm dark:border-mc-gold/25 dark:bg-stone-950/60 lg:sticky lg:top-8 lg:w-1/3 lg:min-w-0 lg:self-start">
                <flux:heading size="sm" class="text-mc-gold-deep dark:text-amber-100/90">{{ __('Nutrition summary') }}</flux:heading>

                <x-recipe-nutrition-breakdown tone="meal-craft" :nutrition="$n" />

                <flux:separator class="my-4 border-stone-200/80 dark:border-stone-700" />

                <flux:heading size="sm" class="text-mc-gold-deep dark:text-amber-100/90">{{ __('SC highlights') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @php
                        $h = $this->highlights;
                    @endphp
                    @if (($h['folate'] ?? false) === true)
                        <flux:badge color="green" size="sm">{{ __('Folate') }}</flux:badge>
                    @endif
                    @if (($h['b12'] ?? false) === true)
                        <flux:badge color="blue" size="sm">{{ __('B12') }}</flux:badge>
                    @endif
                    @if (($h['magnesium'] ?? false) === true)
                        <flux:badge color="purple" size="sm">{{ __('Magnesium') }}</flux:badge>
                    @endif
                    @if (($h['iron'] ?? false) === true)
                        <flux:badge color="red" size="sm">{{ __('Iron') }}</flux:badge>
                    @endif

                    @if (! (($h['folate'] ?? false) || ($h['b12'] ?? false) || ($h['magnesium'] ?? false) || ($h['iron'] ?? false)))
                        <flux:text class="text-sm text-stone-500 dark:text-stone-400">{{ __('—') }}</flux:text>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <flux:heading size="lg" class="text-stone-800 dark:text-stone-100">{{ __('Meal library') }}</flux:heading>

        <div
            class="flex flex-col gap-3 rounded-2xl border border-mc-gold-border/30 bg-mc-cream/80 px-4 py-3 shadow-sm dark:border-mc-gold/25 dark:bg-stone-900/80"
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-4 lg:gap-y-2">
                <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                    <flux:text class="shrink-0 text-sm font-semibold text-stone-800 dark:text-stone-100">
                        {{ __('Filter by meal type') }}
                    </flux:text>
                    <div class="min-w-0 flex-1 sm:max-w-xs">
                        <flux:select wire:model.live="selectedMealType" class="w-full">
                            <option value="">{{ __('All types') }}</option>
                            @foreach (MealType::dropdownCases() as $typeCase)
                                <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="min-w-0 flex-1 sm:max-w-md">
                        <label class="sr-only" for="meal-library-search">{{ __('Search') }}</label>
                        <div class="relative">
                            <flux:icon
                                icon="magnifying-glass"
                                variant="outline"
                                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400 dark:text-stone-500"
                            />
                            <input
                                id="meal-library-search"
                                type="search"
                                inputmode="search"
                                autocomplete="off"
                                wire:model.live.debounce.300ms="search"
                                placeholder="{{ __('Search meals, ingredients, or base recipes...') }}"
                                class="block w-full rounded-xl border border-mc-gold-border/35 bg-mc-cream/90 py-2 pl-10 pr-3 text-sm text-stone-800 shadow-sm placeholder:text-stone-400 focus:border-mc-gold-border/60 focus:outline-none focus:ring-2 focus:ring-mc-gold-border/30 dark:border-mc-gold/25 dark:bg-stone-950/50 dark:text-stone-100 dark:placeholder:text-stone-500"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    @if ($this->filteredMeals->isNotEmpty())
                        <flux:button
                            type="button"
                            size="sm"
                            variant="subtle"
                            wire:click="toggleLibrarySelectAllVisible"
                            class="border border-mc-gold-border/35 text-stone-800 dark:border-mc-gold/25 dark:text-stone-100"
                        >
                            {{ $this->libraryAllVisibleSelected ? __('Deselect page') : __('Select all on page') }}
                        </flux:button>
                    @endif

                    @if (count($selectedMeals) > 0)
                        <flux:button
                            type="button"
                            size="sm"
                            variant="danger"
                            wire:click="deleteSelectedMeals"
                            wire:confirm="{{ __('Delete the selected meals? This cannot be undone.') }}"
                        >
                            {{ __('Delete selected') }} ({{ count($selectedMeals) }})
                        </flux:button>
                    @endif

                    <div class="flex items-center gap-1 rounded-lg border border-mc-gold-border/30 bg-white/70 p-0.5 dark:border-mc-gold/20 dark:bg-stone-950/50">
                        @if ($libraryViewMode === 'grid')
                            <flux:button
                                type="button"
                                size="sm"
                                variant="primary"
                                wire:click="$set('libraryViewMode', 'grid')"
                                class="!border-transparent !bg-mc-gold !text-white"
                            >
                                {{ __('Grid') }}
                            </flux:button>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="$set('libraryViewMode', 'list')">
                                {{ __('List') }}
                            </flux:button>
                        @else
                            <flux:button type="button" size="sm" variant="ghost" wire:click="$set('libraryViewMode', 'grid')">
                                {{ __('Grid') }}
                            </flux:button>
                            <flux:button
                                type="button"
                                size="sm"
                                variant="primary"
                                wire:click="$set('libraryViewMode', 'list')"
                                class="!border-transparent !bg-mc-gold !text-white"
                            >
                                {{ __('List') }}
                            </flux:button>
                        @endif
                    </div>

                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-transparent bg-brand-secondary px-4 py-2 font-sans text-sm font-semibold text-stone-900 shadow-sm transition hover:bg-brand-primary hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-secondary/80 focus-visible:ring-offset-2 focus-visible:ring-offset-mc-cream dark:text-stone-950 dark:hover:text-white dark:focus-visible:ring-offset-stone-900"
                        @click="window.generateLibraryExportCSV(@js(route('meals.library.export-csv'))).catch(() => alert(@js(__('Export failed. Please try again.'))))"
                    >
                        {{ __('Export CSV') }}
                    </button>
                </div>
            </div>
        </div>

        @if ($libraryViewMode === 'list')
            <div
                class="overflow-x-auto rounded-2xl border border-mc-gold-border/30 bg-mc-cream/80 shadow-sm dark:border-mc-gold/25 dark:bg-stone-900/80"
            >
                @if ($this->filteredMeals->isEmpty())
                    @if (! $this->savedMealsExist)
                        <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <flux:heading size="lg" class="mb-2 text-stone-800 dark:text-stone-100">{{ __('No meals yet') }}</flux:heading>
                            <flux:text class="mb-6 max-w-sm text-stone-600 dark:text-stone-400">
                                {{ __('Use the builder above to create your first meal with full nutrition.') }}
                            </flux:text>
                        </div>
                    @else
                        <div class="p-10 text-center">
                            <flux:heading size="md" class="mb-2">{{ __('No meals found') }}</flux:heading>
                            @if (filled($search))
                                <flux:text class="text-stone-600 dark:text-stone-300">
                                    {{ __('No meals found matching ":term". Try a different keyword or create a new meal above.', ['term' => $search]) }}
                                </flux:text>
                            @else
                                <flux:text class="text-stone-600 dark:text-stone-300">{{ __('No meals match this filter.') }}</flux:text>
                            @endif
                        </div>
                    @endif
                @else
                    <table class="min-w-[640px] w-full divide-y divide-stone-200/80 text-left text-sm dark:divide-stone-700">
                        <thead class="bg-stone-100/80 dark:bg-stone-950/80">
                            <tr>
                                <th scope="col" class="w-12 px-3 py-3">
                                    <span class="sr-only">{{ __('Select') }}</span>
                                </th>
                                <th scope="col" class="px-3 py-3 font-semibold text-stone-800 dark:text-stone-100">
                                    {{ __('Meal name') }}
                                </th>
                                <th scope="col" class="px-3 py-3 font-semibold text-stone-800 dark:text-stone-100">
                                    {{ __('Category') }}
                                </th>
                                <th scope="col" class="px-3 py-3 font-semibold text-stone-800 dark:text-stone-100">
                                    {{ __('Calories') }}
                                </th>
                                <th scope="col" class="w-28 px-3 py-3 text-end font-semibold text-stone-800 dark:text-stone-100">
                                    {{ __('Details') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200/70 dark:divide-stone-700/80">
                            @foreach ($this->filteredMeals as $meal)
                                @php
                                    $rowNut = $meal->nutritionForDisplay();
                                    $rowIsBaseRecipe = $meal->meal_type === \App\Enums\MealType::BaseRecipe;
                                @endphp
                                <tr
                                    wire:key="library-meal-list-{{ $meal->id }}"
                                    @class([
                                        'bg-white/60 hover:bg-mc-gold/5 dark:bg-stone-900/40 dark:hover:bg-mc-gold/10',
                                        'border-s-2 border-stone-400/80 bg-stone-100/70 dark:border-stone-500 dark:bg-stone-900/70' => $rowIsBaseRecipe,
                                    ])
                                >
                                    <td class="px-3 py-3 align-middle">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-stone-300 text-mc-gold-deep focus:ring-mc-gold-border/60 dark:border-stone-600 dark:bg-stone-900 dark:text-amber-300"
                                            wire:model.live="selectedMeals"
                                            value="{{ $meal->id }}"
                                            aria-label="{{ __('Select :name', ['name' => $meal->name]) }}"
                                        />
                                    </td>
                                    <td class="max-w-[12rem] px-3 py-3 align-middle sm:max-w-md">
                                        <button
                                            type="button"
                                            wire:click="openMealDetails({{ $meal->id }})"
                                            class="text-left font-serif text-base font-semibold text-stone-800 underline decoration-mc-gold-border/50 decoration-1 underline-offset-2 transition hover:text-mc-gold-deep dark:text-stone-100 dark:hover:text-amber-200/90"
                                        >
                                            {{ $meal->name }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-3 align-middle whitespace-nowrap">
                                        @if ($rowIsBaseRecipe && $meal->meal_type)
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <flux:badge
                                                    color="zinc"
                                                    size="sm"
                                                    class="border border-stone-400/60 bg-stone-200/90 text-stone-800 dark:border-stone-500 dark:bg-stone-800/90 dark:text-stone-100"
                                                >
                                                    {{ $meal->meal_type->label() }}
                                                </flux:badge>
                                                <flux:badge
                                                    color="zinc"
                                                    size="sm"
                                                    class="border border-stone-400/50 bg-stone-300/50 text-stone-800 dark:border-stone-600 dark:bg-stone-700/70 dark:text-stone-200"
                                                >
                                                    {{ __('Ingredient component') }}
                                                </flux:badge>
                                            </div>
                                        @elseif ($meal->category)
                                            <flux:badge
                                                :color="$meal->category->badgeColor()"
                                                size="sm"
                                                class="border border-mc-gold-border/25 bg-white/80 dark:border-mc-gold/20 dark:bg-stone-900/50"
                                            >
                                                {{ $meal->category->value }}
                                            </flux:badge>
                                        @else
                                            <span class="text-stone-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 align-middle tabular-nums text-stone-800 dark:text-stone-100">
                                        {{ number_format((float) $rowNut['calories'], 0) }}
                                    </td>
                                    <td class="px-3 py-3 align-middle text-end">
                                        <flux:tooltip :content="__('View details')">
                                            <flux:button
                                                type="button"
                                                size="xs"
                                                variant="ghost"
                                                icon="eye"
                                                class="!text-mc-gold-deep dark:!text-amber-200/90"
                                                wire:click="openMealDetails({{ $meal->id }})"
                                            />
                                        </flux:tooltip>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                @forelse ($this->filteredMeals as $meal)
                    <x-meal-card :meal="$meal" compact show-bulk-checkbox wire:key="library-meal-{{ $meal->id }}" />
                @empty
                    @if (! $this->savedMealsExist)
                        <div class="col-span-full flex flex-col items-center justify-center rounded-2xl border border-dashed border-mc-gold-border/40 bg-mc-cream/50 px-6 py-16 text-center dark:border-mc-gold/30 dark:bg-stone-900/40">
                            <flux:heading size="lg" class="mb-2 text-stone-800 dark:text-stone-100">{{ __('No meals yet') }}</flux:heading>
                            <flux:text class="mb-6 max-w-sm text-stone-600 dark:text-stone-400">
                                {{ __('Use the builder above to create your first meal with full nutrition.') }}
                            </flux:text>
                        </div>
                    @else
                        <div class="col-span-full rounded-xl border border-dashed border-mc-gold-border/35 bg-white/80 p-10 text-center dark:bg-stone-900/80">
                            <flux:heading size="md" class="mb-2">{{ __('No meals found') }}</flux:heading>
                            @if (filled($search))
                                <flux:text class="text-stone-600 dark:text-stone-300">
                                    {{ __('No meals found matching ":term". Try a different keyword or create a new meal above.', ['term' => $search]) }}
                                </flux:text>
                            @else
                                <flux:text class="text-stone-600 dark:text-stone-300">{{ __('No meals match this filter.') }}</flux:text>
                            @endif
                        </div>
                    @endif
                @endforelse

                @if ($this->filteredMeals->hasPages())
                    <div class="col-span-full border-t border-stone-200/80 pt-4 md:col-span-2 lg:col-span-4 dark:border-stone-700">
                        {{ $this->filteredMeals->links() }}
                    </div>
                @endif
            </div>
        @endif

        @if ($libraryViewMode === 'list' && $this->filteredMeals->hasPages())
            <div class="border-t border-stone-200/80 pt-4 dark:border-stone-700">
                {{ $this->filteredMeals->links() }}
            </div>
        @endif
    </div>

    <flux:modal
        wire:model.self="showMealDetailsModal"
        class="max-h-[90vh] max-w-3xl overflow-y-auto border border-mc-gold-border/35 !bg-mc-cream dark:border-mc-gold/30 dark:!bg-stone-900"
    >
        @if ($this->detailsMeal)
            <div class="space-y-0">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-mc-gold-border/25 pb-4 dark:border-mc-gold/20">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-serif text-2xl font-semibold leading-tight text-stone-800 dark:text-stone-100">
                            {{ $this->detailsMeal->name }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @if ($this->detailsMeal->meal_type === \App\Enums\MealType::BaseRecipe)
                                <flux:badge
                                    color="zinc"
                                    size="sm"
                                    class="border border-stone-400/60 bg-stone-200/90 text-stone-800 dark:border-stone-500 dark:bg-stone-800/90 dark:text-stone-100"
                                >
                                    {{ $this->detailsMeal->meal_type->label() }}
                                </flux:badge>
                                <flux:badge
                                    color="zinc"
                                    size="sm"
                                    class="border border-stone-400/50 bg-stone-300/50 text-stone-800 dark:border-stone-600 dark:bg-stone-700/70 dark:text-stone-200"
                                >
                                    {{ __('Ingredient component') }}
                                </flux:badge>
                            @elseif ($this->detailsMeal->meal_type)
                                <flux:badge color="zinc" size="sm">
                                    {{ $this->detailsMeal->meal_type->label() }}
                                </flux:badge>
                            @endif
                            @if ($this->detailsMeal->category)
                                <flux:badge :color="$this->detailsMeal->category->badgeColor()" size="sm">
                                    {{ $this->detailsMeal->category->value }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                    <flux:modal.close>
                        <flux:button variant="ghost" size="sm" class="text-stone-600 hover:bg-mc-gold/10 hover:text-mc-gold-deep dark:text-stone-300 dark:hover:bg-mc-gold/15">
                            {{ __('Close') }}
                        </flux:button>
                    </flux:modal.close>
                </div>

                <div class="space-y-6 pt-6">
                    <div>
                        <h3 class="mb-2 border-b border-mc-gold-border/20 pb-1.5 font-sans text-sm font-semibold uppercase tracking-wide text-mc-gold dark:border-mc-gold/25 dark:text-amber-200/90">
                            {{ __('Ingredients') }}
                        </h3>
                        @if ($this->detailsMeal->ingredients->isEmpty())
                            <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('No ingredients linked.') }}</p>
                        @else
                            <ul class="space-y-1.5 text-sm text-stone-700 dark:text-stone-300">
                                @foreach ($this->detailsMeal->ingredients as $ingredient)
                                    @php
                                        $p = $ingredient->pivot;
                                        $amt = $p->amount;
                                        $hasDisplay = $amt !== null && $amt !== '' && (float) $amt > 0 && filled($p->unit ?? null);
                                        $line = $hasDisplay
                                            ? rtrim(rtrim(number_format((float) $amt, 2, '.', ''), '0'), '.').' '.$p->unit
                                            : rtrim(rtrim(number_format((float) $p->amount_grams, 2, '.', ''), '0'), '.').'g';
                                    @endphp
                                    <li>{{ $ingredient->name }} — {{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="border-t border-stone-200/80 pt-6 dark:border-stone-700/80">
                        <h3 class="mb-2 border-b border-mc-gold-border/20 pb-1.5 font-sans text-sm font-semibold uppercase tracking-wide text-mc-gold dark:border-mc-gold/25 dark:text-amber-200/90">
                            {{ __('Instructions') }}
                        </h3>
                        @if (filled($this->detailsMeal->description))
                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-stone-700 dark:text-stone-300">{{ $this->detailsMeal->description }}</p>
                        @else
                            <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('No instructions provided.') }}</p>
                        @endif
                    </div>

                    <div class="border-t border-stone-200/80 pt-6 dark:border-stone-700/80">
                        <h3 class="mb-3 border-b border-mc-gold-border/20 pb-1.5 font-sans text-sm font-semibold uppercase tracking-wide text-mc-gold dark:border-mc-gold/25 dark:text-amber-200/90">
                            {{ __('Nutrition summary') }}
                        </h3>
                        <x-recipe-nutrition-breakdown tone="meal-craft" :nutrition="$this->detailsMealNutrition" />
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
