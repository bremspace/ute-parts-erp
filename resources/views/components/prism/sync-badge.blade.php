@props([
    'compact' => false,
])

@php
    $syncEnabled = (bool) config('sync.enabled', false);
    $nodeRole = (string) config('sync.node_role', 'standalone');
    $remoteUrl = trim((string) config('sync.remote_url', ''));
    $remoteConfigured = $remoteUrl !== '';
    $remoteReachable = \Illuminate\Support\Facades\Cache::get('hybrid_sync:remote_reachable');
@endphp

<div
    x-data="hybridSyncBadge({
        statusUrl: '{{ route('sync.status') }}',
        triggerUrl: '{{ route('sync.trigger') }}',
        syncEnabled: {{ $syncEnabled ? 'true' : 'false' }},
        nodeRole: '{{ $nodeRole }}',
        remoteConfigured: {{ $remoteConfigured ? 'true' : 'false' }},
        initialRemoteReachable: {{ $remoteReachable === null ? 'null' : ($remoteReachable ? 'true' : 'false') }},
        compact: {{ $compact ? 'true' : 'false' }}
    })"
    x-init="init()"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}
>
    <button
        type="button"
        @click="triggerSync()"
        :disabled="isSyncing || !isOnline || !syncEnabled"
        :title="tooltipText"
        class="group relative inline-flex items-center rounded-lg border font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-up-primary/40 disabled:cursor-not-allowed"
        :class="[
            compact ? 'gap-1.5 px-2 py-1 text-[11px]' : 'gap-2 px-2.5 py-1.5 text-xs',
            badgeClasses
        ]"
    >
        <span class="relative flex h-2 w-2 shrink-0">
            <span
                x-show="isOnline && syncEnabled && remoteConfigured && remoteReachable !== false && (isSyncing || pendingCount > 0)"
                class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                :class="dotColorClass"
            ></span>
            <span
                class="relative inline-flex rounded-full h-2 w-2"
                :class="dotColorClass"
            ></span>
        </span>

        <span x-text="labelText" class="truncate max-w-[140px]"></span>

        <svg
            x-show="isSyncing"
            class="animate-spin h-3 w-3 shrink-0"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>

        <svg
            x-show="!isSyncing && isOnline && syncEnabled"
            class="h-3 w-3 opacity-60 group-hover:opacity-100 transition-opacity shrink-0"
            :class="{ 'group-hover:rotate-180 transition-transform duration-500': !isSyncing }"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
    </button>
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('hybridSyncBadge', (config) => ({
                statusUrl: config.statusUrl,
                triggerUrl: config.triggerUrl,
                syncEnabled: config.syncEnabled,
                nodeRole: config.nodeRole,
                remoteConfigured: config.remoteConfigured,
                remoteReachable: config.initialRemoteReachable,
                compact: config.compact,
                isOnline: navigator.onLine,
                isSyncing: false,
                pendingCount: 0,
                failedCount: 0,
                lastSyncedAt: null,
                pollInterval: null,

                init() {
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.fetchStatus(true);
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });

                    if (this.syncEnabled) {
                        this.fetchStatus(true);
                        this.pollInterval = setInterval(() => {
                            if (this.isOnline && document.visibilityState === 'visible') {
                                this.fetchStatus(false);
                            }
                        }, 30000);
                    }
                },

                destroy() {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                    }
                },

                async fetchStatus(probe = false) {
                    if (!this.isOnline || !this.syncEnabled) return;
                    try {
                        const url = probe ? `${this.statusUrl}?probe=1` : this.statusUrl;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (res.ok) {
                            const json = await res.json();
                            if (json.success && json.data) {
                                this.syncEnabled = Boolean(json.data.enabled);
                                this.pendingCount = json.data.pending_count ?? 0;
                                this.failedCount = json.data.failed_count ?? 0;
                                this.lastSyncedAt = json.data.last_synced_at;
                                this.remoteConfigured = Boolean(json.data.remote_configured);
                                if (json.data.remote_reachable !== undefined) {
                                    this.remoteReachable = json.data.remote_reachable;
                                }
                            }
                        }
                    } catch (e) {
                        // Biarkan offline/poll silent tanpa spam console
                    }
                },

                async triggerSync() {
                    if (!this.isOnline || !this.syncEnabled || this.isSyncing) return;
                    this.isSyncing = true;
                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch(this.triggerUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
                            }
                        });
                        const json = await res.json();
                        if (res.ok && json.success) {
                            if (json.data) {
                                this.pendingCount = json.data.pending_count ?? 0;
                                this.failedCount = json.data.failed_count ?? 0;
                                this.lastSyncedAt = json.data.last_synced_at;
                                this.remoteConfigured = Boolean(json.data.remote_configured);
                                if (json.data.remote_reachable !== undefined) {
                                    this.remoteReachable = json.data.remote_reachable;
                                }
                            }
                            this.notify(json.message || 'Sinkronisasi berhasil dijalankan.', 'success');
                        } else {
                            this.notify(json.message || 'Gagal menjalankan sinkronisasi.', 'error');
                        }
                    } catch (e) {
                        this.notify('Koneksi terputus saat sinkronisasi.', 'error');
                    } finally {
                        this.isSyncing = false;
                    }
                },

                notify(message, type = 'info') {
                    if (typeof window.showToast === 'function') {
                        window.showToast(message, type);
                    } else if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                        window.Livewire.dispatch('alert', { type, message });
                    }
                },

                get dotColorClass() {
                    if (!this.syncEnabled) return 'bg-slate-400';
                    if (!this.isOnline || this.remoteReachable === false || this.failedCount > 0) return 'bg-red-500';
                    if (!this.remoteConfigured || this.isSyncing || this.pendingCount > 0) return 'bg-amber-500';
                    return 'bg-emerald-500';
                },

                get badgeClasses() {
                    if (!this.syncEnabled) {
                        return 'border-slate-300 bg-slate-100/80 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-400';
                    }
                    if (!this.isOnline || this.remoteReachable === false || this.failedCount > 0) {
                        return 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20';
                    }
                    if (!this.remoteConfigured || this.isSyncing || this.pendingCount > 0) {
                        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300 hover:bg-amber-500/20';
                    }
                    return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/20';
                },

                get labelText() {
                    if (!this.syncEnabled) return this.compact ? 'Off' : 'Sync Nonaktif';
                    if (!this.isOnline) return 'Offline';
                    if (!this.remoteConfigured) return this.compact ? 'No URL' : 'Remote Belum Diatur';
                    if (this.remoteReachable === false) return this.compact ? 'Putus' : 'Cloud Terputus';
                    if (this.isSyncing) return 'Sinkron...';
                    if (this.failedCount > 0) return `Gagal (${this.failedCount})`;
                    if (this.pendingCount > 0) return `Antri (${this.pendingCount})`;
                    return this.compact ? 'Sync OK' : 'Tersinkronisasi';
                },

                get tooltipText() {
                    if (!this.syncEnabled) return 'Hybrid Sync tidak aktif pada node ini.';
                    if (!this.isOnline) return 'Perangkat offline. Transaksi lokal disimpan di outbox untuk disinkronkan saat online.';
                    if (!this.remoteConfigured) return 'URL server remote (SYNC_REMOTE_URL) belum diatur di Pengaturan Integrasi.';
                    if (this.remoteReachable === false) return 'Server Cloud tidak terjangkau. Klik untuk mencoba sinkronisasi ulang.';
                    if (this.failedCount > 0) return `Ada ${this.failedCount} event gagal sinkron. Klik untuk retry.`;
                    if (this.pendingCount > 0) return `${this.pendingCount} event menunggu di outbox. Klik untuk sinkron sekarang.`;
                    return 'Terhubung ke Cloud' + (this.lastSyncedAt ? ' · Terakhir: ' + this.lastSyncedAt : '');
                }
            }));
        });
    </script>
    @endpush
@endonce
