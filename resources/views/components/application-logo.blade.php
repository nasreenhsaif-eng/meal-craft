@props([
    /** @var 'vertical'|'horizontal' */
    'mode' => 'vertical',
])

@php
    $brandName = config('branding.name', config('app.name', 'Meal Craft'));
    $tagline = config('branding.tagline', 'ANTI-INFLAMMATORY SMART KITCHEN');
@endphp

@if ($mode === 'horizontal')
    <div {{ $attributes->class('inline-flex max-w-full items-center gap-3') }}>
        <div class="relative aspect-square h-full max-h-12 shrink-0 overflow-hidden sm:max-h-16" aria-hidden="true">
            <x-app-logo-icon class="h-full w-full object-contain" />
        </div>
        <span class="truncate font-['Montserrat'] text-lg font-semibold leading-tight text-[#6E8C47]">{{ $brandName }}</span>
    </div>
@else
    <div {{ $attributes->class('flex flex-col items-center gap-3 text-center') }}>
        <div class="relative aspect-square w-40 max-w-full shrink-0 overflow-hidden sm:w-48" aria-hidden="true">
            <x-app-logo-icon class="h-full w-full object-contain" />
        </div>
        <div class="flex flex-col gap-1">
            <span class="font-['Montserrat'] text-xl font-semibold text-[#6E8C47]">{{ $brandName }}</span>
            <span class="text-sm font-medium uppercase tracking-wide text-neutral-600">{{ $tagline }}</span>
        </div>
    </div>
@endif
