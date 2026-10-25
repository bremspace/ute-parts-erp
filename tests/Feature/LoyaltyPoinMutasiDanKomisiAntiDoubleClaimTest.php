<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\PembayaranSubledgerService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\PelangganPoinLog;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\TransaksiVoidService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LoyaltyPoinMutasiDanKomisiAntiDoubleClaimTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $kasirUser;

    protected User $supervisorUser;

    protected TierMembership $tierGold;

    protected Produk $produkA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Ute Test',
            'kode' => 'CAB-TST',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Test',
            'kode' => 'GDG-TST',
            'is_active' => true,
        ]);

        $this->kasirUser = User::create([
            'name' => 'Kasir Test',
            'email' => 'kasir.test@uteparts.id',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->kasirUser->assignRole('super-admin');
        $this->kasirUser->cabangs()->attach($this->cabang->id);

        $this->supervisorUser = User::create([
            'name' => 'Supervisor Test',
            'email' => 'supervisor.test@uteparts.id',
            'password' => bcrypt('supervisor123'),
            'is_active' => true,
        ]);
        $this->supervisorUser->assignRole('super-admin');
        $this->supervisorUser->cabangs()->attach($this->cabang->id);

        // Akun COA yang dibutuhkan
        foreach ([
            ['kode' => '110-01', 'nama' => 'Kas Operasional', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-02', 'nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci Kasir', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lancar', 'saldo_normal' => 'kredit'],
            ['kode' => '210-03', 'nama' => 'Utang Komisi Reseller', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lancar', 'saldo_normal' => 'kredit'],
            ['kode' => '220-01', 'nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-01', 'nama' => 'Beban Komisi Reseller', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit'],
            ['kode' => '510-02', 'nama' => 'HPP Penjualan', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ] as $coaData) {
            AkunCOA::firstOrCreate(['kode' => $coaData['kode']], array_merge($coaData, ['is_active' => true]));
        }

        $config = app(KonfigurasiService::class);
        $config->set('poin_earn_persen', 10.0); // 10% belanja jadi poin
        $config->set('poin_redeem_rupiah', 100.0); // 1 poin = Rp 100

        $this->tierGold = TierMembership::create([
            'nama' => 'Gold Member',
            'kode' => 'GOLD',
            'min_belanja_12bulan' => 1000000,
            'diskon_persen' => 5,
            'poin_multiplier' => 1.0,
            'poin_redeem_rupiah' => 100.0,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $this->produkA = Produk::create([
            'nama' => 'Baterai Hippo Pro',
            'slug' => 'baterai-hippo-pro-'.Str::random(4),
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $this->produkA->id,
            'sku' => 'BAT-01',
            'barcode' => 'BAR-BAT-01',
            'nama_varian' => 'Standar',
            'satuan_kode' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produkA->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 100,
        ]);
    }

    public function test_penukaran_poin_di_kasir_pos_memotong_otomatis_dan_mencatat_log_mutasi(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Member Loyal',
            'telepon' => '081234567890',
            'tier_membership_id' => $this->tierGold->id,
            'poin_loyalty' => 200, // punya 200 poin = Rp 20.000 diskon
            'total_belanja_12bulan' => 500000,
            'tipe_konsumen' => 'retail',
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->kasirUser)
            ->test(PosKasir::class)
            ->set('selectedCustomerId', $pelanggan->id)
            ->call('addToCart', $this->produkA->id)
            ->set('poinDitukar', 100); // tukar 100 poin = Rp 10.000

        $this->assertEquals(100, $component->get('poinDitukar'));
        $this->assertEquals(10000.0, (float) $component->get('diskonPoin'));

        // Simpan transaksi via transfer
        $component->set('metodeBayar', 'transfer')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $pelanggan->refresh();

        // Poin terpotong 100. Dari sisa pembayaran Rp 85.000 (100k - diskon tier 5k - poin 10k),
        // didapat poin baru 10% = 8.500 / 100 = 85 poin.
        // Saldo akhir: 200 - 100 + 85 = 185 poin.
        $this->assertEquals(185, (int) $pelanggan->poin_loyalty);

        // Verifikasi PelangganPoinLog mencatat mutasi penukaran (redeemed) dan perolehan (earned)
        $logTukar = PelangganPoinLog::where('pelanggan_id', $pelanggan->id)
            ->where('tipe', PelangganPoinLog::TIPE_REDEEMED)
            ->first();
        $this->assertNotNull($logTukar);
        $this->assertEquals(-100, (int) $logTukar->poin);
        $this->assertEquals(200, (int) $logTukar->saldo_sebelum);
        $this->assertEquals(100, (int) $logTukar->saldo_sesudah);

        $logEarn = PelangganPoinLog::where('pelanggan_id', $pelanggan->id)
            ->where('tipe', PelangganPoinLog::TIPE_EARNED)
            ->first();
        $this->assertNotNull($logEarn);
        $this->assertEquals(85, (int) $logEarn->poin);
        $this->assertEquals(100, (int) $logEarn->saldo_sebelum);
        $this->assertEquals(185, (int) $logEarn->saldo_sesudah);

        // Struk kasir menampilkan saldo poin akhir yang akurat
        $receipt = $component->get('receiptData');
        $this->assertEquals(185, (int) $receipt['saldo_poin']);
        $this->assertEquals(100, (int) $receipt['poin_ditukar']);
    }

    public function test_void_transaksi_mengembalikan_poin_yang_ditukar_dan_membatalkan_poin_earned(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Siti Member',
            'telepon' => '0899998888',
            'tier_membership_id' => $this->tierGold->id,
            'poin_loyalty' => 150,
            'total_belanja_12bulan' => 500000,
            'tipe_konsumen' => 'retail',
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->kasirUser)
            ->test(PosKasir::class)
            ->set('selectedCustomerId', $pelanggan->id)
            ->call('addToCart', $this->produkA->id)
            ->set('poinDitukar', 50) // tukar 50 poin
            ->set('metodeBayar', 'transfer')
            ->set('jumlahBayar', 100000)
            ->call('processTransaction');

        $transaksiId = $component->get('completedTransactionId');
        $transaksi = Transaksi::findOrFail($transaksiId);

        $pelanggan->refresh();
        $saldoSebelumVoid = (int) $pelanggan->poin_loyalty;

        // Void transaksi
        app(TransaksiVoidService::class)->voidTransaksi(
            $transaksi,
            $this->supervisorUser,
            'Batal beli karena salah barang'
        );

        $pelanggan->refresh();

        // 50 poin yang ditukar harus kembali (refund)
        // Dan poin yang didapat dari belanja dibatalkan
        $logRefund = PelangganPoinLog::where('pelanggan_id', $pelanggan->id)
            ->where('tipe', PelangganPoinLog::TIPE_REFUND)
            ->where('transaksi_id', $transaksi->id)
            ->first();

        $this->assertNotNull($logRefund);
        $this->assertEquals(50, (int) $logRefund->poin);
        $this->assertEquals(150, (int) $pelanggan->poin_loyalty); // saldo pulih kembali ke awal
    }

    public function test_komisi_reseller_tidak_dapat_dibuat_ganda_idempotent(): void
    {
        $reseller = Pelanggan::create([
            'nama' => 'Toko Mitra Berkah',
            'telepon' => '0877777777',
            'is_reseller' => true,
            'tipe_konsumen' => 'reseller',
        ]);

        SkemaKomisi::create([
            'nama' => 'Komisi Reseller Umum',
            'tipe' => 'persen',
            'nilai' => 5.0,
            'is_active' => true,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TST-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasirUser->id,
            'pelanggan_id' => $reseller->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'pos',
            'subtotal' => 200000,
            'total_akhir' => 200000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $transaksi->items()->create([
            'produk_id' => $this->produkA->id,
            'jumlah' => 2,
            'satuan' => 'pcs',
            'harga_satuan' => 100000,
            'subtotal' => 200000,
            'hpp' => 50000,
        ]);

        $komisiService = app(KomisiService::class);

        $k1 = $komisiService->hitungKomisi($transaksi, $reseller);
        $this->assertNotNull($k1);
        $this->assertEquals('pending', $k1->status);

        // Panggilan kedua tidak boleh menduplikasi komisi
        $k2 = $komisiService->hitungKomisi($transaksi, $reseller);
        $this->assertEquals($k1->id, $k2->id);

        $count = Komisi::where('transaksi_id', $transaksi->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_komisi_yang_sudah_cair_tidak_dapat_diklaim_atau_dicairkan_ganda(): void
    {
        $reseller = Pelanggan::create([
            'nama' => 'Agen Sukses Makmur',
            'telepon' => '0855555555',
            'is_reseller' => true,
            'tipe_konsumen' => 'reseller',
        ]);

        $komisi = Komisi::create([
            'no_komisi' => 'KMS-20261025-0001',
            'pelanggan_id' => $reseller->id,
            'nominal_komisi' => 50000,
            'jumlah_transaksi' => 1000000,
            'status' => Komisi::STATUS_PENDING,
        ]);

        $komisiService = app(KomisiService::class);

        // 1. Approve komisi -> status disetujui & terbuat Utang
        $komisiService->prosesApproval([$komisi->id], 'approve', $this->supervisorUser->id);
        $komisi->refresh();
        $this->assertEquals(Komisi::STATUS_DISETUJUI, $komisi->status);

        $utang = Utang::where('referensi_tipe', 'komisi')->where('referensi_id', $komisi->id)->first();
        $this->assertNotNull($utang);
        $this->assertEquals(50000.0, (float) $utang->jumlah);

        // 2. Cairkan via pembayaran Utang di Akunting
        app(PembayaranSubledgerService::class)->bayarUtang(
            $utang->id,
            50000.0,
            $this->cabang->id,
            $this->supervisorUser->id
        );

        $komisi->refresh();
        $this->assertEquals(Komisi::STATUS_CAIR, $komisi->status);
        $this->assertNotNull($komisi->paid_at);

        // 3. Tes proteksi: Upaya pencairan ulang (double payout/claim) WAJIB DITOLAK
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sudah dicairkan');

        $komisiService->cairkanKomisi($komisi->id, $this->supervisorUser->id, 'Cairkan lagi');
    }

    public function test_transaksi_dengan_komisi_yang_sudah_cair_menolak_divoid(): void
    {
        $reseller = Pelanggan::create([
            'nama' => 'Reseller Sentosa',
            'telepon' => '0822222222',
            'is_reseller' => true,
            'tipe_konsumen' => 'reseller',
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TST-002',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasirUser->id,
            'pelanggan_id' => $reseller->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'pos',
            'subtotal' => 500000,
            'total_akhir' => 500000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $komisi = Komisi::create([
            'no_komisi' => 'KMS-20261025-0002',
            'pelanggan_id' => $reseller->id,
            'transaksi_id' => $transaksi->id,
            'nominal_komisi' => 25000,
            'jumlah_transaksi' => 500000,
            'status' => Komisi::STATUS_CAIR, // komisi sudah cair!
            'paid_at' => now(),
        ]);

        // Void transaksi harus ditolak karena komisi sudah dicairkan
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sudah dicairkan');

        app(TransaksiVoidService::class)->voidTransaksi(
            $transaksi,
            $this->supervisorUser,
            'Coba batalkan transaksi yang komisinya sudah cair'
        );
    }
}
