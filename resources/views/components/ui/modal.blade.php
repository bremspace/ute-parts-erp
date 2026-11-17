@props([
    'name' => null,             // Alpine model property name or event name
    'show' => false,            // wire:model or x-model or boolean
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'md',         // sm, md, lg, xl, 2xl, full
    'bottomSheetOnMobile' => true,
    'teleport' => true,
])

@php
$maxWidthClasses = match($maxWidth) {
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    'full' => 'sm:max-w-4xl',
    default => 'sm:max-w-md',
};

// Container alignment: on mobile bottom sheet (items-end), on tablet/desktop centered (sm:items-center sm:p-4)
$alignmentClasses = $bottomSheetOnMobile
    ? 'items-end sm:items-center justify-center p-0 sm:p-4'
    : 'items-center justify-center p-4';

// Modal card classes: bottom sheet rounded on mobile, standard rounded on desktop
$cardClasses = $bottomSheetOnMobile
    ? 'rounded-t-3xl sm:rounded-2xl max-h-[90dvh] sm:max-h-[85vh]'
    : 'rounded-2xl max-h-[85vh]';
@endphp

@php
    $modalContent = '
    <div
        x-cloak
        ' . ($name ? "x-show=\"{$name}\"" : 'x-show="open"') . '
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop with smooth fade -->
        <div
            x-show="' . ($name ? $name : 'open') . '"
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-ink-950/70 dark:bg-black/80 backdrop-blur-xs transition-opacity"
            @click="' . ($name ? "{$name} = false" : 'open = false') . '"
            aria-hidden="true"
        ></div>

        <!-- Dialog Container -->
        <div class="fixed inset-0 z-10 overflow-y-auto flex ' . $alignmentClasses . ' pointer-events-none">
            <!-- Modal Box: Emil Kowalski curve: starts at scale-96, never scale-0 -->
            <div
                x-show="' . ($name ? $name : 'open') . '"
                x-transition:enter="transition-[transform,opacity] ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition-[transform,opacity] ease-in duration-140"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="w-full ' . $maxWidthClasses . ' ' . $cardClasses . ' bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 shadow-2xl flex flex-col pointer-events-auto overflow-hidden"
                style="transition-timing-function: cubic-bezier(0.23, 1, 0.32, 1);"
            >
                <!-- Drag Handle for Mobile Bottom Sheet -->
                ' . ($bottomSheetOnMobile ? '
                <div class="sm:hidden w-full flex items-center justify-center pt-2.5 pb-1">
                    <div class="w-10 h-1 rounded-full bg-ink-300 dark:bg-white/20"></div>
                </div>
                ' : '') . '

                <!-- Header -->
                ' . ($title || $subtitle || isset($header) ? '
                <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100 dark:border-white/[0.06]">
                    <div>
                        ' . ($title ? '<h3 class="font-semibold text-base sm:text-lg text-ink-900 dark:text-white tracking-tight">' . e($title) . '</h3>' : '') . '
                        ' . ($subtitle ? '<p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed">' . e($subtitle) . '</p>' : '') . '
                    </div>
                    <button
                        type="button"
                        @click="' . ($name ? "{$name} = false" : 'open = false') . '"
                        class="p-1.5 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/[0.06] transition-colors cursor-pointer"
                        title="Tutup (Esc)"
                    >
                        <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                ' : '') . '

                <!-- Body Content -->
                <div class="p-5 overflow-y-auto flex-1">
                    ' . $slot . '
                </div>

                <!-- Footer Slot -->
                ' . (isset($footer) ? '
                <div class="px-5 py-3.5 border-t border-ink-100 dark:border-white/[0.06] bg-ink-50/50 dark:bg-white/[0.02] flex items-center justify-end gap-2.5">
                    ' . $footer . '
                </div>
                ' : '') . '
            </div>
        </div>
    </div>
    ';
@endphp

@if($teleport)
    <template x-teleport="body">
        {!! $modalContent !!}
    </template>
@else
    {!! $modalContent !!}
@endif
