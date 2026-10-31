<div class="relative" wire:poll.25s="checkNewNotifications" x-data="{ open: @entangle('open') }" @click.outside="open = false" @keydown.escape.window="open = false">
    <!-- Backdrop for mobile screens (<sm) -->
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 bg-ink-950/60 backdrop-blur-xs z-40 sm:hidden"
        @click="open = false"
        aria-hidden="true"
    ></div>

    <!-- Bell Trigger Button -->
    <button
        type="button"
        @click="open = !open"
        title="Pusat Notifikasi"
        aria-label="Pusat Notifikasi"
        class="relative p-1.5 sm:p-2 rounded-xl text-ink-500 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-all duration-150 cursor-pointer flex items-center justify-center min-w-[34px] min-h-[34px] sm:min-w-[38px] sm:min-h-[38px]"
        :class="{ 'bg-black/5 dark:bg-white/10 text-ink-900 dark:text-white': open }"
    >
        <svg class="w-5 h-5 sm:w-5.5 sm:h-5.5 transition-transform duration-150 active:scale-95" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        @if($totalUnprocessedCount > 0)
            <!-- Continuous High-Visibility Blinking Ping Radar Ring & Badge -->
            <span class="absolute -top-0.5 -right-0.5 sm:-top-1 sm:-right-1 flex h-4.5 sm:h-5 min-w-[18px] sm:min-w-[20px] px-1 pointer-events-none">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-red opacity-80"></span>
                <span class="relative inline-flex rounded-full h-4.5 sm:h-5 min-w-[18px] sm:min-w-[20px] px-1 bg-gradient-to-r from-up-red to-rose-600 animate-pulse text-[9px] sm:text-[10px] font-black text-white items-center justify-center shadow-lg shadow-up-red/50 ring-2 ring-white dark:ring-[#090D16]">
                    {{ $totalUnprocessedCount > 99 ? '99+' : $totalUnprocessedCount }}
                </span>
            </span>
        @endif
    </button>

    <!-- Notification Dropdown Panel: Responsive centered on mobile, right-aligned on tablet/desktop -->
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-out duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
        class="fixed inset-x-2 top-16 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2.5 sm:w-96 md:w-[440px] origin-top sm:origin-top-right max-h-[85vh] rounded-3xl bg-white/95 dark:bg-[#0B1120]/95 backdrop-blur-2xl border border-ink-200/90 dark:border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.35)] dark:shadow-[0_25px_60px_rgba(0,0,0,0.8)] z-50 flex flex-col overflow-hidden"
    >
        <!-- Header -->
        <div class="p-3.5 sm:p-4 border-b border-ink-200/70 dark:border-white/10 bg-ink-50/70 dark:bg-white/[0.02]">
            <div class="flex items-center justify-between gap-2 sm:gap-3 mb-3">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="p-1.5 rounded-xl bg-up-primary/10 text-up-primary dark:bg-up-primary/20 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-ink-900 dark:text-white tracking-tight truncate">Pusat Notifikasi</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">Sesuai Role &amp; Preferensi Anda</p>
                    </div>
                </div>

                <div class="flex items-center gap-1 sm:gap-1.5 flex-shrink-0" x-data="{ soundActive: window.isUteNotificationSoundEnabled ? window.isUteNotificationSoundEnabled() : true }">
                    <!-- Sound Toggle -->
                    <button
                        type="button"
                        @click="soundActive = window.toggleUteNotificationSound(); if (soundActive) window.playUteNotificationSound('default');"
                        :title="soundActive ? 'Suara notifikasi aktif (klik untuk membisukan)' : 'Suara notifikasi dibisukan (klik untuk aktifkan)'"
                        class="p-1.5 rounded-lg transition-colors cursor-pointer"
                        :class="soundActive ? 'text-up-mint hover:bg-up-mint/10' : 'text-ink-400 hover:bg-black/5 dark:hover:bg-white/5'"
                    >
                        <svg x-show="soundActive" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                        </svg>
                        <svg x-show="!soundActive" x-cloak class="w-4 h-4 text-up-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                        </svg>
                    </button>

                    <!-- Test Chime Button -->
                    <button
                        type="button"
                        onclick="window.playUteNotificationSound('urgent')"
                        title="Uji Suara Notifikasi"
                        class="p-1.5 rounded-lg text-ink-400 hover:text-up-primary hover:bg-up-primary/10 transition-colors cursor-pointer text-[10px] font-mono font-bold tracking-tight"
                    >
                        TEST
                    </button>

                    <!-- Event Preference Gear Toggle -->
                    <button
                        type="button"
                        wire:click="setFilter('{{ $filter === 'pengaturan' ? 'belum_proses' : 'pengaturan' }}')"
                        title="Pilih Event Notifikasi"
                        class="p-1.5 rounded-lg transition-colors cursor-pointer {{ $filter === 'pengaturan' ? 'bg-up-primary/15 text-up-primary' : 'text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5' }}"
                    >
                        <svg class="w-4 h-4 transition-transform duration-200 {{ $filter === 'pengaturan' ? 'rotate-90 text-up-primary' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>

                    @if($totalUnprocessedCount > 0 && $filter !== 'pengaturan')
                        <button
                            type="button"
                            wire:click="markAllAsRead"
                            class="text-[11px] font-bold text-up-primary hover:text-indigo-400 px-2 py-1 rounded-lg hover:bg-up-primary/10 transition-colors cursor-pointer"
                        >
                            Tandai Selesai
                        </button>
                    @endif
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-1 bg-ink-200/60 dark:bg-black/30 p-1 rounded-xl">
                <button
                    type="button"
                    wire:click="setFilter('belum_proses')"
                    class="flex-1 py-1 px-2 rounded-lg text-xs font-bold transition-all duration-150 flex items-center justify-center gap-1.5 cursor-pointer {{ $filter === 'belum_proses' ? 'bg-white dark:bg-ink-800 text-up-red shadow-sm' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}"
                >
                    @if($totalUnprocessedCount > 0)
                        <span class="w-2 h-2 rounded-full bg-up-red animate-ping flex-shrink-0"></span>
                    @endif
                    <span>Belum Selesai</span>
                    @if($totalUnprocessedCount > 0)
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full bg-up-red/15 text-up-red font-black">{{ $totalUnprocessedCount }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="setFilter('approval')"
                    class="py-1 px-2.5 rounded-lg text-xs font-bold transition-all duration-150 flex items-center justify-center gap-1 cursor-pointer {{ $filter === 'approval' ? 'bg-white dark:bg-ink-800 text-up-amber shadow-sm' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}"
                >
                    <span>Approval</span>
                    @if($pendingApprovalCount > 0)
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full bg-up-amber/15 text-up-amber font-black">{{ $pendingApprovalCount }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="setFilter('semua')"
                    class="py-1 px-2.5 rounded-lg text-xs font-bold transition-all duration-150 flex items-center justify-center gap-1 cursor-pointer {{ $filter === 'semua' ? 'bg-white dark:bg-ink-800 text-ink-900 dark:text-white shadow-sm' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}"
                >
                    <span>Semua</span>
                </button>

                <button
                    type="button"
                    wire:click="setFilter('pengaturan')"
                    title="Preferensi Notifikasi"
                    class="py-1 px-2 rounded-lg text-xs font-bold transition-all duration-150 flex items-center justify-center gap-1 cursor-pointer {{ $filter === 'pengaturan' ? 'bg-white dark:bg-ink-800 text-up-primary shadow-sm' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                </button>
            </div>
        </div>

        <!-- Notification List OR Settings View -->
        <div class="flex-1 overflow-y-auto divide-y divide-ink-100 dark:divide-white/5 p-2 space-y-1 max-h-[55vh]">
            {{-- VIEW 1: PREFERENSI NOTIFIKASI EVENT --}}
            @if($filter === 'pengaturan')
                <div class="p-2 space-y-3">
                    <div class="flex items-center justify-between pb-1">
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-ink-900 dark:text-white">Pilih Event Notifikasi</h4>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400">Aktifkan notifikasi yang relevan dengan pekerjaan Anda</p>
                        </div>
                        <button
                            type="button"
                            wire:click="selectAllEventPreferences"
                            class="text-[11px] font-bold text-up-primary hover:text-indigo-400 px-2 py-1 rounded-lg hover:bg-up-primary/10 transition-colors"
                        >
                            Pilih Semua
                        </button>
                    </div>

                    <div class="space-y-2">
                        @foreach($availableEvents as $kategoriKey => $ev)
                            @php
                                $isEnabled = in_array($kategoriKey, $userEvents);
                            @endphp
                            <div
                                wire:click="toggleEventPreference('{{ $kategoriKey }}')"
                                class="p-3 rounded-2xl border transition-all duration-150 cursor-pointer flex items-center justify-between gap-3 {{ $isEnabled ? 'bg-up-primary/5 border-up-primary/30 dark:bg-up-primary/10' : 'bg-black/[0.02] border-ink-200/60 dark:bg-white/[0.02] dark:border-white/5 opacity-60' }}"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="text-xs font-bold text-ink-900 dark:text-white {{ $ev['color'] }}">{{ $ev['label'] }}</span>
                                        <span class="text-[9px] uppercase font-mono px-1.5 py-0.2 rounded font-extrabold {{ $isEnabled ? 'bg-up-primary/20 text-up-primary' : 'bg-black/10 dark:bg-white/10 text-ink-400' }}">
                                            {{ $isEnabled ? 'AKTIF' : 'NONAKTIF' }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 leading-tight">{{ $ev['desc'] }}</p>
                                </div>

                                <!-- Custom Toggle Pill -->
                                <div class="w-11 h-6 rounded-full transition-colors duration-200 ease-in-out p-0.5 flex items-center flex-shrink-0 {{ $isEnabled ? 'bg-up-primary' : 'bg-ink-300 dark:bg-ink-700' }}">
                                    <div class="w-5 h-5 rounded-full bg-white shadow-md transform transition-transform duration-200 ease-in-out {{ $isEnabled ? 'translate-x-5' : 'translate-x-0' }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button
                            type="button"
                            wire:click="setFilter('belum_proses')"
                            class="w-full py-2 rounded-xl text-xs font-bold text-white bg-up-primary hover:bg-indigo-600 shadow-md shadow-up-primary/25 transition-all text-center"
                        >
                            Selesai &amp; Simpan Preferensi
                        </button>
                    </div>
                </div>

            {{-- VIEW 2: LIST NOTIFIKASI & APPROVALS --}}
            @else
                {{-- Pending Approval Cards --}}
                @if(($filter === 'semua' || $filter === 'belum_proses' || $filter === 'approval') && $pendingApprovals->isNotEmpty())
                    @foreach($pendingApprovals as $appr)
                        <div class="p-3 rounded-2xl bg-up-amber/10 border border-up-amber/30 dark:bg-up-amber/5 transition-all hover:bg-up-amber/15 relative group">
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-xl bg-up-amber/20 text-up-amber flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                        <span class="text-xs font-bold text-ink-900 dark:text-white">Persetujuan Diperlukan</span>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-extrabold uppercase bg-up-amber/20 text-up-amber border border-up-amber/30">
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-amber opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-up-amber animate-pulse"></span>
                                            </span>
                                            <span>Menunggu</span>
                                        </span>
                                    </div>
                                    <p class="text-xs text-ink-600 dark:text-ink-300 font-medium leading-relaxed">
                                        {{ class_basename($appr->entity_type) }} #{{ $appr->entity_id }} diajukan oleh {{ $appr->requestedBy?->name ?? 'Staf' }}.
                                    </p>
                                    <div class="mt-2 flex items-center justify-between gap-2 flex-wrap">
                                        <span class="text-[10px] text-ink-400 font-mono">{{ $appr->created_at ? $appr->created_at->diffForHumans() : 'Baru saja' }}</span>
                                        <a
                                            href="/app/approvals"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-up-amber hover:bg-amber-600 shadow-sm transition-colors cursor-pointer"
                                        >
                                            <span>Review &amp; Setujui</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- In-App Notifications --}}
                @forelse($notifications as $notif)
                    @php
                        $isUnprocessed = is_null($notif->read_at);
                        $level = $notif->payload['level'] ?? $notif->payload['type'] ?? 'info';
                        $cat = $notif->kategori ?? 'sistem';
                    @endphp
                    <div class="p-3 rounded-2xl transition-all duration-150 {{ $isUnprocessed ? 'bg-black/[0.03] dark:bg-white/[0.04] border border-up-primary/20 dark:border-white/10' : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.02] opacity-75' }}">
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-xl flex-shrink-0 mt-0.5 {{ $isUnprocessed ? 'bg-up-primary/15 text-up-primary dark:bg-up-primary/25 dark:text-indigo-300' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">
                                @if($level === 'error' || str_contains(strtolower($notif->judul ?? ''), 'gagal') || str_contains(strtolower($notif->judul ?? ''), 'peringatan'))
                                    <svg class="w-4 h-4 text-up-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @elseif($level === 'success' || str_contains(strtolower($notif->judul ?? ''), 'berhasil') || str_contains(strtolower($notif->judul ?? ''), 'selesai'))
                                    <svg class="w-4 h-4 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($cat === 'stok')
                                    <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @elseif($cat === 'servis')
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                    <span class="text-xs font-bold text-ink-900 dark:text-white truncate max-w-[180px] sm:max-w-[220px]">
                                        {{ $notif->judul ?? 'Notifikasi Sistem' }}
                                    </span>

                                    <!-- Category badge -->
                                    <span class="text-[9px] uppercase font-mono font-extrabold px-1.5 py-0.2 rounded bg-black/5 dark:bg-white/10 text-ink-400">
                                        {{ $cat }}
                                    </span>

                                    @php
                                        $txId = $notif->payload['transaksi_id'] ?? null;
                                        $tx = $txId && isset($transaksiMap) ? ($transaksiMap[$txId] ?? null) : null;
                                    @endphp
                                    @if($tx)
                                        <span class="text-[9px] uppercase font-mono font-extrabold px-1.5 py-0.2 rounded {{ in_array($tx->status, ['lunas', 'selesai']) ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : ($tx->status === 'menunggu_pembayaran' ? 'bg-up-amber/15 text-up-amber border border-up-amber/30' : 'bg-up-primary/15 text-up-primary border border-up-primary/30') }}">
                                            {{ str_replace('_', ' ', $tx->status) }}
                                        </span>
                                    @endif

                                    @if($isUnprocessed)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-extrabold uppercase bg-up-red/15 text-up-red border border-up-red/30">
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-red opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-up-red animate-pulse"></span>
                                            </span>
                                            <span>Belum Diproses</span>
                                        </span>
                                    @else
                                        <span class="text-[10px] font-medium text-ink-400">Selesai</span>
                                    @endif
                                </div>

                                <p class="text-xs text-ink-600 dark:text-ink-300 font-medium leading-relaxed">
                                    {{ $notif->konten }}
                                </p>

                                <div class="mt-2.5 flex items-center justify-between gap-2 flex-wrap">
                                    <span class="text-[10px] text-ink-400 font-mono">{{ $notif->created_at ? $notif->created_at->diffForHumans() : 'Baru saja' }}</span>

                                    <div class="flex items-center gap-1.5">
                                        @if($notif->action_url)
                                            <button
                                                type="button"
                                                wire:click="bukaAksi({{ $notif->id }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-up-primary hover:bg-indigo-600 shadow-sm transition-colors cursor-pointer"
                                            >
                                                <span>Buka / Proses</span>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                            </button>
                                        @endif

                                        @if($isUnprocessed)
                                            <button
                                                type="button"
                                                wire:click="markAsRead({{ $notif->id }})"
                                                title="Tandai Selesai / Diproses"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold text-ink-600 dark:text-ink-300 hover:text-up-mint hover:bg-up-mint/10 border border-ink-200 dark:border-white/10 transition-colors cursor-pointer"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <span>Selesai</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    @if($pendingApprovals->isEmpty())
                        <div class="py-12 px-4 text-center">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-up-mint/10 text-up-mint flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-xs font-bold text-ink-900 dark:text-white">Semua Notifikasi Bersih</p>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-1">Tidak ada notifikasi atau approval yang tertunda.</p>
                        </div>
                    @endif
                @endforelse
            @endif
        </div>

        <!-- Footer -->
        <div class="p-3 border-t border-ink-200/70 dark:border-white/10 bg-ink-50/70 dark:bg-white/[0.02] flex items-center justify-between text-[11px] text-ink-500 dark:text-ink-400">
            <span class="flex items-center gap-1.5 font-medium">
                <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                <span>Sinkron aktif (tiap 8d)</span>
            </span>
            <button
                type="button"
                wire:click="setFilter('{{ $filter === 'pengaturan' ? 'belum_proses' : 'pengaturan' }}')"
                class="hover:text-up-primary transition-colors font-semibold flex items-center gap-1 cursor-pointer"
            >
                <span>{{ $filter === 'pengaturan' ? 'Lihat Notifikasi' : 'Atur Preferensi Event' }}</span>
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </div>
</div>
