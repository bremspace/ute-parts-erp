<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-8 sm:space-y-10">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-ink-500 dark:text-ink-400">
        <a href="/" class="hover:text-ink-900 dark:hover:text-white transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('panduan.index') }}" class="hover:text-ink-900 dark:hover:text-white transition-colors">Panduan Lab</a>
        <span>/</span>
        <span class="text-ink-900 dark:text-white font-medium truncate max-w-[200px] sm:max-w-md">{{ $article->judul }}</span>
    </nav>

    <!-- Header Artikel -->
    <header class="space-y-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $article->kategori === 'panduan_servis' ? 'bg-up-primary/10 text-up-primary border border-up-primary/20' : ($article->kategori === 'edukasi_part' ? 'bg-up-mint/10 text-up-mint border border-up-mint/20' : 'bg-up-accent/10 text-up-accent border border-up-accent/20') }}">
                {{ $article->kategori === 'panduan_servis' ? 'Panduan Servis' : ($article->kategori === 'edukasi_part' ? 'Edukasi Suku Cadang' : 'Tips & Perawatan') }}
            </span>
            <span class="text-xs text-ink-400">•</span>
            <span class="text-xs text-ink-500 dark:text-ink-400">
                Diterbitkan {{ $article->published_at ? $article->published_at->format('d M Y') : $article->created_at->format('d M Y') }}
            </span>
            <span class="text-xs text-ink-400">•</span>
            <span class="text-xs text-ink-500 dark:text-ink-400">{{ number_format($article->views_count) }} kali dibaca</span>
        </div>

        <h1 class="text-2xl sm:text-4xl font-black text-ink-900 dark:text-white tracking-tight leading-tight">
            {{ $article->judul }}
        </h1>

        <p class="text-sm sm:text-base text-ink-600 dark:text-ink-300 leading-relaxed font-medium">
            {{ $article->ringkasan }}
        </p>

        <!-- Penulis Info -->
        <div class="flex items-center gap-3 pt-2">
            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-up-primary to-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow">
                {{ substr($article->penulis?->name ?? 'Tim Lab', 0, 1) }}
            </div>
            <div>
                <div class="text-xs font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                    <span>{{ $article->penulis?->name ?? 'Tim Teknisi & Ahli Suku Cadang' }}</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-up-primary/15 text-up-primary font-bold">Verified Author</span>
                </div>
                <div class="text-[11px] text-ink-400">Pusat Edukasi &amp; Standar QC Toko</div>
            </div>
        </div>
    </header>

    <!-- YouTube Video Player / Facade (Bila ada video) -->
    @if ($article->youtube_video_id)
    <div class="space-y-2">
        <div class="relative aspect-video rounded-3xl overflow-hidden bg-black/80 border border-ink-200 dark:border-white/10 shadow-2xl group"
             x-data="{ playing: false }">
            <template x-if="!playing">
                <div class="w-full h-full relative cursor-pointer" @click="playing = true">
                    <img src="https://img.youtube.com/vi/{{ $article->youtube_video_id }}/maxresdefault.jpg"
                         alt="{{ $article->judul }}"
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         onerror="this.src='https://img.youtube.com/vi/{{ $article->youtube_video_id }}/hqdefault.jpg'">
                    <div class="absolute inset-0 bg-black/40 group-hover:bg-black/25 transition-colors flex flex-col items-center justify-center gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-red-600 text-white flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-8 h-8 sm:w-9 sm:h-9 fill-current ml-1" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-black/70 backdrop-blur-md text-white text-xs font-bold shadow">
                            Putar Video Tutorial YouTube
                        </span>
                    </div>
                </div>
            </template>
            <template x-if="playing">
                <iframe
                    src="https://www.youtube-nocookie.com/embed/{{ $article->youtube_video_id }}?autoplay=1&rel=0"
                    title="{{ $article->judul }}"
                    class="w-full h-full border-0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </template>
        </div>
        <p class="text-[11px] text-ink-400 text-center">Video di-embed melalui facade responsif hemat kuota (server 1GB RAM friendly).</p>
    </div>
    @elseif ($article->cover_video && $article->tipe_media_cover === 'video')
    <!-- Video Loop Cover -->
    <div class="aspect-video rounded-3xl overflow-hidden bg-black border border-white/10 shadow-xl">
        <video src="{{ $article->cover_video }}" autoplay loop muted playsinline class="w-full h-full object-cover"></video>
    </div>
    @elseif ($article->cover_image)
    <!-- Image / GIF Cover -->
    <div class="aspect-[16/9] sm:aspect-[21/9] rounded-3xl overflow-hidden bg-ink-100 dark:bg-black/40 border border-ink-200/80 dark:border-white/10 shadow-lg">
        <img src="{{ $article->cover_image }}" alt="{{ $article->judul }}" class="w-full h-full object-cover">
    </div>
    @endif

    <!-- Konten Artikel Lengkap -->
    <article class="prose prose-slate dark:prose-invert max-w-none text-sm sm:text-base leading-relaxed text-ink-800 dark:text-ink-200 whitespace-pre-line py-2 border-b border-ink-100 dark:border-white/10">
{{ $article->konten_lengkap }}
    </article>

    <!-- Content to Commerce 1: Rekomendasi Booking Servis -->
    @if ($article->rekomendasiServis)
    <div class="rounded-3xl bg-gradient-to-r from-up-primary/10 via-indigo-600/10 to-transparent border border-up-primary/20 p-5 sm:p-7 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="space-y-1.5 max-w-xl">
            <div class="inline-flex items-center gap-1.5 text-xs font-bold text-up-primary uppercase tracking-wider">
                <span>🔧 Butuh Bantuan Pasang / Servis?</span>
            </div>
            <h3 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">
                {{ $article->rekomendasiServis->nama }}
            </h3>
            <p class="text-xs text-ink-500 dark:text-ink-400 leading-relaxed">
                Teknisi lab siap mengganti atau memperbaiki unit Anda dengan alat standar ESD dan garansi resmi toko.
            </p>
            <div class="text-xs font-bold text-up-mint pt-1">
                Biaya Jasa Mulai: Rp {{ number_format($article->rekomendasiServis->biaya_jasa, 0, ',', '.') }}
            </div>
        </div>

        @php
            $storeName = $webConfig['store_name'] ?? config('app.name', 'Official Store');
            $waPhone = preg_replace('/[^0-9]/', '', $webConfig['store_phone'] ?? '628123456789');
            $msgBooking = 'Halo ' . $storeName . ', saya membaca panduan "' . $article->judul . '" dan ingin konsultasi/booking layanan servis ' . $article->rekomendasiServis->nama;
        @endphp
        <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode($msgBooking) }}"
           target="_blank"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-lg shadow-up-primary/25 transition-[background-color,transform] duration-140 active:scale-95 shrink-0 min-h-[44px]">
            <span>Booking Servis Teknisi</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>
    @endif

    <!-- Content to Commerce 2: Suku Cadang Terkait (Beli Langsung) -->
    @if ($produkTerkait && $produkTerkait->isNotEmpty())
    <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-ink-900 dark:text-white flex items-center gap-2">
                    <span>Suku Cadang yang Digunakan dalam Panduan Ini</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/10 text-up-primary border border-up-primary/20">
                        {{ $produkTerkait->count() }} Part
                    </span>
                </h3>
                <p class="text-xs text-ink-500 dark:text-ink-400">Komponen teruji laboratorium dengan garansi fungsi dan kecocokan presisi.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($produkTerkait as $part)
            <div class="p-4 rounded-2xl bg-white dark:bg-ink-900 border border-ink-100 dark:border-white/10 shadow-sm flex flex-col justify-between space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-16 h-16 rounded-xl bg-ink-50 dark:bg-black/30 border border-ink-100 dark:border-white/10 shrink-0 overflow-hidden flex items-center justify-center">
                        @if ($part->foto_utama)
                            <img src="{{ $part->foto_utama }}" alt="{{ $part->nama }}" class="w-full h-full object-cover">
                        @else
                            <svg class="w-8 h-8 text-ink-400 stroke-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <a href="/shop/{{ $part->slug }}" class="font-bold text-xs text-ink-900 dark:text-white hover:text-up-primary transition-colors line-clamp-2 leading-snug">
                            {{ $part->nama }}
                        </a>
                        <div class="text-[11px] text-ink-400 font-mono mt-0.5">SKU: {{ $part->kode_produk }}</div>
                        <div class="text-xs font-black text-up-primary mt-1">
                            Rp {{ number_format($part->harga_jual_retail, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2 border-t border-ink-100 dark:border-white/5">
                    <a href="/shop/{{ $part->slug }}" class="flex-1 py-2 text-center rounded-xl bg-ink-100 dark:bg-white/5 hover:bg-ink-200 dark:hover:bg-white/10 text-xs font-semibold text-ink-800 dark:text-ink-200 transition-colors">
                        Lihat Detail
                    </a>
                    <button type="button"
                            wire:click="tambahKeKeranjang({{ $part->id }})"
                            class="py-2 px-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white text-xs font-bold shadow-sm transition-[background-color,transform] active:scale-95 cursor-pointer">
                        + Keranjang
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Panduan Terkait Lainnya -->
    @if ($relatedArticles && $relatedArticles->isNotEmpty())
    <div class="space-y-4 pt-6 border-t border-ink-100 dark:border-white/10">
        <h3 class="text-lg font-bold text-ink-900 dark:text-white">Panduan Terkait Lainnya</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($relatedArticles as $rel)
            <a href="{{ route('panduan.detail', $rel->slug) }}" class="p-4 rounded-2xl bg-white dark:bg-ink-900 border border-ink-100 dark:border-white/10 hover:border-up-primary/40 transition-all flex flex-col justify-between space-y-2 group">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-up-primary">{{ $rel->kategori }}</span>
                    <h4 class="text-xs font-bold text-ink-900 dark:text-white group-hover:text-up-primary transition-colors line-clamp-2 mt-1">
                        {{ $rel->judul }}
                    </h4>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400 line-clamp-2 mt-1 leading-relaxed">{{ $rel->ringkasan }}</p>
                </div>
                <span class="text-[11px] font-bold text-up-primary flex items-center gap-1 pt-2">
                    <span>Baca Selengkapnya</span>
                    <span>→</span>
                </span>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
