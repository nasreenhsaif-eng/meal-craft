@php
    $consultationConfig = $consultationConfig ?? [
        'closeHref' => route('admin.dashboard'),
        'adaptedMenuUrl' => url('/api/menu/adapted'),
        'planTiers' => \App\Services\Nutrition\UserPlanCalculator::planTiers(),
        'planTier' => null,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/consultation-crafted-for-you.jsx'],
        'title' => __('Crafted for YOU — Consultation'),
    ])
</head>
<body class="min-h-screen bg-[#F8F9F6] antialiased">
    <script id="mc-consultation-crafted-config" type="application/json">@json($consultationConfig)</script>
    <div id="mc-consultation-crafted-root"></div>
    @fluxScripts
</body>
</html>
