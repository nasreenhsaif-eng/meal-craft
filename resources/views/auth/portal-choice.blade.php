@php
    $portalChoiceConfig = $portalChoiceConfig ?? [
        'userName' => auth()->user()?->name ?? '',
        'onboardingHref' => route('app.home'),
        'adminHref' => route('admin.dashboard'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-portal-choice.jsx'],
        'title' => __('Choose your workspace'),
    ])
</head>
<body class="h-full min-h-[100dvh] bg-white antialiased">
    <script id="mc-auth-portal-choice-config" type="application/json">@json($portalChoiceConfig)</script>
    <div id="mc-auth-portal-choice-root" class="min-h-[100dvh] w-full"></div>
    @fluxScripts
</body>
</html>
