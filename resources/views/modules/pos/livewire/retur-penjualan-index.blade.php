<div
    class="p-4 sm:p-6 space-y-6"
    x-data="{
        openFilter: false,
        openCameraRetur() {
            if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                window.uteBarcode.openCameraScanner({
                    title: 'Scan QR Faktur / Struk Transaksi',
                    continuous: false,
                    onScan: (code) => {
                        const clean = (code || '').trim();
                        if (clean) {
                            @this.set('kwitansiInput', clean);
                            $nextTick(() => {
                                @this.call('cariKwitansi');
                            });
                        }
                    }
                });
            } else {
                alert('Modul barcode/QR scanner sedang dimuat...');
            }
        },
        openCameraTukar() {
            if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                window.uteBarcode.openCameraScanner({
                    title: 'Scan Barcode / QR Produk Pengganti',
                    continuous: false,
                    onScan: (code) => {
                        const clean = (code || '').trim();
                        if (clean) {
                            $wire.cariTukarDenganBarcode(clean);
                        }
                    }
                });
            } else {
                alert('Modul barcode/QR scanner sedang dimuat...');
            }
        }
    }"
    @ute:barcode-scanned.window="if ($wire.showCreateModal) { if ($wire.tipePenyelesaian === 'tukar_produk') { $wire.cariTukarDenganBarcode($event.detail.code); } else { $wire.set('kwitansiInput', $event.detail.code); $nextTick(() => { $wire.call('cariKwitansi'); }); } }"
