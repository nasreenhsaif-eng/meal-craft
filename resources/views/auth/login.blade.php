{{-- Figma: Meal-Craft-App → Log in screen (2485:4605). Slot uses display:contents so .mc-auth-card gap applies between sections. --}}
<x-layouts::auth :title="__('Log in')">
    <div class="mc-auth-slot-contents">
        <a href="{{ route('home') }}" wire:navigate class="mc-auth-logo-link">
            <span class="sr-only">{{ config('branding.name', config('app.name', 'Meal Craft')) }}</span>
            <x-application-logo mode="vertical" />
        </a>

        <div class="mc-auth-intro">
            <h1 class="mc-auth-heading">{{ __('Log in to your account') }}</h1>
            <p class="mc-auth-subheading">{{ __('Enter your email and password below to log in') }}</p>
        </div>

        <x-auth-session-status class="mc-auth-col mc-auth-session text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="mc-auth-form">
            @csrf

            <div class="mc-auth-email-stack">
                <label class="mc-auth-label" for="email">{{ __('Email address') }}</label>
                <x-mc-input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                @error('email')
                    <p class="mc-auth-field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="mc-auth-password-stack">
                <div class="mc-auth-password-label-row">
                    <label class="mc-auth-label" for="password">{{ __('Password') }}</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate class="mc-auth-link">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                </div>
                <x-mc-input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Password') }}"
                />
                @error('password')
                    <p class="mc-auth-field-error">{{ $message }}</p>
                @enderror
                <div class="mc-auth-remember-row">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        value="1"
                        @checked(old('remember'))
                        class="mc-auth-checkbox"
                    />
                    <label class="mc-auth-label" for="remember">{{ __('Remember me') }}</label>
                </div>
            </div>

            <div class="mc-auth-submit-wrap">
                <x-mc-pill-button type="submit" class="mc-auth-login-submit" data-test="login-button">
                    {{ __('Log in') }}
                </x-mc-pill-button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="mc-auth-footer">
                <span>{{ __('Don\'t have an account?') }}</span>
                <a href="{{ route('register') }}" wire:navigate>{{ __('Sign up') }}</a>
            </div>
        @endif
    </div>
</x-layouts::auth>
