<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Wms\Livewire\MonitoringHargaTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\MarginGuardService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MarginGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TierMembershipSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'CU01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GU01', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Owner Store',
            'email' => 'owner@ute.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);

        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pcs', 'is_active' => true]);

        // Skema Komisi Default 5%
        KomisiSkema::create([
            'nama' => 'Komisi Penjualan Standar 5%',
            'aktor_tipe' => 'reseller',
            'trigger_tipe' => 'penjualan',
            'tipe' => 'persen',
            'nilai' => 5.0,
            'is_aktif' => true,
        ]);
    }

    public function test_margin_guard_kalkulasi_parameter_dan_harga_aman(): void
    {
        $service = app(MarginGuardService::class);
        $params = $service->getParameterMargin();

        // Tier Platinum di seeder adalah 10%
        $this->assertGreaterThanOrEqual(10.0, $params['max_mitra_diskon']);
        $this->assertEquals(5.0, $params['komisi_persen']);
        $this->assertEquals(5.0, $params['buffer_biaya_persen']);

        // Total potongan = 10 + 5 + 5 = 20%
        $this->assertEquals(20.0, $params['total_potongan_persen']);

        // HPP 100.000 / (1 - 0.20) = 100.000 / 0.80 = Rp 125.000
        $hargaAman = $service->hitungHargaAman(100000);
        $this->assertEquals(125000.0, $hargaAman);
    }

    public function test_margin_guard_evaluasi_status_bahaya_peringatan_dan_aman(): void
    {
        $service = app(MarginGuardService::class);

        // 1. Jual di bawah HPP modal -> Status BAHAYA (Rugi Keras)
        $evalRugi = $service->evaluasiHarga(100000, 95000);
        $this->assertEquals('bahaya', $evalRugi['status']);
        $this->assertStringContainsString('di bawah atau sama dengan HPP', $evalRugi['pesan']);

        // 2. Jual sama dengan HPP modal -> Status BAHAYA
        $evalImpas = $service->evaluasiHarga(100000, 100000);
        $this->assertEquals('bahaya', $evalImpas['status']);

        // 3. Jual di atas modal tapi di bawah rekomendasi aman (110.000 < 125.000) -> Status PERINGATAN (Margin Tipis)
        $evalTipis = $service->evaluasiHarga(100000, 110000);
        $this->assertEquals('peringatan', $evalTipis['status']);
        $this->assertEquals(125000.0, $evalTipis['rekomendasi_harga']);

        // 4. Jual di atas rekomendasi aman (130.000 >= 125.000) -> Status AMAN (Margin Sehat)
        $evalAman = $service->evaluasiHarga(100000, 130000);
        $this->assertEquals('aman', $evalAman['status']);
    }

    public function test_pembuatan_produk_manual_menolak_harga_jual_di_bawah_modal_hpp(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProdukTab::class)
            ->set('produkForm.nama', 'LCD iPhone 11 Pro')
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.harga_beli', 500000)
            ->set('produkForm.harga_jual_retail', 450000) // Di bawah modal HPP!
            ->set('produkForm.stok_awal', 0)
            ->call('simpanProduk')
            ->assertDispatched('alert', function ($event, $params) {
                return ($params[0]['type'] ?? null) === 'error'
                    && str_contains($params[0]['message'] ?? '', 'tidak boleh lebih rendah atau sama dengan HPP');
            });

        $this->assertDatabaseMissing('produk', ['nama' => 'LCD iPhone 11 Pro']);
    }

    public function test_import_produk_menolak_baris_dengan_harga_jual_di_bawah_hpp(): void
    {
        $service = app(ImportProdukService::class);

        $rowRugi = [
            'sku' => 'SKU-RUGI-01',
            'nama' => 'Kabel Data Defisit',
            'satuan' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual' => 40000, // Di bawah modal 50.000
            'stok_awal' => 0,
        ];

        $errors = $service->validateRow($rowRugi, ['skus' => [], 'barcodes' => []]);

        $this->assertNotEmpty($errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'potensi rugi langsung')));
    }

    public function test_monitoring_harga_tab_scans_and_auto_fixes_risky_products(): void
    {
        // 1. Buat produk bermargin tipis & produk berisiko rugi
        $kategori = KategoriProduk::create(['nama' => 'LCD', 'slug' => 'lcd', 'is_active' => true]);

        $produkTepis = Produk::create([
            'nama' => 'LCD Xiaomi Redmi Note 10',
            'slug' => 'lcd-redmi-note-10-'.Str::random(4),
            'kategori' => 'LCD',
            'kategori_id' => $kategori->id,
            'harga_beli' => 100000,
            'harga_jual_retail' => 105000, // Margin hanya 5%, butuh min 125.000
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        SkuVariant::create([
            'produk_id' => $produkTepis->id,
            'sku' => 'SKU-REDMI-10',
            'barcode' => 'BAR-REDMI-10',
            'nama_varian' => 'Original',
            'satuan_kode' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 105000,
            'is_active' => true,
        ]);

        // 2. Render MonitoringHargaTab
        $test = Livewire::actingAs($this->admin)
            ->test(MonitoringHargaTab::class)
            ->assertSee('Monitoring Margin')
            ->assertSee('LCD Xiaomi Redmi Note 10')
            ->assertSee('Rp 100.000')
            ->assertSee('Rp 105.000')
            ->assertSee('Rp 125.000');

        // 3. Eksekusi Auto-Fix via terapkanSatu
        $test->call('terapkanSatu', $produkTepis->id)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $produkTepis->refresh();

        // Terbukti harga disesuaikan otomatis ke harga aman Rp 125.000
        $this->assertEquals(125000.0, (float) $produkTepis->harga_jual_retail);
        $this->assertEquals(20.0, (float) $produkTepis->margin_persen);
    }

    public function test_staf_biasa_dilarang_mengakses_monitoring_harga(): void
    {
        $staf = User::create([
            'name' => 'Staf Biasa',
            'email' => 'staf@ute.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $staf->assignRole('kasir');

        Livewire::actingAs($staf)
            ->test(MonitoringHargaTab::class)
            ->assertForbidden();
    }
}
