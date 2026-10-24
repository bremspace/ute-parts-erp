<div>
    <!-- Modal -->
    <div x-data="{ show: @entangle('isOpen') }"
         x-on:open-product-search-modal.window="show = true"
         x-on:close-product-search-modal.window="show = false"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-[70] overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true">

        <!-- Backdrop -->
        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
             wire:click="closeModal"
             @click="show = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="show"
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-[0.97]"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-[0.97]"
                 class="relative transform overflow-hidden rounded-3xl glass-panel border border-black/10 dark:border-white/10 text-left shadow-2xl transition-[opacity,transform] sm:my-8 sm:w-full sm:max-w-3xl bg-white/95 dark:bg-[#0e1017]/95 text-ink-900 dark:text-white">

                <!-- Header -->
                <div class="px-6 pt-6 pb-4 border-b border-black/10 dark:border-white/10 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white flex items-center gap-2" id="modal-title">
                            <span>Cari Produk</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary font-mono font-medium">Global</span>
                        </h3>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">
                            Cari produk berdasarkan nama, barcode, SKU, atau kompatibilitas tipe HP.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Tombol Set Filter Preferensi -->
                        <button type="button"
                                wire:click="$toggle('showCustomFilterSettings')"
                                class="px-2.5 py-1.5 rounded-xl border border-black/10 dark:border-white/10 text-xs font-medium text-ink-700 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition flex items-center gap-1.5 cursor-pointer"
                                title="Atur checkbox filter kustom">
                            <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            <span>Atur Filter</span>
                        </button>
                        <button type="button"
                                wire:click="closeModal"
                                @click="show = false"
                                class="p-1.5 rounded-lg text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Custom Filter Preferences Drawer / Accordion -->
                @if($showCustomFilterSettings)
                    <div class="px-6 py-3 bg-black/[0.02] dark:bg-white/[0.03] border-b border-black/10 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-xs font-semibold text-ink-700 dark:text-ink-300">Tampilkan Filter:</span>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-kategori-{{ $visibleFilters['kategori'] ? '1' : '0' }}" wire:click="toggleFilter('kategori')" @checked($visibleFilters['kategori']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kategori</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-brand-{{ $visibleFilters['brand'] ? '1' : '0' }}" wire:click="toggleFilter('brand')" @checked($visibleFilters['brand']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Brand</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-kualitas-{{ $visibleFilters['kualitas'] ? '1' : '0' }}" wire:click="toggleFilter('kualitas')" @checked($visibleFilters['kualitas']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kualitas</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-tipe_hp-{{ $visibleFilters['tipe_hp'] ? '1' : '0' }}" wire:click="toggleFilter('tipe_hp')" @checked($visibleFilters['tipe_hp']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Tipe HP</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-brand_kompatibel-{{ $visibleFilters['brand_kompatibel'] ? '1' : '0' }}" wire:click="toggleFilter('brand_kompatibel')" @checked($visibleFilters['brand_kompatibel']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Brand Kompatibel</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-model_kompatibel-{{ $visibleFilters['model_kompatibel'] ? '1' : '0' }}" wire:click="toggleFilter('model_kompatibel')" @checked($visibleFilters['model_kompatibel']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Model Kompatibel</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-kondisi-{{ $visibleFilters['kondisi'] ? '1' : '0' }}" wire:click="toggleFilter('kondisi')" @checked($visibleFilters['kondisi']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kondisi</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-satuan-{{ $visibleFilters['satuan'] ? '1' : '0' }}" wire:click="toggleFilter('satuan')" @checked($visibleFilters['satuan']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Satuan</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-abc_class-{{ $visibleFilters['abc_class'] ? '1' : '0' }}" wire:click="toggleFilter('abc_class')" @checked($visibleFilters['abc_class']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kelas ABC</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-800 dark:text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="psm-chk-stok-{{ $visibleFilters['stok'] ? '1' : '0' }}" wire:click="toggleFilter('stok')" @checked($visibleFilters['stok']) class="rounded border-black/20 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary/30">
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

                <!-- Search Input & Barcode Scan -->
                <div class="p-4 sm:p-6 pb-3 space-y-3">
                    @if($context === 'po' && $curatedSupplier)
                        <div class="flex items-center justify-between gap-2 p-1.5 rounded-2xl bg-white/[0.04] border border-white/10 flex-wrap">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button
                                    type="button"
                                    wire:click="setSupplierScope('curated')"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $supplierScope === 'curated' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-300 hover:text-white hover:bg-white/5' }}"
                                >
                                    <span>🏢</span>
                                    <span>Produk Supplier: <strong class="text-white">{{ $curatedSupplier->nama }}</strong></span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $supplierScope === 'curated' ? 'bg-white/20 text-white' : 'bg-white/5 text-ink-300' }}">
                                        {{ $curatedProductCount }}
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="setSupplierScope('all')"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $supplierScope === 'all' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-300 hover:text-white hover:bg-white/5' }}"
                                >
                                    <span>🌐</span>
                                    <span>Semua Produk (Lintas Supplier)</span>
                                </button>
                            </div>

                            @if($supplierScope === 'curated')
                                <span class="text-[11px] text-up-mint font-medium px-2 py-0.5 rounded-lg bg-up-mint/10 border border-up-mint/20 flex items-center gap-1">
                                    <span>✓</span> Terkurasi
                                </span>
                            @else
                                <span class="text-[11px] text-amber-400 font-medium px-2 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center gap-1">
                                    <span>⚠️</span> Semua Supplier
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg class="h-4 w-4 text-ink-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text"
                                   wire:model.live.debounce.250ms="search"
                                   wire:keydown.enter="scanBarcodeDirect($event.target.value)"
                                   class="w-full pl-10 pr-10 py-2.5 rounded-xl glass-input text-xs sm:text-sm text-ink-900 dark:text-white bg-white/90 dark:bg-ink-900/90 border border-black/10 dark:border-white/10 placeholder-ink-400 min-h-[44px] focus:ring-1 focus:ring-up-primary"
                                   placeholder="Ketik nama, barcode, SKU, tipe HP, atau scan..."
                                   autofocus>
                            @if(!empty($search))
                                <button type="button"
                                        wire:click="$set('search', '')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer">
                                    ✕
                                </button>
                            @endif
                        </div>

                        <!-- Tombol Scan Barcode Kamera -->
                        <button
                            type="button"
                            x-data="{}"
                            @click="if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                                window.uteBarcode.openCameraScanner({
                                    title: 'Scan Barcode Produk',
                                    continuous: false,
                                    onScan: (code) => { $wire.scanBarcodeDirect(code); }
                                });
                            } else {
                                alert('Modul kamera scanner sedang memuat...');
                            }"
                            class="px-3.5 py-2.5 rounded-xl bg-up-primary/15 hover:bg-up-primary/25 border border-up-primary/30 text-up-primary text-xs font-bold min-h-[44px] flex items-center gap-1.5 cursor-pointer shrink-0 transition active:scale-95"
                            title="Buka Kamera untuk Scan Barcode"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            <span class="hidden sm:inline">Scan</span>
                        </button>
                    </div>

                    <div wire:loading wire:target="search, scanBarcodeDirect" class="text-[11px] text-up-primary flex items-center gap-1.5 font-medium animate-pulse">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Mencari katalog produk...</span>
                    </div>

                    <!-- Filter Bar Dinamis (hanya tampil bila di-centang pengguna) -->
                    @if(collect($visibleFilters)->contains(true))
                        <div class="flex items-center gap-2.5 flex-wrap pt-1">
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
                                <div class="w-32 sm:w-40">
                                    <select wire:model.live="filterBrandId" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                                        <option value="">Semua Brand</option>
                                        @foreach($brandList as $b)
                                            <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($visibleFilters['kualitas'])
                                <div class="w-32 sm:w-40">
                                    <select wire:model.live="filterKualitasId" class="w-full px-2.5 py-1.5 rounded-xl glass-input text-xs text-ink-200 bg-ink-900/90 border border-white/10">
                                        <option value="">Semua Kualitas</option>
                                        @foreach($kualitasList as $k)
                                            <option value="{{ $k->id }}" class="bg-ink-900">{{ $k->nama }}{{ $k->brand_id ? ' ('.$k->brand?->nama.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($visibleFilters['tipe_hp'])
                                <div class="w-40 sm:w-48">
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
                </div>

                <!-- Product List -->
                <div class="max-h-96 overflow-y-auto px-6 pb-4 space-y-1.5">
                    @if($products->isEmpty())
                        <div class="text-center py-10">
                            <svg class="mx-auto h-10 w-10 text-ink-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <h4 class="mt-2 text-sm font-semibold text-white">Tidak ada produk ditemukan</h4>
                            @if($context === 'po' && $curatedSupplier && $supplierScope === 'curated')
                                <p class="mt-1 text-xs text-ink-400">Tidak ada produk terdaftar untuk supplier <strong>{{ $curatedSupplier->nama }}</strong> dengan kata kunci ini.</p>
                                <button type="button" wire:click="setSupplierScope('all')" class="mt-3 px-3 py-1.5 rounded-xl bg-up-primary/20 hover:bg-up-primary text-up-primary hover:text-white text-xs font-bold border border-up-primary/30 transition cursor-pointer">
                                    🌐 Cari di Semua Produk (Lintas Supplier)
                                </button>
                            @else
                                <p class="mt-1 text-xs text-ink-400">Coba kata kunci atau sesuaikan filter Anda.</p>
                            @endif
                        </div>
                    @else
                        <div class="divide-y divide-white/5">
                            @foreach($products as $product)
                                @php
                                    $sku = $product->skuVariants->first()?->sku;
                                    $barcode = $product->barcode ?: $product->skuVariants->first()?->barcode;
                                    $stokTotal = (int) ($product->total_stok ?? 0);
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3 px-3.5 rounded-2xl hover:bg-white/5 border border-transparent hover:border-white/5 cursor-pointer transition group"
                                     wire:click="pilihProduk({{ $product->id }})">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs sm:text-sm font-semibold text-white group-hover:text-up-primary transition">
                                            {{ $product->nama }}
                                        </p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-ink-400">
                                            @if($barcode)
                                                <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-0.5 text-[10px] sm:text-[11px] font-mono text-ink-300">
                                                    {{ $barcode }}
                                                </span>
                                            @endif
                                            @if($sku)
                                                <span class="inline-flex items-center rounded-md bg-up-primary/20 text-up-primary border border-up-primary/30 px-2 py-0.5 text-[10px] sm:text-[11px] font-mono font-bold">
                                                    {{ $sku }}
                                                </span>
                                            @endif
                                            @if($product->brand)
                                                <span class="inline-flex items-center rounded-md bg-white/5 px-2 py-0.5 text-[10px] sm:text-[11px] text-ink-300">
                                                    {{ $product->brand->nama }}
                                                </span>
                                            @endif
                                            @if($product->kategoriRelasi)
                                                <span class="inline-flex items-center rounded-md bg-white/5 px-2 py-0.5 text-[10px] sm:text-[11px] text-ink-400">
                                                    {{ $product->kategoriRelasi->nama }}
                                                </span>
                                            @endif
                                            @if($product->kualitas)
                                                <span class="inline-flex items-center rounded-md bg-amber-500/10 text-amber-400 border border-amber-500/20 px-2 py-0.5 text-[10px] sm:text-[11px]">
                                                    {{ $product->kualitas->nama }}
                                                </span>
                                            @endif
                                            @if($product->supplier)
                                                @if($curatedSupplierId && $product->supplier_id === $curatedSupplierId)
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-up-mint/15 text-up-mint border border-up-mint/30 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold" title="Default Supplier: {{ $product->supplier->nama }}">
                                                        <span>🏢</span> {{ $product->supplier->nama }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-white/10 text-ink-300 border border-white/10 px-2 py-0.5 text-[10px] sm:text-[11px]" title="Default Supplier: {{ $product->supplier->nama }}">
                                                        <span>🏢</span> {{ $product->supplier->nama }}
                                                    </span>
                                                @endif
                                            @endif
                                            @if($product->satuanKonversi && $product->satuanKonversi->isNotEmpty())
                                                <span class="inline-flex items-center rounded-md bg-up-primary/10 text-up-primary border border-up-primary/20 px-2 py-0.5 text-[10px] sm:text-[11px]" title="{{ $product->satuanKonversi->map(fn($sk) => $sk->keterangan_konversi)->join(' | ') }}">
                                                    +{{ $product->satuanKonversi->count() }} Satuan
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-white/5">
                                        <div class="text-left sm:text-right">
                                            <p class="text-xs sm:text-sm font-bold text-white tabular-nums">
                                                Rp {{ number_format($product->harga_beli, 0, ',', '.') }}
                                            </p>
                                            <p class="text-[11px] {{ $stokTotal > 0 ? 'text-up-mint font-semibold' : 'text-up-red font-medium' }} tabular-nums">
                                                Stok: {{ $stokTotal }} unit
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click.stop="pilihProduk({{ $product->id }})"
                                            class="px-3 py-1.5 rounded-xl bg-up-primary/20 hover:bg-up-primary text-up-primary hover:text-white border border-up-primary/30 text-xs font-bold transition min-h-[38px] sm:min-h-0 flex items-center justify-center cursor-pointer active:scale-95"
                                        >
                                            + Pilih
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Pagination -->
                @if($products->hasPages())
                    <div class="px-6 py-3 border-t border-white/5">
                        {{ $products->links() }}
                    </div>
                @endif

                <!-- Footer -->
                <div class="bg-white/[0.02] border-t border-white/10 px-6 py-3.5 flex justify-between items-center">
                    <span class="text-xs text-ink-400">
                        Preferensi filter tersimpan otomatis per akun.
                    </span>
                    <button type="button"
                            wire:click="closeModal"
                            @click="show = false"
                            class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.97]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
