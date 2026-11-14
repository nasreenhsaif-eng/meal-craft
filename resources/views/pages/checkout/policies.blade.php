<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Policies — Meal Craft</title>
        @php
            $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
            $entry = $manifest['resources/js/admin-inertia.jsx'];
            $cssFiles = $entry['css'] ?? [];
        @endphp
        @foreach ($cssFiles as $cssFile)
            <link rel="stylesheet" href="/build/{{ $cssFile }}">
        @endforeach
    </head>
    <body class="m-0 min-h-dvh w-full bg-gray-50 antialiased text-[#262A22] max-[429px]:bg-white">
        <x-checkout.customer-shell
            :customer-name="$customerName"
            :home-url="$homeUrl"
            :meal-plan-summary-url="$mealPlanSummaryUrl"
            :shop-url="$shopUrl"
            :profile-url="$profileUrl"
        >
            <div class="flex items-start justify-between gap-3">
                <h1 class="min-w-0 font-montserrat text-3xl font-semibold tracking-tight text-brand-primary-pressed">
                    Policies
                </h1>
                <a
                    href="{{ $paymentUrl }}"
                    class="shrink-0 font-montserrat text-sm font-semibold uppercase tracking-wide text-[#5A6B44] underline"
                >
                    Back
                </a>
            </div>
            <p class="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                Freeze and refund terms for your Meal Craft package.
            </p>

            <section id="freeze" class="mt-6 scroll-mt-4 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Freeze policy</h2>
                <p class="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                    If you wish to freeze your package, the packages are calculated as a 20 day package and a 24 day
                    package. The maximum total number of days to be frozen are 14 days.
                </p>
            </section>

            <section id="refund" class="mt-5 scroll-mt-4 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Refund policy</h2>
                <p class="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                    As we do not offer a refund policy, we suggest that you do a 5 day trial, if for any reason during
                    the 20 day package you would like to stop please keep in mind that the credit is transferable to a
                    loved one.
                </p>
                <p class="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                    By making the payment, you agree to the Refund Policy.
                </p>
            </section>
        </x-checkout.customer-shell>
    </body>
</html>
