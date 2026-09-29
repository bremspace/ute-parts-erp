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
                'kategori' => 'LCD & Touchscreen',
                'tipe_hp' => 'Apple iPhone 13 Pro Max',
                'brand' => 'Crown',
                'kualitas' => 'Grade A',
                'kondisi' => 'oem',
                'harga_beli' => 750000,
                'harga_jual' => 1100000,
                'harga_reseller' => 950000,
                'harga_agen' => 900000,
                'min_stock' => 5,
                'reorder_point' => 12,
                'sn' => 'ya',
                'stok_awal' => 8,
                'gudang_id' => $gudang->id,
                'rak_id' => $rak->id,
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
        $this->assertSame('Grade A', $produk->kualitas?->nama);

        // Tipe HP pivot
        $this->assertTrue($produk->tipeHps()->where('model', 'iPhone 13 Pro Max')->exists());

        // Stok item jumlah_minimum = min_stock
        $stokItem = StokItem::where('produk_id', $produk->id)->first();
        $this->assertNotNull($stokItem);
        $this->assertSame(8, $stokItem->jumlah);
        $this->assertSame(5, $stokItem->jumlah_minimum);

        @unlink($filePath);
    }
}
