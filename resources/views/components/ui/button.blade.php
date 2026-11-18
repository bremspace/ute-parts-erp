@props([
    'variant' => 'primary', // primary, secondary, ghost, danger, mint, pos-action
    'size' => 'md',        // sm, md, lg, icon
    'type' => 'button',
    'disabled' => false,
    'loading' => false,
    'target' => null,      // Livewire target for wire:loading
    'icon' => null,
    'iconRight' => null,
])

@php
// Base classes: GPU-friendly transition, tactile press feedback (Emil Kowalski), touch-manipulation, min 44px on mobile
$baseClasses = 'inline-flex items-center justify-center font-medium rounded-xl transition-[transform,background-color,border-color,box-shadow,color] duration-120 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-ink-950 disabled:opacity-50 disabled:pointer-events-none cursor-pointer select-none active:scale-[0.97] touch-manipulation relative overflow-hidden';

$sizeClasses = [
    'sm' => 'px-3 py-1.5 min-h-[44px] sm:min-h-[38px] text-xs gap-1.5',
    'md' => 'px-4 py-2 min-h-[44px] text-sm gap-2',
    'lg' => 'px-5 py-2.5 min-h-[48px] text-base gap-2.5 font-semibold',
    'icon' => 'w-10 h-10 min-h-[40px] min-w-[40px] sm:w-9 sm:h-9 p-0 text-sm justify-center',
][$size] ?? 'px-4 py-2 min-h-[44px] text-sm gap-2';

$variantClasses = [
    'primary' => 'bg-up-primary hover:bg-up-primary-dark text-white shadow-sm shadow-up-primary/25 focus:ring-up-primary border border-transparent',
    'secondary' => 'bg-ink-100 hover:bg-ink-200 dark:bg-white/[0.06] dark:hover:bg-white/[0.12] text-ink-800 dark:text-ink-100 border border-ink-200 dark:border-white/10 focus:ring-ink-400',
    'ghost' => 'bg-transparent hover:bg-ink-100 dark:hover:bg-white/[0.06] text-ink-700 dark:text-ink-200 hover:text-ink-950 dark:hover:text-white border border-transparent focus:ring-ink-400',
    'danger' => 'bg-up-red hover:opacity-95 text-white shadow-sm shadow-up-red/25 focus:ring-up-red border border-transparent',
    'mint' => 'bg-up-mint hover:opacity-95 text-white shadow-sm shadow-up-mint/25 focus:ring-up-mint border border-transparent font-semibold',
    'pos-action' => 'bg-ink-800 hover:bg-ink-700 text-white border border-up-primary/40 shadow-sm active:shadow-inner font-bold tracking-wide',
][$variant] ?? 'bg-up-primary hover:bg-up-primary-dark text-white shadow-sm focus:ring-up-primary border border-transparent';
@endphp

<button
    type="{{ $type }}"
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge(['class' => "{$baseClasses} {$sizeClasses} {$variantClasses}"]) }}
>
    {{-- Spinner indicator when loading --}}
    @if($target)
        <svg wire:loading wire:target="{{ $target }}" class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @elseif($loading)
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @endif

    @if($icon)
        <span class="shrink-0 {{ $target ? 'wire:loading.remove wire:target="' . $target . '"' : '' }}">
            {!! $icon !!}
        </span>
    @endif

    <span class="truncate">{{ $slot }}</span>

    @if($iconRight)
        <span class="shrink-0">
            {!! $iconRight !!}
        </span>
    @endif
</button>
