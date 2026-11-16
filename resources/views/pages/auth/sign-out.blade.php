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

            <form method="POST" action="{{ route('logout') }}" class="flex w-full justify-center">
                @csrf
                <button
                    type="submit"
                    data-test="logout-button"
                    class="inline-flex h-[50px] min-h-[50px] min-w-[190px] cursor-pointer items-center justify-center rounded-[12px] border border-transparent bg-[#5A6B44] px-6 font-montserrat text-[16px] font-bold uppercase leading-none tracking-wider text-white shadow-sm transition-all duration-200 ease-in-out hover:scale-[1.02] hover:bg-[#485636] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 focus-visible:ring-offset-white active:scale-[0.98] active:bg-[#485636] active:shadow-inner"
                >
                    {{ __('Log out') }}
                </button>
            </form>
        @else
            <div class="flex w-full flex-col gap-[19px]">
                <h1 class="text-2xl font-semibold leading-tight text-black">{{ __('You are not signed in') }}</h1>
                <p class="text-base font-normal leading-normal text-black">
                    {{ __('No account session was found in this browser. You can sign up as a customer or log in.') }}
                </p>
            </div>

            <div class="flex w-full flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('join') }}" class="inline-flex h-[50px] min-h-[50px] min-w-[190px] items-center justify-center rounded-[12px] border border-transparent bg-[#5A6B44] px-6 font-montserrat text-[16px] font-bold uppercase leading-none tracking-wider text-white shadow-sm transition-all duration-200 ease-in-out hover:scale-[1.02] hover:bg-[#485636] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 focus-visible:ring-offset-white active:scale-[0.98] active:bg-[#485636] active:shadow-inner">
                    {{ __('Customer signup') }}
                </a>
                <a href="{{ route('login') }}" class="inline-flex h-[50px] min-h-[50px] min-w-[190px] items-center justify-center rounded-[12px] border border-transparent bg-transparent px-6 font-montserrat text-[16px] font-bold uppercase leading-none tracking-wider text-[#5A6B44] transition-all duration-200 ease-in-out hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 focus-visible:ring-offset-white">
                    {{ __('Log in') }}
                </a>
            </div>
        @endauth
    </div>
</x-layouts::auth>
