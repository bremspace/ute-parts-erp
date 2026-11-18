@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'placeholder' => '',
    'error' => null,
    'hint' => null,
    'icon' => null,
    'iconRight' => null,
    'mono' => false,
    'required' => false,
    'disabled' => false,
    'inputmode' => null,
])

@php
$id = $id ?? $name ?? 'input-' . uniqid();
// Ensure 16px minimum on mobile (text-base) to prevent iOS Safari auto-zoom trap
$fontClasses = ($mono ? 'font-mono tabular-nums ' : '') . 'text-base sm:text-sm';
$baseInputClasses = 'w-full rounded-xl bg-white dark:bg-ink-900/80 text-ink-900 dark:text-ink-100 placeholder:text-ink-400 dark:placeholder:text-ink-500 border transition-[border-color,box-shadow] duration-120 outline-none focus:ring-2 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed';

$stateClasses = $error
    ? 'border-up-red/60 focus:border-up-red focus:ring-up-red/20'
    : 'border-ink-200 dark:border-white/10 hover:border-ink-300 dark:hover:border-white/20 focus:border-up-primary focus:ring-up-primary/25';

$paddingClasses = ($icon ? 'pl-10 ' : 'pl-3.5 ') . ($iconRight ? 'pr-10 ' : 'pr-3.5 ') . 'py-2.5 sm:py-2 min-h-[44px] sm:min-h-[38px]';
@endphp

<div class="w-full flex flex-col gap-1.5">
    @if($label)
        <label for="{{ $id }}" class="text-xs font-semibold text-ink-700 dark:text-ink-300 flex items-center justify-between">
            <span>
                {{ $label }}
                @if($required)
                    <span class="text-up-red ml-0.5">*</span>
                @endif
            </span>
            @isset($labelRight)
                <span class="text-[11px] font-normal text-ink-400">{{ $labelRight }}</span>
            @endisset
        </label>
    @endif

    <div class="relative flex items-center w-full">
        @if($icon)
            <div class="absolute left-3.5 pointer-events-none text-ink-400 dark:text-ink-500 flex items-center justify-center">
                {!! $icon !!}
            </div>
        @endif

        <input
            type="{{ $type }}"
            @if($name) name="{{ $name }}" @endif
            id="{{ $id }}"
            placeholder="{{ $placeholder }}"
            @if($inputmode) inputmode="{{ $inputmode }}" @endif
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->merge(['class' => "{$baseInputClasses} {$fontClasses} {$stateClasses} {$paddingClasses}"]) }}
        />

        @if($iconRight)
            <div class="absolute right-3.5 flex items-center justify-center text-ink-400 dark:text-ink-500">
                {!! $iconRight !!}
            </div>
        @endif
    </div>

    @if($error)
        <p class="text-[11px] text-up-red font-medium flex items-center gap-1 mt-0.5 animate-fade-in">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ $error }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11px] text-ink-400 mt-0.5 leading-relaxed">{{ $hint }}</p>
    @endif
</div>
