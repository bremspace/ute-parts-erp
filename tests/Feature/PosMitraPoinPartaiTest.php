<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PosMitraPoinPartaiTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $kasir;

    protected Produk $produk;

    protected Pelanggan $member;

    protected Pelanggan $reseller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '110-02' => ['Bank', 'aset', 'kas', 'debit'],
            '110-04' => ['Kas Laci', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'C01',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-01',
            'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir POS',
            'email' => 'kasir@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->kasir->assignRole('super-admin');
        $this->kasir->cabangs()->attach($this->cabang->id);

        $tier = TierMembership::create([
            'nama' => 'Silver',
            'kode' => 'SLV',
            'min_belanja_12bulan' => 500000,
            'diskon_persen' => 5,
            'poin_multiplier' => 1,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->member = Pelanggan::create([
            'nama' => 'Member Poin',
            'telepon' => '08123456701',
            'email' => 'poin@test.com',
            'tier_membership_id' => $tier->id,
            'poin_loyalty' => 200, // 200 pts x Rp 100 = Rp 20.000
            'is_reseller' => false,
            'tipe_konsumen' => 'retail',
            'status' => 'approved',
        ]);

        $this->reseller = Pelanggan::create([
            'nama' => 'Mitra Reseller',
            'telepon' => '08123456702',
            'email' => 'reseller@test.com',
            'tier_membership_id' => null,
            'is_reseller' => true,
            'tipe_konsumen' => 'reseller',
            'mode_reward_mitra' => 'komisi_akumulasi',
            'status' => 'approved',
        ]);

        $this->produk = Produk::create([
            'nama' => 'Sparepart LCD',
            'slug' => Str::slug('Sparepart LCD').'-'.Str::random(4),
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'harga_reseller' => 85000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $this->produk->id,
            'sku' => 'SKU-LCD-01',
            'barcode' => 'BAR-LCD-01',
            'nama_varian' => 'Original',
            'satuan_kode' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'harga_reseller' => 85000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
        ]);

        HargaTier::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $variant->id,
            'tier_membership_id' => null,
            'tipe_konsumen' => 'reseller',
            'nominal_tetap' => 85000,
            'harga' => 85000,
            'is_reseller' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        app(KasSesiState::class)->bukaKas(
            saldoAwal: 50000,
            cabangId: $this->cabang->id,
            userId: $this->kasir->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );
    }

    public function test_penukaran_poin_member_memotong_total_dan_mengurangi_saldo(): void
    {
        $this->actingAs($this->kasir);

        // Member beli 1 pcs retail 100.000, tier diskon 5% -> subtotal 95.000.
        // Tukar 100 poin (Rp 10.000). Total akhir jadi 85.000.
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id)
            ->set('poinDitukar', 100)
            ->assertSet('poinDitukar', 100)
            ->assertSet('diskonPoin', 10000.0)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals(100, $trx->poin_ditukar);
        $this->assertEquals(10000.0, (float) $trx->diskon_poin);
        $this->assertEquals(85000.0, (float) $trx->total_akhir);

        // Cek poin di model member berkurang dari 200 (-100 tukar + 85 dapat dari belanja 85rb) -> 185
        $this->member->refresh();
        $this->assertEquals(185, $this->member->poin_loyalty);
    }

    public function test_harga_partai_otomatis_untuk_member_saat_qty_memenuhi_syarat(): void
    {
        $this->actingAs($this->kasir);

        // Min Qty partai = 5 pcs, diskon 3%
        // Beli 5 pcs LCD @ Rp 95.000 (setelah tier 5%) = Subtotal 475.000
        // Diskon partai 3% dari 475.000 = Rp 14.250
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id);

        $cartKey = array_key_first($test->get('cart'));

        $test->call('updateQty', $cartKey, 4) // total 5 pcs
            ->assertSet('diskonPartai', 14250.0)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 500000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals(14250.0, (float) $trx->diskon_partai);
        $this->assertEquals(460750.0, (float) $trx->total_akhir);
    }

    public function test_reseller_mode_komisi_akumulasi_pakai_harga_retail_dan_catat_komisi(): void
    {
        $this->actingAs($this->kasir);

        // Reseller mode komisi_akumulasi:
        // Harga jual kasir tetap retail 100.000, selisih 15.000 tercatat sebagai potensi komisi
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->reseller->id)
            ->assertSet('modeRewardMitra', 'komisi_akumulasi');

        $cart = $test->get('cart');
        $item = reset($cart);
        $this->assertEquals(100000.0, (float) $item['harga']);
        $this->assertEquals(15000.0, (float) ($item['potensi_komisi'] ?? 0));

        $test->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals('komisi_akumulasi', $trx->mode_reward_mitra);
        $this->assertEquals(15000.0, (float) $trx->komisi_mitra_nominal);
        $this->assertEquals(100000.0, (float) $trx->total_akhir);

        $this->assertGreaterThan(0, Komisi::count(), 'Komisi count is 0! Transaksi: '.json_encode($trx->toArray()));
    }

    public function test_rincian_struk_transparan_mencatat_diskon_partai_poin_dan_saldo(): void
    {
        $this->actingAs($this->kasir);

        // Beli 5 pcs LCD @ 100rb retail -> 5 pcs @ 95rb (tier 5%) = 475rb subtotal
        // Partai 3% = 14.250
        // Tukar 50 poin = 5.000
        // Total = 475.000 - 14.250 - 5.000 = 455.750
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id);

        $cartKey = array_key_first($test->get('cart'));
        $test->call('updateQty', $cartKey, 4)
            ->set('poinDitukar', 50)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 500000)
            ->call('processTransaction');

        $receipt = $test->get('receiptData');
        $this->assertNotNull($receipt);
        $this->assertEquals(500000.0, (float) $receipt['subtotal_retail']);
        $this->assertEquals(25000.0, (float) $receipt['diskon_tier']);
        $this->assertEquals(14250.0, (float) $receipt['diskon_partai']);
        $this->assertEquals(50, (int) $receipt['poin_ditukar']);
        $this->assertEquals(5000.0, (float) $receipt['diskon_poin']);
        $this->assertEquals(455750.0, (float) $receipt['total']);
        $this->assertArrayHasKey('saldo_poin', $receipt);
        $this->assertArrayHasKey('hemat_total', $receipt);
        $this->assertEquals(44250.0, (float) $receipt['hemat_total']); // 25.000 + 14.250 + 5.000
    }

    public function test_penukaran_poin_menggunakan_rate_kustom_tier_membership(): void
    {
        $this->actingAs($this->kasir);

        // Buat tier VIP dengan rate kustom: 1 poin = Rp 250
        $tierVip = TierMembership::create([
            'nama' => 'VIP Member',
            'kode' => 'VIP',
            'min_belanja_12bulan' => 2000000,
            'diskon_persen' => 10,
            'poin_multiplier' => 2,
            'poin_redeem_rupiah' => 250,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $memberVip = Pelanggan::create([
            'nama' => 'Sultan VIP',
            'telepon' => '08123456799',
            'email' => 'vip@test.com',
            'tier_membership_id' => $tierVip->id,
            'poin_loyalty' => 100, // 100 pts x Rp 250 = Rp 25.000
            'is_reseller' => false,
            'tipe_konsumen' => 'retail',
            'status' => 'approved',
        ]);

        $rateVip = app(PelangganService::class)->nilaiTukarPoinPelanggan($memberVip);
        $this->assertEquals(250.0, $rateVip);

        // Fallback untuk member biasa tanpa kustom: 1 poin = Rp 100
        $rateBiasa = app(PelangganService::class)->nilaiTukarPoinPelanggan($this->member);
        $this->assertEquals(100.0, $rateBiasa);

        // POS Kasir dengan Member VIP:
        // Produk retail 100.000, diskon tier VIP 10% = Rp 90.000
        // Tukar 40 poin x Rp 250 = Rp 10.000 diskon poin.
        // Total akhir = 80.000
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $memberVip->id)
            ->set('poinDitukar', 40)
            ->assertSet('poinDitukar', 40)
            ->assertSet('diskonPoin', 10000.0)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals(40, $trx->poin_ditukar);
        $this->assertEquals(10000.0, (float) $trx->diskon_poin);
        $this->assertEquals(80000.0, (float) $trx->total_akhir);
    }

    public function test_crm_dashboard_bisa_mengubah_konfigurasi_loyalty_global_dan_rate_tier_kustom(): void
    {
        $this->actingAs($this->kasir);

        // 1. Simpan konfigurasi loyalty global dengan opsi metode
        Livewire::test(CrmDashboard::class)
            ->set('poinRedeemRupiahGlobal', 175)
            ->set('hargaPartaiAktif', true)
            ->set('hargaPartaiOpsiQty', true)
            ->set('hargaPartaiMinQty', 8)
            ->set('hargaPartaiQtyDiskon', 4.5)
            ->set('hargaPartaiOpsiNominal', true)
            ->set('hargaPartaiMinNominal', 2000000)
            ->set('hargaPartaiNominalDiskon', 3.5)
            ->call('simpanPengaturanLoyaltyGlobal')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success');

        $config = app(KonfigurasiService::class);
        $this->assertEquals(175.0, $config->poinRedeemRupiah());
        $this->assertEquals(8, $config->hargaPartaiMinQty());
        $this->assertEquals(4.5, $config->hargaPartaiQtyDiskonPersen());
        $this->assertEquals(2000000.0, $config->hargaPartaiMinNominal());
        $this->assertEquals(3.5, $config->hargaPartaiNominalDiskonPersen());

        // 2. Simpan dengan hanya metode Qty (Nominal uncheck / nonaktif)
        Livewire::test(CrmDashboard::class)
            ->set('hargaPartaiAktif', true)
            ->set('hargaPartaiOpsiQty', true)
            ->set('hargaPartaiMinQty', 6)
            ->set('hargaPartaiQtyDiskon', 5.0)
            ->set('hargaPartaiOpsiNominal', false)
            ->call('simpanPengaturanLoyaltyGlobal')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success');

        $this->assertEquals(6, $config->hargaPartaiMinQty());
        $this->assertEquals(5.0, $config->hargaPartaiQtyDiskonPersen());
        $this->assertEquals(0.0, $config->hargaPartaiMinNominal());
        $this->assertEquals(0.0, $config->hargaPartaiNominalDiskonPersen());

        // 3. Menolak jika kedua metode uncheck saat status aktif
        Livewire::test(CrmDashboard::class)
            ->set('hargaPartaiAktif', true)
            ->set('hargaPartaiOpsiQty', false)
            ->set('hargaPartaiOpsiNominal', false)
            ->call('simpanPengaturanLoyaltyGlobal')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error');

        // 4. Buat dan simpan tier baru dengan rate kustom
        Livewire::test(CrmDashboard::class)
            ->call('openTierModal')
            ->set('tierForm.nama', 'Platinum Plus')
            ->set('tierForm.kode', 'PLT-PLUS')
            ->set('tierForm.min_belanja_12bulan', '5.000.000')
            ->set('tierForm.diskon_persen', 15)
            ->set('tierForm.poin_multiplier', 3)
            ->set('tierForm.poin_redeem_rupiah', '350')
            ->call('simpanTier')
            ->assertDispatched('alert');

        $tier = TierMembership::where('kode', 'PLT-PLUS')->first();
        $this->assertNotNull($tier);
        $this->assertEquals(350.0, (float) $tier->poin_redeem_rupiah);
        $this->assertEquals(5000000.0, (float) $tier->min_belanja_12bulan);
    }

    public function test_harga_partai_berjalan_dengan_satu_metode_saja_qty_atau_nominal(): void
    {
        $this->actingAs($this->kasir);
        $config = app(KonfigurasiService::class);

        // SKENARIO A: Hanya metode Qty yang aktif (Min 5 pcs, Diskon 5%), Nominal nonaktif (0)
        $config->set('harga_partai_aktif', true);
        $config->set('harga_partai_min_qty', 5);
        $config->set('harga_partai_qty_diskon_persen', 5.0);
        $config->set('harga_partai_min_nominal', 0.0);
        $config->set('harga_partai_nominal_diskon_persen', 0.0);

        // Beli 5 pcs LCD @ Rp 95.000 = Rp 475.000 (memenuhi syarat Qty >= 5) -> diskon 5% = Rp 23.750
        $testA = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id);

        $cartKeyA = array_key_first($testA->get('cart'));
        $testA->call('updateQty', $cartKeyA, 4) // total 5 pcs
            ->assertSet('diskonPartai', 23750.0);

        // SKENARIO B: Hanya metode Nominal yang aktif (Min Belanja Rp 400.000, Diskon 4%), Qty nonaktif (0)
        $config->set('harga_partai_aktif', true);
        $config->set('harga_partai_min_qty', 0);
        $config->set('harga_partai_qty_diskon_persen', 0.0);
        $config->set('harga_partai_min_nominal', 400000.0);
        $config->set('harga_partai_nominal_diskon_persen', 4.0);

        // Beli 5 pcs LCD @ Rp 95.000 = Rp 475.000 (memenuhi syarat nominal >= 400.000) -> diskon 4% = Rp 19.000
        $testB = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id);

        $cartKeyB = array_key_first($testB->get('cart'));
        $testB->call('updateQty', $cartKeyB, 4) // total 5 pcs
            ->assertSet('diskonPartai', 19000.0);

        // Beli 1 pcs LCD @ Rp 95.000 (tidak memenuhi syarat nominal < 400.000) -> diskon partai 0
        $testC = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id)
            ->assertSet('diskonPartai', 0.0);
    }

    public function test_workflow_modal_diskon_langsung_transaksi_kasir_persen_dan_nominal(): void
    {
        $this->actingAs($this->kasir);

        // Beli 1 pcs LCD retail @ 100.000 untuk Pelanggan Umum
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->assertSet('subtotal', 100000.0)
            ->assertSet('showDiskonModal', false);

        // 1. Buka modal diskon langsung & terapkan diskon persen 10% via preset
        $test->call('openDiskonModal')
            ->assertSet('showDiskonModal', true)
            ->call('setPresetDiskonPersen', 10)
            ->assertSet('tipeDiskonPilihan', 'persen')
            ->assertSet('tempDiskonPersen', 10.0)
            ->call('terapkanDiskonLangsung')
            ->assertSet('showDiskonModal', false)
            ->assertSet('diskonPersen', 10.0)
            ->assertSet('diskonNominal', 0.0)
            ->assertSet('diskonTotal', 10000.0)
            ->assertSet('totalAkhir', 90000.0);

        // 2. Ubah diskon ke nominal tunai Rp 15.000 via preset
        $test->call('openDiskonModal')
            ->assertSet('showDiskonModal', true)
            ->call('setPresetDiskonNominal', 15000)
            ->assertSet('tipeDiskonPilihan', 'nominal')
            ->assertSet('tempDiskonNominal', '15000')
            ->call('terapkanDiskonLangsung')
            ->assertSet('showDiskonModal', false)
            ->assertSet('diskonNominal', 15000.0)
            ->assertSet('diskonPersen', 0.0)
            ->assertSet('diskonTotal', 15000.0)
            ->assertSet('totalAkhir', 85000.0);

        // 3. Reset / hapus diskon langsung
        $test->call('openDiskonModal')
            ->call('resetDiskonLangsung')
            ->assertSet('showDiskonModal', false)
            ->assertSet('diskonNominal', 0.0)
            ->assertSet('diskonPersen', 0.0)
            ->assertSet('diskonTotal', 0.0)
            ->assertSet('totalAkhir', 100000.0);

        // 4. Terapkan diskon persen 5% dan selesaikan transaksi -> cek receiptData
        $test->call('openDiskonModal')
            ->call('setPresetDiskonPersen', 5)
            ->call('terapkanDiskonLangsung')
            ->assertSet('diskonTotal', 5000.0)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $receipt = $test->get('receiptData');
        $this->assertNotNull($receipt);
        $this->assertEquals(5000.0, (float) $receipt['diskon']);
        $this->assertEquals(5.0, (float) $receipt['diskon_persen']);
        $this->assertEquals(95000.0, (float) $receipt['total']);
        $this->assertEquals(5000.0, (float) $receipt['hemat_total']);
    }

    public function test_diskon_manual_kasir_memerlukan_otorisasi_owner_atau_superadmin(): void
    {
        // 1. Buat user kasir biasa tanpa role owner/super-admin
        $kasirBiasa = User::create([
            'name' => 'Kasir Toko',
            'email' => 'kasirtoko@test.com',
            'password' => Hash::make('kasir123'),
            'is_active' => true,
        ]);
        $kasirBiasa->assignRole('kasir');
        $kasirBiasa->cabangs()->attach($this->cabang->id);

        // 2. Buat akun Owner
        $owner = User::create([
            'name' => 'Pak Owner',
            'email' => 'owner@test.com',
            'password' => Hash::make('owner123'),
            'is_active' => true,
        ]);
        $owner->assignRole('owner');

        app(KasSesiState::class)->bukaKas(
            saldoAwal: 50000,
            cabangId: $this->cabang->id,
            userId: $kasirBiasa->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );

        $this->actingAs($kasirBiasa);

        // Kasir biasa coba beri diskon manual tanpa kredensial owner -> Ditolak
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('openDiskonModal')
            ->call('setPresetDiskonPersen', 10)
            ->call('terapkanDiskonLangsung')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error')
            ->assertSet('diskonPersen', 0.0)
            ->assertSet('showDiskonModal', true);

        // Kasir coba masukkan password salah -> Ditolak
        $test->set('diskonSupervisorAuth', 'owner@test.com')
            ->set('diskonSupervisorPassword', 'passwordsalah')
            ->call('terapkanDiskonLangsung')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error')
            ->assertSet('diskonPersen', 0.0);

        // Kasir masukkan kredensial Owner yang benar -> Berhasil diotorisasi & diterapkan
        $test->set('diskonSupervisorAuth', 'owner@test.com')
            ->set('diskonSupervisorPassword', 'owner123')
            ->set('diskonManualAlasan', 'Diskon pelanggan langganan')
            ->call('terapkanDiskonLangsung')
            ->assertSet('showDiskonModal', false)
            ->assertSet('diskonPersen', 10.0)
            ->assertSet('diskonManualAuthorizedById', $owner->id)
            ->assertSet('diskonManualAuthorizedByName', 'Pak Owner');

        // Selesaikan transaksi -> Cek kolom otorisasi tersimpan di DB
        $test->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals($owner->id, $trx->diskon_manual_authorized_by_id);
        $this->assertEquals('Diskon pelanggan langganan', $trx->diskon_manual_alasan);
    }

    public function test_toggle_skema_poin_member_antara_simpan_akumulasi_dan_diskon_langsung(): void
    {
        $this->actingAs($this->kasir);

        // Member punya 200 pts (nilai Rp 20.000)
        // Beli 1 pcs LCD @ Rp 95.000 (setelah tier 5%)
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id)
            ->assertSet('subtotal', 95000.0)
            ->assertSet('poinDitukar', 0) // Default: Simpan Poin (Akumulasi)
            ->assertSet('diskonPoin', 0.0);

        // 1. Klik toggle -> beralih ke Tukar Poin Jadi Diskon Langsung
        $test->call('toggleModePoinMember')
            ->assertSet('poinDitukar', 200)
            ->assertSet('diskonPoin', 20000.0)
            ->assertSet('totalAkhir', 75000.0); // 95.000 - 20.000

        // 2. Klik toggle lagi -> beralih kembali ke Simpan Poin (Akumulasi)
        $test->call('toggleModePoinMember')
            ->assertSet('poinDitukar', 0)
            ->assertSet('diskonPoin', 0.0)
            ->assertSet('totalAkhir', 95000.0);
    }

    public function test_harga_partai_otomatis_untuk_pelanggan_umum_walk_in_dan_input_set_qty(): void
    {
        $this->actingAs($this->kasir);

        // Pelanggan umum/walk-in (tanpa member)
        // Min Qty partai = 5 pcs, diskon 3%
        // Beli 5 pcs LCD @ Rp 100.000 (retail) = Subtotal 500.000
        // Diskon partai 3% dari 500.000 = Rp 15.000
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id);

        $cartKey = array_key_first($test->get('cart'));

        // Input kuantitas partai secara langsung lewat setQty
        $test->call('setQty', $cartKey, 5)
            ->assertSet('diskonPartai', 15000.0)
            ->assertSet('totalAkhir', 485000.0)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 500000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals(15000.0, (float) $trx->diskon_partai);
        $this->assertEquals(485000.0, (float) $trx->total_akhir);
    }

    public function test_harga_partai_bisa_dibatasi_khusus_member_saja_lewat_konfigurasi(): void
    {
        $this->actingAs($this->kasir);

        // Set konfigurasi harga partai hanya berlaku untuk member
        app(KonfigurasiService::class)->set('harga_partai_berlaku_untuk', 'member');

        // 1. Pelanggan umum (walk-in) beli 5 pcs -> TIDAK dapat diskon partai
        $testUmum = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id);

        $cartKeyUmum = array_key_first($testUmum->get('cart'));
        $testUmum->call('setQty', $cartKeyUmum, 5)
            ->assertSet('diskonPartai', 0.0)
            ->assertSet('totalAkhir', 500000.0);

        // 2. Member beli 5 pcs -> DAPAT diskon partai
        $testMember = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id);

        $cartKeyMember = array_key_first($testMember->get('cart'));
        $testMember->call('setQty', $cartKeyMember, 5)
            ->assertSet('diskonPartai', 14250.0);
    }

    public function test_harga_partai_bisa_dibatasi_khusus_umum_saja_lewat_konfigurasi(): void
    {
        $this->actingAs($this->kasir);

        // Set konfigurasi harga partai khusus pembeli umum saja
        app(KonfigurasiService::class)->set('harga_partai_berlaku_untuk', 'umum');

        // 1. Pelanggan umum (walk-in) beli 5 pcs -> DAPAT diskon partai (3% dari Rp 500.000 = Rp 15.000)
        $testUmum = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id);

        $cartKeyUmum = array_key_first($testUmum->get('cart'));
        $testUmum->call('setQty', $cartKeyUmum, 5)
            ->assertSet('diskonPartai', 15000.0)
            ->assertSet('totalAkhir', 485000.0);

        // 2. Member beli 5 pcs -> TIDAK dapat diskon partai (karena khusus umum)
        $testMember = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id);

        $cartKeyMember = array_key_first($testMember->get('cart'));
        $testMember->call('setQty', $cartKeyMember, 5)
            ->assertSet('diskonPartai', 0.0);
    }

    public function test_mitra_tidak_dapat_poin_loyalty_dan_fallback_skema_default(): void
    {
        $pelangganService = app(PelangganService::class);

        // Buat agen dengan kode referral
        $agen = Pelanggan::create([
            'nama' => 'Agen Sukses',
            'telepon' => '08777777777',
            'is_reseller' => true,
            'tipe_konsumen' => 'agen',
            'kode_agen' => 'AGN-SUKSES',
            'poin_loyalty' => 0,
            'status' => 'approved',
        ]);

        // Produk baru tanpa baris harga tier manual
        $produkPolos = Produk::create([
            'nama' => 'Baterai Polos',
            'slug' => 'baterai-polos-'.Str::random(4),
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        // 1. Uji Fallback Harga Default Global CRM (Agen 10% diskon dari 100.000 = 90.000)
        $pricing = $pelangganService->resolveHarga($produkPolos, $agen);
        $this->assertEquals(90000.0, (float) $pricing['harga']);
        $this->assertEquals(10000.0, (float) $pricing['diskon_nominal']);
        $this->assertEquals('Agen', $pricing['tier']);

        // 2. Uji Proteksi Poin: Mitra (Agen/Reseller) tidak mendapat poin bila mitra_dapat_poin = false
        $pelangganService->tambahBelanjaDanPoin($agen->id, 500000);
        $agen->refresh();
        $this->assertEquals(500000.0, (float) $agen->total_belanja_12bulan);
        $this->assertEquals(0, (int) $agen->poin_loyalty, 'Mitra tidak boleh mendapat poin loyalty dobel benefit!');

        // 3. Uji Downline Agen via referral_kode
        $downline = Pelanggan::create([
            'nama' => 'Downline Agen',
            'telepon' => '08888888888',
            'referral_kode' => 'AGN-SUKSES',
            'tipe_konsumen' => 'retail',
            'is_reseller' => false,
            'status' => 'approved',
        ]);

        $this->assertEquals('AGN-SUKSES', $downline->referral_kode);
        $downlines = Pelanggan::where('referral_kode', $agen->kode_agen)->get();
        $this->assertTrue($downlines->contains('id', $downline->id));
    }
}
