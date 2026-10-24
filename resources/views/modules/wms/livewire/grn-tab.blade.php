<div class="space-y-5">
    {{-- Stepper Progress Alur Penerimaan Barang & Filter Terintegrasi --}}
    <div class="p-3.5 sm:p-4 rounded-2xl glass-panel bg-white/70 dark:bg-ink-950/70 border border-black/10 dark:border-white/10 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="text-sm font-extrabold text-ink-900 dark:text-white">Alur Progres Penerimaan (GRN)</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-mono font-semibold">Step-by-Step</span>
            </div>
            <span class="text-[11px] text-ink-500 dark:text-ink-400">PO Dikirim ➔ Input Fisik &amp; QC ➔ Approval ➔ Stok Masuk &amp; Jurnal AP</span>
        </div>

        {{-- Visual Stepper Buttons --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <!-- Step 1: PO Siap Diterima -->
            <button
                type="button"
                @click="document.getElementById('section-po-siap-terima')?.scrollIntoView({ behavior: 'smooth' })"
                class="p-2.5 rounded-xl border text-left transition active:scale-[0.98] cursor-pointer bg-white/50 dark:bg-white/5 hover:border-up-primary/40 {{ $countPoSiapTerima > 0 ? 'border-up-amber/40 bg-up-amber/5' : 'border-black/10 dark:border-white/10' }}"
            >
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-bold text-ink-400 uppercase">Step 1: Fisik Tiba</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold tabular-nums {{ $countPoSiapTerima > 0 ? 'bg-up-amber/20 text-up-amber' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">
                        {{ $countPoSiapTerima }}
                    </span>
                </div>
                <div class="text-xs font-bold text-ink-900 dark:text-white">PO Siap Terima</div>
                <div class="text-[9px] text-ink-500 dark:text-ink-400 mt-0.5">Input kuantitas &amp; rak</div>
            </button>

            <!-- Step 2: Menunggu Approval (Draft) -->
            <button
                type="button"
                wire:click="$set('filterStatus', 'draft')"
                class="p-2.5 rounded-xl border text-left transition active:scale-[0.98] cursor-pointer {{ $filterStatus === 'draft' ? 'bg-up-primary/15 border-up-primary text-white shadow-sm' : 'bg-white/50 dark:bg-white/5 hover:border-up-primary/40 border-black/10 dark:border-white/10' }}"
            >
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-bold text-ink-400 uppercase">Step 2: Verifikasi</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold tabular-nums {{ $countDraft > 0 ? 'bg-up-amber/20 text-up-amber' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">
                        {{ $countDraft }}
                    </span>
                </div>
                <div class="text-xs font-bold text-ink-900 dark:text-white">Menunggu Approval</div>
                <div class="text-[9px] text-ink-500 dark:text-ink-400 mt-0.5">Review QC &amp; nilai HPP</div>
            </button>

            <!-- Step 3: Diterima & Masuk Stok -->
            <button
                type="button"
                wire:click="$set('filterStatus', 'terima')"
                class="p-2.5 rounded-xl border text-left transition active:scale-[0.98] cursor-pointer {{ $filterStatus === 'terima' ? 'bg-up-mint/15 border-up-mint text-white shadow-sm' : 'bg-white/50 dark:bg-white/5 hover:border-up-mint/40 border-black/10 dark:border-white/10' }}"
            >
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-bold text-ink-400 uppercase">Step 3: Selesai</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold tabular-nums bg-up-mint/20 text-up-mint">
                        {{ $countTerima }}
                    </span>
                </div>
                <div class="text-xs font-bold text-ink-900 dark:text-white">Diterima (Stok Masuk)</div>
                <div class="text-[9px] text-ink-500 dark:text-ink-400 mt-0.5">Jurnal AP &amp; moving avg</div>
            </button>

            <!-- Step 4: Ditolak -->
            <button
                type="button"
                wire:click="$set('filterStatus', 'ditolak')"
                class="p-2.5 rounded-xl border text-left transition active:scale-[0.98] cursor-pointer {{ $filterStatus === 'ditolak' ? 'bg-up-red/15 border-up-red text-white shadow-sm' : 'bg-white/50 dark:bg-white/5 hover:border-up-red/40 border-black/10 dark:border-white/10' }}"
            >
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-bold text-ink-400 uppercase">Kendala</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold tabular-nums {{ $countDitolak > 0 ? 'bg-up-red/20 text-up-red' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">
                        {{ $countDitolak }}
                    </span>
                </div>
                <div class="text-xs font-bold text-ink-900 dark:text-white">GRN Ditolak</div>
                <div class="text-[9px] text-ink-500 dark:text-ink-400 mt-0.5">Stok &amp; utang batal</div>
            </button>
        </div>

        {{-- Filter tabs tambahan & tombol reset filter --}}
        <div class="flex items-center justify-between gap-2 pt-2 border-t border-black/5 dark:border-white/5 flex-wrap">
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none py-0.5 flex-nowrap sm:flex-wrap">
                <button
                    wire:click="$set('filterStatus', 'all')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'all' ? 'bg-up-primary text-white' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    Semua Dokumen ({{ $countTotal }})
                </button>
                <button
                    wire:click="$set('filterStatus', 'draft')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'draft' ? 'bg-up-primary text-white' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    Menunggu Approval ({{ $countDraft }})
                </button>
                <button
                    wire:click="$set('filterStatus', 'terima')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'terima' ? 'bg-up-primary text-white' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    Diterima ({{ $countTerima }})
                </button>
                <button
                    wire:click="$set('filterStatus', 'ditolak')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'ditolak' ? 'bg-up-primary text-white' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    Ditolak ({{ $countDitolak }})
                </button>
            </div>

            @if($filterStatus !== 'all')
                <button
                    wire:click="$set('filterStatus', 'all')"
                    class="text-[11px] text-ink-400 hover:text-ink-200 underline cursor-pointer"
                >
                    Reset Filter
                </button>
            @endif
        </div>
    </div>

    {{-- PO siap diterima — input GRN wajib sebelum stok masuk --}}
    <div id="section-po-siap-terima" class="rounded-2xl glass-panel p-4">
        <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                    <span>📦</span>
                    <span>PO Siap Diterima</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-accent/20 text-up-accent font-mono">
                        {{ $countPoSiapTerima }} PO
                    </span>
                </h3>
                <p class="text-[11px] text-ink-400 mt-0.5">PO berstatus dikirim. Lakukan input penerimaan fisik gudang agar stok dan jurnal AP tercatat.</p>
            </div>
        </div>
        <x-prism.data-table :headers="['No. PO', 'Supplier', 'Gudang Tujuan', 'Total', 'Dokumen / Aksi']">
            @forelse($poList as $po)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3 px-4 font-mono font-bold text-white">{{ $po->no_po }}</td>
                    <td class="py-3 px-4 text-ink-200">
                        <div class="font-medium text-white">{{ $po->supplier?->nama ?? '-' }}</div>
                        @if($po->supplier?->telepon)
                            <div class="text-[10px] text-ink-400 font-mono">{{ $po->supplier->telepon }}</div>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-ink-300">{{ $po->gudangTujuan?->nama ?? '-' }}</td>
                    <td class="py-3 px-4 tabular-nums font-bold text-white">Rp {{ number_format($po->total, 0, ',', '.') }}</td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-1.5 flex-wrap min-w-[200px]">
                            <button
                                wire:click="openGrnModal({{ $po->id }})"
                                class="px-2.5 py-1.5 rounded-lg bg-up-accent hover:opacity-90 text-white font-bold text-[11px] cursor-pointer min-h-[32px] flex items-center gap-1 active:scale-95 transition whitespace-nowrap shadow-sm shadow-up-accent/20"
                                title="Input GRN Penerimaan Fisik"
                            >
                                <span>📥</span>
                                <span>Terima</span>
                            </button>

                            <button
                                wire:click="bukaKonfirmasiTolakPo({{ $po->id }})"
                                class="px-2.5 py-1.5 rounded-lg bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-[11px] cursor-pointer min-h-[32px] flex items-center gap-1 active:scale-95 transition whitespace-nowrap"
                                title="Tolak PO &amp; Batalkan Pesanan di Pintu Gudang"
                            >
                                <span>🚫</span>
                                <span>Tolak PO</span>
                            </button>

                            <a
                                href="{{ route('wms.po.print', $po->id) }}"
                                target="_blank"
                                class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-[11px] font-bold flex items-center justify-center min-w-[32px] min-h-[32px] border border-white/5"
                                title="Cetak Dokumen Purchase Order Resmi (A4)"
                            >
                                🖨️
                            </a>

                            @if($po->supplier?->telepon)
                                <a
                                    href="{{ $po->generateWaPesananUrl() }}"
                                    target="_blank"
                                    class="p-1.5 rounded-lg bg-up-mint/15 hover:bg-up-mint/25 text-up-mint text-[11px] font-bold flex items-center justify-center min-w-[32px] min-h-[32px] border border-up-mint/30"
                                    title="Kirim Ringkasan Pesanan ke WA Supplier"
                                >
                                    💬
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-6 text-center text-ink-400 text-xs">Tidak ada PO menunggu penerimaan.</td>
                </tr>
            @endforelse
        </x-prism.data-table>
    </div>

    {{-- Daftar GRN --}}
    <div>
        <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
            <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                <span>📑</span>
                <span>Daftar Dokumen GRN</span>
                <span class="text-xs text-ink-400 font-normal">({{ $filterStatus === 'all' ? 'Semua Status' : 'Filter: '.strtoupper($filterStatus) }})</span>
            </h3>
        </div>
        <x-prism.data-table :headers="['No. GRN', 'PO', 'Supplier', 'Gudang', 'Total HPP', 'Status', 'Aksi']">
            @forelse($grnList as $grn)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $grn->no_grn }}</td>
                    <td class="py-3.5 px-4 text-ink-200">{{ $grn->purchaseOrder?->no_po }}</td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $grn->purchaseOrder?->supplier?->nama ?? '-' }}</td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $grn->gudang?->nama ?? '-' }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-white">Rp {{ number_format((float) $grn->total_hpp, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4">
                        @if($grn->status === 'draft')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/10 text-up-amber border border-up-amber/20">Menunggu Approval</span>
                        @elseif($grn->status === 'terima')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/10 text-up-mint border border-up-mint/20">Diterima</span>
                        @elseif($grn->status === 'ditolak')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/10 text-up-red border border-up-red/20">Ditolak</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-ink-300">{{ $grn->status }}</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4">
                        <div class="flex gap-1.5 flex-wrap min-w-[130px]">
                            @if($grn->status === 'draft')
                                @can('approve-workflow')
                                    <button
                                        wire:click="bukaKonfirmasiSetuju({{ $grn->id }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-[10px] cursor-pointer active:scale-95 transition"
                                        title="Setujui GRN (Posting Jurnal &amp; Stok Masuk)"
                                    >
                                        Setujui
                                    </button>
                                    <button
                                        wire:click="bukaKonfirmasiTolak({{ $grn->id }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-up-red/15 hover:bg-up-red/25 text-up-red font-bold text-[10px] border border-up-red/30 cursor-pointer active:scale-95 transition"
                                        title="Tolak GRN dengan alasan audit"
                                    >
                                        Tolak
                                    </button>
                                @endcan
                            @endif
                            <button
                                wire:click="bukaDetailGrn({{ $grn->id }})"
                                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[10px] cursor-pointer active:scale-95 transition"
                            >
                                Detail
                            </button>
                            <a
                                href="{{ route('wms.grn.print', $grn->id) }}"
                                target="_blank"
                                class="px-2.5 py-1.5 rounded-lg bg-up-primary/15 hover:bg-up-primary/25 border border-up-primary/30 text-up-primary font-bold text-[10px] cursor-pointer active:scale-95 transition"
                                title="Cetak Bukti Penerimaan Barang (Format A5)"
                            >
                                Cetak
                            </a>
                            <button
                                wire:click="bukaRiwayat('grn', {{ $grn->id }})"
                                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer active:scale-95 transition"
                            >
                                Riwayat
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-6 text-center text-ink-400 text-xs">Belum ada dokumen GRN untuk status ini.</td>
                </tr>
            @endforelse
        </x-prism.data-table>
        <div class="mt-3">{{ $grnList->links() }}</div>
    </div>

    {{-- MODAL: INPUT GRN [F2-2] --}}
    @if($showGrnModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto"
            x-data="{
                init() {
                    window.addEventListener('focus-grn-scan-input', () => {
                        $nextTick(() => { $refs.grnSearchInput?.focus(); });
                    });
                    window.addEventListener('focus-active-grn-qty', () => {
                        $nextTick(() => {
                            if ($refs.activeGrnQtyInput) {
                                $refs.activeGrnQtyInput.focus();
                                $refs.activeGrnQtyInput.select();
                            }
                        });
                    });
                }
            }"
        >
            <div class="w-full max-w-3xl glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-4 sm:p-6 rounded-3xl relative my-auto max-h-[92vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-black/10 dark:border-white/10 shrink-0">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white flex items-center gap-2">
                            <span>Input GRN (Penerimaan Barang)</span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary border border-up-primary/30">Penerimaan PO</span>
                        </h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Scan barcode atau ketik qty barang yang diterima dari supplier</p>
                    </div>
                    <button wire:click="cancelGrnModal" class="p-1.5 text-ink-400 hover:text-ink-900 dark:hover:text-white rounded-xl hover:bg-black/5 dark:hover:bg-white/5 transition cursor-pointer font-bold">✕</button>
                </div>

                <div class="overflow-y-auto flex-1 pr-1 space-y-3.5">
                    {{-- Barcode Scanner & Search Bar (Sama seperti Stock Opname) --}}
                    <div class="p-3.5 rounded-2xl bg-black/[0.02] dark:bg-white/[0.03] border border-black/10 dark:border-white/10 space-y-3">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-xs font-bold text-ink-700 dark:text-ink-200 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                <span>Pencarian Cepat & Scan Barcode Penerimaan PO</span>
                            </span>
                            <button
                                type="button"
                                wire:click="terimaSemuaItem"
                                class="px-2.5 py-1 rounded-lg bg-up-mint/20 hover:bg-up-mint/30 border border-up-mint/40 text-up-mint text-xs font-bold cursor-pointer flex items-center gap-1.5 transition active:scale-95 shadow-sm"
                                title="Set seluruh item PO ke kuantitas maksimal diterima"
                            >
                                <span>⚡ Terima Semua Lengkap (Max)</span>
                            </button>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    x-ref="grnSearchInput"
                                    wire:model.live.debounce.250ms="barcodeInput"
                                    wire:keydown.enter.prevent="prosesBarcode"
                                    placeholder="Scan barcode fisik atau ketik nama part / SKU PO..."
                                    class="w-full pl-9 pr-10 py-2.5 rounded-xl glass-input text-xs sm:text-sm text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10 placeholder-ink-400 min-h-[44px] focus:ring-1 focus:ring-up-primary"
                                    autofocus
                                />
                                @if(!empty($barcodeInput))
                                    <button
                                        type="button"
                                        wire:click="$set('barcodeInput', '')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-400 hover:text-white cursor-pointer"
                                    >
                                        ✕
                                    </button>
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="prosesBarcode"
                                class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs font-bold min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer transition active:scale-95 shrink-0 shadow-lg shadow-up-primary/20"
                            >
                                <span>Pilih / Hitung</span>
                            </button>

                            <!-- Tombol Kamera Scanner -->
                            <button
                                type="button"
                                x-data="{}"
                                @click="if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                                    window.uteBarcode.openCameraScanner({
                                        title: 'Scan Barcode Penerimaan (GRN)',
                                        continuous: true,
                                        onScan: (code) => { $wire.scanBarcodeDirect(code); }
                                    });
                                } else {
                                    alert('Modul scanner kamera sedang memuat...');
                                }"
                                class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-teal-300 text-xs font-bold min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer transition shrink-0 active:scale-95"
                                title="Buka Kamera HP untuk Scan Barcode"
                            >
                                <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                <span>Scan Kamera</span>
                            </button>
                        </div>
                    </div>

                    {{-- Panel Produk Aktif / Sedang Diterima (Alur: User Scan -> UI Menampilkan Produk & Input Stok -> Lanjut) --}}
                    @if($activeItemIndex !== null && isset($grnForm['item_qty'][$activeItemIndex]))
                        @php
                            $activeItem = $grnForm['item_qty'][$activeItemIndex];
                            $actPoQ = (int) ($activeItem['qty_po'] ?? 0);
                            $actRcvQ = (int) ($activeItem['qty_received'] ?? 0);
                        @endphp
                        <div class="p-4 sm:p-5 rounded-2xl bg-up-primary/[0.08] border border-up-primary/40 ring-1 ring-up-primary/30 space-y-3.5 transition animate-in fade-in zoom-in-95 duration-150">
                            <div class="flex items-start justify-between gap-3 pb-3 border-b border-up-primary/20">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-up-primary text-white uppercase tracking-wider animate-pulse">
                                            ● Produk Aktif Diterima
                                        </span>
                                        <span class="text-xs text-ink-400 font-mono">#{{ $activeItemIndex + 1 }}</span>
                                    </div>
                                    <h4 class="text-sm sm:text-base font-bold text-white mt-1.5">{{ $activeItem['produk_nama'] }}</h4>
                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-ink-300 font-mono mt-1">
                                        <span class="px-2 py-0.5 rounded bg-white/10 text-up-mint font-bold">PO: {{ $actPoQ }} {{ $activeItem['satuan'] ?? 'pcs' }}</span>
                                        @if(!empty($activeItem['sku']))
                                            <span class="px-2 py-0.5 rounded bg-white/10 text-teal-300 border border-teal-400/20">SKU: {{ $activeItem['sku'] }}</span>
                                        @endif
                                        @if(!empty($activeItem['barcode']))
                                            <span class="px-2 py-0.5 rounded bg-white/10 text-ink-200">Barcode: {{ $activeItem['barcode'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    wire:click="lewatiActiveGrn"
                                    class="p-1 text-ink-400 hover:text-white rounded-lg hover:bg-white/10 transition cursor-pointer"
                                    title="Tutup / Ganti Produk"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Input Jumlah Diterima Aktual & Lokasi Rak -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <span class="text-xs text-ink-300 font-semibold block mb-1">Lokasi Rak Simpan:</span>
                                    <select wire:model="grnForm.item_qty.{{ $activeItemIndex }}.rak_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono min-h-[40px]">
                                        <option value="">— Tanpa Rak —</option>
                                        @foreach($gudangRaks as $r)
                                            <option value="{{ $r['id'] }}" class="bg-ink-900">{{ $r['nama'] }} ({{ $r['kode'] }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <span class="text-xs text-ink-300 font-semibold block mb-1">Qty Diterima Aktual (Maks: {{ $actPoQ }}):</span>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center bg-ink-950/80 border border-up-primary/40 rounded-xl p-0.5 shadow-inner">
                                            <button
                                                type="button"
                                                wire:click="kurangActiveQtyGrn"
                                                class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/15 text-white flex items-center justify-center font-bold text-base cursor-pointer active:scale-95 transition"
                                            >-</button>
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $actPoQ }}"
                                                x-ref="activeGrnQtyInput"
                                                wire:model.live="activeItemQty"
                                                wire:keydown.enter.prevent="simpanDanLanjutGrn"
                                                placeholder="0"
                                                class="w-20 sm:w-24 px-2 py-1 bg-transparent text-center text-lg font-extrabold text-white tabular-nums focus:outline-none"
                                            />
                                            <button
                                                type="button"
                                                wire:click="tambahActiveQtyGrn"
                                                class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/15 text-white flex items-center justify-center font-bold text-base cursor-pointer active:scale-95 transition"
                                            >+</button>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="setActiveItemLengkap"
                                            class="px-3 py-2 rounded-xl bg-up-mint/20 hover:bg-up-mint/30 text-up-mint font-bold text-xs min-h-[40px] cursor-pointer transition active:scale-95"
                                        >
                                            Full
                                        </button>
                                        <span class="text-xs text-ink-400 font-medium">{{ $activeItem['satuan'] ?? 'pcs' }}</span>
                                    </div>
                                </div>
                            </div>

                            @if($activeItem['sn_flag'] ?? false)
                                <div class="pt-2 border-t border-white/10">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-bold text-up-accent uppercase flex items-center gap-1">
                                            <span>🔢</span> Nomor Seri (Wajib diisi sesuai qty diterima)
                                        </span>
                                        <span class="text-[10px] text-ink-400 tabular-nums">Jumlah SN: {{ count(array_filter(preg_split('/[\r\n,]+/', (string) ($activeItem['sn'] ?? '')))) }} / {{ $actRcvQ }}</span>
                                    </div>
                                    <textarea
                                        wire:model="grnForm.item_qty.{{ $activeItemIndex }}.sn"
                                        rows="2"
                                        placeholder="Scan atau tempel serial number (pisahkan per baris atau koma)"
                                        class="w-full px-3 py-1.5 rounded-lg glass-input text-xs font-mono"
                                    ></textarea>
                                </div>
                            @endif

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-up-primary/20">
                                <button
                                    type="button"
                                    wire:click="lewatiActiveGrn"
                                    class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-ink-300 text-xs font-semibold cursor-pointer transition"
                                >
                                    Lewati
                                </button>
                                <button
                                    type="button"
                                    wire:click="simpanDanLanjutGrn"
                                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-extrabold text-xs flex items-center justify-center gap-1.5 cursor-pointer transition active:scale-95 shadow-lg shadow-up-primary/25"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>Simpan & Lanjut Scan (Enter)</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between shrink-0 pt-1">
                        <span class="text-xs font-bold text-ink-200 uppercase tracking-wide flex items-center gap-2">
                            <span>Daftar Item PO</span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-white/5 text-ink-300">
                                {{ collect($grnForm['item_qty'])->filter(fn($i) => (int)($i['qty_received'] ?? 0) >= (int)($i['qty_po'] ?? 0))->count() }} / {{ count($grnForm['item_qty']) }} item lengkap
                            </span>
                        </span>
                        <span class="text-[10px] text-ink-400">Qty diterima wajib ≤ qty PO</span>
                    </div>

                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        @foreach($grnForm['item_qty'] as $idx => $item)
                            @php
                                $poQ = (int) ($item['qty_po'] ?? 0);
                                $rcvQ = (int) ($item['qty_received'] ?? 0);
                                $isFull = $rcvQ >= $poQ && $poQ > 0;
                                $isPartial = $rcvQ > 0 && $rcvQ < $poQ;
                                $isActive = ($activeItemIndex === $idx);
                            @endphp
                            <div class="p-3 rounded-2xl border transition {{ $isActive ? 'border-up-primary/60 bg-up-primary/10 shadow-lg shadow-up-primary/10' : ($isFull ? 'bg-up-mint/5 border-up-mint/20' : ($isPartial ? 'bg-up-amber/5 border-up-amber/20' : 'bg-white/[0.02] border-white/5')) }} space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-white flex items-center gap-1.5 flex-wrap">
                                            <button
                                                type="button"
                                                wire:click="pilihItemGrn({{ $idx }})"
                                                class="px-2 py-0.5 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary text-[10px] font-bold cursor-pointer transition mr-1"
                                                title="Fokuskan untuk scan / edit"
                                            >
                                                Pilih
                                            </button>
                                            <span>{{ $item['produk_nama'] ?? ('#'.$item['produk_id']) }}</span>
                                            @if($isActive)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-up-primary text-white">Aktif</span>
                                            @endif
                                            @if($isFull)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-up-mint/20 text-up-mint border border-up-mint/30">Lengkap</span>
                                            @elseif($isPartial)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-up-amber/20 text-up-amber border border-up-amber/30">Parsial</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-ink-400 font-mono mt-0.5 flex gap-2 flex-wrap">
                                            <span>PO: <b class="text-ink-200">{{ $poQ }}</b> {{ $item['satuan'] ?? 'pcs' }}</span>
                                            @if((float)($item['konversi'] ?? 1) > 1)
                                                <span class="text-amber-300">(= {{ $poQ * (float)$item['konversi'] }} {{ $item['satuan_dasar'] ?? 'pcs' }})</span>
                                            @endif
                                            @if(!empty($item['sku'])) <span>SKU: {{ $item['sku'] }}</span> @endif
                                            @if(!empty($item['barcode'])) <span>Barcode: {{ $item['barcode'] }}</span> @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 flex-wrap">
                                        @cansee('harga_beli')
                                        <div class="w-24 sm:w-28">
                                            <div class="text-[10px] text-ink-400 mb-0.5">Harga Beli</div>
                                            <input
                                                type="number"
                                                min="0"
                                                step="100"
                                                wire:model="grnForm.item_qty.{{ $idx }}.harga_beli"
                                                class="w-full px-2 py-1.5 rounded-lg glass-input text-xs tabular-nums text-right font-medium"
                                                placeholder="Rp 0"
                                            />
                                        </div>
                                        @endcansee

                                        <!-- Lokasi Rak Dropdown -->
                                        <div class="w-32 sm:w-36">
                                            <div class="text-[10px] text-ink-400 mb-0.5">Lokasi Rak</div>
                                            <select wire:model="grnForm.item_qty.{{ $idx }}.rak_id" class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-mono">
                                                <option value="">— Tanpa Rak —</option>
                                                @foreach($gudangRaks as $r)
                                                    <option value="{{ $r['id'] }}" class="bg-ink-900">{{ $r['nama'] }} ({{ $r['kode'] }})</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Qty Received & Quick Buttons -->
                                        <div>
                                            <div class="text-[10px] text-ink-400 mb-0.5">Diterima ({{ $item['satuan'] ?? 'pcs' }})</div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" wire:click="tambahQtyItem({{ $idx }}, -1)" class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 font-bold text-xs flex items-center justify-center cursor-pointer active:scale-95">-</button>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $poQ }}"
                                                    wire:model.live="grnForm.item_qty.{{ $idx }}.qty_received"
                                                    class="w-16 px-1.5 py-1 rounded-lg glass-input text-xs tabular-nums text-center font-bold {{ $isFull ? 'text-up-mint' : 'text-white' }}"
                                                />
                                                <button type="button" wire:click="tambahQtyItem({{ $idx }}, 1)" class="w-7 h-7 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary font-bold text-xs flex items-center justify-center cursor-pointer active:scale-95">+</button>
                                                <button type="button" wire:click="setItemLengkap({{ $idx }})" class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-[10px] font-semibold text-ink-300 cursor-pointer" title="Set Sesuai Qty PO">Full</button>
                                            </div>
                                            @if((float)($item['konversi'] ?? 1) > 1 && $rcvQ > 0)
                                                <div class="text-[9px] text-up-mint mt-0.5 text-center font-medium">
                                                    = {{ $rcvQ * (float)$item['konversi'] }} {{ $item['satuan_dasar'] ?? 'pcs' }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- [F2-3] Input SN utk produk sn=true — jumlah wajib = qty diterima --}}
                                @if($item['sn_flag'] ?? false)
                                    <div class="pt-2 border-t border-white/5">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-[10px] font-bold text-up-accent uppercase flex items-center gap-1">
                                                <span>🔢</span> Nomor Seri (Wajib diisi sesuai qty diterima)
                                            </span>
                                            <span class="text-[10px] text-ink-400 tabular-nums">Jumlah SN: {{ count(array_filter(preg_split('/[\r\n,]+/', (string) ($item['sn'] ?? '')))) }} / {{ $rcvQ }}</span>
                                        </div>
                                        <textarea
                                            wire:model="grnForm.item_qty.{{ $idx }}.sn"
                                            rows="2"
                                            placeholder="Scan atau tempel serial number (pisahkan per baris atau koma)"
                                            class="w-full px-3 py-1.5 rounded-lg glass-input text-xs font-mono"
                                        ></textarea>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if(!empty($grnForm['is_cash']))
                        <div class="p-3.5 rounded-2xl bg-up-mint/5 border border-up-mint/20 space-y-2.5">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                                <span class="text-xs font-bold text-up-mint uppercase tracking-wider">Konfirmasi Pembayaran PO Tunai (Cash / COD / Transfer)</span>
                            </div>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 leading-relaxed">
                                PO ini menggunakan metode <strong class="text-up-mint">Tunai Langsung</strong> (tanpa jatuh tempo). Konfirmasikan siapa yang membayar dan kas/bank yang digunakan saat barang diterima.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Petugas Pembayar (Otomatis Akun Aktif)</label>
                                    <div class="px-3 py-2 rounded-xl bg-black/[0.04] dark:bg-white/[0.05] border border-black/10 dark:border-white/10 flex items-center justify-between min-h-[40px]">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-up-mint"></span>
                                            <span class="font-bold text-xs text-ink-900 dark:text-white">{{ auth()->user()?->name ?? 'User Aktif' }}</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-up-primary uppercase tracking-wider px-2 py-0.5 rounded-md bg-up-primary/10 border border-up-primary/20">
                                            {{ auth()->user()?->roles->pluck('name')->map(fn($r) => ucfirst(str_replace('-', ' ', $r)))->implode(', ') ?: 'Staff' }}
                                        </span>
                                    </div>
                                    @php $bolehKas = \App\Modules\Wms\Services\GrnService::userBolehOtorisasiKas(auth()->user()); @endphp
                                    @if($bolehKas)
                                        <p class="text-[10px] text-up-mint mt-1 flex items-center gap-1 font-medium">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Hak akses otorisasi pembayaran kas terverifikasi.
                                        </p>
                                    @else
                                        <p class="text-[10px] text-up-red mt-1 flex items-center gap-1 font-semibold">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Akun Anda tidak memiliki role/izin pengeluaran kas (butuh role Kasir, Finance, atau Admin Toko).
                                        </p>
                                    @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Sumber Kas / Bank *</label>
                                    <select wire:model="grnForm.akun_kas_bank" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[40px]" required>
                                        @foreach($akunKasBankList ?? [] as $ak)
                                            <option value="{{ $ak->kode }}" class="bg-ink-900 text-white">[{{ $ak->kode }}] {{ $ak->nama }} ({{ ucfirst($ak->kelompok) }})</option>
                                        @endforeach
                                    </select>
                                    <p class="text-[10px] text-ink-500 mt-1">Pilih kas laci kasir atau rekening bank tempat uang keluar.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="pt-1 shrink-0">
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Penerimaan</label>
                        <textarea wire:model="grnForm.catatan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs" placeholder="Opsional — misal nomor surat jalan, kondisi kemasan, atau catatan selisih"></textarea>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="flex gap-3 pt-3 mt-3 border-t border-black/10 dark:border-white/10 shrink-0">
                    <button wire:click="cancelGrnModal" class="flex-1 py-3 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.97]">Batal</button>
                    @php $tombolDisabled = !empty($grnForm['is_cash']) && ! \App\Modules\Wms\Services\GrnService::userBolehOtorisasiKas(auth()->user()); @endphp
                    <button
                        wire:click="simpanGrn"
                        @if($tombolDisabled) disabled title="Akun Anda tidak memiliki role/izin untuk otorisasi pembayaran kas" @endif
                        class="flex-1 py-3 rounded-xl {{ $tombolDisabled ? 'bg-ink-700 opacity-50 cursor-not-allowed text-ink-300' : 'bg-up-primary hover:bg-up-primary-dark text-white cursor-pointer shadow-lg shadow-up-primary/25 active:scale-[0.97]' }} font-bold text-xs min-h-[44px] transition"
                    >
                        {{ $tombolDisabled ? 'Izin Kas Tidak Memadai' : 'Simpan GRN' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: DETAIL GRN --}}
    @if($detailGrn)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
            <div class="w-full max-w-2xl glass-panel p-5 sm:p-7 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <!-- Header -->
                <div class="flex items-start sm:items-center justify-between pb-4 border-b border-white/10 gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-white font-mono tracking-wide">{{ $detailGrn->no_grn }}</h3>
                            <x-prism.status-pill :status="str_replace('_','-',$detailGrn->status)" size="sm" />
                        </div>
                        <p class="text-xs text-ink-400 mt-1">
                            PO: <span class="font-mono font-bold text-white">{{ $detailGrn->purchaseOrder?->no_po ?? '-' }}</span> · 
                            Penerima: {{ $detailGrn->user?->name ?? '-' }} · 
                            Tanggal: {{ $detailGrn->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <button wire:click="tutupDetailGrn" class="p-2 text-ink-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Summary Info -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 my-4 text-xs">
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5 space-y-1">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Supplier</span>
                        <div class="font-bold text-white">{{ $detailGrn->purchaseOrder?->supplier?->nama ?? '-' }}</div>
                        <div class="text-ink-400 text-[11px]">{{ $detailGrn->purchaseOrder?->supplier?->telepon ?? '-' }}</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5 space-y-1">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Gudang & Total HPP</span>
                        <div class="font-bold text-white">{{ $detailGrn->gudang?->nama ?? '-' }}</div>
                        <div class="text-up-mint font-bold tabular-nums">
                            @cansee('harga_beli')
                                Total HPP: Rp {{ number_format((float) $detailGrn->total_hpp, 0, ',', '.') }}
                            @cannotsee('harga_beli')
                                Total HPP: —
                            @endcansee
                        </div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5 space-y-1">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Metode & Pembayaran</span>
                        <div class="font-bold {{ $detailGrn->purchaseOrder?->metode_bayar === 'tunai' ? 'text-up-mint' : 'text-up-amber' }}">
                            {{ $detailGrn->purchaseOrder?->metode_bayar === 'tunai' ? 'TUNAI LANGSUNG' : 'KREDIT / TEMPO' }}
                        </div>
                        @if($detailGrn->purchaseOrder?->metode_bayar === 'tunai')
                            <div class="text-[11px] text-ink-300">
                                Kas: <span class="font-mono text-white">{{ $detailGrn->akun_kas_bank ?: ($detailGrn->purchaseOrder?->akun_kas_bank ?: '110-01') }}</span>
                                @if($detailGrn->dibayar_oleh)
                                    · Oleh: <span class="text-white">{{ $detailGrn->dibayar_oleh }}</span>
                                @endif
                            </div>
                        @else
                            <div class="text-[11px] text-ink-400">
                                Tempo: {{ $detailGrn->purchaseOrder?->jatuh_tempo?->format('d/m/Y') ?? '-' }}
                            </div>
                        @endif
                    </div>
                </div>

                @if($detailGrn->catatan)
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs text-ink-300 mb-4">
                        <span class="text-[10px] text-ink-400 block font-semibold mb-0.5">Catatan:</span>
                        {{ $detailGrn->catatan }}
                    </div>
                @endif

                <!-- Items Table -->
                <div class="space-y-2 mb-4 flex-1 overflow-y-auto">
                    <h4 class="text-xs font-bold text-ink-300 uppercase tracking-wider">Item Diterima</h4>
                    <div class="rounded-xl border border-white/5 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-ink-400 font-semibold border-b border-white/5">
                                <tr>
                                    <th class="py-2 px-3">Produk</th>
                                    <th class="py-2 px-3 text-center">Lokasi Rak</th>
                                    <th class="py-2 px-3 text-center">Qty PO</th>
                                    <th class="py-2 px-3 text-center">Qty Diterima</th>
                                    <th class="py-2 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($detailGrn->item_qty_received ?? [] as $item)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-2 px-3">
                                            <div class="font-bold text-white">{{ $item['produk_nama'] ?? ('Produk #'.$item['produk_id']) }}</div>
                                            @cansee('harga_beli')
                                                <div class="text-[10px] text-ink-400 tabular-nums">@ Rp {{ number_format((float) ($item['harga_beli'] ?? 0), 0, ',', '.') }}</div>
                                            @endcansee
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            @if(!empty($item['rak_id']))
                                                @php $rk = \App\Modules\Wms\Models\Rak::find($item['rak_id']); @endphp
                                                <span class="px-2 py-0.5 rounded-md bg-up-primary/10 text-up-primary border border-up-primary/20 font-mono text-[10px] font-bold">
                                                    🏷️ {{ $rk ? $rk->nama . ' (' . $rk->kode . ')' : '#' . $item['rak_id'] }}
                                                </span>
                                            @else
                                                <span class="text-ink-500 font-mono text-[11px]">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-center tabular-nums text-ink-300">
                                            {{ $item['qty_po'] ?? '-' }} {{ $item['satuan'] ?? 'pcs' }}
                                            @if((float)($item['konversi'] ?? 1) > 1)
                                                <div class="text-[10px] text-ink-500">({{ ((float)($item['qty_po'] ?? 0)) * (float)$item['konversi'] }} pcs)</div>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-center tabular-nums font-bold text-white">
                                            {{ $item['qty_received'] ?? 0 }} {{ $item['satuan'] ?? 'pcs' }}
                                            @if((float)($item['konversi'] ?? 1) > 1)
                                                <div class="text-[10px] text-up-mint">({{ ((float)($item['qty_received'] ?? 0)) * (float)$item['konversi'] }} pcs)</div>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ ($item['status'] ?? '') === 'lengkap' ? 'bg-up-mint/10 text-up-mint' : 'bg-up-amber/10 text-up-amber' }}">
                                                {{ $item['status'] ?? 'selisih' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-2 gap-3">
                    <a href="{{ route('wms.grn.print', $detailGrn->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer shadow-lg shadow-up-primary/25 transition active:scale-[0.97]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak Bukti GRN (A5)
                    </a>
                    <button wire:click="tutupDetailGrn" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: KONFIRMASI SETUJUI GRN [F2-2] --}}
    @if($showConfirmDialog)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $confirmTitle }}</h3>
                    <button wire:click="tutupKonfirmasi" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>
                <p class="text-xs text-ink-300 leading-relaxed mb-5">{{ $confirmText }}</p>
                <div class="flex gap-3">
                    <button wire:click="tutupKonfirmasi" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="setujuiGrn({{ $confirmGrnId }})" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">Ya, Setujui</button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: DIALOG TOLAK GRN --}}
    @if($showTolakDialog)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 overflow-y-auto">
            <div class="w-full max-w-md glass-panel p-5 sm:p-6 rounded-3xl relative my-auto border border-up-red/30 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🚫</span>
                        <h3 class="text-base font-bold text-white">Tolak GRN: {{ $tolakGrnNo }}</h3>
                    </div>
                    <button wire:click="tutupKonfirmasiTolak" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                {{-- Penjelasan Alur Penolakan --}}
                <div class="p-3 rounded-2xl bg-up-red/10 border border-up-red/20 text-xs text-ink-200 space-y-1.5 leading-relaxed">
                    <div class="font-bold text-up-red flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Alur &amp; Konsekuensi Penolakan:</span>
                    </div>
                    <ul class="list-disc pl-4 space-y-1 text-[11px] text-ink-300">
                        <li>Dokumen GRN akan berstatus <strong>Ditolak</strong> dan permintaan approval diselesaikan.</li>
                        <li>Stok fisik <strong>TIDAK akan masuk</strong> ke inventori gudang.</li>
                        <li>Jurnal Akuntansi Utang Usaha (AP) <strong>TIDAK akan diposting</strong>.</li>
                        <li>PO terkait tetap tercatat dan dapat diproses ulang atau ditindaklanjuti retur/pembatalan.</li>
                    </ul>
                </div>

                {{-- Input Alasan --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-ink-200">
                        Alasan Penolakan <span class="text-up-red">*</span>
                    </label>
                    
                    {{-- Quick template reasons --}}
                    <div class="flex flex-wrap gap-1 mb-1.5">
                        <button
                            type="button"
                            wire:click="$set('alasanTolak', 'Barang rusak / pecah fisik saat tiba di gudang')"
                            class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 cursor-pointer"
                        >
                            + Barang rusak/pecah
                        </button>
                        <button
                            type="button"
                            wire:click="$set('alasanTolak', 'Kuantitas fisik tidak sesuai surat jalan supplier')"
                            class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 cursor-pointer"
                        >
                            + Qty selisih
                        </button>
                        <button
                            type="button"
                            wire:click="$set('alasanTolak', 'Spesifikasi part salah / tidak sesuai PO')"
                            class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 cursor-pointer"
                        >
                            + Salah part
                        </button>
                    </div>

                    <textarea
                        wire:model="alasanTolak"
                        rows="3"
                        class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white placeholder-ink-400 border border-white/10 focus:ring-1 focus:ring-up-red"
                        placeholder="Tuliskan catatan rinci alasan penolakan untuk audit trail..."
                    ></textarea>
                    @error('alasanTolak')
                        <span class="text-[11px] text-up-red font-medium block">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-2 border-t border-white/10">
                    <button
                        type="button"
                        wire:click="tutupKonfirmasiTolak"
                        class="flex-1 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.98]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="konfirmasiTolakGrn"
                        class="flex-1 py-2.5 rounded-xl bg-up-red hover:bg-up-red/90 text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-red/25 transition active:scale-[0.98] flex items-center justify-center gap-1.5"
                    >
                        <span>🚫</span>
                        <span>Konfirmasi Tolak</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: DIALOG TOLAK PO DI PINTU GUDANG --}}
    @if($showTolakPoDialog)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 overflow-y-auto">
            <div class="w-full max-w-md glass-panel p-5 sm:p-6 rounded-3xl relative my-auto border border-up-red/30 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🚫</span>
                        <h3 class="text-base font-bold text-white">Tolak PO: {{ $tolakPoNo }}</h3>
                    </div>
                    <button wire:click="tutupKonfirmasiTolakPo" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                {{-- Penjelasan Alur Penolakan PO --}}
                <div class="p-3 rounded-2xl bg-up-red/10 border border-up-red/20 text-xs text-ink-200 space-y-1.5 leading-relaxed">
                    <div class="font-bold text-up-red flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Konsekuensi Penolakan di Pintu Gudang:</span>
                    </div>
                    <ul class="list-disc pl-4 space-y-1 text-[11px] text-ink-300">
                        <li>Supplier: <strong>{{ $tolakPoSupplier ?? '-' }}</strong>.</li>
                        <li>Status PO diubah menjadi <strong>Dibatalkan</strong>.</li>
                        <li>Barang tidak di-input ke GRN &amp; stok fisik <strong>TIDAK bertambah</strong>.</li>
                        <li>Tidak ada Utang Usaha (AP) yang diakui di pembukuan.</li>
                        <li>Setelah ditolak, Berita Acara Penolakan / Kontra Bon Penolakan dapat dicetak atau dikirim via WhatsApp ke supplier.</li>
                    </ul>
                </div>

                {{-- Input Alasan --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-ink-200">
                        Alasan Penolakan PO <span class="text-up-red">*</span>
                    </label>

                    {{-- Quick template reasons --}}
                    <div class="flex flex-wrap gap-1 mb-1.5">
                        <button
                            type="button"
                            wire:click="$set('alasanTolakPo', 'Fisik barang hancur / cacat pabrik di pintu gudang')"
                            class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 cursor-pointer"
                        >
                            + Barang cacat/rusak
                        </button>
                        <button
                            type="button"
                            wire:click="$set('alasanTolakPo', 'Barang yang tiba tidak sesuai tipe/pesanan PO')"
                            class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 cursor-pointer"
                        >
                            + Salah kirim tipe
                        </button>
                        <button
                            type="button"
                            wire:click="$set('alasanTolakPo', 'Pengiriman melewati batas toleransi jatuh tempo supplier')"
                            class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 cursor-pointer"
                        >
                            + Terlambat / dibatalkan
                        </button>
                    </div>

                    <textarea
                        wire:model="alasanTolakPo"
                        rows="3"
                        class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white placeholder-ink-400 border border-white/10 focus:ring-1 focus:ring-up-red"
                        placeholder="Tuliskan catatan rinci mengapa barang ditolak di pintu gudang..."
                    ></textarea>
                    @error('alasanTolakPo')
                        <span class="text-[11px] text-up-red font-medium block">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-2 border-t border-white/10">
                    <button
                        type="button"
                        wire:click="tutupKonfirmasiTolakPo"
                        class="flex-1 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.98]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="konfirmasiTolakPo"
                        class="flex-1 py-2.5 rounded-xl bg-up-red hover:bg-up-red/90 text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-red/25 transition active:scale-[0.98] flex items-center justify-center gap-1.5"
                    >
                        <span>🚫</span>
                        <span>Tolak &amp; Batalkan PO</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per GRN --}}
    @include('partials.riwayat-modal')
</div>
