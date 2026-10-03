<div>
    <!-- TAB 2: TRANSFER ANTAR GUDANG -->
        <div class="space-y-4">
            <x-prism.data-table :headers="['No. Transfer', 'Asal', 'Tujuan', 'Jumlah Item', 'Pengirim', 'Status', 'Aksi']">
                @forelse($transfers as $trf)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">
                            <button
                                type="button"
                                wire:click="bukaDetail({{ $trf->id }})"
                                class="hover:text-up-primary hover:underline transition-colors text-left font-mono font-bold cursor-pointer"
                            >
                                {{ $trf->no_transfer }}
                            </button>
                        </td>
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
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    wire:click="bukaDetail({{ $trf->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-semibold text-xs transition-all cursor-pointer"
                                >
                                    Detail
                                </button>
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
                            </div>
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Asal</label>
                        <select wire:model.live="transferGudangAsalId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                            <option value="" class="bg-ink-900">Pilih Gudang Asal...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan</label>
                        <select wire:model.live="transferGudangTujuanId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
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
                            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 sm:items-center">
                                <div class="flex-1 flex gap-1.5 items-center">
                                    <div class="flex-1 relative">
                                        <input type="text"
                                               wire:model="transferItems.{{ $index }}.produk_nama"
                                               wire:click="$dispatch('buka-pencarian-produk', { targetIndex: {{ $index }}, context: 'transfer' })"
                                               class="w-full px-3 py-2 rounded-xl glass-input text-xs min-h-[44px] cursor-pointer"
                                               placeholder="Klik untuk cari produk..."
                                               readonly>
                                        @if($row['produk_id'])
                                            <button type="button"
                                                    wire:click="$set('transferItems.{{ $index }}.produk_id', null); $set('transferItems.{{ $index }}.produk_nama', ''); $set('transferItems.{{ $index }}.sku_variant_id', null)"
                                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="$dispatch('buka-pencarian-produk', { targetIndex: {{ $index }}, context: 'transfer' })"
                                        class="px-2.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white border border-white/10 text-xs min-h-[44px] flex items-center justify-center cursor-pointer shrink-0 transition"
                                        title="Cari Produk Lengkap"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="w-full sm:w-32">
                                    <select wire:model="transferItems.{{ $index }}.rak_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs min-h-[44px]">
                                        <option value="" class="bg-ink-900">Rak Tujuan</option>
                                        @foreach($raks as $rk)
                                            @if($rk->gudang_id == $transferGudangTujuanId)
                                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full sm:w-24">
                                    <input
                                        type="number"
                                        wire:model.live="transferItems.{{ $index }}.jumlah"
                                        data-trf-qty="{{ $index }}"
                                        min="1"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-center tabular-nums min-h-[44px]"
                                    />
                                </div>
                                <div class="flex items-center justify-end">
                                    <button
                                        type="button"
                                        wire:click="removeTransferRow({{ $index }})"
                                        class="p-2 text-up-red hover:bg-white/5 rounded-lg text-xs min-h-[44px] flex items-center justify-center cursor-pointer"
                                    >
                                        ✕
                                    </button>
                                </div>
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

    <!-- MODAL: DETAIL TRANSFER & MUTASI STOK -->
    @if($showDetailModal && $selectedTransfer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] overflow-y-auto space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-bold text-white font-mono">{{ $selectedTransfer->no_transfer }}</h3>
                        <x-prism.status-pill :status="$selectedTransfer->status" />
                    </div>
                    <button type="button" wire:click="tutupDetail" class="text-ink-400 hover:text-white text-base">✕</button>
                </div>

                <!-- Info Header Ringkasan -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Gudang Asal</span>
                        <strong class="text-white">{{ $selectedTransfer->gudangAsal?->nama ?? '-' }}</strong>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Gudang Tujuan</span>
                        <strong class="text-white">{{ $selectedTransfer->gudangTujuan?->nama ?? '-' }}</strong>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Pengirim / Pembuat</span>
                        <strong class="text-white">{{ $selectedTransfer->pengirim?->name ?? '-' }}</strong>
                        @if($selectedTransfer->tanggal_kirim)
                            <span class="text-[10px] text-ink-400 block">{{ $selectedTransfer->tanggal_kirim->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Penerima</span>
                        <strong class="text-white">{{ $selectedTransfer->penerima?->name ?? '-' }}</strong>
                        @if($selectedTransfer->tanggal_terima)
                            <span class="text-[10px] text-ink-400 block">{{ $selectedTransfer->tanggal_terima->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                </div>

                @if($selectedTransfer->catatan)
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs">
                        <span class="text-ink-400 block text-[11px] font-semibold">Catatan:</span>
                        <p class="text-ink-200 mt-0.5">{{ $selectedTransfer->catatan }}</p>
                    </div>
                @endif

                <!-- Daftar Item yang Ditransfer -->
                <div>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Item Sparepart yang Ditransfer</h4>
                    <div class="rounded-xl border border-white/10 overflow-hidden">
                        <table class="w-full text-xs text-left text-ink-100">
                            <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold">
                                <tr>
                                    <th class="p-3">Produk</th>
                                    <th class="p-3">SKU / Varian</th>
                                    <th class="p-3">Rak Tujuan</th>
                                    <th class="p-3 text-right">Jumlah Unit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($selectedTransfer->items as $item)
                                    <tr>
                                        <td class="p-3 font-medium text-white">{{ $item->produk?->nama ?? '-' }}</td>
                                        <td class="p-3 text-ink-300 font-mono text-[11px]">
                                            {{ $item->skuVariant?->sku ?? '-' }}
                                            @if($item->skuVariant?->nama_varian)
                                                <span class="text-ink-400">({{ $item->skuVariant->nama_varian }})</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-ink-300">{{ $item->rak?->kode ?? '-' }}</td>
                                        <td class="p-3 text-right font-bold text-white tabular-nums">{{ $item->jumlah }} unit</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Riwayat Mutasi Stok (StokLog) Terkait -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider">Audit Mutasi Stok (StokLog)</h4>
                        <span class="text-[11px] text-ink-400">{{ $mutasiLogs->count() }} mutasi tercatat</span>
                    </div>

                    @if($mutasiLogs->isEmpty())
                        <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 text-center text-xs text-ink-400">
                            Belum ada pergerakan stok tercatat (status draft / belum dikirim).
                        </div>
                    @else
                        <div class="rounded-xl border border-white/10 overflow-hidden">
                            <table class="w-full text-xs text-left text-ink-100">
                                <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold">
                                    <tr>
                                        <th class="p-3">Waktu</th>
                                        <th class="p-3">Gudang</th>
                                        <th class="p-3">Produk</th>
                                        <th class="p-3">Jenis</th>
                                        <th class="p-3 text-center">Sebelum</th>
                                        <th class="p-3 text-center">Perubahan</th>
                                        <th class="p-3 text-center">Setelah</th>
                                        <th class="p-3">User</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($mutasiLogs as $log)
                                        <tr>
                                            <td class="p-3 text-ink-400 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="p-3 text-ink-300">{{ $log->gudang?->nama ?? '-' }}</td>
                                            <td class="p-3 text-white font-medium">{{ $log->produk?->nama ?? '-' }}</td>
                                            <td class="p-3">
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $log->jenis === 'transfer_masuk' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-primary/20 text-up-primary' }}">
                                                    {{ $log->jenis === 'transfer_masuk' ? 'Transfer Masuk (+)' : 'Transfer Keluar (-)' }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-center tabular-nums text-ink-300">{{ $log->jumlah_sebelum }}</td>
                                            <td class="p-3 text-center tabular-nums font-bold {{ $log->perubahan > 0 ? 'text-up-mint' : 'text-up-red' }}">
                                                {{ $log->perubahan > 0 ? "+{$log->perubahan}" : $log->perubahan }}
                                            </td>
                                            <td class="p-3 text-center tabular-nums font-bold text-white">{{ $log->jumlah_setelah }}</td>
                                            <td class="p-3 text-ink-400">{{ $log->user?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" wire:click="tutupDetail" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
