<div class="space-y-5">
    @php
        $role = $status['node_role'] ?? 'standalone';
        $isClient = $role === 'primary_store';
        $isCloud = $role === 'cloud_storefront';
        $enabled = (bool) ($status['enabled'] ?? false);
        $remoteOk = $status['remote_reachable'] ?? null;
    @endphp

    {{-- Wizard peran --}}
    <div class="glass-panel p-5 rounded-2xl border border-white/10 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-white">Status & Peran Sinkronisasi</h2>
                <p class="text-[11px] text-ink-400">Pantau hubungan antara client lokal dan cloud pusat (uteparts.id).</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="refreshStatus" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-ink-200 border border-white/10 cursor-pointer">
                    Refresh
                </button>
                <button wire:click="triggerSync" class="px-3 py-2 rounded-xl bg-up-primary text-white text-xs font-bold cursor-pointer">
                    Sync Sekarang
                </button>
                @if($isClient)
                    <button wire:click="bootstrap" class="px-3 py-2 rounded-xl bg-sky-500/15 text-sky-300 text-xs font-bold border border-sky-500/25 cursor-pointer">
                        Bootstrap dari Cloud
                    </button>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                <p class="text-[10px] uppercase tracking-wider text-ink-400">Peran node ini</p>
                <p class="mt-1 text-sm font-bold {{ $isClient ? 'text-up-primary' : ($isCloud ? 'text-sky-400' : 'text-ink-300') }}">
                    {{ $isClient ? 'CLIENT / REPLIKA' : ($isCloud ? 'CLOUD / SUMBER' : 'STANDALONE') }}
                </p>
                <p class="text-[10px] text-ink-400 font-mono">{{ $status['node_id'] ?? '-' }}</p>
            </div>
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                <p class="text-[10px] uppercase tracking-wider text-ink-400">Status sync</p>
                <p class="mt-1 text-sm font-bold {{ $enabled ? 'text-up-mint' : 'text-up-red' }}">
                    {{ $enabled ? 'AKTIF' : 'NONAKTIF' }}
                </p>
                <p class="text-[10px] text-ink-400">{{ $status['cloud_host'] ?? 'remote belum diatur' }}</p>
            </div>
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                <p class="text-[10px] uppercase tracking-wider text-ink-400">Koneksi ke remote</p>
                <p class="mt-1 text-sm font-bold {{ $remoteOk === true ? 'text-up-mint' : ($remoteOk === false ? 'text-up-red' : 'text-ink-400') }}">
                    {{ $remoteOk === true ? 'TERHUBUNG' : ($remoteOk === false ? 'TERPUTUS' : 'BELUM DICEK') }}
                </p>
                <p class="text-[10px] text-ink-400">Last sync: {{ $status['last_synced_at'] ?? '-' }}</p>
            </div>
        </div>

        @if(! $enabled || ! ($status['remote_configured'] ?? false))
            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-300">
                ⚠ Sinkronisasi belum siap. Pastikan <span class="font-mono">SYNC_ENABLED=true</span>,
                <span class="font-mono">SYNC_REMOTE_URL</span> terisi, dan <span class="font-mono">SYNC_API_SECRET</span>
                identik di cloud dan client.
            </div>
        @endif

        @if($message !== '')
            <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 text-[11px] text-indigo-300">{{ $message }}</div>
        @endif
    </div>

    {{-- Statistik antrean --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
            ['Pending', 'pending_count', 'text-up-amber'],
            ['Processing', 'processing_count', 'text-sky-400'],
            ['Stuck', 'stuck_processing_count', 'text-up-red'],
            ['Gagal', 'failed_count', 'text-up-red'],
            ['Tersinkron', 'synced_count', 'text-up-mint'],
        ] as $stat)
            <div class="glass-panel p-3 rounded-xl border border-white/5">
                <p class="text-[10px] text-ink-400">{{ $stat[0] }}</p>
                <p class="text-xl font-black {{ $stat[2] }}">{{ $status[$stat[1]] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    {{-- Outbox & Inbox --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="glass-panel p-4 rounded-2xl border border-white/10">
            <h3 class="text-xs font-bold text-white mb-3">Antrean Keluar (Outbox)</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($outbox as $row)
                    <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 text-[10px] space-y-1">
                        <div class="flex justify-between gap-2">
                            <span class="font-mono text-ink-200">{{ $row['event_type'] }}</span>
                            <span class="font-bold {{ $row['status'] === 'synced' ? 'text-up-mint' : ($row['status'] === 'failed' ? 'text-up-red' : 'text-up-amber') }}">{{ strtoupper($row['status']) }}</span>
                        </div>
                        <p class="text-ink-400 truncate">{{ $row['sync_uuid'] }} · attempt {{ $row['attempts'] }} · {{ $row['created_at'] }}</p>
                        @if($row['error'] !== '')
                            <p class="text-up-red break-words">{{ $row['error'] }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-[11px] text-ink-400">Belum ada event keluar.</p>
                @endforelse
            </div>
        </div>

        <div class="glass-panel p-4 rounded-2xl border border-white/10">
            <h3 class="text-xs font-bold text-white mb-3">Event Masuk (Inbox)</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($inbox as $row)
                    <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 text-[10px] space-y-1">
                        <div class="flex justify-between gap-2">
                            <span class="font-mono text-ink-200">{{ $row['event_type'] }}</span>
                            <span class="font-bold {{ $row['status'] === 'processed' ? 'text-up-mint' : 'text-up-red' }}">{{ strtoupper($row['status']) }}</span>
                        </div>
                        <p class="text-ink-400 truncate">{{ $row['source_node'] }} · {{ $row['sync_uuid'] }} · {{ $row['created_at'] }}</p>
                        @if($row['error'] !== '')
                            <p class="text-up-red break-words">{{ $row['error'] }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-[11px] text-ink-400">Belum ada event masuk.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
