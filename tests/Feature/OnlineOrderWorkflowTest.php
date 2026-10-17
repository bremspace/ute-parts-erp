<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Pelanggan $pelanggan;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'alamat' => 'Jl. Pusat No. 1',
            'telepon' => '08123456789',
            'is_pusat' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-01',
            'is_aktif' => true,
        ]);

        // Buat Akun COA yang dibutuhkan jurnal
        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::create(['kode' => '510-02', 'nama' => 'HPP Suku Cadang', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);

        $perm1 = Permission::firstOrCreate(['name' => 'pos.view']);
        $role = Role::create(['name' => 'super-admin']);
        $role->givePermissionTo($perm1);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
        $this->staff->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart Rem', 'kode' => 'REM']);

        $this->produk = Produk::create([
            'kode' => 'PRD-01',
            'nama' => 'Brake Pad Brembo',
            'sku' => 'SKU-BRM-01',
            'kategori_id' => $kat->id,
            'harga_beli' => 100000,
            'harga_jual' => 150000,
            'stok_minimum' => 5,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 20,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Otomotif',
            'email' => 'budi@example.com',
            'telepon' => '081299887766',
            'alamat' => 'Jl. Otomotif No. 10 Jakarta',
            'tipe_konsumen' => 'retail',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_order_creation_triggers_backoffice_and_customer_notifications(): void
    {
        $orderService = app(OrderService::class);

        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [
                ['produk_id' => $this->produk->id, 'jumlah' => 2],
            ],
            $this->cabang->id,
            'pengiriman',
            'Tolong bubble wrap tebal',
            [
                'kurir' => 'J&T Express',
                'nama_penerima' => 'Budi Santoso',
                'alamat_tujuan' => 'Jl. Raden Saleh No 5',
                'telepon_tujuan' => '081299887766',
            ]
        );

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'sumber' => 'marketplace',
            'status' => 'menunggu_pembayaran',
        ]);

        $this->assertDatabaseHas('pengiriman', [
            'transaksi_id' => $transaksi->id,
            'kurir' => 'J&T Express',
            'nama_penerima' => 'Budi Santoso',
        ]);

        // Cek Notifikasi Backoffice (kasir)
        $this->assertDatabaseHas('notifikasi_keluar', [
            'target_role' => 'kasir',
            'kategori' => 'transaksi',
            'action_url' => '/app/pos/online-orders',
        ]);

        // Cek Notifikasi Pelanggan In-App
        $this->assertDatabaseHas('notifikasi_keluar', [
            'pelanggan_id' => $this->pelanggan->id,
            'tipe' => 'inapp',
            'judul' => 'Pesanan Berhasil Didaftarkan',
        ]);
    }

    public function test_backoffice_can_process_online_order_lifecycle(): void
    {
        $this->actingAs($this->staff);

        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [
                ['produk_id' => $this->produk->id, 'jumlah' => 2],
            ],
            $this->cabang->id,
            'pengiriman'
        );

        // 1. Verifikasi Lunas via Livewire Backoffice
        Livewire::test(PesananOnlineIndex::class)
            ->call('konfirmasiLunas', $transaksi->id)
            ->assertDispatched('alert');

        $transaksi->refresh();
        $this->assertEquals('lunas', $transaksi->status);

        // Stok terpotong dari 20 jadi 18
        $stok = StokItem::where('produk_id', $this->produk->id)->first();
        $this->assertEquals(18, $stok->jumlah);

        // 2. Mulai Kemas
        Livewire::test(PesananOnlineIndex::class)
            ->call('mulaiKemas', $transaksi->id)
            ->assertDispatched('alert');

        $transaksi->refresh();
        $this->assertEquals('diproses', $transaksi->status);

        // 3. Simpan Pengiriman (Input Resi)
        Livewire::test(PesananOnlineIndex::class)
            ->set('kirimOrderId', $transaksi->id)
            ->set('kurirPilihan', 'JNE Express')
            ->set('nomorResi', 'JNE123456789')
            ->call('simpanPengiriman')
            ->assertDispatched('alert');

        $transaksi->refresh();
        $this->assertEquals('dikirim', $transaksi->status);
        $this->assertEquals('JNE123456789', $transaksi->pengiriman->tracking_id);

        // 4. Selesaikan Pesanan
        Livewire::test(PesananOnlineIndex::class)
            ->call('selesaikanPesanan', $transaksi->id)
            ->assertDispatched('alert');

        $transaksi->refresh();
        $this->assertEquals('selesai', $transaksi->status);
    }

    public function test_customer_can_view_orders_and_notifications_in_account(): void
    {
        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [
                ['produk_id' => $this->produk->id, 'jumlah' => 1],
            ],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $this->actingAs($this->pelanggan, 'customer');

        // [T-05] `bayarPesanan()` simulasi sudah DIHAPUS — pembayaran kini alur
        // nyata Duitku (POST /api/payment/create + redirect payment_url); status
        // lunas/stok/jurnal hanya diproses webhook PAY-02. Halaman akun cukup
        // menampilkan order dengan status menunggu_pembayaran + tombol "Bayar Sekarang".
        Livewire::test(CustomerAccount::class)
            ->assertSee($transaksi->no_transaksi)
            ->assertSee('Bayar Sekarang')
            ->assertDontSee('Simulasi');

        $transaksi->refresh();
        $this->assertEquals('menunggu_pembayaran', $transaksi->status);

        // Konsumen melihat notifikasi (dibuat OrderService saat order dibuat) dan dapat menandainya terbaca
        $notif = NotifikasiKeluar::forPelanggan($this->pelanggan->id)->first();
        $this->assertNotNull($notif);

        Livewire::test(CustomerAccount::class)
            ->set('activeTab', 'notifikasi')
            ->call('markNotifikasiRead', $notif->id);

        $notif->refresh();
        $this->assertNotNull($notif->read_at);
    }

    /**
     * Alur PICKUP: konsumen pesan online "ambil_ke_toko" tapi bayar di konter kasir.
     * Kasir proses lewat modal Pickup — bayar tunai, potong stok, jurnal 110-04/410-03.
     */
    public function test_pickup_order_paid_at_counter_by_cashier(): void
    {
        $this->actingAs($this->staff);

        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 3]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        // Kasir membuka modal pickup & memproses pembayaran tunai + serah terima
        Livewire::test(PesananOnlineIndex::class)
            ->call('bukaModalPickup', $transaksi->id)
            ->assertSet('showPickupModal', true)
            ->set('metodeBayarPickup', 'tunai')
            ->set('nominalBayarPickup', (float) $transaksi->total_akhir + 50000)
            ->call('prosesPickupKasir')
            ->assertDispatched('alert');

        $transaksi->refresh();
        $this->assertEquals('selesai', $transaksi->status);
        $this->assertEquals('tunai', $transaksi->metode_bayar);

        // Stok terpotong 20 - 3 = 17
        $stok = StokItem::where('produk_id', $this->produk->id)->first();
        $this->assertEquals(17, $stok->jumlah);

        // Jurnal penjualan pickup terposting (akun kas laci 110-04 / pendapatan web 410-03)
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_id' => $transaksi->id,
            'referensi_tipe' => 'App\Modules\Pos\Models\Transaksi',
        ]);
    }

    /**
     * Alur PICKUP online-paid: konsumen sudah bayar online, datang ke toko,
     * kasir tinggal serahkan barang (tanpa transaksi pembayaran baru).
     */
    public function test_pickup_already_paid_online_handover_only(): void
    {
        $this->actingAs($this->staff);

        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 2]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        // Verifikasi lunas dulu (sudah bayar via gateway/transfer)
        Livewire::test(PesananOnlineIndex::class)
            ->call('konfirmasiLunas', $transaksi->id)
            ->assertDispatched('alert');

        $stok = StokItem::where('produk_id', $this->produk->id)->first();
        $this->assertEquals(18, $stok->jumlah);

        // Serah terima fisik di toko
        Livewire::test(PesananOnlineIndex::class)
            ->call('bukaModalPickup', $transaksi->id)
            ->call('prosesPickupKasir')
            ->assertDispatched('alert');

        $transaksi->refresh();
        $this->assertEquals('selesai', $transaksi->status);

        // Stok TIDAK dipotong dua kali
        $stok->refresh();
        $this->assertEquals(18, $stok->jumlah);
    }
}
