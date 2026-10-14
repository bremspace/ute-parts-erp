<!-- Cart Content Partial - Used in both mobile bottom sheet and desktop side panel -->
<div class="space-y-3">

    <!-- Cart Items List (Scrollable) -->
    <div class="space-y-2.5 max-h-[42vh] overflow-y-auto pr-1">
        {{-- [B-02/P0-1] Banner backorder DIHAPUS (kebijakan owner final: stok 0
             tidak bisa dipilih) — addToCart()/updateQty() sudah hard-block, jadi
             tidak mungkin ada item stok kosong dari jalur UI. --}}
        @forelse($cart as $key => $item)
            <div
                wire:key="cart-item-mobile-{{ $key }}"
                x-data="{
                    flexOpen: false,
                    flexHarga: '{{ number_format($item['harga'] ?? 0, 0, '', '') }}',
                    saveFlexHarga(key) {
                        const raw = this.flexHarga.replace(/\./g, '').replace(/,/g, '.');
                        const val = Number(raw);
                        if (isNaN(val)) {
                            this.$dispatch('alert', { type: 'error', message: 'Harga tidak valid' });
                            return;
                        }
                        if (val < {{ canSeeField('harga_beli') ? ($item['harga_beli'] ?? $item['harga'] ?? 0) : ($item['harga'] ?? 0) }}) {
                            this.$dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal (Rp {{ number_format($item['harga_beli'] ?? $item['harga'] ?? 0, 0, '', '') }})' });
                            return;
                        }
                        @this.setHargaFleksibel('{{ $key }}', val);
                        this.flexOpen = false;
                        this.flexHarga = '{{ number_format($item['harga'] ?? 0, 0, '', '') }}';
                    }
                }"
                class="p-3 rounded-xl bg-ink-50/80 dark:bg-white/[0.03] border border-ink-200/60 dark:border-white/5 flex items-center justify-between gap-3 transition-colors"
            >
                <div class="flex-1 min-w-0">
                    <h5 class="text-sm font-semibold text-ink-900 dark:text-white truncate">{{ $item['nama'] }}</h5>
                    <div class="flex items-center gap-2 mt-0.5 text-xs text-ink-500 dark:text-ink-400 flex-wrap">
                        @if(isset($item['flex']) && $item['flex'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-up-amber/20 text-up-amber text-[10px] font-medium border border-up-amber/30">
                                Fleksibel
                            </span>
                        @endif
                        @if($item['varian'] !== 'Standar')
                            <span class="px-1.5 py-0.2 rounded bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 text-[10px]">{{ $item['varian'] }}</span>
                        @endif
                        {{-- [B-02/P0-1] Badge stok habis per item (bukan backorder) --}}
                        @if(($item['stok_max'] ?? 1) <= 0)
                            <x-prism.status-pill status="batal">Stok habis</x-prism.status-pill>
                        @endif
                    </div>
                    <!-- Price & Flex Control -->
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        @if(isset($item['flex']) && $item['flex'])
                            <span
                                :class="flexSet['{{ $key }}'] ? 'text-ink-900 dark:text-white' : 'text-up-amber'"
                                class="tabular-nums font-semibold text-sm"
                            >
                                Rp {{ number_format($item['harga'], 0, ',', '.') }}
                            </span>
                            @if(!flexSet['{{ $key }}'])
                                <span class="text-up-amber text-[10px] font-medium">(belum diatur)</span>
                            @endif
                            @if($canHargaFleksibel ?? false)
                                <button
                                    type="button"
                                    @click="flexOpen = true"
                                    class="px-2 py-1 rounded-lg text-[10px] font-semibold text-up-amber border border-up-amber/40 hover:bg-up-amber/10 transition-colors cursor-pointer whitespace-nowrap"
                                    aria-label="Atur harga jual fleksibel"
                                    title="Atur Harga (Superadmin)"
                                >
                                    Atur Harga
                                </button>
                            @else
                                <span class="px-2 py-1 rounded-lg text-[10px] font-medium text-ink-500 bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5 cursor-not-allowed" title="Akses superadmin diperlukan">
                                    Atur Harga
                                </span>
                            @endif

                            <!-- Inline Popover: Atur Harga -->
                            <div
                                x-show="flexOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 transform -translate-y-1"
                                x-transition:enter-end="opacity-100 transform translate-y-0"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 transform translate-y-0"
                                x-transition:leave-end="opacity-0 transform -translate-y-1"
                                @click.outside="flexOpen = false"
                                @keydown.escape="flexOpen = false"
                                class="absolute z-20 mt-1 min-w-[200px] glass-panel p-3 rounded-xl border border-black/10 dark:border-white/10 shadow-lg"
                                style="max-width: 240px;"
                                x-cloak
                            >
                                <label class="block text-[10px] font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Harga Jual (Rp)</label>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    x-format-number
                                    x-model="flexHarga"
                                    @keydown.enter.prevent="saveFlexHarga('{{ $key }}')"
                                    x-ref="flexInput"
                                    placeholder="0"
                                    class="w-full px-3 py-2 rounded-lg glass-input text-sm font-bold tabular-nums text-ink-900 dark:text-white"
                                    aria-label="Harga jual fleksibel"
                                />
                                <!-- Harga Beli ditampilkan hanya jika user punya permission -->
@cansee('harga_beli')
    <p class="text-[10px] text-up-amber/90 mt-1.5">Min: Rp {{ number_format($item['harga_beli'] ?? $item['harga'], 0, ',', '.') }}</p>
@cannotsee('harga_beli')
    <p class="text-[10px] text-up-amber/90 mt-1.5">Min: —</p>
@endcansee
                                <div class="flex gap-2 mt-2.5">
                                    <button
                                        type="button"
                                        @click="saveFlexHarga('{{ $key }}')"
                                        class="flex-1 py-2 rounded-lg bg-up-amber hover:opacity-90 text-ink-950 font-bold text-xs transition-[opacity,transform] duration-100 active:scale-[0.98] cursor-pointer min-h-[40px]"
                                    >
                                        Simpan
                                    </button>
                                    <button
                                        type="button"
                                        @click="flexOpen = false"
                                        class="flex-1 py-2 rounded-lg bg-black/5 hover:bg-black/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[40px] border border-black/5 dark:border-white/5"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </div>
                        @else
                            <span class="tabular-nums font-semibold text-ink-700 dark:text-ink-300">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                        @endif
                    </div>

                    {{-- [F2-3] SN wajib utk produk sn=true --}}
                    @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                </div>

                <!-- Quantity Controls & Line Subtotal -->
                <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                    <div class="flex items-center gap-1.5">
                        <button
                            wire:click="updateQty('{{ $key }}', -1)"
                            class="w-8 h-8 rounded-lg bg-black/5 hover:bg-black/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-sm font-bold transition-all cursor-pointer active:scale-95 min-h-[36px] min-w-[36px]"
                            aria-label="Kurangi jumlah">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4" />
                            </svg>
                        </button>
                        <span class="w-7 text-center text-sm font-bold tabular-nums text-ink-900 dark:text-white">{{ $item['qty'] }}</span>
                        <button
                            wire:click="updateQty('{{ $key }}', 1)"
                            class="w-8 h-8 rounded-lg bg-black/5 hover:bg-black/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-sm font-bold transition-all cursor-pointer active:scale-95 min-h-[36px] min-w-[36px]"
                            aria-label="Tambah jumlah">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                        <button
                            wire:click="removeFromCart('{{ $key }}')"
                            class="w-8 h-8 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red flex items-center justify-center transition-all cursor-pointer active:scale-95 min-h-[36px] min-w-[36px]"
                            aria-label="Hapus item">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <span class="text-xs font-bold text-ink-900 dark:text-white tabular-nums">
                        Rp {{ number_format($item['subtotal'] ?? ($item['harga'] * $item['qty']), 0, ',', '.') }}
                    </span>
                </div>
            </div>
        @empty
            <div class="py-12 text-center text-ink-400">
                <svg class="w-16 h-16 mx-auto mb-3 opacity-30 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <p class="text-sm font-semibold text-ink-700 dark:text-ink-300">Keranjang kosong</p>
                <p class="text-xs text-ink-500 mt-1">Tambah produk dari daftar katalog</p>
            </div>
        @endforelse
    </div>

    <!-- Payment Summary -->
    <div class="border-t border-ink-200/60 dark:border-white/5 pt-4 space-y-2.5">
        <div class="flex justify-between text-xs sm:text-sm">
            <span class="text-ink-500 dark:text-ink-400">Subtotal</span>
            <span class="font-semibold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($total ?? 0, 0, ',', '.') }}</span>
        </div>

        @if($diskonTotal > 0)
            <div class="flex justify-between text-xs sm:text-sm text-up-amber">
                <span>Diskon</span>
                <span class="font-semibold tabular-nums">-Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="flex justify-between items-center text-base font-bold border-t border-ink-200/60 dark:border-white/10 pt-2.5">
            <span class="text-ink-900 dark:text-white">TOTAL</span>
            <span class="text-up-mint tabular-nums text-lg font-black">Rp {{ number_format($totalBayar ?? $total ?? 0, 0, ',', '.') }}</span>
        </div>

        @if($customer && $customer->tierMembership)
            <div class="text-xs text-up-mint flex items-center gap-1.5 font-medium">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>Diskon {{ $customer->tierMembership->diskon_persen }}% aktif untuk {{ $customer->tierMembership->nama }}</span>
            </div>
        @endif
    </div>

    <!-- Action Buttons - Touch-friendly -->
    <div class="flex gap-2 pt-1">
        <button
            wire:click="clearCart"
            class="flex-1 py-3 rounded-xl bg-ink-100 hover:bg-up-red/15 dark:bg-white/5 dark:hover:bg-up-red/20 text-ink-600 hover:text-up-red dark:text-ink-300 dark:hover:text-up-red font-semibold text-xs transition-colors cursor-pointer min-h-[44px] border border-ink-200/60 dark:border-white/5"
        >
            Kosongkan
        </button>
        <button
            wire:click="openPaymentModal"
            class="flex-1 py-3 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark hover:to-indigo-700 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer min-h-[44px] border border-up-primary/40 transition-all active:scale-95"
        >
            Bayar (F4)
        </button>
    </div>

    <!-- Transaction Hold Buttons -->
    <div class="flex gap-2 pt-1 border-t border-ink-200/60 dark:border-white/5">
        <button
            wire:click="tahanTransaksi"
            class="flex-1 py-2.5 rounded-xl bg-up-amber/10 hover:bg-up-amber/20 text-up-amber border border-up-amber/30 font-semibold text-xs cursor-pointer min-h-[44px] transition-colors"
        >
            Tahan (F6)
        </button>
        <button
            wire:click="$toggle('showDitahanPanel')"
            class="flex-1 py-2.5 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/30 font-semibold text-xs cursor-pointer min-h-[44px] transition-colors"
        >
            Ditahan ({{ count($ditahanList ?? []) }})
        </button>
    </div>
</div>