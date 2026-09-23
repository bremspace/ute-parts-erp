<div>
    <!-- TAB 1: INVENTORI & STOK -->
        <div class="space-y-4">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <x-prism.barcode-scan-input
                        placeholder="Cari sparepart berdasarkan nama, brand, atau tipe HP..."
                        model="search"
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
            </div>

            <!-- DataTable Dual-Mode -->
            <x-prism.data-table :headers="['Produk & SKU', 'Kategori', 'Kompatibilitas', 'Gudang', 'Harga Retail', 'Stok', 'Status', '']">
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
                        <td colspan="8" class="py-12 text-center text-ink-400">
                            Tidak ada item stok yang cocok dengan kriteria pencarian
                        </td>
                    </tr>
                @endforelse

                <!-- Mobile Card List Slot -->
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
                            <div class="flex justify-between items-center text-xs pt-2 border-t border-white/5">
                                <span class="text-ink-400">{{ $stok->gudang?->nama }}</span>
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

    <!-- MODAL: ATUR RAK [T-12] -->
    @if($showRakModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Manajemen Rak (Bin)</h3>
                    <button wire:click="$set('showRakModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Gudang *</label>
                            <select wire:model="rakForm.gudang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs">
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Kode Rak *</label>
                            <input type="text" wire:model="rakForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" placeholder="RAK-A1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Nama / Label</label>
                            <input type="text" wire:model="rakForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" placeholder="Rak LCD & Baterai" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Zona</label>
                            <input type="text" wire:model="rakForm.zona" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" placeholder="A" />
                        </div>
                    </div>
                    <button wire:click="simpanRak" class="w-full py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">+ Tambah Rak</button>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-bold text-ink-300 uppercase">Rak Terdaftar</p>
                    @forelse($raks as $rk)
                        <div class="flex justify-between py-2 px-3 rounded-lg bg-white/[0.03] border border-white/5 text-xs">
                            <span class="font-mono text-up-primary">{{ $rk->kode }}</span>
                            <span class="text-ink-200">{{ $rk->nama }}</span>
                            <span class="text-ink-400">{{ $rk->gudang?->nama }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-ink-500 text-center py-4">Belum ada rak.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per item stok --}}
    @include('partials.riwayat-modal')
</div>
