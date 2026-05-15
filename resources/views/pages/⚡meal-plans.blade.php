<?php

use App\Enums\MealCyclePhaseTag;
use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\MealType;
use App\Enums\RecipeAmountUnit;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\MealCyclePhaseTaggingService;
use App\Services\MealPlanService;
use App\Services\RecipeIngredientUnitConverter;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealPlanSlotBasedDayNutrition;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Meal Plans')] class extends Component {
    public string $name = '';

    public string $goal = '';

    /** @var value-of<MealPlanLibraryCategory> */
    public string $planCategory = 'balanced';

    /** @var value-of<MealCyclePhaseTag>|'' */
    public string $cyclePhase = '';

    public string $targetDailyCalories = '';

    public string $targetDailyProteinG = '';

    public string $targetDailyCarbsG = '';

    public string $targetDailyFatG = '';

    public ?string $status = null;

    public bool $showDetailsModal = false;

    public ?int $detailsPlanId = null;

    /** 1 = Monday … 7 = Sunday */
    public int $detailsDay = 1;

    public bool $detailsOptionB = false;

    public bool $showEditModal = false;

    public int $editDay = 1;

    public string $editSlotType = '';

    public int $editSlotIndex = 1;

    public bool $editOptionB = false;

    public string $editReplacementMealId = '';

    public string $editMealSearch = '';

    public ?int $inlineEditDayMealId = null;

    /**
     * @var list<array{ingredient_id: int|null, amount: float, unit: string}>
     */
    public array $inlineEditRows = [];

    /** @var array<int, string> */
    public array $inlineEditIngredientSearch = [];

    public bool $showCreateNewMealModal = false;

    public string $createNewMealName = '';

    /**
     * @var array<int, string>
     */
    public array $weekdayShortLabels = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    public function createMealPlan(MealPlanService $mealPlanService): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['required', 'string', 'max:5000'],
            'planCategory' => ['required', Rule::enum(MealPlanLibraryCategory::class)],
            'targetDailyCalories' => ['required', 'numeric', 'min:1'],
            'targetDailyProteinG' => ['nullable', 'numeric', 'min:0'],
            'targetDailyCarbsG' => ['nullable', 'numeric', 'min:0'],
            'targetDailyFatG' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->planCategory === MealPlanLibraryCategory::CycleSync->value) {
            $rules['cyclePhase'] = ['required', Rule::enum(MealCyclePhaseTag::class)];
        } else {
            $rules['cyclePhase'] = ['nullable', 'string'];
        }

        $validated = $this->validate($rules);

        if (! app()->environment('testing')) {
            sleep(3);
        }

        $category = MealPlanLibraryCategory::from($validated['planCategory']);
        $phase = $category === MealPlanLibraryCategory::CycleSync
            ? MealCyclePhaseTag::from((string) $validated['cyclePhase'])
            : null;

        $mealPlan = MealPlan::query()->create([
            'name' => $validated['name'],
            'goal' => $validated['goal'],
            'schema_type' => MealPlanSchemaType::WeeklyStructured,
            'plan_category' => $category,
            'cycle_phase' => $phase,
        ]);

        $dailyCal = (float) $validated['targetDailyCalories'];
        $dailyP = $validated['targetDailyProteinG'] !== '' && $validated['targetDailyProteinG'] !== null
            ? (float) $validated['targetDailyProteinG']
            : null;
        $dailyC = $validated['targetDailyCarbsG'] !== '' && $validated['targetDailyCarbsG'] !== null
            ? (float) $validated['targetDailyCarbsG']
            : null;
        $dailyF = $validated['targetDailyFatG'] !== '' && $validated['targetDailyFatG'] !== null
            ? (float) $validated['targetDailyFatG']
            : null;

        $mealPlanService->syncMacroTargets(
            $mealPlan,
            $dailyCal * 7.0,
            $dailyP !== null ? $dailyP * 7.0 : null,
            $dailyC !== null ? $dailyC * 7.0 : null,
            $dailyF !== null ? $dailyF * 7.0 : null,
        );

        $result = $mealPlanService->autoFillWeeklyStructuredPlan($mealPlan->fresh());

        if (! $result->ok) {
            $mealPlan->delete();
            $this->addError('createPlan', $result->message ?? __('Could not build this plan from your library.'));

            return;
        }

        $this->reset('name', 'goal', 'targetDailyCalories', 'targetDailyProteinG', 'targetDailyCarbsG', 'targetDailyFatG');
        $this->planCategory = MealPlanLibraryCategory::Balanced->value;
        $this->cyclePhase = '';
        $this->resetErrorBag();
        $this->status = $result->message;
        $this->detailsPlanId = $mealPlan->id;
        $this->detailsDay = 1;
        $this->detailsOptionB = false;
        $this->resetPlanDetailsEditors();
        $this->showDetailsModal = true;
    }

    public function openPlanDetails(int $planId): void
    {
        $plan = MealPlan::query()->where('schema_type', MealPlanSchemaType::WeeklyStructured)->findOrFail($planId);
        $this->detailsPlanId = $plan->id;
        $this->detailsDay = 1;
        $this->detailsOptionB = false;
        $this->resetPlanDetailsEditors();
        $this->showDetailsModal = true;
    }

    public function closePlanDetails(): void
    {
        $this->showDetailsModal = false;
        $this->resetPlanDetailsEditors();
    }

    public function updatedShowDetailsModal(bool $value): void
    {
        if (! $value) {
            $this->resetPlanDetailsEditors();
        }
    }

    private function resetPlanDetailsEditors(): void
    {
        $this->inlineEditDayMealId = null;
        $this->inlineEditRows = [];
        $this->inlineEditIngredientSearch = [];
        $this->showCreateNewMealModal = false;
        $this->createNewMealName = '';
        $this->resetErrorBag(['inlineEditRows', 'inlineEditRows.*', 'createNewMealName']);
    }

    public function setDetailsDay(int $day): void
    {
        if ($day >= 1 && $day <= 7) {
            if ($day !== $this->detailsDay) {
                $this->resetPlanDetailsEditors();
            }
            $this->detailsDay = $day;
        }
    }

    public function updatedDetailsOptionB(bool $value): void
    {
        unset($value);
        $this->resetPlanDetailsEditors();
    }

    public function openEditSlot(int $day, string $slotTypeValue, int $slotIndex, bool $optionB, int $currentMealId): void
    {
        $this->editDay = $day;
        $this->editSlotType = $slotTypeValue;
        $this->editSlotIndex = $slotIndex;
        $this->editOptionB = $optionB;
        $this->editReplacementMealId = (string) $currentMealId;
        $this->editMealSearch = '';
        $this->resetErrorBag('editReplacementMealId');
        $this->showEditModal = true;
    }

    public function saveSlotReplacement(MealPlanService $mealPlanService): void
    {
        if ($this->detailsPlanId === null) {
            return;
        }

        $this->validate([
            'editReplacementMealId' => ['required', 'exists:meals,id'],
        ]);

        $plan = MealPlan::query()->findOrFail($this->detailsPlanId);
        $slotType = MealPlanSlotType::from($this->editSlotType);

        try {
            $mealPlanService->updateSlotMeal(
                $plan,
                $this->editDay,
                $slotType,
                $this->editSlotIndex,
                $this->editOptionB,
                (int) $this->editReplacementMealId,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('editReplacementMealId', $e->getMessage());

            return;
        }

        $this->showEditModal = false;
        $this->editMealSearch = '';
        $this->status = __('Meal slot updated.');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editMealSearch = '';
    }

    /**
     * @return array<string, float>
     */
    public function getInlineEditPreviewNutritionProperty(): array
    {
        if ($this->inlineEditDayMealId === null) {
            return [];
        }

        return RecipeNutritionCalculator::fromRows($this->inlineEditRows);
    }

    public function toggleInlineIngredientEditor(int $dayMealId): void
    {
        if ($this->inlineEditDayMealId === $dayMealId) {
            $this->closeInlineIngredientEditor();

            return;
        }

        $this->openInlineIngredientEditor($dayMealId);
    }

    public function openInlineIngredientEditor(int $dayMealId): void
    {
        if ($this->detailsPlanId === null) {
            return;
        }

        $row = MealPlanDayMeal::query()
            ->whereKey($dayMealId)
            ->where('meal_plan_id', $this->detailsPlanId)
            ->with(['meal.ingredients'])
            ->first();

        if ($row === null || $row->meal === null) {
            return;
        }

        $this->showCreateNewMealModal = false;
        $this->inlineEditDayMealId = $row->id;
        $meal = $row->meal;
        $meal->loadMissing('ingredients');

        $this->inlineEditRows = $meal->ingredients->map(function (Ingredient $ingredient): array {
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
                'amount' => $displayAmount > 0 ? $displayAmount : 100.0,
                'unit' => $unit,
            ];
        })->values()->all();

        if ($this->inlineEditRows === []) {
            $this->inlineEditRows = [$this->emptyIngredientRow()];
        }

        $this->inlineEditIngredientSearch = collect($this->inlineEditRows)
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

        $this->resetErrorBag(['inlineEditRows', 'inlineEditRows.*', 'createNewMealName']);
    }

    public function closeInlineIngredientEditor(): void
    {
        $this->inlineEditDayMealId = null;
        $this->inlineEditRows = [];
        $this->inlineEditIngredientSearch = [];
        $this->showCreateNewMealModal = false;
        $this->createNewMealName = '';
        $this->resetErrorBag(['inlineEditRows', 'inlineEditRows.*', 'createNewMealName']);
    }

    public function addInlineEditRow(): void
    {
        $this->inlineEditRows[] = $this->emptyIngredientRow();
        $this->inlineEditIngredientSearch[] = '';
    }

    public function removeInlineEditRow(int $index): void
    {
        if (! isset($this->inlineEditRows[$index])) {
            return;
        }

        array_splice($this->inlineEditRows, $index, 1);
        if (isset($this->inlineEditIngredientSearch[$index])) {
            array_splice($this->inlineEditIngredientSearch, $index, 1);
        }

        if ($this->inlineEditRows === []) {
            $this->inlineEditRows = [$this->emptyIngredientRow()];
            $this->inlineEditIngredientSearch = [''];
        }
    }

    /**
     * @return Collection<int, Ingredient>
     */
    public function inlineIngredientSearchResults(int $index): Collection
    {
        $term = trim((string) ($this->inlineEditIngredientSearch[$index] ?? ''));
        $query = Ingredient::query()->orderBy('name', 'asc');

        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        return $query->limit(25)->get(['id', 'name', 'density', 'source_meal_id']);
    }

    public function chooseInlineIngredient(int $index, int $ingredientId): void
    {
        if (! isset($this->inlineEditRows[$index])) {
            return;
        }

        $name = Ingredient::query()->whereKey($ingredientId)->value('name');

        if ($name === null) {
            return;
        }

        $this->inlineEditRows[$index]['ingredient_id'] = $ingredientId;
        $this->inlineEditIngredientSearch[$index] = (string) $name;
    }

    public function saveInlineIngredientsUpdateOriginal(): void
    {
        if ($this->inlineEditDayMealId === null || $this->detailsPlanId === null) {
            return;
        }

        $this->validate([
            'inlineEditRows' => ['required', 'array', 'min:1'],
            'inlineEditRows.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'inlineEditRows.*.amount' => ['required', 'numeric', 'min:0'],
            'inlineEditRows.*.unit' => ['required', 'string', Rule::enum(RecipeAmountUnit::class)],
        ]);

        $row = MealPlanDayMeal::query()
            ->whereKey($this->inlineEditDayMealId)
            ->where('meal_plan_id', $this->detailsPlanId)
            ->with('meal')
            ->first();

        if ($row === null || $row->meal === null) {
            return;
        }

        try {
            $this->applyIngredientRowsToMeal($row->meal, $this->inlineEditRows, 'inlineEditRows');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        app(MealCyclePhaseTaggingService::class)->refreshAutoTagsForEntireLibrary();
        $this->status = __('Meal updated.');
        $this->closeInlineIngredientEditor();
    }

    public function openCreateNewMealModal(): void
    {
        if ($this->inlineEditDayMealId === null || $this->detailsPlanId === null) {
            return;
        }

        $row = MealPlanDayMeal::query()
            ->whereKey($this->inlineEditDayMealId)
            ->where('meal_plan_id', $this->detailsPlanId)
            ->with('meal')
            ->first();

        if ($row === null || $row->meal === null) {
            return;
        }

        $base = $row->meal->name.' - Copy';
        $this->createNewMealName = strlen($base) <= 255 ? $base : substr($base, 0, 252).'…';
        $this->showCreateNewMealModal = true;
        $this->resetErrorBag(['createNewMealName']);
    }

    public function closeCreateNewMealModal(): void
    {
        $this->showCreateNewMealModal = false;
        $this->createNewMealName = '';
        $this->resetErrorBag(['createNewMealName']);
    }

    public function confirmCreateNewMeal(): void
    {
        if ($this->inlineEditDayMealId === null || $this->detailsPlanId === null) {
            return;
        }

        $this->validate([
            'createNewMealName' => ['required', 'string', 'max:255'],
            'inlineEditRows' => ['required', 'array', 'min:1'],
            'inlineEditRows.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'inlineEditRows.*.amount' => ['required', 'numeric', 'min:0'],
            'inlineEditRows.*.unit' => ['required', 'string', Rule::enum(RecipeAmountUnit::class)],
        ]);

        $row = MealPlanDayMeal::query()
            ->whereKey($this->inlineEditDayMealId)
            ->where('meal_plan_id', $this->detailsPlanId)
            ->with('meal')
            ->first();

        if ($row === null || $row->meal === null) {
            return;
        }

        $original = $row->meal;
        $newMeal = $original->replicate();
        $newMeal->name = (string) $this->createNewMealName;
        $newMeal->save();

        try {
            $this->applyIngredientRowsToMeal($newMeal, $this->inlineEditRows, 'inlineEditRows');
        } catch (ValidationException $e) {
            $newMeal->delete();
            $this->setErrorBag($e->errors());

            return;
        }

        $row->update(['meal_id' => $newMeal->id]);

        app(MealCyclePhaseTaggingService::class)->refreshAutoTagsForEntireLibrary();
        $this->showCreateNewMealModal = false;
        $this->createNewMealName = '';
        $this->status = __('New meal added to your library and assigned to this slot.');
        $this->closeInlineIngredientEditor();
    }

    /**
     * @return array{ingredient_id: int|null, amount: float, unit: string}
     */
    private function emptyIngredientRow(): array
    {
        return [
            'ingredient_id' => null,
            'amount' => 100.0,
            'unit' => RecipeAmountUnit::Grams->value,
        ];
    }

    /**
     * @param  list<array{ingredient_id: int|null, amount: float|string, unit: string}>  $rows
     *
     * @throws ValidationException
     */
    private function applyIngredientRowsToMeal(Meal $meal, array $rows, string $errorKey = 'inlineEditRows'): void
    {
        $sync = $this->buildIngredientSyncPayload($rows);
        if ($sync === []) {
            throw ValidationException::withMessages([
                $errorKey => __('Add at least one ingredient with a positive amount.'),
            ]);
        }

        $nutrition = RecipeNutritionCalculator::fromRows($rows);
        $meal->update(Meal::nutritionSummaryToPersistedAttributes($nutrition));
        $meal->ingredients()->sync($sync);
    }

    /**
     * @param  list<array{ingredient_id: int|null, amount: float|string, unit: string}>  $rows
     * @return array<int, array{amount: float, unit: string, amount_grams: float}>
     */
    private function buildIngredientSyncPayload(array $rows): array
    {
        $sync = [];

        foreach ($rows as $row) {
            $ingredientIdRaw = $row['ingredient_id'] ?? null;
            $ingredientId = is_numeric($ingredientIdRaw) ? (int) $ingredientIdRaw : 0;
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0.0;
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

    /**
     * @return Collection<int, MealPlan>
     */
    public function getMealPlansProperty(): Collection
    {
        return MealPlan::query()
            ->where('schema_type', MealPlanSchemaType::WeeklyStructured)
            ->latest()
            ->get();
    }

    /**
     * @return array<int, array{a: array<string, MealPlanDayMeal>, b: array<string, MealPlanDayMeal>}>
     */
    public function getDetailsWeekGridProperty(): array
    {
        if ($this->detailsPlanId === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $this->detailsPlanId)
            ->with(['meal'])
            ->get();

        $grid = [];
        foreach (range(1, 7) as $d) {
            $grid[$d] = ['a' => [], 'b' => []];
        }

        foreach ($rows as $row) {
            $slotType = $row->slot_type;
            if (! $slotType instanceof MealPlanSlotType) {
                continue;
            }
            $key = $slotType->value.'_'.$row->slot_index;
            $opt = $row->is_option_b ? 'b' : 'a';
            $grid[$row->day_number][$opt][$key] = $row;
        }

        return $grid;
    }

    /**
     * @return Collection<int, Meal>
     */
    public function getEditSlotMealsProperty(): Collection
    {
        $type = MealPlanSlotType::tryFrom($this->editSlotType);
        if ($type === null) {
            return collect();
        }

        $query = Meal::query()
            ->where('meal_type', $type->mealType()->value)
            ->where('meal_type', '!=', MealType::BaseRecipe->value)
            ->where('category', '!=', RecipeCategory::BaseRecipe)
            ->orderBy('name');

        $term = trim($this->editMealSearch);
        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @return array<string, ?Meal>
     */
    private function currentDaySlotMealMap(): array
    {
        if ($this->detailsPlanId === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $this->detailsPlanId)
            ->where('day_number', $this->detailsDay)
            ->where('is_option_b', $this->detailsOptionB)
            ->with(['meal'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $type = $row->slot_type;
            if (! $type instanceof MealPlanSlotType) {
                continue;
            }
            $map[$type->value.'_'.$row->slot_index] = $row->meal;
        }

        return $map;
    }

    private function mealWithPreviewTotals(Meal $template, array $calculatorNutrition): Meal
    {
        $keep = array_intersect_key($template->getAttributes(), array_flip([
            'id', 'name', 'category', 'meal_type', 'description', 'image_path', 'health_score',
        ]));
        $m = new Meal;
        $m->forceFill(array_merge(
            $keep,
            Meal::nutritionSummaryToPersistedAttributes($calculatorNutrition)
        ));

        return $m;
    }

    /**
     * @return array<string, ?Meal>
     */
    private function currentDaySlotMealMapWithInlinePreview(): array
    {
        $map = $this->currentDaySlotMealMap();

        if ($this->inlineEditDayMealId === null || $this->detailsPlanId === null) {
            return $map;
        }

        $row = MealPlanDayMeal::query()
            ->whereKey($this->inlineEditDayMealId)
            ->where('meal_plan_id', $this->detailsPlanId)
            ->where('day_number', $this->detailsDay)
            ->where('is_option_b', $this->detailsOptionB)
            ->with('meal')
            ->first();

        if ($row === null || $row->meal === null || ! $row->slot_type instanceof MealPlanSlotType) {
            return $map;
        }

        $key = $row->slot_type->value.'_'.$row->slot_index;
        $preview = RecipeNutritionCalculator::fromRows($this->inlineEditRows);
        $map[$key] = $this->mealWithPreviewTotals($row->meal, $preview);

        return $map;
    }

    /**
     * @param  list<array{ingredient_id: int|null, amount: float|string, unit: string}>  $rows
     * @return array<int, array{name: string, grams: float}>
     */
    private function gramsContributionsFromIngredientRows(array $rows): array
    {
        $byId = [];

        foreach ($rows as $row) {
            $ingredientId = isset($row['ingredient_id']) && is_numeric($row['ingredient_id']) ? (int) $row['ingredient_id'] : 0;
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0.0;
            $unit = (string) ($row['unit'] ?? RecipeAmountUnit::Grams->value);

            if ($ingredientId <= 0 || $amount <= 0) {
                continue;
            }

            $ingredient = Ingredient::query()->find($ingredientId, ['id', 'name', 'density']);

            if ($ingredient === null) {
                continue;
            }

            $density = (float) ($ingredient->density ?? 1.0);
            $grams = RecipeIngredientUnitConverter::toGrams($amount, $unit, $density);

            if ($grams <= 0) {
                continue;
            }

            if (! isset($byId[$ingredientId])) {
                $byId[$ingredientId] = ['name' => $ingredient->name, 'grams' => 0.0];
            }

            $byId[$ingredientId]['grams'] += $grams;
        }

        return $byId;
    }

    /**
     * @return callable(string, int): (?Meal)
     */
    private function detailsDayMealResolver(): callable
    {
        if ($this->detailsPlanId === null) {
            return static fn (string $slotTypeValue, int $index): ?Meal => null;
        }

        $map = $this->currentDaySlotMealMapWithInlinePreview();

        return static function (string $slotTypeValue, int $index) use ($map): ?Meal {
            return $map[$slotTypeValue.'_'.$index] ?? null;
        };
    }

    /**
     * Full day macros: 1,200 kcal core model + optional soup slot.
     *
     * @return array<string, float>
     */
    public function getDetailsDayNutritionTotalsProperty(): array
    {
        if ($this->detailsPlanId === null) {
            return [];
        }

        return MealPlanSlotBasedDayNutrition::fullDayNutrition($this->detailsDayMealResolver());
    }

    /**
     * Core budget only (breakfast + 2× mains + salads + desserts); soup excluded.
     *
     * @return array<string, float>
     */
    public function getDetailsDayCoreNutritionTotalsProperty(): array
    {
        if ($this->detailsPlanId === null) {
            return [];
        }

        return MealPlanSlotBasedDayNutrition::coreBudgetNutrition($this->detailsDayMealResolver());
    }

    /**
     * Soup slot only.
     *
     * @return array<string, float>
     */
    public function getDetailsDaySoupNutritionTotalsProperty(): array
    {
        if ($this->detailsPlanId === null) {
            return [];
        }

        return MealPlanSlotBasedDayNutrition::soupSlotNutrition($this->detailsDayMealResolver());
    }

    /**
     * Admin validation — low/high calorie paths for the core budget only (soup excluded).
     *
     * @return array{min: float, max: float}
     */
    public function getDetailsDayMenuPathKcalRangeProperty(): array
    {
        if ($this->detailsPlanId === null) {
            return ['min' => 0.0, 'max' => 0.0];
        }

        return MealPlanSlotBasedDayNutrition::adminCorePathCalorieRange($this->detailsDayMealResolver());
    }

    public function getDetailsDayAdminCoreHighPathOverBudgetProperty(): bool
    {
        if ($this->detailsPlanId === null) {
            return false;
        }

        $max = $this->detailsDayMenuPathKcalRange['max'];

        return $max > MealPlanSlotBasedDayNutrition::CORE_HIGH_PATH_WARNING_KCAL;
    }

    /**
     * @return list<array{name: string, grams: float}>
     */
    public function getDetailsDayShoppingListProperty(): array
    {
        if ($this->detailsPlanId === null) {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $this->detailsPlanId)
            ->where('day_number', $this->detailsDay)
            ->where('is_option_b', $this->detailsOptionB)
            ->with(['meal.ingredients'])
            ->get();

        /** @var array<int, array{name: string, grams: float}> $byId */
        $byId = [];

        foreach ($rows as $row) {
            $meal = $row->meal;
            if ($meal === null) {
                continue;
            }

            if ($this->inlineEditDayMealId === $row->id) {
                foreach ($this->gramsContributionsFromIngredientRows($this->inlineEditRows) as $id => $data) {
                    if (! isset($byId[$id])) {
                        $byId[$id] = ['name' => $data['name'], 'grams' => 0.0];
                    }
                    $byId[$id]['grams'] += $data['grams'];
                }

                continue;
            }

            $meal->loadMissing('ingredients');

            foreach ($meal->ingredients as $ingredient) {
                $g = (float) ($ingredient->pivot->amount_grams ?? 0);
                if ($g <= 0.0) {
                    continue;
                }
                $id = (int) $ingredient->id;
                if (! isset($byId[$id])) {
                    $byId[$id] = ['name' => $ingredient->name, 'grams' => 0.0];
                }
                $byId[$id]['grams'] += $g;
            }
        }

        $list = array_values($byId);
        usort($list, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    public function exportCsv()
    {
        $filename = 'meal-plans-'.Carbon::now()->format('Ymd_His').'.csv';

        $weekLabels = $this->weekdayShortLabels;

        return response()->streamDownload(function () use ($weekLabels): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Plan Name', 'Goal', 'Schema', 'Day', 'Slot', 'Option', 'Meal'], ',', '"', '\\');

            MealPlan::query()
                ->with(['meals', 'dayMeals.meal'])
                ->orderBy('name')
                ->each(function (MealPlan $mealPlan) use ($handle, $weekLabels): void {
                    if ($mealPlan->usesStructuredDaySlots()) {
                        $dayRows = $mealPlan->dayMeals;
                        if ($dayRows->isEmpty()) {
                            fputcsv($handle, [
                                $mealPlan->name,
                                $mealPlan->goal,
                                $mealPlan->schema_type->value,
                                '',
                                '',
                                '',
                                '',
                            ], ',', '"', '\\');

                            return;
                        }

                        foreach ($dayRows->sortBy(['day_number', 'slot_type', 'slot_index', 'is_option_b']) as $row) {
                            $slot = $row->slot_type instanceof MealPlanSlotType
                                ? $row->slot_type->value.'#'.$row->slot_index
                                : (string) $row->slot_type;
                            $dayLabel = $mealPlan->schema_type === MealPlanSchemaType::WeeklyStructured
                                ? ($weekLabels[$row->day_number] ?? (string) $row->day_number)
                                : 'Day '.$row->day_number;
                            fputcsv($handle, [
                                $mealPlan->name,
                                $mealPlan->goal,
                                $mealPlan->schema_type->value,
                                $dayLabel,
                                $slot,
                                $row->is_option_b ? 'B' : 'A',
                                $row->meal?->name ?? '',
                            ], ',', '"', '\\');
                        }

                        return;
                    }

                    if ($mealPlan->meals->isEmpty()) {
                        fputcsv($handle, [$mealPlan->name, $mealPlan->goal, $mealPlan->schema_type->value, '', '', '', ''], ',', '"', '\\');

                        return;
                    }

                    foreach ($mealPlan->meals as $meal) {
                        fputcsv($handle, [
                            $mealPlan->name,
                            $mealPlan->goal,
                            $mealPlan->schema_type->value,
                            $meal->pivot->day_of_week,
                            $meal->pivot->meal_type,
                            '',
                            $meal->name,
                        ], ',', '"', '\\');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<section class="relative w-full space-y-8">
    <div
        wire:loading.delay.longest
        wire:target="createMealPlan"
        class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-white/85 backdrop-blur-sm dark:bg-neutral-950/80"
        aria-live="polite"
        aria-busy="true"
    >
        <div
            class="h-14 w-14 animate-spin rounded-full border-4 border-neutral-200 border-t-brand-primary dark:border-neutral-700 dark:border-t-amber-400"
            role="status"
        ></div>
        <flux:heading size="lg" class="text-center text-neutral-800 dark:text-neutral-100">
            {{ __('Crafting your personalized plan…') }}
        </flux:heading>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Meal plan management') }}</flux:heading>
            <flux:text class="text-neutral-600 dark:text-neutral-300">
                {{ __('Create a weekly structured plan, then explore each day, shopping list, and nutrition summaries.') }}
            </flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge>{{ __('7-day structured') }}</flux:badge>
            <flux:button :href="route('meal-plans.four-week')" variant="ghost" size="sm" wire:navigate>
                {{ __('4-week plan creator') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <flux:heading size="lg" class="mb-4">{{ __('Plan creator') }}</flux:heading>
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('Plan name')" />
            <flux:select wire:model.live="planCategory" :label="__('Plan category')">
                @foreach (MealPlanLibraryCategory::cases() as $cat)
                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="goal" :label="__('Plan description & goal')" rows="3" class="md:col-span-2" />
            @if ($planCategory === \App\Enums\MealPlanLibraryCategory::CycleSync->value)
                <flux:select wire:model="cyclePhase" :label="__('Cycle phase')" class="md:col-span-2">
                    <option value="">{{ __('Select phase') }}</option>
                    @foreach (MealCyclePhaseTag::cases() as $phase)
                        <option value="{{ $phase->value }}">{{ $phase->label() }}</option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input type="number" step="0.01" wire:model="targetDailyCalories" :label="__('Target calories (per day)')" />
            <flux:input type="number" step="0.01" wire:model="targetDailyProteinG" :label="__('Target protein g (per day, optional)')" />
            <flux:input type="number" step="0.01" wire:model="targetDailyCarbsG" :label="__('Target carbs g (per day, optional)')" />
            <flux:input type="number" step="0.01" wire:model="targetDailyFatG" :label="__('Target fat g (per day, optional)')" />
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <flux:button variant="primary" wire:click="createMealPlan" wire:loading.attr="disabled" wire:target="createMealPlan">
                <span wire:loading.remove wire:target="createMealPlan">{{ __('Create meal plan') }}</span>
                <span wire:loading wire:target="createMealPlan">{{ __('Working…') }}</span>
            </flux:button>
            <flux:button wire:click="exportCsv" variant="subtle">{{ __('Export CSV') }}</flux:button>
            @if ($status)
                <flux:text class="font-medium !text-green-700 !dark:text-green-400">{{ $status }}</flux:text>
            @endif
        </div>
        @error('createPlan')
            <flux:text class="mt-2 font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </div>

    <div>
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <flux:heading size="lg">{{ __('Meal plan library') }}</flux:heading>
            <flux:text class="text-sm text-neutral-500">{{ __('Newest plans appear first.') }}</flux:text>
        </div>

        @if ($this->mealPlans->isEmpty())
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-600">
                <flux:text>{{ __('No structured plans yet. Create one above to see it here.') }}</flux:text>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->mealPlans as $plan)
                    <article
                        wire:key="meal-plan-card-{{ $plan->id }}"
                        class="flex flex-col rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900"
                    >
                        <flux:heading size="md" class="line-clamp-2">{{ $plan->name }}</flux:heading>
                        @if ($plan->plan_category instanceof \App\Enums\MealPlanLibraryCategory)
                            <flux:badge class="mt-2 w-fit" size="sm">{{ $plan->plan_category->label() }}</flux:badge>
                        @endif
                        @if ($plan->plan_category === \App\Enums\MealPlanLibraryCategory::CycleSync && $plan->cycle_phase instanceof \App\Enums\MealCyclePhaseTag)
                            <flux:text class="mt-1 text-xs text-neutral-500">{{ $plan->cycle_phase->label() }}</flux:text>
                        @endif
                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-lg bg-neutral-50 px-2 py-1.5 dark:bg-neutral-800">
                                <span class="text-neutral-500">{{ __('Cal') }}</span>
                                <div class="font-semibold tabular-nums">
                                    {{ $plan->target_total_calories !== null ? number_format((float) $plan->target_total_calories / 7.0, 0) : '—' }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-neutral-50 px-2 py-1.5 dark:bg-neutral-800">
                                <span class="text-neutral-500">{{ __('Pro') }}</span>
                                <div class="font-semibold tabular-nums">
                                    {{ $plan->target_total_protein_g !== null ? number_format((float) $plan->target_total_protein_g / 7.0, 0).'g' : '—' }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-neutral-50 px-2 py-1.5 dark:bg-neutral-800">
                                <span class="text-neutral-500">{{ __('Carb') }}</span>
                                <div class="font-semibold tabular-nums">
                                    {{ $plan->target_total_carbs_g !== null ? number_format((float) $plan->target_total_carbs_g / 7.0, 0).'g' : '—' }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-neutral-50 px-2 py-1.5 dark:bg-neutral-800">
                                <span class="text-neutral-500">{{ __('Fat') }}</span>
                                <div class="font-semibold tabular-nums">
                                    {{ $plan->target_total_fat_g !== null ? number_format((float) $plan->target_total_fat_g / 7.0, 0).'g' : '—' }}
                                </div>
                            </div>
                        </div>
                        <flux:button class="mt-4 w-full" variant="primary" size="sm" wire:click="openPlanDetails({{ $plan->id }})">
                            {{ __('View details') }}
                        </flux:button>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal wire:model="showDetailsModal" class="max-h-[90vh] max-w-5xl overflow-y-auto">
        @if ($detailsPlanId !== null)
            @php
                $detailPlan = \App\Models\MealPlan::query()->find($detailsPlanId);
            @endphp
            @if ($detailPlan)
                <flux:heading size="xl">{{ $detailPlan->name }}</flux:heading>
                <flux:text class="mt-1 text-neutral-600 dark:text-neutral-300">{{ $detailPlan->goal }}</flux:text>

                <div class="mt-4 flex flex-wrap gap-2 border-b border-neutral-200 pb-3 dark:border-neutral-700">
                    @foreach ($weekdayShortLabels as $dNum => $label)
                        <button
                            type="button"
                            wire:click="setDetailsDay({{ $dNum }})"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $detailsDay === $dNum ? 'bg-brand-primary text-white' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-300">{{ __('Day path') }}</flux:text>
                    <flux:button size="sm" variant="{{ $detailsOptionB ? 'ghost' : 'primary' }}" wire:click="$set('detailsOptionB', false)">
                        {{ __('Option A') }}
                    </flux:button>
                    <flux:button size="sm" variant="{{ $detailsOptionB ? 'primary' : 'ghost' }}" wire:click="$set('detailsOptionB', true)">
                        {{ __('Option B') }}
                    </flux:button>
                </div>

                @php
                    $g = $this->detailsWeekGrid;
                    $optKey = $detailsOptionB ? 'b' : 'a';
                    $slot = fn (\App\Enums\MealPlanSlotType $t, int $i) => $g[$detailsDay][$optKey][$t->value.'_'.$i] ?? null;
                @endphp

                <div class="mt-6 space-y-8">
                    {{-- Breakfast --}}
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Breakfast') }}</flux:heading>
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-3 dark:border-neutral-700 dark:bg-neutral-900/30">
                            <div
                                class="flex flex-nowrap gap-4 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2 [-ms-overflow-style:none] [scrollbar-width:thin]"
                            >
                                @foreach ([1, 2] as $bi)
                                    @php
                                        $row = $slot(\App\Enums\MealPlanSlotType::Breakfast, $bi);
                                    @endphp
                                    <div
                                        class="min-w-[min(280px,85vw)] max-w-[320px] shrink-0 snap-start snap-always pt-1"
                                        wire:key="mp-bf-{{ $detailsPlanId }}-{{ $detailsDay }}-{{ $bi }}-{{ $row instanceof \App\Models\MealPlanDayMeal ? $row->id : 'e' }}-{{ $row instanceof \App\Models\MealPlanDayMeal && $row->meal ? $row->meal_id : '0' }}"
                                    >
                                        <x-meal-plan-slot-shell kind="breakfast" :row="$row" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Main meals --}}
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Main meals') }}</flux:heading>
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-3 dark:border-neutral-700 dark:bg-neutral-900/30">
                            <div
                                class="flex flex-nowrap gap-4 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2 [-ms-overflow-style:none] [scrollbar-width:thin]"
                            >
                                @foreach (range(1, 4) as $mi)
                                    @php
                                        $row = $slot(\App\Enums\MealPlanSlotType::Main, $mi);
                                    @endphp
                                    <div
                                        class="min-w-[min(280px,85vw)] max-w-[320px] shrink-0 snap-start snap-always pt-1"
                                        wire:key="mp-main-{{ $detailsPlanId }}-{{ $detailsDay }}-{{ $mi }}-{{ $row instanceof \App\Models\MealPlanDayMeal ? $row->id : 'e' }}-{{ $row instanceof \App\Models\MealPlanDayMeal && $row->meal ? $row->meal_id : '0' }}"
                                    >
                                        <x-meal-plan-slot-shell kind="main" :row="$row" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Salads --}}
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Side salads') }}</flux:heading>
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-3 dark:border-neutral-700 dark:bg-neutral-900/30">
                            <div
                                class="flex flex-nowrap gap-4 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2 [-ms-overflow-style:none] [scrollbar-width:thin]"
                            >
                                @foreach ([1, 2] as $si)
                                    @php
                                        $row = $slot(\App\Enums\MealPlanSlotType::Salad, $si);
                                    @endphp
                                    <div
                                        class="min-w-[min(280px,85vw)] max-w-[320px] shrink-0 snap-start snap-always pt-1"
                                        wire:key="mp-salad-{{ $detailsPlanId }}-{{ $detailsDay }}-{{ $si }}-{{ $row instanceof \App\Models\MealPlanDayMeal ? $row->id : 'e' }}-{{ $row instanceof \App\Models\MealPlanDayMeal && $row->meal ? $row->meal_id : '0' }}"
                                    >
                                        <x-meal-plan-slot-shell kind="salad" :row="$row" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Desserts --}}
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Desserts') }}</flux:heading>
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-3 dark:border-neutral-700 dark:bg-neutral-900/30">
                            <div
                                class="flex flex-nowrap gap-4 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2 [-ms-overflow-style:none] [scrollbar-width:thin]"
                            >
                                @foreach ([1, 2] as $di)
                                    @php
                                        $row = $slot(\App\Enums\MealPlanSlotType::Dessert, $di);
                                    @endphp
                                    <div
                                        class="min-w-[min(280px,85vw)] max-w-[320px] shrink-0 snap-start snap-always pt-1"
                                        wire:key="mp-dessert-{{ $detailsPlanId }}-{{ $detailsDay }}-{{ $di }}-{{ $row instanceof \App\Models\MealPlanDayMeal ? $row->id : 'e' }}-{{ $row instanceof \App\Models\MealPlanDayMeal && $row->meal ? $row->meal_id : '0' }}"
                                    >
                                        <x-meal-plan-slot-shell kind="dessert" :row="$row" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Soup --}}
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Soup of the day') }}</flux:heading>
                        @php
                            $soupRow = $slot(\App\Enums\MealPlanSlotType::Soup, 1);
                        @endphp
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-3 dark:border-neutral-700 dark:bg-neutral-900/30">
                            <div
                                class="flex flex-nowrap gap-4 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2 [-ms-overflow-style:none] [scrollbar-width:thin]"
                            >
                                <div
                                    class="min-w-[min(280px,85vw)] max-w-[320px] shrink-0 snap-start snap-always pt-1"
                                    wire:key="mp-soup-{{ $detailsPlanId }}-{{ $detailsDay }}-{{ $soupRow instanceof \App\Models\MealPlanDayMeal ? $soupRow->id : 'e' }}-{{ $soupRow instanceof \App\Models\MealPlanDayMeal && $soupRow->meal ? $soupRow->meal_id : '0' }}"
                                >
                                    <x-meal-plan-slot-shell kind="soup" :row="$soupRow" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Data summary --}}
                <div class="mt-10 grid gap-6 border-t border-neutral-200 pt-8 dark:border-neutral-700 lg:grid-cols-2">
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Shopping list') }}</flux:heading>
                        <flux:text class="mb-2 text-sm text-neutral-500">
                            {{ __('Ingredients for :day (Option :opt).', ['day' => $weekdayShortLabels[$detailsDay] ?? '', 'opt' => $detailsOptionB ? 'B' : 'A']) }}
                        </flux:text>
                        @if (count($this->detailsDayShoppingList) === 0)
                            <flux:text class="text-neutral-500">{{ __('Add ingredients to meals in the library to populate this list.') }}</flux:text>
                        @else
                            <ul class="max-h-56 space-y-1 overflow-y-auto text-sm">
                                @foreach ($this->detailsDayShoppingList as $line)
                                    <li class="flex justify-between gap-2 border-b border-neutral-100 py-1 dark:border-neutral-800">
                                        <span>{{ $line['name'] }}</span>
                                        <span class="shrink-0 tabular-nums text-neutral-600 dark:text-neutral-400">{{ number_format($line['grams'], 0) }} g</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Nutrition summary') }}</flux:heading>
                        <flux:text class="mb-3 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('The core day budget targets 1,200 kcal (breakfast + two main portions + side salad + dessert). Empty slots use fixed targets: breakfast 200, main 350 each, salad 150, dessert 150. Soup is optional and adds separately (150 kcal when empty).') }}
                        </flux:text>
                        @php
                            $dn = $this->detailsDayNutritionTotals;
                            $coreNut = $this->detailsDayCoreNutritionTotals;
                            $soupNut = $this->detailsDaySoupNutritionTotals;
                            $pathRange = $this->detailsDayMenuPathKcalRange;
                            $coreHighOver = $this->detailsDayAdminCoreHighPathOverBudget;
                            $coreKcal = (float) ($coreNut['calories'] ?? 0);
                            $soupKcal = (float) ($soupNut['calories'] ?? 0);
                            $cal = (float) ($dn['calories'] ?? 0);
                            $pro = (float) ($dn['protein'] ?? 0);
                            $carb = (float) ($dn['carbs'] ?? 0);
                            $fat = (float) ($dn['fat'] ?? 0);
                            $fol = (float) ($dn['b9_folate'] ?? 0);
                            $iron = (float) ($dn['iron'] ?? 0);
                            $mag = (float) ($dn['magnesium'] ?? 0);
                            $zinc = (float) ($dn['zinc'] ?? 0);
                            $sickleBadges = \App\Support\SickleCellNutrientRdi::highlightBadgeLabels($dn);
                        @endphp
                        <div class="mb-4 space-y-2 rounded-lg border border-neutral-200 bg-neutral-50/90 p-3 text-sm dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <flux:text class="font-medium text-neutral-800 dark:text-neutral-100">{{ __('Core') }}</flux:text>
                                <flux:text class="tabular-nums text-neutral-800 dark:text-neutral-100">
                                    {{ number_format($coreKcal, 0) }} {{ __('kcal') }}
                                    <span class="text-neutral-500 dark:text-neutral-400">/ {{ number_format(\App\Support\MealPlanSlotBasedDayNutrition::CORE_CALORIE_TARGET, 0) }} {{ __('kcal') }}</span>
                                </flux:text>
                            </div>
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <flux:text class="font-medium text-neutral-800 dark:text-neutral-100">{{ __('Optional soup') }}</flux:text>
                                <flux:text class="tabular-nums text-neutral-800 dark:text-neutral-100">
                                    {{ number_format($soupKcal, 0) }} {{ __('kcal') }}
                                </flux:text>
                            </div>
                            <flux:text class="border-t border-neutral-200 pt-2 text-sm font-medium tabular-nums text-neutral-900 dark:border-neutral-700 dark:text-neutral-50">
                                {{ __(':core kcal (Core) + :soup kcal (Soup) = :total kcal Total', ['core' => number_format($coreKcal, 0), 'soup' => number_format($soupKcal, 0), 'total' => number_format($cal, 0)]) }}
                            </flux:text>
                        </div>
                        <div
                            class="mb-4 rounded-lg border border-amber-200/90 bg-amber-50/90 p-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100/95"
                            role="status"
                        >
                            <flux:text class="font-semibold text-amber-950 dark:text-amber-50">{{ __('Admin validation — core calorie paths (soup excluded)') }}</flux:text>
                            <flux:text class="mt-2 leading-relaxed">
                                {{ __('High path (core): highest breakfast + two highest main meals + highest salad + highest dessert. Low path: lowest breakfast + two lowest mains + lowest salad + lowest dessert.') }}
                            </flux:text>
                            <flux:text class="mt-2 leading-relaxed">
                                {{ __('Low path: :min kcal', ['min' => number_format($pathRange['min'], 0)]) }}
                            </flux:text>
                            <flux:text class="mt-1 leading-relaxed">
                                <span>{{ __('High path: ') }}</span>
                                @if ($coreHighOver)
                                    <span class="font-semibold tabular-nums text-orange-600 dark:text-orange-400">{{ number_format($pathRange['max'], 0) }}</span>
                                @else
                                    <span class="font-semibold tabular-nums">{{ number_format($pathRange['max'], 0) }}</span>
                                @endif
                                <span> {{ __('kcal') }}</span>
                            </flux:text>
                            @if ($coreHighOver)
                                <flux:text class="mt-2 font-medium text-orange-700 dark:text-orange-300">
                                    {{ __('High path is above :limit kcal — core choices may be too heavy for the 1,200 kcal budget.', ['limit' => number_format(\App\Support\MealPlanSlotBasedDayNutrition::CORE_HIGH_PATH_WARNING_KCAL, 0)]) }}
                                </flux:text>
                            @endif
                        </div>
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-neutral-200 dark:border-neutral-700">
                                    <th class="py-2 pe-2">{{ __('Metric') }}</th>
                                    <th class="py-2">{{ __('Plan total (core + soup)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="tabular-nums">
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Calories') }}</td>
                                    <td class="py-2">{{ number_format($cal, 0) }}</td>
                                </tr>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Protein') }}</td>
                                    <td class="py-2">{{ number_format($pro, 1) }} g</td>
                                </tr>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Carbs') }}</td>
                                    <td class="py-2">{{ number_format($carb, 1) }} g</td>
                                </tr>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Fat') }}</td>
                                    <td class="py-2">{{ number_format($fat, 1) }} g</td>
                                </tr>
                            </tbody>
                        </table>
                        <flux:heading size="sm" class="mb-2 mt-4">{{ __('Micronutrient highlights') }}</flux:heading>
                        <table class="w-full text-left text-sm">
                            <tbody class="tabular-nums">
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Folate (B9)') }}</td>
                                    <td class="py-2">{{ number_format($fol, 1) }}</td>
                                </tr>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Iron') }}</td>
                                    <td class="py-2">{{ number_format($iron, 2) }}</td>
                                </tr>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Magnesium') }}</td>
                                    <td class="py-2">{{ number_format($mag, 1) }}</td>
                                </tr>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td class="py-2 font-medium">{{ __('Zinc') }}</td>
                                    <td class="py-2">{{ number_format($zinc, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm dark:border-neutral-700 dark:bg-neutral-800/80">
                            @if ($detailPlan->plan_category === \App\Enums\MealPlanLibraryCategory::CycleSync && $detailPlan->cycle_phase instanceof \App\Enums\MealCyclePhaseTag)
                                <flux:text>{{ $detailPlan->cycle_phase->compatibilityHighlight() }}</flux:text>
                            @elseif ($detailPlan->plan_category === \App\Enums\MealPlanLibraryCategory::SickleCellWarrior)
                                <flux:text class="font-medium">{{ __('Sickle cell planning cues (daily totals)') }}</flux:text>
                                <ul class="mt-2 list-inside list-disc space-y-1 text-neutral-700 dark:text-neutral-300">
                                    @foreach ($sickleBadges as $badge)
                                        <li>{{ \App\Support\SickleCellNutrientRdi::tooltipForBadge($badge) }}</li>
                                    @endforeach
                                    @if ($sickleBadges === [])
                                        <li>{{ __('Add more High Source micronutrient meals from your library to strengthen this plan.') }}</li>
                                    @endif
                                </ul>
                            @else
                                <flux:text class="text-neutral-600 dark:text-neutral-400">
                                    {{ __('Balanced plan: use the totals above to stay close to your daily macro targets.') }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="primary">{{ __('Done') }}</flux:button>
                    </flux:modal.close>
                </div>
            @endif
        @endif
    </flux:modal>

    <flux:modal wire:model="showCreateNewMealModal" class="max-w-md">
        <flux:heading size="lg">{{ __('Create New') }}</flux:heading>
        <flux:text class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
            {{ __('This adds a new meal to your library and assigns it to this slot only.') }}
        </flux:text>
        <div class="mt-4">
            <flux:input
                wire:model.blur="createNewMealName"
                :label="__('Meal name')"
                class="border-neutral-200 dark:border-neutral-700"
            />
            @error('createNewMealName')
                <flux:text class="mt-1 !text-red-600">{{ $message }}</flux:text>
            @enderror
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeCreateNewMealModal">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="confirmCreateNewMeal">{{ __('Create New') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showEditModal" class="max-w-lg">
        <flux:heading size="lg">{{ __('Swap meal') }}</flux:heading>
        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-300">
            {{ __('Day') }} {{ $weekdayShortLabels[$editDay] ?? $editDay }} · {{ ucfirst($editSlotType) }} #{{ $editSlotIndex }}
            · {{ $editOptionB ? __('Option B') : __('Option A') }}
        </flux:text>
        <div class="mt-4">
            <flux:input
                wire:model.live.debounce.300ms="editMealSearch"
                :label="__('Search library')"
                :placeholder="__('Filter by meal name…')"
            />
        </div>
        <div class="mt-4">
            <flux:select wire:model="editReplacementMealId" :label="__('Meal from library')">
                <option value="">{{ __('Choose a meal') }}</option>
                @foreach ($this->editSlotMeals as $meal)
                    <option value="{{ $meal->id }}">{{ $meal->name }}</option>
                @endforeach
            </flux:select>
            @error('editReplacementMealId')
                <flux:text class="mt-1 !text-red-600">{{ $message }}</flux:text>
            @enderror
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeEditModal">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveSlotReplacement">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>
</section>
