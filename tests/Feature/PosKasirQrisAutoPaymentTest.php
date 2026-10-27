<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class PosKasirQrisAutoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->gudang = Gudang::where('cabang_id', $this->cabang->id)->first() ?? Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Toko',
            'kode' => 'GDG-UTAMA',
            'is_aktif' => true,
        ]);

        $this->user = User::create([
            'name' => 'Kasir Staging',
            'email' => 'kasir-qris@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        app(KasSesiState::class)->bukaKas(
            saldoAwal: 500000,
            cabangId: $this->cabang->id,
            userId: $this->user->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );

        $this->produk = Produk::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Kabel Data Type-C Fast Charging',
            'sku' => 'KBL-TC-01',
            'tipe' => 'stok',
            'harga_beli' => 25000,
            'harga_jual_retail' => 50000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::forget('qris_payment_50000');
        Cache::forget('qris_latest_payment');
        parent::tearDown();
    }

    public function test_transaksi_kasir_qris_otomatis_selesai_saat_pembayaran_terdeteksi(): void
    {
        // Masukkan pembayaran ke cache (seolah webhook baru saja diterima dari Android)
        Cache::put('qris_payment_50000', [
            'nominal' => 50000,
            'title' => 'BCA Mobile',
            'text' => 'Transaksi QRIS sebesar Rp 50.000,00 dari BUDI berhasil',
            'waktu' => now()->toIso8601String(),
        ], 300);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->set('metodeBayar', 'qris')
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->call('cekStatusQrisOtomatis')
            ->assertSet('qrisTerbayarOtomatis', true)
            ->assertSet('showPaymentModal', false)
            ->assertSet('showReceiptModal', true)
            ->assertSet('cart', []);

        // Verifikasi transaksi tercatat di database
        $trx = Transaksi::where('cabang_id', $this->cabang->id)
            ->where('metode_bayar', 'qris')
            ->where('total_akhir', 50000)
            ->first();

        $this->assertNotNull($trx);
        $this->assertSame(50000.0, (float) $trx->jumlah_bayar);
        $this->assertNull(Cache::get('qris_payment_50000'));
    }

    public function test_webhook_nominal_satu_rupiah_terbaca_dan_masuk_cache(): void
    {
        $response = $this->call(
            'POST',
            '/api/webhook/qris-notif',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            "title=Pembayaran QRIS statis diterima&text=Rp 1 di sinicantik, CCNTYN.\n&secret=uteparts"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nominal' => 1,
            ],
        ]);

        $cached = Cache::get('qris_payment_1');
        $this->assertNotNull($cached);
        $this->assertSame(1, $cached['nominal']);
        Cache::forget('qris_payment_1');
    }

    public function test_tombol_cek_status_qris_manual_memberikan_respon_saat_belum_bayar(): void
    {
        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->set('metodeBayar', 'qris')
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->call('cekStatusQrisManual')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'info'
                && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'belum terdeteksi masuk'));
    }

    public function test_tombol_cek_status_qris_manual_memberikan_respon_saat_nominal_mismatch(): void
    {
        // Masukkan pembayaran dengan nominal lain ke cache
        Cache::put('qris_latest_payment', [
            'nominal' => 25000,
            'title' => 'BCA Mobile',
            'text' => 'QRIS 25.000',
        ], 300);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->set('metodeBayar', 'qris')
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->call('cekStatusQrisManual')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'warning'
                && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'Belum lunas. Terdeteksi notifikasi'));
    }

    public function test_tombol_cek_status_qris_manual_menyelesaikan_transaksi_saat_lunas(): void
    {
        Cache::put('qris_payment_50000', [
            'nominal' => 50000,
            'title' => 'BCA Mobile',
            'text' => 'QRIS 50.000',
        ], 300);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->set('metodeBayar', 'qris')
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->call('cekStatusQrisManual')
            ->assertSet('qrisTerbayarOtomatis', true)
            ->assertSet('showPaymentModal', false)
            ->assertSet('showReceiptModal', true);
    }
}
