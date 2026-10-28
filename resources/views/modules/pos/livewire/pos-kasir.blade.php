<div
    class="flex flex-col gap-2.5 sm:gap-3.5 min-w-0 flex-1 min-h-[calc(100dvh-12rem)] md:min-h-0 md:h-[calc(100dvh-6.5rem)] pb-4 md:pb-0"
    x-data="{
        flexSet: {},
        searchOpen: false,
        cartViewMode: localStorage.getItem('pos_cart_view_mode') || 'card',
        setCartViewMode(mode) {
            this.cartViewMode = mode;
            localStorage.setItem('pos_cart_view_mode', mode);
        },
        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.$wire.cart && Object.keys(this.$wire.cart).length > 0) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        }
    }"
    @keydown.window.f4.prevent="$wire.openPaymentModal()"
    @keydown.window.f6.prevent="$wire.tahanTransaksi()"
    @keydown.window.escape.prevent="$wire.clearCart()"
>
    <!-- PWA Install Prompt Banner -->
    <x-prism.pwa-install-prompt variant="banner" class="flex-shrink-0" />

    <!-- TOP CONTROL BAR: Scan/Search, Gudang, Pelanggan, Aksi Cepat & Big Total -->
    <div class="glass-panel p-3 sm:p-3.5 rounded-2xl border border-ink-200/80 dark:border-white/10 shadow-sm flex-shrink-0 flex flex-col gap-2.5">
        <div class="flex items-center gap-2.5 flex-wrap lg:flex-nowrap justify-between">
            <!-- Search & Barcode Input with Floating Dropdown -->
            <div class="relative flex-1 min-w-[200px] sm:min-w-[260px]" @click.outside="searchOpen = false">
                <x-prism.barcode-scan-input
                    placeholder="Scan Barcode / ketik nama barang (F2 / Kamera)..."
                    model="search"
                    wire:keydown.enter="scanEnter"
                    :continuous="true"
                    title="Kamera Scanner Barcode Kasir"
                    @focus="searchOpen = true"
                    @input="searchOpen = true"
                />

                <!-- Floating Search Results Dropdown (MUNCUL OTOMATIS SAAT FOKUS / PENCARIAN) -->
                <div
                    x-show="searchOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-out duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-2 scale-[0.98]"
                    class="absolute left-0 right-0 sm:left-0 sm:right-auto sm:w-[580px] lg:w-[680px] top-full mt-2 z-50 origin-top bg-white/95 dark:bg-[#0B1120]/95 backdrop-blur-xl border border-ink-200/90 dark:border-white/15 rounded-2xl shadow-2xl overflow-hidden max-h-[70vh] flex flex-col"
                    x-cloak
                >
                    <div class="px-3.5 sm:px-4 py-2.5 bg-ink-50/90 dark:bg-white/[0.04] border-b border-ink-200/80 dark:border-white/10 flex items-center justify-between text-xs flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                {{ strlen($search) > 0 ? 'Hasil Pencarian Produk' : 'Katalog Produk Cepat' }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-bold text-[10px] tabular-nums border border-up-primary/20">
                                {{ count($products) }} item
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="searchOpen = false"
                            class="text-xs text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer px-2 py-1 rounded-lg hover:bg-ink-200/70 dark:hover:bg-white/10 transition-colors"
                        >
                            ✕ Tutup (ESC)
                        </button>
                    </div>

                    <div class="overflow-y-auto divide-y divide-ink-200/60 dark:divide-white/5 p-1.5 sm:p-2 space-y-1">
                        @forelse($products as $prod)
                            @php
                                $stokTotal = $stokPerProduk[$prod->id] ?? (\App\Modules\Pos\Livewire\PosKasir::STOK_TANPA_GUDANG);
                                $stokKosong = $stokTotal <= 0;
                                $gudangDipilih = (bool) $selectedGudangId;
                                $alasanTidakBisaDipilih = ! $gudangDipilih
                                    ? 'Pilih gudang terlebih dahulu untuk melihat stok & memilih produk ini.'
                                    : "Stok {$prod->nama} habis di gudang ini — tidak bisa dipilih. Pilih gudang lain yang punya stok.";
                                $pricing = app(\App\Modules\Pos\Services\PricingService::class)->resolve($prod, $customer);
                                $baseHarga = (float) $pricing['harga'];
                                $satuanDasar = strtoupper($prod->satuan ?: 'PCS');
                                $satuanList = $prod->satuanKonversi ?? collect();
                            @endphp
                            <div
                                wire:key="search-product-{{ $prod->id }}"
                                @if($stokKosong)
                                    role="button"
                                    aria-disabled="true"
                                    title="{{ $alasanTidakBisaDipilih }}"
                                @endif
                                class="p-2.5 sm:p-3 rounded-xl border border-transparent transition-all flex flex-col gap-2 {{ $stokKosong ? 'opacity-60 grayscale-[40%] bg-ink-50/40 dark:bg-white/[0.01]' : 'hover:bg-ink-50/90 dark:hover:bg-white/[0.04] hover:border-ink-200/60 dark:border-white/10' }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex items-start gap-2.5 sm:gap-3 flex-1">
                                        <!-- Thumbnail with fallback icon -->
                                        <div class="w-12 h-12 rounded-xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 flex-shrink-0 flex items-center justify-center overflow-hidden">
                                            @if($prod->thumbnail_url ?: $prod->gambar)
                                                <img src="{{ $prod->thumbnail_url ?: $prod->gambar }}" alt="{{ $prod->nama }}" class="w-full h-full object-cover rounded-xl" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                                                <div class="hidden w-full h-full items-center justify-center text-ink-400 dark:text-ink-500">
                                                    <svg class="w-5 h-5 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            @else
                                                <svg class="w-5 h-5 text-ink-400 dark:text-ink-500 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                            @endif
                                        </div>

                                        <!-- Title, SKU, Chips -->
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                <span class="font-mono text-[10px] sm:text-[11px] font-bold text-ink-600 dark:text-ink-400 bg-ink-100 dark:bg-white/5 px-1.5 py-0.5 rounded border border-ink-200/60 dark:border-white/5">
                                                    {{ $prod->sku }}
                                                </span>
                                                <span class="text-[10px] uppercase font-semibold text-ink-500 dark:text-ink-400 bg-ink-100/60 dark:bg-white/5 px-1.5 py-0.5 rounded">
                                                    {{ $prod->kategoriRelasi?->nama ?? $prod->kategori ?? 'Sparepart' }}
                                                </span>
                                                <span class="text-[10px] font-bold text-ink-500 dark:text-ink-400 bg-ink-100/60 dark:bg-white/5 px-1.5 py-0.5 rounded uppercase">
                                                    Satuan: {{ $satuanDasar }}
                                                </span>
                                                @php
                                                    $thModels = collect($prod->tipeHps ? $prod->tipeHps->pluck('model') : []);
                                                    if (is_array($prod->kompatibilitas_hp)) {
                                                        $thModels = $thModels->merge(collect($prod->kompatibilitas_hp)->pluck('model'));
                                                    }
                                                    if (!empty($prod->model_kompatibel)) {
                                                        $thModels = $thModels->merge(explode(',', (string) $prod->model_kompatibel));
                                                    }
                                                    $thModels = $thModels->map(fn($m) => trim((string)$m))->filter()->unique()->values();
                                                @endphp
                                                @if($thModels->isNotEmpty())
                                                    <span class="text-[10px] text-up-primary font-medium bg-up-primary/10 px-1.5 py-0.5 rounded border border-up-primary/20">
                                                        📱 {{ $thModels->join(', ') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <h4 class="font-bold text-xs sm:text-sm text-ink-900 dark:text-white leading-snug line-clamp-1">
                                                {{ $prod->nama }}
                                            </h4>
                                        </div>
                                    </div>

                                    <!-- Price & Stock Badge -->
                                    <div class="text-right flex-shrink-0 flex flex-col items-end gap-1">
                                        @if(($pricing['diskon_nominal'] ?? 0) > 0)
                                            <div class="flex items-center gap-1">
                                                <span class="text-[10px] text-ink-400 line-through tabular-nums">
                                                    Rp {{ number_format($pricing['harga_dasar'], 0, ',', '.') }}
                                                </span>
                                                @if(!empty($pricing['tier']))
                                                    <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-up-primary/10 text-up-primary border border-up-primary/20">
                                                        {{ $pricing['tier'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        <span class="text-xs sm:text-sm font-black text-up-mint tabular-nums">
                                            Rp {{ number_format($baseHarga, 0, ',', '.') }}
                                            <span class="text-[10px] font-normal text-ink-400">/{{ $satuanDasar }}</span>
                                        </span>
                                        @if(($pricing['diskon_nominal'] ?? 0) > 0)
                                            <span class="text-[9px] font-semibold text-emerald-500 tabular-nums">
                                                Hemat Rp {{ number_format($pricing['diskon_nominal'], 0, ',', '.') }}
                                            </span>
                                        @endif

                                        @if($stokKosong)
                                            <x-prism.status-pill status="batal">
                                                {{ $gudangDipilih ? 'Stok kosong' : 'Pilih gudang dulu' }}
                                            </x-prism.status-pill>
                                            <span class="hidden">{{ $alasanTidakBisaDipilih }}</span>
                                        @else
                                            <x-prism.stock-gauge :stok="$stokTotal" :min="5" />
                                        @endif
                                    </div>
                                </div>

                                <!-- Action Buttons & Multi-Satuan Quick-Add -->
                                <div class="flex items-center justify-between gap-2 pt-2 border-t border-ink-100 dark:border-white/5 flex-wrap">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @if($satuanList->isNotEmpty())
                                            <span class="text-[10px] font-semibold text-ink-400 uppercase tracking-wider hidden sm:inline">Pilihan Kemasan:</span>
                                            @foreach($satuanList as $sk)
                                                @php
                                                    $fKonv = max(0.0001, (float) $sk->faktor_konversi);
                                                    $hargaSk = $sk->harga_jual > 0 ? (float) $sk->harga_jual : round($baseHarga * $fKonv, 2);
                                                @endphp
                                                <span class="text-[10px] px-2 py-0.5 rounded-md bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/10 text-ink-600 dark:text-ink-300 tabular-nums">
                                                    1 {{ strtoupper($sk->satuan_kode) }} ({{ $fKonv + 0 }} {{ $satuanDasar }}) • Rp {{ number_format($hargaSk, 0, ',', '.') }}
                                                </span>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Quick Add Buttons -->
                                    <div class="flex items-center gap-1.5 ml-auto">
                                        @if($stokKosong)
                                            <button
                                                type="button"
                                                disabled
                                                title="{{ $alasanTidakBisaDipilih }}"
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-ink-200/50 dark:bg-white/5 text-ink-400 cursor-not-allowed select-none"
                                            >
                                                Stok Habis
                                            </button>
                                        @else
                                            <!-- Tombol Tambah Satuan Dasar -->
                                            <button
                                                type="button"
                                                wire:click="addToCart({{ $prod->id }})"
                                                @click="searchOpen = false"
                                                title="Tambah 1 {{ $satuanDasar }}"
                                                class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-up-primary hover:bg-up-primary-dark text-white cursor-pointer transition active:scale-95 flex items-center gap-1 shadow-sm shadow-up-primary/20 min-h-[34px]"
                                            >
                                                <span>+ {{ $satuanDasar }}</span>
                                            </button>

                                            <!-- Tombol Tambah Tiap Multi-Satuan Konversi -->
                                            @foreach($satuanList as $sk)
                                                <button
                                                    type="button"
                                                    wire:click="addToCart({{ $prod->id }}, null, '{{ $sk->satuan_kode }}')"
                                                    @click="searchOpen = false"
                                                    title="Tambah 1 {{ strtoupper($sk->satuan_kode) }}"
                                                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/30 cursor-pointer transition active:scale-95 flex items-center gap-1 min-h-[34px]"
                                                >
                                                    <span>+ {{ strtoupper($sk->satuan_kode) }}</span>
                                                </button>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-ink-400 text-xs">
                                <div class="w-10 h-10 rounded-2xl bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/10 mx-auto mb-2 flex items-center justify-center text-ink-400">
                                    <svg class="w-5 h-5 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <p>Tidak ada produk ditemukan untuk pencarian "<span class="text-ink-900 dark:text-white font-semibold">{{ $search }}</span>"</p>
                            </div>
                        @endforelse

                        @if($adaLebihBanyak)
                            <div class="p-2.5 text-center">
                                <button
                                    type="button"
                                    wire:click="muatLebihBanyak"
                                    class="text-xs font-bold text-up-primary hover:underline cursor-pointer py-1 px-3 rounded-lg hover:bg-up-primary/10 transition"
                                >
                                    Muat lebih banyak…
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Filter Preferences Button -->
            <button
                type="button"
                wire:click="$toggle('showCustomFilterSettings')"
                class="px-3.5 py-2.5 rounded-xl border border-ink-200/80 dark:border-white/10 text-xs font-semibold text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[44px]"
                title="Atur filter pencarian produk"
            >
                <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span class="hidden sm:inline">Filter</span>
            </button>

            <!-- Kolom Pencarian Konsumen Optimal (Tanpa Dropdown CRM Berlebih) -->
            <div
                class="w-full sm:w-64 md:w-72 lg:w-80 flex-shrink-0 relative"
                x-data="{ crmOpen: false }"
                @click.outside="crmOpen = false"
            >
                <div class="relative flex items-center">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="pelangganSearch"
                        @focus="crmOpen = true"
                        @input="crmOpen = true"
                        placeholder="Cari konsumen (nama / no HP)..."
                        class="w-full pl-8 pr-8 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]"
                    />
                    <div class="absolute left-2.5 pointer-events-none text-ink-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    @if(strlen($pelangganSearch) > 0)
                        <button
                            type="button"
                            wire:click="$set('pelangganSearch', '')"
                            @click="crmOpen = false"
                            class="absolute right-2.5 text-ink-400 hover:text-ink-900 dark:hover:text-white p-1 rounded-md"
                        >
                            ✕
                        </button>
                    @endif
                </div>

                <!-- Floating Customer Search Results -->
                <div
                    x-show="crmOpen"
                    x-transition:enter="transition ease-out duration-120"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0 translate-y-1"
                    class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white dark:bg-[#0B1120] border border-ink-200/90 dark:border-white/15 rounded-2xl shadow-2xl overflow-hidden max-h-64 overflow-y-auto p-1 text-xs"
                    x-cloak
                >
                    @if($pelangganCari->isNotEmpty())
                        <div class="px-2.5 py-1.5 text-[10px] uppercase font-bold text-ink-400 tracking-wider">
                            {{ strlen($pelangganSearch) >= 1 ? 'Hasil Pencarian Konsumen' : 'Konsumen Terakhir / Cepat' }}
                        </div>
                        @foreach($pelangganCari as $p)
                            <div
                                wire:key="pelanggan-pilih-{{ $p->id }}"
                                wire:click="setPelanggan({{ $p->id }})"
                                @click="crmOpen = false"
                                class="p-2 rounded-xl flex items-center justify-between gap-2 hover:bg-up-primary/10 cursor-pointer transition select-none group"
                            >
                                <div class="min-w-0">
                                    <div class="font-bold text-ink-900 dark:text-white truncate group-hover:text-up-primary">
                                        {{ $p->nama }}
                                    </div>
                                    <div class="text-[11px] text-ink-500 font-mono">
                                        {{ $p->telepon ?: '-' }}
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-bold text-[10px] whitespace-nowrap">
                                    {{ $p->tierMembership?->nama ?? 'Retail' }}
                                </span>
                            </div>
                        @endforeach
                    @else
                        <!-- Tidak ditemukan -> Tombol Tambah Konsumen Baru -->
                        <div class="p-3 text-center space-y-2">
                            <p class="text-xs text-ink-500 dark:text-ink-400">
                                Konsumen "<span class="font-bold text-ink-900 dark:text-white">{{ $pelangganSearch }}</span>" tidak ditemukan.
                            </p>
                            <button
                                type="button"
                                wire:click="openPelangganBaru"
                                @click="crmOpen = false"
                                class="w-full py-2 px-3 rounded-xl bg-up-primary hover:bg-indigo-600 text-white font-bold text-xs shadow-sm shadow-up-primary/20 transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Tambah Konsumen Baru</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- SID RETAIL STYLE BIG DISPLAY TOTAL -->
            <div class="w-full sm:w-auto px-3.5 py-1.5 rounded-xl bg-ink-900 dark:bg-black/80 border border-ink-700/80 dark:border-white/10 flex sm:flex-col justify-between sm:justify-center items-center sm:items-end min-w-[150px] sm:min-w-[180px] min-h-[44px] flex-shrink-0 shadow-inner">
                <span class="text-[9px] uppercase font-bold text-ink-400 tracking-wider">TOTAL TAGIHAN</span>
                <span class="text-xl sm:text-2xl font-black font-mono text-up-mint tabular-nums leading-tight">
                    Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                </span>
            </div>
        </div>

        <!-- BARIS 2: SHORTCUT AKSI KASIR (RETUR, RIWAYAT, ONLINE, SERVIS) & DATA KONSUMEN TERPILIH -->
        <div class="flex items-center justify-between gap-2.5 pt-2 border-t border-ink-200/50 dark:border-white/5 flex-wrap">
            <!-- Left: Shortcut Actions -->
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto scrollbar-none w-full sm:w-auto flex-nowrap sm:flex-wrap pb-0.5">
                @canany(['pos.view', 'pos.view-own', 'pos.create'])
                <a
                    href="/app/pos/retur"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-up-red/30 bg-up-red/10 hover:bg-up-red/20 text-xs font-bold text-up-red transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98] flex-shrink-0 whitespace-nowrap"
                    title="Buka Manajemen Retur Konsumen"
                >
                    <svg class="w-4 h-4 text-up-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                    <span>Retur</span>
                </a>
                @endcanany

                <button
                    type="button"
                    wire:click="bukaRiwayatTransaksi"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-ink-200/80 dark:border-white/10 bg-black/5 dark:bg-white/5 hover:bg-black/10 text-xs font-bold text-ink-700 dark:text-ink-200 transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98] flex-shrink-0 whitespace-nowrap"
                    title="Riwayat Transaksi Kasir Hari Ini"
                >
                    <svg class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Riwayat</span>
                </button>

                <button
                    type="button"
                    wire:click="bukaPesananOnlineModal"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-up-amber/30 bg-up-amber/10 hover:bg-up-amber/20 text-xs font-bold text-up-amber transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98] flex-shrink-0 whitespace-nowrap"
                    title="Pesanan Online Pickup Cabang"
                >
                    <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>Online</span>
                    @if($jumlahPesananOnline > 0)
                        <span class="min-w-[18px] h-4.5 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums">{{ $jumlahPesananOnline }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="bukaBayarServisModal"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/20 text-xs font-bold text-blue-500 dark:text-blue-400 transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98] flex-shrink-0 whitespace-nowrap"
                    title="Bayar Tiket Servis di Kasir"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                    <span>Bayar Servis</span>
                </button>

                <!-- Shortcut Mutasi Kas Laci (Sejajar dengan Retur & Riwayat) -->
                <button
                    type="button"
                    wire:click="bukaMutasiKasModal('keluar')"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 hover:bg-emerald-500/20 text-xs font-bold text-emerald-600 dark:text-emerald-400 transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98] flex-shrink-0 whitespace-nowrap"
                    title="Catat Mutasi Kas Masuk / Keluar Laci"
                >
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Mutasi Kas</span>
                </button>

                <!-- Shortcut Transaksi Ditahan (Park) -->
                <button
                    type="button"
                    wire:click="bukaDitahanPanel"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-up-amber/30 bg-up-amber/10 hover:bg-up-amber/20 text-xs font-bold text-up-amber transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98] flex-shrink-0 whitespace-nowrap"
                    title="Lihat & Lanjutkan Transaksi yang Ditahan"
                >
                    <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Ditahan</span>
                    @if($ditahanList->count() > 0)
                        <span class="min-w-[18px] h-4.5 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums">{{ $ditahanList->count() }}</span>
                    @endif
                </button>
            </div>

            <!-- Right: Data Konsumen Terpilih (atau Pelanggan Umum) + Helper Key -->
            <div class="flex items-center gap-3 flex-wrap ml-auto">
                <!-- Status & Info Konsumen Terpilih -->
                <div class="flex items-center gap-1.5 text-xs">
                    @if($customer)
                        <div class="px-3 py-1.5 rounded-xl bg-up-primary/10 border border-up-primary/30 text-ink-900 dark:text-white font-semibold flex items-center gap-2 flex-wrap">
                            <span class="text-up-primary font-bold">👤 {{ $customer->nama }}</span>
                            <span class="px-2 py-0.5 rounded-md bg-up-primary text-white text-[10px] font-black uppercase">
                                {{ $customer->tierMembership?->nama ?? ($customer->tipe_konsumen === 'reseller' ? 'Reseller' : ($customer->tipe_konsumen === 'agen' ? 'Agen' : 'Member')) }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md bg-up-amber/15 text-up-amber text-[10px] font-bold border border-up-amber/30 tabular-nums">
                                ⭐ {{ number_format($customer->poin_loyalty ?? 0) }} pts
                            </span>

                            <!-- Opsi Poin Member: Simpan Poin (Akumulasi) vs Tukar Diskon Langsung -->
                            @if(($customer->poin_loyalty ?? 0) > 0 || $poinDitukar > 0)
                                <button
                                    type="button"
                                    wire:click="toggleModePoinMember"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer flex items-center gap-1 {{ $poinDitukar > 0 ? 'bg-up-mint/20 text-up-mint border-up-mint/30 shadow-sm' : 'bg-white/10 text-ink-700 dark:text-ink-200 border-ink-200 dark:border-white/15 hover:bg-white/15' }}"
                                    title="Klik untuk ubah: Simpan Poin (Akumulasi) vs Tukar Poin Jadi Diskon Langsung"
                                >
                                    @if($poinDitukar > 0)
                                        <span>🏷️ Poin: Diskon (-Rp {{ number_format($diskonPoin, 0, ',', '.') }})</span>
                                    @else
                                        <span>🎁 Poin: Disimpan</span>
                                    @endif
                                </button>
                            @endif

                            <!-- Opsi Reward Mitra: Diskon Langsung vs Komisi Akumulasi -->
                            @if($customer->is_reseller || in_array($customer->tipe_konsumen, ['reseller', 'agen'], true))
                                <button
                                    type="button"
                                    wire:click="toggleModeRewardMitra"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer flex items-center gap-1 {{ $modeRewardMitra === 'komisi_akumulasi' ? 'bg-up-amber text-ink-950 border-up-amber' : 'bg-up-mint/20 text-up-mint border-up-mint/30' }}"
                                    title="Klik untuk ubah: Diskon Langsung vs Simpan Komisi Mitra"
                                >
                                    @if($modeRewardMitra === 'komisi_akumulasi')
                                        <span>💰 Mitra: Simpan Komisi {{ ($potensiKomisiTotal ?? 0) > 0 ? '(+Rp '.number_format($potensiKomisiTotal/1000).'rb)' : '' }}</span>
                                    @else
                                        <span>🏷️ Mitra: Diskon Langsung {{ ($diskonTierTotal ?? 0) > 0 ? '(-Rp '.number_format($diskonTierTotal/1000).'rb)' : '' }}</span>
                                    @endif
                                </button>
                            @endif

                            <!-- Menu Pengaturan Skema & Diskon Manual -->
                            <button
                                type="button"
                                wire:click="openDiskonModal"
                                class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer flex items-center gap-1 {{ ($diskonNominal > 0 || $diskonPersen > 0) ? 'bg-up-amber text-ink-950 border-up-amber shadow-sm' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 border-ink-200/80 dark:border-white/10 hover:bg-black/10' }}"
                                title="Buka Pengaturan Skema Poin, Komisi Mitra, dan Diskon Manual Khusus"
                            >
                                <span>⚙️ Skema / Diskon</span>
                                @if($diskonPersen > 0)
                                    <span class="bg-black/20 px-1 py-0.2 rounded font-black text-[9px]">{{ $diskonPersen }}%</span>
                                @elseif($diskonNominal > 0)
                                    <span class="bg-black/20 px-1 py-0.2 rounded font-black text-[9px]">-Rp {{ number_format($diskonNominal / 1000) }}rb</span>
                                @endif
                            </button>
                            @if($customer->telepon)
                                <span class="text-[11px] text-ink-400 font-mono hidden md:inline">({{ $customer->telepon }})</span>
                            @endif
                            <button
                                type="button"
                                wire:click="setPelanggan(null)"
                                class="ml-1 p-0.5 text-ink-400 hover:text-up-red rounded cursor-pointer"
                                title="Lepas / Ganti ke Pelanggan Umum"
                            >
                                ✕
                            </button>
                        </div>
                    @else
                        <div class="px-2.5 py-1.5 rounded-xl bg-black/5 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 text-ink-500 text-[11px] font-medium flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-ink-400"></span>
                            <span>Konsumen: <strong>Pelanggan Umum</strong></span>
                            <button
                                type="button"
                                wire:click="openDiskonModal"
                                class="ml-1 px-1.5 py-0.5 rounded bg-up-amber/15 text-up-amber hover:bg-up-amber/25 text-[10px] font-bold border border-up-amber/30 cursor-pointer"
                                title="Beri Diskon Langsung pada transaksi"
                            >
                                🏷️ Diskon
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Keyboard Shortcuts Helper Badge -->
                <div class="hidden xl:flex items-center gap-1 text-[10px] text-ink-400 font-mono">
                    <span class="px-1.5 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 font-bold">F2 Scan</span>
                    <span class="px-1.5 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 font-bold">F4 Bayar</span>
                    <span class="px-1.5 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 font-bold">F6 Tahan</span>
                </div>
            </div>
        </div>

        <!-- Custom Filter Drawer (Toggleable) -->
        @if($showCustomFilterSettings)
            <div class="p-3 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="font-bold text-ink-700 dark:text-ink-300">Tampilkan Filter:</span>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-kategori-{{ $visibleFilters['kategori'] ? '1' : '0' }}" wire:click="toggleFilter('kategori')" @checked($visibleFilters['kategori']) class="rounded border-ink-300 text-up-primary">
                        <span>Kategori</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-brand-{{ $visibleFilters['brand'] ? '1' : '0' }}" wire:click="toggleFilter('brand')" @checked($visibleFilters['brand']) class="rounded border-ink-300 text-up-primary">
                        <span>Brand Part</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-kualitas-{{ $visibleFilters['kualitas'] ? '1' : '0' }}" wire:click="toggleFilter('kualitas')" @checked($visibleFilters['kualitas']) class="rounded border-ink-300 text-up-primary">
                        <span>Kualitas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-tipe_hp-{{ $visibleFilters['tipe_hp'] ? '1' : '0' }}" wire:click="toggleFilter('tipe_hp')" @checked($visibleFilters['tipe_hp']) class="rounded border-ink-300 text-up-primary">
                        <span>Tipe HP</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-brand_kompatibel-{{ $visibleFilters['brand_kompatibel'] ? '1' : '0' }}" wire:click="toggleFilter('brand_kompatibel')" @checked($visibleFilters['brand_kompatibel']) class="rounded border-ink-300 text-up-primary">
                        <span>Brand Kompatibel</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-model_kompatibel-{{ $visibleFilters['model_kompatibel'] ? '1' : '0' }}" wire:click="toggleFilter('model_kompatibel')" @checked($visibleFilters['model_kompatibel']) class="rounded border-ink-300 text-up-primary">
                        <span>Model Kompatibel</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-kondisi-{{ $visibleFilters['kondisi'] ? '1' : '0' }}" wire:click="toggleFilter('kondisi')" @checked($visibleFilters['kondisi']) class="rounded border-ink-300 text-up-primary">
                        <span>Kondisi</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-satuan-{{ $visibleFilters['satuan'] ? '1' : '0' }}" wire:click="toggleFilter('satuan')" @checked($visibleFilters['satuan']) class="rounded border-ink-300 text-up-primary">
                        <span>Satuan</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-abc_class-{{ $visibleFilters['abc_class'] ? '1' : '0' }}" wire:click="toggleFilter('abc_class')" @checked($visibleFilters['abc_class']) class="rounded border-ink-300 text-up-primary">
                        <span>Kelas ABC</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:key="pos-chk-stok-{{ $visibleFilters['stok'] ? '1' : '0' }}" wire:click="toggleFilter('stok')" @checked($visibleFilters['stok']) class="rounded border-ink-300 text-up-primary">
                        <span>Status Stok</span>
                    </label>
                </div>
                <button type="button" wire:click="resetCustomFilters" class="text-xs text-up-red font-semibold hover:underline cursor-pointer">
                    Reset Filter
                </button>
            </div>
        @endif

        <!-- Active Dinamis Filter Bar -->
        @if(collect($visibleFilters)->contains(true))
            <div class="flex items-center gap-2 flex-wrap pt-1 text-xs">
                @if($visibleFilters['kategori'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterKategoriId" class="px-2.5 py-1.5 {{ $filterKategoriId ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Kategori</option>
                            @foreach($kategoriList as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->nama }}</option>
                                @foreach($parent->children as $child)
                                    <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        @if($filterKategoriId)
                            <button type="button" wire:click="$set('filterKategoriId', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Kategori">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['brand'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterBrandId" class="px-2.5 py-1.5 {{ $filterBrandId ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Brand Part</option>
                            @foreach($brandList as $b)
                                <option value="{{ $b->id }}">{{ $b->nama }}</option>
                            @endforeach
                        </select>
                        @if($filterBrandId)
                            <button type="button" wire:click="$set('filterBrandId', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Brand">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['kualitas'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterKualitasId" class="px-2.5 py-1.5 {{ $filterKualitasId ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Kualitas</option>
                            @foreach($kualitasList as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}{{ $k->brand_id ? ' ('.$k->brand?->nama.')' : '' }}</option>
                            @endforeach
                        </select>
                        @if($filterKualitasId)
                            <button type="button" wire:click="$set('filterKualitasId', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Kualitas">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['tipe_hp'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterTipeHpId" class="px-2.5 py-1.5 {{ $filterTipeHpId ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Tipe HP</option>
                            @foreach($tipeHpList as $th)
                                @php
                                    $thLabel = str_starts_with(strtolower($th->model), strtolower($th->merk))
                                        ? $th->model
                                        : (strcasecmp($th->merk, 'Apple') === 0 && preg_match('/^\d+/i', $th->model)
                                            ? 'iPhone ' . $th->model
                                            : $th->merk . ' ' . $th->model);
                                @endphp
                                <option value="{{ $th->id }}">{{ $thLabel }}</option>
                            @endforeach
                        </select>
                        @if($filterTipeHpId)
                            <button type="button" wire:click="$set('filterTipeHpId', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Tipe HP">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['brand_kompatibel'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterBrandKompatibel" class="px-2.5 py-1.5 {{ $filterBrandKompatibel ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Brand Kompatibel</option>
                            @foreach($brandKompatibelList as $bk)
                                <option value="{{ $bk }}">{{ $bk }}</option>
                            @endforeach
                        </select>
                        @if($filterBrandKompatibel)
                            <button type="button" wire:click="$set('filterBrandKompatibel', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Brand Kompatibel">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['model_kompatibel'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterModelKompatibel" class="px-2.5 py-1.5 {{ $filterModelKompatibel ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">{{ $filterBrandKompatibel ? 'Semua Tipe '.$filterBrandKompatibel : 'Semua Model Kompatibel' }}</option>
                            @foreach($modelKompatibelList as $mk)
                                @php
                                    $brandFilter = trim((string) ($filterBrandKompatibel ?? ''));
                                    $lowerMk = strtolower($mk);
                                    $nonPhoneMerks = ['Accessories', 'Tg', 'Tools Service', 'Universal', 'OEM'];
                                    if (in_array($lowerMk, ['universal', 'semua tipe hp'], true)) {
                                        $mkLabel = $mk;
                                    } elseif (!empty($brandFilter)) {
                                        if (in_array($brandFilter, $nonPhoneMerks, true) || str_starts_with($lowerMk, strtolower($brandFilter))) {
                                            $mkLabel = $mk;
                                        } elseif (strcasecmp($brandFilter, 'Apple') === 0) {
                                            if (str_starts_with($lowerMk, 'iphone') || str_starts_with($lowerMk, 'ipad')) {
                                                $mkLabel = $mk;
                                            } elseif (preg_match('/^\d+(\s*(pro|plus|max|mini|e))?/i', $mk)) {
                                                $mkLabel = 'iPhone ' . $mk;
                                            } else {
                                                $mkLabel = $brandFilter . ' ' . $mk;
                                            }
                                        } elseif (str_starts_with($lowerMk, 'galaxy') || str_starts_with($lowerMk, 'redmi') || str_starts_with($lowerMk, 'poco') || str_starts_with($lowerMk, 'pixel')) {
                                            $mkLabel = $mk;
                                        } else {
                                            $mkLabel = $brandFilter . ' ' . $mk;
                                        }
                                    } else {
                                        if (str_starts_with($lowerMk, 'iphone') || str_starts_with($lowerMk, 'ipad') || str_starts_with($lowerMk, 'galaxy') || str_starts_with($lowerMk, 'redmi') || str_starts_with($lowerMk, 'poco') || str_starts_with($lowerMk, 'pixel')) {
                                            $mkLabel = $mk;
                                        } else {
                                            $foundTh = isset($tipeHpList) ? ($tipeHpList->where('model', $mk)->first(fn($t) => !in_array($t->merk, $nonPhoneMerks, true)) ?? $tipeHpList->firstWhere('model', $mk)) : null;
                                            if ($foundTh && !empty($foundTh->merk) && !in_array($foundTh->merk, $nonPhoneMerks, true) && !str_starts_with($lowerMk, strtolower($foundTh->merk))) {
                                                if (strcasecmp($foundTh->merk, 'Apple') === 0 && preg_match('/^\d+/i', $mk)) {
                                                    $mkLabel = 'iPhone ' . $mk;
                                                } else {
                                                    $mkLabel = $foundTh->merk . ' ' . $mk;
                                                }
                                            } else {
                                                $mkLabel = $mk;
                                            }
                                        }
                                    }
                                @endphp
                                <option value="{{ $mk }}">{{ $mkLabel }}</option>
                            @endforeach
                        </select>
                        @if($filterModelKompatibel)
                            <button type="button" wire:click="$set('filterModelKompatibel', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Model Kompatibel">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['kondisi'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterKondisi" class="px-2.5 py-1.5 {{ $filterKondisi ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Kondisi</option>
                            @foreach($kondisiList as $kd)
                                <option value="{{ is_array($kd) ? $kd['kode'] : $kd }}">{{ is_array($kd) ? $kd['nama'] : ucfirst($kd) }}</option>
                            @endforeach
                        </select>
                        @if($filterKondisi)
                            <button type="button" wire:click="$set('filterKondisi', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Kondisi">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['satuan'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterSatuan" class="px-2.5 py-1.5 {{ $filterSatuan ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Satuan</option>
                            @foreach($satuanList as $su)
                                <option value="{{ is_object($su) ? $su->kode : $su }}">{{ is_object($su) ? ($su->nama . ' (' . $su->kode . ')') : strtoupper($su) }}</option>
                            @endforeach
                        </select>
                        @if($filterSatuan)
                            <button type="button" wire:click="$set('filterSatuan', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Satuan">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['abc_class'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterAbcClass" class="px-2.5 py-1.5 {{ $filterAbcClass ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]">
                            <option value="">Semua Kelas ABC</option>
                            <option value="A">Kelas A (Fast Moving)</option>
                            <option value="B">Kelas B (Medium Moving)</option>
                            <option value="C">Kelas C (Slow Moving)</option>
                        </select>
                        @if($filterAbcClass)
                            <button type="button" wire:click="$set('filterAbcClass', null)" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Kelas ABC">✕</button>
                        @endif
                    </div>
                @endif
                @if($visibleFilters['stok'])
                    <div class="relative inline-flex items-center">
                        <select wire:model.live="filterStokStatus" class="px-2.5 py-1.5 {{ $filterStokStatus !== 'semua' ? 'pr-7' : '' }} rounded-xl glass-input text-xs min-h-[38px] sm:min-h-[34px]" title="Ketersediaan Stok">
                            <option value="semua">Semua Stok</option>
                            <option value="ready">Ready Stok (> 0)</option>
                            <option value="menipis">Stok Menipis (≤ Min)</option>
                            <option value="habis">Stok Habis (0)</option>
                        </select>
                        @if($filterStokStatus !== 'semua')
                            <button type="button" wire:click="$set('filterStokStatus', 'semua')" class="absolute right-2 text-ink-400 hover:text-up-red p-0.5 rounded cursor-pointer" title="Reset Stok">✕</button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- MAIN STAGE: TABEL TRANSAKSI KASIR (LELUASA, MODERN & BERSIH) -->
    <div class="flex-1 min-h-[300px] sm:min-h-0 bg-white dark:bg-[#0F172A]/70 backdrop-blur-md border border-ink-200/80 dark:border-white/5 rounded-2xl p-3 sm:p-4 flex flex-col shadow-sm overflow-hidden">
        <!-- Mobile Cart Header: Counter & View Toggle (sm:hidden) -->
        <div class="sm:hidden flex items-center justify-between pb-2.5 mb-2 border-b border-ink-200/70 dark:border-white/10 flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-ink-900 dark:text-white">Keranjang Belanja</span>
                <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-bold text-[10px] tabular-nums border border-up-primary/20">
                    {{ count($cart) }} item
                </span>
            </div>

            <!-- Toggle View Mode: Card vs List -->
            <div class="flex items-center bg-ink-100 dark:bg-white/5 rounded-xl p-0.5 border border-ink-200/80 dark:border-white/10">
                <button
                    type="button"
                    @click="setCartViewMode('card')"
                    :class="cartViewMode === 'card' ? 'bg-white dark:bg-ink-800 text-up-primary shadow-sm font-bold' : 'text-ink-500 hover:text-ink-900 dark:hover:text-white'"
                    class="px-2.5 py-1 rounded-lg text-xs transition flex items-center gap-1 cursor-pointer min-h-[30px]"
                    title="Tampilan Kartu"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Card</span>
                </button>
                <button
                    type="button"
                    @click="setCartViewMode('list')"
                    :class="cartViewMode === 'list' ? 'bg-white dark:bg-ink-800 text-up-primary shadow-sm font-bold' : 'text-ink-500 hover:text-ink-900 dark:hover:text-white'"
                    class="px-2.5 py-1 rounded-lg text-xs transition flex items-center gap-1 cursor-pointer min-h-[30px]"
                    title="Tampilan List Kompak"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>List</span>
                </button>
            </div>
        </div>

        <!-- 1. Mobile Cart: Card View Mode -->
        <div x-show="cartViewMode === 'card'" class="sm:hidden flex-1 overflow-y-auto space-y-2.5 pr-0.5">
            @forelse($cart as $key => $item)
                <div
                    wire:key="cart-mobile-card-{{ $key }}"
                    x-data="{
                        flexOpen: false,
                        flexHarga: '{{ number_format($item['harga'] ?? 0, 0, '', '') }}',
                        saveFlexHarga(k) {
                            const raw = this.flexHarga.replace(/\./g, '').replace(/,/g, '.');
                            const val = Number(raw);
                            if (isNaN(val)) {
                                $dispatch('alert', { type: 'error', message: 'Harga tidak valid' });
                                return;
                            }
                            @if(canSeeField('harga_beli'))
                            if (val < {{ $item['harga_beli'] ?? $item['harga'] ?? 0 }}) {
                                $dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal' });
                                return;
                            }
                            @endif
                            @this.setHargaFleksibel(k, val);
                            this.flexOpen = false;
                        }
                    }"
                    class="p-3.5 rounded-2xl bg-ink-50/80 dark:bg-white/[0.03] border border-ink-200/70 dark:border-white/5 flex flex-col gap-2.5 shadow-sm"
                >
                    <!-- Header Kartu: SKU, Nama, Satuan & Tombol Hapus -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                <span class="font-mono text-[10px] font-bold text-ink-600 dark:text-ink-400 bg-ink-100 dark:bg-white/5 px-1.5 py-0.5 rounded border border-ink-200/60 dark:border-white/5">
                                    {{ $item['sku'] ?? $key }}
                                </span>
                                @if(($item['varian'] ?? 'Standar') !== 'Standar')
                                    <span class="px-1.5 py-0.5 rounded bg-ink-200/70 dark:bg-white/10 text-ink-700 dark:text-ink-300 text-[10px] font-medium">
                                        {{ $item['varian'] }}
                                    </span>
                                @endif
                                @if(isset($item['flex']) && $item['flex'])
                                    <span class="px-1.5 py-0.5 rounded-full bg-up-amber/15 text-up-amber text-[10px] font-bold border border-up-amber/30">
                                        Fleksibel
                                    </span>
                                @endif
                                @if(($item['stok_max'] ?? 1) <= 0)
                                    <span class="px-1.5 py-0.5 rounded-full bg-up-red/15 text-up-red text-[10px] font-bold border border-up-red/30">
                                        Stok habis
                                    </span>
                                @endif
                            </div>
                            <h5 class="text-xs font-bold text-ink-900 dark:text-white leading-snug line-clamp-2">
                                {{ $item['nama'] }}
                            </h5>
                        </div>

                        <!-- Tombol Hapus Touch Friendly (min 44px) -->
                        <button
                            type="button"
                            wire:click="removeFromCart('{{ $key }}')"
                            class="w-11 h-11 rounded-xl text-ink-400 hover:text-up-red hover:bg-up-red/10 flex items-center justify-center transition-colors cursor-pointer active:scale-90 flex-shrink-0 -mr-1 -mt-1"
                            title="Hapus item"
                            aria-label="Hapus item dari keranjang"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <!-- Satuan Selector / Dropdown Interaktif Mobile -->
                    <div class="flex items-center justify-between gap-2 py-2 px-3 rounded-xl bg-white dark:bg-white/[0.02] border border-ink-200/70 dark:border-white/5">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-bold text-ink-700 dark:text-ink-300">Satuan / Kemasan:</span>
                            @if(($item['konversi'] ?? 1) > 1)
                                <span class="text-[10px] text-ink-400 font-medium">1 {{ strtoupper($item['satuan']) }} = {{ (float)$item['konversi'] }} {{ strtoupper($item['satuan_dasar'] ?? 'pcs') }}</span>
                            @endif
                        </div>
                        @if(count($item['daftar_satuan'] ?? []) > 1)
                            <div class="relative flex-1 max-w-[190px]">
                                <select
                                    wire:change="ubahSatuanItem('{{ $key }}', $event.target.value)"
                                    class="w-full text-xs font-black uppercase py-2 pl-3 pr-8 rounded-lg bg-up-primary/10 border-2 border-up-primary/30 text-up-primary dark:text-up-primary hover:bg-up-primary/20 cursor-pointer focus:outline-none focus:ring-1 focus:ring-up-primary appearance-none min-h-[40px]"
                                    title="Ganti Satuan / Kemasan"
                                >
                                    @foreach($item['daftar_satuan'] as $ds)
                                        <option value="{{ $ds['satuan_kode'] }}" {{ ($item['satuan'] ?? 'pcs') === $ds['satuan_kode'] ? 'selected' : '' }} class="bg-white dark:bg-[#0B1120] text-ink-900 dark:text-white font-bold">
                                            {{ strtoupper($ds['satuan_kode']) }} ({{ (float)$ds['faktor_konversi'] }}x)
                                        </option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-up-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        @else
                            <span class="px-2.5 py-1.5 rounded-md bg-ink-100 dark:bg-white/5 text-ink-700 dark:text-ink-300 text-xs font-bold uppercase tracking-wider border border-ink-200/60 dark:border-white/5">
                                {{ strtoupper($item['satuan'] ?? 'pcs') }}
                            </span>
                        @endif
                    </div>

                    <!-- Harga, Stepper Qty & Subtotal -->
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-ink-200/60 dark:border-white/5">
                        <!-- Harga per satuan -->
                        <div class="relative">
                            @if(isset($item['flex']) && $item['flex'])
                                <div class="flex items-center gap-1.5">
                                    <span class="tabular-nums font-bold text-xs text-up-amber">
                                        Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                    </span>
                                    @if($canHargaFleksibel)
                                        <button
                                            type="button"
                                            @click="flexOpen = true"
                                            class="px-2 py-0.5 rounded-md text-[10px] font-bold text-up-amber bg-up-amber/10 border border-up-amber/30 hover:bg-up-amber/20 cursor-pointer"
                                        >
                                            Edit
                                        </button>
                                    @endif
                                </div>

                                <!-- Popover Atur Harga Mobile -->
                                <div
                                    x-show="flexOpen"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    @click.outside="flexOpen = false"
                                    @keydown.escape="flexOpen = false"
                                    class="absolute left-0 bottom-full mb-1 z-30 min-w-[210px] p-3 rounded-2xl glass-panel border border-ink-200/90 dark:border-white/10 shadow-2xl bg-white dark:bg-[#0B1120]"
                                    x-cloak
                                >
                                    <label class="block text-[10px] font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1">Ubah Harga (Rp)</label>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        x-model="flexHarga"
                                        @keydown.enter.prevent="saveFlexHarga('{{ $key }}')"
                                        class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs font-bold tabular-nums text-ink-900 dark:text-white mb-1.5"
                                    />
                                    @cansee('harga_beli')
                                        <p class="text-[9px] text-up-amber font-medium mb-2">Batas Modal: Rp {{ number_format($item['harga_beli'] ?? $item['harga'], 0, ',', '.') }}</p>
                                    @endcansee
                                    <div class="flex gap-1.5">
                                        <button
                                            type="button"
                                            @click="saveFlexHarga('{{ $key }}')"
                                            class="flex-1 py-1.5 rounded-lg bg-up-amber hover:opacity-95 text-ink-950 font-bold text-[11px] cursor-pointer"
                                        >
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            @click="flexOpen = false"
                                            class="px-2.5 py-1.5 rounded-lg bg-ink-200/80 dark:bg-white/10 text-ink-700 dark:text-ink-300 text-[11px] cursor-pointer"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-ink-400">Harga Satuan</span>
                                    @if(($item['diskon'] ?? 0) > 0)
                                        <div class="flex items-center gap-1">
                                            <span class="text-[10px] text-ink-400 line-through tabular-nums">
                                                Rp {{ number_format($item['harga_dasar'], 0, ',', '.') }}
                                            </span>
                                            @if(!empty($item['tier_nama']))
                                                <span class="px-1.5 py-0.2 rounded-full text-[8px] font-bold bg-up-primary/10 text-up-primary border border-up-primary/20">
                                                    {{ $item['tier_nama'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    <span class="tabular-nums font-semibold text-xs text-ink-700 dark:text-ink-300">
                                        Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                    </span>
                                    @if(($item['diskon'] ?? 0) > 0)
                                        <span class="text-[9px] text-emerald-500 font-medium tabular-nums">
                                            Hemat Rp {{ number_format($item['diskon'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Stepper & Subtotal -->
                        <div class="flex items-center gap-2.5">
                            <div class="inline-flex items-center bg-ink-100 dark:bg-white/5 rounded-xl p-0.5 border border-ink-200/80 dark:border-white/10">
                                <button
                                    type="button"
                                    wire:click="updateQty('{{ $key }}', -1)"
                                    class="w-8 h-8 rounded-lg hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                    aria-label="Kurangi"
                                >-</button>
                                <input
                                    type="number"
                                    min="1"
                                    max="{{ $item['stok_max'] ?? 9999 }}"
                                    value="{{ $item['qty'] }}"
                                    wire:change="setQty('{{ $key }}', $event.target.value)"
                                    class="w-10 text-center text-xs font-bold tabular-nums text-ink-900 dark:text-white bg-transparent border-0 p-0 focus:ring-1 focus:ring-up-primary rounded"
                                    aria-label="Jumlah item"
                                >
                                <button
                                    type="button"
                                    wire:click="updateQty('{{ $key }}', 1)"
                                    class="w-8 h-8 rounded-lg hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                    aria-label="Tambah"
                                >+</button>
                            </div>

                            <div class="text-right min-w-[75px]">
                                <span class="text-[10px] text-ink-400 block">Subtotal</span>
                                <span class="text-xs sm:text-sm font-black text-up-mint tabular-nums block">
                                    Rp {{ number_format($item['subtotal'] ?? ($item['harga'] * $item['qty']), 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- SN Control Partial -->
                    @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                </div>
            @empty
                <div class="py-12 text-center text-ink-400">
                    <div class="w-14 h-14 rounded-3xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary mx-auto mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white">Keranjang Masih Kosong</h3>
                    <p class="text-xs text-ink-500 mt-1">Scan barcode atau ketik nama barang di atas.</p>
                </div>
            @endforelse
        </div>

        <!-- 2. Mobile Cart: List View Mode (Kompak & Padat untuk Banyak Item) -->
        <div x-show="cartViewMode === 'list'" class="sm:hidden flex-1 overflow-y-auto divide-y divide-ink-200/60 dark:divide-white/5 pr-0.5">
            @forelse($cart as $key => $item)
                <div
                    wire:key="cart-mobile-list-{{ $key }}"
                    x-data="{
                        flexOpen: false,
                        flexHarga: '{{ number_format($item['harga'] ?? 0, 0, '', '') }}',
                        saveFlexHarga(k) {
                            const raw = this.flexHarga.replace(/\./g, '').replace(/,/g, '.');
                            const val = Number(raw);
                            if (isNaN(val)) {
                                $dispatch('alert', { type: 'error', message: 'Harga tidak valid' });
                                return;
                            }
                            @if(canSeeField('harga_beli'))
                            if (val < {{ $item['harga_beli'] ?? $item['harga'] ?? 0 }}) {
                                $dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal' });
                                return;
                            }
                            @endif
                            @this.setHargaFleksibel(k, val);
                            this.flexOpen = false;
                        }
                    }"
                    class="py-2.5 px-1.5 flex flex-col gap-1.5 transition-colors hover:bg-ink-50/50 dark:hover:bg-white/[0.02]"
                >
                    <!-- Top Row: SKU, Nama (truncate), Satuan & Tombol Hapus -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex items-center gap-1.5 flex-1">
                            <span class="font-mono text-[9px] font-bold text-ink-500 dark:text-ink-400 bg-ink-100 dark:bg-white/5 px-1.5 py-0.5 rounded flex-shrink-0">
                                {{ $item['sku'] ?? $key }}
                            </span>
                            <h5 class="text-xs font-semibold text-ink-900 dark:text-white truncate" title="{{ $item['nama'] }}">
                                {{ $item['nama'] }}
                            </h5>
                        </div>

                        <!-- Dropdown / Badge Satuan Ringkas -->
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @if(count($item['daftar_satuan'] ?? []) > 1)
                                <select
                                    wire:change="ubahSatuanItem('{{ $key }}', $event.target.value)"
                                    class="text-[10px] font-bold uppercase py-1 px-1.5 rounded-lg bg-up-primary/10 border border-up-primary/25 text-up-primary dark:text-up-primary cursor-pointer focus:outline-none min-h-[34px]"
                                    title="Ganti Satuan"
                                >
                                    @foreach($item['daftar_satuan'] as $ds)
                                        <option value="{{ $ds['satuan_kode'] }}" {{ ($item['satuan'] ?? 'pcs') === $ds['satuan_kode'] ? 'selected' : '' }} class="bg-white dark:bg-[#0B1120] text-ink-900 dark:text-white">
                                            {{ strtoupper($ds['satuan_kode']) }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span class="px-1.5 py-0.5 rounded bg-ink-100 dark:bg-white/5 text-ink-600 dark:text-ink-300 text-[10px] font-bold uppercase">
                                    {{ strtoupper($item['satuan'] ?? 'pcs') }}
                                </span>
                            @endif

                            <!-- Tombol Hapus Touch Friendly (min 38-44px) -->
                            <button
                                type="button"
                                wire:click="removeFromCart('{{ $key }}')"
                                class="w-10 h-10 rounded-lg text-ink-400 hover:text-up-red hover:bg-up-red/10 flex items-center justify-center transition-colors cursor-pointer active:scale-90"
                                title="Hapus item"
                                aria-label="Hapus item"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Row: Harga Satuan, Stepper (+ -), Subtotal -->
                    <div class="flex items-center justify-between gap-2">
                        <!-- Harga Satuan / Flex -->
                        <div class="relative min-w-0">
                            @if(isset($item['flex']) && $item['flex'])
                                <div class="flex items-center gap-1">
                                    <span class="tabular-nums font-bold text-[11px] text-up-amber">
                                        Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                    </span>
                                    @if($canHargaFleksibel)
                                        <button
                                            type="button"
                                            @click="flexOpen = true"
                                            class="px-1.5 py-0.5 rounded text-[9px] font-bold text-up-amber bg-up-amber/10 border border-up-amber/30 cursor-pointer"
                                        >
                                            Edit
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div class="flex flex-col">
                                    @if(($item['diskon'] ?? 0) > 0)
                                        <div class="flex items-center gap-1">
                                            <span class="text-[9px] text-ink-400 line-through tabular-nums">
                                                Rp {{ number_format($item['harga_dasar'], 0, ',', '.') }}
                                            </span>
                                            @if(!empty($item['tier_nama']))
                                                <span class="px-1 py-0.2 rounded text-[8px] font-bold bg-up-primary/10 text-up-primary">
                                                    {{ $item['tier_nama'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    <span class="text-[11px] tabular-nums font-medium text-ink-700 dark:text-ink-300">
                                        @ Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <!-- Stepper & Subtotal -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <div class="inline-flex items-center bg-ink-100 dark:bg-white/5 rounded-lg p-0.5 border border-ink-200/80 dark:border-white/10">
                                <button
                                    type="button"
                                    wire:click="updateQty('{{ $key }}', -1)"
                                    class="w-8 h-8 rounded hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                    aria-label="Kurangi"
                                >-</button>
                                <input
                                    type="number"
                                    min="1"
                                    max="{{ $item['stok_max'] ?? 9999 }}"
                                    value="{{ $item['qty'] }}"
                                    wire:change="setQty('{{ $key }}', $event.target.value)"
                                    class="w-10 text-center text-xs font-bold tabular-nums text-ink-900 dark:text-white bg-transparent border-0 p-0 focus:ring-1 focus:ring-up-primary rounded"
                                    aria-label="Jumlah item"
                                >
                                <button
                                    type="button"
                                    wire:click="updateQty('{{ $key }}', 1)"
                                    class="w-8 h-8 rounded hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                    aria-label="Tambah"
                                >+</button>
                            </div>

                            <span class="text-xs font-black text-up-mint tabular-nums min-w-[75px] text-right">
                                Rp {{ number_format($item['subtotal'] ?? ($item['harga'] * $item['qty']), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- SN Control Partial -->
                    @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                </div>
            @empty
                <div class="py-12 text-center text-ink-400">
                    <p class="text-xs font-semibold text-ink-700 dark:text-ink-300">Keranjang masih kosong</p>
                </div>
            @endforelse
        </div>

        <!-- 3. Table Area (Desktop sm:block) -->
        <div class="hidden sm:block flex-1 overflow-x-auto overflow-y-auto pr-0.5">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="sticky top-0 z-10 bg-white dark:bg-[#0F172A] border-b border-ink-200/80 dark:border-white/10">
                    <tr class="text-[11px] font-bold text-ink-500 uppercase tracking-wider">
                        <th class="py-2.5 px-2.5 text-center w-10">#</th>
                        <th class="py-2.5 px-3 w-28 sm:w-32">Kode / SKU</th>
                        <th class="py-2.5 px-3 min-w-[180px]">Nama Barang & Spesifikasi</th>
                        <th class="py-2.5 px-3 text-center w-28 sm:w-36">Satuan</th>
                        <th class="py-2.5 px-3 text-right w-28 sm:w-36">Harga Satuan</th>
                        <th class="py-2.5 px-3 text-center w-28 sm:w-32">Qty</th>
                        <th class="py-2.5 px-3 text-right w-32 sm:w-36">Subtotal</th>
                        <th class="py-2.5 px-2.5 text-center w-12">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                    @forelse($cart as $key => $item)
                        <tr wire:key="cart-row-{{ $key }}" class="hover:bg-ink-50/70 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="py-2.5 px-2.5 text-center font-bold text-ink-400 tabular-nums">
                                {{ $loop->iteration }}
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="font-mono text-[11px] font-semibold text-ink-600 dark:text-ink-400 bg-ink-100 dark:bg-white/5 px-2 py-0.5 rounded-md border border-ink-200/60 dark:border-white/5">
                                    {{ $item['sku'] ?? $key }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h5 class="text-xs sm:text-sm font-semibold text-ink-900 dark:text-white leading-snug">
                                            {{ $item['nama'] }}
                                        </h5>
                                        @if(($item['varian'] ?? 'Standar') !== 'Standar')
                                            <span class="px-2 py-0.5 rounded-md bg-ink-100 dark:bg-white/10 text-ink-700 dark:text-ink-300 text-[10px] font-medium">
                                                {{ $item['varian'] }}
                                            </span>
                                        @endif
                                        @if(isset($item['flex']) && $item['flex'])
                                            <span class="px-2 py-0.5 rounded-full bg-up-amber/15 text-up-amber text-[10px] font-bold border border-up-amber/30">
                                                Fleksibel
                                            </span>
                                        @endif
                                        @if(($item['stok_max'] ?? 1) <= 0)
                                            <span class="px-2 py-0.5 rounded-full bg-up-red/15 text-up-red text-[10px] font-bold border border-up-red/30">
                                                Stok habis
                                            </span>
                                        @endif
                                    </div>

                                    <!-- SN Control Partial -->
                                    @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                                </div>
                            </td>
                            <!-- Kolom Satuan / Kemasan Interaktif Desktop -->
                            <td class="py-2.5 px-3 text-center">
                                @if(count($item['daftar_satuan'] ?? []) > 1)
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <div class="relative w-full min-w-[110px]">
                                            <select
                                                wire:change="ubahSatuanItem('{{ $key }}', $event.target.value)"
                                                class="w-full text-xs font-bold uppercase py-1.5 pl-2.5 pr-7 rounded-lg bg-up-primary/10 border border-up-primary/30 text-up-primary hover:bg-up-primary/20 cursor-pointer focus:outline-none focus:ring-1 focus:ring-up-primary appearance-none transition shadow-sm text-center"
                                                title="Ganti Satuan / Kemasan"
                                            >
                                                @foreach($item['daftar_satuan'] as $ds)
                                                    <option value="{{ $ds['satuan_kode'] }}" {{ ($item['satuan'] ?? 'pcs') === $ds['satuan_kode'] ? 'selected' : '' }} class="bg-white dark:bg-[#0B1120] text-ink-900 dark:text-white font-bold">
                                                        {{ strtoupper($ds['satuan_kode']) }} ({{ (float)$ds['faktor_konversi'] }}x)
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-up-primary">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                        @if(($item['konversi'] ?? 1) > 1)
                                            <span class="text-[9px] text-ink-400 font-medium whitespace-nowrap">
                                                1 {{ strtoupper($item['satuan']) }} = {{ (float)$item['konversi'] }} {{ strtoupper($item['satuan_dasar'] ?? 'pcs') }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-md bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/5 text-ink-600 dark:text-ink-400 text-xs font-bold uppercase tracking-wider">
                                        {{ strtoupper($item['satuan'] ?? 'pcs') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                @if(isset($item['flex']) && $item['flex'])
                                    <div
                                        class="relative inline-flex items-center gap-1.5"
                                        x-data="{
                                            flexOpen: false,
                                            flexHarga: '{{ number_format($item['harga'] ?? 0, 0, '', '') }}',
                                            saveFlexHarga(k) {
                                                const raw = this.flexHarga.replace(/\./g, '').replace(/,/g, '.');
                                                const val = Number(raw);
                                                if (isNaN(val)) {
                                                    $dispatch('alert', { type: 'error', message: 'Harga tidak valid' });
                                                    return;
                                                }
                                                @if(canSeeField('harga_beli'))
                                                if (val < {{ $item['harga_beli'] ?? $item['harga'] ?? 0 }}) {
                                                    $dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal' });
                                                    return;
                                                }
                                                @endif
                                                @this.setHargaFleksibel(k, val);
                                                this.flexOpen = false;
                                            }
                                        }"
                                    >
                                        <span class="font-bold tabular-nums text-xs text-up-amber">
                                            Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                        </span>
                                        @if($canHargaFleksibel)
                                            <button
                                                type="button"
                                                @click="flexOpen = true"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold text-up-amber bg-up-amber/10 border border-up-amber/30 hover:bg-up-amber/20 cursor-pointer"
                                            >
                                                Ubah
                                            </button>
                                        @endif

                                        <!-- Flex Price Popover -->
                                        <div
                                            x-show="flexOpen"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-out duration-100"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            @click.outside="flexOpen = false"
                                            @keydown.escape="flexOpen = false"
                                            class="absolute right-0 bottom-full mb-2 z-30 min-w-[210px] p-3 rounded-2xl bg-white dark:bg-[#0B1120] border border-ink-200/90 dark:border-white/10 shadow-2xl text-left origin-bottom-right"
                                            x-cloak
                                        >
                                            <label class="block text-[10px] font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1">Ubah Harga</label>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                x-model="flexHarga"
                                                class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs font-bold tabular-nums mb-1.5"
                                            />
                                            <div class="flex gap-1.5">
                                                <button type="button" @click="saveFlexHarga('{{ $key }}')" class="flex-1 py-1 rounded-lg bg-up-amber text-ink-950 font-bold text-[11px]">Simpan</button>
                                                <button type="button" @click="flexOpen = false" class="px-2 py-1 rounded-lg bg-ink-200 dark:bg-white/10 text-xs">Batal</button>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex flex-col items-end">
                                        @if(($item['diskon'] ?? 0) > 0)
                                            <div class="flex items-center gap-1">
                                                <span class="text-[10px] text-ink-400 line-through tabular-nums">
                                                    Rp {{ number_format($item['harga_dasar'], 0, ',', '.') }}
                                                </span>
                                                @if(!empty($item['tier_nama']))
                                                    <span class="px-1.5 py-0.2 rounded-full text-[8px] font-bold bg-up-primary/10 text-up-primary border border-up-primary/20">
                                                        {{ $item['tier_nama'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        <span class="font-semibold tabular-nums text-xs text-ink-700 dark:text-ink-300">
                                            Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                        </span>
                                        @if(($item['diskon'] ?? 0) > 0)
                                            <span class="text-[9px] text-emerald-500 font-medium tabular-nums">
                                                Hemat Rp {{ number_format($item['diskon'], 0, ',', '.') }}
                                            </span>
                                        @endif
                                        @if(($item['konversi'] ?? 1) > 1)
                                            <span class="text-[9px] text-ink-400 tabular-nums">
                                                isi {{ (float)$item['konversi'] }} unit
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <div class="inline-flex items-center bg-ink-100 dark:bg-white/5 rounded-xl p-0.5 border border-ink-200/80 dark:border-white/10">
                                    <button
                                        type="button"
                                        wire:click="updateQty('{{ $key }}', -1)"
                                        class="w-7 h-7 rounded-lg hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                        aria-label="Kurangi"
                                    >-</button>
                                    <input
                                        type="number"
                                        min="1"
                                        max="{{ $item['stok_max'] ?? 9999 }}"
                                        value="{{ $item['qty'] }}"
                                        wire:change="setQty('{{ $key }}', $event.target.value)"
                                        class="w-10 text-center text-xs font-bold tabular-nums text-ink-900 dark:text-white bg-transparent border-0 p-0 focus:ring-1 focus:ring-up-primary rounded"
                                        aria-label="Jumlah item"
                                    >
                                    <button
                                        type="button"
                                        wire:click="updateQty('{{ $key }}', 1)"
                                        class="w-7 h-7 rounded-lg hover:bg-white dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer active:scale-90"
                                        aria-label="Tambah"
                                    >+</button>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <span class="text-xs sm:text-sm font-bold text-up-mint tabular-nums">
                                    Rp {{ number_format($item['subtotal'] ?? ($item['harga'] * $item['qty']), 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-2.5 px-2.5 text-center">
                                <button
                                    type="button"
                                    wire:click="removeFromCart('{{ $key }}')"
                                    class="w-8 h-8 rounded-xl text-ink-400 hover:text-up-red hover:bg-up-red/10 flex items-center justify-center transition-colors cursor-pointer active:scale-90 mx-auto"
                                    title="Hapus baris"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-16 sm:py-20">
                                <div class="max-w-md mx-auto flex flex-col items-center">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-3xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary mb-3">
                                        <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-sm sm:text-base font-bold text-ink-900 dark:text-white">Kasir Siap Melayani Transaksi</h3>
                                    <p class="text-xs text-ink-500 mt-1">Scan barcode barang dengan scanner atau tekan F2 untuk mencari produk.</p>
                                    <div class="flex items-center gap-2 mt-4 flex-wrap justify-center text-[11px] text-ink-400 font-mono">
                                        <span class="px-2.5 py-1 rounded-lg bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/5"><kbd class="text-up-primary font-bold">F2</kbd> Cari/Scan</span>
                                        <span class="px-2.5 py-1 rounded-lg bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/5"><kbd class="text-up-mint font-bold">F4</kbd> Bayar</span>
                                        <span class="px-2.5 py-1 rounded-lg bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/5"><kbd class="text-up-amber font-bold">F6</kbd> Tahan</span>
                                        <span class="px-2.5 py-1 rounded-lg bg-ink-100 dark:bg-white/5 border border-ink-200/60 dark:border-white/5"><kbd class="text-up-red font-bold">ESC</kbd> Batal</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- MEMBER LOYALTY & DISKON BAR -->
        @if($customer || $diskonPartai > 0 || $diskonPoin > 0)
            <div class="mt-2.5 p-2 rounded-xl bg-ink-50/70 dark:bg-white/[0.02] border border-ink-200/60 dark:border-white/5 flex flex-wrap items-center justify-between gap-2 text-xs flex-shrink-0">
                <!-- Sisi Kiri: Info Mitra & Partai -->
                <div class="flex items-center gap-2 flex-wrap">
                    @if($diskonPartai > 0)
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-up-mint/15 text-up-mint border border-up-mint/30 font-bold text-[11px]">
                            <span>🎉 {{ $alasanPartai }}</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                        </div>
                    @elseif($customer && app(\App\Modules\Crm\Services\KonfigurasiService::class)->hargaPartaiAktif())
                        @php
                            $cfgPartai = app(\App\Modules\Crm\Services\KonfigurasiService::class);
                            $syaratList = [];
                            if ($cfgPartai->hargaPartaiMinQty() > 0 && $cfgPartai->hargaPartaiQtyDiskonPersen() > 0) {
                                $syaratList[] = 'min. ' . $cfgPartai->hargaPartaiMinQty() . ' pcs (' . $cfgPartai->hargaPartaiQtyDiskonPersen() . '%)';
                            }
                            if ($cfgPartai->hargaPartaiMinNominal() > 0 && $cfgPartai->hargaPartaiNominalDiskonPersen() > 0) {
                                $syaratList[] = 'min. Rp ' . number_format($cfgPartai->hargaPartaiMinNominal(), 0, ',', '.') . ' (' . $cfgPartai->hargaPartaiNominalDiskonPersen() . '%)';
                            }
                        @endphp
                        @if(count($syaratList) > 0)
                            <div class="text-[11px] text-ink-400">
                                <span>📦 Syarat Diskon Partai Member: {{ implode(' atau ', $syaratList) }}</span>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Sisi Kanan: Penukaran Poin Member -->
                @if($customer && ($customer->poin_loyalty > 0 || $poinDitukar > 0))
                    @php
                        $ratePoin = app(\App\Modules\Crm\Services\PelangganService::class)->nilaiTukarPoinPelanggan($customer);
                    @endphp
                    <div class="flex items-center gap-2 flex-wrap ml-auto">
                        <span class="text-[11px] text-ink-500">Saldo: <strong class="text-ink-800 dark:text-white font-bold">{{ number_format($customer->poin_loyalty ?? 0) }} pts</strong> (Rp {{ number_format(($customer->poin_loyalty ?? 0) * $ratePoin, 0, ',', '.') }} · @ Rp {{ number_format($ratePoin, 0, ',', '.') }}/pt)</span>
                        <div class="inline-flex items-center gap-1 bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 rounded-lg p-0.5">
                            <span class="text-[10px] text-ink-400 pl-1.5 font-bold">Tukar Poin:</span>
                            <input
                                type="number"
                                wire:model.live.debounce.300ms="poinDitukar"
                                min="0"
                                max="{{ $customer->poin_loyalty ?? 0 }}"
                                class="w-14 px-1 py-0.5 text-xs font-bold text-center bg-transparent border-0 focus:outline-none tabular-nums text-ink-900 dark:text-white"
                                placeholder="0"
                            />
                            <span class="text-[10px] text-ink-400 pr-1">pts</span>
                            @if($poinDitukar < ($customer->poin_loyalty ?? 0))
                                <button
                                    type="button"
                                    wire:click="tukarSemuaPoin"
                                    class="px-2 py-0.5 text-[10px] font-bold rounded bg-up-primary/10 hover:bg-up-primary/20 text-up-primary cursor-pointer transition"
                                >
                                    Semua
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="batalTukarPoin"
                                    class="px-2 py-0.5 text-[10px] font-bold rounded bg-ink-100 hover:bg-ink-200 dark:bg-white/10 text-ink-600 dark:text-ink-300 cursor-pointer transition"
                                >
                                    Reset
                                </button>
                            @endif
                        </div>
                        @if($diskonPoin > 0)
                            <span class="text-xs font-bold text-emerald-500 tabular-nums">
                                -Rp {{ number_format($diskonPoin, 0, ',', '.') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- BOTTOM CONTROL CENTER: Status Sesi Kas, Ditahan & Action Bayar -->
        <div class="border-t border-ink-200/70 dark:border-white/10 pt-3 mt-2.5 flex-shrink-0 flex items-center justify-between gap-3 flex-wrap">
            <!-- Left Info & Sesi Kas -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Sesi Kas Indicator -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    @if($kasAktif)
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-up-mint/15 text-up-mint border border-up-mint/30 font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                            <span>Kas Aktif</span>
                        </div>
                        <button wire:click="bukaMutasiKasModal('keluar')" class="text-xs font-bold text-up-accent hover:underline cursor-pointer">± Mutasi</button>
                        <span class="text-ink-300 dark:text-white/20">|</span>
                        <button wire:click="tutupKasModal" class="text-xs font-bold text-up-amber hover:text-up-red cursor-pointer">Tutup Kas</button>
                    @elseif($kasPendingApproval)
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-up-amber/15 text-up-amber border border-up-amber/30 font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-amber animate-pulse"></span>
                            <span>Review Tutup Kas</span>
                        </div>
                    @else
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-up-red/15 text-up-red border border-up-red/30 font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-red"></span>
                            <span>Kas Belum Dibuka</span>
                        </div>
                        <button wire:click="bukaKasModal" class="text-xs font-bold text-up-primary hover:underline cursor-pointer">Buka Kas</button>
                    @endif
                </div>

                <span class="hidden sm:inline text-ink-300 dark:text-white/20">|</span>

                <!-- Transaksi Ditahan Modal Trigger -->
                <div>
                    <button
                        type="button"
                        wire:click="bukaDitahanPanel"
                        class="text-xs font-semibold text-ink-600 dark:text-ink-400 hover:text-up-amber dark:hover:text-up-amber flex items-center gap-1.5 cursor-pointer py-1 transition"
                        title="Buka Daftar Transaksi Ditahan (F6)"
                    >
                        <span>Ditahan</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold {{ $ditahanList->isNotEmpty() ? 'bg-up-amber/20 text-up-amber border border-up-amber/30' : 'bg-ink-100 dark:bg-white/10 text-ink-500' }}">
                            {{ $ditahanList->count() }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Right Breakdown & Action Buttons -->
            <div class="flex items-center gap-3 sm:gap-4 flex-wrap ml-auto w-full sm:w-auto justify-between sm:justify-end">
                <!-- Mini Breakdown -->
                <div class="flex items-center gap-2 sm:gap-3 text-xs text-ink-600 dark:text-ink-400 flex-wrap">
                    <div>
                        <span>Subtotal:</span>
                        <span class="font-semibold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($diskonTotal > 0)
                        <button
                            type="button"
                            wire:click="openDiskonModal"
                            class="text-up-amber font-semibold hover:underline flex items-center gap-1 cursor-pointer"
                            title="Klik untuk ubah Diskon Langsung"
                        >
                            <span>Diskon:</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="openDiskonModal"
                            class="px-2 py-0.5 rounded-md bg-up-amber/10 hover:bg-up-amber/20 text-up-amber border border-up-amber/25 font-bold text-[11px] cursor-pointer transition active:scale-95"
                            title="Beri Diskon Langsung pada transaksi"
                        >
                            + Diskon Langsung
                        </button>
                    @endif
                    @if($diskonPartai > 0)
                        <div class="text-up-mint font-semibold text-[11px]">
                            <span>Partai:</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($diskonPoin > 0)
                        <div class="text-emerald-500 font-semibold text-[11px]">
                            <span>Poin:</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonPoin, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($pajakNominal > 0)
                        <div>
                            <span>PPN:</span>
                            <span class="font-semibold text-ink-900 dark:text-white tabular-nums">+Rp {{ number_format($pajakNominal, 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>

                <!-- Action Buttons: Batal, Tahan & BAYAR (Responsif Mobile & Desktop) -->
                <div class="w-full sm:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5 sm:gap-2">
                    <!-- Baris Atas Mobile: Batal & Tahan -->
                    <div class="grid grid-cols-2 sm:flex sm:items-center gap-1.5 sm:gap-2">
                        <button
                            type="button"
                            wire:click="clearCart"
                            class="w-full sm:w-auto px-3 sm:px-3.5 py-2 rounded-xl bg-ink-100 hover:bg-up-red/15 dark:bg-white/5 dark:hover:bg-up-red/20 text-ink-600 hover:text-up-red dark:text-ink-400 dark:hover:text-up-red font-bold text-xs transition border border-ink-200/80 dark:border-white/5 cursor-pointer active:scale-95 min-h-[44px] flex items-center justify-center gap-1"
                        >
                            <span>Batal</span>
                            <span class="text-[10px] font-mono text-ink-400 dark:text-ink-500">(ESC)</span>
                        </button>

                        <button
                            type="button"
                            wire:click="tahanTransaksi"
                            class="w-full sm:w-auto px-3 sm:px-3.5 py-2 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs transition border border-ink-200/80 dark:border-white/5 cursor-pointer active:scale-95 min-h-[44px] flex items-center justify-center gap-1"
                        >
                            <span>Tahan</span>
                            <span class="text-[10px] font-mono text-ink-400 dark:text-ink-500">(F6)</span>
                        </button>
                    </div>

                    <!-- Baris Bawah Mobile / Tombol Utama: BAYAR Penuh & Lega -->
                    <button
                        type="button"
                        wire:click="openPaymentModal"
                        class="w-full sm:w-auto px-4 sm:px-5 py-2.5 sm:py-2 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-sm shadow-md shadow-up-mint/25 transition active:scale-95 cursor-pointer min-h-[44px] flex items-center justify-between sm:justify-center gap-2"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-ink-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>BAYAR (F4)</span>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-ink-950/20 text-ink-950 text-xs font-mono font-black tabular-nums whitespace-nowrap">
                            Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS SECTION -->
    @include('modules.pos.livewire.modals.payment-modal')
    @include('modules.pos.livewire.modals.diskon-modal')
    @include('partials.thermal-receipt-modal')
    @include('modules.pos.livewire.modals.riwayat-transaksi-modal')
    @include('modules.pos.livewire.modals.pelanggan-baru-modal')
    @include('modules.pos.livewire.modals.kas-modal')
    @include('modules.pos.livewire.modals.mutasi-kas-modal')
    @include('modules.pos.livewire.modals.bayar-servis-modal')
    @include('modules.pos.livewire.modals.pesanan-online-modal')
    @include('modules.pos.livewire.modals.transaksi-ditahan-modal')
    @include('modules.pos.livewire.modals.void-transaksi-modal')
    @include('partials.riwayat-modal')
</div>
