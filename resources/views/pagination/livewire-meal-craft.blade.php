@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
            <div class="flex flex-1 justify-between sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <span class="relative inline-flex cursor-default items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                            {!! __('pagination.previous') !!}
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="previousPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before"
                            class="relative inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-neutral-800 transition duration-150 ease-in-out hover:bg-neutral-50 focus:border-[var(--color-brand-gold)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-gold)]/35 active:bg-neutral-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:active:bg-neutral-800"
                        >
                            {!! __('pagination.previous') !!}
                        </button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button
                            type="button"
                            wire:click="nextPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before"
                            class="relative ml-3 inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-neutral-800 transition duration-150 ease-in-out hover:bg-neutral-50 focus:border-[var(--color-brand-gold)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-gold)]/35 active:bg-neutral-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:active:bg-neutral-800"
                        >
                            {!! __('pagination.next') !!}
                        </button>
                    @else
                        <span class="relative ml-3 inline-flex cursor-default items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-500">
                            {!! __('pagination.next') !!}
                        </span>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm leading-5 text-neutral-600 dark:text-neutral-400">
                        <span>{!! __('Showing') !!}</span>
                        <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $paginator->firstItem() }}</span>
                        <span>{!! __('to') !!}</span>
                        <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $paginator->lastItem() }}</span>
                        <span>{!! __('of') !!}</span>
                        <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $paginator->total() }}</span>
                        <span>{!! __('results') !!}</span>
                    </p>
                </div>

                <div>
                    <span class="relative z-0 inline-flex rounded-md shadow-sm rtl:flex-row-reverse">
                        <span>
                            @if ($paginator->onFirstPage())
                                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                                    <span class="relative inline-flex cursor-default items-center rounded-l-md border border-neutral-300 bg-white px-2 py-2 text-sm font-medium leading-5 text-neutral-400 dark:border-neutral-600 dark:bg-neutral-800" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @else
                                <button
                                    type="button"
                                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after"
                                    class="relative inline-flex items-center rounded-l-md border border-neutral-300 bg-white px-2 py-2 text-sm font-medium leading-5 text-neutral-600 transition duration-150 ease-in-out hover:bg-neutral-50 focus:z-10 focus:border-[var(--color-brand-gold)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-gold)]/35 active:bg-neutral-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:active:bg-neutral-800"
                                    aria-label="{{ __('pagination.previous') }}"
                                >
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>

                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <span aria-disabled="true">
                                    <span class="relative -ml-px inline-flex cursor-default items-center border border-neutral-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-neutral-600 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">{{ $element }}</span>
                                </span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page">
                                                <span class="relative -ml-px inline-flex cursor-default items-center border border-neutral-400 bg-[var(--color-brand-gold)] px-4 py-2 text-sm font-semibold leading-5 text-neutral-900 dark:border-neutral-500">{{ $page }}</span>
                                            </span>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                                class="relative -ml-px inline-flex items-center border border-neutral-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-neutral-700 transition duration-150 ease-in-out hover:bg-neutral-50 focus:z-10 focus:border-[var(--color-brand-gold)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-gold)]/35 active:bg-neutral-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:active:bg-neutral-800"
                                                aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                            >
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        <span>
                            @if ($paginator->hasMorePages())
                                <button
                                    type="button"
                                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after"
                                    class="relative -ml-px inline-flex items-center rounded-r-md border border-neutral-300 bg-white px-2 py-2 text-sm font-medium leading-5 text-neutral-600 transition duration-150 ease-in-out hover:bg-neutral-50 focus:z-10 focus:border-[var(--color-brand-gold)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-gold)]/35 active:bg-neutral-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:active:bg-neutral-800"
                                    aria-label="{{ __('pagination.next') }}"
                                >
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @else
                                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                                    <span class="relative -ml-px inline-flex cursor-default items-center rounded-r-md border border-neutral-300 bg-white px-2 py-2 text-sm font-medium leading-5 text-neutral-400 dark:border-neutral-600 dark:bg-neutral-800" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
