@php
    $consultationConfig = $consultationConfig ?? [
        'closeHref' => route('admin.dashboard'),
        'adaptedMenuUrl' => route('api.menu.adapted', absolute: false),
        'planTiers' => \App\Services\Nutrition\UserPlanCalculator::planTiers(),
        'planTier' => null,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light h-full">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/consultation-crafted-for-you.jsx'],
        'title' => __('Crafted for YOU — Consultation'),
    ])
</head>
<body class="h-full min-h-[100dvh] bg-[#F8F9F6] antialiased">
    <script id="mc-consultation-crafted-config" type="application/json">@json($consultationConfig)</script>
    <div id="mc-consultation-crafted-root" class="h-full min-h-0"></div>
    @fluxScripts
</body>
</html>
