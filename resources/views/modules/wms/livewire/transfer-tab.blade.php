<div>
    <!-- TAB 2: TRANSFER ANTAR GUDANG -->
        <div class="space-y-4">
            <x-prism.data-table :headers="['No. Transfer', 'Asal', 'Tujuan', 'Jumlah Item', 'Pengirim', 'Status', 'Aksi']">
                @forelse($transfers as $trf)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $trf->no_transfer }}</td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $trf->gudangAsal?->nama }}</td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $trf->gudangTujuan?->nama }}</td>
                        <td class="py-3.5 px-4 text-white font-bold tabular-nums">{{ $trf->items->sum('jumlah') }} unit</td>
                        <td class="py-3.5 px-4 text-ink-400">
                            {{ $trf->pengirim?->name }}
                            @if($trf->approver && $trf->approver?->id !== $trf->user_pengirim_id)
                                <span class="block text-[10px] text-up-mint">disetujui {{ $trf->approver->name }}</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            <x-prism.status-pill :status="$trf->status" />
                        </td>
                        <td class="py-3.5 px-4">
                            @if($trf->status === 'draft')
                                <button
                                    wire:click="kirimTransfer({{ $trf->id }})"
                                    class="px-3 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-semibold text-xs transition-all cursor-pointer"
                                >
                                    Kirim
                                </button>
                            @elseif($trf->status === 'dikirim')
                                <button
                                    wire:click="terimaTransfer({{ $trf->id }})"
                                    class="px-3 py-1 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs transition-all cursor-pointer"
                                >
                                    Konfirmasi Terima
                                </button>
                            @else
                                <span class="text-ink-500 text-[11px]">- Selesai -</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-ink-400">
                            Belum ada riwayat transfer antar gudang
                        </td>
                    </tr>
                @endforelse
            </x-prism.data-table>
        </div>

    <!-- MODAL: BUAT TRANSFER BARU [T-41] -->
    @if($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <form
                class="w-full max-w-2xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative"
                x-data="{
                    stokInfo: {{ \Illuminate\Support\Js::from($transferStokRows) }},
                    errors: {},
                    validasiStok() {
                        this.errors = {};
                        let ok = true;
                        this.$el.querySelectorAll('[data-trf-qty]').forEach(inp => {
                            const i = Number(inp.dataset.trfQty);
                            const qty = Number(inp.value) || 0;
                            const tersedia = this.stokInfo[i] ? Number(this.stokInfo[i].stok_tersedia) : 0;
                            if (qty > tersedia) {
                                this.errors[i] = 'Qty melebihi stok tersedia di gudang sumber (tersedia: ' + tersedia + ' unit)';
                                ok = false;
                            }
                        });
                        return ok;
                    }
                }"
                @submit.prevent="validasiStok() && $wire.saveTransfer()"
            >
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Buat Transfer Antar Gudang</h3>
                    <button type="button" wire:click="$set('showTransferModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Asal</label>
                        <select wire:model.live="transferGudangAsalId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang Asal...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan</label>
                        <select wire:model.live="transferGudangTujuanId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang Tujuan...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Items Row -->
                <div class="space-y-3 mb-4 max-h-72 overflow-y-auto pr-1">
                    <div class="flex justify-between items-center text-xs font-semibold text-ink-400">
                        <span>Daftar Sparepart yang Ditransfer</span>
                        <button type="button" wire:click="addTransferRow" class="text-up-primary hover:text-indigo-400 font-bold">+ Tambah Baris</button>
                    </div>

                    @foreach($transferItems as $index => $row)
                        @php
                            $trfInfo = $transferStokRows[$index] ?? null;
                        @endphp
                        <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                            <div class="flex gap-3 items-center">
                                <div class="flex-1">
                                    <select
                                        wire:model="transferItems.{{ $index }}.produk_id"
                                        wire:change="transferProdukDipilih({{ $index }})"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs"
                                    >
                                        <option value="" class="bg-ink-900">Pilih Produk...</option>
                                        @foreach($allProducts as $p)
                                            <option value="{{ $p->id }}" class="bg-ink-900">{{ $p->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-32">
                                    <select wire:model="transferItems.{{ $index }}.rak_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs">
                                        <option value="" class="bg-ink-900">Rak Tujuan</option>
                                        @foreach($raks as $rk)
                                            @if($rk->gudang_id == $transferGudangTujuanId)
                                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-24">
                                    <input
                                        type="number"
                                        wire:model.live="transferItems.{{ $index }}.jumlah"
                                        data-trf-qty="{{ $index }}"
                                        min="1"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-center tabular-nums"
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="removeTransferRow({{ $index }})"
                                    class="p-2 text-up-red hover:bg-white/5 rounded-lg text-xs"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- [T-41] Info stok real-time per item -->
                            <div class="grid grid-cols-3 gap-2 mt-2 text-[10px] text-ink-400">
                                <div class="rounded-lg bg-white/[0.03] px-2 py-1.5">
                                    Stok Sumber:
                                    <strong class="text-white tabular-nums">{{ $trfInfo ? $trfInfo['stok_sumber'].' unit' : '-' }}</strong>
                                    @if($trfInfo && $trfInfo['stok_dikunci'] > 0)
                                        <span class="text-up-amber">({{ $trfInfo['stok_dikunci'] }} terkunci)</span>
                                    @endif
                                </div>
                                <div class="rounded-lg bg-white/[0.03] px-2 py-1.5">
                                    Tersedia:
                                    <strong class="text-up-mint tabular-nums">{{ $trfInfo ? $trfInfo['stok_tersedia'].' unit' : '-' }}</strong>
                                </div>
                                <div class="rounded-lg bg-white/[0.03] px-2 py-1.5">
                                    Est. Stok Tujuan:
                                    <strong class="text-white tabular-nums">{{ $trfInfo ? ($trfInfo['stok_tujuan'].' + '.$row['jumlah'].' = '.$trfInfo['estimasi_tujuan']) : '-' }}</strong>
                                </div>
                            </div>

                            <span
                                x-show="errors['{{ $index }}']"
                                x-text="errors['{{ $index }}']"
                                class="block mt-1.5 text-[10px] font-bold text-up-red"
                            ></span>

                            @error('transferItems.'.$index.'.jumlah')
                                <span class="block mt-1.5 text-[10px] font-bold text-up-red">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan Pengiriman</label>
                    <textarea wire:model="transferCatatan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs" placeholder="Misal: Restok darurat LCD iPhone 13..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" wire:click="$set('showTransferModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="submit" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Draft Transfer</button>
                </div>
            </form>
        </div>
    @endif
</div>
