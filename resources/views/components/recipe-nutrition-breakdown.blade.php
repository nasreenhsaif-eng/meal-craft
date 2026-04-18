@props([
    'nutrition' => [],
    'tone' => 'default',
])

@php
    /** @var array<string, float> $nutrition */
    $n = $nutrition;
    $fv = fn (float $v): string => number_format(round($v, 2), 2, '.', '');

    $s = match ($tone) {
        'meal-craft' => [
            'wrap' => 'overflow-x-auto rounded-xl border border-mc-gold-border/35 bg-white/70 dark:border-mc-gold/25 dark:bg-stone-900/50',
            'thead' => 'border-b border-mc-gold-border/35 bg-mc-gold/10 dark:border-mc-gold/20 dark:bg-mc-gold/10',
            'th' => 'px-3 py-2.5 text-left font-semibold text-mc-gold-deep dark:text-amber-100/90',
            'thR' => 'px-3 py-2.5 text-right font-semibold text-mc-gold-deep dark:text-amber-100/90',
            'tbody' => 'divide-y divide-stone-200/90 dark:divide-stone-700/90',
            'section' => 'bg-mc-gold/10 dark:bg-mc-gold/10',
            'sectionTxt' => 'px-3 py-2 text-xs font-semibold uppercase tracking-wide text-mc-gold dark:text-amber-200/85',
            'td' => 'px-3 py-2 text-stone-600 dark:text-stone-400',
            'tdR' => 'px-3 py-2 text-right font-medium tabular-nums text-stone-800 dark:text-stone-200',
        ],
        default => [
            'wrap' => 'overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800',
            'thead' => 'border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950',
            'th' => 'px-3 py-2 font-semibold text-neutral-700 dark:text-neutral-200',
            'thR' => 'px-3 py-2 text-right font-semibold text-neutral-700 dark:text-neutral-200',
            'tbody' => 'divide-y divide-neutral-200 dark:divide-neutral-800',
            'section' => 'bg-neutral-50/80 dark:bg-neutral-900/80',
            'sectionTxt' => 'px-3 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400',
            'td' => 'px-3 py-2 text-neutral-600 dark:text-neutral-300',
            'tdR' => 'px-3 py-2 text-right font-medium tabular-nums',
        ],
    };
@endphp

<div {{ $attributes->merge(['class' => $s['wrap']]) }}>
    <table class="w-full text-sm">
        <thead>
            <tr class="{{ $s['thead'] }}">
                <th class="{{ $s['th'] }}">{{ __('Nutrient') }}</th>
                <th class="{{ $s['thR'] }}">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody class="{{ $s['tbody'] }}">
            <tr class="{{ $s['section'] }}">
                <td colspan="2" class="{{ $s['sectionTxt'] }}">
                    {{ __('Macros') }}
                </td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Calories') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['calories'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Protein (g)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['protein'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Fat (g)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['fat'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Carbs (g)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['carbs'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Fiber (g)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['fiber'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Sugar (g)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['sugar'] ?? 0)) }}</td>
            </tr>

            <tr class="{{ $s['section'] }}">
                <td colspan="2" class="{{ $s['sectionTxt'] }}">
                    {{ __('Sickle cell highlights') }}
                </td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin B6 (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['b6'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Folate B9 (mcg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['b9_folate'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin B12 (mcg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['b12'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Iron (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['iron'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Magnesium (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['magnesium'] ?? 0)) }}</td>
            </tr>

            <tr class="{{ $s['section'] }}">
                <td colspan="2" class="{{ $s['sectionTxt'] }}">
                    {{ __('Other essential micros') }}
                </td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Zinc (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['zinc'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin C (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['vitamin_c'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin A') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['vitamin_a'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin D') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['vitamin_d'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin E') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['vitamin_e'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Vitamin K') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['vitamin_k'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Potassium (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['potassium'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Sodium (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['sodium'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="{{ $s['td'] }}">{{ __('Calcium (mg)') }}</td>
                <td class="{{ $s['tdR'] }}">{{ $fv((float) ($n['calcium'] ?? 0)) }}</td>
            </tr>
        </tbody>
    </table>
</div>
