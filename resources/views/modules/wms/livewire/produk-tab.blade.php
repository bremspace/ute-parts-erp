@php
    $kondisiMeta = [
        'baru' => ['bg' => 'bg-emerald-500/15', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'dot' => 'bg-emerald-400', 'desc' => 'Baru (Segel Pabrik / Fresh Stock)'],
        'oem' => ['bg' => 'bg-blue-500/15', 'border' => 'border-blue-500/30', 'text' => 'text-blue-400', 'dot' => 'bg-blue-400', 'desc' => 'OEM (Original Equipment Manufacturer)'],
        'compatible' => ['bg' => 'bg-cyan-500/15', 'border' => 'border-cyan-500/30', 'text' => 'text-cyan-400', 'dot' => 'bg-cyan-400', 'desc' => 'Compatible (Part Pihak Ketiga Berkualitas)'],
        'bekas' => ['bg' => 'bg-amber-500/15', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'dot' => 'bg-amber-400', 'desc' => 'Bekas / Copotan Unit Mesin Normal'],
        'rusak' => ['bg' => 'bg-rose-500/15', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'dot' => 'bg-rose-400', 'desc' => 'Rusak / Bahan Kanibal Part Teknisi'],
    ];
@endphp

<div x-data="{
        wmsViewMode: localStorage.getItem('ute-wms-view-mode') || 'table',
        setWmsView(mode) {
            this.wmsViewMode = mode;
            try { localStorage.setItem('ute-wms-view-mode', mode); } catch (e) {}
        },
        ...kameraProdukHandler()
     }">
    <!-- TAB 1b: MASTER PRODUK -->
        <div class="space-y-4">
            <!-- Search & Filters -->
            <div class="space-y-3">
                <!-- Baris 1: Search Box (Full Width agar tidak menumpuk dengan filter) -->
                <div class="w-full">
                    <x-prism.barcode-scan-input placeholder="Cari produk: nama, brand, model, SKU, barcode..." model="produkSearch" title="Scan Barcode Master Produk" />
                </div>

                <!-- Baris 2: Toolbar Filter & Kontrol Tampilan (Baris Baru) -->
                <div class="flex flex-wrap items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-0.5 flex-wrap">
                        <!-- Tombol Atur Filter Kustom -->
                        <button type="button"
                                wire:click="$toggle('showCustomFilterSettings')"
                                class="px-2.5 py-2 rounded-xl border border-white/10 text-xs font-medium text-ink-300 hover:text-white hover:bg-white/5 transition flex items-center gap-1.5 min-h-[40px] cursor-pointer whitespace-nowrap {{ $showCustomFilterSettings ? 'bg-up-primary/20 text-up-primary border-up-primary/40' : '' }}"
                                title="Atur checkbox filter kustom">
                            <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            <span class="hidden sm:inline">Filter</span>
                            @if($this->activeFilterCount > 0)
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-up-primary text-white font-bold animate-pulse">
                                    {{ $this->activeFilterCount }}
                                </span>
                            @endif
                        </button>

                        <!-- Tombol Atur Kolom Master Produk -->
                        <button type="button"
                                wire:click="$toggle('showColumnSettings')"
                                class="px-2.5 py-2 rounded-xl border border-white/10 text-xs font-semibold flex items-center gap-1.5 transition active:scale-95 cursor-pointer whitespace-nowrap min-h-[40px] {{ $showColumnSettings ? 'bg-up-primary/20 text-up-primary border-up-primary/40' : 'bg-white/5 text-ink-300 hover:text-white hover:bg-white/10' }}"
                                title="Sesuaikan Kolom Tabel Master Produk">
                            <span>📐</span>
                            <span class="hidden sm:inline">Kolom</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10 text-ink-200 font-bold">
                                {{ count(array_filter($visibleColumns)) }}
                            </span>
                        </button>

                        @if($visibleFilters['kategori'] ?? true)
                            <select wire:model.live="filterKategoriId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Semua Kategori</option>
                                @foreach($kategoriTree as $parent)
                                    <option value="{{ $parent->id }}" class="bg-ink-900 font-bold text-white">{{ $parent->nama }}</option>
                                    @foreach($parent->children as $child)
                                        <option value="{{ $child->id }}" class="bg-ink-900 text-ink-300">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        @endif

                        @if($visibleFilters['brand'] ?? true)
                            <select wire:model.live="filterBrandId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Semua Brand Part</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if($visibleFilters['kualitas'] ?? false)
                            <select wire:model.live="filterKualitasId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Semua Kualitas</option>
                                @foreach($kualitasList as $k)
                                    <option value="{{ $k->id }}" class="bg-ink-900">{{ $k->nama }}{{ $k->brand_id ? ' ('.$k->brand?->nama.')' : '' }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if(($visibleFilters['brand_kompatibel'] ?? false) || ($visibleFilters['merk_hp'] ?? false))
                            <select wire:model.live="filterBrandKompatibel" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]" title="Filter berdasarkan Merk Smartphone Kompatibel">
                                <option value="">Semua Merk HP</option>
                                @foreach($brandKompatibelList as $bk)
                                    <option value="{{ $bk }}" class="bg-ink-900">{{ $bk }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if(($visibleFilters['model_kompatibel'] ?? false) || ($visibleFilters['tipe_hp'] ?? false))
                            <select wire:model.live="filterModelKompatibel" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]" title="Filter berdasarkan Tipe/Model Smartphone Kompatibel">
                                <option value="">{{ $filterBrandKompatibel ? 'Semua Tipe '.$filterBrandKompatibel : 'Semua Tipe HP' }}</option>
                                @foreach($modelKompatibelList as $mk)
                                    <option value="{{ $mk }}" class="bg-ink-900">{{ $mk }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if($visibleFilters['kondisi'] ?? false)
                            <select wire:model.live="filterKondisi" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Semua Kondisi</option>
                                @foreach($kondisiList as $kd)
                                    <option value="{{ $kd['kode'] }}" class="bg-ink-900">{{ $kd['nama'] }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if($visibleFilters['satuan'] ?? false)
                            <select wire:model.live="filterSatuan" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Semua Satuan</option>
                                @foreach($satuanUnits as $su)
                                    <option value="{{ $su->kode }}" class="bg-ink-900">{{ $su->nama }} ({{ $su->kode }})</option>
                                @endforeach
                            </select>
                        @endif

                        @if($visibleFilters['abc_class'] ?? false)
                            <select wire:model.live="filterAbcClass" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Kelas ABC</option>
                                <option value="A" class="bg-ink-900">Kelas A</option>
                                <option value="B" class="bg-ink-900">Kelas B</option>
                                <option value="C" class="bg-ink-900">Kelas C</option>
                            </select>
                        @endif

                        @if($visibleFilters['stok'] ?? false)
                            <label class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-ink-900/60 border border-white/10 text-xs text-ink-200 cursor-pointer select-none min-h-[40px]">
                                <input type="checkbox" wire:model.live="filterHanyaReadyStok" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Ready Stok</span>
                            </label>
                        @endif

                        @if($visibleFilters['supplier'] ?? false)
                            <select wire:model.live="filterSupplierId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[40px]">
                                <option value="">Semua Supplier</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" class="bg-ink-900">{{ $sup->nama }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if($visibleFilters['tier'] ?? true)
                            <!-- PRICELIST: Eksternal / Customer-facing (sesuai tier) -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5 bg-up-primary/10 border border-up-primary/20 p-1.5 rounded-xl min-w-[200px] sm:min-w-0 flex-1 sm:flex-none" title="Pricelist untuk konsumen (harga sesuai tier)">
                                <span class="px-2 py-1 text-[10px] uppercase tracking-wider text-up-primary font-bold hidden sm:flex items-center">📊 Pricelist</span>
                                <select wire:model.live="filterTierHarga" class="px-3 py-2 rounded-lg bg-black/40 border-0 text-xs font-medium text-white min-h-[44px] outline-none cursor-pointer w-full sm:w-auto" title="Pilih tier harga untuk ekspor pricelist">
                                    <option value="retail" class="bg-ink-900 text-white">Tier: Retail Standar</option>
                                    <option value="reseller" class="bg-ink-900 text-white">Tier: Reseller</option>
                                    <option value="agen" class="bg-ink-900 text-white">Tier: Agen</option>
                                    @foreach($tierMemberships as $tm)
                                        <option value="tier_{{ $tm->id }}" class="bg-ink-900 text-white">Tier CRM: {{ $tm->nama }}</option>
                                    @endforeach
                                </select>
                                <div class="flex items-center gap-1.5 w-full sm:w-auto">
                                    <button type="button" wire:click="exportPricelist('xlsx')" class="flex-1 sm:flex-none px-3 py-2 rounded-lg bg-up-primary hover:bg-up-primary/80 text-white font-semibold text-xs min-h-[44px] cursor-pointer transition-[transform,background-color] active:scale-[0.97]" title="Export Pricelist Excel (untuk konsumen)">
                                        <span class="hidden xs:inline">📊 </span><span>Excel</span>
                                    </button>
                                    <button type="button" wire:click="exportPricelist('csv')" class="flex-1 sm:flex-none px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-ink-200 font-semibold text-xs min-h-[44px] cursor-pointer transition-[transform,background-color] active:scale-[0.97]" title="Export Pricelist CSV (untuk konsumen)">
                                        <span class="hidden xs:inline">📄 </span><span>CSV</span>
                                    </button>
                                </div>
                            </div>
                        @endif

                        <!-- Divider visual pemisah -->
                        <div class="hidden sm:block w-px h-8 bg-white/10 mx-1.5"></div>
                        <div class="sm:hidden w-full h-px bg-white/10 my-1.5"></div>

                        <!-- MASTER PRODUK: Internal / Untuk manajemen & update data (sejajar Import) -->
                        @php $hasSelected = ! empty($selectedProdukIds); $selectedCount = count($selectedProdukIds); @endphp
                        <div class="relative text-left w-full sm:w-auto" x-data="{ open: false }" @click.away="open = false" @keydown.escape.window="open = false">
                            <button type="button"
                                    @click="open = !open"
                                    aria-haspopup="true" :aria-expanded="open"
                                    class="w-full sm:w-auto px-3 py-2 rounded-xl bg-ink-900/60 hover:bg-black/40 border border-white/10 text-ink-200 font-semibold text-xs flex items-center justify-center sm:justify-start gap-1.5 transition-[transform,background-color] active:scale-95 cursor-pointer whitespace-nowrap min-h-[44px]"
                                    title="Export Master Produk (Internal: untuk import update / backup)">
                                <span>📦</span>
                                <span class="hidden sm:inline">Master Produk</span>
                                <span class="text-[9px] ml-auto sm:ml-0" :class="open ? 'rotate-180' : ''" style="transition: transform 150ms">▼</span>
                            </button>

                            <div x-show="open" x-cloak
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="absolute left-0 right-0 sm:right-auto z-50 mt-2 w-full sm:w-60 rounded-xl glass-card bg-ink-900 border border-white/10 shadow-2xl py-2 origin-top-left"
                                 style="display: none;">
                                <!-- Section: Semua (Sesuai Filter) -->
                                <div class="px-3 pb-1.5 text-[10px] uppercase tracking-wider text-up-mint font-bold">📤 Semua (Sesuai Filter)</div>
                                <button type="button" wire:click="exportMaster('xlsx')" class="w-full text-left px-3 py-2.5 text-xs text-ink-200 hover:bg-white/5 hover:text-white flex items-center gap-2 cursor-pointer min-h-[44px]" title="Export SEMUA produk yang terlihat (mengikuti filter aktif) — Excel">
                                    <span>📊</span><span>Excel (xlsx)</span>
                                </button>
                                <button type="button" wire:click="exportMaster('csv')" class="w-full text-left px-3 py-2.5 text-xs text-ink-200 hover:bg-white/5 hover:text-white flex items-center gap-2 cursor-pointer min-h-[44px]" title="Export SEMUA produk yang terlihat (mengikuti filter aktif) — CSV">
                                    <span>📊</span><span>CSV</span>
                                </button>

                                <div class="my-1.5 border-t border-white/10"></div>

                                <!-- Section: Terpilih -->
                                <div class="px-3 pb-1.5 text-[10px] uppercase tracking-wider text-up-amber font-bold">🖨 Terpilih ({{ $selectedCount }} item)</div>
                                <button type="button" wire:click="exportTerpilih('xlsx')" @if($hasSelected) wire:loading.attr="disabled" @else disabled title="Pilih minimal satu produk dulu (centang checkbox di tabel)" @endif
                                        class="w-full text-left px-3 py-2.5 text-xs text-ink-200 hover:bg-white/5 hover:text-white flex items-center gap-2 cursor-pointer min-h-[44px] @if(! $hasSelected) opacity-50 pointer-events-none @endif">
                                    <span>🖨</span><span>Excel (xlsx)</span>
                                </button>
                                <button type="button" wire:click="exportTerpilih('csv')" @if($hasSelected) wire:loading.attr="disabled" @else disabled title="Pilih minimal satu produk dulu (centang checkbox di tabel)" @endif
                                        class="w-full text-left px-3 py-2.5 text-xs text-ink-200 hover:bg-white/5 hover:text-white flex items-center gap-2 cursor-pointer min-h-[44px] @if(! $hasSelected) opacity-50 pointer-events-none @endif">
                                    <span>🖨</span><span>CSV</span>
                                </button>
                            </div>
                        </div>

                        @if($this->activeFilterCount > 0)
                            <button type="button"
                                    wire:click="resetSemuaFilter"
                                    class="px-2.5 py-1.5 rounded-xl bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 text-xs font-semibold flex items-center gap-1.5 transition active:scale-95 cursor-pointer whitespace-nowrap min-h-[40px]"
                                    title="Reset Semua Filter & Pencarian">
                                <span>✕</span>
                                <span class="hidden sm:inline">Reset Filter ({{ $this->activeFilterCount }})</span>
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 ml-auto shrink-0 py-0.5">
                        <span class="text-[11px] text-ink-400 pl-1 whitespace-nowrap">{{ $produks->total() }} produk</span>

                        <!-- Per Page Selector -->
                        <div class="flex items-center gap-1.5 bg-white/5 border border-white/10 px-2 py-1 rounded-xl">
                            <span class="text-[10px] text-ink-400">Hal:</span>
                            <select wire:model.live="perPage" class="bg-transparent text-xs font-semibold text-ink-200 outline-none cursor-pointer">
                                <option value="12" class="bg-ink-900 text-white">12</option>
                                <option value="25" class="bg-ink-900 text-white">25</option>
                                <option value="50" class="bg-ink-900 text-white">50</option>
                                <option value="100" class="bg-ink-900 text-white">100</option>
                            </select>
                        </div>

                        <!-- View Switcher (Table vs Grid) -->
                        <div class="inline-flex items-center p-0.5 rounded-xl bg-white/5 border border-white/10">
                            <button type="button"
                                    @click="setWmsView('table')"
                                    :class="wmsViewMode === 'table' ? 'bg-up-primary text-white shadow-sm font-bold' : 'text-ink-400 hover:text-white'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition-[background-color,color] duration-140 cursor-pointer"
                                    title="Tampilan Tabel">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                                <span>Tabel</span>
                            </button>
                            <button type="button"
                                    @click="setWmsView('grid')"
                                    :class="wmsViewMode === 'grid' ? 'bg-up-primary text-white shadow-sm font-bold' : 'text-ink-400 hover:text-white'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition-[background-color,color] duration-140 cursor-pointer"
                                    title="Tampilan Grid Kartu">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <span>Grid</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Custom Filter Preferences Drawer -->
                @if($showCustomFilterSettings)
                    <div class="p-3 bg-white/[0.03] border border-white/10 rounded-2xl flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-4 flex-wrap">
                            <span class="text-xs font-semibold text-ink-300">Tampilkan Filter:</span>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-kategori-{{ ($visibleFilters['kategori'] ?? true) ? '1' : '0' }}" wire:click="toggleFilter('kategori')" @checked($visibleFilters['kategori'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kategori</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-brand-{{ ($visibleFilters['brand'] ?? true) ? '1' : '0' }}" wire:click="toggleFilter('brand')" @checked($visibleFilters['brand'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Brand Part</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-kualitas-{{ ($visibleFilters['kualitas'] ?? false) ? '1' : '0' }}" wire:click="toggleFilter('kualitas')" @checked($visibleFilters['kualitas'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kualitas</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-brand_kompatibel-{{ (($visibleFilters['brand_kompatibel'] ?? false) || ($visibleFilters['merk_hp'] ?? false)) ? '1' : '0' }}" wire:click="toggleFilter('brand_kompatibel')" @checked(($visibleFilters['brand_kompatibel'] ?? false) || ($visibleFilters['merk_hp'] ?? false)) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Merk HP</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-model_kompatibel-{{ (($visibleFilters['model_kompatibel'] ?? false) || ($visibleFilters['tipe_hp'] ?? false)) ? '1' : '0' }}" wire:click="toggleFilter('model_kompatibel')" @checked(($visibleFilters['model_kompatibel'] ?? false) || ($visibleFilters['tipe_hp'] ?? false)) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Tipe HP</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-kondisi-{{ ($visibleFilters['kondisi'] ?? false) ? '1' : '0' }}" wire:click="toggleFilter('kondisi')" @checked($visibleFilters['kondisi'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kondisi</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-satuan-{{ ($visibleFilters['satuan'] ?? false) ? '1' : '0' }}" wire:click="toggleFilter('satuan')" @checked($visibleFilters['satuan'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Satuan</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-abc_class-{{ ($visibleFilters['abc_class'] ?? false) ? '1' : '0' }}" wire:click="toggleFilter('abc_class')" @checked($visibleFilters['abc_class'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kelas ABC</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-stok-{{ ($visibleFilters['stok'] ?? false) ? '1' : '0' }}" wire:click="toggleFilter('stok')" @checked($visibleFilters['stok'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Ready Stok</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-tier-{{ ($visibleFilters['tier'] ?? true) ? '1' : '0' }}" wire:click="toggleFilter('tier')" @checked($visibleFilters['tier'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Tier & Export</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-chk-supplier-{{ ($visibleFilters['supplier'] ?? false) ? '1' : '0' }}" wire:click="toggleFilter('supplier')" @checked($visibleFilters['supplier'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Supplier</span>
                            </label>
                        </div>
                        <button type="button"
                                wire:click="resetCustomFilters"
                                class="text-xs text-up-red hover:underline cursor-pointer">
                            Reset ke Default
                        </button>
                    </div>
                @endif

                <!-- Custom Column Preferences Drawer -->
                @if($showColumnSettings)
                    <div class="p-3.5 bg-ink-950/90 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-xl backdrop-blur-md">
                        <div class="flex items-center gap-3.5 flex-wrap">
                            <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                <span>📐</span> Kolom Master Produk:
                            </span>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-400 select-none cursor-not-allowed" title="Kolom nama produk wajib tampil">
                                <input type="checkbox" checked disabled class="rounded border-white/20 bg-ink-900 text-up-primary opacity-60">
                                <span class="font-bold text-white">Produk (Wajib)</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-chk-{{ ($visibleColumns['checkbox'] ?? true) ? '1' : '0' }}" wire:click="toggleColumn('checkbox')" @checked($visibleColumns['checkbox'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Checkbox</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-sku-{{ ($visibleColumns['sku'] ?? true) ? '1' : '0' }}" wire:click="toggleColumn('sku')" @checked($visibleColumns['sku'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>SKU / Barcode</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-kategori-{{ ($visibleColumns['kategori'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('kategori')" @checked($visibleColumns['kategori'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kategori</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-brand-{{ ($visibleColumns['brand'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('brand')" @checked($visibleColumns['brand'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Brand & Kualitas</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-kompatibilitas-{{ ($visibleColumns['kompatibilitas'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('kompatibilitas')" @checked($visibleColumns['kompatibilitas'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kompatibilitas HP</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-supplier-{{ ($visibleColumns['supplier'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('supplier')" @checked($visibleColumns['supplier'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Supplier</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-harga_beli-{{ ($visibleColumns['harga_beli'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('harga_beli')" @checked($visibleColumns['harga_beli'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Harga Beli (HPP)</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-harga_jual-{{ ($visibleColumns['harga_jual'] ?? true) ? '1' : '0' }}" wire:click="toggleColumn('harga_jual')" @checked($visibleColumns['harga_jual'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Harga Jual</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-stok-{{ ($visibleColumns['stok'] ?? true) ? '1' : '0' }}" wire:click="toggleColumn('stok')" @checked($visibleColumns['stok'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Stok Total</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-gudang-{{ ($visibleColumns['gudang'] ?? true) ? '1' : '0' }}" wire:click="toggleColumn('gudang')" @checked($visibleColumns['gudang'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Gudang & Rak</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-reorder-{{ ($visibleColumns['reorder'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('reorder')" @checked($visibleColumns['reorder'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Parameter Pengadaan (ROP/ABC)</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-spesifikasi-{{ ($visibleColumns['spesifikasi'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('spesifikasi')" @checked($visibleColumns['spesifikasi'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Kondisi & Satuan</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-status-{{ ($visibleColumns['status'] ?? false) ? '1' : '0' }}" wire:click="toggleColumn('status')" @checked($visibleColumns['status'] ?? false) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Status Keaktifan</span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 text-xs text-ink-200 cursor-pointer select-none">
                                <input type="checkbox" wire:key="pt-col-aksi-{{ ($visibleColumns['aksi'] ?? true) ? '1' : '0' }}" wire:click="toggleColumn('aksi')" @checked($visibleColumns['aksi'] ?? true) class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30">
                                <span>Aksi</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="showAllColumns" class="text-xs text-up-mint hover:underline cursor-pointer font-semibold">
                                Tampilkan Semua
                            </button>
                            <span class="text-ink-500">•</span>
                            <button type="button" wire:click="resetColumns" class="text-xs text-up-red hover:underline cursor-pointer">
                                Reset Default
                            </button>
                        </div>
                    </div>
                @endif
            @if(!empty($selectedProdukIds))
                <div class="p-3 bg-up-primary/10 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs">
                            {{ count($selectedProdukIds) }}
                        </span>
                        <div>
                            <span class="text-xs font-bold text-white block">Produk Terpilih</span>
                            <span class="text-[10px] text-ink-300">
                                Filter Harga Aktif: <strong class="text-up-mint">{{ $labelTierAktif }}</strong>
                            </span>
                        </div>
                        @if(count($selectedProdukIds) < $produks->total())
                            <button type="button" wire:click="selectAllMatching" class="text-xs text-up-primary hover:underline font-semibold ml-2 cursor-pointer">
                                Pilih semua {{ $produks->total() }} produk yang cocok
                            </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button"
                                wire:click="openBulkEditModal"
                                class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm"
                                title="Edit masal produk terpilih">
                            <span>✏️ Bulk Edit ({{ count($selectedProdukIds) }})</span>
                        </button>

                        <!-- Tombol Salin Data / Harga & Pengaturan Data yang Disalin -->
                        <div class="inline-flex rounded-xl bg-up-mint p-0.5 shadow-sm text-ink-950 font-bold text-xs">
                            <button type="button"
                                    wire:click="salinHargaTerpilih"
                                    class="px-2.5 py-1.5 rounded-lg hover:bg-black/10 flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                    title="Salin langsung data terpilih ke clipboard (format saat ini)">
                                <span>📋 Salin Data ({{ count($selectedProdukIds) }})</span>
                            </button>
                            <button type="button"
                                    wire:click="openSalinDataModal"
                                    class="px-2 py-1.5 rounded-lg hover:bg-black/10 border-l border-ink-950/20 flex items-center transition cursor-pointer"
                                    title="Pilih atribut data produk yang ingin disalin (Nama, SKU, Harga, Stok, Rak, dll)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </button>
                        </div>

                        <button type="button"
                                wire:click="exportTerpilih('xlsx')"
                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                title="Ekspor produk terpilih ke Excel">
                            <span>📥 Ekspor Terpilih</span>
                        </button>

                        <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                            <button type="button"
                                    wire:click="bulkSetStatus(true)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer"
                                    title="Aktifkan produk terpilih">
                                Set Aktif
                            </button>
                            <button type="button"
                                    wire:click="bulkSetStatus(false)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer"
                                    title="Nonaktifkan produk terpilih">
                                Set Nonaktif
                            </button>
                        </div>

                        <button type="button"
                                wire:click="cetakBarcodeTerpilih"
                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                title="Cetak barcode produk yang dipilih">
                            <span>🖨️ Cetak Label QR</span>
                        </button>

                        <button type="button"
                                wire:click="autoKlasifikasiAbcTerpilih"
                                wire:confirm="Hitung dan terapkan kelas ABC serta parameter WMS otomatis untuk {{ count($selectedProdukIds) }} produk terpilih?"
                                class="px-3 py-1.5 rounded-xl bg-up-primary/20 hover:bg-up-primary/30 text-up-primary border border-up-primary/30 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                title="Kalkulasi otomatis kelas ABC dan parameter WMS untuk produk terpilih">
                            <span>⚡ Auto Kelas ABC</span>
                        </button>

                        @if(isSuperAdminOrOwner())
                            <button type="button"
                                    wire:click="hapusProdukMasal"
                                    wire:confirm="Yakin ingin memproses penghapusan {{ count($selectedProdukIds) }} produk terpilih? Produk dengan riwayat transaksi akan dinonaktifkan, sedangkan produk tanpa riwayat transaksi dan stok kosong akan dihapus permanen."
                                    class="px-3 py-1.5 rounded-xl bg-up-red/20 hover:bg-up-red/30 text-up-red border border-up-red/30 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                    title="Hapus masal produk terpilih">
                                <span>🗑️ Hapus Masal</span>
                            </button>
                        @endif

                        <button type="button"
                                wire:click="clearSelected"
                                class="px-2.5 py-1.5 rounded-xl text-ink-400 hover:text-white text-xs cursor-pointer">
                            Batal
                        </button>
                    </div>
                </div>
            @endif

            <!-- 1. Table View -->
            <div x-show="wmsViewMode === 'table'">
            <x-prism.data-table>
                <x-slot:thead>
                    <tr>
                        <!-- Checkbox Select All -->
                        @if($visibleColumns['checkbox'] ?? true)
                            <th class="py-3 px-3 w-10 text-center">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30 cursor-pointer" title="Pilih Semua di Halaman Ini">
                            </th>
                        @endif

                        <!-- Kolom Produk (Sort & Filter) -->
                        <th class="py-3 px-3 min-w-[220px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <div wire:click="sortByColumn('nama')" class="flex items-center gap-1 cursor-pointer select-none group" title="Klik untuk mengurutkan Nama Produk">
                                    <span class="group-hover:text-up-primary transition-colors">Produk</span>
                                    @if($sortBy === 'nama')
                                        <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                    @else
                                        <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                    @endif
                                </div>

                                <!-- Filter Popover Kolom Produk -->
                                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ (!empty($produkSearch) || $filterKategoriId || $filterBrandId || $filterKualitasId) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                        title="Filter Kolom Produk"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                        @if(!empty($produkSearch) || $filterKategoriId || $filterBrandId || $filterKualitasId)
                                            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                        @endif
                                    </button>

                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        class="absolute left-0 mt-2 z-30 w-72 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                    >
                                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                            <span class="text-xs font-bold text-white">Filter Produk</span>
                                            <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                        </div>

                                        <div class="space-y-2">
                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Cari Nama / Model</label>
                                                <input type="text" wire:model.live.debounce.300ms="produkSearch" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs" placeholder="Ketik kata kunci...">
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Kategori</label>
                                                <select wire:model.live="filterKategoriId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Kategori</option>
                                                    @foreach($kategoriTree as $kat)
                                                        <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                                                        @foreach($kat->children as $child)
                                                            <option value="{{ $child->id }}">└ {{ $child->nama }}</option>
                                                        @endforeach
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Brand Part</label>
                                                <select wire:model.live="filterBrandId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Brand Part</option>
                                                    @foreach($brands as $b)
                                                        <option value="{{ $b->id }}">{{ $b->nama }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Kualitas</label>
                                                <select wire:model.live="filterKualitasId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Kualitas</option>
                                                    @foreach($kualitasList as $k)
                                                        <option value="{{ $k->id }}">{{ $k->nama }}{{ $k->brand_id ? ' ('.$k->brand?->nama.')' : '' }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-white/10 flex justify-between">
                                            <button type="button" wire:click="resetFilterKolom('produk')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                Reset Kolom Ini
                                            </button>
                                            <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                Tutup
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </th>

                        <!-- Kolom Kategori Tambahan -->
                        @if($visibleColumns['kategori'] ?? false)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('kategori')" class="flex items-center gap-1 cursor-pointer select-none group" title="Urutkan Kategori">
                                        <span class="group-hover:text-up-primary transition-colors">Kategori</span>
                                        @if($sortBy === 'kategori')
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ $filterKategoriId ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterKategoriId) <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Kategori</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <select wire:model.live="filterKategoriId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                <option value="">Semua Kategori</option>
                                                @foreach($kategoriTree as $kat)
                                                    <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                                                    @foreach($kat->children as $child)
                                                        <option value="{{ $child->id }}">└ {{ $child->nama }}</option>
                                                    @endforeach
                                                @endforeach
                                            </select>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('kategori')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Brand & Kualitas Tambahan -->
                        @if($visibleColumns['brand'] ?? false)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('brand')" class="flex items-center gap-1 cursor-pointer select-none group" title="Urutkan Brand">
                                        <span class="group-hover:text-up-primary transition-colors">Brand & Kualitas</span>
                                        @if($sortBy === 'brand')
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ ($filterBrandId || $filterKualitasId) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterBrandId || $filterKualitasId) <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Brand Part & Kualitas</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <div class="space-y-2">
                                                <select wire:model.live="filterBrandId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Brand Part</option>
                                                    @foreach($brands as $b) <option value="{{ $b->id }}">{{ $b->nama }}</option> @endforeach
                                                </select>
                                                <select wire:model.live="filterKualitasId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Kualitas</option>
                                                    @foreach($kualitasList as $k) <option value="{{ $k->id }}">{{ $k->nama }}{{ $k->brand_id ? ' ('.$k->brand?->nama.')' : '' }}</option> @endforeach
                                                </select>
                                            </div>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('brand')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Kompatibilitas HP Tambahan -->
                        @if($visibleColumns['kompatibilitas'] ?? false)
                            <th class="py-3 px-3 min-w-[140px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span>Kompatibilitas</span>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ ($filterTipeHpId || $filterBrandKompatibel || $filterModelKompatibel) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterTipeHpId || $filterBrandKompatibel || $filterModelKompatibel) <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Kompatibilitas</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <div class="space-y-2">
                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-ink-400 mb-1">Merk HP</label>
                                                    <select wire:model.live="filterBrandKompatibel" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs text-ink-200 bg-ink-900">
                                                        <option value="">Semua Merk HP</option>
                                                        @foreach($brandKompatibelList as $bk) <option value="{{ $bk }}">{{ $bk }}</option> @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-ink-400 mb-1">Tipe HP</label>
                                                    <select wire:model.live="filterModelKompatibel" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs text-ink-200 bg-ink-900">
                                                        <option value="">{{ $filterBrandKompatibel ? 'Semua Tipe '.$filterBrandKompatibel : 'Semua Tipe HP' }}</option>
                                                        @foreach($modelKompatibelList as $mk) <option value="{{ $mk }}">{{ $mk }}</option> @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('kompatibilitas')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Supplier (Filter) -->
                        @if($visibleColumns['supplier'] ?? false)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span class="text-ink-400 font-semibold text-xs">Supplier</span>
                                    <!-- Filter Popover Kolom Supplier -->
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ $filterSupplierId ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                            title="Filter Kolom Supplier"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterSupplierId)
                                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                            @endif
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                        >
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Supplier</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Pilih Supplier</label>
                                                <select wire:model.live="filterSupplierId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-medium">
                                                    <option value="">Semua Supplier</option>
                                                    @foreach($suppliers as $sup)
                                                        <option value="{{ $sup->id }}">{{ $sup->nama }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('supplier')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                    Reset Kolom Ini
                                                </button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                    Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom SKU / Barcode (Sort & Filter) -->
                        @if($visibleColumns['sku'] ?? true)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('sku')" class="flex items-center gap-1 cursor-pointer select-none group" title="Klik untuk mengurutkan SKU">
                                        <span class="group-hover:text-up-primary transition-colors">SKU / Barcode</span>
                                        @if($sortBy === 'sku')
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>

                                    <!-- Filter Popover Kolom SKU -->
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ !empty($filterSku) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                            title="Filter Kolom SKU"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if(!empty($filterSku))
                                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                            @endif
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                        >
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter SKU / Barcode</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Cari Kode SKU atau Barcode</label>
                                                <input type="text" wire:model.live.debounce.300ms="filterSku" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono" placeholder="Ketik kode SKU...">
                                            </div>

                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('sku')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                    Reset Kolom Ini
                                                </button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                    Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Harga Beli Tersendiri (HPP) -->
                        @if($visibleColumns['harga_beli'] ?? false)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('harga_beli')" class="flex items-center gap-1 cursor-pointer select-none group" title="Urutkan Harga Beli">
                                        <span class="group-hover:text-up-primary transition-colors">Harga Beli</span>
                                        @if($sortBy === 'harga_beli')
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ ($filterMinHargaBeli !== null || $filterMaxHargaBeli !== null) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}" title="Filter Harga Beli">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterMinHargaBeli !== null || $filterMaxHargaBeli !== null) <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Harga Beli</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="number" wire:model.live.debounce.400ms="filterMinHargaBeli" class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-mono" placeholder="Min Rp">
                                                <input type="number" wire:model.live.debounce.400ms="filterMaxHargaBeli" class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-mono" placeholder="Maks Rp">
                                            </div>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('harga_beli')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Harga Jual (Sort & Filter) -->
                        @if($visibleColumns['harga_jual'] ?? true)
                            <th class="py-3 px-3 min-w-[140px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('harga_jual')" class="flex items-center gap-1 cursor-pointer select-none group" title="Klik untuk mengurutkan Harga Jual">
                                        <span class="group-hover:text-up-primary transition-colors">Harga ({{ $labelTierAktif }})</span>
                                        @if(in_array($sortBy, ['harga_jual', 'harga_jual_retail', 'harga_beli']))
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>

                                    <!-- Filter Popover Kolom Harga -->
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ ($filterMinHarga !== null || $filterMaxHarga !== null || $filterTierHarga !== 'retail') ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                            title="Filter Kolom Harga"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterMinHarga !== null || $filterMaxHarga !== null || $filterTierHarga !== 'retail')
                                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                            @endif
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute left-0 mt-2 z-30 w-72 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                        >
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter & Urutkan Harga</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>

                                            <!-- Opsi Sort Cepat Harga -->
                                            <div class="space-y-1">
                                                <span class="text-[10px] uppercase font-bold text-ink-400 tracking-wider">Urutan Cepat</span>
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <button type="button" wire:click="updatedSortPreset('harga_jual_desc')" @click="open = false" class="px-2 py-1.5 rounded-lg text-left text-[11px] bg-white/5 hover:bg-white/10 text-ink-200">
                                                        📈 Jual Tertinggi
                                                    </button>
                                                    <button type="button" wire:click="updatedSortPreset('harga_jual_asc')" @click="open = false" class="px-2 py-1.5 rounded-lg text-left text-[11px] bg-white/5 hover:bg-white/10 text-ink-200">
                                                        📉 Jual Terendah
                                                    </button>
                                                    <button type="button" wire:click="updatedSortPreset('harga_beli_desc')" @click="open = false" class="px-2 py-1.5 rounded-lg text-left text-[11px] bg-white/5 hover:bg-white/10 text-ink-200">
                                                        📦 Beli Tertinggi
                                                    </button>
                                                    <button type="button" wire:click="updatedSortPreset('harga_beli_asc')" @click="open = false" class="px-2 py-1.5 rounded-lg text-left text-[11px] bg-white/5 hover:bg-white/10 text-ink-200">
                                                        🏷️ Beli Terendah
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="space-y-2 pt-2 border-t border-white/10">
                                                <div>
                                                    <label class="block text-[11px] text-ink-300 mb-1">Tier Harga Aktif</label>
                                                    <select wire:model.live="filterTierHarga" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                        <option value="retail">Retail (Umum)</option>
                                                        <option value="reseller">Reseller</option>
                                                        <option value="agen">Agen</option>
                                                        @foreach($tierMemberships as $tm)
                                                            <option value="tier_{{ $tm->id }}">CRM: {{ $tm->nama_tier }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[11px] text-ink-300 mb-1">Min (Rp)</label>
                                                        <input type="number" wire:model.live.debounce.400ms="filterMinHarga" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono" placeholder="0">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] text-ink-300 mb-1">Maks (Rp)</label>
                                                        <input type="number" wire:model.live.debounce.400ms="filterMaxHarga" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono" placeholder="Tak terbatas">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('harga')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                    Reset Kolom Ini
                                                </button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                    Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Stok Total (WAJIB teks persis 'Stok Total' untuk test compatibility) -->
                        @if($visibleColumns['stok'] ?? true)
                            <th class="py-3 px-3 min-w-[120px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('stok')" class="flex items-center gap-1 cursor-pointer select-none group" title="Klik untuk mengurutkan Stok Total">
                                        <span class="group-hover:text-up-primary transition-colors">Stok Total</span>
                                        @if(in_array($sortBy, ['stok', 'total_stok']))
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>

                                    <!-- Filter Popover Kolom Stok -->
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ ($filterStokStatus !== 'semua' || $filterHanyaReadyStok) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                            title="Filter Kolom Stok"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterStokStatus !== 'semua' || $filterHanyaReadyStok)
                                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                            @endif
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute left-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                        >
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter & Sort Stok</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>

                                            <!-- Opsi Sort Cepat Stok -->
                                            <div class="space-y-1">
                                                <span class="text-[10px] uppercase font-bold text-ink-400 tracking-wider">Urutan Cepat</span>
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <button type="button" wire:click="updatedSortPreset('stok_desc')" @click="open = false" class="px-2 py-1.5 rounded-lg text-left text-[11px] bg-white/5 hover:bg-white/10 text-ink-200">
                                                        📦 Stok Terbanyak
                                                    </button>
                                                    <button type="button" wire:click="updatedSortPreset('stok_asc')" @click="open = false" class="px-2 py-1.5 rounded-lg text-left text-[11px] bg-white/5 hover:bg-white/10 text-ink-200">
                                                        ⚠️ Stok Menipis
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="space-y-2 pt-2 border-t border-white/10">
                                                <div>
                                                    <label class="block text-[11px] text-ink-300 mb-1">Status Ketersediaan</label>
                                                    <select wire:model.live="filterStokStatus" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                        <option value="semua">Semua Stok</option>
                                                        <option value="ready">✅ Ready Stok Saja (&gt; 0)</option>
                                                        <option value="menipis">⚠️ Stok Menipis (≤ Min)</option>
                                                        <option value="habis">❌ Stok Habis (= 0)</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('stok')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                    Reset Kolom Ini
                                                </button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                    Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Gudang & Rak (Filter) -->
                        @if($visibleColumns['gudang'] ?? true)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span>Gudang & Rak</span>

                                    <!-- Filter Popover Kolom Gudang -->
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ $filterGudangId ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                            title="Filter Kolom Gudang"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterGudangId)
                                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                            @endif
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute right-0 mt-2 z-30 w-64 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                        >
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Gudang</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Pilih Gudang Cabang</label>
                                                <select wire:model.live="filterGudangId" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Gudang</option>
                                                    @foreach($gudangsCabang as $g)
                                                        <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('gudang')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                    Reset Kolom Ini
                                                </button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                    Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Parameter Pengadaan / Reorder (ROP & ABC) Tambahan -->
                        @if($visibleColumns['reorder'] ?? false)
                            <th class="py-3 px-3 min-w-[130px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('reorder_point')" class="flex items-center gap-1 cursor-pointer select-none group" title="Urutkan ROP">
                                        <span class="group-hover:text-up-primary transition-colors">Parameter ROP</span>
                                        @if($sortBy === 'reorder_point')
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ $filterAbcClass ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterAbcClass) <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute right-0 mt-2 z-30 w-56 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Kelas ABC</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <select wire:model.live="filterAbcClass" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                <option value="">Semua Kelas</option>
                                                <option value="A">Kelas A (Pareto 80% Nilai)</option>
                                                <option value="B">Kelas B (15% Nilai)</option>
                                                <option value="C">Kelas C (5% Nilai)</option>
                                            </select>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('reorder')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Spesifikasi & Satuan Tambahan -->
                        @if($visibleColumns['spesifikasi'] ?? false)
                            <th class="py-3 px-3 min-w-[120px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('kondisi')" class="flex items-center gap-1 cursor-pointer select-none group" title="Urutkan Kondisi">
                                        <span class="group-hover:text-up-primary transition-colors">Spesifikasi</span>
                                        @if(in_array($sortBy, ['kondisi', 'satuan']))
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ ($filterKondisi || $filterSatuan) ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterKondisi || $filterSatuan) <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute right-0 mt-2 z-30 w-56 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Filter Spesifikasi</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <div class="space-y-2">
                                                <select wire:model.live="filterKondisi" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Kondisi</option>
                                                    @foreach($kondisiList as $kd) <option value="{{ $kd['kode'] }}">{{ $kd['nama'] }}</option> @endforeach
                                                </select>
                                                <select wire:model.live="filterSatuan" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="">Semua Satuan</option>
                                                    @foreach($satuanUnits as $su) <option value="{{ $su->kode }}">{{ $su->nama ?? $su->kode }}</option> @endforeach
                                                </select>
                                            </div>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('spesifikasi')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Status Keaktifan Tambahan -->
                        @if($visibleColumns['status'] ?? false)
                            <th class="py-3 px-3 min-w-[110px]">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div wire:click="sortByColumn('status')" class="flex items-center gap-1 cursor-pointer select-none group" title="Urutkan Status">
                                        <span class="group-hover:text-up-primary transition-colors">Status</span>
                                        @if(in_array($sortBy, ['status', 'is_active']))
                                            <span class="text-up-primary font-black text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @else
                                            <span class="text-ink-500/40 group-hover:text-ink-300 text-xs">↕</span>
                                        @endif
                                    </div>
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ $filterStatusAktif !== 'semua' ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterStatusAktif !== 'semua') <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span> @endif
                                        </button>
                                        <div x-show="open" x-cloak class="absolute right-0 mt-2 z-30 w-56 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case">
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Status Produk</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>
                                            <select wire:model.live="filterStatusAktif" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                <option value="semua">Semua Status</option>
                                                <option value="aktif">🟢 Aktif Saja</option>
                                                <option value="nonaktif">🔴 Nonaktif Saja</option>
                                            </select>
                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('status')" @click="open = false" class="text-[11px] text-up-red hover:underline">Reset</button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif

                        <!-- Kolom Aksi / Status (Filter Status) -->
                        @if($visibleColumns['aksi'] ?? true)
                            <th class="py-3 px-3 text-right min-w-[90px]">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Aksi</span>

                                    <!-- Filter Popover Status -->
                                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer relative {{ $filterStatusAktif !== 'semua' ? 'text-up-primary bg-up-primary/10' : 'text-ink-400 hover:text-white' }}"
                                            title="Filter Status Produk"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            @if($filterStatusAktif !== 'semua')
                                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                            @endif
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute right-0 mt-2 z-30 w-56 p-3.5 rounded-2xl glass-panel bg-ink-950/95 border border-white/10 shadow-2xl space-y-3 font-normal normal-case tracking-normal"
                                        >
                                            <div class="flex items-center justify-between pb-2 border-b border-white/10">
                                                <span class="text-xs font-bold text-white">Status Produk</span>
                                                <button type="button" @click="open = false" class="text-ink-400 hover:text-white text-xs">✕</button>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] text-ink-300 mb-1">Status Keaktifan</label>
                                                <select wire:model.live="filterStatusAktif" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs">
                                                    <option value="semua">Semua Status</option>
                                                    <option value="aktif">🟢 Aktif Saja</option>
                                                    <option value="nonaktif">🔴 Nonaktif Saja</option>
                                                </select>
                                            </div>

                                            <div class="pt-2 border-t border-white/10 flex justify-between">
                                                <button type="button" wire:click="resetFilterKolom('status')" @click="open = false" class="text-[11px] text-up-red hover:underline">
                                                    Reset
                                                </button>
                                                <button type="button" @click="open = false" class="px-2.5 py-1 rounded-lg bg-up-primary text-white text-[11px] font-bold">
                                                    Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endif
                    </tr>
                </x-slot:thead>
                @forelse($produks as $p)
                    @php
                        $stokTotal = $p->stokItems->sum('jumlah');
                        $skuPertama = $p->skuVariants->first()?->sku;
                        $tierInfo = $this->resolveHargaTierProduk($p);
                        $isSelected = in_array($p->id, $selectedProdukIds, true);
                    @endphp
                    <tr class="hover:bg-white/[0.02] transition-colors {{ $isSelected ? 'bg-up-primary/5' : '' }}">
                        @if($visibleColumns['checkbox'] ?? true)
                            <td class="py-2.5 px-3 w-10 text-center">
                                <input type="checkbox"
                                       value="{{ $p->id }}"
                                       wire:model.live="selectedProdukIds"
                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30 cursor-pointer">
                            </td>
                        @endif
                        <td class="py-2.5 px-3 min-w-[220px]">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-xl overflow-hidden bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 shrink-0 flex items-center justify-center relative">
                                    @if($p->thumbnail_url)
                                        <img src="{{ $p->thumbnail_url }}" alt="{{ $p->nama }}" class="w-full h-full object-cover" loading="lazy"
                                             onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                    @endif
                                    <span class="text-base text-ink-500 items-center justify-center w-full h-full" style="{{ $p->thumbnail_url ? 'display:none;' : 'display:flex;' }}">📦</span>
                                </div>
                                <div class="min-w-0">
                                    <span class="font-semibold text-ink-900 dark:text-white block text-xs leading-snug line-clamp-2" title="{{ $p->nama }}">{{ $p->nama }}</span>
                                    <span class="block text-[10px] text-ink-400 mt-0.5 truncate">
                                        <strong class="text-ink-700 dark:text-ink-200">{{ $p->kategoriRelasi?->nama ?? $p->kategori ?? '-' }}</strong> · {{ $p->brand_kompatibel ?? '-' }} {{ $p->model_kompatibel ?? '' }}
                                    </span>
                                    <span class="flex gap-1 mt-1 flex-wrap items-center">
                                        @if(!empty($filterModelKompatibel) && strcasecmp((string)$p->model_kompatibel, (string)$filterModelKompatibel) !== 0)
                                            <span class="px-1.5 py-0.2 rounded bg-up-primary/20 border border-up-primary/40 text-[9px] font-bold text-up-primary" title="Produk ini kompatibel silang / substitusi untuk {{ $filterModelKompatibel }}">
                                                Kompatibel {{ $filterModelKompatibel }}
                                            </span>
                                        @endif
                                        @if($p->brand)
                                            <span class="px-1.5 py-0.2 rounded bg-up-primary/15 border border-up-primary/25 text-[9px] font-bold text-up-primary">{{ $p->brand->nama }}</span>
                                        @endif
                                        @if($p->kualitas)
                                            <span class="px-1.5 py-0.2 rounded bg-up-mint/10 border border-up-mint/25 text-[9px] font-bold text-up-mint">{{ $p->kualitas->nama }}</span>
                                        @endif
                                        @if($p->satuan)
                                            <span class="px-1.5 py-0.2 rounded bg-white/5 border border-white/10 text-[9px] font-bold text-ink-300">{{ $p->satuan }}</span>
                                        @endif
                                        @if($p->satuanKonversi && $p->satuanKonversi->isNotEmpty())
                                            <span class="px-1.5 py-0.2 rounded bg-up-primary/10 border border-up-primary/20 text-[9px] font-bold text-up-primary" title="{{ $p->satuanKonversi->map(fn($sk) => $sk->keterangan_konversi)->join(' | ') }}">
                                                +{{ $p->satuanKonversi->count() }} Satuan
                                            </span>
                                        @endif
                                        @if($p->harga_fleksibel)
                                            <span class="px-1.5 py-0.2 rounded bg-up-amber/15 border border-up-amber/30 text-[9px] font-bold text-up-amber">Fleksibel</span>
                                        @endif
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold {{ $p->abc_class === 'A' ? 'bg-up-red/15 border border-up-red/30 text-up-red' : ($p->abc_class === 'C' ? 'bg-white/10 border border-white/20 text-ink-300' : 'bg-up-primary/15 border border-up-primary/30 text-up-primary') }}">
                                            {{ $p->abc_class ?? 'B' }}
                                        </span>
                                        @if($p->is_ondemand)
                                            <span class="px-1.5 py-0.2 rounded bg-purple-500/15 border border-purple-500/30 text-[9px] font-bold text-purple-400">JIT</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </td>

                        @if($visibleColumns['kategori'] ?? false)
                            <td class="py-2.5 px-3 text-xs whitespace-nowrap">
                                <span class="font-medium text-ink-200 block">{{ $p->kategoriRelasi?->nama ?? $p->kategori ?? '-' }}</span>
                                @if($p->kategoriRelasi?->parent)
                                    <span class="text-[10px] text-ink-400 block">Induk: {{ $p->kategoriRelasi->parent->nama }}</span>
                                @endif
                            </td>
                        @endif

                        @if($visibleColumns['brand'] ?? false)
                            <td class="py-2.5 px-3 text-xs whitespace-nowrap">
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-white">{{ $p->brand?->nama ?? '-' }}</span>
                                    @if($p->kualitas)
                                        <span class="text-[10px] text-up-mint font-medium">{{ $p->kualitas->nama }}</span>
                                    @endif
                                </div>
                            </td>
                        @endif

                        @if($visibleColumns['kompatibilitas'] ?? false)
                            <td class="py-2.5 px-3 text-xs min-w-[140px]">
                                <span class="font-medium text-ink-200 block truncate">{{ $p->brand_kompatibel ?? '-' }} {{ $p->model_kompatibel ?? '' }}</span>
                                @if(!empty($filterModelKompatibel) && strcasecmp((string)$p->model_kompatibel, (string)$filterModelKompatibel) !== 0)
                                    <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded bg-up-primary/20 border border-up-primary/40 text-[9px] font-bold text-up-primary">
                                        Kompatibel {{ $filterModelKompatibel }}
                                    </span>
                                @endif
                                @if($p->tipeHps && $p->tipeHps->isNotEmpty())
                                    <span class="text-[10px] text-ink-400 block line-clamp-1" title="{{ $p->tipeHps->map(fn($t) => $t->merk.' '.$t->model)->join(', ') }}">
                                        {{ $p->tipeHps->count() }} Tipe HP: {{ $p->tipeHps->first()?->model }}
                                    </span>
                                @endif
                            </td>
                        @endif

                        @if($visibleColumns['supplier'] ?? false)
                            <td class="py-2.5 px-3 text-xs whitespace-nowrap min-w-[130px]">
                                @if($p->supplier)
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-semibold text-white">{{ $p->supplier->nama }}</span>
                                    </div>
                                    @if($p->supplier->telepon)
                                        <span class="text-[10px] text-ink-500 font-mono block">{{ $p->supplier->telepon }}</span>
                                    @endif
                                @else
                                    <span class="text-ink-500 text-xs">-</span>
                                @endif
                            </td>
                        @endif

                        @if($visibleColumns['sku'] ?? true)
                            <td class="py-2.5 px-3 font-mono text-xs whitespace-nowrap">
                                <span class="text-ink-300 block font-semibold">{{ $skuPertama ?? '-' }}</span>
                                @if($p->barcode)
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-[10px] text-up-primary font-mono block px-1.5 py-0.2 rounded bg-up-primary/10 border border-up-primary/20">{{ $p->barcode }}</span>
                                    </div>
                                @else
                                    <span class="text-[11px] text-ink-500 font-mono block mt-0.5">-</span>
                                @endif
                            </td>
                        @endif

                        @if($visibleColumns['harga_beli'] ?? false)
                            <td class="py-2.5 px-3 tabular-nums text-xs whitespace-nowrap">
                                @if(canSeeField('harga_beli'))
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-ink-900 dark:text-white font-semibold">Rp {{ number_format($p->harga_beli, 0, ',', '.') }}</span>
                                        @if(($p->metode_harga_beli ?? 'manual') === 'average')
                                            <span class="px-1.5 py-0.2 rounded bg-up-mint/15 text-up-mint text-[9px] font-bold border border-up-mint/30" title="Metode: Moving Average Otomatis dari PO">Avg</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-ink-900 dark:text-white font-semibold">—</span>
                                @endif
                            </td>
                        @endif

                        @if($visibleColumns['harga_jual'] ?? true)
                            <td class="py-2.5 px-3 tabular-nums text-xs whitespace-nowrap">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-ink-400">{{ $filterTierHarga === 'retail' ? 'Jual' : $labelTierAktif }}</span>
                                    <span class="text-up-mint font-semibold">Rp {{ number_format($tierInfo['harga'], 0, ',', '.') }}</span>
                                    @if($filterTierHarga !== 'retail')
                                        <span class="text-[9px] text-ink-500 line-through">({{ number_format($p->harga_jual_retail, 0, ',', '.') }})</span>
                                    @elseif($p->margin_persen > 0)
                                        <span class="text-[9px] text-ink-400 font-mono" title="Margin {{ $p->margin_persen }}%">+{{ (float) $p->margin_persen }}%</span>
                                    @endif
                                </div>
                            </td>
                        @endif

                        @if($visibleColumns['stok'] ?? true)
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <x-prism.stock-gauge :stok="$stokTotal" :min="$p->stokItems->min('jumlah_minimum') ?? 2" />
                            </td>
                        @endif

                        @if($visibleColumns['gudang'] ?? true)
                            <td class="py-2.5 px-3 text-xs min-w-[130px]">
                                @if($p->stokItems->isNotEmpty())
                                    <div class="space-y-0.5">
                                        @foreach($p->stokItems as $si)
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-ink-300 font-medium">{{ $si->gudang?->nama ?? '-' }}</span>
                                                <span class="text-ink-400 font-bold tabular-nums">({{ $si->jumlah }})</span>
                                                @if($si->rak)
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-up-primary/10 border border-up-primary/20 text-up-primary text-[10px] font-bold">
                                                        🏷️ {{ $si->rak->kode }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-ink-500 text-xs">-</span>
                                @endif
                            </td>
                        @endif

                        @if($visibleColumns['reorder'] ?? false)
                            <td class="py-2.5 px-3 text-xs whitespace-nowrap">
                                <div class="flex flex-col gap-0.5 font-mono">
                                    <span class="text-ink-200">ROP: <strong class="text-white">{{ $p->reorder_point ?? '-' }}</strong></span>
                                    <span class="text-[10px] text-ink-400">Min {{ $p->stok_minimum ?? 0 }} · Maks {{ $p->stok_maksimum ?? '-' }}</span>
                                </div>
                            </td>
                        @endif

                        @if($visibleColumns['spesifikasi'] ?? false)
                            <td class="py-2.5 px-3 text-xs whitespace-nowrap">
                                <div class="flex flex-col gap-1">
                                    @php
                                        $kd = strtolower($p->kondisi ?? 'baru');
                                        $meta = $kondisiMeta[$kd] ?? [
                                            'bg' => 'bg-emerald-500/15',
                                            'border' => 'border-emerald-500/30',
                                            'text' => 'text-emerald-400',
                                            'dot' => 'bg-emerald-400',
                                            'desc' => 'Baru (Segel Pabrik / Fresh Stock)'
                                        ];
                                        $kdNama = collect($kondisiList)->firstWhere('kode', $kd)['nama'] ?? ucfirst($kd);
                                        $kdDesc = collect($kondisiList)->firstWhere('kode', $kd)['keterangan'] ?? $meta['desc'];
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $meta['bg'] }} {{ $meta['text'] }} border {{ $meta['border'] }} w-fit" title="{{ $kdDesc }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                        <span>{{ $kdNama }}</span>
                                    </span>
                                    <span class="text-[10px] text-ink-400 uppercase font-mono">{{ $p->satuan ?? 'pcs' }}</span>
                                </div>
                            </td>
                        @endif

                        @if($visibleColumns['status'] ?? false)
                            <td class="py-2.5 px-3 text-xs whitespace-nowrap">
                                <button type="button" wire:click="toggleStatusAktif({{ $p->id }})" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer {{ $p->is_active ? 'bg-up-mint/15 border-up-mint/30 text-up-mint hover:bg-up-mint/25' : 'bg-up-red/15 border-up-red/30 text-up-red hover:bg-up-red/25' }}" title="Klik untuk toggle status aktif/nonaktif">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $p->is_active ? 'bg-up-mint' : 'bg-up-red' }}"></span>
                                    <span>{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </button>
                            </td>
                        @endif

                        @if($visibleColumns['aksi'] ?? true)
                            <td class="py-2.5 px-3 whitespace-nowrap w-20">
                                <div class="flex items-center gap-1.5"
                                     x-data="{
                                         openMenu: false,
                                         coords: { top: 0, left: 0 },
                                         updatePosition() {
                                             const btn = this.$refs.triggerBtn;
                                             if (!btn) return;
                                             const rect = btn.getBoundingClientRect();
                                             const menuWidth = 176;
                                             const menuEl = this.$refs.menuDropdown;
                                             const menuHeight = menuEl ? (menuEl.offsetHeight || 260) : 260;
                                             const spaceBelow = window.innerHeight - rect.bottom;
                                             const spaceAbove = rect.top;

                                             let top;
                                             if (spaceBelow < menuHeight && spaceAbove > spaceBelow) {
                                                 top = Math.max(10, rect.top - menuHeight - 6);
                                             } else {
                                                 top = Math.min(window.innerHeight - menuHeight - 10, rect.bottom + 6);
                                             }

                                             let left = rect.right - menuWidth;
                                             if (left < 10) left = 10;
                                             if (left + menuWidth > window.innerWidth - 10) {
                                                 left = window.innerWidth - menuWidth - 10;
                                             }

                                             this.coords = { top: Math.round(top), left: Math.round(left) };
                                         },
                                         toggleMenu() {
                                             if (!this.openMenu) {
                                                 this.openMenu = true;
                                                 this.updatePosition();
                                                 this.$nextTick(() => {
                                                     this.updatePosition();
                                                 });
                                             } else {
                                                 this.openMenu = false;
                                             }
                                         },
                                         init() {
                                             const closeOnScroll = (e) => {
                                                 if (!this.openMenu) return;
                                                 if (this.$refs.menuDropdown && !this.$refs.menuDropdown.contains(e.target)) {
                                                     this.openMenu = false;
                                                 }
                                             };
                                             window.addEventListener('scroll', closeOnScroll, true);
                                             window.addEventListener('resize', closeOnScroll);
                                         }
                                     }">
                                    @if(isSuperAdminOrOwner() && (int) ($p->total_stok ?? 0) === 0 && (int) ($p->stok_logs_count ?? 0) === 0)
                                        <button wire:click="openSaldoAwalModal({{ $p->id }})" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border border-emerald-500/40 font-bold text-xs cursor-pointer whitespace-nowrap active:scale-95 transition" title="Input Saldo Awal Fisik (Ekuitas Modal Pemilik)">
                                            📥 + Saldo Awal
                                        </button>
                                    @endif

                                    <button wire:click="openEditProdukModal({{ $p->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-mint/10 hover:bg-up-mint/20 text-up-mint border border-up-mint/30 font-bold text-xs cursor-pointer whitespace-nowrap active:scale-95 transition" title="Edit Master Produk">
                                        ✏️ Edit
                                    </button>

                                    <!-- Dropdown Menu Lainnya (Fixed Floating Teleport - 100% bebas dari potongan overflow/table) -->
                                    <div class="relative">
                                        <button x-ref="triggerBtn"
                                                @click.stop="toggleMenu()"
                                                type="button"
                                                class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 text-xs cursor-pointer active:scale-95 transition"
                                                title="Menu lainnya">
                                            •••
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="openMenu"
                                                 x-cloak
                                                 x-ref="menuDropdown"
                                                 @click.outside="openMenu = false"
                                                 @keydown.escape.window="openMenu = false"
                                                 :style="`position: fixed; top: ${coords.top}px; left: ${coords.left}px; z-index: 9999;`"
                                                 class="w-44 rounded-xl bg-ink-900 border border-white/20 shadow-[0_12px_36px_rgba(0,0,0,0.85)] py-1 text-xs backdrop-blur-xl">
                                                <button type="button" wire:click="duplikatProduk({{ $p->id }})" @click="openMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                    <span>📑</span> Duplikat Produk
                                                </button>
                                                <button type="button" wire:click="toggleStatusAktif({{ $p->id }})" @click="openMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 {{ $p->is_active ? 'text-up-amber' : 'text-up-mint' }} hover:bg-white/5 transition cursor-pointer">
                                                    <span>{{ $p->is_active ? '⏸️' : '▶️' }}</span> {{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                                <button type="button" wire:click="copySingleProduk({{ $p->id }})" @click="openMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                    <span>📋</span> Salin Harga
                                                </button>
                                                <button type="button" wire:click="orderPo({{ $p->id }})" @click="openMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                    <span>📦</span> Order PO
                                                </button>
                                                <a href="{{ route('barcode.print') }}?ids={{ $p->id }}" target="_blank" class="flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition">
                                                    <span>🖨️</span> Cetak Label QR
                                                </a>
                                                @can('lihat-audit-log')
                                                    <button type="button" wire:click="bukaRiwayat('produk', {{ $p->id }})" @click="openMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                        <span>📜</span> Riwayat Audit
                                                    </button>
                                                @endcan
                                                @if(isSuperAdminOrOwner())
                                                    <div class="border-t border-white/5 my-1"></div>
                                                    <button type="button"
                                                            wire:click="hapusProduk({{ $p->id }})"
                                                            wire:confirm="Yakin ingin menghapus master produk '{{ $p->nama }}'? Jika produk memiliki riwayat transaksi/mutasi, status akan dinonaktifkan."
                                                            @click="openMenu = false"
                                                            class="w-full text-left flex items-center gap-2 px-3 py-2 text-up-red hover:bg-up-red/10 transition cursor-pointer">
                                                        <span>🗑️</span> Hapus Produk
                                                    </button>
                                                @endif
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count(array_filter($visibleColumns)) ?: 7 }}" class="py-12 text-center text-ink-400">Belum ada produk. Klik "Tambah Produk" untuk menambahkan master produk baru (tersinkron jurnal akunting).</td>
                    </tr>
                @endforelse

                <x-slot:pagination>
                    {{ $produks->links() }}
                </x-slot:pagination>
            </x-prism.data-table>
            </div>

            <!-- 2. Grid View -->
            <div x-show="wmsViewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3.5" x-cloak>
                @forelse($produks as $p)
                    @php
                        $stokTotal = $p->stokItems->sum('jumlah');
                        $skuPertama = $p->skuVariants->first()?->sku;
                    @endphp
                    <div class="glass-panel p-4 rounded-2xl border border-white/5 hover:border-white/15 transition-[border-color,box-shadow] duration-140 flex flex-col justify-between group">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="w-14 h-14 rounded-xl overflow-hidden bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 shrink-0 flex items-center justify-center relative">
                                    @if($p->thumbnail_url)
                                        <img src="{{ $p->thumbnail_url }}" alt="{{ $p->nama }}" class="w-full h-full object-cover" loading="lazy"
                                             onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                    @endif
                                    <span class="text-xl text-ink-500 items-center justify-center w-full h-full" style="{{ $p->thumbnail_url ? 'display:none;' : 'display:flex;' }}">📦</span>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <x-prism.stock-gauge :stok="$stokTotal" :min="$p->stok_minimum" />
                                    <span class="text-[10px] text-ink-400 font-mono mt-1">{{ $skuPertama ?? '-' }}</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                    @if($p->kualitas)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                            {{ $p->kualitas->nama }}
                                        </span>
                                    @endif
                                    @php
                                        $cardKd = strtolower($p->kondisi ?? 'baru');
                                        $cardMeta = $kondisiMeta[$cardKd] ?? [
                                            'bg' => 'bg-emerald-500/15',
                                            'border' => 'border-emerald-500/30',
                                            'text' => 'text-emerald-400',
                                            'dot' => 'bg-emerald-400',
                                            'desc' => 'Baru (Segel Pabrik / Fresh Stock)'
                                        ];
                                        $cardKdNama = collect($kondisiList)->firstWhere('kode', $cardKd)['nama'] ?? ucfirst($cardKd);
                                        $cardKdDesc = collect($kondisiList)->firstWhere('kode', $cardKd)['keterangan'] ?? $cardMeta['desc'];
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $cardMeta['bg'] }} {{ $cardMeta['text'] }} border {{ $cardMeta['border'] }}" title="{{ $cardKdDesc }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $cardMeta['dot'] }}"></span>
                                        <span>{{ $cardKdNama }}</span>
                                    </span>

                                    @if($p->brand)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-white/5 text-ink-400">
                                            {{ $p->brand->nama }}
                                        </span>
                                    @endif

                                    @if($p->supplier)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-up-primary/10 text-up-primary border border-up-primary/20 flex items-center gap-1" title="Supplier: {{ $p->supplier->nama }}">
                                            <span>🏢</span> {{ $p->supplier->nama }}
                                        </span>
                                    @endif
                                </div>

                                <h4 class="font-bold text-sm text-white group-hover:text-up-primary transition-colors line-clamp-2 leading-snug">
                                    {{ $p->nama }}
                                </h4>
                                <p class="text-[10px] text-ink-400 mt-0.5 truncate">
                                    {{ $p->kategoriRelasi?->nama ?? $p->kategori ?? '-' }}
                                </p>
                            </div>

                            @php
                                $kompatibelGrid = trim(($p->brand_kompatibel ?? '').' '.($p->model_kompatibel ?? ''));
                                if (empty($kompatibelGrid) && !empty($p->kompatibilitas_hp) && is_array($p->kompatibilitas_hp)) {
                                    $kompatibelGrid = collect($p->kompatibilitas_hp)->map(fn($k) => trim(($k['merk'] ?? '').' '.($k['model'] ?? '')))->filter()->implode(', ');
                                }
                                if (empty($kompatibelGrid) && $p->relationLoaded('tipeHps') && $p->tipeHps->isNotEmpty()) {
                                    $kompatibelGrid = $p->tipeHps->map(fn($t) => trim("{$t->merk} {$t->model}"))->filter()->implode(', ');
                                }
                            @endphp
                            <div class="px-2.5 py-2 rounded-xl bg-white/[0.02] border border-white/5 text-[11px] flex items-center justify-between gap-2">
                                <span class="text-ink-400 text-[10px] uppercase font-semibold shrink-0 flex items-center gap-1">
                                    <span>📱</span> Kompatibel:
                                </span>
                                <span class="text-ink-200 font-medium truncate text-right text-[11px]" title="{{ $kompatibelGrid ?: 'Universal / Semua' }}">
                                    {{ $kompatibelGrid ?: 'Universal' }}
                                </span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-between text-xs">
                                @if(isSuperAdminOrOwner())
                                    <div>
                                        <span class="text-[9px] text-ink-500 uppercase block font-semibold">Beli</span>
                                        <span class="text-ink-300 font-medium">Rp {{ number_format($p->harga_beli, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[9px] text-ink-500 uppercase block font-semibold">Jual</span>
                                        <span class="font-bold text-up-mint">Rp {{ number_format($p->harga_jual_retail, 0, ',', '.') }}</span>
                                    </div>
                                @else
                                    <div class="w-full flex items-center justify-between">
                                        <span class="text-[10px] text-ink-400 uppercase font-semibold">Harga Jual</span>
                                        <span class="font-bold text-sm text-up-mint">Rp {{ number_format($p->harga_jual_retail, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5 text-[10px] space-y-1">
                                <span class="text-ink-500 font-semibold uppercase block text-[9px] flex items-center gap-1">
                                    <span>📍</span> Lokasi Rak &amp; Gudang:
                                </span>
                                @if($p->stokItems->isNotEmpty())
                                    <div class="space-y-0.5 max-h-24 overflow-y-auto">
                                        @foreach($p->stokItems as $si)
                                            <div class="flex items-center justify-between gap-1 text-ink-300 text-[10px]">
                                                <span class="truncate max-w-[120px]">{{ $si->gudang?->nama ?? '-' }} ({{ $si->jumlah }})</span>
                                                @if($si->rak)
                                                    <span class="text-up-primary font-bold shrink-0">🏷️ {{ $si->rak->kode }}</span>
                                                @else
                                                    <span class="text-ink-500 shrink-0">Tanpa Rak</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-ink-500 block text-[10px]">Belum ada stok gudang</span>
                                @endif
                            </div>
                        </div>

                        <div class="pt-2.5 mt-2.5 border-t border-white/5 flex items-center justify-between gap-2" x-data="{ openGridMenu: false }">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs text-ink-300">
                                <input type="checkbox"
                                       value="{{ $p->id }}"
                                       wire:model.live="selectedProdukIds"
                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30 cursor-pointer">
                                <span class="text-[10px]">Pilih</span>
                            </label>
                            <div class="flex items-center gap-1.5">
                                @if(isSuperAdminOrOwner() && (int) ($p->total_stok ?? 0) === 0 && (int) ($p->stok_logs_count ?? 0) === 0)
                                    <button wire:click="openSaldoAwalModal({{ $p->id }})" class="px-2 py-1 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 text-[11px] font-bold border border-emerald-500/40 cursor-pointer active:scale-95 transition" title="Input Saldo Awal Fisik">
                                        📥 + Saldo Awal
                                    </button>
                                @endif
                                <button wire:click="openEditProdukModal({{ $p->id }})" class="px-2.5 py-1 rounded-lg bg-up-mint/10 hover:bg-up-mint/20 text-up-mint text-[11px] font-bold border border-up-mint/20 cursor-pointer active:scale-95 transition">
                                    ✏️ Edit
                                </button>
                                <div class="relative"
                                     x-data="{
                                         openGridMenu: false,
                                         coords: { top: 0, left: 0 },
                                         updatePosition() {
                                             const btn = this.$refs.triggerGridBtn;
                                             if (!btn) return;
                                             const rect = btn.getBoundingClientRect();
                                             const menuWidth = 176;
                                             const menuEl = this.$refs.gridMenuDropdown;
                                             const menuHeight = menuEl ? (menuEl.offsetHeight || 260) : 260;
                                             const spaceBelow = window.innerHeight - rect.bottom;
                                             const spaceAbove = rect.top;

                                             let top;
                                             if (spaceBelow < menuHeight && spaceAbove > spaceBelow) {
                                                 top = Math.max(10, rect.top - menuHeight - 6);
                                             } else {
                                                 top = Math.min(window.innerHeight - menuHeight - 10, rect.bottom + 6);
                                             }

                                             let left = rect.right - menuWidth;
                                             if (left < 10) left = 10;
                                             if (left + menuWidth > window.innerWidth - 10) {
                                                 left = window.innerWidth - menuWidth - 10;
                                             }

                                             this.coords = { top: Math.round(top), left: Math.round(left) };
                                         },
                                         toggleMenu() {
                                             if (!this.openGridMenu) {
                                                 this.openGridMenu = true;
                                                 this.updatePosition();
                                                 this.$nextTick(() => {
                                                     this.updatePosition();
                                                 });
                                             } else {
                                                 this.openGridMenu = false;
                                             }
                                         },
                                         init() {
                                             const closeOnScroll = (e) => {
                                                 if (!this.openGridMenu) return;
                                                 if (this.$refs.gridMenuDropdown && !this.$refs.gridMenuDropdown.contains(e.target)) {
                                                     this.openGridMenu = false;
                                                 }
                                             };
                                             window.addEventListener('scroll', closeOnScroll, true);
                                             window.addEventListener('resize', closeOnScroll);
                                         }
                                     }">
                                    <button x-ref="triggerGridBtn"
                                            @click.stop="toggleMenu()"
                                            type="button"
                                            class="p-1 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 text-xs cursor-pointer active:scale-95 transition"
                                            title="Menu lainnya">
                                        •••
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="openGridMenu"
                                             x-cloak
                                             x-ref="gridMenuDropdown"
                                             @click.outside="openGridMenu = false"
                                             @keydown.escape.window="openGridMenu = false"
                                             :style="`position: fixed; top: ${coords.top}px; left: ${coords.left}px; z-index: 9999;`"
                                             class="w-44 rounded-xl bg-ink-900 border border-white/20 shadow-[0_12px_36px_rgba(0,0,0,0.85)] py-1 text-xs backdrop-blur-xl">
                                            <button type="button" wire:click="duplikatProduk({{ $p->id }})" @click="openGridMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                <span>📑</span> Duplikat Produk
                                            </button>
                                            <button type="button" wire:click="toggleStatusAktif({{ $p->id }})" @click="openGridMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 {{ $p->is_active ? 'text-up-amber' : 'text-up-mint' }} hover:bg-white/5 transition cursor-pointer">
                                                <span>{{ $p->is_active ? '⏸️' : '▶️' }}</span> {{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                            <button type="button" wire:click="copySingleProduk({{ $p->id }})" @click="openGridMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                <span>📋</span> Salin Harga
                                            </button>
                                            <button type="button" wire:click="orderPo({{ $p->id }})" @click="openGridMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                <span>📦</span> Order PO
                                            </button>
                                            <a href="{{ route('barcode.print') }}?ids={{ $p->id }}" target="_blank" class="flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition">
                                                <span>🖨️</span> Cetak Label QR
                                            </a>
                                            @can('lihat-audit-log')
                                                <button type="button" wire:click="bukaRiwayat('produk', {{ $p->id }})" @click="openGridMenu = false" class="w-full text-left flex items-center gap-2 px-3 py-2 text-ink-200 hover:bg-white/5 hover:text-white transition cursor-pointer">
                                                    <span>📜</span> Riwayat Audit
                                                </button>
                                            @endcan
                                            @if(isSuperAdminOrOwner())
                                                <div class="border-t border-white/5 my-1"></div>
                                                <button type="button"
                                                        wire:click="hapusProduk({{ $p->id }})"
                                                        wire:confirm="Yakin ingin menghapus master produk '{{ $p->nama }}'? Jika produk memiliki riwayat transaksi/mutasi, status akan dinonaktifkan."
                                                        @click="openGridMenu = false"
                                                        class="w-full text-left flex items-center gap-2 px-3 py-2 text-up-red hover:bg-up-red/10 transition cursor-pointer">
                                                    <span>🗑️</span> Hapus Produk
                                                </button>
                                            @endif
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-ink-400">Belum ada produk.</div>
                @endforelse
            </div>

            <div x-show="wmsViewMode === 'grid'" class="p-3 sm:p-4 rounded-2xl glass-panel border border-white/5">
                {{ $produks->links() }}
            </div>

            <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-[11px] text-ink-400">
                @if(auth()->user()?->hasRole('super-admin'))
                    💡 <strong class="text-ink-200">Tambah Produk / Tambah Stok = Pembelian dari supplier</strong> —
                    stok masuk dicatat ke <strong class="text-up-mint">StokLog</strong> dan otomatis membuat
                    <strong class="text-ink-200">jurnal akunting</strong> (Debit Persediaan / Kredit Utang Usaha) di modul Akunting.
                @else
                    💡 <strong class="text-up-amber">Stok masuk hanya via PO Supplier</strong> —
                    gunakan tab <strong class="text-ink-200">PO &amp; Supplier</strong> untuk pembelian stok (draft → kirim → terima).
                @endif
            </div>
        </div>

    <!-- MODAL: TAMBAH PRODUK -->
    @if($showProdukModal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/80 backdrop-blur-sm p-0 sm:p-4">
            <div class="w-full max-w-2xl glass-panel p-4 sm:p-6 rounded-t-3xl sm:rounded-3xl relative max-h-[92vh] sm:max-h-[90vh] overflow-y-auto" x-data="{ tab: 'utama' }">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                            <span>Tambah Produk Baru</span>
                        </h3>
                        <p class="text-[11px] text-ink-400 mt-0.5">Kelola data master barang, harga bertingkat, dan multi-satuan kemasan.</p>
                    </div>
                    <button wire:click="$set('showProdukModal', false)" class="text-ink-400 hover:text-white p-1.5 rounded-lg hover:bg-white/5 transition cursor-pointer">✕</button>
                </div>

                <!-- Tab Navigasi Sederhana -->
                <div class="flex items-center gap-1.5 p-1 bg-white/[0.04] rounded-2xl border border-white/10 mb-4 overflow-x-auto scrollbar-none flex-nowrap -mx-1 px-1 sm:mx-0 sm:px-1">
                    <button type="button" @click="tab = 'utama'"
                            :class="tab === 'utama' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
                            class="flex-shrink-0 sm:flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>📦 Info Pokok &amp; Harga</span>
                        @if($errors->has('produkForm.nama') || $errors->has('produkForm.kategori_id') || $errors->has('produkForm.harga_beli') || $errors->has('produkForm.harga_jual_retail'))
                            <span class="w-2 h-2 rounded-full bg-up-red animate-pulse" title="Input belum lengkap"></span>
                        @endif
                    </button>

                    <button type="button" @click="tab = 'satuan'"
                            :class="tab === 'satuan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
                            class="flex-shrink-0 sm:flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>🔄 Multi-Satuan</span>
                        @if(!empty($produkForm['multi_satuan']))
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-up-mint text-ink-950 font-black">
                                {{ count($produkForm['multi_satuan']) }}
                            </span>
                        @endif
                    </button>

                    <button type="button" @click="tab = 'lanjutan'"
                            :class="tab === 'lanjutan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
                            class="flex-shrink-0 sm:flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>⚙️ Detail &amp; Foto</span>
                        @if(!empty($fotoUploads))
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-up-accent text-ink-950 font-black">
                                {{ count($fotoUploads) }} Foto
                            </span>
                        @endif
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- TAB 1: INFO POKOK & HARGA -->
                    <div x-show="tab === 'utama'" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Produk *</label>
                            <input type="text" wire:model="produkForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="LCD iPhone 13 Original" />
                            @error('produkForm.nama') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kode Barcode (Fisik / Scan)</label>
                                <button type="button" wire:click="generateBarcodeForm('create')" class="text-[10px] text-up-amber hover:underline font-semibold cursor-pointer" title="Buat kode barcode otomatis">⚡ Auto</button>
                            </div>
                            <div class="relative flex items-center">
                                <button type="button"
                                        onclick="window.uteBarcode && window.uteBarcode.openCameraScanner({ title: 'Scan Barcode Produk', onScan: (code) => { @this.set('produkForm.barcode', code); } })"
                                        class="absolute left-2.5 p-1 rounded-lg text-up-primary hover:text-white hover:bg-up-primary/20 active:scale-95 transition-[transform,background-color,color] duration-150 flex items-center justify-center cursor-pointer z-10"
                                        title="Buka Scanner Barcode (Kamera HP / Web)"
                                        aria-label="Scan barcode kamera">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </button>
                                <input type="text" wire:model="produkForm.barcode" class="w-full pl-9 pr-3 py-2.5 rounded-xl glass-input text-xs font-mono font-medium" placeholder="Cth: 8991234500001 (klik icon barcode untuk scan)" />
                            </div>
                            @error('produkForm.barcode') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kategori Utama / Sub *</label>
                                <button type="button" wire:click="openQuickAdd('kategori')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.kategori_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih Kategori —</option>
                                @foreach($kategoriTree as $parent)
                                    <option value="{{ $parent->id }}" class="bg-ink-900 font-bold text-white">{{ $parent->nama }}</option>
                                    @foreach($parent->children as $child)
                                        <option value="{{ $child->id }}" class="bg-ink-900 text-ink-300">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kondisi Produk *</label>
                                <button type="button" wire:click="openQuickAdd('kondisi')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model.live="produkForm.kondisi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($kondisiList as $knd)
                                    <option value="{{ $knd['kode'] }}" class="bg-ink-900">
                                        ● {{ $knd['nama'] }} — {{ $knd['keterangan'] ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                            @php
                                $cKondisi = strtolower($produkForm['kondisi'] ?? 'baru');
                                $cMeta = $kondisiMeta[$cKondisi] ?? [
                                    'bg' => 'bg-emerald-500/15',
                                    'border' => 'border-emerald-500/30',
                                    'text' => 'text-emerald-400',
                                    'dot' => 'bg-emerald-400',
                                    'desc' => 'Baru (Segel Pabrik / Fresh Stock)'
                                ];
                                $cLabel = collect($kondisiList)->firstWhere('kode', $cKondisi)['nama'] ?? ucfirst($cKondisi);
                                $cDesc = collect($kondisiList)->firstWhere('kode', $cKondisi)['keterangan'] ?? $cMeta['desc'];
                            @endphp
                            <div class="mt-2 flex items-center gap-2 px-2.5 py-1.5 rounded-lg {{ $cMeta['bg'] }} border {{ $cMeta['border'] }}">
                                <span class="w-2 h-2 rounded-full {{ $cMeta['dot'] }} animate-pulse"></span>
                                <span class="text-[11px] font-bold {{ $cMeta['text'] }}">{{ $cLabel }}</span>
                                <span class="text-[10px] text-ink-300 truncate">— {{ $cDesc }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Brand Part</label>
                                <button type="button" wire:click="openQuickAdd('brand')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model.live="produkForm.brand_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih Brand Part —</option>
                                @foreach($allBrands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Merk suku cadang (cth: Crown, OGS, BPT, Apple Ori)</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kualitas</label>
                                <button type="button" wire:click="openQuickAdd('kualitas')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.kualitas_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih kualitas —</option>
                                @foreach($kualitasCreateList as $k)
                                    <option value="{{ $k->id }}" class="bg-ink-900">
                                        {{ $k->nama }} {{ $k->brand_id ? '('.($k->brand?->nama ?? 'Brand Khusus').')' : '(Universal)' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Grade khusus brand atau grade universal</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Supplier Utama / Default</label>
                                <button type="button" wire:click="openQuickAdd('supplier')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Supplier Baru</button>
                            </div>
                            <select wire:model="produkForm.supplier_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Tanpa / Belum Ditentukan —</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" class="bg-ink-900">{{ $sup->nama }} {{ $sup->telepon ? '('.$sup->telepon.')' : '' }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Otomatis terhubung ke PO dan pembelian supplier</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Satuan Dasar *</label>
                                <button type="button" wire:click="openQuickAdd('satuan')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.satuan_kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($satuanUnits as $s)
                                    <option value="{{ $s->kode }}" class="bg-ink-900">{{ $s->kode }} — {{ $s->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Satuan terkecil inventori (cth: pcs, meter)</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode SKU (opsional)</label>
                        <input type="text" wire:model="produkForm.sku" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="Otomatis ikuti Barcode jika kosong" />
                    </div>

                    <!-- Kecocokan Smartphone (Multi-Kompatibilitas Tipe HP — Merek & Model Terpisah) -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-bold text-white">📱 Kecocokan Smartphone (Kompatibilitas Tipe HP)</label>
                                <p class="text-[10px] text-ink-400">Pilih Merek HP dan Model HP target. Satu produk bisa cocok untuk banyak tipe HP tanpa duplikat.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-up-primary/10 border border-up-primary/20 text-up-primary font-mono font-semibold">
                                    {{ count($produkForm['tipe_hp_ids'] ?? []) }} Tipe Terpilih
                                </span>
                                <button type="button" wire:click="openQuickAdd('tipe_hp')" class="text-[11px] text-up-mint hover:underline font-semibold cursor-pointer">+ Tambah Tipe HP Baru</button>
                            </div>
                        </div>

                        <!-- Selector Kompatibilitas: 1. Merek HP -> 2. Pencarian & Multi-Select Tipe HP -->
                        <div class="p-3.5 rounded-xl bg-ink-950/40 border border-white/5 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                <div class="sm:col-span-5">
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1">1. Pilih Merek HP</label>
                                    <select wire:model.live="kompatibelMerekHp" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Pilih Merek Smartphone —</option>
                                        @foreach($merekHpOptions as $merek)
                                            <option value="{{ $merek }}" class="bg-ink-900">{{ $merek }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-7">
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1">
                                        2. Cari Model {{ $kompatibelMerekHp ? "({$kompatibelMerekHp})" : '' }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-400 text-xs">🔍</span>
                                        <input type="text"
                                               wire:model.live.debounce.150ms="searchTipeHpCreate"
                                               placeholder="{{ empty($kompatibelMerekHp) ? 'Pilih merek smartphone terlebih dahulu...' : 'Cari tipe/model '.$kompatibelMerekHp.' (cth: A11, 13 Pro, Note)...' }}"
                                               class="w-full pl-8 pr-8 py-2 rounded-xl glass-input text-xs {{ empty($kompatibelMerekHp) ? 'opacity-50 cursor-not-allowed' : '' }}"
                                               {{ empty($kompatibelMerekHp) ? 'disabled' : '' }} />
                                        @if(!empty($searchTipeHpCreate))
                                            <button type="button" wire:click="$set('searchTipeHpCreate', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-400 hover:text-white text-xs cursor-pointer">✕</button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if(!empty($kompatibelMerekHp))
                                <!-- Multi-Select Container -->
                                <div class="space-y-2 pt-2 border-t border-white/5">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-ink-300">Pilih Tipe HP Kompatibel:</span>
                                            <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary text-[10px] font-semibold font-mono">
                                                {{ count($kompatibelSelectedTipeHpIds) }} dipilih
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    wire:click="pilihSemuaTipeHp('create')"
                                                    class="text-[10px] text-up-primary hover:underline cursor-pointer">
                                                ✓ Pilih Semua ({{ $tipeHpCreateOptions->count() }})
                                            </button>
                                            <span class="text-ink-600">|</span>
                                            <button type="button"
                                                    wire:click="batalPilihSemuaTipeHp('create')"
                                                    class="text-[10px] text-ink-400 hover:text-white cursor-pointer">
                                                Batal Pilih
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Scrollable Multi-Select Grid / Checklist -->
                                    <div class="max-h-44 overflow-y-auto p-2 rounded-xl bg-ink-950/80 border border-white/10 grid grid-cols-2 sm:grid-cols-3 gap-1.5 custom-scrollbar">
                                        @forelse($tipeHpCreateOptions as $opt)
                                            @php
                                                $sudahTerdaftar = in_array($opt->id, array_map('intval', (array) ($produkForm['tipe_hp_ids'] ?? [])), true);
                                                $isSelected = in_array($opt->id, array_map('intval', $kompatibelSelectedTipeHpIds), true);
                                            @endphp
                                            <label class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer transition select-none {{ $sudahTerdaftar ? 'bg-white/[0.02] border-white/5 opacity-60' : ($isSelected ? 'bg-up-primary/15 border-up-primary/40 text-white font-medium' : 'bg-white/[0.03] border-white/5 hover:bg-white/[0.06] text-ink-300') }}">
                                                <input type="checkbox"
                                                       value="{{ $opt->id }}"
                                                       wire:model.live="kompatibelSelectedTipeHpIds"
                                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30"
                                                       {{ $sudahTerdaftar ? 'disabled' : '' }}>
                                                <span class="truncate flex-1">{{ $opt->model }}</span>
                                                @if($sudahTerdaftar)
                                                    <span class="text-[9px] text-up-mint font-semibold">✓</span>
                                                @endif
                                            </label>
                                        @empty
                                            <div class="col-span-full py-4 text-center text-xs text-ink-400">
                                                Tidak ada model {{ $kompatibelMerekHp }} yang cocok dengan pencarian "{{ $searchTipeHpCreate }}".
                                                <button type="button" wire:click="openQuickAdd('tipe_hp')" class="text-up-mint hover:underline font-semibold ml-1 cursor-pointer">+ Tambah Model Baru</button>
                                            </div>
                                        @endforelse
                                    </div>

                                    <!-- Tombol Aksi Tambahkan ke Produk -->
                                    <div class="flex items-center justify-between pt-1">
                                        <p class="text-[10px] text-ink-400">
                                            Centang model yang kompatibel, lalu klik tombol di sebelah kanan.
                                        </p>
                                        <button type="button"
                                                wire:click="tambahKompatibelKeForm('create')"
                                                class="py-1.5 px-4 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs font-semibold flex items-center justify-center gap-1.5 transition shadow-lg shadow-up-primary/20 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                {{ empty($kompatibelSelectedTipeHpIds) ? 'disabled' : '' }}>
                                            <span>+ Tambahkan {{ count($kompatibelSelectedTipeHpIds) > 0 ? '('.count($kompatibelSelectedTipeHpIds).')' : '' }} Tipe Terpilih</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Daftar Tipe HP Kompatibel Terpilih (Chips / Tags Anti-Duplikat) -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] text-ink-400">
                                <span class="font-medium">Daftar Kompatibilitas Terdaftar:</span>
                                <span class="text-[10px] text-up-mint">✓ Anti-Duplikat Aktif</span>
                            </div>

                            @if(!empty($selectedCreateTipeHps) && $selectedCreateTipeHps->isNotEmpty())
                                <div class="flex flex-wrap gap-2 p-2.5 rounded-xl bg-ink-950/60 border border-white/5 min-h-[44px] items-center">
                                    @foreach($selectedCreateTipeHps as $th)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-up-primary/15 text-up-primary border border-up-primary/30 transition hover:bg-up-primary/25">
                                            <span class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                                            <strong class="font-bold">{{ $th->merk }}</strong> {{ $th->model }}
                                            <button type="button"
                                                    wire:click="hapusKompatibelDariForm({{ $th->id }}, 'create')"
                                                    class="ml-1 text-ink-400 hover:text-up-red transition cursor-pointer"
                                                    title="Hapus {{ $th->merk }} {{ $th->model }} dari daftar">✕</button>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-xl bg-ink-950/30 border border-dashed border-white/10 text-center">
                                    <p class="text-xs text-ink-400">Belum ada tipe HP kompatibel yang ditambahkan.</p>
                                    <p class="text-[10px] text-ink-500 mt-0.5">Pilih Merek dan Model HP di atas, lalu klik <strong>+ Tambah</strong>.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Skema Harga Beli Dinamis & Harga Jual Margin -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white">💰 Skema Harga Beli &amp; Harga Jual</span>
                            <div class="flex items-center gap-2">
                                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-ink-300 cursor-pointer">
                                    <input type="radio" value="manual" wire:model.live="produkForm.metode_harga_beli" class="text-up-primary focus:ring-0">
                                    <span>Beli Manual</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-up-mint cursor-pointer">
                                    <input type="radio" value="average" wire:model.live="produkForm.metode_harga_beli" class="text-up-mint focus:ring-0">
                                    <span>Otomatis (Moving Average PO)</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-ink-300">Harga Beli (Rp) *</label>
                                    @if(($produkForm['metode_harga_beli'] ?? 'manual') === 'average')
                                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-up-mint/20 text-up-mint font-bold">Auto-Avg PO</span>
                                    @endif
                                </div>
                                <input type="text" inputmode="numeric" x-format-number wire:model.live="produkForm.harga_beli" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                <p class="text-[9px] text-ink-500 mt-1">
                                    {{ ($produkForm['metode_harga_beli'] ?? 'manual') === 'average' ? 'Diperbarui otomatis saat terima PO/supplier (rata-rata tertimbang).' : 'Harga beli pokok tetap manual.' }}
                                </p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-ink-300">Margin Jual (%)</label>
                                    <label class="inline-flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" wire:model.live="produkForm.hitung_dari_margin" class="rounded bg-black/40 border-white/20 text-up-primary focus:ring-0 w-3 h-3">
                                        <span class="text-[10px] text-up-primary font-bold">Hitung Jual</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <input type="number" step="0.5" min="0" max="1000" wire:model.live.debounce.300ms="produkForm.margin_persen" placeholder="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums pr-8" />
                                    <span class="absolute right-3 top-2.5 text-xs text-ink-400 font-bold">%</span>
                                </div>
                                <p class="text-[9px] text-ink-500 mt-1">Persentase keuntungan dari harga beli</p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-ink-300">Harga Jual Retail (Rp) *</label>
                                    @if(!empty($produkForm['hitung_dari_margin']))
                                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-up-primary/20 text-up-primary font-bold">Otomatis %</span>
                                    @endif
                                </div>
                                <input type="text" inputmode="numeric" x-format-number wire:model.live="produkForm.harga_jual_retail" wire:change="produkHargaJualBerubah" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                <p class="text-[9px] text-ink-500 mt-1">Harga jual ke konsumen akhir (retail)</p>
                            </div>
                        </div>
                    </div>

                    <!-- [T-44] Harga Tier per tipe konsumen -->
                    <div class="p-3.5 rounded-xl bg-up-primary/[0.07] border border-up-primary/20">
                        <p class="text-xs font-bold text-up-primary mb-2">Harga Tier Konsumen (Satu Sumber Harga Terpadu)</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['retail' => 'Retail', 'reseller' => 'Reseller', 'agen' => 'Agen'] as $tipe => $label)
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[10px] font-semibold text-ink-300">{{ $label }}</label>
                                        @if($tipe === 'retail')
                                            <span class="text-[9px] text-up-mint font-medium">Otomatis</span>
                                        @endif
                                    </div>
                                    <input type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        wire:model.live="produkForm.harga_tier.{{ $tipe }}.nominal_tetap"
                                        placeholder="Nominal"
                                        @if($tipe === 'retail') readonly tabindex="-1" title="Terkunci: otomatis sama dengan Harga Jual Retail" @endif
                                        class="w-full px-2 py-2 rounded-lg glass-input text-xs font-bold tabular-nums @if($tipe === 'retail') bg-white/5 opacity-80 cursor-not-allowed @endif" />
                                    <input type="number"
                                        wire:model.live="produkForm.harga_tier.{{ $tipe }}.persen_diskon"
                                        min="0" max="100" step="0.5"
                                        placeholder="% diskon"
                                        @if($tipe === 'retail') disabled tabindex="-1" @endif
                                        class="w-full px-2 py-2 mt-1.5 rounded-lg glass-input text-xs tabular-nums @if($tipe === 'retail') bg-white/5 opacity-40 cursor-not-allowed @endif" />
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[9px] text-ink-500 mt-2">Harga Retail otomatis sama dengan harga jual pokok. Reseller &amp; Agen dapat diatur nominal khusus atau diskon persen.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi Produk (Marketplace &amp; Detail)</label>
                        <textarea wire:model="produkForm.deskripsi" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Spesifikasi, catatan garansi, atau petunjuk pemasangan..."></textarea>
                    </div>
                </div>

                <!-- TAB 2: MULTI-SATUAN & KEMASAN -->
                <div x-show="tab === 'satuan'" class="space-y-4">
                    <div class="p-3.5 sm:p-4 rounded-2xl bg-up-primary/10 border border-up-primary/20 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div>
                                <h4 class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>📦 Konversi Multi-Satuan Khusus Produk Ini</span>
                                </h4>
                                <p class="text-[11px] text-ink-300 mt-1 leading-relaxed">
                                    Setiap barang memiliki kemasan berbeda (misal: 1 Dus LCD = 10 Pcs, atau 1 Box Baut = 100 Pcs).
                                    Pilih satuan dari master agar tidak duplikat, atau ketik baru bila belum tersedia. Konversi berantai akan dihitung otomatis saat pembelian (PO) dan stok gudang (GRN).
                                </p>
                            </div>
                            <button type="button" wire:click="addMultiSatuan('create')" class="w-full sm:w-auto justify-center px-3.5 py-2 rounded-xl bg-up-primary hover:bg-up-primary/80 text-white text-xs font-bold cursor-pointer transition shadow-sm shadow-up-primary/30 flex items-center gap-1.5 whitespace-nowrap min-h-[38px]">
                                <span>+ Tambah Satuan</span>
                            </button>
                        </div>

                        <!-- Visual Chained Hierarchy Summary -->
                        @if(!empty($produkForm['multi_satuan']))
                            <div class="mt-3 pt-3 border-t border-white/10 flex flex-wrap items-center gap-2">
                                <span class="text-[10px] text-ink-400 font-semibold">Alur Konversi:</span>
                                <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-xs font-bold text-white">
                                    1 {{ $produkForm['satuan_kode'] ?: 'pcs' }} (Satuan Dasar)
                                </span>
                                @foreach($produkForm['multi_satuan'] as $chainSk)
                                    @if(!empty($chainSk['satuan_kode']))
                                        <span class="text-ink-400 text-xs">←</span>
                                        <span class="px-2 py-0.5 rounded-md bg-up-primary/20 border border-up-primary/30 text-xs font-bold text-up-mint flex items-center gap-1">
                                            <span>1 {{ $chainSk['satuan_kode'] }}</span>
                                            <span class="text-[10px] text-ink-300">({{ ((float)($chainSk['faktor_konversi'] ?? 1) + 0) }} {{ $produkForm['satuan_kode'] ?: 'pcs' }})</span>
                                            @if(!empty($chainSk['is_satuan_beli']))
                                                <span class="text-[9px] bg-up-mint text-ink-950 px-1 rounded font-black">PO</span>
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if(!empty($produkForm['multi_satuan']))
                        <div class="space-y-3">
                            @foreach($produkForm['multi_satuan'] as $idx => $sk)
                                <div class="p-3.5 sm:p-4 rounded-2xl bg-white/[0.03] border border-white/10 hover:border-white/20 transition space-y-3">
                                    <!-- Header: Level Satuan + Checkbox PO + Tombol Hapus -->
                                    <div class="flex items-center justify-between gap-2 pb-2 border-b border-white/5">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-up-primary/20 text-up-primary text-[10px] font-bold">
                                                {{ $idx + 1 }}
                                            </span>
                                            <span class="text-xs font-bold text-white tracking-wide">
                                                Satuan Kemasan #{{ $idx + 1 }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2.5">
                                            <label class="inline-flex items-center gap-1.5 text-[10px] text-ink-300 hover:text-white cursor-pointer select-none">
                                                <input type="checkbox"
                                                       wire:model="produkForm.multi_satuan.{{ $idx }}.is_satuan_jual"
                                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                                                <span class="{{ !empty($sk['is_satuan_jual']) ? 'text-up-primary font-bold' : '' }}">Bisa Dijual (POS)</span>
                                            </label>
                                            <label class="inline-flex items-center gap-1.5 text-[10px] text-ink-300 hover:text-white cursor-pointer select-none">
                                                <input type="checkbox"
                                                       wire:model="produkForm.multi_satuan.{{ $idx }}.is_satuan_beli"
                                                       wire:click="toggleSatuanBeli('create', {{ $idx }})"
                                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                                                <span class="{{ !empty($sk['is_satuan_beli']) ? 'text-up-mint font-bold' : '' }}">Default Beli (PO)</span>
                                            </label>
                                            <button type="button"
                                                    wire:click="removeMultiSatuan({{ $idx }}, 'create')"
                                                    class="p-1.5 text-ink-400 hover:text-up-red hover:bg-up-red/10 rounded-lg text-xs cursor-pointer transition"
                                                    title="Hapus Satuan Kemasan">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Grid Input Utama Responsif (Satuan Kemasan, Rasio, Satuan Asal) -->
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                                        <!-- Satuan Kemasan -->
                                        <div class="sm:col-span-5">
                                            <label class="block text-[10px] font-semibold text-ink-400 mb-1">Satuan Kemasan</label>
                                            @if(!($sk['is_custom'] ?? false))
                                                <select wire:change="pilihSatuanUnit('create', {{ $idx }}, $event.target.value)"
                                                        class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900">
                                                    <option value="" class="bg-ink-900">— Pilih Satuan —</option>
                                                    @foreach($satuanUnits as $su)
                                                        @if(strtolower($su->kode) !== strtolower($produkForm['satuan_kode'] ?: 'pcs'))
                                                            <option value="{{ $su->kode }}" class="bg-ink-900" @selected(strtolower($sk['satuan_kode'] ?? '') === strtolower($su->kode))>
                                                                {{ ucfirst($su->kode) }} ({{ $su->nama }})
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                    <option value="__custom__" class="bg-ink-900 text-up-mint font-bold">+ Ketik Satuan Baru...</option>
                                                </select>
                                            @else
                                                <div class="flex items-center gap-1.5">
                                                    <input type="text"
                                                           wire:model="produkForm.multi_satuan.{{ $idx }}.satuan_kode"
                                                           wire:change="hitungKonversiMultiSatuan('create')"
                                                           placeholder="Cth: slop, ikat"
                                                           class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-semibold text-white">
                                                    <button type="button"
                                                            wire:click="batalCustomSatuan('create', {{ $idx }})"
                                                            class="px-2.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-[10px] text-ink-300 whitespace-nowrap cursor-pointer transition"
                                                            title="Pilih dari daftar satuan yang ada">
                                                        Daftar
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Pengali / Isi -->
                                        <div class="sm:col-span-3">
                                            <label class="block text-[10px] font-semibold text-ink-400 mb-1">Isi / Rasio</label>
                                            <input type="number"
                                                   step="any"
                                                   min="0.01"
                                                   wire:model="produkForm.multi_satuan.{{ $idx }}.pengali"
                                                   wire:change="hitungKonversiMultiSatuan('create')"
                                                   placeholder="10"
                                                   class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums text-center font-bold">
                                        </div>

                                        <!-- Dari Satuan -->
                                        <div class="sm:col-span-4">
                                            <label class="block text-[10px] font-semibold text-ink-400 mb-1">Dari Satuan</label>
                                            <select wire:model="produkForm.multi_satuan.{{ $idx }}.satuan_asal"
                                                    wire:change="hitungKonversiMultiSatuan('create')"
                                                    class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-medium bg-ink-900">
                                                <option value="{{ $produkForm['satuan_kode'] ?: 'pcs' }}" class="bg-ink-900">{{ $produkForm['satuan_kode'] ?: 'pcs' }} (Satuan Dasar)</option>
                                                @foreach($produkForm['multi_satuan'] as $pIdx => $prevSk)
                                                    @if($pIdx < $idx && !empty($prevSk['satuan_kode']))
                                                        <option value="{{ $prevSk['satuan_kode'] }}" class="bg-ink-900">{{ $prevSk['satuan_kode'] }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Sub-Card: Informasi & Skema Harga Satuan Kemasan -->
                                    <div class="p-2.5 sm:p-3 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs font-bold text-white">💰 Harga Satuan Kemasan</span>
                                                <span class="text-[10px] text-ink-400">({{ $sk['satuan_kode'] ?: 'Satuan' }})</span>
                                            </div>
                                            <!-- Toggle Mode Harga: Otomatis vs Manual -->
                                            <div class="inline-flex p-0.5 rounded-lg bg-black/40 border border-white/10 text-[10px] font-semibold">
                                                <button type="button"
                                                        wire:click="setModeHargaMultiSatuan('create', {{ $idx }}, 'auto')"
                                                        class="px-2 py-0.5 rounded-md transition cursor-pointer {{ ($sk['mode_harga'] ?? 'auto') === 'auto' ? 'bg-up-primary text-white shadow-sm font-bold' : 'text-ink-400 hover:text-white' }}">
                                                    ⚡ Otomatis
                                                </button>
                                                <button type="button"
                                                        wire:click="setModeHargaMultiSatuan('create', {{ $idx }}, 'manual')"
                                                        class="px-2 py-0.5 rounded-md transition cursor-pointer {{ ($sk['mode_harga'] ?? 'auto') === 'manual' ? 'bg-up-mint text-ink-950 shadow-sm font-bold' : 'text-ink-400 hover:text-white' }}">
                                                    ✏️ Manual
                                                </button>
                                            </div>
                                        </div>

                                        @if(($sk['mode_harga'] ?? 'auto') === 'auto')
                                            <!-- Mode Otomatis (Perhitungan dari Harga Dasar / Diskon) -->
                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center text-xs">
                                                <div class="sm:col-span-7 flex flex-wrap items-center gap-1.5 text-ink-300 text-[11px]">
                                                    <span>Dasar: <b class="text-white font-mono">Rp {{ number_format((float)($produkForm['harga_jual_retail'] ?? 0), 0, ',', '.') }}</b></span>
                                                    <span>× <b class="text-white font-mono">{{ (float)($sk['faktor_konversi'] ?? 1) + 0 }}</b></span>
                                                    <span>=</span>
                                                    <span class="text-white font-mono font-bold">Rp {{ number_format(round(((float)($sk['faktor_konversi'] ?? 1)) * ((float)($produkForm['harga_jual_retail'] ?? 0))), 0, ',', '.') }}</span>
                                                </div>
                                                <div class="sm:col-span-5 flex items-center justify-end gap-2">
                                                    <span class="text-[10px] text-ink-400 whitespace-nowrap">Diskon Kemasan (%):</span>
                                                    <input type="number"
                                                           step="0.5"
                                                           min="0"
                                                           max="100"
                                                           wire:model="produkForm.multi_satuan.{{ $idx }}.diskon_persen"
                                                           wire:change="hitungKonversiMultiSatuan('create')"
                                                           placeholder="0"
                                                           class="w-16 px-2 py-1 rounded-lg glass-input text-xs text-center font-bold font-mono">
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between pt-1 border-t border-white/5 text-xs">
                                                <span class="text-[10px] text-ink-400">Harga Jual Otomatis:</span>
                                                <div class="flex items-center gap-2">
                                                    @if(!empty($sk['diskon_persen']) && (float)$sk['diskon_persen'] > 0)
                                                        <span class="text-[10px] text-ink-400 line-through">Rp {{ number_format(round(((float)($sk['faktor_konversi'] ?? 1)) * ((float)($produkForm['harga_jual_retail'] ?? 0))), 0, ',', '.') }}</span>
                                                        <span class="text-[10px] text-up-amber font-semibold">(-{{ (float)$sk['diskon_persen'] }}%)</span>
                                                    @endif
                                                    <span class="text-sm font-bold text-up-mint font-mono">
                                                        Rp {{ number_format((float)($sk['harga_jual'] ?? 0), 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <!-- Mode Manual (Input Nominal Bebas) -->
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 items-center">
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-ink-400 mb-1">Harga Jual Kemasan (Manual) *</label>
                                                    <div class="relative flex items-center">
                                                        <span class="absolute left-2.5 text-xs text-ink-400 font-bold">Rp</span>
                                                        <input type="number"
                                                               min="0"
                                                               wire:model="produkForm.multi_satuan.{{ $idx }}.harga_jual"
                                                               placeholder="0"
                                                               class="w-full pl-8 pr-2.5 py-1.5 rounded-lg glass-input text-xs font-bold font-mono text-up-mint">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-ink-400 mb-1">Estimasi HPP / Harga Beli</label>
                                                    <div class="relative flex items-center">
                                                        <span class="absolute left-2.5 text-xs text-ink-400 font-bold">Rp</span>
                                                        <input type="number"
                                                               min="0"
                                                               wire:model="produkForm.multi_satuan.{{ $idx }}.harga_beli"
                                                               placeholder="0"
                                                               class="w-full pl-8 pr-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-ink-200">
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-[9px] text-ink-400 italic">Harga satuan kemasan diset manual tetap, tidak berubah otomatis bila harga retail dasar diubah.</p>
                                        @endif
                                    </div>

                                    <!-- Footer: Detail Rumus Konversi & Input Barcode Kemasan dengan Scan Icon -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2.5 border-t border-white/5 text-[11px]">
                                        <div class="flex items-center gap-1.5 text-up-mint font-medium flex-wrap">
                                            <span>⚡ 1 {{ $sk['satuan_kode'] ?: '...' }} = {{ ((float)($sk['pengali'] ?? 1) + 0) }} {{ $sk['satuan_asal'] ?: ($produkForm['satuan_kode'] ?: 'pcs') }}</span>
                                            @if(strtolower($sk['satuan_asal'] ?? '') !== strtolower($produkForm['satuan_kode'] ?? 'pcs'))
                                                <span>= <b class="text-white">{{ (float)($sk['faktor_konversi'] ?? 1) + 0 }}</b> {{ $produkForm['satuan_kode'] ?: 'pcs' }}</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-1.5 w-full sm:w-auto">
                                            <span class="text-[10px] text-ink-400 whitespace-nowrap">Barcode:</span>
                                            <div class="relative flex items-center flex-1 sm:w-48">
                                                <button type="button"
                                                        onclick="window.uteBarcode && window.uteBarcode.openCameraScanner({ title: 'Scan Barcode Kemasan', onScan: (code) => { @this.set('produkForm.multi_satuan.{{ $idx }}.barcode', code); } })"
                                                        class="absolute left-1.5 p-1 rounded-md text-up-primary hover:text-white hover:bg-up-primary/20 active:scale-95 transition flex items-center justify-center cursor-pointer z-10"
                                                        title="Scan Barcode Kemasan via Kamera">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                                    </svg>
                                                </button>
                                                <input type="text"
                                                       wire:model="produkForm.multi_satuan.{{ $idx }}.barcode"
                                                       placeholder="Scan / ketik..."
                                                       class="w-full pl-7 pr-2.5 py-1.5 rounded-lg glass-input text-[11px] font-mono">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 px-4 rounded-2xl bg-white/[0.02] border border-dashed border-white/10 text-center space-y-2">
                            <span class="text-2xl">📦</span>
                            <p class="text-xs text-ink-300 font-semibold">Produk ini hanya menggunakan Satuan Tunggal ({{ $produkForm['satuan_kode'] ?: 'pcs' }}).</p>
                            <p class="text-[11px] text-ink-400 max-w-md mx-auto">Jika produk dibeli atau disimpan dalam box/dus/karton dengan isi lebih dari 1, klik tombol di bawah untuk menambah satuan kemasan.</p>
                            <button type="button" wire:click="addMultiSatuan('create')" class="mt-2 px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-up-mint border border-up-mint/30 text-xs font-bold cursor-pointer transition">
                                + Tambah Satuan Kemasan
                            </button>
                        </div>
                    @endif
                </div>

                <!-- TAB 3: DETAIL & LANJUTAN -->
                <div x-show="tab === 'lanjutan'" class="space-y-3.5">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-ink-300">🔄 Kompatibel Antar Produk (Substitusi / Part Pengganti)</label>
                        </div>
                        <x-prism.searchable-multi-select model="produkForm.produk_kompatibel_ids" :options="$allProdukOptions" placeholder="Cari produk substitusi / pengganti jika stok barang ini kosong..." />
                        <p class="text-[9px] text-ink-500 mt-1">Produk lain yang fungsinya sama dan bisa saling menggantikan saat kasir atau teknisi servis kehabisan stok.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- [F2-3] Serial Number (SN) Toggle -->
                        <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30 flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-0.5">Serial Number (SN)</label>
                                <p class="text-[10px] text-up-mint/80">Wajib input SN saat GRN/stok masuk &amp; penjualan</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.defer="produkForm.sn" class="sr-only peer" />
                                <div class="w-10 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-[background-color,transform] peer-checked:bg-up-mint"></div>
                            </label>
                        </div>

                        <!-- Harga Fleksibel Toggle -->
                        <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-0.5">Harga Fleksibel</label>
                                <p class="text-[10px] text-up-amber/80">Input harga saat kasir</p>
                            </div>
                            @can('atur-harga-fleksibel')
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.defer="produkForm.harga_fleksibel" class="sr-only peer" />
                                    <div class="w-10 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-[background-color,transform] peer-checked:bg-up-amber"></div>
                                </label>
                            @else
                                <span class="text-[10px] text-ink-500">Khusus superadmin</span>
                            @endcan
                        </div>
                    </div>

                    @if(auth()->user()?->hasRole('super-admin'))
                        <div class="p-3.5 rounded-xl bg-up-accent/10 border border-up-accent/30">
                            <p class="text-xs font-bold text-up-accent mb-2">Stok Awal (Pembelian) — opsional</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Gudang Tujuan</label>
                                    <select wire:model.live="produkForm.gudang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Tanpa stok awal —</option>
                                        @foreach($gudangs as $g)
                                            <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Lokasi Rak Penempatan</label>
                                    <select wire:model="produkForm.rak_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" @if(empty($produkForm['gudang_id'])) disabled @endif>
                                        <option value="" class="bg-ink-900">— Tanpa Rak / Pilih Rak —</option>
                                        @php
                                            $filteredRaks = !empty($produkForm['gudang_id'])
                                                ? $raks->where('gudang_id', $produkForm['gudang_id'])
                                                : $raks;
                                        @endphp
                                        @foreach($filteredRaks as $rk)
                                            <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->nama }} ({{ $rk->kode }}) @if(empty($produkForm['gudang_id']) && $rk->gudang) - {{ $rk->gudang->nama }} @endif</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Qty Stok Awal</label>
                                    <input type="number" wire:model="produkForm.stok_awal" min="0" placeholder="0" class="w-full px-2.5 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                </div>
                            </div>
                            <p class="text-[10px] text-up-accent/80 mt-2">
                                Batas minimum stok dapat diatur pada blok Strategi Pengadaan Stok di bawah. Jika Qty diisi &gt; 0 → otomatis buat jurnal persediaan &amp; utang.
                            </p>
                        </div>
                    @endif

                    <!-- Konfigurasi Pengadaan (ABC, ROP, Min-Max, Modified JIT) -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <p class="text-xs font-bold text-ink-200">⚙️ Strategi Pengadaan Stok (WMS Auto ABC / ROP / Min-Max)</p>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-up-primary/15 text-up-primary border border-up-primary/30">Otomatis Sistem</span>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="produkForm.is_ondemand" class="rounded bg-black/40 border-white/20 text-up-accent focus:ring-0">
                                <span class="text-xs font-semibold text-up-accent">Modified JIT (On-Demand)</span>
                            </label>
                        </div>
                        <p class="text-[10px] text-ink-400">
                            Sistem secara berkala menghitung pergerakan omzet barang (Pareto 80/20) dan menetapkan kelas ABC, ROP, serta Min-Max secara otomatis. Untuk produk baru, awal diberikan Kelas B (Normal).
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5 sm:col-span-1">
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Klasifikasi ABC</label>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg bg-up-primary/20 text-up-primary font-bold text-xs border border-up-primary/30">
                                        Kelas {{ $produkForm['abc_class'] ?? 'B' }}
                                    </span>
                                    <span class="text-[10px] text-ink-400">Otomatis Sistem</span>
                                </div>
                                <input type="hidden" wire:model="produkForm.abc_class" value="{{ $produkForm['abc_class'] ?? 'B' }}">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Reorder Point (ROP)</label>
                                <input type="number" wire:model="produkForm.reorder_point" min="0" placeholder="Pemicu Order Otomatis" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Min Stock</label>
                                <input type="number" wire:model="produkForm.min_stock" min="0" placeholder="Batas Bawah" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                        </div>
                    </div>

                    <!-- Foto Produk Upload & Kamera -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-bold text-white">Foto Produk</label>
                                <p class="text-[10px] text-ink-400">Otomatis kompresi WebP (full &amp; thumbnail 250px).</p>
                            </div>
                            <span wire:loading wire:target="fotoUploads, fotoKameraUpload" class="text-[10px] text-up-mint font-semibold animate-pulse flex items-center gap-1">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Mengunggah…
                            </span>
                        </div>

                        <!-- Tombol Pilihan: Kamera Ponsel vs Galeri / File -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <!-- 1. Tombol Kamera HP (Memicu Kamera Belakang Ponsel Secara Langsung) -->
                            <label for="input-kamera-tambah" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-gradient-to-r from-up-mint/20 to-teal-500/20 hover:from-up-mint/30 hover:to-teal-500/30 border border-up-mint/40 text-up-mint font-bold text-xs cursor-pointer transition active:scale-[0.98] text-center shadow-sm">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>📸 Ambil Foto (Kamera)</span>
                                <input type="file" id="input-kamera-tambah" wire:model="fotoKameraUpload" accept="image/*" capture="environment" class="hidden" />
                            </label>

                            <!-- 2. Tombol Upload dari Galeri / File -->
                            <label for="input-file-tambah" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-semibold text-xs cursor-pointer transition active:scale-[0.98] text-center">
                                <svg class="w-4 h-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>📁 Pilih dari Galeri / File</span>
                                <input type="file" id="input-file-tambah" wire:model="fotoUploads" multiple accept="image/*" class="hidden" />
                            </label>
                        </div>

                        <!-- Opsi Live Viewfinder Webcam untuk Laptop / Desktop -->
                        <div class="flex items-center justify-between pt-0.5">
                            <button type="button" @click="bukaLiveKamera('tambah')" class="text-[11px] text-up-primary hover:underline flex items-center gap-1.5 cursor-pointer font-medium">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span>Buka Viewfinder Webcam / Kamera Live</span>
                            </button>
                        </div>

                        <!-- Daftar Thumbnail Foto Baru Siap Simpan -->
                        @if(!empty($fotoUploads))
                            <div class="space-y-1.5 pt-2 border-t border-white/5">
                                <div class="flex items-center justify-between text-[11px] text-ink-400">
                                    <span>{{ count($fotoUploads) }} foto siap disimpan:</span>
                                    <span class="text-[10px] text-up-mint font-semibold">Foto #1 otomatis jadi Foto Utama</span>
                                </div>
                                <div class="flex gap-2 flex-wrap">
                                    @foreach($fotoUploads as $idx => $fu)
                                        <div class="w-16 h-16 rounded-xl overflow-hidden border border-white/20 bg-black/40 relative group shadow-sm">
                                            <img src="{{ $fu->temporaryUrl() }}" class="w-full h-full object-cover">
                                            <span class="absolute bottom-0 inset-x-0 bg-black/70 text-[8px] font-mono text-center text-white truncate py-0.5">
                                                {{ $idx === 0 ? 'Utama' : '#' . ($idx + 1) }}
                                            </span>
                                            <button type="button" wire:click="hapusFotoUploadBaru({{ $idx }})" class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-up-red text-white flex items-center justify-center text-[10px] opacity-80 hover:opacity-100 cursor-pointer shadow" title="Hapus foto ini">
                                                ✕
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showProdukModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanProduk" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">Simpan Produk</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: IMPORT PRODUK EXCEL [T-43] -->
    @if($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Import Master Produk (Excel)</h3>
                    <button wire:click="tutupImportModal" class="text-ink-400 hover:text-white">✕</button>
                </div>

                @if($importStep === 'upload')
                    <div class="space-y-4">
                        {{-- Format Selector Radio Pills --}}
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-2">Pilih Format Sumber Data</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-[background-color,border-color,color] duration-140 {{ $importFormat === 'standar' ? 'bg-up-primary/15 border-up-primary text-white' : 'bg-white/5 border-white/10 text-ink-300 hover:bg-white/10' }}">
                                    <input type="radio" wire:model.live="importFormat" value="standar" class="accent-up-primary" />
                                    <div>
                                        <div class="text-xs font-bold leading-none">Template Standar UteParts</div>
                                        <div class="text-[10px] text-ink-400 mt-1">Format Excel baku dengan kolom lengkap</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-[background-color,border-color,color] duration-140 {{ $importFormat === 'sid_retail' ? 'bg-up-primary/15 border-up-primary text-white' : 'bg-white/5 border-white/10 text-ink-300 hover:bg-white/10' }}">
                                    <input type="radio" wire:model.live="importFormat" value="sid_retail" class="accent-up-primary" />
                                    <div>
                                        <div class="text-xs font-bold leading-none">Export SID Retail Pro</div>
                                        <div class="text-[10px] text-ink-400 mt-1">Mapping otomatis stok etalase & gudang</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        @if($importFormat === 'sid_retail')
                            {{-- Info Box SID Retail --}}
                            <div class="p-3.5 rounded-xl bg-up-primary/10 border border-up-primary/25 text-[11px] text-ink-200 leading-relaxed">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-primary text-white uppercase tracking-wider">SID Retail Pro</span>
                                    <span class="font-bold text-up-primary">Otomatis Terpetakan</span>
                                </div>
                                Kolom <span class="font-semibold text-white">KODE_BARANG</span>, <span class="font-semibold text-white">BARCODE</span>, <span class="font-semibold text-white">NAMA</span>, <span class="font-semibold text-white">KATEGORI</span>, <span class="font-semibold text-white">SUB_KATEGORI</span>, <span class="font-semibold text-white">HPP</span>, <span class="font-semibold text-white">HARGA_TOKO_1/2</span>, <span class="font-semibold text-white">HARGA_PARTAI_1</span>, serta stok <span class="font-semibold text-white">TOKO</span> dan <span class="font-semibold text-white">GUDANG</span> akan diproses otomatis secara hemat memori (streaming XML).
                            </div>

                            {{-- Dropdown Pilihan Gudang Cabang --}}
                            <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-white/5 border border-white/10">
                                <div>
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1.5">
                                        🏪 Gudang untuk Kolom <strong class="text-up-mint">TOKO</strong> (Etalase)
                                    </label>
                                    <select wire:model="gudangTokoId" class="w-full text-xs bg-slate-900/80 border border-white/15 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-up-primary cursor-pointer">
                                        <option value="">-- Lewati Stok Toko --</option>
                                        @foreach($gudangsCabang as $g)
                                            <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1.5">
                                        🏢 Gudang untuk Kolom <strong class="text-up-amber">GUDANG</strong> (Penyimpanan)
                                    </label>
                                    <select wire:model="gudangPusatId" class="w-full text-xs bg-slate-900/80 border border-white/15 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-up-primary cursor-pointer">
                                        <option value="">-- Lewati Stok Gudang --</option>
                                        @foreach($gudangsCabang as $g)
                                            <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @else
                            {{-- Info Box & Download Template Standar --}}
                            <div class="p-3.5 rounded-xl bg-up-primary/10 border border-up-primary/25 text-[11px] text-ink-200 leading-relaxed">
                                <p class="font-bold text-up-primary mb-1">📥 Template &amp; cara pakai</p>
                                Unduh template di bawah, isi (baris contoh bisa dihapus), lalu upload.<br>
                                <strong class="text-up-amber">Import = inisialisasi master produk</strong> (termasuk stok awal modal: jurnal Debit Persediaan / Kredit Modal).
                                Stok masuk harian TETAP wajib lewat <strong>PO Supplier</strong>. SKU/barcode duplikat otomatis ditolak — jalankan 2× tidak menimbulkan dobel.
                            </div>
                            <a href="{{ url('/api/wms/produk/import/template') }}" target="_blank"
                               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">
                                ⬇️ Download Template (.xlsx)
                            </a>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">File Excel (.xlsx / .xls / .csv, maks 5MB)</label>
                            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="w-full text-xs text-ink-300 file:mr-3 file:px-4 file:py-2 file:rounded-xl file:border-0 file:bg-up-primary file:text-white file:font-bold file:text-xs file:cursor-pointer" />
                            @error('importFile') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex gap-3 pt-3">
                            <button wire:click="tutupImportModal" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                            <button wire:click="previewImport" wire:loading.attr="disabled" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">
                                <span wire:loading.remove wire:target="previewImport">🔍 Preview (Dry-run)</span>
                                <span wire:loading wire:target="previewImport">Memeriksa…</span>
                            </button>
                        </div>
                    </div>
                @elseif($importStep === 'preview')
                    <div class="space-y-4">
                        <div class="grid grid-cols-4 gap-2 text-center text-[11px]">
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <span class="text-ink-400 block text-[9px] uppercase font-bold tracking-wider">Total Baris</span>
                                <span class="text-white text-base font-extrabold tabular-nums">{{ $importPreview['total_baris'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-up-mint/10 border border-up-mint/30">
                                <span class="text-up-mint block text-[9px] uppercase font-bold tracking-wider">Valid (Siap)</span>
                                <span class="text-up-mint text-base font-extrabold tabular-nums">{{ $importPreview['valid'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl {{ ($importPreview['invalid'] ?? 0) > 0 ? 'bg-up-red/10 border border-up-red/30' : 'bg-white/5 border border-white/10' }}">
                                <span class="{{ ($importPreview['invalid'] ?? 0) > 0 ? 'text-up-red' : 'text-ink-400' }} block text-[9px] uppercase font-bold tracking-wider">Error (Dilewati)</span>
                                <span class="{{ ($importPreview['invalid'] ?? 0) > 0 ? 'text-up-red' : 'text-ink-400' }} text-base font-extrabold tabular-nums">{{ $importPreview['invalid'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl {{ ($importPreview['total_peringatan'] ?? 0) > 0 ? 'bg-up-amber/10 border border-up-amber/30' : 'bg-white/5 border border-white/10' }}">
                                <span class="{{ ($importPreview['total_peringatan'] ?? 0) > 0 ? 'text-up-amber' : 'text-ink-400' }} block text-[9px] uppercase font-bold tracking-wider">Perhatian Khusus</span>
                                <span class="{{ ($importPreview['total_peringatan'] ?? 0) > 0 ? 'text-up-amber' : 'text-ink-400' }} text-base font-extrabold tabular-nums">{{ $importPreview['total_peringatan'] ?? 0 }}</span>
                            </div>
                        </div>

                        <!-- Banner Status Preview -->
                        <div class="p-3 rounded-xl {{ ($importPreview['invalid'] ?? 0) > 0 ? 'bg-up-amber/10 border border-up-amber/30 text-up-amber' : 'bg-up-mint/10 border border-up-mint/30 text-up-mint' }} text-[11px] flex items-center justify-between">
                            <span class="font-bold">{{ $importPreview['label_status'] ?? 'Hasil Pemeriksaan File' }}</span>
                            <span class="text-[10px] text-ink-300">Baris error otomatis dilewati</span>
                        </div>

                        <!-- Daftar Perhatian Khusus Kompatibilitas -->
                        @if(! empty($importPreview['warning_rows']))
                            <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 text-[11px] space-y-1.5 max-h-40 overflow-y-auto">
                                <p class="font-bold text-up-amber flex items-center gap-1.5 text-xs">
                                    <span>⚠️</span> Perhatian Kompatibilitas / Potensi Master Ganda:
                                </p>
                                <p class="text-[10px] text-ink-400">
                                    Sistem mendeteksi adanya kemiripan produk. Jika fisik sparepart identik, Anda disarankan menggabungkannya via multi-tipe HP atau produk substitusi:
                                </p>
                                <ul class="space-y-1 mt-1 text-[10px]">
                                    @foreach($importPreview['warning_rows'] as $w)
                                        <li class="bg-black/20 p-1.5 rounded-lg text-ink-200">
                                            <strong class="text-white">Baris {{ $w['baris'] }} ({{ $w['sku'] }}):</strong>
                                            @foreach($w['warnings'] as $warn)
                                                <div class="text-up-amber/90 mt-0.5">{{ $warn }}</div>
                                            @endforeach
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(! empty($importPreview['sampel']))
                            <x-prism.dual-scroll>
                                <table class="w-full text-[10px]">
                                    <thead>
                                        <tr class="text-ink-400 text-left border-b border-white/10">
                                            <th class="py-1.5 pr-2">Baris</th>
                                            <th class="py-1.5 pr-2">SKU</th>
                                            <th class="py-1.5 pr-2">Nama</th>
                                            <th class="py-1.5">Status &amp; Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($importPreview['sampel'] as $s)
                                            <tr class="border-b border-white/5">
                                                <td class="py-1.5 pr-2 font-mono text-ink-400">{{ $s['baris'] }}</td>
                                                <td class="py-1.5 pr-2 font-mono text-ink-200">{{ $s['sku'] }}</td>
                                                <td class="py-1.5 pr-2 text-ink-200 max-w-[200px] truncate">{{ $s['nama'] }}</td>
                                                <td class="py-1.5">
                                                    @if($s['valid'])
                                                        <span class="text-up-mint font-bold">✓ OK</span>
                                                        @if(! empty($s['warnings']))
                                                            <span class="ml-1 text-[9px] px-1.5 py-0.5 rounded bg-up-amber/20 text-up-amber border border-up-amber/30" title="{{ implode('; ', $s['warnings']) }}">Perhatian</span>
                                                        @endif
                                                    @else
                                                        <span class="text-up-red font-bold" title="{{ implode(' | ', $s['errors']) }}">✕ {{ implode('; ', $s['errors']) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-prism.dual-scroll>
                            <p class="text-[10px] text-ink-500">Menampilkan 5 baris pertama — error lengkap ada di notifikasi setelah import.</p>
                        @endif

                        <div class="flex gap-3 pt-3">
                            <button wire:click="previewImport" class="px-4 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Upload ulang</button>
                            <button wire:click="tutupImportModal" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                            <button wire:click="commitImport" wire:loading.attr="disabled" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">
                                <span wire:loading.remove wire:target="commitImport">✅ Import Sekarang</span>
                                <span wire:loading wire:target="commitImport">Mengirim…</span>
                            </button>
                        </div>
                    </div>
                @elseif($importStep === 'selesai')
                    <div class="space-y-4 py-3" @if(($activeImportLog['status'] ?? '') === 'proses') wire:poll.2000ms="cekStatusImport" @endif>
                        @if(($activeImportLog['status'] ?? '') === 'proses')
                            <div class="text-center py-6 space-y-3">
                                <div class="text-4xl animate-spin inline-block">⏳</div>
                                <h4 class="text-base font-bold text-white">Sedang Memproses Data Import…</h4>
                                <p class="text-xs text-ink-400 max-w-md mx-auto">
                                    Sistem sedang memproses baris produk di background queue. Halaman ini otomatis memuat progress secara berkala.
                                </p>
                                <div class="w-full bg-white/10 rounded-full h-2 max-w-xs mx-auto overflow-hidden">
                                    <div class="bg-up-primary h-2 rounded-full animate-pulse w-2/3"></div>
                                </div>
                                <button type="button" wire:click="cekStatusImport" class="text-xs text-up-mint hover:underline font-semibold cursor-pointer">
                                    🔄 Refresh Status Sekarang
                                </button>
                            </div>
                        @else
                            <!-- Status Selesai / Selesai Sebagian / Gagal -->
                            <div class="text-center pb-2">
                                <span class="inline-block text-xs font-bold px-3 py-1 rounded-full border {{ $activeImportLog['status_badge_class'] ?? 'bg-up-mint/20 text-up-mint border-up-mint/40' }} mb-2">
                                    {{ $activeImportLog['status_label'] ?? 'Import Selesai' }}
                                </span>
                                <h4 class="text-lg font-extrabold text-white">Laporan Hasil Import Master Produk</h4>
                            </div>

                            <div class="grid grid-cols-3 gap-2.5 text-center text-[11px]">
                                <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                                    <span class="text-ink-400 block text-[9px] uppercase font-bold tracking-wider">Total Baris</span>
                                    <span class="text-white text-xl font-extrabold tabular-nums">{{ $activeImportLog['total_baris'] ?? 0 }}</span>
                                </div>
                                <div class="p-3 rounded-2xl bg-up-mint/10 border border-up-mint/30">
                                    <span class="text-up-mint block text-[9px] uppercase font-bold tracking-wider">Sukses Diterima</span>
                                    <span class="text-up-mint text-xl font-extrabold tabular-nums">{{ $activeImportLog['sukses'] ?? 0 }}</span>
                                </div>
                                <div class="p-3 rounded-2xl {{ ($activeImportLog['gagal'] ?? 0) > 0 ? 'bg-up-red/10 border border-up-red/30' : 'bg-white/5 border border-white/10' }}">
                                    <span class="{{ ($activeImportLog['gagal'] ?? 0) > 0 ? 'text-up-red' : 'text-ink-400' }} block text-[9px] uppercase font-bold tracking-wider">Gagal / Dilewati</span>
                                    <span class="{{ ($activeImportLog['gagal'] ?? 0) > 0 ? 'text-up-red' : 'text-ink-400' }} text-xl font-extrabold tabular-nums">{{ $activeImportLog['gagal'] ?? 0 }}</span>
                                </div>
                            </div>

                            <!-- Peringatan Kompatibilitas Khusus -->
                            @if(! empty($activeImportLog['peringatan_list']))
                                <div class="p-3.5 rounded-2xl bg-up-amber/10 border border-up-amber/30 text-[11px] space-y-1.5 max-h-48 overflow-y-auto">
                                    <p class="font-bold text-up-amber flex items-center gap-1.5 text-xs">
                                        <span>🔍</span> Catatan Perhatian Kompatibilitas ({{ count($activeImportLog['peringatan_list']) }} produk):
                                    </p>
                                    <p class="text-[10px] text-ink-300">
                                        Produk-produk berikut berhasil diimpor, namun memiliki indikasi kemiripan fisik. Anda dapat menghubungkan kompatibilitasnya melalui tombol edit produk jika merupakan sparepart yang sama:
                                    </p>
                                    <ul class="space-y-1.5 text-[10px] mt-1">
                                        @foreach($activeImportLog['peringatan_list'] as $p)
                                            <li class="bg-black/30 p-2 rounded-xl border border-up-amber/20">
                                                <div class="font-semibold text-white">Baris {{ $p['baris'] ?? '-' }} — SKU: {{ $p['sku'] ?? '-' }} {{ isset($p['nama']) ? '('.$p['nama'].')' : '' }}</div>
                                                <div class="text-up-amber/90 mt-0.5">{{ $p['warning'] ?? '' }}</div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <!-- Baris Gagal jika ada -->
                            @if(! empty($activeImportLog['gagal_list']))
                                <div class="p-3.5 rounded-2xl bg-up-red/10 border border-up-red/30 text-[11px] space-y-1.5 max-h-44 overflow-y-auto">
                                    <p class="font-bold text-up-red flex items-center gap-1.5 text-xs">
                                        <span>❌</span> Rincian Baris Gagal / Dilewati ({{ count($activeImportLog['gagal_list']) }} baris):
                                    </p>
                                    <ul class="space-y-1 text-[10px]">
                                        @foreach($activeImportLog['gagal_list'] as $g)
                                            <li class="bg-black/30 p-2 rounded-xl border border-up-red/20 text-ink-300">
                                                <strong class="text-white">Baris {{ $g['baris'] ?? '-' }} [{{ $g['sku'] ?? '-' }}]:</strong> {{ $g['error'] ?? 'Terjadi kesalahan' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="pt-3 text-center">
                                <button wire:click="tutupImportModal" class="px-8 py-3 rounded-xl bg-up-primary hover:opacity-90 text-white font-bold text-xs cursor-pointer min-h-[44px]">
                                    Tutup &amp; Lihat Master Produk
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- MODAL: EDIT PRODUK -->
    @if($showEditProdukModal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/80 backdrop-blur-sm p-0 sm:p-4">
            <div class="w-full max-w-2xl glass-panel p-4 sm:p-6 rounded-t-3xl sm:rounded-3xl relative max-h-[92vh] sm:max-h-[90vh] overflow-y-auto" x-data="{ tab: 'utama' }">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                            <span>Edit Master Produk</span>
                        </h3>
                        <p class="text-[11px] text-ink-400 mt-0.5">Ubah data master, harga bertingkat, dan multi-satuan kemasan.</p>
                    </div>
                    <button wire:click="$set('showEditProdukModal', false)" class="text-ink-400 hover:text-white p-1.5 rounded-lg hover:bg-white/5 transition cursor-pointer">✕</button>
                </div>

                <!-- Tab Navigasi -->
                <div class="flex items-center gap-1.5 p-1 bg-white/[0.04] rounded-2xl border border-white/10 mb-4 overflow-x-auto scrollbar-none flex-nowrap -mx-1 px-1 sm:mx-0 sm:px-1">
                    <button type="button" @click="tab = 'utama'"
                            :class="tab === 'utama' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
                            class="flex-shrink-0 sm:flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>📦 Info Pokok &amp; Harga</span>
                        @if($errors->has('editProdukForm.nama') || $errors->has('editProdukForm.kategori_id') || $errors->has('editProdukForm.harga_beli') || $errors->has('editProdukForm.harga_jual_retail'))
                            <span class="w-2 h-2 rounded-full bg-up-red animate-pulse" title="Input belum lengkap"></span>
                        @endif
                    </button>

                    <button type="button" @click="tab = 'satuan'"
                            :class="tab === 'satuan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
                            class="flex-shrink-0 sm:flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>🔄 Multi-Satuan</span>
                        @if(!empty($editProdukForm['multi_satuan']))
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-up-mint text-ink-950 font-black">
                                {{ count($editProdukForm['multi_satuan']) }}
                            </span>
                        @endif
                    </button>

                    <button type="button" @click="tab = 'lanjutan'"
                            :class="tab === 'lanjutan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
                            class="flex-shrink-0 sm:flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>⚙️ Detail &amp; Foto</span>
                        @if(!empty($editFotoExisting) || !empty($editFotoUploads))
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-up-accent text-ink-950 font-black">
                                {{ count($editFotoExisting ?? []) + count($editFotoUploads ?? []) }} Foto
                            </span>
                        @endif
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- TAB 1: INFO POKOK & HARGA -->
                    <div x-show="tab === 'utama'" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Produk *</label>
                            <input type="text" wire:model="editProdukForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('editProdukForm.nama') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode SKU (Internal)</label>
                            <input type="text" wire:model="editProdukForm.sku" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono font-medium" placeholder="Cth: LCD-IP11-ORI" />
                            @error('editProdukForm.sku') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kode Barcode (Fisik / Scan)</label>
                                <button type="button" wire:click="generateBarcodeForm('edit')" class="text-[10px] text-up-amber hover:underline font-semibold cursor-pointer" title="Buat kode barcode otomatis">⚡ Auto</button>
                            </div>
                            <div class="relative flex items-center">
                                <button type="button"
                                        onclick="window.uteBarcode && window.uteBarcode.openCameraScanner({ title: 'Scan Barcode Produk', onScan: (code) => { @this.set('editProdukForm.barcode', code); } })"
                                        class="absolute left-2.5 p-1 rounded-lg text-up-primary hover:text-white hover:bg-up-primary/20 active:scale-95 transition-[transform,background-color,color] duration-150 flex items-center justify-center cursor-pointer z-10"
                                        title="Buka Scanner Barcode (Kamera HP / Web)"
                                        aria-label="Scan barcode kamera">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </button>
                                <input type="text" wire:model="editProdukForm.barcode" class="w-full pl-9 pr-3 py-2.5 rounded-xl glass-input text-xs font-mono font-medium" placeholder="Cth: 8991234500001 (klik icon barcode untuk scan)" />
                            </div>
                            @error('editProdukForm.barcode') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kategori Utama / Sub *</label>
                                <button type="button" wire:click="openQuickAdd('kategori')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.kategori_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih Kategori —</option>
                                @foreach($kategoriTree as $parent)
                                    <option value="{{ $parent->id }}" class="bg-ink-900 font-bold text-white">{{ $parent->nama }}</option>
                                    @foreach($parent->children as $child)
                                        <option value="{{ $child->id }}" class="bg-ink-900 text-ink-300">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kondisi Produk *</label>
                                <button type="button" wire:click="openQuickAdd('kondisi')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model.live="editProdukForm.kondisi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($kondisiList as $knd)
                                    <option value="{{ $knd['kode'] }}" class="bg-ink-900">
                                        ● {{ $knd['nama'] }} — {{ $knd['keterangan'] ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                            @php
                                $eKondisi = strtolower($editProdukForm['kondisi'] ?? 'baru');
                                $eMeta = $kondisiMeta[$eKondisi] ?? [
                                    'bg' => 'bg-emerald-500/15',
                                    'border' => 'border-emerald-500/30',
                                    'text' => 'text-emerald-400',
                                    'dot' => 'bg-emerald-400',
                                    'desc' => 'Baru (Segel Pabrik / Fresh Stock)'
                                ];
                                $eLabel = collect($kondisiList)->firstWhere('kode', $eKondisi)['nama'] ?? ucfirst($eKondisi);
                                $eDesc = collect($kondisiList)->firstWhere('kode', $eKondisi)['keterangan'] ?? $eMeta['desc'];
                            @endphp
                            <div class="mt-2 flex items-center gap-2 px-2.5 py-1.5 rounded-lg {{ $eMeta['bg'] }} border {{ $eMeta['border'] }}">
                                <span class="w-2 h-2 rounded-full {{ $eMeta['dot'] }} animate-pulse"></span>
                                <span class="text-[11px] font-bold {{ $eMeta['text'] }}">{{ $eLabel }}</span>
                                <span class="text-[10px] text-ink-300 truncate">— {{ $eDesc }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Brand Part</label>
                                <button type="button" wire:click="openQuickAdd('brand')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model.live="editProdukForm.brand_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih Brand Part —</option>
                                @foreach($allBrands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Merk suku cadang (cth: Crown, OGS, BPT, Apple Ori)</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kualitas</label>
                                <button type="button" wire:click="openQuickAdd('kualitas')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.kualitas_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih kualitas —</option>
                                @foreach($kualitasEditList as $k)
                                    <option value="{{ $k->id }}" class="bg-ink-900">
                                        {{ $k->nama }} {{ $k->brand_id ? '('.($k->brand?->nama ?? 'Brand Khusus').')' : '(Universal)' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Grade khusus brand atau grade universal</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Supplier Utama / Default</label>
                                <button type="button" wire:click="openQuickAdd('supplier')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Supplier Baru</button>
                            </div>
                            <select wire:model="editProdukForm.supplier_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Tanpa / Belum Ditentukan —</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" class="bg-ink-900">{{ $sup->nama }} {{ $sup->telepon ? '('.$sup->telepon.')' : '' }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Otomatis terhubung ke PO dan pembelian supplier</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Satuan Dasar *</label>
                                <button type="button" wire:click="openQuickAdd('satuan')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.satuan_kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($satuanUnits as $s)
                                    <option value="{{ $s->kode }}" class="bg-ink-900">{{ $s->kode }} — {{ $s->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Satuan terkecil inventori (cth: pcs, meter)</p>
                        </div>
                    </div>

                    <!-- Kecocokan Smartphone (Multi-Kompatibilitas Tipe HP — Merek & Model Terpisah) -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-bold text-white">📱 Kecocokan Smartphone (Kompatibilitas Tipe HP)</label>
                                <p class="text-[10px] text-ink-400">Pilih Merek HP dan Model HP target. Satu produk bisa cocok untuk banyak tipe HP tanpa duplikat.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-up-primary/10 border border-up-primary/20 text-up-primary font-mono font-semibold">
                                    {{ count($editProdukForm['tipe_hp_ids'] ?? []) }} Tipe Terpilih
                                </span>
                                <button type="button" wire:click="openQuickAdd('tipe_hp')" class="text-[11px] text-up-mint hover:underline font-semibold cursor-pointer">+ Tambah Tipe HP Baru</button>
                            </div>
                        </div>

                        <!-- Selector Kompatibilitas: 1. Merek HP -> 2. Pencarian & Multi-Select Tipe HP -->
                        <div class="p-3.5 rounded-xl bg-ink-950/40 border border-white/5 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                <div class="sm:col-span-5">
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1">1. Pilih Merek HP</label>
                                    <select wire:model.live="editKompatibelMerekHp" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Pilih Merek Smartphone —</option>
                                        @foreach($merekHpOptions as $merek)
                                            <option value="{{ $merek }}" class="bg-ink-900">{{ $merek }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-7">
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1">
                                        2. Cari Model {{ $editKompatibelMerekHp ? "({$editKompatibelMerekHp})" : '' }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-400 text-xs">🔍</span>
                                        <input type="text"
                                               wire:model.live.debounce.150ms="searchTipeHpEdit"
                                               placeholder="{{ empty($editKompatibelMerekHp) ? 'Pilih merek smartphone terlebih dahulu...' : 'Cari tipe/model '.$editKompatibelMerekHp.' (cth: A11, 13 Pro, Note)...' }}"
                                               class="w-full pl-8 pr-8 py-2 rounded-xl glass-input text-xs {{ empty($editKompatibelMerekHp) ? 'opacity-50 cursor-not-allowed' : '' }}"
                                               {{ empty($editKompatibelMerekHp) ? 'disabled' : '' }} />
                                        @if(!empty($searchTipeHpEdit))
                                            <button type="button" wire:click="$set('searchTipeHpEdit', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-400 hover:text-white text-xs cursor-pointer">✕</button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if(!empty($editKompatibelMerekHp))
                                <!-- Multi-Select Container -->
                                <div class="space-y-2 pt-2 border-t border-white/5">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-ink-300">Pilih Tipe HP Kompatibel:</span>
                                            <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary text-[10px] font-semibold font-mono">
                                                {{ count($editKompatibelSelectedTipeHpIds) }} dipilih
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    wire:click="pilihSemuaTipeHp('edit')"
                                                    class="text-[10px] text-up-primary hover:underline cursor-pointer">
                                                ✓ Pilih Semua ({{ $tipeHpEditOptions->count() }})
                                            </button>
                                            <span class="text-ink-600">|</span>
                                            <button type="button"
                                                    wire:click="batalPilihSemuaTipeHp('edit')"
                                                    class="text-[10px] text-ink-400 hover:text-white cursor-pointer">
                                                Batal Pilih
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Scrollable Multi-Select Grid / Checklist -->
                                    <div class="max-h-44 overflow-y-auto p-2 rounded-xl bg-ink-950/80 border border-white/10 grid grid-cols-2 sm:grid-cols-3 gap-1.5 custom-scrollbar">
                                        @forelse($tipeHpEditOptions as $opt)
                                            @php
                                                $sudahTerdaftar = in_array($opt->id, array_map('intval', (array) ($editProdukForm['tipe_hp_ids'] ?? [])), true);
                                                $isSelected = in_array($opt->id, array_map('intval', $editKompatibelSelectedTipeHpIds), true);
                                            @endphp
                                            <label class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer transition select-none {{ $sudahTerdaftar ? 'bg-white/[0.02] border-white/5 opacity-60' : ($isSelected ? 'bg-up-primary/15 border-up-primary/40 text-white font-medium' : 'bg-white/[0.03] border-white/5 hover:bg-white/[0.06] text-ink-300') }}">
                                                <input type="checkbox"
                                                       value="{{ $opt->id }}"
                                                       wire:model.live="editKompatibelSelectedTipeHpIds"
                                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-up-primary/30"
                                                       {{ $sudahTerdaftar ? 'disabled' : '' }}>
                                                <span class="truncate flex-1">{{ $opt->model }}</span>
                                                @if($sudahTerdaftar)
                                                    <span class="text-[9px] text-up-mint font-semibold">✓</span>
                                                @endif
                                            </label>
                                        @empty
                                            <div class="col-span-full py-4 text-center text-xs text-ink-400">
                                                Tidak ada model {{ $editKompatibelMerekHp }} yang cocok dengan pencarian "{{ $searchTipeHpEdit }}".
                                                <button type="button" wire:click="openQuickAdd('tipe_hp')" class="text-up-mint hover:underline font-semibold ml-1 cursor-pointer">+ Tambah Model Baru</button>
                                            </div>
                                        @endforelse
                                    </div>

                                    <!-- Tombol Aksi Tambahkan ke Produk -->
                                    <div class="flex items-center justify-between pt-1">
                                        <p class="text-[10px] text-ink-400">
                                            Centang model yang kompatibel, lalu klik tombol di sebelah kanan.
                                        </p>
                                        <button type="button"
                                                wire:click="tambahKompatibelKeForm('edit')"
                                                class="py-1.5 px-4 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs font-semibold flex items-center justify-center gap-1.5 transition shadow-lg shadow-up-primary/20 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                {{ empty($editKompatibelSelectedTipeHpIds) ? 'disabled' : '' }}>
                                            <span>+ Tambahkan {{ count($editKompatibelSelectedTipeHpIds) > 0 ? '('.count($editKompatibelSelectedTipeHpIds).')' : '' }} Tipe Terpilih</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Daftar Tipe HP Kompatibel Terpilih (Chips / Tags Anti-Duplikat) -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] text-ink-400">
                                <span class="font-medium">Daftar Kompatibilitas Terdaftar:</span>
                                <span class="text-[10px] text-up-mint">✓ Anti-Duplikat Aktif</span>
                            </div>

                            @if(!empty($selectedEditTipeHps) && $selectedEditTipeHps->isNotEmpty())
                                <div class="flex flex-wrap gap-2 p-2.5 rounded-xl bg-ink-950/60 border border-white/5 min-h-[44px] items-center">
                                    @foreach($selectedEditTipeHps as $th)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-up-primary/15 text-up-primary border border-up-primary/30 transition hover:bg-up-primary/25">
                                            <span class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                                            <strong class="font-bold">{{ $th->merk }}</strong> {{ $th->model }}
                                            <button type="button"
                                                    wire:click="hapusKompatibelDariForm({{ $th->id }}, 'edit')"
                                                    class="ml-1 text-ink-400 hover:text-up-red transition cursor-pointer"
                                                    title="Hapus {{ $th->merk }} {{ $th->model }} dari daftar">✕</button>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-xl bg-ink-950/30 border border-dashed border-white/10 text-center">
                                    <p class="text-xs text-ink-400">Belum ada tipe HP kompatibel yang ditambahkan.</p>
                                    <p class="text-[10px] text-ink-500 mt-0.5">Pilih Merek dan Model HP di atas, lalu klik <strong>+ Tambah</strong>.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Skema Harga Beli Dinamis & Harga Jual Margin -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white">💰 Skema Harga Beli &amp; Harga Jual</span>
                            <div class="flex items-center gap-2">
                                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-ink-300 cursor-pointer">
                                    <input type="radio" value="manual" wire:model.live="editProdukForm.metode_harga_beli" class="text-up-primary focus:ring-0">
                                    <span>Beli Manual</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-up-mint cursor-pointer">
                                    <input type="radio" value="average" wire:model.live="editProdukForm.metode_harga_beli" class="text-up-mint focus:ring-0">
                                    <span>Otomatis (Moving Average PO)</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-ink-300">Harga Beli (Rp) *</label>
                                    @if(($editProdukForm['metode_harga_beli'] ?? 'manual') === 'average')
                                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-up-mint/20 text-up-mint font-bold">Auto-Avg PO</span>
                                    @endif
                                </div>
                                <input type="text" inputmode="numeric" x-format-number wire:model.live="editProdukForm.harga_beli" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                @error('editProdukForm.harga_beli') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                                <p class="text-[9px] text-ink-500 mt-1">
                                    {{ ($editProdukForm['metode_harga_beli'] ?? 'manual') === 'average' ? 'Diperbarui otomatis saat terima PO/supplier (rata-rata tertimbang).' : 'Harga beli pokok tetap manual.' }}
                                </p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-ink-300">Margin Jual (%)</label>
                                    <label class="inline-flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" wire:model.live="editProdukForm.hitung_dari_margin" class="rounded bg-black/40 border-white/20 text-up-primary focus:ring-0 w-3 h-3">
                                        <span class="text-[10px] text-up-primary font-bold">Hitung Jual</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <input type="number" step="0.5" min="0" max="1000" wire:model.live.debounce.300ms="editProdukForm.margin_persen" placeholder="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums pr-8" />
                                    <span class="absolute right-3 top-2.5 text-xs text-ink-400 font-bold">%</span>
                                </div>
                                <p class="text-[9px] text-ink-500 mt-1">Persentase keuntungan dari harga beli</p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-ink-300">Harga Jual Retail (Rp) *</label>
                                    @if(!empty($editProdukForm['hitung_dari_margin']))
                                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-up-primary/20 text-up-primary font-bold">Otomatis %</span>
                                    @endif
                                </div>
                                <input type="text" inputmode="numeric" x-format-number wire:model.live="editProdukForm.harga_jual_retail" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                @error('editProdukForm.harga_jual_retail') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                                <p class="text-[9px] text-ink-500 mt-1">Harga jual ke konsumen akhir (retail)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Harga Tier -->
                    <div class="p-3.5 rounded-xl bg-up-primary/[0.07] border border-up-primary/20">
                        <p class="text-xs font-bold text-up-primary mb-2">Harga Tier Pelanggan</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['retail' => 'Retail', 'reseller' => 'Reseller', 'agen' => 'Agen'] as $tipe => $label)
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[10px] font-semibold text-ink-300">{{ $label }}</label>
                                        @if($tipe === 'retail')
                                            <span class="text-[9px] text-up-mint font-medium">Otomatis</span>
                                        @endif
                                    </div>
                                    <input type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        wire:model="editProdukForm.harga_tier.{{ $tipe }}.nominal_tetap"
                                        placeholder="Nominal"
                                        @if($tipe === 'retail') readonly tabindex="-1" title="Terkunci: otomatis sama dengan Harga Jual Retail" @endif
                                        class="w-full px-2 py-2 rounded-lg glass-input text-xs font-bold tabular-nums @if($tipe === 'retail') bg-white/5 opacity-80 cursor-not-allowed @endif" />
                                    <input type="number"
                                        wire:model="editProdukForm.harga_tier.{{ $tipe }}.persen_diskon"
                                        min="0" max="100" step="0.5"
                                        placeholder="% diskon"
                                        @if($tipe === 'retail') disabled tabindex="-1" @endif
                                        class="w-full px-2 py-2 mt-1.5 rounded-lg glass-input text-xs tabular-nums @if($tipe === 'retail') bg-white/5 opacity-40 cursor-not-allowed @endif" />
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[9px] text-ink-500 mt-2">Harga Retail otomatis mengikuti Harga Jual Retail di atas. Reseller &amp; Agen dapat diatur diskon khusus.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi Produk (Marketplace &amp; Detail)</label>
                        <textarea wire:model="editProdukForm.deskripsi" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Spesifikasi, catatan garansi, atau petunjuk pemasangan..."></textarea>
                    </div>
                </div>

                <!-- TAB 2: MULTI-SATUAN & KEMASAN (Edit) -->
                <div x-show="tab === 'satuan'" class="space-y-4">
                    <div class="p-3.5 sm:p-4 rounded-2xl bg-up-primary/10 border border-up-primary/20 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div>
                                <h4 class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>📦 Konversi Multi-Satuan Khusus Produk Ini</span>
                                </h4>
                                <p class="text-[11px] text-ink-300 mt-1 leading-relaxed">
                                    Setiap barang memiliki kemasan berbeda (misal: 1 Dus LCD = 10 Pcs, atau 1 Box Baut = 100 Pcs).
                                    Pilih satuan dari master agar tidak duplikat, atau ketik baru bila belum tersedia.
                                </p>
                            </div>
                            <button type="button" wire:click="addMultiSatuan('edit')" class="w-full sm:w-auto justify-center px-3.5 py-2 rounded-xl bg-up-primary hover:bg-up-primary/80 text-white text-xs font-bold cursor-pointer transition shadow-sm shadow-up-primary/30 flex items-center gap-1.5 whitespace-nowrap min-h-[38px]">
                                <span>+ Tambah Satuan</span>
                            </button>
                        </div>

                        <!-- Visual Chained Hierarchy Summary -->
                        @if(!empty($editProdukForm['multi_satuan']))
                            <div class="mt-3 pt-3 border-t border-white/10 flex flex-wrap items-center gap-2">
                                <span class="text-[10px] text-ink-400 font-semibold">Alur Konversi:</span>
                                <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-xs font-bold text-white">
                                    1 {{ $editProdukForm['satuan_kode'] ?: 'pcs' }} (Satuan Dasar)
                                </span>
                                @foreach($editProdukForm['multi_satuan'] as $chainSk)
                                    @if(!empty($chainSk['satuan_kode']))
                                        <span class="text-ink-400 text-xs">←</span>
                                        <span class="px-2 py-0.5 rounded-md bg-up-primary/20 border border-up-primary/30 text-xs font-bold text-up-mint flex items-center gap-1">
                                            <span>1 {{ $chainSk['satuan_kode'] }}</span>
                                            <span class="text-[10px] text-ink-300">({{ ((float)($chainSk['faktor_konversi'] ?? 1) + 0) }} {{ $editProdukForm['satuan_kode'] ?: 'pcs' }})</span>
                                            @if(!empty($chainSk['is_satuan_beli']))
                                                <span class="text-[9px] bg-up-mint text-ink-950 px-1 rounded font-black">PO</span>
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if(!empty($editProdukForm['multi_satuan']))
                        <div class="space-y-3">
                            @foreach($editProdukForm['multi_satuan'] as $idx => $sk)
                                <div class="p-3.5 sm:p-4 rounded-2xl bg-white/[0.03] border border-white/10 hover:border-white/20 transition space-y-3">
                                    <!-- Header: Level Satuan + Checkbox PO + Tombol Hapus -->
                                    <div class="flex items-center justify-between gap-2 pb-2 border-b border-white/5">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-up-primary/20 text-up-primary text-[10px] font-bold">
                                                {{ $idx + 1 }}
                                            </span>
                                            <span class="text-xs font-bold text-white tracking-wide">
                                                Satuan Kemasan #{{ $idx + 1 }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2.5">
                                            <label class="inline-flex items-center gap-1.5 text-[10px] text-ink-300 hover:text-white cursor-pointer select-none">
                                                <input type="checkbox"
                                                       wire:model="editProdukForm.multi_satuan.{{ $idx }}.is_satuan_jual"
                                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                                                <span class="{{ !empty($sk['is_satuan_jual']) ? 'text-up-primary font-bold' : '' }}">Bisa Dijual (POS)</span>
                                            </label>
                                            <label class="inline-flex items-center gap-1.5 text-[10px] text-ink-300 hover:text-white cursor-pointer select-none">
                                                <input type="checkbox"
                                                       wire:model="editProdukForm.multi_satuan.{{ $idx }}.is_satuan_beli"
                                                       wire:click="toggleSatuanBeli('edit', {{ $idx }})"
                                                       class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                                                <span class="{{ !empty($sk['is_satuan_beli']) ? 'text-up-mint font-bold' : '' }}">Default Beli (PO)</span>
                                            </label>
                                            <button type="button"
                                                    wire:click="removeMultiSatuan({{ $idx }}, 'edit')"
                                                    class="p-1.5 text-ink-400 hover:text-up-red hover:bg-up-red/10 rounded-lg text-xs cursor-pointer transition"
                                                    title="Hapus Satuan Kemasan">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Grid Input Utama Responsif (Satuan Kemasan, Rasio, Satuan Asal) -->
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                                        <!-- Satuan Kemasan -->
                                        <div class="sm:col-span-5">
                                            <label class="block text-[10px] font-semibold text-ink-400 mb-1">Satuan Kemasan</label>
                                            @if(!($sk['is_custom'] ?? false))
                                                <select wire:change="pilihSatuanUnit('edit', {{ $idx }}, $event.target.value)"
                                                        class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-semibold text-white bg-ink-900">
                                                    <option value="" class="bg-ink-900">— Pilih Satuan —</option>
                                                    @foreach($satuanUnits as $su)
                                                        @if(strtolower($su->kode) !== strtolower($editProdukForm['satuan_kode'] ?: 'pcs'))
                                                            <option value="{{ $su->kode }}" class="bg-ink-900" @selected(strtolower($sk['satuan_kode'] ?? '') === strtolower($su->kode))>
                                                                {{ ucfirst($su->kode) }} ({{ $su->nama }})
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                    <option value="__custom__" class="bg-ink-900 text-up-mint font-bold">+ Ketik Satuan Baru...</option>
                                                </select>
                                            @else
                                                <div class="flex items-center gap-1.5">
                                                    <input type="text"
                                                           wire:model="editProdukForm.multi_satuan.{{ $idx }}.satuan_kode"
                                                           wire:change="hitungKonversiMultiSatuan('edit')"
                                                           placeholder="Cth: slop, ikat"
                                                           class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-semibold text-white">
                                                    <button type="button"
                                                            wire:click="batalCustomSatuan('edit', {{ $idx }})"
                                                            class="px-2.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-[10px] text-ink-300 whitespace-nowrap cursor-pointer transition"
                                                            title="Pilih dari daftar satuan yang ada">
                                                        Daftar
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Pengali / Isi -->
                                        <div class="sm:col-span-3">
                                            <label class="block text-[10px] font-semibold text-ink-400 mb-1">Isi / Rasio</label>
                                            <input type="number" step="any" min="0.01"
                                                   wire:model="editProdukForm.multi_satuan.{{ $idx }}.pengali"
                                                   wire:change="hitungKonversiMultiSatuan('edit')"
                                                   placeholder="10"
                                                   class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums text-center font-bold">
                                        </div>

                                        <!-- Dari Satuan -->
                                        <div class="sm:col-span-4">
                                            <label class="block text-[10px] font-semibold text-ink-400 mb-1">Dari Satuan</label>
                                            <select wire:model="editProdukForm.multi_satuan.{{ $idx }}.satuan_asal"
                                                    wire:change="hitungKonversiMultiSatuan('edit')"
                                                    class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-medium bg-ink-900">
                                                <option value="{{ $editProdukForm['satuan_kode'] ?: 'pcs' }}" class="bg-ink-900">{{ $editProdukForm['satuan_kode'] ?: 'pcs' }} (Satuan Dasar)</option>
                                                @foreach($editProdukForm['multi_satuan'] as $pIdx => $prevSk)
                                                    @if($pIdx < $idx && !empty($prevSk['satuan_kode']))
                                                        <option value="{{ $prevSk['satuan_kode'] }}" class="bg-ink-900">{{ $prevSk['satuan_kode'] }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Sub-Card: Informasi & Skema Harga Satuan Kemasan -->
                                    <div class="p-2.5 sm:p-3 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs font-bold text-white">💰 Harga Satuan Kemasan</span>
                                                <span class="text-[10px] text-ink-400">({{ $sk['satuan_kode'] ?: 'Satuan' }})</span>
                                            </div>
                                            <!-- Toggle Mode Harga: Otomatis vs Manual -->
                                            <div class="inline-flex p-0.5 rounded-lg bg-black/40 border border-white/10 text-[10px] font-semibold">
                                                <button type="button"
                                                        wire:click="setModeHargaMultiSatuan('edit', {{ $idx }}, 'auto')"
                                                        class="px-2 py-0.5 rounded-md transition cursor-pointer {{ ($sk['mode_harga'] ?? 'auto') === 'auto' ? 'bg-up-primary text-white shadow-sm font-bold' : 'text-ink-400 hover:text-white' }}">
                                                    ⚡ Otomatis
                                                </button>
                                                <button type="button"
                                                        wire:click="setModeHargaMultiSatuan('edit', {{ $idx }}, 'manual')"
                                                        class="px-2 py-0.5 rounded-md transition cursor-pointer {{ ($sk['mode_harga'] ?? 'auto') === 'manual' ? 'bg-up-mint text-ink-950 shadow-sm font-bold' : 'text-ink-400 hover:text-white' }}">
                                                    ✏️ Manual
                                                </button>
                                            </div>
                                        </div>

                                        @if(($sk['mode_harga'] ?? 'auto') === 'auto')
                                            <!-- Mode Otomatis (Perhitungan dari Harga Dasar / Diskon) -->
                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center text-xs">
                                                <div class="sm:col-span-7 flex flex-wrap items-center gap-1.5 text-ink-300 text-[11px]">
                                                    <span>Dasar: <b class="text-white font-mono">Rp {{ number_format((float)($editProdukForm['harga_jual_retail'] ?? 0), 0, ',', '.') }}</b></span>
                                                    <span>× <b class="text-white font-mono">{{ (float)($sk['faktor_konversi'] ?? 1) + 0 }}</b></span>
                                                    <span>=</span>
                                                    <span class="text-white font-mono font-bold">Rp {{ number_format(round(((float)($sk['faktor_konversi'] ?? 1)) * ((float)($editProdukForm['harga_jual_retail'] ?? 0))), 0, ',', '.') }}</span>
                                                </div>
                                                <div class="sm:col-span-5 flex items-center justify-end gap-2">
                                                    <span class="text-[10px] text-ink-400 whitespace-nowrap">Diskon Kemasan (%):</span>
                                                    <input type="number"
                                                           step="0.5"
                                                           min="0"
                                                           max="100"
                                                           wire:model="editProdukForm.multi_satuan.{{ $idx }}.diskon_persen"
                                                           wire:change="hitungKonversiMultiSatuan('edit')"
                                                           placeholder="0"
                                                           class="w-16 px-2 py-1 rounded-lg glass-input text-xs text-center font-bold font-mono">
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between pt-1 border-t border-white/5 text-xs">
                                                <span class="text-[10px] text-ink-400">Harga Jual Otomatis:</span>
                                                <div class="flex items-center gap-2">
                                                    @if(!empty($sk['diskon_persen']) && (float)$sk['diskon_persen'] > 0)
                                                        <span class="text-[10px] text-ink-400 line-through">Rp {{ number_format(round(((float)($sk['faktor_konversi'] ?? 1)) * ((float)($editProdukForm['harga_jual_retail'] ?? 0))), 0, ',', '.') }}</span>
                                                        <span class="text-[10px] text-up-amber font-semibold">(-{{ (float)$sk['diskon_persen'] }}%)</span>
                                                    @endif
                                                    <span class="text-sm font-bold text-up-mint font-mono">
                                                        Rp {{ number_format((float)($sk['harga_jual'] ?? 0), 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <!-- Mode Manual (Input Nominal Bebas) -->
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 items-center">
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-ink-400 mb-1">Harga Jual Kemasan (Manual) *</label>
                                                    <div class="relative flex items-center">
                                                        <span class="absolute left-2.5 text-xs text-ink-400 font-bold">Rp</span>
                                                        <input type="number"
                                                               min="0"
                                                               wire:model="editProdukForm.multi_satuan.{{ $idx }}.harga_jual"
                                                               placeholder="0"
                                                               class="w-full pl-8 pr-2.5 py-1.5 rounded-lg glass-input text-xs font-bold font-mono text-up-mint">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-ink-400 mb-1">Estimasi HPP / Harga Beli</label>
                                                    <div class="relative flex items-center">
                                                        <span class="absolute left-2.5 text-xs text-ink-400 font-bold">Rp</span>
                                                        <input type="number"
                                                               min="0"
                                                               wire:model="editProdukForm.multi_satuan.{{ $idx }}.harga_beli"
                                                               placeholder="0"
                                                               class="w-full pl-8 pr-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-ink-200">
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-[9px] text-ink-400 italic">Harga satuan kemasan diset manual tetap, tidak berubah otomatis bila harga retail dasar diubah.</p>
                                        @endif
                                    </div>

                                    <!-- Footer: Detail Rumus Konversi & Input Barcode Kemasan dengan Scan Icon -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2.5 border-t border-white/5 text-[11px]">
                                        <div class="flex items-center gap-1.5 text-up-mint font-medium flex-wrap">
                                            <span>⚡ 1 {{ $sk['satuan_kode'] ?: '...' }} = {{ ((float)($sk['pengali'] ?? 1) + 0) }} {{ $sk['satuan_asal'] ?: ($editProdukForm['satuan_kode'] ?: 'pcs') }}</span>
                                            @if(strtolower($sk['satuan_asal'] ?? '') !== strtolower($editProdukForm['satuan_kode'] ?? 'pcs'))
                                                <span>= <b class="text-white">{{ (float)($sk['faktor_konversi'] ?? 1) + 0 }}</b> {{ $editProdukForm['satuan_kode'] ?: 'pcs' }}</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-1.5 w-full sm:w-auto">
                                            <span class="text-[10px] text-ink-400 whitespace-nowrap">Barcode:</span>
                                            <div class="relative flex items-center flex-1 sm:w-48">
                                                <button type="button"
                                                        onclick="window.uteBarcode && window.uteBarcode.openCameraScanner({ title: 'Scan Barcode Kemasan', onScan: (code) => { @this.set('editProdukForm.multi_satuan.{{ $idx }}.barcode', code); } })"
                                                        class="absolute left-1.5 p-1 rounded-md text-up-primary hover:text-white hover:bg-up-primary/20 active:scale-95 transition flex items-center justify-center cursor-pointer z-10"
                                                        title="Scan Barcode Kemasan via Kamera">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                                    </svg>
                                                </button>
                                                <input type="text"
                                                       wire:model="editProdukForm.multi_satuan.{{ $idx }}.barcode"
                                                       placeholder="Scan / ketik..."
                                                       class="w-full pl-7 pr-2.5 py-1.5 rounded-lg glass-input text-[11px] font-mono">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 px-4 rounded-2xl bg-white/[0.02] border border-dashed border-white/10 text-center space-y-2">
                            <span class="text-2xl">📦</span>
                            <p class="text-xs text-ink-300 font-semibold">Produk ini hanya menggunakan Satuan Tunggal ({{ $editProdukForm['satuan_kode'] ?: 'pcs' }}).</p>
                            <p class="text-[11px] text-ink-400 max-w-md mx-auto">Jika produk dibeli atau disimpan dalam box/dus/karton dengan isi lebih dari 1, klik tombol di bawah untuk menambah satuan kemasan.</p>
                            <button type="button" wire:click="addMultiSatuan('edit')" class="mt-2 px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-up-mint border border-up-mint/30 text-xs font-bold cursor-pointer transition">
                                + Tambah Satuan Kemasan
                            </button>
                        </div>
                    @endif
                </div>

                <!-- TAB 3: DETAIL & LANJUTAN (Edit) -->
                <div x-show="tab === 'lanjutan'" class="space-y-3.5">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-ink-300">🔄 Kompatibel Antar Produk (Substitusi / Part Pengganti)</label>
                        </div>
                        <x-prism.searchable-multi-select model="editProdukForm.produk_kompatibel_ids" :options="collect($allProdukOptions)->reject(fn($o) => $o['id'] === $editProdukId)->values()->all()" placeholder="Cari produk substitusi / pengganti jika stok barang ini habis..." />
                        <p class="text-[9px] text-ink-500 mt-1">Produk lain yang fungsinya sama dan bisa saling menggantikan saat kasir atau teknisi servis kehabisan stok.</p>
                    </div>

                    <!-- Harga Fleksibel & SN -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300">Harga Fleksibel</label>
                                <p class="text-[9px] text-up-amber/80">Input harga saat kasir</p>
                            </div>
                            <input type="checkbox" wire:model.defer="editProdukForm.harga_fleksibel" class="w-4 h-4 rounded text-up-amber" />
                        </div>
                        <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30 flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300">Serial Number (SN)</label>
                                <p class="text-[9px] text-up-mint/80">Wajib SN saat stok &amp; jual</p>
                            </div>
                            <input type="checkbox" wire:model.defer="editProdukForm.sn" class="w-4 h-4 rounded text-up-mint" />
                        </div>
                    </div>

                    <!-- Konfigurasi Pengadaan (ABC, ROP, Min-Max, Modified JIT) -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <p class="text-xs font-bold text-ink-200">⚙️ Strategi Pengadaan Stok (WMS Auto ABC / ROP / Min-Max)</p>
                                <button type="button"
                                        wire:click="hitungOtomatisParameterEdit"
                                        class="px-2.5 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary border border-up-primary/30 text-[10px] font-bold flex items-center gap-1 transition active:scale-95 cursor-pointer">
                                    <span>⚡ Hitung Otomatis Sistem</span>
                                </button>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="editProdukForm.is_ondemand" class="rounded bg-black/40 border-white/20 text-up-accent focus:ring-0">
                                <span class="text-xs font-semibold text-up-accent">Modified JIT (On-Demand)</span>
                            </label>
                        </div>

                        @if(!empty($autoAbcKeterangan))
                            <div class="p-2.5 rounded-xl bg-up-primary/[0.07] border border-up-primary/20 text-[11px] text-ink-200 flex items-start gap-2">
                                <span class="text-up-primary text-sm shrink-0">📊</span>
                                <div class="leading-relaxed">
                                    <strong class="text-white font-semibold">Hasil Analisis WMS:</strong> {{ $autoAbcKeterangan }}
                                </div>
                            </div>
                        @else
                            <p class="text-[10px] text-ink-500">
                                Sistem menentukan kelas ABC secara otomatis berbasis akumulasi omzet &amp; pergerakan riil (Pareto 80/20) serta menghitung titik pemesanan optimal.
                            </p>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5 sm:col-span-1">
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Klasifikasi ABC</label>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg bg-up-primary/20 text-up-primary font-bold text-xs border border-up-primary/30">
                                        Kelas {{ $editProdukForm['abc_class'] ?? 'B' }}
                                    </span>
                                    <span class="text-[10px] text-ink-400">Otomatis Pareto</span>
                                </div>
                                <input type="hidden" wire:model="editProdukForm.abc_class" value="{{ $editProdukForm['abc_class'] ?? 'B' }}">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Reorder Point (ROP)</label>
                                <input type="number" wire:model="editProdukForm.reorder_point" min="0" placeholder="Pemicu Order Otomatis" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Min Stock</label>
                                <input type="number" wire:model="editProdukForm.min_stock" min="0" placeholder="Batas Bawah" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                        </div>
                    </div>

                    <!-- Lokasi Rak per Gudang Penyimpanan -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-ink-200">🏷️ Lokasi Rak &amp; Gudang Penyimpanan</p>
                            <span class="text-[10px] text-ink-400">Atur penempatan rak per gudang</span>
                        </div>

                        @if(!empty($editStokItems))
                            <div class="space-y-2">
                                @foreach($editStokItems as $si)
                                    @php
                                        $raksForGudang = $raks->where('gudang_id', $si['gudang_id']);
                                    @endphp
                                    <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                        <div>
                                            <span class="font-bold text-white text-xs block">🏢 {{ $si['gudang_nama'] }}</span>
                                            <span class="text-[10px] text-ink-400">Stok: <strong class="text-up-mint tabular-nums">{{ $si['jumlah'] }}</strong> unit</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <select wire:model="editStokRaks.{{ $si['id'] }}" class="px-2.5 py-1.5 rounded-xl glass-input text-xs font-medium min-w-[180px]">
                                                <option value="" class="bg-ink-900">— Tanpa Rak —</option>
                                                @foreach($raksForGudang as $rk)
                                                    <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->nama }} ({{ $rk->kode }})</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="simpanRakStokItem({{ $si['id'] }})" class="px-2.5 py-1.5 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary border border-up-primary/30 text-xs font-bold cursor-pointer whitespace-nowrap active:scale-95" title="Simpan lokasi rak untuk gudang ini">
                                                💾 Simpan Rak
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[11px] text-ink-500">Belum ada data inventori/gudang untuk produk ini. Stok dan lokasi rak akan terbentuk saat GRN atau penambahan stok.</p>
                        @endif
                    </div>

                    <!-- Galeri Foto Existing & Kelola Foto -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        <label class="block text-xs font-semibold text-ink-300">Galeri Foto Produk (WebP)</label>
                        @if(! empty($editFotoExisting))
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @foreach($editFotoExisting as $idx => $f)
                                    <div class="rounded-xl overflow-hidden border {{ !empty($f['is_primary']) ? 'border-up-mint ring-1 ring-up-mint' : 'border-white/10' }} bg-black/40 p-1.5 space-y-1.5">
                                        <div class="w-full aspect-square rounded-lg overflow-hidden bg-black/60 relative flex items-center justify-center">
                                            <img src="{{ $f['thumb'] ?? $f['url'] }}" class="w-full h-full object-cover"
                                                 onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                            <div class="items-center justify-center w-full h-full text-ink-500 text-center p-1" style="display:none;">
                                                <span class="text-[10px]">⚠️ Rusak</span>
                                            </div>
                                            @if(!empty($f['is_primary']))
                                                <span class="absolute top-1 left-1 px-1.5 py-0.5 rounded bg-up-mint text-ink-950 font-black text-[8px] uppercase tracking-wider">Utama</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center justify-between gap-1">
                                            @if(empty($f['is_primary']))
                                                <button type="button" wire:click="setFotoUtama({{ $idx }})" class="text-[9px] text-up-mint hover:underline font-semibold cursor-pointer">Set Utama</button>
                                            @else
                                                <span class="text-[9px] text-ink-500">Utama</span>
                                            @endif
                                            <button type="button" wire:click="hapusFotoItem({{ $idx }})" class="text-[9px] text-up-red hover:underline font-semibold cursor-pointer">Hapus</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[11px] text-ink-500">Belum ada foto tersimpan untuk produk ini.</p>
                        @endif

                        <!-- Tambah Foto Baru (Kamera & Galeri) -->
                        <div class="pt-3 border-t border-white/5 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-[11px] font-bold text-white">Tambah Foto Baru</label>
                                    <p class="text-[9px] text-ink-500">Foto baru otomatis dikonversi ke WebP full + thumbnail 250px hemat storage.</p>
                                </div>
                                <span wire:loading wire:target="editFotoUploads, editFotoKameraUpload" class="text-[10px] text-up-mint font-semibold animate-pulse flex items-center gap-1">
                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    Mengunggah…
                                </span>
                            </div>

                            <!-- Tombol Aksi: Kamera HP vs Galeri -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <!-- 1. Kamera HP -->
                                <label for="input-kamera-edit" class="flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-gradient-to-r from-up-mint/20 to-teal-500/20 hover:from-up-mint/30 hover:to-teal-500/30 border border-up-mint/40 text-up-mint font-bold text-xs cursor-pointer transition active:scale-[0.98] text-center shadow-sm">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>📸 Ambil Foto (Kamera)</span>
                                    <input type="file" id="input-kamera-edit" wire:model="editFotoKameraUpload" accept="image/*" capture="environment" class="hidden" />
                                </label>

                                <!-- 2. Galeri / File -->
                                <label for="input-file-edit" class="flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-semibold text-xs cursor-pointer transition active:scale-[0.98] text-center">
                                    <svg class="w-4 h-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>📁 Pilih dari Galeri / File</span>
                                    <input type="file" id="input-file-edit" wire:model="editFotoUploads" multiple accept="image/*" class="hidden" />
                                </label>
                            </div>

                            <!-- Opsi Live Webcam -->
                            <div class="flex items-center justify-between pt-0.5">
                                <button type="button" @click="bukaLiveKamera('edit')" class="text-[11px] text-up-primary hover:underline flex items-center gap-1.5 cursor-pointer font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <span>Buka Viewfinder Webcam / Kamera Live</span>
                                </button>
                            </div>

                            <!-- Thumbnail Foto Baru Siap Simpan -->
                            @if(!empty($editFotoUploads))
                                <div class="space-y-1 pt-1.5">
                                    <span class="text-[10px] text-ink-400 block">{{ count($editFotoUploads) }} foto baru siap ditambahkan:</span>
                                    <div class="flex gap-2 flex-wrap">
                                        @foreach($editFotoUploads as $idx => $efu)
                                            <div class="w-14 h-14 rounded-lg overflow-hidden border border-white/20 bg-black/40 relative group">
                                                <img src="{{ $efu->temporaryUrl() }}" class="w-full h-full object-cover">
                                                <button type="button" wire:click="hapusEditFotoUploadBaru({{ $idx }})" class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-up-red text-white flex items-center justify-center text-[10px] opacity-80 hover:opacity-100 cursor-pointer shadow" title="Hapus foto ini">
                                                    ✕
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 pt-4 border-t border-white/5 mt-5">
                    @if(isSuperAdminOrOwner() && $editProdukId)
                        <button type="button" wire:click="hapusProduk({{ $editProdukId }})" wire:confirm="Yakin ingin menghapus master produk ini? Jika produk memiliki riwayat transaksi/mutasi, status akan dinonaktifkan." class="px-4 py-3 rounded-xl bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 font-bold text-xs cursor-pointer min-h-[44px]">
                            🗑️ Hapus Produk
                        </button>
                    @endif
                    <div class="flex-1 flex gap-3 justify-end">
                        <button wire:click="$set('showEditProdukModal', false)" class="px-5 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button wire:click="simpanEditProduk" wire:loading.attr="disabled" class="px-6 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">
                            <span wire:loading.remove wire:target="simpanEditProduk">💾 Simpan Perubahan</span>
                            <span wire:loading wire:target="simpanEditProduk">Menyimpan…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: QUICK ADD MASTER DATA INLINE -->
    @if($showQuickAddModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full {{ $quickAddTipe === 'tipe_hp' ? 'max-w-md' : 'max-w-sm' }} glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        @if($quickAddTipe === 'tipe_hp')
                            <span>📱 + Tambah Tipe HP Baru</span>
                        @else
                            <span>+ Tambah {{ ucfirst($quickAddTipe) }} Baru</span>
                        @endif
                    </h3>
                    <button wire:click="$set('showQuickAddModal', false)" class="text-ink-400 hover:text-white transition cursor-pointer">✕</button>
                </div>

                <div class="space-y-3">
                    @if($quickAddTipe === 'tipe_hp')
                        <!-- 1. MERK HP: Dropdown List yang Ada + Opsi Isi Baru jika belum ada -->
                        <div class="space-y-2 p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-white">1. Merek Smartphone *</label>
                                <span class="text-[10px] text-ink-400">Pilih dari list atau isi baru</span>
                            </div>

                            <!-- Dropdown Merek yang Sudah Ada -->
                            <div>
                                <select wire:model.live="quickAddSelectedMerk" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                    <option value="" class="bg-ink-900">— Pilih Merek yang Sudah Ada ({{ count($merekHpOptions) }} merek) —</option>
                                    @foreach($merekHpOptions as $m)
                                        <option value="{{ $m }}" class="bg-ink-900">{{ $m }}</option>
                                    @endforeach
                                    <option value="__custom__" class="bg-ink-900 text-up-mint font-semibold">+ Merek Baru Lainnya (Ketik Baru)...</option>
                                </select>
                            </div>

                            <!-- Kolom Ketik Merek Baru jika belum ada di list -->
                            @if($quickAddSelectedMerk === '__custom__' || empty($quickAddSelectedMerk))
                                <div class="mt-1.5 pt-2 border-t border-white/5 space-y-1">
                                    <label class="block text-[11px] text-ink-300">
                                        {{ empty($quickAddSelectedMerk) ? 'Atau ketik Merek HP baru jika belum ada di atas:' : 'Ketik Merek HP Baru *:' }}
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="quickAddForm.merk"
                                        placeholder="cth: Infinix, Tecno, Google Pixel..."
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                                        autofocus
                                    />
                                </div>
                            @else
                                <div class="flex items-center justify-between text-[11px] text-ink-300 px-1 pt-0.5">
                                    <span>Merek aktif: <strong class="text-white">{{ $quickAddForm['merk'] }}</strong></span>
                                    <button type="button" wire:click="$set('quickAddSelectedMerk', '__custom__')" class="text-up-mint hover:underline text-[10px] cursor-pointer">+ Ketik Merek Baru</button>
                                </div>
                            @endif
                            @error('quickAddForm.merk') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- 2. MODEL / TIPE HP: Dropdown List yang Ada + Opsi Isi Baru jika belum ada -->
                        <div class="space-y-2 p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-white">2. Tipe / Model HP *</label>
                                @if(!empty($quickAddExistingModels))
                                    <span class="text-[10px] text-ink-400">{{ count($quickAddExistingModels) }} model terdaftar</span>
                                @endif
                            </div>

                            <!-- Dropdown Model yang Sudah Ada untuk Merek ini (jika ada) -->
                            @if(!empty($quickAddExistingModels))
                                <div>
                                    <select wire:model.live="quickAddSelectedModel" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Pilih Model {{ $quickAddForm['merk'] ?? '' }} yang Sudah Ada —</option>
                                        @foreach($quickAddExistingModels as $mod)
                                            <option value="{{ $mod }}" class="bg-ink-900">{{ $mod }}</option>
                                        @endforeach
                                        <option value="__custom__" class="bg-ink-900 text-up-mint font-semibold">+ Model Baru Lainnya (Ketik Baru)...</option>
                                    </select>
                                </div>
                            @endif

                            <!-- Kolom Ketik Model Baru jika belum ada di list atau ingin buat baru -->
                            @if(empty($quickAddExistingModels) || $quickAddSelectedModel === '__custom__' || empty($quickAddSelectedModel))
                                <div class="{{ !empty($quickAddExistingModels) ? 'mt-1.5 pt-2 border-t border-white/5 space-y-1' : 'space-y-1' }}">
                                    @if(!empty($quickAddExistingModels))
                                        <label class="block text-[11px] text-ink-300">
                                            {{ empty($quickAddSelectedModel) ? 'Atau ketik Model HP baru jika belum ada di atas:' : 'Ketik Model / Seri HP Baru *:' }}
                                        </label>
                                    @else
                                        <label class="block text-[11px] text-ink-300">Ketik Model / Seri HP Baru *:</label>
                                    @endif
                                    <input
                                        type="text"
                                        wire:model="quickAddForm.model"
                                        placeholder="cth: A15s, Redmi Note 12, iPhone 15 Pro..."
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                                    />
                                </div>
                            @else
                                <div class="flex items-center justify-between text-[11px] text-ink-300 px-1 pt-0.5">
                                    <span>Model aktif: <strong class="text-white">{{ $quickAddForm['model'] }}</strong></span>
                                    <button type="button" wire:click="$set('quickAddSelectedModel', '__custom__')" class="text-up-mint hover:underline text-[10px] cursor-pointer">+ Ketik Model Baru</button>
                                </div>
                            @endif
                            @error('quickAddForm.model') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- 3. NAMA LENGKAP TAMPILAN (OPSIONAL) -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Lengkap Tampilan (Opsional)</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.nama"
                                wire:keydown.enter="simpanQuickAdd"
                                placeholder="Kosongkan jika otomatis: [Merk] [Model]..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                            />
                            <p class="text-[10px] text-ink-500 mt-1">Otomatis diisi: "{{ trim(($quickAddForm['merk'] ?? '') . ' ' . ($quickAddForm['model'] ?? '')) }}" jika kosong</p>
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama / Label *</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.nama"
                                wire:keydown.enter="simpanQuickAdd"
                                placeholder="Ketik nama {{ $quickAddTipe }}..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                                autofocus
                            />
                            @error('quickAddForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if($quickAddTipe === 'kategori')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori Induk (Opsional)</label>
                            <select wire:model="quickAddForm.parent_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Jadikan Kategori Utama —</option>
                                @foreach($kategoriTree as $p)
                                    <option value="{{ $p->id }}" class="bg-ink-900 font-semibold text-white">{{ $p->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Pilih jika ingin membuat sub-kategori berjenjang</p>
                        </div>
                    @endif

                    @if($quickAddTipe === 'satuan')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Satuan (cth: pcs, unit, set, roll)</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.kode"
                                placeholder="cth: roll"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono font-medium"
                            />
                        </div>
                    @endif

                    @if($quickAddTipe === 'supplier')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">No. Telepon / WhatsApp</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.telepon"
                                placeholder="Cth: 081234567890"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Kontak (PIC / Sales)</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.kontak"
                                placeholder="Cth: Pak Budi (Distributor)"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat / Lokasi</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.alamat"
                                placeholder="Cth: Harco Mangga Dua Lt. 3"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                            />
                        </div>
                    @endif

                    @if($quickAddTipe === 'kualitas')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Brand Part Induk (Opsional / Universal)</label>
                            <select wire:model="quickAddForm.brand_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Universal (Bisa untuk Semua Brand Part) —</option>
                                @foreach($allBrands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }} (Eksklusif {{ $b->nama }})</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Kosongkan jika kualitas berlaku umum (cth: Original Copotan, OEM, Grade A)</p>
                        </div>
                    @endif

                    @if(in_array($quickAddTipe, ['brand', 'kualitas']))
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan (Opsional)</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.keterangan"
                                placeholder="Catatan singkat..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                            />
                        </div>
                    @endif
                </div>

                <div class="flex gap-2 pt-4 border-t border-white/5 mt-4">
                    <button
                        type="button"
                        wire:click="$set('showQuickAddModal', false)"
                        class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer hover:bg-white/10"
                    >Batal</button>
                    <button
                        type="button"
                        wire:click="simpanQuickAdd"
                        wire:loading.attr="disabled"
                        class="flex-1 py-2.5 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer hover:opacity-90"
                    >
                        <span wire:loading.remove wire:target="simpanQuickAdd">Simpan</span>
                        <span wire:loading wire:target="simpanQuickAdd">Menyimpan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BULK EDIT PRODUK TERPILIH -->
    @if($showBulkEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-cloak>
            <div class="w-full max-w-2xl glass-panel p-5 sm:p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto space-y-4 border border-white/10 shadow-2xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <h3 class="font-bold text-base text-white flex items-center gap-2">
                            <span>✏️ Bulk Edit Master Produk</span>
                            <span class="px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary text-xs font-bold font-mono">
                                {{ count($selectedProdukIds) }} dipilih
                            </span>
                        </h3>
                        <p class="text-xs text-ink-400 mt-0.5">Centang atribut yang ingin diubah masal secara bersamaan.</p>
                    </div>
                    <button type="button" wire:click="closeBulkEditModal" class="p-1.5 rounded-lg text-ink-400 hover:text-white hover:bg-white/5 transition cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Form Fields -->
                <div class="space-y-3.5">
                    <!-- Kategori -->
                    <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['kategori_id'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                        <label class="flex items-center justify-between cursor-pointer select-none">
                            <span class="text-xs font-semibold text-ink-200">Ubah Kategori</span>
                            <input type="checkbox" wire:model.live="bulkEditFields.kategori_id" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                        </label>
                        @if($bulkEditFields['kategori_id'] ?? false)
                            <select wire:model="bulkEditForm.kategori_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                <option value="">— Pilih Kategori Baru —</option>
                                @foreach($kategoriTree as $p)
                                    <option value="{{ $p->id }}" class="font-bold text-white">{{ $p->nama }}</option>
                                    @foreach($p->children as $c)
                                        <option value="{{ $c->id }}" class="text-ink-300">&nbsp;&nbsp;↳ {{ $c->nama }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <!-- Brand & Kualitas (Grid) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Brand -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['brand_id'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Ubah Brand</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.brand_id" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['brand_id'] ?? false)
                                <select wire:model="bulkEditForm.brand_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    <option value="">— Pilih Brand Baru —</option>
                                    @foreach($allBrands as $b)
                                        <option value="{{ $b->id }}">{{ $b->nama }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <!-- Kualitas -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['kualitas_id'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Ubah Kualitas</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.kualitas_id" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['kualitas_id'] ?? false)
                                <select wire:model="bulkEditForm.kualitas_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    <option value="">— Pilih Kualitas Baru —</option>
                                    @foreach($kualitasList as $k)
                                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>

                    <!-- Supplier -->
                    <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['supplier_id'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                        <label class="flex items-center justify-between cursor-pointer select-none">
                            <span class="text-xs font-semibold text-ink-200">Ubah Supplier</span>
                            <input type="checkbox" wire:model.live="bulkEditFields.supplier_id" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                        </label>
                        @if($bulkEditFields['supplier_id'] ?? false)
                            <select wire:model="bulkEditForm.supplier_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                <option value="">— Kosongkan Supplier (Tanpa Supplier) —</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <!-- Kondisi & Satuan (Grid) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Kondisi -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['kondisi'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Ubah Kondisi</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.kondisi" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['kondisi'] ?? false)
                                <select wire:model="bulkEditForm.kondisi" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    @foreach($kondisiList as $kd)
                                        <option value="{{ $kd['kode'] }}">{{ $kd['nama'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <!-- Satuan -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['satuan'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Ubah Satuan Dasar</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.satuan" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['satuan'] ?? false)
                                <select wire:model="bulkEditForm.satuan" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    @foreach($satuanUnits as $su)
                                        <option value="{{ $su->kode }}">{{ $su->nama }} ({{ $su->kode }})</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>

                    <!-- Kelas ABC & Status Aktif (Grid) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Kelas ABC -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['abc_class'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Ubah Kelas ABC</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.abc_class" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['abc_class'] ?? false)
                                <select wire:model="bulkEditForm.abc_class" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    <option value="A">Kelas A (Fast Moving)</option>
                                    <option value="B">Kelas B (Normal)</option>
                                    <option value="C">Kelas C (Slow Moving)</option>
                                </select>
                            @endif
                        </div>

                        <!-- Status Aktif -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['is_active'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Ubah Status Aktif</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.is_active" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['is_active'] ?? false)
                                <select wire:model="bulkEditForm.is_active" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            @endif
                        </div>
                    </div>

                    <!-- Flags: Harga Fleksibel & Serial Number (Grid) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Harga Fleksibel -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['harga_fleksibel'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Flag Harga Fleksibel</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.harga_fleksibel" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['harga_fleksibel'] ?? false)
                                <select wire:model="bulkEditForm.harga_fleksibel" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    <option value="1">Aktifkan Harga Fleksibel di Kasir</option>
                                    <option value="0">Nonaktifkan (Harga Tetap)</option>
                                </select>
                            @endif
                        </div>

                        <!-- Serial Number (SN) -->
                        <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['sn'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                            <label class="flex items-center justify-between cursor-pointer select-none">
                                <span class="text-xs font-semibold text-ink-200">Wajib Serial Number (SN)</span>
                                <input type="checkbox" wire:model.live="bulkEditFields.sn" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                            </label>
                            @if($bulkEditFields['sn'] ?? false)
                                <select wire:model="bulkEditForm.sn" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                    <option value="1">Wajib SN saat Terima & Jual</option>
                                    <option value="0">Tidak Menggunakan SN</option>
                                </select>
                            @endif
                        </div>
                    </div>

                    <!-- Pindah Lokasi Rak Masal -->
                    <div class="p-3 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['rak_id'] ?? false) ? 'border-up-primary/40 bg-up-primary/5' : 'border-white/5' }} space-y-2">
                        <label class="flex items-center justify-between cursor-pointer select-none">
                            <div>
                                <span class="text-xs font-semibold text-ink-200 block">Pindah Lokasi Rak</span>
                                <span class="text-[10px] text-ink-400">Pindahkan lokasi rak penyimpanan untuk semua produk terpilih.</span>
                            </div>
                            <input type="checkbox" wire:model.live="bulkEditFields.rak_id" class="rounded border-white/20 bg-ink-900 text-up-primary focus:ring-0">
                        </label>
                        @if($bulkEditFields['rak_id'] ?? false)
                            <select wire:model="bulkEditForm.rak_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                <option value="">— Kosongkan Lokasi Rak (Tanpa Rak) —</option>
                                @foreach($raks as $rk)
                                    <option value="{{ $rk->id }}">
                                        {{ $rk->kode }} — {{ $rk->nama }} (Gudang: {{ $rk->gudang?->nama ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <!-- Penyesuaian Harga / Margin Masal -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.02] border {{ ($bulkEditFields['penyesuaian_harga'] ?? false) ? 'border-up-mint/40 bg-up-mint/5' : 'border-white/5' }} space-y-3">
                        <label class="flex items-center justify-between cursor-pointer select-none">
                            <div>
                                <span class="text-xs font-semibold text-ink-200 block">Penyesuaian Harga Jual / Margin Masal</span>
                                <span class="text-[10px] text-ink-400">Atur ulang margin harga jual atau naikkan/turunkan harga jual secara masal.</span>
                            </div>
                            <input type="checkbox" wire:model.live="bulkEditFields.penyesuaian_harga" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                        </label>

                        @if($bulkEditFields['penyesuaian_harga'] ?? false)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-white/5">
                                <div>
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1">Metode Penyesuaian</label>
                                    <select wire:model="bulkEditForm.tipe_penyesuaian_harga" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                        <option value="margin_persen">Set Margin Harga Beli (%)</option>
                                        <option value="tambah_persen">Naikkan Harga Jual (%)</option>
                                        <option value="potong_persen">Turunkan Harga Jual (%)</option>
                                        <option value="tambah_nominal">Naikkan Nominal Tetap (Rp)</option>
                                        <option value="potong_nominal">Turunkan Nominal Tetap (Rp)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-ink-300 mb-1">Nilai Penyesuaian</label>
                                    <input type="number" step="any" wire:model="bulkEditForm.nilai_penyesuaian_harga" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-white tabular-nums bg-ink-900" placeholder="0">
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/10">
                    <button type="button" wire:click="closeBulkEditModal" class="px-4 py-2 rounded-xl text-ink-300 hover:text-white text-xs font-semibold hover:bg-white/5 transition cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            wire:click="simpanBulkEdit"
                            wire:loading.attr="disabled"
                            wire:confirm="Terapkan perubahan masal ini ke {{ count($selectedProdukIds) }} master produk terpilih?"
                            class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-lg shadow-up-primary/25 transition active:scale-95 cursor-pointer">
                        <span wire:loading.remove wire:target="simpanBulkEdit">Terapkan Perubahan Masal</span>
                        <span wire:loading wire:target="simpanBulkEdit">Menyimpan Perubahan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KUSTOMISASI SALIN DATA PRODUK KE CLIPBOARD -->
    @if($showSalinDataModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-cloak>
            <div class="w-full max-w-xl glass-panel p-5 sm:p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto space-y-4 border border-white/10 shadow-2xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <h3 class="font-bold text-base text-white flex items-center gap-2">
                            <span>📋 Kustomisasi Salin Data Produk</span>
                            <span class="px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint text-xs font-bold font-mono">
                                {{ count($selectedProdukIds) }} produk
                            </span>
                        </h3>
                        <p class="text-xs text-ink-400 mt-0.5">Pilih atribut data dan format teks yang ingin disalin ke clipboard.</p>
                    </div>
                    <button type="button" wire:click="closeSalinDataModal" class="p-1.5 rounded-lg text-ink-400 hover:text-white hover:bg-white/5 transition cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Format Output Selector -->
                <div class="p-3 rounded-2xl bg-white/[0.02] border border-white/10 space-y-2">
                    <label class="block text-xs font-semibold text-ink-200">Format Salinan:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border {{ ($salinOptions['format'] ?? 'teks') === 'teks' ? 'bg-up-mint/10 border-up-mint/40 text-up-mint' : 'bg-white/5 border-white/5 text-ink-300' }} cursor-pointer transition select-none">
                            <input type="radio" value="teks" wire:model.live="salinOptions.format" class="text-up-mint bg-ink-900 focus:ring-0">
                            <div>
                                <div class="text-xs font-bold">Format Teks Rapi</div>
                                <div class="text-[10px] text-ink-400 leading-tight">Cocok untuk Chat WA, SMS, atau Catatan</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border {{ ($salinOptions['format'] ?? 'teks') === 'tsv' ? 'bg-up-mint/10 border-up-mint/40 text-up-mint' : 'bg-white/5 border-white/5 text-ink-300' }} cursor-pointer transition select-none">
                            <input type="radio" value="tsv" wire:model.live="salinOptions.format" class="text-up-mint bg-ink-900 focus:ring-0">
                            <div>
                                <div class="text-xs font-bold">Format Tabel (TSV)</div>
                                <div class="text-[10px] text-ink-400 leading-tight">Langsung paste ke Excel / Google Sheets</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Atribut Checkbox Grid -->
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-ink-200">Pilih Data yang Mau Disalin:</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <!-- Nama -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['nama'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.nama" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Nama Produk</span>
                        </label>

                        <!-- Harga Jual -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['harga'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.harga" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Harga ({{ $labelTierAktif }})</span>
                        </label>

                        <!-- SKU -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['sku'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.sku" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Kode SKU</span>
                        </label>

                        <!-- Kualitas -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['kualitas'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.kualitas" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Kualitas</span>
                        </label>

                        <!-- Kompatibilitas -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['kompatibilitas'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.kompatibilitas" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Kompatibilitas</span>
                        </label>

                        <!-- Kondisi -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['kondisi'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.kondisi" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Kondisi</span>
                        </label>

                        <!-- Stok Ready -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['stok'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.stok" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Stok Ready</span>
                        </label>

                        <!-- Lokasi Rak -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['rak'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.rak" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Lokasi Rak</span>
                        </label>

                        <!-- Brand -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['brand'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.brand" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Brand</span>
                        </label>

                        <!-- Kategori -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['kategori'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.kategori" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Kategori</span>
                        </label>

                        <!-- Garansi -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['garansi'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.garansi" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Garansi</span>
                        </label>

                        <!-- Deskripsi / Catatan -->
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white/[0.02] border {{ ($salinOptions['deskripsi'] ?? false) ? 'border-up-mint/40 bg-up-mint/5 text-white' : 'border-white/5 text-ink-400' }} cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="salinOptions.deskripsi" class="rounded border-white/20 bg-ink-900 text-up-mint focus:ring-0">
                            <span class="text-xs font-semibold">Deskripsi</span>
                        </label>
                    </div>
                </div>

                <!-- Preview Output Salinan -->
                <div class="p-3 rounded-2xl bg-black/40 border border-white/5 space-y-1.5 font-mono text-[11px] text-ink-300">
                    <div class="text-[10px] uppercase font-bold text-ink-400 flex items-center justify-between">
                        <span>Contoh Tampilan Salinan WhatsApp:</span>
                        <span class="text-up-mint font-sans">Preview Rapi</span>
                    </div>
                    <div class="whitespace-pre-wrap leading-relaxed text-ink-200 bg-white/[0.02] p-2.5 rounded-xl border border-white/5 font-sans text-xs">📦 *Contoh Nama Produk* [SKU-001]
💰 Harga: *Rp 250.000*
@if(!empty($salinOptions['kualitas']))
⭐ Kualitas: Original Equipment Manufacturer
@endif
@if(!empty($salinOptions['kompatibilitas']))
📱 Kompatibel: Samsung Galaxy A50 / A50s
@endif
@if(!empty($salinOptions['kondisi']))
⚙️ Kondisi: Baru
@endif
@if(!empty($salinOptions['stok']))
📊 Stok: 15 pcs
@endif
@if(!empty($salinOptions['rak']))
📍 Rak: RAK-B2
@endif
@if(!empty($salinOptions['brand']))
🏷️ Brand: Samsung
@endif
@if(!empty($salinOptions['kategori']))
📁 Kategori: Sparepart LCD
@endif
@if(!empty($salinOptions['garansi']))
🛡️ Garansi: 1 bln
@endif
@if(!empty($salinOptions['deskripsi']))
📝 Catatan: Sparepart original teruji
@endif
</div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/10">
                    <button type="button" wire:click="closeSalinDataModal" class="px-4 py-2 rounded-xl text-ink-300 hover:text-white text-xs font-semibold hover:bg-white/5 transition cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            wire:click="eksekusiSalinData"
                            class="px-5 py-2.5 rounded-xl bg-up-mint hover:bg-up-mint/90 text-ink-950 font-bold text-xs shadow-lg shadow-up-mint/25 transition active:scale-95 cursor-pointer flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span>Salin ke Clipboard</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: VIEWFINDER KAMERA LIVE (WEBCAM / KAMERA HP) -->
    <div
        x-show="showModalKamera"
        x-cloak
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 backdrop-blur-md p-4"
    >
        <div class="w-full max-w-md glass-panel p-4 sm:p-5 rounded-3xl border border-white/10 shadow-2xl space-y-3.5 relative">
            <!-- Header Modal Kamera -->
            <div class="flex items-center justify-between pb-2.5 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-up-red animate-pulse"></span>
                    <h3 class="text-sm font-bold text-white">Viewfinder Kamera Produk</h3>
                </div>
                <button type="button" @click="tutupKamera()" class="p-1 rounded-lg text-ink-400 hover:text-white hover:bg-white/5 transition cursor-pointer">
                    ✕
                </button>
            </div>

            <!-- Area Viewfinder Video & Error -->
            <div class="relative w-full aspect-[4/3] bg-black rounded-2xl overflow-hidden border border-white/10 flex items-center justify-center">
                <template x-if="isLoadingKamera">
                    <div class="text-center space-y-2 text-ink-400">
                        <svg class="animate-spin w-8 h-8 mx-auto text-up-mint" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <p class="text-xs">Mengaktifkan sensor kamera…</p>
                    </div>
                </template>

                <template x-if="kameraError">
                    <div class="p-4 text-center space-y-2">
                        <p class="text-xs text-up-red font-semibold" x-text="kameraError"></p>
                        <button type="button" @click="mulaiStream()" class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-xs text-white">
                            Coba Lagi
                        </button>
                    </div>
                </template>

                <video
                    x-ref="kameraVideo"
                    autoplay
                    playsinline
                    muted
                    class="w-full h-full object-cover"
                    x-show="!isLoadingKamera && !kameraError"
                ></video>

                <!-- Grid Guide Overlay -->
                <div class="absolute inset-0 pointer-events-none border border-white/15 grid grid-cols-3 grid-rows-3" x-show="!isLoadingKamera && !kameraError">
                    <div class="border-r border-b border-white/10"></div>
                    <div class="border-r border-b border-white/10"></div>
                    <div class="border-b border-white/10"></div>
                    <div class="border-r border-b border-white/10"></div>
                    <div class="border-r border-b border-white/10"></div>
                    <div class="border-b border-white/10"></div>
                    <div class="border-r border-b border-white/10"></div>
                    <div class="border-r border-b border-white/10"></div>
                    <div></div>
                </div>

                <!-- Canvas Tersembunyi untuk Snapshot Frame -->
                <canvas x-ref="kameraCanvas" class="hidden"></canvas>
            </div>

            <!-- Tombol Kontrol Jepret & Switch Kamera -->
            <div class="flex items-center justify-between gap-2 pt-1">
                <button
                    type="button"
                    @click="switchKamera()"
                    class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white border border-white/10 text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer"
                    title="Ganti kamera depan / belakang"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Putar</span>
                </button>

                <button
                    type="button"
                    @click="jepretFoto()"
                    :disabled="isLoadingKamera || !!kameraError"
                    class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs shadow-lg shadow-up-mint/25 flex items-center justify-center gap-2 transition active:scale-[0.98] cursor-pointer disabled:opacity-50"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Jepret &amp; Gunakan Foto</span>
                </button>

                <button
                    type="button"
                    @click="tutupKamera()"
                    class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs font-semibold transition cursor-pointer"
                >
                    Batal
                </button>
            </div>
        </div>
    </div>

    {{-- [F1-4] Modal riwayat audit trail per produk --}}
    @include('partials.riwayat-modal')

    <!-- MODAL: INITIAL STOK / SALDO AWAL (MODAL PEMILIK) -->
    @if($showSaldoAwalModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-cloak>
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative border border-white/10 shadow-2xl">
                <!-- Header Modal -->
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>📥 Input Saldo Awal Fisik</span>
                        </h3>
                        <p class="text-xs text-ink-400 mt-0.5 truncate max-w-xs sm:max-w-sm">{{ $saldoAwalProdukNama }}</p>
                    </div>
                    <button type="button" wire:click="$set('showSaldoAwalModal', false)" class="text-ink-400 hover:text-white transition cursor-pointer p-1">✕</button>
                </div>

                <!-- Info Jurnal Ekuitas -->
                <div class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 space-y-1 mb-4">
                    <div class="flex items-center gap-1.5 font-bold text-white">
                        <span>💡 Jurnal Akuntansi Saldo Awal:</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-ink-300">
                        • <strong>Debit:</strong> Persediaan Barang Dagang (130-01) ➔ <span class="text-emerald-400 font-bold">Aset / Harta Bertambah</span><br>
                        • <strong>Kredit:</strong> Modal Pemilik (310-01) ➔ <span class="text-emerald-400 font-bold">Ekuitas Modal Pemilik Bertambah</span>
                    </p>
                    <p class="text-[10px] text-ink-400 italic pt-0.5">Tidak menimbulkan utang ke supplier dan tidak mengurangi kas operasional toko.</p>
                </div>

                <!-- Form Fields -->
                <div class="space-y-3">
                    <!-- Lokasi Gudang & Rak -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Gudang Penyimpanan *</label>
                            <select wire:model="saldoAwalForm.gudang_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                @foreach($gudangsCabang as $g)
                                    <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                                @endforeach
                            </select>
                            @error('saldoAwalForm.gudang_id') <span class="text-up-red text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Lokasi Rak (Opsional)</label>
                            <select wire:model="saldoAwalForm.rak_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white bg-ink-900">
                                <option value="">— Tanpa Rak —</option>
                                @foreach($raks as $rk)
                                    <option value="{{ $rk->id }}">{{ $rk->kode }} ({{ $rk->nama }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Qty & Harga Beli/Modal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Jumlah Qty Fisik *</label>
                            <input
                                type="number"
                                min="1"
                                wire:model.live="saldoAwalForm.qty"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-white tabular-nums"
                                placeholder="cth: 10"
                            />
                            @error('saldoAwalForm.qty') <span class="text-up-red text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Harga Beli / Modal (Rp) *</label>
                            <input
                                type="number"
                                min="0"
                                wire:model.live="saldoAwalForm.harga_beli"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-white tabular-nums"
                                placeholder="Modal per unit..."
                            />
                            @error('saldoAwalForm.harga_beli') <span class="text-up-red text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Total Nilai Aset Awal -->
                    <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between text-xs">
                        <span class="text-ink-400">Total Nilai Saldo Awal:</span>
                        <span class="font-mono font-bold text-up-mint text-sm">
                            Rp {{ number_format((float) ($saldoAwalForm['qty'] ?? 0) * (float) ($saldoAwalForm['harga_beli'] ?? 0), 0, ',', '.') }}
                        </span>
                    </div>

                    <!-- Input Serial Number jika produk SN -->
                    @if($saldoAwalProdukSn)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-up-amber">Daftar Serial Number (Wajib) *</label>
                                <span class="text-[10px] text-ink-400 font-mono">Wajib {{ (int) ($saldoAwalForm['qty'] ?? 0) }} SN unik</span>
                            </div>
                            <textarea
                                wire:model="saldoAwalForm.sn"
                                rows="3"
                                placeholder="Ketik atau scan nomor seri (pisahkan per baris atau tanda koma)..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono"
                            ></textarea>
                            @error('saldoAwalForm.sn') <span class="text-up-red text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <!-- Catatan / Keterangan -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Keterangan</label>
                        <input
                            type="text"
                            wire:model="saldoAwalForm.keterangan"
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                            placeholder="cth: Saldo awal inventori toko cabang..."
                        />
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="flex gap-2 pt-4 border-t border-white/5 mt-5">
                    <button
                        type="button"
                        wire:click="$set('showSaldoAwalModal', false)"
                        class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer hover:bg-white/10"
                    >Batal</button>
                    <button
                        type="button"
                        wire:click="simpanSaldoAwal"
                        wire:loading.attr="disabled"
                        class="flex-1 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-ink-950 font-bold text-xs cursor-pointer shadow-lg shadow-emerald-500/20 active:scale-95 transition"
                    >
                        <span wire:loading.remove wire:target="simpanSaldoAwal">Simpan Saldo Awal</span>
                        <span wire:loading wire:target="simpanSaldoAwal">Memproses Jurnal…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Datalist Autocomplete Brand & Model Kompatibel --}}
    <datalist id="brand-kompatibel-list">
        @foreach($brandKompatibelList as $b)
            <option value="{{ $b }}"></option>
        @endforeach
    </datalist>
    <datalist id="model-kompatibel-list">
        @foreach($modelKompatibelList as $m)
            <option value="{{ $m }}"></option>
        @endforeach
    </datalist>

    <script>
        window.kameraProdukHandler = function() {
            return {
                showModalKamera: false,
                kameraTarget: 'tambah',
                streamKamera: null,
                isLoadingKamera: false,
                kameraError: '',
                facingModeKamera: 'environment',

                bukaLiveKamera(target = 'tambah') {
                    this.kameraTarget = target;
                    const hasMediaDevices = typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
                    const isSecure = typeof window !== 'undefined' && (window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname));

                    if (!hasMediaDevices || !isSecure) {
                        // Fallback praktis jika browser membatasi WebRTC (misal HTTP non-SSL di ponsel)
                        const inputId = target === 'tambah' ? 'input-kamera-tambah' : 'input-kamera-edit';
                        const inputEl = document.getElementById(inputId);
                        if (inputEl) {
                            inputEl.click();
                            window.dispatchEvent(new CustomEvent('alert', {
                                detail: { type: 'info', message: 'Membuka kamera native perangkat...' }
                            }));
                            return;
                        }
                    }

                    this.showModalKamera = true;
                    this.$nextTick(() => {
                        this.mulaiStream();
                    });
                },

                async mulaiStream() {
                    this.hentikanStream();
                    this.isLoadingKamera = true;
                    this.kameraError = '';

                    try {
                        const constraints = {
                            video: {
                                facingMode: { ideal: this.facingModeKamera },
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            },
                            audio: false
                        };
                        const stream = await navigator.mediaDevices.getUserMedia(constraints);
                        this.streamKamera = stream;
                        if (this.$refs.kameraVideo) {
                            this.$refs.kameraVideo.srcObject = stream;
                            await this.$refs.kameraVideo.play().catch(() => {});
                        }
                    } catch (err) {
                        console.error('Gagal akses kamera:', err);
                        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                            this.kameraError = 'Izin akses kamera ditolak. Harap izinkan akses kamera di pengaturan browser.';
                        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                            this.kameraError = 'Perangkat kamera tidak ditemukan.';
                        } else {
                            this.kameraError = 'Tidak dapat membuka kamera: ' + (err.message || 'Error hardware kamera');
                        }
                    } finally {
                        this.isLoadingKamera = false;
                    }
                },

                switchKamera() {
                    this.facingModeKamera = (this.facingModeKamera === 'environment' ? 'user' : 'environment');
                    this.mulaiStream();
                },

                hentikanStream() {
                    if (this.streamKamera) {
                        try {
                            this.streamKamera.getTracks().forEach(track => track.stop());
                        } catch (e) {}
                        this.streamKamera = null;
                    }
                    if (this.$refs.kameraVideo) {
                        this.$refs.kameraVideo.srcObject = null;
                    }
                },

                tutupKamera() {
                    this.hentikanStream();
                    this.showModalKamera = false;
                    this.kameraError = '';
                },

                jepretFoto() {
                    const video = this.$refs.kameraVideo;
                    const canvas = this.$refs.kameraCanvas;
                    if (!video || !canvas || !video.videoWidth) {
                        window.dispatchEvent(new CustomEvent('alert', {
                            detail: { type: 'error', message: 'Kamera belum siap, tunggu sebentar.' }
                        }));
                        return;
                    }

                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    canvas.toBlob((blob) => {
                        if (!blob) {
                            window.dispatchEvent(new CustomEvent('alert', {
                                detail: { type: 'error', message: 'Gagal mengambil gambar dari sensor kamera.' }
                            }));
                            return;
                        }

                        const fileName = 'foto-kamera-' + Date.now() + '.jpg';
                        const file = new File([blob], fileName, { type: 'image/jpeg' });
                        const targetField = this.kameraTarget === 'tambah' ? 'fotoKameraUpload' : 'editFotoKameraUpload';

                        this.tutupKamera();

                        window.dispatchEvent(new CustomEvent('alert', {
                            detail: { type: 'info', message: 'Mengompresi dan mengunggah foto kamera...' }
                        }));

                        // Unggah file ke Livewire component via Livewire upload API
                        @this.upload(
                            targetField,
                            file,
                            () => {
                                // Selesai terunggah (ditangani hook updated*FotoKameraUpload di PHP)
                            },
                            (error) => {
                                console.error('Upload foto kamera gagal:', error);
                                window.dispatchEvent(new CustomEvent('alert', {
                                    detail: { type: 'error', message: 'Gagal mengunggah foto kamera: ' + error }
                                }));
                            }
                        );
                    }, 'image/jpeg', 0.92);
                }
            };
        };

        document.addEventListener('livewire:init', () => {
            Livewire.on('salin-teks', (event) => {
                const data = Array.isArray(event) ? event[0] : event;
                const teks = data.teks || '';
                const msg = data.message || 'Berhasil disalin ke clipboard!';
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(teks).then(() => {
                        window.dispatchEvent(new CustomEvent('alert', { detail: { type: 'success', message: msg } }));
                    }).catch(() => {
                        fallbackCopyText(teks, msg);
                    });
                } else {
                    fallbackCopyText(teks, msg);
                }
            });

            Livewire.on('buka-tab-baru', (event) => {
                const data = Array.isArray(event) ? event[0] : event;
                if (data.url) {
                    window.open(data.url, '_blank');
                }
            });

            function fallbackCopyText(teks, msg) {
                const textArea = document.createElement('textarea');
                textArea.value = teks;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    window.dispatchEvent(new CustomEvent('alert', { detail: { type: 'success', message: msg } }));
                } catch (err) {
                    window.dispatchEvent(new CustomEvent('alert', { detail: { type: 'error', message: 'Gagal menyalin teks ke clipboard.' } }));
                }
                document.body.removeChild(textArea);
            }
        });
    </script>
</div>
