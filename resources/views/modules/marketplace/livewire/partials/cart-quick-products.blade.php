{{-- Daftar produk cepat: 5 kartu per muat, auto-load saat discroll (infinite). Dipakai desktop & mobile. --}}
<div class="mt-5 pt-4 border-t border-ink-100/80 dark:border-white/10"
     x-data="{
         quickObserver: null,
         init() {
             if (!this.$refs.quickSentinel || !this.$wire) return;
             this.quickObserver = new IntersectionObserver(async (entries) => {
                 if (!entries[0].isIntersecting) return;
                 const before = (this.$wire.get('quickProducts') || []).length;
                 await this.$wire.call('loadMoreQuickProducts');
                 const after = (this.$wire.get('quickProducts') || []).length;
                 if (after === before) this.quickObserver.disconnect();
             }, { rootMargin: '160px 0px' });
             this.quickObserver.observe(this.$refs.quickSentinel);
         }
     }">
    <div class="flex items-end justify-between gap-2 mb-3">
        <div>
            <p class="text-[11px] sm:text-xs font-black uppercase tracking-wider text-ink-700 dark:text-white">Tambah Produk Cepat</p>
            <p class="text-[10px] text-ink-400">5 produk sekali gulir · langsung masuk keranjang</p>
        </div>
        <span class="hidden sm:inline-flex px-2 py-0.5 rounded-lg text-[10px] font-bold bg-up-primary/10 text-up-primary">Infinite scroll</span>
    </div>

    @if(!empty($quickProducts))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-2.5">
            @foreach($quickProducts as $produk)
                <div class="min-w-0 rounded-2xl border border-ink-200/80 dark:border-white/10 bg-white dark:bg-ink-900 p-2 sm:p-2.5 flex flex-col gap-2 hover:border-up-primary/50 transition-colors">
                    <div class="w-full aspect-square rounded-xl bg-ink-50 dark:bg-ink-950 overflow-hidden flex items-center justify-center">
                        @include('modules.marketplace.livewire.partials.product-image-preview', [
                            'url' => $produk['gambar'] ?? null,
                            'nama' => $produk['nama'] ?? '',
                            'class' => 'w-full h-full',
                        ])
                    </div>

                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="text-[11px] font-bold leading-snug break-words text-ink-900 dark:text-white line-clamp-2" title="{{ $produk['nama'] ?? '' }}">{{ $produk['nama'] ?? '-' }}</p>
                        <div class="flex flex-wrap gap-1 text-[9px]">
                            @if(!empty($produk['sku']))
                                <span class="font-mono text-ink-400 truncate max-w-full">{{ $produk['sku'] }}</span>
                            @endif
                            @if(!empty($produk['kategori']))
                                <span class="px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 font-bold">{{ $produk['kategori'] }}</span>
                            @endif
                            @if(!empty($produk['kualitas']))
                                <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 font-bold">{{ $produk['kualitas'] }}</span>
                            @endif
                            @if(!empty($produk['brand']))
                                <span class="px-1.5 py-0.5 rounded bg-ink-100 dark:bg-white/5 text-ink-500 dark:text-ink-300 font-bold">{{ $produk['brand'] }}</span>
                            @endif
                            @if(!empty($produk['kompatibel']))
                                <span class="px-1.5 py-0.5 rounded bg-up-mint/10 text-up-mint font-bold truncate max-w-full" title="{{ $produk['kompatibel'] }}">{{ $produk['kompatibel'] }}</span>
                            @endif
                        </div>
                        <p class="text-[11px] font-black text-up-primary tabular-nums">Rp {{ number_format((float) ($produk['harga'] ?? 0), 0, ',', '.') }}</p>
                        <p class="text-[9px] text-up-mint font-bold">Ready · {{ (int) ($produk['stok'] ?? 0) }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="tambahRekomendasi({{ (int) $produk['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="tambahRekomendasi({{ (int) $produk['id'] }})"
                        class="w-full py-2 rounded-xl bg-up-primary/10 text-up-primary hover:bg-up-primary hover:text-white text-[11px] font-bold transition-colors cursor-pointer disabled:opacity-50"
                        aria-label="Tambah {{ $produk['nama'] ?? 'produk' }} ke keranjang">
                        + Tambah
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-4 rounded-2xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 text-center text-[11px] text-ink-400">
            Belum ada produk siap jual.
        </div>
    @endif

    <div x-ref="quickSentinel" class="h-3"></div>

    <div class="mt-1 text-center">
        @if($quickHasMore)
            <button
                type="button"
                wire:click="loadMoreQuickProducts"
                wire:loading.attr="disabled"
                wire:target="loadMoreQuickProducts"
                class="px-4 py-2 rounded-xl bg-white dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-[11px] font-bold text-ink-600 dark:text-ink-300 hover:border-up-primary/50 hover:text-up-primary transition-colors cursor-pointer disabled:opacity-50">
                <span wire:loading.remove wire:target="loadMoreQuickProducts">Muat 5 produk berikutnya…</span>
                <span wire:loading wire:target="loadMoreQuickProducts">Memuat…</span>
            </button>
        @else
            <p class="text-[10px] text-ink-400">Semua produk tersedia sudah ditampilkan.</p>
        @endif
    </div>
</div>
