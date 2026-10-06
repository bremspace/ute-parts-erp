<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
    @if(!$produk)
        <div class="text-center py-24 space-y-4">
            <div class="w-16 h-16 mx-auto rounded-3xl bg-ink-100 dark:bg-white/5 flex items-center justify-center text-ink-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="text-lg font-bold text-ink-900 dark:text-white">Komponen Tidak Ditemukan</h2>
            <p class="text-xs text-ink-500 dark:text-ink-400">Item ini mungkin telah diarsipkan atau dipindahkan.</p>
            <a href="{{ route('shop') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-ink-900 text-white dark:bg-white dark:text-ink-950 font-semibold text-xs transition-all active:scale-[0.97]">
                <span>Kembali ke Katalog</span>
            </a>
        </div>
    @else
        <!-- Breadcrumb navigation -->
        <nav class="flex items-center gap-2 text-xs font-medium text-ink-500 dark:text-ink-400 mb-8 overflow-x-auto whitespace-nowrap pb-1">
            <a href="{{ route('shop') }}" class="hover:text-ink-900 dark:hover:text-white transition-colors">Katalog Sparepart</a>
            <span>/</span>
            <span class="text-ink-400 dark:text-ink-600">
                {{ $produk->kategoriRelasi ? $produk->kategoriRelasi->nama : (is_array($produk->kategori) ? implode(', ', $produk->kategori) : (string) $produk->kategori) }}
            </span>
            <span>/</span>
            <span class="text-ink-900 dark:text-white font-semibold truncate max-w-xs">{{ $produk->nama }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-14 items-start">
            <!-- Left: Interactive Gallery -->
            @php
                $galeri = $produk->galeri_foto;
                $fotoUtama = $produk->foto_utama ?: $produk->gambar;
            @endphp
            <div class="lg:col-span-6 space-y-4 lg:sticky lg:top-24" x-data="{ activeFoto: '{{ $fotoUtama }}' }">
                <!-- Main Stage Canvas -->
                <div class="aspect-square bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/90 dark:border-white/[0.08] flex items-center justify-center overflow-hidden relative shadow-[0_4px_24px_rgba(0,0,0,0.03)] dark:shadow-[0_8px_32px_rgba(0,0,0,0.4)]">
                    <template x-if="activeFoto">
                        <img :src="activeFoto" alt="{{ $produk->nama }}" class="w-full h-full object-cover transition-all duration-300">
                    </template>
                    <template x-if="!activeFoto">
                        <div class="flex flex-col items-center justify-center text-ink-300 dark:text-ink-600 p-8 text-center">
                            <svg class="w-20 h-20 stroke-[1.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-400 mt-2">Visual Preview Belum Tersedia</span>
                        </div>
                    </template>
                </div>

                <!-- Thumbnails Strip -->
                @if(count($galeri) > 1)
                    <div class="flex gap-2 sm:gap-3 overflow-x-auto no-scrollbar pb-2 min-w-0">
                        @foreach($galeri as $item)
                            <button
                                type="button"
                                x-on:click="activeFoto = '{{ $item['url'] }}'"
                                :class="activeFoto === '{{ $item['url'] }}' ? 'ring-2 ring-up-primary dark:ring-white scale-100 opacity-100' : 'opacity-60 hover:opacity-100 scale-95'"
                                class="w-14 sm:w-18 h-14 sm:h-18 rounded-2xl overflow-hidden border border-ink-200/80 dark:border-white/10 flex-shrink-0 transition-all duration-200 cursor-pointer bg-white dark:bg-ink-900"
                            >
                                <img src="{{ $item['thumb'] ?? $item['url'] }}" alt="Thumbnail" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Right: Product Specifications & Action Panel -->
            <div class="lg:col-span-6 space-y-6">
                <!-- Badges header -->
                <div class="space-y-3 min-w-0">
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                        <span class="text-[10px] uppercase tracking-wider font-bold text-up-primary bg-up-primary/10 dark:bg-up-primary/20 px-2.5 py-1 rounded-full max-w-full truncate">
                            {{ $produk->kategoriRelasi ? $produk->kategoriRelasi->nama_lengkap : (is_array($produk->kategori) ? implode(', ', $produk->kategori) : (string) $produk->kategori) }}
                        </span>
                        @if($produk->kondisi)
                            <span class="text-[10px] uppercase tracking-wider font-bold text-ink-600 dark:text-ink-300 bg-ink-100 dark:bg-white/10 px-2.5 py-1 rounded-full flex-shrink-0">
                                {{ $produk->kondisi }}
                            </span>
                        @endif
                        @if($produk->brand)
                            <span class="text-[10px] uppercase tracking-wider font-bold text-ink-700 dark:text-ink-200 bg-ink-50 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 px-2.5 py-1 rounded-full truncate">
                                {{ $produk->brand->nama }}
                            </span>
                        @endif
                        @if($produk->kualitas)
                            <span class="text-[10px] uppercase tracking-wider font-bold text-up-mint bg-up-mint/10 dark:bg-up-mint/20 px-2.5 py-1 rounded-full flex-shrink-0">
                                {{ $produk->kualitas->nama }}
                            </span>
                        @endif
                    </div>

                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-ink-900 dark:text-white tracking-tight leading-tight break-words">
                        {{ $produk->nama }}
                    </h1>

                    @if($produk->brand_kompatibel)
                        <p class="text-xs sm:text-sm text-ink-500 dark:text-ink-400 break-words">
                            Kompatibilitas Pabrikan: <strong class="text-ink-800 dark:text-ink-200">{{ is_array($produk->brand_kompatibel) ? implode(' ', $produk->brand_kompatibel) : (string) $produk->brand_kompatibel }} {{ is_array($produk->model_kompatibel) ? implode(' ', $produk->model_kompatibel) : (string) $produk->model_kompatibel }}</strong>
                        </p>
                    @endif
                </div>

                <!-- Price panel (Apple Store style clean box) -->
                <div class="rounded-3xl p-6 bg-white dark:bg-ink-900 border border-ink-100/90 dark:border-white/[0.08] shadow-[0_4px_24px_rgba(0,0,0,0.02)] space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-ink-400 dark:text-ink-500">Harga Resmi</span>
                        @if($hargaInfo['diskon_nominal'] > 0)
                            <span class="text-[10px] font-bold text-up-accent bg-up-accent/10 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                                Hemat Rp {{ number_format($hargaInfo['diskon_nominal'], 0, ',', '.') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-baseline gap-3 pt-1">
                        <span class="text-3xl sm:text-4xl font-black tracking-tight text-ink-900 dark:text-white tabular-nums">
                            Rp {{ number_format($hargaInfo['harga'], 0, ',', '.') }}
                        </span>
                        @if($hargaInfo['diskon_nominal'] > 0)
                            <span class="text-lg text-ink-400 line-through tabular-nums">
                                Rp {{ number_format($hargaInfo['harga_dasar'], 0, ',', '.') }}
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-ink-500 dark:text-ink-400 pt-1 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $hargaInfo['alasan'] }}</span>
                    </p>
                </div>

                <!-- Variants Selector (Tactile pills) -->
                @if(($produk->skuVariants ?? collect())->count() > 1)
                    <div class="space-y-2.5">
                        <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                            Pilih Varian
                        </label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($produk->skuVariants as $v)
                                @php $vStok = (int) ($stokPerVariant[$v->id] ?? 0); @endphp
                                <button
                                    type="button"
                                    wire:click="$set('selectedVariantId', {{ $v->id }})"
                                    class="px-4 py-2.5 rounded-2xl border text-xs font-semibold transition-all duration-200 cursor-pointer active:scale-[0.96] flex items-center gap-2 {{ $selectedVariantId === $v->id ? 'border-ink-900 bg-ink-900 text-white dark:border-white dark:bg-white dark:text-ink-950 shadow-sm' : 'border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 bg-white dark:bg-ink-900 hover:border-ink-400' }}"
                                >
                                    <span>{{ $v->nama_varian }}</span>
                                    <span class="text-[10px] opacity-60 tabular-nums">({{ $vStok }})</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Stepper Quantity & Stock Status -->
                <div class="flex items-center gap-4 pt-1">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                            Jumlah
                        </label>
                        <div class="flex items-center bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 rounded-2xl p-1 shadow-sm">
                            <button
                                type="button"
                                wire:click="$set('qty', {{ max(1, $qty - 1) }})"
                                class="w-8 h-8 rounded-xl bg-ink-100/60 dark:bg-white/5 hover:bg-ink-200 dark:hover:bg-white/10 font-bold text-ink-700 dark:text-ink-300 transition-colors flex items-center justify-center cursor-pointer active:scale-[0.92]">
                                −
                            </button>
                            <span class="w-12 text-center font-bold text-sm text-ink-900 dark:text-white tabular-nums">
                                {{ $qty }}
                            </span>
                            <button
                                type="button"
                                wire:click="$set('qty', {{ $qty + 1 }})"
                                class="w-8 h-8 rounded-xl bg-ink-100/60 dark:bg-white/5 hover:bg-ink-200 dark:hover:bg-white/10 font-bold text-ink-700 dark:text-ink-300 transition-colors flex items-center justify-center cursor-pointer active:scale-[0.92]">
                                +
                            </button>
                        </div>
                    </div>

                    <div class="pt-5">
                        <div class="flex items-center gap-1.5 text-xs font-semibold {{ $stokTotal > 0 ? 'text-up-mint' : 'text-up-red' }}">
                            <span class="w-2 h-2 rounded-full {{ $stokTotal > 0 ? 'bg-up-mint shadow-[0_0_8px_rgba(31,191,143,0.8)]' : 'bg-up-red' }}"></span>
                            <span>{{ $stokTotal > 0 ? $stokTotal . ' unit tersedia' : 'Stok habis' }}</span>
                        </div>
                        <p class="text-[11px] text-ink-400">Siap diproses hari ini</p>
                    </div>
                </div>

                <!-- Call to action buttons -->
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="addToCart"
                        {{ $stokTotal <= 0 ? 'disabled' : '' }}
                        class="flex-1 py-4 px-6 rounded-2xl font-bold text-sm tracking-tight transition-all duration-200 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-up-primary hover:bg-up-primary-dark text-white shadow-[0_8px_24px_rgba(91,79,233,0.3)] active:scale-[0.97] min-h-[48px] flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Tambah ke Keranjang</span>
                    </button>
                    @if(auth('customer')->user())
                        <a href="{{ route('checkout') }}"
                           class="px-8 py-4 rounded-2xl font-bold text-sm bg-ink-900 text-white dark:bg-white dark:text-ink-950 hover:opacity-90 transition-all duration-200 cursor-pointer min-h-[48px] flex items-center justify-center active:scale-[0.97] shadow-sm">
                            Beli Langsung
                        </a>
                    @endif
                </div>

                @guest('customer')
                    <div class="p-4 rounded-2xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 flex items-center gap-3">
                        <span class="text-base">✨</span>
                        <p class="text-xs text-ink-600 dark:text-ink-300">
                            Masuk untuk mendapatkan harga khusus mitra atau reseller:
                            <a href="{{ route('customer.login') }}" class="text-up-primary font-bold hover:underline">Masuk</a> /
                            <a href="{{ route('customer.register') }}" class="text-up-primary font-bold hover:underline">Daftar</a>
                        </p>
                    </div>
                @endguest

                <!-- Product Description -->
                @if($produk->deskripsi)
                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Spesifikasi & Keterangan</h3>
                        <div class="text-xs sm:text-sm text-ink-600 dark:text-ink-400 leading-relaxed whitespace-pre-line">
                            {{ $produk->deskripsi }}
                        </div>
                    </div>
                @endif

                <!-- Device Compatibility List -->
                @if($produk->tipeHps && $produk->tipeHps->count() > 0)
                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Model Gadget Terverifikasi</h3>
                            <span class="text-[11px] font-semibold text-up-mint">{{ $produk->tipeHps->count() }} Model Cocok</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($produk->tipeHps as $thp)
                                <span class="px-3 py-1.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200/70 dark:border-white/10 text-xs font-medium text-ink-800 dark:text-ink-200">
                                    {{ $thp->merk }} {{ $thp->model }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Substitutions / Compatible Parts -->
                @if($produk->produkKompatibel && $produk->produkKompatibel->count() > 0)
                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Alternatif Suku Cadang Terkait</h3>
                        <div class="space-y-2">
                            @foreach($produk->produkKompatibel as $pk)
                                <a href="{{ route('shop.detail', $pk->slug) }}"
                                   class="flex items-center justify-between p-3 rounded-2xl border border-ink-100/80 dark:border-white/10 bg-white dark:bg-ink-900 hover:border-up-primary/40 transition-all duration-200 group min-w-0 gap-2">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-11 h-11 rounded-xl overflow-hidden bg-ink-50 dark:bg-ink-950 flex-shrink-0">
                                            @if($pk->thumbnail_url ?: $pk->gambar)
                                                <img src="{{ $pk->thumbnail_url ?: $pk->gambar }}" alt="{{ $pk->nama }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-ink-300 text-xs font-bold">P</div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-bold text-ink-900 dark:text-white group-hover:text-up-primary transition-colors truncate">{{ $pk->nama }}</p>
                                            <p class="text-[11px] text-ink-400 truncate">{{ $pk->pivot->catatan ?: 'Dapat saling menggantikan' }}</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-black text-up-primary tabular-nums flex-shrink-0">Rp {{ number_format($pk->harga_jual_retail, 0, ',', '.') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Stock across branches -->
                @if(count($stokPerCabang) > 0)
                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Ketersediaan Stok Fisik di Cabang</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach($stokPerCabang as $sc)
                                <div class="flex items-center justify-between bg-white dark:bg-ink-900 border border-ink-100/80 dark:border-white/10 rounded-2xl p-3 sm:p-3.5 min-w-0 gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $sc['cabang'] }}</p>
                                        @if($sc['alamat'])
                                            <p class="text-[10px] text-ink-400 truncate max-w-full">{{ $sc['alamat'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs font-bold tabular-nums flex-shrink-0 {{ $sc['stok'] > 0 ? 'text-up-mint' : 'text-up-red' }}">
                                        {{ $sc['stok'] }} unit
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Mobile Floating Sticky Bar for Quick Purchase -->
        <div class="md:hidden fixed bottom-20 inset-x-3 max-w-xs sm:max-w-sm mx-auto bg-white/95 dark:bg-ink-950/95 backdrop-blur-2xl border border-ink-200/90 dark:border-white/15 rounded-2xl p-2.5 z-30 flex items-center justify-between gap-2.5 shadow-[0_12px_36px_rgba(0,0,0,0.18)] dark:shadow-[0_16px_40px_rgba(0,0,0,0.7)]"
             style="bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px));">
            <div class="pl-2 min-w-0 flex-1">
                <span class="text-[10px] text-ink-500 dark:text-ink-400 uppercase font-semibold block tracking-tight truncate">Total ({{ $qty }})</span>
                <span class="text-xs sm:text-base font-black text-ink-900 dark:text-white tabular-nums block truncate">
                    Rp {{ number_format($hargaInfo['harga'] * $qty, 0, ',', '.') }}
                </span>
            </div>
            <button
                type="button"
                wire:click="addToCart"
                {{ $stokTotal <= 0 ? 'disabled' : '' }}
                class="py-2.5 px-3.5 rounded-xl font-bold text-xs bg-up-primary hover:bg-up-primary-dark text-white shadow-md active:scale-[0.96] transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-40 min-h-[44px] flex-shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span>+ Keranjang</span>
            </button>
        </div>
    @endif
</div>
