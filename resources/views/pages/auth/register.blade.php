<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col items-center gap-10 font-['Montserrat']">
        <a href="{{ route('home') }}" wire:navigate class="flex w-full flex-col items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2 rounded-lg">
            <span class="sr-only">{{ config('app.name', 'Meal Craft') }}</span>
            <x-application-logo mode="vertical" />
        </a>

        <div class="flex w-full max-w-[492px] flex-col items-center gap-[19px] text-center">
            <h1 class="text-2xl font-semibold leading-tight text-black">{{ __('Create an account') }}</h1>
            <p class="text-base font-normal leading-normal text-black">{{ __('Enter your details below to create your account') }}</p>
        </div>

        <x-auth-session-status class="w-full max-w-[492px] text-center text-sm text-red-600" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex w-full max-w-[492px] flex-col gap-8">
            @csrf

            <div class="flex w-full flex-col gap-2.5">
                <label class="text-base font-medium leading-3 text-black" for="name">{{ __('Name') }}</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="{{ __('Full name') }}"
                    class="h-[50px] w-full rounded-[15px] border border-[#E5E7EB] bg-white px-[18px] font-['Montserrat'] text-xs font-normal text-[#364153] placeholder:font-['Montserrat'] placeholder:text-[#364153] focus:border-[#6E8C47] focus:outline-none focus:ring-2 focus:ring-[#6E8C47]/30"
                />
                @error('name')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex w-full flex-col gap-2.5">
                <label class="text-base font-medium leading-3 text-black" for="email">{{ __('Email address') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                    class="h-[50px] w-full rounded-[15px] border border-[#E5E7EB] bg-white px-[18px] font-['Montserrat'] text-xs font-normal text-[#364153] placeholder:font-['Montserrat'] placeholder:text-[#364153] focus:border-[#6E8C47] focus:outline-none focus:ring-2 focus:ring-[#6E8C47]/30"
                />
                @error('email')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex w-full flex-col gap-2.5">
                <label class="text-base font-medium leading-3 text-black" for="password">{{ __('Password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Password') }}"
                    class="h-[50px] w-full rounded-[15px] border border-[#E5E7EB] bg-white px-[18px] font-['Montserrat'] text-xs font-normal text-[#364153] placeholder:font-['Montserrat'] placeholder:text-[#364153] focus:border-[#6E8C47] focus:outline-none focus:ring-2 focus:ring-[#6E8C47]/30"
                />
                @error('password')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex w-full flex-col gap-2.5">
                <label class="text-base font-medium leading-3 text-black" for="password_confirmation">{{ __('Confirm password') }}</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Confirm password') }}"
                    class="h-[50px] w-full rounded-[15px] border border-[#E5E7EB] bg-white px-[18px] font-['Montserrat'] text-xs font-normal text-[#364153] placeholder:font-['Montserrat'] placeholder:text-[#364153] focus:border-[#6E8C47] focus:outline-none focus:ring-2 focus:ring-[#6E8C47]/30"
                />
            </div>

            <div class="flex justify-center pt-2">
                <x-mc-pill-button type="submit" class="min-w-[190px]" data-test="register-user-button">
                    {{ __('Create account') }}
                </x-mc-pill-button>
            </div>
        </form>

        <div class="flex w-full max-w-[492px] flex-wrap items-center justify-center gap-1 text-center text-base font-medium text-black">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="text-[#6E8C47] underline underline-offset-2 hover:text-[#5a7539]">
                {{ __('Log in') }}
            </a>
        </div>
    </div>
</x-layouts::auth>
