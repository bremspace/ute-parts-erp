<div
    class="flex flex-col gap-2.5 sm:gap-3.5 min-w-0 flex-1 min-h-[calc(100dvh-12rem)] md:min-h-0 md:h-[calc(100dvh-6.5rem)] pb-4 md:pb-0"
    x-data="{
        flexSet: {},
        searchOpen: false,
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

                <!-- Floating Search Results Dropdown (HANYA MUNCUL SAAT PENCARIAN) -->
                <div
                    x-show="searchOpen && $wire.search.length > 0"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1 scale-[0.99]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-1 scale-[0.99]"
                    class="absolute left-0 right-0 top-full mt-2 z-50 bg-white dark:bg-[#0B1120] border border-ink-200/90 dark:border-white/15 rounded-2xl shadow-2xl overflow-hidden max-h-[65vh] flex flex-col"
                    x-cloak
                >
                    <div class="px-4 py-2.5 bg-ink-50 dark:bg-white/[0.04] border-b border-ink-200/80 dark:border-white/10 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-ink-900 dark:text-white">Hasil Pencarian Produk</span>
                            <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-bold text-[10px] tabular-nums">
                                {{ count($products) }} item
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="searchOpen = false"
                            class="text-xs text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer px-1 py-0.5 rounded hover:bg-ink-200 dark:hover:bg-white/10"
                        >
                            ✕ Tutup (ESC)
                        </button>
                    </div>

                    <div class="overflow-y-auto divide-y divide-ink-200/60 dark:divide-white/5 p-1">
                        @forelse($products as $prod)
                            @php
                                $stokTotal = $stokPerProduk[$prod->id] ?? (\App\Modules\Pos\Livewire\PosKasir::STOK_TANPA_GUDANG);
                                $stokKosong = $stokTotal <= 0;
                                $gudangDipilih = (bool) $selectedGudangId;
                                $alasanTidakBisaDipilih = ! $gudangDipilih
                                    ? 'Pilih gudang terlebih dahulu untuk melihat stok & memilih produk ini.'
                                    : "Stok {$prod->nama} habis di gudang ini — tidak bisa dipilih. Pilih gudang lain yang punya stok.";
                                $pricing = app(\App\Modules\Pos\Services\PricingService::class)->resolve($prod, $customer);
                            @endphp
                            <div
                                wire:key="search-product-{{ $prod->id }}"
                                @if($stokKosong)
                                    role="button"
                                    aria-disabled="true"
                                    title="{{ $alasanTidakBisaDipilih }}"
                                    class="p-3 rounded-xl flex items-center justify-between gap-3 opacity-55 grayscale cursor-not-allowed select-none bg-ink-50/50 dark:bg-white/[0.01]"
                                @else
                                    role="button"
                                    wire:click="addToCart({{ $prod->id }})"
                                    @click="searchOpen = false"
                                    title="{{ $prod->nama }} — stok {{ $stokTotal }} unit. Klik untuk menambah."
                                    class="p-3 rounded-xl flex items-center justify-between gap-3 cursor-pointer hover:bg-ink-100/80 dark:hover:bg-white/[0.06] transition group select-none active:scale-[0.99]"
                                @endif
                            >
                                <div class="min-w-0 flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 flex-shrink-0 flex items-center justify-center font-bold text-ink-400 text-[10px]">
                                        @if($prod->thumbnail_url ?: $prod->gambar)
                                            <img src="{{ $prod->thumbnail_url ?: $prod->gambar }}" alt="{{ $prod->nama }}" class="w-full h-full object-cover rounded-xl" onerror="this.style.display='none';">
                                        @else
                                            UTE
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-[11px] font-bold text-ink-500">{{ $prod->sku }}</span>
                                            <span class="text-[10px] uppercase font-semibold text-ink-400">
                                                {{ $prod->kategoriRelasi?->nama ?? $prod->kategori ?? 'Sparepart' }}
                                            </span>
                                            @if($prod->tipeHps->count() > 0)
                                                <span class="text-[10px] text-up-primary font-medium">
                                                    📱 {{ $prod->tipeHps->pluck('model')->take(2)->join(', ') }}
                                                </span>
                                            @endif
                                        </div>
                                        <h4 class="font-semibold text-xs sm:text-sm text-ink-900 dark:text-white truncate group-hover:text-up-primary transition-colors">
                                            {{ $prod->nama }}
                                        </h4>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <!-- Stock -->
                                    <div class="text-right">
                                        @if($stokKosong)
                                            <x-prism.status-pill status="batal">
                                                {{ $gudangDipilih ? 'Stok kosong' : 'Pilih gudang dulu' }}
                                            </x-prism.status-pill>
                                            <span class="hidden">{{ $alasanTidakBisaDipilih }}</span>
                                        @else
                                            <x-prism.stock-gauge :stok="$stokTotal" :min="5" />
                                        @endif
                                    </div>

                                    <!-- Price & Action -->
                                    <div class="text-right min-w-[90px]">
                                        <span class="text-xs sm:text-sm font-bold text-up-mint tabular-nums block">
                                            Rp {{ number_format($pricing['harga'], 0, ',', '.') }}
                                        </span>
                                        @if(! $stokKosong)
                                            <span class="text-[10px] font-bold text-up-primary group-hover:underline">
                                                + Tambah
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-ink-400 text-xs">
                                <p>Tidak ada produk ditemukan untuk pencarian "<span class="text-ink-900 dark:text-white font-semibold">{{ $search }}</span>"</p>
                            </div>
                        @endforelse

                        @if($adaLebihBanyak)
                            <div class="p-2 text-center">
                                <button
                                    type="button"
                                    wire:click="muatLebihBanyak"
                                    class="text-xs font-bold text-up-primary hover:underline cursor-pointer"
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
                    x-show="crmOpen && $wire.pelangganSearch.length >= 1"
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
                            Pilih Konsumen Terdaftar
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
            <div class="px-3.5 py-1.5 rounded-xl bg-ink-900 dark:bg-black/80 border border-ink-700/80 dark:border-white/10 flex flex-col items-end min-w-[150px] sm:min-w-[180px] min-h-[44px] justify-center flex-shrink-0 shadow-inner">
                <span class="text-[9px] uppercase font-bold text-ink-400 tracking-wider">TOTAL TAGIHAN</span>
                <span class="text-xl sm:text-2xl font-black font-mono text-up-mint tabular-nums leading-tight">
                    Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                </span>
            </div>
        </div>

        <!-- BARIS 2: SHORTCUT AKSI KASIR (RETUR, RIWAYAT, ONLINE, SERVIS) & DATA KONSUMEN TERPILIH -->
        <div class="flex items-center justify-between gap-2.5 pt-2 border-t border-ink-200/50 dark:border-white/5 flex-wrap">
            <!-- Left: Shortcut Actions -->
            <div class="flex items-center gap-2 flex-wrap">
                <a
                    href="/app/pos/retur"
                    class="px-3 py-1.5 rounded-xl border border-up-red/30 bg-up-red/10 hover:bg-up-red/20 text-xs font-bold text-up-red transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98]"
                    title="Buka Manajemen Retur Konsumen"
                >
                    <svg class="w-4 h-4 text-up-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                    <span>Retur Konsumen</span>
                </a>

                <button
                    type="button"
                    wire:click="bukaRiwayatTransaksi"
                    class="px-3 py-1.5 rounded-xl border border-ink-200/80 dark:border-white/10 bg-black/5 dark:bg-white/5 hover:bg-black/10 text-xs font-bold text-ink-700 dark:text-ink-200 transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98]"
                    title="Riwayat Transaksi Kasir Hari Ini"
                >
                    <svg class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Riwayat Transaksi</span>
                </button>

                <button
                    type="button"
                    wire:click="bukaPesananOnlineModal"
                    class="px-3 py-1.5 rounded-xl border border-up-amber/30 bg-up-amber/10 hover:bg-up-amber/20 text-xs font-bold text-up-amber transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98]"
                    title="Pesanan Online Pickup Cabang"
                >
                    <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>Pesanan Online</span>
                    @if($jumlahPesananOnline > 0)
                        <span class="min-w-[18px] h-4.5 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums">{{ $jumlahPesananOnline }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="bukaBayarServisModal"
                    class="px-3 py-1.5 rounded-xl border border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/20 text-xs font-bold text-blue-500 dark:text-blue-400 transition flex items-center gap-1.5 cursor-pointer min-h-[36px] active:scale-[0.98]"
                    title="Bayar Tiket Servis di Kasir"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                    <span>Bayar Servis</span>
                </button>
            </div>

            <!-- Right: Data Konsumen Terpilih (atau Pelanggan Umum) + Helper Key -->
            <div class="flex items-center gap-3 flex-wrap ml-auto">
                <!-- Status & Info Konsumen Terpilih -->
                <div class="flex items-center gap-1.5 text-xs">
                    @if($customer)
                        <div class="px-3 py-1.5 rounded-xl bg-up-primary/10 border border-up-primary/30 text-ink-900 dark:text-white font-semibold flex items-center gap-2">
                            <span class="text-up-primary font-bold">👤 {{ $customer->nama }}</span>
                            <span class="px-2 py-0.5 rounded-md bg-up-primary text-white text-[10px] font-black uppercase">
                                {{ $customer->tierMembership?->nama ?? 'Retail' }}
                            </span>
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
                        <span>Brand</span>
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
                        <span>Hanya Ready Stok</span>
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
                    <select wire:model.live="filterKategoriId" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Kategori</option>
                        @foreach($kategoriList as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->nama }}</option>
                            @foreach($parent->children as $child)
                                <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                            @endforeach
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['brand'])
                    <select wire:model.live="filterBrandId" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Brand</option>
                        @foreach($brandList as $b)
                            <option value="{{ $b->id }}">{{ $b->nama }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['kualitas'])
                    <select wire:model.live="filterKualitasId" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Kualitas</option>
                        @foreach($kualitasList as $k)
                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['tipe_hp'])
                    <select wire:model.live="filterTipeHpId" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Tipe HP</option>
                        @foreach($tipeHpList as $th)
                            <option value="{{ $th->id }}">{{ $th->merk }} - {{ $th->model }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['brand_kompatibel'])
                    <select wire:model.live="filterBrandKompatibel" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Brand Kompatibel</option>
                        @foreach($brandKompatibelList as $bk)
                            <option value="{{ $bk }}">{{ $bk }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['model_kompatibel'])
                    <select wire:model.live="filterModelKompatibel" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Model Kompatibel</option>
                        @foreach($modelKompatibelList as $mk)
                            <option value="{{ $mk }}">{{ $mk }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['kondisi'])
                    <select wire:model.live="filterKondisi" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Kondisi</option>
                        @foreach($kondisiList as $kd)
                            <option value="{{ is_array($kd) ? $kd['kode'] : $kd }}">{{ is_array($kd) ? $kd['nama'] : ucfirst($kd) }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['satuan'])
                    <select wire:model.live="filterSatuan" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Satuan</option>
                        @foreach($satuanList as $su)
                            <option value="{{ is_object($su) ? $su->kode : $su }}">{{ is_object($su) ? ($su->nama . ' (' . $su->kode . ')') : strtoupper($su) }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['abc_class'])
                    <select wire:model.live="filterAbcClass" class="px-2.5 py-1.5 rounded-xl glass-input text-xs bg-ink-900 text-white min-h-[34px]">
                        <option value="">Semua Kelas ABC</option>
                        <option value="A">Kelas A (Fast Moving)</option>
                        <option value="B">Kelas B (Medium Moving)</option>
                        <option value="C">Kelas C (Slow Moving)</option>
                    </select>
                @endif
                @if($visibleFilters['stok'])
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 text-xs cursor-pointer min-h-[34px]">
                        <input type="checkbox" wire:model.live="filterHanyaReadyStok" class="rounded text-up-mint">
                        <span>Hanya Ready Stok</span>
                    </label>
                @endif

                @if($filterKategoriId || $filterBrandId || $filterKualitasId || $filterTipeHpId || $filterBrandKompatibel || $filterModelKompatibel || $filterKondisi || $filterSatuan || $filterAbcClass || $filterHanyaReadyStok)
                    <button
                        type="button"
                        wire:click="resetFilterValues"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-red hover:bg-up-red/10 transition cursor-pointer"
                        title="Reset nilai filter aktif kembali ke default"
                    >
                        ✕ Reset Filter Aktif
                    </button>
                @endif
            </div>
        @endif
    </div>

    <!-- MAIN STAGE: TABEL TRANSAKSI KASIR (LELUASA, MODERN & BERSIH) -->
    <div class="flex-1 min-h-[300px] sm:min-h-0 bg-white dark:bg-[#0F172A]/70 backdrop-blur-md border border-ink-200/80 dark:border-white/5 rounded-2xl p-3 sm:p-4 flex flex-col shadow-sm overflow-hidden">
        <!-- Table Area -->
        <div class="flex-1 overflow-x-auto overflow-y-auto pr-0.5">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="sticky top-0 z-10 bg-white dark:bg-[#0F172A] border-b border-ink-200/80 dark:border-white/10">
                    <tr class="text-[11px] font-bold text-ink-500 uppercase tracking-wider">
                        <th class="py-2.5 px-2.5 text-center w-10">#</th>
                        <th class="py-2.5 px-3 w-28 sm:w-32">Kode / SKU</th>
                        <th class="py-2.5 px-3 min-w-[180px]">Nama Barang & Spesifikasi</th>
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
                                            @click.outside="flexOpen = false"
                                            class="absolute right-0 bottom-full mb-2 z-30 min-w-[210px] p-3 rounded-2xl bg-white dark:bg-[#0B1120] border border-ink-200/90 dark:border-white/10 shadow-2xl text-left"
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
                                    <span class="font-semibold tabular-nums text-xs text-ink-700 dark:text-ink-300">
                                        Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                    </span>
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
                                    <span class="w-8 text-center text-xs font-bold tabular-nums text-ink-900 dark:text-white">{{ $item['qty'] }}</span>
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
                            <td colspan="7" class="text-center py-16 sm:py-20">
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

        <!-- BOTTOM CONTROL CENTER: Status Sesi Kas, Ditahan & Action Bayar -->
        <div class="border-t border-ink-200/70 dark:border-white/10 pt-3 mt-2.5 flex-shrink-0 flex items-center justify-between gap-3 flex-wrap">
            <!-- Left Info & Sesi Kas -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Sesi Kas Indicator -->
                <div class="flex items-center gap-2 text-xs">
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

                <span class="text-ink-300 dark:text-white/20">|</span>

                <!-- Transaksi Ditahan Dropdown -->
                <div class="relative" x-data="{ openDitahan: false }">
                    <button
                        type="button"
                        @click="openDitahan = !openDitahan"
                        class="text-xs font-semibold text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white flex items-center gap-1 cursor-pointer"
                    >
                        <span>Ditahan ({{ $ditahanList->count() }})</span>
                        <span class="text-[10px]">▼</span>
                    </button>

                    <div
                        x-show="openDitahan && {{ $ditahanList->isNotEmpty() ? 'true' : 'false' }}"
                        @click.outside="openDitahan = false"
                        class="absolute left-0 bottom-full mb-2 z-40 w-72 p-2 bg-white dark:bg-[#0B1120] border border-ink-200/90 dark:border-white/10 rounded-2xl shadow-2xl space-y-1.5 max-h-48 overflow-y-auto"
                        x-cloak
                    >
                        @foreach($ditahanList as $dt)
                            <div class="flex items-center justify-between gap-2 p-2 rounded-xl bg-ink-50 dark:bg-white/[0.03] text-xs">
                                <div class="min-w-0">
                                    <span class="font-mono font-bold text-ink-900 dark:text-white truncate block">{{ $dt->no_transaksi }}</span>
                                    <span class="text-[10px] text-ink-500">{{ $dt->items->count() }} item</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button wire:click="resumeTransaksi({{ $dt->id }})" @click="openDitahan = false" class="px-2 py-1 rounded-lg bg-up-primary text-white text-[10px] font-bold">Lanjut</button>
                                    <button wire:click="hapusDitahan({{ $dt->id }})" class="p-1 text-ink-400 hover:text-up-red">✕</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Breakdown & Action Buttons -->
            <div class="flex items-center gap-3 sm:gap-4 flex-wrap ml-auto">
                <!-- Mini Breakdown -->
                <div class="flex items-center gap-2.5 sm:gap-3 text-xs text-ink-600 dark:text-ink-400">
                    <div>
                        <span>Subtotal:</span>
                        <span class="font-semibold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($diskonNominal > 0 || $diskonPersen > 0)
                        <div class="text-up-amber font-semibold">
                            <span>Diskon:</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($pajakNominal > 0)
                        <div>
                            <span>PPN:</span>
                            <span class="font-semibold text-ink-900 dark:text-white tabular-nums">+Rp {{ number_format($pajakNominal, 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>

                <!-- Action Buttons: Batal, Tahan & BAYAR -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="clearCart"
                        class="px-3.5 py-2 rounded-xl bg-ink-100 hover:bg-up-red/15 dark:bg-white/5 dark:hover:bg-up-red/20 text-ink-600 hover:text-up-red dark:text-ink-400 dark:hover:text-up-red font-bold text-xs transition border border-ink-200/80 dark:border-white/5 cursor-pointer active:scale-95 min-h-[44px]"
                    >
                        Batal (ESC)
                    </button>

                    <button
                        type="button"
                        wire:click="tahanTransaksi"
                        class="px-3.5 py-2 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs transition border border-ink-200/80 dark:border-white/5 cursor-pointer active:scale-95 min-h-[44px]"
                    >
                        Tahan (F6)
                    </button>

                    <button
                        type="button"
                        wire:click="openPaymentModal"
                        class="px-5 py-2 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-sm shadow-md shadow-up-mint/25 transition active:scale-95 cursor-pointer min-h-[44px] flex items-center gap-2"
                    >
                        <span>BAYAR (F4)</span>
                        <span class="px-2 py-0.5 rounded-md bg-ink-950/20 text-ink-950 text-xs font-mono font-black tabular-nums">
                            Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS SECTION -->
    @include('modules.pos.livewire.modals.payment-modal')
    @include('partials.thermal-receipt-modal')
    @include('modules.pos.livewire.modals.riwayat-transaksi-modal')
    @include('modules.pos.livewire.modals.pelanggan-baru-modal')
    @include('modules.pos.livewire.modals.kas-modal')
    @include('modules.pos.livewire.modals.mutasi-kas-modal')
    @include('modules.pos.livewire.modals.bayar-servis-modal')
    @include('modules.pos.livewire.modals.pesanan-online-modal')
    @include('partials.riwayat-modal')
</div>
