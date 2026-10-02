<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use App\Modules\Wms\Services\ProductImageService;
use App\Modules\Wms\Services\ProdukService;
use App\Traits\ParsesNominal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * [F1-8 / S-01] Tab "Master Produk" — dipecah dari WmsDashboard (paritas perilaku).
 */
class ProdukTab extends Component
{
    use ParsesNominal;
    use PunyaRiwayatAktivitas;
    use WithFileUploads;
    use WithPagination;

    // Produk tab filters
    public string $produkSearch = '';

    public ?int $filterKategoriId = null;

    public ?int $filterBrandId = null;

    // Foto Uploads (Tambah Produk)
    public $fotoUploads = [];

    // Stok tab filter default (dipakai modal tambah stok) — sama dgn mount shell lama
    public ?int $filterGudangId = null;

    // New Produk Modal (Tambah Produk — sinkron Akunting)
    public bool $showProdukModal = false;

    public array $produkForm = [
        'nama' => '', 'barcode' => '', 'kategori' => '', 'kategori_id' => null, 'brand_kompatibel' => '', 'model_kompatibel' => '',
        'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
        'sku' => '', 'gudang_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
        'deskripsi' => '',
        // [T-44]
        'brand_id' => null, 'kualitas_id' => null, 'satuan_kode' => 'pcs',
        'tipe_hp_ids' => [], 'produk_kompatibel_ids' => [],
        'harga_tier' => [
            'retail' => ['nominal_tetap' => 0, 'persen_diskon' => null],
            'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
            'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
        ],
        'harga_tier_tier_id' => null,
        // [HARGA FLEKSIBEL]
        'harga_fleksibel' => false,
        // [F2-3] Serial number tracking
        'sn' => false,
        // [PROCUREMENT: ABC, ROP, Min-Max, JIT]
        'abc_class' => 'B',
        'reorder_point' => null,
        'min_stock' => null,
        'max_stock' => null,
        'is_ondemand' => false,
    ];

    // Edit Produk Modal
    public bool $showEditProdukModal = false;

    public ?int $editProdukId = null;

    public array $editProdukForm = [];

    public array $editFotoExisting = [];

    public $editFotoUploads = [];

    // Quick Add Modal (Kategori, Brand, Kualitas, Satuan, Kondisi, Tipe HP)
    public bool $showQuickAddModal = false;

    public string $quickAddTipe = 'kategori';

    public array $quickAddForm = [
        'nama' => '',
        'kode' => '',
        'parent_id' => null,
        'keterangan' => '',
        'merk' => '',
        'model' => '',
    ];

    // Tambah Stok Modal (pembelian — sinkron Akunting)
    public bool $showTambahStokModal = false;

    public ?int $stokProdukId = null;

    // [F2-3] Flag sn produk target tambah stok — kontrol UI input SN di modal
    public bool $stokProdukSn = false;

    public array $tambahStokForm = [
        'gudang_id' => null, 'rak_id' => null, 'qty' => 1, 'harga_beli' => 0, 'keterangan' => 'Pembelian dari supplier',
        // [F2-3]
        'sn' => '',
    ];

    // [T-43] Import master produk Excel
    public bool $showImportModal = false;

    public $importFile = null;

    public string $importStep = 'upload'; // upload → preview → selesai

    public string $importFormat = 'standar'; // 'standar' | 'sid_retail'

    public ?int $gudangTokoId = null;

    public ?int $gudangPusatId = null;

    public array $importPreview = [];

    public string $importFilePath = '';

    public int $importLogId = 0;

    public ?array $activeImportLog = null;

