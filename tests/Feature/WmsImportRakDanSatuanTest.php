<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\AkunCoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WmsImportRakDanSatuanTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $admin;

    private ImportProdukService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-01',
            'nama' => 'Gudang Utama',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();

        $this->service = app(ImportProdukService::class);
    }

    public function test_import_satuan_dan_rak_baru_tidak_error_dan_dibuat_otomatis(): void
    {
        // Pastikan satuan 'slop' dan rak 'Rak Z99' belum ada di sistem
        $this->assertFalse(SatuanUnit::where('kode', 'slop')->exists());
        $this->assertFalse(Rak::where('nama', 'Rak Z99')->exists());

        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang;rak',
            'KBL-001;Kabel Tembaga 1M;slop;10000;15000;5;Gudang Utama;Rak Z99',
            'KBL-002;Kabel Tembaga 2M;slop;18000;25000;10;Gudang Utama;Rak Z99',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_rak_satuan.csv';
        file_put_contents($filePath, $csv);

        try {
            // 1. Preview harus valid (tidak ada error satuan tidak terdaftar)
            $preview = $this->service->preview($filePath);
            $this->assertSame(2, $preview['total_baris']);
            $this->assertSame(2, $preview['valid']);
            $this->assertSame(0, $preview['invalid']);

            // 2. Commit import
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id);

            $this->assertSame(2, $hasil['sukses']);
            $this->assertSame(0, $hasil['gagal']);

            // 3. Verifikasi Satuan dibuat di master
            $this->assertTrue(SatuanUnit::where('kode', 'slop')->exists());

            // 4. Verifikasi Rak dibuat di master
            $rak = Rak::where('nama', 'Rak Z99')->first();
            $this->assertNotNull($rak);
            $this->assertSame($this->gudang->id, $rak->gudang_id);

            // 5. Verifikasi produk tersimpan dengan satuan dan rak tersebut
            $var = SkuVariant::with('produk')->where('sku', 'KBL-001')->first();
            $this->assertNotNull($var);
            $this->assertSame('slop', $var->satuan_kode);
            $this->assertSame('slop', $var->produk->satuan);
            $this->assertDatabaseHas('stok_items', [
                'produk_id' => $var->produk_id,
                'gudang_id' => $this->gudang->id,
                'rak_id' => $rak->id,
                'jumlah' => 5,
            ]);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_payload_preview_dibatasi_untuk_mencegah_payload_too_large(): void
    {
        // Buat 50 baris dengan potensi warning/kemiripan
        $lines = ['sku;nama;satuan;harga_beli;harga_jual'];
        for ($i = 1; $i <= 50; $i++) {
            $lines[] = "TEST-SKU-{$i};Test Produk LCD iPhone {$i};pcs;100000;150000";
        }
        $csv = implode("\n", $lines);

        $filePath = sys_get_temp_dir().'/test_import_payload_size.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);

            // Total baris harus 50
            $this->assertSame(50, $preview['total_baris']);

            // Sampel & warning_rows harus dibatasi maksimal 10 baris
            $this->assertLessThanOrEqual(5, count($preview['sampel']));
            $this->assertLessThanOrEqual(10, count($preview['warning_rows']));
            $this->assertLessThanOrEqual(20, count($preview['konfirmasi']));

            // Payload JSON tidak boleh bengkak
            $jsonLength = strlen(json_encode($preview));
            // Harus jauh di bawah 100KB (bukan 2228KB)
            $this->assertLessThan(100 * 1024, $jsonLength);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_import_auto_create_gudang_default_jika_belum_ada_gudang_sama_sekali(): void
    {
        // Hapus gudang agar sistem benar-benar kosong dari gudang
        Gudang::query()->delete();
        $this->assertSame(0, Gudang::count());

        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang;rak',
            'AUTO-G01;Produk Auto Gudang;lusin;50000;75000;0;;Rak Mandiri',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_auto_gudang.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertSame(1, $preview['total_baris']);
            $this->assertSame(1, $preview['valid']);

            // Ada konfirmasi pembuatan gudang dan rak otomatis
            $konfirmasiTipe = collect($preview['konfirmasi'])->pluck('tipe')->all();
            $this->assertContains('gudang', $konfirmasiTipe);
            $this->assertContains('rak', $konfirmasiTipe);

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_auto_gudang.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id);
            $this->assertSame(1, $hasil['sukses']);

            // Gudang otomatis terbuat
            $gudangBaru = Gudang::where('nama', 'Gudang Utama')->first();
            $this->assertNotNull($gudangBaru);

            // Rak otomatis terbuat dengan parent gudang yang baru dibuat
            $rakBaru = Rak::where('nama', 'Rak Mandiri')->first();
            $this->assertNotNull($rakBaru);
            $this->assertSame($gudangBaru->id, $rakBaru->gudang_id);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_rak_kosong_dialokasikan_ke_rak_default_bawaan_sistem(): void
    {
        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang;rak',
            'DEF-RAK-01;Produk Tanpa Rak;pcs;10000;15000;8;Gudang Utama;',
        ]);

        $filePath = sys_get_temp_dir().'/test_rak_kosong_default.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertSame(1, $preview['valid']);
            $this->assertSame(0, $preview['invalid']);

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_def_rak.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id);
            $this->assertSame(1, $hasil['sukses']);

            $p = Produk::where('nama', 'Produk Tanpa Rak')->first();
            $this->assertNotNull($p);

            $stok = StokItem::with('rak')->where('produk_id', $p->id)->first();
            $this->assertNotNull($stok);
            $this->assertSame(8, $stok->jumlah);
            $this->assertNotNull($stok->rak_id);
            $this->assertSame('Rak Default', $stok->rak?->nama);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_threshold_pencocokan_diperketat_ke_99_persen_dan_deteksi_kemiripan(): void
    {
        // Buat produk eksisting di database
        $p = Produk::create([
            'nama' => 'LCD Samsung Galaxy A52s 5G Black',
            'slug' => 'lcd-samsung-galaxy-a52s-5g-black',
            'kategori' => 'LCD',
            'harga_beli' => 200000,
            'harga_jual_retail' => 300000,
        ]);
        SkuVariant::create([
            'produk_id' => $p->id,
            'sku' => 'SAM-A52S-BLK',
            'nama_varian' => 'Default',
            'harga_beli' => 200000,
            'harga_jual_retail' => 300000,
        ]);

        $konteks = ['skuDalamFile' => [], 'barcodeDalamFile' => [], 'namaDalamFile' => []];

        // 1. Nama ini memiliki kemiripan sekitar 96% (di bawah 99%) -> TIDAK boleh memicu kemiripan pada default 99%
        $row96 = [
            'sku' => 'TEST-96-PCT',
            'nama' => 'LCD Samsung Galaxy A52s 5G Black A',
            'tipe_hp' => '',
            'stok_awal' => 0,
        ];
        $warnings96 = $this->service->checkRowWarnings($row96, 2, $konteks);
        $hasMirip96 = collect($warnings96)->contains(fn ($w) => str_contains($w, 'Perhatian Master Ganda'));
        $this->assertFalse($hasMirip96, 'Kemiripan di bawah 99% tidak boleh memicu warning master ganda');
        $this->assertNull($this->service->detectKemiripanRow($row96, 2, $konteks));

        // Tetapi jika threshold diatur manual ke 90%, produk ini HARUS terdeteksi
        $kemiripan90 = $this->service->detectKemiripanRow($row96, 2, $konteks, 90);
        $this->assertNotNull($kemiripan90, 'Threshold 90% harus mendeteksi kemiripan nama 96%');
        $this->assertGreaterThanOrEqual(90, $kemiripan90['persentase']);

        // 2. Nama identik (100% >= 99%) -> HARUS memicu kemiripan
        $row99 = [
            'sku' => 'TEST-99-PCT',
            'nama' => 'LCD Samsung Galaxy A52s 5G Black',
            'tipe_hp' => '',
            'stok_awal' => 0,
        ];
        $warnings99 = $this->service->checkRowWarnings($row99, 3, $konteks);
        $hasMirip99 = collect($warnings99)->contains(fn ($w) => str_contains($w, 'Perhatian Master Ganda'));
        $this->assertTrue($hasMirip99, 'Kemiripan >= 99% harus memicu warning master ganda');

        $kemiripan = $this->service->detectKemiripanRow($row99, 3, $konteks);
        $this->assertNotNull($kemiripan);
        $this->assertSame('database', $kemiripan['tipe_kemiripan']);
        $this->assertSame($p->id, $kemiripan['target_id']);
        $this->assertSame('gabung', $kemiripan['default_aksi']);
    }

    public function test_pencocokan_manual_gabung_terpisah_dan_skip(): void
    {
        $existing = Produk::create([
            'nama' => 'Baterai iPhone 13 Pro Max Original',
            'slug' => 'baterai-iphone-13-pro-max-original',
            'kategori' => 'Baterai',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
        ]);
        SkuVariant::create([
            'produk_id' => $existing->id,
            'sku' => 'BAT-IP13PM-01',
            'nama_varian' => 'Default',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
        ]);

        $csv = implode("\n", [
            'sku;nama;satuan;brand;kategori;tipe_hp;harga_beli;harga_jual;stok_awal;gudang;rak',
            'BAT-GABUNG-01;Baterai iPhone 13 Pro Max Original;pcs;OEM;Baterai;Apple iPhone 13 Pro Max;150000;250000;5;Gudang Utama;Rak A1',
            'BAT-SKIP-02;Baterai iPhone 13 Pro Max Original;pcs;OEM;Baterai;Apple iPhone 13 Pro Max;150000;250000;10;Gudang Utama;Rak A1',
            'BAT-PISAH-03;Baterai iPhone 13 Pro Max Original;pcs;OEM;Baterai;Apple iPhone 13 Pro Max;150000;250000;2;Gudang Utama;Rak A1',
        ]);

        $filePath = sys_get_temp_dir().'/test_aksi_kemiripan.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertSame(3, $preview['valid']);
            $this->assertCount(3, $preview['kemiripan_rows']);

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_aksi_kemiripan.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            // Instruksi tindakan manual:
            // Baris 2 -> gabung ke master eksisting
            // Baris 3 -> skip (lewati)
            // Baris 4 -> terpisah (buat produk baru)
            $aksi = [
                2 => 'gabung',
                3 => 'skip',
                4 => 'terpisah',
            ];

            $out = $this->service->commit($filePath, $log->id, $this->admin->id, $aksi);

            $this->assertSame(2, $out['sukses'], 'Baris gabung dan terpisah harus sukses');
            $this->assertSame(0, $out['gagal']);

            // Produk eksisting digabungkan dan stok bertambah 5
            $stokGabung = StokItem::where('produk_id', $existing->id)->first();
            $this->assertNotNull($stokGabung);
            $this->assertSame(5, $stokGabung->jumlah);

            // Baris skip tidak membuat produk baru
            $this->assertFalse(SkuVariant::where('sku', 'BAT-SKIP-02')->exists());

            // Baris terpisah membuat master produk baru
            $this->assertTrue(SkuVariant::where('sku', 'BAT-PISAH-03')->exists());
            $produkBaru = SkuVariant::where('sku', 'BAT-PISAH-03')->first()->produk;
            $this->assertNotSame($existing->id, $produkBaru->id);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_seluruh_baris_error_dilaporkan_lengkap_tanpa_pemotongan(): void
    {
        // Buat CSV dengan 15 baris error (SKU kosong dan nama kosong)
        $lines = ['sku;nama;satuan;harga_beli;harga_jual;stok_awal'];
        for ($i = 1; $i <= 15; $i++) {
            $lines[] = ';;pcs;10000;15000;0';
        }
        $csv = implode("\n", $lines);

        $filePath = sys_get_temp_dir().'/test_error_lengkap.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertSame(15, $preview['invalid']);
            $this->assertCount(15, $preview['error_rows'], 'Semua 15 baris error harus dilaporkan satu per satu tanpa dipotong 10');
            $this->assertSame(2, $preview['error_rows'][0]['baris']);
            $this->assertSame(16, $preview['error_rows'][14]['baris']);
            $this->assertNotEmpty($preview['error_rows'][0]['errors']);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_import_gudang_baru_didaftarkan_otomatis_tanpa_error(): void
    {
        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang;rak',
            'GDG-NEW-01;Produk Gudang Baru;pcs;10000;15000;3;Gudang Transit Baru;Rak T1',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_gudang_baru.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertSame(1, $preview['valid']);
            $this->assertSame(0, $preview['invalid']);

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_gdg_baru.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id);
            $this->assertSame(1, $hasil['sukses']);

            $gudangBaru = Gudang::where('nama', 'Gudang Transit Baru')->first();
            $this->assertNotNull($gudangBaru);

            $rakBaru = Rak::where('nama', 'Rak T1')->where('gudang_id', $gudangBaru->id)->first();
            $this->assertNotNull($rakBaru);

            $stok = StokItem::where('gudang_id', $gudangBaru->id)->where('rak_id', $rakBaru->id)->first();
            $this->assertNotNull($stok);
            $this->assertSame(3, $stok->jumlah);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_rak_dan_satuan_otomatis_terdaftar_di_master_seperti_brand_kategori_supplier_tanpa_warning(): void
    {
        // Pastikan satuan 'pak' dan rak 'Rak Display 88' belum ada
        $this->assertFalse(SatuanUnit::where('kode', 'pak')->exists());
        $this->assertFalse(Rak::where('nama', 'Rak Display 88')->exists());

        $csv = implode("\n", [
            'sku;nama;satuan;brand;kategori;supplier;harga_beli;harga_jual;stok_awal;gudang;rak',
            'AUTO-MSTR-01;Sparepart Layar;pak;Brand Baru Cerdas;Kategori Baru Cerdas;Supplier Baru Cerdas;150000;200000;0;Gudang Utama;Rak Display 88',
        ]);

        $filePath = sys_get_temp_dir().'/test_mstr_auto.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertSame(1, $preview['valid']);
            $this->assertSame(0, $preview['invalid']);
            $this->assertSame(0, $preview['total_peringatan'], 'Rak baru tidak boleh memicu warning baris, harus bersih seperti brand/kategori/supplier');

            // Konfirmasi master baru harus mencakup brand, kategori, supplier, satuan, dan rak
            $konfirmasiTipe = collect($preview['konfirmasi'])->pluck('tipe')->all();
            $this->assertContains('brand', $konfirmasiTipe);
            $this->assertContains('kategori', $konfirmasiTipe);
            $this->assertContains('supplier', $konfirmasiTipe);
            $this->assertContains('satuan', $konfirmasiTipe);
            $this->assertContains('rak', $konfirmasiTipe);

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_mstr.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id);
            $this->assertSame(1, $hasil['sukses']);

            // Seluruh entitas master baru terdaftar otomatis
            $this->assertTrue(SatuanUnit::where('kode', 'pak')->exists());
            $this->assertTrue(Rak::where('nama', 'Rak Display 88')->exists());
            $this->assertDatabaseHas('brands', ['nama' => 'Brand Baru Cerdas']);
            $this->assertDatabaseHas('kategori_produk', ['nama' => 'Kategori Baru Cerdas']);
            $this->assertDatabaseHas('supplier', ['nama' => 'Supplier Baru Cerdas']);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_deteksi_duplikat_sku_dan_resolusi_interaktif_ganti_sku_timpa_dan_skip(): void
    {
        // Setup produk eksisting dengan SKU 'DUP-SKU-01'
        $existing = Produk::create([
            'nama' => 'Kabel Data Type C 1M',
            'slug' => 'kabel-data-type-c-1m',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
        ]);
        $existingVariant = SkuVariant::create([
            'produk_id' => $existing->id,
            'sku' => 'DUP-SKU-01',
            'nama_varian' => 'Default',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
        ]);

        $csv = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang;rak',
            'DUP-SKU-01;Kabel Data Type C 1M Updated;pcs;12000;25000;10;Gudang Utama;Rak A1', // Akan ditimpa (update)
            'DUP-SKU-01;Kabel Data Type C Versi Baru;pcs;15000;30000;5;Gudang Utama;Rak A1',  // Akan ganti SKU -> DUP-SKU-01-V2
            'DUP-SKU-01;Kabel Data Rusak;pcs;5000;10000;1;Gudang Utama;Rak A1',              // Akan di-skip
        ]);

        $filePath = sys_get_temp_dir().'/test_resolusi_duplikat.csv';
        file_put_contents($filePath, $csv);

        try {
            $preview = $this->service->preview($filePath);
            $this->assertNotEmpty($preview['duplikat_rows']);
            $this->assertSame(3, $preview['total_duplikat_rows']);

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_resolusi_duplikat.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $resolusi = [
                2 => ['aksi' => 'timpa_update'],
                3 => ['aksi' => 'ganti_sku', 'sku_baru' => 'DUP-SKU-01-V2'],
                4 => ['aksi' => 'skip'],
            ];

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id, [], $resolusi);

            // Baris 2 (timpa) sukses, Baris 3 (ganti sku) sukses, Baris 4 (skip) di-skip
            $this->assertSame(2, $hasil['sukses']);
            $this->assertSame(0, $hasil['gagal']);

            // 1. Verifikasi baris 2: produk eksisting terupdate harga dan stok bertambah 10
            $existingVariant->refresh();
            $this->assertEquals(12000, (float) $existingVariant->harga_beli);
            $this->assertEquals(25000, (float) $existingVariant->harga_jual_retail);
            $stokTimpa = StokItem::where('produk_id', $existing->id)->where('sku_variant_id', $existingVariant->id)->first();
            $this->assertNotNull($stokTimpa);
            $this->assertSame(10, $stokTimpa->jumlah);

            // 2. Verifikasi baris 3: produk baru dengan SKU 'DUP-SKU-01-V2' terbuat
            $newVariant = SkuVariant::where('sku', 'DUP-SKU-01-V2')->first();
            $this->assertNotNull($newVariant);
            $this->assertSame('Kabel Data Type C Versi Baru', $newVariant->produk->nama);

            // 3. Verifikasi baris 4: 'Kabel Data Rusak' di-skip dan tidak ada di database
            $this->assertFalse(Produk::where('nama', 'Kabel Data Rusak')->exists());
        } finally {
            @unlink($filePath);
        }
    }

    public function test_workspace_import_export_page_render_berhasil(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->admin->assignRole($role);

        $response = $this->actingAs($this->admin)->get(route('wms.import-export'));
        $response->assertStatus(200);
        $response->assertSee('Workspace Export & Import Produk');
    }

    public function test_livewire_import_export_page_threshold_dapat_diatur_manual(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->admin->assignRole($role);

        Livewire::actingAs($this->admin)
            ->test(ImportExportPage::class)
            ->assertSet('thresholdKemiripan', 99)
            ->set('thresholdKemiripan', 95)
            ->assertSet('thresholdKemiripan', 95)
            ->set('thresholdKemiripan', 90)
            ->assertSet('thresholdKemiripan', 90);
    }
}
