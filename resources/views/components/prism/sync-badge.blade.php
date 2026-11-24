@props([
    'showLabel' => true,
    'class' => '',
])

<div
    x-data="{
        isOnline: navigator.onLine,
        isSyncing: false,
        pendingCount: 0,
        failedCount: 0,
        syncedCount: 0,
        lastSyncedAt: null,
        nodeRole: 'primary_store',
        syncEnabled: true,
        cloudHost: null,
        drawerOpen: false,
        pollTimer: null,

        init() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.fetchStatus();
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            this.fetchStatus();

            // Polling interval tiap 15 detik jika online
            this.pollTimer = setInterval(() => {
                if (this.isOnline && !this.isSyncing) {
                    this.fetchStatus();
                }
            }, 15000);
        },

        async fetchStatus() {
            try {
                const res = await fetch('{{ route('sync.status') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success && json.data) {
                        this.pendingCount = json.data.pending_count || 0;
                        this.failedCount = json.data.failed_count || 0;
                        this.syncedCount = json.data.synced_count || 0;
                        this.lastSyncedAt = json.data.last_synced_at;
                        this.nodeRole = json.data.node_role || 'primary_store';
                        this.syncEnabled = json.data.enabled;
                        this.cloudHost = json.data.cloud_host;
                    }
                }
            } catch (e) {
                if (!navigator.onLine) {
                    this.isOnline = false;
                }
            }
        },

        async triggerManualSync() {
            if (this.isSyncing) return;
            this.isSyncing = true;
            try {
                const res = await fetch('{{ route('sync.trigger') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || ''
                    }
                });
                const json = await res.json();
                if (json.success) {
                    this.pendingCount = json.data?.pending_count || 0;
                    this.failedCount = json.data?.failed_count || 0;
                    if (window.Alpine && window.Alpine.dispatch) {
                        window.Alpine.dispatch('alert', { type: 'success', message: 'Sinkronisasi berhasil!' });
                    }
                } else {
                    if (window.Alpine && window.Alpine.dispatch) {
                        window.Alpine.dispatch('alert', { type: 'warning', message: json.message || 'Sinkronisasi tertunda' });
                    }
                }
            } catch (e) {
                if (window.Alpine && window.Alpine.dispatch) {
                    window.Alpine.dispatch('alert', { type: 'error', message: 'Koneksi ke cloud terputus: ' + e.message });
                }
            } finally {
                this.isSyncing = false;
                this.fetchStatus();
            }
        }
    }"
    class="relative inline-flex items-center {{ $class }}"
