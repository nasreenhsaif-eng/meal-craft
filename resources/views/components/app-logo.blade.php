@props([
    'sidebar' => false,
])

@php
    $brandName = config('branding.name', config('app.name', 'Meal Craft'));
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-white">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-white">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:brand>
@endif
