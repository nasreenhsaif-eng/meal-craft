@php
    /** @var array<string, mixed> $form */
    /** @var array<string, list<array{value: string, label: string}>> $options */
    $labelFor = static function (mixed $value, array $opts): string {
        $raw = $value === null || $value === '' ? '' : (string) $value;
        if ($raw === '') {
            return '—';
        }
        foreach ($opts as $opt) {
            if ((string) ($opt['value'] ?? '') === $raw) {
                return (string) ($opt['label'] ?? $raw);
            }
        }

        return $raw;
    };
    $defaultStart = now()->addWeek()->toDateString();
    $old = static function (string $key, mixed $default = '') use ($form) {
        return old($key, $form[$key] ?? $default);
    };
    $plannedStart = (string) $old('planned_start_date', $defaultStart);
    if ($plannedStart === '') {
        $plannedStart = $defaultStart;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Your details — Meal Craft</title>
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
            :show-footer="false"
        >
            <div class="flex items-start justify-between gap-3">
                <h1 class="min-w-0 font-montserrat text-3xl font-semibold tracking-tight text-brand-primary-pressed">Your details</h1>
                <a
                    href="{{ $homeUrl }}"
                    class="shrink-0 font-montserrat text-sm font-semibold uppercase tracking-wide text-[#5A6B44] underline"
                >
                    Close
                </a>
            </div>
            <p class="mt-3 font-body text-sm leading-relaxed text-[#555555]">
                Review your onboarding details, complete delivery information, and confirm everything is correct.
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-[12px] border border-red-200 bg-red-50 p-4" role="alert">
                    <p class="font-montserrat text-sm font-bold text-red-700">Please fix the following:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 font-body text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p
                id="checkout-client-error"
                class="mt-4 hidden rounded-[12px] border border-red-200 bg-red-50 p-3 font-body text-sm text-red-700"
                role="alert"
            ></p>

            <section class="mt-6 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Contact</h2>
                <dl class="mt-4 grid gap-3">
                    <div class="rounded-[12px] bg-[#F8F9F6] px-4 py-3">
                        <dt class="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">Name</dt>
                        <dd class="mt-1 font-body text-sm font-medium">
                            {{ trim(($form['first_name'] ?? '').' '.($form['last_name'] ?? '')) ?: '—' }}
                        </dd>
                    </div>
                    <div class="rounded-[12px] bg-[#F8F9F6] px-4 py-3">
                        <dt class="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">Email</dt>
                        <dd class="mt-1 font-body text-sm font-medium">{{ $form['email'] ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            @php
                $dob = (string) ($form['date_of_birth'] ?? '');
                $age = (string) ($form['age'] ?? '');
                $birthdayLabel = $dob !== ''
                    ? ($age !== '' ? $dob.' ('.$age.')' : $dob)
                    : ($age !== '' ? $age : '—');
                $calMin = (string) ($form['daily_calories_min'] ?? '');
                $calMax = (string) ($form['daily_calories_max'] ?? '');
                $calTarget = (string) ($form['daily_calorie_target'] ?? '');
                $calorieLabel = ($calMin !== '' && $calMax !== '')
                    ? number_format((int) $calMin).' – '.number_format((int) $calMax)
                    : ($calTarget !== '' ? number_format((int) $calTarget) : '—');
                $heightLabel = ($form['height_cm'] ?? '') !== '' ? $form['height_cm'].' cm' : '—';
                $weightLabel = ($form['weight_kg'] ?? '') !== '' ? $form['weight_kg'].' kg' : '—';
                $targetWeightLabel = ($form['target_weight_kg'] ?? '') !== '' ? $form['target_weight_kg'].' kg' : '—';
                $planBiometricRows = [
                    ['Date of birth / age', $birthdayLabel],
                    ['Gender', $labelFor($form['gender'] ?? '', $options['genders'] ?? [])],
                    ['Height', $heightLabel],
                    ['Weight', $weightLabel],
                    ['Target weight', $targetWeightLabel],
                    ['Activity level', $labelFor($form['activity_level'] ?? '', $options['activityLevels'] ?? [])],
                    ['Daily calorie range', $calorieLabel],
                    ['Macro style', $labelFor($form['macro_split_style'] ?? '', $options['macroSplitStyles'] ?? [])],
                    ['Type of plan', $labelFor($form['plan_type'] ?? '', $options['planTypes'] ?? [])],
                    ['Days', $labelFor((string) ($form['plan_days'] ?? ''), $options['planDays'] ?? [])],
                    ['Diet protocol', $labelFor($form['diet_protocol'] ?? '', $options['dietProtocols'] ?? [])],
                    ['Food filters / allergies', ($form['dislikes_and_allergies'] ?? '') !== '' ? $form['dislikes_and_allergies'] : '—'],
                ];
            @endphp
            <section class="mt-5 rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Plan &amp; Biometrics</h2>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($planBiometricRows as [$label, $value])
                        <div class="rounded-[12px] bg-[#F8F9F6] px-4 py-3{{ $label === 'Food filters / allergies' ? ' sm:col-span-2' : '' }}">
                            <dt class="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">{{ $label }}</dt>
                            <dd class="mt-1 font-body text-sm font-medium capitalize">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <form id="checkout-details-form" method="POST" action="{{ $submitUrl }}" class="mt-5 space-y-5">
                @csrf

                <section class="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Delivery &amp; contact</h2>
                    <div class="mt-4 grid gap-4">
                        <label class="block">
                            <span class="font-montserrat text-sm font-bold">Phone</span>
                            <input
                                type="tel"
                                name="phone"
                                value="{{ $old('phone') }}"
                                required
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                            >
                        </label>

                        <label class="block">
                            <span class="font-montserrat text-sm font-bold">Contact preference</span>
                            <select
                                name="contact_preference"
                                required
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                            >
                                <option value="">Select…</option>
                                @foreach ($options['contactPreferences'] ?? [] as $opt)
                                    <option
                                        value="{{ $opt['value'] }}"
                                        @selected((string) $old('contact_preference') === (string) $opt['value'])
                                    >
                                        {{ $opt['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="font-montserrat text-sm font-bold">Delivery time</span>
                            <select
                                name="delivery_time"
                                required
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                            >
                                <option value="">Select…</option>
                                @foreach ($options['deliveryTimes'] ?? [] as $opt)
                                    <option
                                        value="{{ $opt['value'] }}"
                                        @selected((string) $old('delivery_time') === (string) $opt['value'])
                                    >
                                        {{ $opt['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="font-montserrat text-sm font-bold">Planned starting date</span>
                            <input
                                type="date"
                                name="planned_start_date"
                                value="{{ $plannedStart }}"
                                required
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm @error('planned_start_date') border-red-500 @enderror"
                            >
                            @error('planned_start_date')
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="font-montserrat text-sm font-bold">Area</span>
                            <input
                                type="text"
                                name="area"
                                value="{{ $old('area') }}"
                                required
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                            >
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="font-montserrat text-sm font-bold">Block</span>
                                <input
                                    type="text"
                                    name="block"
                                    value="{{ $old('block') }}"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                                >
                            </label>
                            <label class="block">
                                <span class="font-montserrat text-sm font-bold">Road</span>
                                <input
                                    type="text"
                                    name="road"
                                    value="{{ $old('road') }}"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                                >
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="font-montserrat text-sm font-bold">House number</span>
                                <input
                                    type="text"
                                    name="house_number"
                                    value="{{ $old('house_number') }}"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                                >
                            </label>
                            <label class="block">
                                <span class="font-montserrat text-sm font-bold">Gate / flat</span>
                                <input
                                    type="text"
                                    name="gate_flat_number"
                                    value="{{ $old('gate_flat_number') }}"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                                >
                            </label>
                        </div>

                        <label class="block">
                            <span class="font-montserrat text-sm font-bold">Country</span>
                            <input
                                type="text"
                                name="country"
                                value="{{ $old('country', 'Bahrain') }}"
                                required
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-body text-sm"
                            >
                        </label>
                    </div>
                </section>

                <section class="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="m-0 font-montserrat text-base font-bold tracking-tight">Confirm</h2>
                    <label class="mt-4 flex cursor-pointer select-none items-start gap-3">
                        <input
                            type="checkbox"
                            name="declaration_accepted"
                            value="1"
                            required
                            @checked(old('declaration_accepted', $declarationAccepted))
                            class="mt-1 h-4 w-4 accent-[#5A6B44]"
                        >
                        <span class="font-montserrat text-sm font-bold uppercase leading-snug tracking-wide">
                            I declare that all the information that was provided is true and to the best of my
                            knowledge
                        </span>
                    </label>

                    <p class="mt-4 font-body text-xs text-[#555555]">
                        Unique ID: {{ $uniqueCode !== '' ? $uniqueCode : '—' }}
                        @if ($intakeSubmissionId !== '')
                            · Submission: {{ $intakeSubmissionId }}
                        @endif
                    </p>

                    <button
                        type="submit"
                        class="mt-6 flex w-full items-center justify-center rounded-md bg-[#5A6B44] px-4 py-3 font-montserrat text-sm font-bold uppercase tracking-[0.08em] text-white"
                    >
                        Confirm &amp; continue to payment
                    </button>
                </section>
            </form>

        </x-checkout.customer-shell>

        <script>
            (function () {
                var form = document.getElementById('checkout-details-form');
                var banner = document.getElementById('checkout-client-error');
                if (!form || !banner) {
                    return;
                }

                form.addEventListener('submit', function () {
                    banner.classList.add('hidden');
                    banner.textContent = '';
                });

                form.addEventListener(
                    'invalid',
                    function (event) {
                        event.preventDefault();
                        var field = event.target;
                        if (!(field instanceof HTMLElement)) {
                            return;
                        }
                        field.focus({ preventScroll: false });
                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        var label =
                            field.getAttribute('name') === 'planned_start_date'
                                ? 'Please choose a planned starting date.'
                                : field.getAttribute('name') === 'declaration_accepted'
                                  ? 'Please accept the declaration to continue.'
                                  : 'Please complete the highlighted field, then try again.';
                        banner.textContent = label;
                        banner.classList.remove('hidden');
                        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    },
                    true,
                );
            })();
        </script>
    </body>
</html>
