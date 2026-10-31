<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ReturPenjualanIndex extends Component
{
    use PunyaRiwayatAktivitas;
    use WithFileUploads;
    use WithPagination;

    // Filter
    public string $filterStatus = 'all'; // all, draft, disetujui, selesai, ditolak

    public string $filterTipe = 'all'; // all, refund, tukar_produk, campur

    public string $search = '';

    public string $filterTanggalDari = '';

    public string $filterTanggalSampai = '';

    // Modal Form Pengajuan Retur
    public bool $showCreateModal = false;

    public string $kwitansiInput = '';

    public ?Transaksi $selectedTransaksi = null;

    public array $transaksiItemData = [];

    public array $itemInputs = [];

    // Deteksi Cerdas Garansi
    public bool $isGaransiExpired = false;

    public string $garansiKeterangan = '';

    public ?string $garansiBerakhirTanggal = null;

    public int $durasiGaransiHari = 3;

    // Upload Foto dari Kamera per Item
    public array $uploadFotoPerItem = [];

    // Global Fallback Form Properties
    public string $tipePenyelesaian = 'refund'; // refund | tukar_produk

    public string $metodePengembalian = 'kas'; // kas | piutang

    public string $alasan = '';

    public string $catatanQc = '';

    // State Tukar Produk
    public string $tukarSearch = '';

    public string $tukarBarcode = '';

    public array $tukarItems = [];

    public ?int $activeTukarItemId = null; // Item yang sedang dipilih produk penggantinya

    // Alur Pembayaran Selisih Tukar Barang
    public string $metodeBayarSelisih = 'tunai'; // tunai | transfer | qris

    public int|float|string|null $jumlahBayarSelisih = 0;

    // Modal Struk Khusus Retur
    public bool $showReceiptModal = false;

    public array $receiptData = [];

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

    public function updatedTipePenyelesaian(string $val): void
    {
        foreach ($this->itemInputs as $k => $item) {
            $this->itemInputs[$k]['tipe_penyelesaian'] = $val;
        }
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
        $this->isGaransiExpired = false;
        $this->garansiKeterangan = '';
        $this->garansiBerakhirTanggal = null;
        $this->durasiGaransiHari = 3;
        $this->uploadFotoPerItem = [];
        $this->tipePenyelesaian = 'refund';
        $this->metodePengembalian = 'kas';
        $this->alasan = '';
        $this->catatanQc = '';
        $this->tukarSearch = '';
        $this->tukarBarcode = '';
        $this->tukarItems = [];
        $this->activeTukarItemId = null;
        $this->metodeBayarSelisih = 'tunai';
        $this->jumlahBayarSelisih = 0;
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

        // [ANTI-FRAUD] Deteksi Cerdas Masa Garansi
        $tglTransaksi = $transaksi->created_at ?: now();
        $durasiGaransi = (int) ($transaksi->durasi_garansi_hari ?? 3);
        if ($durasiGaransi <= 0) {
            $durasiGaransi = 3;
        }
        $garansiBerakhir = $transaksi->garansi_berakhir_pada
            ? Carbon::parse($transaksi->garansi_berakhir_pada)->endOfDay()
            : $tglTransaksi->copy()->addDays($durasiGaransi)->endOfDay();

        $this->isGaransiExpired = now()->greaterThan($garansiBerakhir);
        $this->garansiBerakhirTanggal = $garansiBerakhir->format('d/m/Y');
        $this->durasiGaransiHari = $durasiGaransi;

        if ($this->isGaransiExpired) {
            $selisihHari = max(1, (int) now()->diffInDays($garansiBerakhir));
            $this->garansiKeterangan = "Masa garansi telah habis sejak {$this->garansiBerakhirTanggal} ({$selisihHari} hari lalu). Garansi resmi berlaku {$durasiGaransi} hari sejak transaksi tanggal {$tglTransaksi->format('d/m/Y')}. Retur produk otomatis ditolak.";
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "RETUR DITOLAK: {$this->garansiKeterangan}",
            ]);
        } else {
            $sisaHari = max(0, (int) now()->diffInDays($garansiBerakhir, false));
            $this->garansiKeterangan = "Garansi Aktif hingga {$this->garansiBerakhirTanggal} (Sisa {$sisaHari} hari).";
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
                'kategori_id' => $item->produk?->kategori_id,
                'kategori' => $item->produk?->kategori,
                'brand_kompatibel' => $item->produk?->brand_kompatibel,
                'model_kompatibel' => $item->produk?->model_kompatibel,
                'has_sn' => (bool) ($item->produk?->has_sn ?? false),
                'qty_beli' => (float) $item->jumlah,
                'sudah_diretur' => $sudahDiretur,
                'sisa_bisa_diretur' => $sisaBisaDiretur,
                'harga_final' => (float) ($item->harga_final ?? $item->harga_satuan ?? 0),
            ];

            $this->itemInputs[$item->id] = [
                'transaksi_item_id' => $item->id,
                'jumlah' => 0,
                'kondisi_barang' => 'rusak', // rusak | bagus
                'alasan' => '',
                'tipe_penyelesaian' => $this->tipePenyelesaian, // refund | tukar_produk
                'metode_pengembalian' => $this->metodePengembalian,  // kas | piutang
                'sn_raw' => '',
                'foto_bukti' => [],
                'tukar_items' => [],
                'tukar_search' => '',
            ];
        }

        if (! $this->isGaransiExpired) {
            $this->dispatch('alert', ['type' => 'success', 'message' => "Faktur {$transaksi->no_transaksi} diverifikasi. Garansi aktif s.d. {$this->garansiBerakhirTanggal}."]);
        }
    }

    /**
     * Upload foto kerusakan dari kamera / file per barang.
     */
    public function updatedUploadFotoPerItem($file, $itemId): void
    {
        $this->validate([
            "uploadFotoPerItem.{$itemId}" => 'image|max:10240',
        ], [
            "uploadFotoPerItem.{$itemId}.image" => 'File harus berupa gambar/foto.',
            "uploadFotoPerItem.{$itemId}.max" => 'Ukuran foto bukti maksimal 10MB.',
        ]);

        if (! $file) {
            return;
        }

        $path = $file->store('retur-bukti', 'public');
        if (! isset($this->itemInputs[$itemId]['foto_bukti'])) {
            $this->itemInputs[$itemId]['foto_bukti'] = [];
        }
        $this->itemInputs[$itemId]['foto_bukti'][] = $path;
        $this->uploadFotoPerItem[$itemId] = null;

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Foto bukti kerusakan berhasil diunggah.']);
    }

    /**
     * Hook real-time pembatasan Qty Retur agar tidak melebihi sisa pembelian
     */
    public function updatedItemInputs($value, $key): void
    {
        if (str_ends_with($key, '.jumlah')) {
            $parts = explode('.', $key);
            $itemId = (int) $parts[0];
            $itemInfo = $this->transaksiItemData[$itemId] ?? null;
            if ($itemInfo) {
                $max = (float) ($itemInfo['sisa_bisa_diretur'] ?? 0);
                $val = (float) $value;
                if ($val > $max) {
                    $this->itemInputs[$itemId]['jumlah'] = $max;
                    $this->addError("itemInputs.{$itemId}.jumlah", "Qty maksimal yang dapat diretur adalah {$max} unit.");
                    $this->dispatch('alert', ['type' => 'warning', 'message' => "Qty retur {$itemInfo['produk_nama']} dibatasi maksimal {$max} unit sesuai riwayat pembelian."]);
                } elseif ($val < 0) {
                    $this->itemInputs[$itemId]['jumlah'] = 0;
                } else {
                    $this->resetErrorBag("itemInputs.{$itemId}.jumlah");
                }
            }
        }
    }

    public function hapusFotoBukti(int $itemId, int $idx): void
    {
        if (isset($this->itemInputs[$itemId]['foto_bukti'][$idx])) {
            unset($this->itemInputs[$itemId]['foto_bukti'][$idx]);
            $this->itemInputs[$itemId]['foto_bukti'] = array_values($this->itemInputs[$itemId]['foto_bukti']);
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Foto bukti dihapus.']);
        }
    }

    /**
     * Buka / tutup panel picker produk pengganti untuk item tertentu.
     */
    public function bukaPilihTukar(int $itemId): void
    {
        $this->activeTukarItemId = $this->activeTukarItemId === $itemId ? null : $itemId;
    }

    /**
     * Cari rekomendasi produk pengganti terurut prioritas (Sama -> Sejenis/Kompatibel -> Global)
     */
    public function getRekomendasiTukar(int $itemId): array
    {
        $target = $this->transaksiItemData[$itemId] ?? null;
        if (! $target) {
            return ['prioritas_1' => collect(), 'prioritas_2' => collect(), 'prioritas_3' => collect()];
        }

        $hargaKonsumen = (float) $target['harga_final'];
        $targetProdukId = (int) $target['produk_id'];
        $targetKategoriId = $target['kategori_id'] ? (int) $target['kategori_id'] : null;
        $targetKategoriString = $target['kategori'] ? trim(strtolower($target['kategori'])) : null;
        $targetBrand = $target['brand_kompatibel'] ? trim(strtolower($target['brand_kompatibel'])) : null;
        $targetModel = $target['model_kompatibel'] ? trim(strtolower($target['model_kompatibel'])) : null;

        $cabangId = session('cabang_id') ?: ($this->selectedTransaksi?->cabang_id ?: 1);
        $gudangId = $this->selectedTransaksi?->gudang_id;
        if (! $gudangId || ! Gudang::where('id', $gudangId)->exists()) {
            $gudangId = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                ?? Gudang::where('cabang_id', $cabangId)->value('id')
                ?? Gudang::value('id');
        }

        $term = trim($this->itemInputs[$itemId]['tukar_search'] ?? '');

        $query = Produk::where('is_active', true)
            ->with(['stokItems' => fn ($q) => $q->when($gudangId, fn ($sq) => $sq->where('gudang_id', $gudangId))]);

        if (mb_strlen($term) >= 2) {
            $query->where(function ($q) use ($term) {
                $q->where('nama', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('kode', 'like', "%{$term}%");
            });
        }

        $allProduks = $query->limit(30)->get();

        $p1 = collect(); // Prioritas 1: Produk yang Sama Persis
        $p2 = collect(); // Prioritas 2: Kategori Sejenis atau Kompatibilitas Sama
        $p3 = collect(); // Prioritas 3: Global / Lainnya

        foreach ($allProduks as $p) {
            $hargaJual = (float) ($p->harga_jual_retail ?? $p->harga_jual ?? 0);
            $stokTersedia = (int) ($p->stokItems->first()?->jumlah ?? 0);
            $isLebihMurah = $hargaJual < ($hargaKonsumen - 0.01);
            $selisih = round($hargaJual - $hargaKonsumen, 2);

            $p->stok_tersedia_val = $stokTersedia;
            $p->harga_jual_val = $hargaJual;
            $p->is_lebih_murah = $isLebihMurah;
            $p->selisih_nominal = $selisih;
            $p->bisa_dipilih = ! $isLebihMurah && $stokTersedia > 0;

            if ($p->id === $targetProdukId) {
                $p1->push($p);
            } elseif (
                ($targetKategoriId && (int) $p->kategori_id === $targetKategoriId) ||
                ($targetKategoriString && trim(strtolower($p->kategori ?? '')) === $targetKategoriString) ||
                ($targetBrand && trim(strtolower($p->brand_kompatibel ?? '')) === $targetBrand) ||
                ($targetModel && trim(strtolower($p->model_kompatibel ?? '')) === $targetModel)
            ) {
                $p2->push($p);
            } else {
                $p3->push($p);
            }
        }

        return [
            'prioritas_1' => $p1,
            'prioritas_2' => $p2,
            'prioritas_3' => $p3,
        ];
    }

    /**
     * Tambah produk pengganti (Exchange) untuk item tertentu.
     */
    public function tambahTukarItem(int $produkId, ?int $targetItemId = null): void
    {
        $targetItemId = $targetItemId ?: $this->activeTukarItemId;
        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
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

        // Tentukan batas harga minimum dari item yang diretur
        $minHargaUnit = 0;
        if ($targetItemId && isset($this->transaksiItemData[$targetItemId])) {
            $minHargaUnit = (float) $this->transaksiItemData[$targetItemId]['harga_final'];
        } else {
            $selectedItems = array_filter($this->transaksiItemData, fn ($i) => ($this->itemInputs[$i['item_id']]['jumlah'] ?? 0) > 0);
            if (! empty($selectedItems)) {
                $minHargaUnit = (float) max(array_column($selectedItems, 'harga_final'));
            }
        }

        $hargaPengganti = (float) ($produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
        if ($hargaPengganti < $minHargaUnit - 0.01) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Produk [{$produk->nama}] (Rp ".number_format($hargaPengganti, 0, ',', '.').') TIDAK BISA DIPILIH karena lebih murah dari harga beli konsumen (Rp '.number_format($minHargaUnit, 0, ',', '.').').',
            ]);

            return;
        }

        // Jika targetItemId dispesifikasikan, simpan ke itemInputs target
        if ($targetItemId && isset($this->itemInputs[$targetItemId])) {
            $currentList = $this->itemInputs[$targetItemId]['tukar_items'] ?? [];
            foreach ($currentList as $idx => $tItem) {
                if ($tItem['produk_id'] === $produkId) {
                    if ($currentList[$idx]['jumlah'] + 1 > $stokTersedia) {
                        $this->dispatch('alert', ['type' => 'warning', 'message' => "Stok hanya tersedia {$stokTersedia} unit"]);

                        return;
                    }
                    $this->itemInputs[$targetItemId]['tukar_items'][$idx]['jumlah'] += 1;
                    $this->itemInputs[$targetItemId]['tukar_items'][$idx]['subtotal'] = $this->itemInputs[$targetItemId]['tukar_items'][$idx]['jumlah'] * $hargaPengganti;
                    $this->itemInputs[$targetItemId]['tukar_search'] = '';
                    $this->dispatch('alert', ['type' => 'success', 'message' => "Qty produk pengganti {$produk->nama} ditambah."]);

                    return;
                }
            }

            $this->itemInputs[$targetItemId]['tukar_items'][] = [
                'produk_id' => $produk->id,
                'nama' => $produk->nama,
                'stok_tersedia' => $stokTersedia,
                'harga_satuan' => $hargaPengganti,
                'jumlah' => 1,
                'subtotal' => $hargaPengganti,
            ];
            $this->itemInputs[$targetItemId]['tukar_search'] = '';
            $this->dispatch('alert', ['type' => 'success', 'message' => "Produk pengganti [{$produk->nama}] berhasil dipilih."]);

            return;
        }

        // Fallback backward compatibility ke global tukarItems
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

        $this->tukarItems[] = [
            'produk_id' => $produk->id,
            'nama' => $produk->nama,
            'stok_tersedia' => $stokTersedia,
            'harga_satuan' => $hargaPengganti,
            'jumlah' => 1,
            'subtotal' => $hargaPengganti,
        ];
        $this->tukarSearch = '';
    }

    public function hapusTukarItem(int $index, ?int $targetItemId = null): void
    {
        $targetItemId = $targetItemId ?: $this->activeTukarItemId;
        if ($targetItemId && isset($this->itemInputs[$targetItemId]['tukar_items'][$index])) {
            unset($this->itemInputs[$targetItemId]['tukar_items'][$index]);
            $this->itemInputs[$targetItemId]['tukar_items'] = array_values($this->itemInputs[$targetItemId]['tukar_items']);
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Produk pengganti dihapus.']);

            return;
        }

        unset($this->tukarItems[$index]);
        $this->tukarItems = array_values($this->tukarItems);
    }

    public function cariTukarDenganBarcode(string $barcode, ?int $targetItemId = null): void
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

        $this->tambahTukarItem($produk->id, $targetItemId);
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

    /**
     * Hitung total nilai retur dan selisih secara realtime.
     */
    public function getRingkasanFinansialProperty(): array
    {
        $totalRetur = 0;
        $totalReturTukar = 0;
        $totalPengganti = 0;
        $hasTukar = false;

        foreach ($this->itemInputs as $itemId => $input) {
            $qty = (float) ($input['jumlah'] ?? 0);
            if ($qty > 0 && isset($this->transaksiItemData[$itemId])) {
                $subtotal = $qty * (float) $this->transaksiItemData[$itemId]['harga_final'];
                $totalRetur += $subtotal;

                $tipe = $input['tipe_penyelesaian'] ?? $this->tipePenyelesaian;
                if ($tipe === 'tukar_produk') {
                    $hasTukar = true;
                    $totalReturTukar += $subtotal;

                    foreach ($input['tukar_items'] ?? [] as $tIt) {
                        $hargaIt = (float) ($tIt['harga_satuan'] ?? $tIt['harga'] ?? 0);
                        $totalPengganti += ((float) $tIt['jumlah'] * $hargaIt);
                    }
                }
            }
        }

        // Tambah juga dari global tukarItems jika ada
        if (! empty($this->tukarItems)) {
            $hasTukar = true;
            foreach ($this->tukarItems as $tIt) {
                $hargaIt = (float) ($tIt['harga_satuan'] ?? $tIt['harga'] ?? 0);
                $totalPengganti += ((float) $tIt['jumlah'] * $hargaIt);
            }
        }

        $selisih = max(0, round($totalPengganti - $totalReturTukar, 2));
        $kembalian = max(0, round((float) $this->jumlahBayarSelisih - $selisih, 2));

        return [
            'total_retur' => $totalRetur,
            'total_retur_tukar' => $totalReturTukar,
            'total_pengganti' => $totalPengganti,
            'selisih' => $selisih,
            'kembalian' => $kembalian,
            'has_tukar' => $hasTukar,
        ];
    }

    public function simpanRetur(): void
    {
        $this->resetErrorBag();

        // Otomatis himpun alasan retur dari per-item agar kasir tidak perlu mengetik dua kali
        $alasanItems = collect($this->itemInputs)
            ->filter(fn ($it) => ((float) ($it['jumlah'] ?? 0)) > 0)
            ->pluck('alasan')
            ->filter()
            ->all();

        if (empty($this->alasan) && ! empty($alasanItems)) {
            $this->alasan = implode('; ', $alasanItems);
        }

        $this->validate([
            'alasan' => 'required',
        ], [
            'alasan.required' => 'Alasan retur / kerusakan pada barang wajib diisi.',
        ]);

        if (! $this->selectedTransaksi) {
            $this->addError('kwitansiInput', 'Wajib memasukkan dan memverifikasi nomor kwitansi / faktur terlebih dahulu.');
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Wajib memasukkan dan memverifikasi nomor kwitansi / faktur terlebih dahulu.']);

            return;
        }

        // Tolak otomatis jika garansi habis
        if ($this->isGaransiExpired) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "RETUR DITOLAK: {$this->garansiKeterangan}",
            ]);

            return;
        }

        $items = [];
        $totalReturQty = 0;
        $hasSnError = false;
        $hasTukarError = false;
        $allTukarPrepared = [];

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

                if (! empty($itemInfo['has_sn']) && empty($snList)) {
                    $this->addError("itemInputs.{$itemId}.sn_raw", "Produk {$nama} memerlukan Serial Number (SN) unit yang diretur.");
                    $hasSnError = true;
                }

                $itemTipe = $input['tipe_penyelesaian'] ?? $this->tipePenyelesaian;
                $itemTukar = $input['tukar_items'] ?? [];

                if ($itemTipe === 'tukar_produk' && empty($itemTukar) && empty($this->tukarItems)) {
                    $this->addError("itemInputs.{$itemId}.tukar_items", "Pilih minimal 1 produk pengganti untuk {$nama}.");
                    $this->addError('tukarItems', "Pilih minimal 1 produk pengganti untuk {$nama}.");
                    $hasTukarError = true;
                }

                $itemTukarPrepared = [];
                foreach ($itemTukar as $t) {
                    $hargaT = (float) ($t['harga_satuan'] ?? $t['harga'] ?? 0);
                    $itemTukarPrepared[] = [
                        'produk_id' => (int) $t['produk_id'],
                        'jumlah' => (float) $t['jumlah'],
                        'harga_satuan' => $hargaT,
                    ];
                    $allTukarPrepared[] = [
                        'produk_id' => (int) $t['produk_id'],
                        'jumlah' => (float) $t['jumlah'],
                        'harga_satuan' => $hargaT,
                    ];
                }

                $items[] = [
                    'transaksi_item_id' => (int) $input['transaksi_item_id'],
                    'jumlah' => $qty,
                    'kondisi_barang' => $input['kondisi_barang'] ?? 'rusak',
                    'alasan' => ! empty($input['alasan']) ? $input['alasan'] : ($this->alasan ?: 'Retur konsumen'),
                    'tipe_penyelesaian' => $itemTipe,
                    'metode_pengembalian' => $input['metode_pengembalian'] ?? $this->metodePengembalian,
                    'sn' => $snList,
                    'foto_bukti' => $input['foto_bukti'] ?? [],
                    'tukar_items' => $itemTukarPrepared,
                ];
            }
        }

        // Masukkan global tukarItems jika ada
        foreach ($this->tukarItems as $t) {
            $allTukarPrepared[] = [
                'produk_id' => (int) $t['produk_id'],
                'jumlah' => (float) $t['jumlah'],
                'harga_satuan' => (float) ($t['harga_satuan'] ?? $t['harga'] ?? 0),
            ];
        }

        if ($this->tipePenyelesaian === 'tukar_produk' && empty($allTukarPrepared)) {
            $this->addError('tukarItems', 'Pilih minimal satu produk pengganti untuk transaksi tukar barang.');
            $hasTukarError = true;
        }

        if ($hasSnError) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Lengkapi Nomor Seri (SN) untuk setiap barang yang mewajibkan Serial Number.']);

            return;
        }

        if ($hasTukarError) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk pengganti untuk setiap item berstatus Tukar Barang.']);

            return;
        }

        if ($totalReturQty <= 0 || empty($items)) {
            $this->addError('items', 'Belum ada barang yang dipilih untuk diretur. Masukkan kuantiti retur minimal 1 pada salah satu barang.');
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Belum memilih barang untuk diretur (kuantiti masih 0 semua). Masukkan kuantiti retur > 0.']);

            return;
        }

        $ringkasan = $this->ringkasanFinansial;
        if ($ringkasan['selisih'] > 0 && (float) $this->jumlahBayarSelisih < $ringkasan['selisih'] - 0.01) {
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => 'Jumlah bayar selisih (Rp '.number_format((float) $this->jumlahBayarSelisih, 0, ',', '.').') kurang dari selisih wajib dibayar (Rp '.number_format($ringkasan['selisih'], 0, ',', '.').').',
            ]);

            return;
        }

        try {
            $retur = app(ReturnPenjualanService::class)->buatRetur(
                $this->selectedTransaksi,
                $items,
                $this->alasan ?: 'Retur Konsumen',
                $this->metodePengembalian,
                auth()->id(),
                $this->tipePenyelesaian,
                $allTukarPrepared,
                $this->selectedTransaksi->sumber ?? 'pos',
                [],
                $this->catatanQc,
                $this->metodeBayarSelisih,
                (float) $this->jumlahBayarSelisih,
                (float) $ringkasan['kembalian']
            );

            $pesan = $retur->status === 'selesai'
                ? "Retur {$retur->no_return} berhasil diproses — transaksi tercatat resmi."
                : "Retur {$retur->no_return} dibuat — menunggu approval supervisor.";

            $this->dispatch('alert', ['type' => 'success', 'message' => $pesan]);
            $this->tutupModalTambah();

            // Buka Modal Struk Khusus Retur
            $this->siapkanStrukRetur($retur);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Siapkan payload struk khusus transaksi retur (format standar global).
     */
    public function siapkanStrukRetur(ReturnPenjualan $retur): void
    {
        $retur->load(['transaksi.pelanggan', 'user', 'items.produk', 'tukarTransaksi.items.produk', 'cabang']);
        $strukConfig = app(KonfigurasiService::class)->getStrukConfig();

        $isTukar = in_array($retur->tipe_penyelesaian, ['tukar_produk', 'campur'], true) || $retur->tukarTransaksi;

        $receiptItems = [];
        foreach ($retur->items as $it) {
            $receiptItems[] = [
                'tipe' => 'retur',
                'nama' => '[RETUR] '.($it->produk?->nama ?? 'Produk'),
                'qty' => (float) $it->jumlah,
                'harga' => (float) $it->harga_satuan,
                'subtotal' => (float) $it->subtotal,
                'catatan' => 'Kondisi: '.strtoupper($it->kondisi_barang).' · '.($it->alasan ?: 'Retur Konsumen'),
            ];
        }

        if ($retur->tukarTransaksi) {
            foreach ($retur->tukarTransaksi->items as $tIt) {
                $receiptItems[] = [
                    'tipe' => 'part',
                    'nama' => '[PENGGANTI] '.($tIt->produk?->nama ?? 'Produk Pengganti'),
                    'qty' => (float) $tIt->jumlah,
                    'harga' => (float) $tIt->harga_final,
                    'subtotal' => (float) $tIt->subtotal,
                    'catatan' => 'Barang Tukar Baru (Garansi Toko)',
                ];
            }
        }

        $totalRetur = (float) $retur->jumlah;
        $totalPengganti = (float) ($retur->tukarTransaksi?->total_akhir ?? 0);
        $selisih = (float) $retur->selisih_nominal;

        $this->receiptData = [
            'nama_toko' => $strukConfig['nama_toko'] ?? config('app.name', 'BREMSPACE'),
            'slogan' => $strukConfig['slogan'] ?? null,
            'alamat' => $strukConfig['alamat'] ?? null,
            'telepon' => $strukConfig['telepon'] ?? null,
            'cabang' => $retur->cabang?->nama ?? 'Cabang Utama',
            'waktu' => now()->format('d/m/Y H:i'),
            'no_transaksi' => $retur->no_return,
            'no_transaksi_asal' => $retur->transaksi?->no_transaksi ?? '-',
            'kasir' => $retur->user?->name ?? auth()->user()?->name ?? 'Kasir',
            'pelanggan' => $retur->pelanggan?->nama ?? 'Umum',
            'label_struk' => $isTukar ? '*** STRUK RETUR & TUKAR BARANG ***' : '*** STRUK RETUR PENGEMBALIAN DANA ***',
            'is_retur' => true,
            'tipe_retur' => $retur->tipe_penyelesaian,
            'items' => $receiptItems,
            'total_retur' => $totalRetur,
            'total_pengganti' => $totalPengganti,
            'selisih' => $selisih,
            'subtotal' => $selisih > 0 ? $selisih : $totalRetur,
            'total' => $selisih > 0 ? $selisih : $totalRetur,
            'metode' => strtoupper($retur->metode_bayar_selisih ?? $retur->metode_pengembalian ?? 'TUNAI'),
            'bayar' => (float) ($retur->jumlah_bayar_selisih > 0 ? $retur->jumlah_bayar_selisih : ($selisih > 0 ? $selisih : $totalRetur)),
            'kembali' => (float) $retur->kembalian_selisih,
            'qr_caption' => 'Scan QR ini untuk verifikasi retur & garansi',
            'kebijakan_retur' => $strukConfig['kebijakan_retur'] ?? 'Simpan struk ini sebagai bukti retur dan klaim garansi yang sah.',
        ];

        $this->showReceiptModal = true;
    }

    public function cetakStrukRetur(int $returId): void
    {
        $retur = ReturnPenjualan::find($returId);
        if ($retur) {
            $this->siapkanStrukRetur($retur);
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
                ->whereIn('tipe_penyelesaian', ['tukar_produk', 'campur'])
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
            'ringkasanFinansial' => $this->ringkasanFinansial,
        ])->layout('layouts.backoffice', ['header' => 'Manajemen Retur Konsumen']);
    }
}
