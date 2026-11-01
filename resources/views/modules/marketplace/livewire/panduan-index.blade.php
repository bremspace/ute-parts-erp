<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-8 sm:space-y-12">
    <!-- Header Lab & Editorial -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0F172A] via-[#1E1B4B] to-[#0F172A] border border-white/10 p-6 sm:p-12 text-white shadow-2xl">
        <div class="max-w-3xl space-y-3 sm:space-y-4">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-up-primary/80 backdrop-blur-md text-white text-[11px] font-bold uppercase tracking-wider shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                <span>Pusat Edukasi &amp; Knowledge Base</span>
            </div>

            <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                Panduan Diagnostik &amp; <span class="bg-gradient-to-r from-up-mint via-sky-400 to-indigo-400 bg-clip-text text-transparent">Edukasi Suku Cadang</span>
            </h1>

            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl">
                Pelajari cara diagnosa kerusakan layar, baterai, hingga komponen IC, serta saksikan video bedah komponen oleh teknisi berpengalaman.
            </p>
        </div>

        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-up-primary/20 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Search & Filter Category Pills -->
    <div class="space-y-4">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-ink-400 group-focus-within:text-up-primary transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari topik panduan (misal: LCD iPhone original, ganti baterai kembung, IC charging)..."
                class="w-full pl-12 pr-12 py-3.5 sm:py-4 rounded-2xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-ink-900 dark:text-white placeholder-ink-400 text-sm font-medium shadow-sm focus:outline-none focus:border-up-primary focus:ring-4 focus:ring-up-primary/10 transition-all"
            />
            @if(!empty($search))
                <button
                    type="button"
                    wire:click="$set('search', '')"
                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-ink-400 hover:text-ink-700 dark:hover:text-white cursor-pointer"
                    title="Hapus pencarian">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Filter Pills Dinamis & Luas -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
            <button
                type="button"
                wire:click="setKategori('')"
                class="px-4 py-2 rounded-full font-semibold transition-[transform,background-color,color] duration-140 active:scale-95 cursor-pointer shrink-0 {{ $kategori === '' ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-950 shadow-sm' : 'bg-white dark:bg-ink-900 text-ink-600 dark:text-ink-300 border border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
                Semua Panduan
            </button>
            @foreach($kategoriList as $kItem)
            <button
                type="button"
                wire:click="setKategori('{{ $kItem }}')"
                class="px-4 py-2 rounded-full font-semibold transition-[transform,background-color,color] duration-140 active:scale-95 cursor-pointer shrink-0 {{ $kategori === $kItem ? 'bg-up-primary text-white shadow-sm' : 'bg-white dark:bg-ink-900 text-ink-600 dark:text-ink-300 border border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
                {{ ucwords(str_replace('_', ' ', $kItem)) }}
            </button>
            @endforeach
        </div>
    </div>

    <!-- Highlight Featured Video Guide (Jika Ada) -->
    @if ($highlightArticle && empty($search) && empty($kategori))
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-white/10 p-5 sm:p-8 overflow-hidden shadow-xl text-white">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            <div class="lg:col-span-5 space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-600/90 text-white shadow-sm">
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                    <span>Featured Video Guide</span>
                </span>
                <h2 class="text-xl sm:text-2xl font-black leading-tight">
                    <a href="{{ route('panduan.detail', $highlightArticle->slug) }}" class="hover:text-up-mint transition-colors">
                        {{ $highlightArticle->judul }}
                    </a>
                </h2>
                <p class="text-xs sm:text-sm text-slate-300 line-clamp-2 leading-relaxed">
                    {{ $highlightArticle->ringkasan }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('panduan.detail', $highlightArticle->slug) }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-ink-950 hover:bg-slate-100 font-bold text-xs transition-[background-color,transform] duration-140 active:scale-95 shadow-md">
                        <span>Tonton Video &amp; Baca Panduan</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            <!-- YouTube Video Facade Mini Player -->
            <div class="lg:col-span-7">
                <div class="relative aspect-video rounded-2xl overflow-hidden bg-black/60 border border-white/15 shadow-2xl group"
                     x-data="{ playing: false }">
                    <template x-if="!playing">
                        <div class="w-full h-full relative cursor-pointer" @click="playing = true">
                            <img src="https://img.youtube.com/vi/{{ $highlightArticle->youtube_video_id }}/maxresdefault.jpg"
                                 alt="{{ $highlightArticle->judul }}"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                 onerror="this.src='https://img.youtube.com/vi/{{ $highlightArticle->youtube_video_id }}/hqdefault.jpg'">
                            <div class="absolute inset-0 bg-black/35 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-red-600/90 text-white flex items-center justify-center shadow-xl group-hover:scale-110 transition-transform duration-200">
                                    <svg class="w-6 h-6 sm:w-7 sm:h-7 fill-current ml-1" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template x-if="playing">
                        <iframe
                            src="https://www.youtube-nocookie.com/embed/{{ $highlightArticle->youtube_video_id }}?autoplay=1&rel=0"
                            title="{{ $highlightArticle->judul }}"
                            class="w-full h-full border-0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Grid Artikel Panduan -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
        @forelse ($articles as $art)
            <article class="flex flex-col rounded-3xl bg-white dark:bg-ink-900 border border-ink-100/90 dark:border-white/[0.08] shadow-[0_4px_24px_rgba(0,0,0,0.02)] overflow-hidden transition-[transform,box-shadow] duration-200 hover:-translate-y-1 hover:shadow-xl group">
                <!-- Cover Media Frame -->
                <a href="{{ route('panduan.detail', $art->slug) }}" class="block relative aspect-[16/10] overflow-hidden bg-ink-50 dark:bg-black/40">
                    @if ($art->cover_video && $art->tipe_media_cover === 'video')
                        <video src="{{ $art->cover_video }}" autoplay loop muted playsinline class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"></video>
                    @elseif ($art->cover_image)
                        <img src="{{ $art->cover_image }}" alt="{{ $art->judul }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @elseif ($art->youtube_video_id)
                        <img src="https://img.youtube.com/vi/{{ $art->youtube_video_id }}/hqdefault.jpg" alt="{{ $art->judul }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center text-ink-300 dark:text-ink-600 p-6 text-center">
                            <svg class="w-12 h-12 stroke-[1.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                    @endif

                    <!-- Media Indicator Badges -->
                    <div class="absolute top-3 left-3 flex items-center gap-1.5">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider backdrop-blur-md shadow-sm {{ $art->kategori === 'panduan_servis' ? 'bg-up-primary/90 text-white' : ($art->kategori === 'edukasi_part' ? 'bg-up-mint/90 text-ink-950' : 'bg-up-accent/90 text-white') }}">
                            {{ $art->kategori === 'panduan_servis' ? 'Servis' : ($art->kategori === 'edukasi_part' ? 'Part Grade' : 'Tips') }}
                        </span>
                    </div>

                    @if ($art->youtube_video_id)
                        <div class="absolute bottom-3 right-3 px-2 py-0.5 rounded-lg bg-black/60 backdrop-blur-md text-white text-[10px] font-bold flex items-center gap-1 shadow">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                            <span>Video Tutorial</span>
                        </div>
                    @endif
                </a>

                <!-- Card Body -->
                <div class="p-5 sm:p-6 flex flex-col flex-1 justify-between space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-[11px] text-ink-400">
                            <span>{{ $art->published_at ? $art->published_at->format('d M Y') : $art->created_at->format('d M Y') }}</span>
                            <span>•</span>
                            <span>{{ number_format($art->views_count) }}x dibaca</span>
                        </div>

                        <h3 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white group-hover:text-up-primary transition-colors line-clamp-2 leading-snug">
                            <a href="{{ route('panduan.detail', $art->slug) }}">
                                {{ $art->judul }}
                            </a>
                        </h3>

                        <p class="text-xs text-ink-500 dark:text-ink-400 line-clamp-2 leading-relaxed">
                            {{ $art->ringkasan }}
                        </p>
                    </div>

                    <!-- Card Footer: Commerce link chips -->
                    <div class="pt-3 border-t border-ink-100/80 dark:border-white/10 flex items-center justify-between text-xs font-semibold">
                        <div class="flex items-center gap-2 text-ink-500 dark:text-ink-400">
                            @if(!empty($art->produk_terkait_ids))
                                <span class="inline-flex items-center gap-1 text-[11px] text-up-primary font-semibold">
                                    <span>📦</span> {{ count($art->produk_terkait_ids) }} Suku Cadang
                                </span>
                            @endif
                        </div>

                        <a href="{{ route('panduan.detail', $art->slug) }}"
                           class="inline-flex items-center gap-1 text-xs font-bold text-up-primary group-hover:translate-x-0.5 transition-transform">
                            <span>Baca Lengkap</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full py-16 text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-ink-100 dark:bg-white/5 flex items-center justify-center text-ink-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-base font-bold text-ink-900 dark:text-white">Tidak ada artikel panduan ditemukan</h3>
                <p class="text-xs text-ink-500 dark:text-ink-400">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-4">
        {{ $articles->links() }}
    </div>
</div>
