@props([
    'variant' => 'button', // button | banner
])

<div x-data="pwaInstaller()" x-cloak x-show="canInstall && !dismissed" {{ $attributes }}>
    @if($variant === 'banner')
        <div class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-up-primary/20 via-indigo-900/30 to-up-accent/20 border border-up-primary/30 flex items-center justify-between gap-3 text-xs backdrop-blur-md shadow-lg">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-up-primary flex items-center justify-center flex-shrink-0 shadow-sm shadow-up-primary/30">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="font-medium text-white truncate">Pasang Ute Parts di layar utama</p>
                    <p class="text-[11px] text-ink-300 hidden sm:block truncate">Akses kasir POS & data gudang lebih cepat dan praktis.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <button
                    type="button"
                    @click="promptInstall()"
                    class="px-3.5 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-[transform,background-color] active:scale-95 cursor-pointer shadow-md shadow-up-primary/30"
                >
                    Pasang Aplikasi
                </button>
                <button
                    type="button"
                    @click="dismiss()"
                    class="p-1 rounded-lg text-ink-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    aria-label="Tutup saran pemasangan"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    @else
        <!-- Compact Header / Toolbar Button -->
        <button
            type="button"
            @click="promptInstall()"
            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-up-primary/15 hover:bg-up-primary/25 border border-up-primary/30 text-up-primary hover:text-white text-xs font-semibold transition-[transform,background-color,color] duration-150 active:scale-[0.97] cursor-pointer shadow-sm min-h-[38px]"
            title="Pasang Ute Parts sebagai aplikasi mandiri"
        >
            <svg class="w-3.5 h-3.5 text-up-accent flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span class="hidden sm:inline whitespace-nowrap">Pasang Aplikasi</span>
            <span class="sm:hidden text-[11px] font-bold">Pasang</span>
        </button>
    @endif
</div>
