<div class="space-y-6"
     x-data="{
        device: @entangle('previewDevice'),
        activeTab: @entangle('activeTab'),
     }"
     @keydown.window.prevent.cmd.s="$wire.save()"
     @keydown.window.prevent.ctrl.s="$wire.save()">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/[0.08]">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs text-ink-400">
                <a href="{{ route('pengaturan') }}" class="hover:text-white transition-colors">Pengaturan</a>
                <span>/</span>
                <span class="text-white font-medium">Manajemen Web Konsumen</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight mt-1 flex items-center gap-2.5 flex-wrap">
                <span>Manajemen Halaman Web Konsumen</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/20 text-up-primary border border-up-primary/30">
                    CMS Storefront
                </span>
            </h1>
            <p class="text-xs text-ink-400 mt-0.5 leading-relaxed">
                Konfigurasi section hero, kebijakan privasi stok, banner promo, dan kontak toko tanpa edit kode.
            </p>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap shrink-0">
            <span class="hidden xl:inline-flex text-[11px] text-ink-400 font-mono items-center gap-1 bg-white/[0.04] px-2.5 py-1 rounded-lg border border-white/5">
                <kbd class="px-1 py-0.5 rounded bg-black/40 border border-white/10 text-slate-300">Cmd/Ctrl</kbd> + <kbd class="px-1 py-0.5 rounded bg-black/40 border border-white/10 text-slate-300">S</kbd> simpan
            </span>

            <a href="{{ url('/') }}" target="_blank"
               class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-2.5 rounded-xl text-xs font-medium text-ink-300 bg-white/5 hover:bg-white/10 hover:text-white border border-white/10 transition-[background-color,color,transform] duration-140 active:scale-[0.97] min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <span>Lihat Web</span>
            </a>

            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 active:scale-[0.97] transition-[background-color,transform,box-shadow] duration-140 shadow-sm shadow-up-primary/40 cursor-pointer disabled:opacity-50 min-h-[44px]">
                <svg wire:loading.remove wire:target="save" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="save" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Simpan Perubahan</span>
            </button>
        </div>
    </div>

    <!-- Tab Navigation: Segmented Touch Carousel -->
    <div class="relative border-b border-white/[0.08] pb-1">
        <nav class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 max-w-full flex-nowrap scrollbar-none scroll-smooth touch-pan-x -mx-1 px-1 sm:mx-0 sm:px-0">
            <a href="{{ url('/app/pengaturan/web-konsumen/hero') }}" wire:click.prevent="setTab('hero')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'hero' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                </svg>
                <span>Section Hero</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/banner') }}" wire:click.prevent="setTab('banner')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'banner' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Banner &amp; Media</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'banner' ? 'bg-white/20 text-white' : 'bg-white/10 text-slate-300' }}">{{ $banners->count() }}</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/panduan') }}" wire:click.prevent="setTab('panduan')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'panduan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Knowledge Base</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'panduan' ? 'bg-white/20 text-white' : 'bg-white/10 text-slate-300' }}">{{ $articles->total() }}</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/review') }}" wire:click.prevent="setTab('review')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'review' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>
                <span>Ulasan &amp; Testimoni</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'review' ? 'bg-white/20 text-white' : 'bg-white/10 text-slate-300' }}">{{ $reviews->total() }}</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/faq') }}" wire:click.prevent="setTab('faq')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'faq' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>FAQ &amp; Garansi</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'faq' ? 'bg-white/20 text-white' : 'bg-white/10 text-slate-300' }}">{{ $faqs->count() }}</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/stok') }}" wire:click.prevent="setTab('stok')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'stok' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>Kebijakan Stok &amp; Katalog</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/branding') }}" wire:click.prevent="setTab('branding')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'branding' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                </svg>
                <span>Branding &amp; Topbar</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/kontak') }}" wire:click.prevent="setTab('kontak')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'kontak' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span>Kontak &amp; Pop-up</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/pembayaran') }}" wire:click.prevent="setTab('pembayaran')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'pembayaran' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/[0.03] text-ink-400 hover:text-white hover:bg-white/[0.07] border border-white/5' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Metode Pembayaran</span>
            </a>

            <a href="{{ url('/app/pengaturan/web-konsumen/preview') }}" wire:click.prevent="setTab('preview')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color,color] duration-140 active:scale-[0.97] min-h-[44px] flex items-center gap-2 flex-nowrap shrink-0 whitespace-nowrap cursor-pointer {{ $activeTab === 'preview' ? 'bg-up-mint text-ink-950 shadow-md shadow-up-mint/25' : 'bg-up-mint/10 text-up-mint hover:bg-up-mint/20 border border-up-mint/20' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span>Live Simulator</span>
            </a>
        </nav>
    </div>

    <!-- Tab 1: Hero Section -->
    @if ($activeTab === 'hero')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">
        <div class="lg:col-span-8 space-y-5 sm:space-y-6">
            <!-- Mode Tampilan Hero & Banner -->
            <x-prism.glass-card class="p-4 sm:p-6 space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-white">Mode Layout Section Hero &amp; Banner</h2>
                    <p class="text-xs text-ink-400">Pilih apakah posisi hero tetap, atau digantikan oleh banner carousel layar penuh.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="flex flex-col justify-between p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_display_mode'] === 'hero_standard' ? 'bg-up-primary/10 border-up-primary text-white shadow-sm' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                        <div class="flex items-start gap-2.5">
                            <input type="radio" wire:model.live="heroForm.hero_display_mode" value="hero_standard" class="mt-0.5 text-up-primary focus:ring-0">
                            <div>
                                <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>Hero Tetap Standar</span>
                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-white/10 text-slate-300 font-mono">Default</span>
                                </div>
                                <div class="text-[11px] text-ink-400 mt-1 leading-snug">
                                    Section editorial teks branding dengan widget kanan (member card / mini slider).
                                </div>
                            </div>
                        </div>
                    </label>

                    <label class="flex flex-col justify-between p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_display_mode'] === 'carousel_replace' ? 'bg-up-primary/10 border-up-primary text-white shadow-sm' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                        <div class="flex items-start gap-2.5">
                            <input type="radio" wire:model.live="heroForm.hero_display_mode" value="carousel_replace" class="mt-0.5 text-up-primary focus:ring-0">
                            <div>
                                <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>Gantikan Hero</span>
                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-up-accent/20 text-up-accent font-semibold">Slider</span>
                                </div>
                                <div class="text-[11px] text-ink-400 mt-1 leading-snug">
                                    Carousel banner layar penuh modern menggantikan seluruh posisi section hero di atas.
                                </div>
                            </div>
                        </div>
                    </label>

                    <label class="flex flex-col justify-between p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_display_mode'] === 'hero_and_carousel' ? 'bg-up-primary/10 border-up-primary text-white shadow-sm' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                        <div class="flex items-start gap-2.5">
                            <input type="radio" wire:model.live="heroForm.hero_display_mode" value="hero_and_carousel" class="mt-0.5 text-up-primary focus:ring-0">
                            <div>
                                <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>Hero + Carousel</span>
                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-up-mint/20 text-up-mint font-semibold">Kombinasi</span>
                                </div>
                                <div class="text-[11px] text-ink-400 mt-1 leading-snug">
                                    Section hero editorial tetap di atas, disusul banner slider penuh di bawahnya.
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
            </x-prism.glass-card>

            <!-- Foto Background Hero & Kontras Dinamis -->
            <x-prism.glass-card class="p-4 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/[0.06]">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Foto Background &amp; Kontras Teks Dinamis</h2>
                        <p class="text-xs text-ink-400">Gunakan foto custom sebagai latar belakang hero dengan proteksi keterbacaan teks otomatis.</p>
                    </div>
                    @if ($uploadHeroBg || $heroForm['hero_bg_image'])
                    <button type="button" wire:click="removeHeroBg"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-red-400 bg-red-500/10 hover:bg-red-500/20 active:scale-[0.97] transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Hapus Background</span>
                    </button>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                    <div class="sm:col-span-4">
                        <div class="relative w-full h-28 rounded-2xl bg-black/40 border border-white/10 overflow-hidden flex items-center justify-center group">
                            @if ($uploadHeroBg)
                                <img src="{{ $uploadHeroBg->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover">
                                <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-up-primary/80 text-white">Baru</span>
                            @elseif ($heroForm['hero_bg_image'])
                                <img src="{{ $heroForm['hero_bg_image'] }}" alt="Hero Background" class="w-full h-full object-cover">
                                <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-white/20 text-white">Aktif</span>
                            @else
                                <div class="text-center p-3">
                                    <svg class="w-6 h-6 mx-auto text-ink-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-[10px] text-ink-400">Gradien Standar</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="sm:col-span-8 space-y-2">
                        <input type="file" wire:model="uploadHeroBg" accept="image/*"
                               class="w-full text-xs text-ink-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer">
                        <p class="text-[11px] text-ink-500">
                            Disarankan format WebP/JPG ukuran 1920×800px (rasio 21:9 atau landscape). Maksimal 4MB.
                        </p>
                    </div>
                </div>

                <!-- Pilihan Filter Kontras Dinamis -->
                <div class="pt-3 border-t border-white/[0.06] space-y-2">
                    <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider">Proteksi Keterbacaan Teks (Dynamic Contrast)</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_bg_overlay'] === 'scrim_dark' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                            <input type="radio" wire:model.live="heroForm.hero_bg_overlay" value="scrim_dark" class="text-up-primary focus:ring-0">
                            <div>
                                <div class="text-xs font-semibold text-white">Gradien Gelap Halus</div>
                                <div class="text-[10px] text-ink-400">Kombinasi gelap kiri &amp; foto kanan</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_bg_overlay'] === 'scrim_heavy' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                            <input type="radio" wire:model.live="heroForm.hero_bg_overlay" value="scrim_heavy" class="text-up-primary focus:ring-0">
                            <div>
                                <div class="text-xs font-semibold text-white">Lapisan Gelap Intens</div>
                                <div class="text-[10px] text-ink-400">Untuk foto yang sangat terang/ramai</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_bg_overlay'] === 'glass_card' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                            <input type="radio" wire:model.live="heroForm.hero_bg_overlay" value="glass_card" class="text-up-primary focus:ring-0">
                            <div>
                                <div class="text-xs font-semibold text-white">Panel Kaca Frosted</div>
                                <div class="text-[10px] text-ink-400">Teks dibungkus card kaca buram</div>
                            </div>
                        </label>
                    </div>
                </div>
            </x-prism.glass-card>

            <!-- Form Utama Hero -->
            <x-prism.glass-card class="p-4 sm:p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 sm:pb-4 border-b border-white/[0.06]">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Editorial Hero Section</h2>
                        <p class="text-xs text-ink-400">Section utama di halaman depan katalog web konsumen.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer min-h-[44px]">
                        <input type="checkbox" wire:model.live="heroForm.hero_active" class="sr-only peer">
                        <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[12px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-primary"></div>
                        <span class="ml-2.5 text-xs font-medium text-slate-300 select-none">{{ $heroForm['hero_active'] ? 'Hero Aktif' : 'Nonaktif' }}</span>
                    </label>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Pill Badge Text</label>
                        <input type="text" wire:model.live="heroForm.hero_badge_text"
                               placeholder="Contoh: Premium Guaranteed Suku Cadang &amp; Servis"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Headline Utama</label>
                        <textarea wire:model.live="heroForm.hero_headline" rows="2"
                                  placeholder="Suku Cadang Presisi. Garansi Nyata Toko Resmi."
                                  class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary"></textarea>
                        <span class="text-[11px] text-ink-500">Gunakan baris baru (enter) untuk pemenggalan judul hero yang proporsional.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Subheadline / Deskripsi</label>
                        <textarea wire:model.live="heroForm.hero_subheadline" rows="3"
                                  placeholder="Layar LCD grade OEM, modul baterai densitas tinggi..."
                                  class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Widget Sisi Kanan Hero</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_right_widget'] === 'member_card' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                                <input type="radio" wire:model.live="heroForm.hero_right_widget" value="member_card" class="text-up-primary focus:ring-0">
                                <div>
                                    <div class="text-xs font-semibold">Member Privileges Card</div>
                                    <div class="text-[11px] text-ink-400">Keuntungan diskon tier reseller & teknisi</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $heroForm['hero_right_widget'] === 'banner_slider' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                                <input type="radio" wire:model.live="heroForm.hero_right_widget" value="banner_slider" class="text-up-primary focus:ring-0">
                                <div>
                                    <div class="text-xs font-semibold">Banner Slider Promosi</div>
                                    <div class="text-[11px] text-ink-400">Carousel gambar banner promo aktif</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </x-prism.glass-card>

            <!-- Fitur Highlights & Stat Counters -->
            <x-prism.glass-card class="p-4 sm:p-6 space-y-5">
                <h2 class="text-sm font-semibold text-white">Trust Highlights &amp; Statistik</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    @foreach ($heroForm['hero_features'] as $idx => $feat)
                    <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/10 space-y-2">
                        <div class="text-[11px] font-semibold text-ink-400 uppercase tracking-wider">Highlight #{{ $idx + 1 }}</div>
                        <input type="text" wire:model.live="heroForm.hero_features.{{ $idx }}.text"
                               class="w-full px-2.5 py-1.5 rounded-lg bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-white/[0.06] grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    @foreach ($heroForm['hero_stats'] as $idx => $stat)
                    <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/10 space-y-2">
                        <label class="block text-[11px] font-semibold text-ink-400 uppercase tracking-wider">Stat #{{ $idx + 1 }} ({{ $stat['label'] }})</label>
                        @if ($stat['type'] === 'dynamic_catalog')
                            <div class="text-xs text-up-mint font-semibold py-1.5 px-2.5 bg-up-mint/10 rounded-lg border border-up-mint/20">
                                Dihitung Otomatis dari Total Produk
                            </div>
                        @else
                            <input type="text" wire:model.live="heroForm.hero_stats.{{ $idx }}.val"
                                   class="w-full px-2.5 py-1.5 rounded-lg bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                        @endif
                    </div>
                    @endforeach
                </div>
            </x-prism.glass-card>
        </div>

        <!-- Quick Preview Widget Hero -->
        <div class="lg:col-span-4 space-y-4">
            @php
                $previewBg = $uploadHeroBg ? $uploadHeroBg->temporaryUrl() : ($heroForm['hero_bg_image'] ?? '');
            @endphp
            <div class="relative overflow-hidden rounded-2xl bg-[#0F172A] border border-white/10 text-white shadow-xl">
                <!-- Background image preview if present -->
                @if ($previewBg)
                    <img src="{{ $previewBg }}" alt="Background" class="absolute inset-0 w-full h-full object-cover">
                    @if ($heroForm['hero_bg_overlay'] === 'scrim_heavy')
                        <div class="absolute inset-0 bg-black/85 backdrop-blur-[1px]"></div>
                    @elseif ($heroForm['hero_bg_overlay'] === 'glass_card')
                        <div class="absolute inset-0 bg-black/40"></div>
                    @else
                        <!-- scrim_dark default -->
                        <div class="absolute inset-0 bg-gradient-to-r from-[#0F172A] via-[#0F172A]/80 to-transparent"></div>
                    @endif
                @endif

                <div class="relative z-10 p-5 space-y-4 {{ ($previewBg && $heroForm['hero_bg_overlay'] === 'glass_card') ? 'm-3 p-4 rounded-xl bg-black/60 border border-white/15 backdrop-blur-md' : '' }}">
                    <div class="flex items-center justify-between text-xs text-ink-300 pb-2 border-b border-white/10">
                        <div class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-wider">
                            <span>Pratinjau Hero Cepat</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-white/10 text-slate-300">{{ $heroForm['hero_display_mode'] }}</span>
                        </div>
                        <span class="w-2 h-2 rounded-full {{ $heroForm['hero_active'] ? 'bg-up-mint' : 'bg-red-400' }}"></span>
                    </div>

                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-md text-[10px] font-semibold text-slate-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                            <span>{{ $heroForm['hero_badge_text'] ?: 'Badge Hero' }}</span>
                        </div>

                        <h3 class="text-base font-black leading-tight text-white whitespace-pre-line drop-shadow-sm">
                            {{ $heroForm['hero_headline'] ?: 'Headline Toko' }}
                        </h3>

                        <p class="text-xs text-slate-200 leading-relaxed line-clamp-3 drop-shadow-sm">
                            {{ $heroForm['hero_subheadline'] ?: 'Deskripsi keunggulan produk...' }}
                        </p>

                        <div class="pt-3 border-t border-white/10 flex flex-wrap gap-2">
                            @foreach ($heroForm['hero_features'] as $f)
                            <span class="text-[10px] px-2 py-1 rounded-md bg-white/10 backdrop-blur-sm border border-white/15 text-slate-200">
                                ✓ {{ $f['text'] }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Tab 2: Banner Promosi -->
    @if ($activeTab === 'banner')
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-white">Banner Promosi &amp; Event</h2>
                <p class="text-xs text-ink-400">Atur slider banner di homepage, kartu promo tengah katalog, dan pop-up diskon.</p>
            </div>
            <button type="button" wire:click="openCreateBanner"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 active:scale-[0.97] transition-[background-color,transform] duration-140 cursor-pointer min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Banner Baru</span>
            </button>
        </div>

        <!-- Mobile Card List View (< sm) -->
        <div class="sm:hidden space-y-3">
            @forelse ($banners as $b)
            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3.5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center text-xs font-mono font-bold text-white shrink-0">
                            #{{ $b->urutan }}
                        </span>
                        <div class="w-16 h-10 rounded-lg overflow-hidden bg-black/40 border border-white/10 flex items-center justify-center shrink-0">
                            @if ($b->gambar_desktop)
                                <img src="{{ $b->gambar_desktop }}" alt="{{ $b->judul }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-[9px] text-ink-600">No Img</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-xs font-bold text-white truncate">{{ $b->judul }}</h3>
                            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-white/5 border border-white/10 text-slate-300 uppercase">
                                    {{ str_replace('_', ' ', $b->posisi) }}
                                </span>
                                @if ($b->badge_label)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-up-accent/20 text-up-accent border border-up-accent/30">
                                        {{ $b->badge_label }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if ($b->target_url)
                <div class="text-[11px] font-mono text-ink-400 bg-black/30 px-2.5 py-1 rounded-lg border border-white/5 truncate">
                    🔗 {{ $b->target_url }}
                </div>
                @endif

                <div class="flex items-center justify-between pt-2 border-t border-white/[0.06] gap-2">
                    <button type="button" wire:click="toggleBannerStatus({{ $b->id }})"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition-colors cursor-pointer min-h-[44px] {{ $b->is_active ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-red-500/15 text-red-400 border border-red-500/30' }}">
                        <span class="w-2 h-2 rounded-full {{ $b->is_active ? 'bg-up-mint animate-pulse' : 'bg-red-400' }}"></span>
                        <span>{{ $b->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openEditBanner({{ $b->id }})"
                                class="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 text-xs font-semibold border border-white/10 active:scale-[0.97] transition-[background-color,transform] duration-140 cursor-pointer min-h-[44px]">
                            Edit
                        </button>
                        <button type="button" wire:click="deleteBanner({{ $b->id }})"
                                wire:confirm="Hapus banner ini?"
                                class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-semibold border border-red-500/20 active:scale-[0.97] transition-[background-color,transform] duration-140 cursor-pointer min-h-[44px]">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-8 text-center rounded-2xl bg-white/[0.02] border border-white/5 text-ink-500 text-xs">
                Belum ada banner terdaftar. Klik tombol Tambah Banner Baru.
            </div>
            @endforelse
        </div>

        <!-- Desktop Table View (>= sm) -->
        <x-prism.glass-card class="hidden sm:block overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/[0.02] text-ink-400 font-semibold uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-4">Urutan</th>
                            <th class="py-3 px-4">Preview</th>
                            <th class="py-3 px-4">Judul &amp; Label</th>
                            <th class="py-3 px-4">Posisi</th>
                            <th class="py-3 px-4">Target URL</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.06] text-ink-300">
                        @forelse ($banners as $b)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-white">{{ $b->urutan }}</td>
                            <td class="py-3 px-4">
                                <div class="w-20 h-10 rounded-lg overflow-hidden bg-black/40 border border-white/10 flex items-center justify-center">
                                    @if ($b->gambar_desktop)
                                        <img src="{{ $b->gambar_desktop }}" alt="{{ $b->judul }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-[9px] text-ink-600">No Img</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-white">{{ $b->judul }}</div>
                                @if ($b->badge_label)
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-up-accent/20 text-up-accent border border-up-accent/30">
                                        {{ $b->badge_label }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-white/5 border border-white/10 text-slate-300 uppercase">
                                    {{ str_replace('_', ' ', $b->posisi) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-ink-400 max-w-[150px] truncate">
                                {{ $b->target_url ?: '-' }}
                            </td>
                            <td class="py-3 px-4">
                                <button type="button" wire:click="toggleBannerStatus({{ $b->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition-colors cursor-pointer min-h-[32px] {{ $b->is_active ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-red-500/15 text-red-400 border border-red-500/30' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $b->is_active ? 'bg-up-mint animate-pulse' : 'bg-red-400' }}"></span>
                                    <span>{{ $b->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </button>
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <button type="button" wire:click="openEditBanner({{ $b->id }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition-colors cursor-pointer">
                                    Edit
                                </button>
                                <button type="button" wire:click="deleteBanner({{ $b->id }})"
                                        wire:confirm="Hapus banner ini?"
                                        class="px-2.5 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 transition-colors cursor-pointer">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-ink-500 text-xs">
                                Belum ada banner terdaftar. Klik tombol Tambah Banner Baru.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-prism.glass-card>
    </div>
    @endif

    <!-- Tab 3: Kebijakan Stok & Katalog -->
    @if ($activeTab === 'stok')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 space-y-6">
            <!-- Kebijakan Tampilan Stok -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="text-sm font-semibold text-white">Kebijakan Privasi & Tampilan Stok Konsumen</h2>
                    <p class="text-xs text-ink-400">Atur cara ketersediaan stok disajikan ke pengunjung toko publik.</p>
                </div>

                <div class="space-y-3">
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $stockForm['stock_display_mode'] === 'threshold_only' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                        <input type="radio" wire:model.live="stockForm.stock_display_mode" value="threshold_only" class="mt-0.5 text-up-primary focus:ring-0">
                        <div>
                            <div class="text-xs font-semibold">Batas Kritis / Urgensi (Rekomendasi)</div>
                            <div class="text-[11px] text-ink-400">
                                Jika stok di atas batas: tampilkan <span class="text-up-mint font-semibold">"Stok Tersedia"</span>. Jika stok &le; ambang batas: tampilkan <span class="text-up-amber font-semibold">"Sisa X pcs (Segera Habis)"</span> untuk dorong konversi beli.
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $stockForm['stock_display_mode'] === 'badge_only' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                        <input type="radio" wire:model.live="stockForm.stock_display_mode" value="badge_only" class="mt-0.5 text-up-primary focus:ring-0">
                        <div>
                            <div class="text-xs font-semibold">Status Badge Saja (Privasi Total)</div>
                            <div class="text-[11px] text-ink-400">
                                Hanya menampilkan <span class="text-up-mint font-semibold">"Tersedia"</span> atau <span class="text-red-400 font-semibold">"Habis"</span> tanpa membocorkan angka riil inventaris kepada kompetitor.
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $stockForm['stock_display_mode'] === 'exact_number' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                        <input type="radio" wire:model.live="stockForm.stock_display_mode" value="exact_number" class="mt-0.5 text-up-primary focus:ring-0">
                        <div>
                            <div class="text-xs font-semibold">Nominal Persis (Transparansi Penuh)</div>
                            <div class="text-[11px] text-ink-400">
                                Selalu menampilkan jumlah pasti fisik inventaris (contoh: <span class="text-white font-mono font-semibold">"Stok: 142 pcs"</span>).
                            </div>
                        </div>
                    </label>
                </div>

                @if ($stockForm['stock_display_mode'] === 'threshold_only')
                <div class="pt-3 border-t border-white/[0.06] flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold text-white">Ambang Batas Peringatan Stok Kritis</div>
                        <div class="text-[11px] text-ink-400">Nilai minimum sebelum label "Sisa X pcs" ditampilkan.</div>
                    </div>
                    <div class="w-32">
                        <input type="number" min="1" max="50" wire:model.live="stockForm.stock_threshold_limit"
                               class="w-full px-3 py-1.5 rounded-lg bg-black/30 border border-white/10 text-white text-base sm:text-xs font-mono text-center focus:outline-none focus:border-up-primary">
                    </div>
                </div>
                @endif
            </x-prism.glass-card>

            <!-- Pengaturan Fitur Halaman Detail & Katalog -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-semibold text-white">Opsi Tampilan Detail & Katalog</h2>

                <div class="space-y-3">
                    <label class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/10 cursor-pointer">
                        <div>
                            <div class="text-xs font-semibold text-white">Rincian Ketersediaan Stok per Cabang</div>
                            <div class="text-[11px] text-ink-400">Tampilkan breakdown stok di tiap toko cabang pada halaman detail produk</div>
                        </div>
                        <input type="checkbox" wire:model.live="stockForm.show_branch_breakdown" class="rounded border-white/20 text-up-primary focus:ring-0">
                    </label>

                    <label class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/10 cursor-pointer">
                        <div>
                            <div class="text-xs font-semibold text-white">Indikator Unit Terjual</div>
                            <div class="text-[11px] text-ink-400">Tampilkan counter popularitas produk (contoh: "Terjual 50+")</div>
                        </div>
                        <input type="checkbox" wire:model.live="stockForm.show_sold_counter" class="rounded border-white/20 text-up-primary focus:ring-0">
                    </label>

                    <label class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/10 cursor-pointer">
                        <div>
                            <div class="text-xs font-semibold text-white">Teaser Upgrade Member Teknisi/Reseller</div>
                            <div class="text-[11px] text-ink-400">Tampilkan kartu promosi registrasi akun reseller dengan harga khusus</div>
                        </div>
                        <input type="checkbox" wire:model.live="stockForm.show_tier_teaser" class="rounded border-white/20 text-up-primary focus:ring-0">
                    </label>
                </div>

                <div class="pt-3 border-t border-white/[0.06] space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/10">
                        <div>
                            <div class="text-xs font-semibold text-white">Menu Filter Baris Katalog</div>
                            <div class="text-[11px] text-ink-400">Tampilkan atau sembunyikan seluruh menu filter di halaman katalog web konsumen</div>
                        </div>
                        <input type="checkbox" wire:model.live="stockForm.allowed_filters.enabled" class="rounded border-white/20 text-up-primary focus:ring-0">
                    </div>

                    @if($stockForm['allowed_filters']['enabled'] ?? true)
                        <div>
                            <div class="text-xs font-semibold text-white mb-2">Pilih Filter yang Ingin Dimunculkan:</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.kategori" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Kategori</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.kondisi" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Kondisi (Baru/OEM)</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.hp" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Merk & Model Gadget</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.brand" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Brand Sparepart</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.harga" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Rentang Harga</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.kualitas" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Grade Kualitas</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-300">
                                    <input type="checkbox" wire:model.live="stockForm.allowed_filters.stok" class="rounded border-white/20 text-up-primary focus:ring-0">
                                    <span>Filter Ketersediaan Stok</span>
                                </label>
                            </div>
                        </div>
                    @endif
                </div>
            </x-prism.glass-card>
        </div>

        <div class="lg:col-span-4 space-y-4">
            <div class="p-5 rounded-2xl bg-black/40 border border-white/10 space-y-3">
                <div class="text-xs font-semibold text-white">Simulasi Penampilan Badge Stok</div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/10 space-y-2">
                    <div class="text-[11px] text-ink-400">Contoh produk A (Stok 3 pcs):</div>
                    @if ($stockForm['stock_display_mode'] === 'exact_number')
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-500 border border-amber-500/20">
                            Stok: 3 pcs
                        </span>
                    @elseif ($stockForm['stock_display_mode'] === 'badge_only')
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                            Stok Tersedia
                        </span>
                    @else
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-500 border border-amber-500/20 animate-pulse">
                            Sisa 3 pcs (Segera Habis)
                        </span>
                    @endif
                </div>

                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/10 space-y-2">
                    <div class="text-[11px] text-ink-400">Contoh produk B (Stok 45 pcs):</div>
                    @if ($stockForm['stock_display_mode'] === 'exact_number')
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                            Stok: 45 pcs
                        </span>
                    @else
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                            Stok Tersedia
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Tab 4: Branding & Topbar -->
    @if ($activeTab === 'branding')
    <div class="max-w-3xl space-y-6">
        <x-prism.glass-card class="p-5 sm:p-6 space-y-5">
            <h2 class="text-sm font-semibold text-white">Identitas & Logo Toko</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-2">Logo Storefront</label>
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-black/40 border border-white/10 p-2 flex items-center justify-center overflow-hidden">
                            @if ($uploadLogo)
                                <img src="{{ $uploadLogo->temporaryUrl() }}" alt="Preview" class="w-full h-full object-contain">
                            @elseif ($brandingForm['store_logo_url'])
                                <img src="{{ $brandingForm['store_logo_url'] }}" alt="Logo" class="w-full h-full object-contain">
                            @else
                                <span class="text-xs text-ink-600">No Logo</span>
                            @endif
                        </div>
                        <div class="space-y-1.5 flex-1">
                            <input type="file" wire:model="uploadLogo" accept="image/*"
                                   class="text-xs text-ink-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20">
                            <p class="text-[11px] text-ink-500">Format disarankan SVG atau PNG transparan (maks 2MB).</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-white/[0.06] space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-white">Announcement / Running Text Baris Teratas</div>
                            <div class="text-[11px] text-ink-400">Pita promosi bergerak di atas navigasi utama</div>
                        </div>
                        <input type="checkbox" wire:model.live="brandingForm.topbar_active" class="rounded border-white/20 text-up-primary focus:ring-0">
                    </div>

                    @if ($brandingForm['topbar_active'])
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Teks Pengumuman</label>
                        <input type="text" wire:model.live="brandingForm.topbar_text"
                               placeholder="Garansi Resmi Toko & Kirim Cepat se-Indonesia"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Link Tautan Pengumuman (Opsional)</label>
                        <input type="text" wire:model.live="brandingForm.topbar_url"
                               placeholder="https://... atau /shop"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary">
                    </div>
                    @endif
                </div>

                <!-- Foto Background Halaman Login Konsumen -->
                <div class="pt-4 border-t border-white/[0.06] space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-white">Background Halaman Login Pelanggan</div>
                            <div class="text-[11px] text-ink-400">Kustomisasi wallpaper dan nuansa visual auth pelanggan</div>
                        </div>
                        @if ($uploadLoginBg || $brandingForm['customer_login_bg_image'])
                        <button type="button" wire:click="removeLoginBg"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-red-400 bg-red-500/10 hover:bg-red-500/20 active:scale-[0.97] transition-all cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Hapus Background</span>
                        </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                        <div class="sm:col-span-4">
                            <div class="relative w-full h-28 rounded-2xl bg-black/40 border border-white/10 overflow-hidden flex items-center justify-center group">
                                @if ($uploadLoginBg)
                                    <img src="{{ $uploadLoginBg->temporaryUrl() }}" alt="Preview Login Bg" class="w-full h-full object-cover">
                                    <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-up-primary/80 text-white">Baru</span>
                                @elseif ($brandingForm['customer_login_bg_image'])
                                    <img src="{{ $brandingForm['customer_login_bg_image'] }}" alt="Login Bg" class="w-full h-full object-cover">
                                    <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-white/20 text-white">Aktif</span>
                                @else
                                    <div class="text-center p-3">
                                        <svg class="w-6 h-6 mx-auto text-ink-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-[10px] text-ink-400">Default Minimalist</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="sm:col-span-8 space-y-2">
                            <input type="file" wire:model="uploadLoginBg" accept="image/*"
                                   class="w-full text-xs text-ink-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer">
                            <p class="text-[11px] text-ink-500">
                                Disarankan format WebP/JPG ukuran 1920×1080px (rasio 16:9). Maksimal 4MB.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider">Efek Scrim / Kaca Background Login</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $brandingForm['customer_login_bg_overlay'] === 'scrim_dark' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                                <input type="radio" wire:model.live="brandingForm.customer_login_bg_overlay" value="scrim_dark" class="text-up-primary focus:ring-0">
                                <div>
                                    <div class="text-xs font-semibold text-white">Gradien Gelap Halus</div>
                                    <div class="text-[10px] text-ink-400">Kontras seimbang &amp; bersih</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $brandingForm['customer_login_bg_overlay'] === 'scrim_heavy' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                                <input type="radio" wire:model.live="brandingForm.customer_login_bg_overlay" value="scrim_heavy" class="text-up-primary focus:ring-0">
                                <div>
                                    <div class="text-xs font-semibold text-white">Gelap Intens 80%</div>
                                    <div class="text-[10px] text-ink-400">Fokus utama ke card login</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $brandingForm['customer_login_bg_overlay'] === 'frosted_glass' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-white/[0.02] border-white/10 text-ink-300 hover:bg-white/[0.04]' }}">
                                <input type="radio" wire:model.live="brandingForm.customer_login_bg_overlay" value="frosted_glass" class="text-up-primary focus:ring-0">
                                <div>
                                    <div class="text-xs font-semibold text-white">Frosted Blur Mewah</div>
                                    <div class="text-[10px] text-ink-400">Glassmorphism blur tinggi</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-4 border-t border-white/10 space-y-3">
                <h3 class="text-sm font-semibold text-white">SEO (Homepage)</h3>
                <div>
                    <label class="block text-xs font-semibold text-ink-300 mb-1">SEO Title</label>
                    <input type="text" wire:model.live="brandingForm.seo_title" class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-sm" placeholder="Judul SEO homepage">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-300 mb-1">SEO Description</label>
                    <textarea wire:model.live="brandingForm.seo_description" rows="2" class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-sm" placeholder="Deskripsi SEO homepage"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-300 mb-1">SEO Keywords</label>
                    <input type="text" wire:model.live="brandingForm.seo_keywords" class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-sm" placeholder="keyword1, keyword2">
                </div>
            </div>
        </x-prism.glass-card>
    </div>
    @endif

    <!-- Tab 5: Kontak & Pop-up Promo -->
    @if ($activeTab === 'kontak')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 space-y-6">
            <!-- Kontak WhatsApp -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/[0.06]">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Floating Button WhatsApp CS</h2>
                        <p class="text-xs text-ink-400">Tombol pintas chat di pojok kanan bawah web konsumen.</p>
                    </div>
                    <input type="checkbox" wire:model.live="contactForm.wa_floating_active" class="rounded border-white/20 text-up-primary focus:ring-0">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Nomor WhatsApp Toko</label>
                        <input type="text" wire:model.live="contactForm.wa_number"
                               placeholder="6281234567890"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary">
                        <span class="text-[11px] text-ink-500">Awali kode negara 62 tanpa spasi atau strip.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Jam Operasional</label>
                        <input type="text" wire:model.live="contactForm.operating_hours"
                               placeholder="Senin - Sabtu: 08:30 - 20:30 WIB"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Template Pesan WhatsApp</label>
                    <textarea wire:model.live="contactForm.wa_default_msg" rows="2"
                              class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs placeholder:text-ink-600 focus:outline-none focus:border-up-primary"></textarea>
                </div>
            </x-prism.glass-card>

            <!-- Tautan Media Sosial -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-semibold text-white">Akun Media Sosial & Marketplace Resmi</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Instagram URL</label>
                        <input type="text" wire:model.live="contactForm.social_links.instagram"
                               placeholder="https://instagram.com/bremspace"
                               class="w-full px-3.5 py-2 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">TikTok URL</label>
                        <input type="text" wire:model.live="contactForm.social_links.tiktok"
                               placeholder="https://tiktok.com/@bremspace"
                               class="w-full px-3.5 py-2 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Shopee Store URL</label>
                        <input type="text" wire:model.live="contactForm.social_links.shopee"
                               placeholder="https://shopee.co.id/bremspace"
                               class="w-full px-3.5 py-2 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Tokopedia Store URL</label>
                        <input type="text" wire:model.live="contactForm.social_links.tokopedia"
                               placeholder="https://tokopedia.com/bremspace"
                               class="w-full px-3.5 py-2 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                </div>
            </x-prism.glass-card>

            <!-- Pop-up Promosi -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/[0.06]">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Modal Pop-up Promosi</h2>
                        <p class="text-xs text-ink-400">Banner melayang otomatis saat pengunjung pertama kali membuka website.</p>
                    </div>
                    <input type="checkbox" wire:model.live="contactForm.popup_active" class="rounded border-white/20 text-up-primary focus:ring-0">
                </div>

                @if ($contactForm['popup_active'])
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Pilih Banner Promosi</label>
                        <select wire:model.live="contactForm.popup_banner_id"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                            <option value="">-- Pilih Banner --</option>
                            @foreach ($banners as $b)
                                <option value="{{ $b->id }}">{{ $b->judul }} ({{ $b->posisi }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 uppercase tracking-wider mb-1.5">Frekuensi Tampil</label>
                        <select wire:model.live="contactForm.popup_frequency"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                            <option value="once_per_day">1x Sehari per Pengunjung</option>
                            <option value="once_per_session">1x per Sesi Browser</option>
                            <option value="always">Setiap Buka Halaman (Untuk Testing)</option>
                        </select>
                    </div>
                </div>
                @endif
            </x-prism.glass-card>
        </div>
    </div>
    @endif

    <!-- Tab 6: Pengaturan Metode Pembayaran MP Toko -->
    @if ($activeTab === 'pembayaran')
    <div class="space-y-6">
        @php
            $activeQris = app(\App\Modules\Pos\Services\QrisService::class)->getActiveQris();
            $duitkuActive = (bool) ($paymentForm['payment_mp_duitku_enabled'] ?? false);
            $qrisTokoActive = (bool) ($paymentForm['payment_mp_qris_toko_enabled'] ?? false);
        @endphp

        <!-- Banner Info Aturan Otomatis -->
        <div class="p-4 sm:p-5 rounded-2xl border transition-all duration-200 {{ $duitkuActive && $qrisTokoActive ? 'bg-up-primary/10 border-up-primary/30 text-indigo-100' : ($qrisTokoActive ? 'bg-up-mint/10 border-up-mint/30 text-emerald-100' : 'bg-up-accent/10 border-up-accent/30 text-amber-100') }}">
            <div class="flex items-start gap-3.5">
                <div class="p-2.5 rounded-xl shrink-0 {{ $duitkuActive && $qrisTokoActive ? 'bg-up-primary text-white' : ($qrisTokoActive ? 'bg-up-mint text-ink-950 font-bold' : 'bg-up-accent text-white') }}">
                    @if($duitkuActive && $qrisTokoActive)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    @elseif($qrisTokoActive)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        @if($duitkuActive && $qrisTokoActive)
                            <span>Mode Hibrida Cerdas Aktif</span>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-up-primary/30 text-indigo-300 border border-up-primary/40">Duitku + QRIS Toko</span>
                        @elseif($qrisTokoActive)
                            <span>Mode QRIS Toko Langsung</span>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-up-mint/30 text-up-mint border border-up-mint/40">Hemat Biaya 100%</span>
                        @elseif($duitkuActive)
                            <span>Mode Payment Gateway Penuh</span>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-up-accent/30 text-up-accent border border-up-accent/40">Duitku Only</span>
                        @else
                            <span class="text-up-red font-bold">Semua Opsi Nonaktif (Wajib Pilih Minimal 1)</span>
                        @endif
                    </h3>
                    <p class="text-xs text-ink-300 leading-relaxed mt-1">
                        @if($duitkuActive && $qrisTokoActive)
                            Sistem secara otomatis <strong>menghilangkan channel QRIS dari gateway Duitku</strong> dan <strong>menggantikannya dengan QRIS Dinamis Toko</strong> (bebas potongan biaya transaksi). Metode Virtual Account Transfer Bank (BCA, Mandiri, BRI, BNI) dan E-Wallet Duitku tetap aktif melayani pembeli.
                        @elseif($qrisTokoActive)
                            Pembeli marketplace hanya dapat menyelesaikan pesanan melalui <strong>Scan QRIS Dinamis Toko</strong> (langsung terverifikasi lunas otomatis via webhook Android tanpa potongan fee gateway).
                        @elseif($duitkuActive)
                            Seluruh opsi pembayaran online (Transfer VA, E-Wallet, Retail, dan QRIS Duitku) diproses secara penuh melalui gateway Duitku.
                        @else
                            Mohon aktifkan minimal salah satu metode pembayaran agar pembeli di marketplace dapat menyelesaikan transaksi pesanan.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Selector Toggles -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Card 1: Duitku Gateway -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary font-black text-base">
                            💳
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white">Payment Gateway Duitku</h4>
                            <p class="text-[11px] text-ink-400">Virtual Account Bank, E-Wallet, Gerai Retail</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="paymentForm.payment_mp_duitku_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-primary"></div>
                    </label>
                </div>

                <div class="pt-2 border-t border-white/5 space-y-2 text-xs text-ink-400">
                    <div class="flex items-center justify-between">
                        <span>Status Saluran:</span>
                        <span class="font-bold {{ $duitkuActive ? 'text-up-mint' : 'text-ink-500' }}">
                            {{ $duitkuActive ? '● Aktif di Marketplace' : '○ Dinonaktifkan' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Cakupan Bank:</span>
                        <span class="text-white font-mono text-[11px]">BCA, Mandiri, BRI, BNI, Permata, dll</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Pengaturan Kredensial:</span>
                        <a href="/app/pengaturan?tab=integrasi" class="text-up-primary hover:underline font-semibold flex items-center gap-1">
                            Pusat Pengaturan ↗
                        </a>
                    </div>
                </div>
            </x-prism.glass-card>

            <!-- Card 2: QRIS Toko Langsung -->
            <x-prism.glass-card class="p-5 sm:p-6 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-up-mint/10 border border-up-mint/20 flex items-center justify-center text-up-mint font-black text-base">
                            ⚡
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white">QRIS Dinamis Toko</h4>
                            <p class="text-[11px] text-ink-400">Scan QRIS Langsung (Bebas Biaya Gateway / Fee 0%)</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="paymentForm.payment_mp_qris_toko_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-mint"></div>
                    </label>
                </div>

                <div class="pt-2 border-t border-white/5 space-y-2 text-xs text-ink-400">
                    <div class="flex items-center justify-between">
                        <span>Status Saluran:</span>
                        <span class="font-bold {{ $qrisTokoActive ? 'text-up-mint' : 'text-ink-500' }}">
                            {{ $qrisTokoActive ? '● Aktif di Marketplace' : '○ Dinonaktifkan' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Merchant Aktif:</span>
                        <span class="text-white font-semibold truncate max-w-[180px]">
                            {{ $activeQris?->merchant_name ?: '(Belum Disetup)' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Verifikasi Otomatis:</span>
                        <span class="text-up-mint font-semibold">Webhook Android (MacroDroid / PayHook)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Kelola QRIS &amp; Secret:</span>
                        <a href="/app/pengaturan?tab=qris" class="text-up-primary hover:underline font-semibold flex items-center gap-1">
                            Pengaturan QRIS Toko ↗
                        </a>
                    </div>
                </div>
            </x-prism.glass-card>
        </div>
    </div>
    @endif

    <!-- Tab 4: Knowledge Base / Panduan Teknis -->
    @if ($activeTab === 'panduan')
    <div class="space-y-6">
        <x-prism.glass-card class="p-4 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-white/10">
                <div>
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span>Pusat Edukasi &amp; Knowledge Base Panduan</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/20 text-up-primary border border-up-primary/30">
                            {{ $articles->total() }} Artikel
                        </span>
                    </h2>
                    <p class="text-xs text-ink-400">Kelola artikel panduan teknis, edukasi suku cadang, video YouTube embed, dan rekomendasi produk terkait.</p>
                </div>
                <button type="button" wire:click="openCreateArticle"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 active:scale-[0.97] transition-[background-color,transform] duration-140 shadow-sm cursor-pointer min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Tulis Panduan Baru</span>
                </button>
            </div>

            <!-- Search & Filter Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
                <div class="sm:col-span-8">
                    <input type="text" wire:model.live.debounce.300ms="articleSearch" placeholder="Cari judul artikel, topik perbaikan, atau kata kunci..."
                           class="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white placeholder-ink-400 focus:outline-none focus:border-up-primary min-h-[40px]">
                </div>
                <div class="sm:col-span-4">
                    <select wire:model.live="articleKategoriFilter"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white focus:outline-none focus:border-up-primary min-h-[40px]">
                        <option value="">-- Semua Kategori Panduan --</option>
                        @foreach($allCategories as $cOpt)
                            <option value="{{ $cOpt }}">{{ ucwords(str_replace('_', ' ', $cOpt)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Table Daftar Artikel -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-ink-300">
                    <thead class="bg-white/[0.02] text-ink-400 uppercase text-[10px] tracking-wider border-b border-white/10">
                        <tr>
                            <th class="py-3 px-3">Cover &amp; Media</th>
                            <th class="py-3 px-3">Judul &amp; Ringkasan</th>
                            <th class="py-3 px-3">Kategori</th>
                            <th class="py-3 px-3">Commerce Link</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.06]">
                        @forelse ($articles as $art)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3 px-3 w-24">
                                <div class="w-20 h-14 rounded-xl overflow-hidden bg-black/40 border border-white/10 relative flex items-center justify-center">
                                    @if ($art->cover_image)
                                        <img src="{{ $art->cover_image }}" alt="{{ $art->judul }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-ink-500 font-bold text-[10px]">No Media</span>
                                    @endif

                                    @if ($art->youtube_video_id)
                                        <span class="absolute top-1 right-1 px-1 rounded bg-red-600 text-white text-[8px] font-black uppercase">YT</span>
                                    @elseif ($art->tipe_media_cover === 'video')
                                        <span class="absolute top-1 right-1 px-1 rounded bg-up-primary text-white text-[8px] font-black uppercase">VID</span>
                                    @elseif ($art->tipe_media_cover === 'gif')
                                        <span class="absolute top-1 right-1 px-1 rounded bg-up-accent text-white text-[8px] font-black uppercase">GIF</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3 max-w-sm">
                                <div class="font-bold text-white text-xs line-clamp-1">{{ $art->judul }}</div>
                                <div class="text-ink-400 text-[11px] line-clamp-1 mt-0.5">{{ $art->ringkasan }}</div>
                                <div class="text-[10px] text-ink-500 font-mono mt-0.5">/panduan/{{ $art->slug }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $art->kategori === 'panduan_servis' ? 'bg-up-primary/20 text-up-primary border border-up-primary/30' : ($art->kategori === 'edukasi_part' ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-accent/20 text-up-accent border border-up-accent/30') }}">
                                    {{ $art->kategori === 'panduan_servis' ? 'Panduan Servis' : ($art->kategori === 'edukasi_part' ? 'Edukasi Suku Cadang' : 'Tips & Perawatan') }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="space-y-0.5 text-[11px]">
                                    @if (!empty($art->produk_terkait_ids))
                                        <div class="text-slate-200">📦 {{ count($art->produk_terkait_ids) }} Part Terkait</div>
                                    @endif
                                    @if ($art->rekomendasi_servis_id)
                                        <div class="text-up-mint">🔧 Layanan Servis Di-link</div>
                                    @endif
                                    @if (empty($art->produk_terkait_ids) && !$art->rekomendasi_servis_id)
                                        <span class="text-ink-500">-</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <button type="button" wire:click="togglePublishArticle({{ $art->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $art->is_published ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-white/10 text-ink-400 border border-white/10' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $art->is_published ? 'bg-emerald-400' : 'bg-ink-500' }}"></span>
                                    <span>{{ $art->is_published ? 'Terbit' : 'Draf' }}</span>
                                </button>
                            </td>
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                <button type="button" wire:click="openEditArticle({{ $art->id }})"
                                        class="px-2.5 py-1 text-xs text-up-primary hover:underline font-semibold cursor-pointer">
                                    Edit
                                </button>
                                <span class="text-ink-500">|</span>
                                <button type="button" wire:click="deleteArticle({{ $art->id }})"
                                        wire:confirm="Yakin ingin menghapus artikel panduan ini?"
                                        class="px-2.5 py-1 text-xs text-red-400 hover:underline cursor-pointer">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-ink-500 text-xs">
                                Belum ada artikel panduan. Klik tombol "Tulis Panduan Baru" di atas.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2">
                {{ $articles->links() }}
            </div>
        </x-prism.glass-card>
    </div>
    @endif

    <!-- Tab 5: Review & Moderasi Testimoni -->
    @if ($activeTab === 'review')
    <div class="space-y-6">
        <x-prism.glass-card class="p-4 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-white/10">
                <div>
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span>Moderasi Ulasan Pembeli &amp; Testimoni Servis</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/20 text-up-primary border border-up-primary/30">
                            {{ $reviews->total() }} Ulasan
                        </span>
                    </h2>
                    <p class="text-xs text-ink-400">Verifikasi ulasan kepuasan suku cadang dan perbaikan sebelum tampil di etalase web konsumen.</p>
                </div>

                <!-- Status Filter Badges -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" wire:click="$set('reviewFilter', 'all')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer {{ $reviewFilter === 'all' ? 'bg-up-primary text-white' : 'bg-white/5 text-ink-400 hover:text-white' }}">
                        Semua
                    </button>
                    <button type="button" wire:click="$set('reviewFilter', 'pending')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer {{ $reviewFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-white/5 text-ink-400 hover:text-white' }}">
                        Menunggu Persetujuan
                    </button>
                    <button type="button" wire:click="$set('reviewFilter', 'approved')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer {{ $reviewFilter === 'approved' ? 'bg-emerald-500 text-white' : 'bg-white/5 text-ink-400 hover:text-white' }}">
                        Disetujui
                    </button>
                    <button type="button" wire:click="$set('reviewFilter', 'featured')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer {{ $reviewFilter === 'featured' ? 'bg-up-accent text-white' : 'bg-white/5 text-ink-400 hover:text-white' }}">
                        ⭐ Unggulan (Featured)
                    </button>
                </div>
            </div>

            <!-- Tipe Filter Tab -->
            <div class="flex items-center gap-2 text-xs">
                <span class="text-ink-400">Tipe Ulasan:</span>
                <button type="button" wire:click="$set('reviewTipe', 'all')" class="px-2.5 py-1 rounded-lg {{ $reviewTipe === 'all' ? 'bg-white/20 text-white font-bold' : 'text-ink-400 hover:text-white' }}">Semua</button>
                <button type="button" wire:click="$set('reviewTipe', 'produk')" class="px-2.5 py-1 rounded-lg {{ $reviewTipe === 'produk' ? 'bg-white/20 text-white font-bold' : 'text-ink-400 hover:text-white' }}">📦 Suku Cadang</button>
                <button type="button" wire:click="$set('reviewTipe', 'servis')" class="px-2.5 py-1 rounded-lg {{ $reviewTipe === 'servis' ? 'bg-white/20 text-white font-bold' : 'text-ink-400 hover:text-white' }}">🔧 Servis Gadget</button>
            </div>

            <!-- List Ulasan Cards -->
            <div class="space-y-3">
                @forelse ($reviews as $rev)
                <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="w-8 h-8 rounded-full bg-gradient-to-tr from-up-primary to-indigo-500 text-white flex items-center justify-center text-xs font-bold">
                                {{ substr($rev->pelanggan?->nama ?? 'Konsumen', 0, 1) }}
                            </span>
                            <div>
                                <span class="font-bold text-white text-xs">{{ $rev->pelanggan?->nama ?? 'Konsumen' }}</span>
                                <span class="text-[10px] text-ink-400 ml-1">({{ $rev->created_at->format('d M Y H:i') }})</span>
                            </div>

                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $rev->tipe_ulasan === 'produk' ? 'bg-indigo-500/20 text-indigo-300' : 'bg-emerald-500/20 text-emerald-300' }}">
                                {{ $rev->tipe_ulasan === 'produk' ? 'Part: '.($rev->produk?->nama ?? 'Item') : 'Servis: '.($rev->tiketServis?->no_tiket ?? 'Tiket') }}
                            </span>

                            @if ($rev->is_featured)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                    ⭐ Featured
                                </span>
                            @endif
                        </div>

                        <!-- Rating Stars -->
                        <div class="flex items-center gap-1 text-amber-400 text-xs">
                            @for ($s = 1; $s <= 5; $s++)
                                <span>{{ $s <= $rev->rating ? '★' : '☆' }}</span>
                            @endfor
                            <span class="text-white font-bold ml-1">({{ $rev->rating }}/5)</span>
                        </div>
                    </div>

                    @if ($rev->judul_ulasan)
                        <h4 class="font-bold text-slate-100 text-xs">{{ $rev->judul_ulasan }}</h4>
                    @endif

                    <p class="text-xs text-ink-300 leading-relaxed">{{ $rev->isi_ulasan }}</p>

                    @if (!empty($rev->media_foto) && is_array($rev->media_foto))
                    <div class="flex items-center gap-2 overflow-x-auto pt-1">
                        @foreach ($rev->media_foto as $foto)
                            <a href="{{ $foto }}" target="_blank" class="w-16 h-16 rounded-xl overflow-hidden border border-white/10 shrink-0">
                                <img src="{{ $foto }}" class="w-full h-full object-cover">
                            </a>
                        @endforeach
                    </div>
                    @endif

                    @if ($rev->tanggapan_admin)
                    <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 text-xs text-slate-200">
                        <div class="font-bold text-up-primary text-[11px] mb-0.5">Tanggapan Toko:</div>
                        <p>{{ $rev->tanggapan_admin }}</p>
                    </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="pt-2 border-t border-white/[0.06] flex items-center justify-between flex-wrap gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            @if (!$rev->is_approved)
                                <button type="button" wire:click="approveReview({{ $rev->id }})"
                                        class="px-3 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 font-semibold cursor-pointer">
                                    ✓ Setujui Tampil
                                </button>
                            @else
                                <button type="button" wire:click="rejectReview({{ $rev->id }})"
                                        class="px-3 py-1.5 rounded-lg bg-white/10 text-ink-400 hover:text-white font-semibold cursor-pointer">
                                    ✕ Sembunyikan
                                </button>
                            @endif

                            <button type="button" wire:click="toggleFeaturedReview({{ $rev->id }})"
                                    class="px-3 py-1.5 rounded-lg border border-amber-400/30 text-amber-300 hover:bg-amber-400/10 font-semibold cursor-pointer">
                                {{ $rev->is_featured ? 'Batalkan Featured' : '⭐ Sematkan ke Beranda' }}
                            </button>

                            <button type="button" wire:click="openTanggapanModal({{ $rev->id }})"
                                    class="px-3 py-1.5 rounded-lg bg-up-primary/20 text-up-primary hover:bg-up-primary/30 font-semibold cursor-pointer">
                                💬 {{ $rev->tanggapan_admin ? 'Ubah Tanggapan' : 'Beri Tanggapan' }}
                            </button>
                        </div>

                        <button type="button" wire:click="deleteReview({{ $rev->id }})"
                                wire:confirm="Hapus ulasan ini secara permanen?"
                                class="text-red-400 hover:underline cursor-pointer">
                            Hapus
                        </button>
                    </div>
                </div>
                @empty
                <div class="py-12 text-center text-ink-500 text-xs">
                    Belum ada ulasan yang sesuai dengan filter ini.
                </div>
                @endforelse
            </div>

            <div class="pt-2">
                {{ $reviews->links() }}
            </div>
        </x-prism.glass-card>
    </div>
    @endif

    <!-- Tab 6: FAQ & Garansi -->
    @if ($activeTab === 'faq')
    <div class="space-y-6">
        <x-prism.glass-card class="p-4 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-white/10">
                <div>
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <span>Pusat Tanya Jawab (FAQ) &amp; Kebijakan Garansi</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/20 text-up-primary border border-up-primary/30">
                            {{ $faqs->count() }} Pertanyaan
                        </span>
                    </h2>
                    <p class="text-xs text-ink-400">Kelola informasi syarat garansi LCD, segel toko, retur barang cacat, dan proses pengiriman.</p>
                </div>
                <button type="button" wire:click="openCreateFaq"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 active:scale-[0.97] transition-[background-color,transform] duration-140 shadow-sm cursor-pointer min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah FAQ Baru</span>
                </button>
            </div>

            <!-- List FAQ Cards -->
            <div class="space-y-3">
                @forelse ($faqs as $fq)
                <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/10 text-slate-300">
                                {{ strtoupper($fq->kategori) }}
                            </span>
                            <h4 class="font-bold text-white text-xs">{{ $fq->pertanyaan }}</h4>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="toggleFaqStatus({{ $fq->id }})"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold cursor-pointer {{ $fq->is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/10 text-ink-500' }}">
                                {{ $fq->is_active ? 'Aktif' : 'Non-aktif' }}
                            </button>
                            <button type="button" wire:click="openEditFaq({{ $fq->id }})"
                                    class="text-xs text-up-primary hover:underline font-semibold cursor-pointer">
                                Edit
                            </button>
                            <button type="button" wire:click="deleteFaq({{ $fq->id }})"
                                    wire:confirm="Hapus FAQ ini?"
                                    class="text-xs text-red-400 hover:underline cursor-pointer">
                                Hapus
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-ink-400 leading-relaxed whitespace-pre-line">{{ $fq->jawaban }}</p>
                </div>
                @empty
                <div class="py-12 text-center text-ink-500 text-xs">
                    Belum ada FAQ terdaftar. Klik tombol Tambah FAQ Baru di atas.
                </div>
                @endforelse
            </div>
        </x-prism.glass-card>
    </div>
    @endif

    <!-- Tab 7: Live Simulator Preview -->
    @if ($activeTab === 'preview')
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-black/40 border border-white/10">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs font-semibold text-white">Device Frame:</span>
                <div class="inline-flex rounded-lg p-0.5 bg-white/5 border border-white/10">
                    <button type="button" wire:click="setDevice('desktop')"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition-[background-color,color,transform] duration-140 active:scale-[0.97] cursor-pointer min-h-[36px] {{ $previewDevice === 'desktop' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}">
                        Desktop (1280px)
                    </button>
                    <button type="button" wire:click="setDevice('mobile')"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition-[background-color,color,transform] duration-140 active:scale-[0.97] cursor-pointer min-h-[36px] {{ $previewDevice === 'mobile' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}">
                        Mobile (390px)
                    </button>
                </div>
            </div>

            <div class="text-[11px] text-ink-400">
                Simulasi rendering instan storefront publik
            </div>
        </div>

        <div class="flex justify-center p-3 sm:p-8 bg-black/60 rounded-2xl sm:rounded-3xl border border-white/10 min-h-[400px] sm:min-h-[600px] overflow-x-auto overflow-y-auto">
            <div class="{{ $previewDevice === 'mobile' ? 'w-[390px] max-w-full border-[6px] sm:border-[8px] border-slate-800 rounded-[32px] sm:rounded-[40px] shadow-2xl overflow-hidden shrink-0' : 'w-full max-w-5xl rounded-2xl border border-white/10 shadow-2xl overflow-hidden' }} bg-[#F1F5F9] dark:bg-[#090D16] transition-all duration-300">
                <!-- Top Announcement Simulator -->
                @if ($brandingForm['topbar_active'] && $brandingForm['topbar_text'])
                <div class="bg-gradient-to-r from-up-primary to-indigo-600 text-white text-[11px] font-semibold py-1.5 overflow-hidden select-none">
                    <div class="animate-marquee flex items-center gap-8 whitespace-nowrap">
                        @for ($i = 0; $i < 2; $i++)
                            <div class="flex items-center gap-8 shrink-0">
                                @for ($j = 0; $j < 3; $j++)
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse flex-shrink-0"></span>
                                        <span>{{ $brandingForm['topbar_text'] }}</span>
                                    </div>
                                @endfor
                            </div>
                        @endfor
                    </div>
                </div>
                @endif

                <!-- Navbar Simulator -->
                <div class="bg-white/90 dark:bg-[#0F172A]/90 backdrop-blur border-b border-slate-200 dark:border-white/10 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="{{ $brandingForm['store_logo_url'] }}" alt="Logo" class="w-6 h-6 object-contain">
                        <span class="font-bold text-xs text-ink-900 dark:text-white">{{ config('app.name', 'Bremspace') }}</span>
                    </div>
                    <div class="text-[10px] text-up-mint font-semibold px-2 py-0.5 rounded-full bg-up-mint/10">
                        Official Store
                    </div>
                </div>

                <!-- Hero / Banner Simulator -->
                @php
                    $simBg = $uploadHeroBg ? $uploadHeroBg->temporaryUrl() : ($heroForm['hero_bg_image'] ?? '');
                    $displayMode = $heroForm['hero_display_mode'] ?? 'hero_standard';
                @endphp

                @if ($heroForm['hero_active'])
                    @if ($displayMode === 'carousel_replace')
                        <!-- Carousel Replace Mode Simulator -->
                        <div class="p-4 sm:p-6">
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 border border-white/10 shadow-xl min-h-[200px] flex flex-col justify-between">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-up-accent/20 border border-up-accent/40 text-[10px] font-bold text-up-accent w-fit">
                                    <span>★ Banner Carousel Mode (Menggantikan Hero)</span>
                                </div>
                                <div class="space-y-1.5 my-3">
                                    <div class="text-xs font-semibold text-up-mint uppercase tracking-wider">Promo Spesial Teknisi</div>
                                    <h2 class="text-base sm:text-xl font-black text-white">Diskon Spesial Mitra Bengkel &amp; Reseller 2026</h2>
                                    <p class="text-xs text-slate-300">Pengiriman instant &amp; jaminan retur suku cadang terkalibrasi.</p>
                                </div>
                                <div class="flex items-center justify-between pt-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-5 h-1.5 rounded-full bg-up-mint"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-white/40"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-white/40"></span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-mono">Geser Otomatis (5s)</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Hero Standard or Hero + Carousel -->
                        <div class="p-4 sm:p-6 space-y-4">
                            <div class="relative overflow-hidden rounded-2xl bg-[#0F172A] text-white border border-white/10 shadow-lg">
                                @if ($simBg)
                                    <img src="{{ $simBg }}" alt="Hero Background" class="absolute inset-0 w-full h-full object-cover">
                                    @if ($heroForm['hero_bg_overlay'] === 'scrim_heavy')
                                        <div class="absolute inset-0 bg-black/85 backdrop-blur-[1px]"></div>
                                    @elseif ($heroForm['hero_bg_overlay'] === 'glass_card')
                                        <div class="absolute inset-0 bg-black/40"></div>
                                    @else
                                        <!-- scrim_dark default -->
                                        <div class="absolute inset-0 bg-gradient-to-r from-[#0F172A] via-[#0F172A]/85 to-transparent"></div>
                                    @endif
                                @endif

                                <div class="relative z-10 p-5 sm:p-6 space-y-4 {{ ($simBg && $heroForm['hero_bg_overlay'] === 'glass_card') ? 'm-3 p-4 rounded-xl bg-black/60 border border-white/15 backdrop-blur-md' : '' }}">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-md text-[10px] font-semibold text-slate-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                                        <span>{{ $heroForm['hero_badge_text'] }}</span>
                                    </div>

                                    <h2 class="text-lg sm:text-2xl font-black text-white leading-tight whitespace-pre-line drop-shadow-sm">
                                        {{ $heroForm['hero_headline'] }}
                                    </h2>

                                    <p class="text-xs text-slate-200 leading-relaxed drop-shadow-sm">
                                        {{ $heroForm['hero_subheadline'] }}
                                    </p>

                                    <div class="flex flex-wrap gap-2 pt-1">
                                        @foreach ($heroForm['hero_features'] as $f)
                                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 backdrop-blur-sm border border-white/15 text-[10px] text-slate-200">
                                            <span>✓</span>
                                            <span>{{ $f['text'] }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            @if ($displayMode === 'hero_and_carousel')
                            <div class="p-3.5 rounded-xl bg-white/[0.03] border border-dashed border-white/20 text-center">
                                <span class="text-xs text-up-mint font-semibold">＋ Carousel Banner Layar Penuh Aktif Tepat di Bawah Hero</span>
                            </div>
                            @endif
                        </div>
                    @endif
                @endif

                <!-- Sample Product Card Simulator -->
                <div class="p-4 sm:p-6">
                    <div class="text-xs font-semibold text-ink-500 uppercase tracking-wider mb-3">Simulasi Kartu Produk</div>
                    <div class="max-w-xs p-3.5 rounded-2xl bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-white/10 shadow-sm space-y-2">
                        <div class="h-28 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-xs text-ink-400">
                            Foto Produk
                        </div>
                        <div class="text-xs font-bold text-ink-900 dark:text-white truncate">LCD Screen OEM Grade iPhone 13 Pro</div>
                        <div class="text-sm font-black text-up-primary">Rp 1.450.000</div>

                        <!-- Dynamic Stock Policy Preview -->
                        <div class="pt-2 border-t border-slate-100 dark:border-white/10 flex items-center justify-between">
                            @if ($stockForm['stock_display_mode'] === 'exact_number')
                                <span class="text-[10px] font-semibold text-emerald-500">Stok: 4 pcs</span>
                            @elseif ($stockForm['stock_display_mode'] === 'badge_only')
                                <span class="text-[10px] font-semibold text-emerald-500">Tersedia</span>
                            @else
                                <span class="text-[10px] font-semibold text-amber-500 animate-pulse">Sisa 4 pcs (Segera Habis)</span>
                            @endif

                            @if ($stockForm['show_sold_counter'])
                                <span class="text-[10px] text-ink-400">Terjual 32</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Banner Create/Edit Modal -->
    @if ($bannerModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="w-full max-w-lg rounded-2xl bg-[#0F172A] border border-white/10 p-4 sm:p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto"
             @click.away="$wire.set('bannerModalOpen', false)"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white">
                    {{ $bannerForm['id'] ? 'Edit Banner Promosi' : 'Tambah Banner Baru' }}
                </h3>
                <button type="button" wire:click="$set('bannerModalOpen', false)" class="w-8 h-8 flex items-center justify-center rounded-lg text-ink-400 hover:text-white hover:bg-white/10 transition-colors min-h-[44px] min-w-[44px] cursor-pointer">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Judul Banner</label>
                    <input type="text" wire:model="bannerForm.judul" placeholder="Promo Spesial Teknisi"
                           class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    @error('bannerForm.judul') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Badge Tag</label>
                        <input type="text" wire:model="bannerForm.badge_label" placeholder="HOT DEAL"
                               class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Tipe Media</label>
                        <select wire:model.live="bannerForm.tipe_media" class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                            <option value="image">Gambar Statis (JPG, PNG, WebP)</option>
                            <option value="gif">GIF Animasi</option>
                            <option value="video">Video Pendek Loop (MP4, WebM)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Posisi Tampil</label>
                        <select wire:model="bannerForm.posisi" class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                            <option value="hero_slider">Hero Slider</option>
                            <option value="hero_side">Hero Sisi Kanan</option>
                            <option value="mid_catalog">Tengah Katalog</option>
                            <option value="popup_modal">Pop-up Modal</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Target Link URL</label>
                    <input type="text" wire:model="bannerForm.target_url" placeholder="/shop atau https://..."
                           class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                </div>

                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">
                        {{ $bannerForm['tipe_media'] === 'gif' ? 'Upload File GIF Desktop' : ($bannerForm['tipe_media'] === 'video' ? 'Upload Poster Gambar Fallback Desktop' : 'Upload Gambar Desktop') }}
                    </label>
                    <input type="file" wire:model="uploadBannerDesktop" accept="{{ $bannerForm['tipe_media'] === 'gif' ? 'image/gif,image/*' : 'image/*' }}"
                           class="w-full text-ink-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-white/10 file:text-white file:text-[11px]">
                    @error('uploadBannerDesktop') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                @if ($bannerForm['tipe_media'] === 'video')
                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                    <div class="flex items-center gap-2 text-xs font-semibold text-up-mint">
                        <span>🎬 Pengaturan Video Pendek (Autoplay, Loop, Muted)</span>
                    </div>
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Upload File Video Desktop (MP4 / WebM maks 15MB)</label>
                        <input type="file" wire:model="uploadBannerVideoDesktop" accept="video/mp4,video/webm,video/ogg"
                               class="w-full text-ink-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-white/10 file:text-white file:text-[11px]">
                        @if ($bannerForm['video_desktop'])
                            <p class="text-[10px] text-ink-400 font-mono mt-1 truncate">Current: {{ $bannerForm['video_desktop'] }}</p>
                        @endif
                        @error('uploadBannerVideoDesktop') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Upload Video Mobile (Opsional / Rasio Vertikal)</label>
                        <input type="file" wire:model="uploadBannerVideoMobile" accept="video/mp4,video/webm,video/ogg"
                               class="w-full text-ink-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-white/10 file:text-white file:text-[11px]">
                        @if ($bannerForm['video_mobile'])
                            <p class="text-[10px] text-ink-400 font-mono mt-1 truncate">Current: {{ $bannerForm['video_mobile'] }}</p>
                        @endif
                        @error('uploadBannerVideoMobile') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Urutan</label>
                        <input type="number" wire:model="bannerForm.urutan" class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="flex items-center gap-2 cursor-pointer text-white">
                            <input type="checkbox" wire:model="bannerForm.is_active" class="rounded border-white/20 text-up-primary focus:ring-0">
                            <span>Status Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-white/10 flex flex-col-reverse sm:flex-row justify-end gap-2">
                <button type="button" wire:click="$set('bannerModalOpen', false)"
                        class="px-4 py-2.5 rounded-xl text-xs text-ink-400 hover:text-white bg-white/5 active:scale-[0.97] transition-[background-color,color,transform] duration-140 min-h-[44px] cursor-pointer">
                    Batal
                </button>
                <button type="button" wire:click="saveBanner"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 active:scale-[0.97] transition-[background-color,transform] duration-140 min-h-[44px] cursor-pointer">
                    Simpan Banner
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Article Create/Edit Modal -->
    @if ($articleModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="w-full max-w-2xl rounded-2xl bg-[#0F172A] border border-white/10 p-4 sm:p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto"
             @click.away="$wire.set('articleModalOpen', false)">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white">
                    {{ $articleForm['id'] ? 'Edit Artikel Panduan' : 'Tulis Panduan Baru' }}
                </h3>
                <button type="button" wire:click="$set('articleModalOpen', false)" class="w-8 h-8 flex items-center justify-center rounded-lg text-ink-400 hover:text-white hover:bg-white/10 transition-colors min-h-[44px] min-w-[44px] cursor-pointer">✕</button>
            </div>

            <div class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Judul Panduan Teknis <span class="text-red-400">*</span></label>
                    <input type="text" wire:model="articleForm.judul" placeholder="Cara Bedakan LCD Original vs Incell pada iPhone..."
                           class="w-full px-3 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    @error('articleForm.judul') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Kategori Panduan (Pilih / Ketik Kustom)</label>
                        <input type="text"
                               wire:model="articleForm.kategori"
                               list="kategori-list-options"
                               placeholder="Contoh: panduan_servis, edukasi_part, unboxing..."
                               class="w-full px-3 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                        <datalist id="kategori-list-options">
                            @foreach($allCategories as $cOpt)
                                <option value="{{ $cOpt }}">{{ ucwords(str_replace('_', ' ', $cOpt)) }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Custom Slug (Opsional)</label>
                        <input type="text" wire:model="articleForm.slug" placeholder="otomatis-dari-judul"
                               class="w-full px-3 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Ringkasan Singkat (Lead paragraph, maks 300 char) <span class="text-red-400">*</span></label>
                    <textarea wire:model="articleForm.ringkasan" rows="2" maxlength="300" placeholder="Ringkasan penjelasan untuk ditampilkan di kartu panduan..."
                              class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary"></textarea>
                    @error('articleForm.ringkasan') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <!-- Rich Media Cover & YouTube -->
                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                    <div class="text-xs font-semibold text-white flex items-center gap-2">
                        <span>🎬 Media Visual Cover &amp; Video Tutorial</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Tipe Media Cover</label>
                            <select wire:model.live="articleForm.tipe_media_cover" class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                                <option value="image">Gambar Standar (JPG, PNG, WebP)</option>
                                <option value="gif">GIF Animasi</option>
                                <option value="video">Video Pendek Loop (MP4, WebM)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Link Video YouTube</label>
                            <input type="text" wire:model.live.debounce.400ms="articleForm.youtube_url" placeholder="https://www.youtube.com/watch?v=..."
                                   class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary">
                        </div>
                    </div>

                    @php
                        $ytId = \App\Modules\Marketplace\Models\WebArticle::extractYouTubeId($articleForm['youtube_url'] ?? null);
                    @endphp
                    @if ($ytId)
                    <div class="p-2.5 rounded-lg bg-red-600/10 border border-red-500/20 flex items-center gap-3">
                        <img src="https://img.youtube.com/vi/{{ $ytId }}/hqdefault.jpg" class="w-16 h-10 object-cover rounded">
                        <div class="min-w-0 flex-1">
                            <span class="text-[11px] font-bold text-red-400">YouTube Video Terdeteksi</span>
                            <p class="text-[10px] text-ink-400 font-mono truncate">ID: {{ $ytId }}</p>
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Upload Cover Gambar / GIF</label>
                            <input type="file" wire:model="uploadArticleCover" accept="image/*"
                                   class="w-full text-ink-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-white/10 file:text-white file:text-[11px]">
                            @if ($articleForm['cover_image'])
                                <p class="text-[10px] text-ink-400 font-mono mt-1 truncate">Current: {{ $articleForm['cover_image'] }}</p>
                            @endif
                        </div>
                        @if ($articleForm['tipe_media_cover'] === 'video')
                        <div>
                            <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Upload Video Cover (MP4 maks 15MB)</label>
                            <input type="file" wire:model="uploadArticleVideo" accept="video/mp4,video/webm"
                                   class="w-full text-ink-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-white/10 file:text-white file:text-[11px]">
                            @if ($articleForm['cover_video'])
                                <p class="text-[10px] text-ink-400 font-mono mt-1 truncate">Current: {{ $articleForm['cover_video'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Konten Lengkap -->
                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Konten Lengkap Panduan <span class="text-red-400">*</span></label>
                    <textarea wire:model="articleForm.konten_lengkap" rows="6" placeholder="Tulis langkah-langkah, analisa kerusakan, dan tips perbaikan secara mendalam..."
                              class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-base sm:text-xs focus:outline-none focus:border-up-primary font-sans"></textarea>
                    @error('articleForm.konten_lengkap') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <!-- Content to Commerce Relations -->
                <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-4">
                    <div class="text-xs font-semibold text-white flex items-center justify-between">
                        <span class="flex items-center gap-2">🛒 Integrasi Belanja &amp; Servis (Content-to-Commerce)</span>
                        <span class="text-[10px] text-ink-400">Smart Search Engine Terpadu</span>
                    </div>

                    <!-- 1. Rekomendasi Jasa Servis -->
                    <div class="space-y-2">
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider text-[11px]">Rekomendasi Layanan Servis Terkait</label>
                        <div class="space-y-2">
                            <input type="text"
                                   wire:model.live.debounce.300ms="servisSearchQuery"
                                   placeholder="Cari jenis servis (misal: ganti lcd, baterai, bypass)..."
                                   class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary">

                            <select wire:model="articleForm.rekomendasi_servis_id" class="w-full px-3 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary">
                                <option value="">-- Tanpa Tautan Layanan Servis --</option>
                                @foreach ($jenisServisList as $js)
                                    <option value="{{ $js->id }}">{{ $js->nama }} (Mulai Rp {{ number_format($js->biaya_jasa, 0, ',', '.') }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- 2. Sematkan Suku Cadang Terkait dengan Smart Search -->
                    <div class="space-y-2 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <label class="block font-semibold text-ink-300 uppercase tracking-wider text-[11px]">Sematkan Suku Cadang Terkait</label>
                            <span class="text-[10px] font-bold text-up-mint">
                                {{ count($articleForm['produk_terkait_ids'] ?? []) }} Part Disematkan
                            </span>
                        </div>

                        <!-- Pencarian Part dengan SmartSearch Engine -->
                        <div class="relative">
                            <input type="text"
                                   wire:model.live.debounce.300ms="partSearchQuery"
                                   placeholder="Ketik nama part, kode SKU, tipe hp (Smart Search: sinonim 'ip11', 'batre', 'lcd')..."
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary">
                        </div>

                        <!-- Hasil Pencarian & Checkbox Toggle Card -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto pr-1">
                            @forelse ($produkList as $pr)
                                @php
                                    $isAttached = in_array($pr->id, (array) ($articleForm['produk_terkait_ids'] ?? []), true);
                                @endphp
                                <div wire:click="toggleProdukTerkait({{ $pr->id }})"
                                     class="p-2.5 rounded-xl border flex items-center justify-between gap-2.5 cursor-pointer transition-colors {{ $isAttached ? 'bg-up-primary/20 border-up-primary text-white' : 'bg-black/30 border-white/5 hover:border-white/20 text-ink-300' }}">
                                    <div class="w-8 h-8 rounded-lg bg-black/40 border border-white/10 overflow-hidden shrink-0 flex items-center justify-center">
                                        @if($pr->foto_utama)
                                            <img src="{{ $pr->foto_utama }}" class="w-full h-full object-cover">
                                        @else
                                            <span class="text-[9px] font-bold text-ink-500">PART</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[11px] font-bold truncate {{ $isAttached ? 'text-white' : 'text-slate-200' }}">{{ $pr->nama }}</div>
                                        <div class="text-[10px] text-ink-400 font-mono">Rp {{ number_format($pr->harga_jual_retail, 0, ',', '.') }}</div>
                                    </div>
                                    <span class="text-xs font-bold shrink-0 {{ $isAttached ? 'text-up-mint' : 'text-ink-500' }}">
                                        {{ $isAttached ? '✓' : '+' }}
                                    </span>
                                </div>
                            @empty
                                <div class="col-span-full py-4 text-center text-[11px] text-ink-400">
                                    Tidak ada produk yang cocok dengan pencarian smart search.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- 3. Status Publikasi -->
                    <div class="pt-2 border-t border-white/10">
                        <label class="flex items-center gap-2 cursor-pointer text-white text-xs">
                            <input type="checkbox" wire:model="articleForm.is_published" class="rounded border-white/20 text-up-primary focus:ring-0">
                            <span>Publikasikan Langsung ke Web Konsumen</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-white/10 flex flex-col-reverse sm:flex-row justify-end gap-2">
                <button type="button" wire:click="$set('articleModalOpen', false)"
                        class="px-4 py-2.5 rounded-xl text-xs text-ink-400 hover:text-white bg-white/5 active:scale-[0.97] transition-all min-h-[44px] cursor-pointer">
                    Batal
                </button>
                <button type="button" wire:click="saveArticle"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 active:scale-[0.97] transition-all min-h-[44px] cursor-pointer">
                    Simpan Panduan
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Tanggapan Review Modal -->
    @if ($tanggapanModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="w-full max-w-md rounded-2xl bg-[#0F172A] border border-white/10 p-5 space-y-4 shadow-2xl"
             @click.away="$wire.set('tanggapanModalOpen', false)">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white">Tanggapan Resmi Toko / Teknisi</h3>
                <button type="button" wire:click="$set('tanggapanModalOpen', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
            </div>

            <div class="space-y-2 text-xs">
                <label class="block font-semibold text-ink-300">Respon Terhadap Ulasan Pelanggan:</label>
                <textarea wire:model="adminTanggapanText" rows="4" placeholder="Terima kasih atas kepercayaannya terhadap layanan kami..."
                          class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary"></textarea>
            </div>

            <div class="pt-3 border-t border-white/10 flex justify-end gap-2">
                <button type="button" wire:click="$set('tanggapanModalOpen', false)"
                        class="px-3.5 py-2 rounded-xl text-xs text-ink-400 hover:text-white bg-white/5 cursor-pointer">
                    Batal
                </button>
                <button type="button" wire:click="saveTanggapanReview"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 cursor-pointer">
                    Simpan Respon
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- FAQ Create/Edit Modal -->
    @if ($faqModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="w-full max-w-lg rounded-2xl bg-[#0F172A] border border-white/10 p-5 space-y-4 shadow-2xl"
             @click.away="$wire.set('faqModalOpen', false)">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white">
                    {{ $faqForm['id'] ? 'Edit Pertanyaan & Jawaban' : 'Tambah FAQ Baru' }}
                </h3>
                <button type="button" wire:click="$set('faqModalOpen', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Kategori</label>
                        <select wire:model="faqForm.kategori" class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary">
                            <option value="garansi">Ketentuan Garansi</option>
                            <option value="servis">Prosedur Servis</option>
                            <option value="pengiriman">Pengiriman &amp; Packing</option>
                            <option value="part_original">Keaslian Suku Cadang</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Urutan</label>
                        <input type="number" wire:model="faqForm.urutan" class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Pertanyaan</label>
                    <input type="text" wire:model="faqForm.pertanyaan" placeholder="Apakah LCD baru bergaransi jika sudah dipasang lem?"
                           class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary">
                    @error('faqForm.pertanyaan') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block font-semibold text-ink-300 uppercase tracking-wider mb-1">Jawaban</label>
                    <textarea wire:model="faqForm.jawaban" rows="4" placeholder="Jelaskan secara jelas dan transparan kepada konsumen..."
                              class="w-full px-3 py-2 rounded-xl bg-black/40 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary"></textarea>
                    @error('faqForm.jawaban') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div class="pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-white">
                        <input type="checkbox" wire:model="faqForm.is_active" class="rounded border-white/20 text-up-primary focus:ring-0">
                        <span>Status Aktif Tampil</span>
                    </label>
                </div>
            </div>

            <div class="pt-3 border-t border-white/10 flex justify-end gap-2">
                <button type="button" wire:click="$set('faqModalOpen', false)"
                        class="px-3.5 py-2 rounded-xl text-xs text-ink-400 hover:text-white bg-white/5 cursor-pointer">
                    Batal
                </button>
                <button type="button" wire:click="saveFaq"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-up-primary hover:bg-up-primary/90 cursor-pointer">
                    Simpan FAQ
                </button>
            </div>
        </div>
    </div>
    @endif

    <script nonce="{{ request()->attributes->get(\'csp_nonce\') }}">
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('tab-changed', (event) => {
                if (event.url && window.location.href !== event.url) {
                    window.history.pushState(null, '', event.url);
                }
            });

            window.addEventListener('popstate', () => {
                const parts = window.location.pathname.split('/');
                const last = parts[parts.length - 1];
                if (last && @this) {
                    @this.call('setTab', last);
                }
            });
        });
    </script>
</div>
