<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <!-- Hero dengan gradient signature + circuit line -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-up-primary via-indigo-600 to-up-accent p-8 sm:p-12 text-white">
        <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center relative z-10">
            <div>
                <span class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest bg-white/15 border border-white/20 px-3 py-1.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> Sparepart & Servis HP
                </span>
                <h1 class="text-3xl sm:text-4xl font-black mt-4 leading-tight">
                    Satu tempat untuk semua<br>kebutuhan HP Anda
                </h1>
                <p class="text-white/85 text-sm sm:text-base mt-3 max-w-md">
                    LCD, baterai, kamera, hingga servis profesional. Garansi resmi untuk setiap part & perbaikan.
                </p>

                <!-- Stat cepat -->
                <div class="flex gap-6 mt-6">
                    <div>
                        <p class="text-2xl font-black tabular-nums">{{ $products->total() }}+</p>
                        <p class="text-[11px] text-white/75 uppercase tracking-wider">Produk tersedia</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black">7+</p>
                        <p class="text-[11px] text-white/75 uppercase tracking-wider">Tahun pengalaman</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black">5+</p>
                        <p class="text-[11px] text-white/75 uppercase tracking-wider">Cabang melayani</p>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block">
                <div class="glass-panel rounded-2xl p-6">
                    <p class="text-xs uppercase tracking-wider text-white/70 font-semibold mb-2">Belanja lebih hemat</p>
                    <h3 class="font-bold text-white text-sm mb-3">Harga member otomatis saat login</h3>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between bg-white/10 rounded-lg px-3 py-2">
                            <span>Member Silver</span>
                            <span class="font-bold tabular-nums">diskon 3%</span>
                        </div>
                        <div class="flex justify-between bg-white/10 rounded-lg px-3 py-2">
                            <span>Member Gold</span>
                            <span class="font-bold tabular-nums">diskon 5%</span>
                        </div>
                        <div class="flex justify-between bg-white/10 rounded-lg px-3 py-2">
                            <span>Reseller / Agen</span>
                            <span class="font-bold tabular-nums">harga khusus</span>
                        </div>
                    </div>
                    @guest('customer')
                        <a href="{{ route('customer.register') }}" class="mt-4 block text-center py-2.5 rounded-xl bg-white text-up-primary font-bold text-xs hover:bg-ink-50 transition-colors">
                            Daftar Gratis Sekarang
                        </a>
                    @endguest
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Bar -->
    <section class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
        <div class="relative flex-1">
            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 pointer-events-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.250ms="search"
                placeholder="Cari LCD iPhone, baterai Samsung, dsb..."
                class="w-full pl-11 pr-4 py-3 rounded-xl bg-white border border-ink-100 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium"
            />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-6 gap-2">
            <select wire:model.live="filterKategori" class="px-3 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium text-ink-700 outline-none focus:border-up-primary">
                <option value="" class="text-ink-400">Semua Kategori</option>
                @foreach($categories as $k)
                    <option value="{{ $k }}" class="text-ink-900">{{ $k }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterKondisi" class="px-3 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium text-ink-700 outline-none focus:border-up-primary">
                <option value="" class="text-ink-400">Semua Kondisi</option>
                <option value="baru">Baru</option>
                <option value="oem">OEM</option>
                <option value="compatible">Compatible</option>
            </select>
            <select wire:model.live="filterHpMerk" class="px-3 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium text-ink-700 outline-none focus:border-up-primary">
                <option value="" class="text-ink-400">Merk HP</option>
                @foreach($hpMerkList as $m)
                    <option value="{{ $m }}" class="text-ink-900">{{ $m }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterHpModel" class="px-3 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium text-ink-700 outline-none focus:border-up-primary">
                <option value="" class="text-ink-400">Model HP</option>
                @foreach($hpModelList as $m)
                    <option value="{{ $m }}" class="text-ink-900">{{ $m }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterBrand" class="px-3 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium text-ink-700 outline-none focus:border-up-primary">
                <option value="" class="text-ink-400">Semua Brand</option>
                @foreach($brands as $b)
                    <option value="{{ $b }}" class="text-ink-900">{{ $b }}</option>
                @endforeach
            </select>
            <select wire:model.live="hargaMax" class="px-3 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium text-ink-700 outline-none focus:border-up-primary">
                <option value="" class="text-ink-400">Semua Harga</option>
                @foreach([100000, 250000, 500000, 1000000] as $hb)
                    <option value="{{ $hb }}" class="text-ink-900">≤ Rp {{ number_format($hb, 0, ',', '.') }}</option>
                @endforeach
            </select>
        </div>
    </section>

    <!-- Catalog Grid -->
    <section>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @forelse($products as $p)
                @php
                    $stok = (int) \App\Modules\Wms\Models\StokItem::where('produk_id', $p->id)->sum('jumlah');
                    $customer = auth('customer')->user();
                    $price = app(\App\Modules\Pos\Services\PricingService::class)->resolve($p, $customer);
                @endphp
                <a href="{{ route('shop.detail', $p->slug) }}" class="group bg-white rounded-2xl border border-ink-100 hover:border-up-primary/50 hover:shadow-lg hover:shadow-up-primary/5 transition-all overflow-hidden flex flex-col">
                    <!-- Thumb -->
                    <div class="aspect-square bg-ink-50 flex items-center justify-center relative overflow-hidden">
                        @if($p->gambar)
                            <img src="{{ $p->gambar }}" alt="{{ $p->nama }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <svg class="w-12 h-12 text-ink-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            </svg>
                        @endif
                        @if($stok <= 0)
                            <span class="absolute top-2 right-2 text-[10px] font-bold bg-up-red text-white px-2 py-0.5 rounded-full">Habis</span>
                        @elseif($price['diskon_nominal'] > 0)
                            <span class="absolute top-2 right-2 text-[10px] font-bold bg-up-accent text-white px-2 py-0.5 rounded-full">
                                Hemat {{ number_format($price['diskon_nominal'] / $price['harga_dasar'] * 100, 0) }}%
                            </span>
                        @endif
                    </div>

                    <!-- Info -->
                    <div class="p-3.5 flex flex-col flex-1">
                        <span class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold">{{ $p->kategori }}</span>
                        <h3 class="text-sm font-bold text-ink-900 mt-1 line-clamp-2 leading-snug group-hover:text-up-primary transition-colors">{{ $p->nama }}</h3>
                        @if($p->brand_kompatibel)
                            <p class="text-[11px] text-ink-400 mt-0.5">{{ $p->brand_kompatibel }} {{ $p->model_kompatibel }}</p>
                        @endif

                        <div class="mt-auto pt-3">
                            @if($price['diskon_nominal'] > 0)
                                <span class="block text-[11px] text-ink-400 line-through tabular-nums">Rp {{ number_format($price['harga_dasar'], 0, ',', '.') }}</span>
                            @endif
                            <span class="block text-base font-black text-up-primary tabular-nums">Rp {{ number_format($price['harga'], 0, ',', '.') }}</span>
                            <span class="flex items-center gap-1 mt-1.5 text-[10px] {{ $stok > 0 ? 'text-up-mint' : 'text-up-red' }} font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full {{ $stok > 0 ? 'bg-up-mint' : 'bg-up-red' }}"></span>
                                {{ $stok > 0 ? $stok . ' tersedia' : 'Stok habis' }}
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-20 text-center text-ink-400">
                    <svg class="w-12 h-12 mx-auto mb-3 text-ink-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p class="font-semibold text-ink-500">Tidak ditemukan produk yang cocok</p>
                    <p class="text-sm mt-1">Coba ubah kata kunci atau filter pencarian.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $products->links() }}
        </div>
    </section>
</div>