>
    {{-- Header & Aksi Utama --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-ink-900 dark:text-white flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-up-red/10 border border-up-red/20 flex items-center justify-center text-up-red text-base shrink-0">↩</span>
                <span>Manajemen Retur Konsumen</span>
            </h1>
            <p class="text-xs sm:text-sm text-ink-400 mt-1">
                Layanan pengembalian dana, tukar barang, verifikasi nomor seri anti-fraud, dan isolasi karantina produk rusak.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <button
                type="button"
                @click="openCameraRetur()"
                class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-up-mint hover:text-white text-xs font-bold transition active:scale-[0.98] cursor-pointer min-h-[44px]"
                title="Scan QR Code Struk Menggunakan Kamera"
                aria-label="Scan QR Code Struk Menggunakan Kamera"
            >
                <svg class="w-4 h-4 text-up-mint shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
                <span>Scan QR Struk</span>
            </button>

            <button
                type="button"
                wire:click="bukaModalTambah"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-up-red to-rose-600 hover:opacity-95 text-white text-xs font-bold shadow-md shadow-up-red/20 transition active:scale-[0.98] cursor-pointer min-h-[44px]"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>+ Buat Retur Konsumen Baru</span>
            </button>
        </div>
    </div>

    {{-- Kartu Ringkasan KPI Retur --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Total Retur Bulan Ini</span>
                <span class="text-base">📦</span>
            </div>
            <p class="text-lg sm:text-2xl font-bold text-white tabular-nums mt-1">
                {{ $stats['total_retur_bulan_ini'] }} <span class="text-xs text-ink-400 font-normal">Klaim</span>
            </p>
        </div>

        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Total Nilai Retur</span>
                <span class="text-base">💵</span>
            </div>
            <p class="text-lg sm:text-2xl font-bold text-up-red tabular-nums mt-1">
                Rp {{ number_format($stats['total_nilai_bulan_ini'], 0, ',', '.') }}
            </p>
        </div>

        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Penyelesaian Tukar Produk</span>
                <span class="text-base">🔄</span>
            </div>
            <p class="text-lg sm:text-2xl font-bold text-up-mint tabular-nums mt-1">
                {{ $stats['tukar_produk_count'] }} <span class="text-xs text-ink-400 font-normal">Transaksi</span>
            </p>
        </div>

        <div class="p-4 rounded-2xl glass-panel border border-white/10">
            <div class="flex items-center justify-between text-xs text-ink-400 font-medium">
                <span>Menunggu Approval</span>
                <span class="text-base">⏳</span>
            </div>
            <p class="text-lg sm:text-2xl font-bold {{ $stats['pending_approval'] > 0 ? 'text-up-amber' : 'text-ink-300' }} tabular-nums mt-1">
                {{ $stats['pending_approval'] }} <span class="text-xs text-ink-400 font-normal">Dokumen</span>
            </p>
        </div>
    </div>

    {{-- Filter Bar (Collapsible on Mobile, Expanded on Desktop) --}}
    @php
        $hasActiveFilter = ($filterStatus !== 'all') || ($filterTipe !== 'all') || !empty($filterTanggalDari) || !empty($filterTanggalSampai) || !empty($search);
    @endphp
    <div class="p-3.5 sm:p-4 rounded-2xl glass-panel border border-white/10 space-y-3">
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari No. Retur / No. Kwitansi / Pelanggan..."
                    class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-white min-h-[44px]"
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

            {{-- Mobile Filter Toggle Button --}}
            <button
                type="button"
                @click="openFilter = !openFilter"
                class="sm:hidden px-3.5 py-2.5 rounded-xl border border-white/10 text-xs font-bold flex items-center justify-center gap-1.5 transition cursor-pointer min-h-[44px] shrink-0"
                :class="openFilter || {{ $hasActiveFilter ? 'true' : 'false' }} ? 'bg-up-primary text-white border-up-primary' : 'bg-white/5 text-ink-300 hover:bg-white/10'"
                aria-label="Filter"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span>Filter</span>
                @if($hasActiveFilter)
                    <span class="w-2 h-2 rounded-full bg-up-accent animate-pulse"></span>
                @endif
            </button>
        </div>

        {{-- Filter Parameters (Collapsible on mobile, always visible on sm+) --}}
        <div :class="openFilter ? 'block' : 'hidden sm:block'" class="space-y-3 pt-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                <div>
                    <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Status Dokumen</label>
                    <select wire:model.live="filterStatus" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs text-white bg-ink-900 min-h-[44px] cursor-pointer">
                        <option value="all">Semua Status</option>
                        <option value="draft">Draft / Menunggu Approval</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="selesai">Selesai</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </div>

                <div>
                    <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Bentuk Penyelesaian</label>
                    <select wire:model.live="filterTipe" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs text-white bg-ink-900 min-h-[44px] cursor-pointer">
                        <option value="all">Semua Tipe</option>
                        <option value="refund">Pengembalian Uang (Refund)</option>
                        <option value="tukar_produk">Tukar Produk (Exchange)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Dari Tanggal</label>
                        <input type="date" wire:model.live="filterTanggalDari" class="w-full px-2 py-2.5 rounded-xl glass-input text-xs text-white min-h-[44px] cursor-pointer" />
                    </div>
                    <div>
                        <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Sampai</label>
                        <input type="date" wire:model.live="filterTanggalSampai" class="w-full px-2 py-2.5 rounded-xl glass-input text-xs text-white min-h-[44px] cursor-pointer" />
                    </div>
                </div>
            </div>

            @if($hasActiveFilter)
                <div class="flex items-center justify-end pt-1">
                    <button
                        type="button"
                        wire:click="$set('search', ''); $set('filterStatus', 'all'); $set('filterTipe', 'all'); $set('filterTanggalDari', ''); $set('filterTanggalSampai', '');"
                        class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-xs font-semibold cursor-pointer transition text-center min-h-[42px] flex items-center justify-center gap-1.5"
                    >
                        <span>✕ Reset Filter</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabel Data Retur Penjualan --}}
    <x-prism.data-table :headers="['No. Retur', 'No. Kwitansi Asal', 'Pelanggan', 'Tanggal', 'Penyelesaian', 'Total Nilai', 'Status', 'Aksi']">
        @forelse($returList as $row)
            <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                <td class="py-3 px-4 font-mono font-bold text-white">{{ $row->no_return }}</td>
                <td class="py-3 px-4 font-mono text-ink-200">
                    {{ $row->transaksi?->no_transaksi ?? '-' }}
                </td>
                <td class="py-3 px-4 text-ink-300">{{ $row->pelanggan?->nama ?? 'Umum' }}</td>
                <td class="py-3 px-4 text-ink-300 tabular-nums">{{ $row->tanggal?->format('d/m/Y') ?? '-' }}</td>
                <td class="py-3 px-4">
                    @if($row->tipe_penyelesaian === 'tukar_produk')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary font-bold text-[10px]">
                            🔄 Tukar Produk
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint font-bold text-[10px]">
                            💵 Refund ({{ strtoupper($row->metode_pengembalian) }})
                        </span>
                    @endif
                </td>
                <td class="py-3 px-4 font-bold text-up-red tabular-nums">
                    Rp {{ number_format($row->jumlah, 0, ',', '.') }}
                </td>
                <td class="py-3 px-4">
                    <x-prism.status-pill :status="str_replace('_','-', $row->status)" />
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="cetakStrukRetur({{ $row->id }})"
                            class="px-2 py-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/20 text-[11px] font-bold cursor-pointer transition active:scale-95 flex items-center gap-1"
                            title="Cetak Struk Retur"
                        >
                            <span>🖨️</span>
                            <span>Struk</span>
                        </button>
                        <button
                            type="button"
                            wire:click="bukaDetail({{ $row->id }})"
                            class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[11px] cursor-pointer transition active:scale-95"
                        >
                            Detail
                        </button>
                        @can('lihat-audit-log')
                            <button
                                type="button"
                                wire:click="bukaRiwayat('retur_penjualan', {{ $row->id }})"
                                class="px-2 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 text-[11px] font-bold cursor-pointer transition active:scale-95"
                            >
                                Audit
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="py-12 text-center text-ink-400">
                    Belum ada data retur penjualan yang sesuai dengan filter.
                </td>
            </tr>
        @endforelse

        {{-- Mobile Cards Slot --}}
        <x-slot:mobileCards>
            @forelse($returList as $row)
                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-white/10 transition space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="font-mono font-bold text-white text-xs block">{{ $row->no_return }}</span>
                            <span class="text-[10px] text-ink-400 font-mono">Faktur: {{ $row->transaksi?->no_transaksi ?? '-' }}</span>
                        </div>
                        <x-prism.status-pill :status="str_replace('_','-', $row->status)" />
                    </div>

                    <div class="flex justify-between items-start text-xs gap-2 pt-1 border-t border-white/5">
                        <div>
                            <span class="text-white font-medium block">{{ $row->pelanggan?->nama ?? 'Umum' }}</span>
                            <span class="text-ink-400 text-[11px] tabular-nums">{{ $row->tanggal?->format('d/m/Y') ?? '-' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-up-red tabular-nums text-sm block">Rp {{ number_format($row->jumlah, 0, ',', '.') }}</span>
                            @if($row->tipe_penyelesaian === 'tukar_produk')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary font-bold text-[10px] mt-0.5">
                                    🔄 Tukar Produk
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint font-bold text-[10px] mt-0.5">
                                    💵 Refund ({{ strtoupper($row->metode_pengembalian) }})
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-white/5">
                        <button
                            type="button"
                            wire:click="cetakStrukRetur({{ $row->id }})"
                            class="px-3 py-2.5 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/20 font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center gap-1 transition active:scale-95"
                            title="Cetak Struk Retur"
                        >
                            <span>🖨️</span>
                            <span>Struk</span>
                        </button>
                        <button
                            type="button"
                            wire:click="bukaDetail({{ $row->id }})"
                            class="flex-1 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                        >
                            Detail
                        </button>
                        @can('lihat-audit-log')
                            <button
                                type="button"
                                wire:click="bukaRiwayat('retur_penjualan', {{ $row->id }})"
                                class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-bold cursor-pointer min-h-[44px] flex items-center justify-center transition active:scale-95"
                            >
                                Audit
                            </button>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-ink-400 text-xs">
                    Belum ada data retur penjualan yang sesuai dengan filter.
                </div>
            @endforelse
        </x-slot:mobileCards>

        <x-slot:pagination>
            {{ $returList->links() }}
        </x-slot:pagination>
    </x-prism.data-table>

    {{-- MODAL: FORM PENGAJUAN RETUR BARU (WAJIB NO KWITANSI) --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 overflow-y-auto">
            <div class="w-full max-w-3xl glass-panel p-5 sm:p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>↩</span>
                            <span>Form Pengajuan Retur Konsumen</span>
                        </h3>
                        <p class="text-[11px] text-ink-400">Wajib validasi nomor kwitansi / faktur penjualan asal sebelum memproses retur.</p>
                    </div>
                    <button type="button" wire:click="tutupModalTambah" class="text-ink-400 hover:text-white cursor-pointer p-1">✕</button>
                </div>

                <div class="space-y-4 flex-1 overflow-y-auto pr-1 text-xs">
                    <!-- LANGKAH 1: INPUT & VERIFIKASI NO KWITANSI / FAKTUR -->
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-up-primary text-white flex items-center justify-center text-[10px]">1</span>
                                <span>Nomor Kwitansi / Faktur Penjualan (Wajib)</span>
                            </span>
                            <span class="text-[10px] text-ink-400">Scan QR di struk atau ketik no. faktur</span>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="relative flex-1">
                                <!-- Tombol Kamera Scanner di dalam input -->
                                <button
                                    type="button"
                                    @click="openCameraRetur()"
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 p-2 rounded-lg text-up-primary hover:text-white hover:bg-up-primary/20 transition cursor-pointer min-h-[36px] min-w-[36px] flex items-center justify-center"
                                    title="Scan QR Code / Barcode Struk menggunakan Kamera"
                                    aria-label="Scan QR Code Kamera"
                                >
                                    <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </button>

                                <input
                                    type="text"
                                    wire:model="kwitansiInput"
                                    wire:keydown.enter="cariKwitansi"
                                    placeholder="Ketik atau Scan QR / Barcode Struk Faktur..."
                                    class="w-full pl-11 pr-3.5 py-2.5 rounded-xl glass-input text-xs font-mono font-bold text-white min-h-[44px]"
                                    data-barcode-input="true"
                                    x-ref="kwitansiField"
                                />
                            </div>

                            <button
                                type="button"
                                wire:click="cariKwitansi"
                                class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs min-h-[44px] cursor-pointer transition active:scale-95 shrink-0 flex items-center justify-center"
                            >
                                Verifikasi Faktur
                            </button>
                        </div>

                        @if($selectedTransaksi)
                            <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/20 grid grid-cols-2 sm:grid-cols-4 gap-2 text-ink-300 text-[11px]">
                                <div>
                                    <span class="text-ink-400 block text-[10px]">No Faktur:</span>
                                    <span class="font-mono font-bold text-white">{{ $selectedTransaksi->no_transaksi }}</span>
                                </div>
                                <div>
                                    <span class="text-ink-400 block text-[10px]">Tanggal Beli:</span>
                                    <span class="text-white">{{ $selectedTransaksi->created_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div>
                                    <span class="text-ink-400 block text-[10px]">Pelanggan:</span>
                                    <span class="text-white font-semibold">{{ $selectedTransaksi->pelanggan?->nama ?? 'Umum' }}</span>
                                </div>
                                <div>
                                    <span class="text-ink-400 block text-[10px]">Total Belanja:</span>
                                    <span class="font-bold text-up-mint tabular-nums">Rp {{ number_format($selectedTransaksi->total_akhir, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            {{-- [ANTI-FRAUD] Deteksi Cerdas Masa Garansi --}}
                            @if($isGaransiExpired)
                                <div class="p-3.5 rounded-2xl bg-up-red/15 border border-up-red/40 text-white space-y-1">
                                    <div class="flex items-center gap-2 font-bold text-up-red text-xs sm:text-sm">
                                        <span class="text-base">⛔</span>
                                        <span>RETUR OTOMATIS DITOLAK — MASA GARANSI HABIS</span>
                                    </div>
                                    <p class="text-[11px] text-ink-200 leading-relaxed">
                                        {{ $garansiKeterangan }}
                                    </p>
                                </div>
                            @else
                                <div class="p-3 rounded-2xl bg-up-mint/10 border border-up-mint/30 flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-up-mint animate-pulse"></span>
                                        <span class="font-bold text-up-mint text-xs">Masa Garansi Toko Aktif</span>
                                    </div>
                                    <span class="text-[11px] font-mono text-ink-300">
                                        {{ $garansiKeterangan }}
                                    </span>
                                </div>
                            @endif
                        @endif
                    </div>

                    @if($selectedTransaksi)
                        <!-- LANGKAH 2: PILIH BARANG & KONFIGURASI PER-ITEM -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded-full bg-up-primary text-white flex items-center justify-center text-[10px]">2</span>
                                    <span>Pilih Barang, Kondisi, Alasan & Bentuk Penyelesaian Per Item</span>
                                </span>
                                @error('items')
                                    <span class="text-[11px] font-bold text-up-red flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            @error('items')
                                <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/30 text-up-red text-xs flex items-center gap-2">
                                    <span class="text-base">⚠️</span>
                                    <span><strong>Perhatian:</strong> Belum memilih barang untuk diretur (kuantiti masih 0 semua). Silakan tentukan Qty Retur pada salah satu barang di bawah.</span>
                                </div>
                            @enderror

                            <div class="rounded-2xl border {{ $errors->has('items') ? 'border-up-red/50 ring-1 ring-up-red/30' : 'border-white/10' }} divide-y divide-white/5 overflow-hidden">
                                @foreach($transaksiItemData as $itemId => $item)
                                    @php
                                        $hasSnErr = $errors->has("itemInputs.{$itemId}.sn_raw");
                                        $hasQtyErr = $errors->has("itemInputs.{$itemId}.jumlah");
                                        $hasTukarErr = $errors->has("itemInputs.{$itemId}.tukar_items");
                                    @endphp
                                    <div class="p-3.5 bg-white/[0.02] space-y-3 {{ ($hasSnErr || $hasQtyErr || $hasTukarErr) ? 'bg-up-red/[0.03]' : '' }}">
                                        <div class="flex items-center justify-between gap-3 flex-wrap sm:flex-nowrap">
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <div class="font-bold text-white text-xs sm:text-sm flex items-center gap-2">
                                                    <span>{{ $item['produk_nama'] }}</span>
                                                    @if($item['has_sn'])
                                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 uppercase tracking-wider">Wajib SN</span>
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-ink-400">
                                                    SKU: {{ $item['kode'] }} · Qty Beli: <strong class="text-white">{{ $item['qty_beli'] }}</strong> · Sisa Bisa Diretur: <strong class="text-up-mint">{{ $item['sisa_bisa_diretur'] }}</strong>
                                                </div>
                                                {{-- Referensi Harga Beli Konsumen --}}
                                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-[11px]">
                                                    <span class="text-ink-400">Harga Beli Konsumen:</span>
                                                    <strong class="text-up-mint font-mono font-bold">Rp {{ number_format($item['harga_final'], 0, ',', '.') }}</strong>
                                                </div>
                                            </div>

                                            <div class="w-full sm:w-32 flex-shrink-0">
                                                <label class="text-[10px] font-bold text-ink-400 uppercase tracking-wider block mb-1">Qty Retur</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $item['sisa_bisa_diretur'] }}"
                                                    oninput="if(parseFloat(this.value) > {{ $item['sisa_bisa_diretur'] }}) { this.value = {{ $item['sisa_bisa_diretur'] }}; }"
                                                    wire:model.live="itemInputs.{{ $itemId }}.jumlah"
                                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs font-black tabular-nums text-center min-h-[44px] {{ $hasQtyErr ? '!border-up-red ring-1 ring-up-red' : '' }}"
                                                    {{ $item['sisa_bisa_diretur'] <= 0 || $isGaransiExpired ? 'disabled' : '' }}
                                                />
                                                @error("itemInputs.{$itemId}.jumlah")
                                                    <span class="text-[10px] text-up-red font-medium block mt-1">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        @if(($itemInputs[$itemId]['jumlah'] ?? 0) > 0)
                                            <div class="space-y-3 pt-3 border-t border-white/5">
                                                <!-- Baris 1: Kondisi Fisik & Serial Number -->
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                    <div>
                                                        <label class="text-[10px] font-bold text-ink-300 uppercase tracking-wider block mb-1">Kondisi Fisik Barang *</label>
                                                        <select wire:model="itemInputs.{{ $itemId }}.kondisi_barang" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs text-white bg-ink-900 min-h-[44px] cursor-pointer">
                                                            <option value="rusak">⚠️ Cacat / Rusak (Masuk Pool Karantina Supplier)</option>
                                                            <option value="bagus">✅ Normal / Layak Jual (Kembali ke Stok Aktif)</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="text-[10px] font-bold uppercase tracking-wider block mb-1 {{ $item['has_sn'] ? 'text-amber-300' : 'text-ink-300' }}">
                                                            Nomor Seri (SN) {{ $item['has_sn'] ? '(Wajib Diisi - Produk Ber-SN)' : '(Opsional)' }}
                                                        </label>
                                                        <input
                                                            type="text"
                                                            wire:model="itemInputs.{{ $itemId }}.sn_raw"
                                                            placeholder="{{ $item['has_sn'] ? 'Wajib ketik SN unit yang diretur...' : 'Ketik SN yang tertera pada unit...' }}"
                                                            class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-mono text-white min-h-[44px] {{ $hasSnErr ? '!border-up-red ring-1 ring-up-red bg-up-red/[0.05]' : ($item['has_sn'] ? 'border-amber-500/40' : '') }}"
                                                        />
                                                        @error("itemInputs.{$itemId}.sn_raw")
                                                            <span class="text-[10px] text-up-red font-bold flex items-center gap-1 mt-1">
                                                                <span>⚠️</span> {{ $message }}
                                                            </span>
                                                        @elseif($item['has_sn'])
                                                            <span class="text-[9px] text-amber-300/80 block mt-0.5">Produk ini memiliki Serial Number aktif. Pastikan SN terverifikasi.</span>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <!-- Baris 2: Alasan Khusus & Tipe Penyelesaian Per-Item -->
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                    <div>
                                                        <label class="text-[10px] font-bold text-ink-300 uppercase tracking-wider block mb-1">Alasan Retur Item Ini</label>
                                                        <input
                                                            type="text"
                                                            wire:model="itemInputs.{{ $itemId }}.alasan"
                                                            placeholder="Alasan spesifik item (opsional)..."
                                                            class="w-full px-2.5 py-2 rounded-xl glass-input text-xs text-white min-h-[44px]"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label class="text-[10px] font-bold text-ink-300 uppercase tracking-wider block mb-1">Metode Penyelesaian Item *</label>
                                                        <select wire:model.live="itemInputs.{{ $itemId }}.tipe_penyelesaian" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs text-white bg-ink-900 min-h-[44px] cursor-pointer font-bold">
                                                            <option value="refund">💵 Pengembalian Uang (Refund)</option>
                                                            <option value="tukar_produk">🔄 Tukar Produk (Exchange)</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Jika Item Refund: Pilihan Akun / Kas -->
                                                @if(($itemInputs[$itemId]['tipe_penyelesaian'] ?? 'refund') === 'refund')
                                                    <div class="p-2.5 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between gap-2">
                                                        <span class="text-[11px] text-ink-300">Tujuan Pengembalian Dana:</span>
                                                        <select wire:model="itemInputs.{{ $itemId }}.metode_pengembalian" class="px-2.5 py-1.5 rounded-lg glass-input text-xs text-white bg-ink-900 cursor-pointer font-semibold">
                                                            <option value="kas">Kas Toko (Tunai / Transfer)</option>
                                                            <option value="piutang">Potong Piutang Konsumen</option>
                                                        </select>
                                                    </div>
                                                @else
                                                    <!-- Jika Item Tukar Produk: Panel Picker 3 Prioritas -->
                                                    <div class="p-3 rounded-2xl bg-up-primary/5 border border-up-primary/20 space-y-3">
                                                        <div class="flex items-center justify-between flex-wrap gap-2">
                                                            <div>
                                                                <span class="font-bold text-white text-xs flex items-center gap-1.5">
                                                                    <span>🔄</span>
                                                                    <span>Produk Pengganti (Exchange)</span>
                                                                </span>
                                                                <p class="text-[10px] text-ink-400">Harga produk pengganti wajib &ge; harga beli konsumen (Rp {{ number_format($item['harga_final'], 0, ',', '.') }})</p>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                wire:click="bukaPilihTukar({{ $itemId }})"
                                                                class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[11px] cursor-pointer transition active:scale-95"
                                                            >
                                                                {{ $activeTukarItemId === $itemId ? '✕ Tutup Pencarian' : '🔍 Pilih Produk Pengganti' }}
                                                            </button>
                                                        </div>

                                                        @error("itemInputs.{$itemId}.tukar_items")
                                                            <div class="p-2.5 rounded-xl bg-up-red/10 border border-up-red/30 text-up-red text-[11px] font-bold flex items-center gap-1.5">
                                                                <span>⚠️</span> {{ $message }}
                                                            </div>
                                                        @enderror

                                                        <!-- Daftar Produk Pengganti Terpilih untuk Item Ini -->
                                                        @if(!empty($itemInputs[$itemId]['tukar_items']))
                                                            <div class="space-y-1.5">
                                                                <span class="text-[10px] font-bold text-up-mint uppercase tracking-wider block">Pengganti Terpilih:</span>
                                                                <div class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                                                    @foreach($itemInputs[$itemId]['tukar_items'] as $tIdx => $tIt)
                                                                        <div class="p-2.5 bg-white/5 flex items-center justify-between gap-2 text-xs">
                                                                            <div class="min-w-0 flex-1">
                                                                                <div class="font-bold text-white truncate">{{ $tIt['nama'] }}</div>
                                                                                <div class="text-[10px] text-ink-400">
                                                                                    Rp {{ number_format($tIt['harga_satuan'], 0, ',', '.') }} · Stok: {{ $tIt['stok_tersedia'] }}
                                                                                </div>
                                                                            </div>
                                                                            <div class="flex items-center gap-2">
                                                                                <input
                                                                                    type="number"
                                                                                    min="1"
                                                                                    max="{{ $tIt['stok_tersedia'] }}"
                                                                                    wire:model.live="itemInputs.{{ $itemId }}.tukar_items.{{ $tIdx }}.jumlah"
                                                                                    class="w-14 px-1.5 py-1 rounded-lg glass-input text-center text-xs font-bold"
                                                                                />
                                                                                <button
                                                                                    type="button"
                                                                                    wire:click="hapusTukarItem({{ $tIdx }}, {{ $itemId }})"
                                                                                    class="text-up-red hover:bg-up-red/10 p-1.5 rounded-lg cursor-pointer"
                                                                                    title="Hapus"
                                                                                >✕</button>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <!-- Drawer Rekomendasi Terurut 3 Prioritas -->
                                                        @if($activeTukarItemId === $itemId)
                                                            @php $rekomendasi = $this->getRekomendasiTukar($itemId); @endphp
                                                            <div class="p-3 rounded-2xl bg-ink-900 border border-white/15 space-y-3">
                                                                <div class="flex items-center gap-2">
                                                                    <input
                                                                        type="text"
                                                                        wire:model.live.debounce.300ms="itemInputs.{{ $itemId }}.tukar_search"
                                                                        placeholder="Cari nama produk atau scan barcode..."
                                                                        class="flex-1 px-3 py-2 rounded-xl glass-input text-xs text-white"
                                                                    />
                                                                </div>

                                                                <!-- Prioritas 1: Produk Sama Persis -->
                                                                @if($rekomendasi['prioritas_1']->isNotEmpty())
                                                                    <div class="space-y-1">
                                                                        <span class="text-[10px] font-bold text-up-mint uppercase tracking-wider flex items-center gap-1">
                                                                            <span>⭐ Prioritas 1: Produk Sama Persis</span>
                                                                        </span>
                                                                        <div class="divide-y divide-white/5 rounded-xl border border-white/10 overflow-hidden">
                                                                            @foreach($rekomendasi['prioritas_1'] as $p)
                                                                                <div class="p-2.5 bg-white/[0.02] flex items-center justify-between gap-2 {{ $p->is_lebih_murah ? 'opacity-50' : '' }}">
                                                                                    <div class="min-w-0 flex-1">
                                                                                        <div class="font-bold text-white text-xs">{{ $p->nama }}</div>
                                                                                        <div class="text-[10px] text-ink-400">
                                                                                            Stok: <strong class="{{ $p->stok_tersedia_val > 0 ? 'text-up-mint' : 'text-up-red' }}">{{ $p->stok_tersedia_val }}</strong> · Rp {{ number_format($p->harga_jual_val, 0, ',', '.') }}
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        @if($p->is_lebih_murah)
                                                                                            <span class="text-[9px] font-bold text-up-red px-2 py-0.5 rounded bg-up-red/10 border border-up-red/20">Dilarang (Lebih Murah)</span>
                                                                                        @elseif($p->stok_tersedia_val <= 0)
                                                                                            <span class="text-[9px] text-ink-400">Stok Habis</span>
                                                                                        @else
                                                                                            <button
                                                                                                type="button"
                                                                                                wire:click="tambahTukarItem({{ $p->id }}, {{ $itemId }})"
                                                                                                class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] cursor-pointer"
                                                                                            >+ Pilih</button>
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                <!-- Prioritas 2: Kategori Sejenis / Kompatibel -->
                                                                @if($rekomendasi['prioritas_2']->isNotEmpty())
                                                                    <div class="space-y-1">
                                                                        <span class="text-[10px] font-bold text-up-primary uppercase tracking-wider flex items-center gap-1">
                                                                            <span>🔧 Prioritas 2: Kategori Sejenis / Kompatibel</span>
                                                                        </span>
                                                                        <div class="divide-y divide-white/5 rounded-xl border border-white/10 overflow-hidden">
                                                                            @foreach($rekomendasi['prioritas_2'] as $p)
                                                                                <div class="p-2.5 bg-white/[0.02] flex items-center justify-between gap-2 {{ $p->is_lebih_murah ? 'opacity-50' : '' }}">
                                                                                    <div class="min-w-0 flex-1">
                                                                                        <div class="font-bold text-white text-xs">{{ $p->nama }}</div>
                                                                                        <div class="text-[10px] text-ink-400">
                                                                                            Stok: <strong class="{{ $p->stok_tersedia_val > 0 ? 'text-up-mint' : 'text-up-red' }}">{{ $p->stok_tersedia_val }}</strong> · Rp {{ number_format($p->harga_jual_val, 0, ',', '.') }}
                                                                                            @if($p->selisih_nominal > 0)
                                                                                                <span class="text-up-amber font-semibold">(+Rp {{ number_format($p->selisih_nominal, 0, ',', '.') }})</span>
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        @if($p->is_lebih_murah)
                                                                                            <span class="text-[9px] font-bold text-up-red px-2 py-0.5 rounded bg-up-red/10 border border-up-red/20">Dilarang (Lebih Murah)</span>
                                                                                        @elseif($p->stok_tersedia_val <= 0)
                                                                                            <span class="text-[9px] text-ink-400">Stok Habis</span>
                                                                                        @else
                                                                                            <button
                                                                                                type="button"
                                                                                                wire:click="tambahTukarItem({{ $p->id }}, {{ $itemId }})"
                                                                                                class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] cursor-pointer"
                                                                                            >+ Pilih</button>
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                <!-- Prioritas 3: Global / Lainnya -->
                                                                @if($rekomendasi['prioritas_3']->isNotEmpty())
                                                                    <div class="space-y-1">
                                                                        <span class="text-[10px] font-bold text-ink-300 uppercase tracking-wider flex items-center gap-1">
                                                                            <span>🌐 Prioritas 3: Global (Harga Setara / Lebih Tinggi)</span>
                                                                        </span>
                                                                        <div class="divide-y divide-white/5 rounded-xl border border-white/10 overflow-hidden max-h-48 overflow-y-auto">
                                                                            @foreach($rekomendasi['prioritas_3'] as $p)
                                                                                <div class="p-2.5 bg-white/[0.02] flex items-center justify-between gap-2 {{ $p->is_lebih_murah ? 'opacity-50' : '' }}">
                                                                                    <div class="min-w-0 flex-1">
                                                                                        <div class="font-bold text-white text-xs">{{ $p->nama }}</div>
                                                                                        <div class="text-[10px] text-ink-400">
                                                                                            Stok: <strong class="{{ $p->stok_tersedia_val > 0 ? 'text-up-mint' : 'text-up-red' }}">{{ $p->stok_tersedia_val }}</strong> · Rp {{ number_format($p->harga_jual_val, 0, ',', '.') }}
                                                                                            @if($p->selisih_nominal > 0)
                                                                                                <span class="text-up-amber font-semibold">(+Rp {{ number_format($p->selisih_nominal, 0, ',', '.') }})</span>
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        @if($p->is_lebih_murah)
                                                                                            <span class="text-[9px] font-bold text-up-red px-2 py-0.5 rounded bg-up-red/10 border border-up-red/20">Dilarang (Lebih Murah)</span>
                                                                                        @elseif($p->stok_tersedia_val <= 0)
                                                                                            <span class="text-[9px] text-ink-400">Stok Habis</span>
                                                                                        @else
                                                                                            <button
                                                                                                type="button"
                                                                                                wire:click="tambahTukarItem({{ $p->id }}, {{ $itemId }})"
                                                                                                class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] cursor-pointer"
                                                                                            >+ Pilih</button>
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Upload Bukti Foto Kerusakan Fisik dari Kamera Langsung -->
                                                <div class="p-3 rounded-2xl bg-white/5 border border-white/5 space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[10px] font-bold text-ink-300 uppercase tracking-wider flex items-center gap-1.5">
                                                            <span>📷</span>
                                                            <span>Upload Foto Bukti Kerusakan Fisik</span>
                                                        </span>
                                                        <span class="text-[10px] text-ink-400">Kamera HP / File Gambar</span>
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 text-white font-bold text-xs transition active:scale-95">
                                                            <svg class="w-4 h-4 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            </svg>
                                                            <span>Ambil Foto / Upload Kamera</span>
                                                            <input
                                                                type="file"
                                                                accept="image/*"
                                                                capture="environment"
                                                                wire:model="uploadFotoPerItem.{{ $itemId }}"
                                                                class="hidden"
                                                            />
                                                        </label>
                                                        <div wire:loading wire:target="uploadFotoPerItem.{{ $itemId }}" class="text-[10px] text-up-mint animate-pulse">
                                                            Mengunggah foto bukti...
                                                        </div>
                                                    </div>

                                                    <!-- Preview Foto Terunggah -->
                                                    @if(!empty($itemInputs[$itemId]['foto_bukti']))
                                                        <div class="flex flex-wrap gap-2 pt-1">
                                                            @foreach($itemInputs[$itemId]['foto_bukti'] as $fIdx => $fPath)
                                                                <div class="relative group w-16 h-16 rounded-xl overflow-hidden border border-white/20 bg-black/40">
                                                                    <img src="{{ asset('storage/' . $fPath) }}" alt="Bukti {{ $fIdx + 1 }}" class="w-full h-full object-cover" />
                                                                    <button
                                                                        type="button"
                                                                        wire:click="hapusFotoBukti({{ $itemId }}, {{ $fIdx }})"
                                                                        class="absolute top-0.5 right-0.5 bg-black/70 hover:bg-up-red text-white w-4 h-4 rounded-full flex items-center justify-center text-[10px] cursor-pointer"
                                                                        title="Hapus foto"
                                                                    >✕</button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Section Tukar Produk Cepat (Global Fallback) -->
                        @if($tipePenyelesaian === 'tukar_produk')
                            <div class="space-y-2.5 p-3.5 sm:p-4 rounded-2xl bg-white/5 border {{ $errors->has('tukarItems') ? 'border-up-red/50 ring-1 ring-up-red/30' : 'border-white/10' }}">
                                <div class="flex items-center justify-between">
                                    <label class="block text-ink-200 font-bold text-xs">Pencarian Cepat Produk Pengganti (Global) *</label>
                                    <span class="text-[10px] text-ink-400">Scan Barcode / QR atau ketik nama part</span>
                                </div>

                                @error('tukarItems')
                                    <div class="p-2.5 rounded-xl bg-up-red/10 border border-up-red/30 text-up-red text-xs font-semibold flex items-center gap-2">
                                        <span class="text-sm">⚠️</span>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror

                                <div class="relative space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <input
                                                type="text"
                                                wire:model.live.debounce.300ms="tukarSearch"
                                                data-barcode-input="true"
                                                placeholder="Scan barcode/QR atau ketik nama produk..."
                                                class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-white min-h-[44px] focus:ring-1 focus:ring-up-primary"
                                            />
                                            @if(!empty($tukarSearch))
                                                <button
                                                    type="button"
                                                    wire:click="$set('tukarSearch', '')"
                                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white text-xs p-1.5 cursor-pointer"
                                                    title="Hapus pencarian"
                                                >✕</button>
                                            @endif
                                        </div>
                                    </div>

                                    @if(!empty($this->hasilCariTukarProduk) && count($this->hasilCariTukarProduk) > 0)
                                        <div class="relative mt-2 z-20 bg-ink-900 border border-white/10 rounded-2xl shadow-2xl max-h-64 overflow-y-auto divide-y divide-white/5">
                                            @foreach($this->hasilCariTukarProduk as $pCari)
                                                @php $stokTersedia = (int) ($pCari->stokItems->first()?->jumlah ?? 0); @endphp
                                                <button
                                                    type="button"
                                                    wire:click="tambahTukarItem({{ $pCari->id }})"
                                                    class="w-full p-2.5 sm:p-3 text-left hover:bg-white/5 flex items-center justify-between gap-2.5 cursor-pointer transition active:scale-[0.99] min-h-[44px]"
                                                >
                                                    <div class="min-w-0 flex-1">
                                                        <div class="font-bold text-xs sm:text-sm text-white break-words">{{ $pCari->nama }}</div>
                                                        <div class="text-[10px] text-ink-400 mt-0.5">Stok: <strong class="{{ $stokTersedia > 0 ? 'text-up-mint' : 'text-up-red' }}">{{ $stokTersedia }}</strong> · SKU: {{ $pCari->kode }} @if($pCari->barcode)· Barcode: {{ $pCari->barcode }}@endif</div>
                                                    </div>
                                                    <div class="text-right shrink-0">
                                                        <div class="font-bold text-xs text-up-mint tabular-nums">Rp {{ number_format($pCari->harga_jual_retail ?? $pCari->harga_jual ?? 0, 0, ',', '.') }}</div>
                                                        <span class="text-[10px] text-up-primary font-bold px-2 py-0.5 rounded-md bg-up-primary/10 border border-up-primary/20 mt-1 inline-block">+ Pilih</span>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if(!empty($tukarItems))
                                    <div class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                        @foreach($tukarItems as $idx => $tItem)
                                            <div class="p-2.5 sm:p-3 bg-white/[0.02] flex items-center justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-bold text-white text-xs truncate">{{ $tItem['nama'] }}</div>
                                                    <div class="text-[10px] text-ink-400">Rp {{ number_format($tItem['harga_satuan'], 0, ',', '.') }} · Stok: {{ $tItem['stok_tersedia'] }}</div>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="{{ $tItem['stok_tersedia'] }}"
                                                        wire:model.live="tukarItems.{{ $idx }}.jumlah"
                                                        class="w-16 px-2 py-1.5 rounded-lg glass-input text-xs font-bold text-center min-h-[38px]"
                                                    />
                                                    <button
                                                        type="button"
                                                        wire:click="hapusTukarItem({{ $idx }})"
                                                        class="text-up-red p-2 hover:bg-up-red/10 rounded-lg cursor-pointer min-h-[38px] min-w-[38px] flex items-center justify-center transition active:scale-95"
                                                        title="Hapus barang penukar"
                                                    >✕</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- ALUR PEMBAYARAN SELISIH TRANSAKSI (JIKA ADA TUKAR BARANG) -->
                        @if($ringkasanFinansial['has_tukar'])
                            <div class="p-4 rounded-2xl bg-gradient-to-br from-up-primary/10 to-transparent border border-up-primary/20 space-y-3">
                                <span class="text-xs font-bold text-white uppercase tracking-wider block">
                                    💵 Alur Transaksi & Pembayaran Selisih Tukar Barang
                                </span>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                                    <div class="p-2.5 rounded-xl bg-white/5">
                                        <span class="text-ink-400 block text-[10px]">Nilai Barang Retur:</span>
                                        <span class="font-bold text-white tabular-nums">Rp {{ number_format($ringkasanFinansial['total_retur_tukar'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-white/5">
                                        <span class="text-ink-400 block text-[10px]">Nilai Produk Pengganti:</span>
                                        <span class="font-bold text-up-mint tabular-nums">Rp {{ number_format($ringkasanFinansial['total_pengganti'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-up-primary/20 col-span-2 sm:col-span-1">
                                        <span class="text-up-primary block text-[10px] font-bold uppercase">Selisih Wajib Bayar:</span>
                                        <span class="font-bold text-sm text-white tabular-nums">Rp {{ number_format($ringkasanFinansial['selisih'], 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                @if($ringkasanFinansial['selisih'] > 0)
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-white/10">
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-300 uppercase block mb-1">Metode Bayar Selisih</label>
                                            <select wire:model="metodeBayarSelisih" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs text-white bg-ink-900 min-h-[42px] cursor-pointer">
                                                <option value="tunai">Tunai / Cash Laci</option>
                                                <option value="transfer">Transfer Bank</option>
                                                <option value="qris">QRIS Toko</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-300 uppercase block mb-1">Jumlah Uang Diterima (Rp)</label>
                                            <input
                                                type="number"
                                                min="0"
                                                wire:model.live="jumlahBayarSelisih"
                                                placeholder="Contoh: 50000"
                                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-white min-h-[42px]"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-300 uppercase block mb-1">Kembalian Konsumen</label>
                                            <div class="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-bold text-up-mint min-h-[42px] flex items-center">
                                                Rp {{ number_format($ringkasanFinansial['kembalian'], 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- LANGKAH 4: CATATAN QC & VALIDASI FISIK -->
                        <div class="pt-2 border-t border-white/10 space-y-2">
                            <div>
                                <label class="block text-ink-300 font-bold mb-1">Catatan QC / Bukti Pemeriksaan Fisik (Opsional)</label>
                                <input
                                    type="text"
                                    wire:model="catatanQc"
                                    placeholder="Contoh: Segel garansi utuh, unit tidak ada bekas jatuh / air"
                                    class="w-full px-3 py-2.5 rounded-xl glass-input text-xs text-white min-h-[44px]"
                                />
                                <span class="text-[10px] text-ink-400 mt-1 block">
                                    💡 Alasan retur otomatis dirangkum dari rincian kerusakan item barang di atas.
                                </span>
                            </div>
                            @error('alasan')
                                <span class="text-[11px] text-up-red font-bold flex items-center gap-1 mt-1">
                                    <span>⚠️</span> {{ $message }}
                                </span>
                            @enderror
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="flex gap-2.5 pt-4 border-t border-white/10 mt-4">
                    <button
                        type="button"
                        wire:click="tutupModalTambah"
                        class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-bold text-xs cursor-pointer min-h-[46px] flex items-center justify-center transition active:scale-95"
                    >
                        Batal
                    </button>
                    @if($selectedTransaksi)
                        <button
                            type="button"
                            wire:click="simpanRetur"
                            @if($isGaransiExpired) disabled @endif
                            class="flex-1 py-3 rounded-xl {{ $isGaransiExpired ? 'bg-ink-700 text-ink-400 cursor-not-allowed opacity-60' : 'bg-gradient-to-r from-up-red to-rose-600 hover:opacity-95 text-white shadow-lg shadow-up-red/25 cursor-pointer' }} font-bold text-xs min-h-[46px] transition active:scale-[0.98] flex items-center justify-center gap-1.5"
                        >
                            @if($isGaransiExpired)
                                <span>⛔ Retur Ditolak (Garansi Habis)</span>
                            @else
                                <span>{{ $ringkasanFinansial['has_tukar'] ? 'Proses Tukar Barang & Selisih' : 'Proses Pengajuan Retur' }}</span>
                            @endif
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL DETAIL RETUR --}}
    @if($detailRetur)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 overflow-y-auto">
            <div class="w-full max-w-xl glass-panel p-5 sm:p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl text-xs">
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white">{{ $detailRetur->no_return }}</h3>
                        <p class="text-[10px] text-ink-400">Dokumen Retur Penjualan Konsumen</p>
                    </div>
                    <button
                        type="button"
                        wire:click="tutupDetail"
                        class="text-ink-400 hover:text-white cursor-pointer p-2 rounded-xl hover:bg-white/5 min-h-[44px] min-w-[44px] flex items-center justify-center"
                        title="Tutup"
                    >✕</button>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto pr-1">
                    <div class="grid grid-cols-2 gap-2 p-3 rounded-xl bg-white/5">
                        <div>
                            <span class="text-ink-400 block text-[10px]">No. Kwitansi / Faktur Asal:</span>
                            <span class="font-mono font-bold text-white">{{ $detailRetur->transaksi?->no_transaksi ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-ink-400 block text-[10px]">Pelanggan:</span>
                            <span class="font-semibold text-white">{{ $detailRetur->pelanggan?->nama ?? 'Umum' }}</span>
                        </div>
                        <div>
                            <span class="text-ink-400 block text-[10px]">Tanggal:</span>
                            <span class="text-white">{{ $detailRetur->tanggal?->format('d/m/Y') }}</span>
                        </div>
                        <div>
                            <span class="text-ink-400 block text-[10px]">Penyelesaian:</span>
                            <span class="font-bold text-up-mint uppercase">{{ str_replace('_', ' ', $detailRetur->tipe_penyelesaian) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-ink-300 uppercase tracking-wider text-[10px]">Rincian Barang yang Diretur</label>
                        <div class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                            @foreach($detailRetur->items as $dItem)
                                <div class="p-2.5 space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <div class="font-bold text-white text-xs">{{ $dItem->produk?->nama ?? '-' }}</div>
                                            <div class="text-[10px] text-ink-400">
                                                Kondisi: <span class="{{ $dItem->kondisi_barang === 'rusak' ? 'text-up-red' : 'text-up-mint' }} font-bold">{{ strtoupper($dItem->kondisi_barang) }}</span> · SN: {{ !empty($dItem->nomor_seri) ? implode(', ', $dItem->nomor_seri) : '-' }} · Tipe: <span class="text-up-primary font-semibold">{{ strtoupper($dItem->tipe_penyelesaian ?? 'REFUND') }}</span>
                                            </div>
                                            @if($dItem->alasan)
                                                <div class="text-[10px] text-ink-300 italic mt-0.5">
                                                    "{{ $dItem->alasan }}"
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-bold text-white">{{ (int) $dItem->jumlah }} unit</div>
                                            <div class="text-[10px] text-up-red font-mono">Rp {{ number_format($dItem->subtotal, 0, ',', '.') }}</div>
                                        </div>
                                    </div>

                                    @if(!empty($dItem->foto_bukti))
                                        <div class="flex flex-wrap gap-1.5 pt-1">
                                            @foreach($dItem->foto_bukti as $fPath)
                                                <a href="{{ asset('storage/' . $fPath) }}" target="_blank" class="block w-12 h-12 rounded-lg overflow-hidden border border-white/20 bg-black/30 hover:opacity-80 transition" title="Lihat Foto Bukti">
                                                    <img src="{{ asset('storage/' . $fPath) }}" alt="Foto Bukti" class="w-full h-full object-cover" />
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($detailRetur->tipe_penyelesaian === 'tukar_produk' && $detailRetur->tukarTransaksi)
                        <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 space-y-1">
                            <span class="text-[10px] font-bold text-up-primary uppercase tracking-wider block">Faktur Tukar Produk Pengganti:</span>
                            <div class="font-mono font-bold text-white">{{ $detailRetur->tukarTransaksi->no_transaksi }}</div>
                            <div class="text-[10px] text-ink-300">
                                Total Pengganti: Rp {{ number_format($detailRetur->tukarTransaksi->total_akhir, 0, ',', '.') }} · Selisih Bayar: <span class="font-bold text-up-mint">Rp {{ number_format($detailRetur->selisih_nominal, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/20 flex items-center justify-between">
                        <span class="font-bold text-ink-200">Total Nilai Retur:</span>
                        <span class="font-bold text-base text-up-red tabular-nums">Rp {{ number_format($detailRetur->jumlah, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="pt-4 border-t border-white/10 mt-4 flex gap-2">
                    <button
                        type="button"
                        wire:click="cetakStrukRetur({{ $detailRetur->id }})"
                        class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer min-h-[46px] flex items-center justify-center gap-1.5 transition active:scale-95 shadow-md shadow-up-primary/25"
                    >
                        <span>🖨️</span>
                        <span>Cetak Struk Retur</span>
                    </button>
                    <button
                        type="button"
                        wire:click="tutupDetail"
                        class="px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-white font-bold text-xs cursor-pointer min-h-[46px] flex items-center justify-center transition active:scale-95"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Struk Thermal Reusable (Format Global ERP) --}}
    @include('partials.thermal-receipt-modal')

    {{-- Modal Riwayat Aktivitas Audit Trail --}}
    @include('partials.riwayat-modal')
</div>
