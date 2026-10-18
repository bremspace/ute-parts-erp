<div
    class="flex flex-col lg:flex-row gap-4 h-[calc(100dvh-8.5rem)]"
    x-data="{
        cartOpen: false,
        flexSet: {},
        posViewMode: localStorage.getItem('ute-pos-view-mode') || 'grid',
        setPosView(mode) {
            this.posViewMode = mode;
            try { localStorage.setItem('ute-pos-view-mode', mode); } catch (e) {}
        }
    }"
    @keydown.window.f4.prevent="$wire.openPaymentModal()"
    @keydown.window.f6.prevent="$wire.tahanTransaksi()"
    @keydown.window.escape.prevent="$wire.clearCart()"
>
    <!-- LEFT COLUMN: Product Catalog & Search -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
        <!-- PWA Install Prompt Micro-Banner (POS) -->
        <x-prism.pwa-install-prompt variant="banner" class="mb-2 flex-shrink-0" />

        <!-- Top Toolbar: Search, Filter Toggle & Gudang Selector -->
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

            <!-- Tombol Atur Filter Preferensi -->
            <button type="button"
                    wire:click="$toggle('showCustomFilterSettings')"
                    class="px-3 py-2.5 rounded-xl border border-white/10 text-xs font-semibold text-ink-300 hover:text-white hover:bg-white/5 transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[42px]"
                    title="Atur checkbox filter kustom">
                <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span class="hidden sm:inline">Filter</span>
            </button>

            <!-- Quick Action: Pesanan Online (Pickup Kasir) -->
            <button type="button"
                    wire:click="bukaPesananOnlineModal"
                    class="px-3 py-2.5 rounded-xl border border-up-amber/30 bg-up-amber/10 hover:bg-up-amber/20 text-xs font-bold text-up-amber transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[42px] active:scale-[0.98]"
                    title="Terima pembayaran & serah terima pesanan online (Ambil di Toko)">
                <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span class="hidden md:inline">Pesanan Online</span>
                @if($jumlahPesananOnline > 0)
                    <span class="min-w-[18px] h-4.5 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums shadow-sm">{{ $jumlahPesananOnline }}</span>
                @endif
            </button>

            <!-- Quick Action: Bayar Servis -->
            <button type="button"
                    wire:click="bukaBayarServisModal"
                    class="px-3 py-2.5 rounded-xl border border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/20 text-xs font-bold text-blue-500 dark:text-blue-400 transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[42px] active:scale-[0.98]"
                    title="Proses pelunasan servis di kasir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                </svg>
                <span class="hidden xl:inline">Bayar Servis</span>
            </button>

            <!-- Screen Wake Lock Toggle (Kasir PWA) -->
            <div x-data="screenWakeLock()" x-init="init()" x-show="isSupported" class="flex-shrink-0" x-cloak>
                <button type="button" @click="toggle()"
                        :class="isActive ? 'bg-up-amber/20 border-up-amber/40 text-up-amber' : 'border-white/10 text-ink-400 hover:text-white hover:bg-white/5'"
                        class="px-2.5 py-2.5 rounded-xl border text-xs font-semibold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer min-h-[42px]"
                        :title="isActive ? 'Layar Terus Menyala (Aktif)' : 'Kunci Layar Tetap Menyala (PWA Kasir)'">
                    <span class="text-xs" x-text="isActive ? '☀️' : '🌙'"></span>
                    <span class="hidden xl:inline text-[11px]" x-text="isActive ? 'Layar Aktif' : 'Standby'"></span>
                </button>
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

        <!-- Custom Filter Preferences Drawer -->
        @if($showCustomFilterSettings)
            <div class="p-3 mb-2 rounded-2xl bg-white/[0.03] border border-white/10 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-semibold text-ink-300">Tampilkan Filter:</span>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-kategori-{{ $visibleFilters['kategori'] ? '1' : '0' }}" wire:click="toggleFilter('kategori')" @checked($visibleFilters['kategori']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Kategori</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-brand-{{ $visibleFilters['brand'] ? '1' : '0' }}" wire:click="toggleFilter('brand')" @checked($visibleFilters['brand']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Brand</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-kualitas-{{ $visibleFilters['kualitas'] ? '1' : '0' }}" wire:click="toggleFilter('kualitas')" @checked($visibleFilters['kualitas']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Kualitas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-tipe_hp-{{ $visibleFilters['tipe_hp'] ? '1' : '0' }}" wire:click="toggleFilter('tipe_hp')" @checked($visibleFilters['tipe_hp']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Tipe HP</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-brand_kompatibel-{{ $visibleFilters['brand_kompatibel'] ? '1' : '0' }}" wire:click="toggleFilter('brand_kompatibel')" @checked($visibleFilters['brand_kompatibel']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Brand Kompatibel</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-model_kompatibel-{{ $visibleFilters['model_kompatibel'] ? '1' : '0' }}" wire:click="toggleFilter('model_kompatibel')" @checked($visibleFilters['model_kompatibel']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Model Kompatibel</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-kondisi-{{ $visibleFilters['kondisi'] ? '1' : '0' }}" wire:click="toggleFilter('kondisi')" @checked($visibleFilters['kondisi']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Kondisi</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-satuan-{{ $visibleFilters['satuan'] ? '1' : '0' }}" wire:click="toggleFilter('satuan')" @checked($visibleFilters['satuan']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Satuan</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-abc_class-{{ $visibleFilters['abc_class'] ? '1' : '0' }}" wire:click="toggleFilter('abc_class')" @checked($visibleFilters['abc_class']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Kelas ABC</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:key="pos-chk-stok-{{ $visibleFilters['stok'] ? '1' : '0' }}" wire:click="toggleFilter('stok')" @checked($visibleFilters['stok']) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                        <span>Hanya Ready Stok</span>
                    </label>
                </div>
                <button type="button"
                        wire:click="resetCustomFilters"
                        class="text-xs text-up-red hover:underline cursor-pointer">
                    Reset ke Default
                </button>
            </div>
        @endif

        <!-- Active Filter Bar Dinamis -->
        @if(collect($visibleFilters)->contains(true))
            <div class="flex items-center gap-2.5 flex-wrap mb-2 flex-shrink-0">
                @if($visibleFilters['kategori'])
                    <div class="w-36 sm:w-44">
                        <select wire:model.live="filterKategoriId" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Semua Kategori</option>
                            @foreach($kategoriList as $parent)
                                <option value="{{ $parent->id }}" class="font-bold text-white bg-ink-900">{{ $parent->nama }}</option>
                                @foreach($parent->children as $child)
                                    <option value="{{ $child->id }}" class="text-ink-300 bg-ink-900">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['brand'])
                    <div class="w-32 sm:w-36">
                        <select wire:model.live="filterBrandId" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Semua Brand</option>
                            @foreach($brandList as $b)
                                <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['kualitas'])
                    <div class="w-32 sm:w-36">
                        <select wire:model.live="filterKualitasId" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Semua Kualitas</option>
                            @foreach($kualitasList as $k)
                                <option value="{{ $k->id }}" class="bg-ink-900">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['tipe_hp'])
                    <div class="w-36 sm:w-44">
                        <select wire:model.live="filterTipeHpId" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Semua Tipe HP</option>
                            @foreach($tipeHpList as $th)
                                <option value="{{ $th->id }}" class="bg-ink-900">{{ $th->merk }} - {{ $th->model }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['brand_kompatibel'])
                    <div class="w-36 sm:w-44">
                        <select wire:model.live="filterBrandKompatibel" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Brand Kompatibel</option>
                            @foreach($brandKompatibelList as $bk)
                                <option value="{{ $bk }}" class="bg-ink-900">{{ $bk }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['model_kompatibel'])
                    <div class="w-36 sm:w-44">
                        <select wire:model.live="filterModelKompatibel" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Model Kompatibel</option>
                            @foreach($modelKompatibelList as $mk)
                                <option value="{{ $mk }}" class="bg-ink-900">{{ $mk }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['kondisi'])
                    <div class="w-28 sm:w-36">
                        <select wire:model.live="filterKondisi" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Semua Kondisi</option>
                            @foreach($kondisiList as $kd)
                                <option value="{{ $kd['kode'] }}" class="bg-ink-900">{{ $kd['nama'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['satuan'])
                    <div class="w-28 sm:w-36">
                        <select wire:model.live="filterSatuan" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Semua Satuan</option>
                            @foreach($satuanList as $su)
                                <option value="{{ $su->kode }}" class="bg-ink-900">{{ $su->nama }} ({{ $su->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($visibleFilters['abc_class'])
                    <div class="w-28 sm:w-32">
                        <select wire:model.live="filterAbcClass" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                            <option value="">Kelas ABC</option>
                            <option value="A" class="bg-ink-900">Kelas A</option>
                            <option value="B" class="bg-ink-900">Kelas B</option>
                            <option value="C" class="bg-ink-900">Kelas C</option>
                        </select>
                    </div>
                @endif

                @if($visibleFilters['stok'])
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs text-ink-200 cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="filterHanyaReadyStok" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-up-mint/30">
                        <span>Hanya Ready Stok</span>
                    </label>
                @endif
            </div>
        @endif

        <!-- [T-51] Keyboard Shortcuts & View Mode Switcher (Grid / List Cepat) -->
        <div class="flex items-center justify-between gap-2 mb-2 text-[11px] text-ink-400">
            <!-- Shortcut Badge (Desktop Only) -->
            <div class="hidden lg:flex items-center gap-2">
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

            <!-- Kasir View Mode Switcher (Aktif di Mobile & Desktop) -->
            <div class="inline-flex items-center p-0.5 rounded-lg bg-black/10 dark:bg-white/5 border border-black/10 dark:border-white/10 ml-auto">
                <button type="button" @click="setPosView('grid')" :class="posViewMode === 'grid' ? 'bg-up-primary text-white font-bold shadow-sm' : 'text-ink-400 hover:text-white'" class="px-2.5 py-1 rounded-md text-[11px] flex items-center gap-1 cursor-pointer transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Grid</span>
                </button>
                <button type="button" @click="setPosView('list')" :class="posViewMode === 'list' ? 'bg-up-primary text-white font-bold shadow-sm' : 'text-ink-400 hover:text-white'" class="px-2.5 py-1 rounded-md text-[11px] flex items-center gap-1 cursor-pointer transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>List Cepat</span>
                </button>
            </div>
        </div>

        <!-- Product Grid & List Container -->
        <div class="flex-1 overflow-y-auto pr-1">
            <!-- Mode 1: Grid -->
            <div x-show="posViewMode === 'grid'" class="grid grid-cols-1 xxs:grid-cols-2 xl:grid-cols-3 gap-3">
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
                            class="glass-panel glass-panel-hover p-3.5 rounded-2xl flex flex-col justify-between cursor-pointer border border-white/5 transition-[border-color,box-shadow,transform] duration-140 select-none group active:scale-[0.98]"
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
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-white/5 border border-white/10 flex-shrink-0 flex items-center justify-center relative">
                                    @if($prod->thumbnail_url ?: $prod->gambar)
                                        <img src="{{ $prod->thumbnail_url ?: $prod->gambar }}" alt="{{ $prod->nama }}" loading="lazy" class="w-full h-full object-cover"
                                             onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                    @endif
                                    <span class="text-[9px] font-bold text-ink-400 items-center justify-center w-full h-full" style="{{ ($prod->thumbnail_url ?: $prod->gambar) ? 'display:none;' : 'display:flex;' }}">UTE</span>
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
                                <span class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center text-ink-300 group-hover:bg-up-primary group-hover:text-white transition-[background-color,color] duration-140 text-xs">
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

            <!-- Mode 2: List Cepat Kasir -->
            <div x-show="posViewMode === 'list'" class="space-y-1.5" x-cloak>
                @forelse($products as $prod)
                    @php
                        $stokTotal = $stokPerProduk[$prod->id] ?? (\App\Modules\Pos\Livewire\PosKasir::STOK_TANPA_GUDANG);
                        $stokKosong = $stokTotal <= 0;
                        $pricing = app(\App\Modules\Pos\Services\PricingService::class)->resolve($prod, $customer);
                    @endphp
                    <div
                        wire:key="product-list-{{ $prod->id }}"
                        @if($stokKosong)
                            class="px-3.5 py-2.5 rounded-xl border border-white/5 bg-ink-900/60 opacity-50 flex items-center justify-between gap-3 cursor-not-allowed select-none"
                        @else
                            wire:click="addToCart({{ $prod->id }})"
                            class="px-3.5 py-2.5 rounded-xl border border-white/5 bg-ink-900/80 hover:bg-white/5 hover:border-up-primary/40 flex items-center justify-between gap-3 cursor-pointer select-none transition-colors group active:scale-[0.99]"
                        @endif
                    >
                        <div class="min-w-0 flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $stokKosong ? 'bg-up-red' : 'bg-up-mint' }}"></span>
                            <div class="truncate">
                                <h4 class="text-xs font-semibold text-white group-hover:text-up-primary transition-colors truncate">
                                    {{ $prod->nama }}
                                </h4>
                                <span class="text-[10px] text-ink-400 font-mono">{{ $prod->sku }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 flex-shrink-0">
                            <span class="text-xs font-semibold tabular-nums {{ $stokKosong ? 'text-up-red' : 'text-ink-300' }}">
                                {{ $stokKosong ? 'Habis' : $stokTotal . ' unit' }}
                            </span>
                            <span class="text-xs sm:text-sm font-bold text-up-mint tabular-nums">
                                Rp {{ number_format($pricing['harga'], 0, ',', '.') }}
                            </span>
                            <button type="button" class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center text-xs text-ink-300 group-hover:bg-up-primary group-hover:text-white" :disabled="{{ $stokKosong ? 'true' : 'false' }}">
                                +
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-ink-500">Tidak ada produk ditemukan.</div>
                @endforelse
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
        <div class="lg:hidden fixed inset-x-3 z-35 flex items-center justify-center pointer-events-none"
             style="bottom: calc(4.75rem + max(env(safe-area-inset-bottom, 0px), 0.25rem));">
            <div class="w-full max-w-sm pointer-events-auto flex items-center gap-2 p-1.5 rounded-2xl bg-white/95 dark:bg-[#0F172A]/95 backdrop-blur-2xl border border-ink-200/80 dark:border-white/10 shadow-[0_10px_30px_rgba(0,0,0,0.12)] dark:shadow-[0_16px_36px_rgba(0,0,0,0.6)]">
                <button @click="cartPanelOpen = !cartPanelOpen"
                        type="button"
                        class="flex-1 flex items-center justify-between px-3.5 py-2 rounded-xl bg-ink-100/70 hover:bg-ink-100 dark:bg-white/5 dark:hover:bg-white/10 text-ink-900 dark:text-white min-h-[44px] transition-colors cursor-pointer"
                        :aria-expanded="cartPanelOpen"
                        aria-label="Buka ringkasan keranjang">
                    <div class="flex items-center gap-2.5">
                        <div class="relative flex items-center justify-center">
                            <div class="w-8 h-8 rounded-lg bg-up-primary/10 dark:bg-up-primary/20 flex items-center justify-center text-up-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <span class="absolute -top-1.5 -right-2 px-1.5 py-0.5 rounded-full bg-up-primary text-white text-[10px] font-bold tabular-nums shadow-sm shadow-up-primary/40">
                                {{ count($cart) }}
                            </span>
                        </div>
                        <div class="flex flex-col text-left">
                            <span class="text-xs font-bold leading-tight">Keranjang</span>
                            <span class="text-[10px] text-ink-500 dark:text-ink-400 font-medium">{{ count($cart) }} item</span>
                        </div>
                    </div>
                    <span class="text-xs sm:text-sm font-bold text-up-mint tabular-nums">
                        Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                    </span>
                </button>
                @if(count($cart) > 0)
                    <button wire:click="openPaymentModal"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:from-teal-500 hover:to-up-mint text-ink-950 font-bold text-xs shadow-md shadow-up-mint/25 active:scale-[0.96] min-h-[44px] flex items-center gap-1.5 cursor-pointer transition-all">
                        <span>Bayar</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                @else
                    <button wire:click="bukaPesananOnlineModal"
                            type="button"
                            class="px-3.5 py-2 rounded-xl bg-up-amber/15 hover:bg-up-amber/25 text-up-amber border border-up-amber/30 font-bold text-xs min-h-[44px] flex items-center gap-1.5 cursor-pointer transition-all active:scale-[0.96]"
                            title="Pesanan Online (Pickup)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Pickup</span>
                        @if($jumlahPesananOnline > 0)
                            <span class="px-1.5 py-0.5 rounded-full bg-up-amber text-ink-950 text-[10px] font-black tabular-nums">{{ $jumlahPesananOnline }}</span>
                        @endif
                    </button>
                @endif
            </div>
        </div>

        <!-- Mobile: Bottom Sheet Overlay + Panel -->
        <div x-show="cartPanelOpen && window.innerWidth < 1024"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-ink-950/70 backdrop-blur-sm z-50 lg:hidden"
             @click="cartPanelOpen = false"
             aria-hidden="true"></div>

        <aside x-show="cartPanelOpen && window.innerWidth < 1024"
               x-transition:enter="transition-transform ease-out duration-250"
               x-transition:enter-start="translate-y-full"
               x-transition:enter-end="translate-y-0"
               x-transition:leave="transition-transform ease-in duration-200"
               x-transition:leave-start="translate-y-0"
               x-transition:leave-end="translate-y-full"
               class="fixed bottom-0 left-0 right-0 z-50 max-h-[88vh] lg:hidden"
               @click.outside="cartPanelOpen = false"
               style="padding-bottom: env(safe-area-inset-bottom, 0);">
            <div class="bg-white dark:bg-[#0B1120] border-t border-x border-ink-200/80 dark:border-white/10 rounded-t-3xl p-4 sm:p-5 shadow-2xl max-h-[88vh] flex flex-col">
                <!-- Drag Handle -->
                <div class="w-12 h-1.5 bg-ink-300 dark:bg-white/20 rounded-full mx-auto mb-3.5"></div>
                
                <!-- Drawer Header -->
                <div class="flex items-center justify-between mb-3 pb-3 border-b border-ink-200/60 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Keranjang Penjualan</h3>
                        <span class="px-2 py-0.5 rounded-full bg-up-primary/10 dark:bg-up-primary/20 text-up-primary text-[11px] font-bold tabular-nums">
                            {{ count($cart) }} item
                        </span>
                    </div>
                    <button @click="cartPanelOpen = false"
                            class="p-2 text-ink-400 hover:text-ink-900 dark:hover:text-white rounded-xl hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer"
                            aria-label="Tutup keranjang">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Customer Picker in Mobile Sheet -->
                <div class="mb-3 pb-3 border-b border-ink-200/60 dark:border-white/10">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-[11px] font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Pelanggan / Member</label>
                        @if($customer)
                            <x-prism.tier-badge :tier="$customer->tierMembership?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail')" />
                        @endif
                    </div>
                    <select
                        wire:model.live="selectedCustomerId"
                        wire:change="setPelanggan($event.target.value)"
                        class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"
                    >
                        <option value="" class="bg-ink-900">Pelanggan Umum (Tanpa Member)</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" class="bg-ink-900">{{ $c->nama }} ({{ $c->telepon ?? '-' }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Scrollable Cart Content -->
                <div class="flex-1 overflow-y-auto space-y-3 pr-0.5">
                    @include('modules.pos.livewire.partials.cart-content', [
                        'cart' => $cart,
                        'total' => $total,
                        'diskonTotal' => $diskonTotal,
                        'totalBayar' => $totalBayar,
                        'customer' => $customer,
                        'canHargaFleksibel' => $canHargaFleksibel,
                    ])

                    <!-- Mobile Sesi Kas & Actions Shortcut -->
                    <div class="pt-3 border-t border-ink-200/60 dark:border-white/5 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            @if($kasAktif)
                                <div class="flex items-center gap-1.5 text-up-mint">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                                    <span>Kas aktif</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="bukaMutasiKasModal('keluar')" class="text-[11px] font-bold text-up-accent hover:text-orange-400 cursor-pointer">± Mutasi Kas</button>
                                    <span class="text-ink-300 dark:text-white/20">|</span>
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

                        <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                            <button wire:click="bukaPesananOnlineModal" class="py-2.5 px-3 rounded-xl bg-up-amber/10 border border-up-amber/25 text-up-amber hover:bg-up-amber/20 font-bold flex items-center justify-center gap-1.5 cursor-pointer transition active:scale-[0.98]">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                <span>Pesanan Online</span>
                                @if($jumlahPesananOnline > 0)
                                    <span class="min-w-[16px] h-4 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums">{{ $jumlahPesananOnline }}</span>
                                @endif
                            </button>
                            <button wire:click="bukaBayarServisModal" class="py-2.5 px-3 rounded-xl bg-blue-500/10 border border-blue-500/25 text-blue-500 dark:text-blue-400 hover:bg-blue-500/20 font-bold flex items-center justify-center gap-1.5 cursor-pointer transition active:scale-[0.98]">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                                <span>Bayar Servis</span>
                            </button>
                        </div>
                        <div class="flex items-center justify-end pt-0.5 text-[11px]">
                            <a href="/app/pos/riwayat" class="text-up-primary hover:underline font-semibold flex items-center gap-1 cursor-pointer">
                                <span>📋 Riwayat Transaksi</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Desktop: Side Panel Content -->
        <div class="hidden lg:flex lg:flex-col lg:h-full lg:bg-white lg:dark:bg-[#0F172A]/70 lg:backdrop-blur-md lg:border lg:border-ink-200/60 lg:dark:border-white/5 lg:rounded-2xl lg:p-5 lg:overflow-hidden lg:shadow-sm">
            <!-- Customer Selector -->
            <div class="mb-4 pb-3.5 border-b border-ink-200/60 dark:border-white/5 flex-shrink-0">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Pelanggan / Member</label>
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
                            class="px-2.5 py-2 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white text-xs transition-colors cursor-pointer"
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
                    class="p-3 rounded-xl bg-ink-50/70 dark:bg-white/[0.03] border border-ink-200/60 dark:border-white/5 flex items-center justify-between gap-3 transition-colors"
                >
                    <div class="flex-1 min-w-0">
                        <h5 class="text-xs font-semibold text-ink-900 dark:text-white truncate">{{ $item['nama'] }}</h5>
                        <div class="flex items-center gap-2 mt-0.5 text-[11px] flex-wrap">
                            @if(isset($item['flex']) && $item['flex'])
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-up-amber/20 text-up-amber text-[10px] font-medium border border-up-amber/30">
                                    Fleksibel
                                </span>
                            @endif
                            @if($item['varian'] !== 'Standar')
                                <span class="px-1.5 py-0.2 rounded bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 text-[10px]">{{ $item['varian'] }}</span>
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
                                    :class="flexSet['{{ $key }}'] ? 'text-ink-900 dark:text-white' : 'text-up-amber'"
                                    class="tabular-nums font-semibold text-xs"
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
                                    <span class="px-2 py-1 rounded-lg text-[10px] font-medium text-ink-500 bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5 cursor-not-allowed" title="Akses superadmin diperlukan">
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
                                    class="absolute z-20 mt-1 min-w-[200px] glass-panel p-3 rounded-xl border border-black/10 dark:border-white/10 shadow-lg"
                                    style="max-width: 240px;"
                                    x-cloak
                                >
                                    <label class="block text-[10px] font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Harga Jual (Rp)</label>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        x-model="flexHarga"
                                        @keydown.enter.prevent="saveFlexHarga('{{ $key }}')"
                                        x-ref="flexInput"
                                        placeholder="0"
                                        class="w-full px-3 py-2 rounded-lg glass-input text-sm font-bold tabular-nums text-ink-900 dark:text-white"
                                        aria-label="Harga jual fleksibel"
                                    />
                                    @if(canSeeField('harga_beli'))
                                    <p class="text-[10px] text-up-amber/90 mt-1.5">Min: Rp {{ number_format($item['harga_beli'] ?? $item['harga'], 0, ',', '.') }}</p>
@else
                                    <p class="text-[10px] text-up-amber/90 mt-1.5">Min: —</p>
@endif
                                    <div class="flex gap-2 mt-2.5">
                                        <button
                                            type="button"
                                            @click="saveFlexHarga('{{ $key }}')"
                                            class="flex-1 py-2 rounded-lg bg-up-amber hover:opacity-90 text-ink-950 font-bold text-xs transition-[opacity,transform] duration-100 active:scale-[0.98] cursor-pointer min-h-[40px]"
                                        >
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            @click="flexOpen = false"
                                            class="flex-1 py-2 rounded-lg bg-black/5 hover:bg-black/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[40px] border border-black/5 dark:border-white/5"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            @else
                                <span class="tabular-nums font-semibold text-ink-700 dark:text-ink-300">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                            @endif
                        </div>

                        {{-- [F2-3] SN wajib utk produk sn=true --}}
                        @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                    </div>

                    <!-- Quantity Controls -->
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <button
                            wire:click="updateQty('{{ $key }}', -1)"
                            class="w-7 h-7 rounded-lg bg-black/5 hover:bg-black/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all active:scale-95 cursor-pointer"
                        >
                            -
                        </button>
                        <span class="w-8 text-center text-xs font-bold text-ink-900 dark:text-white tabular-nums">
                            {{ $item['qty'] }}
                        </span>
                        <button
                            wire:click="updateQty('{{ $key }}', 1)"
                            class="w-7 h-7 rounded-lg bg-black/5 hover:bg-black/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-800 dark:text-white flex items-center justify-center text-xs font-bold transition-all active:scale-95 cursor-pointer"
                        >
                            +
                        </button>
                    </div>

                    <!-- Line Subtotal & Delete -->
                    <div class="text-right min-w-[70px] flex-shrink-0">
                        <span class="text-xs font-bold text-ink-900 dark:text-white tabular-nums block">
                            Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                        </span>
                        <button
                            wire:click="removeFromCart('{{ $key }}')"
                            class="text-[10px] text-up-red/70 hover:text-up-red font-medium transition-colors cursor-pointer"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-center text-ink-400 py-12">
                    <svg class="w-10 h-10 mb-2 opacity-20 text-ink-900 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="text-xs font-medium text-ink-600 dark:text-ink-400">Keranjang belanja kosong</p>
                    <span class="text-[10px] text-ink-500 mt-1">Pilih produk di sebelah kiri atau tekan F2</span>
                </div>
            @endforelse
        </div>

        <!-- Summary & Actions Area (Fixed at bottom) -->
        <div class="border-t border-ink-200/60 dark:border-white/5 pt-4 flex-shrink-0 space-y-3">
            <div class="space-y-1.5 text-xs text-ink-600 dark:text-ink-300">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span class="font-semibold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                @if($diskonNominal > 0 || $diskonPersen > 0)
                    <div class="flex justify-between text-up-mint">
                        <span>Diskon</span>
                        <span class="font-semibold tabular-nums">- Rp {{ number_format($diskonTotal, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if($pajakNominal > 0)
                    <div class="flex justify-between text-ink-600 dark:text-ink-300">
                        <span>{{ $pajakNama ?? 'PPN' }} ({{ $ppnPersen }}%)</span>
                        <span class="font-semibold text-ink-900 dark:text-white tabular-nums">+ Rp {{ number_format($pajakNominal, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between items-center text-base font-bold text-ink-900 dark:text-white pt-2 border-t border-ink-200/60 dark:border-white/10">
                    <span>Total Akhir</span>
                    <span class="text-up-mint text-xl tabular-nums font-black">Rp {{ number_format($totalAkhir, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Action Buttons Grid -->
            <div class="grid grid-cols-3 gap-2 pt-1">
                <button
                    type="button"
                    wire:click="clearCart"
                    class="py-2.5 px-2 rounded-xl bg-ink-100 hover:bg-up-red/15 dark:bg-white/5 dark:hover:bg-up-red/20 text-ink-600 hover:text-up-red dark:text-ink-400 dark:hover:text-up-red font-semibold text-xs transition-colors border border-ink-200/60 dark:border-white/5 flex flex-col items-center justify-center gap-0.5 cursor-pointer min-h-[44px] active:scale-[0.97]"
                >
                    <span>Batal</span>
                    <span class="text-[10px] font-mono text-ink-400 dark:text-ink-500">ESC</span>
                </button>

                <button
                    type="button"
                    wire:click="tahanTransaksi"
                    class="py-2.5 px-2 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white font-semibold text-xs transition-colors border border-ink-200/60 dark:border-white/5 flex flex-col items-center justify-center gap-0.5 cursor-pointer min-h-[44px] active:scale-[0.97]"
                >
                    <span>Tahan</span>
                    <span class="text-[10px] font-mono text-ink-400 dark:text-ink-500">F6</span>
                </button>

                <button
                    type="button"
                    wire:click="openPaymentModal"
                    class="py-2.5 px-2 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark hover:to-indigo-700 text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-all border border-up-primary/40 flex flex-col items-center justify-center gap-0.5 cursor-pointer active:scale-95 min-h-[44px]"
                >
                    <span>BAYAR</span>
                    <span class="text-[10px] font-mono text-white/80">F4</span>
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
                        <span class="text-ink-300 dark:text-white/20">|</span>
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
            <div class="pt-2 border-t border-ink-200/60 dark:border-white/5 flex items-center justify-between">
                <button wire:click="$toggle('showDitahanPanel')" class="text-[11px] font-semibold text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white flex items-center gap-1 cursor-pointer">
                    <span>{{ $showDitahanPanel ? '▼' : '▶' }}</span>
                    Ditahan ({{ $ditahanList->count() }})
                </button>
                <div class="flex items-center gap-2.5">
                    <button wire:click="bukaPesananOnlineModal" class="text-[11px] font-bold text-up-amber hover:underline flex items-center gap-1 cursor-pointer" title="Terima pembayaran pesanan online (Ambil di Toko) di kasir">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        <span>Pesanan Online</span>
                        @if($jumlahPesananOnline > 0)
                            <span class="min-w-[16px] h-4 px-1 rounded-full bg-up-amber text-ink-950 text-[10px] font-black flex items-center justify-center tabular-nums">{{ $jumlahPesananOnline }}</span>
                        @endif
                    </button>
                    <span class="text-ink-300 dark:text-white/20">|</span>
                    <button wire:click="bukaBayarServisModal" class="text-[11px] font-bold text-blue-500 dark:text-blue-400 hover:underline flex items-center gap-1 cursor-pointer" title="Proses Pelunasan Servis di Kasir">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                        <span>Bayar Servis</span>
                    </button>
                    <span class="text-ink-300 dark:text-white/20">|</span>
                    <a href="/app/pos/riwayat" class="text-[11px] font-semibold text-up-primary hover:underline flex items-center gap-1 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Riwayat Transaksi</span>
                    </a>
                </div>
            </div>
            @if($showDitahanPanel && $ditahanList->isNotEmpty())
                <div class="mt-2 space-y-1.5 max-h-44 overflow-y-auto">
                    @foreach($ditahanList as $dt)
                        <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-ink-50 dark:bg-white/[0.03] border border-ink-200/60 dark:border-white/5 text-[11px]">
                            <div class="min-w-0">
                                <span class="font-mono text-ink-900 dark:text-white truncate block">{{ $dt->no_transaksi }}</span>
                                <span class="text-ink-500 dark:text-ink-400">Rp <span class="tabular-nums">{{ number_format($dt->total_akhir, 0, ',', '.') }}</span> · {{ $dt->kasir?->name }}</span>
                            </div>
                            <button wire:click="resumeDitahan({{ $dt->id }})" class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold cursor-pointer whitespace-nowrap">Lanjutkan</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @include('modules.pos.livewire.modals.payment-modal')

    <!-- RECEIPT MODAL (Thermal Print 58mm / 80mm) -->
    @include('partials.thermal-receipt-modal')

    @include('modules.pos.livewire.modals.riwayat-transaksi-modal')

    @include('modules.pos.livewire.modals.retur-modal')

    @include('modules.pos.livewire.modals.pelanggan-baru-modal')

    @include('modules.pos.livewire.modals.kas-modal')

    @include('modules.pos.livewire.modals.mutasi-kas-modal')

    <!-- ===== [POS-SERVIS] MODAL: PEMBAYARAN SERVIS DI KASIR POS ===== -->
    @include('modules.pos.livewire.modals.bayar-servis-modal')

    <!-- ===== [POS-ONLINE-PICKUP] MODAL: PEMBAYARAN PESANAN ONLINE (AMBIL DI TOKO) DI KASIR ===== -->
    @include('modules.pos.livewire.modals.pesanan-online-modal')


    {{-- [F1-4] Modal riwayat audit trail transaksi (dibuka dari header struk) --}}
    @include('partials.riwayat-modal')
</div>
