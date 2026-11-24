@php
    $signOutConfig = [
        'authenticated' => auth()->check(),
        'email' => auth()->user()?->email ?? '',
        'logoutAction' => route('logout'),
        'csrfToken' => csrf_token(),
        'joinHref' => route('join'),
        'loginHref' => route('login'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light h-full">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-sign-out.jsx'],
        'title' => __('Sign out'),
    ])
</head>
<body class="h-full min-h-[100dvh] bg-white antialiased">
    <script id="mc-auth-sign-out-config" type="application/json">@json($signOutConfig)</script>
    <div id="mc-auth-sign-out-root" class="h-full min-h-0"></div>
    @fluxScripts
</body>
</html>
