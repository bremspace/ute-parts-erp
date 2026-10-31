<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ReturnPembelianTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ReturnPembelianService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReturPembelianMitigasiFluktuasiHargaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Supplier $supplier;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Retur Mitigasi',
            'kode' => 'CBG-MIT',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Mitigasi',
            'kode' => 'G-MIT',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'nama' => 'Supplier Fluktuasi Part',
            'kode' => 'SUP-FLK',
            'kebijakan_retur' => 'faktur_asli',
            'saldo_deposit' => 0,
            'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'kategori_id' => 1,
            'nama' => 'Baterai Hippo 5000mAh',
            'barcode' => 'BAT-HIP-5000',
            'harga_beli' => 200000,
            'harga_jual' => 280000,
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 10,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
    }

    public function test_penjurnalan_selisih_klaim_rugi_dibukukan_ke_beban_520_08(): void
    {
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        // Buat draft retur pembelian (Nilai buku: 1 unit @ 200.000)
        $retur = ReturnPembelian::create([
            'no_return' => 'RET-BUY-RUGI-01',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => 'draft',
            'metode_pengembalian' => 'saldo',
            'alasan' => 'Baterai drop cacat pabrik',
        ]);

        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 200000,
            'subtotal' => 200000,
        ]);

        // Supplier hanya menyetujui klaim 180.000 (rugi selisih fluktuasi 20.000)
        $service = app(ReturnPembelianService::class);
        $returSelesai = $service->kirimDanSelesaikanRetur(
            $retur,
            'saldo',
            'Dikirim via J&T',
            $this->user->id,
            180000, // nilaiRealisasi
            'harga_pasar'
        );

        $this->assertEquals('selesai', $returSelesai->status);
        $this->assertEquals(180000, (float) $returSelesai->nilai_realisasi);
        $this->assertEquals(-20000, (float) $returSelesai->selisih_klaim);
        $this->assertEquals('harga_pasar', $returSelesai->kebijakan_retur);

        // Saldo deposit supplier harus bertambah sebesar realisasi (180.000)
        $this->supplier->refresh();
        $this->assertEquals(180000, (float) $this->supplier->saldo_deposit);

        // Cek jurnal akuntansi:
        // Debit: 210-01 (180.000)
        // Debit: 520-08 Beban Selisih Stok/Klaim (20.000)
        // Kredit: 130-01 Persediaan (200.000)
        $jurnals = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->get();

        $this->assertCount(3, $jurnals);

        $debitUtang = $jurnals->first(fn ($j) => $j->akun?->kode === '210-01');
        $this->assertNotNull($debitUtang);
        $this->assertEquals(180000, (float) $debitUtang->debit);
        $this->assertEquals(0, (float) $debitUtang->kredit);

        $debitBebanSelisih = $jurnals->first(fn ($j) => $j->akun?->kode === '520-08');
        $this->assertNotNull($debitBebanSelisih, 'Harus mencatat debit ke akun 520-08');
        $this->assertEquals(20000, (float) $debitBebanSelisih->debit);
        $this->assertEquals(0, (float) $debitBebanSelisih->kredit);

        $kreditPersediaan = $jurnals->first(fn ($j) => $j->akun?->kode === '130-01');
        $this->assertNotNull($kreditPersediaan);
        $this->assertEquals(0, (float) $kreditPersediaan->debit);
        $this->assertEquals(200000, (float) $kreditPersediaan->kredit);
    }

    public function test_penjurnalan_selisih_klaim_untung_dibukukan_ke_pendapatan_lain_430_01(): void
    {
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $retur = ReturnPembelian::create([
            'no_return' => 'RET-BUY-UNTUNG-01',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => 'draft',
            'metode_pengembalian' => 'kas',
            'alasan' => 'Klaim garansi dengan kompensasi tambahan',
        ]);

        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 200000,
            'subtotal' => 200000,
        ]);

        // Supplier menyetujui klaim 215.000 (untung 15.000)
        $service = app(ReturnPembelianService::class);
        $returSelesai = $service->kirimDanSelesaikanRetur(
            $retur,
            'kas',
            'Transfer bank langsung dari vendor',
            $this->user->id,
            215000,
            'faktur_asli'
        );

        $this->assertEquals(215000, (float) $returSelesai->nilai_realisasi);
        $this->assertEquals(15000, (float) $returSelesai->selisih_klaim);

        // Cek jurnal:
        // Debit: 110-01 Kas (215.000)
        // Kredit: 130-01 Persediaan (200.000)
        // Kredit: 430-01 Pendapatan Lain-lain (15.000)
        $jurnals = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->get();

        $this->assertCount(3, $jurnals);

        $debitKas = $jurnals->first(fn ($j) => $j->akun?->kode === '110-01');
        $this->assertNotNull($debitKas);
        $this->assertEquals(215000, (float) $debitKas->debit);

        $kreditPersediaan = $jurnals->first(fn ($j) => $j->akun?->kode === '130-01');
        $this->assertNotNull($kreditPersediaan);
        $this->assertEquals(200000, (float) $kreditPersediaan->kredit);

        $kreditPendapatan = $jurnals->first(fn ($j) => $j->akun?->kode === '430-01');
        $this->assertNotNull($kreditPendapatan, 'Harus mencatat kredit ke akun 430-01');
        $this->assertEquals(15000, (float) $kreditPendapatan->kredit);
    }

    public function test_konfirmasi_retur_tukar_unit_tidak_membuat_jurnal_finansial(): void
    {
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $retur = ReturnPembelian::create([
            'no_return' => 'RET-BUY-TUKAR-01',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => 'draft',
            'metode_pengembalian' => 'tukar_unit',
            'alasan' => 'Ganti 1-to-1 replacement',
        ]);

        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 200000,
            'subtotal' => 200000,
        ]);

        $service = app(ReturnPembelianService::class);
        $returSelesai = $service->kirimDanSelesaikanRetur(
            $retur,
            'tukar_unit',
            'Barang ditukar unit sejenis',
            $this->user->id,
            200000,
            'tukar_unit'
        );

        $this->assertEquals('tukar_unit', $returSelesai->metode_pengembalian);
        $this->assertEquals('selesai', $returSelesai->status);

        // Tidak ada jurnal kas/utang untuk tukar unit fisik murni
        $jurnals = JurnalAkuntansi::where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->count();

        $this->assertEquals(0, $jurnals);
    }

    public function test_ui_livewire_konfirmasi_realisasi_dan_kebijakan_retur(): void
    {
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $retur = ReturnPembelian::create([
            'no_return' => 'RET-BUY-UI-01',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => 'draft',
            'metode_pengembalian' => 'saldo',
        ]);

        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 200000,
            'subtotal' => 200000,
        ]);

        Livewire::test(ReturnPembelianTab::class)
            ->call('bukaDetail', $retur->id)
            ->assertSet('kirimNilaiRealisasi', 200000)
            ->assertSet('kirimKebijakanRetur', 'faktur_asli')
            ->set('kirimNilaiRealisasi', 175000)
            ->set('kirimKebijakanRetur', 'harga_pasar')
            ->set('kirimCatatan', 'Potongan harga pasar dari distributor')
            ->call('konfirmasiKirimKeSupplier', $retur->id)
            ->assertDispatched('alert');

        $retur->refresh();
        $this->assertEquals('selesai', $retur->status);
        $this->assertEquals(175000, (float) $retur->nilai_realisasi);
        $this->assertEquals(-25000, (float) $retur->selisih_klaim);
        $this->assertEquals('harga_pasar', $retur->kebijakan_retur);
    }
}
