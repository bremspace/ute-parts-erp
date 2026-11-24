@if($searchSuggestions->isNotEmpty())
    <div x-show="suggestionsOpen"
         x-cloak
         x-transition.opacity
         x-ref="suggestionPanel"
         class="absolute left-0 right-0 top-full mt-2 z-[80] rounded-2xl bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 shadow-2xl overflow-y-auto overscroll-contain"
         style="max-height: min(70vh, 420px); overscroll-behavior: contain; touch-action: pan-y; -webkit-overflow-scrolling: touch;">
        <div class="sticky top-0 z-20 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-ink-400 bg-white dark:bg-ink-900 border-b border-ink-100 dark:border-white/5">
            Saran produk
        </div>
        <div class="divide-y divide-ink-100 dark:divide-white/5">
            @foreach($searchSuggestions as $s)
                <a href="{{ route('shop.detail', $s['slug']) }}"
                   @click="suggestionsOpen = false"
                   class="flex items-center gap-2.5 px-3 py-2 hover:bg-ink-50 dark:hover:bg-white/5 transition-colors cursor-pointer">
                    <span class="w-9 h-9 shrink-0 rounded-lg bg-ink-50 dark:bg-ink-950 border border-ink-100 dark:border-white/10 overflow-hidden flex items-center justify-center">
                        @if($s['gambar'])
                            <img src="{{ $s['gambar'] }}" alt="" class="w-full h-full object-contain" loading="{{ ($lazyLoad ?? true) ? 'lazy' : 'eager' }}">
                        @else
                            <svg class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-bold text-ink-900 dark:text-white leading-tight break-words">{{ $s['nama'] }}</span>
                        <span class="block text-[10px] text-ink-400 font-mono truncate">{{ $s['sku'] ?: '-' }}</span>
                        <span class="mt-0.5 flex items-center gap-1 flex-wrap text-[9px] leading-none">
                            @if(!empty($s['kategori']))
                                <span class="px-1.5 py-0.5 rounded bg-ink-100 dark:bg-white/5 text-ink-500 dark:text-ink-300 font-semibold truncate max-w-[100px]" title="{{ $s['kategori'] }}">{{ $s['kategori'] }}</span>
                            @endif
                            @if(!empty($s['kualitas']))
                                <span class="px-1.5 py-0.5 rounded bg-up-primary/10 text-up-primary font-bold truncate max-w-[90px]" title="Kualitas {{ $s['kualitas'] }}">{{ $s['kualitas'] }}</span>
                            @endif
                            @if(!empty($s['kompatibel']))
                                <span class="inline-flex items-center gap-1 rounded bg-up-mint/10 px-1.5 py-0.5 text-up-mint font-bold max-w-full">
                                    <span class="truncate max-w-[180px]" x-show="!isFitExpanded({{ $loop->index }})" title="Persamaan: {{ $s['kompatibel'] }}">
                                        Persamaan: {{ $s['kompatibel_short'] }}
                                    </span>
                                    <span class="break-words leading-snug" x-show="isFitExpanded({{ $loop->index }})" x-cloak>
                                        Persamaan: {{ $s['kompatibel'] }}
                                    </span>
                                    @if(!empty($s['kompatibel_need_expand']))
                                        <button type="button"
                                                @click.stop.prevent="toggleFit({{ $loop->index }})"
                                                class="shrink-0 rounded px-1 text-[9px] font-black text-up-mint hover:bg-up-mint/20 cursor-pointer"
                                                :aria-expanded="isFitExpanded({{ $loop->index }})">
                                            <span x-text="isFitExpanded({{ $loop->index }}) ? 'Collapse' : 'Expand'">Expand</span>
                                            <svg class="inline w-2.5 h-2.5 transition-transform" :class="isFitExpanded({{ $loop->index }}) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    @endif
                                </span>
                            @endif
                        </span>
                    </span>
                    <span class="shrink-0 flex flex-col items-end gap-1">
                        <span class="block text-[11px] font-bold text-up-primary tabular-nums">Rp {{ number_format($s['harga'], 0, ',', '.') }}</span>
                        <span class="block text-[9px] {{ $s['stok'] > 0 ? 'text-up-mint' : 'text-up-red' }}">{{ $s['stok'] > 0 ? 'Ready' : 'Habis' }}</span>
                        @if($s['stok'] > 0)
                            <button type="button"
                                    wire:click.stop="addToCart({{ $s['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="addToCart({{ $s['id'] }})"
                                    aria-label="Tambah {{ $s['nama'] }} ke keranjang"
                                    title="Tambah ke keranjang"
                                    class="w-7 h-7 rounded-lg bg-up-primary/10 text-up-primary hover:bg-up-primary hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer transition-colors active:scale-90 disabled:opacity-50">
                                <span wire:loading.remove wire:target="addToCart({{ $s['id'] }})">+</span>
                                <svg wire:loading wire:target="addToCart({{ $s['id'] }})" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                                </svg>
                            </button>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>

        @if($suggestionHasMore)
            <div x-ref="suggestionSentinel"
                 x-init="initSuggestionScroll()"
                 class="px-3 py-2 text-center border-t border-ink-100 dark:border-white/5">
                <button type="button"
                        wire:click="loadMoreSuggestions"
                        wire:loading.attr="disabled"
                        wire:target="loadMoreSuggestions"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-bold text-up-primary bg-up-primary/5 hover:bg-up-primary/10 cursor-pointer disabled:opacity-50">
                    <span wire:loading.remove wire:target="loadMoreSuggestions">Muat lebih banyak hasil…</span>
                    <span wire:loading wire:target="loadMoreSuggestions">Memuat…</span>
                </button>
                <p class="mt-1 text-[9px] text-ink-400">Gulir ke bawah untuk memuat otomatis</p>
            </div>
        @endif

        <button type="button"
                @click="suggestionsOpen = false; document.getElementById('katalog-produk')?.scrollIntoView({ behavior: 'smooth' })"
                class="sticky bottom-0 z-20 w-full px-3 py-2.5 text-[11px] font-bold text-up-primary bg-white dark:bg-ink-900 border-t border-ink-100 dark:border-white/5 hover:bg-up-primary/5 cursor-pointer">
            Lihat semua hasil untuk “{{ $search }}”
        </button>
    </div>
@endif
