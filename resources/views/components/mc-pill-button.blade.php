@props([
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center rounded-[12px] font-montserrat font-bold uppercase tracking-wider transition-all duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2';

    $sizes = [
        'md' => 'h-[50px] min-h-[50px] px-6 text-[16px] leading-none',
        'sm' => 'h-[40px] min-h-[40px] px-4 text-[14px] leading-none',
    ];

    $variants = [
        'primary' =>
            'border border-transparent bg-[#5A6B44] text-white shadow-sm '
            .'hover:bg-[#485636] hover:shadow-md hover:scale-[1.02] '
            .'active:bg-[#485636] active:shadow-inner active:scale-[0.98]',
        'secondary' =>
            'border border-transparent bg-[color-mix(in_srgb,#6E8C47_50%,white)] text-[#364153] '
            .'hover:bg-[color-mix(in_srgb,#6E8C47_70%,white)] '
            .'active:border-[#6E8C47] active:bg-[#6E8C47] active:text-white active:scale-[0.98]',
        'outline' =>
            'border border-transparent bg-transparent text-[#5A6B44] '
            .'hover:bg-[#5A6B44]/10',
        'ghost' =>
            'border border-transparent bg-transparent text-[#5A6B44] '
            .'hover:bg-[#5A6B44]/10',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
    {{ $slot }}
</button>
