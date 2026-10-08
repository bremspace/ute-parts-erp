<div>
    <!-- TAB 1b: MASTER PRODUK -->
        <div class="space-y-4">
            <!-- Search & Filters -->
            <div class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <x-prism.barcode-scan-input placeholder="Cari produk: nama, brand, model, SKU, barcode..." model="produkSearch" title="Scan Barcode Master Produk" />
                </div>
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap sm:flex-wrap">
                    <select wire:model.live="filterKategoriId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[44px]">
                        <option value="">Semua Kategori</option>
                        @foreach($kategoriTree as $parent)
                            <option value="{{ $parent->id }}" class="bg-ink-900 font-bold text-white">{{ $parent->nama }}</option>
                            @foreach($parent->children as $child)
                                <option value="{{ $child->id }}" class="bg-ink-900 text-ink-300">&nbsp;&nbsp;↳ {{ $child->nama }}</option>
                            @endforeach
                        @endforeach
                    </select>

                    <select wire:model.live="filterBrandId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-200 bg-ink-900/60 min-h-[44px]">
                        <option value="">Semua Brand</option>
                        @foreach($brands as $b)
                            <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                        @endforeach
                    </select>

                    <!-- Filter Tier Harga Pricelist & Export -->
                    <div class="flex items-center gap-1.5 bg-white/5 border border-white/10 p-1 rounded-xl">
                        <select wire:model.live="filterTierHarga" class="px-2.5 py-1.5 rounded-lg bg-black/40 border-0 text-xs font-medium text-white min-h-[36px] outline-none cursor-pointer" title="Pilih tier harga untuk ekspor pricelist">
                            <option value="retail" class="bg-ink-900 text-white">Tier: Retail Standar</option>
                            <option value="reseller" class="bg-ink-900 text-white">Tier: Reseller</option>
                            <option value="agen" class="bg-ink-900 text-white">Tier: Agen</option>
                            @foreach($tierMemberships as $tm)
                                <option value="tier_{{ $tm->id }}" class="bg-ink-900 text-white">Tier CRM: {{ $tm->nama }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="exportPricelist('xlsx')" class="px-2.5 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary/80 text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97]" title="Export Pricelist format Excel">
                            Excel
                        </button>
                        <button type="button" wire:click="exportPricelist('csv')" class="px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97]" title="Export Pricelist format CSV">
                            CSV
                        </button>
                    </div>

                    <span class="text-[11px] text-ink-400 pl-1 whitespace-nowrap">{{ $produks->total() }} produk</span>
                </div>
            </div>

            <x-prism.data-table :headers="['Produk', 'SKU / Barcode', 'Harga Beli/Jual', 'Stok Total', 'Gudang', 'Aksi']">
                @forelse($produks as $p)
                    @php
                        $stokTotal = $p->stokItems->sum('jumlah');
                        $skuPertama = $p->skuVariants->first()?->sku;
                    @endphp
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-white/5 border border-white/10 shrink-0 flex items-center justify-center">
                                    @if($p->thumbnail_url)
                                        <img src="{{ $p->thumbnail_url }}" alt="{{ $p->nama }}" class="w-full h-full object-cover" loading="lazy">
                                    @else
                                        <span class="text-lg text-ink-500">📦</span>
                                    @endif
                                </div>
                                <div>
                                    <span class="font-semibold text-white block">{{ $p->nama }}</span>
                                    <span class="block text-[10px] text-ink-400">
                                        <strong class="text-ink-200">{{ $p->kategoriRelasi?->nama ?? $p->kategori ?? '-' }}</strong> · {{ $p->brand_kompatibel ?? '-' }} {{ $p->model_kompatibel ?? '' }}
                                    </span>
                                    <span class="flex gap-1 mt-1 flex-wrap">
                                        @if($p->brand)
                                            <span class="px-1.5 py-0.5 rounded-md bg-up-primary/15 border border-up-primary/25 text-[9px] font-bold text-up-primary">{{ $p->brand->nama }}</span>
                                        @endif
                                        @if($p->kualitas)
                                            <span class="px-1.5 py-0.5 rounded-md bg-up-mint/10 border border-up-mint/25 text-[9px] font-bold text-up-mint">{{ $p->kualitas->nama }}</span>
                                        @endif
                                        @if($p->satuan)
                                            <span class="px-1.5 py-0.5 rounded-md bg-white/5 border border-white/10 text-[9px] font-bold text-ink-300">{{ $p->satuan }}</span>
                                        @endif
                                        @if($p->harga_fleksibel)
                                            <span class="px-1.5 py-0.5 rounded-md bg-up-amber/15 border border-up-amber/30 text-[9px] font-bold text-up-amber">Fleksibel</span>
                                        @endif
                                        <span class="px-1.5 py-0.5 rounded-md text-[9px] font-bold {{ $p->abc_class === 'A' ? 'bg-up-red/15 border border-up-red/30 text-up-red' : ($p->abc_class === 'C' ? 'bg-white/10 border border-white/20 text-ink-300' : 'bg-up-primary/15 border border-up-primary/30 text-up-primary') }}">
                                            Kelas {{ $p->abc_class ?? 'B' }}
                                        </span>
                                        @if($p->is_ondemand)
                                            <span class="px-1.5 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-[9px] font-bold text-purple-400">JIT</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono text-xs">
                            <span class="text-ink-300 block font-semibold">{{ $skuPertama ?? '-' }}</span>
                            @if($p->barcode)
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="text-[10px] text-up-primary font-mono block px-1.5 py-0.5 rounded bg-up-primary/10 border border-up-primary/20">{{ $p->barcode }}</span>
                                </div>
                            @else
                                <span class="text-[11px] text-ink-500 font-mono block mt-0.5">-</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 tabular-nums text-xs">
                            @cansee('harga_beli')
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-ink-400">Beli</span>
                                <span class="text-white font-semibold">Rp {{ number_format($p->harga_beli, 0, ',', '.') }}</span>
                                @if(($p->metode_harga_beli ?? 'manual') === 'average')
                                    <span class="px-1.5 py-0.2 rounded bg-up-mint/15 text-up-mint text-[9px] font-bold border border-up-mint/30" title="Metode: Moving Average Otomatis dari PO">Avg</span>
                                @endif
                            </div>
                            @cannotsee('harga_beli')
                            <span class="text-ink-400">Beli</span> <span class="text-white font-semibold">—</span>
                            @endcansee
                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                <span class="text-ink-400">Jual</span>
                                <span class="text-up-mint font-semibold">Rp {{ number_format($p->harga_jual_retail, 0, ',', '.') }}</span>
                                @if($p->margin_persen > 0)
                                    <span class="text-[9px] text-ink-400 font-mono" title="Margin {{ $p->margin_persen }}%">+{{ (float) $p->margin_persen }}%</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4">
                            <x-prism.stock-gauge :stok="$stokTotal" :min="$p->stokItems->min('jumlah_minimum') ?? 2" />
                        </td>
                        <td class="py-3.5 px-4 text-ink-300 text-xs">
                            {{ $p->stokItems->map(fn($si) => $si->gudang?->nama)->filter()->unique()->join(', ') ?: '-' }}
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 flex-nowrap">
                                <button wire:click="openEditProdukModal({{ $p->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-up-mint border border-up-mint/30 font-bold text-[11px] cursor-pointer whitespace-nowrap active:scale-[0.97]">
                                    ✏️ Edit
                                </button>
                                @if(isSuperAdminOrOwner())
                                    <button wire:click="hapusProduk({{ $p->id }})" wire:confirm="Yakin ingin menghapus master produk '{{ $p->nama }}'? Jika produk memiliki riwayat transaksi/mutasi, status akan dinonaktifkan." class="px-2.5 py-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 font-bold text-[11px] cursor-pointer whitespace-nowrap active:scale-[0.97]" title="Hapus Master Produk">
                                        🗑️ Hapus
                                    </button>
                                @endif
                                <button wire:click="orderPo({{ $p->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[11px] transition-all cursor-pointer whitespace-nowrap active:scale-[0.97]" title="Buat PO untuk produk ini">
                                    📦 Order PO
                                </button>
                                <a href="{{ route('barcode.print') }}?ids={{ $p->id }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-[11px] cursor-pointer whitespace-nowrap active:scale-[0.97]">
                                    🖨️ Cetak
                                </a>
                                @can('lihat-audit-log')
                                    <button wire:click="bukaRiwayat('produk', {{ $p->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-[11px] cursor-pointer whitespace-nowrap active:scale-[0.97]">
                                        Riwayat
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-ink-400">Belum ada produk. Klik "Tambah Produk" untuk menambahkan master produk baru (tersinkron jurnal akunting).</td>
                    </tr>
                @endforelse
            </x-prism.data-table>

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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Produk Baru</h3>
                    <button wire:click="$set('showProdukModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
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
                                <input type="text" wire:model="produkForm.barcode" class="w-full pl-3 pr-16 py-2.5 rounded-xl glass-input text-xs font-mono font-medium" placeholder="Cth: 8991234500001" />
                                <button type="button"
                                        onclick="window.uteBarcode && window.uteBarcode.openCameraScanner({ title: 'Scan Barcode Produk', onScan: (code) => { @this.set('produkForm.barcode', code); } })"
                                        class="absolute right-1.5 px-2 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary text-[10px] font-bold flex items-center gap-1 cursor-pointer active:scale-95"
                                        title="Scan Barcode via Kamera HP/Web">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>Scan</span>
                                </button>
                            </div>
                            @error('produkForm.barcode') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
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
                                <label class="text-xs font-semibold text-ink-300">Kondisi</label>
                                <button type="button" wire:click="openQuickAdd('kondisi')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.kondisi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($kondisiList as $knd)
                                    <option value="{{ $knd['kode'] }}" class="bg-ink-900">{{ $knd['nama'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Brand Sparepart / Merk</label>
                                <button type="button" wire:click="openQuickAdd('brand')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.brand_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih brand —</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kualitas</label>
                                <button type="button" wire:click="openQuickAdd('kualitas')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.kualitas_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih kualitas —</option>
                                @foreach($kualitasList as $k)
                                    <option value="{{ $k->id }}" class="bg-ink-900">{{ $k->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Original / Grade A / Grade B / Refurbished</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Satuan *</label>
                                <button type="button" wire:click="openQuickAdd('satuan')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="produkForm.satuan_kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($satuanUnits as $s)
                                    <option value="{{ $s->kode }}" class="bg-ink-900">{{ $s->kode }} — {{ $s->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-semibold text-ink-300">Tipe HP (Kompatibilitas)</label>
                                <button type="button" wire:click="openQuickAdd('tipe_hp')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Tambah Tipe</button>
                            </div>
                            <x-prism.searchable-multi-select model="produkForm.tipe_hp_ids" :options="$tipeHpOptions" placeholder="Cari tipe HP..." />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kompatibel Antar Produk (Substitusi / Cross-Reference)</label>
                        <x-prism.searchable-multi-select model="produkForm.produk_kompatibel_ids" :options="$allProdukOptions" placeholder="Cari produk substitusi / pengganti..." />
                        <p class="text-[9px] text-ink-500 mt-1">Produk lain yang bisa saling menggantikan (part substitution)</p>
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

                    <!-- Harga Fleksibel Toggle -->
                    <div class="p-3.5 rounded-xl bg-up-amber/10 border border-up-amber/30">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-0.5">Harga Fleksibel</label>
                                <p class="text-[10px] text-up-amber/80">Harga diinput saat transaksi oleh superadmin (min: harga modal)</p>
                            </div>
                            @can('atur-harga-fleksibel')
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.defer="produkForm.harga_fleksibel" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-white/10 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-up-amber/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-amber"></div>
                                </label>
                            @else
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-medium text-ink-500 bg-white/5 border border-white/5 cursor-not-allowed" title="Khusus superadmin">
                                    Khusus superadmin
                                </span>
                            @endcan
                        </div>
                    </div>

                    <!-- [F2-3] Serial Number (SN) Toggle -->
                    <div class="p-3.5 rounded-xl bg-up-mint/10 border border-up-mint/30">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-0.5">Serial Number (SN)</label>
                                <p class="text-[10px] text-up-mint/80">Wajib input SN saat GRN/stok masuk &amp; penjualan</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.defer="produkForm.sn" class="sr-only peer" />
                                <div class="w-11 h-6 bg-white/10 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-up-mint/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-mint"></div>
                            </label>
                        </div>
                    </div>

                    <!-- [T-44] Harga Tier per tipe konsumen -->
                    <div class="p-3.5 rounded-xl bg-up-primary/[0.07] border border-up-primary/20">
                        <p class="text-xs font-bold text-up-primary mb-2">Harga Tier * (minimal 1 diisi — satu sumber harga pelanggan)</p>
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
                        <p class="text-[9px] text-ink-500 mt-2">Nominal ATAU persen diskon. Harga Retail otomatis mengikuti Harga Jual Retail di atas. Reseller &amp; Agen diisi nominal khusus atau persen diskon.</p>
                    </div>

                    @if(auth()->user()?->hasRole('super-admin'))
                        <div class="p-3.5 rounded-xl bg-up-accent/10 border border-up-accent/30">
                            <p class="text-xs font-bold text-up-accent mb-2">Stok Awal (Pembelian) — opsional</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Gudang Tujuan</label>
                                    <select wire:model="produkForm.gudang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Tanpa stok awal —</option>
                                        @foreach($gudangs as $g)
                                            <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
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
                            <p class="text-xs font-bold text-ink-200">⚙️ Strategi Pengadaan Stok (ABC / ROP / Min-Max / JIT)</p>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="produkForm.is_ondemand" class="rounded bg-black/40 border-white/20 text-up-accent focus:ring-0">
                                <span class="text-xs font-semibold text-up-accent">Modified JIT (On-Demand)</span>
                            </label>
                        </div>
                        <p class="text-[10px] text-ink-500">Jika Modified JIT dicentang, pengecekan stok otomatis (ROP &amp; Min-Max) diabaikan. Pengadaan hanya dipesan saat ada pesanan konsumen/servis aktif.</p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Klasifikasi ABC</label>
                                <select wire:model="produkForm.abc_class" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-bold">
                                    <option value="A" class="bg-ink-900">Kelas A (Fast Moving)</option>
                                    <option value="B" class="bg-ink-900">Kelas B (Normal)</option>
                                    <option value="C" class="bg-ink-900">Kelas C (Slow Moving)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Reorder Point (ROP)</label>
                                <input type="number" wire:model="produkForm.reorder_point" min="0" placeholder="Pemicu Kelas A" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Min Stock</label>
                                <input type="number" wire:model="produkForm.min_stock" min="0" placeholder="Pemicu Kelas B/C" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Max Stock</label>
                                <input type="number" wire:model="produkForm.max_stock" min="0" placeholder="Batas Atas Order" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                        </div>
                    </div>

                    <!-- Foto Produk Upload -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-2">
                        <label class="block text-xs font-semibold text-ink-300">Foto Produk (WebP Otomatis)</label>
                        <input type="file" wire:model="fotoUploads" multiple accept="image/*" class="w-full text-xs text-ink-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-xl file:border-0 file:bg-up-primary file:text-white file:font-bold file:text-xs file:cursor-pointer" />
                        <p class="text-[9px] text-ink-500">Upload satu atau beberapa foto (JPG/PNG/WebP). Otomatis dikompresi ke WebP responsive (full &amp; thumbnail).</p>
                        @if($fotoUploads)
                            <div class="flex gap-2 flex-wrap pt-2">
                                @foreach($fotoUploads as $fu)
                                    <div class="w-14 h-14 rounded-lg overflow-hidden border border-white/20 bg-black/40 relative">
                                        <img src="{{ $fu->temporaryUrl() }}" class="w-full h-full object-cover">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi Produk (Marketplace &amp; Detail)</label>
                        <textarea wire:model="produkForm.deskripsi" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Spesifikasi, catatan garansi, atau petunjuk pemasangan..."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">SKU (opsional, auto-generate jika kosong)</label>
                        <input type="text" wire:model="produkForm.sku" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="LCD-IP13-OEM" />
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
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all {{ $importFormat === 'standar' ? 'bg-up-primary/15 border-up-primary text-white' : 'bg-white/5 border-white/10 text-ink-300 hover:bg-white/10' }}">
                                    <input type="radio" wire:model.live="importFormat" value="standar" class="accent-up-primary" />
                                    <div>
                                        <div class="text-xs font-bold leading-none">Template Standar UteParts</div>
                                        <div class="text-[10px] text-ink-400 mt-1">Format Excel baku dengan kolom lengkap</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all {{ $importFormat === 'sid_retail' ? 'bg-up-primary/15 border-up-primary text-white' : 'bg-white/5 border-white/10 text-ink-300 hover:bg-white/10' }}">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Edit Master Produk</h3>
                    <button wire:click="$set('showEditProdukModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Produk *</label>
                            <input type="text" wire:model="editProdukForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('editProdukForm.nama') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kode Barcode (Fisik / Scan)</label>
                                <button type="button" wire:click="generateBarcodeForm('edit')" class="text-[10px] text-up-amber hover:underline font-semibold cursor-pointer" title="Buat kode barcode otomatis">⚡ Auto</button>
                            </div>
                            <div class="relative flex items-center">
                                <input type="text" wire:model="editProdukForm.barcode" class="w-full pl-3 pr-16 py-2.5 rounded-xl glass-input text-xs font-mono font-medium" placeholder="Cth: 8991234500001" />
                                <button type="button"
                                        onclick="window.uteBarcode && window.uteBarcode.openCameraScanner({ title: 'Scan Barcode Produk', onScan: (code) => { @this.set('editProdukForm.barcode', code); } })"
                                        class="absolute right-1.5 px-2 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary text-[10px] font-bold flex items-center gap-1 cursor-pointer active:scale-95"
                                        title="Scan Barcode via Kamera HP/Web">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>Scan</span>
                                </button>
                            </div>
                            @error('editProdukForm.barcode') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
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
                                <label class="text-xs font-semibold text-ink-300">Kondisi</label>
                                <button type="button" wire:click="openQuickAdd('kondisi')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.kondisi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($kondisiList as $knd)
                                    <option value="{{ $knd['kode'] }}" class="bg-ink-900">{{ $knd['nama'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Brand Sparepart / Merk</label>
                                <button type="button" wire:click="openQuickAdd('brand')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.brand_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih brand —</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Kualitas</label>
                                <button type="button" wire:click="openQuickAdd('kualitas')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.kualitas_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih kualitas —</option>
                                @foreach($kualitasList as $k)
                                    <option value="{{ $k->id }}" class="bg-ink-900">{{ $k->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-ink-300">Satuan *</label>
                                <button type="button" wire:click="openQuickAdd('satuan')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Baru</button>
                            </div>
                            <select wire:model="editProdukForm.satuan_kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($satuanUnits as $s)
                                    <option value="{{ $s->kode }}" class="bg-ink-900">{{ $s->kode }} — {{ $s->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-semibold text-ink-300">Tipe HP (Kompatibilitas)</label>
                                <button type="button" wire:click="openQuickAdd('tipe_hp')" class="text-[10px] text-up-mint hover:underline font-semibold cursor-pointer">+ Tambah Tipe</button>
                            </div>
                            <x-prism.searchable-multi-select model="editProdukForm.tipe_hp_ids" :options="$tipeHpOptions" placeholder="Cari tipe HP..." />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kompatibel Antar Produk (Substitusi / Cross-Reference)</label>
                        <x-prism.searchable-multi-select model="editProdukForm.produk_kompatibel_ids" :options="collect($allProdukOptions)->reject(fn($o) => $o['id'] === $editProdukId)->values()->all()" placeholder="Cari produk substitusi / pengganti..." />
                        <p class="text-[9px] text-ink-500 mt-1">Produk lain yang bisa saling menggantikan</p>
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

                    <!-- Konfigurasi Pengadaan (ABC, ROP, Min-Max, Modified JIT) -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-ink-200">⚙️ Strategi Pengadaan Stok (ABC / ROP / Min-Max / JIT)</p>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="editProdukForm.is_ondemand" class="rounded bg-black/40 border-white/20 text-up-accent focus:ring-0">
                                <span class="text-xs font-semibold text-up-accent">Modified JIT (On-Demand)</span>
                            </label>
                        </div>
                        <p class="text-[10px] text-ink-500">Jika Modified JIT dicentang, pengecekan stok otomatis diabaikan. Part hanya dipesan saat ada order pelanggan/servis aktif.</p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Klasifikasi ABC</label>
                                <select wire:model="editProdukForm.abc_class" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-bold">
                                    <option value="A" class="bg-ink-900">Kelas A (Fast Moving)</option>
                                    <option value="B" class="bg-ink-900">Kelas B (Normal)</option>
                                    <option value="C" class="bg-ink-900">Kelas C (Slow Moving)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Reorder Point (ROP)</label>
                                <input type="number" wire:model="editProdukForm.reorder_point" min="0" placeholder="Pemicu Kelas A" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Min Stock</label>
                                <input type="number" wire:model="editProdukForm.min_stock" min="0" placeholder="Pemicu Kelas B/C" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-300 mb-1">Max Stock</label>
                                <input type="number" wire:model="editProdukForm.max_stock" min="0" placeholder="Batas Atas Order" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs tabular-nums" />
                            </div>
                        </div>
                    </div>

                    <!-- Galeri Foto Existing & Kelola Foto -->
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        <label class="block text-xs font-semibold text-ink-300">Galeri Foto Produk (WebP)</label>
                        @if(! empty($editFotoExisting))
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @foreach($editFotoExisting as $idx => $f)
                                    <div class="rounded-xl overflow-hidden border {{ !empty($f['is_primary']) ? 'border-up-mint ring-1 ring-up-mint' : 'border-white/10' }} bg-black/40 p-1.5 space-y-1.5">
                                        <div class="w-full aspect-square rounded-lg overflow-hidden bg-black/60 relative">
                                            <img src="{{ $f['thumb'] ?? $f['url'] }}" class="w-full h-full object-cover">
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

                        <!-- Tambah Foto Baru -->
                        <div class="pt-2 border-t border-white/5 space-y-2">
                            <label class="block text-[11px] font-semibold text-ink-300">Tambah Foto Baru</label>
                            <input type="file" wire:model="editFotoUploads" multiple accept="image/*" class="w-full text-xs text-ink-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-xl file:border-0 file:bg-up-primary file:text-white file:font-bold file:text-xs file:cursor-pointer" />
                            <p class="text-[9px] text-ink-500">Foto baru akan otomatis dikonversi ke WebP full + thumbnail 250px hemat storage.</p>
                            @if($editFotoUploads)
                                <div class="flex gap-2 flex-wrap pt-1">
                                    @foreach($editFotoUploads as $efu)
                                        <div class="w-12 h-12 rounded-lg overflow-hidden border border-white/20 bg-black/40">
                                            <img src="{{ $efu->temporaryUrl() }}" class="w-full h-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi Produk</label>
                        <textarea wire:model="editProdukForm.deskripsi" rows="3" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
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
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10">
                    <h3 class="text-sm font-bold text-white">
                        + Tambah {{ ucfirst($quickAddTipe) }} Baru
                    </h3>
                    <button wire:click="$set('showQuickAddModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-3">
                    @if($quickAddTipe === 'tipe_hp')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Merk / Brand HP *</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.merk"
                                list="brand-kompatibel-list"
                                placeholder="cth: Apple, Samsung, Xiaomi..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                                autofocus
                            />
                            @error('quickAddForm.merk') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Model / Seri HP *</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.model"
                                list="model-kompatibel-list"
                                placeholder="cth: iPhone 13 Pro, Redmi Note 11..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                            />
                            @error('quickAddForm.model') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Lengkap (Opsional)</label>
                            <input
                                type="text"
                                wire:model="quickAddForm.nama"
                                wire:keydown.enter="simpanQuickAdd"
                                placeholder="Kosongkan jika sama dengan Merk + Model..."
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:ring-2 focus:ring-up-mint/30"
                            />
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

    {{-- [F1-4] Modal riwayat audit trail per produk --}}
    @include('partials.riwayat-modal')

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
</div>
