<div class="space-y-6">
    <!-- Header with Tabs and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 flex-wrap">
            <button
                wire:click="$set('activeTab', 'stok')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'stok' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Inventori & Stok
            </button>
            <button
                wire:click="$set('activeTab', 'produk')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'produk' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Master Produk
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
            <button
                wire:click="$set('activeTab', 'po')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'po' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                PO & Supplier
            </button>
        </div>

        <!-- Tab-specific Quick Action -->
        <div>
            @if($activeTab === 'produk')
                <div class="flex items-center gap-2">
                    <button
                        wire:click="openImportModal"
                        class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-ink-200 font-bold text-xs transition-all flex items-center gap-2 cursor-pointer"
                        title="Import master produk dari Excel (inisialisasi — bukan stok masuk harian)"
                    >
                        📥 Import Excel
                    </button>
                    <button
                        wire:click="openProdukModal"
                        class="px-4 py-2 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-all flex items-center gap-2 cursor-pointer"
                    >
                        <span>+ Tambah Produk</span>
                    </button>
                </div>
            @elseif($activeTab === 'transfer')
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
            @elseif($activeTab === 'po')
                <div class="flex gap-2">
                    <button wire:click="$set('showSupplierModal', true)" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs cursor-pointer">+ Supplier</button>
                    <button wire:click="openPoModal" class="px-4 py-2 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs cursor-pointer">+ Buat PO</button>
                </div>
            @elseif($activeTab === 'stok')
                <button wire:click="$set('showRakModal', true)" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs cursor-pointer">🏬 Atur Rak</button>
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

    <!-- TAB 1b: MASTER PRODUK -->
    @if($activeTab === 'produk')
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
                        <td class="py-3.5 px-4 text-ink-400">
                            {{ $trf->pengirim?->name }}
                            @if($trf->approver && $trf->approver?->id !== $trf->user_pengirim_id)
                                <span class="block text-[10px] text-up-mint">disetujui {{ $trf->approver->name }}</span>
                            @endif
                        </td>
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

    <!-- TAB 4: PO & SUPPLIER [T-10] -->
    @if($activeTab === 'po')
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
                            @if($po->status === 'draft')
                                <button wire:click="kirimPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] cursor-pointer">Kirim</button>
                                <button wire:click="terimaPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-mint text-ink-950 font-bold text-[10px] cursor-pointer">Terima</button>
                            @elseif($po->status === 'dikirim')
                                <button wire:click="terimaPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-mint text-ink-950 font-bold text-[10px] cursor-pointer">Terima</button>
                            @endif
                            @if($po->status === 'diterima' && $po->sisa > 0)
                                <button wire:click="bukaBayarPo({{ $po->id }})" class="px-2.5 py-1 rounded-lg bg-up-accent text-white font-bold text-[10px] cursor-pointer">Bayar</button>
                            @endif
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
    @endif

    <!-- MODAL: BUAT TRANSFER BARU [T-41] -->
    @if($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <form
                class="w-full max-w-2xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative"
                x-data="{
                    stokInfo: {{ \Illuminate\Support\Js::from($transferStokRows) }},
                    errors: {},
                    validasiStok() {
                        this.errors = {};
                        let ok = true;
                        this.$el.querySelectorAll('[data-trf-qty]').forEach(inp => {
                            const i = Number(inp.dataset.trfQty);
                            const qty = Number(inp.value) || 0;
                            const tersedia = this.stokInfo[i] ? Number(this.stokInfo[i].stok_tersedia) : 0;
                            if (qty > tersedia) {
                                this.errors[i] = 'Qty melebihi stok tersedia di gudang sumber (tersedia: ' + tersedia + ' unit)';
                                ok = false;
                            }
                        });
                        return ok;
                    }
                }"
                @submit.prevent="validasiStok() && $wire.saveTransfer()"
            >
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Buat Transfer Antar Gudang</h3>
                    <button type="button" wire:click="$set('showTransferModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Asal</label>
                        <select wire:model.live="transferGudangAsalId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang Asal...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang Tujuan</label>
                        <select wire:model.live="transferGudangTujuanId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang Tujuan...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Items Row -->
                <div class="space-y-3 mb-4 max-h-72 overflow-y-auto pr-1">
                    <div class="flex justify-between items-center text-xs font-semibold text-ink-400">
                        <span>Daftar Sparepart yang Ditransfer</span>
                        <button type="button" wire:click="addTransferRow" class="text-up-primary hover:text-indigo-400 font-bold">+ Tambah Baris</button>
                    </div>

                    @foreach($transferItems as $index => $row)
                        @php
                            $trfInfo = $transferStokRows[$index] ?? null;
                        @endphp
                        <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                            <div class="flex gap-3 items-center">
                                <div class="flex-1">
                                    <select
                                        wire:model="transferItems.{{ $index }}.produk_id"
                                        wire:change="transferProdukDipilih({{ $index }})"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs"
                                    >
                                        <option value="" class="bg-ink-900">Pilih Produk...</option>
                                        @foreach($allProducts as $p)
                                            <option value="{{ $p->id }}" class="bg-ink-900">{{ $p->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-32">
                                    <select wire:model="transferItems.{{ $index }}.rak_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs">
                                        <option value="" class="bg-ink-900">Rak Tujuan</option>
                                        @foreach($raks as $rk)
                                            @if($rk->gudang_id == $transferGudangTujuanId)
                                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-24">
                                    <input
                                        type="number"
                                        wire:model.live="transferItems.{{ $index }}.jumlah"
                                        data-trf-qty="{{ $index }}"
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

                            <!-- [T-41] Info stok real-time per item -->
                            <div class="grid grid-cols-3 gap-2 mt-2 text-[10px] text-ink-400">
                                <div class="rounded-lg bg-white/[0.03] px-2 py-1.5">
                                    Stok Sumber:
                                    <strong class="text-white tabular-nums">{{ $trfInfo ? $trfInfo['stok_sumber'].' unit' : '-' }}</strong>
                                    @if($trfInfo && $trfInfo['stok_dikunci'] > 0)
                                        <span class="text-up-amber">({{ $trfInfo['stok_dikunci'] }} terkunci)</span>
                                    @endif
                                </div>
                                <div class="rounded-lg bg-white/[0.03] px-2 py-1.5">
                                    Tersedia:
                                    <strong class="text-up-mint tabular-nums">{{ $trfInfo ? $trfInfo['stok_tersedia'].' unit' : '-' }}</strong>
                                </div>
                                <div class="rounded-lg bg-white/[0.03] px-2 py-1.5">
                                    Est. Stok Tujuan:
                                    <strong class="text-white tabular-nums">{{ $trfInfo ? ($trfInfo['stok_tujuan'].' + '.$row['jumlah'].' = '.$trfInfo['estimasi_tujuan']) : '-' }}</strong>
                                </div>
                            </div>

                            <span
                                x-show="errors['{{ $index }}']"
                                x-text="errors['{{ $index }}']"
                                class="block mt-1.5 text-[10px] font-bold text-up-red"
                            ></span>

                            @error('transferItems.'.$index.'.jumlah')
                                <span class="block mt-1.5 text-[10px] font-bold text-up-red">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan Pengiriman</label>
                    <textarea wire:model="transferCatatan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs" placeholder="Misal: Restok darurat LCD iPhone 13..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" wire:click="$set('showTransferModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="submit" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Draft Transfer</button>
                </div>
            </form>
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
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rak (Opsional — filter per lokasi)</label>
                    <select wire:model.live="opnameRakId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                        <option value="" class="bg-ink-900">Semua Rak di Gudang</option>
                        @foreach($raks as $rk)
                            @if($rk->gudang_id == $opnameGudangId)
                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }} - {{ $rk->nama }}</option>
                            @endif
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
                    <button wire:click="$set('showOpnameModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveOpname" class="flex-2 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Kirim ke Supervisor untuk Approval</button>
                </div>
            </div>
        </div>
    @endif

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
                            <input type="number" wire:model="poForm.items.{{ $idx }}.harga_beli" class="w-24 px-2 py-2 rounded-xl glass-input text-xs tabular-nums" placeholder="Harga" />
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
</div>
