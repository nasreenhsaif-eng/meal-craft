@props([
    /** @var string minimal|smart|marketing|standard|vertical-minimal|vertical-standard|vertical-smart|vertical-marketing|seal-xs|seal-sm|seal-md|seal-lg|seal-xl|leaf */
    'variant' => 'smart',
    /** Pixel width, CSS length, or null for slice native width */
    'width' => null,
    /** @var string|null For `variant="leaf"` — token: gold | red | purple | green | blue (aliases: yellow→gold, crimson→red, …). Maps to fixed sprite rects; not arbitrary hex. */
    'color' => null,
])

@php
    $variant = strtolower(trim($variant));
    $sheetW = 1340;
    $sheetH = 1260;
    $sheetUrl = asset('images/branding/logo-sheet.svg');

    $logoBoxes = [
        'minimal' => '20 217 128 30',
        'smart' => '315 204 217 68',
        'standard' => '315 204 217 68',
        'marketing' => '933 167 387 130',
        'vertical-minimal' => '222 660 280 180',
        'vertical-standard' => '222 660 280 180',
        'vertical-smart' => '222 840 280 220',
        'vertical-marketing' => '470 870 400 380',
    ];
    $sealBoxes = [
        'seal-xs' => '72 515 84 84',
        'seal-sm' => '186 481 152 152',
        'seal-md' => '368 473 168 168',
        'seal-lg' => '566 431 252 252',
        'seal-xl' => '836 348 448 428',
    ];
    $leafBoxes = [
        'gold' => '57 30 33 87',
        'red' => '355 30 33 87',
        'purple' => '653 30 33 87',
        'green' => '951 30 33 87',
        'blue' => '1249 30 33 87',
    ];
    $leafAliases = [
        'yellow' => 'gold',
        'amber' => 'gold',
        'crimson' => 'red',
        'rose' => 'red',
        'violet' => 'purple',
        'magenta' => 'purple',
        'lime' => 'green',
        'primary' => 'green',
        'navy' => 'blue',
        'indigo' => 'blue',
    ];

    if ($variant === 'leaf') {
        $raw = strtolower(trim((string) ($color ?? 'gold')));
        $shade = $leafAliases[$raw] ?? $raw;
        $vb = $leafBoxes[$shade] ?? $leafBoxes['gold'];
    } elseif (isset($sealBoxes[$variant])) {
        $vb = $sealBoxes[$variant];
    } else {
        $vb = $logoBoxes[$variant] ?? $logoBoxes['smart'];
    }

    [, , $vw, $vh] = array_map('floatval', preg_split('/\s+/', trim($vb)));
    $aspect = $vh > 0 ? $vw / $vh : 1;
    $nativeW = (int) $vw;
    $resolved = $width ?? $nativeW;
@endphp

<svg
    {{ $attributes->class('inline-block max-w-full shrink-0 overflow-hidden') }}
    viewBox="{{ $vb }}"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="{{ config('branding.name', config('app.name', 'Meal Craft')) }}"
    preserveAspectRatio="xMidYMid meet"
    @if (in_array($variant, ['smart', 'standard', 'marketing', 'vertical-smart', 'vertical-marketing'], true))
        data-tagline-opacity="0.6"
    @endif
    @if ($resolved === null || is_numeric($resolved))
        width="{{ (int) $resolved }}"
        height="{{ $aspect > 0 ? round((int) $resolved / $aspect, 3) : (int) $vh }}"
    @else
        style="width: {{ $resolved }}; height: auto; aspect-ratio: {{ (int) $vw }} / {{ (int) $vh }};"
    @endif
>
    <image
        href="{{ $sheetUrl }}"
        x="0"
        y="0"
        width="{{ $sheetW }}"
        height="{{ $sheetH }}"
        preserveAspectRatio="none"
    />
</svg>
