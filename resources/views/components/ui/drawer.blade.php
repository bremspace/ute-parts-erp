@props([
    'name' => 'drawerOpen',      // Alpine variable name
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'md',          // sm, md, lg, xl
    'teleport' => true,
])

@php
$widthClasses = match($maxWidth) {
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    default => 'sm:max-w-md',
};
@endphp

@php
$drawerMarkup = '
<div
    x-cloak
    x-show="' . $name . '"
    class="fixed inset-0 z-50 overflow-hidden"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="' . $name . ' = false"
>
    <!-- Backdrop Overlay -->
    <div
        x-show="' . $name . '"
        x-transition:enter="transition-opacity ease-out duration-250"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-ink-950/70 dark:bg-black/80 backdrop-blur-xs transition-opacity"
        @click="' . $name . ' = false"
        aria-hidden="true"
    ></div>

    <div class="fixed inset-0 flex justify-end items-end sm:items-stretch pointer-events-none">
        <!-- Drawer Panel: Slide from bottom on mobile, slide from right on tablet/desktop -->
        <div
            x-show="' . $name . '"
            x-transition:enter="transition-transform duration-250"
            x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
            x-transition:enter-end="translate-y-0 sm:translate-x-0"
            x-transition:leave="transition-transform duration-200"
            x-transition:leave-start="translate-y-0 sm:translate-x-0"
            x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full"
            class="w-full ' . $widthClasses . ' max-h-[92dvh] sm:max-h-full h-auto sm:h-full bg-white dark:bg-ink-900 border-t sm:border-t-0 sm:border-l border-ink-200/80 dark:border-white/10 rounded-t-3xl sm:rounded-none shadow-2xl flex flex-col pointer-events-auto overflow-hidden"
            style="transition-timing-function: cubic-bezier(0.32, 0.72, 0, 1);"
        >
            <!-- Mobile Grab Handle -->
            <div class="sm:hidden w-full flex items-center justify-center pt-3 pb-1">
                <div class="w-10 h-1 rounded-full bg-ink-300 dark:bg-white/20"></div>
            </div>

            <!-- Drawer Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100 dark:border-white/[0.06]">
                <div class="min-w-0 pr-3">
                    ' . ($title ? '<h3 class="font-semibold text-base sm:text-lg text-ink-900 dark:text-white tracking-tight truncate">' . e($title) . '</h3>' : '') . '
                    ' . ($subtitle ? '<p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed truncate">' . e($subtitle) . '</p>' : '') . '
                </div>
                <button
                    type="button"
                    @click="' . $name . ' = false"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/[0.06] transition-colors cursor-pointer shrink-0"
                    title="Tutup (Esc)"
                >
                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="p-5 overflow-y-auto flex-1 overscroll-contain">
                ' . $slot . '
            </div>

            <!-- Drawer Footer Slot -->
            ' . (isset($footer) ? '
            <div class="px-5 py-3.5 border-t border-ink-100 dark:border-white/[0.06] bg-ink-50/50 dark:bg-white/[0.02] flex items-center justify-end gap-2.5 shrink-0" style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 0.875rem);">
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
        {!! $drawerMarkup !!}
    </template>
@else
    {!! $drawerMarkup !!}
@endif
