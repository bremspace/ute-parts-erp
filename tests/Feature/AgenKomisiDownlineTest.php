<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AgenKomisiDownlineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private SkuVariant $variant;

    private Pelanggan $agen;

    private Pelanggan $downline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TierMembershipSeeder::class);

        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '110-02' => ['Bank', 'aset', 'kas', 'debit'],
            '110-04' => ['Kas Laci', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '210-03' => ['Utang Komisi', 'kewajiban', 'utang_komisi', 'kredit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '410-03' => ['Pendapatan Penjualan Online', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-01' => ['Beban Komisi Reseller', 'beban', 'beban_operasional', 'debit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Pusat', 'kode' => 'GP01', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Admin Owner',
            'email' => 'admin@ute.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'LCD Samsung A50',
            'slug' => 'lcd-samsung-a50-'.Str::random(4),
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $this->variant = SkuVariant::create([
            'produk_id' => $this->produk->id,
            'sku' => 'SKU-A50-ORI',
            'barcode' => 'BAR-A50-ORI',
            'nama_varian' => 'Original Crown',
            'satuan_kode' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 100,
        ]);

        // Skema Komisi 5% untuk penjualan
        KomisiSkema::create([
            'nama' => 'Komisi Penjualan Umum 5%',
            'aktor_tipe' => 'reseller',
            'trigger_tipe' => 'penjualan',
            'tipe' => 'persen',
            'nilai' => 5.0,
            'min_amount' => 0,
            'is_aktif' => true,
        ]);

        $pelangganService = app(PelangganService::class);

        // 1. Agen terdaftar dengan kode agen otomatis
        $this->agen = $pelangganService->create([
            'nama' => 'Agen Master Surabaya',
            'telepon' => '081234567890',
            'email' => 'agen@ute.id',
            'tipe_konsumen' => 'agen',
        ]);
        $this->agen->update(['status' => 'approved']);

        // 2. Downline terdaftar via referral kode agen
        $this->downline = $pelangganService->create([
            'nama' => 'Bengkel Downline Budi',
            'telepon' => '081987654321',
            'email' => 'downline@ute.id',
            'referral_kode' => $this->agen->kode_agen,
            'tipe_konsumen' => 'retail',
        ]);
        $this->downline->update(['status' => 'approved']);
        session(['cabang_id' => $this->cabang->id]);

        app(KasSesiState::class)->bukaKas(
            saldoAwal: 50000,
            cabangId: $this->cabang->id,
            userId: $this->admin->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );
    }

    public function test_transaksi_pos_downline_menghasilkan_komisi_untuk_agen(): void
    {
        $this->actingAs($this->admin);

        // Downline beli 2 pcs LCD Samsung @ 200.000 = Rp 400.000
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id, $this->variant->id)
            ->call('setPelanggan', $this->downline->id);

        $cart = $test->get('cart');
        $this->assertNotEmpty($cart);
        $cartKey = array_key_first($cart);

        $test->call('setQty', $cartKey, 2)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 400000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertGreaterThan(0, (float) $trx->total_akhir);

        // Verifikasi Komisi untuk Agen
        $komisi = Komisi::where('transaksi_id', $trx->id)->where('aktor_tipe', 'agen')->first();
        $this->assertNotNull($komisi, 'Komisi agen dari transaksi downline harus tercatat!');
        $this->assertEquals($this->agen->id, $komisi->pelanggan_id);
        $this->assertEquals($this->agen->id, $komisi->aktor_id);
        // 5% dari 388.000 subtotal bersih = 19.400
        $this->assertEquals(19400.0, (float) $komisi->nominal_komisi);
        $this->assertEquals('pending', $komisi->status);
        $this->assertStringContainsString('Bengkel Downline Budi', $komisi->keterangan);
    }

    public function test_agen_melihat_downline_dan_komisi_di_customer_portal(): void
    {
        // Catat komisi dummy untuk agen
        Komisi::create([
            'no_komisi' => 'KMS-AGN-9999',
            'pelanggan_id' => $this->agen->id,
            'aktor_tipe' => 'agen',
            'aktor_id' => $this->agen->id,
            'jumlah_transaksi' => 1000000,
            'nominal_komisi' => 50000,
            'status' => 'pending',
            'keterangan' => 'Komisi penjualan — Bengkel Downline Budi',
        ]);

        $this->actingAs($this->agen, 'customer');

        // Render Livewire CustomerAccount tab komisi
        $test = Livewire::test(CustomerAccount::class)
            ->set('activeTab', 'komisi')
            ->assertSee($this->agen->kode_agen)
            ->assertSee('Bengkel Downline Budi')
            ->assertSee('KMS-AGN-9999')
            ->assertSee('Rp 50.000');

        $downlines = $test->get('downlines');
        $this->assertEquals(1, $downlines->count());
        $this->assertEquals('Bengkel Downline Budi', $downlines->first()->nama);
    }

    public function test_approval_komisi_agen_menghasilkan_jurnal_akuntansi_presisi(): void
    {
        $komisi = Komisi::create([
            'no_komisi' => 'KMS-AGN-8888',
            'pelanggan_id' => $this->agen->id,
            'aktor_tipe' => 'agen',
            'aktor_id' => $this->agen->id,
            'jumlah_transaksi' => 2000000,
            'nominal_komisi' => 100000,
            'status' => 'pending',
            'keterangan' => 'Komisi referral transaksi downline',
        ]);

        // Finance menyetujui komisi di ResellerDashboard
        Livewire::actingAs($this->admin)
            ->test(ResellerDashboard::class)
            ->set('activeTab', 'komisi')
            ->call('toggleKomisi', $komisi->id)
            ->call('prosesApproval', 'approve')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $komisi->refresh();
        $this->assertEquals('disetujui', $komisi->status);

        // Verifikasi Jurnal Akuntansi Beban Komisi (510-01) vs Utang Komisi (210-03)
        $jurnal = JurnalHeader::where('sumber', 'komisi')->latest('id')->first();
        $this->assertNotNull($jurnal);
        $this->assertTrue($jurnal->balanced());
        $this->assertEquals(100000.0, (float) $jurnal->total_debit);
        $this->assertEquals(100000.0, (float) $jurnal->total_kredit);

        $lineDebit = $jurnal->lines()->with('akun')->where('debit', '>', 0)->first();
        $this->assertEquals('510-01', $lineDebit->akun->kode);

        $lineKredit = $jurnal->lines()->with('akun')->where('kredit', '>', 0)->first();
        $this->assertEquals('210-03', $lineKredit->akun->kode);

        // Verifikasi Entri Utang Usaha (AP) untuk Agen
        $utang = Utang::where('referensi_tipe', 'komisi')->where('referensi_id', $komisi->id)->first();
        $this->assertNotNull($utang);
        $this->assertEquals(100000.0, (float) $utang->jumlah);
        $this->assertEquals('belum_lunas', $utang->status);
    }

    public function test_safety_floor_membatasi_komisi_saat_margin_tipis_agar_toko_tidak_rugi(): void
    {
        $this->actingAs($this->admin);

        // Produk margin tipis: HPP 95.000, Retail 100.000
        $produkTepis = Produk::create([
            'nama' => 'Baterai Margin Tipis',
            'slug' => 'baterai-margin-tipis-'.Str::random(4),
            'harga_beli' => 95000,
            'harga_jual_retail' => 100000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $variantTipis = SkuVariant::create([
            'produk_id' => $produkTepis->id,
            'sku' => 'SKU-TIPIS-01',
            'barcode' => 'BAR-TIPIS-01',
            'nama_varian' => 'Standard',
            'satuan_kode' => 'pcs',
            'harga_beli' => 95000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produkTepis->id,
            'sku_variant_id' => $variantTipis->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 50,
        ]);

        // Downline beli 1 pcs: Retail 100.000 - Diskon Tier 3% = 97.000
        // HPP = 95.000, Sisa Laba Kotor = 97.000 - 95.000 = Rp 2.000
        // Komisi 5% normal = Rp 4.850.
        // Dengan Safety Floor: Komisi dibatasi maks Rp 2.000!
        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $produkTepis->id, $variantTipis->id)
            ->call('setPelanggan', $this->downline->id);

        $cart = $test->get('cart');
        $cartKey = array_key_first($cart);

        $test->call('setQty', $cartKey, 1)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 97000)
            ->call('processTransaction');

        $trx = Transaksi::latest('id')->first();
        $this->assertNotNull($trx);
        $this->assertEquals(97000.0, (float) $trx->total_akhir);

        $komisi = Komisi::where('transaksi_id', $trx->id)->where('aktor_tipe', 'agen')->first();
        $this->assertNotNull($komisi);

        // Terbukti dibatasi ke margin maksimal Rp 2.000 (bukan Rp 4.850)
        $this->assertEquals(2000.0, (float) $komisi->nominal_komisi);
        $this->assertStringContainsString('Safety Floor', $komisi->keterangan);
        $this->assertStringContainsString('4.850', $komisi->keterangan);
        $this->assertStringContainsString('2.000', $komisi->keterangan);
    }

    public function test_safety_floor_mencegah_komisi_jika_transaksi_rugi_di_bawah_hpp(): void
    {
        $this->actingAs($this->admin);

        // Transaksi simulasi di bawah modal (obral rugi: bayar 90.000 vs HPP 95.000)
        $produkTepis = Produk::create([
            'nama' => 'Barang Obral Rugi',
            'slug' => 'barang-obral-rugi-'.Str::random(4),
            'harga_beli' => 95000,
            'harga_jual_retail' => 90000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $trxRugi = Transaksi::create([
            'no_transaksi' => 'TRX-RUGI-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->admin->id,
            'pelanggan_id' => $this->downline->id,
            'sumber' => 'pos',
            'subtotal' => 90000,
            'total_akhir' => 90000,
            'status' => 'selesai',
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 90000,
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trxRugi->id,
            'produk_id' => $produkTepis->id,
            'nama_produk' => $produkTepis->nama,
            'harga_satuan' => 90000,
            'jumlah' => 1,
            'subtotal' => 90000,
            'hpp' => 95000,
        ]);

        $komisiService = app(KomisiService::class);
        $hasil = $komisiService->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $trxRugi->id]);

        // Karena margin minus (90.000 - 95.000 = -5.000), Safety Floor membatasi ke 0 dan tidak ada komisi dibayar
        $this->assertEmpty($hasil, 'Komisi tidak boleh dibuat jika transaksi menghasilkan margin rugi / di bawah HPP');
    }
}
