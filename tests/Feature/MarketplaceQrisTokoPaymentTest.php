<?php

namespace Tests\Feature;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Pos\Models\QrisCabang;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceQrisTokoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Pelanggan $pelanggan;

    private Produk $produk;

    private string $sampleStaticQris = '00020101021126610016ID.CO.QRIS.WWW01189360000201100123450210G1234567890303UMI51530014ID.LINKAJA.WWW011893600999001122334402091234567895204541153033605802ID5915UTE PARTS STORE6007JAKARTA61051234563047686';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->gudang = Gudang::firstOrCreate(
            ['cabang_id' => $this->cabang->id, 'kode' => 'GDG-TEST'],
            ['nama' => 'Gudang Utama', 'is_aktif' => true]
        );

        $this->pelanggan = Pelanggan::create([
            'kode' => 'PEL-'.Str::random(5),
            'nama' => 'Budi Marketplace',
            'telepon' => '081234567890',
            'email' => 'budi@test.com',
            'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'kode' => 'PRD-TEST-'.Str::random(4),
            'nama' => 'Kampas Rem Racing',
            'slug' => 'kampas-rem-racing-'.Str::random(4),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 70000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        StokItem::create([
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 20,
            'kondisi' => 'baik',
        ]);

        // Setup QRIS Toko Aktif untuk Cabang
        QrisCabang::create([
            'cabang_id' => $this->cabang->id,
            'merchant_name' => 'UTE PARTS PUSAT',
            'payload_static' => $this->sampleStaticQris,
            'is_active' => true,
        ]);

        app(KonfigurasiService::class)->set('qris_webhook_secret', 'secret-test-qris');
    }

    private function createPendingTransaksi(float $total = 100000): Transaksi
    {
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-MP-'.date('YmdHis').'-'.Str::random(4),
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'total_kotor' => $total,
            'diskon' => 0,
            'pajak' => 0,
            'ongkir' => 0,
            'total_akhir' => $total,
            'jumlah_bayar' => 0,
            'kembalian' => 0,
            'status' => 'menunggu_pembayaran',
            'metode_bayar' => 'duitku',
            'sumber' => 'marketplace',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => $total,
            'subtotal' => $total,
            'hpp' => 70000,
        ]);

        return $transaksi;
    }

    public function test_payment_methods_hanya_qris_toko_jika_duitku_dinonaktifkan(): void
    {
        $config = app(KonfigurasiService::class);
        $config->set('payment_mp_duitku_enabled', false);
        $config->set('payment_mp_qris_toko_enabled', true);

        $response = $this->getJson('/api/payment/methods?amount=100000');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                [
                    'kode' => 'QRIS_TOKO',
                    'badge' => 'QRIS',
                ],
            ],
        ]);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_payment_methods_hibrida_menggantikan_qris_duitku_dengan_qris_toko(): void
    {
        $config = app(KonfigurasiService::class);
        $config->set('payment_mp_duitku_enabled', true);
        $config->set('payment_mp_qris_toko_enabled', true);

        // Mock Duitku Service agar return VA dan QRIS
        $this->mock(DuitkuService::class, function ($mock) {
            $mock->shouldReceive('getPaymentMethods')->once()->andReturn([
                [
                    'kode' => 'SP',
                    'nama' => 'ShopeePay QRIS',
                    'fee' => 1500,
                    'kategori' => 'qris',
                    'badge' => 'QRIS',
                ],
                [
                    'kode' => 'BC',
                    'nama' => 'BCA Virtual Account',
                    'fee' => 4000,
                    'kategori' => 'va',
                    'badge' => 'BCA',
                ],
                [
                    'kode' => 'M2',
                    'nama' => 'Mandiri Virtual Account',
                    'fee' => 4000,
                    'kategori' => 'va',
                    'badge' => 'Mandiri',
                ],
            ]);
        });

        $response = $this->getJson('/api/payment/methods?amount=100000');

        $response->assertOk();
        $data = $response->json('data');

        // QRIS_TOKO harus di urutan pertama
        $this->assertEquals('QRIS_TOKO', $data[0]['kode']);

        // Channel SP (QRIS Duitku) harus disaring keluar
        $kodes = array_column($data, 'kode');
        $this->assertNotContains('SP', $kodes);
        $this->assertContains('BC', $kodes);
        $this->assertContains('M2', $kodes);
    }

    public function test_create_tagihan_qris_toko_berhasil(): void
    {
        $transaksi = $this->createPendingTransaksi(100000);

        $response = $this->actingAs($this->pelanggan, 'customer')
            ->postJson('/api/payment/create', [
                'no_transaksi' => $transaksi->no_transaksi,
                'payment_method' => 'QRIS_TOKO',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'no_transaksi' => $transaksi->no_transaksi,
                'is_qris_toko' => true,
                'total' => 100000,
            ],
        ]);

        $this->assertNotEmpty($response->json('data.qr_image'));
        $this->assertNotEmpty($response->json('data.qr_string'));

        $transaksi->refresh();
        $this->assertEquals('qris', $transaksi->metode_bayar);
        $this->assertEquals('QRIS-'.$transaksi->no_transaksi, $transaksi->payment_reference);
    }

    public function test_check_status_qris_toko_otomatis_melunasi_order_dan_menjurnal_bank(): void
    {
        $transaksi = $this->createPendingTransaksi(150000);
        $transaksi->update([
            'metode_bayar' => 'qris',
            'payment_reference' => 'QRIS-'.$transaksi->no_transaksi,
        ]);

        // Simulasikan notifikasi masuk di cache untuk nominal 150000
        Cache::put('qris_payment_150000', [
            'nominal' => 150000,
            'title' => 'BCA QRIS Masuk',
            'text' => 'QRIS Rp 150.000 berhasil diterima',
        ], 300);

        $response = $this->postJson('/api/payment/check-status', [
            'no_transaksi' => $transaksi->no_transaksi,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'is_lunas' => true,
                'status' => 'lunas',
            ],
        ]);

        $transaksi->refresh();
        $this->assertEquals('lunas', $transaksi->status);
        $this->assertEquals('qris', $transaksi->metode_bayar);

        // Cache harus sudah dibersihkan
        $this->assertNull(Cache::get('qris_payment_150000'));
    }

    public function test_webhook_qris_langsung_auto_settle_order_marketplace(): void
    {
        $transaksi = $this->createPendingTransaksi(85000);
        $transaksi->update([
            'metode_bayar' => 'qris',
            'payment_reference' => 'QRIS-'.$transaksi->no_transaksi,
        ]);

        $payload = [
            'secret' => 'secret-test-qris',
            'title' => 'BCA Mobile',
            'text' => 'Pembayaran QRIS sebesar Rp 85.000 telah masuk ke rekening Anda.',
        ];

        $response = $this->postJson('/webhook/qris-notif', $payload);

        $response->assertOk();
        $transaksi->refresh();
        $this->assertEquals('lunas', $transaksi->status);
        $this->assertEquals('qris', $transaksi->metode_bayar);
    }
}
