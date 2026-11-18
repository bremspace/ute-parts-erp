@props([
    'title' => 'Tidak ada data',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-8 sm:py-12 px-4 text-center select-none']) }}>
    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-ink-100 dark:bg-white/[0.04] border border-ink-200/60 dark:border-white/10 flex items-center justify-center text-ink-400 dark:text-ink-500 mb-3.5 shadow-xs">
        @if($icon)
            {!! $icon !!}
        @else
            <svg class="w-6 h-6 stroke-[1.75]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        @endif
    </div>

    <h4 class="font-semibold text-sm sm:text-base text-ink-800 dark:text-ink-200 tracking-tight">
        {{ $title }}
    </h4>

    @if($description)
        <p class="text-xs text-ink-500 dark:text-ink-400 mt-1 max-w-sm leading-relaxed">
            {{ $description }}
        </p>
    @endif

    @if($slot->isNotEmpty())
        <div class="mt-4 flex items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
