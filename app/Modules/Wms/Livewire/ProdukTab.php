<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ProdukService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * [F1-8 / S-01] Tab "Master Produk" — dipecah dari WmsDashboard (paritas perilaku).
 */
class ProdukTab extends Component
{
    use PunyaRiwayatAktivitas;
    use WithFileUploads;
    use WithPagination;

    // Produk tab filters
    public string $produkSearch = '';

    // Stok tab filter default (dipakai modal tambah stok) — sama dgn mount shell lama
    public ?int $filterGudangId = null;

    // New Produk Modal (Tambah Produk — sinkron Akunting)
    public bool $showProdukModal = false;

    public array $produkForm = [
        'nama' => '', 'kategori' => '', 'brand_kompatibel' => '', 'model_kompatibel' => '',
        'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
        'sku' => '', 'gudang_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
        // [T-44]
        'brand_id' => null, 'kualitas_id' => null, 'satuan_kode' => 'pcs',
        'tipe_hp_ids' => [], 'harga_tier' => [
            'retail' => ['nominal_tetap' => 0, 'persen_diskon' => null],
            'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
            'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
        ],
        'harga_tier_tier_id' => null,
        // [HARGA FLEKSIBEL]
        'harga_fleksibel' => false,
    ];

    // Tambah Stok Modal (pembelian — sinkron Akunting)
    public bool $showTambahStokModal = false;

    public ?int $stokProdukId = null;

    public array $tambahStokForm = [
        'gudang_id' => null, 'rak_id' => null, 'qty' => 1, 'harga_beli' => 0, 'keterangan' => 'Pembelian dari supplier',
    ];

    // [T-43] Import master produk Excel
    public bool $showImportModal = false;

    public $importFile = null;

    public string $importStep = 'upload'; // upload → preview → selesai

    public array $importPreview = [];

    public string $importFilePath = '';

    public int $importLogId = 0;

