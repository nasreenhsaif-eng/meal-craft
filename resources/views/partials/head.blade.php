<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
@php
    $brandName = config('branding.name', config('app.name', 'Meal Craft'));
    $brandColors = config('branding.colors', []);
    $brandFonts = config('branding.fonts', []);
@endphp

<title>
    {{ filled($title ?? null) ? $title.' - '.$brandName : $brandName }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<style>
    :root {
        --font-sans: "{{ $brandFonts['sans'] ?? 'Montserrat' }}", ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        --color-brand-gold: {{ $brandColors['primary'] ?? '#D8A933' }};
        --color-brand-green: {{ $brandColors['secondary'] ?? '#6E8C47' }};
        --color-brand-purple: {{ $brandColors['accent'] ?? '#8F55A8' }};
        --color-brand-red: {{ $brandColors['danger'] ?? '#C44F5D' }};
        --color-brand-blue: {{ $brandColors['info'] ?? '#2F4C9B' }};
        --color-accent: var(--color-brand-gold);
        --color-accent-content: var(--color-brand-gold);
        --color-accent-foreground: var(--color-white);
    }

    .dark {
        --color-accent: var(--color-brand-green);
        --color-accent-content: var(--color-brand-green);
        --color-accent-foreground: var(--color-white);
    }
</style>
