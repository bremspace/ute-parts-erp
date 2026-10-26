<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ExportMasterProdukService;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportMultiCabangGudangTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang1;

    private Cabang $cabang2;

    private Gudang $gudangCabang1;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang1 = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CBG-01',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->cabang2 = Cabang::create([
            'nama' => 'Cabang Barat',
            'kode' => 'CBG-02',
            'is_pusat' => false,
            'is_active' => true,
        ]);

        $this->gudangCabang1 = Gudang::create([
            'cabang_id' => $this->cabang1->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-UTAMA',
            'is_active' => true,
        ]);

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teknisi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);

        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '310-01'], ['nama' => 'Modal Pemilik', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang1->id, ['is_default' => true]);
    }

    public function test_import_pada_cabang_2_tidak_mencatut_gudang_cabang_1(): void
    {
        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang;rak',
            'PART-CBG2-01;LCD Test Cabang 2;pcs;50000;75000;10;Gudang Utama;',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_cbg2_'.uniqid().'.csv';
        file_put_contents($filePath, $csv);

        try {
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_import_cbg2.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            session(['cabang_id' => $this->cabang2->id]);

            $service = app(ImportProdukService::class);
            $hasil = $service->commit(
                filePath: $filePath,
                importLogId: $log->id,
                userId: $this->admin->id,
                cabangId: $this->cabang2->id
            );

            $this->assertSame(1, $hasil['sukses'], json_encode($hasil['detail'] ?? []));

            // Pastikan gudang cabang 2 dibuat terpisah dan tidak memakai gudang cabang 1
            $gudangsCabang2 = Gudang::where('cabang_id', $this->cabang2->id)->get();
            $this->assertCount(1, $gudangsCabang2);
            $this->assertNotSame($this->gudangCabang1->id, $gudangsCabang2->first()->id);
            $this->assertNotSame($this->gudangCabang1->kode, $gudangsCabang2->first()->kode);

            // Gudang cabang 1 tetap 1 baris
            $this->assertSame(1, Gudang::where('cabang_id', $this->cabang1->id)->count());
        } finally {
            @unlink($filePath);
        }
    }

    public function test_dropdown_gudang_di_ui_scoped_ke_cabang_aktif(): void
    {
        $gudangCabang2 = Gudang::create([
            'cabang_id' => $this->cabang2->id,
            'nama' => 'Gudang Barat',
            'kode' => 'GDG-CBG02',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);

        // Simulasi sesi Cabang 1
        session(['cabang_id' => $this->cabang1->id]);

        Livewire::test(ProdukTab::class)
            ->assertViewHas('gudangs', function ($gudangs) use ($gudangCabang2) {
                return $gudangs->contains('id', $this->gudangCabang1->id)
                    && ! $gudangs->contains('id', $gudangCabang2->id);
            });

        Livewire::test(OpnameTab::class)
            ->assertViewHas('gudangs', function ($gudangs) use ($gudangCabang2) {
                return $gudangs->contains('id', $this->gudangCabang1->id)
                    && ! $gudangs->contains('id', $gudangCabang2->id);
            });

        Livewire::test(ImportExportPage::class)
            ->assertViewHas('gudangList', function ($gudangList) use ($gudangCabang2) {
                return $gudangList->contains('id', $this->gudangCabang1->id)
                    && ! $gudangList->contains('id', $gudangCabang2->id);
            });

        Livewire::test(ServisBoard::class)
            ->assertViewHas('gudangList', function ($gudangList) use ($gudangCabang2) {
                return $gudangList->contains('id', $this->gudangCabang1->id)
                    && ! $gudangList->contains('id', $gudangCabang2->id);
            });

        // Simulasi berpindah ke Cabang 2
        session(['cabang_id' => $this->cabang2->id]);

        Livewire::test(ProdukTab::class)
            ->assertViewHas('gudangs', function ($gudangs) use ($gudangCabang2) {
                return $gudangs->contains('id', $gudangCabang2->id)
                    && ! $gudangs->contains('id', $this->gudangCabang1->id);
            });
    }

    public function test_referensi_gudang_export_dan_template_tidak_duplikat(): void
    {
        Gudang::create([
            'cabang_id' => $this->cabang2->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-CBG02-UTAMA',
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang1->id]);

        $exportService = app(ExportMasterProdukService::class);
        $referensi = $exportService->buatReferensiSistem($this->cabang1->id);

        $gudangNames = [];
        foreach ($referensi as $row) {
            if (! empty($row[12])) {
                $gudangNames[] = $row[12];
            }
        }

        // Tidak boleh ada nama gudang yang berulang
        $this->assertSame(count($gudangNames), count(array_unique($gudangNames)));
        $this->assertContains('Gudang Utama', $gudangNames);
    }

    public function test_export_master_produk_mengambil_gudang_dan_stok_cabang_aktif(): void
    {
        $gudangCabang2 = Gudang::create([
            'cabang_id' => $this->cabang2->id,
            'nama' => 'Gudang Barat',
            'kode' => 'GDG-CBG02',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'sku' => 'PROD-MULTI-01',
            'nama' => 'Produk Multi Cabang',
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'PROD-MULTI-01',
            'nama_varian' => 'Default',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        // Stok di Cabang 1 = 15
        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudangCabang1->id,
            'jumlah' => 15,
        ]);

        // Stok di Cabang 2 = 7
        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $gudangCabang2->id,
            'jumlah' => 7,
        ]);

        $exportService = app(ExportMasterProdukService::class);

        // Ekspor Cabang 1 harus menampilkan stok 15 dan Gudang Utama
        $rowsCabang1 = $exportService->buildMasterRows($this->cabang1->id);
        $rowP1 = collect($rowsCabang1)->firstWhere('sku', 'PROD-MULTI-01');
        $this->assertSame(15, (int) $rowP1['stok_awal']);
        $this->assertSame('Gudang Utama', $rowP1['gudang']);

        // Ekspor Cabang 2 harus menampilkan stok 7 dan Gudang Barat
        $rowsCabang2 = $exportService->buildMasterRows($this->cabang2->id);
        $rowP2 = collect($rowsCabang2)->firstWhere('sku', 'PROD-MULTI-01');
        $this->assertSame(7, (int) $rowP2['stok_awal']);
        $this->assertSame('Gudang Barat', $rowP2['gudang']);
    }

    public function test_import_dengan_kolom_cabang_eksplisit_berhasil(): void
    {
        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;cabang;gudang;rak',
            'SKU-CBG-EXP-01;Item Eksplisit Cabang;pcs;20000;30000;5;Cabang Barat;Gudang Utama;Rak B1',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_cbg_col_'.uniqid().'.csv';
        file_put_contents($filePath, $csv);

        try {
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_cbg_col.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $service = app(ImportProdukService::class);
            $hasil = $service->commit($filePath, $log->id, $this->admin->id);

            $this->assertSame(1, $hasil['sukses'], json_encode($hasil['detail'] ?? []));

            $gudangCbg2 = Gudang::where('cabang_id', $this->cabang2->id)->first();
            $this->assertNotNull($gudangCbg2);

            $stok = StokItem::where('gudang_id', $gudangCbg2->id)
                ->whereHas('skuVariant', fn ($q) => $q->where('sku', 'SKU-CBG-EXP-01'))
                ->first();

            $this->assertNotNull($stok);
            $this->assertSame(5, (int) $stok->jumlah);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_open_edit_produk_modal_tidak_membuat_duplikat_gudang(): void
    {
        $this->actingAs($this->admin);
        session(['cabang_id' => $this->cabang1->id]);

        $produk = Produk::create([
            'sku' => 'PROD-MODAL-TEST',
            'nama' => 'Produk Modal Test',
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'PROD-MODAL-TEST',
            'nama_varian' => 'Default',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudangCabang1->id,
            'jumlah' => 10,
        ]);

        $component = Livewire::test(ProdukTab::class);
        $component->call('openEditProdukModal', $produk->id);

        $stoks = StokItem::where('produk_id', $produk->id)->get();
        $this->assertCount(1, $stoks, 'Stok item tidak boleh terduplikasi saat membuka edit modal');
        $this->assertSame(10, (int) $stoks->first()->jumlah);
        $this->assertSame($variant->id, $stoks->first()->sku_variant_id);
    }
}
