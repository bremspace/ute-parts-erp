@php
    $deviceAccess = app(\App\Modules\Rbac\Services\NetworkManagementService::class)->getDeviceAccessUrls();
@endphp

<div x-data="{
        open: false,
        activeTab: '{{ $deviceAccess['is_online'] ? 'lan' : 'lan' }}',
        copied: false,
        lanUrl: '{{ $deviceAccess['lan_url'] }}',
        onlineUrl: '{{ $deviceAccess['online_url'] ?? '' }}',
        copyLink(url) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url);
            } else {
                const ta = document.createElement('textarea');
                ta.value = url;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try { document.execCommand('copy'); } catch(e) {}
                document.body.removeChild(ta);
            }
            this.copied = true;
            if (window.showToast) {
                window.showToast('Alamat URL berhasil disalin!', 'success');
            }
            setTimeout(() => { this.copied = false; }, 2500);
        }
    }"
    @keydown.escape.window="open = false"
    class="relative inline-block">

    <!-- Trigger Button in App Bar / Toolbar -->
    <button
        type="button"
        @click="open = true"
        class="inline-flex items-center justify-center gap-1.5 p-1.5 sm:px-3 sm:py-1.5 rounded-xl bg-up-primary/15 hover:bg-up-primary/25 border border-up-primary/30 text-up-primary hover:text-white text-xs font-semibold transition-[transform,background-color,color] duration-150 active:scale-[0.97] cursor-pointer shadow-sm min-w-[34px] min-h-[34px] sm:min-w-[38px] sm:min-h-[38px]"
        title="Buka Kasir / Backoffice di HP atau Tablet (Jaringan Wi-Fi Toko)"
        aria-label="Akses Multi-Device">
        <svg class="w-4 h-4 text-up-mint flex-shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span class="hidden md:inline font-bold">Buka di HP/Tablet</span>
    </button>

    <!-- Modal Dialog -->
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
            aria-modal="true"
            role="dialog">

            <!-- Backdrop Blur -->
            <div
                x-show="open"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-ink-950/80 backdrop-blur-md"
                @click="open = false"
                aria-hidden="true"></div>

            <!-- Modal Content (Glassmorphism Surface) -->
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-250 transform-gpu"
                x-transition:enter-start="opacity-0 scale-95 translate-y-3"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200 transform-gpu"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-3"
                class="relative w-full max-w-lg bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 rounded-3xl shadow-2xl p-5 sm:p-7 overflow-hidden z-10 text-ink-900 dark:text-ink-100">

                <!-- Circuit Line Ambient Accent -->
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-up-primary/20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-up-mint/15 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Header -->
                <div class="flex items-start justify-between gap-3 mb-5 border-b border-ink-100 dark:border-white/10 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-up-primary to-up-mint p-0.5 flex-shrink-0 shadow-md shadow-up-primary/20">
                            <div class="w-full h-full bg-white dark:bg-[#0B1120] rounded-[14px] flex items-center justify-center">
                                <svg class="w-5 h-5 text-up-primary dark:text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-bold tracking-tight text-ink-900 dark:text-white flex items-center gap-2">
                                Akses Multi-Device
                                <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded-full bg-up-mint/15 text-up-mint border border-up-mint/30">
                                    Offline LAN &amp; Online
                                </span>
                            </h3>
                            <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">
                                Buka kasir POS dan backoffice dari HP, tablet, atau laptop lain tanpa instalasi.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="open = false"
                        class="p-1.5 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer"
                        aria-label="Tutup modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Mode Switcher (Jika Online Mode Tersedia) -->
                @if($deviceAccess['is_online'] && !empty($deviceAccess['online_url']))
                    <div class="flex items-center p-1 bg-ink-100 dark:bg-white/5 rounded-2xl mb-5 border border-ink-200/60 dark:border-white/5">
                        <button
                            type="button"
                            @click="activeTab = 'lan'"
                            :class="activeTab === 'lan' ? 'bg-white dark:bg-up-primary text-ink-900 dark:text-white shadow-sm font-bold' : 'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white font-medium'"
                            class="flex-1 py-1.5 text-xs rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.343 6.343c5.857-5.857 15.355-5.857 21.213 0" />
                            </svg>
                            <span>Wi-Fi Toko (LAN Lokal)</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'online'"
                            :class="activeTab === 'online' ? 'bg-white dark:bg-up-primary text-ink-900 dark:text-white shadow-sm font-bold' : 'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white font-medium'"
                            class="flex-1 py-1.5 text-xs rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                            </svg>
                            <span>Domain Online (Internet)</span>
                        </button>
                    </div>
                @endif

                <!-- Body / QR Display Section -->
                <div class="flex flex-col items-center justify-center my-3">
                    <!-- QR Box with High Contrast White Backplate -->
                    <div class="p-3 bg-white rounded-2xl shadow-md border border-ink-200/80 mb-4 inline-block transform transition hover:scale-105 duration-200">
                        <div x-show="activeTab === 'lan'" class="flex items-center justify-center w-40 h-40">
                            {!! $deviceAccess['lan_qr_svg'] !!}
                        </div>
                        @if($deviceAccess['is_online'] && !empty($deviceAccess['online_qr_svg']))
                            <div x-show="activeTab === 'online'" class="flex items-center justify-center w-40 h-40" style="display: none;">
                                {!! $deviceAccess['online_qr_svg'] !!}
                            </div>
                        @endif
                    </div>

                    <!-- URL Display and Copy Button -->
                    <div class="w-full bg-ink-100/70 dark:bg-white/5 border border-ink-200/60 dark:border-white/10 rounded-2xl p-2.5 flex items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2 min-w-0 pl-1.5 font-mono">
                            <span class="w-2 h-2 rounded-full bg-up-mint flex-shrink-0 animate-ping"></span>
                            <span x-text="activeTab === 'lan' ? lanUrl : onlineUrl" class="font-bold truncate select-all text-ink-800 dark:text-ink-200"></span>
                        </div>
                        <button
                            type="button"
                            @click="copyLink(activeTab === 'lan' ? lanUrl : onlineUrl)"
                            class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[11px] transition-[transform,background-color] active:scale-95 flex items-center gap-1.5 flex-shrink-0 cursor-pointer shadow-sm">
                            <template x-if="!copied">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </template>
                            <template x-if="copied">
                                <svg class="w-3.5 h-3.5 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </template>
                            <span x-text="copied ? 'Tersalin!' : 'Salin URL'"></span>
                        </button>
                    </div>
                </div>

                <!-- Practical Instructions & Tips -->
                <div class="mt-4 pt-4 border-t border-ink-100 dark:border-white/10 text-[11px] space-y-2 text-ink-500 dark:text-ink-400">
                    <div class="flex items-start gap-2">
                        <span class="flex-shrink-0 w-4 h-4 rounded-full bg-up-primary/20 text-up-primary flex items-center justify-center font-bold text-[10px]">1</span>
                        <p>Pastikan HP/Tablet terhubung ke <strong>Wi-Fi toko yang sama</strong> dengan PC server kasir ini.</p>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="flex-shrink-0 w-4 h-4 rounded-full bg-up-primary/20 text-up-primary flex items-center justify-center font-bold text-[10px]">2</span>
                        <p>Arahkan kamera HP ke QR Code di atas atau buka browser dan ketik alamat URL tersebut.</p>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="flex-shrink-0 w-4 h-4 rounded-full bg-up-mint/20 text-up-mint flex items-center justify-center font-bold text-[10px]">★</span>
                        <p class="text-ink-700 dark:text-ink-300 font-medium">
                            <strong>Tips Praktis:</strong> Pilih <em>"Tambahkan ke Layar Utama" (Add to Home Screen)</em> di browser Chrome/Safari HP agar kasir tampil layar penuh seperti aplikasi native.
                        </p>
                    </div>
                </div>

                <!-- Footer Action -->
                <div class="mt-5 pt-3 flex justify-end">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-5 py-2 rounded-xl bg-ink-200/80 dark:bg-white/10 hover:bg-ink-300 dark:hover:bg-white/20 text-ink-800 dark:text-white font-bold text-xs transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
