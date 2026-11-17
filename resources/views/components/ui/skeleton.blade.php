@props([
    'variant' => 'text', // text, circle, button, card, table-row, badge
    'lines' => 1,
    'width' => 'w-full',
    'height' => null,
])

@php
$baseClasses = 'animate-pulse bg-ink-200/70 dark:bg-white/[0.06] rounded-lg shrink-0';

$variantClasses = match($variant) {
    'circle' => 'rounded-full w-10 h-10',
    'button' => 'rounded-xl h-10 w-28',
    'badge' => 'rounded-full h-5 w-16',
    'card' => 'rounded-2xl h-28 w-full border border-ink-100 dark:border-white/5',
    'table-row' => 'h-10 w-full rounded-lg',
    default => 'h-4 ' . ($width ?? 'w-full'),
};

$customHeight = $height ? $height : '';
@endphp

@if($variant === 'text' && $lines > 1)
    <div class="space-y-2 w-full">
        @for($i = 0; $i < $lines; $i++)
            <div class="{{ $baseClasses }} h-4 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
@elseif($variant === 'table-row')
    <div class="flex items-center gap-4 py-2 w-full">
        <div class="{{ $baseClasses }} h-4 w-1/4"></div>
        <div class="{{ $baseClasses }} h-4 w-1/3"></div>
        <div class="{{ $baseClasses }} h-4 w-1/6"></div>
        <div class="{{ $baseClasses }} h-4 w-1/6 ml-auto"></div>
    </div>
@else
    <div {{ $attributes->merge(['class' => "{$baseClasses} {$variantClasses} {$customHeight}"]) }}></div>
@endif
