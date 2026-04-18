{{--
    Auth shell: page uses Montserrat via --font-sans (partials/head + app.css).
    Login panel matches Figma node 2485:4605 — outer padding centers the card on a white viewport.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head')
    </head>
    <body class="mc-auth-body">
        <div class="mc-auth-outer">
            <div class="mc-auth-card" data-figma-node="2485:4605">
                {{ $slot }}
            </div>
        </div>
        @fluxScripts
    </body>
</html>
