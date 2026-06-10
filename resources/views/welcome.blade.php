@php
    $welcomeConfig = $welcomeConfig ?? [
        'loginHref' => route('login'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-welcome.jsx'],
        'title' => __('Welcome'),
    ])
</head>
<body class="min-h-screen w-full bg-white antialiased">
    <script id="mc-auth-welcome-config" type="application/json">@json($welcomeConfig)</script>
    <div id="mc-auth-welcome-root" class="min-h-screen w-full"></div>
    @fluxScripts
</body>
</html>
