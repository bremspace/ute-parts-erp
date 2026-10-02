<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    @if(!$produk)
        <div class="text-center py-20">
            <p class="font-semibold text-ink-500">Produk tidak ditemukan.</p>
            <a href="{{ route('shop') }}" class="inline-block mt-4 px-5 py-2.5 rounded-xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors">Kembali ke Katalog</a>
        </div>
    @else
        <nav class="text-xs text-ink-400 mb-6">
            <a href="{{ route('shop') }}" class="hover:text-up-primary">Katalog</a>
            <span class="mx-1.5">/</span>
            <span class="text-ink-700 font-medium">{{ $produk->nama }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
            <!-- Galeri Foto Produk Responsive & Hemat Bandwidth -->
            @php
                $galeri = $produk->galeri_foto;
                $fotoUtama = $produk->foto_utama ?: $produk->gambar;
            @endphp
            <div x-data="{ activeFoto: '{{ $fotoUtama }}' }">
                <div class="aspect-square bg-white rounded-3xl border border-ink-100 flex items-center justify-center overflow-hidden sticky lg:top-20 relative">
                    <template x-if="activeFoto">
                        <img :src="activeFoto" alt="{{ $produk->nama }}" class="w-full h-full object-cover transition-all duration-300">
                    </template>
                    <template x-if="!activeFoto">
                        <div class="flex flex-col items-center justify-center text-ink-300">
                            <svg class="w-24 h-24 text-ink-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-xs text-ink-400 mt-2">Tidak ada foto</span>
                        </div>
                    </template>
                </div>
                @if(count($galeri) > 1)
                    <div class="flex gap-2.5 mt-3.5 overflow-x-auto pb-1">
                        @foreach($galeri as $item)
                            <button
                                type="button"
                                x-on:click="activeFoto = '{{ $item['url'] }}'"
                                :class="activeFoto === '{{ $item['url'] }}' ? 'ring-2 ring-up-primary' : 'opacity-70 hover:opacity-100'"
                                class="w-16 h-16 rounded-xl overflow-hidden border border-ink-200 flex-shrink-0 transition-all cursor-pointer bg-white"
                            >
                                <img src="{{ $item['thumb'] ?? $item['url'] }}" alt="Thumbnail" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Info + Beli -->
            <div class="space-y-5">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] uppercase tracking-wider font-bold text-up-accent bg-up-accent/10 px-2.5 py-1 rounded-full">
                            {{ $produk->kategoriRelasi ? $produk->kategoriRelasi->nama_lengkap : (is_array($produk->kategori) ? implode(', ', $produk->kategori) : (string) $produk->kategori) }}
                        </span>
                        <span class="text-[11px] uppercase tracking-wider font-bold text-ink-500 bg-ink-50 px-2.5 py-1 rounded-full">{{ $produk->kondisi }}</span>
                        @if($produk->brand)
                            <span class="text-[11px] uppercase tracking-wider font-bold text-up-primary bg-up-primary/10 px-2.5 py-1 rounded-full">
                                {{ $produk->brand->nama }}
                            </span>
                        @endif
                        @if($produk->kualitas)
                            <span class="text-[11px] uppercase tracking-wider font-bold text-up-mint bg-up-mint/10 px-2.5 py-1 rounded-full">
                                {{ $produk->kualitas->nama }}
                            </span>
                        @endif
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-ink-900 mt-3 leading-tight">{{ $produk->nama }}</h1>
                    @if($produk->brand_kompatibel)
                        <p class="text-sm text-ink-500 mt-1.5">Kompatibel Utama: <strong class="text-ink-700">{{ is_array($produk->brand_kompatibel) ? implode(' ', $produk->brand_kompatibel) : (string) $produk->brand_kompatibel }} {{ is_array($produk->model_kompatibel) ? implode(' ', $produk->model_kompatibel) : (string) $produk->model_kompatibel }}</strong></p>
                    @endif
                </div>

                <!-- Harga -->
                <div class="bg-white rounded-2xl border border-ink-100 p-5">
                    @if($hargaInfo['diskon_nominal'] > 0)
                        <div class="flex items-baseline gap-2.5">
                            <span class="text-xl text-ink-400 line-through tabular-nums">Rp {{ number_format($hargaInfo['harga_dasar'], 0, ',', '.') }}</span>
                            <span class="text-[11px] font-bold text-up-accent bg-up-accent/10 px-2 py-0.5 rounded-full">HEMAT</span>
                        </div>
                    @endif
                    <p class="text-3xl sm:text-4xl font-black text-up-primary tabular-nums mt-1">Rp {{ number_format($hargaInfo['harga'], 0, ',', '.') }}</p>
                    <p class="text-xs text-ink-500 mt-1.5">{{ $hargaInfo['alasan'] }}</p>
                </div>

                <!-- Varian -->
                @if(($produk->skuVariants ?? collect())->count() > 1)
                    <div>
                        <label class="block text-xs font-bold text-ink-700 uppercase tracking-wider mb-2">Varian</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($produk->skuVariants as $v)
                                {{-- [B-15a] Stok per varian dari 1 query agregat (ShopPage::stokSummary) — bukan query per varian. --}}
                                @php $vStok = (int) ($stokPerVariant[$v->id] ?? 0); @endphp
                                <button
                                    wire:click="$set('selectedVariantId', {{ $v->id }})"
                                    class="px-3.5 py-2 rounded-xl border text-xs font-semibold transition-all {{ $selectedVariantId === $v->id ? 'border-up-primary bg-up-primary text-white' : 'border-ink-200 hover:border-up-primary text-ink-700' }}"
                                >
                                    {{ $v->nama_varian }} <span class="opacity-60">({{ $vStok }})</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Qty -->
                <div class="flex items-center gap-3">
                    <label class="text-xs font-bold text-ink-700 uppercase tracking-wider">Jumlah</label>
                    <div class="flex items-center gap-2 bg-white border border-ink-200 rounded-xl px-2 py-1.5">
                        <button type="button" wire:click="$set('qty', {{ max(1, $qty - 1) }})" class="w-7 h-7 rounded-lg bg-ink-50 hover:bg-ink-100 font-bold text-ink-700 transition-colors cursor-pointer">−</button>
                        <span class="w-10 text-center font-bold tabular-nums">{{ $qty }}</span>
                        <button type="button" wire:click="$set('qty', {{ $qty + 1 }})" class="w-7 h-7 rounded-lg bg-ink-50 hover:bg-ink-100 font-bold text-ink-700 transition-colors cursor-pointer">+</button>
                    </div>
                    <span class="text-xs text-ink-400 font-medium">{{ $stokTotal }} unit tersedia</span>
                </div>

                <!-- Aksi -->
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button
                        wire:click="addToCart"
                        {{ $stokTotal <= 0 ? 'disabled' : '' }}
                        class="flex-1 py-3.5 rounded-xl font-bold text-sm transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-gradient-to-r from-up-primary to-indigo-600 text-white hover:from-up-primary-dark shadow-lg shadow-up-primary/25 hover:shadow-up-primary/35 active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Masukkan Keranjang
                    </button>
                    @if(auth('customer')->user())
                        <a href="{{ route('checkout') }}" class="px-6 py-3.5 rounded-xl font-bold text-sm bg-up-accent text-white hover:opacity-90 transition-all cursor-pointer min-h-[44px] flex items-center justify-center active:scale-[0.97]">Checkout</a>
                    @endif
                </div>

                @guest('customer')
                    <p class="text-xs text-ink-400 bg-ink-50 rounded-xl px-4 py-3">
                        💡 <a href="{{ route('customer.login') }}" class="text-up-primary font-bold hover:underline">Masuk</a> atau
                        <a href="{{ route('customer.register') }}" class="text-up-primary font-bold hover:underline">daftar</a>
                        untuk melihat harga member & reseller.
                    </p>
                @endguest

                <!-- Deskripsi -->
                @if($produk->deskripsi)
                    <div class="pt-4 border-t border-ink-100">
                        <h3 class="text-sm font-bold text-ink-900 mb-2">Deskripsi</h3>
                        <p class="text-sm text-ink-600 leading-relaxed">{{ $produk->deskripsi }}</p>
                    </div>
                @endif

                <!-- Kompatibilitas Perangkat (HP) -->
                @if($produk->tipeHps && $produk->tipeHps->count() > 0)
                    <div class="pt-4 border-t border-ink-100">
                        <h3 class="text-sm font-bold text-ink-900 mb-2.5 flex items-center gap-2">
                            <span>📱 Kompatibel dengan Model HP:</span>
                            <span class="text-[11px] font-normal text-ink-400">({{ $produk->tipeHps->count() }} model terverifikasi)</span>
                        </h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($produk->tipeHps as $thp)
                                <span class="px-2.5 py-1 rounded-lg bg-ink-50 border border-ink-200 text-xs font-semibold text-ink-800">
                                    {{ $thp->merk }} {{ $thp->model }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Produk Kompatibel & Substitusi Part -->
                @if($produk->produkKompatibel && $produk->produkKompatibel->count() > 0)
                    <div class="pt-4 border-t border-ink-100">
                        <h3 class="text-sm font-bold text-ink-900 mb-2.5 flex items-center gap-2">
                            <span>🔄 Part Alternatif & Kompatibel:</span>
                        </h3>
                        <div class="space-y-2">
                            @foreach($produk->produkKompatibel as $pk)
                                <a href="{{ route('shop.detail', $pk->slug) }}" class="flex items-center justify-between p-2.5 rounded-xl border border-ink-100 bg-ink-50/50 hover:border-up-primary/40 hover:bg-white transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-white border border-ink-100 flex-shrink-0">
                                            @if($pk->thumbnail_url ?: $pk->gambar)
                                                <img src="{{ $pk->thumbnail_url ?: $pk->gambar }}" alt="{{ $pk->nama }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-ink-300 text-xs">P</div>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-ink-900 group-hover:text-up-primary transition-colors">{{ $pk->nama }}</p>
                                            <p class="text-[10px] text-ink-400">{{ $pk->pivot->catatan ?: 'Dapat saling menggantikan' }}</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-black text-up-primary tabular-nums">Rp {{ number_format($pk->harga_jual_retail, 0, ',', '.') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Stok per cabang -->
                @if(count($stokPerCabang) > 0)
                    <div class="pt-4 border-t border-ink-100">
                        <h3 class="text-sm font-bold text-ink-900 mb-2.5">Ketersediaan di Cabang</h3>
                        <div class="space-y-2">
                            @foreach($stokPerCabang as $sc)
                                <div class="flex items-center justify-between bg-white border border-ink-100 rounded-xl px-4 py-2.5">
                                    <div>
                                        <p class="text-sm font-semibold text-ink-800">{{ $sc['cabang'] }}</p>
                                        @if($sc['alamat'])<p class="text-[11px] text-ink-400">{{ $sc['alamat'] }}</p>@endif
                                    </div>
                                    <span class="text-xs font-bold {{ $sc['stok'] > 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums">{{ $sc['stok'] }} unit</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- [T-54] Mobile Floating Action Bar (md:hidden) -->
        <div class="md:hidden fixed bottom-14 inset-x-0 bg-white/95 dark:bg-ink-900/95 backdrop-blur-xl border-t border-ink-100 dark:border-white/10 p-3 z-30 flex items-center justify-between gap-3 shadow-2xl">
            <div>
                <span class="text-[10px] text-ink-400 block uppercase font-medium">Total ({{ $qty }} unit)</span>
                <span class="text-sm font-black text-up-primary tabular-nums">Rp {{ number_format($hargaInfo['harga'] * $qty, 0, ',', '.') }}</span>
            </div>
            <button
                wire:click="addToCart"
                {{ $stokTotal <= 0 ? 'disabled' : '' }}
                class="flex-1 py-3 px-4 rounded-xl font-bold text-xs bg-gradient-to-r from-up-primary to-indigo-600 text-white shadow-lg shadow-up-primary/25 active:scale-[0.97] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                + Keranjang
            </button>
        </div>
    @endif
</div>