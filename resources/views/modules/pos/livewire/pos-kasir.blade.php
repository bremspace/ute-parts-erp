<div
    class="flex flex-col lg:flex-row gap-4 h-[calc(100vh-8.5rem)]"
    x-data="{ cartOpen: false, flexSet: {} }"
    @keydown.window.f4.prevent="$wire.openPaymentModal()"
    @keydown.window.f6.prevent="$wire.tahanTransaksi()"
    @keydown.window.escape.prevent="$wire.clearCart()"
>
    <!-- LEFT COLUMN: Product Catalog & Search -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
        <!-- PWA Install Prompt Micro-Banner (POS) -->
        <x-prism.pwa-install-prompt variant="banner" class="mb-2 flex-shrink-0" />

        <!-- Top Toolbar: Search & Gudang Selector -->
        <div class="flex items-center gap-3 mb-2 flex-shrink-0 flex-wrap sm:flex-nowrap">
            <div class="flex-1 min-w-0">
                <x-prism.barcode-scan-input
                    placeholder="Scan Barcode atau ketik nama/tipe HP (F2 / Kamera)..."
                    model="search"
                    wire:keydown.enter="scanEnter"
                    :continuous="true"
                    title="Kamera Scanner Barcode Kasir"
                />
            </div>

            <div class="w-full sm:w-48">
                <select
                    wire:model.live="selectedGudangId"
                    class="w-full px-3 py-2.5 rounded-xl glass-input text-sm font-medium"
                >
                    <option value="" class="bg-ink-900">Pilih Gudang...</option>
                    {{-- [B-02/P1-2] Hanya gudang milik cabang aktif (session cabang_id) --}}
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- [T-51] Desktop Keyboard Shortcuts Visual Hint Bar (hidden on mobile) -->
        <div class="hidden lg:flex items-center gap-2 mb-3 text-[11px] text-ink-400">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 font-mono text-ink-300">
                <kbd class="text-up-primary font-bold">F2</kbd> Cari/Scan
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 font-mono text-ink-300">
                <kbd class="text-up-mint font-bold">F4</kbd> Bayar
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 font-mono text-ink-300">
                <kbd class="text-up-amber font-bold">F6</kbd> Tahan
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 font-mono text-ink-300">
                <kbd class="text-up-red font-bold">ESC</kbd> Reset Keranjang
            </span>
        </div>

        <!-- Product Grid -->
        <div class="flex-1 overflow-y-auto pr-1">
            <div class="grid grid-cols-1 xxs:grid-cols-2 xl:grid-cols-3 gap-3">
                @forelse($products as $prod)
                    @php
                        // [B-02/P0-2] Stok kartu = kriteria SAMA dgn addToCart (agregat produk+gudang)
                        $stokTotal = $stokPerProduk[$prod->id] ?? (\App\Modules\Pos\Livewire\PosKasir::STOK_TANPA_GUDANG);
                        $stokKosong = $stokTotal <= 0;
                        $gudangDipilih = (bool) $selectedGudangId;
                        // [B-02/P0-1] Keterangan jelas utk kasir: kenapa tak bisa dipilih
                        $alasanTidakBisaDipilih = ! $gudangDipilih
                            ? 'Pilih gudang terlebih dahulu untuk melihat stok & memilih produk ini.'
                            : "Stok {$prod->nama} habis di gudang ini — tidak bisa dipilih. Pilih gudang lain yang punya stok.";
                        $pricing = app(\App\Modules\Pos\Services\PricingService::class)->resolve($prod, $customer);
                    @endphp
                    <div
                        wire:key="product-{{ $prod->id }}"
                        @if($stokKosong)
                            {{-- [B-02/P0-1] Stok 0 = TIDAK BISA DIPILIH (kebijakan owner final).
                                 Server tetap menolak (addToCart) — ini hanya tampilan
                                 supaya kasir melihat & paham produknya tidak bisa dipakai. --}}
                            role="button"
                            aria-disabled="true"
                            title="{{ $alasanTidakBisaDipilih }}"
                            class="glass-panel p-3.5 rounded-2xl flex flex-col justify-between border border-white/5 select-none opacity-55 grayscale cursor-not-allowed"
                        @else
                            role="button"
                            wire:click="addToCart({{ $prod->id }})"
                            title="{{ $prod->nama }} — stok {{ $stokTotal }} unit. Klik untuk menambah ke keranjang."
                            class="glass-panel glass-panel-hover p-3.5 rounded-2xl flex flex-col justify-between cursor-pointer border border-white/5 transition-all select-none group"
                        @endif
                    >
                        <div>
                            <!-- Header: Category & Stock -->
                            <div class="flex items-center justify-between mb-2 gap-1">
                                <div class="flex items-center gap-1.5 truncate max-w-[140px]">
                                    <span class="text-[10px] uppercase font-semibold text-ink-400 truncate">
                                        {{ $prod->kategoriRelasi?->nama ?? $prod->kategori ?? 'Sparepart' }}
                                    </span>
                                    @if((int) ($prod->total_terjual ?? 0) > 0)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 whitespace-nowrap" title="{{ $prod->total_terjual }} transaksi">
                                            🔥 {{ $prod->total_terjual }} terjual
                                        </span>
                                    @endif
                                </div>
                                @if($stokKosong)
                                    {{-- [B-02/P0-1] Keterangan stok kosong — produk dikunci (tidak bisa dipilih) --}}
                                    <x-prism.status-pill status="batal">
                                        {{ $gudangDipilih ? 'Stok kosong' : 'Pilih gudang dulu' }}
                                    </x-prism.status-pill>
                                @else
                                    <x-prism.stock-gauge :stok="$stokTotal" :min="5" />
                                @endif
                            </div>

                            <!-- Product Thumbnail & Details -->
                            <div class="flex gap-2.5">
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-white/5 border border-white/10 flex-shrink-0 flex items-center justify-center">
                                    @if($prod->thumbnail_url ?: $prod->gambar)
                                        <img src="{{ $prod->thumbnail_url ?: $prod->gambar }}" alt="{{ $prod->nama }}" loading="lazy" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-[9px] font-bold text-ink-400">UTE</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 @class([
                                        'font-semibold text-xs sm:text-sm line-clamp-2 leading-snug transition-colors',
                                        'text-ink-400' => $stokKosong,
                                        'text-white group-hover:text-up-primary' => ! $stokKosong,
                                    ])>
                                        {{ $prod->nama }}
                                    </h4>

                                    @if($prod->tipeHps->count() > 0)
                                        <p class="text-[10px] text-up-primary font-medium mt-0.5 truncate">
                                            📱 {{ $prod->tipeHps->pluck('model')->take(2)->join(', ') }}
                                        </p>
                                    @elseif($prod->brand_kompatibel || $prod->model_kompatibel)
                                        <p class="text-[11px] text-ink-400 mt-0.5 truncate">
                                            {{ $prod->brand_kompatibel }} {{ $prod->model_kompatibel }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Price Section -->
                        <div class="mt-3 pt-2.5 border-t border-white/5 flex items-end justify-between">
                            <div>
                                @if($pricing['diskon_nominal'] > 0)
                                    <span class="text-[10px] text-ink-500 line-through tabular-nums block">
                                        Rp {{ number_format($pricing['harga_dasar'], 0, ',', '.') }}
                                    </span>
                                @endif
                                <span class="text-sm font-bold text-up-mint tabular-nums">
                                    Rp {{ number_format($pricing['harga'], 0, ',', '.') }}
                                </span>
                            </div>

                            @if($stokKosong)
                                {{-- [B-02/P0-1] Ikon kunci, bukan "+" — produk stok kosong tidak bisa dipilih --}}
                                <span class="w-6 h-6 rounded-lg bg-up-red/10 flex items-center justify-center text-up-red/70 text-xs"
                                      aria-hidden="true" title="{{ $alasanTidakBisaDipilih }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 11V7a6 6 0 1112 0v4m-13 0h14a1 1 0 011 1v8a1 1 0 01-1 1H5a1 1 0 01-1-1v-8a1 1 0 011-1z" />
                                    </svg>
                                </span>
                            @else
                                <span class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center text-ink-300 group-hover:bg-up-primary group-hover:text-white transition-all text-xs">
                                    +
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-16 text-center text-ink-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-30 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="text-sm">Tidak ada produk ditemukan untuk pencarian "{{ $search }}"</p>
                    </div>
                @endforelse

                {{-- [B-02/P1-5] Load-more — seluruh produk aktif bisa diakses (bukan hard-stop 16) --}}
                @if($adaLebihBanyak)
                    <div class="col-span-full flex justify-center pt-2 pb-4">
                        <button
                            type="button"
                            wire:click="muatLebihBanyak"
                            wire:loading.attr="disabled"
                            wire:target="muatLebihBanyak"
                            class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-ink-200 cursor-pointer disabled:opacity-50 min-h-[44px]"
                        >
                            <span wire:loading.remove wire:target="muatLebihBanyak">Muat lebih banyak ({{ count($products) }} dimuat)</span>
                            <span wire:loading wire:target="muatLebihBanyak" class="text-ink-400">Memuat…</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Customer, Cart & Payment Summary -->
    <!-- Mobile: Bottom Sheet | Desktop: Side Panel -->
    <div x-data="{ cartPanelOpen: false }"
         x-init="cartPanelOpen = window.innerWidth >= 1024"
         @resize.window.debounce.100ms="cartPanelOpen = window.innerWidth >= 1024"
         class="lg:w-[420px] lg:flex-shrink-0 lg:flex lg:flex-col lg:h-full">

        <!-- Mobile: Floating Cart Bar (Sticky Bottom, 44px+ touch targets) -->
        <div class="lg:hidden fixed inset-x-2 z-30 flex items-center gap-2 p-2 rounded-2xl bg-ink-900/95 dark:bg-ink-950/95 backdrop-blur-xl border border-black/10 dark:border-white/10 shadow-2xl"
             style="bottom: calc(3.5rem + max(env(safe-area-inset-bottom, 0px), 0.5rem));">
            <button @click="cartPanelOpen = !cartPanelOpen"
                    type="button"
                    class="flex-1 flex items-center justify-between px-3.5 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-50 dark:text-white min-h-[44px] cursor-pointer"
                    :aria-expanded="cartPanelOpen"
                    aria-label="Buka ringkasan keranjang">
                <div class="flex items-center gap-2.5">
                    <div class="relative">
                        <svg class="w-5 h-5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span class="absolute -top-1.5 -right-2 px-1.5 py-0.2 rounded-full bg-up-primary text-white text-[10px] font-bold tabular-nums">
                            {{ count($cart) }}
                        </span>
                    </div>
                    <span class="text-xs font-semibold">Keranjang</span>
                </div>
                <span class="text-xs font-bold text-up-mint tabular-nums">
                    Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                </span>
            </button>
            @if(count($cart) > 0)
                <button wire:click="openPaymentModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer">
                    <span>Bayar</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Mobile: Bottom Sheet Overlay + Panel -->
        <div x-show="cartPanelOpen && window.innerWidth < 1024"
             x-transition:enter="transition-opacity ease-linear duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/60 z-50 lg:hidden"
             @click="cartPanelOpen = false"
             aria-hidden="true"></div>

        <aside x-show="cartPanelOpen && window.innerWidth < 1024"
               x-transition:enter="transition-transform ease-out duration-200"
               x-transition:enter-start="translate-y-full"
               x-transition:enter-end="translate-y-0"
               x-transition:leave="transition-transform ease-in duration-150"
               x-transition:leave-start="translate-y-0"
               x-transition:leave-end="translate-y-full"
               class="fixed bottom-0 left-0 right-0 z-50 max-h-[85vh] lg:hidden"
               @click.outside="cartPanelOpen = false"
               style="padding-bottom: env(safe-area-inset-bottom, 0);">
            <div class="bg-ink-900 border-t border-black/10 dark:border-white/10 rounded-t-3xl p-4 shadow-2xl max-h-[85vh] flex flex-col">
                <!-- Drag Handle -->
                <div class="w-10 h-1 bg-white/20 rounded-full mx-auto mb-3"></div>
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-black/10 dark:border-white/10">
                    <h3 class="text-base font-bold text-ink-50 dark:text-white">Keranjang & Pelanggan</h3>
                    <button @click="cartPanelOpen = false"
                            class="p-2 text-ink-400 hover:text-white rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Customer Picker in Mobile Sheet -->
                <div class="mb-3 pb-3 border-b border-white/5">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-[11px] font-semibold text-ink-300 uppercase">Pelanggan</label>
                        @if($customer)
                            <x-prism.tier-badge :tier="$customer->tierMembership?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail')" />
                        @endif
                    </div>
                    <select
                        wire:model.live="selectedCustomerId"
                        wire:change="setPelanggan($event.target.value)"
                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                    >
                        <option value="" class="bg-ink-900">Pelanggan Umum (Tanpa Member)</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" class="bg-ink-900">{{ $c->nama }} ({{ $c->telepon ?? '-' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3">
                    @include('modules.pos.livewire.partials.cart-content', [
                        'cart' => $cart,
                        'total' => $total,
                        'diskonTotal' => $diskonTotal,
                        'totalBayar' => $totalBayar,
                        'customer' => $customer,
                        'canHargaFleksibel' => $canHargaFleksibel,
                    ])

                    <!-- Mobile Sesi Kas & Actions Shortcut -->
                    <div class="pt-3 border-t border-white/5 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            @if($kasAktif)
                                <div class="flex items-center gap-1.5 text-up-mint">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                                    <span>Kas aktif</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="bukaMutasiKasModal('keluar')" class="text-[11px] font-bold text-up-accent hover:text-orange-400 cursor-pointer">± Mutasi Kas</button>
                                    <span class="text-white/20">|</span>
                                    <button wire:click="tutupKasModal" class="text-[11px] font-bold text-up-amber hover:text-up-red cursor-pointer">Tutup Kas</button>
                                </div>
                            @elseif($kasPendingApproval)
                                <div class="flex items-center gap-1.5 text-up-amber">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-amber animate-pulse"></span>
                                    <span>Review tutup kas</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 text-up-amber">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-amber"></span>
                                    <span>Kas belum dibuka</span>
                                </div>
                                <button wire:click="bukaKasModal" class="text-[11px] font-bold text-up-primary hover:text-indigo-400 cursor-pointer">Buka Kas</button>
                            @endif
                        </div>

                        <div class="flex items-center justify-between pt-1 text-[11px]">
                            <button wire:click="bukaBayarServisModal" class="text-blue-400 hover:text-blue-300 font-semibold flex items-center gap-1 cursor-pointer">
                                <span>🔧 Bayar Servis</span>
                            </button>
                            <a href="/app/pos/riwayat" class="text-up-primary hover:text-indigo-300 font-semibold flex items-center gap-1 cursor-pointer">
                                <span>📋 Riwayat Transaksi</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Desktop: Side Panel Content -->
        <div class="hidden lg:flex lg:flex-col lg:h-full lg:bg-ink-900 lg:border lg:border-white/5 lg:rounded-3xl lg:p-5 lg:overflow-hidden lg:shadow-2xl">
            <!-- Customer Selector -->
            <div class="mb-4 pb-3.5 border-b border-white/5 flex-shrink-0">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold text-ink-300 uppercase tracking-wider">Pelanggan / Member</label>
                    @if($customer)
                        <x-prism.tier-badge :tier="$customer->tierMembership?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail')" />
                    @endif
                </div>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <select
                            wire:model.live="selectedCustomerId"
                            wire:change="setPelanggan($event.target.value)"
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                        >
                            @if(!$pelangganCari->isEmpty())<option value="" class="bg-ink-900">— Pilih dari daftar / cari di bawah —</option>@endif
                            <option value="" class="bg-ink-900">Pelanggan Umum (Tanpa Member)</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" class="bg-ink-900">
                                    {{ $c->nama }} — {{ $c->telepon ?? '-' }} ({{ $c->tierMembership?->nama ?? 'Retail' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($selectedCustomerId)
                        <button
                            wire:click="setPelanggan(null)"
                            class="px-2.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs"
                            title="Reset Pelanggan"
                        >
                            ✕
                        </button>
                    @endif

                    <!-- [T-18] Pencarian + tambah pelanggan reusable (sinkron CRM-06 via PelangganService) -->
                    <x-customer-picker
                        :results="$pelangganCari"
                        wireModel="pelangganSearch"
                        selectAction="setPelanggan"
                        addAction="openPelangganBaru"
                        searchPlaceholder="Cari pelanggan: nama / no HP..."
                    />
                </div>

            @if($customer && $customer->tierMembership)
                <div class="mt-2 text-[11px] text-up-mint flex items-center gap-1.5 font-medium">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Diskon {{ $customer->tierMembership->diskon_persen }}% otomatis aktif untuk member {{ $customer->tierMembership->nama }}</span>
                </div>
            @endif
        </div>

        <!-- Cart Items List (Scrollable) -->
        <div class="flex-1 overflow-y-auto pr-1 space-y-2.5 mb-4">
            {{-- [B-02/P0-1] Banner backorder DIHAPUS (kebijakan owner final: stok 0
                 tidak bisa dipilih). Tidak ada lagi jalur keranjang berisi stok kosong
                 dari addToCart()/updateQty() — keduanya hard-block. Badge "Stok habis"
                 per item tetap dipertahankan sebagai penanda kalau stok berubah habis
                 setelah item masuk keranjang (mis. stok habis di gudang lain / resume
                 transaksi ditahan). --}}
            @forelse($cart as $key => $item)
                <div
                    wire:key="cart-item-desktop-{{ $key }}"
                    x-data="{
                        flexOpen: false,
                        flexHarga: '{{ number_format($item['harga'] ?? 0, 0, '', '') }}',
                        saveFlexHarga(key) {
                            const raw = this.flexHarga.replace(/\./g, '').replace(/,/g, '.');
                            const val = Number(raw);
                            if (isNaN(val)) {
                                this.$dispatch('alert', { type: 'error', message: 'Harga tidak valid' });
                                return;
                            }
                            @if(canSeeField('harga_beli'))
                            if (val < {{ $item['harga_beli'] ?? $item['harga'] ?? 0 }}) {
                                this.$dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal (Rp {{ number_format($item['harga_beli'] ?? $item['harga'] ?? 0, 0, '', '') }})' });
                                return;
                            }
@else
                            if (val < {{ $item['harga'] ?? 0 }}) {
                                this.$dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal (Rp {{ number_format($item['harga'] ?? 0, 0, '', '') }})' });
                                return;
                            }
@endif
                            @this.setHargaFleksibel('{{ $key }}', val);
                            this.flexOpen = false;
                            this.flexHarga = '{{ number_format($item['harga'] ?? 0, 0, '', '') }}';
                        }
                    }"
                    class="p-3 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between gap-3"
                >
                    <div class="flex-1 min-w-0">
                        <h5 class="text-xs font-semibold text-white truncate">{{ $item['nama'] }}</h5>
                        <div class="flex items-center gap-2 mt-0.5 text-[11px] flex-wrap">
                            @if(isset($item['flex']) && $item['flex'])
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-up-amber/20 text-up-amber text-[10px] font-medium border border-up-amber/30">
                                    Fleksibel
                                </span>
                            @endif
                            @if($item['varian'] !== 'Standar')
                                <span class="px-1.5 py-0.2 rounded bg-white/5 text-ink-300 text-[10px]">{{ $item['varian'] }}</span>
                            @endif
                            {{-- [B-02/P0-1] Badge stok habis per item (bukan backorder) --}}
                            @if(($item['stok_max'] ?? 1) <= 0)
                                <x-prism.status-pill status="batal">Stok habis</x-prism.status-pill>
                            @endif
                        </div>
                        <!-- Price & Flex Control -->
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            @if(isset($item['flex']) && $item['flex'])
                                <span
                                    :class="flexSet['{{ $key }}'] ? 'text-white' : 'text-up-amber'"
                                    class="tabular-nums font-medium text-xs"
                                >
                                    Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                </span>
                                @if(!flexSet['{{ $key }}'])
                                    <span class="text-up-amber text-[10px] font-medium">(belum diatur)</span>
                                @endif
                                @if($canHargaFleksibel)
                                    <button
                                        type="button"
                                        @click="flexOpen = true"
                                        class="px-2 py-1 rounded-lg text-[10px] font-semibold text-up-amber border border-up-amber/40 hover:bg-up-amber/10 transition-colors cursor-pointer whitespace-nowrap"
                                        aria-label="Atur harga jual fleksibel"
                                        title="Atur Harga (Superadmin)"
                                    >
                                        Atur Harga
                                    </button>
                                @else
                                    <span class="px-2 py-1 rounded-lg text-[10px] font-medium text-ink-500 bg-white/5 border border-white/5 cursor-not-allowed" title="Akses superadmin diperlukan">
                                        Atur Harga
                                    </span>
                                @endif

                                <!-- Inline Popover: Atur Harga -->
                                <div
                                    x-show="flexOpen"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 transform -translate-y-1"
                                    x-transition:enter-end="opacity-100 transform translate-y-0"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 transform translate-y-0"
                                    x-transition:leave-end="opacity-0 transform -translate-y-1"
                                    @click.outside="flexOpen = false"
                                    @keydown.escape="flexOpen = false"
                                    class="absolute z-20 mt-1 min-w-[200px] glass-panel p-3 rounded-xl border border-white/10 shadow-lg"
                                    style="max-width: 240px;"
                                    x-cloak
                                >
                                    <label class="block text-[10px] font-semibold text-ink-300 mb-1.5">Harga Jual (Rp)</label>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        x-model="flexHarga"
                                        @keydown.enter.prevent="saveFlexHarga('{{ $key }}')"
                                        x-ref="flexInput"
                                        placeholder="0"
                                        class="w-full px-3 py-2 rounded-lg glass-input text-sm font-bold tabular-nums text-white"
                                        aria-label="Harga jual fleksibel"
                                    />
                                    @if(canSeeField('harga_beli'))
                                    <p class="text-[10px] text-up-amber/80 mt-1.5">Min: Rp {{ number_format($item['harga_beli'] ?? $item['harga'], 0, ',', '.') }}</p>
@else
                                    <p class="text-[10px] text-up-amber/80 mt-1.5">Min: —</p>
@endif
                                    <div class="flex gap-2 mt-2.5">
                                        <button
                                            type="button"
                                            @click="saveFlexHarga('{{ $key }}')"
                                            class="flex-1 py-2 rounded-lg bg-up-amber hover:opacity-90 text-ink-950 font-bold text-xs transition-all cursor-pointer min-h-[40px]"
                                        >
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            @click="flexOpen = false"
                                            class="flex-1 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[40px] border border-white/5"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            @else
                                <span class="tabular-nums">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                            @endif
                        </div>

                        {{-- [F2-3] SN wajib utk produk sn=true --}}
                        @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                    </div>

                    <!-- Quantity Controls -->
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <button
                            wire:click="updateQty('{{ $key }}', -1)"
                            class="w-6 h-6 rounded-md bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer"
                        >
                            -
                        </button>
                        <span class="w-8 text-center text-xs font-bold text-white tabular-nums">
                            {{ $item['qty'] }}
                        </span>
                        <button
                            wire:click="updateQty('{{ $key }}', 1)"
                            class="w-6 h-6 rounded-md bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer"
                        >
                            +
                        </button>
                    </div>

                    <!-- Line Subtotal & Delete -->
                    <div class="text-right min-w-[70px] flex-shrink-0">
                        <span class="text-xs font-bold text-white tabular-nums block">
                            Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                        </span>
                        <button
                            wire:click="removeFromCart('{{ $key }}')"
                            class="text-[10px] text-up-red/70 hover:text-up-red transition-colors"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-center text-ink-400 py-12">
                    <svg class="w-10 h-10 mb-2 opacity-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="text-xs">Keranjang belanja kosong</p>
                    <span class="text-[10px] text-ink-500 mt-1">Pilih produk di sebelah kiri atau tekan F2</span>
                </div>
            @endforelse
        </div>

        <!-- Summary & Actions Area (Fixed at bottom) -->
        <div class="border-t border-white/5 pt-4 flex-shrink-0 space-y-3">
            <div class="space-y-1.5 text-xs text-ink-300">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span class="font-semibold text-white tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                @if($diskonNominal > 0 || $diskonPersen > 0)
                    <div class="flex justify-between text-up-mint">
                        <span>Diskon</span>
                        <span class="font-semibold tabular-nums">- Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if($pajakNominal > 0)
                    <div class="flex justify-between text-ink-300">
                        <span>{{ $pajakNama ?? 'PPN' }} ({{ $ppnPersen }}%)</span>
                        <span class="font-semibold text-white tabular-nums">+ Rp {{ number_format($pajakNominal, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-white/10">
                    <span>Total Akhir</span>
                    <span class="text-up-mint text-xl tabular-nums">Rp {{ number_format($totalAkhir, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Action Buttons Grid -->
            <div class="grid grid-cols-3 gap-2 pt-1">
                <button
                    type="button"
                    wire:click="clearCart"
                    class="py-3 px-2 rounded-xl bg-white/5 hover:bg-up-red/20 text-ink-400 hover:text-up-red font-semibold text-xs transition-all border border-white/5 flex flex-col items-center justify-center gap-1 cursor-pointer min-h-[44px]"
                >
                    <span>Batal (ESC)</span>
                </button>

                <button
                    type="button"
                    wire:click="tahanTransaksi"
                    class="py-3 px-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white font-semibold text-xs transition-all border border-white/5 flex flex-col items-center justify-center gap-1 cursor-pointer min-h-[44px]"
                >
                    <span>Tahan (F6)</span>
                </button>

                <button
                    type="button"
                    wire:click="openPaymentModal"
                    class="py-3 px-2 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark hover:to-indigo-700 text-white font-bold text-xs shadow-lg shadow-up-primary/30 transition-all border border-white/20 flex flex-col items-center justify-center gap-0.5 cursor-pointer active:scale-95 min-h-[44px]"
                >
                    <span>BAYAR</span>
                    <span class="text-[10px] font-mono text-white/70">F4</span>
                </button>
            </div>

            <!-- [T-09] Status Kas Sesi -->
            <div class="flex items-center justify-between pt-2">
                @if($kasAktif)
                    <div class="flex items-center gap-2 text-[11px] text-up-mint">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                        <span>Kas terbuka · saldo awal <span class="tabular-nums">Rp {{ number_format($kasAktif->saldo_awal, 0, ',', '.') }}</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="bukaMutasiKasModal('keluar')" class="text-[11px] font-bold text-up-accent hover:text-orange-400 cursor-pointer flex items-center gap-1" title="Catat Kas Masuk / Keluar Laci">
                            <span>± Mutasi Kas</span>
                        </button>
                        <span class="text-white/20">|</span>
                        <button wire:click="tutupKasModal" class="text-[11px] font-bold text-up-amber hover:text-up-red cursor-pointer">Tutup Kas</button>
                    </div>
                @elseif($kasPendingApproval)
                    <div class="flex items-center gap-2 text-[11px] text-up-amber">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-amber animate-pulse"></span>
                        <span>Menunggu persetujuan tutup kas (selisih)</span>
                    </div>
                    <span class="text-[10px] text-ink-500 italic">Proses review</span>
                @else
                    <div class="flex items-center gap-2 text-[11px] text-up-amber">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-amber"></span>
                        <span>Kas belum dibuka — transaksi tunai diblokir</span>
                    </div>
                    <button wire:click="bukaKasModal" class="text-[11px] font-bold text-up-primary hover:text-indigo-400 cursor-pointer">Buka Kas</button>
                @endif
            </div>

            <!-- [T-03] Panel transaksi ditahan & Riwayat Transaksi & Bayar Servis -->
            <div class="pt-2 border-t border-white/5 flex items-center justify-between">
                <button wire:click="$toggle('showDitahanPanel')" class="text-[11px] font-semibold text-ink-400 hover:text-white flex items-center gap-1 cursor-pointer">
                    <span>{{ $showDitahanPanel ? '▼' : '▶' }}</span>
                    Ditahan ({{ $ditahanList->count() }})
                </button>
                <div class="flex items-center gap-2.5">
                    <button wire:click="bukaBayarServisModal" class="text-[11px] font-bold text-blue-400 hover:text-blue-300 flex items-center gap-1 cursor-pointer" title="Proses Pelunasan Servis di Kasir">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                        <span>Bayar Servis</span>
                    </button>
                    <span class="text-white/20">|</span>
                    <a href="/app/pos/riwayat" class="text-[11px] font-semibold text-up-primary hover:text-indigo-300 flex items-center gap-1 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Riwayat Transaksi</span>
                    </a>
                </div>
            </div>
            @if($showDitahanPanel && $ditahanList->isNotEmpty())
                <div class="mt-2 space-y-1.5 max-h-44 overflow-y-auto">
                    @foreach($ditahanList as $dt)
                        <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white/[0.03] border border-white/5 text-[11px]">
                            <div class="min-w-0">
                                <span class="font-mono text-white truncate block">{{ $dt->no_transaksi }}</span>
                                <span class="text-ink-400">Rp <span class="tabular-nums">{{ number_format($dt->total_akhir, 0, ',', '.') }}</span> · {{ $dt->kasir?->name }}</span>
                            </div>
                            <button wire:click="resumeDitahan({{ $dt->id }})" class="px-2.5 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary font-bold cursor-pointer whitespace-nowrap">Lanjutkan</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- PAYMENT MODAL -->
    @if($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Pembayaran Transaksi</h3>
                    <button wire:click="$set('showPaymentModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <!-- Total Amount Highlight -->
                <div class="bg-white/5 p-4 rounded-2xl text-center mb-5 border border-white/5">
                    <span class="text-xs uppercase tracking-wider text-ink-400 font-semibold block mb-1">Total Tagihan</span>
                    <span class="text-3xl font-black text-up-mint tabular-nums">
                        Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                    </span>
                </div>

                <!-- Payment Method Selector -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-ink-300 mb-2">Metode Pembayaran</label>
                    <div class="grid grid-cols-2 xxs:grid-cols-3 sm:grid-cols-5 gap-2">
                        @foreach(['tunai' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'split' => 'Split', 'piutang' => 'Kasbon'] as $val => $label)
                            <button
                                type="button"
                                wire:click="$set('metodeBayar', '{{ $val }}')"
                                class="py-2.5 px-2 rounded-xl text-xs font-bold border transition-[transform,background-color,border-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer {{ $metodeBayar === $val ? 'bg-up-primary text-white border-up-primary shadow-md shadow-up-primary/30' : 'bg-white/5 text-ink-300 border-white/10 hover:bg-white/10' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Method Detail Inputs -->
                @if($metodeBayar === 'tunai')
                    <div class="space-y-3 mb-5">
                        <label class="block text-xs font-semibold text-ink-300">Uang Diterima</label>
                        <input
                            type="text" inputmode="numeric" x-format-number
                            wire:model.live.debounce.300ms="jumlahBayar"
                            class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums text-white min-h-[44px]"
                        />

                        <!-- Quick Cash Shortcuts -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1">
                            <button type="button" wire:click="setQuickCash({{ $totalAkhir }})" class="px-3 py-2.5 rounded-xl bg-white/5 text-xs text-ink-200 hover:bg-white/10 active:scale-[0.97] border border-white/10 font-bold min-h-[44px] flex items-center justify-center cursor-pointer">Uang Pas</button>
                            <button type="button" wire:click="setQuickCash(50000)" class="px-3 py-2.5 rounded-xl bg-white/5 text-xs text-ink-200 hover:bg-white/10 active:scale-[0.97] border border-white/10 font-bold tabular-nums min-h-[44px] flex items-center justify-center cursor-pointer">Rp 50.000</button>
                            <button type="button" wire:click="setQuickCash(100000)" class="px-3 py-2.5 rounded-xl bg-white/5 text-xs text-ink-200 hover:bg-white/10 active:scale-[0.97] border border-white/10 font-bold tabular-nums min-h-[44px] flex items-center justify-center cursor-pointer">Rp 100.000</button>
                            <button type="button" wire:click="setQuickCash(200000)" class="px-3 py-2.5 rounded-xl bg-white/5 text-xs text-ink-200 hover:bg-white/10 active:scale-[0.97] border border-white/10 font-bold tabular-nums min-h-[44px] flex items-center justify-center cursor-pointer">Rp 200.000</button>
                        </div>

                        <!-- Kembalian Display -->
                        <div class="flex justify-between items-center p-3 rounded-xl bg-white/[0.02] border border-white/5 text-sm">
                            <span class="text-ink-400 font-medium">Kembalian</span>
                            <span class="font-bold text-lg tabular-nums {{ $kembalian > 0 ? 'text-up-mint' : 'text-white' }}">
                                Rp {{ number_format($kembalian, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @elseif($metodeBayar === 'split')
                    <div class="space-y-3 mb-5">
                        <div>
                            <label class="block text-xs text-ink-300 mb-1">Nominal Tunai</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live.debounce.300ms="splitTunai" class="w-full px-3 py-2.5 rounded-xl glass-input text-sm font-bold tabular-nums min-h-[44px]" />
                        </div>
                        <div>
                            <label class="block text-xs text-ink-300 mb-1">Nominal Non-Tunai</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live.debounce.300ms="splitNonTunai" class="w-full px-3 py-2.5 rounded-xl glass-input text-sm font-bold tabular-nums min-h-[44px]" />
                        </div>
                    </div>
                @elseif($metodeBayar === 'piutang')
                    <div class="p-4 rounded-xl bg-up-amber/10 text-center text-xs text-up-amber mb-5 border border-up-amber/30">
                        <svg class="w-6 h-6 mx-auto mb-1.5" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h16v12H4V6zm5 2a1 1 0 100 2h6a1 1 0 100-2H9zm-1 6a1 1 0 011-1h6a1 1 0 110 2H9a1 1 0 01-1-1zm1-4a1 1 0 100 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
                        </svg>
                        <strong class="block text-sm mb-1">Kasbon / Piutang</strong>
                        <span>Transaksi dicatat sebagai piutang pelanggan (jatuh tempo 30 hari). Akun <strong>Piutang Usaha</strong> otomatis ter-debit di jurnal.</span>
                        @if(!$selectedCustomerId)
                            <div class="mt-2.5 p-2 rounded-lg bg-up-red/20 text-up-red font-semibold border border-up-red/30 text-left flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Pelanggan belum dipilih! Pilih pelanggan di atas sebelum kasbon.</span>
                            </div>
                        @else
                            <div class="mt-2 text-ink-200">
                                Pelanggan: <strong class="text-white">{{ $customer?->nama }}</strong>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="p-4 rounded-xl bg-white/5 text-center text-xs text-ink-300 mb-5 border border-white/5">
                        Silakan scan QRIS atau konfirmasi bukti transfer sebesar
                        <strong class="text-white block mt-1 text-sm font-bold tabular-nums">
                            Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                        </strong>
                    </div>
                @endif

                <!-- Submit Button -->
                <div class="flex flex-col-reverse sm:flex-row gap-2.5 sm:gap-3">
                    <button
                        type="button"
                        wire:click="$set('showPaymentModal', false)"
                        class="w-full sm:flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-sm transition-[transform,background-color] active:scale-[0.97] border border-white/10 cursor-pointer min-h-[44px]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="processTransaction"
                        class="w-full sm:flex-2 py-3 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-bold text-sm transition-[transform,opacity] active:scale-[0.97] shadow-lg shadow-up-mint/20 cursor-pointer min-h-[44px]"
                    >
                        Konfirmasi & Cetak Struk
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- RECEIPT MODAL (Thermal Print 58mm / 80mm) -->
    @include('partials.thermal-receipt-modal')

    <!-- MODAL: RIWAYAT TRANSAKSI HARI INI -->
    @if($showRiwayatTransaksiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-4xl glass-panel p-6 rounded-3xl relative max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Riwayat Transaksi Hari Ini</h3>
                            <p class="text-xs text-ink-400">Daftar transaksi kasir di cabang aktif hari ini</p>
                        </div>
                    </div>
                    <button wire:click="tutupRiwayatTransaksi" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto flex-1 pr-1">
                    @if($riwayatTransaksiHariIni->isEmpty())
                        <div class="py-12 text-center text-ink-400 text-xs">
                            Belum ada transaksi tercatat hari ini di cabang ini.
                        </div>
                    @else
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-white/10 text-ink-400 uppercase text-[10px] tracking-wider">
                                    <th class="pb-3 px-3">No Transaksi</th>
                                    <th class="pb-3 px-3">Waktu</th>
                                    <th class="pb-3 px-3">Kasir</th>
                                    <th class="pb-3 px-3">Pelanggan</th>
                                    <th class="pb-3 px-3 text-right">Total</th>
                                    <th class="pb-3 px-3">Metode</th>
                                    <th class="pb-3 px-3">Status</th>
                                    <th class="pb-3 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($riwayatTransaksiHariIni as $trx)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3 px-3 font-mono font-bold text-white">{{ $trx->no_transaksi }}</td>
                                        <td class="py-3 px-3 text-ink-300 tabular-nums">{{ $trx->created_at?->format('H:i:s') }}</td>
                                        <td class="py-3 px-3 text-ink-200">{{ $trx->kasir?->name ?? '-' }}</td>
                                        <td class="py-3 px-3 text-ink-200">{{ $trx->pelanggan?->nama ?? 'Umum' }}</td>
                                        <td class="py-3 px-3 text-right font-bold text-white tabular-nums">Rp {{ number_format($trx->total_akhir, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 uppercase text-[10px] text-ink-300 font-bold">{{ $trx->metode_bayar }}</td>
                                        <td class="py-3 px-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $trx->status === 'selesai' ? 'bg-up-mint/10 text-up-mint border border-up-mint/20' : ($trx->status === 'dibatalkan' ? 'bg-up-red/10 text-up-red border border-up-red/20' : 'bg-up-amber/10 text-up-amber border border-up-amber/20') }}">
                                                {{ ucfirst($trx->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button
                                                    wire:click="lihatDetailTransaksi({{ $trx->id }})"
                                                    class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-ink-100 font-semibold text-[11px] transition-colors border border-white/10 cursor-pointer"
                                                    title="Lihat Struk / Detail Barang"
                                                >
                                                    Detail
                                                </button>
                                                @if($trx->status === 'selesai')
                                                    <button
                                                        wire:click="bukaModalRetur({{ $trx->id }})"
                                                        class="px-2.5 py-1 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red font-bold text-[11px] transition-colors border border-up-red/20 cursor-pointer"
                                                        title="Proses Retur Barang Penjualan"
                                                    >
                                                        Retur
                                                    </button>
                                                @endif
                                                @can('lihat-audit-log')
                                                    <button
                                                        wire:click="bukaRiwayat('transaksi', {{ $trx->id }})"
                                                        class="px-2 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-[11px] transition-colors border border-up-primary/20 cursor-pointer"
                                                        title="Lihat Log Mutasi Transaksi"
                                                    >
                                                        Audit Log
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="pt-4 mt-2 border-t border-white/10 flex justify-end">
                    <button
                        wire:click="tutupRiwayatTransaksi"
                        class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white font-medium text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL RETUR PENJUALAN --}}
    @if($showReturPenjualanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white">Retur Barang Penjualan</h3>
                        <p class="text-[10px] text-ink-400">Pengembalian barang pelanggan, pemulihan stok & pembukuan kas/piutang</p>
                    </div>
                    <button wire:click="tutupModalRetur" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-4 flex-1 overflow-y-auto pr-1 text-xs">
                    @if(!empty($returItemInputs))
                        <div class="space-y-2">
                            <label class="block font-semibold text-ink-300">Pilih Barang yang Diretur & Jumlah</label>
                            <div class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
                                @foreach($returItemInputs as $itemId => $item)
                                    <div class="p-3 bg-white/[0.02] space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="font-bold text-white">{{ $item['produk_nama'] }}</div>
                                                <div class="text-[11px] text-ink-400">
                                                    Qty Beli: {{ $item['qty_beli'] }} · Harga: Rp {{ number_format($item['harga_final'], 0, ',', '.') }}
                                                </div>
                                            </div>
                                            <div class="w-28">
                                                <label class="text-[10px] text-ink-400 block mb-0.5">Qty Retur</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $item['qty_beli'] }}"
                                                    wire:model.live="returItemInputs.{{ $itemId }}.jumlah"
                                                    class="w-full px-2 py-1.5 rounded-lg glass-input text-xs tabular-nums text-center"
                                                />
                                            </div>
                                        </div>
                                        @if(($returItemInputs[$itemId]['jumlah'] ?? 0) > 0)
                                            <div>
                                                <label class="text-[10px] text-ink-400 block mb-0.5">Nomor Seri yang diretur (Pisahkan koma / baris baru jika produk ber-SN):</label>
                                                <textarea
                                                    wire:model="returItemInputs.{{ $itemId }}.sn_raw"
                                                    rows="2"
                                                    placeholder="SN123, SN124..."
                                                    class="w-full px-2.5 py-1.5 rounded-lg glass-input text-[11px] font-mono"
                                                ></textarea>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-ink-300 mb-1.5">Metode Pengembalian *</label>
                            <select wire:model="returMetodePengembalian" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                                <option value="kas" class="bg-ink-900">Uang Tunai / Kas (110-01)</option>
                                <option value="piutang" class="bg-ink-900">Potong Piutang Pelanggan (120-01)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-300 mb-1.5">Alasan Retur *</label>
                            <input
                                type="text"
                                wire:model="returAlasan"
                                placeholder="Contoh: Barang cacat pabrik / salah tipe"
                                class="w-full px-3 py-2.5 rounded-xl glass-input text-xs min-h-[44px]"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/10 mt-4">
                    <button wire:click="tutupModalRetur" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    <button wire:click="simpanReturPenjualan" class="flex-1 py-3 rounded-xl bg-up-red text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-md shadow-up-red/20 active:scale-[0.97]">
                        Proses Retur Penjualan
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- [T-08] MODAL: PELANGGAN BARU -->
    @if($showPelangganBaruModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Pelanggan Baru</h3>
                    <button wire:click="$set('showPelangganBaruModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="pelangganBaruForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">No. HP *</label>
                        <input type="text" wire:model="pelangganBaruForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="08xx-xxxx-xxxx" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email</label>
                        <input type="email" wire:model="pelangganBaruForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="pelangganBaruForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Lahir (opsional)</label>
                        <input type="date" wire:model="pelangganBaruForm.tanggal_lahir" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <p class="text-[10px] text-ink-500">Pelanggan baru langsung tersedia di POS, CRM, dan Servis (satu data).</p>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showPelangganBaruModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanPelangganBaru" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- [T-09] MODAL: KAS SESI -->
    @if($showKasModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
             x-data="{}"
             x-init="$nextTick(() => { const el = $refs.kasSaldoInput; if (el) el.focus(); el?.select(); })">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $kasModeBuka ? 'Buka Kas (Shift Baru)' : 'Tutup Kas (Rekap Shift)' }}</h3>
                    <button wire:click="$set('showKasModal', false)" class="p-2.5 text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                @if(!$kasHasil)
                    <div class="space-y-4">
                        @if($kasModeBuka)
                            {{-- [KAS-LACI] Pemilihan Sumber Dana --}}
                            <div>
                                <label for="selectedAkunSumber" class="block text-xs font-semibold text-ink-300 mb-1.5">Sumber Dana *</label>
                                <select
                                    id="selectedAkunSumber"
                                    wire:model.live="selectedAkunSumber"
                                    class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900/80 border border-white/10 focus:border-up-primary cursor-pointer"
                                >
                                    @foreach($akunSumberList as $akun)
                                        <option value="{{ $akun['kode'] }}" class="bg-ink-900 text-white">
                                            {{ $akun['kode'] }} — {{ $akun['nama'] }} (Saldo: Rp {{ number_format($akun['saldo'], 0, ',', '.') }})
                                        </option>
                                    @endforeach
                                </select>
                                @php
                                    $selectedAkun = collect($akunSumberList)->firstWhere('kode', $selectedAkunSumber);
                                @endphp
                                @if($selectedAkun)
                                    <p class="mt-1 text-[11px] text-ink-400">
                                        Saldo tersedia: <span class="tabular-nums font-semibold {{ $selectedAkun['saldo'] > 0 ? 'text-up-mint' : 'text-up-red' }}">Rp {{ number_format($selectedAkun['saldo'], 0, ',', '.') }}</span>
                                    </p>
                                @endif
                            </div>

                            <div>
                                <label for="kasSaldoAwal" class="block text-xs font-semibold text-ink-300 mb-1.5">Saldo Awal (Rp) *</label>
                                <input
                                    id="kasSaldoAwal"
                                    type="text" inputmode="numeric" x-format-number wire:model.debounce.300ms="kasSaldoAwalRaw" min="0"
                                    x-ref="kasSaldoInput"
                                    @keydown.enter.prevent="$wire.prosesKas()"
                                    @blur="$wire.formatKasSaldoAwal()"
                                    class="w-full px-3 py-3 rounded-xl glass-input text-lg font-bold tabular-nums {{ $errors->has('kasSaldoAwalRaw') ? 'border-up-red/60' : '' }}"
                                    placeholder="0"
                                />
                                @error('kasSaldoAwalRaw')
                                    <p class="mt-1.5 text-[11px] font-medium text-up-red">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            {{-- [KAS-LACI / BLIND COUNT] Kasir hanya menginput saldo fisik tanpa melihat saldo sistem --}}
                            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                                <p class="text-[11px] text-ink-400">
                                    Hitung seluruh uang tunai fisik di laci kasir dan masukkan nominalnya di bawah. Sistem akan mencocokkan dengan catatan transaksi.
                                </p>
                            </div>
                            <div>
                                <label for="kasSaldoFisik" class="block text-xs font-semibold text-ink-300 mb-1.5">Saldo Fisik Akhir (Rp) *</label>
                                <input
                                    id="kasSaldoFisik"
                                    type="text" inputmode="numeric" x-format-number wire:model.debounce.300ms="kasSaldoFisikRaw" min="0" step="500"
                                    x-ref="kasSaldoInput"
                                    @keydown.enter.prevent="$wire.prosesKas()"
                                    @blur="$wire.formatKasSaldoFisik()"
                                    class="w-full px-3 py-3 rounded-xl glass-input text-lg font-bold tabular-nums {{ $errors->has('kasSaldoFisikRaw') ? 'border-up-red/60' : '' }}"
                                    placeholder="0"
                                />
                                @error('kasSaldoFisikRaw')
                                    <p class="mt-1.5 text-[11px] font-medium text-up-red">{{ $message }}</p>
                                @enderror
                            </div>
                            <p class="text-[10px] text-ink-500">Bila terjadi selisih, penutupan shift memerlukan persetujuan owner / manager toko.</p>
                        @endif
                    </div>
                @else
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between p-2.5 rounded-lg bg-white/[0.03]">
                            <span class="text-ink-400">{{ $kasModeBuka ? 'Saldo awal' : 'Saldo sistem' }}</span>
                            <span class="font-bold text-white tabular-nums">Rp {{ number_format($kasHasil['saldo_awal'] ?? $kasHasil['saldo_sistem'], 0, ',', '.') }}</span>
                        </div>
                        @if(!$kasModeBuka)
                            <div class="flex justify-between p-2.5 rounded-lg bg-white/[0.03]">
                                <span class="text-ink-400">Saldo fisik</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($kasHasil['saldo_fisik'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between p-2.5 rounded-lg {{ $kasHasil['selisih'] == 0 ? 'bg-up-mint/10' : 'bg-up-amber/10' }}">
                                <span class="text-ink-400">Selisih</span>
                                <span class="font-bold {{ $kasHasil['selisih'] == 0 ? 'text-up-mint' : 'text-up-amber' }} tabular-nums">Rp {{ number_format($kasHasil['selisih'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKasModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Tutup</button>
                    @if(!$kasHasil)
                        <button wire:click="prosesKas" class="flex-1 py-3 rounded-xl {{ $kasModeBuka ? 'bg-up-mint' : 'bg-up-accent' }} text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">
                            {{ $kasModeBuka ? 'Buka Kas' : 'Tutup & Rekap' }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- [KAS-LACI-MUTASI] MODAL MUTASI KAS LACI (IN/OUT NON-POS) --}}
    @if($showMutasiKasModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
             x-data="{}"
             x-init="$nextTick(() => { $refs.mutasiNominalInput?.focus(); })">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $mutasiJenis === 'masuk' ? 'bg-up-mint' : 'bg-up-accent' }}"></span>
                        <h3 class="text-base font-bold text-white">Mutasi Kas Laci (Non-POS)</h3>
                    </div>
                    <button wire:click="$set('showMutasiKasModal', false)" class="p-2 text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                {{-- Tab Switcher: Masuk vs Keluar --}}
                <div class="grid grid-cols-2 gap-2 p-1 bg-white/5 rounded-xl mb-4">
                    <button
                        type="button"
                        wire:click="$set('mutasiJenis', 'keluar')"
                        class="py-2 text-xs font-bold rounded-lg transition-all cursor-pointer {{ $mutasiJenis === 'keluar' ? 'bg-up-accent text-white shadow-lg' : 'text-ink-400 hover:text-white' }}">
                        Pengeluaran (Kas Keluar)
                    </button>
                    <button
                        type="button"
                        wire:click="$set('mutasiJenis', 'masuk')"
                        class="py-2 text-xs font-bold rounded-lg transition-all cursor-pointer {{ $mutasiJenis === 'masuk' ? 'bg-up-mint text-ink-950 shadow-lg' : 'text-ink-400 hover:text-white' }}">
                        Pemasukan (Kas Masuk)
                    </button>
                </div>

                <div class="space-y-3.5 overflow-y-auto pr-1">
                    {{-- Pilihan Kategori / Akun Lawan --}}
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">
                            Kategori Transaksi (Akun Lawan COA) *
                        </label>
                        <select
                            wire:model.live="mutasiAkunLawan"
                            class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900/80 border border-white/10 focus:border-up-primary cursor-pointer">
                            @foreach($mutasiKategoriList as $kat)
                                <option value="{{ $kat['kode'] }}" class="bg-ink-900 text-white">
                                    [{{ $kat['kode'] }}] {{ $kat['nama'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Nominal --}}
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nominal (Rp) *</label>
                        <input
                            type="text" inputmode="numeric" x-format-number wire:model="mutasiNominalRaw" min="0"
                            x-ref="mutasiNominalInput"
                            class="w-full px-3 py-2.5 rounded-xl glass-input text-base font-bold tabular-nums {{ $errors->has('mutasiNominalRaw') ? 'border-up-red/60' : '' }}"
                            placeholder="0"
                        />
                        @error('mutasiNominalRaw')
                            <p class="mt-1 text-[11px] font-medium text-up-red">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Keterangan / Keperluan --}}
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan / Keperluan *</label>
                        <input
                            type="text"
                            wire:model="mutasiKeterangan"
                            placeholder="Contoh: Beli lakban & kantong plastik / Tambahan uang kembalian"
                            class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium text-white {{ $errors->has('mutasiKeterangan') ? 'border-up-red/60' : '' }}"
                        />
                        @error('mutasiKeterangan')
                            <p class="mt-1 text-[11px] font-medium text-up-red">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Info Jurnal Otomatis --}}
                    <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 text-[11px] text-ink-400">
                        <span class="font-semibold text-ink-300">Jurnal Otomatis:</span>
                        @if($mutasiJenis === 'keluar')
                            <p class="mt-0.5 font-mono text-[10px] text-amber-300">
                                D: [{{ $mutasiAkunLawan }}] · K: [110-04] Kas Laci
                            </p>
                        @else
                            <p class="mt-0.5 font-mono text-[10px] text-up-mint">
                                D: [110-04] Kas Laci · K: [{{ $mutasiAkunLawan }}]
                            </p>
                        @endif
                    </div>

                    {{-- Riwayat Mutasi Sesi Ini --}}
                    @if(!empty($riwayatMutasiSesi))
                        <div class="pt-2 border-t border-white/10">
                            <h4 class="text-[11px] font-semibold text-ink-300 mb-2">Mutasi Shift Ini:</h4>
                            <div class="space-y-1.5 max-h-32 overflow-y-auto">
                                @foreach($riwayatMutasiSesi as $m)
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white/[0.02] border border-white/5 text-[10px]">
                                        <div class="flex items-center gap-2">
                                            <span class="px-1.5 py-0.5 rounded font-bold uppercase {{ $m['jenis'] === 'masuk' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-accent/20 text-up-accent' }}">
                                                {{ $m['jenis'] }}
                                            </span>
                                            <span class="text-white truncate max-w-[170px]" title="{{ $m['keterangan'] }}">{{ $m['keterangan'] }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-bold tabular-nums text-white">Rp {{ number_format($m['nominal'], 0, ',', '.') }}</span>
                                            <span class="block text-ink-500 font-mono text-[9px]">{{ $m['jam'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex gap-3 pt-3 border-t border-white/5 mt-4">
                    <button wire:click="$set('showMutasiKasModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[40px]">
                        Batal
                    </button>
                    <button wire:click="simpanMutasiKas" class="flex-1 py-2.5 rounded-xl {{ $mutasiJenis === 'masuk' ? 'bg-up-mint text-ink-950' : 'bg-up-accent text-white' }} font-bold text-xs cursor-pointer min-h-[40px]">
                        Simpan Mutasi
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== [POS-SERVIS] MODAL: PEMBAYARAN SERVIS DI KASIR POS ===== -->
    @if($showBayarServisModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Pembayaran Servis di Kasir POS</h3>
                            <p class="text-xs text-ink-400">Penyatuan kas masuk laci kasir untuk pelunasan tiket servis</p>
                        </div>
                    </div>
                    <button wire:click="tutupBayarServisModal" class="p-2 text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                    @if(! $selectedServisDetail)
                        {{-- STATE 1: PILIH TIKET SERVIS --}}
                        <div class="space-y-3">
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="searchServis"
                                    placeholder="Cari no tiket / nama / no hp / tipe hp..."
                                    class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-white border border-white/10"
                                    autofocus
                                />
                                @if($searchServis)
                                    <button wire:click="$set('searchServis', '')" class="absolute right-3 top-2.5 text-xs text-ink-400 hover:text-white">✕</button>
                                @endif
                            </div>

                            <div class="space-y-2 max-h-[55vh] overflow-y-auto">
                                @forelse($daftarServisSiapBayar as $ts)
                                    <div class="p-3.5 rounded-2xl bg-white/[0.02] hover:bg-white/[0.05] border border-white/5 transition flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-white text-xs">{{ $ts->no_tiket }}</span>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                                    {{ ucfirst($ts->status) }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-ink-200 font-medium mt-1">
                                                {{ $ts->pelanggan?->nama ?? $ts->nama_pelanggan ?? 'Pelanggan Umum' }}
                                                <span class="text-ink-400">({{ $ts->pelanggan?->telepon ?? $ts->telepon_pelanggan ?? '-' }})</span>
                                            </p>
                                            <p class="text-[11px] text-ink-400 mt-0.5 truncate">
                                                {{ $ts->jenis_hp }}{{ $ts->seri_hp ? ' · '.$ts->seri_hp : '' }} — {{ $ts->keluhan }}
                                            </p>
                                        </div>
                                        <div class="text-right flex flex-col items-end gap-1.5 flex-shrink-0">
                                            <span class="font-bold text-sm text-up-mint tabular-nums">
                                                Rp {{ number_format($ts->estimasi_biaya ?? 0, 0, ',', '.') }}
                                            </span>
                                            <button
                                                wire:click="pilihTiketServis({{ $ts->id }})"
                                                class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-bold transition active:scale-[0.98] cursor-pointer">
                                                Pilih
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-12 text-center text-ink-400 text-xs">
                                        Tidak ada tiket servis siap bayar (status selesai/diambil belum lunas) di cabang ini.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        {{-- STATE 2: DETAIL TAGIHAN & PEMBAYARAN --}}
                        <div class="space-y-4">
                            <!-- Card Info Tiket -->
                            <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-sm text-white">{{ $selectedServisDetail['no_tiket'] }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-400">
                                            {{ ucfirst($selectedServisDetail['status']) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-ink-200 mt-1">
                                        {{ $selectedServisDetail['pelanggan_nama'] }} · {{ $selectedServisDetail['pelanggan_telepon'] }}
                                    </p>
                                    <p class="text-[11px] text-ink-400">
                                        Unit: <span class="text-white font-medium">{{ $selectedServisDetail['jenis_hp'] }}</span>
                                    </p>
                                </div>
                                <button
                                    wire:click="$set('selectedServisDetail', null)"
                                    class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-[11px] font-semibold cursor-pointer">
                                    Ganti Tiket
                                </button>
                            </div>

                            <!-- Rincian Biaya Servis -->
                            <div>
                                <h4 class="text-xs font-semibold text-ink-300 mb-2">Rincian Pekerjaan & Suku Cadang:</h4>
                                <div class="rounded-xl border border-white/5 overflow-hidden">
                                    <table class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="border-b border-white/10 text-ink-400 text-[10px] bg-white/[0.02]">
                                                <th class="py-2 px-3">Item</th>
                                                <th class="py-2 px-3 text-center">Qty</th>
                                                <th class="py-2 px-3 text-right">Harga</th>
                                                <th class="py-2 px-3 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            @foreach($selectedServisDetail['items'] as $it)
                                                <tr>
                                                    <td class="py-2 px-3 text-white">{{ $it['nama'] }}</td>
                                                    <td class="py-2 px-3 text-center text-white tabular-nums">{{ $it['qty'] }}</td>
                                                    <td class="py-2 px-3 text-right text-ink-300 tabular-nums">Rp {{ number_format($it['harga'], 0, ',', '.') }}</td>
                                                    <td class="py-2 px-3 text-right text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Total Tagihan Banner -->
                            <div class="p-3.5 rounded-2xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-between">
                                <span class="text-xs font-semibold text-ink-200">Total Tagihan Servis</span>
                                <span class="text-xl font-bold text-up-mint tabular-nums">
                                    Rp {{ number_format($selectedServisDetail['total'], 0, ',', '.') }}
                                </span>
                            </div>

                            <!-- Metode Pembayaran -->
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-1.5">Metode Pembayaran</label>
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach(['tunai' => 'Tunai (Kas Laci)', 'transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'split' => 'Split'] as $m => $lbl)
                                        <button
                                            type="button"
                                            wire:click="$set('servisMetodeBayar', '{{ $m }}')"
                                            class="py-2 px-2.5 rounded-xl border text-center text-xs font-semibold transition cursor-pointer {{ $servisMetodeBayar === $m ? 'bg-up-primary text-white border-up-primary shadow-sm shadow-up-primary/30' : 'bg-white/[0.02] border-white/5 text-ink-300 hover:bg-white/5' }}">
                                            {{ $lbl }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Input Pembayaran -->
                            @if($servisMetodeBayar === 'tunai')
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs font-semibold text-ink-300">
                                        <span>Jumlah Bayar (Tunai)</span>
                                        <div class="flex gap-1.5">
                                            <button type="button" wire:click="$set('servisJumlahBayar', {{ $selectedServisDetail['total'] }})" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-[10px] text-ink-300 cursor-pointer">Uang Pas</button>
                                            <button type="button" wire:click="$set('servisJumlahBayar', 50000)" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-[10px] text-ink-300 cursor-pointer">50k</button>
                                            <button type="button" wire:click="$set('servisJumlahBayar', 100000)" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-[10px] text-ink-300 cursor-pointer">100k</button>
                                            <button type="button" wire:click="$set('servisJumlahBayar', 200000)" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-[10px] text-ink-300 cursor-pointer">200k</button>
                                        </div>
                                    </div>
                                    <input
                                        type="text"
                                        wire:model.live="servisJumlahBayar"
                                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-sm font-bold text-white tabular-nums border border-white/10"
                                        placeholder="0"
                                    />
                                    @if($servisKembalian > 0)
                                        <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between text-xs">
                                            <span class="text-ink-400">Kembalian:</span>
                                            <span class="font-bold text-up-mint tabular-nums">Rp {{ number_format($servisKembalian, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                </div>
                            @elseif($servisMetodeBayar === 'split')
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Porsi Tunai (Kas Laci)</label>
                                        <input
                                            type="text"
                                            wire:model.live="servisSplitTunai"
                                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-bold text-white tabular-nums border border-white/10"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Porsi Non-Tunai</label>
                                        <input
                                            type="text"
                                            wire:model.live="servisSplitNonTunai"
                                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-bold text-white tabular-nums border border-white/10"
                                        />
                                    </div>
                                </div>
                            @endif

                            <!-- Checkbox Serahkan Unit -->
                            @if($selectedServisDetail['status'] === 'selesai')
                                <label class="flex items-center gap-2.5 p-3 rounded-xl bg-white/[0.02] border border-white/5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="servisUbahStatusDiambil"
                                        class="w-4 h-4 rounded text-up-primary focus:ring-0 bg-transparent border-white/20"
                                    />
                                    <span class="text-xs text-white">
                                        Serahkan unit ke pelanggan sekarang (status tiket berubah ke <strong class="text-up-mint">Diambil</strong>)
                                    </span>
                                </label>
                            @endif

                            <!-- Catatan -->
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Tambahan (opsional)</label>
                                <input
                                    type="text"
                                    wire:model="servisCatatan"
                                    placeholder="Contoh: No ref transfer / garansi khusus..."
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white border border-white/10"
                                />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex gap-3 pt-3 border-t border-white/5 mt-4">
                    <button wire:click="tutupBayarServisModal" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[40px]">
                        Tutup
                    </button>
                    @if($selectedServisDetail)
                        <button wire:click="prosesBayarServis" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white font-bold text-xs cursor-pointer min-h-[40px] shadow-sm shadow-up-primary/30">
                            Proses Bayar & Struk POS
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail transaksi (dibuka dari header struk) --}}
    @include('partials.riwayat-modal')
</div>
