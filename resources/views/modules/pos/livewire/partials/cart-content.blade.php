<!-- Cart Content Partial - Used in both mobile bottom sheet and desktop side panel -->
<div class="space-y-3">

    <!-- Cart Items List (Scrollable) -->
    <div class="space-y-2.5 max-h-[40vh] overflow-y-auto pr-1">
        @forelse($cart as $key => $item)
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <h5 class="text-sm font-semibold text-white truncate">{{ $item['nama'] }}</h5>
                    <div class="flex items-center gap-2 mt-0.5 text-xs text-ink-400">
                        <span class="tabular-nums">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                        @if($item['varian'] !== 'Standar')
                            <span class="px-1.5 py-0.2 rounded bg-white/5 text-ink-300 text-[10px]">{{ $item['varian'] }}</span>
                        @endif
                    </div>
                </div>

                <!-- Quantity Controls - Touch-friendly 44x44px minimum -->
                <div class="flex items-center gap-2">
                    <button
                        wire:click="updateQty('{{ $key }}', -1)"
                        class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-lg font-bold transition-all cursor-pointer active:scale-95 min-h-[44px] min-w-[44px]"
                        aria-label="Kurangi jumlah">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4" />
                        </svg>
                    </button>
                    <span class="w-10 text-center text-base font-bold tabular-nums text-white">{{ $item['qty'] }}</span>
                    <button
                        wire:click="updateQty('{{ $key }}', 1)"
                        class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-lg font-bold transition-all cursor-pointer active:scale-95 min-h-[44px] min-w-[44px]"
                        aria-label="Tambah jumlah">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                    <button
                        wire:click="removeFromCart('{{ $key }}')"
                        class="w-10 h-10 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red flex items-center justify-center transition-all cursor-pointer active:scale-95 min-h-[44px] min-w-[44px]"
                        aria-label="Hapus item">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        @empty
            <div class="py-12 text-center text-ink-400">
                <svg class="w-16 h-16 mx-auto mb-3 opacity-30 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <p class="text-sm">Keranjang kosong</p>
                <p class="text-xs text-ink-500 mt-1">Tambah produk dari daftar di samping</p>
            </div>
        @endforelse
    </div>

    <!-- Payment Summary -->
    <div class="border-t border-white/5 pt-4 space-y-3">
        <div class="flex justify-between text-sm">
            <span class="text-ink-400">Subtotal</span>
            <span class="font-semibold text-white tabular-nums">Rp {{ number_format($total ?? 0, 0, ',', '.') }}</span>
        </div>

        @if($diskonTotal > 0)
            <div class="flex justify-between text-sm text-up-amber">
                <span>Diskon</span>
                <span class="font-semibold tabular-nums">-Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="flex justify-between text-base font-bold border-t border-white/5 pt-3">
            <span class="text-white">TOTAL</span>
            <span class="text-up-mint tabular-nums">Rp {{ number_format($totalBayar ?? $total ?? 0, 0, ',', '.') }}</span>
        </div>

        @if($this->customer && $this->customer->tierMembership)
            <div class="text-xs text-up-mint flex items-center gap-1.5 font-medium">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>Diskon {{ $this->customer->tierMembership->diskon_persen }}% aktif untuk {{ $this->customer->tierMembership->nama }}</span>
            </div>
        @endif
    </div>

    <!-- Action Buttons - Touch-friendly -->
    <div class="flex gap-2 pt-2">
        <button
            wire:click="clearCart"
            class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-sm cursor-pointer min-h-[44px] border border-white/5"
        >
            Kosongkan
        </button>
        <button
            wire:click="openPaymentModal"
            class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-md cursor-pointer min-h-[44px]"
        >
            Bayar (F4)
        </button>
    </div>

    <!-- Transaction Hold Buttons -->
    <div class="flex gap-2 pt-2 border-t border-white/5">
        <button
            wire:click="holdTransaction"
            class="flex-1 py-3 rounded-xl bg-up-amber/15 hover:bg-up-amber/25 text-up-amber border border-up-amber/30 font-semibold text-sm cursor-pointer min-h-[44px]"
        >
            Tahan (F6)
        </button>
        <button
            wire:click="showHeldTransactions"
            class="flex-1 py-3 rounded-xl bg-up-primary/15 hover:bg-up-primary/25 text-up-primary border border-up-primary/30 font-semibold text-sm cursor-pointer min-h-[44px]"
        >
            Ditahan
        </button>
    </div>
</div>