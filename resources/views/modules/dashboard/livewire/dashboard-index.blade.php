<div class="space-y-6">
    <!-- Greeting -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-white">
                Halo, {{ auth()->user()?->name }} 👋
            </h2>
            <p class="text-xs text-ink-400 mt-0.5">
                {{ now()->format('l, d F Y') }} — ringkasan operasional di seluruh cabang.
            </p>
        </div>
        <span class="text-[10px] font-mono text-ink-400 bg-white/5 border border-white/10 px-2.5 py-1 rounded-full">
            env:{{ $serverStatus['env'] }} · cache:{{ $serverStatus['cache'] }} · queue:{{ $serverStatus['queue'] }}
        </span>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-prism.glass-card class="p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Omzet Hari Ini</p>
                    <p class="text-2xl font-black text-up-mint tabular-nums mt-1">Rp {{ number_format($omzetHariIni['cabang_aktif'], 0, ',', '.') }}</p>
                    <p class="text-[11px] text-ink-500 mt-0.5">Semua cabang: <span class="tabular-nums">Rp {{ number_format($omzetHariIni['semua_cabang'], 0, ',', '.') }}</span></p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-up-mint/10 border border-up-mint/30 flex items-center justify-center text-up-mint">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-[11px] text-ink-500 mt-2">{{ $omzetHariIni['jumlah_transaksi'] }} transaksi selesai hari ini</p>
        </x-prism.glass-card>

        <x-prism.glass-card class="p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Antrian Servis Aktif</p>
                    <p class="text-2xl font-black text-up-primary tabular-nums mt-1">{{ $antrianServis['aktif'] }} <span class="text-xs font-semibold text-ink-400">tiket</span></p>
                    <p class="text-[11px] text-up-amber mt-0.5">{{ $antrianServis['menunggu_approval'] }} menunggu approval estimasi</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-up-primary/10 border border-up-primary/30 flex items-center justify-center text-up-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                </div>
            </div>
            <a href="/app/servis" class="inline-block text-[11px] text-up-primary hover:text-indigo-400 mt-2 font-semibold">Buka Kanban Servis →</a>
        </x-prism.glass-card>

        <x-prism.glass-card class="p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Stok Kritis</p>
                    <p class="text-2xl font-black text-up-amber tabular-nums mt-1">{{ $stokKritis['total'] }} <span class="text-xs font-semibold text-ink-400">item</span></p>
                    <p class="text-[11px] text-up-red mt-0.5">Dibawah / sama dengan minimum</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-up-amber/10 border border-up-amber/30 flex items-center justify-center text-up-amber">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <a href="/app/wms" class="inline-block text-[11px] text-up-primary hover:text-indigo-400 mt-2 font-semibold">Kelola Gudang →</a>
        </x-prism.glass-card>

        <x-prism.glass-card class="p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Komisi Pending</p>
                    <p class="text-2xl font-black text-up-accent tabular-nums mt-1">Rp {{ number_format($komisiPending, 0, ',', '.') }}</p>
                    <p class="text-[11px] text-ink-500 mt-0.5">Menunggu approval finance</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-up-accent/10 border border-up-accent/30 flex items-center justify-center text-up-accent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                </div>
            </div>
            <a href="/app/reseller" class="inline-block text-[11px] text-up-primary hover:text-indigo-400 mt-2 font-semibold">Approval Komisi →</a>
        </x-prism.glass-card>
    </div>

    <!-- Piutang jatuh tempo + Transaksi terbaru -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-prism.glass-card title="Piutang Jatuh Tempo (7 hari)" :subtitle="$piutangJatuhTempo['total'] . ' piutang perlu perhatian'">
            <div class="space-y-2">
                @forelse($piutangJatuhTempo['items'] as $p)
                    <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                        <div>
                            <p class="text-xs font-semibold text-white">{{ $p->pelanggan?->nama ?? '-' }}</p>
                            <p class="text-[10px] text-ink-500 font-mono">{{ $p->no_piutang }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-up-amber tabular-nums">Rp {{ number_format($p->sisa, 0, ',', '.') }}</p>
                            <p class="text-[10px] {{ $p->jatuh_tempo_lewat ? 'text-up-red font-bold' : 'text-ink-500' }} tabular-nums">
                                {{ $p->jatuh_tempo?->format('d/m/Y') }} {{ $p->jatuh_tempo_lewat ? '(LEWAT)' : '' }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-ink-500 py-4 text-center">Tidak ada piutang jatuh tempo — aman 🎉</p>
                @endforelse
            </div>
            <div class="mt-3">
                <a href="/app/akunting" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold">Buka Modul Akunting →</a>
            </div>
        </x-prism.glass-card>

        <x-prism.glass-card title="Transaksi Terbaru" subtitle="Semua sumber (POS, Marketplace)">
            <div class="space-y-2">
                @forelse($transaksiTerbaru as $t)
                    <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-white font-mono">{{ $t->no_transaksi }}</p>
                            <p class="text-[10px] text-ink-500 truncate">
                                {{ $t->sumber }} · {{ $t->cabang?->nama }} · {{ $t->created_at->format('H:i d/m') }}
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-xs font-bold text-white tabular-nums">Rp {{ number_format($t->total_akhir, 0, ',', '.') }}</p>
                            <x-prism.status-pill :status="$t->status" size="sm" />
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-ink-500 py-4 text-center">Belum ada transaksi tercatat.</p>
                @endforelse
            </div>
        </x-prism.glass-card>
    </div>

    <!-- Stok kritis detail -->
    @if($stokKritis['items']->isNotEmpty())
        <x-prism.glass-card title="Item Stok Menipis" :subtitle="$stokKritis['total'] . ' item di bawah/equal minimum'">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach($stokKritis['items'] as $s)
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-xs font-bold text-white truncate">{{ $s->produk?->nama }}</p>
                        <p class="text-[10px] text-ink-500">{{ $s->gudang?->nama }}</p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs font-black text-up-red tabular-nums">{{ $s->jumlah }}</span>
                            <span class="text-[10px] text-ink-400">min {{ $s->jumlah_minimum }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-prism.glass-card>
    @endif
</div>