    public function mount()
    {
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->filterGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }
    }

    /**
     * [T-40] Guard role: super-admin atau owner.
     */
    protected function isSuperAdmin(): bool
    {
        return isSuperAdminOrOwner();
    }

    // Quick action header "+ Tambah Produk" (dari shell WmsDashboard via $dispatch)
    #[On('wms-produk-modal')]
    public function openProdukModal()
    {
        $this->produkForm = [
            'nama' => '', 'barcode' => '', 'kategori' => '', 'kategori_id' => null, 'brand_kompatibel' => '', 'model_kompatibel' => '',
            'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
            'sku' => '', 'gudang_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
            'deskripsi' => '',
            // [T-44]
            'brand_id' => null, 'kualitas_id' => null, 'satuan_kode' => 'pcs',
            'tipe_hp_ids' => [], 'produk_kompatibel_ids' => [], 'harga_tier' => [
                'retail' => ['nominal_tetap' => 0, 'persen_diskon' => null],
                'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
                'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
            ],
            'harga_tier_tier_id' => null,
            // [HARGA FLEKSIBEL]
            'harga_fleksibel' => false,
            // [F2-3]
            'sn' => false,
            // [PROCUREMENT: ABC, ROP, Min-Max, JIT]
            'abc_class' => 'B',
            'reorder_point' => null,
            'min_stock' => null,
            'max_stock' => null,
            'is_ondemand' => false,
        ];
        $this->fotoUploads = [];
        $this->showProdukModal = true;
    }

    public function produkHargaJualBerubah()
    {
        $harga = $this->parseNominal($this->produkForm['harga_jual_retail'] ?? 0);
        // Isi otomatis harga tier retail bila belum di-set manual
        if (! $this->produkForm['harga_tier']['retail']['nominal_tetap']
            && $harga > 0) {
            $this->produkForm['harga_tier']['retail']['nominal_tetap'] = $harga;
        }
    }

    public function updatedProdukFormHargaJualRetail($value): void
    {
        $this->produkForm['harga_tier']['retail']['nominal_tetap'] = $this->parseNominal($value);
    }

    public function updatedEditProdukFormHargaJualRetail($value): void
    {
        $this->editProdukForm['harga_tier']['retail']['nominal_tetap'] = $this->parseNominal($value);
    }

    public function simpanProduk()
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin menambah produk.')) {
            return;
        }

        $this->produkForm['harga_beli'] = $this->parseNominal($this->produkForm['harga_beli'] ?? 0);
        $this->produkForm['harga_jual_retail'] = $this->parseNominal($this->produkForm['harga_jual_retail'] ?? 0);
        if (isset($this->produkForm['harga_tier'])) {
            foreach ($this->produkForm['harga_tier'] as $k => $tier) {
                if (isset($tier['nominal_tetap']) && $tier['nominal_tetap'] !== '' && $tier['nominal_tetap'] !== null) {
                    $this->produkForm['harga_tier'][$k]['nominal_tetap'] = $this->parseNominal($tier['nominal_tetap']);
                }
            }
        }

        $this->validate([
            'produkForm.nama' => 'required|string|max:255',
            'produkForm.barcode' => 'nullable|string|max:50|unique:produk,barcode',
            'produkForm.satuan_kode' => 'required|exists:satuan_unit,kode',
            'produkForm.harga_beli' => 'required|numeric|min:0',
            'produkForm.harga_jual_retail' => 'required|numeric|min:0',
            'fotoUploads.*' => 'nullable|image|max:10240',
            // [T-44] minimal 1 harga tier (retail wajib isi)
            'produkForm.harga_tier.retail.nominal_tetap' => 'nullable|numeric|min:0',
            // [HARGA FLEKSIBEL]
            'produkForm.harga_fleksibel' => 'boolean',
            // [F2-3]
            'produkForm.sn' => 'boolean',
        ]);

        // [T-44] minimal 1 harga tier: retail (fallback ke harga_jual) atau salah satu tipe lain
        $adaHargaTier = collect($this->produkForm['harga_tier'])
            ->contains(fn ($t) => ($t['nominal_tetap'] ?? null) !== null || ($t['persen_diskon'] ?? null) !== null);
        if (! $adaHargaTier) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Minimal 1 harga tier wajib diisi (retail / reseller / agen)']);

            return;
        }

        // [T-40] Stok awal (pembelian manual) hanya boleh diinput super-admin —
        // stok masuk wajib via PO Supplier utk role lain.
        if (! $this->isSuperAdmin() && ((int) $this->produkForm['stok_awal'] > 0 || $this->produkForm['gudang_id'])) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Stok masuk hanya via PO Supplier — stok awal hanya boleh diatur super-admin']);

            return;
        }

        // [HARGA FLEKSIBEL] Guard: hanya superadmin boleh set harga_fleksibel = true
        if ((bool) $this->produkForm['harga_fleksibel'] && ! auth()->user()?->hasPermissionTo('atur-harga-fleksibel')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya superadmin yang boleh mengaktifkan harga fleksibel']);

            return;
        }

        // [F2-3] Produk SN: stok awal tanpa input SN tidak diizinkan — stok masuk
        // wajib lewat modal "+ Stok" (form-nya menyediakan input SN bila sn=true).
        if ((bool) $this->produkForm['sn'] && (int) $this->produkForm['stok_awal'] > 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk SN — kosongkan stok awal, lalu tambah stok via "+ Stok" (wajib isi nomor seri)']);

            return;
        }

        // Proses foto terkompresi WebP
        $fotoList = [];
        if (! empty($this->fotoUploads)) {
            $imgService = app(ProductImageService::class);
            foreach ($this->fotoUploads as $idx => $file) {
                $saved = $imgService->prosesDanSimpan($file);
                $saved['is_primary'] = ($idx === 0);
                $fotoList[] = $saved;
            }
        }

        // Pastikan harga tier retail selalu sinkron dengan harga jual retail
        $this->produkForm['harga_tier']['retail']['nominal_tetap'] = (float) $this->produkForm['harga_jual_retail'];

        // Pastikan brand & model kompatibel sinkron dari Tipe HP bila ada
        if (! empty($this->produkForm['tipe_hp_ids']) && (empty($this->produkForm['brand_kompatibel']) || empty($this->produkForm['model_kompatibel']))) {
            $firstT = TipeHp::find($this->produkForm['tipe_hp_ids'][0]);
            if ($firstT) {
                $this->produkForm['brand_kompatibel'] = $this->produkForm['brand_kompatibel'] ?: $firstT->merk;
                $this->produkForm['model_kompatibel'] = $this->produkForm['model_kompatibel'] ?: $firstT->model;
            }
        }

        $minStokVal = ! empty($this->produkForm['min_stock']) ? (int) $this->produkForm['min_stock'] : (int) $this->produkForm['stok_minimum'];

        try {
            app(ProdukService::class)->buatProduk(
                nama: $this->produkForm['nama'],
                kategori: $this->produkForm['kategori'] ?: 'Umum',
                brand: $this->produkForm['brand_kompatibel'] ?: null,
                model: $this->produkForm['model_kompatibel'] ?: null,
                kondisi: $this->produkForm['kondisi'],
                hargaBeli: (float) $this->produkForm['harga_beli'],
                hargaJual: (float) $this->produkForm['harga_jual_retail'],
                sku: $this->produkForm['sku'] ?: null,
                gudangId: $this->produkForm['gudang_id'] ?: null,
                stokAwal: (int) $this->produkForm['stok_awal'],
                stokMinimum: $minStokVal,
                userId: auth()->id(),
                brandId: $this->produkForm['brand_id'] ?: null,
                kualitasId: $this->produkForm['kualitas_id'] ?: null,
                satuanKode: $this->produkForm['satuan_kode'],
                tipeHpIds: $this->produkForm['tipe_hp_ids'] ?: [],
                hargaTier: $this->produkForm['harga_tier'],
                hargaFleksibel: (bool) $this->produkForm['harga_fleksibel'],
                sn: (bool) $this->produkForm['sn'], // [F2-3]
                kategoriId: $this->produkForm['kategori_id'] ?: null,
                foto: $fotoList,
                deskripsi: $this->produkForm['deskripsi'] ?: null,
                produkKompatibelIds: $this->produkForm['produk_kompatibel_ids'] ?: [],
                abcClass: $this->produkForm['abc_class'] ?? 'B',
                reorderPoint: ! empty($this->produkForm['reorder_point']) ? (int) $this->produkForm['reorder_point'] : null,
                minStock: ! empty($this->produkForm['min_stock']) ? (int) $this->produkForm['min_stock'] : null,
                maxStock: ! empty($this->produkForm['max_stock']) ? (int) $this->produkForm['max_stock'] : null,
                isOndemand: (bool) ($this->produkForm['is_ondemand'] ?? false),
                barcode: ! empty($this->produkForm['barcode']) ? trim($this->produkForm['barcode']) : null
            );

            $this->showProdukModal = false;
            $this->fotoUploads = [];
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Produk tersimpan'.((int) $this->produkForm['stok_awal'] > 0 ? ' + jurnal pembelian dibuat' : ''),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openEditProdukModal(int $produkId)
    {
        $p = Produk::with(['brand', 'kualitas', 'tipeHps', 'produkKompatibel', 'hargaTier', 'skuVariants'])->findOrFail($produkId);
        $this->editProdukId = $p->id;

        $tierMap = [
            'retail' => ['nominal_tetap' => (float) $p->harga_jual_retail, 'persen_diskon' => null],
            'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
            'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
        ];
        foreach ($p->hargaTier as $ht) {
            if ($ht->tipe_konsumen && isset($tierMap[$ht->tipe_konsumen])) {
                $tierMap[$ht->tipe_konsumen] = [
                    'nominal_tetap' => $ht->nominal_tetap ? (float) $ht->nominal_tetap : null,
                    'persen_diskon' => $ht->persen_diskon ? (float) $ht->persen_diskon : null,
                ];
            }
        }

        $this->editProdukForm = [
            'nama' => $p->nama,
            'barcode' => $p->barcode ?? '',
            'kategori' => $p->kategori,
            'kategori_id' => $p->kategori_id,
            'brand_kompatibel' => $p->brand_kompatibel,
            'model_kompatibel' => $p->model_kompatibel,
            'kondisi' => $p->kondisi,
            'satuan_kode' => $p->satuan ?: 'pcs',
            'harga_beli' => (float) $p->harga_beli,
            'harga_jual_retail' => (float) $p->harga_jual_retail,
            'brand_id' => $p->brand_id,
            'kualitas_id' => $p->kualitas_id,
            'tipe_hp_ids' => $p->tipeHps->pluck('id')->all(),
            'produk_kompatibel_ids' => $p->produkKompatibel->pluck('id')->all(),
            'harga_tier' => $tierMap,
            'harga_fleksibel' => (bool) $p->harga_fleksibel,
            'sn' => (bool) $p->sn,
            'deskripsi' => $p->deskripsi ?? '',
            // [PROCUREMENT: ABC, ROP, Min-Max, JIT]
            'abc_class' => $p->abc_class ?: 'B',
            'reorder_point' => $p->reorder_point,
            'min_stock' => $p->min_stock,
            'max_stock' => $p->max_stock,
            'is_ondemand' => (bool) $p->is_ondemand,
        ];

        $this->editFotoExisting = $p->galeri_foto;
        $this->editFotoUploads = [];
        $this->showEditProdukModal = true;
    }

    public function setFotoUtama(int $index)
    {
        foreach ($this->editFotoExisting as $i => &$item) {
            $item['is_primary'] = ($i === $index);
        }
    }

    public function hapusFotoItem(int $index)
    {
        if (isset($this->editFotoExisting[$index])) {
            $foto = $this->editFotoExisting[$index];
            app(ProductImageService::class)->hapusFoto($foto['url'], $foto['thumb'] ?? null);
            unset($this->editFotoExisting[$index]);
            $this->editFotoExisting = array_values($this->editFotoExisting);
            if (! empty($this->editFotoExisting) && ! collect($this->editFotoExisting)->contains('is_primary', true)) {
                $this->editFotoExisting[0]['is_primary'] = true;
            }
        }
    }

    public function simpanEditProduk()
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengedit produk.')) {
            return;
        }

        $this->editProdukForm['harga_beli'] = $this->parseNominal($this->editProdukForm['harga_beli'] ?? 0);
        $this->editProdukForm['harga_jual_retail'] = $this->parseNominal($this->editProdukForm['harga_jual_retail'] ?? 0);
        if (isset($this->editProdukForm['harga_tier'])) {
            foreach ($this->editProdukForm['harga_tier'] as $k => $tier) {
                if (isset($tier['nominal_tetap']) && $tier['nominal_tetap'] !== '' && $tier['nominal_tetap'] !== null) {
                    $this->editProdukForm['harga_tier'][$k]['nominal_tetap'] = $this->parseNominal($tier['nominal_tetap']);
                }
            }
        }

        $this->validate([
            'editProdukForm.nama' => 'required|string|max:255',
            'editProdukForm.barcode' => 'nullable|string|max:50|unique:produk,barcode,'.$this->editProdukId,
            'editProdukForm.harga_beli' => 'required|numeric|min:0',
            'editProdukForm.harga_jual_retail' => 'required|numeric|min:0',
            'editFotoUploads.*' => 'nullable|image|max:10240',
        ]);

        $imageService = app(ProductImageService::class);
        $fotoList = $this->editFotoExisting;

        if (! empty($this->editFotoUploads)) {
            foreach ($this->editFotoUploads as $file) {
                $saved = $imageService->prosesDanSimpan($file);
                $saved['is_primary'] = empty($fotoList);
                $fotoList[] = $saved;
            }
        }

        // Pastikan harga tier retail selalu sinkron dengan harga jual retail
        $this->editProdukForm['harga_tier']['retail']['nominal_tetap'] = (float) $this->editProdukForm['harga_jual_retail'];

        // Pastikan brand & model kompatibel sinkron dari Tipe HP bila ada
        if (! empty($this->editProdukForm['tipe_hp_ids']) && (empty($this->editProdukForm['brand_kompatibel']) || empty($this->editProdukForm['model_kompatibel']))) {
            $firstT = TipeHp::find($this->editProdukForm['tipe_hp_ids'][0]);
            if ($firstT) {
                $this->editProdukForm['brand_kompatibel'] = $this->editProdukForm['brand_kompatibel'] ?: $firstT->merk;
                $this->editProdukForm['model_kompatibel'] = $this->editProdukForm['model_kompatibel'] ?: $firstT->model;
            }
        }

        try {
            app(ProdukService::class)->updateProduk(
                produkId: $this->editProdukId,
                nama: $this->editProdukForm['nama'],
                kategori: $this->editProdukForm['kategori'] ?? null,
                brand: $this->editProdukForm['brand_kompatibel'] ?: null,
                model: $this->editProdukForm['model_kompatibel'] ?: null,
                kondisi: $this->editProdukForm['kondisi'] ?? 'baru',
                hargaBeli: (float) $this->editProdukForm['harga_beli'],
                hargaJual: (float) $this->editProdukForm['harga_jual_retail'],
                brandId: $this->editProdukForm['brand_id'] ?: null,
                kualitasId: $this->editProdukForm['kualitas_id'] ?: null,
                satuanKode: $this->editProdukForm['satuan_kode'] ?? 'pcs',
                tipeHpIds: $this->editProdukForm['tipe_hp_ids'] ?: [],
                hargaTier: $this->editProdukForm['harga_tier'] ?: [],
                hargaFleksibel: (bool) ($this->editProdukForm['harga_fleksibel'] ?? false),
                sn: (bool) ($this->editProdukForm['sn'] ?? false),
                kategoriId: $this->editProdukForm['kategori_id'] ?: null,
                foto: $fotoList,
                deskripsi: $this->editProdukForm['deskripsi'] ?: null,
                produkKompatibelIds: $this->editProdukForm['produk_kompatibel_ids'] ?: [],
                abcClass: $this->editProdukForm['abc_class'] ?? 'B',
                reorderPoint: ! empty($this->editProdukForm['reorder_point']) ? (int) $this->editProdukForm['reorder_point'] : null,
                minStock: ! empty($this->editProdukForm['min_stock']) ? (int) $this->editProdukForm['min_stock'] : null,
                maxStock: ! empty($this->editProdukForm['max_stock']) ? (int) $this->editProdukForm['max_stock'] : null,
                isOndemand: (bool) ($this->editProdukForm['is_ondemand'] ?? false),
                barcode: isset($this->editProdukForm['barcode']) ? trim((string) $this->editProdukForm['barcode']) : null
            );

            $this->showEditProdukModal = false;
            $this->editFotoUploads = [];
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Master produk berhasil diperbarui']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function hapusProduk(int $produkId): void
    {
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin / Owner yang berhak menghapus master data produk.']);

            return;
        }

        $produk = Produk::find($produkId);
        if (! $produk) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak ditemukan.']);

            return;
        }

        // 1. Cek sisa stok fisik
        $stokFisik = (int) StokItem::where('produk_id', $produkId)->sum('jumlah');
        if ($stokFisik > 0) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Tidak dapat menghapus produk '{$produk->nama}'. Masih ada sisa stok fisik sebanyak {$stokFisik} unit. Kosongkan stok terlebih dahulu via transfer atau opname.",
            ]);

            return;
        }

        // 2. Cek riwayat transaksi/mutasi yang terhubung
        $hasTransaksi = DB::table('transaksi_items')->where('produk_id', $produkId)->exists();
        $hasPo = DB::table('po_items')->where('produk_id', $produkId)->exists();
        $hasServis = DB::table('servis_item')->where('produk_id', $produkId)->exists();
        $hasTransfer = DB::table('stok_transfer_item')->where('produk_id', $produkId)->exists();
        $hasOpname = DB::table('stok_opname_item')->where('produk_id', $produkId)->exists();

        if ($hasTransaksi || $hasPo || $hasServis || $hasTransfer || $hasOpname) {
            $produk->update(['is_active' => false]);
            activity()
                ->performedOn($produk)
                ->causedBy(auth()->user())
                ->log("Produk '{$produk->nama}' dinonaktifkan karena memiliki riwayat transaksi/mutasi.");

            if ($this->editProdukId === $produkId) {
                $this->showEditProdukModal = false;
                $this->editProdukId = null;
            }

            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Produk '{$produk->nama}' memiliki riwayat transaksi/mutasi sehingga tidak dapat dihapus permanen demi keutuhan data audit & akuntansi. Status produk berhasil diubah menjadi NONAKTIF.",
            ]);

            return;
        }

        // 3. Jika bersih dari riwayat transaksi dan stok = 0, hapus permanen
        try {
            DB::transaction(function () use ($produk, $produkId) {
                HargaTier::where('produk_id', $produkId)->delete();
                StokItem::where('produk_id', $produkId)->delete();
                SkuVariant::where('produk_id', $produkId)->delete();
                DB::table('sid_import_maps')->where('entity_type', 'produk')->where('entity_id', $produkId)->delete();

                if (! empty($produk->foto)) {
                    $fotos = is_array($produk->foto) ? $produk->foto : json_decode((string) $produk->foto, true);
                    if (is_array($fotos)) {
                        $imgService = app(ProductImageService::class);
                        foreach ($fotos as $f) {
                            if (isset($f['url'])) {
                                $imgService->hapusFoto($f['url'], $f['thumb'] ?? null);
                            }
                        }
                    }
                }

                $produk->delete();
            });

            if ($this->editProdukId === $produkId) {
                $this->showEditProdukModal = false;
                $this->editProdukId = null;
            }

            $this->dispatch('alert', ['type' => 'success', 'message' => "Produk '{$produk->nama}' berhasil dihapus permanen."]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal menghapus produk: '.$e->getMessage()]);
        }
    }

    public function updatedProdukFormTipeHpIds($value): void
    {
        if (! empty($value) && is_array($value)) {
            $first = TipeHp::find($value[0] ?? null);
            if ($first) {
                if (empty($this->produkForm['brand_kompatibel'])) {
                    $this->produkForm['brand_kompatibel'] = $first->merk;
                }
                if (empty($this->produkForm['model_kompatibel'])) {
                    $this->produkForm['model_kompatibel'] = $first->model;
                }
            }
        }
    }

    public function updatedEditProdukFormTipeHpIds($value): void
    {
        if (! empty($value) && is_array($value)) {
            $first = TipeHp::find($value[0] ?? null);
            if ($first) {
                if (empty($this->editProdukForm['brand_kompatibel'])) {
                    $this->editProdukForm['brand_kompatibel'] = $first->merk;
                }
                if (empty($this->editProdukForm['model_kompatibel'])) {
                    $this->editProdukForm['model_kompatibel'] = $first->model;
                }
            }
        }
    }

    public function openQuickAdd(string $tipe): void
    {
        $this->quickAddTipe = $tipe;
        $defaultMerk = '';
        $defaultModel = '';
        if ($tipe === 'tipe_hp') {
            $defaultMerk = $this->showEditProdukModal
                ? ($this->editProdukForm['brand_kompatibel'] ?? '')
                : ($this->produkForm['brand_kompatibel'] ?? '');
            $defaultModel = $this->showEditProdukModal
                ? ($this->editProdukForm['model_kompatibel'] ?? '')
                : ($this->produkForm['model_kompatibel'] ?? '');
        }

        $this->quickAddForm = [
            'nama' => '',
            'kode' => '',
            'parent_id' => null,
            'keterangan' => '',
            'merk' => $defaultMerk,
            'model' => $defaultModel,
        ];
        $this->showQuickAddModal = true;
    }

    public function simpanQuickAdd(): void
    {
        if ($this->quickAddTipe === 'tipe_hp') {
            $this->validate([
                'quickAddForm.merk' => 'required|string|max:100',
                'quickAddForm.model' => 'required|string|max:100',
            ]);
        } else {
            $this->validate([
                'quickAddForm.nama' => 'required|string|max:255',
            ]);
        }

        $nama = trim($this->quickAddForm['nama']);

        switch ($this->quickAddTipe) {
            case 'tipe_hp':
                $merk = trim($this->quickAddForm['merk']);
                $model = trim($this->quickAddForm['model']);
                $namaLengkap = $nama ?: "{$merk} {$model}";

                $tipe = TipeHp::firstOrCreate(
                    ['merk' => $merk, 'model' => $model],
                    ['nama' => $namaLengkap, 'is_active' => true]
                );

                if ($this->showEditProdukModal) {
                    $cur = $this->editProdukForm['tipe_hp_ids'] ?? [];
                    if (! in_array($tipe->id, $cur)) {
                        $cur[] = $tipe->id;
                    }
                    $this->editProdukForm['tipe_hp_ids'] = array_values($cur);
                    if (empty($this->editProdukForm['brand_kompatibel'])) {
                        $this->editProdukForm['brand_kompatibel'] = $tipe->merk;
                    }
                    if (empty($this->editProdukForm['model_kompatibel'])) {
                        $this->editProdukForm['model_kompatibel'] = $tipe->model;
                    }
                } else {
                    $cur = $this->produkForm['tipe_hp_ids'] ?? [];
                    if (! in_array($tipe->id, $cur)) {
                        $cur[] = $tipe->id;
                    }
                    $this->produkForm['tipe_hp_ids'] = array_values($cur);
                    if (empty($this->produkForm['brand_kompatibel'])) {
                        $this->produkForm['brand_kompatibel'] = $tipe->merk;
                    }
                    if (empty($this->produkForm['model_kompatibel'])) {
                        $this->produkForm['model_kompatibel'] = $tipe->model;
                    }
                }
                $pesan = "Tipe HP '{$tipe->nama}' berhasil ditambahkan";
                break;
            case 'kategori':
                $kat = KategoriProduk::create([
                    'nama' => $nama,
                    'parent_id' => $this->quickAddForm['parent_id'] ?: null,
                    'is_active' => true,
                ]);
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['kategori_id'] = $kat->id;
                } else {
                    $this->produkForm['kategori_id'] = $kat->id;
                }
                $pesan = "Kategori '{$nama}' berhasil ditambahkan";
                break;

            case 'brand':
                $brand = Brand::firstOrCreate(
                    ['nama' => $nama],
                    ['keterangan' => $this->quickAddForm['keterangan'] ?: null, 'is_active' => true]
                );
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['brand_id'] = $brand->id;
                } else {
                    $this->produkForm['brand_id'] = $brand->id;
                }
                $pesan = "Brand '{$nama}' berhasil ditambahkan";
                break;

            case 'kualitas':
                $kualitas = KualitasProduk::firstOrCreate(
                    ['nama' => $nama],
                    ['keterangan' => $this->quickAddForm['keterangan'] ?: null, 'is_active' => true]
                );
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['kualitas_id'] = $kualitas->id;
                } else {
                    $this->produkForm['kualitas_id'] = $kualitas->id;
                }
                $pesan = "Kualitas '{$nama}' berhasil ditambahkan";
                break;

            case 'satuan':
                $kode = strtolower(trim($this->quickAddForm['kode'] ?: $nama));
                $satuan = SatuanUnit::firstOrCreate(
                    ['kode' => $kode],
                    ['nama' => $nama, 'is_active' => true]
                );
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['satuan_kode'] = $satuan->kode;
                } else {
                    $this->produkForm['satuan_kode'] = $satuan->kode;
                }
                $pesan = "Satuan '{$satuan->kode}' berhasil ditambahkan";
                break;

            case 'kondisi':
                $kode = Str::slug($nama, '_');
                if (empty($kode)) {
                    $kode = 'kondisi_'.time();
                }
                $config = app(KonfigurasiService::class);
                $list = $config->get('master_produk_kondisi_list') ?? [
                    ['kode' => 'baru', 'nama' => 'Baru'],
                    ['kode' => 'oem', 'nama' => 'OEM'],
                    ['kode' => 'compatible', 'nama' => 'Compatible'],
                    ['kode' => 'bekas', 'nama' => 'Bekas / Copotan'],
                ];
                $ada = false;
                foreach ($list as &$k) {
                    if ($k['kode'] === $kode) {
                        $k['nama'] = $nama;
                        $ada = true;
                        break;
                    }
                }
                if (! $ada) {
                    $list[] = ['kode' => $kode, 'nama' => $nama, 'is_active' => true];
                }
                $config->set('master_produk_kondisi_list', $list);
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['kondisi'] = $kode;
                } else {
                    $this->produkForm['kondisi'] = $kode;
                }
                $pesan = "Kondisi '{$nama}' berhasil ditambahkan";
                break;

            default:
                $pesan = 'Data berhasil ditambahkan';
        }

        $this->showQuickAddModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => $pesan]);
    }

    public function orderPo(int $produkId): void
    {
        $this->dispatch('order-po-produk', produkId: $produkId);
        $this->dispatch('wms-pindah-tab', tab: 'po');
    }

    public function openTambahStokModal(int $produkId): void
    {
        $this->orderPo($produkId);
    }

    // [T-15] Generate barcode utk produk (api paralel ke WMS-14)
    public function generateBarcodeProduk(int $id)
    {
        $produk = Produk::findOrFail($id);

        if (empty($produk->barcode)) {
            $checksum = substr(hash('crc32b', (string) $produk->id), 0, 4);
            $produk->update(['barcode' => sprintf('UTP-%05d-%s', $produk->id, strtoupper($checksum))]);
        }

        $variant = $produk->skuVariants()->first();
        if ($variant && empty($variant->barcode)) {
            $variant->update(['barcode' => $produk->barcode.'-'.$variant->id]);
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Barcode: '.$produk->fresh()->barcode]);
    }

    // Generate barcode acak unik untuk form Tambah/Edit Produk
    public function generateBarcodeForm(string $target = 'create'): void
    {
        do {
            $candidate = '899'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
        } while (Produk::where('barcode', $candidate)->exists() || SkuVariant::where('barcode', $candidate)->exists());

        if ($target === 'create') {
            $this->produkForm['barcode'] = $candidate;
        } else {
            $this->editProdukForm['barcode'] = $candidate;
        }
        $this->dispatch('alert', ['type' => 'info', 'message' => "Kode barcode dibuat: {$candidate}"]);
    }

    // Quick action header "Import Excel" (dari shell WmsDashboard via $dispatch)
    #[On('wms-import-modal')]
    public function openImportModal()
    {
        $this->importStep = 'upload';
        $this->importFile = null;
        $this->importPreview = [];
        $this->importFilePath = '';
        $this->activeImportLog = null;
        $this->importFormat = 'standar';

        $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;
        $gudangsCabang = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->get();
        $this->gudangTokoId = $gudangsCabang->firstWhere('kode', 'GD-TOKO')?->id
            ?? $gudangsCabang->first(fn ($g) => str_contains(strtolower($g->nama), 'toko'))?->id
            ?? $gudangsCabang->first()?->id;

        $this->gudangPusatId = $gudangsCabang->firstWhere('kode', 'GD-PUSAT')?->id
            ?? $gudangsCabang->first(fn ($g) => str_contains(strtolower($g->nama), 'pusat') || str_contains(strtolower($g->nama), 'gudang'))?->id
            ?? $gudangsCabang->skip(1)->first()?->id
            ?? $this->gudangTokoId;

        $this->showImportModal = true;
    }

    public function tutupImportModal()
    {
        $this->showImportModal = false;
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
            @unlink(storage_path('app/'.$this->importFilePath));
            @unlink(storage_path('app/private/'.$this->importFilePath));
        }
        $this->importFile = null;
        $this->importPreview = [];
        $this->importFilePath = '';
        $this->activeImportLog = null;
    }

    /**
     * [T-43] Preview (dry-run): validasi seluruh baris, tampilkan 5 baris pertama + error per baris.
     * Wajib sebelum commit. File sementara disimpan sampai commit.
     */
    public function previewImport()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $path = $this->importFile->store('import-tmp');
            $realPath = Storage::disk('local')->path($path);
            $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;

            if ($this->importFormat === 'sid_retail') {
                $hasil = app(ImportSidRetailService::class)->preview(
                    $realPath,
                    $cabangId,
                    $this->gudangTokoId,
                    $this->gudangPusatId
                );
            } else {
                $hasil = app(ImportProdukService::class)->preview($realPath);
            }

            $this->importFilePath = $path;
            $this->importPreview = $hasil;
            $this->importStep = 'preview';

            if ($hasil['invalid'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "Preview: {$hasil['valid']} baris valid, {$hasil['invalid']} baris error — perbaiki file lalu upload ulang, atau import hanya baris valid",
                ]);
            } elseif (! empty($hasil['total_peringatan']) && $hasil['total_peringatan'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'info',
                    'message' => "Preview: Semua baris valid! Terdapat {$hasil['total_peringatan']} catatan perhatian kompatibilitas.",
                ]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * [T-43] Commit: antrikan ImportProdukExcelJob (queue database) + catat import_log.
     * Import = inisialisasi master (bukan stok masuk harian — tetap lewat PO).
     */
    public function commitImport()
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengimpor produk.')) {
            return;
        }

        if (! $this->importFilePath) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Lakukan preview terlebih dahulu']);

            return;
        }

        try {
            $totalBaris = (int) ($this->importPreview['total_baris'] ?? 0);
            $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;

            if ($this->importFormat === 'sid_retail') {
                $log = ImportLog::create([
                    'tipe' => 'produk_sid_retail',
                    'nama_file' => $this->importFile?->getClientOriginalName() ?? 'sid-retail-import.xlsx',
                    'status' => 'proses',
                    'user_id' => auth()->id(),
                ]);
                $this->importLogId = $log->id;
                $filePath = $this->importFilePath;

                $this->importFilePath = '';

                if ($totalBaris <= 100) {
                    ImportProdukExcelJob::dispatchSync(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'sid_retail',
                        $cabangId,
                        $this->gudangTokoId,
                        $this->gudangPusatId
                    );
                } else {
                    ImportProdukExcelJob::dispatch(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'sid_retail',
                        $cabangId,
                        $this->gudangTokoId,
                        $this->gudangPusatId
                    );
                }
            } else {
                $log = ImportLog::create([
                    'tipe' => 'produk_excel',
                    'nama_file' => $this->importFile?->getClientOriginalName() ?? 'produk-import.xlsx',
                    'status' => 'proses',
                    'user_id' => auth()->id(),
                ]);
                $this->importLogId = $log->id;
                $filePath = $this->importFilePath;

                // Lepas referensi importFilePath agar tidak terhapus jika modal ditutup saat proses
                $this->importFilePath = '';

                // Jika <= 100 baris, proses langsung secara sinkron agar hasil instan dan tidak stuck di antrian
                if ($totalBaris <= 100) {
                    ImportProdukExcelJob::dispatchSync($log->id, $filePath, auth()->id());
                } else {
                    ImportProdukExcelJob::dispatch($log->id, $filePath, auth()->id());
                }
            }

            $this->importStep = 'selesai';
            $this->cekStatusImport();
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $totalBaris <= 100 ? 'Import produk selesai diproses.' : 'Import sedang diproses oleh sistem di latar belakang.',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cekStatusImport(): void
    {
        if (! $this->importLogId) {
            return;
        }

        $log = ImportLog::find($this->importLogId);
        if ($log) {
            $this->activeImportLog = [
                'id' => $log->id,
                'status' => $log->status,
                'status_label' => $log->status_label,
                'status_badge_class' => $log->status_badge_class,
                'total_baris' => $log->total_baris,
                'sukses' => $log->sukses,
                'gagal' => $log->gagal,
                'peringatan_count' => count($log->peringatan_list),
                'peringatan_list' => $log->peringatan_list,
                'gagal_list' => $log->gagal_list,
            ];
        }
    }

    public function updatingProdukSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterKategoriId()
    {
        $this->resetPage();
    }

    public function updatingFilterBrandId()
    {
        $this->resetPage();
    }

    protected function boleh(string $permission, string $pesan = 'Anda tidak memiliki hak akses untuk tindakan ini.'): bool
    {
        if (auth()->user()?->can($permission)) {
            return true;
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
    }

    public function render()
    {
        $kategoriTree = KategoriProduk::getTree();

        $selectedKategoriIds = [];
        if ($this->filterKategoriId) {
            $kat = KategoriProduk::with('children')->find($this->filterKategoriId);
            if ($kat) {
                $selectedKategoriIds = array_merge([$kat->id], $kat->children->pluck('id')->all());
            }
        }

        $produks = Produk::with(['skuVariants', 'stokItems.gudang', 'brand', 'kualitas', 'tipeHps', 'kategoriRelasi'])
            ->when($this->produkSearch, fn ($q) => $q->cariPintar($this->produkSearch))
            ->when(! empty($selectedKategoriIds), fn ($q) => $q->whereIn('kategori_id', $selectedKategoriIds))
            ->when($this->filterBrandId, fn ($q) => $q->where('brand_id', $this->filterBrandId))
            ->orderBy('nama')
            ->paginate(12);

        $gudangs = Gudang::where('is_active', true)->get();

        $config = app(KonfigurasiService::class);
        $savedKondisi = $config->get('master_produk_kondisi_list');
        $kondisiList = (is_array($savedKondisi) && ! empty($savedKondisi))
            ? array_values(array_filter($savedKondisi, fn ($k) => ($k['is_active'] ?? true)))
            : [
                ['kode' => 'baru', 'nama' => 'Baru'],
                ['kode' => 'oem', 'nama' => 'OEM'],
                ['kode' => 'compatible', 'nama' => 'Compatible'],
                ['kode' => 'bekas', 'nama' => 'Bekas / Copotan'],
            ];

        $tipeHpList = TipeHp::where('is_active', true)->orderBy('merk')->orderBy('model')->get();
        $tipeHpOptions = $tipeHpList->map(fn ($t) => [
            'id' => $t->id,
            'label' => "{$t->merk} {$t->model}",
        ])->all();

        $selectedIds = array_filter(array_merge(
            (array) ($this->produkForm['produk_kompatibel_ids'] ?? []),
            (array) ($this->editProdukForm['produk_kompatibel_ids'] ?? [])
        ));

        $selectedProduk = ! empty($selectedIds)
            ? Produk::whereIn('id', $selectedIds)->select('id', 'nama')->get()
            : collect();

        $topProduk = Produk::where('is_active', true)
            ->select('id', 'nama')
            ->orderBy('nama')
            ->limit(100)
            ->get();

        $allProdukList = $selectedProduk->merge($topProduk)->unique('id')->values();
        $allProdukOptions = $allProdukList->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->nama,
        ])->all();

        $brandKompatibelList = TipeHp::where('is_active', true)
            ->distinct()
            ->pluck('merk')
            ->merge(Brand::where('is_active', true)->pluck('nama'))
            ->merge(Produk::whereNotNull('brand_kompatibel')->where('brand_kompatibel', '!=', '')->distinct()->pluck('brand_kompatibel'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $modelKompatibelList = TipeHp::where('is_active', true)
            ->distinct()
            ->pluck('model')
            ->merge(Produk::whereNotNull('model_kompatibel')->where('model_kompatibel', '!=', '')->distinct()->pluck('model_kompatibel'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;
        $gudangsCabang = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->get();

        return view('modules.wms.livewire.produk-tab', [
            'gudangs' => $gudangs,
            'gudangsCabang' => $gudangsCabang,
            'raks' => Rak::with('gudang')->get(),
            'produks' => $produks,
            'kategoriTree' => $kategoriTree,
            // [T-44] master data pendukung
            'brands' => Brand::where('is_active', true)->orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::where('is_active', true)->orderBy('nama')->get(),
            'satuanUnits' => SatuanUnit::where('is_active', true)->orderBy('kode')->get(),
            'kondisiList' => $kondisiList,
            'tipeHpList' => $tipeHpList,
            'tipeHpOptions' => $tipeHpOptions,
            'allProdukList' => $allProdukList,
            'allProdukOptions' => $allProdukOptions,
            'brandKompatibelList' => $brandKompatibelList,
            'modelKompatibelList' => $modelKompatibelList,
        ]);
    }
}
