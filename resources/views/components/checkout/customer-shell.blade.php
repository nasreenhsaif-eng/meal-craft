@props([
    'customerName' => '',
    'homeUrl' => '/app',
    'mealPlanSummaryUrl' => '/app/meal-plan',
    'shopUrl' => '/app/shop',
    'profileUrl' => '/app/profile',
    'activeTab' => 'meals',
    'showFooter' => true,
])
{{-- Matches React CustomerLayout + CustomerOnboardingPhoneFrame. --}}
<div
    class="flex min-h-dvh w-full flex-col items-center justify-start bg-gray-50 px-3 py-4 font-sans text-[#262A22] min-[430px]:px-8 min-[430px]:py-10 max-[429px]:bg-white max-[429px]:px-0 max-[429px]:py-0"
    data-customer-onboarding-root
>
    <div
        class="relative mx-auto flex h-[min(844px,100dvh-5rem)] w-full max-w-[430px] flex-col overflow-clip rounded-[28px] border border-gray-200 bg-white shadow-lg max-[429px]:h-dvh max-[429px]:max-h-dvh max-[429px]:rounded-none max-[429px]:border-0 max-[429px]:shadow-none px-3 pt-3"
        data-customer-onboarding-frame
    >
        <div class="relative flex h-full min-h-0 w-full flex-1 flex-col bg-[#F8F9F6] font-sans text-[#262A22]">
            <header class="flex w-full shrink-0 items-center justify-between gap-3 border-b border-gray-100 bg-white px-1 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    {{-- Same seal-xs sprite as React MealCraftLogo (viewBox 72 515 84 84). --}}
                    <span class="inline-flex shrink-0 overflow-hidden">
                        <svg
                            class="block shrink-0 overflow-hidden"
                            viewBox="72 515 84 84"
                            width="40"
                            height="40"
                            role="img"
                            aria-label="Meal Craft"
                            xmlns="http://www.w3.org/2000/svg"
                            style="fill: none; overflow: hidden; contain: paint;"
                            preserveAspectRatio="xMidYMid meet"
                        >
                            <image
                                href="/images/branding/logo-sheet.svg"
                                x="0"
                                y="0"
                                width="1340"
                                height="1260"
                                preserveAspectRatio="none"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="font-montserrat text-base font-bold tracking-tight">Meal Craft</p>
                        <p class="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-[#555555]">
                            Your plan
                        </p>
                    </div>
                </div>
                @if ($customerName !== '')
                    <p class="max-w-[45%] shrink-0 truncate text-right text-sm font-medium text-[#555555]">
                        {{ $customerName }}
                    </p>
                @endif
            </header>

            <main @class([
                'mx-auto min-h-0 w-full flex-1 overflow-y-auto overscroll-contain px-1 py-4',
                'pb-28' => $showFooter,
                'pb-4' => ! $showFooter,
            ])>
                {{ $slot }}
            </main>

            @if ($showFooter)
                <nav
                    class="absolute inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur-sm pb-[max(0.5rem,env(safe-area-inset-bottom))]"
                    aria-label="Customer app"
                >
                    <ul class="mx-auto flex w-full items-stretch justify-between px-1 pt-1 sm:px-2">
                        @foreach ([
                            ['id' => 'home', 'label' => 'Home', 'href' => $homeUrl],
                            ['id' => 'meals', 'label' => 'Meal Plan', 'href' => $mealPlanSummaryUrl],
                            ['id' => 'shop', 'label' => 'Shop', 'href' => $shopUrl],
                            ['id' => 'profile', 'label' => 'Profile', 'href' => $profileUrl],
                        ] as $item)
                            @php $isActive = $activeTab === $item['id']; @endphp
                            <li class="min-w-0 flex-1">
                                <a
                                    href="{{ $item['href'] }}"
                                    @if ($isActive) aria-current="page" @endif
                                    class="flex min-h-[3.5rem] flex-col items-center justify-center gap-0.5 rounded-[12px] px-1 py-1.5 font-montserrat text-[10px] font-bold uppercase tracking-[0.06em] sm:text-[11px] sm:tracking-[0.08em] transition-colors {{ $isActive ? 'bg-[#5A6B44]/10 text-[#5A6B44]' : 'text-[#6B7280] hover:bg-[#F8F9F6] hover:text-[#374151]' }}"
                                >
                                    @if ($item['id'] === 'home')
                                        <svg class="shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 11.5 12 4l8 7.5V20a2 2 0 0 1-2 2h-4v-6H10v6H6a2 2 0 0 1-2-2v-8.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>
                                    @elseif ($item['id'] === 'meals')
                                        <svg class="shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h2l1 5M15 15h4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @elseif ($item['id'] === 'shop')
                                        <svg class="shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7ZM9 7V5a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @else
                                        <svg class="shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @endif
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
</div>
