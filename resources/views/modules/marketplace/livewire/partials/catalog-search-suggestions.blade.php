@if($searchSuggestions->isNotEmpty())
    <div x-show="suggestionsOpen"
         x-cloak
         x-transition.opacity
         class="absolute left-0 right-0 top-full mt-2 z-[80] rounded-2xl bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 shadow-2xl overflow-hidden">
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-ink-400 border-b border-ink-100 dark:border-white/5">
            Saran produk
        </div>
        <div class="max-h-[60vh] overflow-y-auto divide-y divide-ink-100 dark:divide-white/5">
            @foreach($searchSuggestions as $s)
                <a href="{{ route('shop.detail', $s['slug']) }}"
                   @click="suggestionsOpen = false"
                   class="flex items-center gap-2.5 px-3 py-2 hover:bg-ink-50 dark:hover:bg-white/5 transition-colors cursor-pointer">
                    <span class="w-9 h-9 shrink-0 rounded-lg bg-ink-50 dark:bg-ink-950 border border-ink-100 dark:border-white/10 overflow-hidden flex items-center justify-center">
                        @if($s['gambar'])
                            <img src="{{ $s['gambar'] }}" alt="" class="w-full h-full object-contain" loading="lazy">
                        @else
                            <svg class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-bold text-ink-900 dark:text-white leading-tight break-words">{{ $s['nama'] }}</span>
                        <span class="block text-[10px] text-ink-400 font-mono truncate">{{ $s['sku'] ?: '-' }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block text-[11px] font-bold text-up-primary tabular-nums">Rp {{ number_format($s['harga'], 0, ',', '.') }}</span>
                        <span class="block text-[9px] {{ $s['stok'] > 0 ? 'text-up-mint' : 'text-up-red' }}">{{ $s['stok'] > 0 ? 'Ready' : 'Habis' }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        <button type="button"
                @click="suggestionsOpen = false; document.getElementById('katalog-produk')?.scrollIntoView({ behavior: 'smooth' })"
                class="w-full px-3 py-2.5 text-[11px] font-bold text-up-primary bg-up-primary/5 hover:bg-up-primary/10 cursor-pointer">
            Lihat semua hasil untuk “{{ $search }}”
        </button>
    </div>
@endif
