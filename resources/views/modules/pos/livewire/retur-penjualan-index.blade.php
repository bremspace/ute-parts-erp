<div
    class="p-4 sm:p-6 space-y-6"
    x-data="{
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
        }
    }"
    @ute:barcode-scanned.window="if ($wire.showCreateModal) { $wire.set('kwitansiInput', $event.detail.code); $nextTick(() => { $wire.call('cariKwitansi'); }); }"
>
    {{-- Header & Aksi Utama --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-ink-900 dark:text-white flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-up-red/10 border border-up-red/20 flex items-center justify-center text-up-red text-base">↩</span>
                <span>Manajemen Retur Konsumen</span>
            </h1>
            <p class="text-xs sm:text-sm text-ink-400 mt-1">
                Layanan pengembalian dana, tukar barang, verifikasi nomor seri anti-fraud, dan isolasi karantina produk rusak.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="openCameraRetur()"
                class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-up-mint hover:text-white text-xs font-bold transition active:scale-[0.98] cursor-pointer"
                title="Scan QR Code Struk Menggunakan Kamera"
            >
                <svg class="w-4 h-4 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
                <span>📷 Scan QR Struk</span>
            </button>

            <button
                type="button"
                wire:click="bukaModalTambah"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-up-red to-rose-600 hover:opacity-95 text-white text-xs font-bold shadow-md shadow-up-red/20 transition active:scale-[0.98] cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    {{-- Filter Bar --}}
    <div class="p-4 rounded-2xl glass-panel border border-white/10 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div>
                <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Cari Data</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="No. Retur / No. Kwitansi / Pelanggan..."
                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                />
            </div>

            <div>
                <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Status Dokumen</label>
                <select wire:model.live="filterStatus" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white bg-ink-900">
                    <option value="all">Semua Status</option>
                    <option value="draft">Draft / Menunggu Approval</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="selesai">Selesai</option>
                    <option value="ditolak">Ditolak</option>
                </select>
            </div>

            <div>
                <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Bentuk Penyelesaian</label>
                <select wire:model.live="filterTipe" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white bg-ink-900">
                    <option value="all">Semua Tipe</option>
                    <option value="refund">Pengembalian Uang (Refund)</option>
                    <option value="tukar_produk">Tukar Produk (Exchange)</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Dari Tanggal</label>
                    <input type="date" wire:model.live="filterTanggalDari" class="w-full px-2 py-2 rounded-xl glass-input text-xs text-white" />
                </div>
                <div>
                    <label class="block text-ink-400 text-[10px] font-bold uppercase tracking-wider mb-1">Sampai</label>
                    <input type="date" wire:model.live="filterTanggalSampai" class="w-full px-2 py-2 rounded-xl glass-input text-xs text-white" />
                </div>
            </div>
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
                            wire:click="bukaDetail({{ $row->id }})"
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[11px] cursor-pointer"
                        >
                            Detail
                        </button>
                        @can('lihat-audit-log')
                            <button
                                type="button"
                                wire:click="bukaRiwayat('retur_penjualan', {{ $row->id }})"
                                class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 text-[11px] font-bold cursor-pointer"
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
    </x-prism.data-table>

    <div>{{ $returList->links() }}</div>

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

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <!-- Tombol Kamera Scanner di dalam input -->
                                <button
                                    type="button"
                                    @click="openCameraRetur()"
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-up-primary hover:text-white hover:bg-up-primary/20 transition cursor-pointer"
                                    title="Scan QR Code / Barcode Struk menggunakan Kamera"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </button>

                                <input
                                    type="text"
                                    wire:model="kwitansiInput"
                                    wire:keydown.enter="cariKwitansi"
                                    placeholder="Ketik atau Scan QR / Barcode Struk Faktur..."
                                    class="w-full pl-10 pr-3.5 py-2.5 rounded-xl glass-input text-xs font-mono font-bold text-white min-h-[42px]"
                                    data-barcode-input="true"
                                    x-ref="kwitansiField"
                                />
                            </div>

                            <button
                                type="button"
                                wire:click="cariKwitansi"
                                class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs min-h-[42px] cursor-pointer transition active:scale-95 shrink-0"
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
                        @endif
                    </div>

                    @if($selectedTransaksi)
                        <!-- LANGKAH 2: PILIH BARANG YANG DIKEMBALIKAN -->
                        <div class="space-y-2">
                            <span class="font-bold text-white text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-up-primary text-white flex items-center justify-center text-[10px]">2</span>
                                <span>Pilih Barang & Tentukan Kondisi Fisik</span>
                            </span>

                            <div class="rounded-2xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                @foreach($transaksiItemData as $itemId => $item)
                                    <div class="p-3.5 bg-white/[0.02] space-y-2.5">
                                        <div class="flex items-center justify-between gap-3 flex-wrap sm:flex-nowrap">
                                            <div class="min-w-0 flex-1">
                                                <div class="font-bold text-white text-xs sm:text-sm">{{ $item['produk_nama'] }}</div>
                                                <div class="text-[10px] text-ink-400 mt-0.5">
                                                    SKU: {{ $item['kode'] }} · Qty Beli: <strong class="text-white">{{ $item['qty_beli'] }}</strong> · Pernah Diretur: <span class="text-up-amber font-semibold">{{ $item['sudah_diretur'] }}</span> · Sisa Bisa Diretur: <strong class="text-up-mint">{{ $item['sisa_bisa_diretur'] }}</strong>
                                                </div>
                                            </div>

                                            <div class="w-32 flex-shrink-0">
                                                <label class="text-[10px] font-bold text-ink-400 uppercase tracking-wider block mb-1">Qty Retur</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $item['sisa_bisa_diretur'] }}"
                                                    wire:model.live="itemInputs.{{ $itemId }}.jumlah"
                                                    class="w-full px-3 py-1.5 rounded-xl glass-input text-xs font-black tabular-nums text-center"
                                                    {{ $item['sisa_bisa_diretur'] <= 0 ? 'disabled' : '' }}
                                                />
                                            </div>
                                        </div>

                                        @if(($itemInputs[$itemId]['jumlah'] ?? 0) > 0)
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2 border-t border-white/5">
                                                <div>
                                                    <label class="text-[10px] font-bold text-ink-300 uppercase tracking-wider block mb-1">Kondisi Fisik Barang *</label>
                                                    <select wire:model="itemInputs.{{ $itemId }}.kondisi_barang" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-white bg-ink-900">
                                                        <option value="rusak">⚠️ Cacat / Rusak (Masuk Pool Karantina Supplier)</option>
                                                        <option value="bagus">✅ Normal / Layak Jual (Kembali ke Stok Aktif)</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="text-[10px] font-bold text-ink-300 uppercase tracking-wider block mb-1">
                                                        Nomor Seri (SN) {{ $item['has_sn'] ? '(Wajib SN Faktur Ini)' : '(Opsional)' }}
                                                    </label>
                                                    <input
                                                        type="text"
                                                        wire:model="itemInputs.{{ $itemId }}.sn_raw"
                                                        placeholder="Ketik SN yang tertera pada unit..."
                                                        class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs font-mono text-white"
                                                    />
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- LANGKAH 3: PILIH BENTUK PENYELESAIAN -->
                        <div class="space-y-2.5 pt-2 border-t border-white/10">
                            <span class="font-bold text-white text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-up-primary text-white flex items-center justify-center text-[10px]">3</span>
                                <span>Bentuk Penyelesaian Retur</span>
                            </span>

                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    wire:click="$set('tipePenyelesaian', 'refund')"
                                    class="p-3.5 rounded-2xl border text-left cursor-pointer transition {{ $tipePenyelesaian === 'refund' ? 'bg-up-primary/15 border-up-primary/40 text-white ring-2 ring-up-primary/20' : 'bg-white/5 border-transparent text-ink-300 hover:bg-white/10' }}"
                                >
                                    <div class="font-bold text-xs flex items-center gap-1.5">
                                        <span>💵</span>
                                        <span>Pengembalian Uang (Refund)</span>
                                    </div>
                                    <div class="text-[10px] text-ink-400 mt-1">Kembalikan dana via kas laci toko atau pemotongan saldo piutang.</div>
                                </button>

                                <button
                                    type="button"
                                    wire:click="$set('tipePenyelesaian', 'tukar_produk')"
                                    class="p-3.5 rounded-2xl border text-left cursor-pointer transition {{ $tipePenyelesaian === 'tukar_produk' ? 'bg-up-primary/15 border-up-primary/40 text-white ring-2 ring-up-primary/20' : 'bg-white/5 border-transparent text-ink-300 hover:bg-white/10' }}"
                                >
                                    <div class="font-bold text-xs flex items-center gap-1.5">
                                        <span>🔄</span>
                                        <span>Tukar Produk (Exchange)</span>
                                    </div>
                                    <div class="text-[10px] text-ink-400 mt-1">Ganti dengan produk sejenis atau tipe lain + kalkulasi selisih nominal.</div>
                                </button>
                            </div>

                            @if($tipePenyelesaian === 'refund')
                                <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                    <label class="block text-ink-300 font-bold mb-1">Metode Pengembalian Dana *</label>
                                    <select wire:model="metodePengembalian" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white bg-ink-900">
                                        <option value="kas">Kas Toko (110-01) — Pengembalian Tunai / Transfer</option>
                                        <option value="piutang">Piutang Usaha (120-01) — Potong Tagihan Konsumen</option>
                                    </select>
                                </div>
                            @else
                                <!-- Section Tukar Produk -->
                                <div class="space-y-2 p-3.5 rounded-2xl bg-white/5 border border-white/10">
                                    <label class="block text-ink-200 font-bold">Cari Produk Pengganti</label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="tukarSearch"
                                            placeholder="Ketik nama produk, kode SKU, atau barcode pengganti..."
                                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-white"
                                        />

                                        @if(!empty($this->hasilCariTukarProduk))
                                            <div class="absolute left-0 right-0 top-full mt-1.5 z-20 bg-ink-900 border border-white/10 rounded-2xl shadow-2xl max-h-48 overflow-y-auto divide-y divide-white/5">
                                                @foreach($this->hasilCariTukarProduk as $pCari)
                                                    @php $stokTersedia = (int) ($pCari->stokItems->first()?->jumlah ?? 0); @endphp
                                                    <button
                                                        type="button"
                                                        wire:click="tambahTukarItem({{ $pCari->id }})"
                                                        class="w-full p-2.5 text-left hover:bg-white/5 flex items-center justify-between gap-2 cursor-pointer transition"
                                                    >
                                                        <div>
                                                            <div class="font-bold text-xs text-white">{{ $pCari->nama }}</div>
                                                            <div class="text-[10px] text-ink-400">Stok: {{ $stokTersedia }} · SKU: {{ $pCari->kode }}</div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="font-bold text-xs text-up-mint">Rp {{ number_format($pCari->harga_jual, 0, ',', '.') }}</div>
                                                            <span class="text-[10px] text-up-primary font-bold">+ Pilih</span>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    @if(!empty($tukarItems))
                                        <div class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                            @foreach($tukarItems as $idx => $tItem)
                                                <div class="p-2.5 bg-white/[0.02] flex items-center justify-between gap-2">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="font-bold text-white text-xs truncate">{{ $tItem['nama'] }}</div>
                                                        <div class="text-[10px] text-ink-400">Rp {{ number_format($tItem['harga_satuan'], 0, ',', '.') }} · Stok: {{ $tItem['stok_tersedia'] }}</div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            max="{{ $tItem['stok_tersedia'] }}"
                                                            wire:model.live="tukarItems.{{ $idx }}.jumlah"
                                                            class="w-16 px-2 py-1 rounded-lg glass-input text-xs font-bold text-center"
                                                        />
                                                        <button type="button" wire:click="hapusTukarItem({{ $idx }})" class="text-up-red p-1 hover:bg-up-red/10 rounded cursor-pointer">✕</button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- LANGKAH 4: ALASAN & CATATAN QC -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-white/10">
                            <div>
                                <label class="block text-ink-300 font-bold mb-1">Alasan Retur Konsumen *</label>
                                <input
                                    type="text"
                                    wire:model="alasan"
                                    placeholder="Contoh: LCD bergaris / salah beli tipe / cacat pabrik"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                                />
                            </div>

                            <div>
                                <label class="block text-ink-300 font-bold mb-1">Catatan QC / Bukti Fisik</label>
                                <input
                                    type="text"
                                    wire:model="catatanQc"
                                    placeholder="Contoh: Segel garansi utuh, unit tidak ada bekas jatuh"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                                />
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="flex gap-2.5 pt-4 border-t border-white/10 mt-4">
                    <button
                        type="button"
                        wire:click="tutupModalTambah"
                        class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-bold text-xs cursor-pointer min-h-[44px]"
                    >
                        Batal
                    </button>
                    @if($selectedTransaksi)
                        <button
                            type="button"
                            wire:click="simpanRetur"
                            class="flex-1 py-3 rounded-xl bg-gradient-to-r from-up-red to-rose-600 text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-red/25 transition active:scale-[0.98]"
                        >
                            {{ $tipePenyelesaian === 'tukar_produk' ? 'Proses Tukar Barang' : 'Proses Pengembalian Dana' }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL DETAIL RETUR --}}
    @if($detailRetur)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 overflow-y-auto">
            <div class="w-full max-w-xl glass-panel p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl text-xs">
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white">{{ $detailRetur->no_return }}</h3>
                        <p class="text-[10px] text-ink-400">Dokumen Retur Penjualan Konsumen</p>
                    </div>
                    <button type="button" wire:click="tutupDetail" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
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
                                <div class="p-2.5 flex items-center justify-between gap-2">
                                    <div>
                                        <div class="font-bold text-white text-xs">{{ $dItem->produk?->nama ?? '-' }}</div>
                                        <div class="text-[10px] text-ink-400">
                                            Kondisi: <span class="{{ $dItem->kondisi_barang === 'rusak' ? 'text-up-red' : 'text-up-mint' }} font-bold">{{ strtoupper($dItem->kondisi_barang) }}</span> · SN: {{ !empty($dItem->nomor_seri) ? implode(', ', $dItem->nomor_seri) : '-' }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-white">{{ (int) $dItem->jumlah }} unit</div>
                                        <div class="text-[10px] text-up-red font-mono">Rp {{ number_format($dItem->subtotal, 0, ',', '.') }}</div>
                                    </div>
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

                <div class="pt-4 border-t border-white/10 mt-4">
                    <button
                        type="button"
                        wire:click="tutupDetail"
                        class="w-full py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-white font-bold text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
