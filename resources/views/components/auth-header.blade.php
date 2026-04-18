@props([
    'title',
    'description',
])

<div class="flex w-full flex-col items-center text-center font-['Montserrat']">
    <div class="mb-6 flex w-full justify-center">
        <x-application-logo mode="vertical" class="max-w-[200px]" />
    </div>
    <h1 class="text-2xl font-semibold leading-tight text-black">{{ $title }}</h1>
    <p class="mt-4 max-w-[492px] text-base font-normal leading-normal text-black">{{ $description }}</p>
</div>
