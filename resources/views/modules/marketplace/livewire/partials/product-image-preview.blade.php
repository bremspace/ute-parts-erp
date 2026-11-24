@if(!empty($url))
    <button type="button"
            @click.prevent="$dispatch('open-preview', { url: '{{ $url }}', nama: '{{ addslashes($nama) }}' })"
            class="group/thumb relative {{ $class ?? 'w-12 h-12' }} shrink-0 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200/70 dark:border-white/10 overflow-hidden cursor-zoom-in ring-1 ring-transparent hover:ring-up-primary/50 transition-all"
            title="Lihat gambar {{ $nama }}"
            aria-label="Lihat gambar {{ $nama }}">
        <img src="{{ $url }}"
             alt="{{ $nama }}"
             class="w-full h-full object-contain transition-transform duration-200 group-hover/thumb:scale-105"
             loading="lazy">

        {{-- Mobile: badge zoom selalu terlihat (tidak ada pointer/hover) --}}
        <span class="absolute bottom-0.5 right-0.5 z-10 w-5 h-5 rounded-full bg-black/55 text-white flex items-center justify-center backdrop-blur-sm lg:hidden"
              aria-hidden="true">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
            </svg>
        </span>

        {{-- Desktop: overlay zoom + teks saat mouse di atas thumbnail --}}
        <span class="absolute inset-0 z-10 hidden lg:flex flex-col items-center justify-center gap-0.5 bg-ink-950/0 group-hover/thumb:bg-ink-950/45 text-white opacity-0 group-hover/thumb:opacity-100 transition-all duration-150"
              aria-hidden="true">
            <svg class="w-5 h-5 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
            </svg>
            <span class="text-[9px] font-bold drop-shadow">Lihat gambar</span>
        </span>
    </button>
@endif
