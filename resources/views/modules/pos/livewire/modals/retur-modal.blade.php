{{-- MODAL RETUR PENJUALAN --}}
@if($showReturPenjualanModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto">
        <div class="w-full max-w-2xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-red/10 border border-up-red/20 flex items-center justify-center text-up-red">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Retur Barang Penjualan</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Pengembalian barang pelanggan, pemulihan stok & pembukuan kas/piutang</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupModalRetur"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <div class="space-y-4 flex-1 overflow-y-auto pr-1 text-xs">
                @if(!empty($returItemInputs))
                    <div class="space-y-2">
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px]">Pilih Barang yang Diretur & Jumlah</label>
                        <div class="rounded-2xl border border-ink-200/80 dark:border-white/10 divide-y divide-ink-200/60 dark:divide-white/5 overflow-hidden">
                            @foreach($returItemInputs as $itemId => $item)
                                <div class="p-3.5 bg-ink-50/70 dark:bg-white/[0.02] space-y-2.5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-bold text-ink-900 dark:text-white text-xs sm:text-sm truncate">{{ $item['produk_nama'] }}</div>
                                            <div class="text-[11px] text-ink-500 mt-0.5">
                                                Qty Beli: <span class="font-semibold text-ink-700 dark:text-ink-300">{{ $item['qty_beli'] }}</span> · Harga: <span class="font-semibold tabular-nums text-up-mint">Rp {{ number_format($item['harga_final'], 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        <div class="w-32 flex-shrink-0">
                                            <label class="text-[10px] font-bold text-ink-600 dark:text-ink-400 uppercase tracking-wider block mb-1">Qty Retur</label>
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $item['qty_beli'] }}"
                                                wire:model.live="returItemInputs.{{ $itemId }}.jumlah"
                                                class="w-full px-3 py-1.5 rounded-xl glass-input text-xs font-black tabular-nums text-center min-h-[38px]"
                                            />
                                        </div>
                                    </div>
                                    @if(($returItemInputs[$itemId]['jumlah'] ?? 0) > 0)
                                        <div>
                                            <label class="text-[10px] font-bold text-ink-600 dark:text-ink-400 uppercase tracking-wider block mb-1">
                                                Nomor Seri yang diretur (Pisahkan koma / baris baru jika produk ber-SN):
                                            </label>
                                            <textarea
                                                wire:model="returItemInputs.{{ $itemId }}.sn_raw"
                                                rows="2"
                                                placeholder="Contoh: SN123, SN124..."
                                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono text-ink-900 dark:text-white"
                                            ></textarea>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Metode Pengembalian *</label>
                        <select wire:model="returMetodePengembalian" class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/50 dark:bg-ink-900 min-h-[42px]">
                            <option value="kas" class="bg-white dark:bg-ink-900">Uang Tunai / Kas Laci (110-01)</option>
                            <option value="piutang" class="bg-white dark:bg-ink-900">Potong Piutang Pelanggan (120-01)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Alasan Retur *</label>
                        <input
                            type="text"
                            wire:model="returAlasan"
                            placeholder="Contoh: Barang cacat / salah tipe HP"
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white min-h-[42px]"
                        />
                    </div>
                </div>
            </div>

            <div class="flex gap-2.5 pt-4 border-t border-ink-200/70 dark:border-white/10 mt-4">
                <button
                    type="button"
                    wire:click="tutupModalRetur"
                    class="flex-1 py-3 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[44px] transition-colors"
                >
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="simpanReturPenjualan"
                    class="flex-1 py-3 rounded-xl bg-gradient-to-r from-up-red to-rose-600 text-white font-black text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-red/25 transition-transform active:scale-[0.98]"
                >
                    Proses Retur Penjualan
                </button>
            </div>
        </div>
    </div>
@endif
