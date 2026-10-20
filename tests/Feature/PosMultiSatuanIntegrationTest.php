<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosMultiSatuanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected ProdukSatuanKonversi $konversiBox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CP',
            'alamat' => 'Jl. Pusat',
            'telepon' => '08123456789',
            'is_aktif' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->cabangs()->attach($this->cabang->id);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GD-01',
            'is_aktif' => true,
        ]);

        $kategori = KategoriProduk::create(['nama' => 'Sparepart']);

        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Baut Titanium M5',
            'sku' => 'BAUT-M5',
            'barcode' => '888000111',
            'satuan' => 'pcs',
            'harga_beli' => 5000,
            'harga_jual_retail' => 10000,
            'is_active' => true,
        ]);

        $this->konversiBox = ProdukSatuanKonversi::create([
            'produk_id' => $this->produk->id,
            'satuan_kode' => 'box',
            'satuan_asal' => 'pcs',
            'pengali' => 10,
            'faktor_konversi' => 10,
            'harga_beli' => 45000,
            'harga_jual' => 90000,
            'barcode' => '888000999',
            'is_satuan_jual' => true,
            'is_satuan_beli' => true,
            'urutan' => 1,
        ]);

        // Stok 25 pcs di gudang
        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 25,
        ]);

        // Siapkan Akun COA minimal untuk jurnal
        AkunCOA::firstOrCreate(['kode' => '110-02'], ['nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-04'], ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan POS', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP Produk', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan Produk', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    public function test_scan_barcode_kemasan_otomatis_masuk_keranjang_dengan_satuan_dan_harga_box(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('scanBarcodeDirect', '888000999'); // Barcode kemasan Box

        $cart = $component->get('cart');
        $this->assertNotEmpty($cart);

        $boxItem = collect($cart)->firstWhere('satuan', 'box');
        $this->assertNotNull($boxItem);
        $this->assertSame(90000.0, (float) $boxItem['harga']);
        $this->assertSame(10.0, (float) $boxItem['konversi']);
        $this->assertSame(1, $boxItem['qty']);
    }

    public function test_ubah_satuan_di_keranjang_menyesuaikan_harga_konversi_dan_stok(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $itemKey = $this->produk->id.'-0';

        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $this->produk->id);

        // Awalnya pcs
        $cart = $component->get('cart');
        $this->assertArrayHasKey($itemKey, $cart);
        $this->assertSame('pcs', $cart[$itemKey]['satuan']);
        $this->assertSame(10000.0, (float) $cart[$itemKey]['harga']);

        // Ganti ke Box
        $component->call('ubahSatuanItem', $itemKey, 'box');

        $cartAfter = $component->get('cart');
        $newItemKey = $this->produk->id.'-0-box';
        $this->assertArrayHasKey($newItemKey, $cartAfter);
        $this->assertSame('box', $cartAfter[$newItemKey]['satuan']);
        $this->assertSame(90000.0, (float) $cartAfter[$newItemKey]['harga']);
        $this->assertSame(10.0, (float) $cartAfter[$newItemKey]['konversi']);
    }

    public function test_transaksi_multi_satuan_memotong_stok_dasar_dan_catat_jurnal_akuntansi(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('scanBarcodeDirect', '888000999') // 1 Box = 10 pcs
            ->call('openPaymentModal')
            ->set('metodeBayar', 'transfer')
            ->set('jumlahBayar', '90.000')
            ->call('processTransaction');

        $this->assertSame(1, Transaksi::count());
        $transaksi = Transaksi::first();

        // Cek TransaksiItem tersimpan dengan satuan dan konversi
        $item = TransaksiItem::where('transaksi_id', $transaksi->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('box', $item->satuan);
        $this->assertSame(10.0, (float) $item->konversi);
        $this->assertSame(1, $item->jumlah);
        $this->assertSame(90000.0, (float) $item->harga_satuan);

        // Cek sisa stok di gudang (25 - 10 = 15 pcs)
        $stokTersisa = StokItem::where('gudang_id', $this->gudang->id)
            ->where('produk_id', $this->produk->id)
            ->value('jumlah');
        $this->assertSame(15.0, (float) $stokTersisa);

        // Cek Jurnal Akuntansi (HPP = 10 pcs * 5.000 = 50.000)
        $jurnalHpp = JurnalAkuntansi::where('sumber', 'pos')
            ->whereHas('akun', fn ($q) => $q->where('kode', '510-02'))
            ->first();
        $this->assertNotNull($jurnalHpp);
        $this->assertSame(50000.0, (float) $jurnalHpp->debit);
    }

    public function test_ui_dropdown_multi_satuan_muncul_pada_keranjang_kasir(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $this->produk->id);

        $cart = $component->get('cart');
        $this->assertNotEmpty($cart);

        $firstItem = reset($cart);
        $this->assertArrayHasKey('daftar_satuan', $firstItem);
        $this->assertGreaterThan(1, count($firstItem['daftar_satuan']));

        // Verifikasi view Livewire merender dropdown selector multi-satuan
        $component->assertSeeHtml('ubahSatuanItem')
            ->assertSeeHtml('Ganti Satuan / Kemasan')
            ->assertSee('BOX')
            ->assertSee('PCS');
    }

    public function test_transaksi_ditahan_dapat_dibuka_diresume_dan_dihapus(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $this->produk->id)
            ->call('tahanTransaksi');

        $this->assertTrue($component->get('showDitahanPanel'));
        $ditahan = Transaksi::where('cabang_id', $this->cabang->id)->where('status', 'ditahan')->first();
        $this->assertNotNull($ditahan);

        // Uji hapus ditahan
        $component->call('hapusDitahan', $ditahan->id);
        $ditahan->refresh();
        $this->assertSame('dibatalkan', $ditahan->status);

        // Buat lagi untuk uji resumeTransaksi
        $component->call('addToCart', $this->produk->id)
            ->call('tahanTransaksi');
        $ditahanBaru = Transaksi::where('cabang_id', $this->cabang->id)->where('status', 'ditahan')->latest()->first();

        $component->call('resumeTransaksi', $ditahanBaru->id);
        $this->assertNotEmpty($component->get('cart'));
        $this->assertFalse($component->get('showDitahanPanel'));
    }

    public function test_transaksi_selesai_dapat_divoid_dengan_otorisasi_supervisor_dan_reversal_lengkap(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        // Buat user supervisor
        $supervisor = User::factory()->create([
            'email' => 'spv@uteparts.id',
            'password' => bcrypt('rahasia123'),
        ]);
        $supervisor->cabangs()->attach($this->cabang->id);
        Role::firstOrCreate(['name' => 'super-admin']);
        $supervisor->assignRole('super-admin');

        // Jalankan transaksi POS: Beli 1 Box (10 pcs)
        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('scanBarcodeDirect', '888000999') // 1 Box = 10 pcs
            ->call('openPaymentModal')
            ->set('metodeBayar', 'transfer')
            ->set('jumlahBayar', '90.000')
            ->call('processTransaction');

        $transaksi = Transaksi::where('cabang_id', $this->cabang->id)->latest()->first();
        $this->assertNotNull($transaksi);
        $this->assertSame('selesai', $transaksi->status);

        // Stok awal 25, terjual 10 -> sisa 15
        $stokSetelahJual = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produk->id)->value('jumlah');
        $this->assertSame(15.0, (float) $stokSetelahJual);

        // Buka modal void dan jalankan void
        $component->call('bukaVoidModal', $transaksi->id)
            ->set('voidAlasan', 'Konsumen salah beli tipe barang')
            ->set('voidSupervisorAuth', 'spv@uteparts.id')
            ->set('voidSupervisorPassword', 'rahasia123')
            ->call('prosesVoidTransaksi');

        $transaksi->refresh();
        $this->assertSame('void', $transaksi->status);
        $this->assertSame($supervisor->id, $transaksi->void_by_id);
        $this->assertSame('Konsumen salah beli tipe barang', $transaksi->void_alasan);

        // 1. Verifikasi Reversal Stok (15 + 10 = 25 pcs kembali utuh)
        $stokSetelahVoid = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produk->id)->value('jumlah');
        $this->assertSame(25.0, (float) $stokSetelahVoid);

        // 2. Verifikasi Reversal Jurnal (Ada jurnal pembalik void: Bank 110-02 dikreditkan 90.000)
        $jurnalPembalik = JurnalAkuntansi::where('sumber', 'pos')
            ->where('deskripsi', 'like', '%Pembalik Jurnal Void POS%')
            ->get();
        $this->assertNotEmpty($jurnalPembalik);
        $this->assertSame(90000.0, (float) $jurnalPembalik->where('akun_coa_id', AkunCOA::where('kode', '110-02')->first()?->id)->sum('kredit'));
    }
}
