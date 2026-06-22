<x-layouts::auth :title="__('Sign out')">
    <div class="flex w-full max-w-[492px] flex-col items-center gap-8 text-center font-['Montserrat']">
        <a href="{{ auth()->check() ? route('sign-out') : route('login') }}" class="flex w-full flex-col items-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2">
            <span class="sr-only">{{ config('app.name', 'Meal Craft') }}</span>
            <x-application-logo mode="vertical" />
        </a>

        @auth
            <div class="flex w-full flex-col gap-[19px]">
                <h1 class="text-2xl font-semibold leading-tight text-black">{{ __('Sign out') }}</h1>
                <p class="text-base font-normal leading-normal text-black">
                    {{ __('You are signed in as :email. Sign out to create a customer account or sign in with a different account.', ['email' => auth()->user()->email]) }}
                </p>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="flex w-full flex-col gap-4">
                @csrf
                <x-mc-pill-button type="submit" class="min-w-[190px] w-full" data-test="logout-button">
                    {{ __('Log out') }}
                </x-mc-pill-button>
            </form>

            <p class="text-sm text-[#555555]">
                {{ __('After signing out, go to') }}
                <a href="{{ route('join') }}" class="font-semibold text-[#556C37] underline underline-offset-2">{{ __('customer signup') }}</a>
            </p>
        @else
            <div class="flex w-full flex-col gap-[19px]">
                <h1 class="text-2xl font-semibold leading-tight text-black">{{ __('You are not signed in') }}</h1>
                <p class="text-base font-normal leading-normal text-black">
                    {{ __('No account session was found in this browser. You can sign up as a customer or log in.') }}
                </p>
            </div>

            <div class="flex w-full flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('join') }}" class="inline-flex min-w-[190px] items-center justify-center rounded-full bg-[#6E8C47] px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#5A6B44] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2">
                    {{ __('Customer signup') }}
                </a>
                <a href="{{ route('login') }}" class="inline-flex min-w-[190px] items-center justify-center rounded-full border border-[#6E8C47] px-6 py-3 text-sm font-semibold text-[#556C37] transition-colors hover:bg-[#6E8C47]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2">
                    {{ __('Log in') }}
                </a>
            </div>
        @endauth
    </div>
</x-layouts::auth>
