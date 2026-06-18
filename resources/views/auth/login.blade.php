@php
    $homeUrl = auth()->check() ? auth()->user()->homePath() : null;
@endphp

<x-layouts::auth :title="__('Log in')">
    <div class="flex w-full flex-col items-center font-['Montserrat']">
        <a
            href="{{ route('home') }}"
            class="flex w-full flex-col items-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
        >
            <span class="sr-only">{{ config('app.name', 'Meal Craft') }}</span>
            <x-application-logo mode="vertical" />
        </a>

        <header class="mc-auth-intro w-full">
            <h1 class="mc-auth-heading">{{ __('Welcome to Meal Craft') }}</h1>
        </header>

        @auth
            <div class="mt-8 w-full space-y-4 rounded-[12px] border border-[#5A6B44]/25 bg-[#5A6B44]/10 px-4 py-4 text-center text-sm text-[#262A22]">
                <p>{{ __('You are already signed in as :email.', ['email' => auth()->user()->email]) }}</p>
                @if ($homeUrl)
                    <a
                        href="{{ url($homeUrl) }}"
                        class="inline-flex min-h-[50px] items-center justify-center rounded-[12px] border-2 border-[#5A6B44] bg-[#5A6B44] px-8 text-base font-bold text-white"
                    >
                        {{ __('Continue to your account') }}
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="pt-1">
                    @csrf
                    <button type="submit" class="font-semibold text-[#5A6B44] underline underline-offset-2">
                        {{ __('Sign out and use a different account') }}
                    </button>
                </form>
            </div>
        @else
            @if ($errors->any())
                <div class="mc-auth-session w-full rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-800" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <x-auth-session-status class="mc-auth-session w-full text-center text-sm text-green-600" :status="session('status')" />

            @if (session('error'))
                <p class="mc-auth-session w-full rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-800" role="alert">
                    {{ session('error') }}
                </p>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mc-auth-form">
                @csrf

                <div class="mc-auth-email-stack">
                    <label for="email" class="mc-auth-label">{{ __('Email') }}</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="you@example.com"
                        class="h-[49px] w-full rounded-[12px] border border-[#E5E7EB] bg-white px-5 text-base text-[#364153] shadow-sm outline-none focus:border-[#6E8C47] focus:shadow-[0_1px_2px_rgba(0,0,0,0.05),inset_0_0_0_1px_rgba(110,140,71,0.18)]"
                    />
                    @error('email')
                        <p class="mc-auth-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mc-auth-password-stack">
                    <div class="mc-auth-password-label-row">
                        <label for="password" class="mc-auth-label">{{ __('Password') }}</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-medium text-[#5A6B44] underline underline-offset-2">
                                {{ __('Forgot password?') }}
                            </a>
                        @endif
                    </div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="h-[49px] w-full rounded-[12px] border border-[#E5E7EB] bg-white px-5 text-base text-[#364153] shadow-sm outline-none focus:border-[#6E8C47] focus:shadow-[0_1px_2px_rgba(0,0,0,0.05),inset_0_0_0_1px_rgba(110,140,71,0.18)]"
                    />
                    @error('password')
                        <p class="mc-auth-field-error">{{ $message }}</p>
                    @enderror

                    <div class="mc-auth-remember-row">
                        <input
                            id="remember"
                            type="checkbox"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                            class="mc-auth-checkbox"
                        />
                        <label for="remember" class="mc-auth-label">{{ __('Remember me') }}</label>
                    </div>
                </div>

                <div class="mc-auth-submit-wrap">
                    <button
                        type="submit"
                        class="mc-auth-login-submit h-[50px] rounded-[12px] border-2 border-[#5A6B44] bg-[#5A6B44] text-base font-bold text-white hover:bg-[#4F5F3D]"
                        data-test="login-button"
                    >
                        {{ __('Sign In') }}
                    </button>
                </div>
            </form>

            @if (Route::has('join'))
                <p class="mc-auth-footer">
                    <span class="font-medium">{{ __('New here?') }}</span>
                    <a href="{{ route('join') }}" class="font-bold text-[#5A6B44] underline underline-offset-2">{{ __('Sign up') }}</a>
                </p>
            @endif
        @endauth
    </div>
</x-layouts::auth>
