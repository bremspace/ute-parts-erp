<div>
    <!-- TAB 1b: MASTER PRODUK -->
        <div class="space-y-4">
            <!-- Search -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <x-prism.barcode-scan-input placeholder="Cari produk: nama, kategori, brand..." model="produkSearch" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-ink-400">{{ $produks->total() }} produk</span>
                </div>
            </div>

            <x-prism.data-table :headers="['Produk', 'SKU', 'Harga Beli/Jual', 'Stok Total', 'Gudang', '']">
                @forelse($produks as $p)
                    @php
                        $stokTotal = $p->stokItems->sum('jumlah');
                        $skuPertama = $p->skuVariants->first()?->sku;
                    @endphp
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="py-3.5 px-4">
                            <span class="font-semibold text-white">{{ $p->nama }}</span>
                            <span class="block text-[10px] text-ink-400">{{ $p->kategori }} · {{ $p->brand_kompatibel ?? '-' }} {{ $p->model_kompatibel ?? '' }}</span>
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
                            </span>
                        </td>
                        <td class="py-3.5 px-4 font-mono text-ink-300 text-xs">{{ $skuPertama ?? '-' }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-xs">
                            <span class="text-ink-400">Beli</span> <span class="text-white font-semibold">Rp {{ number_format($p->harga_beli, 0, ',', '.') }}</span>
                            <span class="block text-ink-400 mt-0.5">Jual</span> <span class="text-up-mint font-semibold">Rp {{ number_format($p->harga_jual_retail, 0, ',', '.') }}</span>
                        </td>
                        <td class="py-3.5 px-4">
                            <x-prism.stock-gauge :stok="$stokTotal" :min="$p->stokItems->min('jumlah_minimum') ?? 2" />
                        </td>
                        <td class="py-3.5 px-4 text-ink-300 text-xs">
                            {{ $p->stokItems->map(fn($si) => $si->gudang?->nama)->filter()->unique()->join(', ') ?: '-' }}
                        </td>
                        <td class="py-3.5 px-4 flex gap-1.5 flex-wrap">
                            @if(auth()->user()?->hasRole('super-admin'))
                                <button wire:click="openTambahStokModal({{ $p->id }})" class="px-3 py-1.5 rounded-lg bg-up-accent hover:opacity-90 text-white font-bold text-[11px] transition-all cursor-pointer">
                                    + Stok
                                </button>
                            @else
                                <span class="px-3 py-1.5 rounded-lg bg-white/[0.03] border border-white/10 text-[10px] text-up-amber" title="Stok masuk hanya via PO Supplier">Stok masuk via PO</span>
                            @endif
                            <button wire:click="generateBarcodeProduk({{ $p->id }})" class="px-3 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[11px] cursor-pointer">
                                Barcode
                            </button>
                            <a href="{{ route('barcode.print') }}?ids={{ $p->id }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-[11px] cursor-pointer">
                                🖨️ Cetak
                            </a>
                            @can('lihat-audit-log')
                                <button wire:click="bukaRiwayat('produk', {{ $p->id }})" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-[11px] cursor-pointer">
                                    Riwayat
                                </button>
                            @endcan
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
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Produk *</label>
                        <input type="text" wire:model="produkForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="LCD iPhone 13 Original" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori *</label>
                            <input type="text" wire:model="produkForm.kategori" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="LCD / Layar" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kondisi</label>
                            <select wire:model="produkForm.kondisi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="baru">Baru</option>
                                <option value="oem">OEM</option>
                                <option value="compatible">Compatible</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Brand</label>
                            <select wire:model="produkForm.brand_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih brand —</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kualitas</label>
                            <select wire:model="produkForm.kualitas_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih kualitas —</option>
                                @foreach($kualitasList as $k)
                                    <option value="{{ $k->id }}" class="bg-ink-900">{{ $k->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Original / Grade A / Grade B / Refurbished / Compatible (nama baru bisa lewat Import Excel)</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Satuan *</label>
                            <select wire:model="produkForm.satuan_kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($satuanUnits as $s)
                                    <option value="{{ $s->kode }}" class="bg-ink-900">{{ $s->kode }} — {{ $s->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tipe HP (kompatibilitas)</label>
                            <select wire:model="produkForm.tipe_hp_ids" multiple size="3" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($tipeHpList as $t)
                                    <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->merk }} {{ $t->model }}</option>
                                @endforeach
                            </select>
                            <p class="text-[9px] text-ink-500 mt-1">Ctrl+Klik untuk multi-pilih</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Brand Kompatibel (legacy)</label>
                            <input type="text" wire:model="produkForm.brand_kompatibel" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Apple" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Model Kompatibel (legacy)</label>
                            <input type="text" wire:model="produkForm.model_kompatibel" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="iPhone 13" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Harga Beli (Rp) *</label>
                            <input type="number" wire:model.live="produkForm.harga_beli" step="500" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Harga Jual Retail (Rp) *</label>
                            <input type="number" wire:model.live="produkForm.harga_jual_retail" step="500" min="0" wire:change="produkHargaJualBerubah" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
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

                    <!-- [T-44] Harga Tier per tipe konsumen -->
                    <div class="p-3.5 rounded-xl bg-up-primary/[0.07] border border-up-primary/20">
                        <p class="text-xs font-bold text-up-primary mb-2">Harga Tier * (minimal 1 diisi — satu sumber harga pelanggan)</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['retail' => 'Retail', 'reseller' => 'Reseller', 'agen' => 'Agen'] as $tipe => $label)
                                <div>
                                    <label class="block text-[10px] font-semibold text-ink-300 mb-1">{{ $label }}</label>
                                    <input type="number" wire:model.live="produkForm.harga_tier.{{ $tipe }}.nominal_tetap" min="0" placeholder="Nominal" class="w-full px-2 py-2 rounded-lg glass-input text-xs font-bold tabular-nums" />
                                    <input type="number" wire:model.live="produkForm.harga_tier.{{ $tipe }}.persen_diskon" min="0" max="100" step="0.5" placeholder="% diskon" class="w-full px-2 py-2 mt-1.5 rounded-lg glass-input text-xs tabular-nums" />
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[9px] text-ink-500 mt-2">Nominal ATAU persen diskon dari harga jual. Retail default = harga jual retail. Reseller/agen kosong = mengikuti diskon tier pelanggan / harga retail.</p>
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
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Qty</label>
                                        <input type="number" wire:model="produkForm.stok_awal" min="0" class="w-full px-2.5 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Min Stok</label>
                                        <input type="number" wire:model="produkForm.stok_minimum" min="0" class="w-full px-2.5 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                                    </div>
                                </div>
                            </div>
                            <p class="text-[10px] text-up-accent/80 mt-2">
                                Jika diisi → otomatis buat jurnal pembelian: Debit Persediaan / Kredit Utang Usaha (di Akunting).
                            </p>
                        </div>
                    @endif

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

    <!-- MODAL: TAMBAH STOK (PEMBELIAN) -->
    @if($showTambahStokModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Stok — Pembelian</h3>
                    <button wire:click="$set('showTambahStokModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan *</label>
                        <select wire:model="tambahStokForm.gudang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rak (Bin) *</label>
                        <select wire:model="tambahStokForm.rak_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Rak...</option>
                            @foreach($raks as $rk)
                                @if($rk->gudang_id == (isset($tambahStokForm['gudang_id']) ? $tambahStokForm['gudang_id'] : $filterGudangId))
                                    <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }} - {{ $rk->nama }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jumlah *</label>
                            <input type="number" wire:model.live="tambahStokForm.qty" min="1" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Harga Beli (Rp) *</label>
                            <input type="number" wire:model="tambahStokForm.harga_beli" step="500" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan</label>
                        <input type="text" wire:model="tambahStokForm.keterangan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Pembelian dari supplier" />
                    </div>

                    <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30 text-[11px] text-up-mint">
                        Pencatatan pembelian dari supplier — stok bertambah + StokLog + <strong>jurnal otomatis (Persediaan / Utang Usaha)</strong>.
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showTambahStokModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanTambahStok" class="flex-1 py-3 rounded-xl bg-up-accent text-white font-bold text-xs cursor-pointer min-h-[44px]">Catat Pembelian</button>
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
                        <div class="p-3 rounded-xl {{ $importPreview['invalid'] > 0 ? 'bg-up-amber/10 border border-up-amber/30' : 'bg-up-mint/10 border border-up-mint/30' }} text-[11px]">
                            <span class="font-bold {{ $importPreview['invalid'] > 0 ? 'text-up-amber' : 'text-up-mint' }}">
                                {{ $importPreview['valid'] ?? 0 }} baris valid · {{ $importPreview['invalid'] ?? 0 }} baris error</span>
                            dari {{ $importPreview['total_baris'] ?? 0 }} baris. Baris error akan <strong>dilewati</strong> (bukan menghentikan import).
                        </div>

                        @if(! empty($importPreview['sampel']))
                            <div class="overflow-x-auto">
                                <table class="w-full text-[10px]">
                                    <thead>
                                        <tr class="text-ink-400 text-left border-b border-white/10">
                                            <th class="py-1.5 pr-2">Baris</th>
                                            <th class="py-1.5 pr-2">SKU</th>
                                            <th class="py-1.5 pr-2">Nama</th>
                                            <th class="py-1.5">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($importPreview['sampel'] as $s)
                                            <tr class="border-b border-white/5">
                                                <td class="py-1.5 pr-2 font-mono text-ink-400">{{ $s['baris'] }}</td>
                                                <td class="py-1.5 pr-2 font-mono text-ink-200">{{ $s['sku'] }}</td>
                                                <td class="py-1.5 pr-2 text-ink-200 max-w-[220px] truncate">{{ $s['nama'] }}</td>
                                                <td class="py-1.5">
                                                    @if($s['valid'])
                                                        <span class="text-up-mint font-bold">✓ OK</span>
                                                    @else
                                                        <span class="text-up-red font-bold" title="{{ implode(' | ', $s['errors']) }}">✕ {{ implode('; ', $s['errors']) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
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
                    <div class="space-y-4 text-center py-6">
                        <div class="text-4xl">✅</div>
                        <p class="text-sm font-bold text-white">Import diproses via antrian (queue)</p>
                        <p class="text-[11px] text-ink-400">
                            Hasil akhir (sukses/gagal per baris + jurnal stok awal) masuk ke <strong>notifikasi dalam aplikasi</strong>.
                            Cek tab Master Produk beberapa saat lagi.
                        </p>
                        <button wire:click="tutupImportModal" class="px-6 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Selesai</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per produk --}}
    @include('partials.riwayat-modal')
</div>
