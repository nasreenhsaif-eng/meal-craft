@php
    /** @var array<string, mixed> $basket */
    $currency = $basket['currency'] ?? 'BHD';
    $formatMoney = static function (mixed $amount) use ($currency): string {
        $value = is_numeric($amount) ? (float) $amount : null;

        if ($value === null) {
            return $currency.' —';
        }

        return $currency.' '.number_format($value, 3, '.', '');
    };
    $address = $basket['deliveryAddress'] ?? [];
    $benefitPay = $basket['benefitPayNumber'] ?? '33177718';
    $refundPolicyUrl = $basket['refundPolicyUrl'] ?? route('policies.refund').'#refund';
    $freezePolicyUrl = $basket['freezePolicyUrl'] ?? route('policies.refund').'#freeze';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Payment checkout — Meal Craft</title>
        @php
            $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
            $entry = $manifest['resources/js/admin-inertia.jsx'];
            $cssFiles = $entry['css'] ?? [];
        @endphp
        @foreach ($cssFiles as $cssFile)
            <link rel="stylesheet" href="/build/{{ $cssFile }}">
        @endforeach
    </head>
    <body class="m-0 antialiased text-[#262A22]">
        <x-checkout.customer-shell
            :customer-name="$customerName"
            :home-url="$homeUrl"
            :meal-plan-summary-url="$mealPlanSummaryUrl"
            :shop-url="$shopUrl"
            :profile-url="$profileUrl"
        >
            <div class="flex items-start justify-between gap-3">
                <h1 class="min-w-0 font-montserrat text-3xl font-semibold tracking-tight text-brand-primary-pressed">
                    Payment checkout
                </h1>
                <a
                    href="{{ $detailsUrl }}"
                    class="shrink-0 font-montserrat text-sm font-semibold uppercase tracking-wide text-[#5A6B44] underline"
                >
                    Close
                </a>
            </div>
            <p class="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                Review your basket, confirm delivery, and pay with Benefit Pay.
            </p>

            @if (session('success'))
                <p class="mt-4 font-body text-sm font-medium text-[#5A6B44]">{{ session('success') }}</p>
            @endif
            @if (session('consultation_resume_choose_meals') || session('consultation_resume_draft') || session('checkout_resume_choose_meals'))
                <p class="mt-3 font-body text-sm text-[#555555]">
                    <a
                        href="{{ route('consultation.crafted-for-you', ['choose_meals' => 1]) }}"
                        class="font-semibold text-[#5A6B44] underline underline-offset-2"
                    >
                        Continue to Choose your meals
                    </a>
                </p>
            @endif
            @if (session('error'))
                <p class="mt-4 font-body text-sm font-medium text-red-600" role="alert">{{ session('error') }}</p>
            @endif

            <section class="mt-6 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Basket summary</h2>
                <p class="mt-3 font-body text-sm font-medium">{{ $basket['planLabel'] ?? 'Meal plan' }}</p>

                <dl class="mt-5 space-y-3 border-t border-gray-100 pt-4 font-body text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-[#555555]">Subtotal</dt>
                        <dd class="font-semibold">{{ $formatMoney($basket['subtotal'] ?? 0) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-[#555555]">Delivery cost</dt>
                        <dd class="font-semibold">{{ $formatMoney($basket['deliveryCost'] ?? 0) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-[#555555]">
                            Total discount
                            <span class="mt-0.5 block text-xs font-normal text-[#5A6B44]">
                                {{ $basket['limitedTimeDiscountLabel'] ?? 'Limited time offer' }}
                            </span>
                        </dt>
                        <dd class="font-semibold text-[#5A6B44]">
                            −{{ $formatMoney($basket['limitedTimeDiscount'] ?? 0) }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 border-t border-gray-100 pt-3">
                        <dt class="font-montserrat text-base font-bold">Total</dt>
                        <dd class="font-montserrat text-base font-bold">{{ $formatMoney($basket['total'] ?? 0) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="mt-5 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Delivery address</h2>
                <p class="mt-3 font-body text-sm leading-relaxed">
                    {{ $address['formatted'] ?? '—' }}
                </p>
            </section>

            <section class="mt-5 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Payment options</h2>
                <p class="mt-4 font-montserrat text-sm font-bold uppercase tracking-wide text-[#5A6B44]">
                    Benefit Pay to {{ $benefitPay }}
                </p>

                {{-- novalidate: keep required checks inline so browser tooltips cannot escape the phone frame --}}
                <form
                    id="mc-checkout-payment-form"
                    method="POST"
                    action="{{ $submitUrl }}"
                    class="mt-6 space-y-4"
                    novalidate
                >
                    @csrf
                    <input type="hidden" name="payment_method" value="benefit_pay">
                    <input type="hidden" name="invoice_same_address" value="1">

                    <div class="space-y-4 rounded-[12px] bg-[#F8F9F6] p-4">
                        <div>
                            <h3 class="m-0 font-montserrat text-sm font-bold tracking-tight">
                                <a
                                    href="{{ $freezePolicyUrl }}"
                                    class="text-[#5A6B44] underline decoration-[#5A6B44]/40 underline-offset-2"
                                >
                                    Freeze policy
                                </a>
                            </h3>
                            <p class="mt-2 font-body text-sm leading-relaxed text-[#555555]">
                                If you wish to freeze your package, the packages are calculated as a 20 day package and
                                a 24 day package. The maximum total number of days to be frozen are 14 days.
                            </p>
                        </div>
                        <div>
                            <h3 class="m-0 font-montserrat text-sm font-bold tracking-tight">
                                <a
                                    href="{{ $refundPolicyUrl }}"
                                    class="text-[#5A6B44] underline decoration-[#5A6B44]/40 underline-offset-2"
                                >
                                    Refund policy
                                </a>
                            </h3>
                            <p class="mt-2 font-body text-sm leading-relaxed text-[#555555]">
                                As we do not offer a refund policy, we suggest that you do a 5 day trial, if for any
                                reason during the 20 day package you would like to stop please keep in mind that the
                                credit is transferable to a loved one.
                            </p>
                        </div>
                    </div>

                    {{-- Design-system checkbox: resources/css/app.css `.mc-auth-checkbox` (Storybook SquareCheckbox is React-only) --}}
                    <div>
                        <label for="mc-cart-confirmed" class="flex cursor-pointer select-none items-start gap-2.5">
                            <input
                                id="mc-cart-confirmed"
                                type="checkbox"
                                name="cart_confirmed"
                                value="1"
                                class="mc-auth-checkbox mt-0.5 shrink-0 outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-1"
                            >
                            <span class="font-montserrat text-sm font-bold uppercase leading-snug tracking-wide text-[#262A22]">
                                Confirm cart
                            </span>
                        </label>
                        <p
                            id="mc-cart-confirmed-error"
                            class="mt-2 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 font-body text-sm leading-snug text-[#262A22]"
                            role="alert"
                        >
                            Please check this box if you want to proceed.
                        </p>
                        @error('cart_confirmed')
                            <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="mc-refund-policy-accepted" class="flex cursor-pointer select-none items-start gap-2.5">
                            <input
                                id="mc-refund-policy-accepted"
                                type="checkbox"
                                name="refund_policy_accepted"
                                value="1"
                                class="mc-auth-checkbox mt-0.5 shrink-0 outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-1"
                            >
                            <span class="font-body text-sm leading-snug text-[#555555]">
                                By making the payment, you agree to the
                                <a
                                    href="{{ $refundPolicyUrl }}"
                                    class="font-semibold text-[#5A6B44] underline decoration-[#5A6B44]/40 underline-offset-2"
                                    onclick="event.stopPropagation()"
                                >
                                    Refund Policy
                                </a>.
                            </span>
                        </label>
                        <p
                            id="mc-refund-policy-error"
                            class="mt-2 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 font-body text-sm leading-snug text-[#262A22]"
                            role="alert"
                        >
                            Please check this box if you want to proceed.
                        </p>
                        @error('refund_policy_accepted')
                            <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-[#5A6B44] px-4 py-3 font-montserrat text-sm font-bold uppercase tracking-[0.08em] text-white"
                    >
                        Make a payment
                    </button>
                </form>
                <script>
                    (() => {
                        const form = document.getElementById('mc-checkout-payment-form');
                        if (!form) {
                            return;
                        }

                        const cart = document.getElementById('mc-cart-confirmed');
                        const refund = document.getElementById('mc-refund-policy-accepted');
                        const cartError = document.getElementById('mc-cart-confirmed-error');
                        const refundError = document.getElementById('mc-refund-policy-error');

                        const setVisible = (el, visible) => {
                            if (!el) {
                                return;
                            }
                            el.classList.toggle('hidden', !visible);
                        };

                        const syncErrors = () => {
                            setVisible(cartError, cart && !cart.checked);
                            setVisible(refundError, refund && !refund.checked);
                        };

                        cart?.addEventListener('change', () => setVisible(cartError, false));
                        refund?.addEventListener('change', () => setVisible(refundError, false));

                        form.addEventListener('submit', (event) => {
                            const cartOk = Boolean(cart?.checked);
                            const refundOk = Boolean(refund?.checked);

                            if (cartOk && refundOk) {
                                return;
                            }

                            event.preventDefault();
                            syncErrors();
                            (cartOk ? refund : cart)?.focus();
                        });
                    })();
                </script>
            </section>
        </x-checkout.customer-shell>
    </body>
</html>
