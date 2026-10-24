<div x-data="{
        stokViewMode: localStorage.getItem('ute-stok-view-mode') || 'table',
        setStokView(mode) {
            this.stokViewMode = mode;
            try { localStorage.setItem('ute-stok-view-mode', mode); } catch (e) {}
        }
     }">
    <!-- TAB 1: INVENTORI & STOK -->
        <div class="space-y-4">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <x-prism.barcode-scan-input
                        placeholder="Scan Barcode / ketik nama, brand, SKU, tipe HP..."
                        model="search"
                        title="Scan Barcode Inventori Stok"
                    />
                </div>

                <div class="w-full sm:w-64">
                    <select wire:model.live="filterGudangId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                        <option value="" class="bg-ink-900">Semua Gudang</option>
                        @foreach($gudangs as $g)
                            <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-48">
                    <select wire:model.live="filterRakId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono">
                        <option value="" class="bg-ink-900">Semua Rak</option>
                        @foreach($raks as $rk)
                            @if(!$filterGudangId || $rk->gudang_id == $filterGudangId)
                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->nama }} ({{ $rk->kode }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <!-- [F2-5] Export laporan stok & View Mode Switcher -->
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap sm:flex-wrap">
                    @can('laporan.cabang')
                        <button type="button" wire:click="exportLaporan('xlsx')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px]">Export Excel</button>
                        <button type="button" wire:click="exportLaporan('csv')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px]">Export CSV</button>
                    @endcan

                    <!-- View Switcher (Table vs Grid Kartu) -->
                    <div class="inline-flex items-center p-0.5 rounded-xl bg-white/5 border border-white/10 ml-auto">
                        <button type="button"
                                @click="setStokView('table')"
                                :class="stokViewMode === 'table' ? 'bg-up-primary text-white shadow-sm font-bold' : 'text-ink-400 hover:text-white'"
                                class="px-2.5 py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition-[background-color,color] duration-140 cursor-pointer"
                                title="Tampilan Tabel">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <span>Tabel</span>
                        </button>
                        <button type="button"
                                @click="setStokView('grid')"
                                :class="stokViewMode === 'grid' ? 'bg-up-primary text-white shadow-sm font-bold' : 'text-ink-400 hover:text-white'"
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

            <!-- 1. Table View -->
            <div x-show="stokViewMode === 'table'">
                <x-prism.data-table :headers="['Produk & SKU', 'Kategori', 'Kompatibilitas', 'Gudang', 'Lokasi Rak', 'Harga Retail', 'Stok', 'Status', '']">
                    @forelse($stokItems as $stok)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <div>{{ $stok->produk?->nama }}</div>
                                @if($stok->skuVariant)
                                    <span class="text-[11px] font-mono text-ink-400">{{ $stok->skuVariant->sku }} ({{ $stok->skuVariant->nama_varian }})</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-ink-300 text-xs uppercase">{{ $stok->produk?->kategori ?? '-' }}</td>
                            <td class="py-3.5 px-4 text-ink-300 text-xs">{{ $stok->produk?->brand_kompatibel }} {{ $stok->produk?->model_kompatibel }}</td>
                            <td class="py-3.5 px-4 text-ink-300 text-xs font-medium">{{ $stok->gudang?->nama }}</td>
                            <td class="py-3.5 px-4 text-xs font-mono">
                                @if($stok->rak)
                                    <span class="px-2 py-0.5 rounded-lg bg-up-primary/10 text-up-primary border border-up-primary/20 font-bold inline-flex items-center gap-1">
                                        🏷️ {{ $stok->rak->nama }} ({{ $stok->rak->kode }})
                                    </span>
                                @else
                                    <span class="text-ink-500 italic text-[11px]">— Belum diset</span>
                                @endif
                                <button wire:click="bukaUbahRak({{ $stok->id }})" class="ml-1.5 text-[10px] text-ink-400 hover:text-white underline cursor-pointer" title="Ubah Lokasi Rak">Ubah</button>
                            </td>
                            <td class="py-3.5 px-4 text-white font-semibold tabular-nums text-xs">
                                Rp {{ number_format($stok->produk?->harga_jual_retail ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 font-bold tabular-nums text-sm">
                                {{ $stok->jumlah }}
                            </td>
                            <td class="py-3.5 px-4">
                                <x-prism.stock-gauge :stok="$stok->jumlah" :min="$stok->jumlah_minimum" />
                            </td>
                            <td class="py-3.5 px-4">
                                @can('lihat-audit-log')
                                    <button wire:click="bukaRiwayat('stok', {{ $stok->id }})" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer">Riwayat</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-ink-400">
                                Tidak ada item stok yang cocok dengan kriteria pencarian
                            </td>
                        </tr>
                    @endforelse

                    <!-- Mobile Card List Slot (jika mode tabel aktif di mobile) -->
                    <x-slot:mobileCards>
                        @foreach($stokItems as $stok)
                            <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h4 class="font-bold text-white text-xs">{{ $stok->produk?->nama }}</h4>
                                        <span class="text-[10px] text-ink-400">{{ $stok->produk?->brand_kompatibel }} {{ $stok->produk?->model_kompatibel }}</span>
                                    </div>
                                    <x-prism.stock-gauge :stok="$stok->jumlah" :min="$stok->jumlah_minimum" />
                                </div>
                                <div class="flex justify-between items-center text-xs pt-1 border-t border-white/5">
                                    <span class="text-ink-400">{{ $stok->gudang?->nama }}</span>
                                    <div class="flex items-center gap-1 font-mono text-[11px]">
                                        <span class="{{ $stok->rak ? 'text-up-primary font-bold' : 'text-ink-500' }}">
                                            {{ $stok->rak ? '🏷️ ' . $stok->rak->nama . ' (' . $stok->rak->kode . ')' : '— Belum diset' }}
                                        </span>
                                        <button wire:click="bukaUbahRak({{ $stok->id }})" class="text-[10px] text-ink-400 hover:text-white underline cursor-pointer">Ubah</button>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-ink-400">Harga Retail:</span>
                                    <span class="font-bold text-white tabular-nums">Rp {{ number_format($stok->produk?->harga_jual_retail ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:mobileCards>

                    <x-slot:pagination>
                        {{ $stokItems->links() }}
                    </x-slot:pagination>
                </x-prism.data-table>
            </div>

            <!-- 2. Grid Card View -->
            <div x-show="stokViewMode === 'grid'" x-cloak class="space-y-4">
                <div class="grid grid-cols-1 xxs:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @forelse($stokItems as $stok)
                        <div class="glass-panel p-3.5 rounded-2xl flex flex-col justify-between border border-white/5 hover:border-white/10 transition-colors">
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-ink-900 dark:text-white text-xs leading-snug line-clamp-2" title="{{ $stok->produk?->nama }}">
                                            {{ $stok->produk?->nama }}
                                        </h4>
                                        @if($stok->skuVariant)
                                            <span class="text-[10px] font-mono text-ink-400 block truncate">{{ $stok->skuVariant->sku }}</span>
                                        @endif
                                    </div>
                                    <x-prism.stock-gauge :stok="$stok->jumlah" :min="$stok->jumlah_minimum" />
                                </div>

                                <div class="text-[11px] text-ink-400 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span>Gudang:</span>
                                        <span class="font-semibold text-ink-200">{{ $stok->gudang?->nama ?? '-' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Lokasi Rak:</span>
                                        <span class="font-mono text-[10px] {{ $stok->rak ? 'text-up-primary font-bold' : 'text-ink-500' }}">
                                            {{ $stok->rak ? '🏷️ ' . $stok->rak->kode : 'Tanpa Rak' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between pt-1 border-t border-black/5 dark:border-white/5">
                                        <span>Harga Retail:</span>
                                        <span class="font-bold text-ink-900 dark:text-white tabular-nums">
                                            Rp {{ number_format($stok->produk?->harga_jual_retail ?? 0, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2.5 mt-2.5 border-t border-black/5 dark:border-white/5 flex items-center justify-between gap-2">
                                <button type="button" wire:click="bukaUbahRak({{ $stok->id }})" class="px-2 py-1 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 text-[10px] font-semibold border border-black/10 dark:border-white/10 cursor-pointer">
                                    Ubah Rak
                                </button>
                                @can('lihat-audit-log')
                                    <button type="button" wire:click="bukaRiwayat('stok', {{ $stok->id }})" class="px-2 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary text-[10px] font-bold border border-up-primary/20 cursor-pointer">
                                        Riwayat
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-ink-400">
                            Tidak ada item stok yang cocok dengan kriteria pencarian
                        </div>
                    @endforelse
                </div>

                <div class="p-3 sm:p-4 rounded-2xl glass-panel border border-black/10 dark:border-white/5">
                    {{ $stokItems->links() }}
                </div>
            </div>
        </div>

    <!-- MODAL: ATUR RAK [T-12] -->
    @if($showRakModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 border border-black/10 dark:border-white/10 shadow-2xl p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-black/10 dark:border-white/10">
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">{{ $editRakId ? 'Edit Rak (Bin)' : 'Manajemen Rak (Bin)' }}</h3>
                    <button wire:click="$set('showRakModal', false)" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Gudang *</label>
                            <select wire:model="rakForm.gudang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs bg-white dark:bg-ink-900 text-ink-900 dark:text-white border border-black/10 dark:border-white/10">
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" class="bg-white dark:bg-ink-900 text-ink-900 dark:text-white">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Kode Rak *</label>
                            <input type="text" wire:model="rakForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs bg-white dark:bg-ink-900 text-ink-900 dark:text-white border border-black/10 dark:border-white/10" placeholder="RAK-A1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Nama / Label</label>
                            <input type="text" wire:model="rakForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs bg-white dark:bg-ink-900 text-ink-900 dark:text-white border border-black/10 dark:border-white/10" placeholder="Rak LCD & Baterai" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Zona</label>
                            <input type="text" wire:model="rakForm.zona" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs bg-white dark:bg-ink-900 text-ink-900 dark:text-white border border-black/10 dark:border-white/10" placeholder="A" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        @if($editRakId)
                            <button type="button" wire:click="$set('editRakId', null); $set('rakForm', ['gudang_id' => null, 'nama' => '', 'kode' => '', 'zona' => ''])" class="px-4 py-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        @endif
                        <button wire:click="simpanRak" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">
                            {{ $editRakId ? '💾 Simpan Perubahan Rak' : '+ Tambah Rak' }}
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase">Rak Terdaftar</p>
                    @forelse($raks as $rk)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-black/[0.02] dark:bg-white/[0.03] border border-black/10 dark:border-white/5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-up-primary font-bold">{{ $rk->kode }}</span>
                                <span class="text-ink-800 dark:text-ink-200">{{ $rk->nama }}</span>
                                <span class="text-ink-500 dark:text-ink-400 text-[10px]">({{ $rk->gudang?->nama }})</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="editRak({{ $rk->id }})" class="p-1 rounded bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white text-xs cursor-pointer" title="Edit Rak">
                                    ✏️
                                </button>
                                <button type="button" wire:click="hapusRak({{ $rk->id }})" wire:confirm="Yakin ingin menghapus rak '{{ $rk->kode }}'?" class="p-1 rounded bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 text-xs cursor-pointer" title="Hapus Rak">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-ink-500 text-center py-4">Belum ada rak.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: UBAH LOKASI RAK STOK ITEM -->
    @if($showUbahRakModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel bg-white/95 dark:bg-ink-950/95 text-ink-900 dark:text-ink-100 border border-black/10 dark:border-white/10 shadow-2xl p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-black/10 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Atur Lokasi Rak</h3>
                        <p class="text-xs text-ink-500 dark:text-ink-400">{{ $ubahRakProdukNama }} — {{ $ubahRakGudangNama }}</p>
                    </div>
                    <button wire:click="$set('showUbahRakModal', false)" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-4 mb-5">
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Pilih Rak / Bin Penempatan</label>
                        <select wire:model="selectedRakId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono bg-white dark:bg-ink-900 text-ink-900 dark:text-white border border-black/10 dark:border-white/10">
                            <option value="" class="bg-white dark:bg-ink-900 text-ink-900 dark:text-white">— Tanpa Rak (Kosong) —</option>
                            @foreach($ubahRakPilihan as $r)
                                <option value="{{ $r['id'] }}" class="bg-white dark:bg-ink-900 text-ink-900 dark:text-white">{{ $r['nama'] }} ({{ $r['kode'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showUbahRakModal', false)" class="flex-1 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanUbahRak" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer shadow-lg shadow-up-primary/25">Simpan Lokasi</button>
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per item stok --}}
    @include('partials.riwayat-modal')
</div>
