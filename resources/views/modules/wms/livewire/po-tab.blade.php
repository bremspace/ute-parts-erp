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
                        <td class="py-3.5 px-4 flex gap-1.5">
                            @if($po->status === 'usulan')
                                <button wire:click="konfirmasiUsulan({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-accent hover:brightness-110 text-white font-bold text-[10px] cursor-pointer">Konfirmasi</button>
                            @elseif($po->status === 'draft')
                                <button wire:click="kirimPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] cursor-pointer">Kirim</button>
                                <button wire:click="terimaPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-mint text-ink-950 font-bold text-[10px] cursor-pointer">Terima</button>
                            @elseif($po->status === 'dikirim')
                                <button wire:click="terimaPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-mint text-ink-950 font-bold text-[10px] cursor-pointer">Terima</button>
                            @endif
                            @if($po->status === 'diterima' && $po->sisa > 0)
                                <button wire:click="bukaBayarPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-accent text-white font-bold text-[10px] cursor-pointer">Bayar</button>
                            @endif
                            @can('lihat-audit-log')
                                <button wire:click="bukaRiwayat('po', {{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer">Riwayat</button>
                            @endcan
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
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-xs font-bold text-white">{{ $sp->nama }}</p>
                            <p class="text-[10px] text-ink-400">{{ $sp->telepon ?? '-' }} · termin {{ $sp->termin_hari }} hari</p>
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

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Supplier *</label>
                        <select wire:model="poForm.supplier_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Supplier...</option>
                            @foreach($suppliers as $sp)
                                <option value="{{ $sp->id }}" class="bg-ink-900">{{ $sp->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan *</label>
                        <select wire:model="poForm.gudang_tujuan_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Metode Bayar *</label>
                        <select wire:model="poForm.metode_bayar" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="kredit" class="bg-ink-900">Kredit (utang)</option>
                            <option value="tunai" class="bg-ink-900">Tunai</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jatuh Tempo (kosong = termin supplier)</label>
                        <input type="date" wire:model="poForm.jatuh_tempo" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                    </div>
                </div>

                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-ink-300 uppercase">Items</span>
                    <button wire:click="addPoItem" class="text-up-primary text-xs font-bold cursor-pointer">+ Tambah Item</button>
                </div>

                <div class="space-y-2 mb-4 max-h-56 overflow-y-auto pr-1">
                    @foreach($poForm['items'] as $idx => $item)
                        <div class="flex gap-2 items-center">
                            <select wire:model="poForm.items.{{ $idx }}.produk_id" wire:change="poProdukDipilih({{ $idx }})" class="flex-1 px-3 py-2 rounded-xl glass-input text-xs">
                                <option value="" class="bg-ink-900">Pilih Produk...</option>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}" class="bg-ink-900">{{ $p->nama }}</option>
                                @endforeach
                            </select>
                            @cansee('harga_beli')
                            <input type="number" wire:model="poForm.items.{{ $idx }}.harga_beli" class="w-24 px-2 py-2 rounded-xl glass-input text-xs tabular-nums" placeholder="Harga" />
@cannotsee('harga_beli')
                            <input type="text" class="w-24 px-2 py-2 rounded-xl glass-input text-xs tabular-nums text-ink-500 bg-white/5 border border-white/5 cursor-not-allowed" placeholder="Harga (superadmin)" disabled />
@endcansee
                            <input type="number" wire:model="poForm.items.{{ $idx }}.jumlah" min="1" class="w-16 px-2 py-2 rounded-xl glass-input text-xs tabular-nums text-center" placeholder="Qty" />
                            <button wire:click="removePoItem({{ $idx }})" class="p-2 text-up-red cursor-pointer text-xs">✕</button>
                        </div>
                    @endforeach
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showPoModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanPo" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan PO (Draft)</button>
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
    @if($bayarPoId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-data="{ open: true }">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative" @keydown.escape.window="Livewire.dispatch('alert', {type:'info',message:''})">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Bayar Purchase Order</h3>
                    <button wire:click="$set('bayarPoId', null)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                @php $poAktif = $poList->firstWhere('id', $bayarPoId); @endphp
                <div class="space-y-4">
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                        <div class="flex justify-between mb-1"><span class="text-ink-400">{{ $poAktif?->no_po }}</span><x-prism.status-pill :status="$poAktif?->status ?? ''" size="sm" /></div>
                        <div class="flex justify-between"><span class="text-ink-400">Sisa utang</span><span class="font-bold text-up-amber tabular-nums">Rp {{ number_format($poAktif?->sisa ?? 0, 0, ',', '.') }}</span></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jumlah Bayar (Rp)</label>
                        <input type="number" wire:model.live="bayarPoJumlah" class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums" />
                    </div>
                    <button wire:click="bayarPo" class="w-full py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">Catat Pembayaran</button>
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per PO --}}
    @include('partials.riwayat-modal')
</div>
