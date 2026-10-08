<div class="p-4 sm:p-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-ink-50 dark:text-white">Riwayat Transaksi</h1>
            <p class="text-xs sm:text-sm text-ink-400 mt-1">
                Pusat riwayat transaksi global, penjualan kasir POS, rekap servis gadget selesai, dan mutasi kas laci.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/app/pos" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-semibold shadow-sm transition active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <span>Buka Kasir POS (F2)</span>
            </a>
            <a href="/app/servis" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white text-xs font-semibold border border-white/10 transition active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                <span>Kanban Servis</span>
            </a>
        </div>
    </div>

    {{-- Kartu Ringkasan (Hari / Tanggal Terpilih) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Penjualan POS</span>
                <span class="px-1.5 py-0.5 rounded bg-white/5 text-[10px] font-mono">{{ $ringkasan['count_penjualan_pos'] }} Trx</span>
            </div>
            <p class="text-lg sm:text-xl font-bold text-white tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_penjualan_pos'], 0, ',', '.') }}
            </p>
        </div>
        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Servis Selesai & Lunas</span>
                <span class="px-1.5 py-0.5 rounded bg-teal-500/10 text-teal-400 text-[10px] font-mono">{{ $ringkasan['count_servis_selesai'] }} Unit</span>
            </div>
            <p class="text-lg sm:text-xl font-bold text-teal-400 tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_servis_selesai'], 0, ',', '.') }}
            </p>
        </div>
        <div class="p-4 rounded-2xl glass-panel border border-white/10">
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
        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Total Kas / Arus Bersih</span>
                <span class="px-1.5 py-0.5 rounded bg-up-mint/10 text-up-mint text-[10px] font-bold">Net</span>
            </div>
            <p class="text-lg sm:text-xl font-bold {{ $ringkasan['total_kas_bersih'] >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums mt-1">
                Rp {{ number_format($ringkasan['total_kas_bersih'], 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Tab Navigasi --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-white/10 pb-2">
        <button
            wire:click="$set('activeTab', 'global')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'global' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 ring-1 ring-white/20' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Semua Transaksi (Global)</span>
        </button>
        <button
            wire:click="$set('activeTab', 'pos')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'pos' ? 'bg-up-primary text-white shadow-lg shadow-up-primary/30' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            <span>Penjualan Kasir POS</span>
        </button>
        <button
            wire:click="$set('activeTab', 'servis')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'servis' ? 'bg-teal-600 text-white shadow-lg shadow-teal-600/30' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            <span>Rekap Servis Selesai & Lunas</span>
        </button>
        <button
            wire:click="$set('activeTab', 'mutasi_kas')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'mutasi_kas' ? 'bg-up-accent text-white shadow-lg shadow-up-accent/30' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Mutasi Kas Laci (In / Out)</span>
        </button>
    </div>

    {{-- Filter Bar --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px]">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari no transaksi / no tiket / nama / keterangan..."
                class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-white border border-white/10"
            />
        </div>
        <div class="w-auto">
            <input
                type="date"
                wire:model.live="filterTanggal"
                class="px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-white border border-white/10 cursor-pointer"
            />
        </div>

        @if($activeTab === 'global')
            <div class="w-auto">
                <select wire:model.live="filterStatus" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Semua Jenis Transaksi</option>
                    <option value="pos">Penjualan Kasir POS</option>
                    <option value="servis">Pelunasan Servis Gadget</option>
                    <option value="mutasi_masuk">Kas Masuk (Laci)</option>
                    <option value="mutasi_keluar">Kas Keluar (Laci)</option>
                </select>
            </div>
            <div class="w-auto">
                <select wire:model.live="filterMetode" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Semua Metode Bayar</option>
                    <option value="tunai">Tunai</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="qris">QRIS</option>
                    <option value="split">Split</option>
                    <option value="piutang">Piutang / Kasbon</option>
                </select>
            </div>
        @elseif($activeTab === 'pos')
            <div class="w-auto">
                <select wire:model.live="filterMetode" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Semua Metode</option>
                    <option value="tunai">Tunai</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="qris">QRIS</option>
                    <option value="split">Split</option>
                    <option value="piutang">Piutang / Kasbon</option>
                </select>
            </div>
            <div class="w-auto">
                <select wire:model.live="filterStatus" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="selesai">Selesai</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
            </div>
        @elseif($activeTab === 'servis')
            <div class="w-auto">
                <select wire:model.live="filterStatus" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Selesai & Lunas (Rekap Penuh)</option>
                    <option value="selesai">Selesai (Siap Diambil)</option>
                    <option value="diambil">Selesai (Sudah Diambil)</option>
                    <option value="lunas">Sudah Lunas</option>
                    <option value="belum_lunas">Selesai Belum Lunas</option>
                    <option value="semua">Semua Status Tiket</option>
                </select>
            </div>
            <div class="w-auto">
                <select wire:model.live="filterMetode" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Semua Metode</option>
                    <option value="tunai">Tunai</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="qris">QRIS</option>
                    <option value="split">Split</option>
                </select>
            </div>
        @elseif($activeTab === 'mutasi_kas')
            <div class="w-auto">
                <select wire:model.live="filterStatus" class="px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 cursor-pointer">
                    <option value="">Semua Jenis Mutasi</option>
                    <option value="keluar">Pengeluaran (Kas Keluar)</option>
                    <option value="masuk">Pemasukan (Kas Masuk)</option>
                </select>
            </div>
        @endif

        <button
            wire:click="resetFilter"
            class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold cursor-pointer transition">
            Reset
        </button>
    </div>

    {{-- Content Table --}}
    <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden shadow-xl">
        @if($activeTab === 'global')
            {{-- TAB 0: RIWAYAT TRANSAKSI GLOBAL --}}
            <x-prism.dual-scroll>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02]">
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
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3.5 px-4 font-mono font-bold text-white">
                                    {{ $row->no_referensi }}
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($row->jenis === 'pos')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/15 text-indigo-300 border border-up-primary/20">
                                            🛒 Kasir POS
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
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ in_array(strtolower($row->metode), ['tunai', 'kas laci']) ? 'bg-up-mint/10 text-up-mint' : 'bg-white/10 text-ink-200' }}">
                                        {{ $row->metode }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold tabular-nums">
                                    @if($row->arah_kas === 'keluar')
                                        <span class="text-up-red">- Rp {{ number_format($row->nominal, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-up-mint">+ Rp {{ number_format($row->nominal, 0, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ in_array(strtolower($row->status), ['selesai', 'lunas', 'kas masuk']) ? 'bg-up-mint/10 text-up-mint' : 'bg-white/10 text-ink-300' }}">
                                        {{ ucfirst($row->status) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($row->jenis === 'pos')
                                            <button
                                                wire:click="lihatDetailTransaksi({{ $row->target_id }})"
                                                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                                title="Lihat Detail Transaksi POS"
                                            >
                                                Detail
                                            </button>
                                            <button
                                                wire:click="cetakUlangStruk('pos', {{ $row->target_id }})"
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
            </x-prism.dual-scroll>

        @elseif($activeTab === 'pos')
            {{-- TAB 1: PENJUALAN KASIR POS --}}
            <x-prism.dual-scroll>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02]">
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
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $row->no_transaksi }}</td>
                                <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-3.5 px-4 text-white font-medium">{{ $row->kasir?->name ?? '-' }}</td>
                                <td class="py-3.5 px-4 text-ink-200">{{ $row->pelanggan?->nama ?? 'Pelanggan Umum' }}</td>
                                <td class="py-3.5 px-4 text-right font-bold text-white tabular-nums">Rp {{ number_format($row->total_akhir, 0, ',', '.') }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $row->metode_bayar === 'tunai' ? 'bg-up-mint/10 text-up-mint' : 'bg-up-primary/10 text-up-primary' }}">
                                        {{ $row->metode_bayar }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $row->status === 'selesai' ? 'bg-up-mint/10 text-up-mint' : 'bg-up-red/10 text-up-red' }}">
                                        {{ ucfirst($row->status) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            wire:click="lihatDetailTransaksi({{ $row->id }})"
                                            class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white text-[11px] font-semibold transition cursor-pointer"
                                        >
                                            Detail
                                        </button>
                                        <button
                                            wire:click="cetakUlangStruk('pos', {{ $row->id }})"
                                            class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition cursor-pointer"
                                            title="Cetak Ulang Struk"
                                        >
                                            🖨️
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
            </x-prism.dual-scroll>

        @elseif($activeTab === 'servis')
            {{-- TAB 2: REKAP SERVIS SELESAI & LUNAS SECARA PENUH --}}
            <x-prism.dual-scroll>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02]">
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
                            <tr class="hover:bg-white/[0.02] transition-colors">
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
            </x-prism.dual-scroll>

        @elseif($activeTab === 'mutasi_kas')
            {{-- TAB 3: MUTASI KAS LACI --}}
            <x-prism.dual-scroll>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider bg-white/[0.02]">
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
                            <tr class="hover:bg-white/[0.02] transition-colors">
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
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <div>
                        @if($selectedDetail['tipe'] === 'pos')
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-up-primary/20 text-up-primary font-bold text-[10px] uppercase">Kasir POS</span>
                                <h3 class="text-base font-bold text-white">Detail Transaksi {{ $selectedDetail['no_transaksi'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · Kasir: {{ $selectedDetail['kasir'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'servis')
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-teal-500/20 text-teal-400 font-bold text-[10px] uppercase">Rekap Servis</span>
                                <h3 class="text-base font-bold text-white">Rincian Transaksi Servis {{ $selectedDetail['no_tiket'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">Selesai: {{ $selectedDetail['tanggal_selesai'] }} · Teknisi: {{ $selectedDetail['teknisi'] }}</p>
                        @elseif($selectedDetail['tipe'] === 'mutasi')
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded {{ $selectedDetail['jenis'] === 'masuk' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-red/20 text-up-red' }} font-bold text-[10px] uppercase">
                                    Mutasi Kas {{ ucfirst($selectedDetail['jenis']) }}
                                </span>
                                <h3 class="text-base font-bold text-white">{{ $selectedDetail['no_jurnal'] }}</h3>
                            </div>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $selectedDetail['tanggal'] }} · Operator: {{ $selectedDetail['operator'] }}</p>
                        @endif
                    </div>
                    <button wire:click="tutupDetailModal" class="p-2 text-ink-400 hover:text-white cursor-pointer rounded-lg hover:bg-white/5">✕</button>
                </div>

                {{-- Body Modal --}}
                <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                    @if($selectedDetail['tipe'] === 'pos')
                        {{-- POS DETAIL --}}
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px]">Pelanggan</span>
                                <span class="font-semibold text-white">{{ $selectedDetail['pelanggan'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px]">Metode Pembayaran</span>
                                <span class="font-semibold text-white uppercase">{{ $selectedDetail['metode_bayar'] }}</span>
                            </div>
                        </div>

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
                    @endif
                </div>

                {{-- Footer Modal --}}
                <div class="pt-3 border-t border-white/10 flex justify-between items-center mt-4">
                    <div>
                        @if($selectedDetail['tipe'] === 'pos')
                            <button
                                wire:click="cetakUlangStruk('pos', {{ $selectedDetail['id'] }})"
                                class="px-4 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white font-bold text-xs cursor-pointer flex items-center gap-1.5 shadow-sm"
                            >
                                🖨️ Cetak Struk POS
                            </button>
                        @elseif($selectedDetail['tipe'] === 'servis')
                            <button
                                wire:click="cetakUlangStruk('servis', {{ $selectedDetail['id'] }})"
                                class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs cursor-pointer flex items-center gap-1.5 shadow-sm"
                            >
                                🖨️ Cetak Nota Servis
                            </button>
                        @endif
                    </div>
                    <button wire:click="tutupDetailModal" class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Struk Thermal Reusable (58mm / 80mm) --}}
    @include('partials.thermal-receipt-modal')

    {{-- Audit Log Modal Include --}}
    @include('partials.riwayat-modal')
</div>
