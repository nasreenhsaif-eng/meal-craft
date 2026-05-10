<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @include('partials.admin-intro-inline')
    </head>
    <body class="min-h-screen bg-mc-cream dark:bg-zinc-900">
        <div id="mc-admin-shell" class="min-h-screen">
        <flux:sidebar sticky collapsible="mobile" class="meal-craft-admin-sidebar">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="carrot" :href="route('ingredients.index')" :current="request()->routeIs('ingredients.index')" wire:navigate>
                        {{ __('Ingredients') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('meals.index')" :current="request()->routeIs('meals.*')" wire:navigate>
                        {{ __('Meal Library') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('meal-plans.index')" :current="request()->routeIs('meal-plans.index')" wire:navigate>
                        {{ __('Meal Plans (weekly)') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="squares-2x2" :href="route('meal-plans.four-week')" :current="request()->routeIs('meal-plans.four-week')" wire:navigate>
                        {{ __('4-week plans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('consultation.crafted-for-you')" :current="request()->routeIs('consultation.crafted-for-you')">
                        {{ __('Consultation') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="sparkles">
                    {{ __('Meal Craft Nutrition ERP') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="meal-craft-admin-header lg:hidden">
            <flux:sidebar.toggle class="lg:hidden text-white hover:bg-white/10" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    class="text-white"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        </div>
        <div id="mc-intro-root"></div>

        @fluxScripts
        @vite(['resources/js/admin-intro.jsx'])
    </body>
</html>
