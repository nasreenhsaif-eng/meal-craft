@php
    $consultationConfig = $consultationConfig ?? [
        'closeHref' => route('app.home', absolute: false),
        'homeHref' => route('app.home', absolute: false),
        'backHref' => route('app.home', absolute: false),
        'pageEyebrow' => 'Your plan',
        'adaptedMenuUrl' => route('api.menu.adapted', absolute: false),
        'planTiers' => \App\Services\Nutrition\UserPlanCalculator::planTiers(),
        'planTier' => null,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light min-h-full">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/consultation-crafted-for-you.jsx'],
        'title' => __('Crafted for YOU'),
    ])
</head>
<body class="min-h-[100dvh] w-full bg-[#F8F9F6] antialiased">
    <script id="mc-consultation-crafted-config" type="application/json">@json($consultationConfig)</script>
    <div id="mc-consultation-crafted-root" class="min-h-full w-full"></div>
    @fluxScripts
</body>
</html>
