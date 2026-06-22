@php
    $loginConfig = [
        'formAction' => route('login.store'),
        'csrfToken' => csrf_token(),
        'forgotPasswordHref' => Route::has('password.request') ? route('password.request') : '#',
        'showForgotPassword' => Route::has('password.request'),
        'signUpHref' => Route::has('join') ? route('join') : '#',
        'showSignUp' => Route::has('join'),
        'initialEmail' => old('email', ''),
        'initialRemember' => old('remember') !== null,
        'emailError' => $errors->first('email'),
        'passwordError' => $errors->first('password'),
        'statusMessage' => session('status') ?? '',
        'errorMessage' => session('error') ?? '',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light h-full">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-login.jsx'],
        'title' => __('Log in'),
    ])
</head>
<body class="h-full min-h-[100dvh] bg-white antialiased">
    <script id="mc-auth-login-config" type="application/json">@json($loginConfig)</script>
    <div id="mc-auth-login-root" class="h-full min-h-0"></div>
    @fluxScripts
</body>
</html>
