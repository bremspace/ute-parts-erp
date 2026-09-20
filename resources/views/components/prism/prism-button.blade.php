@props([
    'variant' => 'primary', // primary, accent, ghost, danger, pos-action
    'size' => 'md',        // sm, md, lg
    'type' => 'button',
    'disabled' => false,
])

@php
$baseClasses = 'inline-flex items-center justify-center font-medium rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-ink-950 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none active:scale-[0.98]';

$sizeClasses = [
    'sm' => 'px-3 py-1.5 text-xs gap-1.5',
    'md' => 'px-4 py-2.5 text-sm gap-2',
    'lg' => 'px-6 py-3.5 text-base gap-2.5 font-semibold',
][$size] ?? 'px-4 py-2.5 text-sm gap-2';

$variantClasses = [
    'primary' => 'bg-up-primary hover:bg-up-primary-dark text-white shadow-lg shadow-up-primary/25 focus:ring-up-primary border border-white/10',
    'accent' => 'bg-up-accent hover:opacity-90 text-white shadow-lg shadow-up-accent/25 focus:ring-up-accent border border-white/10',
    'ghost' => 'bg-white/5 hover:bg-white/10 text-ink-100 hover:text-white border border-white/10 focus:ring-white/20',
    'danger' => 'bg-up-red hover:opacity-90 text-white shadow-lg shadow-up-red/25 focus:ring-up-red border border-white/10',
    'mint' => 'bg-up-mint hover:opacity-90 text-ink-950 shadow-lg shadow-up-mint/20 focus:ring-up-mint border border-white/10',
    'pos-action' => 'bg-gradient-to-b from-ink-800 to-ink-850 hover:from-ink-700 hover:to-ink-800 text-white border border-up-primary/40 shadow-[0_4px_12px_rgba(0,0,0,0.4),inset_0_1px_0_rgba(255,255,255,0.1)] active:shadow-inner active:translate-y-0.5 font-bold tracking-wide',
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
