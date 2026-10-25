<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Livewire\Component;
use Livewire\WithPagination;

class ReturPenjualanIndex extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    // Filter
    public string $filterStatus = 'all'; // all, draft, disetujui, selesai, ditolak

    public string $filterTipe = 'all'; // all, refund, tukar_produk

    public string $search = '';

    public string $filterTanggalDari = '';

    public string $filterTanggalSampai = '';

    // Modal Form Pengajuan Retur
    public bool $showCreateModal = false;

    public string $kwitansiInput = '';

    public ?Transaksi $selectedTransaksi = null;

    public array $transaksiItemData = [];

    public array $itemInputs = [];

    public string $tipePenyelesaian = 'refund'; // refund | tukar_produk

    public string $metodePengembalian = 'kas'; // kas | piutang

    public string $alasan = '';

    public string $catatanQc = '';

    // State Tukar Produk
    public string $tukarSearch = '';

    public string $tukarBarcode = '';

    public array $tukarItems = [];

    // Modal Detail
    public ?int $detailReturId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'all'],
        'filterTipe' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $input = null;
        if (request()->filled('no_faktur') || request()->has('no_faktur')) {
            $input = trim((string) (request()->query('no_faktur') ?? request('no_faktur')));
        } elseif (request()->filled('transaksi_id') || request()->has('transaksi_id')) {
            $trxId = (int) (request()->query('transaksi_id') ?? request('transaksi_id'));
            if ($trxId > 0) {
                $cabangId = session('cabang_id');
                $trx = Transaksi::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->find($trxId);
                $input = $trx?->no_transaksi ?? (string) $trxId;
            }
        }

        if ($input !== null && $input !== '') {
            $this->bukaModalTambah();
            $this->kwitansiInput = $input;
            $this->cariKwitansi();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTipe(): void
    {
        $this->resetPage();
    }

    public function bukaModalTambah(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function tutupModalTambah(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->kwitansiInput = '';
        $this->selectedTransaksi = null;
        $this->transaksiItemData = [];
        $this->itemInputs = [];
        $this->tipePenyelesaian = 'refund';
        $this->metodePengembalian = 'kas';
        $this->alasan = '';
        $this->catatanQc = '';
        $this->tukarSearch = '';
        $this->tukarBarcode = '';
        $this->tukarItems = [];
        $this->showCreateModal = false;
    }

    /**
     * Langkah 1 Wajib: Cari dan Verifikasi No Kwitansi / Faktur / Pesanan
     */
    public function cariKwitansi(): void
    {
        $term = trim($this->kwitansiInput);
        if (empty($term)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Masukkan Nomor Kwitansi / No. Faktur Transaksi terlebih dahulu.']);

            return;
        }

        $cabangId = session('cabang_id');

        $transaksi = Transaksi::with(['items.produk', 'pelanggan', 'kasir'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->where(function ($q) use ($term) {
                $q->where('no_transaksi', $term)
                    ->orWhere('id', is_numeric($term) ? (int) $term : 0);
            })
            ->first();

        if (! $transaksi) {
            $this->selectedTransaksi = null;
            $this->transaksiItemData = [];
            $this->itemInputs = [];
            $this->dispatch('alert', ['type' => 'error', 'message' => "Faktur/Kwitansi [{$term}] tidak ditemukan di cabang aktif."]);

            return;
        }

        if ($transaksi->status !== 'selesai') {
            $this->selectedTransaksi = null;
            $this->dispatch('alert', ['type' => 'warning', 'message' => "Transaksi {$transaksi->no_transaksi} berstatus [{$transaksi->status}]. Hanya transaksi berstatus 'selesai' yang dapat diproses retur."]);

            return;
        }

        // Pastikan transaksi memiliki gudang_id valid untuk referensi stok tukar dan karantina
        if (! $transaksi->gudang_id || ! Gudang::where('id', $transaksi->gudang_id)->exists()) {
            $cabangRef = (int) ($transaksi->cabang_id ?: ($cabangId ?: 1));
            $resolvedGudangId = Gudang::where('cabang_id', $cabangRef)->where('is_active', true)->value('id')
                ?? Gudang::where('cabang_id', $cabangRef)->value('id')
                ?? (int) session('gudang_id')
                ?? Gudang::where('is_active', true)->value('id')
                ?? Gudang::value('id');

            if ($resolvedGudangId) {
                $transaksi->update(['gudang_id' => $resolvedGudangId]);
                $transaksi->gudang_id = $resolvedGudangId;
            }
        }

        $this->selectedTransaksi = $transaksi;
        $this->showCreateModal = true;
        $this->transaksiItemData = [];
        $this->itemInputs = [];

        foreach ($transaksi->items as $item) {
            $sudahDiretur = (float) ReturnPenjualanItem::whereHas('returnPenjualan', function ($q) {
                $q->whereIn('status', ['draft', 'disetujui', 'selesai']);
            })
                ->where('produk_id', $item->produk_id)
                ->where('sku_variant_id', $item->sku_variant_id)
                ->whereHas('returnPenjualan', fn ($q) => $q->where('transaksi_id', $transaksi->id))
                ->sum('jumlah');

            $sisaBisaDiretur = max(0, (float) $item->jumlah - $sudahDiretur);

            $this->transaksiItemData[$item->id] = [
                'item_id' => $item->id,
                'produk_id' => $item->produk_id,
                'produk_nama' => $item->produk?->nama ?? 'Produk ID #'.$item->produk_id,
                'kode' => $item->produk?->kode ?? '-',
                'has_sn' => (bool) ($item->produk?->has_sn ?? false),
                'qty_beli' => (float) $item->jumlah,
                'sudah_diretur' => $sudahDiretur,
                'sisa_bisa_diretur' => $sisaBisaDiretur,
                'harga_final' => (float) ($item->harga_final ?? $item->harga_satuan ?? 0),
            ];

            $this->itemInputs[$item->id] = [
                'transaksi_item_id' => $item->id,
                'jumlah' => 0,
                'kondisi_barang' => 'rusak', // rusak (karantina supplier) | bagus (layak jual)
                'sn_raw' => '',
            ];
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => "Faktur {$transaksi->no_transaksi} berhasil diverifikasi. Silakan pilih item yang akan diretur."]);
    }

    public function tambahTukarItem(int $produkId): void
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
        }

        // Cek kategori & harga minimum terhadap item transaksi yang dipilih untuk diretur
        $selectedItems = [];
        foreach ($this->itemInputs as $itemId => $input) {
            if ((float) ($input['jumlah'] ?? 0) > 0 && isset($this->transaksiItemData[$itemId])) {
                $selectedItems[] = $this->transaksiItemData[$itemId];
            }
        }

        if (! empty($selectedItems)) {
            $returProdukIds = array_unique(array_column($selectedItems, 'produk_id'));
            $returProduks = Produk::whereIn('id', $returProdukIds)->get();

            $allowedKategoriIds = array_filter($returProduks->pluck('kategori_id')->all());
            $allowedKategoriStrings = array_filter(array_map('strtolower', array_map('trim', $returProduks->pluck('kategori')->all())));
            $minHargaUnit = (float) max(array_column($selectedItems, 'harga_final'));

            if (! empty($allowedKategoriIds) || ! empty($allowedKategoriStrings)) {
                $matchId = $produk->kategori_id && in_array((int) $produk->kategori_id, $allowedKategoriIds, true);
                $matchString = $produk->kategori && in_array(trim(strtolower($produk->kategori)), $allowedKategoriStrings, true);
                if (! $matchId && ! $matchString) {
                    $this->dispatch('alert', ['type' => 'warning', 'message' => "Produk pengganti [{$produk->nama}] harus berada dalam kategori yang sama dengan produk yang diretur."]);

                    return;
                }
            }

            $hargaPengganti = (float) ($produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
            if ($hargaPengganti < $minHargaUnit) {
                $this->dispatch('alert', ['type' => 'warning', 'message' => 'Harga satuan produk pengganti (Rp '.number_format($hargaPengganti, 0, ',', '.').') tidak boleh lebih rendah dari harga barang yang diretur (Rp '.number_format($minHargaUnit, 0, ',', '.').').']);

                return;
            }
        }

        $cabangId = session('cabang_id') ?: ($this->selectedTransaksi?->cabang_id ?: 1);
        $gudangId = $this->selectedTransaksi?->gudang_id;
        if (! $gudangId || ! Gudang::where('id', $gudangId)->exists()) {
            $gudangId = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                ?? Gudang::where('cabang_id', $cabangId)->value('id')
                ?? Gudang::value('id');
        }

        $stokTersedia = (int) StokItem::where('produk_id', $produkId)
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->value('jumlah');

        if ($stokTersedia <= 0) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => "Stok produk {$produk->nama} sedang kosong"]);

            return;
        }

        foreach ($this->tukarItems as $idx => $tItem) {
            if ($tItem['produk_id'] === $produkId) {
                if ($this->tukarItems[$idx]['jumlah'] + 1 > $stokTersedia) {
                    $this->dispatch('alert', ['type' => 'warning', 'message' => "Stok hanya tersedia {$stokTersedia} unit"]);

                    return;
                }
                $this->tukarItems[$idx]['jumlah'] += 1;
                $this->tukarItems[$idx]['subtotal'] = $this->tukarItems[$idx]['jumlah'] * $this->tukarItems[$idx]['harga_satuan'];
                $this->tukarSearch = '';

                return;
            }
        }

        $harga = (float) ($produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
        $this->tukarItems[] = [
            'produk_id' => $produk->id,
            'nama' => $produk->nama,
            'stok_tersedia' => $stokTersedia,
            'harga_satuan' => $harga,
            'jumlah' => 1,
            'subtotal' => $harga,
        ];

        $this->tukarSearch = '';
    }

    public function cariTukarDenganBarcode(string $barcode): void
    {
        $barcode = trim($barcode);
        $this->tukarBarcode = '';

        if ($barcode === '') {
            return;
        }

        $produk = Produk::where('is_active', true)->where('barcode', $barcode)->first();
        if (! $produk) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => "Produk dengan barcode {$barcode} tidak ditemukan."]);

            return;
        }

        $this->tambahTukarItem($produk->id);
    }

    public function hapusTukarItem(int $index): void
    {
        unset($this->tukarItems[$index]);
        $this->tukarItems = array_values($this->tukarItems);
    }

    public function getHasilCariTukarProdukProperty()
    {
        $term = trim($this->tukarSearch);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $cabangId = session('cabang_id') ?: ($this->selectedTransaksi?->cabang_id ?: 1);
        $gudangId = $this->selectedTransaksi?->gudang_id;
        if (! $gudangId || ! Gudang::where('id', $gudangId)->exists()) {
            $gudangId = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                ?? Gudang::where('cabang_id', $cabangId)->value('id')
                ?? Gudang::value('id');
        }

        return Produk::where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('nama', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            })
            ->with(['stokItems' => fn ($q) => $q->when($gudangId, fn ($sq) => $sq->where('gudang_id', $gudangId))])
            ->limit(8)
            ->get();
    }

    public function simpanRetur(): void
    {
        $this->resetErrorBag();

        if (! $this->selectedTransaksi) {
            $this->addError('kwitansiInput', 'Wajib memasukkan dan memverifikasi nomor kwitansi / faktur terlebih dahulu.');
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Wajib memasukkan dan memverifikasi nomor kwitansi / faktur terlebih dahulu.']);

            return;
        }

        $this->validate([
            'alasan' => 'required|string|max:500',
            'metodePengembalian' => 'required|in:kas,piutang,saldo',
            'tipePenyelesaian' => 'required|in:refund,tukar_produk',
        ], [
            'alasan.required' => 'Alasan retur wajib diisi.',
            'alasan.max' => 'Alasan retur maksimal 500 karakter.',
            'metodePengembalian.required' => 'Metode pengembalian wajib dipilih.',
            'tipePenyelesaian.required' => 'Bentuk penyelesaian retur wajib dipilih.',
        ]);

        $items = [];
        $totalReturQty = 0;
        $hasSnError = false;

        foreach ($this->itemInputs as $itemId => $input) {
            $qty = (float) ($input['jumlah'] ?? 0);
            if ($qty > 0) {
                $totalReturQty += $qty;
                $itemInfo = $this->transaksiItemData[$itemId] ?? null;
                $sisaMax = $itemInfo['sisa_bisa_diretur'] ?? 0;
                $nama = $itemInfo['produk_nama'] ?? 'Item';

                if ($qty > $sisaMax + 0.001) {
                    $this->addError("itemInputs.{$itemId}.jumlah", "Qty retur {$nama} melebihi sisa yang dapat diretur ({$sisaMax}).");
                    $this->dispatch('alert', ['type' => 'error', 'message' => "Jumlah retur untuk {$nama} melebihi sisa yang dapat diretur ({$sisaMax})."]);

                    return;
                }

                $snList = [];
                if (! empty($input['sn_raw'])) {
                    $snList = preg_split('/[\r\n,;]+/', (string) $input['sn_raw']) ?: [];
                    $snList = array_values(array_filter(array_map('trim', $snList)));
                }

                // Validasi SN jika produk memiliki SN (has_sn = true)
                if (! empty($itemInfo['has_sn']) && empty($snList)) {
                    $this->addError("itemInputs.{$itemId}.sn_raw", "Produk {$nama} memerlukan Serial Number (SN) unit yang diretur.");
                    $hasSnError = true;
                }

                $items[] = [
                    'transaksi_item_id' => (int) $input['transaksi_item_id'],
                    'jumlah' => $qty,
                    'kondisi_barang' => $input['kondisi_barang'] ?? 'rusak',
                    'sn' => $snList,
                ];
            }
        }

        if ($hasSnError) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Lengkapi Nomor Seri (SN) untuk setiap barang yang mewajibkan Serial Number.']);

            return;
        }

        if ($totalReturQty <= 0 || empty($items)) {
            $this->addError('items', 'Belum ada barang yang dipilih untuk diretur. Masukkan kuantiti retur minimal 1 pada salah satu barang.');
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Belum memilih barang untuk diretur (kuantiti masih 0 semua). Masukkan kuantiti retur > 0.']);

            return;
        }

        if ($this->tipePenyelesaian === 'tukar_produk' && empty($this->tukarItems)) {
            $this->addError('tukarItems', 'Pilih minimal 1 produk pengganti untuk opsi Tukar Produk.');
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih minimal satu produk pengganti untuk opsi Tukar Produk.']);

            return;
        }

        try {
            $tukarPrepared = [];
            if ($this->tipePenyelesaian === 'tukar_produk') {
                foreach ($this->tukarItems as $tItem) {
                    $tukarPrepared[] = [
                        'produk_id' => (int) $tItem['produk_id'],
                        'jumlah' => (float) $tItem['jumlah'],
                        'harga_satuan' => (float) $tItem['harga_satuan'],
                    ];
                }
            }

            $retur = app(ReturnPenjualanService::class)->buatRetur(
                $this->selectedTransaksi,
                $items,
                $this->alasan,
                $this->metodePengembalian,
                auth()->id(),
                $this->tipePenyelesaian,
                $tukarPrepared,
                $this->selectedTransaksi->sumber ?? 'pos',
                [],
                $this->catatanQc
            );

            $pesan = $retur->status === 'selesai'
                ? "Retur {$retur->no_return} berhasil diproses — ".($retur->tipe_penyelesaian === 'tukar_produk' ? 'transaksi tukar barang selesai.' : 'pengembalian dana selesai.')
                : "Retur {$retur->no_return} dibuat — menunggu approval supervisor.";

            $this->dispatch('alert', ['type' => 'success', 'message' => $pesan]);
            $this->tutupModalTambah();
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaDetail(int $id): void
    {
        $this->detailReturId = $id;
    }

    public function tutupDetail(): void
    {
        $this->detailReturId = null;
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        $query = ReturnPenjualan::with(['transaksi', 'pelanggan', 'user', 'items.produk', 'tukarTransaksi'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterTipe !== 'all', fn ($q) => $q->where('tipe_penyelesaian', $this->filterTipe))
            ->when(! empty($this->filterTanggalDari), fn ($q) => $q->whereDate('tanggal', '>=', $this->filterTanggalDari))
            ->when(! empty($this->filterTanggalSampai), fn ($q) => $q->whereDate('tanggal', '<=', $this->filterTanggalSampai))
            ->when(! empty($this->search), function ($q) {
                $term = $this->search;
                $q->where(function ($sub) use ($term) {
                    $sub->where('no_return', 'like', "%{$term}%")
                        ->orWhereHas('transaksi', fn ($t) => $t->where('no_transaksi', 'like', "%{$term}%"))
                        ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', 'like', "%{$term}%"));
                });
            });

        $returList = $query->latest('id')->paginate(15);

        // Ringkasan Statistik
        $stats = [
            'total_retur_bulan_ini' => ReturnPenjualan::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->whereMonth('tanggal', now()->month)
                ->whereYear('tanggal', now()->year)
                ->count(),
            'total_nilai_bulan_ini' => ReturnPenjualan::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->where('status', 'selesai')
                ->whereMonth('tanggal', now()->month)
                ->whereYear('tanggal', now()->year)
                ->sum('jumlah'),
            'pending_approval' => ReturnPenjualan::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->where('status', 'draft')
                ->count(),
            'tukar_produk_count' => ReturnPenjualan::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->where('tipe_penyelesaian', 'tukar_produk')
                ->whereMonth('tanggal', now()->month)
                ->whereYear('tanggal', now()->year)
                ->count(),
        ];

        $detailRetur = null;
        if ($this->detailReturId) {
            $detailRetur = ReturnPenjualan::with(['transaksi.items.produk', 'pelanggan', 'user', 'items.produk', 'tukarTransaksi.items.produk', 'cabang'])
                ->find($this->detailReturId);
        }

        return view('modules.pos.livewire.retur-penjualan-index', [
            'returList' => $returList,
            'stats' => $stats,
            'detailRetur' => $detailRetur,
        ])->layout('layouts.backoffice', ['header' => 'Manajemen Retur Konsumen']);
    }
}
