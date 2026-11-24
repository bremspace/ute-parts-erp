{{-- Pencarian cepat di keranjang: mobile/tablet only, desktop sengaja tidak diubah --}}
<div class="mt-3 pt-3 border-t border-ink-100/80 dark:border-white/10"
     x-data="{
         cartSuggestOpen: false,
         cartSuggestPos: { top: 0, left: 0, width: 0, max: 0, up: false },
         positionCartSuggest() {
             const input = this.$refs.cartSearchInput;
             if (!input) return;
             const r = input.getBoundingClientRect();
             const gap = 8;
             const bottomReserve = 84; // ruang untuk sticky checkout + safe area
             const spaceBelow = window.innerHeight - r.bottom - bottomReserve - gap;
             const spaceAbove = r.top - gap - 8;
             const up = spaceBelow < 220 && spaceAbove > spaceBelow;
             const top = up ? r.top - gap : r.bottom + gap;
             const max = Math.min(360, up ? spaceAbove : spaceBelow);
             this.cartSuggestPos = {
                 top: top,
                 left: r.left,
                 width: r.width,
                 max: Math.max(140, max),
                 up: up
             };
         }
     }"
     @click.outside="cartSuggestOpen = false"
     @keydown.escape.window="cartSuggestOpen = false"
     @scroll.window="if (cartSuggestOpen) positionCartSuggest()"
     @resize.window="if (cartSuggestOpen) positionCartSuggest()">
    <div class="flex items-center justify-between gap-2 mb-2">
        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Tambah produk cepat</p>
        <span class="text-[9px] text-ink-400">Langsung tambah</span>
    </div>

    <div class="relative">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-400 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>

            <input
                x-ref="cartSearchInput"
                type="search"
                wire:model.live.debounce.250ms="cartSearch"
                wire:loading.class="opacity-60"
                @focus="cartSuggestOpen = true; positionCartSuggest()"
                @input="cartSuggestOpen = true; positionCartSuggest()"
                placeholder="Cari LCD, baterai, SKU, atau tipe HP…"
                autocomplete="off"
                aria-label="Cari produk untuk ditambahkan ke keranjang"
                class="w-full h-10 pl-9 pr-9 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-xs text-ink-900 dark:text-white placeholder:text-ink-400 outline-none focus:border-up-primary/60 focus:ring-2 focus:ring-up-primary/10 transition-all [appearance:textfield] [&::-webkit-search-cancel-button]:hidden">

            @if($cartSearch !== '')
                <button
                    type="button"
                    wire:click="$set('cartSearch', '')"
                    @click.stop="cartSuggestOpen = false"
                    aria-label="Hapus pencarian"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg text-ink-400 hover:text-up-red hover:bg-up-red/10 flex items-center justify-center text-xs cursor-pointer transition-colors">
                    ✕
                </button>
            @endif

            <svg wire:loading wire:target="cartSearch"
                 class="absolute right-9 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-up-primary animate-spin pointer-events-none"
                 fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
            </svg>
        </div>

        @if(mb_strlen(trim($cartSearch)) >= 2)
            <div x-show="cartSuggestOpen"
                 x-cloak
                 x-transition.opacity
                 :style="`top:${cartSuggestPos.top}px; left:${cartSuggestPos.left}px; width:${cartSuggestPos.width}px; max-height:${cartSuggestPos.max}px`"
                 :class="cartSuggestPos.up ? '-translate-y-full' : ''"
                 class="fixed z-[80] rounded-2xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 shadow-[0_18px_50px_rgba(0,0,0,0.22)] overflow-y-auto overscroll-contain">
                @if(count($cartSearchSuggestions) > 0)
                    <div class="p-1.5 space-y-0.5">
                        @foreach($cartSearchSuggestions as $suggestion)
                            <div class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-ink-50 dark:hover:bg-white/5 transition-colors">
                                @include('modules.marketplace.livewire.partials.cart-product-thumb', [
                                    'url' => $suggestion['gambar'] ?? null,
                                    'nama' => $suggestion['nama'],
                                    'class' => 'w-9 h-9',
                                ])

                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] font-bold leading-snug break-words text-ink-900 dark:text-white">{{ $suggestion['nama'] }}</p>
                                    <div class="flex items-center gap-1.5 flex-wrap text-[9px] mt-0.5">
                                        @if(!empty($suggestion['sku']))
                                            <span class="font-mono text-ink-400">{{ $suggestion['sku'] }}</span>
                                        @endif
                                        <span class="font-bold text-up-mint">Ready · {{ $suggestion['stok'] }}</span>
                                    </div>
                                    <p class="text-[10px] font-black text-up-primary tabular-nums mt-0.5">
                                        Rp {{ number_format($suggestion['harga'], 0, ',', '.') }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    wire:click="tambahDariPencarian({{ $suggestion['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="tambahDariPencarian({{ $suggestion['id'] }})"
                                    @click="cartSuggestOpen = false"
                                    aria-label="Tambah {{ $suggestion['nama'] }} ke keranjang"
                                    class="w-8 h-8 shrink-0 rounded-xl bg-up-primary/10 text-up-primary hover:bg-up-primary hover:text-white flex items-center justify-center text-base font-bold cursor-pointer transition-colors active:scale-[0.92] disabled:opacity-50">
                                    +
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-5 text-center">
                        <p class="text-[11px] font-bold text-ink-900 dark:text-white">Produk tidak ditemukan</p>
                        <p class="text-[10px] text-ink-400 mt-0.5">Coba kata kunci lain, misalnya tipe HP atau SKU.</p>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if(mb_strlen(trim($cartSearch)) < 2)
        <p class="mt-1.5 px-1 text-[9px] text-ink-400">Ketik minimal 2 karakter untuk melihat saran produk.</p>
    @endif
</div>
