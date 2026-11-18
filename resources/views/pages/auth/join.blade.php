@php
    $joinConfig = [
        'formAction' => route('register.store'),
        'csrfToken' => csrf_token(),
        'loginHref' => route('login'),
        'initialName' => old('name', ''),
        'initialEmail' => old('email', ''),
        'initialPhone' => old('phone', ''),
        'nameError' => $errors->first('name'),
        'emailError' => $errors->first('email'),
        'phoneError' => $errors->first('phone'),
        'passwordError' => $errors->first('password') ?: $errors->first('password_confirmation'),
        'statusMessage' => session('status') ?? '',
        'errorMessage' => session('error') ?? '',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light h-full">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-join.jsx'],
        'title' => __('Join Meal Craft'),
    ])
</head>
<body class="h-full min-h-[100dvh] bg-white antialiased">
    <script id="mc-auth-join-config" type="application/json">@json($joinConfig)</script>
    <div id="mc-auth-join-root" class="h-full min-h-0"></div>
    @fluxScripts
</body>
</html>
