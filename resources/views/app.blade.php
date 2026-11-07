<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/admin-inertia.jsx'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
        <script>
            // If React/Inertia never mounts (broken Vite HMR / blank #app), show a recovery UI.
            window.addEventListener('load', function () {
                window.setTimeout(function () {
                    var app = document.getElementById('app');
                    if (!app) {
                        return;
                    }
                    var text = (app.innerText || '').replace(/\s+/g, ' ').trim();
                    if (text.length > 0) {
                        return;
                    }
                    app.innerHTML =
                        '<div style="padding:24px;font-family:system-ui,sans-serif;max-width:28rem;margin:2rem auto;color:#262A22">' +
                        '<h1 style="font-size:1.25rem;font-weight:700;margin:0 0 0.75rem">Page did not load</h1>' +
                        '<p style="margin:0 0 1rem;line-height:1.5;color:#555">The app shell stayed blank. Use a hard refresh, or open payment directly.</p>' +
                        '<p style="margin:0;display:flex;gap:1rem;flex-wrap:wrap">' +
                        '<a href="' + location.href + '" style="font-weight:700;color:#5A6B44">Reload this page</a>' +
                        '<a href="/checkout/payment" style="font-weight:700;color:#5A6B44">Open payment</a>' +
                        '</p></div>';
                }, 3000);
            });
        </script>
    </body>
</html>
