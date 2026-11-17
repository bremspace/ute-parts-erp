@props([
    'variant' => 'neutral', // neutral, primary, mint, amber, danger, blue, purple
    'size' => 'sm',        // sm, md
    'dot' => true,
    'pulse' => false,
])

@php
$variants = [
    'neutral' => [
        'bg' => 'bg-ink-100 dark:bg-white/[0.06]',
        'text' => 'text-ink-700 dark:text-ink-300',
        'border' => 'border-ink-200/80 dark:border-white/10',
        'dot' => 'bg-ink-400 dark:bg-ink-500',
    ],
    'primary' => [
        'bg' => 'bg-up-primary/10 dark:bg-up-primary/15',
        'text' => 'text-up-primary dark:text-indigo-400',
        'border' => 'border-up-primary/25',
        'dot' => 'bg-up-primary',
    ],
    'mint' => [
        'bg' => 'bg-up-mint/10 dark:bg-up-mint/15',
        'text' => 'text-emerald-700 dark:text-emerald-400',
        'border' => 'border-up-mint/25',
        'dot' => 'bg-up-mint',
    ],
    'amber' => [
        'bg' => 'bg-up-amber/10 dark:bg-up-amber/15',
        'text' => 'text-amber-700 dark:text-amber-400',
        'border' => 'border-up-amber/25',
        'dot' => 'bg-up-amber',
    ],
    'danger' => [
        'bg' => 'bg-up-red/10 dark:bg-up-red/15',
        'text' => 'text-red-700 dark:text-red-400',
        'border' => 'border-up-red/25',
        'dot' => 'bg-up-red',
    ],
    'blue' => [
        'bg' => 'bg-blue-500/10 dark:bg-blue-500/15',
        'text' => 'text-blue-700 dark:text-blue-400',
        'border' => 'border-blue-500/25',
        'dot' => 'bg-blue-500',
    ],
    'purple' => [
        'bg' => 'bg-purple-500/10 dark:bg-purple-500/15',
        'text' => 'text-purple-700 dark:text-purple-400',
        'border' => 'border-purple-500/25',
        'dot' => 'bg-purple-500',
    ],
];

$cfg = $variants[$variant] ?? $variants['neutral'];
$sizeClasses = $size === 'sm' ? 'px-2 py-0.5 text-[11px] gap-1.5' : 'px-2.5 py-1 text-xs gap-1.5';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium border {$cfg['bg']} {$cfg['text']} {$cfg['border']} {$sizeClasses} select-none whitespace-nowrap"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }} {{ $pulse ? 'animate-pulse' : '' }} shrink-0"></span>
    @endif
    <span>{{ $slot }}</span>
</span>
