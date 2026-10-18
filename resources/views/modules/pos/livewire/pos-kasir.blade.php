<div
    class="flex flex-col gap-3 h-[calc(100dvh-8.5rem)] min-w-0"
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
            <div class="relative flex-1 min-w-[280px]" @click.outside="searchOpen = false">
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
                class="px-3 py-2.5 rounded-xl border border-ink-200/80 dark:border-white/10 text-xs font-semibold text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[42px]"
                title="Atur filter pencarian produk"
            >
                <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span class="hidden sm:inline">Filter</span>
            </button>

            <!-- Screen Wake Lock Toggle -->
            <div x-data="screenWakeLock()" x-init="init()" x-show="isSupported" class="flex-shrink-0" x-cloak>
                <button
                    type="button"
                    @click="toggle()"
                    :class="isActive ? 'bg-up-amber/20 border-up-amber/40 text-up-amber' : 'border-ink-200/80 dark:border-white/10 text-ink-500 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5'"
                    class="px-2.5 py-2.5 rounded-xl border text-xs font-semibold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[42px]"
                    :title="isActive ? 'Layar Terus Menyala (Aktif)' : 'Kunci Layar Tetap Menyala (PWA Kasir)'"
                >
                    <span class="text-xs" x-text="isActive ? '☀️' : '🌙'"></span>
                </button>
            </div>

            <!-- Gudang Selector -->
            <div class="w-40 sm:w-48 flex-shrink-0">
                <select
                    wire:model.live="selectedGudangId"
                    class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-semibold"
                >
                    <option value="" class="bg-ink-900 text-white">Pilih Gudang...</option>
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" class="bg-ink-900 text-white">{{ $g->nama }} ({{ $g->kode }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Customer Picker -->
            <div class="w-56 sm:w-64 flex-shrink-0">
                <div class="flex items-center gap-1.5">
                    <div class="flex-1 min-w-0">
                        <select
                            wire:model.live="selectedCustomerId"
                            wire:change="setPelanggan($event.target.value)"
                            class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium truncate"
                        >
                            <option value="" class="bg-ink-900 text-white">Pelanggan Umum</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" class="bg-ink-900 text-white">
                                    {{ $c->nama }} ({{ $c->tierMembership?->nama ?? 'Retail' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($selectedCustomerId)
                        <button
                            type="button"
                            wire:click="setPelanggan(null)"
                            class="p-2.5 rounded-xl bg-ink-100 dark:bg-white/5 hover:bg-ink-200 text-ink-500 hover:text-ink-900 dark:hover:text-white text-xs cursor-pointer min-h-[42px]"
                            title="Reset Pelanggan Umum"
                        >
                            ✕
                        </button>
                    @endif
                    <x-customer-picker
                        :results="$pelangganCari"
                        wireModel="pelangganSearch"
                        selectAction="setPelanggan"
                        addAction="openPelangganBaru"
                        searchPlaceholder="Cari / tambah..."
                    />
                </div>
            </div>

            <!-- Quick Action Links -->
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <button
                    type="button"
                    wire:click="bukaPesananOnlineModal"
                    class="px-2.5 py-2.5 rounded-xl border border-up-amber/30 bg-up-amber/10 hover:bg-up-amber/20 text-xs font-bold text-up-amber transition flex items-center gap-1.5 cursor-pointer min-h-[42px] active:scale-[0.98]"
                    title="Pesanan Online Pickup"
                >
                    <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="hidden xl:inline">Online</span>
                    @if($jumlahPesananOnline > 0)
                        <span class="min-w-[18px] h-4.5 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums">{{ $jumlahPesananOnline }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="bukaBayarServisModal"
                    class="px-2.5 py-2.5 rounded-xl border border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/20 text-xs font-bold text-blue-500 dark:text-blue-400 transition flex items-center gap-1.5 cursor-pointer min-h-[42px] active:scale-[0.98]"
                    title="Bayar Servis"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                    <span class="hidden xl:inline">Servis</span>
                </button>
            </div>

            <!-- SID RETAIL STYLE BIG DISPLAY TOTAL -->
            <div class="px-4 py-2 rounded-xl bg-ink-900 dark:bg-black/80 border border-ink-700/80 dark:border-white/10 flex flex-col items-end min-w-[160px] flex-shrink-0 shadow-inner">
                <span class="text-[9px] uppercase font-bold text-ink-400 tracking-wider">TOTAL TAGIHAN</span>
                <span class="text-xl sm:text-2xl font-black font-mono text-up-mint tabular-nums leading-tight">
                    Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                </span>
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
                    <select wire:model.live="filterKategoriId" class="px-2.5 py-1 rounded-xl glass-input text-xs bg-ink-900 text-white">
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
                    <select wire:model.live="filterBrandId" class="px-2.5 py-1 rounded-xl glass-input text-xs bg-ink-900 text-white">
                        <option value="">Semua Brand</option>
                        @foreach($brandList as $b)
                            <option value="{{ $b->id }}">{{ $b->nama }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['kualitas'])
                    <select wire:model.live="filterKualitasId" class="px-2.5 py-1 rounded-xl glass-input text-xs bg-ink-900 text-white">
                        <option value="">Semua Kualitas</option>
                        @foreach($kualitasList as $k)
                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['tipe_hp'])
                    <select wire:model.live="filterTipeHpId" class="px-2.5 py-1 rounded-xl glass-input text-xs bg-ink-900 text-white">
                        <option value="">Semua Tipe HP</option>
                        @foreach($tipeHpList as $th)
                            <option value="{{ $th->id }}">{{ $th->merk }} - {{ $th->model }}</option>
                        @endforeach
                    </select>
                @endif
                @if($visibleFilters['stok'])
                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 text-xs cursor-pointer">
                        <input type="checkbox" wire:model.live="filterHanyaReadyStok" class="rounded text-up-mint">
                        <span>Hanya Ready Stok</span>
                    </label>
                @endif
            </div>
        @endif
    </div>

    <!-- MAIN STAGE: TABEL TRANSAKSI KASIR (LELUASA, MODERN & BERSIH) -->
    <div class="flex-1 min-h-0 bg-white dark:bg-[#0F172A]/70 backdrop-blur-md border border-ink-200/80 dark:border-white/5 rounded-2xl p-4 sm:p-5 flex flex-col shadow-sm overflow-hidden">
        <!-- Table Area -->
        <div class="flex-1 overflow-x-auto overflow-y-auto pr-1">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="sticky top-0 z-10 bg-white dark:bg-[#0F172A] border-b border-ink-200/80 dark:border-white/10">
                    <tr class="text-[11px] font-bold text-ink-500 uppercase tracking-wider">
                        <th class="py-3 px-3 text-center w-12">#</th>
                        <th class="py-3 px-3 w-36">Kode / SKU</th>
                        <th class="py-3 px-4">Nama Barang & Spesifikasi</th>
                        <th class="py-3 px-3 text-right w-44">Harga Satuan</th>
                        <th class="py-3 px-3 text-center w-36">Qty</th>
                        <th class="py-3 px-4 text-right w-44">Subtotal</th>
                        <th class="py-3 px-3 text-center w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                    @forelse($cart as $key => $item)
                        <tr wire:key="cart-row-{{ $key }}" class="hover:bg-ink-50/70 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="py-3 px-3 text-center font-bold text-ink-400 tabular-nums">
                                {{ $loop->iteration }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-mono text-[11px] font-semibold text-ink-600 dark:text-ink-400 bg-ink-100 dark:bg-white/5 px-2 py-1 rounded-md border border-ink-200/60 dark:border-white/5">
                                    {{ $item['sku'] ?? $key }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
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
                            <td class="py-3 px-3 text-right">
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
                            <td class="py-3 px-3 text-center">
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
                            <td class="py-3 px-4 text-right">
                                <span class="text-xs sm:text-sm font-bold text-up-mint tabular-nums">
                                    Rp {{ number_format($item['subtotal'] ?? ($item['harga'] * $item['qty']), 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
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
                            <td colspan="7" class="text-center py-20">
                                <div class="max-w-md mx-auto flex flex-col items-center">
                                    <div class="w-16 h-16 rounded-3xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary mb-3">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-bold text-ink-900 dark:text-white">Kasir Siap Melayani Transaksi</h3>
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
        <div class="border-t border-ink-200/70 dark:border-white/10 pt-3.5 mt-3 flex-shrink-0 flex items-center justify-between gap-4 flex-wrap">
            <!-- Left Info & Sesi Kas -->
            <div class="flex items-center gap-3 flex-wrap">
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

                <span class="text-ink-300 dark:text-white/20">|</span>
                <a href="/app/pos/riwayat" class="text-xs font-semibold text-up-primary hover:underline flex items-center gap-1">
                    <span>Riwayat Transaksi</span>
                </a>
            </div>

            <!-- Right Breakdown & Action Buttons -->
            <div class="flex items-center gap-4 flex-wrap ml-auto">
                <!-- Mini Breakdown -->
                <div class="flex items-center gap-3 text-xs text-ink-600 dark:text-ink-400">
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
                        class="px-3.5 py-2.5 rounded-xl bg-ink-100 hover:bg-up-red/15 dark:bg-white/5 dark:hover:bg-up-red/20 text-ink-600 hover:text-up-red dark:text-ink-400 dark:hover:text-up-red font-bold text-xs transition border border-ink-200/80 dark:border-white/5 cursor-pointer active:scale-95 min-h-[44px]"
                    >
                        Batal (ESC)
                    </button>

                    <button
                        type="button"
                        wire:click="tahanTransaksi"
                        class="px-3.5 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs transition border border-ink-200/80 dark:border-white/5 cursor-pointer active:scale-95 min-h-[44px]"
                    >
                        Tahan (F6)
                    </button>

                    <button
                        type="button"
                        wire:click="openPaymentModal"
                        class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-sm shadow-md shadow-up-mint/25 transition active:scale-95 cursor-pointer min-h-[44px] flex items-center gap-2"
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
    @include('modules.pos.livewire.modals.retur-modal')
    @include('modules.pos.livewire.modals.pelanggan-baru-modal')
    @include('modules.pos.livewire.modals.kas-modal')
    @include('modules.pos.livewire.modals.mutasi-kas-modal')
    @include('modules.pos.livewire.modals.bayar-servis-modal')
    @include('modules.pos.livewire.modals.pesanan-online-modal')
    @include('partials.riwayat-modal')
</div>
