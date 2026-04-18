<?php

use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\MealPlanService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('4-Week Meal Plans')] class extends Component {
    public string $name = '';

    public string $goal = '';

    public string $activePlanId = '';

    public string $targetTotalCalories = '';

    public string $targetTotalProteinG = '';

    public string $targetTotalCarbsG = '';

    public string $targetTotalFatG = '';

    public ?string $status = null;

    /** @var array<string, float>|null */
    public ?array $lastOptionADaily = null;

    /** @var array<string, float>|null */
    public ?array $lastOptionBDaily = null;

    public bool $showEditModal = false;

    public int $editDay = 1;

    public string $editSlotType = '';

    public int $editSlotIndex = 1;

    public bool $editOptionB = false;

    public string $editReplacementMealId = '';

    public function mount(): void
    {
        //
    }

    public function createFourWeekPlan(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['required', 'string', 'max:255'],
        ]);

        $mealPlan = MealPlan::query()->create([
            'name' => $validated['name'],
            'goal' => $validated['goal'],
            'schema_type' => MealPlanSchemaType::FourWeek,
        ]);

        $this->activePlanId = (string) $mealPlan->id;
        $this->targetTotalCalories = '';
        $this->targetTotalProteinG = '';
        $this->targetTotalCarbsG = '';
        $this->targetTotalFatG = '';
        $this->status = __('Four-week plan created. Set macro targets, then run auto-fill.');
        $this->lastOptionADaily = null;
        $this->lastOptionBDaily = null;
    }

    public function updatedActivePlanId(): void
    {
        $this->lastOptionADaily = null;
        $this->lastOptionBDaily = null;
        $this->status = null;

        if ($this->activePlanId === '') {
            return;
        }

        $plan = MealPlan::query()->find((int) $this->activePlanId);

        if ($plan === null) {
            return;
        }

        $this->targetTotalCalories = $plan->target_total_calories !== null ? (string) $plan->target_total_calories : '';
        $this->targetTotalProteinG = $plan->target_total_protein_g !== null ? (string) $plan->target_total_protein_g : '';
        $this->targetTotalCarbsG = $plan->target_total_carbs_g !== null ? (string) $plan->target_total_carbs_g : '';
        $this->targetTotalFatG = $plan->target_total_fat_g !== null ? (string) $plan->target_total_fat_g : '';
    }

    public function runAutoFill(MealPlanService $mealPlanService): void
    {
        if ($this->activePlanId === '') {
            $this->addError('activePlanId', __('Select a four-week plan first.'));

            return;
        }

        $validated = $this->validate([
            'targetTotalCalories' => ['required', 'numeric', 'min:1'],
            'targetTotalProteinG' => ['nullable', 'numeric', 'min:0'],
            'targetTotalCarbsG' => ['nullable', 'numeric', 'min:0'],
            'targetTotalFatG' => ['nullable', 'numeric', 'min:0'],
        ]);

        $plan = MealPlan::query()->findOrFail((int) $this->activePlanId);

        $mealPlanService->syncMacroTargets(
            $plan,
            (float) $validated['targetTotalCalories'],
            $validated['targetTotalProteinG'] !== '' && $validated['targetTotalProteinG'] !== null
                ? (float) $validated['targetTotalProteinG']
                : null,
            $validated['targetTotalCarbsG'] !== '' && $validated['targetTotalCarbsG'] !== null
                ? (float) $validated['targetTotalCarbsG']
                : null,
            $validated['targetTotalFatG'] !== '' && $validated['targetTotalFatG'] !== null
                ? (float) $validated['targetTotalFatG']
                : null,
        );

        $result = $mealPlanService->autoFillFourWeekPlan($plan->fresh());

        if (! $result->ok) {
            $this->status = null;
            $this->lastOptionADaily = null;
            $this->lastOptionBDaily = null;
            $this->addError('autoFill', $result->message ?? __('Auto-fill failed.'));

            return;
        }

        $this->resetErrorBag('autoFill');
        $this->status = $result->message;
        $this->lastOptionADaily = $result->optionADailyAverages;
        $this->lastOptionBDaily = $result->optionBDailyAverages;
    }

    public function openEditSlot(int $day, string $slotTypeValue, int $slotIndex, bool $optionB, int $currentMealId): void
    {
        $this->editDay = $day;
        $this->editSlotType = $slotTypeValue;
        $this->editSlotIndex = $slotIndex;
        $this->editOptionB = $optionB;
        $this->editReplacementMealId = (string) $currentMealId;
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function saveSlotReplacement(MealPlanService $mealPlanService): void
    {
        $this->validate([
            'editReplacementMealId' => ['required', 'exists:meals,id'],
        ]);

        $plan = MealPlan::query()->findOrFail((int) $this->activePlanId);
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
        $this->status = __('Slot updated.');
        $svc = $mealPlanService;
        $this->lastOptionADaily = $svc->averageDailyNutritionForOption($plan->fresh(), false);
        $this->lastOptionBDaily = $svc->averageDailyNutritionForOption($plan->fresh(), true);
    }

    /**
     * @return Collection<int, MealPlan>
     */
    public function getMealPlansProperty(): Collection
    {
        return MealPlan::query()
            ->where('schema_type', MealPlanSchemaType::FourWeek)
            ->latest()
            ->get();
    }

    /**
     * @return array<int, array{a: array<string, MealPlanDayMeal>, b: array<string, MealPlanDayMeal>}>
     */
    public function getFourWeekGridProperty(): array
    {
        if ($this->activePlanId === '') {
            return [];
        }

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', (int) $this->activePlanId)
            ->with(['meal:id,name,category,total_calories,total_protein,total_carbs,total_fat'])
            ->get();

        $grid = [];
        foreach (range(1, 28) as $d) {
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

        return Meal::query()
            ->where('meal_type', $type->mealType()->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('4-week meal plan creator') }}</flux:heading>
            <flux:text class="text-neutral-600 dark:text-neutral-300">
                {{ __('Set 28-day macro totals, auto-fill from your library, then fine-tune each slot.') }}
            </flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge>{{ __('28 days × A/B paths') }}</flux:badge>
            <flux:button :href="route('meal-plans.index')" variant="ghost" size="sm" wire:navigate>
                {{ __('Weekly builder') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <flux:input wire:model="name" :label="__('Plan name')" />
            <flux:input wire:model="goal" :label="__('Goal')" />
            <flux:select wire:model.live="activePlanId" :label="__('Existing four-week plan')">
                <option value="">{{ __('Choose a plan') }}</option>
                @foreach ($this->mealPlans as $mealPlan)
                    <option value="{{ $mealPlan->id }}">{{ $mealPlan->name }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:input type="number" step="0.01" wire:model="targetTotalCalories" :label="__('Total calories (28 days)')" />
            <flux:input type="number" step="0.01" wire:model="targetTotalProteinG" :label="__('Total protein g (optional)')" />
            <flux:input type="number" step="0.01" wire:model="targetTotalCarbsG" :label="__('Total carbs g (optional)')" />
            <flux:input type="number" step="0.01" wire:model="targetTotalFatG" :label="__('Total fat g (optional)')" />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <flux:button wire:click="createFourWeekPlan">{{ __('Create four-week plan') }}</flux:button>
            <flux:button wire:click="runAutoFill" variant="primary">{{ __('Save targets & auto-fill') }}</flux:button>
            @if ($status)
                <flux:text class="font-medium !text-green-700 !dark:text-green-400">{{ $status }}</flux:text>
            @endif
            @error('activePlanId')
                <flux:text class="font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
            @enderror
            @error('autoFill')
                <flux:text class="font-medium !text-red-700 !dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </div>

        @if ($this->activePlanId !== '' && ($lastOptionADaily || $lastOptionBDaily))
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="sm">{{ __('Option A — average per day') }}</flux:heading>
                    @if ($lastOptionADaily)
                        <flux:text class="mt-2 font-mono text-sm">
                            {{ number_format($lastOptionADaily['calories'], 1) }} kcal ·
                            P {{ number_format($lastOptionADaily['protein'], 1) }}g ·
                            C {{ number_format($lastOptionADaily['carbs'], 1) }}g ·
                            F {{ number_format($lastOptionADaily['fat'], 1) }}g
                        </flux:text>
                    @endif
                </div>
                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="sm">{{ __('Option B — average per day') }}</flux:heading>
                    @if ($lastOptionBDaily)
                        <flux:text class="mt-2 font-mono text-sm">
                            {{ number_format($lastOptionBDaily['calories'], 1) }} kcal ·
                            P {{ number_format($lastOptionBDaily['protein'], 1) }}g ·
                            C {{ number_format($lastOptionBDaily['carbs'], 1) }}g ·
                            F {{ number_format($lastOptionBDaily['fat'], 1) }}g
                        </flux:text>
                    @endif
                </div>
            </div>
            <flux:text class="mt-2 text-sm text-neutral-500">
                {{ __('Compare to your plan targets divided by 28 (daily). Auto-fill picks library meals closest to each slot’s share of that daily target.') }}
            </flux:text>
        @endif
    </div>

    @if ($this->activePlanId !== '')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach (range(1, 28) as $day)
                <div
                    wire:key="four-week-day-{{ $day }}"
                    class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900"
                >
                    <flux:heading size="md" class="mb-3">{{ __('Day') }} {{ $day }}</flux:heading>
                    @foreach (['a' => false, 'b' => true] as $label => $optB)
                        <div class="mb-4 last:mb-0">
                            <flux:text class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                {{ $label === 'a' ? __('Option A') : __('Option B') }}
                            </flux:text>
                            <div class="space-y-1.5">
                                @foreach (MealPlanSlotType::daySlotTemplate() as [$slotType, $slotIndex])
                                    @php
                                        $key = $slotType->value.'_'.$slotIndex;
                                        $row = $this->fourWeekGrid[$day][$label][$key] ?? null;
                                    @endphp
                                    <div
                                        class="flex items-start justify-between gap-2 rounded-md border border-neutral-100 px-2 py-1.5 text-sm dark:border-neutral-800"
                                        wire:key="slot-{{ $day }}-{{ $label }}-{{ $key }}"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <span class="text-neutral-500">{{ ucfirst($slotType->value) }} #{{ $slotIndex }}</span>
                                            @if ($row && $row->meal)
                                                <div class="truncate font-medium">{{ $row->meal->name }}</div>
                                            @else
                                                <div class="text-neutral-400">{{ __('Empty') }}</div>
                                            @endif
                                        </div>
                                        @if ($row && $row->meal)
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                wire:click="openEditSlot({{ $day }}, '{{ $slotType->value }}', {{ $slotIndex }}, {{ $optB ? 'true' : 'false' }}, {{ $row->meal->id }})"
                                            >
                                                {{ __('Edit') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model="showEditModal" class="max-w-lg">
        <flux:heading size="lg">{{ __('Swap meal') }}</flux:heading>
        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-300">
            {{ __('Day') }} {{ $editDay }} · {{ ucfirst($editSlotType) }} #{{ $editSlotIndex }}
            · {{ $editOptionB ? __('Option B') : __('Option A') }}
        </flux:text>

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
            <flux:button variant="ghost" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveSlotReplacement">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>
</section>
