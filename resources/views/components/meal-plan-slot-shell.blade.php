@props([
    /** @var 'breakfast'|'main'|'soup'|'salad'|'dessert' */
    'kind' => 'main',
    /** @var \App\Models\MealPlanDayMeal|null */
    'row' => null,
])

@php
    $hasMeal = $row instanceof \App\Models\MealPlanDayMeal && $row->meal;
    $dayMealId = $row instanceof \App\Models\MealPlanDayMeal ? $row->id : null;
    $emptySlotRow = $row instanceof \App\Models\MealPlanDayMeal && ! $row->meal ? $row : null;
    $emptySlotType =
        $emptySlotRow && $emptySlotRow->slot_type instanceof \App\Enums\MealPlanSlotType
            ? $emptySlotRow->slot_type->value
            : $kind;
    $emptySlotIndex = $emptySlotRow ? (int) $emptySlotRow->slot_index : 1;
    $emptySlotMealId = $emptySlotRow ? (int) $emptySlotRow->getAttribute('meal_id') : 0;
@endphp

<div class="space-y-2">
    <div class="origin-top scale-90">
        @if ($hasMeal)
            @php
                $st = $row->slot_type instanceof \App\Enums\MealPlanSlotType ? $row->slot_type->value : (string) $row->slot_type;
                $swapCtx = [
                    'day' => $this->detailsDay,
                    'slot_type' => $st,
                    'slot_index' => (int) $row->slot_index,
                    'option_b' => (bool) $this->detailsOptionB,
                ];
                $preview =
                    $this->inlineEditDayMealId === $row->id && $this->inlineEditDayMealId !== null
                        ? $this->inlineEditPreviewNutrition
                        : null;
            @endphp
            <x-meal-card
                :meal="$row->meal"
                compact
                variant="meal_plan"
                :meal-plan-swap-context="$swapCtx"
                :preview-nutrition="$preview"
            />
        @else
            <x-meal-plan-empty-slot :kind="$kind">
                @if ($emptySlotRow !== null)
                    <x-slot name="actions">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="primary"
                            class="w-full"
                            wire:click="openEditSlot({{ (int) $this->detailsDay }}, '{{ $emptySlotType }}', {{ $emptySlotIndex }}, {{ $this->detailsOptionB ? 'true' : 'false' }}, {{ $emptySlotMealId }})"
                        >
                            {{ __('Add meal') }}
                        </flux:button>
                    </x-slot>
                @endif
            </x-meal-plan-empty-slot>
        @endif
    </div>

    @if ($hasMeal)
        <div class="flex flex-col items-stretch gap-1.5 px-1">
            <flux:button
                type="button"
                size="xs"
                variant="ghost"
                class="w-full border border-mc-gold-border/35 bg-mc-cream/90 text-xs text-stone-800 shadow-sm dark:border-mc-gold/25 dark:bg-stone-900/80 dark:text-stone-100"
                wire:click="toggleInlineIngredientEditor({{ $row->id }})"
            >
                @if ($this->inlineEditDayMealId === $row->id)
                    {{ __('Close') }}
                @else
                    {{ __('Edit ingredients') }}
                @endif
            </flux:button>
        </div>

        @if ($this->inlineEditDayMealId === $row->id)
            <div
                x-data="{ open: true }"
                class="rounded-xl border border-mc-gold-border/40 bg-mc-cream/95 shadow-sm dark:border-mc-gold/25 dark:bg-stone-900/90"
                wire:key="inline-editor-{{ $row->id }}"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-start text-sm font-medium text-stone-800 dark:text-stone-100"
                    x-on:click="open = !open"
                    :aria-expanded="open"
                >
                    <span>{{ __('Ingredients & portions') }}</span>
                    <svg
                        class="h-4 w-4 shrink-0 text-mc-gold-deep transition-transform dark:text-amber-300/90"
                        :class="{ 'rotate-180': open }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="flex min-h-0 flex-col border-t border-mc-gold-border/25 px-3 pb-3 pt-2 dark:border-mc-gold/20"
                >
                    <div class="mb-2 shrink-0">
                        <flux:text class="text-xs text-stone-600 dark:text-stone-400">{{ __('Changes update day totals live. Save when ready.') }}</flux:text>
                    </div>
                    <div class="max-h-64 min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pe-1">
                        @foreach ($this->inlineEditRows as $idx => $irow)
                            <div
                                wire:key="inline-ing-{{ $row->id }}-{{ $idx }}"
                                class="grid gap-2 rounded-lg border border-stone-200/90 bg-white/90 p-2 dark:border-stone-600/80 dark:bg-stone-950/60 sm:grid-cols-[1fr_5rem_5.5rem_auto]"
                            >
                                <div
                                    class="min-w-0"
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
                                            wire:model.live.debounce.300ms="inlineEditIngredientSearch.{{ $idx }}"
                                            @focus="open = true; activeIndex = -1"
                                            @keydown.arrow-down.prevent="move(1, {{ $this->inlineIngredientSearchResults($idx)->count() }})"
                                            @keydown.arrow-up.prevent="move(-1, {{ $this->inlineIngredientSearchResults($idx)->count() }})"
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
                                                $matches = $this->inlineIngredientSearchResults($idx);
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
                                                            wire:click="chooseInlineIngredient({{ $idx }}, {{ (int) $opt->id }})"
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
                                <flux:input
                                    wire:model.live="inlineEditRows.{{ $idx }}.amount"
                                    type="number"
                                    min="0"
                                    step="any"
                                    :label="__('Amount')"
                                    class="border-mc-gold-border/30 dark:border-mc-gold/25"
                                />
                                <flux:select
                                    wire:model.live="inlineEditRows.{{ $idx }}.unit"
                                    :label="__('Unit')"
                                    class="border-mc-gold-border/30 dark:border-mc-gold/25"
                                >
                                    @foreach (\App\Enums\RecipeAmountUnit::cases() as $u)
                                        <option value="{{ $u->value }}">{{ $u->value }}</option>
                                    @endforeach
                                </flux:select>
                                <div class="flex items-end sm:justify-end">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        class="text-red-600 dark:text-red-400"
                                        wire:click="removeInlineEditRow({{ $idx }})"
                                    >
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('inlineEditRows')
                        <flux:text class="mt-2 shrink-0 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    <div
                        class="mt-4 flex shrink-0 flex-wrap items-center justify-between gap-2 gap-y-2 border-t border-mc-gold-border/20 bg-mc-cream/95 pt-3 dark:border-mc-gold/20 dark:bg-stone-900/95"
                    >
                        <div class="flex flex-wrap gap-2">
                            <flux:button
                                type="button"
                                size="sm"
                                variant="primary"
                                wire:click="saveInlineIngredientsUpdateOriginal"
                                wire:confirm="{{ __('This will update this meal across all plans. Continue?') }}"
                            >
                                {{ __('Update Meal') }}
                            </flux:button>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="openCreateNewMealModal">
                                {{ __('Create New') }}
                            </flux:button>
                        </div>
                        <flux:button
                            type="button"
                            size="sm"
                            variant="outline"
                            icon="plus"
                            wire:click="addInlineEditRow"
                            class="shrink-0 border-mc-gold-border/50 text-stone-800 dark:border-mc-gold/40 dark:text-stone-100"
                        >
                            {{ __('Add ingredient') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
