@props([
    'meal',
    'compact' => false,
    'showBulkCheckbox' => false,
    /** @var 'library'|'meal_plan' */
    'variant' => 'library',
    /**
     * When variant is meal_plan, optional slot context for swap: day, slot_type, slot_index, option_b
     *
     * @var array{day: int, slot_type: string, slot_index: int, option_b: bool}|null
     */
    'mealPlanSwapContext' => null,
    /** When set (e.g. live inline edit), overrides displayed macros from this calculator-shaped array */
    'previewNutrition' => null,
])

@php
    /** @var \App\Models\Meal $meal */
    $meal->loadMissing('ingredients');
    $nut = is_array($previewNutrition) && array_key_exists('calories', $previewNutrition)
        ? $previewNutrition
        : $meal->nutritionForDisplay();
    $imageUrl = $meal->imageUrl();
    $isHighNutritiveValue = (float) ($meal->health_score ?? 0) >= 72;
    $isMealPlanEmbed = $variant === 'meal_plan';
    $swapCtx = is_array($mealPlanSwapContext) ? $mealPlanSwapContext : null;
    $isBaseRecipe = $meal->meal_type instanceof \App\Enums\MealType && $meal->meal_type === \App\Enums\MealType::BaseRecipe;
@endphp

@if ($compact)
    <article
        {{ $attributes->class([
            'group relative flex flex-col overflow-hidden rounded-2xl border p-4 shadow-sm transition-shadow duration-300',
            $isBaseRecipe
                ? 'border-stone-400/70 bg-stone-100/90 ring-1 ring-stone-400/35 dark:border-stone-500/55 dark:bg-stone-900/95 dark:ring-stone-500/30'
                : 'border-mc-gold-border/35 bg-mc-cream/90 dark:border-mc-gold/25 dark:bg-stone-900/90',
            'hover:shadow-md hover:shadow-stone-900/10 dark:hover:shadow-black/30',
            $isHighNutritiveValue ? 'ring-1 ring-brand-accent/35 dark:ring-brand-accent/40' : '',
        ]) }}
    >
        @if (! $isMealPlanEmbed && $showBulkCheckbox)
            <div class="absolute start-2 top-2 z-10">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-stone-300 text-mc-gold-deep shadow-sm focus:ring-mc-gold-border/60 dark:border-stone-600 dark:bg-stone-900 dark:text-amber-300"
                    wire:model.live="selectedMeals"
                    value="{{ $meal->id }}"
                    aria-label="{{ __('Select :name', ['name' => $meal->name]) }}"
                />
            </div>
        @endif

        @if (! $isMealPlanEmbed)
            <div class="absolute end-2 top-2 z-10 flex items-center gap-0.5">
                <flux:tooltip :content="__('Edit meal')">
                    <flux:button
                        type="button"
                        size="xs"
                        variant="ghost"
                        icon="pencil-square"
                        class="!border-stone-200/90 !bg-white/90 !p-1 shadow-sm ring-1 ring-stone-200/80 dark:!border-stone-600 dark:!bg-stone-950/90 dark:ring-stone-600/80"
                        wire:click="editMeal({{ $meal->id }})"
                    />
                </flux:tooltip>
                <flux:tooltip :content="__('Delete meal')">
                    <flux:button
                        type="button"
                        size="xs"
                        variant="ghost"
                        icon="trash"
                        class="!border-stone-200/90 !bg-white/90 !p-1 !text-red-600 shadow-sm ring-1 ring-stone-200/80 dark:!border-stone-600 dark:!bg-stone-950/90 dark:!text-red-400 dark:ring-stone-600/80"
                        wire:click="deleteMeal({{ $meal->id }})"
                        wire:confirm="{{ __('Delete this meal? This cannot be undone.') }}"
                    />
                </flux:tooltip>
            </div>
        @elseif ($swapCtx !== null)
            <div class="absolute end-2 top-2 z-10">
                <flux:tooltip :content="__('Swap meal')">
                    <flux:button
                        type="button"
                        size="xs"
                        variant="ghost"
                        icon="arrow-path"
                        class="!border-stone-200/90 !bg-white/90 !p-1 shadow-sm ring-1 ring-stone-200/80 dark:!border-stone-600 dark:!bg-stone-950/90 dark:ring-stone-600/80"
                        wire:click="openEditSlot({{ (int) $swapCtx['day'] }}, '{{ $swapCtx['slot_type'] }}', {{ (int) $swapCtx['slot_index'] }}, {{ ! empty($swapCtx['option_b']) ? 'true' : 'false' }}, {{ $meal->id }})"
                    />
                </flux:tooltip>
            </div>
        @endif

        <div
            class="relative aspect-video w-full shrink-0 overflow-hidden rounded-xl border border-stone-200/90 bg-stone-100/80 dark:border-stone-700 dark:bg-stone-800/60"
        >
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-stone-900/45 via-stone-900/5 to-stone-900/15"
                    aria-hidden="true"
                ></div>
            @else
                <div
                    class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-stone-200/90 via-stone-300/80 to-stone-500/90 dark:from-stone-700/80 dark:via-stone-800/80 dark:to-stone-900/95"
                    aria-hidden="true"
                >
                    <div
                        class="pointer-events-none absolute inset-0 bg-gradient-to-t from-stone-600/20 via-transparent to-stone-500/15 dark:from-stone-950/40"
                        aria-hidden="true"
                    ></div>
                    <svg class="relative z-[1] h-8 w-8 text-stone-500/90 dark:text-stone-400/90" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    <span class="relative z-[1] mt-1.5 text-xs font-medium text-stone-600 dark:text-stone-300">{{ __('No photo') }}</span>
                </div>
            @endif
        </div>

        <div
            class="mt-3 flex flex-1 flex-col gap-3 rounded-xl border border-stone-200/90 bg-white/80 p-4 shadow-sm dark:border-stone-700 dark:bg-stone-950/60"
        >
            <header class="border-b border-mc-gold-border/20 pb-3 pe-8 dark:border-mc-gold/20">
                @if ($meal->meal_type)
                    <flux:badge
                        color="zinc"
                        size="sm"
                        class="{{ $isBaseRecipe ? 'mb-2 border border-stone-400/60 bg-stone-200/90 text-stone-800 dark:border-stone-500 dark:bg-stone-800/90 dark:text-stone-100' : 'mb-2 border border-mc-gold-border/25 bg-white/60 dark:border-mc-gold/20 dark:bg-stone-900/40' }}"
                    >
                        {{ $meal->meal_type->label() }}
                    </flux:badge>
                @elseif ($meal->category)
                    <flux:badge
                        :color="$meal->category->badgeColor()"
                        size="sm"
                        class="mb-2 border border-mc-gold-border/25 bg-white/60 dark:border-mc-gold/20 dark:bg-stone-900/40"
                    >
                        {{ $meal->category->value }}
                    </flux:badge>
                @endif
                @if ($isBaseRecipe)
                    <flux:badge
                        color="zinc"
                        size="sm"
                        class="mb-2 border border-stone-400/50 bg-stone-300/50 text-stone-800 dark:border-stone-600 dark:bg-stone-700/70 dark:text-stone-200"
                    >
                        {{ __('Ingredient component') }}
                    </flux:badge>
                @endif
                @if ($isHighNutritiveValue)
                    <flux:badge
                        size="sm"
                        class="mb-2 ms-0 border border-brand-accent/45 bg-brand-accent/15 font-medium text-brand-accent dark:border-brand-accent/50 dark:bg-brand-accent/20 dark:text-violet-100"
                    >
                        {{ __('High nutritive value') }}
                    </flux:badge>
                @endif
                @if ($isMealPlanEmbed)
                    <h3 class="font-serif text-lg font-semibold leading-snug tracking-tight text-stone-800 line-clamp-2 dark:text-stone-100">
                        {{ $meal->name }}
                    </h3>
                @else
                    <h3 class="font-serif text-lg font-semibold leading-snug tracking-tight text-stone-800 line-clamp-2 dark:text-stone-100">
                        <button
                            type="button"
                            wire:click="openMealDetails({{ $meal->id }})"
                            class="w-full text-left transition hover:text-mc-gold-deep dark:hover:text-amber-200/90"
                        >
                            {{ $meal->name }}
                        </button>
                    </h3>
                @endif
            </header>

            <div class="flex flex-wrap gap-2">
                <div
                    class="rounded-lg border border-stone-200/90 bg-white/90 px-3 py-2 text-center shadow-sm dark:border-stone-600/80 dark:bg-stone-900/40"
                >
                    <span class="block text-[10px] font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ __('Calories') }}</span>
                    <span class="font-sans text-sm font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['calories'], 0) }}
                    </span>
                </div>
                <div
                    class="rounded-lg border border-mc-gold-border/40 bg-mc-gold/10 px-3 py-2 text-center shadow-sm dark:border-mc-gold/35 dark:bg-mc-gold/15"
                >
                    <span class="block text-[10px] font-medium uppercase tracking-wide text-mc-gold-deep dark:text-amber-100/90">{{ __('Protein') }}</span>
                    <span class="font-sans text-sm font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['protein'], 0) }}
                    </span>
                </div>
                <div
                    class="rounded-lg border border-stone-200/90 bg-white/90 px-3 py-2 text-center shadow-sm dark:border-stone-600/80 dark:bg-stone-900/40"
                >
                    <span class="block text-[10px] font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ __('Carbs') }}</span>
                    <span class="font-sans text-sm font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['carbs'], 0) }}
                    </span>
                </div>
                <div
                    class="rounded-lg border border-stone-200/90 bg-white/90 px-3 py-2 text-center shadow-sm dark:border-stone-600/80 dark:bg-stone-900/40"
                >
                    <span class="block text-[10px] font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ __('Fat') }}</span>
                    <span class="font-sans text-sm font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['fat'], 0) }}
                    </span>
                </div>
            </div>

            @if (! $isMealPlanEmbed)
                <button
                    type="button"
                    wire:click="openMealDetails({{ $meal->id }})"
                    class="mt-auto w-full rounded-xl border border-transparent bg-brand-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-brand-secondary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-secondary/80 focus-visible:ring-offset-2 focus-visible:ring-offset-mc-cream dark:focus-visible:ring-offset-stone-900"
                >
                    <span class="!text-white">{{ __('View details') }}</span>
                </button>
            @endif
        </div>
    </article>
