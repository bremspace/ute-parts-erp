@props([
    'title' => null,
    'subtitle' => null,
    'hover' => false,
    'circuit' => false,
    'padding' => 'p-4 sm:p-6',
])

<div {{ $attributes->merge(['class' => 'glass-panel rounded-2xl relative overflow-hidden transition-[border-color,box-shadow,transform] duration-200 ' . ($hover ? 'glass-panel-hover ' : '') . $padding]) }}>
    @if($circuit)
        <div class="circuit-line absolute top-0 left-0 w-full h-[1px]"></div>
    @endif

    @if($title || $subtitle)
        <div class="flex items-center justify-between mb-4 border-b border-black/10 dark:border-white/5 pb-3">
            <div>
                @if($title)
                    <h3 class="font-semibold text-base sm:text-lg text-ink-50 dark:text-white tracking-wide">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs text-ink-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($action)
                <div>{{ $action }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
