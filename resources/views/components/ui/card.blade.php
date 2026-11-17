@props([
    'title' => null,
    'subtitle' => null,
    'hover' => false,
    'padding' => 'p-4 sm:p-5',
    'rounded' => 'rounded-2xl',
])

@php
$baseClasses = "bg-white dark:bg-ink-900/90 text-ink-900 dark:text-ink-100 border border-ink-200/80 dark:border-white/[0.08] shadow-[0_1px_3px_0_rgba(0,0,0,0.03)] dark:shadow-[0_4px_16px_-2px_rgba(0,0,0,0.3)] relative overflow-hidden transition-[border-color,box-shadow,transform] duration-140 {$rounded}";
$hoverClasses = $hover ? 'hover:border-ink-300 dark:hover:border-white/20 hover:shadow-md' : '';
@endphp

<div {{ $attributes->merge(['class' => "{$baseClasses} {$hoverClasses}"]) }}>
    @if($title || $subtitle || isset($header) || isset($action))
        <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/[0.06] px-4 sm:px-5 py-3 sm:py-3.5">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="min-w-0 pr-3">
                    @if($title)
                        <h3 class="font-semibold text-sm sm:text-base text-ink-900 dark:text-white tracking-tight truncate">{{ $title }}</h3>
                    @endif
                    @if($subtitle)
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed truncate">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @isset($action)
                <div class="flex-shrink-0 flex items-center gap-2">
                    {{ $action }}
                </div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-ink-100 dark:border-white/[0.06] bg-ink-50/50 dark:bg-white/[0.02] px-4 sm:px-5 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
