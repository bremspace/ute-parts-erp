<?php

namespace App\Modules\Wms\Livewire;

use App\Models\UserPreference;
use App\Modules\Crm\Models\TierMembership;
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
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use App\Modules\Wms\Services\ProcurementService;
use App\Modules\Wms\Services\ProductImageService;
use App\Modules\Wms\Services\ProdukService;
use App\Traits\ParsesNominal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

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

    public ?int $filterKualitasId = null;

    public ?int $filterTipeHpId = null;

    public ?string $filterBrandKompatibel = null;

    public ?string $filterModelKompatibel = null;

    public ?string $filterKondisi = null;

    public ?string $filterSatuan = null;

    public ?string $filterAbcClass = null;

    public ?int $filterSupplierId = null;

    public bool $filterHanyaReadyStok = false;

    public string $filterTierHarga = 'retail'; // retail, reseller, agen, atau tier_{id}

    public ?string $filterSku = null;

    public ?float $filterMinHarga = null;

    public ?float $filterMaxHarga = null;

    public ?float $filterMinHargaBeli = null;

    public ?float $filterMaxHargaBeli = null;

    public string $filterStokStatus = 'semua'; // 'semua', 'ready', 'menipis', 'habis'

    public string $filterStatusAktif = 'semua'; // 'semua', 'aktif', 'nonaktif'

    public int $perPage = 25;

    public array $selectedProdukIds = [];

    public bool $selectAll = false;

    // Sorting
    public string $sortBy = 'nama';

    public string $sortDir = 'asc';

    public string $sortPreset = 'nama_asc';

    // Bulk Edit Modal
    public bool $showBulkEditModal = false;

    public array $bulkEditFields = [
        'kategori_id' => false,
        'brand_id' => false,
        'kualitas_id' => false,
        'kondisi' => false,
        'satuan' => false,
        'abc_class' => false,
        'is_active' => false,
        'harga_fleksibel' => false,
        'sn' => false,
        'penyesuaian_harga' => false,
        'rak_id' => false,
    ];

    public array $bulkEditForm = [
        'kategori_id' => null,
        'brand_id' => null,
        'kualitas_id' => null,
        'kondisi' => 'baru',
        'satuan' => 'pcs',
        'abc_class' => 'B',
        'is_active' => 1,
        'harga_fleksibel' => 0,
        'sn' => 0,
        'tipe_penyesuaian_harga' => 'margin_persen', // 'margin_persen' | 'tambah_persen' | 'potong_persen' | 'tambah_nominal' | 'potong_nominal'
        'nilai_penyesuaian_harga' => 0,
        'rak_id' => null,
    ];

    // Salin Data Modal & Preferences
    public bool $showSalinDataModal = false;

    public array $salinOptions = [
        'nama' => true,
        'harga' => true,
        'sku' => false,
        'kualitas' => false,
        'kompatibilitas' => false,
        'kondisi' => false,
        'stok' => false,
        'rak' => false,
        'kategori' => false,
        'brand' => false,
        'garansi' => false,
        'deskripsi' => false,
        'format' => 'teks', // 'teks' (Chat WA / Catatan) | 'tsv' (Excel / Spreadsheet)
    ];

    // Filter toggle preferences (checkbox custom)
    public array $visibleFilters = [
        'kategori' => true,
        'brand' => true,
        'kualitas' => false,
        'tipe_hp' => false,
        'brand_kompatibel' => false,
        'model_kompatibel' => false,
        'kondisi' => false,
        'satuan' => false,
        'abc_class' => false,
        'stok' => false,
        'tier' => true,
        'supplier' => false,
    ];

    public bool $showCustomFilterSettings = false;

    // Column visibility preferences (checkbox custom kolom tabel master produk)
    public array $visibleColumns = [
        'checkbox' => true,
        'produk' => true,
        'kategori' => false,
        'brand' => false,
        'kompatibilitas' => false,
        'supplier' => false,
        'sku' => true,
        'harga_beli' => false,
        'harga_jual' => true,
        'stok' => true,
        'gudang' => true,
        'reorder' => false,
        'spesifikasi' => false,
        'status' => false,
        'aksi' => true,
    ];

    public bool $showColumnSettings = false;

    // Foto Uploads (Tambah Produk)
    public $fotoUploads = [];

    public $fotoKameraUpload = null;

    // Stok tab filter default (dipakai modal tambah stok) — sama dgn mount shell lama
    public ?int $filterGudangId = null;

    // New Produk Modal (Tambah Produk — sinkron Akunting)
    public bool $showProdukModal = false;

    public array $produkForm = [
        'nama' => '', 'barcode' => '', 'kategori' => '', 'kategori_id' => null, 'brand_kompatibel' => '', 'model_kompatibel' => '',
        'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
        'sku' => '', 'gudang_id' => null, 'rak_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
        'deskripsi' => '',
        // [T-44]
        'brand_id' => null, 'kualitas_id' => null, 'supplier_id' => null, 'satuan_kode' => 'pcs',
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
        // [HARGA BELI & JUAL DINAMIS]
        'metode_harga_beli' => 'average',
        'margin_persen' => null,
        'hitung_dari_margin' => false,
        'multi_satuan' => [],
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

    public string $autoAbcKeterangan = '';

    public array $editStokItems = [];

    public array $editStokRaks = [];

    public array $editFotoExisting = [];

    public $editFotoUploads = [];

    public $editFotoKameraUpload = null;

    // Kompatibilitas Smartphone Picker (Merek HP & Tipe HP Terpisah — Multi Select & Search)
    public ?string $kompatibelMerekHp = null;

    public ?int $kompatibelTipeHpId = null;

    public array $kompatibelSelectedTipeHpIds = [];

    public string $searchTipeHpCreate = '';

    public ?string $editKompatibelMerekHp = null;

    public ?int $editKompatibelTipeHpId = null;

    public array $editKompatibelSelectedTipeHpIds = [];

    public string $searchTipeHpEdit = '';

    // Quick Add Modal (Kategori, Brand, Kualitas, Satuan, Kondisi, Tipe HP)
    public bool $showQuickAddModal = false;

    public string $quickAddTipe = 'kategori';

    public ?string $quickAddSelectedMerk = '';

    public ?string $quickAddSelectedModel = '';

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

    // Initial Stok / Saldo Awal Modal (untuk produk baru belum ada mutasi)
    public bool $showSaldoAwalModal = false;

    public ?int $saldoAwalProdukId = null;

    public ?string $saldoAwalProdukNama = '';

    public bool $saldoAwalProdukSn = false;

    public array $saldoAwalForm = [
        'gudang_id' => null,
        'rak_id' => null,
        'qty' => 1,
        'harga_beli' => 0,
        'sn' => '',
        'keterangan' => 'Saldo awal inventori produk',
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
        $this->loadUserPreferences();
    }

    public function loadUserPreferences(): void
    {
        $userId = auth()->id();
        if ($userId) {
            $prefFilters = UserPreference::getPref($userId, 'wms_produk_tab_custom_filters');
            if (is_array($prefFilters)) {
                $this->visibleFilters = array_merge($this->visibleFilters, $prefFilters);
            }
            $prefCols = UserPreference::getPref($userId, 'wms_produk_tab_visible_columns');
            if (is_array($prefCols)) {
                $this->visibleColumns = array_merge($this->visibleColumns, $prefCols);
            }
        }
    }

    public function toggleColumn(string $key): void
    {
        if (array_key_exists($key, $this->visibleColumns)) {
            // Kolom produk wajib tampil
            if ($key === 'produk') {
                return;
            }
            $this->visibleColumns[$key] = ! $this->visibleColumns[$key];
            $this->simpanPreferensiKolom();
        }
    }

    public function showAllColumns(): void
    {
        foreach ($this->visibleColumns as $k => $v) {
            $this->visibleColumns[$k] = true;
        }
        $this->simpanPreferensiKolom();
    }

    public function resetColumns(): void
    {
        $this->visibleColumns = [
            'checkbox' => true,
            'produk' => true,
            'kategori' => false,
            'brand' => false,
            'kompatibilitas' => false,
            'supplier' => false,
            'sku' => true,
            'harga_beli' => false,
            'harga_jual' => true,
            'stok' => true,
            'gudang' => true,
            'reorder' => false,
            'spesifikasi' => false,
            'status' => false,
            'aksi' => true,
        ];
        $this->simpanPreferensiKolom();
    }

    protected function simpanPreferensiKolom(): void
    {
        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'wms_produk_tab_visible_columns', $this->visibleColumns);
        }
    }

    public function toggleFilter(string $key): void
    {
        $targetKey = match ($key) {
            'merk_hp' => 'brand_kompatibel',
            'tipe_hp' => 'model_kompatibel',
            default => $key,
        };

        if (array_key_exists($targetKey, $this->visibleFilters)) {
            $this->visibleFilters[$targetKey] = ! $this->visibleFilters[$targetKey];
            if (! $this->visibleFilters[$targetKey]) {
                match ($targetKey) {
                    'kategori' => $this->filterKategoriId = null,
                    'brand' => $this->filterBrandId = null,
                    'kualitas' => $this->filterKualitasId = null,
                    'brand_kompatibel' => $this->filterBrandKompatibel = null,
                    'model_kompatibel' => [
                        $this->filterModelKompatibel = null,
                        $this->filterTipeHpId = null,
                    ],
                    'kondisi' => $this->filterKondisi = null,
                    'satuan' => $this->filterSatuan = null,
                    'abc_class' => $this->filterAbcClass = null,
                    'stok' => $this->filterHanyaReadyStok = false,
                    'tier' => $this->filterTierHarga = 'retail',
                    'supplier' => $this->filterSupplierId = null,
                    default => null,
                };
                $this->resetPage();
            }
            $this->simpanPreferensi();
        }
    }

    public function resetCustomFilters(): void
    {
        $this->visibleFilters = [
            'kategori' => true,
            'brand' => true,
            'kualitas' => false,
            'tipe_hp' => false,
            'brand_kompatibel' => false,
            'model_kompatibel' => false,
            'kondisi' => false,
            'satuan' => false,
            'abc_class' => false,
            'stok' => false,
            'tier' => true,
            'supplier' => false,
        ];

        $this->filterKategoriId = null;
        $this->filterBrandId = null;
        $this->filterKualitasId = null;
        $this->filterTipeHpId = null;
        $this->filterBrandKompatibel = null;
        $this->filterModelKompatibel = null;
        $this->filterKondisi = null;
        $this->filterSatuan = null;
        $this->filterAbcClass = null;
        $this->filterSupplierId = null;
        $this->filterHanyaReadyStok = false;
        $this->filterTierHarga = 'retail';
        $this->resetPage();

        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'wms_produk_tab_custom_filters', $this->visibleFilters);
        }
    }

    public function updatedFilterBrandId($value): void
    {
        $this->resetPage();
        if ($this->filterKualitasId) {
            $k = KualitasProduk::find($this->filterKualitasId);
            if ($k && $k->brand_id && $value && (int) $k->brand_id !== (int) $value) {
                $this->filterKualitasId = null;
            }
        }
    }

    public function updatedProdukFormBrandId($value): void
    {
        if (! empty($this->produkForm['kualitas_id'])) {
            $k = KualitasProduk::find($this->produkForm['kualitas_id']);
            if ($k && $k->brand_id && $value && (int) $k->brand_id !== (int) $value) {
                $this->produkForm['kualitas_id'] = null;
            }
        }
    }

    public function updatedEditProdukFormBrandId($value): void
    {
        if (! empty($this->editProdukForm['kualitas_id'])) {
            $k = KualitasProduk::find($this->editProdukForm['kualitas_id']);
            if ($k && $k->brand_id && $value && (int) $k->brand_id !== (int) $value) {
                $this->editProdukForm['kualitas_id'] = null;
            }
        }
    }

    protected function simpanPreferensi(): void
    {
        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'wms_produk_tab_custom_filters', $this->visibleFilters);
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
            'brand_id' => null, 'kualitas_id' => null, 'supplier_id' => null, 'satuan_kode' => 'pcs',
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
            // [HARGA BELI & JUAL DINAMIS]
            'metode_harga_beli' => 'average',
            'margin_persen' => null,
            'hitung_dari_margin' => false,
            'multi_satuan' => [],
            // [PROCUREMENT: ABC, ROP, Min-Max, JIT]
            'abc_class' => 'B',
            'reorder_point' => null,
            'min_stock' => null,
            'max_stock' => null,
            'is_ondemand' => false,
        ];
        $this->fotoUploads = [];
        $this->fotoKameraUpload = null;
        $this->kompatibelMerekHp = null;
        $this->kompatibelTipeHpId = null;
        $this->kompatibelSelectedTipeHpIds = [];
        $this->searchTipeHpCreate = '';
        $this->showProdukModal = true;
    }

    public function updatedKompatibelMerekHp(): void
    {
        $this->kompatibelTipeHpId = null;
        $this->kompatibelSelectedTipeHpIds = [];
        $this->searchTipeHpCreate = '';
    }

    public function updatedEditKompatibelMerekHp(): void
    {
        $this->editKompatibelTipeHpId = null;
        $this->editKompatibelSelectedTipeHpIds = [];
        $this->searchTipeHpEdit = '';
    }

    public function pilihSemuaTipeHp(string $target = 'create'): void
    {
        $merek = $target === 'edit' ? $this->editKompatibelMerekHp : $this->kompatibelMerekHp;
        $search = $target === 'edit' ? $this->searchTipeHpEdit : $this->searchTipeHpCreate;

        if (empty($merek)) {
            return;
        }

        $query = TipeHp::where('is_active', true)->where('merk', $merek);
        if (! empty($search)) {
            $query->where('model', 'like', '%'.trim($search).'%');
        }

        $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($target === 'edit') {
            $this->editKompatibelSelectedTipeHpIds = array_values(array_unique(array_merge(
                array_map('intval', $this->editKompatibelSelectedTipeHpIds),
                $ids
            )));
        } else {
            $this->kompatibelSelectedTipeHpIds = array_values(array_unique(array_merge(
                array_map('intval', $this->kompatibelSelectedTipeHpIds),
                $ids
            )));
        }
    }

    public function batalPilihSemuaTipeHp(string $target = 'create'): void
    {
        if ($target === 'edit') {
            $this->editKompatibelSelectedTipeHpIds = [];
        } else {
            $this->kompatibelSelectedTipeHpIds = [];
        }
    }

    public function tambahKompatibelKeForm(string $target = 'create'): void
    {
        $selectedIds = $target === 'edit'
            ? $this->editKompatibelSelectedTipeHpIds
            : $this->kompatibelSelectedTipeHpIds;

        // Fallback jika single-select ID diisi
        $singleId = $target === 'edit' ? $this->editKompatibelTipeHpId : $this->kompatibelTipeHpId;
        if (empty($selectedIds) && $singleId) {
            $selectedIds = [$singleId];
        }

        if (empty($selectedIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih satu atau beberapa Tipe HP terlebih dahulu.']);

            return;
        }

        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        $current = array_map('intval', (array) ($this->{$formKey}['tipe_hp_ids'] ?? []));

        $addedCount = 0;
        foreach ($selectedIds as $id) {
            $idInt = (int) $id;
            if ($idInt > 0 && ! in_array($idInt, $current, true)) {
                $current[] = $idInt;
                $addedCount++;
            }
        }

        $this->{$formKey}['tipe_hp_ids'] = array_values(array_unique($current));

        // Sinkronkan brand_kompatibel dan model_kompatibel ringkasan dari tipe pertama
        if (empty($this->{$formKey}['brand_kompatibel']) && ! empty($current)) {
            $th = TipeHp::find($current[0]);
            if ($th) {
                $this->{$formKey}['brand_kompatibel'] = $th->merk;
                $this->{$formKey}['model_kompatibel'] = $th->model;
            }
        }

        if ($target === 'edit') {
            $this->editKompatibelTipeHpId = null;
            $this->editKompatibelSelectedTipeHpIds = [];
        } else {
            $this->kompatibelTipeHpId = null;
            $this->kompatibelSelectedTipeHpIds = [];
        }

        if ($addedCount > 0) {
            $this->dispatch('alert', ['type' => 'success', 'message' => "{$addedCount} Tipe HP berhasil ditambahkan ke daftar kompatibilitas."]);
        } else {
            $th = TipeHp::find($selectedIds[0] ?? null);
            $label = $th ? "{$th->merk} {$th->model}" : 'Tipe HP';
            $this->dispatch('alert', ['type' => 'warning', 'message' => "{$label} sudah ada di daftar kompatibilitas produk ini."]);
        }
    }

    public function hapusKompatibelDariForm(int $tipeHpId, string $target = 'create'): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        $current = array_map('intval', (array) ($this->{$formKey}['tipe_hp_ids'] ?? []));
        $filtered = array_values(array_filter($current, fn ($id) => (int) $id !== (int) $tipeHpId));
        $this->{$formKey}['tipe_hp_ids'] = $filtered;

        if (! empty($filtered)) {
            $first = TipeHp::find($filtered[0]);
            if ($first) {
                $this->{$formKey}['brand_kompatibel'] = $first->merk;
                $this->{$formKey}['model_kompatibel'] = $first->model;
            }
        } else {
            $this->{$formKey}['brand_kompatibel'] = null;
            $this->{$formKey}['model_kompatibel'] = null;
        }
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

    public function updatedProdukFormMarginPersen($value): void
    {
        $margin = is_numeric($value) ? (float) $value : 0;
        $hargaBeli = $this->parseNominal($this->produkForm['harga_beli'] ?? 0);
        if ($hargaBeli > 0 && $margin > 0) {
            $hargaJual = round($hargaBeli * (1 + ($margin / 100)));
            $this->produkForm['harga_jual_retail'] = $hargaJual;
            $this->produkForm['harga_tier']['retail']['nominal_tetap'] = $hargaJual;
        }
    }

    public function updatedProdukFormHargaBeli($value): void
    {
        $hargaBeli = $this->parseNominal($value);
        if (! empty($this->produkForm['hitung_dari_margin']) && ! empty($this->produkForm['margin_persen'])) {
            $margin = (float) $this->produkForm['margin_persen'];
            $hargaJual = round($hargaBeli * (1 + ($margin / 100)));
            $this->produkForm['harga_jual_retail'] = $hargaJual;
            $this->produkForm['harga_tier']['retail']['nominal_tetap'] = $hargaJual;
        } elseif ($hargaBeli > 0 && ! empty($this->produkForm['harga_jual_retail'])) {
            $hargaJual = $this->parseNominal($this->produkForm['harga_jual_retail']);
            if ($hargaJual >= $hargaBeli) {
                $this->produkForm['margin_persen'] = round((($hargaJual - $hargaBeli) / $hargaBeli) * 100, 2);
            }
        }
        $this->hitungKonversiMultiSatuan('create');
    }

    public function updatedProdukFormHitungDariMargin($value): void
    {
        if ($value && ! empty($this->produkForm['margin_persen'])) {
            $this->updatedProdukFormMarginPersen($this->produkForm['margin_persen']);
        }
    }

    public function updatedProdukFormHargaJualRetail($value): void
    {
        $hargaJual = $this->parseNominal($value);
        $this->produkForm['harga_tier']['retail']['nominal_tetap'] = $hargaJual;
        $hargaBeli = $this->parseNominal($this->produkForm['harga_beli'] ?? 0);
        if ($hargaBeli > 0 && empty($this->produkForm['hitung_dari_margin'])) {
            $this->produkForm['margin_persen'] = round((($hargaJual - $hargaBeli) / $hargaBeli) * 100, 2);
        }
        $this->hitungKonversiMultiSatuan('create');
    }

    public function updatedEditProdukFormMarginPersen($value): void
    {
        $margin = is_numeric($value) ? (float) $value : 0;
        $hargaBeli = $this->parseNominal($this->editProdukForm['harga_beli'] ?? 0);
        if ($hargaBeli > 0 && $margin > 0) {
            $hargaJual = round($hargaBeli * (1 + ($margin / 100)));
            $this->editProdukForm['harga_jual_retail'] = $hargaJual;
            $this->editProdukForm['harga_tier']['retail']['nominal_tetap'] = $hargaJual;
            $this->hitungKonversiMultiSatuan('edit');
        }
    }

    public function updatedEditProdukFormHargaBeli($value): void
    {
        $hargaBeli = $this->parseNominal($value);
        if (! empty($this->editProdukForm['hitung_dari_margin']) && ! empty($this->editProdukForm['margin_persen'])) {
            $margin = (float) $this->editProdukForm['margin_persen'];
            $hargaJual = round($hargaBeli * (1 + ($margin / 100)));
            $this->editProdukForm['harga_jual_retail'] = $hargaJual;
            $this->editProdukForm['harga_tier']['retail']['nominal_tetap'] = $hargaJual;
        } elseif ($hargaBeli > 0 && ! empty($this->editProdukForm['harga_jual_retail'])) {
            $hargaJual = $this->parseNominal($this->editProdukForm['harga_jual_retail']);
            if ($hargaJual >= $hargaBeli) {
                $this->editProdukForm['margin_persen'] = round((($hargaJual - $hargaBeli) / $hargaBeli) * 100, 2);
            }
        }
        $this->hitungKonversiMultiSatuan('edit');
    }

    public function updatedEditProdukFormHitungDariMargin($value): void
    {
        if ($value && ! empty($this->editProdukForm['margin_persen'])) {
            $this->updatedEditProdukFormMarginPersen($this->editProdukForm['margin_persen']);
        }
    }

    public function updatedEditProdukFormHargaJualRetail($value): void
    {
        $hargaJual = $this->parseNominal($value);
        $this->editProdukForm['harga_tier']['retail']['nominal_tetap'] = $hargaJual;
        $hargaBeli = $this->parseNominal($this->editProdukForm['harga_beli'] ?? 0);
        if ($hargaBeli > 0 && empty($this->editProdukForm['hitung_dari_margin'])) {
            $this->editProdukForm['margin_persen'] = round((($hargaJual - $hargaBeli) / $hargaBeli) * 100, 2);
        }
        $this->hitungKonversiMultiSatuan('edit');
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
            'produkForm.nama' => 'required|string',
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
        $this->produkForm['tipe_hp_ids'] = array_values(array_unique(array_map('intval', (array) ($this->produkForm['tipe_hp_ids'] ?? []))));
        if (! empty($this->produkForm['tipe_hp_ids'])) {
            $firstT = TipeHp::find($this->produkForm['tipe_hp_ids'][0]);
            if ($firstT) {
                $this->produkForm['brand_kompatibel'] = $firstT->merk;
                $this->produkForm['model_kompatibel'] = $firstT->model;
            }
        } else {
            $this->produkForm['brand_kompatibel'] = null;
            $this->produkForm['model_kompatibel'] = null;
        }

        $minStokVal = ! empty($this->produkForm['min_stock']) ? (int) $this->produkForm['min_stock'] : (int) $this->produkForm['stok_minimum'];

        if (! empty($this->produkForm['multi_satuan'])) {
            foreach ($this->produkForm['multi_satuan'] as $k => $ms) {
                if (isset($ms['harga_jual']) && $ms['harga_jual'] !== '' && $ms['harga_jual'] !== null) {
                    $this->produkForm['multi_satuan'][$k]['harga_jual'] = $this->parseNominal($ms['harga_jual']);
                }
                if (isset($ms['harga_beli']) && $ms['harga_beli'] !== '' && $ms['harga_beli'] !== null) {
                    $this->produkForm['multi_satuan'][$k]['harga_beli'] = $this->parseNominal($ms['harga_beli']);
                }
            }
        }

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
                barcode: ! empty($this->produkForm['barcode']) ? trim($this->produkForm['barcode']) : null,
                metodeHargaBeli: $this->produkForm['metode_harga_beli'] ?? 'average',
                marginPersen: ! empty($this->produkForm['margin_persen']) ? (float) $this->produkForm['margin_persen'] : null,
                rakId: ! empty($this->produkForm['rak_id']) ? (int) $this->produkForm['rak_id'] : null,
                multiSatuan: $this->produkForm['multi_satuan'] ?? [],
                supplierId: ! empty($this->produkForm['supplier_id']) ? (int) $this->produkForm['supplier_id'] : null
            );

            $this->showProdukModal = false;
            $this->fotoUploads = [];
            $this->fotoKameraUpload = null;
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
        $p = Produk::with(['brand', 'kualitas', 'tipeHps', 'produkKompatibel', 'hargaTier', 'skuVariants', 'satuanKonversi'])->findOrFail($produkId);
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

        $existingCodes = SatuanUnit::pluck('kode')->map(fn ($k) => strtolower($k))->all();

        $tipeHpIds = $p->tipeHps->pluck('id')->all();
        if (empty($tipeHpIds) && ! empty($p->brand_kompatibel) && ! empty($p->model_kompatibel)) {
            $matched = TipeHp::where('merk', $p->brand_kompatibel)->where('model', $p->model_kompatibel)->first();
            if ($matched) {
                $tipeHpIds = [$matched->id];
            }
        }
        $tipeHpIds = array_values(array_unique(array_map('intval', $tipeHpIds)));
        $this->editKompatibelMerekHp = null;
        $this->editKompatibelTipeHpId = null;

        $this->editProdukForm = [
            'nama' => $p->nama,
            'sku' => $p->skuVariants->first()?->sku ?? '',
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
            'supplier_id' => $p->supplier_id,
            'tipe_hp_ids' => $tipeHpIds,
            'produk_kompatibel_ids' => $p->produkKompatibel->pluck('id')->all(),
            'harga_tier' => $tierMap,
            'harga_fleksibel' => (bool) $p->harga_fleksibel,
            'sn' => (bool) $p->sn,
            'metode_harga_beli' => $p->metode_harga_beli ?? 'manual',
            'margin_persen' => $p->margin_persen !== null ? (float) $p->margin_persen : null,
            'hitung_dari_margin' => $p->margin_persen !== null && (float) $p->margin_persen > 0,
            'multi_satuan' => $p->satuanKonversi->map(function ($sk) use ($existingCodes, $p) {
                $faktor = (float) $sk->faktor_konversi > 0 ? (float) $sk->faktor_konversi : 1.0;
                $hargaRetail = (float) $p->harga_jual_retail;
                $hargaKotorAuto = round($faktor * $hargaRetail);
                $isManual = ($sk->harga_jual !== null && (float) $sk->harga_jual > 0 && abs((float) $sk->harga_jual - $hargaKotorAuto) > 0.01);
                $diskonPersen = 0.0;
                if (! $isManual && $hargaKotorAuto > 0 && $sk->harga_jual !== null && (float) $sk->harga_jual < $hargaKotorAuto) {
                    $diskonPersen = round((($hargaKotorAuto - (float) $sk->harga_jual) / $hargaKotorAuto) * 100, 1);
                }

                return [
                    'id' => $sk->id,
                    'satuan_kode' => $sk->satuan_kode,
                    'satuan_asal' => $sk->satuan_asal,
                    'pengali' => (float) $sk->pengali + 0,
                    'faktor_konversi' => $faktor + 0,
                    'mode_harga' => $isManual ? 'manual' : 'auto',
                    'diskon_persen' => $diskonPersen,
                    'harga_beli' => $sk->harga_beli !== null ? (float) $sk->harga_beli : null,
                    'harga_jual' => $sk->harga_jual !== null ? (float) $sk->harga_jual : null,
                    'barcode' => $sk->barcode ?? '',
                    'is_satuan_beli' => (bool) $sk->is_satuan_beli,
                    'is_satuan_jual' => (bool) $sk->is_satuan_jual,
                    'is_custom' => ! in_array(strtolower($sk->satuan_kode), $existingCodes, true),
                ];
            })->toArray(),
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
        $this->editFotoKameraUpload = null;

        // Load lokasi rak & stok per gudang untuk produk ini
        $stokList = StokItem::with(['gudang', 'rak'])
            ->where('produk_id', $p->id)
            ->get();

        $this->editStokItems = $stokList->map(function ($si) {
            return [
                'id' => $si->id,
                'gudang_id' => $si->gudang_id,
                'gudang_nama' => $si->gudang?->nama ?? '-',
                'jumlah' => $si->jumlah,
                'rak_id' => $si->rak_id,
                'rak_nama' => $si->rak ? $si->rak->nama.' ('.$si->rak->kode.')' : '— Belum diset',
            ];
        })->toArray();

        $this->editStokRaks = [];
        foreach ($this->editStokItems as $item) {
            $this->editStokRaks[$item['id']] = $item['rak_id'];
        }

        // Ambil data analisis WMS otomatis untuk panduan staf
        try {
            $autoInfo = app(ProcurementService::class)->hitungParameterProdukOtomatis($p->id, session('cabang_id'), 90);
            $this->autoAbcKeterangan = $autoInfo['keterangan'] ?? '';
        } catch (\Throwable $e) {
            $this->autoAbcKeterangan = '';
        }

        $this->showEditProdukModal = true;
    }

    public function simpanRakStokItem(int $stokItemId, ?int $rakId = null): void
    {
        $stok = StokItem::find($stokItemId);
        if ($stok) {
            $val = $rakId ?? ($this->editStokRaks[$stokItemId] ?? null);
            $stok->update(['rak_id' => $val ?: null]);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Lokasi rak produk berhasil diperbarui']);
        }
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

    public function updatedFotoKameraUpload($file): void
    {
        if ($file) {
            $this->validateOnly('fotoKameraUpload', [
                'fotoKameraUpload' => 'image|max:15360',
            ]);
            $this->fotoUploads[] = $file;
            $this->fotoKameraUpload = null;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Foto kamera berhasil ditambahkan ke daftar!']);
        }
    }

    public function updatedEditFotoKameraUpload($file): void
    {
        if ($file) {
            $this->validateOnly('editFotoKameraUpload', [
                'editFotoKameraUpload' => 'image|max:15360',
            ]);
            $this->editFotoUploads[] = $file;
            $this->editFotoKameraUpload = null;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Foto kamera berhasil ditambahkan ke daftar!']);
        }
    }

    public function hapusFotoUploadBaru(int $index): void
    {
        if (isset($this->fotoUploads[$index])) {
            unset($this->fotoUploads[$index]);
            $this->fotoUploads = array_values($this->fotoUploads);
        }
    }

    public function hapusEditFotoUploadBaru(int $index): void
    {
        if (isset($this->editFotoUploads[$index])) {
            unset($this->editFotoUploads[$index]);
            $this->editFotoUploads = array_values($this->editFotoUploads);
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

        $primaryVariant = SkuVariant::where('produk_id', $this->editProdukId)->first();
        $primaryVariantId = $primaryVariant?->id;

        $this->validate([
            'editProdukForm.nama' => 'required|string',
            'editProdukForm.sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('sku_variants', 'sku')->ignore($primaryVariantId),
            ],
            'editProdukForm.barcode' => 'nullable|string|max:50|unique:produk,barcode,'.$this->editProdukId,
            'editProdukForm.harga_beli' => 'required|numeric|min:0',
            'editProdukForm.harga_jual_retail' => 'required|numeric|min:0',
            'editFotoUploads.*' => 'nullable|image|max:10240',
        ], [
            'editProdukForm.sku.unique' => 'Kode SKU sudah digunakan oleh produk lain.',
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
        $this->editProdukForm['tipe_hp_ids'] = array_values(array_unique(array_map('intval', (array) ($this->editProdukForm['tipe_hp_ids'] ?? []))));
        if (! empty($this->editProdukForm['tipe_hp_ids'])) {
            $firstT = TipeHp::find($this->editProdukForm['tipe_hp_ids'][0]);
            if ($firstT) {
                $this->editProdukForm['brand_kompatibel'] = $firstT->merk;
                $this->editProdukForm['model_kompatibel'] = $firstT->model;
            }
        } else {
            $this->editProdukForm['brand_kompatibel'] = null;
            $this->editProdukForm['model_kompatibel'] = null;
        }

        if (! empty($this->editProdukForm['multi_satuan'])) {
            foreach ($this->editProdukForm['multi_satuan'] as $k => $ms) {
                if (isset($ms['harga_jual']) && $ms['harga_jual'] !== '' && $ms['harga_jual'] !== null) {
                    $this->editProdukForm['multi_satuan'][$k]['harga_jual'] = $this->parseNominal($ms['harga_jual']);
                }
                if (isset($ms['harga_beli']) && $ms['harga_beli'] !== '' && $ms['harga_beli'] !== null) {
                    $this->editProdukForm['multi_satuan'][$k]['harga_beli'] = $this->parseNominal($ms['harga_beli']);
                }
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
                barcode: isset($this->editProdukForm['barcode']) ? trim((string) $this->editProdukForm['barcode']) : null,
                metodeHargaBeli: $this->editProdukForm['metode_harga_beli'] ?? 'manual',
                marginPersen: ! empty($this->editProdukForm['margin_persen']) ? (float) $this->editProdukForm['margin_persen'] : null,
                multiSatuan: $this->editProdukForm['multi_satuan'] ?? [],
                supplierId: ! empty($this->editProdukForm['supplier_id']) ? (int) $this->editProdukForm['supplier_id'] : null,
                sku: isset($this->editProdukForm['sku']) ? trim((string) $this->editProdukForm['sku']) : null
            );

            // Simpan perubahan lokasi rak stok per gudang
            if (! empty($this->editStokRaks)) {
                foreach ($this->editStokRaks as $stokId => $rId) {
                    StokItem::where('id', $stokId)->update(['rak_id' => $rId ?: null]);
                }
            }

            $this->showEditProdukModal = false;
            $this->editFotoUploads = [];
            $this->editFotoKameraUpload = null;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Master produk berhasil diperbarui']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function addMultiSatuan(string $target = 'create'): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        $base = $this->{$formKey}['satuan_kode'] ?? 'pcs';

        $currentRows = $this->{$formKey}['multi_satuan'] ?? [];
        $lastRow = ! empty($currentRows) ? end($currentRows) : null;
        $satuanAsal = ! empty($lastRow['satuan_kode']) ? $lastRow['satuan_kode'] : $base;

        $this->{$formKey}['multi_satuan'][] = [
            'satuan_kode' => '',
            'satuan_asal' => $satuanAsal,
            'pengali' => 10,
            'faktor_konversi' => 10,
            'mode_harga' => 'auto',
            'diskon_persen' => 0,
            'harga_beli' => null,
            'harga_jual' => null,
            'barcode' => '',
            'is_satuan_beli' => empty($currentRows),
            'is_satuan_jual' => true,
            'is_custom' => false,
        ];

        $this->hitungKonversiMultiSatuan($target);
    }

    public function setModeHargaMultiSatuan(string $target, int $index, string $mode): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        if (isset($this->{$formKey}['multi_satuan'][$index])) {
            $this->{$formKey}['multi_satuan'][$index]['mode_harga'] = $mode;
            $this->hitungKonversiMultiSatuan($target);
        }
    }

    public function pilihSatuanUnit(string $target, int $index, string $val): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        if ($val === '__custom__') {
            $this->{$formKey}['multi_satuan'][$index]['is_custom'] = true;
            $this->{$formKey}['multi_satuan'][$index]['satuan_kode'] = '';
        } else {
            $this->{$formKey}['multi_satuan'][$index]['is_custom'] = false;
            $this->{$formKey}['multi_satuan'][$index]['satuan_kode'] = strtolower(trim($val));
            $this->hitungKonversiMultiSatuan($target);
        }
    }

    public function batalCustomSatuan(string $target, int $index): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        $this->{$formKey}['multi_satuan'][$index]['is_custom'] = false;
        $this->{$formKey}['multi_satuan'][$index]['satuan_kode'] = '';
        $this->hitungKonversiMultiSatuan($target);
    }

    public function toggleSatuanBeli(string $target, int $index): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        $current = ! empty($this->{$formKey}['multi_satuan'][$index]['is_satuan_beli']);
        foreach ($this->{$formKey}['multi_satuan'] as $i => $row) {
            $this->{$formKey}['multi_satuan'][$i]['is_satuan_beli'] = ($i === $index && ! $current);
        }
    }

    public function removeMultiSatuan(int $index, string $target = 'create'): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        unset($this->{$formKey}['multi_satuan'][$index]);
        $this->{$formKey}['multi_satuan'] = array_values($this->{$formKey}['multi_satuan']);
        $this->hitungKonversiMultiSatuan($target);
    }

    public function hitungKonversiMultiSatuan(string $target = 'create'): void
    {
        $formKey = $target === 'edit' ? 'editProdukForm' : 'produkForm';
        $base = strtolower(trim($this->{$formKey}['satuan_kode'] ?? 'pcs')) ?: 'pcs';
        $hargaJualDasar = $this->parseNominal($this->{$formKey}['harga_jual_retail'] ?? 0);
        $hargaBeliDasar = $this->parseNominal($this->{$formKey}['harga_beli'] ?? 0);

        $factors = [$base => 1.0];

        if (empty($this->{$formKey}['multi_satuan'])) {
            return;
        }

        foreach ($this->{$formKey}['multi_satuan'] as $idx => $row) {
            $kode = strtolower(trim($row['satuan_kode'] ?? ''));
            $asal = strtolower(trim($row['satuan_asal'] ?? $base)) ?: $base;

            if ($asal === $kode) {
                $asal = $base;
                $this->{$formKey}['multi_satuan'][$idx]['satuan_asal'] = $base;
            }

            $pengali = max(0.0001, (float) ($row['pengali'] ?? 1));

            $parentFactor = $factors[$asal] ?? 1.0;
            $faktorKonversi = round($pengali * $parentFactor, 4);

            if ($kode) {
                $factors[$kode] = $faktorKonversi;
            }

            $this->{$formKey}['multi_satuan'][$idx]['faktor_konversi'] = $faktorKonversi;

            // Mode perhitungan harga multi satuan: auto vs manual
            $modeHarga = $row['mode_harga'] ?? 'auto';
            if ($modeHarga === 'auto') {
                $diskon = isset($row['diskon_persen']) ? (float) $row['diskon_persen'] : 0.0;
                $totalKotor = $faktorKonversi * $hargaJualDasar;
                $potongan = ($totalKotor * max(0, min(100, $diskon))) / 100;
                $hargaFinal = max(0, round($totalKotor - $potongan));

                $this->{$formKey}['multi_satuan'][$idx]['harga_jual'] = $hargaFinal;
                $this->{$formKey}['multi_satuan'][$idx]['harga_beli'] = round($faktorKonversi * $hargaBeliDasar);
            }
        }
    }

    public function updatedProdukFormSatuanKode($value): void
    {
        $this->hitungKonversiMultiSatuan('create');
    }

    public function updatedEditProdukFormSatuanKode($value): void
    {
        $this->hitungKonversiMultiSatuan('edit');
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

        // 2. Cek riwayat transaksi/mutasi yang terhubung (hanya transaksi sah yang terjadi sejak produk dibuat)
        $prodCreatedAt = $produk->created_at;
        $validTime = fn ($q) => $prodCreatedAt ? $q->where('created_at', '>=', $prodCreatedAt->copy()->subSeconds(30)) : $q;

        $hasTransaksi = Schema::hasTable('transaksi_item') && DB::table('transaksi_item')->where('produk_id', $produkId)->where($validTime)->exists();
        $hasPo = Schema::hasTable('purchase_order_item') && DB::table('purchase_order_item')->where('produk_id', $produkId)->where($validTime)->exists();
        $hasServis = (Schema::hasTable('tiket_servis_item') && DB::table('tiket_servis_item')->where('produk_id', $produkId)->where($validTime)->exists())
            || (Schema::hasTable('servis_sparepart') && DB::table('servis_sparepart')->where('produk_id', $produkId)->where($validTime)->exists());
        $hasTransfer = Schema::hasTable('stok_transfer_item') && DB::table('stok_transfer_item')->where('produk_id', $produkId)->where($validTime)->exists();
        $hasOpname = Schema::hasTable('stok_opname_item') && DB::table('stok_opname_item')->where('produk_id', $produkId)->where($validTime)->exists();

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
                if (Schema::hasTable('sid_import_maps')) {
                    DB::table('sid_import_maps')->where('entity_type', 'produk')->where('entity_id', $produkId)->delete();
                }

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
        $defaultBrandId = null;
        if ($tipe === 'tipe_hp') {
            $defaultMerk = $this->showEditProdukModal
                ? ($this->editKompatibelMerekHp ?: ($this->editProdukForm['brand_kompatibel'] ?? ''))
                : ($this->kompatibelMerekHp ?: ($this->produkForm['brand_kompatibel'] ?? ''));
            $defaultModel = $this->showEditProdukModal
                ? ($this->editProdukForm['model_kompatibel'] ?? '')
                : ($this->produkForm['model_kompatibel'] ?? '');

            $this->quickAddSelectedMerk = $defaultMerk;
            $this->quickAddSelectedModel = $defaultModel;
        } else {
            $this->quickAddSelectedMerk = '';
            $this->quickAddSelectedModel = '';
            if ($tipe === 'kualitas') {
                $defaultBrandId = $this->showEditProdukModal
                    ? ($this->editProdukForm['brand_id'] ?? null)
                    : ($this->produkForm['brand_id'] ?? null);
            }
        }

        $this->quickAddForm = [
            'nama' => '',
            'kode' => '',
            'parent_id' => null,
            'keterangan' => '',
            'brand_id' => $defaultBrandId,
            'merk' => $defaultMerk,
            'model' => $defaultModel,
            'telepon' => '',
            'kontak' => '',
            'alamat' => '',
        ];
        $this->showQuickAddModal = true;
    }

    public function updatedQuickAddSelectedMerk($value): void
    {
        if ($value && $value !== '__custom__') {
            $this->quickAddForm['merk'] = $value;
        } elseif ($value === '__custom__') {
            $this->quickAddForm['merk'] = '';
        }
        $this->quickAddSelectedModel = '';
        $this->quickAddForm['model'] = '';
    }

    public function updatedQuickAddSelectedModel($value): void
    {
        if ($value && $value !== '__custom__') {
            $this->quickAddForm['model'] = $value;
        } elseif ($value === '__custom__') {
            $this->quickAddForm['model'] = '';
        }
    }

    public function simpanQuickAdd(): void
    {
        if ($this->quickAddTipe === 'tipe_hp') {
            if (! empty($this->quickAddSelectedMerk) && $this->quickAddSelectedMerk !== '__custom__' && empty($this->quickAddForm['merk'])) {
                $this->quickAddForm['merk'] = $this->quickAddSelectedMerk;
            }
            if (! empty($this->quickAddSelectedModel) && $this->quickAddSelectedModel !== '__custom__' && empty($this->quickAddForm['model'])) {
                $this->quickAddForm['model'] = $this->quickAddSelectedModel;
            }

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
                    $cur = array_map('intval', (array) ($this->editProdukForm['tipe_hp_ids'] ?? []));
                    if (! in_array($tipe->id, $cur, true)) {
                        $cur[] = $tipe->id;
                    }
                    $this->editProdukForm['tipe_hp_ids'] = array_values(array_unique($cur));
                    $this->editKompatibelMerekHp = $tipe->merk;
                    $this->editKompatibelTipeHpId = $tipe->id;
                    if (empty($this->editProdukForm['brand_kompatibel'])) {
                        $this->editProdukForm['brand_kompatibel'] = $tipe->merk;
                    }
                    if (empty($this->editProdukForm['model_kompatibel'])) {
                        $this->editProdukForm['model_kompatibel'] = $tipe->model;
                    }
                } else {
                    $cur = array_map('intval', (array) ($this->produkForm['tipe_hp_ids'] ?? []));
                    if (! in_array($tipe->id, $cur, true)) {
                        $cur[] = $tipe->id;
                    }
                    $this->produkForm['tipe_hp_ids'] = array_values(array_unique($cur));
                    $this->kompatibelMerekHp = $tipe->merk;
                    $this->kompatibelTipeHpId = $tipe->id;
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
                $kat = KategoriProduk::firstOrCreate(
                    ['nama' => $nama],
                    ['parent_id' => $this->quickAddForm['parent_id'] ?: null, 'is_active' => true]
                );
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['kategori_id'] = $kat->id;
                    $this->editProdukForm['kategori'] = $kat->nama;
                } else {
                    $this->produkForm['kategori_id'] = $kat->id;
                    $this->produkForm['kategori'] = $kat->nama;
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
                $brandId = ! empty($this->quickAddForm['brand_id']) ? (int) $this->quickAddForm['brand_id'] : null;
                $kualitas = KualitasProduk::firstOrCreate(
                    ['nama' => $nama, 'brand_id' => $brandId],
                    ['keterangan' => $this->quickAddForm['keterangan'] ?: null, 'is_active' => true]
                );
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['kualitas_id'] = $kualitas->id;
                } else {
                    $this->produkForm['kualitas_id'] = $kualitas->id;
                }
                $pesan = "Kualitas '{$nama}'".($brandId ? ' untuk Brand terkait' : ' (Universal)').' berhasil ditambahkan';
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

            case 'supplier':
                $supplier = Supplier::firstOrCreate(
                    ['nama' => $nama],
                    [
                        'telepon' => ! empty($this->quickAddForm['telepon']) ? trim($this->quickAddForm['telepon']) : null,
                        'kontak' => ! empty($this->quickAddForm['kontak']) ? trim($this->quickAddForm['kontak']) : null,
                        'alamat' => ! empty($this->quickAddForm['alamat']) ? trim($this->quickAddForm['alamat']) : null,
                        'termin_hari' => 30,
                        'is_active' => true,
                    ]
                );
                if ($this->showEditProdukModal) {
                    $this->editProdukForm['supplier_id'] = $supplier->id;
                } else {
                    $this->produkForm['supplier_id'] = $supplier->id;
                }
                $pesan = "Supplier '{$nama}' berhasil didaftarkan dan dipilih";
                break;

            default:
                $pesan = 'Data berhasil ditambahkan';
        }

        $this->showQuickAddModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => $pesan]);
    }

    public function orderPo(int $produkId): void
    {
        session()->flash('wms_order_produk_id', $produkId);
        $this->dispatch('wms-pindah-tab', tab: 'po');
        $this->dispatch('order-po-produk', produkId: $produkId);
    }

    public function openTambahStokModal(int $produkId): void
    {
        $this->orderPo($produkId);
    }

    public function openSaldoAwalModal(int $produkId): void
    {
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Input saldo awal produk hanya boleh dilakukan oleh Super Admin / Owner']);

            return;
        }

        $cabangId = session('cabang_id');
        $produk = Produk::withCount('stokLogs')
            ->withSum([
                'stokItems as total_stok' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId))),
            ], 'jumlah')
            ->findOrFail($produkId);

        if ((int) $produk->stok_logs_count > 0 || (int) $produk->total_stok > 0) {
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Produk '{$produk->nama}' sudah memiliki riwayat mutasi / stok fisik. Penambahan stok berikutnya wajib melalui Purchase Order (PO) atau Opname.",
            ]);

            return;
        }

        $gudangsCabang = Gudang::where('cabang_id', $cabangId ?: 1)->where('is_active', true)->get();
        $defaultGudangId = $gudangsCabang->firstWhere('kode', 'GD-TOKO')?->id ?? $gudangsCabang->first()?->id;

        $this->saldoAwalProdukId = $produk->id;
        $this->saldoAwalProdukNama = $produk->nama;
        $this->saldoAwalProdukSn = (bool) $produk->sn;
        $this->saldoAwalForm = [
            'gudang_id' => $defaultGudangId,
            'rak_id' => null,
            'qty' => 1,
            'harga_beli' => (float) $produk->harga_beli,
            'sn' => '',
            'keterangan' => 'Saldo awal inventori produk',
        ];

        $this->showSaldoAwalModal = true;
    }

    public function simpanSaldoAwal(): void
    {
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin / Owner yang boleh memasukkan saldo awal']);

            return;
        }

        if (! $this->saldoAwalProdukId) {
            return;
        }

        $produk = Produk::withCount('stokLogs')->findOrFail($this->saldoAwalProdukId);

        if ((int) $produk->stok_logs_count > 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk ini sudah memiliki mutasi stok. Saldo awal ditolak.']);
            $this->showSaldoAwalModal = false;

            return;
        }

        $this->validate([
            'saldoAwalForm.gudang_id' => 'required|exists:gudang,id',
            'saldoAwalForm.qty' => 'required|integer|min:1',
            'saldoAwalForm.harga_beli' => 'required|numeric|min:0',
        ]);

        $qty = (int) $this->saldoAwalForm['qty'];
        $snList = null;

        if ($this->saldoAwalProdukSn) {
            $snRaw = trim((string) ($this->saldoAwalForm['sn'] ?? ''));
            if ($snRaw === '') {
                $this->dispatch('alert', ['type' => 'error', 'message' => "Produk SN aktif: wajib menginput {$qty} nomor seri unik (pisahkan per baris/koma)."]);

                return;
            }

            $snParts = preg_split('/[\r\n,]+/', $snRaw);
            $snList = array_values(array_filter(array_map('trim', $snParts)));

            if (count($snList) !== $qty) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Jumlah SN ('.count($snList).") tidak sesuai dengan Qty ({$qty})."]);

                return;
            }

            if (count($snList) !== count(array_unique($snList))) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Terdapat duplikasi nomor seri dalam daftar yang diinput.']);

                return;
            }
        }

        try {
            $variant = $produk->skuVariants()->first();
            $keterangan = trim($this->saldoAwalForm['keterangan']) ?: "Saldo awal inventori produk: {$produk->nama}";

            app(ProdukService::class)->inisialisasiStokAwal(
                produkId: $produk->id,
                variantId: $variant?->id,
                gudangId: (int) $this->saldoAwalForm['gudang_id'],
                qty: $qty,
                hargaBeli: (float) $this->saldoAwalForm['harga_beli'],
                keterangan: $keterangan,
                userId: auth()->id(),
                rakId: ! empty($this->saldoAwalForm['rak_id']) ? (int) $this->saldoAwalForm['rak_id'] : null,
                snList: $snList
            );

            // Update harga beli master jika disesuaikan saat input saldo awal
            if ((float) $this->saldoAwalForm['harga_beli'] > 0 && (float) $this->saldoAwalForm['harga_beli'] !== (float) $produk->harga_beli) {
                $produk->update(['harga_beli' => (float) $this->saldoAwalForm['harga_beli']]);
            }

            $this->showSaldoAwalModal = false;
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Saldo awal {$qty} {$produk->satuan} berhasil diinisialisasi + Jurnal Persediaan vs Modal Pemilik berhasil diposting.",
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal simpan saldo awal: '.$e->getMessage()]);
        }
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

    public function updatingFilterTierHarga()
    {
        $this->resetPage();
    }

    public function updatingFilterKualitasId()
    {
        $this->resetPage();
    }

    public function updatedFilterTipeHpId($val): void
    {
        $this->resetPage();
        if (! empty($val)) {
            $th = TipeHp::find($val);
            if ($th) {
                $this->filterBrandKompatibel = $th->merk;
                $this->filterModelKompatibel = $th->model;
            }
        }
    }

    public function updatedFilterBrandKompatibel($val): void
    {
        $this->resetPage();
        if (! empty($val)) {
            if ($this->filterTipeHpId) {
                $th = TipeHp::find($this->filterTipeHpId);
                if ($th && strcasecmp($th->merk, $val) !== 0) {
                    $this->filterTipeHpId = null;
                }
            }
            if ($this->filterModelKompatibel) {
                $exists = TipeHp::where('merk', $val)->where('model', $this->filterModelKompatibel)->exists()
                    || Produk::where('brand_kompatibel', $val)->where('model_kompatibel', $this->filterModelKompatibel)->exists();
                if (! $exists) {
                    $this->filterModelKompatibel = null;
                }
            }
        }
    }

    public function updatedFilterModelKompatibel($val): void
    {
        $this->resetPage();
        if (! empty($val) && empty($this->filterBrandKompatibel)) {
            $th = TipeHp::where('model', $val)->first();
            if ($th) {
                $this->filterBrandKompatibel = $th->merk;
            } else {
                $prod = Produk::where('model_kompatibel', $val)->first();
                if ($prod && $prod->brand_kompatibel) {
                    $this->filterBrandKompatibel = $prod->brand_kompatibel;
                }
            }
        }
    }

    public function updatingFilterKondisi()
    {
        $this->resetPage();
    }

    public function updatingFilterSatuan()
    {
        $this->resetPage();
    }

    public function updatingFilterAbcClass()
    {
        $this->resetPage();
    }

    public function updatingFilterHanyaReadyStok()
    {
        $this->resetPage();
    }

    public function updatingFilterSupplierId()
    {
        $this->resetPage();
    }

    public function updatingFilterSku()
    {
        $this->resetPage();
    }

    public function updatingFilterMinHarga()
    {
        $this->resetPage();
    }

    public function updatingFilterMaxHarga()
    {
        $this->resetPage();
    }

    public function updatingFilterMinHargaBeli()
    {
        $this->resetPage();
    }

    public function updatingFilterMaxHargaBeli()
    {
        $this->resetPage();
    }

    public function updatingFilterStokStatus()
    {
        $this->resetPage();
    }

    public function updatingFilterStatusAktif()
    {
        $this->resetPage();
    }

    public function updatingFilterGudangId()
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value): void
    {
        $currentPageIds = array_map('intval', $this->getCurrentPageProdukIds());
        $currentSelected = array_map('intval', $this->selectedProdukIds);

        if ($value) {
            $this->selectedProdukIds = array_values(array_unique(array_merge($currentSelected, $currentPageIds)));
        } else {
            $this->selectedProdukIds = array_values(array_diff($currentSelected, $currentPageIds));
        }
    }

    public function sortByColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = in_array($column, ['harga_beli', 'harga_jual', 'harga_jual_retail', 'stok', 'total_stok', 'created_at', 'terbaru']) ? 'desc' : 'asc';
        }

        $this->sortPreset = "{$this->sortBy}_{$this->sortDir}";
        $this->resetPage();
    }

    public function updatedSortPreset($value): void
    {
        $parts = explode('_', (string) $value);
        $dir = array_pop($parts);
        $col = implode('_', $parts);

        if (in_array($dir, ['asc', 'desc'])) {
            $this->sortDir = $dir;
            $this->sortBy = $col ?: 'nama';
        }
        $this->resetPage();
    }

    public function getActiveFilterCountProperty(): int
    {
        $count = 0;
        if (! empty($this->produkSearch)) {
            $count++;
        }
        if ($this->filterKategoriId) {
            $count++;
        }
        if ($this->filterBrandId) {
            $count++;
        }
        if ($this->filterKualitasId) {
            $count++;
        }
        if ($this->filterBrandKompatibel) {
            $count++;
        }
        if ($this->filterModelKompatibel) {
            $count++;
        }
        if ($this->filterTipeHpId && ! $this->filterBrandKompatibel && ! $this->filterModelKompatibel) {
            $count++;
        }
        if ($this->filterKondisi) {
            $count++;
        }
        if ($this->filterSatuan) {
            $count++;
        }
        if ($this->filterAbcClass) {
            $count++;
        }
        if ($this->filterHanyaReadyStok) {
            $count++;
        }
        if ($this->filterTierHarga !== 'retail') {
            $count++;
        }
        if (! empty($this->filterSku)) {
            $count++;
        }
        if ($this->filterMinHarga !== null && $this->filterMinHarga !== '') {
            $count++;
        }
        if ($this->filterMaxHarga !== null && $this->filterMaxHarga !== '') {
            $count++;
        }
        if ($this->filterMinHargaBeli !== null && $this->filterMinHargaBeli !== '') {
            $count++;
        }
        if ($this->filterMaxHargaBeli !== null && $this->filterMaxHargaBeli !== '') {
            $count++;
        }
        if ($this->filterStokStatus !== 'semua') {
            $count++;
        }
        if ($this->filterStatusAktif !== 'semua') {
            $count++;
        }
        if ($this->filterGudangId) {
            $count++;
        }
        if ($this->filterSupplierId) {
            $count++;
        }

        return $count;
    }

    public function resetFilterKolom(string $kolom): void
    {
        match ($kolom) {
            'produk' => [
                $this->produkSearch = '',
                $this->filterBrandId = null,
                $this->filterKategoriId = null,
                $this->filterKualitasId = null,
            ],
            'kategori' => $this->filterKategoriId = null,
            'brand' => [
                $this->filterBrandId = null,
                $this->filterKualitasId = null,
            ],
            'kompatibilitas' => [
                $this->filterTipeHpId = null,
                $this->filterBrandKompatibel = null,
                $this->filterModelKompatibel = null,
            ],
            'sku' => $this->filterSku = null,
            'harga' => [
                $this->filterMinHarga = null,
                $this->filterMaxHarga = null,
                $this->filterTierHarga = 'retail',
            ],
            'harga_beli' => [
                $this->filterMinHargaBeli = null,
                $this->filterMaxHargaBeli = null,
            ],
            'stok' => [
                $this->filterStokStatus = 'semua',
                $this->filterHanyaReadyStok = false,
            ],
            'gudang' => $this->filterGudangId = null,
            'reorder' => [
                $this->filterAbcClass = null,
            ],
            'spesifikasi' => [
                $this->filterKondisi = null,
                $this->filterSatuan = null,
            ],
            'supplier' => $this->filterSupplierId = null,
            'status' => $this->filterStatusAktif = 'semua',
            default => null,
        };
        $this->resetPage();
    }

    public function resetSemuaFilter(): void
    {
        $this->produkSearch = '';
        $this->filterKategoriId = null;
        $this->filterBrandId = null;
        $this->filterKualitasId = null;
        $this->filterTipeHpId = null;
        $this->filterBrandKompatibel = null;
        $this->filterModelKompatibel = null;
        $this->filterKondisi = null;
        $this->filterSatuan = null;
        $this->filterAbcClass = null;
        $this->filterSupplierId = null;
        $this->filterHanyaReadyStok = false;
        $this->filterTierHarga = 'retail';
        $this->filterSku = null;
        $this->filterMinHarga = null;
        $this->filterMaxHarga = null;
        $this->filterMinHargaBeli = null;
        $this->filterMaxHargaBeli = null;
        $this->filterStokStatus = 'semua';
        $this->filterStatusAktif = 'semua';
        $this->filterGudangId = null;
        $this->sortBy = 'nama';
        $this->sortDir = 'asc';
        $this->sortPreset = 'nama_asc';
        $this->resetPage();
    }

    public function selectAllMatching(): void
    {
        $this->selectedProdukIds = array_map('intval', $this->getFilteredProdukQuery()->pluck('id')->all());
        $this->selectAll = true;
    }

    public function clearSelected(): void
    {
        $this->selectedProdukIds = [];
        $this->selectAll = false;
    }

    public function getLabelTierAktifProperty(): string
    {
        if ($this->filterTierHarga === 'reseller') {
            return 'Reseller';
        }
        if ($this->filterTierHarga === 'agen') {
            return 'Agen';
        }
        if (str_starts_with($this->filterTierHarga, 'tier_')) {
            $tierId = (int) str_replace('tier_', '', $this->filterTierHarga);
            $tierObj = TierMembership::find($tierId);

            return $tierObj ? 'Tier '.$tierObj->nama : 'Tier Khusus';
        }

        return 'Retail Standar';
    }

    public function resolveHargaTierProduk(Produk $p, ?string $filterTier = null): array
    {
        $filterTier = $filterTier ?? $this->filterTierHarga;
        $hargaJual = (float) $p->harga_jual_retail;
        $hargaFinal = $hargaJual;
        $tierLabel = 'Retail Standar';

        if ($filterTier === 'reseller') {
            $tierLabel = 'Reseller';
            $ht = $p->hargaTier->first(fn ($h) => $h->tipe_konsumen === 'reseller' || ($h->is_reseller && ! $h->tier_membership_id));
            if ($ht) {
                if ($ht->nominal_tetap !== null) {
                    $hargaFinal = (float) $ht->nominal_tetap;
                } elseif ($ht->persen_diskon !== null) {
                    $hargaFinal = round($hargaJual * (1 - (float) $ht->persen_diskon / 100), 2);
                } elseif ($ht->harga !== null) {
                    $hargaFinal = (float) $ht->harga;
                }
            }
        } elseif ($filterTier === 'agen') {
            $tierLabel = 'Agen';
            $ht = $p->hargaTier->first(fn ($h) => $h->tipe_konsumen === 'agen');
            if ($ht) {
                if ($ht->nominal_tetap !== null) {
                    $hargaFinal = (float) $ht->nominal_tetap;
                } elseif ($ht->persen_diskon !== null) {
                    $hargaFinal = round($hargaJual * (1 - (float) $ht->persen_diskon / 100), 2);
                }
            }
        } elseif (str_starts_with($filterTier, 'tier_')) {
            $tierId = (int) str_replace('tier_', '', $filterTier);
            $tierObj = TierMembership::find($tierId);
            $tierLabel = $tierObj ? 'Tier '.$tierObj->nama : 'Tier Khusus';

            $ht = $p->hargaTier->first(fn ($h) => (int) $h->tier_membership_id === $tierId);
            if ($ht) {
                if ($ht->nominal_tetap !== null) {
                    $hargaFinal = (float) $ht->nominal_tetap;
                } elseif ($ht->persen_diskon !== null) {
                    $hargaFinal = round($hargaJual * (1 - (float) $ht->persen_diskon / 100), 2);
                } elseif ($ht->harga !== null) {
                    $hargaFinal = (float) $ht->harga;
                }
            } elseif ($tierObj && (float) $tierObj->diskon_persen > 0) {
                $diskon = round(($hargaJual * (float) $tierObj->diskon_persen) / 100, 2);
                $hargaFinal = max(0, $hargaJual - $diskon);
            }
        }

        return [
            'harga' => $hargaFinal,
            'label' => $tierLabel,
        ];
    }

    public function copySingleProduk(int $produkId): void
    {
        $cabangId = session('cabang_id');
        $p = Produk::with([
            'skuVariants',
            'hargaTier.tierMembership',
            'kategoriRelasi',
            'brand',
            'kualitas',
            'tipeHps',
            'stokItems' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->with('rak'),
        ])->find($produkId);

        if (! $p) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak ditemukan.']);

            return;
        }

        $tierInfo = $this->resolveHargaTierProduk($p);
        $hargaFmt = 'Rp '.number_format($tierInfo['harga'], 0, ',', '.');
        $text = $this->formatTeksProduk($p, $this->salinOptions);

        $this->dispatch('salin-teks', teks: $text, message: "Harga '{$p->nama}' ({$hargaFmt}) berhasil disalin!");
    }

    public function openSalinDataModal(): void
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $this->showSalinDataModal = true;
    }

    public function closeSalinDataModal(): void
    {
        $this->showSalinDataModal = false;
    }

    public function formatTeksProduk(Produk $p, array $options): string
    {
        $hasNama = ! empty($options['nama']);
        $hasHarga = ! empty($options['harga']);
        $hasSku = ! empty($options['sku']);
        $hasKualitas = ! empty($options['kualitas']);
        $hasKompatibilitas = ! empty($options['kompatibilitas']);
        $hasKondisi = ! empty($options['kondisi']);
        $hasStok = ! empty($options['stok']);
        $hasRak = ! empty($options['rak']);
        $hasKategori = ! empty($options['kategori']);
        $hasBrand = ! empty($options['brand']);
        $hasGaransi = ! empty($options['garansi']);
        $hasDeskripsi = ! empty($options['deskripsi']);

        $tierInfo = $this->resolveHargaTierProduk($p);
        $hargaFmt = 'Rp '.number_format($tierInfo['harga'], 0, ',', '.');

        // Jika hanya nama & harga yang dipilih: format klasik standar (nama - harga)
        if ($hasNama && $hasHarga && ! $hasSku && ! $hasStok && ! $hasRak && ! $hasKategori && ! $hasBrand && ! $hasGaransi && ! $hasKompatibilitas && ! $hasKualitas && ! $hasKondisi && ! $hasDeskripsi) {
            return "{$p->nama} - {$hargaFmt}";
        }

        $sku = $p->relationLoaded('skuVariants') ? $p->skuVariants->first()?->sku : null;
        $sku = $sku ?? $p->barcode ?? ($p->getAttribute('sku') ?: null);

        $stok = $p->relationLoaded('stokItems') ? $p->stokItems->sum('jumlah') : $p->stok_total;
        $raks = $p->relationLoaded('stokItems') ? $p->stokItems->map(fn ($s) => $s->rak ? $s->rak->kode : null)->filter()->unique()->implode(', ') : null;
        $brand = $p->brand?->nama ?? (is_string($p->brand) ? $p->brand : null);
        $kat = $p->kategoriRelasi?->nama ?? $p->kategori;
        $kualitas = $p->kualitas?->nama ?? ($p->kualitas_id ? KualitasProduk::where('id', $p->kualitas_id)->value('nama') : null);

        $kompatibel = trim(($p->brand_kompatibel ?? '').' '.($p->model_kompatibel ?? ''));
        if (empty($kompatibel) && ! empty($p->kompatibilitas_hp) && is_array($p->kompatibilitas_hp)) {
            $kompatibel = collect($p->kompatibilitas_hp)->map(fn ($k) => trim(($k['merk'] ?? '').' '.($k['model'] ?? '')))->filter()->implode(', ');
        }
        if (empty($kompatibel) && $p->relationLoaded('tipeHps') && $p->tipeHps->isNotEmpty()) {
            $kompatibel = $p->tipeHps->map(fn ($t) => trim("{$t->merk} {$t->model}"))->filter()->implode(', ');
        }

        $kondisi = ! empty($p->kondisi) ? ucfirst($p->kondisi) : null;
        $garansi = ! empty($p->garansi) ? $p->garansi.' bln' : null;
        $deskripsi = ! empty($p->deskripsi) ? trim(strip_tags($p->deskripsi)) : null;

        // Susun baris rapi dan estetik untuk WhatsApp
        $lines = [];

        $titleParts = [];
        if ($hasNama) {
            $titleParts[] = "*{$p->nama}*";
        }
        if ($hasSku && ! empty($sku)) {
            $titleParts[] = "[{$sku}]";
        }
        if (! empty($titleParts)) {
            $lines[] = '📦 '.implode(' ', $titleParts);
        }

        if ($hasHarga) {
            $lines[] = "💰 Harga: *{$hargaFmt}*";
        }
        if ($hasKualitas && ! empty($kualitas)) {
            $lines[] = "⭐ Kualitas: {$kualitas}";
        }
        if ($hasKompatibilitas && ! empty($kompatibel)) {
            $lines[] = "📱 Kompatibel: {$kompatibel}";
        }
        if ($hasKondisi && ! empty($kondisi)) {
            $lines[] = "⚙️ Kondisi: {$kondisi}";
        }
        if ($hasStok) {
            $lines[] = "📊 Stok: {$stok}";
        }
        if ($hasRak && ! empty($raks)) {
            $lines[] = "📍 Rak: {$raks}";
        }
        if ($hasBrand && ! empty($brand)) {
            $lines[] = "🏷️ Brand: {$brand}";
        }
        if ($hasKategori && ! empty($kat)) {
            $lines[] = "📁 Kategori: {$kat}";
        }
        if ($hasGaransi && ! empty($garansi)) {
            $lines[] = "🛡️ Garansi: {$garansi}";
        }
        if ($hasDeskripsi && ! empty($deskripsi)) {
            $lines[] = "📝 Catatan: {$deskripsi}";
        }

        return implode("\n", $lines) ?: $p->nama;
    }

    public function eksekusiSalinData(): void
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $cabangId = session('cabang_id');
        $produks = Produk::with([
            'skuVariants',
            'hargaTier.tierMembership',
            'kategoriRelasi',
            'brand',
            'kualitas',
            'tipeHps',
            'stokItems' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->with('rak'),
        ])
            ->whereIn('id', $this->selectedProdukIds)
            ->orderBy('nama')
            ->get();

        if ($produks->isEmpty()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk terpilih tidak ditemukan.']);

            return;
        }

        $isTsv = ($this->salinOptions['format'] ?? 'teks') === 'tsv';
        $lines = [];

        if ($isTsv) {
            $headers = [];
            if (! empty($this->salinOptions['nama'])) {
                $headers[] = 'Nama Produk';
            }
            if (! empty($this->salinOptions['sku'])) {
                $headers[] = 'SKU';
            }
            if (! empty($this->salinOptions['harga'])) {
                $headers[] = 'Harga';
            }
            if (! empty($this->salinOptions['kualitas'])) {
                $headers[] = 'Kualitas';
            }
            if (! empty($this->salinOptions['kompatibilitas'])) {
                $headers[] = 'Kompatibilitas';
            }
            if (! empty($this->salinOptions['kondisi'])) {
                $headers[] = 'Kondisi';
            }
            if (! empty($this->salinOptions['stok'])) {
                $headers[] = 'Stok Ready';
            }
            if (! empty($this->salinOptions['rak'])) {
                $headers[] = 'Lokasi Rak';
            }
            if (! empty($this->salinOptions['kategori'])) {
                $headers[] = 'Kategori';
            }
            if (! empty($this->salinOptions['brand'])) {
                $headers[] = 'Brand';
            }
            if (! empty($this->salinOptions['garansi'])) {
                $headers[] = 'Garansi';
            }
            if (! empty($this->salinOptions['deskripsi'])) {
                $headers[] = 'Deskripsi';
            }
            $lines[] = implode("\t", $headers);

            foreach ($produks as $p) {
                $cols = [];
                $tierInfo = $this->resolveHargaTierProduk($p);
                $sku = $p->relationLoaded('skuVariants') ? $p->skuVariants->first()?->sku : null;
                $sku = $sku ?? $p->barcode ?? ($p->getAttribute('sku') ?: null);

                $kualitas = $p->kualitas?->nama ?? ($p->kualitas_id ? KualitasProduk::where('id', $p->kualitas_id)->value('nama') : '-');
                $kompatibel = trim(($p->brand_kompatibel ?? '').' '.($p->model_kompatibel ?? ''));
                if (empty($kompatibel) && ! empty($p->kompatibilitas_hp) && is_array($p->kompatibilitas_hp)) {
                    $kompatibel = collect($p->kompatibilitas_hp)->map(fn ($k) => trim(($k['merk'] ?? '').' '.($k['model'] ?? '')))->filter()->implode(', ');
                }
                if (empty($kompatibel) && $p->relationLoaded('tipeHps') && $p->tipeHps->isNotEmpty()) {
                    $kompatibel = $p->tipeHps->map(fn ($t) => trim("{$t->merk} {$t->model}"))->filter()->implode(', ');
                }

                if (! empty($this->salinOptions['nama'])) {
                    $cols[] = $p->nama;
                }
                if (! empty($this->salinOptions['sku'])) {
                    $cols[] = $sku ?? '-';
                }
                if (! empty($this->salinOptions['harga'])) {
                    $cols[] = (string) $tierInfo['harga'];
                }
                if (! empty($this->salinOptions['kualitas'])) {
                    $cols[] = $kualitas ?: '-';
                }
                if (! empty($this->salinOptions['kompatibilitas'])) {
                    $cols[] = $kompatibel ?: '-';
                }
                if (! empty($this->salinOptions['kondisi'])) {
                    $cols[] = $p->kondisi ? ucfirst($p->kondisi) : '-';
                }
                if (! empty($this->salinOptions['stok'])) {
                    $cols[] = (string) $p->stokItems->sum('jumlah');
                }
                if (! empty($this->salinOptions['rak'])) {
                    $rak = $p->stokItems->map(fn ($s) => $s->rak ? $s->rak->kode : null)->filter()->unique()->implode(', ');
                    $cols[] = $rak ?: '-';
                }
                if (! empty($this->salinOptions['kategori'])) {
                    $cols[] = $p->kategoriRelasi?->nama ?? $p->kategori ?? '-';
                }
                if (! empty($this->salinOptions['brand'])) {
                    $cols[] = $p->brand?->nama ?? (is_string($p->brand) ? $p->brand : '-');
                }
                if (! empty($this->salinOptions['garansi'])) {
                    $cols[] = $p->garansi ? $p->garansi.' bln' : '-';
                }
                if (! empty($this->salinOptions['deskripsi'])) {
                    $cols[] = $p->deskripsi ? str_replace(["\r", "\n", "\t"], ' ', strip_tags($p->deskripsi)) : '-';
                }
                $lines[] = implode("\t", $cols);
            }
        } else {
            foreach ($produks as $p) {
                $lines[] = $this->formatTeksProduk($p, $this->salinOptions);
            }
        }

        $hasExtraOptions = ! empty($this->salinOptions['sku'])
            || ! empty($this->salinOptions['stok'])
            || ! empty($this->salinOptions['rak'])
            || ! empty($this->salinOptions['kualitas'])
            || ! empty($this->salinOptions['kompatibilitas'])
            || ! empty($this->salinOptions['kondisi'])
            || ! empty($this->salinOptions['kategori'])
            || ! empty($this->salinOptions['brand'])
            || ! empty($this->salinOptions['garansi'])
            || ! empty($this->salinOptions['deskripsi']);

        $separator = $isTsv ? "\n" : ($hasExtraOptions ? "\n\n" : "\n");
        $text = implode($separator, $lines);

        $this->dispatch('salin-teks', teks: $text, message: "{$produks->count()} data produk berhasil disalin ke clipboard!");
        $this->showSalinDataModal = false;
    }

    public function salinHargaTerpilih(): void
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $cabangId = session('cabang_id');
        $produks = Produk::with([
            'skuVariants',
            'hargaTier.tierMembership',
            'kategoriRelasi',
            'brand',
            'kualitas',
            'tipeHps',
            'stokItems' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->with('rak'),
        ])
            ->whereIn('id', $this->selectedProdukIds)
            ->orderBy('nama')
            ->get();

        if ($produks->isEmpty()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk terpilih tidak ditemukan.']);

            return;
        }

        $lines = [];
        foreach ($produks as $p) {
            $lines[] = $this->formatTeksProduk($p, $this->salinOptions);
        }

        $hasExtraOptions = ! empty($this->salinOptions['sku'])
            || ! empty($this->salinOptions['stok'])
            || ! empty($this->salinOptions['rak'])
            || ! empty($this->salinOptions['kualitas'])
            || ! empty($this->salinOptions['kompatibilitas'])
            || ! empty($this->salinOptions['kondisi'])
            || ! empty($this->salinOptions['kategori'])
            || ! empty($this->salinOptions['brand'])
            || ! empty($this->salinOptions['garansi'])
            || ! empty($this->salinOptions['deskripsi']);

        $text = implode($hasExtraOptions ? "\n\n" : "\n", $lines);

        $this->dispatch('salin-teks', teks: $text, message: "{$produks->count()} harga produk berhasil disalin ke clipboard!");
    }

    public function cetakBarcodeTerpilih(): void
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $ids = implode(',', $this->selectedProdukIds);
        $url = route('barcode.print').'?ids='.$ids;
        $this->dispatch('buka-tab-baru', url: $url);
    }

    public function hapusProdukMasal(): void
    {
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin / Owner yang berhak menghapus master data produk.']);

            return;
        }

        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih produk yang akan dihapus terlebih dahulu.']);

            return;
        }

        $produks = Produk::whereIn('id', $this->selectedProdukIds)->get();
        if ($produks->isEmpty()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak ada produk valid yang ditemukan.']);

            return;
        }

        $countPermanen = 0;
        $countNonaktif = 0;
        $countGagalStok = 0;
        $imgService = app(ProductImageService::class);

        foreach ($produks as $produk) {
            $produkId = $produk->id;

            // 1. Cek sisa stok fisik
            $stokFisik = (int) StokItem::where('produk_id', $produkId)->sum('jumlah');
            if ($stokFisik > 0) {
                $countGagalStok++;

                continue;
            }

            // 2. Cek riwayat transaksi/mutasi (hanya transaksi sah yang terjadi sejak produk dibuat)
            $prodCreatedAt = $produk->created_at;
            $validTime = fn ($q) => $prodCreatedAt ? $q->where('created_at', '>=', $prodCreatedAt->copy()->subSeconds(30)) : $q;

            $hasTransaksi = Schema::hasTable('transaksi_item') && DB::table('transaksi_item')->where('produk_id', $produkId)->where($validTime)->exists();
            $hasPo = Schema::hasTable('purchase_order_item') && DB::table('purchase_order_item')->where('produk_id', $produkId)->where($validTime)->exists();
            $hasServis = (Schema::hasTable('tiket_servis_item') && DB::table('tiket_servis_item')->where('produk_id', $produkId)->where($validTime)->exists())
                || (Schema::hasTable('servis_sparepart') && DB::table('servis_sparepart')->where('produk_id', $produkId)->where($validTime)->exists());
            $hasTransfer = Schema::hasTable('stok_transfer_item') && DB::table('stok_transfer_item')->where('produk_id', $produkId)->where($validTime)->exists();
            $hasOpname = Schema::hasTable('stok_opname_item') && DB::table('stok_opname_item')->where('produk_id', $produkId)->where($validTime)->exists();

            if ($hasTransaksi || $hasPo || $hasServis || $hasTransfer || $hasOpname) {
                $produk->update(['is_active' => false]);
                activity()
                    ->performedOn($produk)
                    ->causedBy(auth()->user())
                    ->log("Produk '{$produk->nama}' dinonaktifkan via aksi masal karena memiliki riwayat transaksi/mutasi.");
                $countNonaktif++;

                continue;
            }

            // 3. Hapus permanen
            try {
                DB::transaction(function () use ($produk, $produkId, $imgService) {
                    HargaTier::where('produk_id', $produkId)->delete();
                    StokItem::where('produk_id', $produkId)->delete();
                    SkuVariant::where('produk_id', $produkId)->delete();
                    if (Schema::hasTable('sid_import_maps')) {
                        DB::table('sid_import_maps')->where('entity_type', 'produk')->where('entity_id', $produkId)->delete();
                    }

                    if (! empty($produk->foto)) {
                        $fotos = is_array($produk->foto) ? $produk->foto : json_decode((string) $produk->foto, true);
                        if (is_array($fotos)) {
                            foreach ($fotos as $f) {
                                if (isset($f['url'])) {
                                    $imgService->hapusFoto($f['url'], $f['thumb'] ?? null);
                                }
                            }
                        }
                    }

                    $produk->delete();
                });
                $countPermanen++;
            } catch (\Exception $e) {
                // skip on error
            }
        }

        $this->selectedProdukIds = [];
        $this->selectAll = false;

        $msgParts = [];
        if ($countPermanen > 0) {
            $msgParts[] = "{$countPermanen} dihapus permanen";
        }
        if ($countNonaktif > 0) {
            $msgParts[] = "{$countNonaktif} dinonaktifkan (karena riwayat transaksi)";
        }
        if ($countGagalStok > 0) {
            $msgParts[] = "{$countGagalStok} dilewati (masih ada sisa stok fisik)";
        }

        $type = ($countPermanen > 0 || $countNonaktif > 0) ? 'success' : 'error';
        $pesan = ! empty($msgParts)
            ? 'Aksi masal selesai: '.implode(', ', $msgParts).'.'
            : 'Tidak ada produk yang berhasil diproses.';

        $this->dispatch('alert', ['type' => $type, 'message' => $pesan]);
    }

    public function hitungOtomatisParameterEdit(): void
    {
        if (! $this->editProdukId) {
            return;
        }

        $cabangId = session('cabang_id');
        $res = app(ProcurementService::class)->hitungParameterProdukOtomatis($this->editProdukId, $cabangId, 90);

        if ($res) {
            $this->editProdukForm['abc_class'] = $res['rekomendasi_abc'];
            $this->editProdukForm['reorder_point'] = $res['rekomendasi_rop'];
            $this->editProdukForm['min_stock'] = $res['rekomendasi_min'];
            $this->editProdukForm['max_stock'] = $res['rekomendasi_max'];
            $this->autoAbcKeterangan = $res['keterangan'] ?? '';

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Kelas {$res['rekomendasi_abc']} dihitung otomatis (ADU: {$res['adu']}/hari, ROP: {$res['rekomendasi_rop']})",
            ]);
        }
    }

    public function openBulkEditModal(): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengedit produk secara masal.')) {
            return;
        }

        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $this->bulkEditFields = [
            'kategori_id' => false,
            'brand_id' => false,
            'kualitas_id' => false,
            'kondisi' => false,
            'satuan' => false,
            'abc_class' => false,
            'is_active' => false,
            'harga_fleksibel' => false,
            'sn' => false,
            'penyesuaian_harga' => false,
            'rak_id' => false,
        ];

        $this->bulkEditForm = [
            'kategori_id' => null,
            'brand_id' => null,
            'kualitas_id' => null,
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'abc_class' => 'B',
            'is_active' => 1,
            'harga_fleksibel' => 0,
            'sn' => 0,
            'tipe_penyesuaian_harga' => 'margin_persen',
            'nilai_penyesuaian_harga' => 0,
            'rak_id' => null,
        ];

        $this->showBulkEditModal = true;
    }

    public function closeBulkEditModal(): void
    {
        $this->showBulkEditModal = false;
    }

    public function simpanBulkEdit(): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengedit produk secara masal.')) {
            return;
        }

        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $activeFields = array_filter($this->bulkEditFields);
        if (empty($activeFields)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Centang minimal satu kolom yang ingin diubah secara masal.']);

            return;
        }

        $produks = Produk::whereIn('id', $this->selectedProdukIds)->get();
        if ($produks->isEmpty()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak ada produk terpilih yang valid.']);

            return;
        }

        $kategoriNama = null;
        if (! empty($this->bulkEditFields['kategori_id']) && $this->bulkEditForm['kategori_id']) {
            $kategoriNama = KategoriProduk::find($this->bulkEditForm['kategori_id'])?->nama;
        }

        $brandKompatibel = null;
        if (! empty($this->bulkEditFields['brand_id']) && $this->bulkEditForm['brand_id']) {
            $brandKompatibel = Brand::find($this->bulkEditForm['brand_id'])?->nama;
        }

        $targetRakId = ! empty($activeFields['rak_id']) ? ($this->bulkEditForm['rak_id'] ?: null) : null;
        $pindahRak = ! empty($activeFields['rak_id']);
        $targetRak = $targetRakId ? Rak::find($targetRakId) : null;
        $cabangId = session('cabang_id');

        DB::transaction(function () use ($produks, $activeFields, $kategoriNama, $brandKompatibel, $pindahRak, $targetRak, $cabangId) {
            foreach ($produks as $p) {
                $updates = [];

                if (! empty($activeFields['kategori_id'])) {
                    $updates['kategori_id'] = $this->bulkEditForm['kategori_id'] ?: null;
                    if ($kategoriNama) {
                        $updates['kategori'] = $kategoriNama;
                    }
                }

                if (! empty($activeFields['brand_id'])) {
                    $updates['brand_id'] = $this->bulkEditForm['brand_id'] ?: null;
                    if ($brandKompatibel) {
                        $updates['brand_kompatibel'] = $brandKompatibel;
                    }
                }

                if (! empty($activeFields['kualitas_id'])) {
                    $updates['kualitas_id'] = $this->bulkEditForm['kualitas_id'] ?: null;
                }

                if (! empty($activeFields['kondisi'])) {
                    $updates['kondisi'] = $this->bulkEditForm['kondisi'];
                }

                if (! empty($activeFields['satuan'])) {
                    $updates['satuan'] = $this->bulkEditForm['satuan'];
                }

                if (! empty($activeFields['abc_class'])) {
                    $updates['abc_class'] = $this->bulkEditForm['abc_class'];
                }

                if (! empty($activeFields['is_active'])) {
                    $updates['is_active'] = (bool) $this->bulkEditForm['is_active'];
                }

                if (! empty($activeFields['harga_fleksibel'])) {
                    $updates['harga_fleksibel'] = (bool) $this->bulkEditForm['harga_fleksibel'];
                }

                if (! empty($activeFields['sn'])) {
                    $updates['sn'] = (bool) $this->bulkEditForm['sn'];
                }

                if (! empty($activeFields['penyesuaian_harga'])) {
                    $tipe = $this->bulkEditForm['tipe_penyesuaian_harga'] ?? 'margin_persen';
                    $nilai = (float) $this->bulkEditForm['nilai_penyesuaian_harga'];
                    $hargaBeli = (float) $p->harga_beli;
                    $hargaJual = (float) $p->harga_jual_retail;

                    if ($tipe === 'margin_persen') {
                        $updates['margin_persen'] = $nilai;
                        if ($hargaBeli > 0) {
                            $updates['harga_jual_retail'] = round($hargaBeli * (1 + ($nilai / 100)));
                        }
                    } elseif ($tipe === 'tambah_persen') {
                        $updates['harga_jual_retail'] = round($hargaJual * (1 + ($nilai / 100)));
                    } elseif ($tipe === 'potong_persen') {
                        $updates['harga_jual_retail'] = max(0, round($hargaJual * (1 - ($nilai / 100))));
                    } elseif ($tipe === 'tambah_nominal') {
                        $updates['harga_jual_retail'] = max(0, $hargaJual + $nilai);
                    } elseif ($tipe === 'potong_nominal') {
                        $updates['harga_jual_retail'] = max(0, $hargaJual - $nilai);
                    }

                    if (isset($updates['harga_jual_retail'])) {
                        HargaTier::updateOrCreate(
                            ['produk_id' => $p->id, 'tipe_konsumen' => 'retail'],
                            ['nominal_tetap' => $updates['harga_jual_retail']]
                        );
                    }
                }

                if (! empty($updates)) {
                    $p->update($updates);
                }

                if ($pindahRak) {
                    if ($targetRak) {
                        $stok = StokItem::firstOrCreate(
                            [
                                'produk_id' => $p->id,
                                'gudang_id' => $targetRak->gudang_id,
                                'sku_variant_id' => null,
                            ],
                            [
                                'jumlah' => 0,
                                'jumlah_minimum' => $p->stok_minimum ?? 0,
                                'rak_id' => $targetRak->id,
                            ]
                        );
                        $stok->update(['rak_id' => $targetRak->id]);
                    } else {
                        StokItem::where('produk_id', $p->id)
                            ->when($cabangId, fn ($q) => $q->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))
                            ->update(['rak_id' => null]);
                    }
                }
            }
        });

        activity()
            ->causedBy(auth()->user())
            ->log('Bulk edit diterapkan pada '.count($produks).' master produk.');

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Berhasil memperbarui '.count($produks).' produk terpilih secara masal.',
        ]);

        $this->selectedProdukIds = [];
        $this->selectAll = false;
        $this->showBulkEditModal = false;
    }

    public function duplikatProduk(int $id): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin menduplikasi produk.')) {
            return;
        }

        $source = Produk::with(['hargaTier', 'skuVariants', 'satuanKonversi', 'tipeHps', 'produkKompatibel'])->find($id);
        if (! $source) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak ditemukan.']);

            return;
        }

        try {
            $clone = DB::transaction(function () use ($source) {
                // Bersihkan suffix salinan lama untuk mencegah penumpukan "... (Salinan) (Salinan)"
                $cleanBase = trim(preg_replace('/\s*\(Salinan(?:\s+\d+)?\)$/i', '', $source->nama));

                $existingNames = Produk::where('nama', $cleanBase)
                    ->orWhere('nama', 'like', $cleanBase.' (Salinan%')
                    ->pluck('nama')
                    ->all();

                $taken = [];
                $pattern = '#^'.preg_quote($cleanBase, '#').'\s*\(Salinan(?:\s+(\d+))?\)$#i';
                foreach ($existingNames as $name) {
                    if (preg_match($pattern, $name, $matches)) {
                        $taken[] = isset($matches[1]) ? (int) $matches[1] : 1;
                    }
                }

                if (! in_array(1, $taken, true)) {
                    $uniqueName = $cleanBase.' (Salinan)';
                } else {
                    $nextNum = (! empty($taken) ? max($taken) : 1) + 1;
                    $uniqueName = $cleanBase.' (Salinan '.$nextNum.')';
                }

                $sourceVariant = $source->skuVariants->first();
                $cleanSku = $sourceVariant?->sku
                    ? preg_replace('/-COPY(-\d+)?$/i', '', $sourceVariant->sku)
                    : 'SKU-'.strtoupper(Str::random(6));

                $newProduk = $source->replicate([
                    'slug',
                    'barcode',
                ]);
                $newProduk->nama = $uniqueName;
                $newProduk->save();

                $newSku = $cleanSku.'-COPY-'.$newProduk->id;
                $counter = 1;
                while (SkuVariant::where('sku', $newSku)->exists()) {
                    $newSku = $cleanSku.'-COPY-'.$newProduk->id.'-'.$counter++;
                }

                SkuVariant::create([
                    'produk_id' => $newProduk->id,
                    'sku' => $newSku,
                    'nama_varian' => $sourceVariant?->nama_varian ?? 'Default',
                    'satuan_kode' => $sourceVariant?->satuan_kode ?? ($newProduk->satuan ?: 'pcs'),
                    'harga_beli' => $newProduk->harga_beli,
                    'harga_jual_retail' => $newProduk->harga_jual_retail,
                    'is_active' => true,
                ]);

                foreach ($source->hargaTier as $ht) {
                    $newHt = $ht->replicate();
                    $newHt->produk_id = $newProduk->id;
                    $newHt->save();
                }

                if ($source->tipeHps->isNotEmpty()) {
                    $newProduk->tipeHps()->sync($source->tipeHps->pluck('id'));
                }

                if ($source->produkKompatibel->isNotEmpty()) {
                    $newProduk->produkKompatibel()->sync($source->produkKompatibel->pluck('id'));
                }

                foreach ($source->satuanKonversi as $sk) {
                    $newSk = $sk->replicate();
                    $newSk->produk_id = $newProduk->id;
                    $newSk->save();
                }

                activity()
                    ->performedOn($newProduk)
                    ->causedBy(auth()->user())
                    ->log("Menduplikasi produk dari '{$source->nama}' (ID: {$source->id})");

                return $newProduk;
            });

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Produk '{$source->nama}' berhasil diduplikasi sebagai '{$clone->nama}'!",
            ]);

            $this->openEditProdukModal($clone->id);
        } catch (\Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Gagal menduplikasi produk: '.$e->getMessage(),
            ]);
        }
    }

    public function toggleStatusAktif(int $id): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengubah status produk.')) {
            return;
        }

        $p = Produk::find($id);
        if (! $p) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak ditemukan.']);

            return;
        }

        $p->is_active = ! $p->is_active;
        $p->save();

        $statusLabel = $p->is_active ? 'diaktifkan' : 'dinonaktifkan';
        activity()
            ->performedOn($p)
            ->causedBy(auth()->user())
            ->log("Status produk '{$p->nama}' diubah menjadi {$statusLabel}.");

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Produk '{$p->nama}' berhasil {$statusLabel}.",
        ]);
    }

    public function bulkSetStatus(bool $status): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengubah status produk.')) {
            return;
        }

        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return;
        }

        $count = Produk::whereIn('id', $this->selectedProdukIds)->update(['is_active' => $status]);
        $statusLabel = $status ? 'diaktifkan' : 'dinonaktifkan';

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "{$count} produk terpilih berhasil {$statusLabel}.",
        ]);

        $this->selectedProdukIds = [];
        $this->selectAll = false;
    }

    public function autoKlasifikasiAbcTerpilih(): void
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih produk terlebih dahulu.']);

            return;
        }

        $cabangId = session('cabang_id');
        $res = app(ProcurementService::class)->sinkronisasiKelasAbcOtomatis($cabangId, $this->selectedProdukIds, 90);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Klasifikasi otomatis selesai untuk {$res['total_updated']} produk terpilih (Kelas A: {$res['breakdown']['A']}, B: {$res['breakdown']['B']}, C: {$res['breakdown']['C']}).",
        ]);
    }

    #[On('wms-auto-abc')]
    public function autoKlasifikasiAbcSemua(): void
    {
        $cabangId = session('cabang_id');
        $res = app(ProcurementService::class)->sinkronisasiKelasAbcOtomatis($cabangId, [], 90);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Klasifikasi WMS otomatis berhasil diterapkan untuk {$res['total_updated']} produk (Kelas A: {$res['breakdown']['A']}, B: {$res['breakdown']['B']}, C: {$res['breakdown']['C']}).",
        ]);
    }

    protected function getFilteredProdukQuery()
    {
        $selectedKategoriIds = [];
        $kategoriNama = null;
        if ($this->filterKategoriId) {
            $kat = KategoriProduk::find($this->filterKategoriId);
            if ($kat) {
                $selectedKategoriIds = $kat->semuaKeturunanIds();
                $kategoriNama = $kat->nama;
            }
        }

        $cabangId = session('cabang_id');

        $query = Produk::with([
            'skuVariants',
            'stokItems' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->with(['gudang', 'rak']),
            'brand',
            'kualitas',
            'supplier',
            'tipeHps',
            'kategoriRelasi',
            'hargaTier.tierMembership',
        ])
            ->withCount('stokLogs')
            ->withSum([
                'stokItems as total_stok' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId))),
            ], 'jumlah')
            ->when($this->produkSearch, fn ($q) => $q->cariPintar($this->produkSearch));

        if (! empty($selectedKategoriIds)) {
            $query->where(function ($q) use ($selectedKategoriIds, $kategoriNama) {
                $q->whereIn('kategori_id', $selectedKategoriIds);
                if ($kategoriNama) {
                    $q->orWhere('kategori', $kategoriNama);
                }
            });
        }

        if ($this->filterBrandId) {
            $query->where('brand_id', $this->filterBrandId);
        }

        if ($this->filterKualitasId) {
            $query->where('kualitas_id', $this->filterKualitasId);
        }

        $merk = trim((string) $this->filterBrandKompatibel);
        $model = trim((string) $this->filterModelKompatibel);

        if ($merk !== '' && $model !== '') {
            $query->where(function ($q) use ($merk, $model) {
                // 1. Kolom langsung produk cocok keduanya secara presisi
                $q->where(function ($sub) use ($merk, $model) {
                    $sub->where('brand_kompatibel', $merk)
                        ->where('model_kompatibel', $model);
                })
                // 2. Relasi pivot tipe_hp di mana satu record tipe_hp memenuhi merk & model sekaligus
                    ->orWhereHas('tipeHps', function ($th) use ($merk, $model) {
                        $th->where('tipe_hp.merk', $merk)
                            ->where('tipe_hp.model', $model);
                    })
                // 3. JSON kompatibilitas_hp mengandung merk dan model
                    ->orWhere(function ($sub) use ($merk, $model) {
                        $sub->where('kompatibilitas_hp', 'like', "%{$merk}%")
                            ->where('kompatibilitas_hp', 'like', "%{$model}%");
                    })
                // 4. Kompatibilitas silang antar produk (substitusi langsung)
                    ->orWhereHas('produkKompatibel', function ($sub) use ($merk, $model) {
                        $sub->where(function ($sq) use ($merk, $model) {
                            $sq->where('brand_kompatibel', $merk)->where('model_kompatibel', $model)
                                ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $merk)->where('tipe_hp.model', $model))
                                ->orWhere(fn ($jq) => $jq->where('kompatibilitas_hp', 'like', "%{$merk}%")->where('kompatibilitas_hp', 'like', "%{$model}%"));
                        });
                    })
                    ->orWhereHas('disubstitusiOleh', function ($sub) use ($merk, $model) {
                        $sub->where(function ($sq) use ($merk, $model) {
                            $sq->where('brand_kompatibel', $merk)->where('model_kompatibel', $model)
                                ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $merk)->where('tipe_hp.model', $model))
                                ->orWhere(fn ($jq) => $jq->where('kompatibilitas_hp', 'like', "%{$merk}%")->where('kompatibilitas_hp', 'like', "%{$model}%"));
                        });
                    });
            });
        } elseif ($merk !== '') {
            $query->where(function ($q) use ($merk) {
                $q->where('brand_kompatibel', $merk)
                    ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $merk))
                    ->orWhere('kompatibilitas_hp', 'like', "%{$merk}%");
            });
        } elseif ($model !== '') {
            $query->where(function ($q) use ($model) {
                $q->where('model_kompatibel', $model)
                    ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $model))
                    ->orWhere('kompatibilitas_hp', 'like', "%{$model}%")
                    ->orWhereHas('produkKompatibel', function ($sub) use ($model) {
                        $sub->where('model_kompatibel', $model)
                            ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $model))
                            ->orWhere('kompatibilitas_hp', 'like', "%{$model}%");
                    })
                    ->orWhereHas('disubstitusiOleh', function ($sub) use ($model) {
                        $sub->where('model_kompatibel', $model)
                            ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $model))
                            ->orWhere('kompatibilitas_hp', 'like', "%{$model}%");
                    });
            });
        } elseif ($this->filterTipeHpId) {
            $targetTipe = TipeHp::find($this->filterTipeHpId);
            if ($targetTipe) {
                $tMerk = $targetTipe->merk;
                $tModel = $targetTipe->model;
                $query->where(function ($q) use ($targetTipe, $tMerk, $tModel) {
                    $q->whereHas('tipeHps', fn ($th) => $th->where('tipe_hp.id', $targetTipe->id))
                        ->orWhere(function ($sub) use ($tMerk, $tModel) {
                            $sub->where('brand_kompatibel', $tMerk)->where('model_kompatibel', $tModel);
                        })
                        ->orWhere(function ($sub) use ($tMerk, $tModel) {
                            $sub->where('kompatibilitas_hp', 'like', "%{$tMerk}%")
                                ->where('kompatibilitas_hp', 'like', "%{$tModel}%");
                        })
                        ->orWhereHas('produkKompatibel', function ($sub) use ($tMerk, $tModel) {
                            $sub->where('brand_kompatibel', $tMerk)->where('model_kompatibel', $tModel)
                                ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $tMerk)->where('tipe_hp.model', $tModel));
                        })
                        ->orWhereHas('disubstitusiOleh', function ($sub) use ($tMerk, $tModel) {
                            $sub->where('brand_kompatibel', $tMerk)->where('model_kompatibel', $tModel)
                                ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $tMerk)->where('tipe_hp.model', $tModel));
                        });
                });
            }
        }

        if ($this->filterKondisi) {
            $query->where('kondisi', $this->filterKondisi);
        }

        if ($this->filterSatuan) {
            $query->where('satuan', $this->filterSatuan);
        }

        if ($this->filterAbcClass) {
            $query->where('abc_class', $this->filterAbcClass);
        }

        if ($this->filterSupplierId) {
            $query->where('supplier_id', $this->filterSupplierId);
        }

        if ($this->filterHanyaReadyStok) {
            $query->whereHas('stokItems', fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->where('jumlah', '>', 0));
        }

        // Filter Kolom Tambahan (Header List Produk)
        if (! empty($this->filterSku)) {
            $skuTerm = trim($this->filterSku);
            $query->where(function ($q) use ($skuTerm) {
                $q->where('barcode', 'like', "%{$skuTerm}%")
                    ->orWhereIn('id', SkuVariant::query()
                        ->where('barcode', 'like', "%{$skuTerm}%")
                        ->orWhere('sku', 'like', "%{$skuTerm}%")
                        ->select('produk_id')
                    );
            });
        }

        if ($this->filterMinHarga !== null && $this->filterMinHarga !== '') {
            $query->where('harga_jual_retail', '>=', (float) $this->filterMinHarga);
        }

        if ($this->filterMaxHarga !== null && $this->filterMaxHarga !== '') {
            $query->where('harga_jual_retail', '<=', (float) $this->filterMaxHarga);
        }

        if ($this->filterMinHargaBeli !== null && $this->filterMinHargaBeli !== '') {
            $query->where('harga_beli', '>=', (float) $this->filterMinHargaBeli);
        }

        if ($this->filterMaxHargaBeli !== null && $this->filterMaxHargaBeli !== '') {
            $query->where('harga_beli', '<=', (float) $this->filterMaxHargaBeli);
        }

        if ($this->filterStokStatus === 'ready') {
            $query->whereHas('stokItems', fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->where('jumlah', '>', 0));
        } elseif ($this->filterStokStatus === 'menipis') {
            $query->where(function ($mq) use ($cabangId) {
                $mq->whereHas('stokItems', function ($q) use ($cabangId) {
                    $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))
                        ->where('jumlah', '>', 0)
                        ->where(function ($subQ) {
                            $subQ->whereColumn('jumlah', '<=', 'jumlah_minimum')
                                ->orWhere('jumlah', '<=', 5);
                        });
                })->orWhere(function ($oq) {
                    $oq->whereNotNull('min_stock')
                        ->whereHas('stokItems', fn ($sq) => $sq->where('jumlah', '>', 0)->whereColumn('jumlah', '<=', 'produk.min_stock'));
                });
            });
        } elseif ($this->filterStokStatus === 'habis') {
            $query->whereDoesntHave('stokItems', fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))->where('jumlah', '>', 0));
        }

        if ($this->filterStatusAktif === 'aktif') {
            $query->where('is_active', true);
        } elseif ($this->filterStatusAktif === 'nonaktif') {
            $query->where('is_active', false);
        }

        if ($this->filterGudangId) {
            $query->whereHas('stokItems', fn ($q) => $q->where('gudang_id', $this->filterGudangId));
        }

        // Prioritaskan kecocokan tertinggi jika kata kunci pencarian aktif
        $hasSearch = ! empty(trim((string) $this->produkSearch));
        if ($hasSearch) {
            $query->urutkanRelevansi($this->produkSearch);
        } elseif ($model !== '' || $this->filterTipeHpId) {
            // [T-44b] Hierarki kompatibilitas murni filter: Primary target A11 teratas, cross-compatible A15 dibawahnya
            $targetSortingModel = $model !== '' ? $model : (TipeHp::find($this->filterTipeHpId)?->model ?? '');
            $targetSortingMerk = $merk !== '' ? $merk : (TipeHp::find($this->filterTipeHpId)?->merk ?? null);
            if ($targetSortingModel !== '') {
                $query->urutkanKompatibilitas($targetSortingModel, $targetSortingMerk);
            }
        }

        // Apply secondary sorting
        match ($this->sortBy) {
            'sku' => $query->orderBy(
                SkuVariant::select('sku')->whereColumn('sku_variants.produk_id', 'produk.id')->limit(1),
                $this->sortDir
            ),
            'harga_beli' => $query->orderBy('harga_beli', $this->sortDir),
            'harga', 'harga_jual', 'harga_jual_retail' => $query->orderBy('harga_jual_retail', $this->sortDir),
            'stok', 'total_stok' => $query->orderBy('total_stok', $this->sortDir),
            'kategori' => $query->leftJoin('kategori_produk', 'produk.kategori_id', '=', 'kategori_produk.id')
                ->select('produk.*')
                ->orderBy('kategori_produk.nama', $this->sortDir),
            'brand' => $query->leftJoin('brands', 'produk.brand_id', '=', 'brands.id')
                ->select('produk.*')
                ->orderBy('brands.nama', $this->sortDir),
            'created_at', 'terbaru' => $query->orderBy('created_at', $this->sortDir),
            'abc_class' => $query->orderBy('abc_class', $this->sortDir)->orderBy('nama', 'asc'),
            'reorder_point', 'rop' => $query->orderBy('reorder_point', $this->sortDir),
            'kondisi' => $query->orderBy('kondisi', $this->sortDir),
            'satuan' => $query->orderBy('satuan', $this->sortDir),
            'status', 'is_active' => $query->orderBy('is_active', $this->sortDir),
            default => $query->orderBy('is_active', 'desc')->orderBy('nama', $this->sortDir),
        };

        return $query;
    }

    protected function getCurrentPageProdukIds(): array
    {
        return $this->getFilteredProdukQuery()
            ->paginate($this->perPage)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Ekspor daftar master produk sebagai pricelist (Excel / CSV)
     * berdasarkan filter tier harga (retail, reseller, agen, atau tier membership CRM).
     * Aman dari kebocoran internal (tanpa harga_beli / HPP).
     */
    public function exportPricelist(string $format = 'xlsx')
    {
        $selectedKategoriIds = [];
        if ($this->filterKategoriId) {
            $kat = KategoriProduk::find($this->filterKategoriId);
            if ($kat) {
                $selectedKategoriIds = $kat->semuaKeturunanIds();
            }
        }

        $query = Produk::with(['brand', 'kualitas', 'kategoriRelasi', 'hargaTier.tierMembership', 'skuVariants'])
            ->when($this->produkSearch, fn ($q) => $q->cariPintar($this->produkSearch)->urutkanRelevansi($this->produkSearch))
            ->when(! empty($selectedKategoriIds), fn ($q) => $q->whereIn('kategori_id', $selectedKategoriIds))
            ->when($this->filterBrandId, fn ($q) => $q->where('brand_id', $this->filterBrandId))
            ->when($this->filterBrandKompatibel && $this->filterModelKompatibel, fn ($q) => $q->where(fn ($sub) => $sub->where('brand_kompatibel', $this->filterBrandKompatibel)->where('model_kompatibel', $this->filterModelKompatibel)->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $this->filterBrandKompatibel)->where('tipe_hp.model', $this->filterModelKompatibel))))
            ->when($this->filterBrandKompatibel && ! $this->filterModelKompatibel, fn ($q) => $q->where(fn ($sub) => $sub->where('brand_kompatibel', $this->filterBrandKompatibel)->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $this->filterBrandKompatibel))))
            ->when(! $this->filterBrandKompatibel && $this->filterModelKompatibel, fn ($q) => $q->where(fn ($sub) => $sub->where('model_kompatibel', $this->filterModelKompatibel)->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $this->filterModelKompatibel))))
            ->where('is_active', true);

        return $this->lakukanExportPricelist($query, $format, 'pricelist_'.$this->filterTierHarga);
    }

    public function exportTerpilih(string $format = 'xlsx')
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return null;
        }

        $query = Produk::with(['brand', 'kualitas', 'kategoriRelasi', 'hargaTier.tierMembership', 'skuVariants'])
            ->whereIn('id', $this->selectedProdukIds);

        return $this->lakukanExportPricelist($query, $format, 'terpilih_'.count($this->selectedProdukIds));
    }

    protected function lakukanExportPricelist($query, string $format = 'xlsx', string $slugSuffix = 'pricelist')
    {
        $fmt = $format === 'csv' ? 'csv' : 'xlsx';
        $userId = auth()->id() ?? 0;

        $produks = $query->orderBy('nama')->get();

        $rows = [];
        $tierLabel = $this->labelTierAktif;
        foreach ($produks as $p) {
            $hargaJual = (float) $p->harga_jual_retail;
            $tierInfo = $this->resolveHargaTierProduk($p, $this->filterTierHarga);
            $hargaTierFinal = $tierInfo['harga'];

            $rows[] = [
                'Kode / SKU' => $p->kode ?: ($p->skuVariants->first()?->sku ?? '-'),
                'Barcode' => $p->barcode ?: '-',
                'Nama Produk' => $p->nama,
                'Kategori' => $p->kategoriRelasi?->nama ?? $p->kategori ?? '-',
                'Brand' => $p->brand?->nama ?? $p->brand_kompatibel ?? '-',
                'Kualitas' => $p->kualitas?->nama ?? '-',
                'Satuan' => $p->satuan ?: 'pcs',
                'Status' => $p->is_active ? 'Aktif' : 'Nonaktif',
                'Harga Retail (Rp)' => number_format($hargaJual, 0, ',', '.'),
                'Tier Harga' => $tierLabel,
                'Harga Pricelist (Rp)' => number_format(max(0, $hargaTierFinal), 0, ',', '.'),
            ];
        }

        $slug = Str::slug($slugSuffix) ?: 'pricelist';
        $filename = $userId.'_'.$slug.'_'.now()->format('Ymd-His').'.'.$fmt;
        $path = 'exports/'.$filename;

        if ($fmt === 'csv') {
            $out = fopen('php://memory', 'r+');
            if (! empty($rows)) {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, array_values($row));
                }
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            Storage::disk('local')->put($path, $csv);
        } else {
            Excel::store(new class($rows) implements FromArray, WithHeadings
            {
                public function __construct(private array $rows) {}

                public function headings(): array
                {
                    return ! empty($this->rows) ? array_keys($this->rows[0]) : [];
                }

                public function array(): array
                {
                    return array_map(fn ($r) => array_values((array) $r), $this->rows);
                }
            }, $path, 'local');
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Ekspor '.$tierLabel.' berhasil — unduhan dimulai.',
        ]);

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, 'pricelist-'.$slug.'-'.now()->format('Ymd-His').'.'.$fmt);
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
        $kategoriTree = KategoriProduk::getTreeTersediaDiProduk();

        $produks = $this->getFilteredProdukQuery()
            ->paginate($this->perPage);

        $gudangs = Gudang::where('is_active', true)->get();

        $config = app(KonfigurasiService::class);
        $savedKondisi = $config->get('master_produk_kondisi_list');
        $defaultKondisi = [
            ['kode' => 'baru', 'nama' => 'Baru', 'warna' => 'emerald', 'hex' => '#10b981', 'keterangan' => 'Baru (Segel Pabrik / Fresh Stock)'],
            ['kode' => 'oem', 'nama' => 'OEM', 'warna' => 'blue', 'hex' => '#3b82f6', 'keterangan' => 'OEM (Original Equipment Manufacturer)'],
            ['kode' => 'compatible', 'nama' => 'Compatible', 'warna' => 'cyan', 'hex' => '#06b6d4', 'keterangan' => 'Compatible (Part Pihak Ketiga Berkualitas)'],
            ['kode' => 'bekas', 'nama' => 'Bekas / Copotan', 'warna' => 'amber', 'hex' => '#f59e0b', 'keterangan' => 'Bekas / Copotan Unit Mesin Normal'],
            ['kode' => 'rusak', 'nama' => 'Rusak / Kanibal', 'warna' => 'rose', 'hex' => '#ef4444', 'keterangan' => 'Rusak / Bahan Kanibal Part Teknisi'],
        ];

        $defMap = collect($defaultKondisi)->keyBy('kode');
        $kondisiList = (is_array($savedKondisi) && ! empty($savedKondisi))
            ? array_values(array_map(function ($k) use ($defMap) {
                $def = $defMap->get($k['kode'] ?? '');

                return [
                    'kode' => $k['kode'] ?? 'baru',
                    'nama' => $k['nama'] ?? 'Baru',
                    'warna' => $k['warna'] ?? ($def['warna'] ?? 'emerald'),
                    'hex' => $k['hex'] ?? ($def['hex'] ?? '#10b981'),
                    'keterangan' => $k['keterangan'] ?? ($def['keterangan'] ?? 'Kondisi Suku Cadang'),
                    'is_active' => $k['is_active'] ?? true,
                ];
            }, array_filter($savedKondisi, fn ($k) => ($k['is_active'] ?? true))))
            : $defaultKondisi;

        $merekHpOptions = TipeHp::where('is_active', true)
            ->distinct()
            ->orderBy('merk')
            ->pluck('merk')
            ->filter()
            ->values()
            ->all();

        $tipeHpCreateOptions = ! empty($this->kompatibelMerekHp)
            ? TipeHp::where('is_active', true)
                ->where('merk', $this->kompatibelMerekHp)
                ->when($this->searchTipeHpCreate, fn ($q) => $q->where('model', 'like', '%'.trim($this->searchTipeHpCreate).'%'))
                ->orderBy('model')
                ->get()
            : collect();

        $tipeHpEditOptions = ! empty($this->editKompatibelMerekHp)
            ? TipeHp::where('is_active', true)
                ->where('merk', $this->editKompatibelMerekHp)
                ->when($this->searchTipeHpEdit, fn ($q) => $q->where('model', 'like', '%'.trim($this->searchTipeHpEdit).'%'))
                ->orderBy('model')
                ->get()
            : collect();

        $selectedCreateTipeHps = ! empty($this->produkForm['tipe_hp_ids'])
            ? TipeHp::whereIn('id', (array) $this->produkForm['tipe_hp_ids'])->orderBy('merk')->orderBy('model')->get()
            : collect();

        $selectedEditTipeHps = ! empty($this->editProdukForm['tipe_hp_ids'])
            ? TipeHp::whereIn('id', (array) $this->editProdukForm['tipe_hp_ids'])->orderBy('merk')->orderBy('model')->get()
            : collect();

        $tipeHpList = TipeHp::where('is_active', true)
            ->when($this->filterBrandKompatibel, fn ($q) => $q->where('merk', $this->filterBrandKompatibel))
            ->orderBy('merk')
            ->orderBy('model')
            ->get();
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

        // 1. Merk HP Kompatibel (Hanya brand smartphone dari master produk & relasinya)
        $merekHpDariProduk = Produk::query()
            ->whereNotNull('brand_kompatibel')
            ->where('brand_kompatibel', '!=', '')
            ->distinct()
            ->pluck('brand_kompatibel');

        $merekHpDariRelasi = TipeHp::query()
            ->whereHas('produk')
            ->distinct()
            ->pluck('merk');

        $merekHpJson = collect();
        $kompatJsonList = Produk::query()
            ->whereNotNull('kompatibilitas_hp')
            ->pluck('kompatibilitas_hp');
        foreach ($kompatJsonList as $kJson) {
            if (is_array($kJson)) {
                foreach ($kJson as $item) {
                    if (! empty($item['merk'])) {
                        $merekHpJson->push(trim((string) $item['merk']));
                    }
                }
            }
        }

        $brandKompatibelList = $merekHpDariProduk
            ->merge($merekHpDariRelasi)
            ->merge($merekHpJson);

        if ($brandKompatibelList->isEmpty()) {
            $brandKompatibelList = TipeHp::where('is_active', true)->distinct()->pluck('merk');
        }

        $brandKompatibelList = $brandKompatibelList
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // 2. Tipe HP Kompatibel (Model smartphone dari master produk, cascading jika Merk HP dipilih)
        $selectedMerk = $this->filterBrandKompatibel;

        $modelHpDariProduk = Produk::query()
            ->whereNotNull('model_kompatibel')
            ->where('model_kompatibel', '!=', '')
            ->when($selectedMerk, fn ($q) => $q->where('brand_kompatibel', $selectedMerk))
            ->distinct()
            ->pluck('model_kompatibel');

        $modelHpDariRelasi = TipeHp::query()
            ->whereHas('produk')
            ->when($selectedMerk, fn ($q) => $q->where('merk', $selectedMerk))
            ->distinct()
            ->pluck('model');

        $modelHpJson = collect();
        foreach ($kompatJsonList as $kJson) {
            if (is_array($kJson)) {
                foreach ($kJson as $item) {
                    if (! empty($item['model'])) {
                        if (empty($selectedMerk) || strcasecmp(trim((string) ($item['merk'] ?? '')), $selectedMerk) === 0) {
                            $modelHpJson->push(trim((string) $item['model']));
                        }
                    }
                }
            }
        }

        $modelKompatibelList = $modelHpDariProduk
            ->merge($modelHpDariRelasi)
            ->merge($modelHpJson);

        if ($modelKompatibelList->isEmpty()) {
            $modelKompatibelList = TipeHp::where('is_active', true)
                ->when($selectedMerk, fn ($q) => $q->where('merk', $selectedMerk))
                ->distinct()
                ->pluck('model');
        }

        $modelKompatibelList = $modelKompatibelList
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // 3. Filter data pendukung agar sesuai dengan data riil master produk
        $allBrands = Brand::where('is_active', true)->orderBy('nama')->get();
        $brandsMaster = Brand::where('is_active', true)
            ->when(Produk::whereNotNull('brand_id')->exists(), fn ($q) => $q->whereIn('id', Produk::whereNotNull('brand_id')->distinct()->pluck('brand_id')))
            ->orderBy('nama')
            ->get();
        if ($brandsMaster->isEmpty()) {
            $brandsMaster = $allBrands;
        }

        $kualitasFilterOptions = KualitasProduk::with('brand')
            ->where('is_active', true)
            ->when($this->filterBrandId, fn ($q) => $q->forBrand($this->filterBrandId))
            ->orderByRaw('brand_id IS NULL ASC')
            ->orderBy('nama')
            ->get();

        $kualitasCreateOptions = KualitasProduk::with('brand')
            ->where('is_active', true)
            ->forBrand(! empty($this->produkForm['brand_id']) ? (int) $this->produkForm['brand_id'] : null)
            ->orderByRaw('brand_id IS NULL ASC')
            ->orderBy('nama')
            ->get();

        $kualitasEditOptions = KualitasProduk::with('brand')
            ->where('is_active', true)
            ->forBrand(! empty($this->editProdukForm['brand_id']) ? (int) $this->editProdukForm['brand_id'] : null)
            ->orderByRaw('brand_id IS NULL ASC')
            ->orderBy('nama')
            ->get();

        $suppliersMaster = Supplier::where('is_active', true)
            ->when(Produk::whereNotNull('supplier_id')->exists(), fn ($q) => $q->whereIn('id', Produk::whereNotNull('supplier_id')->distinct()->pluck('supplier_id')))
            ->orderBy('nama')
            ->get();
        if ($suppliersMaster->isEmpty()) {
            $suppliersMaster = Supplier::where('is_active', true)->orderBy('nama')->get();
        }

        $usedSatuan = Produk::distinct()->pluck('satuan')->filter()->all();
        $satuanUnitsMaster = SatuanUnit::where('is_active', true)
            ->when(! empty($usedSatuan), fn ($q) => $q->whereIn('kode', $usedSatuan))
            ->orderBy('kode')
            ->get();
        if ($satuanUnitsMaster->isEmpty()) {
            $satuanUnitsMaster = SatuanUnit::where('is_active', true)->orderBy('kode')->get();
        }

        $usedKondisi = Produk::distinct()->pluck('kondisi')->filter()->all();
        $kondisiListMaster = ! empty($usedKondisi)
            ? collect($kondisiList)->filter(fn ($kd) => in_array($kd['kode'], $usedKondisi))->values()->all()
            : $kondisiList;
        if (empty($kondisiListMaster)) {
            $kondisiListMaster = $kondisiList;
        }

        $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;
        $gudangsCabang = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->get();

        $quickAddMerk = trim($this->quickAddForm['merk'] ?? $this->quickAddSelectedMerk ?? '');
        $quickAddExistingModels = (! empty($quickAddMerk) && $quickAddMerk !== '__custom__')
            ? TipeHp::where('is_active', true)
                ->where('merk', $quickAddMerk)
                ->distinct()
                ->orderBy('model')
                ->pluck('model')
                ->filter()
                ->values()
                ->all()
            : [];

        return view('modules.wms.livewire.produk-tab', [
            'gudangs' => $gudangs,
            'gudangsCabang' => $gudangsCabang,
            'raks' => Rak::with('gudang')->get(),
            'produks' => $produks,
            'perPage' => $this->perPage,
            'selectedProdukIds' => $this->selectedProdukIds,
            'selectAll' => $this->selectAll,
            'labelTierAktif' => $this->labelTierAktif,
            'activeFilterCount' => $this->activeFilterCount,
            'sortBy' => $this->sortBy,
            'sortDir' => $this->sortDir,
            'sortPreset' => $this->sortPreset,
            'filterSku' => $this->filterSku,
            'filterMinHarga' => $this->filterMinHarga,
            'filterMaxHarga' => $this->filterMaxHarga,
            'filterStokStatus' => $this->filterStokStatus,
            'filterStatusAktif' => $this->filterStatusAktif,
            'filterGudangId' => $this->filterGudangId,
            'filterSupplierId' => $this->filterSupplierId,
            'kategoriTree' => $kategoriTree,
            'tierMemberships' => TierMembership::where('is_active', true)->orderBy('urutan')->get(),
            // [T-44] master data pendukung terkalibrasi ke master produk
            'brands' => $brandsMaster,
            'allBrands' => $allBrands,
            'kualitasList' => $kualitasFilterOptions,
            'kualitasCreateList' => $kualitasCreateOptions,
            'kualitasEditList' => $kualitasEditOptions,
            'satuanUnits' => $satuanUnitsMaster,
            'suppliers' => $suppliersMaster,
            'kondisiList' => $kondisiListMaster,
            'merekHpOptions' => $merekHpOptions,
            'tipeHpCreateOptions' => $tipeHpCreateOptions,
            'tipeHpEditOptions' => $tipeHpEditOptions,
            'selectedCreateTipeHps' => $selectedCreateTipeHps,
            'selectedEditTipeHps' => $selectedEditTipeHps,
            'tipeHpList' => $tipeHpList,
            'tipeHpOptions' => $tipeHpOptions,
            'allProdukList' => $allProdukList,
            'allProdukOptions' => $allProdukOptions,
            'brandKompatibelList' => $brandKompatibelList,
            'modelKompatibelList' => $modelKompatibelList,
            'quickAddExistingModels' => $quickAddExistingModels,
        ]);
    }
}
