<div class="space-y-5 animate-in fade-in duration-150">
    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-black/10 dark:border-white/5">
        <div>
            <h2 class="text-lg sm:text-xl font-extrabold text-ink-900 dark:text-white flex items-center gap-2">
                <span>📑</span>
                <span>Histori Pengadaan Barang</span>
                <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded-full bg-up-primary/15 text-up-primary border border-up-primary/25">
                    PO &amp; Price Tracker
                </span>
            </h2>
            <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">
                Audit riwayat pesanan supplier, evaluasi pergerakan harga beli per part, dan pelacakan status fisik GRN secara runtut.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <button
                type="button"
                wire:click="exportCsv"
                class="px-3.5 py-2 rounded-xl bg-up-mint/15 hover:bg-up-mint/25 text-up-mint border border-up-mint/30 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[40px]"
                title="Unduh seluruh histori pengadaan terfilter dalam format CSV"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <span>Export CSV</span>
            </button>

            <button
                type="button"
                wire:click="resetFilter"
                class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-600 dark:text-ink-300 font-semibold text-xs transition active:scale-95 cursor-pointer min-h-[40px]"
                title="Reset seluruh filter pencarian"
            >
                <span>Reset Filter</span>
            </button>
        </div>
    </div>

    <!-- Ringkasan Statistik Pengadaan & Retur (KPI Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl glass-card bg-white/70 dark:bg-ink-900/60 border border-black/10 dark:border-white/10 shadow-sm">
            <div class="text-[11px] font-semibold text-ink-500 dark:text-ink-400 mb-1 flex items-center justify-between">
                <span>Total PO Masuk</span>
                <span>📦</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-ink-900 dark:text-white tabular-nums">
                {{ number_format($totalPo) }}
            </div>
            <div class="text-[10px] text-ink-400 mt-0.5">{{ number_format($totalQty) }} unit ({{ number_format($totalProdukUnik) }} SKU)</div>
        </div>

        <div class="p-4 rounded-2xl glass-card bg-white/70 dark:bg-ink-900/60 border border-black/10 dark:border-white/10 shadow-sm">
            <div class="text-[11px] font-semibold text-ink-500 dark:text-ink-400 mb-1 flex items-center justify-between">
                <span>Total Belanja Bruto</span>
                <span>💰</span>
            </div>
            <div class="text-lg sm:text-xl font-black text-up-mint tabular-nums">
                Rp {{ number_format($totalNilai, 0, ',', '.') }}
            </div>
            <div class="text-[10px] text-ink-400 mt-0.5">Nilai subtotal PO bruto</div>
        </div>

        <div class="p-4 rounded-2xl glass-card bg-white/70 dark:bg-ink-900/60 border border-black/10 dark:border-white/10 shadow-sm">
            <div class="text-[11px] font-semibold text-up-red mb-1 flex items-center justify-between">
                <span>Retur ke Supplier</span>
                <span>↩️</span>
            </div>
            <div class="text-lg sm:text-xl font-black text-up-red tabular-nums">
                -Rp {{ number_format($totalReturNilai, 0, ',', '.') }}
            </div>
            <div class="text-[10px] text-ink-400 mt-0.5">{{ number_format($totalReturDokumen) }} Retur ({{ number_format($totalReturQty) }} unit)</div>
        </div>

        <div class="p-4 rounded-2xl glass-card bg-white/70 dark:bg-ink-900/60 border border-black/10 dark:border-white/10 shadow-sm">
            <div class="text-[11px] font-semibold text-up-primary mb-1 flex items-center justify-between">
                <span>Net Pengadaan Bersih</span>
                <span>⚖️</span>
            </div>
            <div class="text-lg sm:text-xl font-black text-up-primary tabular-nums">
                Rp {{ number_format($netPengadaanNilai, 0, ',', '.') }}
            </div>
            <div class="text-[10px] text-ink-400 mt-0.5">Net Qty: {{ number_format($netPengadaanQty) }} unit</div>
        </div>

        <div class="p-4 rounded-2xl glass-card bg-white/70 dark:bg-ink-900/60 border border-black/10 dark:border-white/10 shadow-sm col-span-2 lg:col-span-1">
            <div class="text-[11px] font-semibold text-ink-500 dark:text-ink-400 mb-1 flex items-center justify-between">
                <span>Rata-Rata per PO</span>
                <span>📊</span>
            </div>
            <div class="text-lg sm:text-xl font-black text-ink-900 dark:text-white tabular-nums">
                Rp {{ number_format($totalPo > 0 ? ($totalNilai / $totalPo) : 0, 0, ',', '.') }}
            </div>
            <div class="text-[10px] text-ink-400 mt-0.5">Nilai rata-rata order</div>
        </div>
    </div>

    <!-- Filter & Tracking Toolbar -->
    <div class="p-3.5 sm:p-4 rounded-2xl glass-panel bg-white/80 dark:bg-ink-950/80 border border-black/10 dark:border-white/10 shadow-sm space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
            <!-- Pencarian Input -->
            <div class="relative lg:col-span-2">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama part, SKU, No. PO, atau supplier..."
                    class="w-full pl-9 pr-3 py-2 rounded-xl glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10 placeholder-ink-400 min-h-[40px] focus:ring-1 focus:ring-up-primary"
                />
            </div>

            <!-- Filter Supplier -->
            <div>
                <select
                    wire:model.live="supplierId"
                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10 min-h-[40px]"
                >
                    <option value="">— Semua Supplier —</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->nama }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status PO -->
            <div>
                <select
                    wire:model.live="statusPo"
                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10 min-h-[40px]"
                >
                    <option value="all">— Status PO (Semua) —</option>
                    <option value="diterima">Diterima (GRN Selesai)</option>
                    <option value="dikirim">Sedang Dikirim Supplier</option>
                    <option value="draft">Draft PO</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <select
                    wire:model.live="sortBy"
                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10 min-h-[40px]"
                >
                    <option value="terbaru">PO Terbaru (Default)</option>
                    <option value="terlama">PO Terlama</option>
                    <option value="harga_tertinggi">Harga Beli Tertinggi</option>
                    <option value="harga_terendah">Harga Beli Terendah</option>
                    <option value="qty_tertinggi">Qty Pembelian Terbesar</option>
                </select>
            </div>
        </div>

        <!-- Rentang Tanggal Toolbar -->
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-black/5 dark:border-white/5 text-xs">
            <span class="text-ink-400 font-semibold flex items-center gap-1">
                <span>📅</span>
                <span>Periode:</span>
            </span>
            <div class="flex items-center gap-1.5">
                <input
                    type="date"
                    wire:model.live="tanggalMulai"
                    class="px-2.5 py-1.5 rounded-lg glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10"
                    placeholder="Mulai"
                />
                <span class="text-ink-400">s/d</span>
                <input
                    type="date"
                    wire:model.live="tanggalSelesai"
                    class="px-2.5 py-1.5 rounded-lg glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10"
                    placeholder="Sampai"
                />
            </div>

            @if($tanggalMulai || $tanggalSelesai)
                <button
                    type="button"
                    wire:click="$set('tanggalMulai', null); $set('tanggalSelesai', null)"
                    class="text-[11px] text-red-400 hover:underline cursor-pointer ml-1"
                >
                    ✕ Hapus Filter Tanggal
                </button>
            @endif

            <div class="ml-auto flex items-center gap-2 text-ink-400 text-[11px]">
                <span>Tampilkan:</span>
                <select wire:model.live="perPage" class="px-2 py-1 rounded-lg glass-input text-xs bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10">
                    <option value="15">15 baris</option>
                    <option value="25">25 baris</option>
                    <option value="50">50 baris</option>
                    <option value="100">100 baris</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabel Histori Pengadaan -->
    <div class="glass-card rounded-2xl border border-black/10 dark:border-white/10 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-black/[0.03] dark:bg-white/[0.04] text-ink-500 dark:text-ink-400 font-bold uppercase tracking-wider text-[11px] border-b border-black/10 dark:border-white/10">
                        <th class="py-3 px-3.5">Waktu &amp; Dokumen</th>
                        <th class="py-3 px-3">Supplier</th>
                        <th class="py-3 px-3.5">Part / Produk</th>
                        <th class="py-3 px-3 text-right">Qty Beli</th>
                        <th class="py-3 px-3 text-right">Harga Beli Satuan</th>
                        <th class="py-3 px-3 text-right">Subtotal</th>
                        <th class="py-3 px-3 text-center">Status Transaksi</th>
                        <th class="py-3 px-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 dark:divide-white/5">
                    @forelse($items as $row)
                        @php
                            $po = $row->purchaseOrder;
                            $prod = $row->produk;
                        @endphp
                        <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                            <!-- Waktu & Dokumen -->
                            <td class="py-3 px-3.5">
                                <div class="font-mono font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="bukaDetailPo({{ $row->purchase_order_id }})"
                                        class="hover:text-up-primary hover:underline cursor-pointer"
                                        title="Buka detail Purchase Order"
                                    >
                                        {{ $po?->no_po ?? '-' }}
                                    </button>
                                </div>
                                <div class="text-[10px] text-ink-500 dark:text-ink-400 font-mono mt-0.5">
                                    {{ $po?->created_at ? $po->created_at->format('d/m/Y H:i:s') : '-' }}
                                </div>
                                @if($po?->grn)
                                    <div class="mt-1">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono font-semibold bg-up-primary/10 text-up-primary border border-up-primary/20">
                                            GRN: {{ $po->grn->no_grn }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- Supplier -->
                            <td class="py-3 px-3">
                                <div class="font-semibold text-ink-900 dark:text-white">
                                    {{ $po?->supplier?->nama ?? 'Umum / Tanpa Nama' }}
                                </div>
                                @if($po?->supplier?->telepon)
                                    <div class="text-[10px] text-ink-500 dark:text-ink-400 font-mono">
                                        {{ $po->supplier->telepon }}
                                    </div>
                                @endif
                                <div class="text-[10px] text-ink-500 dark:text-ink-400 mt-0.5">
                                    Gdg: {{ $po?->gudangTujuan?->nama ?? '-' }}
                                </div>
                            </td>

                            <!-- Produk & Part -->
                            <td class="py-3 px-3.5 max-w-xs">
                                <div class="font-bold text-ink-900 dark:text-white line-clamp-2">
                                    {{ $prod?->nama ?? ('Produk #' . $row->produk_id) }}
                                </div>
                                <div class="flex items-center gap-1.5 text-[10px] text-ink-500 dark:text-ink-400 font-mono mt-0.5 flex-wrap">
                                    @if($prod?->sku)
                                        <span class="px-1.5 py-0.2 rounded bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5">
                                            SKU: {{ $prod->sku }}
                                        </span>
                                    @endif
                                    @if($prod?->barcode)
                                        <span>Barcode: {{ $prod->barcode }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Qty Beli -->
                            <td class="py-3 px-3 text-right tabular-nums">
                                <span class="font-bold text-ink-900 dark:text-white text-sm">
                                    {{ number_format($row->jumlah) }}
                                </span>
                                <span class="text-[11px] text-ink-500 dark:text-ink-400 ml-0.5">
                                    {{ $row->satuan ?: ($prod?->satuan ?: 'pcs') }}
                                </span>
                                @if((float)($row->konversi ?? 1) > 1)
                                    <div class="text-[9px] text-amber-500 font-mono">
                                        (= {{ (float)$row->jumlah * (float)$row->konversi }} {{ $prod?->satuan ?: 'pcs' }})
                                    </div>
                                @endif
                            </td>

                            <!-- Harga Beli Satuan & Indikator Perubahan Harga -->
                            <td class="py-3 px-3 text-right tabular-nums">
                                <div class="font-bold text-up-mint text-sm">
                                    Rp {{ number_format($row->harga_beli, 0, ',', '.') }}
                                </div>
                                @if(($row->tren_harga ?? '') === 'naik')
                                    <div class="text-[10px] text-red-500 font-bold flex items-center justify-end gap-0.5 mt-0.5">
                                        <span>▲ +{{ number_format($row->persen_perubahan, 1) }}%</span>
                                        <span class="text-ink-400 font-normal text-[9px]">(+Rp {{ number_format($row->selisih_harga, 0, ',', '.') }})</span>
                                    </div>
                                @elseif(($row->tren_harga ?? '') === 'turun')
                                    <div class="text-[10px] text-emerald-500 font-bold flex items-center justify-end gap-0.5 mt-0.5">
                                        <span>▼ {{ number_format($row->persen_perubahan, 1) }}%</span>
                                        <span class="text-ink-400 font-normal text-[9px]">(-Rp {{ number_format(abs($row->selisih_harga), 0, ',', '.') }})</span>
                                    </div>
                                @elseif(($row->tren_harga ?? '') === 'tetap')
                                    <div class="text-[10px] text-ink-400 flex items-center justify-end gap-0.5 mt-0.5">
                                        <span>▬ Stabil</span>
                                    </div>
                                @else
                                    <div class="text-[10px] text-ink-400 flex items-center justify-end gap-0.5 mt-0.5">
                                        <span>● Order Awal</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Subtotal -->
                            <td class="py-3 px-3 text-right font-bold text-ink-900 dark:text-white tabular-nums">
                                Rp {{ number_format($row->subtotal, 0, ',', '.') }}
                            </td>

                            <!-- Status Transaksi -->
                            <td class="py-3 px-3 text-center">
                                <div class="inline-flex flex-col items-center gap-1">
                                    <x-prism.status-pill :status="str_replace('_','-',$po?->status ?? 'draft')" size="xs" />
                                    @if($po?->grn)
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/25">
                                            GRN: {{ strtoupper($po->grn->status) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3 px-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="bukaDetailProduk({{ $row->produk_id }}, '{{ addslashes($prod?->nama ?? '') }}')"
                                        class="px-2.5 py-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/20 text-[10px] font-bold transition active:scale-95 cursor-pointer flex items-center gap-1"
                                        title="Buka grafik & riwayat seluruh pembelian untuk part ini"
                                    >
                                        <span>📈</span>
                                        <span>Histori Part</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-ink-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="text-3xl">🔍</span>
                                    <span class="text-xs font-semibold">Tidak ditemukan data riwayat pengadaan sesuai kriteria pencarian / filter.</span>
                                    <button
                                        type="button"
                                        wire:click="resetFilter"
                                        class="mt-1 px-3 py-1.5 rounded-xl bg-up-primary text-white text-xs font-bold cursor-pointer hover:bg-up-primary-dark transition"
                                    >
                                        Bersihkan Filter
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-t border-black/10 dark:border-white/10 bg-black/[0.01] dark:bg-white/[0.01]">
            {{ $items->links() }}
        </div>
    </div>

    <!-- MODAL: HISTORI PEMBELIAN PRODUK (PRICE TRACKER) -->
    @if($showProdukModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
            <div class="w-full max-w-4xl glass-panel p-5 sm:p-7 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <!-- Header Modal -->
                <div class="flex items-start sm:items-center justify-between pb-4 border-b border-white/10 gap-3 shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-white tracking-wide">
                                Histori Pengadaan Part: {{ $detailProdukNama }}
                            </h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/20 text-up-primary border border-up-primary/30 font-mono">
                                #{{ $detailProdukId }}
                            </span>
                        </div>
                        <p class="text-xs text-ink-400 mt-1">
                            Moving average, fluktuasi harga supplier, dan audit riwayat PO yang pernah dibuat untuk part ini.
                        </p>
                    </div>
                    <button wire:click="tutupDetailProduk" class="p-2 text-ink-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Statistik Ringkasan Part & Retur -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 my-4 shrink-0 text-xs">
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[11px] font-semibold text-ink-400 block">Harga Terakhir</span>
                        <span class="text-base sm:text-lg font-bold text-white tabular-nums">
                            Rp {{ number_format($historiStatistik['harga_terakhir'] ?? 0, 0, ',', '.') }}
                        </span>
                        <span class="text-[9px] text-ink-500 block mt-0.5">PO Terakhir</span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[11px] font-semibold text-up-mint block">Rata-rata (Weighted)</span>
                        <span class="text-base sm:text-lg font-bold text-up-mint tabular-nums">
                            Rp {{ number_format($historiStatistik['harga_rata_rata'] ?? 0, 0, ',', '.') }}
                        </span>
                        <span class="text-[9px] text-up-mint/80 block mt-0.5">Moving Average PO</span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[11px] font-semibold text-ink-400 block">Total Qty Dibeli</span>
                        <span class="text-base sm:text-lg font-bold text-white tabular-nums">
                            {{ number_format($historiStatistik['total_qty'] ?? 0) }} unit
                        </span>
                        <span class="text-[9px] text-ink-500 block mt-0.5">
                            Dari {{ $historiStatistik['total_transaksi'] ?? 0 }} PO
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[11px] font-semibold text-up-red block">Total Retur Part</span>
                        <span class="text-base sm:text-lg font-bold text-up-red tabular-nums">
                            {{ number_format($historiStatistik['total_retur_qty'] ?? 0) }} unit
                        </span>
                        <span class="text-[9px] text-ink-400 block mt-0.5">
                            Rp {{ number_format($historiStatistik['total_retur_nilai'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 col-span-2 sm:col-span-1">
                        <span class="text-[11px] font-semibold text-up-primary block">Net Pengadaan</span>
                        <span class="text-base sm:text-lg font-bold text-up-primary tabular-nums">
                            {{ number_format($historiStatistik['net_pengadaan_qty'] ?? 0) }} unit
                        </span>
                        <span class="text-[9px] text-ink-400 block mt-0.5">
                            Bersih: Rp {{ number_format($historiStatistik['net_pengadaan_nilai'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Tabel Riwayat Pembelian Produk -->
                <div class="overflow-y-auto flex-1 rounded-2xl border border-white/5 max-h-[45vh]">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-ink-950/95 backdrop-blur-sm z-10 border-b border-white/10">
                            <tr class="text-ink-400 font-semibold text-[11px] uppercase tracking-wider">
                                <th class="py-3 px-3.5">Tanggal</th>
                                <th class="py-3 px-3">No. PO</th>
                                <th class="py-3 px-3">Supplier</th>
                                <th class="py-3 px-3 text-right">Qty</th>
                                <th class="py-3 px-3 text-right">Harga Beli Satuan</th>
                                <th class="py-3 px-3 text-right">Subtotal</th>
                                <th class="py-3 px-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($historiProdukData as $h)
                                <tr class="hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3 px-3.5 text-ink-300 font-mono">{{ $h['tanggal'] }}</td>
                                    <td class="py-3 px-3 font-mono font-bold text-white">{{ $h['no_po'] }}</td>
                                    <td class="py-3 px-3 text-ink-200">
                                        <div class="font-semibold">{{ $h['supplier_nama'] }}</div>
                                        <div class="text-[10px] text-ink-500">{{ $h['supplier_telepon'] }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold text-white tabular-nums">{{ number_format($h['jumlah']) }}</td>
                                    <td class="py-3 px-3 text-right tabular-nums">
                                        <div class="font-bold text-up-mint">Rp {{ number_format($h['harga_beli'], 0, ',', '.') }}</div>
                                        @if(($h['trend'] ?? '') === 'naik')
                                            <div class="text-[10px] text-red-400 font-semibold flex items-center justify-end gap-0.5 mt-0.5">
                                                <span>▲ +{{ number_format($h['persentase'], 1) }}%</span>
                                                <span class="text-ink-500 text-[9px]">(+Rp {{ number_format($h['selisih'], 0, ',', '.') }})</span>
                                            </div>
                                        @elseif(($h['trend'] ?? '') === 'turun')
                                            <div class="text-[10px] text-emerald-400 font-semibold flex items-center justify-end gap-0.5 mt-0.5">
                                                <span>▼ {{ number_format($h['persentase'], 1) }}%</span>
                                                <span class="text-ink-500 text-[9px]">(-Rp {{ number_format(abs($h['selisih']), 0, ',', '.') }})</span>
                                            </div>
                                        @elseif(($h['trend'] ?? '') === 'tetap')
                                            <div class="text-[10px] text-ink-500 flex items-center justify-end gap-0.5 mt-0.5">
                                                <span>▬ Tetap</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right text-ink-200 tabular-nums">
                                        Rp {{ number_format($h['subtotal'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-3.5 text-center">
                                        <x-prism.status-pill :status="str_replace('_','-',$h['status'])" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-ink-400">
                                        Belum ada riwayat pembelian PO untuk produk ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer Modal -->
                <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-3 shrink-0">
                    <span class="text-[11px] text-ink-500">
                        Histori pembelian membantu verifikasi fluktuasi harga supplier sebelum order baru dibuat.
                    </span>
                    <button wire:click="tutupDetailProduk" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: DETAIL PURCHASE ORDER -->
    @if($showPoModal && $poDetail)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
            <div class="w-full max-w-3xl glass-panel p-5 sm:p-7 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <!-- Header -->
                <div class="flex items-start sm:items-center justify-between pb-4 border-b border-white/10 gap-3 shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-white font-mono tracking-wide">{{ $poDetail->no_po }}</h3>
                            <x-prism.status-pill :status="str_replace('_','-',$poDetail->status)" size="sm" />
                        </div>
                        <p class="text-xs text-ink-400 mt-1">
                            Supplier: <span class="text-white font-semibold">{{ $poDetail->supplier?->nama ?? '-' }}</span> · 
                            Gudang Tujuan: {{ $poDetail->gudangTujuan?->nama ?? '-' }} · 
                            Tanggal: {{ $poDetail->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <button wire:click="tutupDetailPo" class="p-2 text-ink-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Info Finansial & GRN -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-4 shrink-0">
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] text-ink-400 block">Total Nilai PO</span>
                        <span class="text-base font-bold text-white tabular-nums">Rp {{ number_format($poDetail->total, 0, ',', '.') }}</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] text-ink-400 block">Metode Bayar</span>
                        <span class="text-base font-bold text-white uppercase font-mono">{{ $poDetail->metode_bayar }}</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] text-ink-400 block">Status Pembayaran</span>
                        <span class="text-base font-bold {{ $poDetail->sisa <= 0 ? 'text-up-mint' : 'text-amber-400' }} tabular-nums">
                            {{ $poDetail->sisa <= 0 ? 'Lunas' : 'Sisa Rp '.number_format($poDetail->sisa, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] text-ink-400 block">Penerimaan GRN</span>
                        <span class="text-base font-bold text-white font-mono">
                            {{ $poDetail->grn ? $poDetail->grn->no_grn : 'Belum GRN' }}
                        </span>
                    </div>
                </div>

                <!-- Daftar Item PO -->
                <div class="overflow-y-auto flex-1 rounded-2xl border border-white/5 max-h-[45vh]">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-ink-950/95 backdrop-blur-sm z-10 border-b border-white/10">
                            <tr class="text-ink-400 font-semibold text-[11px] uppercase tracking-wider">
                                <th class="py-2.5 px-3">Produk</th>
                                <th class="py-2.5 px-3 text-right">Qty</th>
                                <th class="py-2.5 px-3 text-right">Harga Beli Satuan</th>
                                <th class="py-2.5 px-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($poDetail->items as $item)
                                <tr class="hover:bg-white/[0.02]">
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-white">{{ $item->produk?->nama ?? ('Produk #'.$item->produk_id) }}</div>
                                        <div class="text-[10px] text-ink-400 font-mono">SKU: {{ $item->produk?->sku ?? '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-right tabular-nums text-ink-200">
                                        {{ number_format($item->jumlah) }} {{ $item->satuan ?: ($item->produk?->satuan ?: 'pcs') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right tabular-nums font-bold text-up-mint">
                                        Rp {{ number_format($item->harga_beli, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right tabular-nums font-bold text-white">
                                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Footer Modal -->
                <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-3 shrink-0">
                    <span class="text-xs text-ink-400">
                        Catatan: {{ $poDetail->catatan ?: '-' }}
                    </span>
                    <button wire:click="tutupDetailPo" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