>
    <!-- Badge Indicator Button (T-14 & T-15) -->
    <button
        type="button"
        @click="drawerOpen = !drawerOpen"
        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border text-xs font-semibold cursor-pointer transition-all duration-200 select-none shadow-xs"
        :class="{
            'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20': isOnline && pendingCount === 0 && failedCount === 0,
            'bg-amber-500/15 border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/25': isOnline && (pendingCount > 0 || failedCount > 0),
            'bg-rose-500/15 border-rose-500/30 text-rose-700 dark:text-rose-300 hover:bg-rose-500/25': !isOnline
        }"
        title="Status sinkronisasi database hybrid toko & cloud"
    >
        <!-- Status Dot / Animated Pulse / Spinner -->
        <span class="relative flex h-2.5 w-2.5 items-center justify-center">
            <template x-if="isSyncing">
                <svg class="animate-spin h-2.5 w-2.5 text-current" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </template>
            <template x-if="!isSyncing && isOnline && pendingCount === 0 && failedCount === 0">
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </template>
            <template x-if="!isSyncing && isOnline && (pendingCount > 0 || failedCount > 0)">
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500 animate-pulse"></span>
            </template>
            <template x-if="!isSyncing && !isOnline">
                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500 animate-pulse"></span>
            </template>
        </span>

        <!-- T-15: 3 Indikator Visual Ramah Staf Non-IT -->
        @if($showLabel)
            <span class="hidden sm:inline font-medium">
                <template x-if="!isOnline">
                    <span>🔴 Mode Toko Mandiri (Offline)</span>
                </template>
                <template x-if="isOnline && pendingCount === 0 && failedCount === 0">
                    <span>🟢 Online &amp; Sinkron</span>
                </template>
                <template x-if="isOnline && pendingCount > 0">
                    <span x-text="'🟡 Menyimpan di PC (' + pendingCount + ' Antrean)'"></span>
                </template>
                <template x-if="isOnline && pendingCount === 0 && failedCount > 0">
                    <span x-text="'⚠️ ' + failedCount + ' Gagal Sinkron'"></span>
                </template>
            </span>
            <span class="sm:hidden font-medium">
                <template x-if="!isOnline"><span>Offline</span></template>
                <template x-if="isOnline && pendingCount === 0"><span>Sinkron</span></template>
                <template x-if="isOnline && pendingCount > 0"><span x-text="pendingCount + ' Antre'"></span></template>
            </span>
        @endif

        <!-- Chevron Icon -->
        <svg class="w-3.5 h-3.5 opacity-60 transition-transform duration-200" :class="{ 'rotate-180': drawerOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Dropdown / Popover Detail Status (Ramah Pengguna Non-IT) -->
    <div
        x-show="drawerOpen"
        @click.outside="drawerOpen = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-1"
        x-cloak
        class="absolute right-0 top-full mt-2 w-80 rounded-2xl bg-white dark:bg-[#0E1526] border border-ink-200 dark:border-white/10 shadow-2xl p-4 z-50 flex flex-col gap-3 text-xs"
    >
        <!-- Header Popover -->
        <div class="flex items-center justify-between pb-2 border-b border-ink-100 dark:border-white/10">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full" :class="isOnline ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                <span class="font-bold text-ink-900 dark:text-white" x-text="isOnline ? 'Terhubung ke Cloud' : 'Mode Toko Mandiri (Offline)'"></span>
            </div>
            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-ink-100 dark:bg-white/5 text-ink-500" x-text="nodeRole === 'primary_store' ? 'PC Toko' : 'Cloud VPS'"></span>
        </div>

        <!-- Penjelasan Ramah Staf Non-IT -->
        <div class="p-2.5 rounded-xl border text-[11px] leading-relaxed"
             :class="isOnline ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 border-amber-500/20 text-amber-800 dark:text-amber-200'">
            <template x-if="!isOnline">
                <div>
                    <strong class="font-semibold block mb-0.5">Tenang, Kasir Tetap Beroperasi Normal!</strong>
                    Semua transaksi kasir dan cetak struk tersimpan aman di PC toko ini. Data akan otomatis dikirim ke Cloud saat internet terhubung kembali.
                </div>
            </template>
            <template x-if="isOnline && pendingCount === 0">
                <div>
                    <strong class="font-semibold block mb-0.5">Semua Data Sudah Sinkron!</strong>
                    Seluruh penjualan, mutasi stok, dan laporan di PC toko telah cocok 100% dengan Cloud VPS.
                </div>
            </template>
            <template x-if="isOnline && pendingCount > 0">
                <div>
                    <strong class="font-semibold block mb-0.5">Sedang Mengirim Data...</strong>
                    Ada <span class="font-bold" x-text="pendingCount"></span> transaksi kasir yang sedang diproses terkirim ke server pusat.
                </div>
            </template>
        </div>

        <!-- Metric Counter Antrean -->
        <div class="grid grid-cols-2 gap-2 text-center">
            <div class="p-2 rounded-xl bg-ink-50 dark:bg-white/[0.03] border border-ink-100 dark:border-white/5">
                <div class="text-[10px] text-ink-400">Antrean Belum Terkirim</div>
                <div class="text-base font-bold text-ink-900 dark:text-white" x-text="pendingCount"></div>
            </div>
            <div class="p-2 rounded-xl bg-ink-50 dark:bg-white/[0.03] border border-ink-100 dark:border-white/5">
                <div class="text-[10px] text-ink-400">Sudah Terkirim</div>
                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400" x-text="syncedCount"></div>
            </div>
        </div>

        <!-- Tombol Manual Sinkronkan Sekarang (T-16) -->
        <button
            type="button"
            @click="triggerManualSync()"
            :disabled="isSyncing || !isOnline"
            class="w-full py-2 px-3 rounded-xl bg-up-primary hover:bg-up-primary-dark active:scale-[0.98] text-white font-bold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center gap-1.5 shadow-sm"
        >
            <template x-if="isSyncing">
                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </template>
            <span x-text="isSyncing ? 'Memproses Sinkronisasi...' : 'Sinkronkan Sekarang'"></span>
        </button>
    </div>
</div>
