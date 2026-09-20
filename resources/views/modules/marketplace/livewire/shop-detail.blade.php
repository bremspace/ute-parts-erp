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
            <!-- Galeri -->
            <div>
                <div class="aspect-square bg-white rounded-3xl border border-ink-100 flex items-center justify-center overflow-hidden sticky lg:top-20">
                    @if($produk->gambar)
                        <img src="{{ $produk->gambar }}" alt="{{ $produk->nama }}" class="w-full h-full object-cover">
                    @else
                        <svg class="w-24 h-24 text-ink-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    @endif
                </div>
            </div>

            <!-- Info + Beli -->
            <div class="space-y-5">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] uppercase tracking-wider font-bold text-up-accent bg-up-accent/10 px-2.5 py-1 rounded-full">{{ $produk->kategori }}</span>
                        <span class="text-[11px] uppercase tracking-wider font-bold text-ink-500 bg-ink-50 px-2.5 py-1 rounded-full">{{ $produk->kondisi }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-ink-900 mt-3 leading-tight">{{ $produk->nama }}</h1>
                    @if($produk->brand_kompatibel)
                        <p class="text-sm text-ink-500 mt-1.5">Kompatibel: <strong class="text-ink-700">{{ $produk->brand_kompatibel }} {{ $produk->model_kompatibel }}</strong></p>
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
                                @php $vStok = (int) \App\Modules\Wms\Models\StokItem::where('produk_id', $produk->id)->where('sku_variant_id', $v->id)->sum('jumlah'); @endphp
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
                <div class="flex gap-3 pt-2">
                    <button
                        wire:click="addToCart"
                        {{ $stokTotal <= 0 ? 'disabled' : '' }}
                        class="flex-1 py-3.5 rounded-xl font-bold text-sm transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-gradient-to-r from-up-primary to-indigo-600 text-white hover:from-up-primary-dark shadow-lg shadow-up-primary/25 hover:shadow-up-primary/35 active:scale-[0.99] flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Masukkan Keranjang
                    </button>
                    @if(auth('customer')->user())
                        <a href="{{ route('checkout') }}" class="px-6 py-3.5 rounded-xl font-bold text-sm bg-up-accent text-white hover:opacity-90 transition-all cursor-pointer">Checkout</a>
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
    @endif
</div>