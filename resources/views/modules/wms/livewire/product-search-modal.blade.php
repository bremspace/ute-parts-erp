<div>
    <!-- Modal -->
    <div x-data="{ show: @entangle('isOpen') }"
         x-on:open-product-search-modal.window="show = true"
         x-on:close-product-search-modal.window="show = false"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-[70] overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true">

        <!-- Backdrop -->
        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
             wire:click="closeModal"
             @click="show = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-3xl glass-panel border border-white/10 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl bg-[#0e1017]/95 text-white">

                <!-- Header -->
                <div class="px-6 pt-6 pb-4 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-white" id="modal-title">
                            Cari Produk
                        </h3>
                        <p class="text-xs text-ink-400 mt-0.5">
                            Cari produk berdasarkan nama, barcode, SKU, atau tipe HP kompatibel.
                        </p>
                    </div>
                    <button type="button"
                            wire:click="closeModal"
                            @click="show = false"
                            class="p-1 rounded-lg text-ink-400 hover:text-white hover:bg-white/5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="p-6 pb-3">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="h-4 w-4 text-ink-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl glass-input text-xs sm:text-sm text-white placeholder-ink-400 min-h-[44px]"
                               placeholder="Ketik nama produk, barcode, SKU, tipe HP..."
                               autofocus>
                    </div>
                </div>

                <!-- Product List -->
                <div class="max-h-96 overflow-y-auto px-6 pb-4 space-y-1.5">
                    @if($products->isEmpty())
                        <div class="text-center py-10">
                            <svg class="mx-auto h-10 w-10 text-ink-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <h4 class="mt-2 text-sm font-semibold text-white">Tidak ada produk ditemukan</h4>
                            <p class="mt-1 text-xs text-ink-400">Coba kata kunci lain atau periksa ejaan.</p>
                        </div>
                    @else
                        <div class="divide-y divide-white/5">
                            @foreach($products as $product)
                                @php
                                    $sku = $product->skuVariants->first()?->sku;
                                    $barcode = $product->barcode ?: $product->skuVariants->first()?->barcode;
                                @endphp
                                <div class="flex items-center justify-between gap-x-4 py-3 px-3 rounded-xl hover:bg-white/5 cursor-pointer transition group"
                                     wire:click="pilihProduk({{ $product->id }})">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-white group-hover:text-up-primary transition">
                                            {{ $product->nama }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-400">
                                            @if($barcode)
                                                <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-0.5 text-[11px] font-mono text-ink-300">
                                                    {{ $barcode }}
                                                </span>
                                            @endif
                                            @if($sku)
                                                <span class="inline-flex items-center rounded-md bg-up-primary/20 text-up-primary-light border border-up-primary/30 px-2 py-0.5 text-[11px] font-mono">
                                                    {{ $sku }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end shrink-0">
                                        <p class="text-sm font-bold text-white tabular-nums">
                                            Rp {{ number_format($product->harga_beli, 0, ',', '.') }}
                                        </p>
                                        <p class="text-xs text-ink-400">
                                            Stok: {{ $product->stokItems->sum('jumlah') }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Pagination -->
                @if($products->hasPages())
                    <div class="px-6 py-3 border-t border-white/5">
                        {{ $products->links() }}
                    </div>
                @endif

                <!-- Footer -->
                <div class="bg-white/[0.02] border-t border-white/10 px-6 py-3.5 flex justify-end">
                    <button type="button"
                            wire:click="closeModal"
                            @click="show = false"
                            class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.97]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
