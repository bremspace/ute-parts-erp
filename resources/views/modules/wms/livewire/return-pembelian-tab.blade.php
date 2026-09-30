<div class="space-y-5">
    {{-- Filter Status & Tombol Buat Retur --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
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

        @can('wms.create')
            <button
                wire:click="bukaModalTambah"
                class="px-4 py-2.5 rounded-xl bg-up-red/90 hover:bg-up-red text-white font-bold text-xs shadow-md shadow-up-red/20 transition active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
            >
                <span>+ Buat Retur Pembelian</span>
            </button>
        @endcan
    </div>

    {{-- Tabel Retur Pembelian --}}
    <x-prism.data-table :headers="['No. Retur', 'No. PO', 'Supplier', 'Tanggal', 'Metode', 'Total Nilai', 'Status', 'Aksi']">
        @forelse($returList as $retur)
            <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $retur->no_return }}</td>
                <td class="py-3.5 px-4 font-mono text-ink-200">{{ $retur->purchaseOrder?->no_po ?? '-' }}</td>
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
                    Belum ada riwayat retur pembelian. Klik "+ Buat Retur Pembelian" jika ada barang cacat/rusak ke supplier.
                </td>
            </tr>
        @endforelse
    </x-prism.data-table>

    <div>{{ $returList->links() }}</div>

    {{-- MODAL TAMBAH RETUR PEMBELIAN --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white">Buat Retur Pembelian (Supplier)</h3>
                        <p class="text-[10px] text-ink-400">Pengurangan persediaan gudang & penyesuaian utang usaha</p>
                    </div>
                    <button wire:click="tutupModalTambah" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-4 flex-1 overflow-y-auto pr-1 text-xs">
                    <div>
                        <label class="block font-semibold text-ink-300 mb-1.5">Pilih Purchase Order (PO Diterima) *</label>
                        <select wire:model.live="selectedPoId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                            <option value="" class="bg-ink-900">-- Pilih PO --</option>
                            @foreach($poSiapRetur as $p)
                                <option value="{{ $p->id }}" class="bg-ink-900">
                                    {{ $p->no_po }} — {{ $p->supplier?->nama }} (Rp {{ number_format($p->total, 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($selectedPoId && !empty($itemInputs))
                        <div class="space-y-2">
                            <label class="block font-semibold text-ink-300">Pilih Barang yang Diretur & Qty</label>
                            <div class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                @foreach($itemInputs as $itemId => $item)
                                    <div class="p-3 bg-white/[0.02] space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="font-bold text-white">{{ $item['produk_nama'] }}</div>
                                                <div class="text-[11px] text-ink-400">
                                                    Qty PO: {{ $item['qty_po'] }} · Harga: Rp {{ number_format($item['harga_beli'], 0, ',', '.') }}
                                                </div>
                                            </div>
                                            <div class="w-28">
                                                <label class="text-[10px] text-ink-400 block mb-0.5">Qty Retur</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $item['qty_po'] }}"
                                                    wire:model.live="itemInputs.{{ $itemId }}.jumlah"
                                                    class="w-full px-2 py-1.5 rounded-lg glass-input text-xs tabular-nums text-center"
                                                />
                                            </div>
                                        </div>
                                        @if(($itemInputs[$itemId]['jumlah'] ?? 0) > 0)
                                            <div>
                                                <label class="text-[10px] text-ink-400 block mb-0.5">Nomor Seri yang diretur (Pisahkan koma / baris baru jika produk ber-SN):</label>
                                                <textarea
                                                    wire:model="itemInputs.{{ $itemId }}.sn_raw"
                                                    rows="2"
                                                    placeholder="SN123, SN124..."
                                                    class="w-full px-2.5 py-1.5 rounded-lg glass-input text-[11px] font-mono"
                                                ></textarea>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-ink-300 mb-1.5">Kompensasi Pengembalian *</label>
                            <select wire:model="metodePengembalian" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                                <option value="utang" class="bg-ink-900">Potong Saldo Utang (AP Subledger)</option>
                                <option value="kas" class="bg-ink-900">Pengembalian Kas/Dana Tunai</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-300 mb-1.5">Alasan Retur *</label>
                            <input
                                type="text"
                                wire:model="alasan"
                                placeholder="Contoh: Barang rusak fisik / segel terbuka"
                                class="w-full px-3 py-2.5 rounded-xl glass-input text-xs min-h-[44px]"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/10 mt-4">
                    <button wire:click="tutupModalTambah" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    <button wire:click="simpanRetur" class="flex-1 py-3 rounded-xl bg-up-red text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-md shadow-up-red/20 active:scale-[0.97]">
                        Proses Retur Pembelian
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL DETAIL RETUR PEMBELIAN --}}
    @if($detailRetur)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
            <div class="w-full max-w-2xl glass-panel p-5 sm:p-7 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <div class="flex items-start sm:items-center justify-between pb-4 border-b border-white/10 gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-white font-mono tracking-wide">{{ $detailRetur->no_return }}</h3>
                            <x-prism.status-pill :status="str_replace('_','-',$detailRetur->status)" size="sm" />
                        </div>
                        <p class="text-xs text-ink-400 mt-1">
                            PO: <span class="font-mono text-white">{{ $detailRetur->purchaseOrder?->no_po ?? '-' }}</span> · 
                            Supplier: {{ $detailRetur->supplier?->nama ?? '-' }} · 
                            Tanggal: {{ $detailRetur->tanggal?->format('d/m/Y') ?? '-' }}
                        </p>
                    </div>
                    <button wire:click="tutupDetail" class="p-2 text-ink-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors cursor-pointer">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-3 my-4 text-xs">
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Total Nilai Retur</span>
                        <span class="text-sm font-bold text-up-red tabular-nums">Rp {{ number_format((float) $detailRetur->jumlah, 0, ',', '.') }}</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Metode Kompensasi</span>
                        <span class="text-sm font-bold text-white uppercase">{{ $detailRetur->metode_pengembalian }}</span>
                    </div>
                </div>

                @if($detailRetur->alasan)
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs text-ink-300 mb-4">
                        <span class="text-[10px] text-ink-400 block font-semibold mb-0.5">Alasan:</span>
                        {{ $detailRetur->alasan }}
                    </div>
                @endif

                <div class="space-y-2 mb-4 flex-1 overflow-y-auto">
                    <h4 class="text-xs font-bold text-ink-300 uppercase tracking-wider">Daftar Barang Diretur</h4>
                    <div class="rounded-xl border border-white/5 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-ink-400 font-semibold border-b border-white/5">
                                <tr>
                                    <th class="py-2.5 px-3">Produk</th>
                                    <th class="py-2.5 px-3 text-right">Harga Beli</th>
                                    <th class="py-2.5 px-3 text-center">Qty</th>
                                    <th class="py-2.5 px-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($detailRetur->items as $item)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-2.5 px-3 font-bold text-white">{{ $item->produk?->nama ?? '-' }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums text-ink-300">Rp {{ number_format((float) $item->harga_beli, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-center tabular-nums font-bold text-white">{{ $item->jumlah }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums font-bold text-up-red">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-white/10 mt-2">
                    <button wire:click="tutupDetail" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('partials.riwayat-modal')
</div>
