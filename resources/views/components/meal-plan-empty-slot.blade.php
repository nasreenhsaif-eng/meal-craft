@props([
    /** @var 'breakfast'|'main'|'soup'|'salad'|'dessert' */
    'kind' => 'main',
])

@php
    $label = match ($kind) {
        'breakfast' => __('Breakfast option'),
        'main' => __('Main meal slot'),
        'soup' => __('Soup'),
        'salad' => __('Side salad'),
        'dessert' => __('Dessert'),
        default => __('Meal slot'),
    };
@endphp

<article
    {{ $attributes->class([
        'flex min-h-[280px] flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-stone-300/90 bg-stone-50/80 p-6 text-center dark:border-stone-600 dark:bg-stone-900/50',
    ]) }}
    aria-label="{{ $label }}"
>
    <div class="rounded-full bg-stone-200/80 p-4 text-stone-500 dark:bg-stone-800 dark:text-stone-400" aria-hidden="true">
        @if ($kind === 'breakfast')
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h3a2.25 2.25 0 0 1 2.25 2.25V9" />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.75 9h16.5c-.77 4.5-4.24 7.5-8.25 7.5S4.52 13.5 3.75 9Z"
                />
            </svg>
        @elseif ($kind === 'soup')
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.546 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z"
                />
            </svg>
        @elseif ($kind === 'salad')
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z"
                />
            </svg>
        @elseif ($kind === 'dessert')
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"
                />
            </svg>
        @else
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513m-3 4.109v-1.73M7 16.5v-1.73m0 1.73c-.285.47-.76.886-1.39 1.207M7 16.5c.615-.3 1.24-.514 1.86-.696M17 16.5v-1.73c.685.378 1.39.973 1.61 1.697M7 16.5v-1.73c-.685-.378-1.39-.973-1.61-1.697"
                />
            </svg>
        @endif
    </div>
    <flux:text class="text-sm font-medium text-stone-600 dark:text-stone-300">{{ $label }}</flux:text>
    <flux:text class="text-xs text-stone-500 dark:text-stone-500">{{ __('Empty slot — add a meal from your library below, or use Swap on a filled card in this plan.') }}</flux:text>
    @isset($actions)
        <div class="mt-2 w-full max-w-[220px]">{{ $actions }}</div>
    @endisset
</article>
