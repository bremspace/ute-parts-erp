@if(!empty($url))
    <button type="button"
            @click.prevent="$dispatch('open-preview', { url: '{{ $url }}', nama: '{{ addslashes($nama) }}' })"
            class="group relative {{ $class ?? 'w-12 h-12' }} shrink-0 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200/70 dark:border-white/10 overflow-hidden cursor-zoom-in ring-1 ring-transparent hover:ring-up-primary/50 transition-all"
            title="Lihat gambar {{ $nama }}">
        <img src="{{ $url }}"
             alt="{{ $nama }}"
             class="w-full h-full object-contain transition-transform duration-200 group-hover:scale-105"
             loading="lazy">
        <span class="absolute inset-0 bg-ink-950/0 group-hover:bg-ink-950/10 transition-colors flex items-center justify-center">
            <svg class="w-4 h-4 text-white opacity-0 group-hover:opacity-100 drop-shadow transition-opacity"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
            </svg>
        </span>
    </button>
@endif
