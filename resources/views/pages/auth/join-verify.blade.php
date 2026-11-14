@php
    $verifyConfig = [
        'formAction' => route('join.verify.store'),
        'resendAction' => route('join.verify.resend'),
        'csrfToken' => csrf_token(),
        'email' => $email ?? '',
        'maskedPhone' => $maskedPhone ?? '',
        'codeError' => $errors->first('code'),
        'statusMessage' => session('status') ?? '',
        'warningMessage' => session('warning') ?? '',
        'previewCode' => $previewCode ?? '',
        'deliveryWarning' => $deliveryWarning ?? '',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light h-full">
<head>
    @include('partials.head', [
        'viteEntries' => ['resources/css/app.css', 'resources/js/auth-join-verify.jsx'],
        'title' => __('Confirm your code'),
    ])
</head>
<body class="h-full min-h-[100dvh] bg-white antialiased">
    <script id="mc-auth-join-verify-config" type="application/json">@json($verifyConfig)</script>
    <div id="mc-auth-join-verify-root" class="h-full min-h-0"></div>
    @fluxScripts
</body>
</html>
