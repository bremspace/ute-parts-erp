<div class="space-y-5">
    {{-- Sub Navigation: Riwayat Retur vs Pool Karantina Supplier --}}
    <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3 flex-wrap">
        <div class="inline-flex items-center p-1 rounded-2xl bg-white/5 border border-white/10">
            <button
                type="button"
                wire:click="$set('subTab', 'riwayat')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2 {{ $subTab === 'riwayat' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white' }}"
            >
                <span>📦 Riwayat Retur Pembelian</span>
            </button>
            <button
                type="button"
                wire:click="$set('subTab', 'karantina')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2 {{ $subTab === 'karantina' ? 'bg-up-red text-white shadow-md shadow-up-red/25' : 'text-ink-400 hover:text-white' }}"
            >
                <span>⚠️ Pool Karantina Retur ({{ $karantinaItems->count() }} item)</span>
            </button>
        </div>

        @if($subTab === 'riwayat')
            @can('wms.create')
                <button
                    wire:click="bukaModalTambah"
                    class="px-4 py-2.5 rounded-xl bg-up-red/90 hover:bg-up-red text-white font-bold text-xs shadow-md shadow-up-red/20 transition active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
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
                @foreach (['all' => 'Semua', 'draft' => 'Draft / Menunggu Approval', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'] as $val => $label)
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
                            {{ $retur->purchaseOrder?->no_po ?? 'Pool Karantina' }}
                        </td>
                        <td class="py-3.5 px-4 text-ink-300">{{ $retur->supplier?->nama ?? '-' }}</td>
                        <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $retur->tanggal?->format('d/m/Y') ?? '-' }}</td>
                        <td class="py-3.5 px-4 uppercase text-[10px] font-bold text-ink-300">{{ $retur->metode_pengembalian }}</td>
                        <td class="py-3.5 px-4 tabular-nums font-bold text-up-red">Rp {{ number_format($retur->jumlah, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-4"><x-prism.status-pill :status="str_replace('_','-',$retur->status)" /></td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 flex-nowrap">
                                <button
                                    wire:click="bukaDetail({{ $retur->id }})"
                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]"
                                >
                                    Detail
                                </button>
                                @can('lihat-audit-log')
                                    <button
                                        wire:click="bukaRiwayat('retur_pembelian', {{ $retur->id }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]"
                                    >
                                        Riwayat
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
                    <span>Barang-barang rusak di bawah ini dikumpulkan dari hasil retur konsumen. Anda dapat langsung mengelompokkan dan mengirimkannya ke Supplier terkait untuk mendapatkan <strong>Saldo Deposit / Potong Utang</strong>.</span>
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
                            <div class="mt-0.5 text-[11px] text-ink-400">
                                Saldo Deposit Aktif: <span class="font-bold text-ink-200 tabular-nums">Rp {{ number_format($sup?->saldo_deposit ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        @if($supId)
                            <button
                                type="button"
                                wire:click="bukaModalPoolRetur({{ $supId }})"
                                class="w-full py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/80 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer"
                            >
                                <span>📤 Proses Retur ke Supplier Ini</span>
                            </button>
                        @endif
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

                <x-prism.data-table :headers="['Pilih', 'Produk / SKU', 'Supplier Asal', 'Qty', 'Nomor Seri (SN)', 'No. Retur Konsumen', 'HPP Satuan', 'Subtotal']">
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
                            <td class="py-3 px-4 text-ink-300">{{ $kItem->supplier?->nama ?? '-' }}</td>
                            <td class="py-3 px-4 tabular-nums font-bold text-white">{{ (int) $kItem->jumlah }}</td>
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
                            <td colspan="8" class="py-8 text-center text-ink-400">
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
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Kirim Retur ke Supplier</h3>
                        <p class="text-[10px] text-ink-500 dark:text-ink-400">Konfirmasi pengembalian barang rusak dari karantina toko</p>
                    </div>
                    <button wire:click="tutupModalPoolRetur" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer font-bold text-base">✕</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Supplier Tujuan *</label>
                        <select wire:model.live="selectedPoolSupplierId" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[42px]">
                            <option value="">Pilih Supplier</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" class="bg-white dark:bg-ink-900">{{ $s->nama }} (Saldo: Rp {{ number_format($s->saldo_deposit, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Metode Penyelesaian Kompensasi *</label>
                        <select wire:model="poolMetodePengembalian" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[42px]">
                            <option value="saldo" class="bg-white dark:bg-ink-900">Saldo Deposit Supplier (Potong Utang Berjalan / Kredit Belanja PO)</option>
                            <option value="utang" class="bg-white dark:bg-ink-900">Potong Tagihan Utang PO Tertentu</option>
                            <option value="kas" class="bg-white dark:bg-ink-900">Pengembalian Dana / Transfer Supplier (Kas Toko)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Catatan / Alasan Retur</label>
                        <textarea
                            wire:model="poolAlasan"
                            rows="2"
                            placeholder="Alasan retur..."
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10"
                        ></textarea>
                    </div>
                </div>

                <div class="flex gap-2.5 pt-4 border-t border-black/10 dark:border-white/10 mt-5">
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
                        class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-primary/25 transition active:scale-[0.98]"
                    >
                        Konfirmasi Kirim Retur
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL TAMBAH RETUR PEMBELIAN DARI PO --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-2xl glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Buat Retur Pembelian (PO)</h3>
                        <p class="text-[10px] text-ink-500 dark:text-ink-400">Pengurangan persediaan gudang & penyesuaian utang usaha</p>
                    </div>
                    <button wire:click="tutupModalTambah" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-4 flex-1 overflow-y-auto pr-1 text-xs">
                    <div>
                        <label class="block font-bold text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Pilih Purchase Order (PO) *</label>
                        <select wire:model.live="selectedPoId" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900 min-h-[44px]">
                            <option value="">Pilih PO berstatus Diterima</option>
                            @foreach($poSiapRetur as $po)
                                <option value="{{ $po->id }}">
                                    {{ $po->no_po }} — {{ $po->supplier?->nama }} (Total: Rp {{ number_format($po->total, 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($itemInputs))
                        <div class="space-y-2">
                            <label class="block font-bold text-ink-300 uppercase tracking-wider text-[11px]">Daftar Barang yang Diretur</label>
                            <div class="rounded-2xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                @foreach($itemInputs as $itemId => $item)
                                    <div class="p-3 bg-white/[0.02] flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-bold text-white text-xs truncate">{{ $item['produk_nama'] }}</div>
                                            <div class="text-[10px] text-ink-400">
                                                Qty PO: {{ $item['qty_po'] }} · Harga Beli: Rp {{ number_format($item['harga_beli'], 0, ',', '.') }}
                                            </div>
                                        </div>
                                        <div class="w-24">
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $item['qty_po'] }}"
                                                wire:model.live="itemInputs.{{ $itemId }}.jumlah"
                                                class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs font-bold tabular-nums text-center"
                                                placeholder="Qty"
                                            />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Metode Kompensasi *</label>
                            <select wire:model="metodePengembalian" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[44px]">
                                <option value="saldo" class="bg-white dark:bg-ink-900">Saldo Deposit Supplier (Kredit PO)</option>
                                <option value="utang" class="bg-white dark:bg-ink-900">Potong Saldo Utang PO</option>
                                <option value="kas" class="bg-white dark:bg-ink-900">Pengembalian Dana Tunai/Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Alasan Retur *</label>
                            <input
                                type="text"
                                wire:model="alasan"
                                placeholder="Contoh: Barang rusak saat pengiriman"
                                class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900 border border-black/10 dark:border-white/10 min-h-[44px]"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex gap-2.5 pt-4 border-t border-black/10 dark:border-white/10 mt-4">
                    <button
                        type="button"
                        wire:click="tutupModalTambah"
                        class="flex-1 py-3 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[44px]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="simpanRetur"
                        class="flex-1 py-3 rounded-xl bg-up-red hover:bg-up-red-dark text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-red/25 active:scale-[0.98] transition"
                    >
                        Proses Retur Pembelian
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL DETAIL RETUR --}}
    @if($detailRetur)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-xl glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-black/10 dark:border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink-900 dark:text-white font-mono">{{ $detailRetur->no_return }}</h3>
                            <x-prism.status-pill :status="str_replace('_','-',$detailRetur->status)" size="sm" />
                        </div>
                        <p class="text-[10px] text-ink-500 dark:text-ink-400 mt-0.5">Detail dokumen retur pembelian supplier</p>
                    </div>
                    <button wire:click="tutupDetail" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer font-bold text-base">✕</button>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto pr-1 text-xs">
                    <div class="grid grid-cols-2 gap-2 p-3 rounded-xl bg-black/[0.03] dark:bg-white/5 border border-black/5 dark:border-white/5">
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
                            <span class="text-ink-500 dark:text-ink-400 block text-[10px]">Metode:</span>
                            <span class="font-bold uppercase text-up-mint">{{ $detailRetur->metode_pengembalian }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[10px]">Daftar Item</label>
                        <div class="rounded-xl border border-black/10 dark:border-white/10 divide-y divide-black/5 dark:divide-white/5 overflow-hidden">
                            @foreach($detailRetur->items as $dItem)
                                <div class="p-2.5 flex items-center justify-between gap-2">
                                    <div>
                                        <div class="font-bold text-ink-900 dark:text-white text-xs">{{ $dItem->produk?->nama ?? '-' }}</div>
                                        <div class="text-[10px] text-ink-500 dark:text-ink-400 font-mono">{{ $dItem->produk?->kode }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-ink-900 dark:text-white">{{ (int) $dItem->jumlah }} unit</div>
                                        <div class="text-[10px] text-up-red font-mono">Rp {{ number_format($dItem->subtotal, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/20 flex items-center justify-between">
                        <span class="font-bold text-ink-700 dark:text-ink-200">Total Nilai Retur:</span>
                        <span class="font-bold text-base text-up-red tabular-nums">Rp {{ number_format($detailRetur->jumlah, 0, ',', '.') }}</span>
                    </div>

                    @if(!empty($detailRetur->alasan))
                        <div class="p-3 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/5 dark:border-white/5">
                            <span class="text-[10px] font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider block mb-1">Alasan Retur:</span>
                            <p class="text-xs text-ink-800 dark:text-ink-200 italic">{{ $detailRetur->alasan }}</p>
                        </div>
                    @endif
                </div>

                <div class="pt-4 border-t border-black/10 dark:border-white/10 mt-4 flex items-center gap-2">
                    @if($detailRetur->status === 'menunggu')
                        @canany(['wms.approve', 'wms.update'])
                            <button
                                type="button"
                                wire:click="setujuiRetur({{ $detailRetur->id }})"
                                wire:confirm="Setujui dan proses eksekusi retur ini? Stok barang akan disesuaikan dan kompensasi dicatat."
                                class="flex-1 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer shadow-md shadow-up-mint/20 active:scale-[0.97] transition"
                            >
                                ✓ Setujui &amp; Eksekusi
                            </button>
                            <button
                                type="button"
                                wire:click="tolakRetur({{ $detailRetur->id }})"
                                wire:confirm="Tolak pengajuan retur pembelian ini?"
                                class="px-4 py-2.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs cursor-pointer active:scale-[0.97] transition"
                            >
                                ✕ Tolak
                            </button>
                        @endcanany
                    @endif
                    <button
                        type="button"
                        wire:click="tutupDetail"
                        class="{{ $detailRetur->status === 'menunggu' ? 'px-4' : 'w-full' }} py-2.5 rounded-xl bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 text-ink-700 dark:text-white font-bold text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