    public function mount()
    {
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->filterGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }
    }

    /**
     * [T-40] Guard role: tambah stok manual & stok awal produk hanya super-admin.
     * Role lain wajib lewat PO Supplier (single source of truth stok masuk).
     */
    protected function isSuperAdmin(): bool
    {
        return (bool) (auth()->user()?->hasRole('super-admin'));
    }

    // Quick action header "+ Tambah Produk" (dari shell WmsDashboard via $dispatch)
    #[On('wms-produk-modal')]
    public function openProdukModal()
    {
        $this->produkForm = [
            'nama' => '', 'kategori' => '', 'brand_kompatibel' => '', 'model_kompatibel' => '',
            'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
            'sku' => '', 'gudang_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
            // [T-44]
            'brand_id' => null, 'kualitas_id' => null, 'satuan_kode' => 'pcs',
            'tipe_hp_ids' => [], 'harga_tier' => [
                'retail' => ['nominal_tetap' => 0, 'persen_diskon' => null],
                'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
                'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
            ],
            'harga_tier_tier_id' => null,
            // [HARGA FLEKSIBEL]
            'harga_fleksibel' => false,
        ];
        $this->showProdukModal = true;
    }

    public function produkHargaJualBerubah()
    {
        // Isi otomatis harga tier retail bila belum di-set manual
        if (! $this->produkForm['harga_tier']['retail']['nominal_tetap']
            && $this->produkForm['harga_jual_retail'] > 0) {
            $this->produkForm['harga_tier']['retail']['nominal_tetap'] = (float) $this->produkForm['harga_jual_retail'];
        }
    }

    public function simpanProduk()
    {
        $this->validate([
            'produkForm.nama' => 'required|string|max:255',
            'produkForm.kategori' => 'required|string|max:255',
            'produkForm.satuan_kode' => 'required|exists:satuan_unit,kode',
            'produkForm.harga_beli' => 'required|numeric|min:0',
            'produkForm.harga_jual_retail' => 'required|numeric|min:0',
            // [T-44] minimal 1 harga tier (retail wajib isi)
            'produkForm.harga_tier.retail.nominal_tetap' => 'nullable|numeric|min:0',
            // [HARGA FLEKSIBEL]
            'produkForm.harga_fleksibel' => 'boolean',
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

        try {
            app(ProdukService::class)->buatProduk(
                nama: $this->produkForm['nama'],
                kategori: $this->produkForm['kategori'],
                brand: $this->produkForm['brand_kompatibel'] ?: null,
                model: $this->produkForm['model_kompatibel'] ?: null,
                kondisi: $this->produkForm['kondisi'],
                hargaBeli: (float) $this->produkForm['harga_beli'],
                hargaJual: (float) $this->produkForm['harga_jual_retail'],
                sku: $this->produkForm['sku'] ?: null,
                gudangId: $this->produkForm['gudang_id'] ?: null,
                stokAwal: (int) $this->produkForm['stok_awal'],
                stokMinimum: (int) $this->produkForm['stok_minimum'],
                userId: auth()->id(),
                brandId: $this->produkForm['brand_id'] ?: null,
                kualitasId: $this->produkForm['kualitas_id'] ?: null,
                satuanKode: $this->produkForm['satuan_kode'],
                tipeHpIds: $this->produkForm['tipe_hp_ids'] ?: [],
                hargaTier: $this->produkForm['harga_tier'],
                hargaFleksibel: (bool) $this->produkForm['harga_fleksibel'],
            );

            $this->showProdukModal = false;
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Produk tersimpan'.((int) $this->produkForm['stok_awal'] > 0 ? ' + jurnal pembelian dibuat' : ''),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openTambahStokModal(int $produkId)
    {
        // [T-40] Stok masuk wajib via PO Supplier — tambah stok manual hanya super-admin
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Stok masuk hanya via PO Supplier']);

            return;
        }

        $produk = Produk::with('skuVariants')->findOrFail($produkId);
        $this->stokProdukId = $produkId;
        $variant = $produk->skuVariants->first();
        $this->tambahStokForm = [
            'gudang_id' => $this->filterGudangId,
            'qty' => 1,
            'harga_beli' => $variant?->harga_beli ?? $produk->harga_beli ?? 0,
            'keterangan' => 'Pembelian stok '.$produk->nama,
        ];
        $this->showTambahStokModal = true;
    }

    public function simpanTambahStok()
    {
        // [T-40] Guard server-side: jalur tambah stok manual diblokir selain super-admin
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Stok masuk hanya via PO Supplier']);

            return;
        }

        $this->validate([
            'tambahStokForm.gudang_id' => 'required|exists:gudang,id',
            'tambahStokForm.rak_id' => 'nullable|exists:rak,id', // [T-12]
            'tambahStokForm.qty' => 'required|integer|min:1',
            'tambahStokForm.harga_beli' => 'required|numeric|min:0',
        ]);

        try {
            $produk = Produk::with('skuVariants')->findOrFail($this->stokProdukId);
            $variant = $produk->skuVariants->first();

            app(ProdukService::class)->tambahStokPembelian(
                produkId: $produk->id,
                variantId: $variant?->id,
                gudangId: (int) $this->tambahStokForm['gudang_id'],
                qty: (int) $this->tambahStokForm['qty'],
                hargaBeli: (float) $this->tambahStokForm['harga_beli'],
                keterangan: $this->tambahStokForm['keterangan'] ?: 'Pembelian dari supplier',
                userId: auth()->id(),
                rakId: $this->tambahStokForm['rak_id'] ?? null // [T-12]
            );

            $this->showTambahStokModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Stok ditambahkan + jurnal pembelian dibuat']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
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

    // Quick action header "Import Excel" (dari shell WmsDashboard via $dispatch)
    #[On('wms-import-modal')]
    public function openImportModal()
    {
        $this->importStep = 'upload';
        $this->importFile = null;
        $this->importPreview = [];
        $this->importFilePath = '';
        $this->showImportModal = true;
    }

    public function tutupImportModal()
    {
        $this->showImportModal = false;
        if ($this->importFilePath) {
            @unlink(storage_path('app/'.$this->importFilePath));
        }
        $this->importFile = null;
        $this->importPreview = [];
        $this->importFilePath = '';
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
            $hasil = app(ImportProdukService::class)->preview(storage_path('app/'.$path));
            $this->importFilePath = $path;
            $this->importPreview = $hasil;
            $this->importStep = 'preview';

            if ($hasil['invalid'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "Preview: {$hasil['valid']} baris valid, {$hasil['invalid']} baris error — perbaiki file lalu upload ulang, atau import hanya baris valid",
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
        if (! $this->importFilePath) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Lakukan preview terlebih dahulu']);

            return;
        }

        try {
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => $this->importFile?->getClientOriginalName() ?? 'produk-import.xlsx',
                'status' => 'proses',
                'user_id' => auth()->id(),
            ]);
            $this->importLogId = $log->id;

            ImportProdukExcelJob::dispatch($log->id, $this->importFilePath, auth()->id());

            $this->importStep = 'selesai';
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Import diproses via antrian — hasil akan masuk notifikasi dalam aplikasi',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $gudangs = Gudang::where('is_active', true)->get();

        return view('modules.wms.livewire.produk-tab', [
            'gudangs' => $gudangs,
            'raks' => Rak::with('gudang')->get(),
            'produks' => Produk::with(['skuVariants', 'stokItems.gudang', 'brand', 'kualitas', 'tipeHps'])
                ->when($this->produkSearch, fn ($q) => $q->where(function ($q2) {
                    $q2->where('nama', 'like', "%{$this->produkSearch}%")
                        ->orWhere('kategori', 'like', "%{$this->produkSearch}%")
                        ->orWhere('brand_kompatibel', 'like', "%{$this->produkSearch}%");
                }))
                ->orderBy('nama')
                ->paginate(12),
            // [T-44] master data pendukung
            'brands' => Brand::orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::orderBy('nama')->get(),
            'tipeHpList' => TipeHp::orderBy('merk')->orderBy('model')->get(),
            'satuanUnits' => SatuanUnit::where('is_active', true)->orderBy('kode')->get(),
        ]);
    }
}
