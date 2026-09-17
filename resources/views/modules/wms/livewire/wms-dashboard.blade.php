<div class="space-y-6">
    <!-- Header with Tabs and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2">
            <button
                wire:click="$set('activeTab', 'stok')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'stok' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Inventori & Stok
            </button>
            <button
                wire:click="$set('activeTab', 'transfer')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'transfer' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Transfer Antar Gudang
            </button>
            <button
                wire:click="$set('activeTab', 'opname')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'opname' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Stock Opname
            </button>
        </div>

        <!-- Tab-specific Quick Action -->
        <div>
            @if($activeTab === 'transfer')
                <button
                    wire:click="openNewTransferModal"
                    class="px-4 py-2 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs shadow-md shadow-up-accent/25 transition-all flex items-center gap-2 cursor-pointer"
                >
                    <span>+ Buat Transfer Baru</span>
                </button>
            @elseif($activeTab === 'opname')
                <button
                    wire:click="openNewOpnameModal"
                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-all flex items-center gap-2 cursor-pointer"
                >
                    <span>+ Mulai Stock Opname</span>
                </button>
            @endif
        </div>
    </div>

    <!-- TAB 1: INVENTORI & STOK -->
    @if($activeTab === 'stok')
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
            <x-prism.data-table :headers="['Produk & SKU', 'Kategori', 'Kompatibilitas', 'Gudang', 'Harga Retail', 'Stok', 'Status']">
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-ink-400">
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
    @endif

    <!-- TAB 2: TRANSFER ANTAR GUDANG -->
    @if($activeTab === 'transfer')
        <div class="space-y-4">
            <x-prism.data-table :headers="['No. Transfer', 'Asal', 'Tujuan', 'Jumlah Item', 'Pengirim', 'Status', 'Aksi']">
                @forelse($transfers as $trf)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $trf->no_transfer }}</td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $trf->gudangAsal?->nama }}</td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $trf->gudangTujuan?->nama }}</td>
                        <td class="py-3.5 px-4 text-white font-bold tabular-nums">{{ $trf->items->sum('jumlah') }} unit</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $trf->pengirim?->name }}</td>
                        <td class="py-3.5 px-4">
                            <x-prism.status-pill :status="$trf->status" />
                        </td>
                        <td class="py-3.5 px-4">
                            @if($trf->status === 'draft')
                                <button
                                    wire:click="kirimTransfer({{ $trf->id }})"
                                    class="px-3 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-semibold text-xs transition-all cursor-pointer"
                                >
                                    Kirim
                                </button>
                            @elseif($trf->status === 'dikirim')
                                <button
                                    wire:click="terimaTransfer({{ $trf->id }})"
                                    class="px-3 py-1 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs transition-all cursor-pointer"
                                >
                                    Konfirmasi Terima
                                </button>
                            @else
                                <span class="text-ink-500 text-[11px]">- Selesai -</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-ink-400">
                            Belum ada riwayat transfer antar gudang
                        </td>
                    </tr>
                @endforelse
            </x-prism.data-table>
        </div>
    @endif

    <!-- TAB 3: STOCK OPNAME -->
    @if($activeTab === 'opname')
        <div class="space-y-4">
            <x-prism.data-table :headers="['No. Opname', 'Gudang', 'Pembuat', 'Total Item', 'Tanggal', 'Status', 'Aksi Supervisor']">
                @forelse($opnames as $opn)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $opn->no_opname }}</td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $opn->gudang?->nama }}</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $opn->pembuat?->name }}</td>
                        <td class="py-3.5 px-4 text-white font-bold tabular-nums">{{ $opn->items->count() }} sparepart</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $opn->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-3.5 px-4">
                            <x-prism.status-pill :status="$opn->status" />
                        </td>
                        <td class="py-3.5 px-4">
                            @if($opn->status === 'menunggu_approval')
                                <button
                                    wire:click="approveOpname({{ $opn->id }})"
                                    class="px-3 py-1 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs transition-all shadow-md shadow-up-mint/20 cursor-pointer"
                                >
                                    Approve & Sesuaikan
                                </button>
                            @else
                                <span class="text-ink-500 text-[11px]">
                                    {{ $opn->approver ? "Approved by {$opn->approver->name}" : '-' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-ink-400">
                            Belum ada riwayat stock opname
                        </td>
                    </tr>
                @endforelse
            </x-prism.data-table>
        </div>
    @endif

    <!-- MODAL: BUAT TRANSFER BARU -->
    @if($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Buat Transfer Antar Gudang</h3>
                    <button wire:click="$set('showTransferModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Asal</label>
                        <select wire:model="transferGudangAsalId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang Asal...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan</label>
                        <select wire:model="transferGudangTujuanId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang Tujuan...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Items Row -->
                <div class="space-y-3 mb-4 max-h-60 overflow-y-auto pr-1">
                    <div class="flex justify-between items-center text-xs font-semibold text-ink-400">
                        <span>Daftar Sparepart yang Ditransfer</span>
                        <button type="button" wire:click="addTransferRow" class="text-up-primary hover:text-indigo-400 font-bold">+ Tambah Baris</button>
                    </div>

                    @foreach($transferItems as $index => $row)
                        <div class="flex gap-3 items-center">
                            <div class="flex-1">
                                <select wire:model="transferItems.{{ $index }}.produk_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs">
                                    <option value="" class="bg-ink-900">Pilih Produk...</option>
                                    @foreach($allProducts as $p)
                                        <option value="{{ $p->id }}" class="bg-ink-900">{{ $p->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-24">
                                <input
                                    type="number"
                                    wire:model="transferItems.{{ $index }}.jumlah"
                                    min="1"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-center tabular-nums"
                                />
                            </div>
                            <button
                                type="button"
                                wire:click="removeTransferRow({{ $index }})"
                                class="p-2 text-up-red hover:bg-white/5 rounded-lg text-xs"
                            >
                                ✕
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan Pengiriman</label>
                    <textarea wire:model="transferCatatan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs" placeholder="Misal: Restok darurat LCD iPhone 13..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showTransferModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="saveTransfer" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer">Simpan Draft Transfer</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: MULAI STOCK OPNAME -->
    @if($showOpnameModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Input Hasil Stock Opname Fisik</h3>
                    <button wire:click="$set('showOpnameModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Lokasi Gudang yang Di-Opname</label>
                    <select wire:model.live="opnameGudangId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                        @foreach($gudangs as $g)
                            <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Opname Spreadsheet Table -->
                <div class="max-h-72 overflow-y-auto mb-4 rounded-xl border border-white/10 overflow-hidden">
                    <table class="w-full text-xs text-left text-ink-100">
                        <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold sticky top-0 bg-ink-900">
                            <tr>
                                <th class="p-3">Produk</th>
                                <th class="p-3 text-center">Stok Sistem</th>
                                <th class="p-3 text-center w-28">Stok Fisik</th>
                                <th class="p-3 text-center">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($opnameRows as $idx => $r)
                                <tr>
                                    <td class="p-3 font-medium text-white">{{ $r['nama'] }}</td>
                                    <td class="p-3 text-center font-bold tabular-nums text-ink-400">{{ $r['stok_sistem'] }}</td>
                                    <td class="p-2 text-center">
                                        <input
                                            type="number"
                                            wire:change="updateOpnameFisik({{ $idx }}, $event.target.value)"
                                            value="{{ $r['stok_fisik'] }}"
                                            class="w-20 px-2 py-1.5 rounded-lg glass-input text-center font-bold text-white tabular-nums mx-auto block"
                                        />
                                    </td>
                                    <td class="p-3 text-center font-bold tabular-nums {{ $r['selisih'] < 0 ? 'text-up-red' : ($r['selisih'] > 0 ? 'text-up-mint' : 'text-ink-400') }}">
                                        {{ $r['selisih'] > 0 ? "+{$r['selisih']}" : $r['selisih'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showOpnameModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="saveOpname" class="flex-2 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer">Kirim ke Supervisor untuk Approval</button>
                </div>
            </div>
        </div>
    @endif
</div>
