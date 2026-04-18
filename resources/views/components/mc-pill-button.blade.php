@props([
    'variant' => 'primary',
    'size' => 'md',
])

@php
    // Figma: Montserrat 500, pill, primary default/hover/active per spec
    $base = 'inline-flex items-center justify-center rounded-full font-["Montserrat"] font-medium transition-all duration-200';

    $sizes = [
        'md' => 'h-[50px] px-6 text-[16px]',
        'sm' => 'h-[40px] px-4 text-[14px]',
    ];

    $variants = [
        'primary' =>
            'border border-[#E5E7EB] bg-[#FFFFFF] text-[#364153] '
            .'hover:bg-[rgba(229,231,235,0.5)] '
            .'active:bg-[#6E8C47] active:text-[#FFFFFF] active:shadow-[0px_4px_4px_rgba(0,0,0,0.25)]',
        'secondary' =>
            'border border-[#364153] bg-transparent text-[#364153] hover:bg-[rgba(54,65,83,0.05)]',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
    {{ $slot }}
</button>
