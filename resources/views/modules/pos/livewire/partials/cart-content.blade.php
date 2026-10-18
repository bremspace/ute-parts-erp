<!-- Cart Content Partial - Used in mobile bottom sheet & desktop checkout -->
<div class="space-y-4">
    <!-- Cart Items List (Scrollable) -->
    <div class="space-y-2.5 max-h-[44vh] overflow-y-auto pr-1">
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
                class="p-3.5 rounded-2xl bg-ink-50/80 dark:bg-white/[0.03] border border-ink-200/70 dark:border-white/5 flex flex-col gap-2.5 transition-colors"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <h5 class="text-xs sm:text-sm font-semibold text-ink-900 dark:text-white truncate leading-snug">
                            {{ $item['nama'] }}
                        </h5>
                        <div class="flex items-center gap-1.5 mt-1 text-[11px] flex-wrap">
                            @if(isset($item['flex']) && $item['flex'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-up-amber/15 text-up-amber text-[10px] font-bold border border-up-amber/30">
                                    Fleksibel
                                </span>
                            @endif
                            @if(($item['varian'] ?? 'Standar') !== 'Standar')
                                <span class="px-2 py-0.5 rounded-md bg-ink-200/80 dark:bg-white/10 text-ink-700 dark:text-ink-300 text-[10px] font-medium">
                                    {{ $item['varian'] }}
                                </span>
                            @endif
                            @if(($item['stok_max'] ?? 1) <= 0)
                                <span class="px-2 py-0.5 rounded-full bg-up-red/15 text-up-red text-[10px] font-bold border border-up-red/30">
                                    Stok habis
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Delete Button -->
                    <button
                        type="button"
                        wire:click="removeFromCart('{{ $key }}')"
                        class="w-7 h-7 rounded-lg text-ink-400 hover:text-up-red hover:bg-up-red/10 flex items-center justify-center transition-colors cursor-pointer active:scale-90"
                        title="Hapus dari keranjang"
                        aria-label="Hapus item"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>

                <!-- Price, Stepper & Line Total -->
                <div class="flex items-center justify-between gap-2 pt-1 border-t border-ink-200/50 dark:border-white/5">
                    <!-- Price / Flex Price -->
                    <div class="relative">
                        @if(isset($item['flex']) && $item['flex'])
                            <div class="flex items-center gap-1.5">
                                <span
                                    :class="flexSet['{{ $key }}'] ? 'text-ink-900 dark:text-white' : 'text-up-amber'"
                                    class="tabular-nums font-bold text-xs"
                                >
                                    Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                </span>
                                @if($canHargaFleksibel ?? false)
                                    <button
                                        type="button"
                                        @click="flexOpen = true"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold text-up-amber bg-up-amber/10 border border-up-amber/30 hover:bg-up-amber/20 transition-colors cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                @endif
                            </div>

                            <!-- Popover Atur Harga -->
                            <div
                                x-show="flexOpen"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.outside="flexOpen = false"
                                @keydown.escape="flexOpen = false"
                                class="absolute left-0 bottom-full mb-1 z-30 min-w-[210px] p-3 rounded-2xl glass-panel border border-ink-200/90 dark:border-white/10 shadow-2xl"
                                x-cloak
                            >
                                <label class="block text-[10px] font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1">Ubah Harga (Rp)</label>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    x-format-number
                                    x-model="flexHarga"
                                    @keydown.enter.prevent="saveFlexHarga('{{ $key }}')"
                                    class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs font-bold tabular-nums text-ink-900 dark:text-white mb-1.5"
                                />
                                @cansee('harga_beli')
                                    <p class="text-[9px] text-up-amber font-medium mb-2">Batas Modal: Rp {{ number_format($item['harga_beli'] ?? $item['harga'], 0, ',', '.') }}</p>
                                @endcansee
                                <div class="flex gap-1.5">
                                    <button
                                        type="button"
                                        @click="saveFlexHarga('{{ $key }}')"
                                        class="flex-1 py-1.5 rounded-lg bg-up-amber hover:opacity-95 text-ink-950 font-bold text-[11px] cursor-pointer"
                                    >
                                        Simpan
                                    </button>
                                    <button
                                        type="button"
                                        @click="flexOpen = false"
                                        class="px-2.5 py-1.5 rounded-lg bg-ink-200/80 dark:bg-white/10 text-ink-700 dark:text-ink-300 text-[11px] cursor-pointer"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </div>
                        @else
                            <span class="tabular-nums font-semibold text-xs text-ink-600 dark:text-ink-400">
                                @ {{ number_format($item['harga'], 0, ',', '.') }}
                            </span>
                        @endif
                    </div>

                    <!-- Stepper & Subtotal -->
                    <div class="flex items-center gap-3">
                        <div class="flex items-center bg-ink-100 dark:bg-white/5 rounded-xl p-0.5 border border-ink-200/80 dark:border-white/10">
                            <button
                                type="button"
                                wire:click="updateQty('{{ $key }}', -1)"
                                class="w-7 h-7 rounded-lg hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                aria-label="Kurangi"
                            >-</button>
                            <span class="w-7 text-center text-xs font-bold tabular-nums text-ink-900 dark:text-white">{{ $item['qty'] }}</span>
                            <button
                                type="button"
                                wire:click="updateQty('{{ $key }}', 1)"
                                class="w-7 h-7 rounded-lg hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                aria-label="Tambah"
                            >+</button>
                        </div>
                        <span class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white tabular-nums min-w-[70px] text-right">
                            Rp {{ number_format($item['subtotal'] ?? ($item['harga'] * $item['qty']), 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- [F2-3] SN wajib utk produk sn=true --}}
                @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
            </div>
        @empty
            <div class="py-12 text-center text-ink-400">
                <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 mx-auto mb-2 flex items-center justify-center text-ink-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <p class="text-xs font-semibold text-ink-700 dark:text-ink-300">Keranjang masih kosong</p>
                <p class="text-[11px] text-ink-500 mt-0.5">Pilih produk dari katalog atau scan barcode</p>
            </div>
        @endforelse
    </div>

    <!-- Payment Summary -->
    <div class="p-3.5 rounded-2xl bg-ink-50/70 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/5 space-y-2">
        <div class="flex justify-between text-xs text-ink-600 dark:text-ink-400">
            <span>Subtotal</span>
            <span class="font-semibold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($total ?? 0, 0, ',', '.') }}</span>
        </div>

        @if(($diskonTotal ?? 0) > 0)
            <div class="flex justify-between text-xs text-up-amber">
                <span>Diskon</span>
                <span class="font-semibold tabular-nums">-Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="flex justify-between items-center text-sm font-bold border-t border-ink-200/80 dark:border-white/10 pt-2 text-ink-900 dark:text-white">
            <span>TOTAL AKHIR</span>
            <span class="text-up-mint tabular-nums text-lg font-black">Rp {{ number_format($totalBayar ?? $total ?? 0, 0, ',', '.') }}</span>
        </div>

        @if($customer && $customer->tierMembership)
            <div class="text-[11px] text-up-mint flex items-center gap-1.5 font-medium pt-1">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>Diskon member {{ $customer->tierMembership->diskon_persen }}% aktif ({{ $customer->tierMembership->nama }})</span>
            </div>
        @endif
    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-2 gap-2 pt-1">
        <button
            type="button"
            wire:click="clearCart"
            class="py-3 px-3 rounded-xl bg-ink-100 hover:bg-up-red/15 dark:bg-white/5 dark:hover:bg-up-red/20 text-ink-600 hover:text-up-red dark:text-ink-400 dark:hover:text-up-red font-semibold text-xs transition-colors cursor-pointer min-h-[44px] border border-ink-200/80 dark:border-white/5 active:scale-[0.98]"
        >
            Kosongkan
        </button>
        <button
            type="button"
            wire:click="openPaymentModal"
            class="py-3 px-3 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs shadow-md shadow-up-mint/25 cursor-pointer min-h-[44px] transition-all active:scale-[0.98] flex items-center justify-center gap-1.5"
        >
            <span>Bayar (F4)</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </button>
    </div>

    <!-- Transaction Hold & Resume -->
    <div class="grid grid-cols-2 gap-2 pt-1 border-t border-ink-200/60 dark:border-white/5">
        <button
            type="button"
            wire:click="tahanTransaksi"
            class="py-2.5 px-3 rounded-xl bg-up-amber/10 hover:bg-up-amber/20 text-up-amber border border-up-amber/30 font-bold text-xs cursor-pointer min-h-[42px] transition-colors active:scale-[0.98]"
        >
            Tahan (F6)
        </button>
        <button
            type="button"
            wire:click="$toggle('showDitahanPanel')"
            class="py-2.5 px-3 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/30 font-bold text-xs cursor-pointer min-h-[42px] transition-colors active:scale-[0.98]"
        >
            Ditahan ({{ count($ditahanList ?? []) }})
        </button>
    </div>
</div>
