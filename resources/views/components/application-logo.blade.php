@props([
    /** @var 'vertical'|'horizontal' */
    'mode' => 'vertical',
])

@php
    $brandName = config('branding.name', config('app.name', 'Meal Craft'));
    $verticalLogo = config('branding.logo.vertical', 'images/branding/meal-craft-logo-vertical.png');
    $verticalUrl = filter_var($verticalLogo, FILTER_VALIDATE_URL)
        ? $verticalLogo
        : asset($verticalLogo);
@endphp

@if ($mode === 'horizontal')
    <div {{ $attributes->class('inline-flex max-w-full items-center gap-3') }}>
        <div class="relative h-8 w-8 shrink-0 overflow-hidden" aria-hidden="true">
            <x-app-logo-icon class="h-full w-full" />
        </div>
        <span class="truncate font-['Montserrat'] text-lg font-semibold leading-tight text-[#364153]">{{ $brandName }}</span>
    </div>
@else
    <div {{ $attributes->class('mc-logo-vertical') }}>
        <img
            src="{{ $verticalUrl }}"
            alt="{{ $brandName }} — {{ __('Anti-inflammatory smart kitchen') }}"
            width="240"
            height="344"
            decoding="async"
            fetchpriority="high"
            class="select-none"
        />
    </div>
@endif
