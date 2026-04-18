{{-- Document <head> (incl. Google Fonts Montserrat) is in layouts/app/sidebar.blade.php → partials/head.blade.php --}}
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
