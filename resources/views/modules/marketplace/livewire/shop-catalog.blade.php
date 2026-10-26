<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-8 sm:space-y-12"
     x-data="{
        viewMode: localStorage.getItem('ute-shop-view-mode') || 'grid',
        setViewMode(mode) {
            this.viewMode = mode;
            try { localStorage.setItem('ute-shop-view-mode', mode); } catch (e) {}
        }
     }">
    @php
        $heroDisplayMode = $webConfig['hero_display_mode'] ?? 'hero_standard';
        $heroBanners = $webBanners->where('posisi', 'hero_slider');
        $hasHeroBanners = $heroBanners->isNotEmpty();
        $hasHeroBg = !empty($webConfig['hero_bg_image'] ?? '');
        $heroOverlay = $webConfig['hero_bg_overlay'] ?? 'scrim_dark';
    @endphp

    <!-- Mode 1: Carousel Menggantikan Posisi Hero (carousel_replace) -->
    @if(($webConfig['hero_active'] ?? true) && $heroDisplayMode === 'carousel_replace' && $hasHeroBanners)
    <section class="relative overflow-hidden rounded-3xl border border-white/10 shadow-2xl bg-[#0F172A]"
             x-data="{
                active: 0,
                total: {{ $heroBanners->count() }},
                touchStartX: 0,
                touchEndX: 0,
                timer: null,
                next() { this.active = (this.active + 1) % this.total; },
                prev() { this.active = (this.active - 1 + this.total) % this.total; },
                start() { this.stop(); this.timer = setInterval(() => this.next(), 5500); },
                stop() { if (this.timer) clearInterval(this.timer); },
                handleTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX; },
                handleTouchEnd(e) {
                    this.touchEndX = e.changedTouches[0].screenX;
                    if (this.touchStartX - this.touchEndX > 45) { this.next(); }
                    if (this.touchEndX - this.touchStartX > 45) { this.prev(); }
                }
             }"
             x-init="start()"
             @mouseenter="stop()"
             @mouseleave="start()"
             @touchstart.passive="handleTouchStart($event)"
             @touchend.passive="handleTouchEnd($event)">
        <!-- Banner Slides Frame -->
        <div class="relative w-full aspect-[16/9] sm:aspect-[21/9] min-h-[260px] sm:min-h-[360px] lg:min-h-[420px] overflow-hidden">
            @foreach($heroBanners as $bIdx => $banner)
                <a href="{{ $banner->target_url ?: '#' }}"
                   x-show="active === {{ $bIdx }}"
                   x-transition:enter="transition duration-450 ease-[cubic-bezier(0.23,1,0.32,1)]"
                   x-transition:enter-start="opacity-0 scale-[0.98]"
                   x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition duration-250 ease-in"
                   x-transition:leave-start="opacity-100 scale-100"
                   x-transition:leave-end="opacity-0 scale-[0.99]"
                   class="absolute inset-0 block group">
                    <img src="{{ $banner->gambar_desktop }}"
                         alt="{{ $banner->judul }}"
                         class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.02]">

                    <!-- Dynamic Scrim Gradient for Readability -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#070B14]/95 via-[#070B14]/40 to-transparent p-6 sm:p-10 lg:p-12 flex flex-col justify-end">
                        <div class="max-w-2xl space-y-2 sm:space-y-3 transform transition-transform duration-300 group-hover:translate-y-[-2px]">
                            @if($banner->badge_label)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold uppercase tracking-wider bg-up-primary/90 text-white backdrop-blur-md shadow-md w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                                    <span>{{ $banner->badge_label }}</span>
                                </span>
                            @endif

                            <h2 class="text-xl sm:text-3xl lg:text-4xl font-black text-white leading-tight drop-shadow-md">
                                {{ $banner->judul }}
                            </h2>

                            @if($banner->deskripsi)
                                <p class="text-xs sm:text-sm text-slate-200 line-clamp-2 leading-relaxed drop-shadow-sm max-w-xl">
                                    {{ $banner->deskripsi }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <!-- Tactile Navigation Chevrons -->
        @if($heroBanners->count() > 1)
        <button type="button" @click="prev()"
                class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-md border border-white/20 text-white flex items-center justify-center transition-[transform,background-color] duration-140 active:scale-90 cursor-pointer shadow-lg"
                aria-label="Slide sebelumnya">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <button type="button" @click="next()"
                class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-md border border-white/20 text-white flex items-center justify-center transition-[transform,background-color] duration-140 active:scale-90 cursor-pointer shadow-lg"
                aria-label="Slide berikutnya">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Dynamic Indicator Dots -->
        <div class="absolute bottom-4 sm:bottom-6 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/40 backdrop-blur-md border border-white/10">
            @foreach($heroBanners as $bIdx => $banner)
                <button type="button" @click="active = {{ $bIdx }}"
                        class="h-2 rounded-full transition-all duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] cursor-pointer"
                        :class="active === {{ $bIdx }} ? 'w-7 bg-up-mint shadow-[0_0_8px_rgba(31,191,143,0.8)]' : 'w-2 bg-white/40 hover:bg-white/70'"></button>
            @endforeach
        </div>
        @endif
    </section>
    @elseif($webConfig['hero_active'] ?? true)
    <!-- Mode 2 & 3: Hero Standard atau Hero + Carousel -->
    <section class="dark-surface relative overflow-hidden rounded-3xl bg-[#0F172A] text-white p-6 sm:p-8 lg:p-10 border border-white/10 shadow-lg shadow-black/20" data-theme-surface="dark">
        <!-- Background Foto Hero Custom jika dikonfigurasi -->
        @if($hasHeroBg)
            <img src="{{ $webConfig['hero_bg_image'] }}" alt="Hero Background" class="absolute inset-0 w-full h-full object-cover pointer-events-none select-none z-0">
            @if($heroOverlay === 'scrim_heavy')
                <div class="absolute inset-0 bg-[#070B14]/85 backdrop-blur-[2px] z-[1]"></div>
            @elseif($heroOverlay === 'glass_card')
                <div class="absolute inset-0 bg-black/45 z-[1]"></div>
            @else
                <!-- scrim_dark default: gradien gelap halus dari kiri ke kanan -->
                <div class="absolute inset-0 bg-gradient-to-r from-[#070B14]/95 via-[#070B14]/85 to-[#070B14]/30 z-[1]"></div>
            @endif
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center relative z-10">
            <div class="lg:col-span-7 space-y-5 sm:space-y-6 min-w-0 {{ ($hasHeroBg && $heroOverlay === 'glass_card') ? 'p-6 sm:p-8 rounded-3xl bg-black/60 backdrop-blur-xl border border-white/15 shadow-2xl' : '' }}">
                <!-- Exclusive Pill -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/[0.12] border border-white/20 backdrop-blur-md text-[11px] font-semibold tracking-wider uppercase text-slate-100 max-w-full">
                    <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.9)] flex-shrink-0"></span>
                    <span class="truncate">{{ $webConfig['hero_badge_text'] ?? 'Standard Ute Prism™ Precision Parts' }}</span>
                </div>

                <!-- Headline with dynamic high contrast -->
                <div class="space-y-3">
                    <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-[1.12] text-white break-words drop-shadow-sm">
                        {!! nl2br(e($webConfig['hero_headline'] ?? "Suku Cadang Presisi.\nGaransi Nyata Toko Resmi.")) !!}
                    </h1>
                    <p class="text-xs sm:text-base text-slate-200 max-w-xl font-normal leading-relaxed break-words drop-shadow-sm">
                        {{ $webConfig['hero_subheadline'] ?? 'Layar LCD grade OEM, modul baterai densitas tinggi, hingga sparepart IC dengan kalibrasi uji pabrik. Dipersiapkan untuk teknisi profesional dan pengguna gadget teliti.' }}
                    </p>
                </div>

                <!-- Feature highlights pill list -->
                @php
                    $features = $webConfig['hero_features'] ?? null;
                    if (is_string($features)) {
                        $features = json_decode($features, true);
                    }
                    if (!is_array($features)) {
                        $features = [
                            ['icon' => 'qc', 'text' => '100% Diagnostic QC Passed'],
                            ['icon' => 'shield', 'text' => 'Garansi Ganti Unit'],
                            ['icon' => 'clock', 'text' => 'Ambil Cepat di Cabang'],
                        ];
                    }
                @endphp
                <div class="flex flex-wrap gap-2 sm:gap-2.5 pt-1 text-xs">
                    @foreach($features as $feat)
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/[0.08] backdrop-blur-md border border-white/15 text-slate-100 max-w-full min-w-0 shadow-sm">
                        <svg class="w-4 h-4 text-up-mint flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="truncate">{{ $feat['text'] ?? '' }}</span>
                    </div>
                    @endforeach
                </div>

                <!-- Stats summary -->
                @php
                    $stats = $webConfig['hero_stats'] ?? null;
                    if (is_string($stats)) {
                        $stats = json_decode($stats, true);
                    }
                    if (!is_array($stats)) {
                        $stats = [
                            ['label' => 'Item Terkatalog', 'type' => 'dynamic_catalog', 'val' => ''],
                            ['label' => 'Pick-up Center', 'type' => 'fixed', 'val' => '5 Cabang'],
                            ['label' => 'Kepuasan', 'type' => 'fixed', 'val' => '99.4%'],
                        ];
                    }
                @endphp
                <div class="pt-4 border-t border-white/10 flex items-center gap-4 sm:gap-12 flex-wrap">
                    @foreach($stats as $st)
                    <div class="min-w-0">
                        <div class="text-xl sm:text-3xl font-black tabular-nums tracking-tight text-white drop-shadow-sm">
                            {{ ($st['type'] ?? '') === 'dynamic_catalog' ? $products->total().'+' : ($st['val'] ?? '') }}
                        </div>
                        <div class="text-[10px] sm:text-[11px] text-ink-300 uppercase tracking-wider font-semibold mt-0.5">{{ $st['label'] ?? '' }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Right Widget: Member Card OR Banner Slider (CMS) -->
            <div class="lg:col-span-5 min-w-0 w-full">
                @php
                    $isSlider = ($webConfig['hero_right_widget'] ?? 'member_card') === 'banner_slider' && $hasHeroBanners;
                @endphp

                @if($isSlider)
                    <!-- Alpine Banner Carousel Widget with Emil smooth easing -->
                    <div class="rounded-2xl overflow-hidden border border-white/15 shadow-2xl relative bg-black/40"
                         x-data="{
                            active: 0,
                            total: {{ $heroBanners->count() }},
                            next() { this.active = (this.active + 1) % this.total; },
                            prev() { this.active = (this.active - 1 + this.total) % this.total; },
                            timer: null,
                            start() { this.stop(); this.timer = setInterval(() => this.next(), 4500); },
                            stop() { if (this.timer) clearInterval(this.timer); }
                         }"
                         x-init="start()"
                         @mouseenter="stop()"
                         @mouseleave="start()">
                        <div class="relative h-64 sm:h-72 w-full overflow-hidden">
                            @foreach($heroBanners as $bIdx => $banner)
                                <a href="{{ $banner->target_url ?: '#' }}"
                                   x-show="active === {{ $bIdx }}"
                                   x-transition:enter="transition duration-400 ease-[cubic-bezier(0.23,1,0.32,1)]"
                                   x-transition:enter-start="opacity-0 scale-[0.97]"
                                   x-transition:enter-end="opacity-100 scale-100"
                                   x-transition:leave="transition duration-200 ease-in"
                                   x-transition:leave-start="opacity-100"
                                   x-transition:leave-end="opacity-0"
                                   class="absolute inset-0 block group">
                                    <img src="{{ $banner->gambar_desktop }}" alt="{{ $banner->judul }}" class="w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-105">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent p-5 flex flex-col justify-end">
                                        @if($banner->badge_label)
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-up-primary/80 text-white w-max uppercase tracking-wider mb-1">
                                                {{ $banner->badge_label }}
                                            </span>
                                        @endif
                                        <h3 class="text-sm sm:text-base font-bold text-white group-hover:text-up-mint transition-colors">
                                            {{ $banner->judul }}
                                        </h3>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        <!-- Carousel Indicators -->
                        @if($heroBanners->count() > 1)
                        <div class="absolute bottom-3 right-3 flex items-center gap-1.5 z-20">
                            @foreach($heroBanners as $bIdx => $banner)
                                <button type="button" @click="active = {{ $bIdx }}"
                                        class="h-1.5 rounded-full transition-all duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] cursor-pointer"
                                        :class="active === {{ $bIdx }} ? 'w-5 bg-up-mint' : 'w-1.5 bg-white/40'"></button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                @else
                    <!-- Member Privileges Card -->
                    <div class="rounded-2xl p-4 sm:p-7 bg-white/[0.04] border border-white/15 backdrop-blur-xl relative overflow-hidden shadow-2xl">
                    <div class="flex items-center justify-between pb-4 border-b border-white/10 gap-2">
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-up-mint block truncate">Keanggotaan &amp; Bengkel</span>
                            <h3 class="text-sm sm:text-base font-bold text-white mt-0.5 truncate">Tingkatan Harga Spesial</h3>
                        </div>
                        <span class="px-2 sm:px-2.5 py-1 rounded-full bg-white/10 text-white text-[10px] sm:text-[11px] font-bold flex-shrink-0">Otomatis</span>
                    </div>

                    <div class="space-y-2.5 py-4 text-xs">
                        <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-white/[0.03] border border-white/5 gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full bg-slate-300 flex-shrink-0"></span>
                                <span class="font-semibold text-ink-200 truncate">Member Silver</span>
                            </div>
                            <span class="font-bold text-white tabular-nums flex-shrink-0 text-[11px] sm:text-xs">Diskon 3%</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-white/[0.03] border border-white/5 gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                                <span class="font-semibold text-ink-200 truncate">Member Gold</span>
                            </div>
                            <span class="font-bold text-amber-300 tabular-nums flex-shrink-0 text-[11px] sm:text-xs">Diskon 5%</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-gradient-to-r from-up-primary/20 to-indigo-500/10 border border-up-primary/30 gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full bg-up-primary animate-ping flex-shrink-0"></span>
                                <span class="font-semibold text-white truncate">Mitra / Teknisi</span>
                            </div>
                            <span class="font-bold text-up-mint tabular-nums flex-shrink-0 text-[11px] sm:text-xs">Harga Khusus</span>
                        </div>
                    </div>

                    @guest('customer')
                        <div class="pt-2">
                            <a href="{{ route('customer.register') }}"
                               class="w-full py-3 rounded-xl bg-white text-ink-950 font-bold text-xs hover:bg-ink-100 transition-[background-color,transform] duration-140 active:scale-[0.97] flex items-center justify-center gap-2 shadow-lg cursor-pointer">
                                <span>Buat Akun Sekarang</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        </div>
                    @else
                        <div class="pt-2">
                            <a href="{{ route('customer.account') }}"
                               class="w-full py-3 rounded-xl bg-white/10 text-white font-bold text-xs hover:bg-white/20 transition-[background-color,transform] duration-140 active:scale-[0.97] flex items-center justify-center gap-2 cursor-pointer">
                                <span>Lihat Tingkat Akun Saya</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    @endguest
                </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Mode 3: Banner Carousel Tambahan di Bawah Hero (hero_and_carousel) -->
    @if($heroDisplayMode === 'hero_and_carousel' && $hasHeroBanners)
    <section class="relative overflow-hidden rounded-3xl border border-white/10 shadow-xl bg-[#0F172A]"
             x-data="{
                active: 0,
                total: {{ $heroBanners->count() }},
                touchStartX: 0,
                touchEndX: 0,
                timer: null,
                next() { this.active = (this.active + 1) % this.total; },
                prev() { this.active = (this.active - 1 + this.total) % this.total; },
                start() { this.stop(); this.timer = setInterval(() => this.next(), 5500); },
                stop() { if (this.timer) clearInterval(this.timer); },
                handleTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX; },
                handleTouchEnd(e) {
                    this.touchEndX = e.changedTouches[0].screenX;
                    if (this.touchStartX - this.touchEndX > 45) { this.next(); }
                    if (this.touchEndX - this.touchStartX > 45) { this.prev(); }
                }
             }"
             x-init="start()"
             @mouseenter="stop()"
             @mouseleave="start()"
             @touchstart.passive="handleTouchStart($event)"
             @touchend.passive="handleTouchEnd($event)">
        <div class="relative w-full aspect-[21/9] min-h-[220px] sm:min-h-[300px] overflow-hidden">
            @foreach($heroBanners as $bIdx => $banner)
                <a href="{{ $banner->target_url ?: '#' }}"
                   x-show="active === {{ $bIdx }}"
                   x-transition:enter="transition duration-450 ease-[cubic-bezier(0.23,1,0.32,1)]"
                   x-transition:enter-start="opacity-0 scale-[0.98]"
                   x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition duration-250 ease-in"
                   x-transition:leave-start="opacity-100"
                   x-transition:leave-end="opacity-0 scale-[0.99]"
                   class="absolute inset-0 block group">
                    <img src="{{ $banner->gambar_desktop }}"
                         alt="{{ $banner->judul }}"
                         class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.02]">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#070B14]/90 via-[#070B14]/30 to-transparent p-6 sm:p-8 flex flex-col justify-end">
                        <div class="max-w-xl space-y-1.5 sm:space-y-2">
                            @if($banner->badge_label)
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-up-primary text-white w-fit">
                                    {{ $banner->badge_label }}
                                </span>
                            @endif
                            <h3 class="text-base sm:text-2xl font-black text-white group-hover:text-up-mint transition-colors">
                                {{ $banner->judul }}
                            </h3>
                            @if($banner->deskripsi)
                                <p class="text-xs text-slate-200 line-clamp-1">
                                    {{ $banner->deskripsi }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($heroBanners->count() > 1)
        <div class="absolute bottom-3 right-4 z-20 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/40 backdrop-blur-md border border-white/10">
            @foreach($heroBanners as $bIdx => $banner)
                <button type="button" @click="active = {{ $bIdx }}"
                        class="h-1.5 rounded-full transition-all duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] cursor-pointer"
                        :class="active === {{ $bIdx }} ? 'w-5 bg-up-mint' : 'w-1.5 bg-white/40'"></button>
            @endforeach
        </div>
        @endif
    </section>
    @endif
    @endif

    <!-- Spotlight Search & Pill Filter System -->
    <section class="space-y-3 sm:space-y-4">
        <!-- Raycast/Spotlight Style Search Bar -->
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-ink-400 group-focus-within:text-up-primary transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.250ms="search"
                placeholder="Ketik tipe LCD, baterai iPhone, model Samsung, nomor part..."
                class="w-full pl-12 pr-12 py-3.5 sm:py-4 rounded-2xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-ink-900 dark:text-white placeholder-ink-400 text-base sm:text-sm font-medium shadow-[0_4px_16px_rgba(0,0,0,0.02)] focus:outline-none focus:border-up-primary focus:ring-4 focus:ring-up-primary/10 transition-[border-color,box-shadow] duration-140"
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

        <!-- Filter Selectors Matrix -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8 gap-2 sm:gap-2.5">
            <!-- Kategori -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterKategori"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        @php
                            $catId = is_array($cat) ? ($cat['id'] ?? '') : ($cat->id ?? (is_scalar($cat) ? (string) $cat : ''));
                            $catNama = is_array($cat) ? ($cat['nama'] ?? '') : ($cat->nama ?? (is_scalar($cat) ? (string) $cat : ''));
                            $catCount = is_array($cat) ? ($cat['count'] ?? null) : ($cat->count ?? null);
                            $children = is_array($cat) ? ($cat['children'] ?? []) : ($cat->children ?? []);
                            $hasChildren = !empty($children) && (is_countable($children) ? count($children) : 0) > 0;
                        @endphp
                        @if($hasChildren)
                            <optgroup label="{{ $catNama }}">
                                <option value="{{ $catId }}">— Semua {{ $catNama }}{{ $catCount !== null ? ' (' . $catCount . ')' : '' }} —</option>
                                @foreach($children as $child)
                                    @php
                                        $childId = is_array($child) ? ($child['id'] ?? '') : ($child->id ?? (is_scalar($child) ? (string) $child : ''));
                                        $childNama = is_array($child) ? ($child['nama'] ?? '') : ($child->nama ?? (is_scalar($child) ? (string) $child : ''));
                                        $childCount = is_array($child) ? ($child['count'] ?? null) : ($child->count ?? null);
                                    @endphp
                                    <option value="{{ $childId }}">{{ $childNama }}{{ $childCount !== null ? ' (' . $childCount . ')' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @elseif(!empty($catNama))
                            <option value="{{ $catId }}">{{ $catNama }}{{ $catCount !== null ? ' (' . $catCount . ')' : '' }}</option>
                        @endif
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Kondisi -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterKondisi"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Semua Kondisi</option>
                    <option value="baru">Baru</option>
                    <option value="oem">OEM Certified</option>
                    <option value="compatible">Compatible Grade A</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Merk HP -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterHpMerk"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Merk Gadget</option>
                    @foreach($hpMerkList as $key => $val)
                        @php
                            $m = is_numeric($key) ? $val : $key;
                            $count = is_numeric($key) ? null : $val;
                        @endphp
                        <option value="{{ $m }}">{{ $m }}{{ $count !== null ? ' (' . $count . ')' : '' }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Model HP -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterHpModel"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Model Gadget</option>
                    @foreach($hpModelList as $key => $val)
                        @php
                            $m = is_numeric($key) ? $val : $key;
                            $count = is_numeric($key) ? null : $val;
                        @endphp
                        <option value="{{ $m }}">{{ $m }}{{ $count !== null ? ' (' . $count . ')' : '' }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Brand Part -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterBrand"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Semua Brand</option>
                    @foreach($brands as $key => $val)
                        @php
                            $b = is_numeric($key) ? $val : $key;
                            $count = is_numeric($key) ? null : $val;
                        @endphp
                        <option value="{{ $b }}">{{ $b }}{{ $count !== null ? ' (' . $count . ')' : '' }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Rentang Harga -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="hargaMax"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Semua Harga</option>
                    @foreach([100000, 250000, 500000, 1000000] as $hb)
                        <option value="{{ $hb }}">≤ Rp {{ number_format($hb, 0, ',', '.') }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Kualitas / Grade Part -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterKualitas"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Semua Kualitas</option>
                    @foreach($kualitasList as $k)
                        @php
                            $kId = $k['id'] ?? '';
                            $kNama = $k['nama'] ?? '';
                            $kCount = $k['count'] ?? null;
                        @endphp
                        <option value="{{ $kId }}">{{ $kNama }}{{ $kCount !== null ? ' (' . $kCount . ')' : '' }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Ketersediaan Stok (sinkron live dengan stok_items backoffice) -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="filterStok"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Semua Stok</option>
                    <option value="tersedia">Ready Stock</option>
                    <option value="habis">Stok Habis</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Sortasi -->
            <div class="relative min-w-0 w-full">
                <select wire:model.live="sort"
                        class="w-full min-w-0 max-w-full truncate appearance-none px-2.5 sm:px-3 py-2 pr-7 rounded-xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-200 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15 transition-[border-color,box-shadow] duration-140 min-h-[44px] cursor-pointer">
                    <option value="">Paling Relevan</option>
                    <option value="terlaris">Terlaris</option>
                    <option value="terbaru">Terbaru</option>
                    <option value="termurah">Harga Termurah</option>
                    <option value="termahal">Harga Termahal</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Section -->
    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-ink-100/80 dark:border-white/[0.08]">
            <div>
                <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink-900 dark:text-white">Koleksi Suku Cadang</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400">Menampilkan {{ $products->count() }} dari {{ $products->total() }} komponen siap kirim</p>
            </div>

            <div class="flex items-center gap-3">
                @if($search || $filterKategori || $filterKondisi || $filterHpMerk || $filterHpModel || $filterBrand || $hargaMax || $filterKualitas || $filterStok || $sort)
                    <button
                        wire:click="$set('search', ''); $set('filterKategori', ''); $set('filterKondisi', ''); $set('filterHpMerk', ''); $set('filterHpModel', ''); $set('filterBrand', ''); $set('hargaMax', ''); $set('filterKualitas', ''); $set('filterStok', ''); $set('sort', '');"
                        class="text-xs font-semibold text-up-primary hover:underline cursor-pointer flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Reset Filter</span>
                    </button>
                    <div class="h-4 w-px bg-ink-200 dark:bg-white/10 hidden sm:block"></div>
                @endif

                <!-- Modern View Switcher (Grid vs List vs Compact) -->
                <div class="inline-flex items-center p-1 rounded-xl bg-ink-100 dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 shadow-inner">
                    <button type="button"
                            @click="setViewMode('grid')"
                            :class="viewMode === 'grid' ? 'bg-white dark:bg-ink-800 text-ink-950 dark:text-white shadow-sm font-bold' : 'text-ink-600 hover:text-ink-950 dark:text-ink-400 dark:hover:text-white font-medium'"
                            class="p-1.5 sm:px-2.5 sm:py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.97] cursor-pointer"
                            title="Tampilan Grid Kartu">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span class="hidden sm:inline">Grid</span>
                    </button>
                    <button type="button"
                            @click="setViewMode('list')"
                            :class="viewMode === 'list' ? 'bg-white dark:bg-ink-800 text-ink-950 dark:text-white shadow-sm font-bold' : 'text-ink-600 hover:text-ink-950 dark:text-ink-400 dark:hover:text-white font-medium'"
                            class="p-1.5 sm:px-2.5 sm:py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.97] cursor-pointer"
                            title="Tampilan List Baris">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span class="hidden sm:inline">List</span>
                    </button>
                    <button type="button"
                            @click="setViewMode('compact')"
                            :class="viewMode === 'compact' ? 'bg-white dark:bg-ink-800 text-ink-950 dark:text-white shadow-sm font-bold' : 'text-ink-600 hover:text-ink-950 dark:text-ink-400 dark:hover:text-white font-medium'"
                            class="p-1.5 sm:px-2.5 sm:py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition-[background-color,color,box-shadow,transform] duration-140 active:scale-[0.97] cursor-pointer"
                            title="Tampilan Compact Rapat">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M3 8h18M3 12h18M3 16h18M3 20h18" />
                        </svg>
                        <span class="hidden sm:inline">Rapat</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. GRID VIEW -->
        <div x-show="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4.5">
            @forelse($products as $p)
                @php
                    $stok = (int) ($p->stok_items_sum_jumlah ?? 0);
                    $customer = auth('customer')->user();
                    $price = app(\App\Modules\Pos\Services\PricingService::class)->resolve($p, $customer);
                    $stockInfo = app(\App\Modules\Marketplace\Services\WebKonsumenService::class)->formatStockDisplay($stok);
                    $thumbImg = $p->thumbnail_url ?: $p->gambar;
                @endphp
                <a href="{{ route('shop.detail', $p->slug) }}"
                   class="group bg-white dark:bg-ink-900 rounded-2xl border border-ink-200/80 dark:border-white/[0.08] hover:border-up-primary/40 dark:hover:border-up-primary/50 shadow-sm hover:shadow-md transition-[border-color,box-shadow,transform] duration-140 overflow-hidden flex flex-col active:scale-[0.98] min-w-0 w-full">
                    <!-- Image canvas -->
                    <div class="aspect-square w-full bg-ink-50 dark:bg-ink-950 flex items-center justify-center relative overflow-hidden">
                        @if($thumbImg)
                            <img src="{{ $thumbImg }}"
                                 alt="{{ $p->nama }}"
                                 loading="lazy"
                                 class="aspect-square w-full h-full object-cover transition-transform duration-200 ease-out group-hover:scale-105"
                                 onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                        @endif
                        <div class="flex flex-col items-center justify-center text-ink-300 dark:text-ink-600 p-4 text-center {{ $thumbImg ? 'hidden' : '' }}">
                            <svg class="w-8 sm:w-10 h-8 sm:h-10 stroke-[1.25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            <span class="text-[9px] mt-1 font-semibold uppercase tracking-wider text-ink-400 dark:text-ink-500">Ute Parts</span>
                        </div>

                        <!-- Badges top row -->
                        <div class="absolute top-2 inset-x-2 flex items-center justify-between pointer-events-none overflow-hidden gap-1">
                            @if((int) ($p->total_terjual ?? 0) > 0)
                                <span class="text-[9px] font-bold bg-ink-900/80 dark:bg-white/90 backdrop-blur-md text-amber-300 dark:text-ink-950 px-2 py-0.5 rounded-full flex items-center gap-0.5 shadow-sm">
                                    ★ <span class="hidden xs:inline">Terlaris</span>
                                </span>
                            @else
                                <span></span>
                            @endif

                            @if($stok <= 0)
                                <span class="text-[9px] font-bold bg-up-red text-white px-2 py-0.5 rounded-full shadow-sm">
                                    Habis
                                </span>
                            @elseif($price['diskon_nominal'] > 0)
                                <span class="text-[9px] font-bold bg-up-accent text-white px-2 py-0.5 rounded-full shadow-sm tabular-nums">
                                    -{{ number_format($price['diskon_nominal'] / $price['harga_dasar'] * 100, 0) }}%
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Meta info & pricing -->
                    <div class="p-3 sm:p-4 flex flex-col flex-1 justify-between gap-2.5 min-w-0">
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap overflow-hidden text-[10px] text-ink-500 dark:text-ink-400">
                                <span class="font-bold uppercase tracking-wider truncate max-w-[100px]">
                                    {{ ($p->relationLoaded('kategoriRelasi') ? $p->kategoriRelasi?->nama : null) ?? (is_array($p->kategori) ? implode(', ', $p->kategori) : (string) $p->kategori) }}
                                </span>
                                @if($p->kondisi)
                                    <span>•</span>
                                    <span class="font-semibold uppercase">{{ $p->kondisi }}</span>
                                @endif
                            </div>

                            <h3 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white line-clamp-2 leading-snug group-hover:text-up-primary transition-colors break-words">
                                {{ $p->nama }}
                            </h3>

                            @if($p->relationLoaded('tipeHps') && $p->tipeHps->isNotEmpty())
                                <p class="text-[11px] text-up-primary font-medium truncate">
                                    Fit: {{ $p->tipeHps->pluck('model')->take(2)->join(', ') }}
                                </p>
                            @endif
                        </div>

                        <!-- Price & stock footer -->
                        <div class="pt-2 border-t border-ink-100 dark:border-white/[0.06] min-w-0">
                            @if($price['diskon_nominal'] > 0)
                                <span class="block text-[10px] sm:text-xs text-ink-400 line-through tabular-nums truncate">
                                    Rp {{ number_format($price['harga_dasar'], 0, ',', '.') }}
                                </span>
                            @endif
                            <div class="text-sm sm:text-base font-bold text-ink-900 dark:text-white tracking-tight tabular-nums truncate">
                                Rp {{ number_format($price['harga'], 0, ',', '.') }}
                            </div>

                            <div class="flex items-center justify-between gap-1 mt-1.5 text-[10px] min-w-0">
                                <span class="inline-flex items-center gap-1.5 font-medium truncate {{ $stockInfo['is_available'] ? ($stockInfo['is_low'] ? 'text-amber-500 font-semibold' : 'text-up-mint') : 'text-up-red' }}">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $stockInfo['is_available'] ? ($stockInfo['is_low'] ? 'bg-amber-500 animate-pulse' : 'bg-up-mint') : 'bg-up-red' }}"></span>
                                    <span class="truncate">{{ $stockInfo['label'] }}</span>
                                </span>
                                @if(($webConfig['show_sold_counter'] ?? true) && (int) ($p->total_terjual ?? 0) > 0)
                                    <span class="text-ink-400 tabular-nums hidden xs:inline flex-shrink-0">{{ $p->total_terjual }} terjual</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-16 text-center">
                    <p class="text-sm text-ink-500">Tidak ada suku cadang yang cocok dengan kriteria pencarian.</p>
                </div>
            @endforelse
        </div>

        <!-- 2. LIST VIEW -->
        <div x-show="viewMode === 'list'" class="space-y-2.5" x-cloak>
            @forelse($products as $p)
                @php
                    $stok = (int) ($p->stok_items_sum_jumlah ?? 0);
                    $customer = auth('customer')->user();
                    $price = app(\App\Modules\Pos\Services\PricingService::class)->resolve($p, $customer);
                    $stockInfo = app(\App\Modules\Marketplace\Services\WebKonsumenService::class)->formatStockDisplay($stok);
                    $thumbImg = $p->thumbnail_url ?: $p->gambar;
                @endphp
                <a href="{{ route('shop.detail', $p->slug) }}"
                   class="group bg-white dark:bg-ink-900 rounded-2xl border border-ink-200/80 dark:border-white/[0.08] hover:border-up-primary/40 dark:hover:border-up-primary/50 shadow-sm p-3 sm:p-4 flex items-center justify-between gap-4 transition-[border-color,box-shadow,transform] duration-140 active:scale-[0.99]">
                    <div class="flex items-center gap-3.5 min-w-0 flex-1">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-ink-50 dark:bg-ink-950 flex-shrink-0 overflow-hidden flex items-center justify-center relative">
                            @if($thumbImg)
                                <img src="{{ $thumbImg }}" alt="{{ $p->nama }}" class="w-full h-full object-cover"
                                     onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                            @endif
                            <span class="text-xs font-bold text-ink-400 items-center justify-center w-full h-full" style="{{ $thumbImg ? 'display:none;' : 'display:flex;' }}">PART</span>
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center gap-2 text-[10px] text-ink-500">
                                <span class="font-bold uppercase tracking-wider">{{ ($p->relationLoaded('kategoriRelasi') ? $p->kategoriRelasi?->nama : null) ?? (is_array($p->kategori) ? implode(', ', $p->kategori) : (string) $p->kategori) }}</span>
                                @if($p->kondisi)
                                    <span>•</span>
                                    <span class="font-semibold uppercase text-ink-600 dark:text-ink-300">{{ $p->kondisi }}</span>
                                @endif
                                @if($p->sku)
                                    <span class="hidden sm:inline font-mono">({{ $p->sku }})</span>
                                @endif
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white truncate group-hover:text-up-primary transition-colors">
                                {{ $p->nama }}
                            </h3>
                            @if($p->relationLoaded('tipeHps') && $p->tipeHps->isNotEmpty())
                                <p class="text-[11px] text-up-primary font-medium truncate">
                                    Kompatibel: {{ $p->tipeHps->pluck('model')->take(3)->join(', ') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="text-right flex-shrink-0 pl-2 space-y-1">
                        @if($price['diskon_nominal'] > 0)
                            <span class="block text-[11px] text-ink-400 line-through tabular-nums">
                                Rp {{ number_format($price['harga_dasar'], 0, ',', '.') }}
                            </span>
                        @endif
                        <div class="text-sm sm:text-base font-bold text-ink-900 dark:text-white tabular-nums">
                            Rp {{ number_format($price['harga'], 0, ',', '.') }}
                        </div>
                        <div class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $stockInfo['is_available'] ? ($stockInfo['is_low'] ? 'text-amber-500' : 'text-up-mint') : 'text-up-red' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $stockInfo['is_available'] ? ($stockInfo['is_low'] ? 'bg-amber-500 animate-pulse' : 'bg-up-mint') : 'bg-up-red' }}"></span>
                            <span>{{ $stockInfo['label'] }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="py-12 text-center text-sm text-ink-500">Tidak ada produk ditemukan.</div>
            @endforelse
        </div>

        <!-- 3. COMPACT VIEW (Speed Shopping / Table-like Rows) -->
        <div x-show="viewMode === 'compact'" class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-200/80 dark:border-white/[0.08] overflow-hidden shadow-sm" x-cloak>
            <div class="divide-y divide-ink-100 dark:divide-white/[0.06]">
                @forelse($products as $p)
                    @php
                        $stok = (int) ($p->stok_items_sum_jumlah ?? 0);
                        $customer = auth('customer')->user();
                        $price = app(\App\Modules\Pos\Services\PricingService::class)->resolve($p, $customer);
                        $stockInfo = app(\App\Modules\Marketplace\Services\WebKonsumenService::class)->formatStockDisplay($stok);
                    @endphp
                    <a href="{{ route('shop.detail', $p->slug) }}"
                       class="px-3.5 py-2.5 flex items-center justify-between gap-3 hover:bg-ink-50 dark:hover:bg-white/[0.03] transition-colors group">
                        <div class="min-w-0 flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $stockInfo['is_available'] ? ($stockInfo['is_low'] ? 'bg-amber-500 animate-pulse' : 'bg-up-mint') : 'bg-up-red' }}"></span>
                            <div class="truncate">
                                <span class="text-xs sm:text-sm font-semibold text-ink-900 dark:text-white group-hover:text-up-primary transition-colors">
                                    {{ $p->nama }}
                                </span>
                                @if($p->sku)
                                    <span class="text-[10px] font-mono text-ink-400 ml-1.5 hidden sm:inline">{{ $p->sku }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-4 flex-shrink-0 text-right">
                            <span class="text-xs text-ink-500 tabular-nums hidden xs:inline">{{ $stockInfo['label'] }}</span>
                            <span class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white tabular-nums">
                                Rp {{ number_format($price['harga'], 0, ',', '.') }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="py-8 text-center text-xs text-ink-500">Tidak ada data.</div>
                @endforelse
            </div>
        </div>

        <!-- Mid-Catalog Promo Banner (CMS) -->
        @php
            $midBanners = $webBanners->where('posisi', 'mid_catalog');
        @endphp
        @if($midBanners->isNotEmpty())
            <div class="pt-6">
                @foreach($midBanners as $mb)
                    <a href="{{ $mb->target_url ?: '#' }}" class="block rounded-3xl overflow-hidden border border-white/10 shadow-lg hover:border-up-primary/40 transition-all duration-200 group">
                        <img src="{{ $mb->gambar_desktop }}" alt="{{ $mb->judul }}" class="w-full h-auto max-h-48 sm:max-h-64 object-cover">
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Tier Membership / Reseller Teaser (CMS) -->
        @if(($webConfig['show_tier_teaser'] ?? true) && !auth('customer')->check())
            <div class="mt-8 p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-up-primary/10 via-indigo-600/10 to-transparent border border-up-primary/20 flex flex-col sm:flex-row items-center justify-between gap-6 shadow-sm">
                <div class="space-y-1.5 text-center sm:text-left">
                    <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-up-primary">Peluang Teknisi &amp; Mitra Bengkel</span>
                    <h4 class="text-base sm:text-xl font-bold text-ink-900 dark:text-white">Dapatkan Harga Khusus Grosir &amp; Poin Cashback Servis</h4>
                    <p class="text-xs sm:text-sm text-ink-500 dark:text-ink-400 max-w-xl">
                        Tingkatkan margin laba servis HP Anda dengan harga tier khusus teknisi, garansi ganti unit toko, dan pengambilan instan di seluruh cabang.
                    </p>
                </div>
                <a href="{{ route('customer.register') }}" class="px-6 py-3 rounded-2xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs sm:text-sm shadow-lg shadow-up-primary/30 active:scale-[0.97] transition-all flex-shrink-0 whitespace-nowrap">
                    Daftar Akun Teknisi &rarr;
                </a>
            </div>
        @endif

        <div class="pt-6">
            {{ $products->links() }}
        </div>
    </section>
</div>
