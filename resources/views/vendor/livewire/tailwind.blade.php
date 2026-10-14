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
        <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-3">
            {{-- Mobile Pagination (< sm) --}}
            <div class="flex items-center justify-between flex-1 sm:hidden">
                <div>
                    @if ($paginator->onFirstPage())
                        <span class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-xl border border-ink-200/60 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02] text-ink-400 dark:text-ink-600 cursor-not-allowed opacity-60">
                            <span>&larr;</span>
                            <span>{!! __('pagination.previous') !!}</span>
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-xl border border-ink-200/80 dark:border-white/15 bg-white dark:bg-ink-900 text-ink-800 dark:text-ink-200 shadow-sm active:scale-95 transition-all duration-120 hover:border-up-primary hover:text-up-primary">
                            <span>&larr;</span>
                            <span>{!! __('pagination.previous') !!}</span>
                        </button>
                    @endif
                </div>

                <div class="text-xs text-ink-500 font-medium tabular-nums">
                    <span>{{ $paginator->currentPage() }}</span> / <span>{{ $paginator->lastPage() }}</span>
                </div>

                <div>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-xl border border-ink-200/80 dark:border-white/15 bg-white dark:bg-ink-900 text-ink-800 dark:text-ink-200 shadow-sm active:scale-95 transition-all duration-120 hover:border-up-primary hover:text-up-primary">
                            <span>{!! __('pagination.next') !!}</span>
                            <span>&rarr;</span>
                        </button>
                    @else
                        <span class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-xl border border-ink-200/60 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02] text-ink-400 dark:text-ink-600 cursor-not-allowed opacity-60">
                            <span>{!! __('pagination.next') !!}</span>
                            <span>&rarr;</span>
                        </span>
                    @endif
                </div>
            </div>

            {{-- Desktop Pagination (>= sm) --}}
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-xs text-ink-500 dark:text-ink-400">
                        <span>{!! __('Showing') !!}</span>
                        <span class="font-bold text-ink-900 dark:text-white tabular-nums">{{ $paginator->firstItem() ?? 0 }}</span>
                        <span>{!! __('to') !!}</span>
                        <span class="font-bold text-ink-900 dark:text-white tabular-nums">{{ $paginator->lastItem() ?? 0 }}</span>
                        <span>{!! __('of') !!}</span>
                        <span class="font-bold text-ink-900 dark:text-white tabular-nums">{{ $paginator->total() }}</span>
                        <span>{!! __('results') !!}</span>
                    </p>
                </div>

                <div>
                    <div class="inline-flex items-center gap-1 p-1 rounded-2xl bg-black/[0.03] dark:bg-white/[0.05] border border-ink-200/60 dark:border-white/10 shadow-sm">
                        {{-- Previous Page Link --}}
                        @if ($paginator->onFirstPage())
                            <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="w-8 h-8 rounded-xl flex items-center justify-center text-ink-300 dark:text-ink-600 cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </span>
                        @else
                            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="w-8 h-8 rounded-xl flex items-center justify-center text-ink-600 dark:text-ink-300 hover:text-up-primary hover:bg-white dark:hover:bg-white/10 transition-all duration-120 active:scale-95 cursor-pointer" aria-label="{{ __('pagination.previous') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                        @endif

                        {{-- Pagination Elements --}}
                        @foreach ($elements as $element)
                            {{-- "Three Dots" Separator --}}
                            @if (is_string($element))
                                <span aria-disabled="true" class="w-8 h-8 flex items-center justify-center text-xs text-ink-400 dark:text-ink-500 font-medium">
                                    {{ $element }}
                                </span>
                            @endif

                            {{-- Array Of Links --}}
                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page" class="w-8 h-8 rounded-xl bg-up-primary text-white text-xs font-bold flex items-center justify-center shadow-sm shadow-up-primary/30">
                                                {{ $page }}
                                            </span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="w-8 h-8 rounded-xl text-xs font-semibold text-ink-700 dark:text-ink-300 hover:bg-white dark:hover:bg-white/10 hover:text-ink-950 dark:hover:text-white transition-all duration-120 active:scale-95 cursor-pointer" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        {{-- Next Page Link --}}
                        @if ($paginator->hasMorePages())
                            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="w-8 h-8 rounded-xl flex items-center justify-center text-ink-600 dark:text-ink-300 hover:text-up-primary hover:bg-white dark:hover:bg-white/10 transition-all duration-120 active:scale-95 cursor-pointer" aria-label="{{ __('pagination.next') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        @else
                            <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="w-8 h-8 rounded-xl flex items-center justify-center text-ink-300 dark:text-ink-600 cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </nav>
    @endif
</div>
