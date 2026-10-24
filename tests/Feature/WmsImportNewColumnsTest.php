<?php

namespace Tests\Feature;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\AkunCoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class WmsImportNewColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_produk_mengakomodir_kolom_baru_secara_lengkap(): void
    {
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_active' => true]);
        $rak = Rak::create(['gudang_id' => $gudang->id, 'nama' => 'Rak A1', 'kode' => 'RAK-A1', 'is_active' => true]);

        $service = app(ImportProdukService::class);
        $kolom = $service->templateColumns();

        $baris = [
            [
                'sku' => 'TEST-LCD-IP13',
                'nama' => 'LCD iPhone 13 Pro Max OEM',
                'barcode' => '8999990001',
                'satuan' => 'pcs',
                'konversi_satuan' => 'box:10:12000000;pack:5:6200000',
                'kategori' => 'LCD & Touchscreen',
                'sub_kategori' => 'LCD Assembly',
                'brand' => 'Crown',
                'kualitas' => 'Grade A',
                'kondisi' => 'oem',
                'supplier' => 'PT Supplier Utama',
                'merk_hp' => 'Apple',
                'tipe_hp' => 'iPhone 13 Pro Max',
                'harga_beli' => 750000,
                'harga_jual' => 1100000,
                'harga_reseller' => 950000,
                'harga_agen' => 900000,
                'min_stock' => 5,
                'reorder_point' => 12,
                'sn' => 'ya',
                'stok_awal' => 8,
                'gudang' => $gudang->nama,
                'rak' => $rak->nama,
                'produk_kompatibel_sku' => '',
                'kompatibilitas_hp' => '',
                'deskripsi' => 'LCD OEM High Brightness untuk iPhone 13 Pro Max',
                'foto_url' => 'https://example.com/lcd.webp',
            ],
        ];

        $filePath = storage_path('app/import-tmp/test-new-cols.csv');
        if (! is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0775, true);
        }

        $export = new class($kolom, $baris) implements FromCollection, WithHeadings
        {
            public function __construct(private array $kolom, private array $baris) {}

            public function headings(): array
            {
                return $this->kolom;
            }

            public function collection(): Enumerable
            {
                return collect($this->baris);
            }
        };

        Excel::store($export, 'import-tmp/test-new-cols.csv', 'local', \Maatwebsite\Excel\Excel::CSV);
        $filePath = Storage::disk('local')->path('import-tmp/test-new-cols.csv');

        $logImp = ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'test-new-cols.csv',
            'total_baris' => 0, 'sukses' => 0, 'gagal' => 0, 'status' => 'proses',
        ]);

        $hasil = $service->commit($filePath, $logImp->id, null);
        if ($hasil['gagal'] > 0) {
            dump($hasil['detail']);
        }

        $this->assertSame(1, $hasil['sukses']);
        $this->assertSame(0, $hasil['gagal']);

        $produk = Produk::where('nama', 'LCD iPhone 13 Pro Max OEM')->first();
        $this->assertNotNull($produk);
        $this->assertSame('oem', $produk->kondisi);
        $this->assertSame(5, $produk->min_stock);
        $this->assertSame(12, $produk->reorder_point);
        $this->assertTrue((bool) $produk->sn);
        $this->assertSame('LCD OEM High Brightness untuk iPhone 13 Pro Max', $produk->deskripsi);
        $this->assertNotNull($produk->kategori_id);
        $this->assertSame('Crown', $produk->brand?->nama);
        $this->assertSame('PT Supplier Utama', $produk->supplier?->nama);
        $this->assertSame('Grade A', $produk->kualitas?->nama);

        // Tipe HP pivot
        $this->assertTrue($produk->tipeHps()->where('model', 'iPhone 13 Pro Max')->exists());

        // Stok item jumlah_minimum = min_stock
        $stokItem = StokItem::where('produk_id', $produk->id)->first();
        $this->assertNotNull($stokItem);
        $this->assertSame(8, $stokItem->jumlah);
        $this->assertSame(5, $stokItem->jumlah_minimum);

        // Multi-satuan konversi terdaftar
        $this->assertCount(2, $produk->satuanKonversi);
        $boxKonv = $produk->satuanKonversi()->where('satuan_kode', 'box')->first();
        $this->assertNotNull($boxKonv);
        $this->assertEquals(10, (float) $boxKonv->pengali);
        $this->assertEquals(12000000, (float) $boxKonv->harga_jual);

        @unlink($filePath);
    }

    public function test_import_produk_multi_tipe_auto_typo_dan_substitusi_sku(): void
    {
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Pusat 2', 'kode' => 'CBG-02', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang Utama 2', 'kode' => 'GDG-02', 'is_active' => true]);
        $rak = Rak::create(['gudang_id' => $gudang->id, 'kode' => 'RK-02', 'nama' => 'Rak B1', 'is_active' => true]);

        $service = app(ImportProdukService::class);
        $kolom = $service->templateColumns();

        $baris = [
            [
                'sku' => 'LCD-A11-OG',
                'nama' => 'LCD Samsung A11 OG',
                'barcode' => '8999990002',
                'satuan' => 'pcs',
                'konversi_satuan' => '',
                'kategori' => 'LCD & Touchscreen',
                'sub_kategori' => 'LCD Touchscreen',
                'brand' => 'OG',
                'kualitas' => 'Grade A',
                'kondisi' => 'baru',
                'supplier' => 'Supplier Korea',
                'merk_hp' => 'samsng', // typo auto-normalized ke Samsung
                'tipe_hp' => 'Galaxy A11, Galaxy A15, Galaxy M11', // multi-tipe
                'harga_beli' => 150000,
                'harga_jual' => 250000,
                'harga_reseller' => 200000,
                'harga_agen' => 190000,
                'min_stock' => 5,
                'reorder_point' => 10,
                'sn' => 'tidak',
                'stok_awal' => 5,
                'gudang' => $gudang->nama,
                'rak' => $rak->nama,
                'produk_kompatibel_sku' => 'LCD-A11-BRD', // Substitusi ke produk kedua
                'kompatibilitas_hp' => '',
                'deskripsi' => 'LCD OG kompatibel multi model',
                'foto_url' => '',
            ],
            [
                'sku' => 'LCD-A11-BRD',
                'nama' => 'LCD Samsung A11 Braderpart',
                'barcode' => '8999990003',
                'satuan' => 'pcs',
                'konversi_satuan' => '',
                'kategori' => 'LCD & Touchscreen',
                'sub_kategori' => 'LCD Touchscreen',
                'brand' => 'Braderpart',
                'kualitas' => 'Original',
                'kondisi' => 'baru',
                'supplier' => 'Supplier Korea',
                'merk_hp' => 'Samsung',
                'tipe_hp' => 'Galaxy A11',
                'harga_beli' => 180000,
                'harga_jual' => 290000,
                'harga_reseller' => 230000,
                'harga_agen' => 220000,
                'min_stock' => 3,
                'reorder_point' => 8,
                'sn' => 'tidak',
                'stok_awal' => 3,
                'gudang' => $gudang->nama,
                'rak' => $rak->nama,
                'produk_kompatibel_sku' => '',
                'kompatibilitas_hp' => '',
                'deskripsi' => 'LCD Braderpart original quality',
                'foto_url' => '',
            ],
        ];

        $export = new class($kolom, $baris) implements FromCollection, WithHeadings
        {
            public function __construct(private array $kolom, private array $baris) {}

            public function headings(): array
            {
                return $this->kolom;
            }

            public function collection(): Enumerable
            {
                return collect($this->baris);
            }
        };

        Excel::store($export, 'import-tmp/test-multi-compat.csv', 'local', \Maatwebsite\Excel\Excel::CSV);
        $filePath = Storage::disk('local')->path('import-tmp/test-multi-compat.csv');

        $logImp = ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'test-multi-compat.csv',
            'total_baris' => 0, 'sukses' => 0, 'gagal' => 0, 'status' => 'proses',
        ]);

        $hasil = $service->commit($filePath, $logImp->id, null);

        $this->assertSame(2, $hasil['sukses']);
        $this->assertSame(0, $hasil['gagal']);

        $p1 = Produk::where('nama', 'LCD Samsung A11 OG')->first();
        $p2 = Produk::where('nama', 'LCD Samsung A11 Braderpart')->first();

        $this->assertNotNull($p1);
        $this->assertNotNull($p2);

        // Verifikasi normalisasi merk dari typo "samsng"
        $this->assertSame('Samsung', $p1->brand_kompatibel);

        // Verifikasi multi-tipe HP tersinkron (Galaxy A11, Galaxy A15, Galaxy M11)
        $tipeModels = $p1->tipeHps()->pluck('model')->all();
        $this->assertContains('Galaxy A11', $tipeModels);
        $this->assertContains('Galaxy A15', $tipeModels);
        $this->assertContains('Galaxy M11', $tipeModels);
        $this->assertSame('Samsung', $p1->tipeHps()->first()->merk);

        // Verifikasi relasi timbal-balik substitusi antar produk via SKU (Pass-2)
        $this->assertTrue($p1->produkKompatibel()->where('kompatibel_produk_id', $p2->id)->exists());
        $this->assertTrue($p2->produkKompatibel()->where('kompatibel_produk_id', $p1->id)->exists());

        @unlink($filePath);
    }

    public function test_import_produk_rak_dapat_dikosongkan_dan_relaksasi_validasi(): void
    {
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Relaksasi', 'kode' => 'CBG-RLX', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang Relaksasi', 'kode' => 'GDG-RLX', 'is_active' => true]);

        $service = app(ImportProdukService::class);
        $kolom = $service->templateColumns();

        $baris = [
            [
                'sku' => 'SKU-RAK-KOSONG',
                'nama' => 'Baterai Tanpa Rak',
                'barcode' => '',
                'satuan' => '', // kosong -> default pcs
                'konversi_satuan' => '',
                'kategori' => '',
                'sub_kategori' => '',
                'brand' => '',
                'kualitas' => '',
                'kondisi' => 'baru',
                'supplier' => '-', // strip -> null
                'merk_hp' => 'Universal',
                'tipe_hp' => '',
                'harga_beli' => 50000,
                'harga_jual' => 80000,
                'harga_reseller' => 70000,
                'harga_agen' => 65000,
                'min_stock' => 2,
                'reorder_point' => 5,
                'sn' => 'tidak',
                'stok_awal' => 4,
                'gudang' => $gudang->nama,
                'rak' => '-', // strip -> lokasi rak null
                'produk_kompatibel_sku' => '',
                'kompatibilitas_hp' => '',
                'deskripsi' => '',
                'foto_url' => '',
            ],
        ];

        $export = new class($kolom, $baris) implements FromCollection, WithHeadings
        {
            public function __construct(private array $kolom, private array $baris) {}

            public function headings(): array
            {
                return $this->kolom;
            }

            public function collection(): Enumerable
            {
                return collect($this->baris);
            }
        };

        Excel::store($export, 'import-tmp/test-rak-kosong.csv', 'local', \Maatwebsite\Excel\Excel::CSV);
        $filePath = Storage::disk('local')->path('import-tmp/test-rak-kosong.csv');

        $logImp = ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'test-rak-kosong.csv',
            'total_baris' => 0, 'sukses' => 0, 'gagal' => 0, 'status' => 'proses',
        ]);

        $hasil = $service->commit($filePath, $logImp->id, null);

        $this->assertSame(1, $hasil['sukses']);
        $this->assertSame(0, $hasil['gagal']);

        $p = Produk::where('nama', 'Baterai Tanpa Rak')->first();
        $this->assertNotNull($p);
        $this->assertNull($p->supplier_id);
        $this->assertSame('pcs', $p->satuan);

        $stok = StokItem::with('rak')->where('produk_id', $p->id)->first();
        $this->assertNotNull($stok);
        $this->assertSame(4, $stok->jumlah);
        $this->assertNotNull($stok->rak_id);
        $this->assertSame('Rak Default', $stok->rak?->nama);

        @unlink($filePath);
    }
}
