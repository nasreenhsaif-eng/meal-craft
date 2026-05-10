@php
    $authLoginConfig = [
        'formAction' => route('login.store'),
        'csrfToken' => csrf_token(),
        'forgotPasswordHref' => Route::has('password.request') ? route('password.request') : '#',
        'signUpHref' => Route::has('register') ? route('register') : '#',
        'showSignUp' => Route::has('register'),
        'showForgotPassword' => Route::has('password.request'),
        'initialEmail' => old('email', ''),
        'initialRemember' => (bool) old('remember'),
        'emailError' => $errors->first('email'),
        'passwordError' => $errors->first('password'),
        'statusMessage' => session('status'),
        'splashDurationMs' => 5000,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-login.jsx'],
        'title' => __('Log in'),
    ])
</head>
<body class="min-h-screen bg-white antialiased">
    <script id="mc-auth-login-config" type="application/json">@json($authLoginConfig)</script>
    <div id="mc-auth-login-root"></div>
    @fluxScripts
</body>
</html>
