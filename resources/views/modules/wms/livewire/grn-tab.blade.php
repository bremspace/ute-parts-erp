<div class="space-y-5">
    {{-- Filter status [F2-2] --}}
    <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap sm:flex-wrap">
        @foreach (['all' => 'Semua', 'draft' => 'Menunggu Approval', 'terima' => 'Diterima', 'ditolak' => 'Ditolak'] as $val => $label)
            <button
                wire:click="$set('filterStatus', '{{ $val }}')"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] cursor-pointer active:scale-[0.97] whitespace-nowrap min-h-[44px] flex items-center {{ $filterStatus === $val ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- PO siap diterima — input GRN wajib sebelum stok masuk --}}
    <div class="rounded-2xl glass-panel p-4">
        <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
            <h3 class="text-sm font-bold text-white">PO Siap Diterima</h3>
            <span class="text-[10px] text-ink-400">Flow: PO dikirim → input GRN → stok & jurnal AP</span>
        </div>
        <x-prism.data-table :headers="['No. PO', 'Supplier', 'Gudang Tujuan', 'Total', 'Aksi']">
            @forelse($poList as $po)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3 px-4 font-mono font-bold text-white">{{ $po->no_po }}</td>
                    <td class="py-3 px-4 text-ink-200">{{ $po->supplier?->nama }}</td>
                    <td class="py-3 px-4 text-ink-300">{{ $po->gudangTujuan?->nama }}</td>
                    <td class="py-3 px-4 tabular-nums font-bold text-white">Rp {{ number_format($po->total, 0, ',', '.') }}</td>
                    <td class="py-3 px-4">
                        <button
                            wire:click="openGrnModal({{ $po->id }})"
                            class="px-2.5 py-1 rounded-lg bg-up-accent hover:opacity-90 text-white font-bold text-[10px] cursor-pointer"
                        >
                            Input GRN
                        </button>
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
        <h3 class="text-sm font-bold text-white mb-3">Daftar GRN</h3>
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
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/10 text-up-amber">Menunggu Approval</span>
                        @elseif($grn->status === 'terima')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/10 text-up-mint">Diterima</span>
                        @elseif($grn->status === 'ditolak')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/10 text-up-red">Ditolak</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-ink-300">{{ $grn->status }}</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4">
                        <div class="flex gap-1.5 flex-wrap">
                            @if($grn->status === 'draft')
                                @can('approve-workflow')
                                    <button
                                        wire:click="bukaKonfirmasiSetuju({{ $grn->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-up-mint/90 hover:opacity-90 text-ink-950 font-bold text-[10px] cursor-pointer"
                                    >
                                        Setujui
                                    </button>
                                    <button
                                        x-data
                                        x-on:click="const alasan = prompt('Alasan penolakan GRN:'); if (alasan) { @this.call('tolakGrn', {{ $grn->id }}, alasan); }"
                                        class="px-2.5 py-1 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red font-bold text-[10px] cursor-pointer"
                                    >
                                        Tolak
                                    </button>
                                @endcan
                            @endif
                            <button
                                wire:click="bukaDetailGrn({{ $grn->id }})"
                                class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[10px] cursor-pointer"
                            >
                                Detail
                            </button>
                            <button
                                wire:click="bukaRiwayat('grn', {{ $grn->id }})"
                                class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer"
                            >
                                Riwayat
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-6 text-center text-ink-400 text-xs">Belum ada GRN.</td>
                </tr>
            @endforelse
        </x-prism.data-table>
        <div class="mt-3">{{ $grnList->links() }}</div>
    </div>

    {{-- MODAL: INPUT GRN [F2-2] --}}
    @if($showGrnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-xl glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Input GRN (Penerimaan Barang)</h3>
                    <button wire:click="cancelGrnModal" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-ink-300 uppercase">Item Diterima</span>
                    <span class="text-[10px] text-ink-400">Qty diterima wajib ≤ qty PO — selisih → approval</span>
                </div>

                <div class="space-y-2 mb-4 max-h-64 overflow-y-auto pr-1">
                    @foreach($grnForm['item_qty'] as $idx => $item)
                        <div class="flex gap-2 items-center text-xs">
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-white truncate">{{ $item['produk_nama'] ?? ('#'.$item['produk_id']) }}</div>
                                @cansee('harga_beli')
                                    <div class="text-ink-400 tabular-nums">
                                        Harga Rp {{ number_format((float) ($item['harga_beli'] ?? 0), 0, ',', '.') }}
                                    </div>
@cannotsee('harga_beli')
                                    <div class="text-ink-400 tabular-nums">
                                        Harga —
                                    </div>
@endcansee
                            </div>
                            <div class="w-20 text-center tabular-nums text-ink-300">
                                <div class="text-[10px] text-ink-400">Qty PO</div>
                                <div class="font-bold">{{ $item['qty_po'] ?? '-' }}</div>
                            </div>
                            <div class="w-28">
                                <div class="text-[10px] text-ink-400 mb-0.5">Diterima</div>
                                <input
                                    type="number"
                                    min="0"
                                    max="{{ $item['qty_po'] ?? 0 }}"
                                    wire:model.live="grnForm.item_qty.{{ $idx }}.qty_received"
                                    class="w-full px-2 py-2 rounded-xl glass-input text-xs tabular-nums text-center"
                                />
                            </div>
                        </div>

                        {{-- [F2-3] Input SN utk produk sn=true — jumlah wajib = qty diterima --}}
                        @if($item['sn_flag'] ?? false)
                            <div class="pt-1 pb-2">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold text-up-accent uppercase">Nomor Seri (wajib)</span>
                                    <span class="text-[10px] text-ink-400 tabular-nums">Jumlah SN harus = qty diterima</span>
                                </div>
                                <textarea
                                    wire:model="grnForm.item_qty.{{ $idx }}.sn"
                                    rows="2"
                                    placeholder="Satu SN per baris (boleh pisah koma) — scan/ tempel di sini"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono"
                                ></textarea>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan</label>
                    <textarea wire:model="grnForm.catatan" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" placeholder="Opsional — mis. selisih kemasan"></textarea>
                </div>

                <div class="flex gap-3">
                    <button wire:click="cancelGrnModal" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanGrn" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan GRN</button>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-4 text-xs">
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
                                        <td class="py-2 px-3 text-center tabular-nums text-ink-300">{{ $item['qty_po'] ?? '-' }}</td>
                                        <td class="py-2 px-3 text-center tabular-nums font-bold text-white">{{ $item['qty_received'] ?? 0 }}</td>
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
                <div class="flex items-center justify-end pt-4 border-t border-white/10 mt-2">
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

    {{-- [F1-4] Modal riwayat audit trail per GRN --}}
    @include('partials.riwayat-modal')
</div>
