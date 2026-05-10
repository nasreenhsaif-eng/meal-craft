@props([
    'label' => null,
    'value' => '',
    'options' => [],
    'placeholder' => 'Select…',
    /**
     * Livewire property name to set (e.g. "category").
     */
    'wireModel' => null,
])

@php
    $wireModelKey = is_string($wireModel) ? $wireModel : '';
    $id = $attributes->get('id') ?? 'mc-dd-'.($wireModelKey !== '' ? \Illuminate\Support\Str::slug($wireModelKey) : \Illuminate\Support\Str::random(8));
    $current = is_string($value) ? $value : '';
    $optionsList = is_array($options) ? $options : [];
    $listboxLabel = filled($label) ? $label : __('Options');
@endphp

<div {{ $attributes->class(['block w-full max-w-[492px] text-left']) }}>
    @if (filled($label))
        <label
            for="{{ $id }}"
            class="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94"
        >
            {{ $label }}
        </label>
    @endif

    <div
        x-data="{ open: false }"
        @keydown.escape.window="open = false"
        @click.outside="open = false"
        class="relative"
    >
        <button
            id="{{ $id }}"
            type="button"
            aria-haspopup="listbox"
            :aria-expanded="open"
            aria-label="Open dropdown"
            @click="open = !open"
            class="relative flex h-[49px] w-full appearance-none items-center justify-between gap-3 overflow-hidden rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] px-[20px] text-left font-montserrat text-[16px] font-medium tracking-tight text-[#364153] shadow-sm outline-none transition-[border-color,box-shadow,background-color] duration-200 hover:bg-[#F8F9F6] focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
        >
            <span class="min-w-0 truncate">{{ filled($current) ? $current : $placeholder }}</span>

            <div class="flex h-5 w-5 shrink-0 items-center justify-center bg-[#FFFFFF] text-[#5A6B44]" aria-hidden="true">
                <svg class="h-4 w-4 shrink-0 text-[#5A6B44]" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
        </button>

        <div
            x-show="open"
            x-transition
            class="absolute left-0 right-0 top-full z-30 mt-2 w-full rounded-[12px] bg-[#FFFFFF] p-2 shadow-lg"
        >
            <ul role="listbox" aria-label="{{ $listboxLabel }}" class="max-h-[220px] overflow-auto">
                @foreach ($optionsList as $opt)
                    @php
                        $optLabel = is_string($opt) ? $opt : '';
                        $selected = $optLabel !== '' && $optLabel === $current;
                    @endphp

                    <li role="option" aria-selected="{{ $selected ? 'true' : 'false' }}">
                        <button
                            type="button"
                            class="flex w-full appearance-none items-center justify-between gap-3 rounded-[12px] border-0 bg-transparent px-3 py-2 text-left font-montserrat text-sm font-semibold text-[#262A22] shadow-none outline-none transition-[background-color,color] duration-200 ease-in-out hover:bg-[#F8F9F6] hover:text-[#5A6B44]"
                            @click="
                                open = false;
                                @if ($wireModelKey !== '')
                                    $wire.set(@js($wireModelKey), @js($optLabel));
                                @endif
                            "
                        >
                            <span class="min-w-0 truncate">{{ $optLabel }}</span>
                            <span class="{{ $selected ? 'text-[#5A6B44]' : 'opacity-0' }}" aria-hidden="true">✓</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
