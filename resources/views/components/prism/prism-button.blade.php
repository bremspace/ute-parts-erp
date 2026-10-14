@props([
    'variant' => 'primary', // primary, accent, ghost, danger, mint, pos-action
    'size' => 'md',        // sm, md, lg
    'type' => 'button',
    'disabled' => false,
])

@php
$baseClasses = 'inline-flex items-center justify-center font-medium rounded-xl transition-[transform,background-color,border-color,box-shadow,color] duration-120 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-[#090D16] disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none active:scale-[0.97] touch-manipulation';

$sizeClasses = [
    'sm' => 'px-3 py-1.5 min-h-[44px] sm:min-h-[38px] text-xs gap-1.5',
    'md' => 'px-4 py-2.5 min-h-[44px] text-sm gap-2',
    'lg' => 'px-6 py-3.5 min-h-[48px] text-base gap-2.5 font-semibold',
][$size] ?? 'px-4 py-2.5 min-h-[44px] text-sm gap-2';

$variantClasses = [
    'primary' => 'bg-up-primary hover:bg-up-primary-dark text-white shadow-sm shadow-up-primary/20 focus:ring-up-primary border border-transparent',
    'accent' => 'bg-up-accent hover:opacity-90 text-white shadow-sm shadow-up-accent/20 focus:ring-up-accent border border-transparent',
    'ghost' => 'bg-ink-100 dark:bg-white/5 hover:bg-ink-200 dark:hover:bg-white/10 text-ink-800 dark:text-ink-100 hover:text-ink-950 dark:hover:text-white border border-ink-200/80 dark:border-white/10 focus:ring-ink-400',
    'danger' => 'bg-up-red hover:opacity-90 text-white shadow-sm shadow-up-red/20 focus:ring-up-red border border-transparent',
    'mint' => 'bg-up-mint hover:opacity-90 text-white shadow-sm shadow-up-mint/20 focus:ring-up-mint border border-transparent font-semibold',
    'pos-action' => 'bg-ink-800 hover:bg-ink-700 text-white border border-up-primary/40 shadow-sm active:shadow-inner font-bold tracking-wide',
];
$variantClasses = $variantClasses[$variant] ?? $variantClasses['primary'];
@endphp

<button
    type="{{ $type }}"
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge(['class' => "{$baseClasses} {$sizeClasses} {$variantClasses}"]) }}
>
    {{ $slot }}
</button>
