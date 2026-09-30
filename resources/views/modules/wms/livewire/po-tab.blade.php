<div>
    <!-- TAB 4: PO & SUPPLIER [T-10] -->
        <div class="space-y-5">
            <!-- PO List -->
            <x-prism.data-table :headers="['No. PO', 'Supplier', 'Gudang Tujuan', 'Metode', 'Total', 'Dibayar', 'Jatuh Tempo', 'Status', 'Aksi']">
                @forelse($poList as $po)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $po->no_po }}</td>
                        <td class="py-3.5 px-4 text-ink-200">{{ $po->supplier?->nama }}</td>
                        <td class="py-3.5 px-4 text-ink-300">{{ $po->gudangTujuan?->nama }}</td>
                        <td class="py-3.5 px-4">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $po->metode_bayar === 'kredit' ? 'bg-up-amber/10 text-up-amber' : 'bg-up-mint/10 text-up-mint' }}">
                                {{ $po->metode_bayar }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 tabular-nums font-bold text-white">Rp {{ number_format($po->total, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-up-mint">Rp {{ number_format($po->total_dibayar, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $po->jatuh_tempo?->format('d/m/Y') ?? '-' }}</td>
                        <td class="py-3.5 px-4"><x-prism.status-pill :status="str_replace('_','-',$po->status)" /></td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 flex-nowrap">
                                @if($po->status === 'usulan')
                                    <button wire:click="konfirmasiUsulan({{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-accent hover:brightness-110 text-white font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Konfirmasi</button>
                                @elseif($po->status === 'draft')
                                    <button wire:click="kirimPo({{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Kirim</button>
                                    <button wire:click="terimaPo({{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-mint text-ink-950 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Terima</button>
                                @elseif($po->status === 'dikirim')
                                    <button wire:click="terimaPo({{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-mint text-ink-950 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Terima</button>
                                @endif
                                <button wire:click="bukaDetailPo({{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Detail</button>
                                @if($po->status === 'diterima' && $po->sisa > 0)
                                    @if(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('utang.manage') || auth()->user()?->can('akunting.create') || auth()->user()?->hasRole('akuntan') || auth()->user()?->hasRole('keuangan'))
                                        <button wire:click="bukaBayarPo({{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-accent text-white font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Bayar</button>
                                    @endif
                                @endif
                                @can('lihat-audit-log')
                                    <button wire:click="bukaRiwayat('po', {{ $po->id }})" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]">Riwayat</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-12 text-center text-ink-400">Belum ada PO. Klik "+ Buat PO" untuk input pembelian ke supplier.</td></tr>
                @endforelse
            </x-prism.data-table>

            <!-- Supplier Cards -->
            <x-prism.glass-card title="Supplier Terdaftar" subtitle="Pembelian dikelola via Purchase Order (PO)">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse($suppliers as $sp)
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 flex items-start justify-between gap-2">
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-bold text-white">{{ $sp->nama }}</p>
                                    @if(! $sp->is_active)
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-red/10 text-up-red font-semibold">Nonaktif</span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-ink-400">{{ $sp->telepon ?? '-' }} · termin {{ $sp->termin_hari }} hari</p>
                                @if($sp->kontak)
                                    <p class="text-[10px] text-ink-500">PIC: {{ $sp->kontak }}</p>
                                @endif
                            </div>
                            @can('wms.create')
                                <button
                                    wire:click="bukaEditSupplier({{ $sp->id }})"
                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition text-xs cursor-pointer"
                                    title="Edit Supplier"
                                >
                                    ✏️
                                </button>
                            @endcan
                        </div>
                    @empty
                        <p class="text-xs text-ink-500 col-span-full py-4 text-center">Belum ada supplier.</p>
                    @endforelse
                </div>
            </x-prism.glass-card>
        </div>

    <!-- MODAL: BUAT PO [T-10] -->
    @if($showPoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Buat Purchase Order (PO)</h3>
                    <button wire:click="$set('showPoModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Supplier *</label>
                        <select wire:model="poForm.supplier_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                            <option value="" class="bg-ink-900">Pilih Supplier...</option>
                            @foreach($suppliers as $sp)
                                <option value="{{ $sp->id }}" class="bg-ink-900">{{ $sp->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan *</label>
                        <select wire:model="poForm.gudang_tujuan_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                            <option value="" class="bg-ink-900">Pilih Gudang...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Metode Bayar *</label>
                        <select wire:model.live="poForm.metode_bayar" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                            <option value="kredit" class="bg-ink-900">Kredit (utang)</option>
                            <option value="tunai" class="bg-ink-900">Tunai</option>
                        </select>
                    </div>
                    @if(($poForm['metode_bayar'] ?? 'kredit') === 'tunai')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kas / Bank Pembayaran *</label>
                            <select wire:model="poForm.akun_kas_bank" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                                @foreach($akunKasBankList ?? [] as $ak)
                                    <option value="{{ $ak->kode }}" class="bg-ink-900">[{{ $ak->kode }}] {{ $ak->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jatuh Tempo (kosong = termin supplier)</label>
                            <input type="date" wire:model="poForm.jatuh_tempo" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs min-h-[44px]" />
                        </div>
                    @endif
                </div>

                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-ink-300 uppercase">Items</span>
                    <button wire:click="addPoItem" class="text-up-primary text-xs font-bold cursor-pointer min-h-[44px] flex items-center">+ Tambah Item</button>
                </div>

                <div class="space-y-2 mb-4 max-h-56 overflow-y-auto pr-1">
                    @foreach($poForm['items'] as $idx => $item)
                        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                            <select wire:model="poForm.items.{{ $idx }}.produk_id" wire:change="poProdukDipilih({{ $idx }})" class="flex-1 px-3 py-2 rounded-xl glass-input text-xs min-h-[44px]">
                                <option value="" class="bg-ink-900">Pilih Produk...</option>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}" class="bg-ink-900">{{ $p->nama }}</option>
                                @endforeach
                            </select>
                            <div class="flex items-center gap-2">
                                @cansee('harga_beli')
                                <input type="text" inputmode="numeric" x-format-number wire:model="poForm.items.{{ $idx }}.harga_beli" class="w-full sm:w-24 px-2 py-2 rounded-xl glass-input text-xs tabular-nums min-h-[44px]" placeholder="Harga" />
                                @cannotsee('harga_beli')
                                <input type="text" class="w-full sm:w-24 px-2 py-2 rounded-xl glass-input text-xs tabular-nums text-ink-500 bg-white/5 border border-white/5 cursor-not-allowed min-h-[44px]" placeholder="Harga (superadmin)" disabled />
                                @endcansee
                                <input type="number" wire:model="poForm.items.{{ $idx }}.jumlah" min="1" class="w-20 sm:w-16 px-2 py-2 rounded-xl glass-input text-xs tabular-nums text-center min-h-[44px]" placeholder="Qty" />
                                <button wire:click="removePoItem({{ $idx }})" class="p-2 text-up-red cursor-pointer text-xs min-h-[44px] flex items-center justify-center">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button wire:click="$set('showPoModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.97]">Batal</button>
                    <button wire:click="simpanPo" class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-primary/25 transition active:scale-[0.97]">Simpan PO (Draft)</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: TAMBAH SUPPLIER [T-10] -->
    @if($showSupplierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Supplier</h3>
                    <button wire:click="$set('showSupplierModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div><label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label><input type="text" wire:model="supplierForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" /></div>
                    <div><label class="block text-xs font-semibold text-ink-300 mb-1.5">Kontak / PIC</label><input type="text" wire:model="supplierForm.kontak" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" /></div>
                    <div><label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label><input type="text" wire:model="supplierForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" /></div>
                    <div><label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label><textarea wire:model="supplierForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea></div>
                    <div><label class="block text-xs font-semibold text-ink-300 mb-1.5">Termin (hari)</label><input type="number" wire:model="supplierForm.termin_hari" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" /></div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showSupplierModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanSupplier" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BAYAR PO [T-10] -->
    @if($bayarPoId && (auth()->user()?->hasRole('super-admin') || auth()->user()?->can('utang.manage') || auth()->user()?->can('akunting.create') || auth()->user()?->hasRole('akuntan') || auth()->user()?->hasRole('keuangan')))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-data="{ open: true }">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative" @keydown.escape.window="Livewire.dispatch('alert', {type:'info',message:''})">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white">Bayar Purchase Order</h3>
                        <p class="text-[10px] text-ink-400">Pembayaran diaudit & sinkron ke buku utang</p>
                    </div>
                    <button wire:click="$set('bayarPoId', null)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                @php $poAktif = $poList->firstWhere('id', $bayarPoId); @endphp
                <div class="space-y-4">
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                        <div class="flex justify-between mb-1"><span class="text-ink-400">{{ $poAktif?->no_po }}</span><x-prism.status-pill :status="$poAktif?->status ?? ''" size="sm" /></div>
                        <div class="flex justify-between"><span class="text-ink-400">Sisa utang</span><span class="font-bold text-up-amber tabular-nums">Rp {{ number_format($poAktif?->sisa ?? 0, 0, ',', '.') }}</span></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Sumber Dana (Kas / Bank) *</label>
                        <select wire:model="bayarPoAkun" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                            @foreach($akunKasBankList ?? [] as $ak)
                                <option value="{{ $ak->kode }}" class="bg-ink-900">[{{ $ak->kode }}] {{ $ak->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jumlah Bayar (Rp) *</label>
                        <input type="text" inputmode="numeric" x-format-number wire:model.live="bayarPoJumlah" class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums" />
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="$set('bayarPoId', null)" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button wire:click="bayarPo" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px] shadow-md shadow-up-mint/20 active:scale-[0.97]">Konfirmasi Bayar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: EDIT SUPPLIER -->
    @if($editSupplierId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">Edit Supplier</h3>
                    <button wire:click="$set('editSupplierId', null)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="editSupplierForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kontak / PIC</label>
                        <input type="text" wire:model="editSupplierForm.kontak" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                        <input type="text" wire:model="editSupplierForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="editSupplierForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Termin (hari)</label>
                        <input type="number" wire:model="editSupplierForm.termin_hari" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="supplierActive" wire:model="editSupplierForm.is_active" class="rounded border-white/20 text-up-primary focus:ring-up-primary" />
                        <label for="supplierActive" class="text-xs text-ink-200">Supplier Aktif</label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('editSupplierId', null)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="perbaruiSupplier" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px] active:scale-[0.97]">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: DETAIL PURCHASE ORDER (AUDIT & RINCIAN) -->
    @if($detailPo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
            <div class="w-full max-w-3xl glass-panel p-5 sm:p-7 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <!-- Header -->
                <div class="flex items-start sm:items-center justify-between pb-4 border-b border-white/10 gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-white font-mono tracking-wide">{{ $detailPo->no_po }}</h3>
                            <x-prism.status-pill :status="str_replace('_','-',$detailPo->status)" size="sm" />
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $detailPo->metode_bayar === 'kredit' ? 'bg-up-amber/10 text-up-amber' : 'bg-up-mint/10 text-up-mint' }}">
                                {{ strtoupper($detailPo->metode_bayar) }}
                            </span>
                        </div>
                        <p class="text-xs text-ink-400 mt-1">
                            Dibuat: {{ $detailPo->created_at->format('d/m/Y H:i') }} · Jatuh Tempo: {{ $detailPo->jatuh_tempo?->format('d/m/Y') ?? '-' }}
                        </p>
                    </div>
                    <button wire:click="tutupDetailPo" class="p-2 text-ink-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-4 text-xs">
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5 space-y-1">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Supplier</span>
                        <div class="font-bold text-white">{{ $detailPo->supplier?->nama ?? '-' }}</div>
                        <div class="text-ink-400 text-[11px]">{{ $detailPo->supplier?->telepon ?? '-' }} · PIC: {{ $detailPo->supplier?->kontak ?? '-' }}</div>
                        <div class="text-ink-500 text-[11px]">{{ $detailPo->supplier?->alamat ?? '-' }}</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/5 space-y-1">
                        <span class="text-[10px] text-ink-400 block font-semibold uppercase">Gudang Tujuan</span>
                        <div class="font-bold text-white">{{ $detailPo->gudangTujuan?->nama ?? '-' }}</div>
                        <div class="text-ink-400 text-[11px]">Cabang: {{ $detailPo->gudangTujuan?->cabang?->nama ?? '-' }}</div>
                        @if($detailPo->catatan)
                            <div class="text-ink-400 text-[11px] mt-1 pt-1 border-t border-white/5">Catatan: {{ $detailPo->catatan }}</div>
                        @endif
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="space-y-2 mb-4 flex-1 overflow-y-auto">
                    <h4 class="text-xs font-bold text-ink-300 uppercase tracking-wider">Item Pembelian</h4>
                    <div class="rounded-xl border border-white/5 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-ink-400 font-semibold border-b border-white/5">
                                <tr>
                                    <th class="py-2.5 px-3">Produk</th>
                                    <th class="py-2.5 px-3 text-right">Harga Beli</th>
                                    <th class="py-2.5 px-3 text-center">Qty</th>
                                    <th class="py-2.5 px-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($detailPo->items as $item)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-2.5 px-3">
                                            <div class="font-bold text-white">{{ $item->produk?->nama ?? '-' }}</div>
                                            @if($item->skuVariant)
                                                <div class="text-[10px] text-ink-400 font-mono">SKU: {{ $item->skuVariant->sku }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-right tabular-nums text-ink-200">
                                            @cansee('harga_beli')
                                                Rp {{ number_format((float) $item->harga_beli, 0, ',', '.') }}
                                            @cannotsee('harga_beli')
                                                —
                                            @endcansee
                                        </td>
                                        <td class="py-2.5 px-3 text-center tabular-nums font-bold text-white">{{ $item->jumlah }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums font-bold text-white">
                                            @cansee('harga_beli')
                                                Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}
                                            @cannotsee('harga_beli')
                                                —
                                            @endcansee
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financial Summary Cards -->
                <div class="grid grid-cols-3 gap-3 p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 text-xs mb-4">
                    <div>
                        <span class="text-[10px] text-ink-400 block">Total Nilai PO</span>
                        <span class="text-sm font-bold text-white tabular-nums">Rp {{ number_format((float) $detailPo->total, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-ink-400 block">Total Dibayar</span>
                        <span class="text-sm font-bold text-up-mint tabular-nums">Rp {{ number_format((float) $detailPo->total_dibayar, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-ink-400 block">Sisa Utang</span>
                        <span class="text-sm font-bold text-up-amber tabular-nums">Rp {{ number_format((float) $detailPo->sisa, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Riwayat Pembayaran Supplier (Audited) -->
                <div class="space-y-2 mb-2">
                    <h4 class="text-xs font-bold text-ink-300 uppercase tracking-wider">Riwayat Pembayaran Supplier (Audited)</h4>
                    @if($detailPo->pembayaran->isNotEmpty())
                        <div class="rounded-xl border border-white/5 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-white/5 text-ink-400 font-semibold border-b border-white/5">
                                    <tr>
                                        <th class="py-2 px-3">Tanggal</th>
                                        <th class="py-2 px-3 text-right">Nominal</th>
                                        <th class="py-2 px-3">Dicatat Oleh</th>
                                        <th class="py-2 px-3">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($detailPo->pembayaran as $pby)
                                        <tr class="hover:bg-white/[0.02]">
                                            <td class="py-2 px-3 tabular-nums text-ink-300">{{ $pby->dibayar_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums font-bold text-up-mint">Rp {{ number_format((float) $pby->jumlah, 0, ',', '.') }}</td>
                                            <td class="py-2 px-3 text-ink-200">{{ $pby->user?->name ?? 'Sistem' }}</td>
                                            <td class="py-2 px-3 text-ink-400 text-[11px]">{{ $pby->keterangan ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-xs text-ink-500 py-2">Belum ada riwayat pembayaran untuk PO ini.</p>
                    @endif
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-2">
                    <button wire:click="tutupDetailPo" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Tutup
                    </button>
                    @if($detailPo->status === 'diterima' && $detailPo->sisa > 0)
                        @if(auth()->user()?->can('utang.manage') || auth()->user()?->can('akunting.create'))
                            <button
                                wire:click="tutupDetailPo; bukaBayarPo({{ $detailPo->id }})"
                                class="px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-accent/25 active:scale-[0.97]"
                            >
                                Bayar PO Ini (Sisa Rp {{ number_format($detailPo->sisa, 0, ',', '.') }})
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: REKOMENDASI PENGADAAN (ABC, ROP, MIN-MAX, MODIFIED JIT) -->
    @if($showProcurementModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
            <div class="w-full max-w-5xl glass-panel p-5 sm:p-7 rounded-3xl relative my-auto max-h-[90vh] flex flex-col border border-white/10 shadow-2xl">
                <!-- Header Modal -->
                <div class="flex items-start sm:items-center justify-between pb-4 border-b border-white/10 gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📊</span>
                            <h3 class="text-lg font-bold text-white tracking-wide">Rekomendasi Pengadaan Stok</h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                ABC • ROP • Min-Max • JIT
                            </span>
                        </div>
                        <p class="text-xs text-ink-400 mt-1">
                            Sistem menghitung titik pemesanan optimal berbasis pergerakan barang dan pesanan servis aktif.
                        </p>
                    </div>
                    <button wire:click="closeProcurementModal" class="p-2 text-ink-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- KPI & Metric Cards -->
                @if($procurementSummary)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-4">
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[11px] font-semibold text-ink-400 block">Total SKU Terdata</span>
                            <span class="text-lg font-bold text-white tabular-nums">{{ number_format($procurementSummary['total_sku']) }}</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-up-accent/10 border border-up-accent/25">
                            <span class="text-[11px] font-semibold text-up-accent block">Perlu Order (Restock)</span>
                            <span class="text-lg font-bold text-up-accent tabular-nums">{{ number_format($procurementSummary['total_needs_order']) }} SKU</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-up-mint/10 border border-up-mint/25">
                            <span class="text-[11px] font-semibold text-up-mint block">Estimasi Biaya Order</span>
                            <span class="text-lg font-bold text-up-mint tabular-nums">Rp {{ number_format($procurementSummary['total_estimasi_biaya'], 0, ',', '.') }}</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center justify-between text-xs">
                            <div class="space-y-0.5">
                                <div class="text-[10px] text-ink-400">Kelas A: <strong class="text-up-red font-bold">{{ $procurementSummary['breakdown']['A']['needs_order'] ?? 0 }}</strong></div>
                                <div class="text-[10px] text-ink-400">Kelas B: <strong class="text-up-primary font-bold">{{ $procurementSummary['breakdown']['B']['needs_order'] ?? 0 }}</strong></div>
                            </div>
                            <div class="space-y-0.5 text-right">
                                <div class="text-[10px] text-ink-400">Kelas C: <strong class="text-ink-200 font-bold">{{ $procurementSummary['breakdown']['C']['needs_order'] ?? 0 }}</strong></div>
                                <div class="text-[10px] text-ink-400">JIT: <strong class="text-purple-400 font-bold">{{ $procurementSummary['breakdown']['JIT']['needs_order'] ?? 0 }}</strong></div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Filter Bar -->
                <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-2xl bg-black/30 border border-white/5 mb-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <select wire:model.live="procurementFilter.abc_class" class="px-3 py-1.5 rounded-xl glass-input text-xs font-semibold">
                            <option value="" class="bg-ink-900">Semua Kelas ABC</option>
                            <option value="A" class="bg-ink-900">Kelas A (Fast Moving)</option>
                            <option value="B" class="bg-ink-900">Kelas B (Normal)</option>
                            <option value="C" class="bg-ink-900">Kelas C (Slow Moving)</option>
                        </select>

                        <select wire:model.live="procurementFilter.is_ondemand" class="px-3 py-1.5 rounded-xl glass-input text-xs font-semibold">
                            <option value="" class="bg-ink-900">Semua Tipe Stok</option>
                            <option value="0" class="bg-ink-900">Regular (Stok Rutin)</option>
                            <option value="1" class="bg-ink-900">Modified JIT (On-Demand)</option>
                        </select>

                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-ink-200 cursor-pointer">
                            <input type="checkbox" wire:model.live="procurementFilter.only_reorder" class="rounded bg-black/40 border-white/20 text-up-accent focus:ring-0">
                            <span>Hanya yang butuh order</span>
                        </label>
                    </div>

                    <div class="w-full sm:w-64">
                        <input type="text" wire:model.live.debounce.300ms="procurementFilter.search" placeholder="Cari nama produk / barcode..." class="w-full px-3 py-1.5 rounded-xl glass-input text-xs" />
                    </div>
                </div>

                <!-- Table Content (Scrollable) -->
                <div class="overflow-y-auto flex-1 rounded-2xl border border-white/5 max-h-[50vh]">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-ink-950/95 backdrop-blur-sm z-10 border-b border-white/10">
                            <tr class="text-ink-400 font-semibold text-[11px] uppercase tracking-wider">
                                <th class="py-3 px-3.5">Produk</th>
                                <th class="py-3 px-3">Metode &amp; Kelas</th>
                                <th class="py-3 px-3 text-right">Stok / Demand</th>
                                <th class="py-3 px-3 text-right">ROP / Min-Max</th>
                                <th class="py-3 px-3 text-right">Rekomendasi</th>
                                <th class="py-3 px-3 text-right">Estimasi Biaya</th>
                                <th class="py-3 px-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($procurementData ?? [] as $item)
                                <tr class="hover:bg-white/[0.02] transition-colors {{ $item['action'] === 'ORDER' ? 'bg-up-accent/[0.03]' : '' }}">
                                    <td class="py-3 px-3.5">
                                        <div class="font-bold text-white">{{ $item['nama'] }}</div>
                                        <div class="text-[10px] text-ink-400 flex items-center gap-1.5 mt-0.5">
                                            @if($item['barcode']) <span class="font-mono">{{ $item['barcode'] }}</span> • @endif
                                            <span>{{ $item['kategori'] ?? 'Umum' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold {{ $item['abc_class'] === 'A' ? 'bg-up-red/15 text-up-red border border-up-red/30' : ($item['abc_class'] === 'C' ? 'bg-white/10 text-ink-300 border border-white/20' : 'bg-up-primary/15 text-up-primary border border-up-primary/30') }}">
                                                Kelas {{ $item['abc_class'] }}
                                            </span>
                                            @if($item['is_ondemand'])
                                                <span class="px-1.5 py-0.5 rounded bg-purple-500/15 border border-purple-500/30 text-[9px] font-bold text-purple-400">JIT</span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] text-ink-500 block mt-0.5">{{ $item['metode'] }}</span>
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums">
                                        <div class="font-bold text-white">{{ number_format($item['current_stock']) }} {{ $item['satuan'] }}</div>
                                        @if($item['pending_demand'] > 0)
                                            <span class="text-[10px] text-up-amber font-semibold">Demand: {{ $item['pending_demand'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums text-ink-300 text-[11px]">
                                        @if($item['is_ondemand'])
                                            <span class="text-ink-500">On-Demand</span>
                                        @elseif($item['abc_class'] === 'A')
                                            <div>ROP: <strong class="text-white">{{ $item['reorder_point'] ?? '-' }}</strong></div>
                                            <div class="text-[10px] text-ink-500">Max: {{ $item['max_stock'] ?? '-' }}</div>
                                        @else
                                            <div>Min: <strong class="text-white">{{ $item['min_stock'] ?? '-' }}</strong></div>
                                            <div class="text-[10px] text-ink-500">Max: {{ $item['max_stock'] ?? '-' }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums">
                                        @if($item['action'] === 'ORDER')
                                            <span class="px-2 py-0.5 rounded-lg bg-up-accent/20 border border-up-accent/40 text-up-accent font-black text-xs inline-block">
                                                +{{ number_format($item['recommended_order']) }} {{ $item['satuan'] }}
                                            </span>
                                        @else
                                            <span class="text-ink-500 font-semibold text-[11px]">—</span>
                                        @endif
                                        <div class="text-[9px] text-ink-500 mt-0.5 max-w-[160px] truncate" title="{{ $item['alasan'] }}">
                                            {{ $item['alasan'] }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums">
                                        @if($item['estimasi_biaya'] > 0)
                                            <span class="font-bold text-white">Rp {{ number_format($item['estimasi_biaya'], 0, ',', '.') }}</span>
                                            <span class="block text-[10px] text-ink-500">@ Rp {{ number_format($item['harga_beli'], 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-ink-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3.5 text-center">
                                        @if($item['action'] === 'ORDER')
                                            <button wire:click="terapkanKePo({{ $item['produk_id'] }}, {{ $item['recommended_order'] }})" class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/20 transition-all cursor-pointer whitespace-nowrap">
                                                + Buat PO
                                            </button>
                                        @else
                                            <button wire:click="terapkanKePo({{ $item['produk_id'] }}, 1)" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white font-semibold text-[10px] border border-white/5 transition-colors cursor-pointer">
                                                Order Manual
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-ink-400">
                                        Tidak ada rekomendasi pengadaan yang sesuai dengan filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer Modal -->
                <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-3">
                    <span class="text-[11px] text-ink-500">
                        Rekomendasi dihitung secara realtime berdasarkan parameter ABC, ROP, Min-Max, dan order servis aktif.
                    </span>
                    <button wire:click="closeProcurementModal" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per PO --}}
    @include('partials.riwayat-modal')
</div>
