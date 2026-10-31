<div class="space-y-5">
    {{-- Sub Navigation: Riwayat Retur vs Pool Karantina Supplier --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 p-1 rounded-2xl bg-white/5 border border-white/10 w-full sm:w-auto">
            <button
                type="button"
                wire:click="$set('subTab', 'riwayat')"
                class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center justify-center gap-2 {{ $subTab === 'riwayat' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white' }} min-h-[40px]"
            >
                <span>📦</span>
                <span>Riwayat Retur Pembelian</span>
            </button>
            <button
                type="button"
                wire:click="$set('subTab', 'karantina')"
                class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center justify-center gap-2 {{ $subTab === 'karantina' ? 'bg-up-red text-white shadow-md shadow-up-red/25' : 'text-ink-400 hover:text-white' }} min-h-[40px]"
            >
                <span>⚠️</span>
                <span>Pool Karantina ({{ $karantinaItems->count() }} item)</span>
            </button>
        </div>

        @if($subTab === 'riwayat')
            @can('wms.create')
                <button
                    wire:click="bukaModalTambah"
                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-red/90 hover:bg-up-red text-white font-bold text-xs shadow-md shadow-up-red/20 transition active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                >
                    <span>+ Buat Retur Pembelian (PO)</span>
                </button>
            @endcan
        @endif
    </div>

    {{-- TAB 1: RIWAYAT RETUR PEMBELIAN --}}
    @if($subTab === 'riwayat')
        <div class="space-y-4">
            {{-- Filter Status --}}
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 flex-nowrap sm:flex-wrap">
                @foreach (['all' => 'Semua', 'draft' => '1. Draft (Menunggu Kirim)', 'dikirim' => '2. Dikirim (In-Transit)', 'selesai' => '3. Selesai (Diterima / Saldo)', 'ditolak' => 'Ditolak'] as $val => $label)
                    <button
                        wire:click="$set('filterStatus', '{{ $val }}')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] cursor-pointer active:scale-[0.97] whitespace-nowrap min-h-[44px] flex items-center {{ $filterStatus === $val ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Tabel Retur Pembelian --}}
            <x-prism.data-table :headers="['No. Retur', 'No. PO / Asal', 'Supplier', 'Tanggal', 'Metode', 'Total Nilai', 'Status', 'Aksi']">
                @forelse($returList as $retur)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $retur->no_return }}</td>
                        <td class="py-3.5 px-4 font-mono text-ink-200">
                            <div>{{ $retur->purchaseOrder?->no_po ?? 'Pool Karantina' }}</div>
                            @if($retur->no_resi)
                                <div class="text-[10px] text-ink-400">Resi: {{ $retur->no_resi }}</div>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            @if($retur->supplier_id)
                                <button
                                    type="button"
                                    wire:click="bukaProfilSupplier({{ $retur->supplier_id }})"
                                    class="text-up-mint hover:underline font-bold text-left cursor-pointer flex items-center gap-1"
                                    title="Lihat Profil & Riwayat Supplier"
                                >
                                    <span>{{ $retur->supplier?->nama ?? '-' }}</span>
                                    <span class="text-[10px] text-ink-400">↗</span>
                                </button>
                            @else
                                <span class="text-ink-400">-</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $retur->tanggal?->format('d/m/Y') ?? '-' }}</td>
                        <td class="py-3.5 px-4 uppercase text-[10px] font-bold text-ink-300">{{ $retur->metode_pengembalian }}</td>
                        <td class="py-3.5 px-4 tabular-nums font-bold text-up-red">Rp {{ number_format($retur->jumlah, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-4">
                            <x-prism.status-pill :status="str_replace('_','-',$retur->status)" />
                            @if($retur->status === 'dikirim')
                                <div class="text-[9px] text-up-amber font-semibold mt-0.5">In-Transit Vendor</div>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 flex-wrap min-w-[140px]">
                                @if($retur->status === 'draft')
                                    <button
                                        wire:click="bukaDetail({{ $retur->id }})"
                                        class="px-2 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary border border-up-primary/30 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]"
                                        title="Kirim ke Supplier"
                                    >
                                        🚀 Kirim
                                    </button>
                                @elseif($retur->status === 'dikirim')
                                    <button
                                        wire:click="bukaDetail({{ $retur->id }})"
                                        class="px-2 py-1 rounded-lg bg-up-mint/20 hover:bg-up-mint/30 text-up-mint border border-up-mint/30 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]"
                                        title="Proses Hasil Terima Vendor"
                                    >
                                        📥 Terima Hasil
                                    </button>
                                @endif
                                <button
                                    wire:click="bukaDetail({{ $retur->id }})"
                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]"
                                >
                                    Detail
                                </button>
                                <a
                                    href="{{ route('wms.retur-pembelian.print', $retur->id) }}"
                                    target="_blank"
                                    class="px-2 py-1.5 rounded-lg bg-up-red/15 hover:bg-up-red/25 border border-up-red/30 text-up-red font-bold text-[10px] whitespace-nowrap active:scale-[0.97]"
                                    title="Cetak Surat Pengantar Retur"
                                >
                                    🖨️
                                </a>
                                @can('lihat-audit-log')
                                    <button
                                        wire:click="bukaRiwayat('retur_pembelian', {{ $retur->id }})"
                                        class="px-2 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]"
                                        title="Audit Trail"
                                    >
                                        📜
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-ink-400">
                            Belum ada riwayat retur pembelian.
                        </td>
                    </tr>
                @endforelse
            </x-prism.data-table>

            <div>{{ $returList->links() }}</div>
        </div>
    @endif

    {{-- TAB 2: POOL KARANTINA BARANG RETUR (PER SUPPLIER) --}}
    @if($subTab === 'karantina')
        <div class="space-y-4">
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-200 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-lg">💡</span>
                    <span>Barang-barang rusak di bawah ini dikumpulkan dari hasil retur konsumen. Anda dapat membuat <strong>Draft Retur Lengkap</strong>, mencetak surat jalan mirip PO/GRN, lalu mengirimkannya ke Supplier terkait untuk mendapatkan <strong>Saldo Deposit / Potong Utang</strong>.</span>
                </div>
            </div>

            {{-- Kartu Ringkasan per Supplier --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse($karantinaGrouped as $supId => $items)
                    @php
                        $sup = $suppliers->firstWhere('id', $supId);
                        $totalEstimasi = $items->sum('subtotal');
                    @endphp
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 flex flex-col justify-between gap-3">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="font-bold text-sm text-white truncate">{{ $sup?->nama ?? 'Supplier Belum Ditentukan' }}</h4>
                                <span class="px-2 py-0.5 rounded-full bg-up-red/20 text-up-red font-bold text-[10px]">
                                    {{ $items->count() }} item
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-ink-400">
                                Total Estimasi Klaim: <span class="font-bold text-up-mint tabular-nums">Rp {{ number_format($totalEstimasi, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-0.5 text-[11px] text-ink-400 flex items-center justify-between">
                                <span>Saldo Deposit Aktif:</span>
                                <span class="font-bold text-ink-200 tabular-nums">Rp {{ number_format($sup?->saldo_deposit ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2 border-t border-white/5">
                            @if($supId)
                                <button
                                    type="button"
                                    wire:click="bukaModalPoolRetur({{ $supId }})"
                                    class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/80 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md shadow-up-primary/20"
                                >
                                    <span>📤 Buat Draft Retur</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="bukaProfilSupplier({{ $supId }})"
                                    class="p-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white transition active:scale-95 cursor-pointer"
                                    title="Lihat Profil & Riwayat Lengkap Supplier"
                                >
                                    👤
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-8 text-center text-ink-400 border border-dashed border-white/10 rounded-2xl">
                        Tidak ada barang rusak di pool karantina saat ini.
                    </div>
                @endforelse
            </div>

            {{-- Tabel Rincian Barang Karantina --}}
            <div class="space-y-2 pt-2">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-ink-300">Daftar Fisik Barang Karantina</h4>
                    <div class="text-[11px] text-ink-400">
                        {{ count($selectedPoolItemIds) }} item dipilih
                    </div>
                </div>

                <x-prism.data-table :headers="['Pilih', 'Produk / SKU', 'Supplier Asal', 'Alasan Retur / Kerusakan', 'Qty', 'Nomor Seri (SN)', 'No. Retur Konsumen', 'Estimasi HPP', 'Subtotal']">
                    @forelse($karantinaItems as $kItem)
                        <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                            <td class="py-3 px-4">
                                <input
                                    type="checkbox"
                                    value="{{ $kItem->id }}"
                                    wire:model.live="selectedPoolItemIds"
                                    class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30 cursor-pointer"
                                />
                            </td>
                            <td class="py-3 px-4 font-bold text-white">
                                <div>{{ $kItem->produk?->nama ?? '-' }}</div>
                                <div class="text-[10px] text-ink-400 font-mono">{{ $kItem->produk?->kode }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if($kItem->supplier_id)
                                    <button
                                        type="button"
                                        wire:click="bukaProfilSupplier({{ $kItem->supplier_id }})"
                                        class="text-up-mint hover:underline font-bold text-left cursor-pointer"
                                    >
                                        {{ $kItem->supplier?->nama ?? '-' }}
                                    </button>
                                @else
                                    <span class="text-ink-400">-</span>
                                @endif
                            </td>
                            {{-- ALASAN RETUR / KERUSAKAN PER ITEM --}}
                            <td class="py-3 px-4 max-w-xs">
                                <div class="text-ink-200 font-medium">
                                    {{ $kItem->alasan ?: 'Cacat produk / klaim garansi' }}
                                </div>
                                <div class="text-[10px] text-ink-400 flex items-center gap-1.5 mt-0.5">
                                    <span class="px-1.5 py-0.2 rounded bg-up-red/20 text-up-red font-bold text-[9px]">⚠️ Cacat Garansi</span>
                                    <span>Tgl: {{ $kItem->created_at?->format('d/m/Y') }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 tabular-nums font-bold text-white text-center">{{ (int) $kItem->jumlah }}</td>
                            <td class="py-3 px-4 font-mono text-[11px] text-ink-300">
                                {{ !empty($kItem->nomor_seri) ? implode(', ', $kItem->nomor_seri) : '-' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-ink-300">
                                {{ $kItem->returnPenjualan?->no_return ?? '-' }}
                            </td>
                            <td class="py-3 px-4 tabular-nums text-ink-300">
                                Rp {{ number_format($kItem->hpp, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 tabular-nums font-bold text-up-red">
                                Rp {{ number_format($kItem->subtotal, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-ink-400">
                                Tidak ada item karantina.
                            </td>
                        </tr>
                    @endforelse
                </x-prism.data-table>
            </div>
        </div>
    @endif

    {{-- MODAL PROSES RETUR DARI POOL KARANTINA --}}
    @if($showPoolReturModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-lg glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Buat Dokumen Draft Retur ke Supplier</h3>
                        <p class="text-[10px] text-ink-500 dark:text-ink-400">Siapkan draft dokumen dan estimasi nilai sebelum dikirim ke supplier</p>
                    </div>
                    <button wire:click="tutupModalPoolRetur" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer font-bold text-base">✕</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Supplier Tujuan *</label>
                        <select wire:model.live="selectedPoolSupplierId" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[42px]">
                            <option value="">Pilih Supplier</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" class="bg-white dark:bg-ink-900">{{ $s->nama }} (Saldo Deposit: Rp {{ number_format($s->saldo_deposit, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Rencana Penyelesaian Klaim *</label>
                        <select wire:model="poolMetodePengembalian" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[42px]">
                            <option value="saldo" class="bg-white dark:bg-ink-900">Saldo Deposit Supplier (Kredit PO / Belanja Berikutnya)</option>
                            <option value="utang" class="bg-white dark:bg-ink-900">Potong Tagihan Hutang Dagang (AP)</option>
                            <option value="kas" class="bg-white dark:bg-ink-900">Pengembalian Kas / Bank Langsung</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Alasan Retur / Surat Jalan *</label>
                        <input
                            type="text"
                            wire:model="poolAlasan"
                            placeholder="Contoh: Klaim garansi barang cacat dari toko"
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[42px]"
                        />
                    </div>
                </div>

                <div class="flex gap-2.5 pt-4 border-t border-black/10 dark:border-white/10 mt-4">
                    <button
                        type="button"
                        wire:click="tutupModalPoolRetur"
                        class="flex-1 py-3 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[44px]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="simpanReturPoolSupplier"
                        class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-primary/25 active:scale-[0.98] transition flex items-center justify-center gap-1.5"
                    >
                        <span>📝 Buat Dokumen Draft Retur</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL DETAIL RETUR LENGKAP & PRINT & KONFIRMASI KIRIM --}}
    @if($detailRetur)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-2xl glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-6 rounded-3xl relative my-auto max-h-[92vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink-900 dark:text-white font-mono">{{ $detailRetur->no_return }}</h3>
                            <x-prism.status-pill :status="str_replace('_','-',$detailRetur->status)" size="sm" />
                        </div>
                        <p class="text-[10px] text-ink-500 dark:text-ink-400 mt-0.5">Dokumen Surat Pengantar &amp; Retur Pembelian Supplier</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('wms.retur-pembelian.print', $detailRetur->id) }}"
                            target="_blank"
                            class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 border border-up-red/30 text-up-red font-bold text-xs flex items-center gap-1.5 cursor-pointer transition active:scale-95"
                        >
                            <span>🖨️ Cetak Surat Pengantar (PO/GRN Format)</span>
                        </a>
                        <button wire:click="tutupDetail" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer font-bold text-base">✕</button>
                    </div>
                </div>

                <div class="space-y-3.5 flex-1 overflow-y-auto pr-1 text-xs">
                    {{-- Info Header Dokumen --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-2xl bg-black/[0.03] dark:bg-white/5 border border-black/5 dark:border-white/5">
                        <div>
                            <span class="text-ink-500 dark:text-ink-400 block text-[10px]">Supplier:</span>
                            <span class="font-bold text-ink-900 dark:text-white">{{ $detailRetur->supplier?->nama ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-ink-500 dark:text-ink-400 block text-[10px]">No. PO:</span>
                            <span class="font-mono text-ink-900 dark:text-white">{{ $detailRetur->purchaseOrder?->no_po ?? 'Pool Karantina' }}</span>
                        </div>
                        <div>
                            <span class="text-ink-500 dark:text-ink-400 block text-[10px]">Tanggal:</span>
                            <span class="font-semibold text-ink-900 dark:text-white">{{ $detailRetur->tanggal?->format('d/m/Y') ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-ink-500 dark:text-ink-400 block text-[10px]">Opsi Penyelesaian:</span>
                            <span class="font-bold uppercase text-up-mint">{{ $detailRetur->metode_pengembalian }}</span>
                        </div>
                    </div>

                    {{-- Tabel Item Retur --}}
                    <div class="space-y-1.5">
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[10px]">Daftar Item Barang &amp; Estimasi Nilai Klaim</label>
                        <div class="rounded-2xl border border-black/10 dark:border-white/10 divide-y divide-black/5 dark:divide-white/5 overflow-hidden">
                            @foreach($detailRetur->items as $dItem)
                                <div class="p-3 flex items-center justify-between gap-3 bg-white/[0.01]">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-ink-900 dark:text-white text-xs truncate">{{ $dItem->produk?->nama ?? '-' }}</div>
                                        <div class="text-[10px] text-ink-500 dark:text-ink-400 font-mono">
                                            SKU: {{ $dItem->produk?->sku ?? $dItem->produk?->barcode ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-ink-900 dark:text-white">{{ (int) $dItem->jumlah }} unit × Rp {{ number_format($dItem->harga_beli, 0, ',', '.') }}</div>
                                        <div class="text-[11px] text-up-red font-mono font-bold">Subtotal: Rp {{ number_format($dItem->subtotal, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-up-red/10 border border-up-red/20 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-ink-700 dark:text-ink-200 block text-xs">Total Estimasi Nilai Klaim:</span>
                            <span class="text-[10px] text-ink-500 dark:text-ink-400">Akan dikonversi menjadi kompensasi setelah dikirim</span>
                        </div>
                        <span class="font-bold text-lg text-up-red tabular-nums">Rp {{ number_format($detailRetur->jumlah, 0, ',', '.') }}</span>
                    </div>

                    @if(!empty($detailRetur->alasan))
                        <div class="p-3 rounded-2xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/5 dark:border-white/5">
                            <span class="text-[10px] font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider block mb-1">Alasan Retur:</span>
                            <p class="text-xs text-ink-800 dark:text-ink-200 italic">{{ $detailRetur->alasan }}</p>
                        </div>
                    @endif

                    {{-- FORM KONTROL SIKLUS 3 FASE --}}
                    @if($detailRetur->status === 'draft')
                        {{-- FASE 1 -> 2: Pengiriman Fisik ke Supplier --}}
                        <div class="p-4 rounded-2xl bg-up-primary/10 border border-up-primary/20 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">📦</span>
                                    <div>
                                        <h4 class="font-bold text-xs text-white">Fase 1: Konfirmasi Pengiriman ke Supplier (In-Transit)</h4>
                                        <p class="text-[10px] text-ink-400">Catat nomor resi ekspedisi / armada kirim. Stok fisik toko akan dikeluarkan.</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-up-amber/20 text-up-amber font-bold text-[10px]">Status: Draft</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Kurir / Ekspedisi</label>
                                    <input
                                        type="text"
                                        wire:model="kirimEkspedisi"
                                        placeholder="Contoh: JNE / SiCepat / Driver Toko"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white min-h-[42px]"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Nomor Resi / Bukti Kirim</label>
                                    <input
                                        type="text"
                                        wire:model="kirimNoResi"
                                        placeholder="Nomor resi tracking pengiriman"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono text-white min-h-[42px]"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Kontak / Penerima Vendor</label>
                                    <input
                                        type="text"
                                        wire:model="kirimPenerimaVendor"
                                        placeholder="Nama staf RMA vendor / PIC"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white min-h-[42px]"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Kebijakan Acuan Retur</label>
                                    <select wire:model="kirimKebijakanRetur" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 min-h-[42px]">
                                        <option value="faktur_asli">Faktur Asli PO / Invoice Pembelian Awal</option>
                                        <option value="harga_pasar">Harga Pasar / Pricelist Terkini Vendor</option>
                                        <option value="tukar_unit">Tukar Unit Fisik Baru</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Catatan Pengiriman</label>
                                    <input
                                        type="text"
                                        wire:model="kirimCatatan"
                                        placeholder="Catatan tambahan saat pengiriman"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white min-h-[42px]"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-2">
                                <button
                                    type="button"
                                    wire:click="prosesKirimKeSupplier({{ $detailRetur->id }})"
                                    wire:confirm="Kirim barang fisik ke supplier? Stok fisik toko akan dipotong dan status berubah menjadi Dikirim (In-Transit)."
                                    class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer shadow-lg shadow-up-primary/25 active:scale-[0.98] transition flex items-center justify-center gap-1.5"
                                >
                                    <span>🚀 Kirim Barang ke Supplier (Ubah Status ke In-Transit)</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="batalkanDraft({{ $detailRetur->id }})"
                                    wire:confirm="Batalkan draft retur ini? Seluruh barang akan dikembalikan ke pool karantina fisik toko."
                                    class="px-4 py-3 rounded-xl bg-up-red/15 hover:bg-up-red/25 border border-up-red/30 text-up-red font-bold text-xs cursor-pointer active:scale-[0.98] transition"
                                >
                                    ✕ Batalkan Draft
                                </button>
                            </div>
                        </div>
                    @elseif($detailRetur->status === 'dikirim')
                        {{-- FASE 2 -> 3: Penerimaan Hasil dari Supplier --}}
                        @php
                            $estimasiBuku = (float) $detailRetur->jumlah;
                            $realisasiNominal = is_numeric($hasilFinansialRealisasi) ? (float) $hasilFinansialRealisasi : (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $hasilFinansialRealisasi));
                            $selisihPreview = round($realisasiNominal - $estimasiBuku, 2);
                        @endphp
                        <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/20 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🏢</span>
                                    <div>
                                        <h4 class="font-bold text-xs text-white">Fase 2: Barang Sedang di Vendor (In-Transit) &rarr; Penerimaan Hasil</h4>
                                        <p class="text-[10px] text-ink-300">
                                            Dikirim: <span class="text-white font-semibold">{{ $detailRetur->ekspedisi ?: 'Ekspedisi Langsung' }}</span> · Resi: <span class="font-mono text-white">{{ $detailRetur->no_resi ?: '-' }}</span> · Penerima: <span class="text-white">{{ $detailRetur->penerima_vendor ?: '-' }}</span>
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint font-bold text-[10px]">Status: Dikirim</span>
                            </div>

                            <div class="space-y-3 pt-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Pilih Hasil Penyelesaian Vendor *</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <label class="p-3 rounded-xl border cursor-pointer flex items-center gap-2 {{ $hasilTipe === 'tukar_unit' ? 'bg-up-mint/20 border-up-mint text-white font-bold' : 'bg-white/5 border-white/10 text-ink-300' }}">
                                            <input type="radio" wire:model.live="hasilTipe" value="tukar_unit" class="text-up-mint" />
                                            <span class="text-xs">🔄 1. Tukar Unit Baru (Stok Toko)</span>
                                        </label>
                                        <label class="p-3 rounded-xl border cursor-pointer flex items-center gap-2 {{ $hasilTipe === 'finansial' ? 'bg-up-primary/20 border-up-primary text-white font-bold' : 'bg-white/5 border-white/10 text-ink-300' }}">
                                            <input type="radio" wire:model.live="hasilTipe" value="finansial" class="text-up-primary" />
                                            <span class="text-xs">💰 2. Kompensasi Finansial / Saldo</span>
                                        </label>
                                        <label class="p-3 rounded-xl border cursor-pointer flex items-center gap-2 {{ $hasilTipe === 'tolak' ? 'bg-up-red/20 border-up-red text-white font-bold' : 'bg-white/5 border-white/10 text-ink-300' }}">
                                            <input type="radio" wire:model.live="hasilTipe" value="tolak" class="text-up-red" />
                                            <span class="text-xs">❌ 3. Klaim Ditolak Vendor</span>
                                        </label>
                                    </div>
                                </div>

                                {{-- Opsi A: Tukar Unit Baru --}}
                                @if($hasilTipe === 'tukar_unit')
                                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-2 text-xs">
                                        <p class="text-ink-300 text-[11px]">
                                            Unit fisik baru akan ditambahkan kembali ke stok aktif gudang toko. Jika item memiliki Nomor Seri (SN), silakan input SN baru di bawah ini:
                                        </p>
                                        @foreach($detailRetur->items as $dItem)
                                            <div class="p-2 rounded-lg bg-black/20 border border-white/5">
                                                <div class="font-bold text-white mb-1">{{ $dItem->produk?->nama }} (Qty: {{ (int) $dItem->jumlah }})</div>
                                                <input
                                                    type="text"
                                                    wire:model="hasilReplacementSn.{{ $dItem->produk_id }}"
                                                    placeholder="Input SN baru dipisah koma jika ada (contoh: SN-NEW-001, SN-NEW-002)"
                                                    class="w-full px-3 py-1.5 rounded-lg glass-input text-xs text-white"
                                                />
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif($hasilTipe === 'finansial')
                                    {{-- Opsi B: Kompensasi Finansial --}}
                                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-3 text-xs">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Metode Pengembalian *</label>
                                                <select wire:model="hasilFinansialMetode" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 min-h-[42px]">
                                                    <option value="saldo">Saldo Deposit Supplier (Kredit Belanja PO)</option>
                                                    <option value="utang">Potong Tagihan Hutang Berjalan (AP)</option>
                                                    <option value="kas">Kas / Transfer Bank Langsung</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Nilai Realisasi Klaim Vendor (Rp) *</label>
                                                <input
                                                    type="number"
                                                    step="any"
                                                    wire:model.live="hasilFinansialRealisasi"
                                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-white tabular-nums min-h-[42px]"
                                                />
                                                <div class="mt-1 flex items-center justify-between text-[10px]">
                                                    <span class="text-ink-400">Nilai Buku: Rp {{ number_format($estimasiBuku, 0, ',', '.') }}</span>
                                                    @if($selisihPreview < 0)
                                                        <span class="text-up-amber font-semibold">⚠️ Selisih: -Rp {{ number_format(abs($selisihPreview), 0, ',', '.') }} (Beban 520-08)</span>
                                                    @elseif($selisihPreview > 0)
                                                        <span class="text-up-mint font-semibold">✨ Selisih: +Rp {{ number_format($selisihPreview, 0, ',', '.') }} (Pendapatan 430-01)</span>
                                                    @else
                                                        <span class="text-up-mint">✓ Nilai klop sesuai buku</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($hasilTipe === 'tolak')
                                    {{-- Opsi C: Tolak --}}
                                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-3 text-xs">
                                        <div>
                                            <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Tindakan Fisik Barang Ditolak *</label>
                                            <select wire:model="tolakOpsiBarang" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 min-h-[42px]">
                                                <option value="kembali_ke_toko">Barang Dikembalikan ke Toko (Kembali ke Stok Toko)</option>
                                                <option value="scrap_vendor">Barang Dimusnahkan / Scrap Vendor (Jurnal Kerugian Persediaan)</option>
                                            </select>
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Catatan Hasil &amp; Penyelesaian Vendor</label>
                                    <input
                                        type="text"
                                        wire:model="hasilCatatan"
                                        placeholder="Keterangan konfirmasi vendor / RMA ticket number"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white min-h-[42px]"
                                    />
                                </div>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="button"
                                    wire:click="prosesHasilVendor({{ $detailRetur->id }})"
                                    wire:confirm="Konfirmasi selesaikan retur ini dengan hasil yang dipilih? Transaksi akan langsung dibukukan."
                                    class="w-full py-3 rounded-xl bg-up-mint hover:bg-up-mint/90 text-ink-950 font-bold text-xs cursor-pointer shadow-lg shadow-up-mint/25 active:scale-[0.98] transition flex items-center justify-center gap-1.5"
                                >
                                    <span>✓ Simpan Hasil Penerimaan &amp; Selesaikan Retur</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="p-3.5 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-xs text-up-mint space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold">
                                    <span>✅</span> Retur Telah Selesai &amp; Tersinkronisasi
                                </span>
                                <span class="text-[11px] text-ink-200">
                                    Metode: <strong class="text-white uppercase">{{ $detailRetur->metode_pengembalian }}</strong> · Realisasi: <strong class="text-white tabular-nums">Rp {{ number_format($detailRetur->nilai_realisasi ?? $detailRetur->jumlah, 0, ',', '.') }}</strong>
                                </span>
                            </div>
                            @if($detailRetur->ekspedisi || $detailRetur->no_resi)
                                <div class="text-[11px] text-ink-300">
                                    Log Kirim: <strong>{{ $detailRetur->ekspedisi ?: 'Ekspedisi' }}</strong> (Resi: <span class="font-mono text-white">{{ $detailRetur->no_resi ?: '-' }}</span>)
                                </div>
                            @endif
                            @if((float) $detailRetur->selisih_klaim != 0)
                                <div class="text-[11px] text-ink-300">
                                    Selisih Nilai Klaim:
                                    <strong class="{{ (float) $detailRetur->selisih_klaim < 0 ? 'text-up-amber' : 'text-up-mint' }} tabular-nums">
                                        {{ (float) $detailRetur->selisih_klaim < 0 ? '-' : '+' }}Rp {{ number_format(abs((float) $detailRetur->selisih_klaim), 0, ',', '.') }}
                                    </strong>
                                    ({{ (float) $detailRetur->selisih_klaim < 0 ? 'Dibukukan ke Beban Selisih 520-08' : 'Dibukukan ke Pendapatan Lain 430-01' }})
                                </div>
                            @endif
                            @if($detailRetur->catatan_penyelesaian)
                                <div class="text-[11px] text-ink-300">Catatan: {{ $detailRetur->catatan_penyelesaian }}</div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="pt-4 border-t border-black/10 dark:border-white/10 mt-4 flex items-center justify-end">
                    <button
                        type="button"
                        wire:click="tutupDetail"
                        class="px-5 py-2.5 rounded-xl bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 text-ink-700 dark:text-white font-bold text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL PROFIL & RIWAYAT LENGKAP SUPPLIER --}}
    @if($showSupplierProfileModal && $supplierProfil)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-3xl glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-6 rounded-3xl relative my-auto max-h-[92vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                {{-- Header Supplier Profile --}}
                <div class="flex items-start justify-between pb-4 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-ink-900 dark:text-white">{{ $supplierProfil->nama }}</h3>
                            @if($supplierProfil->is_active)
                                <span class="px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint font-bold text-[10px]">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-up-red/20 text-up-red font-bold text-[10px]">Nonaktif</span>
                            @endif
                        </div>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">
                            PIC: <strong class="text-white">{{ $supplierProfil->kontak ?: '-' }}</strong> · Telp: <strong class="text-white">{{ $supplierProfil->telepon ?: '-' }}</strong> · Termin: <strong class="text-white">{{ $supplierProfil->termin_hari }} hari</strong>
                        </p>
                    </div>
                    <button wire:click="tutupProfilSupplier" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer font-bold text-base">✕</button>
                </div>

                {{-- Stat Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-4 text-xs">
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <span class="text-ink-400 block text-[10px]">Saldo Deposit Retur:</span>
                        <span class="font-bold text-sm text-up-mint tabular-nums">Rp {{ number_format($supplierProfil->saldo_deposit, 0, ',', '.') }}</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <span class="text-ink-400 block text-[10px]">Total Transaksi PO:</span>
                        <span class="font-bold text-sm text-white tabular-nums">{{ $supplierProfil->purchaseOrders->count() }} Dokumen</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <span class="text-ink-400 block text-[10px]">Total Retur Barang:</span>
                        <span class="font-bold text-sm text-up-red tabular-nums">{{ $supplierProfil->returnPembelians->count() }} Retur</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <span class="text-ink-400 block text-[10px]">Katalog Produk:</span>
                        <span class="font-bold text-sm text-ink-200 tabular-nums">{{ $supplierProfil->produks->count() }} Produk</span>
                    </div>
                </div>

                {{-- Tabs Nav --}}
                <div class="flex items-center gap-1.5 border-b border-white/10 pb-2 mb-3 overflow-x-auto">
                    <button
                        type="button"
                        wire:click="$set('supplierProfileTab', 'profil')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $supplierProfileTab === 'profil' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}"
                    >
                        Profil &amp; Rekening
                    </button>
                    <button
                        type="button"
                        wire:click="$set('supplierProfileTab', 'saldo')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $supplierProfileTab === 'saldo' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}"
                    >
                        Riwayat Saldo Deposit Retur ({{ $supplierProfil->saldoMutasi->count() }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('supplierProfileTab', 'retur')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $supplierProfileTab === 'retur' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}"
                    >
                        Riwayat Retur Pembelian ({{ $supplierProfil->returnPembelians->count() }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('supplierProfileTab', 'po')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $supplierProfileTab === 'po' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}"
                    >
                        Riwayat Pembelian PO ({{ $supplierProfil->purchaseOrders->count() }})
                    </button>
                </div>

                {{-- Tab Content --}}
                <div class="flex-1 overflow-y-auto pr-1 space-y-3 text-xs">
                    {{-- TAB PROFIL --}}
                    @if($supplierProfileTab === 'profil')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-2">
                                <h4 class="font-bold text-xs uppercase text-ink-300">Informasi Kontak &amp; Operasional</h4>
                                <div class="space-y-1.5 text-xs">
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Alamat Kantor / Gudang:</span>
                                        <span class="text-white">{{ $supplierProfil->alamat ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Email Resmi:</span>
                                        <span class="text-white">{{ $supplierProfil->email ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Nomor Telepon:</span>
                                        <span class="text-white">{{ $supplierProfil->telepon ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Termin Pembayaran:</span>
                                        <span class="text-white font-bold">{{ $supplierProfil->termin_hari }} Hari</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Kebijakan Acuan Retur Garansi:</span>
                                        <span class="text-up-mint font-semibold">
                                            @if($supplierProfil->kebijakan_retur === 'harga_pasar')
                                                Harga Pasar / Pricelist Terkini
                                            @elseif($supplierProfil->kebijakan_retur === 'tukar_unit')
                                                Tukar Unit Fisik Baru
                                            @else
                                                Sesuai Faktur Asli PO (Rekomendasi)
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-2">
                                <h4 class="font-bold text-xs uppercase text-ink-300">Rekening Bank untuk Pembayaran / Refund</h4>
                                <div class="space-y-1.5 text-xs">
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Nama Bank:</span>
                                        <span class="text-white font-bold">{{ $supplierProfil->bank_nama ?: 'Belum diatur' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Nomor Rekening:</span>
                                        <span class="font-mono text-up-mint font-bold text-sm">{{ $supplierProfil->nomor_rekening ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Atas Nama Rekening:</span>
                                        <span class="text-white">{{ $supplierProfil->nama_rekening ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-ink-400 text-[11px] block">Catatan Tambahan:</span>
                                        <span class="text-ink-300 italic">{{ $supplierProfil->catatan ?: '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- TAB SALDO DEPOSIT RETUR --}}
                    @if($supplierProfileTab === 'saldo')
                        <div class="space-y-2">
                            <div class="p-3 rounded-2xl bg-up-mint/10 border border-up-mint/20 flex items-center justify-between text-xs">
                                <div>
                                    <span class="text-ink-300 block text-[10px]">Saldo Deposit Aktif Saat Ini:</span>
                                    <span class="font-bold text-base text-up-mint tabular-nums">Rp {{ number_format($supplierProfil->saldo_deposit, 0, ',', '.') }}</span>
                                </div>
                                <span class="text-[10px] text-ink-400 max-w-xs text-right">
                                    Dapat digunakan untuk memotong total tagihan PO berikutnya atau dicairkan via kas/bank.
                                </span>
                            </div>

                            <div class="rounded-2xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                @forelse($supplierProfil->saldoMutasi as $m)
                                    <div class="p-3 flex items-center justify-between gap-3 bg-white/[0.01]">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase {{ $m->jenis === 'masuk' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-red/20 text-up-red' }}">
                                                    {{ $m->jenis === 'masuk' ? '+ Masuk' : '- Keluar' }}
                                                </span>
                                                <span class="font-bold text-white text-xs">{{ $m->keterangan }}</span>
                                            </div>
                                            <div class="text-[10px] text-ink-400 mt-1">
                                                Tgl: {{ $m->created_at?->format('d/m/Y H:i') }} · Oleh: {{ $m->user?->name ?? 'Sistem' }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-xs tabular-nums {{ $m->jenis === 'masuk' ? 'text-up-mint' : 'text-up-red' }}">
                                                {{ $m->jenis === 'masuk' ? '+' : '-' }} Rp {{ number_format($m->jumlah, 0, ',', '.') }}
                                            </div>
                                            <div class="text-[10px] text-ink-400 tabular-nums">
                                                Saldo Akhir: Rp {{ number_format($m->saldo_setelah, 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-6 text-center text-ink-400 text-xs">
                                        Belum ada riwayat mutasi saldo deposit untuk supplier ini.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- TAB RETUR PEMBELIAN --}}
                    @if($supplierProfileTab === 'retur')
                        <div class="rounded-2xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                            @forelse($supplierProfil->returnPembelians as $r)
                                <div class="p-3 flex items-center justify-between gap-3 bg-white/[0.01]">
                                    <div>
                                        <div class="font-mono font-bold text-white text-xs">{{ $r->no_return }}</div>
                                        <div class="text-[10px] text-ink-400">
                                            Tgl: {{ $r->tanggal?->format('d/m/Y') }} · Metode: <strong class="text-white uppercase">{{ $r->metode_pengembalian }}</strong>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-xs text-up-red tabular-nums">Rp {{ number_format($r->jumlah, 0, ',', '.') }}</div>
                                        <x-prism.status-pill :status="str_replace('_','-',$r->status)" size="sm" />
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center text-ink-400 text-xs">
                                    Belum ada transaksi retur barang ke supplier ini.
                                </div>
                            @endforelse
                        </div>
                    @endif

                    {{-- TAB TRANSAKSI PO --}}
                    @if($supplierProfileTab === 'po')
                        <div class="rounded-2xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                            @forelse($supplierProfil->purchaseOrders as $p)
                                <div class="p-3 flex items-center justify-between gap-3 bg-white/[0.01]">
                                    <div>
                                        <div class="font-mono font-bold text-white text-xs">{{ $p->no_po }}</div>
                                        <div class="text-[10px] text-ink-400">
                                            Tgl: {{ $p->created_at?->format('d/m/Y') }} · Status: <span class="uppercase text-white font-bold">{{ $p->status }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-xs text-white tabular-nums">Rp {{ number_format($p->total, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-ink-400">Dibayar: Rp {{ number_format($p->total_dibayar, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center text-ink-400 text-xs">
                                    Belum ada riwayat transaksi Purchase Order (PO).
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>

                <div class="pt-4 border-t border-black/10 dark:border-white/10 mt-4 flex justify-end">
                    <button
                        type="button"
                        wire:click="tutupProfilSupplier"
                        class="px-5 py-2.5 rounded-xl bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 text-ink-700 dark:text-white font-bold text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL TAMBAH RETUR PEMBELIAN DARI PO --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-3xl glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-6 rounded-3xl relative my-auto max-h-[92vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Buat Retur Pembelian dari Purchase Order (PO)</h3>
                        <p class="text-xs text-ink-500 dark:text-ink-400">Pilih PO yang telah berstatus diterima untuk mengembalikan unit ke supplier.</p>
                    </div>
                    <button wire:click="tutupModalTambah" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer font-bold text-base">✕</button>
                </div>

                <div class="space-y-4 overflow-y-auto flex-1 pr-1 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Pilih Dokumen PO (Status Diterima) *</label>
                        <select wire:model.live="selectedPoId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 min-h-[44px]">
                            <option value="">-- Pilih Purchase Order --</option>
                            @foreach($poSiapRetur as $p)
                                <option value="{{ $p->id }}">{{ $p->no_po }} — {{ $p->supplier?->nama ?? 'Tanpa Supplier' }} (Total: Rp {{ number_format($p->total, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>

                    @if(! empty($itemInputs))
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-ink-300 uppercase">Item PO yang Diretur &amp; Jumlah *</label>
                            <div class="rounded-2xl border border-white/10 overflow-hidden divide-y divide-white/5">
                                @foreach($itemInputs as $idx => $input)
                                    <div class="p-3 bg-white/[0.02] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold text-white text-xs truncate">{{ $input['produk_nama'] }}</div>
                                            <div class="text-[10px] text-ink-400">
                                                Qty PO: <strong class="text-white">{{ $input['qty_po'] }}</strong> · Harga Beli: <strong class="text-white">Rp {{ number_format($input['harga_beli'], 0, ',', '.') }}</strong>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <label class="block text-[9px] text-ink-400 uppercase">Qty Retur</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="0"
                                                    max="{{ $input['qty_po'] }}"
                                                    wire:model="itemInputs.{{ $idx }}.jumlah"
                                                    class="w-20 px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold text-white tabular-nums text-center"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    @if((float)($itemInputs[$idx]['jumlah'] ?? 0) > 0)
                                        <div class="px-3 pb-3 bg-white/[0.01]">
                                            <label class="block text-[9px] text-ink-400 uppercase mb-0.5">Nomor Seri SN (Opsional, pisah enter/koma)</label>
                                            <input
                                                type="text"
                                                wire:model="itemInputs.{{ $idx }}.sn_raw"
                                                placeholder="Contoh: SN-1234, SN-5678"
                                                class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs text-white"
                                            />
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Rencana Metode Pengembalian *</label>
                                <select wire:model="metodePengembalian" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 border border-white/10 min-h-[42px]">
                                    <option value="saldo">Saldo Deposit Supplier (Kredit PO Berikutnya)</option>
                                    <option value="utang">Potong Tagihan Hutang PO Ini (AP)</option>
                                    <option value="kas">Kas / Transfer Bank Langsung</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-ink-300 uppercase mb-1">Alasan Retur Pembelian *</label>
                                <input
                                    type="text"
                                    wire:model="alasan"
                                    placeholder="Contoh: Barang rusak saat unboxing / cacat pabrik"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white min-h-[42px]"
                                />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="pt-4 border-t border-black/10 dark:border-white/10 mt-4 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="tutupModalTambah"
                        class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-bold text-xs cursor-pointer"
                    >
                        Batal
                    </button>
                    @if(! empty($itemInputs))
                        <button
                            type="button"
                            wire:click="simpanRetur"
                            class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer shadow-lg shadow-up-primary/25"
                        >
                            ✓ Simpan Draft Retur
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL AUDIT TRAIL LOG --}}
    @include('partials.riwayat-modal')
</div>
