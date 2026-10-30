<div class="p-4 sm:p-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-ink-900 dark:text-white">Riwayat Transaksi</h1>
            <p class="text-xs sm:text-sm text-ink-400 mt-1">
                Pusat riwayat transaksi global, penjualan kasir POS, rekap servis gadget selesai, dan mutasi kas laci.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            @canany(['pos.view', 'pos.view-own', 'pos.create'])
            <a href="/app/pos" class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-semibold shadow-sm transition active:scale-[0.98] min-h-[44px]">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <span>Buka Kasir POS (F2)</span>
            </a>
            @endcanany
            @canany(['servis.view', 'servis.create'])
            <a href="/app/servis" class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-white text-xs font-semibold border border-white/10 transition active:scale-[0.98] min-h-[44px]">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                <span>Kanban Servis</span>
            </a>
            @endcanany
        </div>
    </div>

    {{-- Kartu Ringkasan (Hari / Tanggal Terpilih) --}}
    <div class="flex items-stretch gap-3 overflow-x-auto scrollbar-none py-1 sm:grid sm:grid-cols-2 lg:grid-cols-6 -mx-4 px-4 sm:mx-0 sm:px-0 flex-nowrap snap-x snap-mandatory">
        <div class="w-[165px] min-w-[165px] sm:w-auto sm:min-w-0 flex-shrink-0 p-4 rounded-2xl glass-panel border border-white/10 snap-start">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Penjualan POS</span>
                <span class="px-1.5 py-0.5 rounded bg-white/5 text-[10px] font-mono">{{ $ringkasan['count_penjualan_pos'] }} Trx</span>
            </div>
            <p class="text-lg sm:text-xl font-bold text-white tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_penjualan_pos'], 0, ',', '.') }}
            </p>
        </div>
        <div class="w-[165px] min-w-[165px] sm:w-auto sm:min-w-0 flex-shrink-0 p-4 rounded-2xl glass-panel border border-white/10 snap-start">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Pesanan Online</span>
                <span class="px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 text-[10px] font-mono">{{ $ringkasan['count_pesanan_online'] }} Trx</span>
            </div>
            <p class="text-lg sm:text-xl font-bold text-indigo-400 tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_pesanan_online'], 0, ',', '.') }}
            </p>
        </div>
        <div class="w-[165px] min-w-[165px] sm:w-auto sm:min-w-0 flex-shrink-0 p-4 rounded-2xl glass-panel border border-white/10 snap-start">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Servis Lunas</span>
                <span class="px-1.5 py-0.5 rounded bg-teal-500/10 text-teal-400 text-[10px] font-mono">{{ $ringkasan['count_servis_selesai'] }} Unit</span>
            </div>
            <p class="text-lg sm:text-xl font-bold text-teal-400 tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_servis_selesai'], 0, ',', '.') }}
            </p>
        </div>
        <div class="w-[165px] min-w-[165px] sm:w-auto sm:min-w-0 flex-shrink-0 p-4 rounded-2xl glass-panel border border-white/10 snap-start">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Total Retur</span>
                <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-up-accent text-[10px] font-mono">{{ $ringkasan['count_retur'] ?? 0 }} Trx</span>
            </div>
            <p class="text-lg sm:text-xl font-bold text-up-accent tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_retur'] ?? 0, 0, ',', '.') }}
            </p>
        </div>
        <div class="w-[165px] min-w-[165px] sm:w-auto sm:min-w-0 flex-shrink-0 p-4 rounded-2xl glass-panel border border-white/10 snap-start">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Mutasi Kas Laci</span>
                <span class="text-[10px] text-ink-500">In / Out</span>
            </div>
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs font-bold text-up-mint tabular-nums">+{{ number_format($ringkasan['total_mutasi_masuk'], 0, ',', '.') }}</span>
                <span class="text-ink-600">/</span>
                <span class="text-xs font-bold text-up-accent tabular-nums">-{{ number_format($ringkasan['total_mutasi_keluar'], 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="w-[165px] min-w-[165px] sm:w-auto sm:min-w-0 flex-shrink-0 p-4 rounded-2xl glass-panel border border-white/10 snap-start">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Total Arus Bersih</span>
                <span class="px-1.5 py-0.5 rounded bg-up-mint/10 text-up-mint text-[10px] font-bold">Net</span>
            </div>
            <p class="text-lg sm:text-xl font-bold {{ $ringkasan['total_kas_bersih'] >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_kas_bersih'], 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Tab Navigasi (Horizontal Scroll on Mobile) --}}
    <div class="flex items-center gap-2 border-b border-white/10 pb-2 overflow-x-auto scrollbar-none flex-nowrap -mx-4 px-4 sm:mx-0 sm:px-0 snap-x snap-mandatory">
        <button
            wire:click="$set('activeTab', 'global')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'global' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Semua Transaksi (Global)</span>
        </button>
        <button
            wire:click="$set('activeTab', 'pos')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'pos' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            <span>Penjualan Kasir POS</span>
        </button>
        <button
            wire:click="$set('activeTab', 'online')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'online' ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <span>Pesanan Online (Web & Omni)</span>
        </button>
        <button
            wire:click="$set('activeTab', 'servis')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'servis' ? 'bg-up-mint text-white shadow-sm shadow-up-mint/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            <span>Rekap Servis Selesai & Lunas</span>
        </button>
        <button
            wire:click="$set('activeTab', 'pembelian')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'pembelian' ? 'bg-sky-600 text-white shadow-sm shadow-sky-600/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span>Pembelian (PO)</span>
        </button>
        <button
            wire:click="$set('activeTab', 'retur')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'retur' ? 'bg-amber-600 text-white shadow-sm shadow-amber-600/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
            <span>Histori Retur Transaksi</span>
        </button>
        <button
            wire:click="$set('activeTab', 'mutasi_kas')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.98] cursor-pointer flex items-center gap-2 whitespace-nowrap flex-shrink-0 min-h-[44px] snap-start {{ $activeTab === 'mutasi_kas' ? 'bg-up-accent text-white shadow-sm shadow-up-accent/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Mutasi Kas Laci (In / Out)</span>
        </button>
    </div>

    {{-- Filter Bar (Compact Collapsible on Mobile, Expanded on Desktop) --}}
    <div x-data="{ openFilter: false }" class="space-y-2">
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari no transaksi / no tiket / nama / keterangan..."
                    class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-white border border-white/10 min-h-[44px]"
                />
                @if($search)
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white text-xs p-1 cursor-pointer"
                        title="Hapus pencarian"
                    >✕</button>
                @endif
            </div>
            {{-- Mobile Toggle Button --}}
            <button
                type="button"
                @click="openFilter = !openFilter"
                class="sm:hidden px-3.5 py-2.5 rounded-xl border border-white/10 text-xs font-bold flex items-center justify-center gap-1.5 transition cursor-pointer min-h-[44px] shrink-0"
                :class="openFilter || ('{{ $filterTanggal }}' || '{{ $filterStatus }}' || '{{ $filterMetode }}') ? 'bg-up-primary text-white border-up-primary' : 'bg-white/5 text-ink-300 hover:bg-white/10'"
                aria-label="Filter"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span>Filter</span>
                @if($filterTanggal || $filterStatus || $filterMetode)
                    <span class="w-2 h-2 rounded-full bg-up-accent animate-pulse"></span>
                @endif
            </button>
        </div>

        {{-- Filter Parameters (Always visible on sm+, expandable on mobile) --}}
        <div :class="openFilter ? 'flex' : 'hidden sm:flex'" class="flex-wrap items-center gap-2 pt-1 sm:pt-0">
            {{-- Preset & Custom Date Filters --}}
            <div class="w-full sm:w-auto">
                <select
                    wire:model.live="filterTanggalPreset"
                    class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]"
                    aria-label="Preset Periode Tanggal"
                >
                    <option value="today">Hari Ini</option>
                    <option value="yesterday">Kemarin</option>
                    <option value="this_week">Minggu Ini</option>
                    <option value="this_month">Bulan Ini</option>
                    <option value="custom">Rentang Kustom</option>
                </select>
            </div>

            @if($filterTanggalPreset === 'custom')
                <div class="w-full sm:w-auto flex items-center gap-1.5">
                    <input
                        type="date"
                        wire:model.live.debounce.300ms="filterTanggalDari"
                        class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900/90 border border-white/10 cursor-pointer min-h-[44px] focus:ring-1 focus:ring-up-primary"
                        title="Tanggal Dari"
                        aria-label="Tanggal Dari"
                    />
                    <span class="text-ink-400 text-xs px-1">s/d</span>
                    <input
                        type="date"
                        wire:model.live.debounce.300ms="filterTanggalSampai"
                        class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900/90 border border-white/10 cursor-pointer min-h-[44px] focus:ring-1 focus:ring-up-primary"
                        title="Tanggal Sampai"
                        aria-label="Tanggal Sampai"
                    />
                </div>
            @endif

            @if($activeTab === 'global')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Jenis Transaksi</option>
                        <option value="pos">Penjualan Kasir POS</option>
                        <option value="online">Pesanan Online (Web & Omni)</option>
                        <option value="servis">Pelunasan Servis Gadget</option>
                        <option value="retur">Retur Penjualan</option>
                        <option value="retur_pembelian">Retur Pembelian (Supplier)</option>
                        <option value="pembelian">Pembelian (PO)</option>
                        <option value="void">Riwayat Void (Batal)</option>
                        <option value="mutasi_masuk">Kas Masuk (Laci)</option>
                        <option value="mutasi_keluar">Kas Keluar (Laci)</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterMetode" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Metode Bayar</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                        <option value="split">Split</option>
                        <option value="piutang">Piutang / Kasbon</option>
                    </select>
                </div>
            @elseif($activeTab === 'pos')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterMetode" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                        <option value="split">Split</option>
                        <option value="piutang">Piutang / Kasbon</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Status</option>
                        <option value="selesai">Selesai</option>
                        <option value="void">Void (Dibatalkan)</option>
                        <option value="retur">Terkait Retur</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>
                </div>
            @elseif($activeTab === 'online')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterPlatform" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Platform</option>
                        <option value="marketplace">Web Store Ute</option>
                        <option value="shopee">Shopee</option>
                        <option value="tokopedia">Tokopedia</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Status Order</option>
                        <option value="menunggu_pembayaran">Menunggu Bayar</option>
                        <option value="lunas">Lunas</option>
                        <option value="diproses">Diproses</option>
                        <option value="dikirim">Dikirim</option>
                        <option value="siap_diambil">Siap Diambil (Pickup)</option>
                        <option value="selesai">Selesai</option>
                        <option value="kadaluarsa">Kadaluarsa</option>
                        <option value="batal">Batal</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterMetodePengiriman" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Pengiriman</option>
                        <option value="pickup">Ambil di Toko (Pickup)</option>
                        <option value="kurir">Kurir Ekspedisi</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterMetode" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Metode</option>
                        <option value="duitku">Duitku</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                        <option value="tunai">Tunai</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <button
                        type="button"
                        wire:click="sinkronkanSemuaOnline"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-3.5 py-2.5 rounded-xl bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 hover:text-white border border-indigo-500/30 text-xs font-bold transition flex items-center justify-center gap-1.5 min-h-[44px] cursor-pointer"
                        title="Sinkronkan status transaksi online Duitku & Omnichannel"
                    >
                        <span wire:loading.remove wire:target="sinkronkanSemuaOnline">🔄 Sync Online</span>
                        <span wire:loading wire:target="sinkronkanSemuaOnline" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Sinkronisasi...
                        </span>
                    </button>
                </div>
            @elseif($activeTab === 'servis')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Selesai & Lunas (Rekap Penuh)</option>
                        <option value="selesai">Selesai (Siap Diambil)</option>
                        <option value="diambil">Selesai (Sudah Diambil)</option>
                        <option value="lunas">Sudah Lunas</option>
                        <option value="belum_lunas">Selesai Belum Lunas</option>
                        <option value="semua">Semua Status Tiket</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterMetode" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                        <option value="split">Split</option>
                    </select>
                </div>
            @elseif($activeTab === 'pembelian')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Status PO</option>
                        <option value="diterima">Diterima</option>
                        <option value="draft">Draft</option>
                        <option value="diajukan">Diajukan</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="dibatalkan">Dibatalkan</option>
                        <option value="bisa_retur">Bisa Diretur</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterMetode" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="utang">Utang</option>
                    </select>
                </div>
            @elseif($activeTab === 'retur')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterJenisRetur" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Jenis Retur</option>
                        <option value="penjualan">Retur Penjualan (Konsumen)</option>
                        <option value="pembelian">Retur Pembelian (Supplier)</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Status</option>
                        <option value="selesai">Selesai</option>
                        <option value="draft">Draft</option>
                        <option value="menunggu">Menunggu</option>
                        <option value="disetujui">Disetujui</option>
                    </select>
                </div>
            @elseif($activeTab === 'mutasi_kas')
                <div class="w-full sm:w-auto">
                    <select wire:model.live="filterStatus" class="w-full sm:w-auto px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer min-h-[44px]">
                        <option value="">Semua Jenis Mutasi</option>
                        <option value="keluar">Pengeluaran (Kas Keluar)</option>
                        <option value="masuk">Pemasukan (Kas Masuk)</option>
                    </select>
                </div>
            @endif

            <button
                wire:click="resetFilter"
                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-xs font-semibold cursor-pointer transition text-center min-h-[44px] flex items-center justify-center">
                Reset Filter
            </button>
        </div>
    </div>

    {{-- Content Table --}}
    <div
        x-data="{ view: localStorage.getItem('pos-riwayat-view') || 'list', isDesktop: window.innerWidth >= 768 }"
        x-init="$watch('view', value => localStorage.setItem('pos-riwayat-view', value)); window.addEventListener('resize', () => isDesktop = window.innerWidth >= 768)"
        class="glass-panel rounded-2xl border border-white/10 overflow-hidden shadow-xl"
    >
        {{-- Unified Panel Header with View Mode Switcher --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-b border-white/10 bg-white/[0.02]">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="w-2.5 h-2.5 rounded-full {{ $activeTab === 'global' ? 'bg-up-primary' : ($activeTab === 'pos' ? 'bg-up-primary' : ($activeTab === 'online' ? 'bg-indigo-500' : ($activeTab === 'servis' ? 'bg-teal-400' : ($activeTab === 'pembelian' ? 'bg-sky-500' : ($activeTab === 'retur' ? 'bg-amber-500' : 'bg-up-accent'))))) }}"></span>
                <h2 class="text-xs font-bold text-white tracking-wide uppercase">
                    @if($activeTab === 'global') Riwayat Semua Transaksi
                    @elseif($activeTab === 'pos') Penjualan Kasir POS
                    @elseif($activeTab === 'online') Pesanan Online & Omnichannel
                    @elseif($activeTab === 'servis') Rekap Servis Selesai & Lunas
                    @elseif($activeTab === 'pembelian') Pembelian (Purchase Order)
                    @elseif($activeTab === 'retur') Histori Retur Transaksi
                    @elseif($activeTab === 'mutasi_kas') Mutasi Kas Laci
                    @endif
                </h2>
                @if($dataList)
                    <span class="text-[11px] text-ink-400 font-medium">({{ $dataList->total() }} data)</span>
                @endif
            </div>
            <div class="flex items-center justify-between sm:justify-end gap-3">
                <p class="text-[11px] text-ink-400 hidden lg:block">Aksi transaksi selalu tersedia pada setiap tampilan.</p>
                <div class="inline-flex rounded-xl border border-white/10 bg-white/[0.03] p-1" role="group" aria-label="Pilih tampilan riwayat">
                    <button
                        type="button"
                        @click="view = 'list'"
                        :class="view === 'list' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white'"
                        class="min-h-[36px] rounded-lg px-3 text-[11px] font-bold transition active:scale-[.97] flex items-center gap-1.5 cursor-pointer"
                        title="Tampilan Tabel / Daftar"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <span>Daftar</span>
                    </button>
                    <button
                        type="button"
                        @click="view = 'grid'"
                        :class="view === 'grid' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white'"
                        class="min-h-[36px] rounded-lg px-3 text-[11px] font-bold transition active:scale-[.97] flex items-center gap-1.5 cursor-pointer"
                        title="Tampilan Kartu / Grid"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span>Kartu</span>
                    </button>
                </div>
            </div>
        </div>

        @if($activeTab === 'global')
            {{-- TAB 0: RIWAYAT TRANSAKSI GLOBAL --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[850px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No Referensi</th>
                                <th class="py-3 px-4">Jenis Transaksi</th>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Pihak / Keterangan</th>
                                <th class="py-3 px-4">Operator / Kasir</th>
                                <th class="py-3 px-4">Metode</th>
                                <th class="py-3 px-4 text-right">Nominal Arus Kas</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                @php
                                    $waktuCarbon = $row->waktu ? \Illuminate\Support\Carbon::parse($row->waktu) : null;
                                @endphp
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap {{ $row->status === 'void' ? 'bg-up-red/[0.035]' : '' }}">
                                    <td class="py-3.5 px-4 font-mono font-bold {{ $row->status === 'void' ? 'text-up-red line-through decoration-up-red/70' : 'text-white' }}">
                                        {{ $row->no_referensi }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($row->jenis === 'pos')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/15 text-indigo-300 border border-up-primary/20">
                                                🛒 Kasir POS
                                            </span>
                                        @elseif($row->jenis === 'online')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/20">
                                                🌐 Online ({{ $row->operator }})
                                            </span>
                                        @elseif($row->jenis === 'servis')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-500/15 text-teal-300 border border-teal-500/20">
                                                🔧 Servis Gadget
                                            </span>
                                        @elseif($row->jenis === 'mutasi_masuk')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/20">
                                                📥 Kas Masuk
                                            </span>
                                        @elseif($row->jenis === 'mutasi_keluar')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-red/15 text-up-red border border-up-red/20">
                                                📤 Kas Keluar
                                            </span>
                                        @elseif($row->jenis === 'retur')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-accent/15 text-up-accent border border-up-accent/25">
                                                ↩ Retur Konsumen
                                            </span>
                                        @elseif($row->jenis === 'retur_pembelian')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/15 text-sky-300 border border-sky-500/20">
                                                ↩ Retur Supplier
                                            </span>
                                        @elseif($row->jenis === 'pembelian')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-600/15 text-sky-300 border border-sky-600/20">
                                                📦 Pembelian PO
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">
                                        {{ $waktuCarbon ? $waktuCarbon->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-white">{{ $row->pihak }}</div>
                                        @if(!empty($row->deskripsi))
                                            <div class="text-[10px] text-ink-400 truncate max-w-[200px]" title="{{ $row->deskripsi }}">
                                                {{ $row->deskripsi }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-200">
                                        {{ $row->operator }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ in_array(strtolower($row->metode), ['tunai', 'kas laci']) ? 'bg-up-mint/10 text-up-mint' : (strtolower($row->metode) === 'piutang' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-white/10 text-ink-200') }}">
                                            {{ $row->metode }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold tabular-nums {{ $row->status === 'void' ? 'line-through decoration-up-red/70' : '' }}">
                                        @if($row->arah_kas === 'keluar')
                                            <span class="text-up-red">- Rp {{ number_format($row->nominal, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-up-mint">+ Rp {{ number_format($row->nominal, 0, ',', '.') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if($row->status === 'void')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/15 text-up-red border border-up-red/30">
                                                VOID
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array(strtolower($row->status), ['selesai', 'lunas', 'kas masuk']) ? 'bg-up-mint/10 text-up-mint' : 'bg-white/10 text-ink-300' }}">
                                                {{ ucfirst($row->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($row->jenis === 'pos' || $row->jenis === 'online')
                                                <button
                                                    wire:click="lihatDetailTransaksi({{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                    title="Lihat Detail Transaksi"
                                                >
                                                    Detail
                                                </button>
                                                <button
                                                    wire:click="bukaRiwayat('transaksi', {{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                    title="Audit / Log Aktivitas Transaksi"
                                                >
                                                    Log
                                                </button>
                                                <button
                                                    wire:click="cetakUlangStruk('{{ $row->jenis }}', {{ $row->target_id }})"
                                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                    title="Cetak Ulang Struk"
                                                >
                                                    🖨️
                                                </button>
                                            @elseif($row->jenis === 'servis')
                                                <button
                                                    wire:click="lihatDetailServis({{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-teal-500/15 hover:bg-teal-500/25 text-teal-300 text-[11px] font-semibold transition cursor-pointer"
                                                    title="Lihat Detail Rekap Servis"
                                                >
                                                    Detail
                                                </button>
                                                <button
                                                    wire:click="bukaRiwayat('servis', {{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                    title="Audit / Log Aktivitas Servis"
                                                >
                                                    Log
                                                </button>
                                                <button
                                                    wire:click="cetakUlangStruk('servis', {{ $row->target_id }})"
                                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                    title="Cetak Ulang Nota Servis"
                                                >
                                                    🖨️
                                                </button>
                                            @elseif(str_starts_with($row->jenis, 'mutasi'))
                                                <button
                                                    wire:click="lihatDetailMutasi({{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                    title="Lihat Detail Mutasi Kas"
                                                >
                                                    Detail
                                                </button>
                                            @elseif($row->jenis === 'retur')
                                                <button
                                                    wire:click="lihatDetailRetur({{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-up-accent/15 hover:bg-up-accent/25 text-up-accent text-[11px] font-semibold transition cursor-pointer"
                                                    title="Lihat Detail Retur"
                                                >
                                                    Detail
                                                </button>
                                                <button
                                                    wire:click="bukaRiwayat('retur_penjualan', {{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                    title="Audit / Log Aktivitas Retur"
                                                >
                                                    Log
                                                </button>
                                                <button
                                                    wire:click="cetakUlangStruk('retur', {{ $row->target_id }})"
                                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                    title="Cetak Struk Retur"
                                                >
                                                    🖨️
                                                </button>
                                            @elseif($row->jenis === 'retur_pembelian')
                                                <button wire:click="lihatDetailReturPembelian({{ $row->target_id }})" class="px-2.5 py-1.5 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 text-sky-300 text-[11px] font-semibold transition cursor-pointer">Detail</button>
                                                <button wire:click="cetakUlangStruk('retur_pembelian', {{ $row->target_id }})" class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer">🖨️</button>
                                            @elseif($row->jenis === 'pembelian')
                                                <button wire:click="lihatDetailPembelian({{ $row->target_id }})" class="px-2.5 py-1.5 rounded-lg bg-sky-600/15 hover:bg-sky-600/25 text-sky-300 text-[11px] font-semibold transition cursor-pointer">Detail</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada riwayat transaksi ditemukan untuk tanggal dan filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        @php
                            $waktuCarbon = $row->waktu ? \Illuminate\Support\Carbon::parse($row->waktu) : null;
                        @endphp
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-up-primary/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            {{-- Header Card / Row --}}
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-mono font-bold text-xs {{ $row->status === 'void' ? 'text-up-red line-through decoration-up-red/70' : 'text-white' }} truncate">
                                                {{ $row->no_referensi }}
                                            </span>
                                            @if($row->status === 'void')
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-up-red/20 text-up-red border border-up-red/40">
                                                    VOID
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] text-ink-400 tabular-nums mt-0.5">
                                            <span>📅 {{ $waktuCarbon ? $waktuCarbon->format('d/m/Y H:i') : '-' }}</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        @if($row->status !== 'void')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array(strtolower($row->status), ['selesai', 'lunas', 'kas masuk']) ? 'bg-up-mint/15 text-up-mint border border-up-mint/25' : 'bg-white/10 text-ink-300 border border-white/10' }}">
                                                {{ ucfirst($row->status) }}
                                            </span>
                                        @endif
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase {{ in_array(strtolower($row->metode), ['tunai', 'kas laci']) ? 'bg-up-mint/10 text-up-mint' : 'bg-white/10 text-ink-300' }}">
                                            {{ $row->metode }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Badge Jenis Transaksi & Nominal --}}
                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-2' : 'flex items-center justify-between gap-2'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            @if($row->jenis === 'pos')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-primary/15 text-indigo-300 border border-up-primary/20">
                                                    🛒 POS
                                                </span>
                                            @elseif($row->jenis === 'online')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/20">
                                                    🌐 Online
                                                </span>
                                            @elseif($row->jenis === 'servis')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-500/15 text-teal-300 border border-teal-500/20">
                                                    🔧 Servis
                                                </span>
                                            @elseif($row->jenis === 'mutasi_masuk')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/20">
                                                    📥 Kas Masuk
                                                </span>
                                            @elseif($row->jenis === 'mutasi_keluar')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-red/15 text-up-red border border-up-red/20">
                                                    📤 Kas Keluar
                                                </span>
                                            @elseif($row->jenis === 'retur')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-accent/15 text-up-accent border border-up-accent/25">
                                                    ↩ Retur
                                                </span>
                                            @elseif($row->jenis === 'retur_pembelian')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/15 text-sky-300 border border-sky-500/20">
                                                    ↩ Retur Supplier
                                                </span>
                                            @elseif($row->jenis === 'pembelian')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-600/15 text-sky-300 border border-sky-600/20">
                                                    📦 Pembelian PO
                                                </span>
                                            @endif
                                        </div>

                                        <div class="text-right">
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 block font-bold" x-show="view === 'grid'">Arus Kas</span>
                                            @if($row->arah_kas === 'keluar')
                                                <span class="text-up-red font-black tabular-nums {{ $row->status === 'void' ? 'line-through decoration-up-red/70' : '' }}" :class="view === 'grid' ? 'text-base' : 'text-sm'">
                                                    - Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-up-mint font-black tabular-nums {{ $row->status === 'void' ? 'line-through decoration-up-red/70' : '' }}" :class="view === 'grid' ? 'text-base' : 'text-sm'">
                                                    + Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Info Pihak & Operator --}}
                                    <div class="text-xs space-y-1 pt-1.5 border-t border-white/5" x-show="view === 'grid'">
                                        <div class="flex justify-between items-center text-[11px]">
                                            <span class="text-ink-400">Pihak:</span>
                                            <span class="text-white font-medium truncate max-w-[170px]" title="{{ $row->pihak }}">{{ $row->pihak }}</span>
                                        </div>
                                        <div class="flex justify-between items-center text-[11px]">
                                            <span class="text-ink-400">Operator:</span>
                                            <span class="text-ink-300 font-medium truncate max-w-[150px]">{{ $row->operator }}</span>
                                        </div>
                                        @if(!empty($row->deskripsi))
                                            <div class="text-[10px] text-ink-400 truncate pt-0.5" title="{{ $row->deskripsi }}">
                                                {{ $row->deskripsi }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- In list mode on mobile: compact horizontal info --}}
                                <div class="flex items-center justify-between text-xs text-ink-300" x-show="view !== 'grid'">
                                    <span class="truncate max-w-[200px]">👤 {{ $row->pihak }}</span>
                                    <span class="text-ink-400 text-[10px]">Op: {{ $row->operator }}</span>
                                </div>
                            </div>

                            {{-- Action Bar --}}
                            <div class="flex items-center gap-1.5 pt-2 border-t border-white/5 flex-wrap">
                                @if($row->jenis === 'pos' || $row->jenis === 'online')
                                    <button
                                        wire:click="lihatDetailTransaksi({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail
                                    </button>
                                    <button
                                        wire:click="bukaRiwayat('transaksi', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-bold transition cursor-pointer min-h-[44px] flex items-center justify-center active:scale-95"
                                        title="Audit / Log Aktivitas Transaksi"
                                    >
                                        Log
                                    </button>
                                    <button
                                        wire:click="cetakUlangStruk('{{ $row->jenis }}', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Cetak Ulang Struk"
                                    >
                                        🖨️
                                    </button>
                                @elseif($row->jenis === 'servis')
                                    <button
                                        wire:click="lihatDetailServis({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-teal-500/15 hover:bg-teal-500/25 border border-teal-500/25 text-teal-300 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail
                                    </button>
                                    <button
                                        wire:click="bukaRiwayat('servis', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-bold transition cursor-pointer min-h-[44px] flex items-center justify-center active:scale-95"
                                        title="Audit / Log Aktivitas Servis"
                                    >
                                        Log
                                    </button>
                                    <button
                                        wire:click="cetakUlangStruk('servis', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Cetak Ulang Nota Servis"
                                    >
                                        🖨️
                                    </button>
                                @elseif(str_starts_with($row->jenis, 'mutasi'))
                                    <button
                                        wire:click="lihatDetailMutasi({{ $row->target_id }})"
                                        class="w-full py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail Mutasi
                                    </button>
                                @elseif($row->jenis === 'retur')
                                    <button
                                        wire:click="lihatDetailRetur({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-up-accent/15 hover:bg-up-accent/25 border border-up-accent/25 text-up-accent font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail Retur
                                    </button>
                                    <button
                                        wire:click="bukaRiwayat('retur_penjualan', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-bold transition cursor-pointer min-h-[44px] flex items-center justify-center active:scale-95"
                                        title="Log Aktivitas"
                                    >
                                        Log
                                    </button>
                                    <button
                                        wire:click="cetakUlangStruk('retur', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Cetak Struk Retur"
                                    >
                                        🖨️
                                    </button>
                                @elseif($row->jenis === 'retur_pembelian')
                                    <button
                                        wire:click="lihatDetailReturPembelian({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-sky-500/15 hover:bg-sky-500/25 border border-sky-500/25 text-sky-300 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail Retur
                                    </button>
                                    <button
                                        wire:click="cetakUlangStruk('retur_pembelian', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Cetak Struk Retur"
                                    >
                                        🖨️
                                    </button>
                                @elseif($row->jenis === 'pembelian')
                                    <button
                                        wire:click="lihatDetailPembelian({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-sky-600/15 hover:bg-sky-600/25 border border-sky-600/25 text-sky-300 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail PO
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Tidak ada riwayat transaksi ditemukan untuk tanggal dan filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>

        @elseif($activeTab === 'pos')
            {{-- TAB 1: PENJUALAN KASIR POS --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[780px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No Transaksi</th>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Kasir</th>
                                <th class="py-3 px-4">Pelanggan</th>
                                <th class="py-3 px-4 text-right">Total Akhir</th>
                                <th class="py-3 px-4">Metode</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap {{ $row->status === 'void' ? 'bg-up-red/[0.035]' : '' }}">
                                    <td class="py-3.5 px-4 font-mono font-bold {{ $row->status === 'void' ? 'text-up-red line-through decoration-up-red/70' : 'text-white' }}">{{ $row->no_transaksi }}</td>
                                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="py-3.5 px-4 text-white font-medium">{{ $row->kasir?->name ?? '-' }}</td>
                                    <td class="py-3.5 px-4 text-ink-200">{{ $row->pelanggan?->nama ?? 'Pelanggan Umum' }}</td>
                                    <td class="py-3.5 px-4 text-right font-bold tabular-nums {{ $row->status === 'void' ? 'text-up-red line-through decoration-up-red/70' : 'text-white' }}">Rp {{ number_format($row->total_akhir, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $row->metode_bayar === 'tunai' ? 'bg-up-mint/10 text-up-mint' : ($row->metode_bayar === 'piutang' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-up-primary/10 text-up-primary') }}">
                                            {{ $row->metode_bayar }}
                                        </span>
                                        @if($row->piutang)
                                            <span class="block text-[9px] mt-0.5 font-mono {{ $row->piutang->status === 'lunas' ? 'text-up-mint' : 'text-amber-400' }}">
                                                ({{ strtoupper($row->piutang->status) }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $row->status === 'selesai' ? 'bg-up-mint/10 text-up-mint' : ($row->status === 'void' ? 'bg-up-red/15 text-up-red border border-up-red/30' : 'bg-up-red/10 text-up-red') }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                        @if($row->status === 'void')
                                            <div class="mt-1 max-w-[200px] whitespace-normal rounded-lg border border-up-red/30 bg-up-red/[0.08] p-1.5 text-[10px] leading-relaxed text-up-red">
                                                <div class="font-bold flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-up-red"></span>
                                                    <span>VOID: Reversal Stok & Jurnal</span>
                                                </div>
                                                <p class="mt-0.5 text-up-red/90 line-clamp-2" title="{{ $row->void_alasan ?: 'Transaksi dibatalkan dan stok serta jurnal dibalikkan.' }}">
                                                    {{ $row->void_alasan ?: 'Dibatalkan; stok dan jurnal dibalikkan.' }}
                                                </p>
                                            </div>
                                        @elseif($row->returnPenjualan && $row->returnPenjualan->isNotEmpty())
                                            @php
                                                $returTerbaru = $row->returnPenjualan->first();
                                            @endphp
                                            <div class="mt-1 inline-flex items-center gap-1 rounded-full border border-up-accent/30 bg-up-accent/15 px-2 py-0.5 text-[9px] font-bold text-up-accent">
                                                <span>↩ Retur ({{ strtoupper($returTerbaru->status ?? 'ADA') }})</span>
                                            </div>
                                        @elseif($row->status === 'selesai')
                                            <span class="mt-1 inline-flex rounded-full bg-up-accent/10 px-1.5 py-0.5 text-[9px] font-bold text-up-accent">Retur tersedia</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                wire:click="lihatDetailTransaksi({{ $row->id }})"
                                                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                            >
                                                Detail
                                            </button>
                                            <button wire:click="bukaRiwayat('transaksi', {{ $row->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-semibold transition cursor-pointer" title="Audit / log aktivitas transaksi">Log</button>
                                            @if($row->status === 'selesai')
                                                @canany(['pos.view', 'pos.view-own', 'pos.create'])
                                                <a
                                                    href="/app/pos/retur?no_faktur={{ urlencode($row->no_transaksi) }}"
                                                    class="px-2.5 py-1.5 rounded-lg bg-up-red/20 hover:bg-up-red/30 text-up-red text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                                                    title="Proses Retur / Tukar Barang Konsumen"
                                                >
                                                    <span>↩ Retur</span>
                                                </a>
                                                @endcanany
                                                @can('pos.void')
                                                <button
                                                    type="button"
                                                    wire:click="bukaVoidModal({{ $row->id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                                                    title="Batalkan & Void Transaksi"
                                                >
                                                    <span>Void</span>
                                                </button>
                                                @endcan
                                            @endif
                                            <button
                                                wire:click="cetakUlangStruk('pos', {{ $row->id }})"
                                                class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                title="Cetak Ulang Struk"
                                            >
                                                <span class="sr-only">Cetak</span> 🖨️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada transaksi kasir POS ditemukan untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-up-primary/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            {{-- Header Card / Row --}}
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-mono font-bold text-xs {{ $row->status === 'void' ? 'text-up-red line-through decoration-up-red/70' : 'text-white' }} truncate">
                                                {{ $row->no_transaksi }}
                                            </span>
                                            @if($row->status === 'void')
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-up-red/20 text-up-red border border-up-red/40">
                                                    VOID
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] text-ink-400 tabular-nums block mt-0.5">📅 {{ $row->created_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $row->status === 'selesai' ? 'bg-up-mint/15 text-up-mint border border-up-mint/25' : ($row->status === 'void' ? 'bg-up-red/20 text-up-red border border-up-red/30' : 'bg-up-red/10 text-up-red') }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase {{ $row->metode_bayar === 'tunai' ? 'bg-up-mint/10 text-up-mint' : ($row->metode_bayar === 'piutang' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-up-primary/10 text-up-primary') }}">
                                            {{ $row->metode_bayar }}
                                        </span>
                                        @if($row->piutang)
                                            <span class="block text-[9px] font-mono {{ $row->piutang->status === 'lunas' ? 'text-up-mint' : 'text-amber-400' }}">
                                                ({{ strtoupper($row->piutang->status) }})
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Main Details --}}
                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-2' : 'flex items-center justify-between gap-2'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <span class="text-white font-semibold text-xs block truncate max-w-[170px]">{{ $row->pelanggan?->nama ?? 'Pelanggan Umum' }}</span>
                                            <span class="text-[10px] text-ink-400 block">Kasir: {{ $row->kasir?->name ?? '-' }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 block font-bold" x-show="view === 'grid'">Total Transaksi</span>
                                            <span class="font-black tabular-nums text-white block {{ $row->status === 'void' ? 'text-up-red line-through decoration-up-red/70' : '' }}" :class="view === 'grid' ? 'text-base' : 'text-sm'">
                                                Rp {{ number_format($row->total_akhir, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                @if($row->status === 'void')
                                    <div class="rounded-xl border border-up-red/30 bg-up-red/[0.08] p-2.5 text-[11px] leading-relaxed text-up-red">
                                        <div class="font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-up-red"></span>
                                            <span>VOID (Reversal Selesai)</span>
                                        </div>
                                        <p class="mt-0.5 text-[10px] text-up-red/90">{{ $row->void_alasan ?: 'Transaksi dibatalkan; stok dan jurnal sudah dibalikkan.' }}</p>
                                    </div>
                                @elseif($row->returnPenjualan && $row->returnPenjualan->isNotEmpty())
                                    @php
                                        $returTerbaru = $row->returnPenjualan->first();
                                    @endphp
                                    <div class="flex items-center justify-between rounded-xl border border-up-accent/30 bg-up-accent/15 px-3 py-2 text-xs">
                                        <span class="inline-flex items-center gap-1.5 font-bold text-up-accent truncate">
                                            <span>↩ Retur:</span>
                                            <span class="font-mono text-white">{{ $returTerbaru->no_return }}</span>
                                        </span>
                                        <span class="rounded bg-up-accent/25 px-1.5 py-0.5 text-[10px] font-bold uppercase text-up-accent shrink-0">{{ $returTerbaru->status }}</span>
                                    </div>
                                @elseif($row->status === 'selesai')
                                    <div x-show="view === 'grid'" class="flex items-center gap-1 text-[10px] text-up-accent/80 font-semibold">
                                        <span>↩ Tersedia untuk retur / tukar barang</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Action Bar --}}
                            <div class="flex items-center gap-1.5 pt-2 border-t border-white/5 flex-wrap">
                                <button
                                    wire:click="lihatDetailTransaksi({{ $row->id }})"
                                    class="flex-1 min-w-[70px] py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                >
                                    Detail
                                </button>
                                <button
                                    wire:click="bukaRiwayat('transaksi', {{ $row->id }})"
                                    class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-bold transition cursor-pointer min-h-[44px] flex items-center justify-center active:scale-95"
                                    title="Audit / Log Aktivitas Transaksi"
                                >
                                    Log
                                </button>
                                @if($row->status === 'selesai')
                                    @canany(['pos.view', 'pos.view-own', 'pos.create'])
                                    <a
                                        href="/app/pos/retur?no_faktur={{ urlencode($row->no_transaksi) }}"
                                        class="px-3 py-2 rounded-xl bg-up-red/20 hover:bg-up-red/30 border border-up-red/30 text-up-red text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer min-h-[44px] active:scale-95"
                                        title="Proses Retur"
                                    >
                                        <span>↩ Retur</span>
                                    </a>
                                    @endcanany
                                    @can('pos.void')
                                    <button
                                        type="button"
                                        wire:click="bukaVoidModal({{ $row->id }})"
                                        class="px-2.5 py-2.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 border border-purple-500/30 text-purple-300 text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer min-h-[44px] active:scale-95"
                                        title="Void Transaksi"
                                    >
                                        <span>Void</span>
                                    </button>
                                    @endcan
                                @endif
                                <button
                                    wire:click="cetakUlangStruk('pos', {{ $row->id }})"
                                    class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    title="Cetak Ulang Struk"
                                >
                                    🖨️
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Tidak ada transaksi kasir POS ditemukan untuk filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>

        @elseif($activeTab === 'online')
            {{-- TAB: PESANAN ONLINE & OMNICHANNEL --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[850px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No Pesanan</th>
                                <th class="py-3 px-4">Platform</th>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Pelanggan</th>
                                <th class="py-3 px-4">Metode & Pengiriman</th>
                                <th class="py-3 px-4 text-right">Total Akhir</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                @php
                                    $platformLabel = $row->sumber === 'marketplace' ? 'Web Ute' : (str_starts_with((string) $row->sumber, 'channel:') ? ucfirst(str_replace('channel:', '', $row->sumber)) : 'Online');
                                    $isPickup = str_contains(strtolower($row->catatan ?? ''), 'ambil_ke_toko');
                                @endphp
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap">
                                    <td class="py-3.5 px-4 font-mono font-bold text-white">
                                        {{ $row->no_transaksi }}
                                        @if($row->channelOrder)
                                            <span class="block text-[10px] text-indigo-400 font-sans font-normal">
                                                ID: {{ $row->channelOrder->channel_order_id }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $row->sumber === 'marketplace' ? 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/25' : 'bg-up-accent/15 text-up-accent border border-up-accent/25' }}">
                                            {{ $platformLabel }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="py-3.5 px-4 text-white font-medium">
                                        {{ $row->pelanggan?->nama ?? 'Pelanggan Online' }}
                                        @if($row->pelanggan?->telepon)
                                            <span class="block text-[10px] text-ink-400 font-mono">{{ $row->pelanggan->telepon }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-300">
                                        <div class="font-semibold text-white uppercase text-[11px]">{{ $row->metode_bayar ?: 'Duitku' }}</div>
                                        <div class="text-[10px] text-ink-400 mt-0.5">
                                            @if($isPickup)
                                                <span class="text-amber-400 font-medium">🏬 Ambil di Toko</span>
                                            @elseif($row->pengiriman)
                                                <span>🚚 {{ $row->pengiriman->kurir }} {{ ($row->pengiriman->tracking_id ?? $row->pengiriman->no_resi) ? '('.($row->pengiriman->tracking_id ?? $row->pengiriman->no_resi).')' : '' }}</span>
                                            @else
                                                <span>🚚 Pengiriman Kurir</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-white tabular-nums">Rp {{ number_format($row->total_akhir, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array($row->status, ['lunas', 'selesai']) ? 'bg-up-mint/15 text-up-mint' : (in_array($row->status, ['diproses', 'dikirim', 'siap_diambil']) ? 'bg-sky-500/15 text-sky-400' : ($row->status === 'kadaluarsa' ? 'bg-rose-500/15 text-rose-400 border border-rose-500/25' : 'bg-up-amber/15 text-up-amber')) }}">
                                            {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($isPickup && in_array($row->status, ['lunas', 'diproses', 'siap_diambil'], true))
                                                <button
                                                    wire:click="serahkanPesananPickup({{ $row->id }})"
                                                    wire:confirm="Serahkan pesanan pickup ini ke konsumen di toko?"
                                                    class="px-2 py-1.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 font-bold text-[11px] border border-emerald-500/30 transition cursor-pointer"
                                                    title="Serahkan Barang Pickup di Toko"
                                                >
                                                    Serahkan
                                                </button>
                                            @endif
                                            @if(!in_array($row->status, ['selesai', 'void', 'batal', 'dibatalkan', 'kadaluarsa'], true))
                                                <button
                                                    wire:click="sinkronkanStatusOnline({{ $row->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="p-1.5 rounded-lg bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-300 hover:text-white border border-indigo-500/30 transition cursor-pointer"
                                                    title="Sinkronkan status Duitku / Channel sekarang"
                                                >
                                                    🔄
                                                </button>
                                            @endif
                                            <button
                                                wire:click="lihatDetailTransaksi({{ $row->id }})"
                                                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                title="Detail Pesanan Online"
                                            >
                                                Detail
                                            </button>
                                            <button wire:click="bukaRiwayat('transaksi', {{ $row->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-semibold transition cursor-pointer" title="Audit / log aktivitas pesanan online">Log</button>
                                            <button
                                                wire:click="cetakUlangStruk('online', {{ $row->id }})"
                                                class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                title="Cetak Struk / Label"
                                            >
                                                🖨️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada transaksi pesanan online ditemukan untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        @php
                            $platformLabel = $row->sumber === 'marketplace' ? 'Web Ute' : (str_starts_with((string) $row->sumber, 'channel:') ? ucfirst(str_replace('channel:', '', $row->sumber)) : 'Online');
                            $isPickup = str_contains(strtolower($row->catatan ?? ''), 'ambil_ke_toko');
                        @endphp
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-up-primary/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            {{-- Header Card / Row --}}
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-mono font-bold text-white text-xs block truncate">{{ $row->no_transaksi }}</span>
                                        @if($row->channelOrder)
                                            <span class="text-[10px] text-indigo-400 font-mono block truncate">ID: {{ $row->channelOrder->channel_order_id }}</span>
                                        @endif
                                        <span class="text-[10px] text-ink-400 tabular-nums block mt-0.5">📅 {{ $row->created_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $row->sumber === 'marketplace' ? 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/25' : 'bg-up-accent/15 text-up-accent border border-up-accent/25' }}">
                                            {{ $platformLabel }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array($row->status, ['lunas', 'selesai']) ? 'bg-up-mint/15 text-up-mint border border-up-mint/25' : (in_array($row->status, ['diproses', 'dikirim', 'siap_diambil']) ? 'bg-sky-500/15 text-sky-400 border border-sky-500/25' : ($row->status === 'kadaluarsa' ? 'bg-rose-500/15 text-rose-400 border border-rose-500/25' : 'bg-up-amber/15 text-up-amber border border-up-amber/25')) }}">
                                            {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Main Details --}}
                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-2' : 'flex items-center justify-between gap-2'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <span class="text-white font-semibold text-xs block truncate max-w-[170px]">{{ $row->pelanggan?->nama ?? 'Pelanggan Online' }}</span>
                                            @if($row->pelanggan?->telepon)
                                                <span class="text-[10px] text-ink-400 font-mono block">{{ $row->pelanggan->telepon }}</span>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 block font-bold" x-show="view === 'grid'">Total Tagihan</span>
                                            <span class="font-black text-white tabular-nums block" :class="view === 'grid' ? 'text-base' : 'text-sm'">Rp {{ number_format($row->total_akhir, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-ink-300 uppercase font-semibold">{{ $row->metode_bayar ?: 'Duitku' }}</span>
                                        </div>
                                    </div>

                                    <div class="pt-1.5 border-t border-white/5 text-xs" x-show="view === 'grid'">
                                        <span class="text-ink-400 text-[10px] block mb-0.5">Pengiriman:</span>
                                        @if($isPickup)
                                            <span class="text-amber-400 font-medium">🏬 Ambil di Toko</span>
                                        @elseif($row->pengiriman)
                                            <span class="text-white truncate block">🚚 {{ $row->pengiriman->kurir }} {{ ($row->pengiriman->tracking_id ?? $row->pengiriman->no_resi) ? '('.($row->pengiriman->tracking_id ?? $row->pengiriman->no_resi).')' : '' }}</span>
                                        @else
                                            <span class="text-ink-300">🚚 Pengiriman Kurir</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- In list mode: compact pengiriman info --}}
                                <div class="text-[11px] text-ink-300 flex items-center justify-between" x-show="view !== 'grid'">
                                    @if($isPickup)
                                        <span class="text-amber-400 font-medium">🏬 Ambil di Toko</span>
                                    @elseif($row->pengiriman)
                                        <span class="text-white truncate">🚚 {{ $row->pengiriman->kurir }}</span>
                                    @else
                                        <span class="text-ink-300">🚚 Kurir</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Action Bar --}}
                            <div class="flex items-center gap-1.5 pt-2 border-t border-white/5 flex-wrap">
                                @if($isPickup && in_array($row->status, ['lunas', 'diproses', 'siap_diambil'], true))
                                    <button
                                        wire:click="serahkanPesananPickup({{ $row->id }})"
                                        wire:confirm="Serahkan pesanan pickup ini ke konsumen di toko?"
                                        class="px-3 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Serahkan Barang Pickup di Toko"
                                    >
                                        Serahkan
                                    </button>
                                @endif
                                @if(!in_array($row->status, ['selesai', 'void', 'batal', 'dibatalkan', 'kadaluarsa'], true))
                                    <button
                                        wire:click="sinkronkanStatusOnline({{ $row->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-2.5 py-2 rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-300 border border-indigo-500/30 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Sinkronkan status Duitku / Channel sekarang"
                                    >
                                        🔄 Sync
                                    </button>
                                @endif
                                <button
                                    wire:click="lihatDetailTransaksi({{ $row->id }})"
                                    class="flex-1 min-w-[70px] py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                >
                                    Detail
                                </button>
                                <button wire:click="bukaRiwayat('transaksi', {{ $row->id }})" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-bold transition cursor-pointer min-h-[44px] active:scale-95" title="Audit / log aktivitas">Log</button>
                                <button
                                    wire:click="cetakUlangStruk('online', {{ $row->id }})"
                                    class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    title="Cetak Struk / Label"
                                >
                                    🖨️
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Tidak ada transaksi pesanan online ditemukan untuk filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>

        @elseif($activeTab === 'servis')
            {{-- TAB 2: REKAP SERVIS SELESAI & LUNAS SECARA PENUH --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[920px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No Tiket & Pelunasan</th>
                                <th class="py-3 px-4">Waktu Selesai & Bayar</th>
                                <th class="py-3 px-4">Pelanggan & Perangkat</th>
                                <th class="py-3 px-4">Teknisi</th>
                                <th class="py-3 px-4 text-right">Biaya Jasa</th>
                                <th class="py-3 px-4 text-right">Sparepart</th>
                                <th class="py-3 px-4 text-right">Total Tagihan</th>
                                <th class="py-3 px-4 text-center">Pembayaran</th>
                                <th class="py-3 px-4 text-center">Status Unit</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                @php
                                    $rincian = $row->getRincianBiayaLengkap();
                                    $isLunas = $row->status_pembayaran === 'lunas';
                                @endphp
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap">
                                    <td class="py-3.5 px-4 font-mono">
                                        <div class="font-bold text-white">{{ $row->no_tiket }}</div>
                                        @if($row->transaksi)
                                            <div class="text-[10px] text-indigo-400 font-sans mt-0.5">
                                                Ref: {{ $row->transaksi->no_transaksi }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 tabular-nums">
                                        <div class="text-white">{{ $row->tanggal_selesai?->format('d/m/Y H:i') ?? '-' }}</div>
                                        <div class="text-[10px] text-ink-400">
                                            Lunas: {{ $row->tanggal_bayar?->format('d/m/Y H:i') ?? 'Belum' }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-white">{{ $row->pelanggan?->nama ?? $row->nama_pelanggan ?? 'Pelanggan Umum' }}</div>
                                        <div class="text-[11px] text-teal-400 font-mono mt-0.5">
                                            {{ $row->jenis_hp }}{{ $row->seri_hp ? ' ('.$row->seri_hp.')' : '' }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-200">
                                        {{ $row->teknisi?->name ?? 'Belum Ditugaskan' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-ink-300 tabular-nums">
                                        Rp {{ number_format($rincian['total_jasa'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-ink-300 tabular-nums">
                                        Rp {{ number_format($rincian['total_part'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-white tabular-nums text-sm">
                                        Rp {{ number_format($rincian['total'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="inline-flex flex-col items-center gap-1">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isLunas ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-up-amber/15 text-up-amber border border-up-amber/30' }}">
                                                {{ $isLunas ? 'LUNAS' : 'BELUM LUNAS' }}
                                            </span>
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 font-semibold">
                                                {{ $row->metode_pembayaran ?? $row->transaksi?->metode_bayar ?? 'tunai' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $row->status === 'diambil' ? 'bg-blue-500/15 text-blue-300 border border-blue-500/20' : 'bg-white/10 text-white' }}">
                                            {{ $row->status === 'diambil' ? 'Diambil' : 'Selesai (Siap)' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                wire:click="lihatDetailServis({{ $row->id }})"
                                                class="px-2.5 py-1.5 rounded-lg bg-teal-500/15 hover:bg-teal-500/25 text-teal-300 text-[11px] font-semibold transition cursor-pointer"
                                                title="Lihat Rincian Biaya Servis Lengkap"
                                            >
                                                Detail
                                            </button>
                                            <button wire:click="bukaRiwayat('servis', {{ $row->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-semibold transition cursor-pointer" title="Audit / log aktivitas tiket servis">Log</button>
                                            <button
                                                wire:click="cetakUlangStruk('servis', {{ $row->id }})"
                                                class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                title="Cetak Ulang Nota Servis"
                                            >
                                                🖨️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada transaksi servis selesai ditemukan untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        @php
                            $rincian = $row->getRincianBiayaLengkap();
                            $isLunas = $row->status_pembayaran === 'lunas';
                        @endphp
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-teal-500/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            {{-- Header Card / Row --}}
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-mono font-bold text-white text-xs block truncate">{{ $row->no_tiket }}</span>
                                        @if($row->transaksi)
                                            <span class="text-[10px] text-indigo-400 font-sans block truncate">Ref: {{ $row->transaksi->no_transaksi }}</span>
                                        @endif
                                        <span class="text-[10px] text-ink-400 tabular-nums block mt-0.5">Selesai: {{ $row->tanggal_selesai?->format('d/m/Y H:i') ?? '-' }}</span>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isLunas ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-up-amber/15 text-up-amber border border-up-amber/30' }}">
                                            {{ $isLunas ? 'LUNAS' : 'BELUM LUNAS' }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $row->status === 'diambil' ? 'bg-blue-500/15 text-blue-300 border border-blue-500/20' : 'bg-white/10 text-white' }}">
                                            {{ $row->status === 'diambil' ? 'Diambil' : 'Siap Diambil' }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Main Details --}}
                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-2' : 'flex items-center justify-between gap-2'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <span class="text-white font-semibold text-xs block truncate max-w-[170px]">{{ $row->pelanggan?->nama ?? $row->nama_pelanggan ?? 'Pelanggan Umum' }}</span>
                                            <span class="text-teal-400 font-mono text-[11px] block truncate max-w-[170px]">{{ $row->jenis_hp }}{{ $row->seri_hp ? ' ('.$row->seri_hp.')' : '' }}</span>
                                            <span class="text-[10px] text-ink-400 block" x-show="view === 'grid'">Teknisi: {{ $row->teknisi?->name ?? 'Belum Ditugaskan' }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 block font-bold" x-show="view === 'grid'">Total Tagihan</span>
                                            <span class="font-black text-white tabular-nums block" :class="view === 'grid' ? 'text-base' : 'text-sm'">Rp {{ number_format($rincian['total'], 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-ink-400 block tabular-nums" x-show="view === 'grid'">Jasa: Rp {{ number_format($rincian['total_jasa'], 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-ink-400 block tabular-nums" x-show="view === 'grid'">Part: Rp {{ number_format($rincian['total_part'], 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Action Bar --}}
                            <div class="flex items-center gap-1.5 pt-2 border-t border-white/5 flex-wrap">
                                <button
                                    wire:click="lihatDetailServis({{ $row->id }})"
                                    class="flex-1 min-w-[70px] py-2 rounded-xl bg-teal-500/15 hover:bg-teal-500/25 border border-teal-500/25 text-teal-300 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                >
                                    Detail Rincian
                                </button>
                                <button wire:click="bukaRiwayat('servis', {{ $row->id }})" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-bold transition cursor-pointer min-h-[44px] active:scale-95" title="Audit / log aktivitas">Log</button>
                                <button
                                    wire:click="cetakUlangStruk('servis', {{ $row->id }})"
                                    class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    title="Cetak Nota Servis"
                                >
                                    🖨️
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Tidak ada transaksi servis selesai ditemukan untuk filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>

        @elseif($activeTab === 'pembelian')
            {{-- TAB: PEMBELIAN (PURCHASE ORDER) --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[800px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No PO</th>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4">Gudang Tujuan</th>
                                <th class="py-3 px-4 text-right">Total</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap">
                                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $row->no_po }}</td>
                                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="py-3.5 px-4 text-white font-medium">{{ $row->supplier->nama ?? 'Supplier' }}</td>
                                    <td class="py-3.5 px-4 text-ink-200">{{ $row->gudangTujuan->nama ?? 'Gudang' }}</td>
                                    <td class="py-3.5 px-4 text-right font-bold tabular-nums text-white">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if($row->status === 'diterima')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/25">
                                                Diterima
                                            </span>
                                        @elseif(in_array($row->status, ['draft', 'diajukan', 'disetujui']))
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/25">
                                                {{ ucfirst($row->status) }}
                                            </span>
                                        @elseif($row->status === 'dibatalkan')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/15 text-up-red border border-up-red/25">
                                                Dibatalkan
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-ink-300">
                                                {{ ucfirst($row->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                wire:click="lihatDetailPembelian({{ $row->id }})"
                                                class="px-2.5 py-1.5 rounded-lg bg-sky-600/15 hover:bg-sky-600/25 text-sky-300 text-[11px] font-semibold transition cursor-pointer"
                                                title="Lihat Detail PO"
                                            >
                                                Detail
                                            </button>
                                            @if($row->status === 'diterima')
                                                <button
                                                    wire:click="bukaModalReturPembelian({{ $row->id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-up-accent/15 hover:bg-up-accent/25 text-up-accent text-[11px] font-semibold transition cursor-pointer"
                                                    title="Buat Retur Pembelian ke Supplier"
                                                >
                                                    ↩ Retur
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada data pembelian PO ditemukan untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-sky-500/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-mono font-bold text-xs text-white truncate">
                                                {{ $row->no_po }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-600/15 text-sky-300 border border-sky-600/20">
                                                📦 PO
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-ink-400 tabular-nums mt-0.5">
                                            📅 {{ $row->created_at?->format('d/m/Y H:i') }}
                                        </div>
                                    </div>
                                    <div>
                                        @if($row->status === 'diterima')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/25">
                                                Diterima
                                            </span>
                                        @elseif(in_array($row->status, ['draft', 'diajukan', 'disetujui']))
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/25">
                                                {{ ucfirst($row->status) }}
                                            </span>
                                        @elseif($row->status === 'dibatalkan')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/15 text-up-red border border-up-red/25">
                                                Dibatalkan
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-ink-300">
                                                {{ ucfirst($row->status) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs' : 'text-xs space-y-1'">
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span class="text-ink-400">Supplier:</span>
                                        <span class="text-white font-medium truncate max-w-[170px]">{{ $row->supplier->nama ?? 'Supplier' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span class="text-ink-400">Gudang:</span>
                                        <span class="text-ink-300 font-medium truncate max-w-[150px]">{{ $row->gudangTujuan->nama ?? 'Gudang' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px] pt-1 border-t border-white/5 font-bold">
                                        <span class="text-ink-400">Total PO:</span>
                                        <span class="text-white tabular-nums">Rp {{ number_format($row->total, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 pt-2 border-t border-white/5 flex-wrap">
                                <button
                                    wire:click="lihatDetailPembelian({{ $row->id }})"
                                    class="flex-1 min-w-[70px] py-2 rounded-xl bg-sky-600/15 hover:bg-sky-600/25 border border-sky-600/25 text-sky-300 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                >
                                    Detail PO
                                </button>
                                @if($row->status === 'diterima')
                                    <button
                                        wire:click="bukaModalReturPembelian({{ $row->id }})"
                                        class="px-3.5 py-2 rounded-xl bg-up-accent/15 hover:bg-up-accent/25 border border-up-accent/25 text-up-accent font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Buat Retur Pembelian"
                                    >
                                        ↩ Retur
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Tidak ada data pembelian PO ditemukan untuk filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>

        @elseif($activeTab === 'retur')
            {{-- TAB: HISTORI RETUR TRANSAKSI --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[850px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No Return</th>
                                <th class="py-3 px-4">Jenis Retur</th>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Pihak</th>
                                <th class="py-3 px-4">Ref Transaksi</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap">
                                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $row->no_return }}</td>
                                    <td class="py-3.5 px-4">
                                        @if($row->jenis_retur === 'penjualan')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-accent/15 text-up-accent border border-up-accent/25">
                                                ↩ Retur Konsumen
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/15 text-sky-300 border border-sky-500/20">
                                                ↩ Retur Supplier
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">
                                        {{ $row->tanggal ? \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-white font-medium">{{ $row->pihak }}</td>
                                    <td class="py-3.5 px-4 font-mono text-ink-200">{{ $row->no_referensi }}</td>
                                    <td class="py-3.5 px-4 text-right font-bold tabular-nums text-up-accent">
                                        Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array(strtolower($row->status), ['selesai', 'disetujui']) ? 'bg-up-mint/15 text-up-mint border border-up-mint/25' : 'bg-white/10 text-ink-300 border border-white/10' }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($row->jenis_retur === 'penjualan')
                                                <button
                                                    wire:click="lihatDetailRetur({{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-up-accent/15 hover:bg-up-accent/25 text-up-accent text-[11px] font-semibold transition cursor-pointer"
                                                    title="Lihat Detail Retur"
                                                >
                                                    Detail
                                                </button>
                                                <button
                                                    wire:click="cetakUlangStruk('retur', {{ $row->target_id }})"
                                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                    title="Cetak Struk Retur"
                                                >
                                                    🖨️
                                                </button>
                                            @else
                                                <button
                                                    wire:click="lihatDetailReturPembelian({{ $row->target_id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 text-sky-300 text-[11px] font-semibold transition cursor-pointer"
                                                    title="Lihat Detail Retur Pembelian"
                                                >
                                                    Detail
                                                </button>
                                                <button
                                                    wire:click="cetakUlangStruk('retur_pembelian', {{ $row->target_id }})"
                                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                                    title="Cetak Struk Retur Pembelian"
                                                >
                                                    🖨️
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada riwayat retur ditemukan untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-amber-500/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-mono font-bold text-xs text-white truncate">
                                                {{ $row->no_return }}
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-ink-400 tabular-nums mt-0.5">
                                            📅 {{ $row->tanggal ? \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m/Y') : '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array(strtolower($row->status), ['selesai', 'disetujui']) ? 'bg-up-mint/15 text-up-mint border border-up-mint/25' : 'bg-white/10 text-ink-300 border border-white/10' }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                    </div>
                                </div>

                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-2' : 'flex items-center justify-between gap-2'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            @if($row->jenis_retur === 'penjualan')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-accent/15 text-up-accent border border-up-accent/25">
                                                    ↩ Retur Konsumen
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-500/15 text-sky-300 border border-sky-500/20">
                                                    ↩ Retur Supplier
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 block font-bold" x-show="view === 'grid'">Nominal Retur</span>
                                            <span class="text-up-accent font-black tabular-nums" :class="view === 'grid' ? 'text-base' : 'text-sm'">
                                                Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="text-xs space-y-1 pt-1.5 border-t border-white/5" x-show="view === 'grid'">
                                        <div class="flex justify-between items-center text-[11px]">
                                            <span class="text-ink-400">Pihak:</span>
                                            <span class="text-white font-medium truncate max-w-[170px]">{{ $row->pihak }}</span>
                                        </div>
                                        <div class="flex justify-between items-center text-[11px]">
                                            <span class="text-ink-400">Ref:</span>
                                            <span class="text-ink-300 font-mono text-[11px] truncate max-w-[150px]">{{ $row->no_referensi }}</span>
                                        </div>
                                        <div class="flex justify-between items-center text-[11px]">
                                            <span class="text-ink-400">Operator:</span>
                                            <span class="text-ink-300 text-[11px] truncate max-w-[150px]">{{ $row->operator }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 pt-2 border-t border-white/5 flex-wrap">
                                @if($row->jenis_retur === 'penjualan')
                                    <button
                                        wire:click="lihatDetailRetur({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-up-accent/15 hover:bg-up-accent/25 border border-up-accent/25 text-up-accent font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail Retur
                                    </button>
                                    <button
                                        wire:click="cetakUlangStruk('retur', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Cetak Struk Retur"
                                    >
                                        🖨️
                                    </button>
                                @else
                                    <button
                                        wire:click="lihatDetailReturPembelian({{ $row->target_id }})"
                                        class="flex-1 min-w-[70px] py-2 rounded-xl bg-sky-500/15 hover:bg-sky-500/25 border border-sky-500/25 text-sky-300 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                    >
                                        Detail Retur
                                    </button>
                                    <button
                                        wire:click="cetakUlangStruk('retur_pembelian', {{ $row->target_id }})"
                                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                        title="Cetak Struk Retur Pembelian"
                                    >
                                        🖨️
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Tidak ada riwayat retur ditemukan untuk filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>

        @elseif($activeTab === 'mutasi_kas')
            {{-- TAB 3: MUTASI KAS LACI --}}
            <x-prism.dual-scroll>
                {{-- Desktop Table --}}
                <div x-show="view === 'list' && isDesktop" x-cloak class="hidden md:block">
                    <table class="w-full text-left text-xs min-w-[740px]">
                        <thead>
                            <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02] whitespace-nowrap">
                                <th class="py-3 px-4">No Jurnal</th>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Kasir Operator</th>
                                <th class="py-3 px-4">Jenis Mutasi</th>
                                <th class="py-3 px-4">Akun Lawan</th>
                                <th class="py-3 px-4">Keterangan</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataList as $row)
                                <tr class="hover:bg-white/[0.02] transition-colors whitespace-nowrap">
                                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $row->no_jurnal ?? ('MUT-#'.$row->id) }}</td>
                                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="py-3.5 px-4 text-white font-medium">{{ $row->user_name }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $row->jenis === 'masuk' ? 'bg-up-mint/10 text-up-mint' : 'bg-up-red/10 text-up-red' }}">
                                            {{ $row->jenis === 'masuk' ? 'Kas Masuk' : 'Kas Keluar' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-ink-200">
                                        {{ $row->akun_lawan_kode }}
                                        @if($row->akun_nama)
                                            <span class="text-ink-400 font-sans block text-[10px]">{{ $row->akun_nama }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-300 truncate max-w-[250px]" title="{{ $row->keterangan }}">{{ $row->keterangan }}</td>
                                    <td class="py-3.5 px-4 text-right font-bold tabular-nums {{ $row->jenis === 'masuk' ? 'text-up-mint' : 'text-up-red' }}">
                                        {{ $row->jenis === 'masuk' ? '+' : '-' }} Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <button
                                            wire:click="lihatDetailMutasi({{ $row->id }})"
                                            class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                        >
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-ink-400 text-xs">
                                        Belum ada mutasi kas laci tercatat untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards / List Mode --}}
                <div x-show="view === 'grid' || !isDesktop" x-cloak :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 p-3.5 divide-y-0' : 'block divide-y divide-white/5'" class="md:divide-y-0">
                    @forelse($dataList as $row)
                        <div
                            :class="view === 'grid'
                                ? 'rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.05] to-white/[0.015] backdrop-blur-md hover:border-white/20 hover:shadow-xl hover:shadow-up-primary/5 p-4 space-y-3.5 transition-all duration-200 flex flex-col justify-between group'
                                : 'p-3.5 sm:p-4 hover:bg-white/[0.02] transition-colors space-y-2.5'"
                        >
                            {{-- Header Card / Row --}}
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-mono font-bold text-white text-xs block truncate">{{ $row->no_jurnal ?? ('MUT-#'.$row->id) }}</span>
                                        <span class="text-[10px] text-ink-400 tabular-nums block mt-0.5">📅 {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0 {{ $row->jenis === 'masuk' ? 'bg-up-mint/15 text-up-mint border border-up-mint/25' : 'bg-up-red/15 text-up-red border border-up-red/25' }}">
                                        {{ $row->jenis === 'masuk' ? 'Kas Masuk' : 'Kas Keluar' }}
                                    </span>
                                </div>

                                {{-- Main Details --}}
                                <div :class="view === 'grid' ? 'p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-2' : 'flex items-center justify-between gap-2'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <span class="text-white font-semibold text-xs block truncate max-w-[170px]">Kasir: {{ $row->user_name }}</span>
                                            <span class="text-[10px] font-mono text-ink-300 block truncate max-w-[170px]">{{ $row->akun_lawan_kode }}{{ $row->akun_nama ? ' ('.$row->akun_nama.')' : '' }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] uppercase tracking-wider text-ink-400 block font-bold" x-show="view === 'grid'">Nominal</span>
                                            <span class="font-black tabular-nums block {{ $row->jenis === 'masuk' ? 'text-up-mint' : 'text-up-red' }}" :class="view === 'grid' ? 'text-base' : 'text-sm'">
                                                {{ $row->jenis === 'masuk' ? '+' : '-' }} Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>

                                    @if($row->keterangan)
                                        <div class="pt-1.5 border-t border-white/5 text-xs text-ink-300" x-show="view === 'grid'">
                                            <span class="text-ink-400 text-[10px] block mb-0.5">Keterangan:</span>
                                            <span class="truncate block" title="{{ $row->keterangan }}">{{ $row->keterangan }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Action Bar --}}
                            <div class="pt-2 border-t border-white/5">
                                <button
                                    wire:click="lihatDetailMutasi({{ $row->id }})"
                                    class="w-full py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                                >
                                    Detail Mutasi
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-ink-400 text-xs col-span-full">
                            Belum ada mutasi kas laci tercatat untuk filter ini.
                        </div>
                    @endforelse
                </div>
            </x-prism.dual-scroll>
        @endif

        {{-- Pagination --}}
        @if($dataList)
            <div class="p-4 border-t border-white/10">
                {{ $dataList->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal Terpadu (POS, Servis Selesai, Mutasi Kas) --}}
    @if($showDetailModal && $selectedDetail)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[85vh] flex flex-col shadow-2xl border border-white/10">
                {{-- Header Modal Sesuai Tipe --}}
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10 gap-2">
                    <div class="min-w-0">
                        @if($selectedDetail['tipe'] === 'pos' || $selectedDetail['tipe'] === 'online')
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($selectedDetail['tipe'] === 'online')
                                    <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-bold text-[10px] uppercase">
                                        Pesanan Online ({{ $selectedDetail['sumber'] === 'marketplace' ? 'Web Ute' : $selectedDetail['sumber'] }})
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-up-primary/20 text-up-primary font-bold text-[10px] uppercase">Kasir POS</span>
                                @endif
                                <h3 class="text-base font-bold text-white truncate">Detail Transaksi {{ $selectedDetail['no_transaksi'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · {{ $selectedDetail['tipe'] === 'online' ? 'Operator' : 'Kasir' }}: {{ $selectedDetail['kasir'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'servis')
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-teal-500/20 text-teal-400 font-bold text-[10px] uppercase">Rekap Servis</span>
                                <h3 class="text-base font-bold text-white truncate">Rincian Transaksi Servis {{ $selectedDetail['no_tiket'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">Selesai: {{ $selectedDetail['tanggal_selesai'] }} · Teknisi: {{ $selectedDetail['teknisi'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'mutasi')
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded {{ $selectedDetail['jenis'] === 'masuk' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-red/20 text-up-red' }} font-bold text-[10px] uppercase">
                                    Mutasi Kas {{ ucfirst($selectedDetail['jenis']) }}
                                </span>
                                <h3 class="text-base font-bold text-white truncate">{{ $selectedDetail['no_jurnal'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · Operator: {{ $selectedDetail['operator'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'retur')
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-up-accent/20 text-up-accent font-bold text-[10px] uppercase">
                                    ↩ Retur Penjualan
                                </span>
                                <h3 class="text-base font-bold text-white truncate">Detail Retur {{ $selectedDetail['no_return'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · Ref Trx: {{ $selectedDetail['no_transaksi'] }} · Operator: {{ $selectedDetail['operator'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'pembelian')
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-sky-600/20 text-sky-400 font-bold text-[10px] uppercase">Pembelian (PO)</span>
                                <h3 class="text-base font-bold text-white truncate">Detail PO {{ $selectedDetail['no_po'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · Supplier: {{ $selectedDetail['supplier'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'retur_pembelian')
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 font-bold text-[10px] uppercase">↩ Retur Pembelian</span>
                                <h3 class="text-base font-bold text-white truncate">Detail Retur {{ $selectedDetail['no_return'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · PO: {{ $selectedDetail['no_po'] }} · Operator: {{ $selectedDetail['operator'] }}</p>
                        @endif
                    </div>
                    <button wire:click="tutupDetailModal" class="p-2.5 min-w-[44px] min-h-[44px] flex items-center justify-center text-ink-400 hover:text-white cursor-pointer rounded-xl hover:bg-white/5 shrink-0 transition" aria-label="Tutup modal">✕</button>
                </div>

                {{-- Body Modal --}}
                <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                    @if($selectedDetail['tipe'] === 'pos' || $selectedDetail['tipe'] === 'online')
                        {{-- POS & ONLINE DETAIL --}}
                        @if(!empty($selectedDetail['void']))
                            <div class="p-3.5 rounded-xl border border-up-red/30 bg-up-red/[0.08] text-xs space-y-1">
                                <div class="font-bold flex items-center gap-2 text-up-red text-sm">
                                    <span class="w-2 h-2 rounded-full bg-up-red"></span>
                                    <span>TRANSAKSI TELAH DI-VOID</span>
                                </div>
                                <p class="text-ink-200 text-[11px] leading-relaxed">
                                    Dibatalkan oleh <strong class="text-white">{{ $selectedDetail['void']['oleh'] }}</strong> pada <span class="tabular-nums font-mono text-white">{{ $selectedDetail['void']['pada'] }}</span>.
                                    Seluruh saldo kas, jurnal pembalik, dan pemulihan kuantitas stok produk telah diproses otomatis.
                                </p>
                                <div class="p-2 rounded-lg bg-black/20 text-up-red/90 font-mono text-[11px] border border-up-red/20 mt-1">
                                    Alasan: {{ $selectedDetail['void']['alasan'] ?: 'Tidak ada alasan khusus dicatat' }}
                                </div>
                            </div>
                        @endif

                        @if(!empty($selectedDetail['returns']))
                            <div class="p-3.5 rounded-xl border border-up-accent/30 bg-up-accent/[0.06] text-xs space-y-2">
                                <div class="font-bold flex items-center justify-between text-up-accent">
                                    <span class="flex items-center gap-1.5">
                                        <span>↩</span>
                                        <span>Retur Terkait Transaksi Ini ({{ count($selectedDetail['returns']) }})</span>
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    @foreach($selectedDetail['returns'] as $ret)
                                        <div class="p-2.5 rounded-lg bg-white/[0.02] border border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div>
                                                <div class="font-mono font-bold text-white text-xs">{{ $ret['no_return'] }}</div>
                                                <div class="text-[10px] text-ink-400 mt-0.5">
                                                    Tanggal: {{ $ret['tanggal'] }} · Tipe: <span class="uppercase font-semibold text-ink-300">{{ str_replace('_', ' ', $ret['tipe_penyelesaian']) }}</span> · Alasan: {{ $ret['alasan'] }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                                                <span class="font-bold tabular-nums text-up-accent text-xs">Rp {{ number_format($ret['jumlah'], 0, ',', '.') }}</span>
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $ret['status'] === 'selesai' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-amber/20 text-up-amber' }}">{{ $ret['status'] }}</span>
                                                <button
                                                    wire:click="lihatDetailRetur({{ $ret['id'] }})"
                                                    class="px-2 py-1 rounded bg-up-accent/20 hover:bg-up-accent/30 text-up-accent font-bold text-[10px] cursor-pointer transition active:scale-95"
                                                >
                                                    Buka Retur
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px]">Pelanggan</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['pelanggan'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Metode Pembayaran</span>
                                <span class="font-semibold text-white uppercase">{{ $selectedDetail['metode_bayar'] ?: 'Online' }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Status Transaksi</span>
                                <span class="font-semibold text-up-mint uppercase">{{ ucfirst(str_replace('_', ' ', $selectedDetail['status'])) }}</span>
                            </div>
                        </div>

                        @if(!empty($selectedDetail['piutang']))
                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs space-y-1">
                                <div class="font-bold text-amber-300 flex items-center justify-between">
                                    <span>📋 Subledger Piutang / Kasbon: {{ $selectedDetail['piutang']['no_piutang'] }}</span>
                                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded {{ $selectedDetail['piutang']['status'] === 'lunas' ? 'bg-up-mint/20 text-up-mint' : 'bg-amber-500/20 text-amber-300' }}">
                                        {{ strtoupper(str_replace('_', ' ', $selectedDetail['piutang']['status'])) }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-ink-300 pt-1">
                                    <div>Total Piutang: <span class="font-bold text-white tabular-nums">Rp {{ number_format($selectedDetail['piutang']['jumlah'], 0, ',', '.') }}</span></div>
                                    <div>Sudah Dibayar: <span class="font-bold text-up-mint tabular-nums">Rp {{ number_format($selectedDetail['piutang']['jumlah_dibayar'], 0, ',', '.') }}</span></div>
                                    <div>Sisa Tagihan: <span class="font-bold text-up-accent tabular-nums">Rp {{ number_format($selectedDetail['piutang']['sisa'], 0, ',', '.') }}</span></div>
                                </div>
                                @if(!empty($selectedDetail['piutang']['jatuh_tempo']))
                                    <div class="text-[11px] text-ink-400">Jatuh Tempo: <span class="font-semibold text-white">{{ $selectedDetail['piutang']['jatuh_tempo'] }}</span></div>
                                @endif
                            </div>
                        @endif

                        @if(!empty($selectedDetail['pengiriman']))
                            <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-xs space-y-1">
                                <div class="font-bold text-indigo-300 flex items-center justify-between">
                                    <span>🚚 Ekspedisi / Kurir: {{ $selectedDetail['pengiriman']['kurir'] }} ({{ $selectedDetail['pengiriman']['layanan'] ?? 'Reguler' }})</span>
                                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-200">{{ $selectedDetail['pengiriman']['status'] ?? 'pending' }}</span>
                                </div>
                                <div class="text-ink-300">
                                    No. Resi: <span class="font-mono font-bold text-white">{{ $selectedDetail['pengiriman']['no_resi'] ?? 'Belum ada resi' }}</span>
                                </div>
                                <div class="text-ink-300 text-[11px]">
                                    Penerima: {{ $selectedDetail['pengiriman']['nama_penerima'] }} ({{ $selectedDetail['pengiriman']['telepon_tujuan'] ?? '-' }}) · {{ $selectedDetail['pengiriman']['alamat_tujuan'] }}
                                </div>
                            </div>
                        @endif

                        <div>
                            <h4 class="text-xs font-semibold text-ink-300 mb-2">Item Belanja:</h4>
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-white/10 text-ink-400 text-[10px]">
                                        <th class="pb-2">Barang</th>
                                        <th class="pb-2 text-center">Qty</th>
                                        <th class="pb-2 text-right">Harga</th>
                                        <th class="pb-2 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($selectedDetail['items'] as $it)
                                        <tr>
                                            <td class="py-2 text-white font-medium">
                                                {{ $it['nama'] }}
                                                <span class="block text-[10px] text-ink-500 font-mono">{{ $it['sku'] }}</span>
                                            </td>
                                            <td class="py-2 text-center text-white tabular-nums">{{ $it['qty'] }}</td>
                                            <td class="py-2 text-right text-ink-300 tabular-nums">Rp {{ number_format($it['harga'], 0, ',', '.') }}</td>
                                            <td class="py-2 text-right text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                            <div class="flex justify-between text-ink-400">
                                <span>Subtotal</span>
                                <span class="tabular-nums text-white">Rp {{ number_format($selectedDetail['subtotal'], 0, ',', '.') }}</span>
                            </div>
                            @if($selectedDetail['diskon'] > 0)
                                <div class="flex justify-between text-up-accent">
                                    <span>Diskon</span>
                                    <span class="tabular-nums">- Rp {{ number_format($selectedDetail['diskon'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if($selectedDetail['pajak'] > 0)
                                <div class="flex justify-between text-ink-400">
                                    <span>PPN</span>
                                    <span class="tabular-nums text-white">Rp {{ number_format($selectedDetail['pajak'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-sm font-bold text-white pt-2 border-t border-white/10">
                                <span>Total Akhir</span>
                                <span class="tabular-nums text-up-mint">Rp {{ number_format($selectedDetail['total'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-ink-400 text-[11px] pt-1">
                                <span>Jumlah Bayar (Kembalian)</span>
                                <span class="tabular-nums text-white">Rp {{ number_format($selectedDetail['bayar'], 0, ',', '.') }} (Rp {{ number_format($selectedDetail['kembalian'], 0, ',', '.') }})</span>
                            </div>
                        </div>

                    @elseif($selectedDetail['tipe'] === 'servis')
                        {{-- SERVIS DETAIL --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3.5 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px]">Pelanggan</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['pelanggan'] }}</span>
                                <span class="text-[10px] text-ink-400 block font-mono">{{ $selectedDetail['telepon'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Perangkat Gadget</span>
                                <span class="font-semibold text-teal-400">{{ $selectedDetail['perangkat'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Status Pembayaran</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $selectedDetail['status_pembayaran'] === 'lunas' ? 'bg-up-mint/15 text-up-mint' : 'bg-up-amber/15 text-up-amber' }}">
                                    {{ $selectedDetail['status_pembayaran'] }}
                                </span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Teknisi Penanggung Jawab</span>
                                <span class="font-medium text-white">{{ $selectedDetail['teknisi'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Metode Pembayaran</span>
                                <span class="font-semibold text-white uppercase">{{ $selectedDetail['metode_bayar'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">No Jurnal Pelunasan</span>
                                <span class="font-mono text-ink-300 text-[10px]">{{ $selectedDetail['no_jurnal'] }}</span>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-semibold text-ink-300 mb-2">Rincian Jasa & Sparepart Terpakai:</h4>
                            <div class="rounded-xl border border-white/10 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-white/10 text-ink-400 text-[10px] bg-white/[0.02]">
                                            <th class="py-2 px-3">Tipe</th>
                                            <th class="py-2 px-3">Item / Uraian</th>
                                            <th class="py-2 px-3 text-center">Qty</th>
                                            <th class="py-2 px-3 text-right">Tarif</th>
                                            <th class="py-2 px-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @forelse($selectedDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2 px-3">
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ ($it['tipe'] ?? '') === 'jasa' ? 'bg-teal-500/10 text-teal-400' : 'bg-up-primary/10 text-indigo-300' }}">
                                                        {{ $it['tipe'] ?? 'Part' }}
                                                    </span>
                                                </td>
                                                <td class="py-2 px-3 text-white font-medium">
                                                    {{ $it['nama'] }}
                                                </td>
                                                <td class="py-2 px-3 text-center text-white tabular-nums">{{ $it['qty'] ?? 1 }}</td>
                                                <td class="py-2 px-3 text-right text-ink-300 tabular-nums">Rp {{ number_format($it['harga'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="py-2 px-3 text-right text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'] ?? 0, 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-ink-400 text-xs">
                                                    Tidak ada rincian item tercatat.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                            <div class="flex justify-between text-ink-400">
                                <span>Subtotal Jasa Servis</span>
                                <span class="tabular-nums text-white">Rp {{ number_format($selectedDetail['total_jasa'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-ink-400">
                                <span>Subtotal Sparepart</span>
                                <span class="tabular-nums text-white">Rp {{ number_format($selectedDetail['total_part'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-white pt-2 border-t border-white/10">
                                <span>Total Tagihan Servis</span>
                                <span class="tabular-nums text-teal-400 text-base">Rp {{ number_format($selectedDetail['total'], 0, ',', '.') }}</span>
                            </div>
                        </div>

                    @elseif($selectedDetail['tipe'] === 'mutasi')
                        {{-- MUTASI DETAIL --}}
                        <div class="p-4 rounded-xl bg-white/[0.03] border border-white/5 space-y-3 text-xs">
                            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                                <span class="text-ink-400">Nomor Jurnal Mutasi</span>
                                <span class="font-mono font-bold text-white">{{ $selectedDetail['no_jurnal'] }}</span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                                <span class="text-ink-400">Jenis Transaksi</span>
                                <span class="font-bold uppercase {{ $selectedDetail['jenis'] === 'masuk' ? 'text-up-mint' : 'text-up-red' }}">
                                    {{ $selectedDetail['jenis'] === 'masuk' ? 'Kas Masuk (+)' : 'Kas Keluar (-)' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                                <span class="text-ink-400">Nominal</span>
                                <span class="text-base font-bold tabular-nums {{ $selectedDetail['jenis'] === 'masuk' ? 'text-up-mint' : 'text-up-red' }}">
                                    Rp {{ number_format($selectedDetail['nominal'], 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                                <span class="text-ink-400">Akun Lawan COA</span>
                                <span class="font-mono text-white">{{ $selectedDetail['akun_kode'] }} - {{ $selectedDetail['akun_nama'] }}</span>
                            </div>
                            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                                <span class="text-ink-400">Operator Kasir</span>
                                <span class="text-white font-medium">{{ $selectedDetail['operator'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block mb-1">Keterangan / Keperluan:</span>
                                <p class="p-2.5 rounded-lg bg-black/20 text-white border border-white/5 font-mono text-[11px]">
                                    {{ $selectedDetail['keterangan'] }}
                                </p>
                            </div>
                        </div>
                    @elseif($selectedDetail['tipe'] === 'retur')
                        {{-- RETUR DETAIL --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px]">Pelanggan</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['pelanggan'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Transaksi Asal</span>
                                <span class="font-mono font-bold text-indigo-400">{{ $selectedDetail['no_transaksi'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Penyelesaian</span>
                                <span class="font-semibold text-white uppercase">{{ str_replace('_', ' ', $selectedDetail['tipe_penyelesaian']) }}</span>
                                <span class="text-[10px] text-ink-400 block">({{ $selectedDetail['metode_pengembalian'] ?: 'kas' }})</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Status Retur</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase inline-block mt-0.5 {{ $selectedDetail['status'] === 'selesai' ? 'bg-up-mint/15 text-up-mint' : 'bg-up-amber/15 text-up-amber' }}">
                                    {{ $selectedDetail['status'] }}
                                </span>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs space-y-1">
                            <div class="text-ink-400 text-[10px]">Alasan Pengembalian:</div>
                            <div class="text-white font-medium">{{ $selectedDetail['alasan'] }}</div>
                            @if(!empty($selectedDetail['catatan_qc']) && $selectedDetail['catatan_qc'] !== '-')
                                <div class="text-ink-400 text-[10px] pt-1 border-t border-white/5 mt-1">Catatan QC:</div>
                                <div class="text-ink-300">{{ $selectedDetail['catatan_qc'] }}</div>
                            @endif
                        </div>

                        <div>
                            <h4 class="text-xs font-semibold text-ink-300 mb-2">Item Barang yang Diretur:</h4>
                            <div class="rounded-xl border border-white/10 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-white/10 text-ink-400 text-[10px] bg-white/[0.02]">
                                            <th class="py-2 px-3">Produk</th>
                                            <th class="py-2 px-3 text-center">Qty</th>
                                            <th class="py-2 px-3 text-center">Kondisi</th>
                                            <th class="py-2 px-3 text-right">Harga Retur</th>
                                            <th class="py-2 px-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @forelse($selectedDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2.5 px-3">
                                                    <div class="text-white font-medium">{{ $it['nama'] }}</div>
                                                    <div class="text-[10px] text-ink-500 font-mono">{{ $it['sku'] }}</div>
                                                    @if(!empty($it['sn']))
                                                        <div class="text-[10px] text-indigo-400 font-mono mt-0.5">SN: {{ is_array($it['sn']) ? implode(', ', $it['sn']) : $it['sn'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 text-center text-white tabular-nums font-bold">{{ $it['qty'] }}</td>
                                                <td class="py-2.5 px-3 text-center">
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $it['kondisi'] === 'bagus' ? 'bg-up-mint/15 text-up-mint' : 'bg-up-red/15 text-up-red' }}">
                                                        {{ $it['kondisi'] }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right text-ink-300 tabular-nums">Rp {{ number_format($it['harga'], 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-ink-400 text-xs">Tidak ada item retur tercatat.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if(!empty($selectedDetail['tukar_transaksi']))
                            <div class="p-3.5 rounded-xl border border-indigo-500/30 bg-indigo-500/[0.08] text-xs space-y-1">
                                <div class="font-bold text-indigo-300 flex items-center justify-between">
                                    <span>🔄 Transaksi Tukar Barang:</span>
                                    <span class="font-mono text-white">{{ $selectedDetail['tukar_transaksi']['no_transaksi'] }}</span>
                                </div>
                                <div class="flex justify-between text-ink-300 text-[11px] pt-1">
                                    <span>Nilai Belanja Baru: Rp {{ number_format($selectedDetail['tukar_transaksi']['total'], 0, ',', '.') }}</span>
                                    <span class="text-white font-bold">Selisih Nominal: Rp {{ number_format($selectedDetail['tukar_transaksi']['selisih'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                            <div class="flex justify-between text-sm font-bold text-white">
                                <span>Total Nilai Retur</span>
                                <span class="tabular-nums text-up-accent text-base">Rp {{ number_format($selectedDetail['jumlah'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @elseif($selectedDetail['tipe'] === 'pembelian')
                        {{-- DETAIL PEMBELIAN (PO) --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px]">Supplier</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['supplier'] }}</span>
                                @if(!empty($selectedDetail['supplier_telepon']) && $selectedDetail['supplier_telepon'] !== '-')
                                    <span class="text-[10px] text-ink-400 block font-mono">{{ $selectedDetail['supplier_telepon'] }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Gudang Tujuan</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['gudang'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Metode Bayar</span>
                                <span class="font-semibold text-white uppercase">{{ $selectedDetail['metode_bayar'] }}</span>
                                @if(!empty($selectedDetail['jatuh_tempo']) && $selectedDetail['jatuh_tempo'] !== '-')
                                    <span class="text-[10px] text-ink-400 block">Tempo: {{ $selectedDetail['jatuh_tempo'] }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Status PO</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase inline-block mt-0.5 {{ $selectedDetail['status'] === 'diterima' ? 'bg-up-mint/15 text-up-mint' : 'bg-amber-500/15 text-amber-300' }}">
                                    {{ $selectedDetail['status'] }}
                                </span>
                            </div>
                        </div>

                        @if(!empty($selectedDetail['catatan']) && $selectedDetail['catatan'] !== '-')
                            <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs space-y-1">
                                <div class="text-ink-400 text-[10px]">Catatan PO:</div>
                                <div class="text-ink-200">{{ $selectedDetail['catatan'] }}</div>
                            </div>
                        @endif

                        {{-- Items Table --}}
                        <div>
                            <h4 class="text-xs font-semibold text-ink-300 mb-2">Item Barang Purchase Order:</h4>
                            <div class="rounded-xl border border-white/10 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-white/10 text-ink-400 text-[10px] bg-white/[0.02] whitespace-nowrap">
                                            <th class="py-2 px-3">Produk</th>
                                            <th class="py-2 px-3 text-center">Qty PO</th>
                                            <th class="py-2 px-3 text-center">Sudah Retur</th>
                                            <th class="py-2 px-3 text-center">Sisa Retur</th>
                                            <th class="py-2 px-3 text-center">Stok</th>
                                            <th class="py-2 px-3 text-right">Harga Beli</th>
                                            <th class="py-2 px-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @forelse($selectedDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2.5 px-3">
                                                    <div class="text-white font-medium">{{ $it['nama'] }}</div>
                                                    <div class="text-[10px] text-ink-500 font-mono">{{ $it['sku'] }}</div>
                                                </td>
                                                <td class="py-2.5 px-3 text-center text-white tabular-nums font-bold">{{ $it['qty'] }}</td>
                                                <td class="py-2.5 px-3 text-center text-amber-400 tabular-nums font-medium">{{ $it['sudah_retur'] }}</td>
                                                <td class="py-2.5 px-3 text-center text-sky-400 tabular-nums font-bold">{{ $it['sisa_retur'] }}</td>
                                                <td class="py-2.5 px-3 text-center text-ink-300 tabular-nums">{{ $it['stok_tersedia'] }}</td>
                                                <td class="py-2.5 px-3 text-right text-ink-300 tabular-nums">Rp {{ number_format($it['harga_beli'], 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="py-4 text-center text-ink-400 text-xs">Tidak ada item dalam PO ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Returns List --}}
                        @if(!empty($selectedDetail['returns']))
                            <div class="p-3.5 rounded-xl border border-sky-500/30 bg-sky-500/[0.08] text-xs space-y-2">
                                <div class="font-bold text-sky-300 flex items-center justify-between">
                                    <span>↩ Retur Terkait Pembelian Ini ({{ count($selectedDetail['returns']) }})</span>
                                </div>
                                <div class="space-y-1.5 divide-y divide-white/5">
                                    @foreach($selectedDetail['returns'] as $ret)
                                        <div class="pt-1.5 first:pt-0 flex items-center justify-between text-[11px] gap-2">
                                            <div>
                                                <span class="font-mono font-bold text-white">{{ $ret['no_return'] }}</span>
                                                <span class="text-ink-400 ml-1">({{ $ret['tanggal'] }})</span>
                                                <span class="text-ink-400 block text-[10px]">Alasan: {{ $ret['alasan'] ?? '-' }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-bold text-up-accent tabular-nums">Rp {{ number_format($ret['jumlah'], 0, ',', '.') }}</span>
                                                <span class="block text-[9px] uppercase px-1.5 py-0.5 rounded font-bold {{ $ret['status'] === 'selesai' ? 'bg-up-mint/20 text-up-mint' : 'bg-amber-500/20 text-amber-300' }}">{{ $ret['status'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Totals --}}
                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                            <div class="flex justify-between text-ink-300">
                                <span>Total Pembelian PO</span>
                                <span class="tabular-nums font-bold text-white text-sm">Rp {{ number_format($selectedDetail['total'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-ink-300">
                                <span>Total Dibayar</span>
                                <span class="tabular-nums text-up-mint">Rp {{ number_format($selectedDetail['total_dibayar'], 0, ',', '.') }}</span>
                            </div>
                            @if($selectedDetail['sisa_utang'] > 0)
                                <div class="flex justify-between text-up-accent font-semibold pt-1 border-t border-white/5">
                                    <span>Sisa Utang</span>
                                    <span class="tabular-nums">Rp {{ number_format($selectedDetail['sisa_utang'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>

                    @elseif($selectedDetail['tipe'] === 'retur_pembelian')
                        {{-- DETAIL RETUR PEMBELIAN --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px]">Supplier</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['supplier'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Gudang Asal</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['gudang'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Ref PO Asal</span>
                                <span class="font-mono font-bold text-sky-400">{{ $selectedDetail['no_po'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Pengembalian</span>
                                <span class="font-semibold text-white uppercase">{{ $selectedDetail['metode_pengembalian'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase inline-block mt-0.5 {{ $selectedDetail['status'] === 'selesai' ? 'bg-up-mint/15 text-up-mint' : 'bg-amber-500/15 text-amber-300' }}">
                                    {{ $selectedDetail['status'] }}
                                </span>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs space-y-1">
                            <div class="text-ink-400 text-[10px]">Alasan Retur Supplier:</div>
                            <div class="text-white font-medium">{{ $selectedDetail['alasan'] }}</div>
                        </div>

                        {{-- Items Table --}}
                        <div>
                            <h4 class="text-xs font-semibold text-ink-300 mb-2">Item Barang yang Diretur ke Supplier:</h4>
                            <div class="rounded-xl border border-white/10 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-white/10 text-ink-400 text-[10px] bg-white/[0.02]">
                                            <th class="py-2 px-3">Produk</th>
                                            <th class="py-2 px-3 text-center">Qty</th>
                                            <th class="py-2 px-3 text-right">Harga Beli</th>
                                            <th class="py-2 px-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @forelse($selectedDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2.5 px-3">
                                                    <div class="text-white font-medium">{{ $it['nama'] }}</div>
                                                    <div class="text-[10px] text-ink-500 font-mono">{{ $it['sku'] }}</div>
                                                </td>
                                                <td class="py-2.5 px-3 text-center text-white tabular-nums font-bold">{{ $it['qty'] }}</td>
                                                <td class="py-2.5 px-3 text-right text-ink-300 tabular-nums">Rp {{ number_format($it['harga_beli'], 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-4 text-center text-ink-400 text-xs">Tidak ada item retur tercatat.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                            <div class="flex justify-between text-sm font-bold text-white">
                                <span>Total Nilai Retur Supplier</span>
                                <span class="tabular-nums text-sky-400 text-base">Rp {{ number_format($selectedDetail['jumlah'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer Modal --}}
                <div class="pt-3.5 border-t border-white/10 flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-2 mt-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($selectedDetail['tipe'] === 'pos' || $selectedDetail['tipe'] === 'online')
                            <button
                                wire:click="bukaRiwayat('transaksi', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                title="Audit / Log Aktivitas Transaksi"
                            >
                                Audit Log
                            </button>
                            @if($selectedDetail['tipe'] === 'pos')
                                @if($selectedDetail['status'] === 'selesai')
                                    @canany(['pos.view', 'pos.view-own', 'pos.create'])
                                        <a
                                            href="/app/pos/retur?no_faktur={{ urlencode($selectedDetail['no_transaksi']) }}"
                                            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-accent/20 hover:bg-up-accent/30 border border-up-accent/30 text-up-accent font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                            title="Proses Retur / Tukar Barang Konsumen"
                                        >
                                            ↩ Retur Barang
                                        </a>
                                    @endcanany
                                    @can('pos.void')
                                        <button
                                            type="button"
                                            wire:click="bukaVoidModal({{ $selectedDetail['id'] }})"
                                            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 border border-purple-500/30 text-purple-300 font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                            title="Batalkan & Void Transaksi"
                                        >
                                            ⛔ Void Transaksi
                                        </button>
                                    @endcan
                                @endif
                                <button
                                    wire:click="cetakUlangStruk('pos', {{ $selectedDetail['id'] }})"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                                >
                                    🖨️ Cetak Struk POS
                                </button>
                            @else
                                @if(str_contains(strtolower($selectedDetail['catatan'] ?? ''), 'ambil_ke_toko') && in_array($selectedDetail['status'], ['lunas', 'diproses', 'siap_diambil'], true))
                                    <button
                                        wire:click="serahkanPesananPickup({{ $selectedDetail['id'] }})"
                                        wire:confirm="Serahkan pesanan pickup ini ke konsumen di toko?"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                                    >
                                        Serahkan Barang Pickup
                                    </button>
                                @endif
                                @if(!in_array($selectedDetail['status'], ['selesai', 'void', 'batal', 'dibatalkan'], true))
                                    <button
                                        wire:click="sinkronkanStatusOnline({{ $selectedDetail['id'] }})"
                                        wire:loading.attr="disabled"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 hover:text-white border border-indigo-500/30 font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                    >
                                        🔄 Sync Status
                                    </button>
                                @endif
                                <button
                                    wire:click="cetakUlangStruk('online', {{ $selectedDetail['id'] }})"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                                >
                                    🖨️ Cetak Struk Online
                                </button>
                            @endif
                        @elseif($selectedDetail['tipe'] === 'servis')
                            <button
                                wire:click="bukaRiwayat('servis', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                title="Audit / Log Aktivitas Servis"
                            >
                                Audit Log
                            </button>
                            <button
                                wire:click="cetakUlangStruk('servis', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                            >
                                🖨️ Cetak Nota Servis
                            </button>
                        @elseif($selectedDetail['tipe'] === 'retur')
                            <button
                                wire:click="bukaRiwayat('retur_penjualan', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                title="Audit / Log Aktivitas Retur"
                            >
                                Audit Log Retur
                            </button>
                            <button
                                wire:click="cetakUlangStruk('retur', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-accent hover:bg-amber-600 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                            >
                                🖨️ Cetak Struk Retur
                            </button>
                        @elseif($selectedDetail['tipe'] === 'pembelian')
                            @if(!empty($selectedDetail['returns']))
                                <button
                                    wire:click="bukaRiwayat('po', {{ $selectedDetail['id'] }})"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                    title="Audit / Log Aktivitas Pembelian"
                                >
                                    Audit Log PO
                                </button>
                            @endif
                            @if($selectedDetail['bisa_diretur'] ?? false)
                                <button
                                    wire:click="bukaModalReturPembelian({{ $selectedDetail['id'] }})"
                                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                                    title="Buat Retur Pembelian ke Supplier"
                                >
                                    ↩ Buat Retur Pembelian
                                </button>
                            @endif
                        @elseif($selectedDetail['tipe'] === 'retur_pembelian')
                            <button
                                wire:click="bukaRiwayat('retur_pembelian', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] transition active:scale-95"
                                title="Audit / Log Aktivitas Retur Pembelian"
                            >
                                Audit Log Retur
                            </button>
                            <button
                                wire:click="cetakUlangStruk('retur_pembelian', {{ $selectedDetail['id'] }})"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                            >
                                🖨️ Cetak Struk Retur Pembelian
                            </button>
                        @endif
                    </div>
                    <button wire:click="tutupDetailModal" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer transition min-h-[44px] flex items-center justify-center active:scale-95">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Struk Thermal Reusable (58mm / 80mm) --}}
    @include('partials.thermal-receipt-modal')

    {{-- Modal Form Retur Pembelian ke Supplier --}}
    @if($showReturPembelianModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-5 sm:p-6 rounded-3xl relative max-h-[90vh] flex flex-col shadow-2xl border border-white/10">
                {{-- Header --}}
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10 gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded bg-sky-600/20 text-sky-400 font-bold text-[10px] uppercase">↩ Retur Pembelian</span>
                            <h3 class="text-base font-bold text-white truncate">Retur PO {{ $returNoPo }}</h3>
                        </div>
                        <p class="text-xs text-ink-400 mt-0.5">Supplier: {{ $returSupplierNama }} · Pilih item dengan jumlah retur &gt; 0</p>
                    </div>
                    <button wire:click="tutupModalReturPembelian" class="p-2.5 min-w-[44px] min-h-[44px] flex items-center justify-center text-ink-400 hover:text-white cursor-pointer rounded-xl hover:bg-white/5 shrink-0 transition" aria-label="Tutup modal">✕</button>
                </div>

                {{-- Body --}}
                <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                    {{-- Items Retur --}}
                    <div>
                        <h4 class="text-xs font-semibold text-ink-300 mb-2">Item Barang PO yang Dapat Diretur:</h4>
                        <div class="rounded-xl border border-white/10 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-white/10 text-ink-400 text-[10px] bg-white/[0.02] whitespace-nowrap">
                                        <th class="py-2 px-3">Produk</th>
                                        <th class="py-2 px-3 text-center">Sisa Retur</th>
                                        <th class="py-2 px-3 text-center">Stok</th>
                                        <th class="py-2 px-3 text-right">Harga Beli</th>
                                        <th class="py-2 px-3 text-center w-[110px]">Jumlah Retur</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($returItems as $poItemId => $item)
                                        <tr class="{{ ($item['sisa_bisa_retur'] ?? 0) <= 0 ? 'opacity-50' : '' }}">
                                            <td class="py-2.5 px-3">
                                                <div class="text-white font-medium">{{ $item['produk_nama'] }}</div>
                                                <div class="text-[10px] text-ink-500 font-mono">
                                                    {{ $item['sku'] }} · PO: {{ $item['qty_po'] }} · Sudah retur: {{ $item['sudah_retur'] }}
                                                </div>
                                                @error("returItems.{$poItemId}.jumlah_retur")
                                                    <div class="text-up-red text-[10px] font-semibold mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="py-2.5 px-3 text-center text-sky-400 tabular-nums font-bold">{{ $item['sisa_bisa_retur'] }}</td>
                                            <td class="py-2.5 px-3 text-center tabular-nums {{ $item['stok_tersedia'] <= 0 ? 'text-up-red font-bold' : 'text-ink-300' }}">{{ $item['stok_tersedia'] }}</td>
                                            <td class="py-2.5 px-3 text-right text-ink-300 tabular-nums">Rp {{ number_format($item['harga_beli'], 0, ',', '.') }}</td>
                                            <td class="py-2.5 px-3 text-center">
                                                @if(($item['sisa_bisa_retur'] ?? 0) > 0)
                                                    <input
                                                        type="number"
                                                        wire:model.live.debounce.300ms="returItems.{{ $poItemId }}.jumlah_retur"
                                                        min="0"
                                                        max="{{ $item['sisa_bisa_retur'] }}"
                                                        step="1"
                                                        class="w-full px-2 py-2 rounded-lg glass-input text-xs font-bold text-white bg-ink-900 border border-white/10 text-center min-h-[38px] focus:ring-1 focus:ring-sky-500"
                                                        aria-label="Jumlah retur {{ $item['produk_nama'] }}"
                                                    />
                                                @else
                                                    <span class="text-[10px] text-ink-500 font-bold uppercase">Habis / Sudah Diretur</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Total Estimasi Retur --}}
                    @php
                        $estimasiRetur = 0;
                        foreach ($returItems as $item) {
                            $estimasiRetur += ((float) ($item['harga_beli'] ?? 0)) * ((float) ($item['jumlah_retur'] ?? 0));
                        }
                    @endphp
                    <div class="p-3 rounded-xl bg-sky-500/[0.08] border border-sky-500/25 flex items-center justify-between text-xs">
                        <span class="font-bold text-sky-300">Estimasi Nilai Retur</span>
                        <span class="font-black tabular-nums text-white text-base">Rp {{ number_format($estimasiRetur, 0, ',', '.') }}</span>
                    </div>

                    {{-- Metode Pengembalian --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label class="relative flex cursor-pointer">
                            <input type="radio" wire:model.live="returMetodePengembalian" value="utang" class="peer sr-only" />
                            <div class="w-full p-3 rounded-xl border border-white/10 bg-white/[0.03] peer-checked:border-sky-500 peer-checked:bg-sky-500/10 text-xs font-semibold transition active:scale-[0.98]">
                                <div class="font-bold text-white">Kurangi Utang</div>
                                <div class="text-[10px] text-ink-400 mt-0.5">Sisa utang PO dipotong retur</div>
                            </div>
                        </label>
                        <label class="relative flex cursor-pointer">
                            <input type="radio" wire:model.live="returMetodePengembalian" value="saldo" class="peer sr-only" />
                            <div class="w-full p-3 rounded-xl border border-white/10 bg-white/[0.03] peer-checked:border-sky-500 peer-checked:bg-sky-500/10 text-xs font-semibold transition active:scale-[0.98]">
                                <div class="font-bold text-white">Saldo Supplier</div>
                                <div class="text-[10px] text-ink-400 mt-0.5">Jadi deposit untuk PO berikutnya</div>
                            </div>
                        </label>
                        <label class="relative flex cursor-pointer">
                            <input type="radio" wire:model.live="returMetodePengembalian" value="kas" class="peer sr-only" />
                            <div class="w-full p-3 rounded-xl border border-white/10 bg-white/[0.03] peer-checked:border-sky-500 peer-checked:bg-sky-500/10 text-xs font-semibold transition active:scale-[0.98]">
                                <div class="font-bold text-white">Kas Kembali</div>
                                <div class="text-[10px] text-ink-400 mt-0.5">Dana langsung masuk kas laci</div>
                            </div>
                        </label>
                    </div>
                    @error('returMetodePengembalian')
                        <div class="text-up-red text-[11px] font-semibold">{{ $message }}</div>
                    @enderror

                    {{-- Alasan Retur --}}
                    <div>
                        <label for="returAlasan" class="text-xs font-semibold text-ink-300 block mb-1.5">Alasan Retur ke Supplier <span class="text-up-red">*</span></label>
                        <textarea
                            wire:model.live.debounce.300ms="returAlasan"
                            id="returAlasan"
                            rows="2"
                            placeholder="Contoh: Barang rusak saat QC, LCD tidak menyala, salah kirim varian..."
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-white border border-white/10 min-h-[44px] focus:ring-1 focus:ring-sky-500"
                        ></textarea>
                        @error('returAlasan')
                            <div class="text-up-red text-[11px] font-semibold mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Footer --}}
                <div class="pt-3.5 mt-2 border-t border-white/10 flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2">
                    <button
                        wire:click="tutupModalReturPembelian"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer transition min-h-[44px] flex items-center justify-center active:scale-95"
                    >
                        Batal
                    </button>
                    <button
                        wire:click="prosesSimpanReturPembelian"
                        wire:loading.attr="disabled"
                        wire:target="prosesSimpanReturPembelian"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 disabled:opacity-50 text-white font-bold text-xs cursor-pointer flex items-center justify-center gap-1.5 shadow-sm min-h-[44px] transition active:scale-95"
                        title="Eksekusi Retur Pembelian"
                    >
                        <span wire:loading.remove wire:target="prosesSimpanReturPembelian">↩ Eksekusi Retur</span>
                        <span wire:loading wire:target="prosesSimpanReturPembelian" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Memproses...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Void Transaksi --}}
    @include('modules.pos.livewire.modals.void-transaksi-modal')

    {{-- Audit Log Modal Include --}}
    @include('partials.riwayat-modal')
</div>
