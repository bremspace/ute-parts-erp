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
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-ink-900 text-white dark:bg-white dark:text-ink-950 font-semibold text-xs transition-[background-color,transform] duration-140 active:scale-[0.97]">
                <span>Kembali ke Katalog</span>
            </a>
        </div>
    @else
        @php
            $galeri = $produk->galeri_foto;
            $fotoUtama = $produk->foto_utama ?: $produk->gambar;
            $catRel = $produk->kategoriRelasi;
            $catNama = $catRel ? $catRel->nama : (is_array($produk->kategori) ? implode(', ', $produk->kategori) : (string) $produk->kategori);
            $catParam = $catRel?->id ?: $catNama;
            
            // Kompatibilitas model untuk breadcrumb
            $compatModel = $produk->model_kompatibel ?: ($produk->tipeHps->first()?->model ?? null);
            if (!$compatModel && preg_match('/(iPhone\s*\d+(\s*(Pro|Plus|Max|Mini))?|Galaxy\s*[A-Z0-9]+|Redmi\s*[A-Z0-9\s]+|Xiaomi\s*[A-Z0-9\s]+)/i', $produk->nama, $matches)) {
                $compatModel = trim($matches[0]);
            }
            $compatLabel = $compatModel ? (str_contains(strtolower($compatModel), strtolower($catNama)) ? $compatModel : "{$catNama} {$compatModel}") : null;
            $finalPrice = (float) ($hargaInfo['harga'] ?? $produk->harga_jual_retail);
        @endphp

        @push('seo')
            <link rel="canonical" href="{{ route('shop.detail', $produk->slug) }}" />
            <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($produk->deskripsi ?: "Beli {$produk->nama} berkualitas OEM/Original bergaransi resmi di " . config('app.name', 'Bremspace')), 160) }}">
            <meta name="keywords" content="{{ $produk->nama }}, {{ $catNama }}, {{ $compatModel }}, sparepart handphone, suku cadang resmi">

            <!-- OpenGraph Protocol -->
            <meta property="og:type" content="product">
            <meta property="og:title" content="{{ $produk->nama }} — {{ config('app.name', 'Bremspace') }}">
            <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($produk->deskripsi ?: "Beli {$produk->nama} harga grosir teknisi bergaransi."), 160) }}">
            <meta property="og:url" content="{{ route('shop.detail', $produk->slug) }}">
            <meta property="og:image" content="{{ $fotoUtama ?: asset('icons/icon-512x512.png') }}">
            <meta property="product:price:amount" content="{{ $finalPrice }}">
            <meta property="product:price:currency" content="IDR">
            <meta property="product:availability" content="{{ $stokTotal > 0 ? 'in stock' : 'out of stock' }}">

            <!-- Twitter Card -->
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $produk->nama }}">
            <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($produk->deskripsi ?: "Beli {$produk->nama}."), 160) }}">
            <meta name="twitter:image" content="{{ $fotoUtama ?: asset('icons/icon-512x512.png') }}">

            @php
                $productSchema = [
                    '@context' => 'https://schema.org/',
                    '@type' => 'Product',
                    'name' => $produk->nama,
                    'image' => [$fotoUtama ?: asset('icons/icon-512x512.png')],
                    'description' => strip_tags($produk->deskripsi ?: $produk->nama),
                    'sku' => $produk->sku ?: 'PRD-' . $produk->id,
                    'mpn' => $produk->kode ?: 'PRD-' . $produk->id,
                    'brand' => [
                        '@type' => 'Brand',
                        'name' => $produk->brand?->nama ?: 'Bremspace Genuine',
                    ],
                    'offers' => [
                        '@type' => 'Offer',
                        'url' => route('shop.detail', $produk->slug),
                        'priceCurrency' => 'IDR',
                        'price' => $finalPrice,
                        'priceValidUntil' => now()->addYear()->format('Y-m-d'),
                        'itemCondition' => 'https://schema.org/NewCondition',
                        'availability' => $stokTotal > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        'seller' => [
                            '@type' => 'Organization',
                            'name' => config('app.name', 'Bremspace'),
                        ],
                    ],
                ];
                if (isset($reviews) && count($reviews) > 0) {
                    $productSchema['aggregateRating'] = [
                        '@type' => 'AggregateRating',
                        'ratingValue' => number_format($reviews->avg('rating'), 1),
                        'reviewCount' => count($reviews),
                    ];
                }

                $productBreadcrumbs = [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Katalog Sparepart',
                            'item' => route('shop'),
                        ],
                    ],
                ];
                $bPos = 2;
                if ($catNama) {
                    $productBreadcrumbs['itemListElement'][] = [
                        '@type' => 'ListItem',
                        'position' => $bPos++,
                        'name' => $catNama,
                        'item' => route('shop', ['kategori' => $catParam]),
                    ];
                }
                if ($compatModel && $compatLabel) {
                    $productBreadcrumbs['itemListElement'][] = [
                        '@type' => 'ListItem',
                        'position' => $bPos++,
                        'name' => $compatLabel,
                        'item' => route('shop', ['kategori' => $catParam, 'hpModel' => $compatModel]),
                    ];
                }
                $productBreadcrumbs['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $bPos++,
                    'name' => $produk->nama,
                    'item' => route('shop.detail', $produk->slug),
                ];
            @endphp

            <!-- Schema.org Product Structured Data (Shopee/Amazon Standard) -->
            <script type="application/ld+json">
            {!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
            </script>

            <!-- Schema.org BreadcrumbList Structured Data -->
            <script type="application/ld+json">
            {!! json_encode($productBreadcrumbs, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
            </script>
        @endpush

        <!-- Breadcrumb navigation (Fully clickable & correctly routed) -->
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs font-medium text-ink-500 dark:text-ink-400 mb-8 overflow-x-auto whitespace-nowrap pb-1">
            <a href="{{ route('shop') }}" class="hover:text-up-primary dark:hover:text-white transition-colors flex items-center gap-1.5 font-semibold">
                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Katalog Sparepart</span>
            </a>
            <span class="text-ink-300 dark:text-ink-700">/</span>
            @if($catNama)
                <a href="{{ route('shop', ['kategori' => $catParam]) }}" class="hover:text-up-primary dark:hover:text-white transition-colors font-medium">
                    {{ $catNama }}
                </a>
                <span class="text-ink-300 dark:text-ink-700">/</span>
            @endif
            @if($compatModel && $compatLabel)
                <a href="{{ route('shop', ['kategori' => $catParam, 'hpModel' => $compatModel]) }}" class="hover:text-up-primary dark:hover:text-white transition-colors font-medium">
                    {{ $compatLabel }}
                </a>
                <span class="text-ink-300 dark:text-ink-700">/</span>
            @endif
            <span class="text-ink-900 dark:text-white font-semibold truncate max-w-xs">{{ $produk->nama }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-14 items-start">
            <!-- Left: Interactive Gallery -->
            @php
                $galeri = $produk->galeri_foto;
                $fotoUtama = $produk->foto_utama ?: $produk->gambar;
            @endphp
            <div class="lg:col-span-6 space-y-4 lg:sticky lg:top-24"
                 x-data="{
                    activeMedia: @js($fotoUtama),
                    isVideo(url) {
                        if (!url) return false;
                        return url.match(/\.(mp4|webm|ogg)(\?.*)?$/i);
                    }
                 }">
                <!-- Main Stage Canvas -->
                <div class="aspect-square bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/90 dark:border-white/[0.08] flex items-center justify-center overflow-hidden relative shadow-[0_4px_24px_rgba(0,0,0,0.03)] dark:shadow-[0_8px_32px_rgba(0,0,0,0.4)]">
                    <template x-if="activeMedia && isVideo(activeMedia)">
                        <video :src="activeMedia" autoplay loop muted playsinline class="w-full h-full object-cover"></video>
                    </template>
                    <template x-if="activeMedia && !isVideo(activeMedia)">
                        <img :src="activeMedia" alt="{{ $produk->nama }}" class="w-full h-full object-cover transition-opacity duration-200"
                             x-on:error="activeMedia = null">
                    </template>
                    <template x-if="!activeMedia">
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
                            @php
                                $itemUrl = $item['url'] ?? '';
                                $isVideoItem = preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $itemUrl);
                            @endphp
                            <button
                                type="button"
                                x-on:click="activeMedia = @js($itemUrl)"
                                :class="activeMedia === @js($itemUrl) ? 'ring-2 ring-up-primary dark:ring-white scale-100 opacity-100' : 'opacity-60 hover:opacity-100 scale-95'"
                                class="w-14 sm:w-18 h-14 sm:h-18 rounded-2xl overflow-hidden border border-ink-200/80 dark:border-white/10 flex-shrink-0 transition-[opacity,transform] duration-140 cursor-pointer bg-white dark:bg-ink-900 relative"
                            >
                                @if($isVideoItem)
                                    <video src="{{ $itemUrl }}" muted class="w-full h-full object-cover pointer-events-none"></video>
                                    <span class="absolute inset-0 bg-black/40 flex items-center justify-center text-white text-[10px] font-bold">▶ VID</span>
                                @else
                                    <img src="{{ $item['thumb'] ?? $itemUrl }}" alt="Thumbnail" class="w-full h-full object-cover">
                                @endif
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
                                    class="px-4 py-2.5 rounded-2xl border text-xs font-semibold transition-[background-color,border-color,color,transform] duration-140 cursor-pointer active:scale-[0.96] flex items-center gap-2 {{ $selectedVariantId === $v->id ? 'border-ink-900 bg-ink-900 text-white dark:border-white dark:bg-white dark:text-ink-950 shadow-sm' : 'border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 bg-white dark:bg-ink-900 hover:border-ink-400' }}"
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
                            <input
                                type="number"
                                min="1"
                                wire:model.live.debounce.300ms="qty"
                                class="w-14 text-center font-bold text-sm text-ink-900 dark:text-white bg-transparent outline-none border-0 focus:ring-0 p-0 tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                aria-label="Jumlah beli"
                            />
                            <button
                                type="button"
                                wire:click="$set('qty', {{ $qty + 1 }})"
                                class="w-8 h-8 rounded-xl bg-ink-100/60 dark:bg-white/5 hover:bg-ink-200 dark:hover:bg-white/10 font-bold text-ink-700 dark:text-ink-300 transition-colors flex items-center justify-center cursor-pointer active:scale-[0.92]">
                                +
                            </button>
                        </div>
                    </div>

                    @php
                        $stockDetailInfo = app(\App\Modules\Marketplace\Services\WebKonsumenService::class)->formatStockDisplay($stokTotal);
                    @endphp
                    <div class="pt-5">
                        <div class="flex items-center gap-1.5 text-xs font-semibold {{ $stockDetailInfo['is_available'] ? ($stockDetailInfo['is_low'] ? 'text-amber-500 font-bold' : 'text-up-mint') : 'text-up-red' }}">
                            <span class="w-2 h-2 rounded-full {{ $stockDetailInfo['is_available'] ? ($stockDetailInfo['is_low'] ? 'bg-amber-500 animate-pulse' : 'bg-up-mint') : 'bg-up-red' }}"></span>
                            <span>{{ $stockDetailInfo['label'] }}</span>
                        </div>
                        <p class="text-[11px] text-ink-400">{{ $stockDetailInfo['sublabel'] }}</p>
                    </div>
                </div>

                <!-- Selected Gadget Model Indicator -->
                @if($selectedGadgetModel)
                    <div class="p-3.5 rounded-2xl bg-up-primary/10 border border-up-primary/25 flex items-center justify-between gap-2 transition-all">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-base flex-shrink-0">📱</span>
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-up-primary block">Model Terpilih (Masuk Catatan)</span>
                                <span class="text-xs font-bold text-ink-900 dark:text-white truncate block">{{ $selectedGadgetModel }}</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="$set('selectedGadgetModel', null)"
                            class="px-2.5 py-1 rounded-xl bg-white dark:bg-ink-800 text-ink-500 hover:text-up-red text-[11px] font-bold border border-ink-200/80 dark:border-white/10 transition-colors flex-shrink-0 cursor-pointer"
                            title="Batalkan pilihan model"
                        >
                            ✕ Hapus
                        </button>
                    </div>
                @endif

                <!-- Call to action buttons -->
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="addToCart"
                        {{ $stokTotal <= 0 ? 'disabled' : '' }}
                        class="flex-1 py-4 px-6 rounded-2xl font-bold text-sm tracking-tight transition-[background-color,transform] duration-140 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-up-primary hover:bg-up-primary-dark text-white shadow-sm shadow-up-primary/25 active:scale-[0.97] min-h-[48px] flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Tambah ke Keranjang</span>
                    </button>
                    <button
                        type="button"
                        wire:click="beliLangsung"
                        {{ $stokTotal <= 0 ? 'disabled' : '' }}
                        class="px-8 py-4 rounded-2xl font-bold text-sm bg-ink-900 text-white dark:bg-white dark:text-ink-950 hover:opacity-90 transition-[opacity,transform] duration-140 cursor-pointer min-h-[48px] flex items-center justify-center active:scale-[0.97] shadow-sm disabled:opacity-40 disabled:cursor-not-allowed gap-2"
                    >
                        <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Beli Langsung</span>
                    </button>
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

                <!-- Device Compatibility List (Interactive & Auto-Select) -->
                @if($produk->tipeHps && $produk->tipeHps->count() > 0)
                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Model Gadget Terverifikasi</h3>
                                <p class="text-[11px] text-ink-400 mt-0.5">Klik model gadget Anda untuk otomatis terpilih ke varian / catatan belanja:</p>
                            </div>
                            <span class="text-[11px] font-semibold text-up-mint flex-shrink-0">{{ $produk->tipeHps->count() }} Cocok</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($produk->tipeHps as $thp)
                                @php
                                    $thpNama = trim("{$thp->merk} {$thp->model}");
                                    $isSelected = ($selectedGadgetModel === $thpNama);
                                @endphp
                                <button
                                    type="button"
                                    wire:click="selectGadgetModel('{{ addslashes($thpNama) }}')"
                                    class="px-3.5 py-2 rounded-2xl text-xs font-semibold border transition-[background-color,border-color,color,transform] duration-140 cursor-pointer active:scale-95 flex items-center gap-1.5 {{ $isSelected ? 'border-up-primary bg-up-primary text-white shadow-sm shadow-up-primary/30 ring-2 ring-up-primary/20' : 'bg-ink-50 dark:bg-white/5 border-ink-200/70 dark:border-white/10 text-ink-800 dark:text-ink-200 hover:border-up-primary/60 hover:bg-up-primary/5' }}"
                                >
                                    @if($isSelected)
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                    <span>{{ $thpNama }}</span>
                                </button>
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
                                   class="flex items-center justify-between p-3 rounded-2xl border border-ink-100/80 dark:border-white/10 bg-white dark:bg-ink-900 hover:border-up-primary/40 transition-[border-color,background-color] duration-140 group min-w-0 gap-2">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-11 h-11 rounded-xl overflow-hidden bg-ink-50 dark:bg-ink-950 flex-shrink-0 flex items-center justify-center relative">
                                            @if($pk->thumbnail_url ?: $pk->gambar)
                                                <img src="{{ $pk->thumbnail_url ?: $pk->gambar }}" alt="{{ $pk->nama }}" class="w-full h-full object-cover"
                                                     onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                            @endif
                                            <div class="w-full h-full items-center justify-center text-ink-300 text-xs font-bold" style="{{ ($pk->thumbnail_url ?: $pk->gambar) ? 'display:none;' : 'display:flex;' }}">P</div>
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

                <!-- Stock across branches (CMS Policy) -->
                @if(($webConfig['show_branch_breakdown'] ?? true) && count($stokPerCabang) > 0)
                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Ketersediaan Stok Fisik di Cabang</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach($stokPerCabang as $sc)
                                @php
                                    $scStock = (int) ($sc['stok'] ?? 0);
                                    $scInfo = app(\App\Modules\Marketplace\Services\WebKonsumenService::class)->formatStockDisplay($scStock);
                                @endphp
                                <div class="flex items-center justify-between bg-white dark:bg-ink-900 border border-ink-100/80 dark:border-white/10 rounded-2xl p-3 sm:p-3.5 min-w-0 gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $sc['cabang'] }}</p>
                                        @if($sc['alamat'])
                                            <p class="text-[10px] text-ink-400 truncate max-w-full">{{ $sc['alamat'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs font-bold tabular-nums flex-shrink-0 {{ $scInfo['is_available'] ? ($scInfo['is_low'] ? 'text-amber-500' : 'text-up-mint') : 'text-up-red' }}">
                                        {{ $scInfo['label'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Ulasan & Testimoni Pelanggan -->
                <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Ulasan &amp; Penilaian Pelanggan</h3>
                            <p class="text-[11px] text-ink-400">Pengalaman nyata dari teknisi dan pembeli terverifikasi.</p>
                        </div>
                        <span class="text-xs font-bold text-up-primary">{{ count($reviews ?? []) }} Ulasan</span>
                    </div>

                    <!-- List Review Cards -->
                    <div class="space-y-3">
                        @forelse($reviews ?? [] as $rev)
                        <div class="p-4 rounded-2xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-up-primary/20 text-up-primary font-bold text-xs flex items-center justify-center">
                                        {{ substr($rev->pelanggan?->nama ?? 'P', 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-ink-900 dark:text-white">{{ $rev->pelanggan?->nama ?? 'Konsumen' }}</span>
                                        <span class="text-[10px] text-up-mint ml-1">✓ Pembeli Terverifikasi</span>
                                    </div>
                                </div>
                                <div class="text-amber-400 text-xs">
                                    @for($s = 1; $s <= 5; $s++)
                                        <span>{{ $s <= $rev->rating ? '★' : '☆' }}</span>
                                    @endfor
                                </div>
                            </div>

                            @if($rev->judul_ulasan)
                                <div class="text-xs font-bold text-ink-900 dark:text-white">{{ $rev->judul_ulasan }}</div>
                            @endif

                            <p class="text-xs text-ink-600 dark:text-ink-300 leading-relaxed">{{ $rev->isi_ulasan }}</p>

                            @if($rev->tanggapan_admin)
                                <div class="p-2.5 rounded-xl bg-up-primary/10 border border-up-primary/20 text-[11px] text-ink-800 dark:text-ink-200">
                                    <strong class="text-up-primary">Respon Toko:</strong> {{ $rev->tanggapan_admin }}
                                </div>
                            @endif
                        </div>
                        @empty
                        <div class="py-6 text-center text-xs text-ink-400 bg-ink-50/50 dark:bg-white/[0.02] rounded-2xl border border-dashed border-ink-200 dark:border-white/10">
                            Belum ada ulasan untuk suku cadang ini. Jadilah yang pertama memberikan review!
                        </div>
                        @endforelse
                    </div>

                    <!-- Form Tulis Review (Login Customer) -->
                    @auth('customer')
                    <div class="p-4 rounded-2xl bg-white dark:bg-ink-950/60 border border-ink-200 dark:border-white/10 space-y-3">
                        <h4 class="text-xs font-bold text-ink-900 dark:text-white uppercase tracking-wider">Tulis Ulasan Anda</h4>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center gap-2">
                                <label class="text-ink-500">Rating:</label>
                                <select wire:model="reviewRating" class="px-2 py-1 rounded-lg border border-ink-200 dark:border-white/10 bg-transparent text-ink-900 dark:text-white">
                                    <option value="5">⭐⭐⭐⭐⭐ (5/5) Sempurna</option>
                                    <option value="4">⭐⭐⭐⭐ (4/5) Bagus</option>
                                    <option value="3">⭐⭐⭐ (3/5) Cukup</option>
                                    <option value="2">⭐⭐ (2/5) Kurang</option>
                                    <option value="1">⭐ (1/5) Buruk</option>
                                </select>
                            </div>
                            <input type="text" wire:model="reviewJudul" placeholder="Judul ulasan (opsional)..." class="w-full px-3 py-2 rounded-xl bg-ink-50 dark:bg-black/30 border border-ink-200 dark:border-white/10 text-xs text-ink-900 dark:text-white">
                            <textarea wire:model="reviewIsi" rows="3" placeholder="Ceritakan kualitas fisik part, kemudahan pemasangan, atau respon layar..." class="w-full px-3 py-2 rounded-xl bg-ink-50 dark:bg-black/30 border border-ink-200 dark:border-white/10 text-xs text-ink-900 dark:text-white"></textarea>
                            @error('reviewIsi') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                            <div class="flex justify-end">
                                <button type="button" wire:click="submitReview" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-sm cursor-pointer">
                                    Kirim Ulasan
                                </button>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="p-3 text-center text-xs text-ink-400 bg-ink-50 dark:bg-white/5 rounded-xl">
                        <a href="{{ route('customer.login') }}" class="text-up-primary font-bold hover:underline">Masuk ke akun Anda</a> untuk memberikan ulasan produk.
                    </div>
                    @endauth
                </div>
            </div>
        </div>

        <!-- Mobile Floating Sticky Bar for Quick Purchase -->
        <div class="md:hidden fixed bottom-20 inset-x-3 max-w-sm mx-auto bg-white/95 dark:bg-ink-950/95 backdrop-blur-2xl border border-ink-200/90 dark:border-white/15 rounded-2xl p-2.5 z-30 flex items-center justify-between gap-2 shadow-[0_12px_36px_rgba(0,0,0,0.18)] dark:shadow-[0_16px_40px_rgba(0,0,0,0.7)]"
             style="bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px));">
            <div class="pl-2 min-w-0 flex-1">
                @if($selectedGadgetModel)
                    <span class="text-[9px] text-up-primary font-bold uppercase block tracking-tight truncate">📱 {{ $selectedGadgetModel }}</span>
                @else
                    <span class="text-[10px] text-ink-500 dark:text-ink-400 uppercase font-semibold block tracking-tight truncate">Total ({{ $qty }})</span>
                @endif
                <span class="text-xs sm:text-base font-black text-ink-900 dark:text-white tabular-nums block truncate">
                    Rp {{ number_format($hargaInfo['harga'] * $qty, 0, ',', '.') }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <button
                    type="button"
                    wire:click="addToCart"
                    {{ $stokTotal <= 0 ? 'disabled' : '' }}
                    class="py-2.5 px-3 rounded-xl font-bold text-xs bg-ink-100 dark:bg-white/10 hover:bg-ink-200 text-ink-800 dark:text-white active:scale-[0.96] transition-[background-color,transform] duration-140 flex items-center gap-1 cursor-pointer disabled:opacity-40 min-h-[42px]"
                    title="Tambah ke Keranjang"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>+Keranjang</span>
                </button>
                <button
                    type="button"
                    wire:click="beliLangsung"
                    {{ $stokTotal <= 0 ? 'disabled' : '' }}
                    class="py-2.5 px-3.5 rounded-xl font-bold text-xs bg-up-primary hover:bg-up-primary-dark text-white shadow-md active:scale-[0.96] transition-[background-color,transform] duration-140 flex items-center gap-1 cursor-pointer disabled:opacity-40 min-h-[42px]"
                >
                    <span>Beli</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>
