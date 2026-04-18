<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="xl">{{ __('Meal Craft Dashboard') }}</flux:heading>
                    <flux:text class="mt-1 text-neutral-600 dark:text-neutral-300">
                        {{ __('Nutrition ERP workspace with ingredient intelligence and admin controls.') }}
                    </flux:text>
                </div>
                <x-app-logo class="shrink-0" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                <flux:text class="text-sm text-neutral-500">{{ __('Brand primary (olive)') }}</flux:text>
                <flux:heading size="lg" class="mt-2 font-mono text-base">#6E8C47</flux:heading>
                <div class="mt-4 h-10 rounded-md bg-brand-primary"></div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                <flux:text class="text-sm text-neutral-500">{{ __('Brand secondary (gold)') }}</flux:text>
                <flux:heading size="lg" class="mt-2 font-mono text-base">#D8A933</flux:heading>
                <div class="mt-4 h-10 rounded-md bg-brand-secondary"></div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                <flux:text class="text-sm text-neutral-500">{{ __('Brand accent (femtech / highlights)') }}</flux:text>
                <flux:heading size="lg" class="mt-2 font-mono text-base">#8F55A8</flux:heading>
                <div class="mt-4 h-10 rounded-md bg-brand-accent"></div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:heading size="lg">{{ __('Quick Action') }}</flux:heading>
            <flux:text class="mt-1 text-neutral-600 dark:text-neutral-300">
                {{ __('Go directly to your ingredient library.') }}
            </flux:text>
            <div class="mt-4">
                <flux:button :href="route('ingredients.index')" wire:navigate>
                    {{ __('Open Ingredients') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-layouts::app>