<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\MarginGuardService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CrmPotonganSsotTest extends TestCase
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

        $this->cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Pusat', 'kode' => 'GP01', 'is_active' => true]);

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
    }

    public function test_ssot_ringkasan_potongan_sistem_terintegrasi(): void
    {
        $konfig = app(KonfigurasiService::class);

        // Atur nilai potongan di konfigurasi CRM
        $konfig->set('reseller_default_persen', 5.0);
        $konfig->set('agen_default_persen', 12.0);
        $konfig->set('margin_guard_buffer_persen', 6.0);
        $konfig->set('payment_gateway_fee_persen', 1.5);
        $konfig->set('komisi_agen_default_persen', 5.5);

        $ringkasan = $konfig->getRingkasanPotonganSistem();

        // max_mitra_diskon = max(Tier Platinum 10%, agen 12%, reseller 5%) = 12%
        $this->assertEquals(12.0, $ringkasan['max_mitra_diskon']);
        $this->assertEquals(6.0, $ringkasan['buffer_biaya_persen']);
        $this->assertEquals(1.5, $ringkasan['gateway_fee_persen']);
        $this->assertEquals(5.5, $ringkasan['komisi_persen']);

        // Total potongan = 12 + 5.5 + 6 + 1.5 = 25%
        $this->assertEquals(25.0, $ringkasan['total_potongan_persen']);

        // Margin guard mengonsumsi SSOT ini
        $marginGuard = app(MarginGuardService::class);
        $params = $marginGuard->getParameterMargin();
        $this->assertEquals(25.0, $params['total_potongan_persen']);

        // HPP 75.000 / (1 - 0.25) = 75.000 / 0.75 = Rp 100.000
        $hargaAman = $marginGuard->hitungHargaAman(75000);
        $this->assertEquals(100000.0, $hargaAman);
    }

    public function test_crm_dashboard_mengelola_dan_menyimpan_seluruh_potongan_ssot(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CrmDashboard::class)
            ->set('activeTab', 'tier')
            ->assertSee('SSOT Beban Potongan')
            ->set('agenDefaultPersen', 15.0)
            ->set('resellerDefaultPersen', 7.0)
            ->set('marginGuardBufferPersen', 5.0)
            ->set('paymentGatewayFeePersen', 2.0)
            ->set('poinMaksPotonganPersen', 40.0)
            ->set('komisiAgenDefaultPersen', 6.0)
            ->call('simpanPengaturanLoyaltyGlobal')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $konfig = app(KonfigurasiService::class);
        $this->assertEquals(15.0, $konfig->agenDefaultPersen());
        $this->assertEquals(7.0, $konfig->resellerDefaultPersen());
        $this->assertEquals(5.0, $konfig->marginGuardBufferPersen());
        $this->assertEquals(2.0, $konfig->paymentGatewayFeePersen());
        $this->assertEquals(40.0, $konfig->poinMaksPotonganPersen());
        $this->assertEquals(6.0, $konfig->komisiAgenDefaultPersen());
    }

    public function test_pos_kasir_mematuhi_batas_maksimal_potongan_poin_ssot(): void
    {
        $konfig = app(KonfigurasiService::class);
        // Batasi potongan poin maksimal 20% dari subtotal
        $konfig->set('poin_maks_potongan_persen', 20.0);
        $konfig->set('poin_redeem_rupiah', 1000.0); // 1 pt = Rp 1.000

        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'poin_loyalty' => 500, // punya 500 poin = senilai Rp 500.000
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Baterai iPhone 12',
            'slug' => 'baterai-iphone-12',
            'harga_beli' => 150000,
            'harga_jual_retail' => 200000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'BAT-IP12',
            'barcode' => 'BAR-BAT-IP12',
            'nama_varian' => 'Original',
            'satuan_kode' => 'pcs',
            'harga_beli' => 150000,
            'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);

        // Cart 1 pcs @ 200.000 -> subtotal = 200.000
        // Batas maks potongan 20% = Rp 40.000 (maks 40 poin)
        $component = Livewire::actingAs($this->admin)
            ->test(PosKasir::class)
            ->call('setPelanggan', $pelanggan->id)
            ->set('cart', [
                [
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'sku' => 'BAT-IP12',
                    'nama' => 'Baterai iPhone 12',
                    'harga' => 200000.0,
                    'harga_dasar' => 200000.0,
                    'qty' => 1,
                    'subtotal' => 200000.0,
                    'satuan' => 'pcs',
                    'konversi' => 1,
                    'sn_list' => [],
                    'diskon' => 0.0,
                    'potensi_komisi' => 0.0,
                    'tier_nama' => null,
                ],
            ]);

        // Tukar 100 poin (seharusnya Rp 100.000, tetapi dicap di Rp 40.000 karena batas 20%)
        $component->set('poinDitukar', 100);

        $this->assertEquals(40, $component->get('poinDitukar'));
        $this->assertEquals(40000.0, $component->get('diskonPoin'));
    }

    public function test_staf_tanpa_izin_dilarang_mengubah_konfigurasi_potongan_crm(): void
    {
        $staf = User::create([
            'name' => 'Staf Biasa',
            'email' => 'kasir@ute.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $staf->assignRole('kasir');

        Livewire::actingAs($staf)
            ->test(CrmDashboard::class)
            ->call('simpanPengaturanLoyaltyGlobal')
            ->assertDispatched('alert', function ($event, $params) {
                return ($params[0]['type'] ?? null) === 'error'
                    && str_contains($params[0]['message'] ?? '', 'tidak memiliki izin');
            });
    }
}