@else
    <article
        {{ $attributes->class([
            'group relative flex flex-col gap-4 overflow-hidden rounded-2xl border p-4 shadow-sm transition-shadow duration-300 md:flex-row md:items-stretch md:gap-5 md:p-5',
            $isBaseRecipe
                ? 'border-stone-400/70 bg-stone-100/90 ring-1 ring-stone-400/35 dark:border-stone-500/55 dark:bg-stone-900/95 dark:ring-stone-500/30'
                : 'border-mc-gold-border/35 bg-mc-cream/90 dark:border-mc-gold/25 dark:bg-stone-900/90',
            'hover:shadow-md hover:shadow-stone-900/10 dark:hover:shadow-black/30',
            $isHighNutritiveValue ? 'ring-1 ring-brand-accent/35 dark:ring-brand-accent/40' : '',
        ]) }}
    >
        @if ($isMealPlanEmbed && $swapCtx !== null)
            <div class="absolute end-4 top-4 z-10 md:end-6 md:top-6">
                <flux:tooltip :content="__('Swap meal')">
                    <flux:button
                        type="button"
                        size="xs"
                        variant="ghost"
                        icon="arrow-path"
                        class="!border-stone-200/90 !bg-white/90 !p-1 shadow-sm ring-1 ring-stone-200/80 dark:!border-stone-600 dark:!bg-stone-950/90 dark:ring-stone-600/80"
                        wire:click="openEditSlot({{ (int) $swapCtx['day'] }}, '{{ $swapCtx['slot_type'] }}', {{ (int) $swapCtx['slot_index'] }}, {{ ! empty($swapCtx['option_b']) ? 'true' : 'false' }}, {{ $meal->id }})"
                    />
                </flux:tooltip>
            </div>
        @endif
        <div
            class="relative aspect-video w-full shrink-0 overflow-hidden rounded-xl border border-stone-200/90 bg-stone-100/80 dark:border-stone-700 dark:bg-stone-800/60 md:max-w-[320px] md:aspect-auto md:min-h-[200px] md:w-[40%]"
        >
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover md:min-h-[200px]" loading="lazy" />
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-stone-900/45 via-stone-900/5 to-stone-900/15" aria-hidden="true"></div>
            @else
                <div
                    class="absolute inset-0 flex min-h-[160px] flex-col items-center justify-center bg-gradient-to-br from-stone-200/90 via-stone-300/80 to-stone-500/90 md:min-h-[200px] dark:from-stone-700/80 dark:via-stone-800/80 dark:to-stone-900/95"
                    aria-hidden="true"
                >
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-stone-600/20 via-transparent to-stone-500/15 dark:from-stone-950/40" aria-hidden="true"></div>
                    <svg class="relative z-[1] h-10 w-10 text-stone-500/90 dark:text-stone-400/90" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    <span class="relative z-[1] mt-2 text-sm font-medium text-stone-600 dark:text-stone-300">{{ __('No photo') }}</span>
                </div>
            @endif
        </div>

        <div
            class="flex min-w-0 flex-1 flex-col gap-4 rounded-xl border border-stone-200/90 bg-white/80 p-5 shadow-sm dark:border-stone-700 dark:bg-stone-950/60 md:py-6"
        >
            <header class="border-b border-mc-gold-border/20 pb-3 dark:border-mc-gold/20">
                @if ($meal->meal_type)
                    <flux:badge
                        color="zinc"
                        size="sm"
                        class="{{ $isBaseRecipe ? 'mb-2 border border-stone-400/60 bg-stone-200/90 text-stone-800 dark:border-stone-500 dark:bg-stone-800/90 dark:text-stone-100' : 'mb-2 border border-mc-gold-border/25 bg-white/60 dark:border-mc-gold/20 dark:bg-stone-900/40' }}"
                    >
                        {{ $meal->meal_type->label() }}
                    </flux:badge>
                @elseif ($meal->category)
                    <flux:badge
                        :color="$meal->category->badgeColor()"
                        size="sm"
                        class="mb-2 border border-mc-gold-border/25 bg-white/60 dark:border-mc-gold/20 dark:bg-stone-900/40"
                    >
                        {{ $meal->category->value }}
                    </flux:badge>
                @endif
                @if ($isBaseRecipe)
                    <flux:badge
                        color="zinc"
                        size="sm"
                        class="mb-2 border border-stone-400/50 bg-stone-300/50 text-stone-800 dark:border-stone-600 dark:bg-stone-700/70 dark:text-stone-200"
                    >
                        {{ __('Ingredient component') }}
                    </flux:badge>
                @endif
                @if ($isHighNutritiveValue)
                    <flux:badge
                        size="sm"
                        class="mb-2 border border-brand-accent/45 bg-brand-accent/15 font-medium text-brand-accent dark:border-brand-accent/50 dark:bg-brand-accent/20 dark:text-violet-100"
                    >
                        {{ __('High nutritive value') }}
                    </flux:badge>
                @endif
                <h3 class="font-serif text-xl font-semibold leading-snug text-stone-800 dark:text-stone-100">
                    {{ $meal->name }}
                </h3>
            </header>

            <div class="flex flex-wrap gap-3">
                <div
                    class="rounded-lg border border-stone-200/90 bg-white/90 px-4 py-2 text-center shadow-sm dark:border-stone-600/80 dark:bg-stone-900/40"
                >
                    <span class="block text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ __('Calories') }}</span>
                    <span class="font-sans mt-1 block text-base font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['calories'], 0) }}
                    </span>
                </div>
                <div
                    class="rounded-lg border border-mc-gold-border/40 bg-mc-gold/10 px-4 py-2 text-center shadow-sm dark:border-mc-gold/35 dark:bg-mc-gold/15"
                >
                    <span class="block text-xs font-medium uppercase tracking-wide text-mc-gold-deep dark:text-amber-100/90">{{ __('Protein') }}</span>
                    <span class="font-sans mt-1 block text-base font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['protein'], 0) }}
                    </span>
                </div>
                <div
                    class="rounded-lg border border-stone-200/90 bg-white/90 px-4 py-2 text-center shadow-sm dark:border-stone-600/80 dark:bg-stone-900/40"
                >
                    <span class="block text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ __('Carbs') }}</span>
                    <span class="font-sans mt-1 block text-base font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['carbs'], 0) }}
                    </span>
                </div>
                <div
                    class="rounded-lg border border-stone-200/90 bg-white/90 px-4 py-2 text-center shadow-sm dark:border-stone-600/80 dark:bg-stone-900/40"
                >
                    <span class="block text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ __('Fat') }}</span>
                    <span class="font-sans mt-1 block text-base font-semibold tabular-nums text-stone-800 dark:text-stone-100">
                        {{ number_format((float) $nut['fat'], 0) }}
                    </span>
                </div>
            </div>

            @if (! $isMealPlanEmbed)
                <button
                    type="button"
                    wire:click="openMealDetails({{ $meal->id }})"
                    class="mt-auto self-start rounded-xl border border-transparent bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-secondary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-secondary/80 focus-visible:ring-offset-2 focus-visible:ring-offset-mc-cream dark:focus-visible:ring-offset-stone-900"
                >
                    <span class="!text-white">{{ __('View details') }}</span>
                </button>
            @endif
        </div>
    </article>
@endif
