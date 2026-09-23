<div class="space-y-5">
    {{-- Filter status [F2-2] --}}
    <div class="flex items-center gap-2 flex-wrap">
        @foreach (['all' => 'Semua', 'draft' => 'Menunggu Approval', 'terima' => 'Diterima', 'ditolak' => 'Ditolak'] as $val => $label)
            <button
                wire:click="$set('filterStatus', '{{ $val }}')"
                class="px-3 py-1.5 rounded-xl text-[11px] font-bold transition-all cursor-pointer {{ $filterStatus === $val ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
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
                                <div class="text-ink-400 tabular-nums">
                                    Harga Rp {{ number_format((float) ($item['harga_beli'] ?? 0), 0, ',', '.') }}
                                </div>
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
