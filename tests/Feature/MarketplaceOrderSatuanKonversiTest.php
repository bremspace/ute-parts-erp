<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PembayaranPesananOnlineService;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [C1][REGRESSION][FINANSIAL] Marketplace OrderService harus mengikuti kontrak
 * satuan POS-native (PosCheckoutService / TransaksiVoidService):
 *  - TransaksiItem.jumlah = qty dalam SATUAN jual (box count, bukan pcs).
 *  - satuan = label satuan terpilih; konversi = faktor ke base unit.
 *  - hpp = HPP per SATUAN (harga_beli base x konversi).
 *  - harga_satuan = harga per SATUAN (bukan per base).
 *  - qtyDasar = jumlah x konversi; stok & HPP jurnal pakai qtyDasar.
 *
 * Semantik input buatOrder: 'jumlah' dari cart/API dalam BASE unit (pcs) —
 * CartService.qty & ShopPage.addToCart (rawQty x faktor) sudah membuktikan ini;
 * OrderService yang menormalisasi ke satuan jual saat menyimpan TransaksiItem.
 *
 * Skenario: 1 box = 12 pcs, harga box 1.620.000 (per pcs 135.000),
 * harga beli 100.000/pcs, retail 150.000/pcs.
 */
class MarketplaceOrderSatuanKonversiTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected Pelanggan $pelanggan;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CB-C1', 'nama' => 'Cabang C1', 'is_pusat' => true, 'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang C1', 'kode' => 'GD-C1',
            'is_active' => true, 'is_default' => true,
        ]);
        session(['cabang_id' => $this->cabang->id]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart Rem', 'kode' => 'REM-C1']);
        $this->produk = Produk::create([
            'kode' => 'PRD-C1', 'nama' => 'Brake Pad C1', 'sku' => 'SKU-C1',
            'kategori_id' => $kat->id, 'harga_beli' => 100000,
            'harga_jual' => 150000, 'harga_jual_retail' => 150000,
            'satuan' => 'pcs', 'stok_minimum' => 5,
        ]);
        ProdukSatuanKonversi::create([
            'produk_id' => $this->produk->id, 'satuan_kode' => 'box', 'satuan_asal' => 'pcs',
            'pengali' => 1, 'faktor_konversi' => 12, 'harga_jual' => 1620000, 'urutan' => 1,
        ]);
        StokItem::create([
            'gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id, 'jumlah' => 100,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi C1', 'email' => 'budi-c1@example.com', 'telepon' => '0812000111',
            'alamat' => 'Jl. C1 No. 1', 'tipe_konsumen' => 'retail', 'password' => bcrypt('secret123'),
        ]);

        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-02', 'nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '120-02', 'nama' => 'Kliring Gateway', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-04', 'nama' => 'Titipan Ongkir', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit'],
            ['kode' => '220-01', 'nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-03', 'nama' => 'Pendapatan Online', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $coa) {
            AkunCOA::firstOrCreate(['kode' => $coa['kode']], $coa);
        }

        Permission::firstOrCreate(['name' => 'pos.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'approve-workflow', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
        $this->staff->givePermissionTo(['pos.view', 'pos.create', 'wms.view', 'wms.create', 'approve-workflow']);
        $this->staff->cabangs()->attach($this->cabang->id, ['is_default' => true]);
    }

    private function stok(): int
    {
        return (int) StokItem::where('produk_id', $this->produk->id)->first()->jumlah;
    }

    private function jurnalHpp(Transaksi $transaksi): float
    {
        return (float) JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)
            ->get()
            ->filter(fn ($l) => $l->akun?->kode === '510-02')
            ->sum('debit');
    }

    public function test_order_box_murni_menyimpan_satuan_konversi_hpp_per_satuan(): void
    {
        // Beli 24 pcs (= 2 box) dengan satuan box — semantik cart: jumlah base unit.
        $transaksi = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 24, 'satuan' => 'box']],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $this->assertCount(1, $transaksi->items);
        $item = $transaksi->items->first();
        $this->assertEquals(2, (int) $item->jumlah, 'jumlah harus qty satuan jual (2 box), bukan 24 pcs');
        $this->assertEquals('box', $item->satuan, 'satuan harus label terpilih (box)');
        $this->assertEquals(12.0, (float) $item->konversi, 'konversi harus faktor box (12), bukan default 1');
        $this->assertEquals(1620000.0, (float) $item->harga_satuan, 'harga_satuan harus per box (1.620.000)');
        $this->assertEquals(1200000.0, (float) $item->hpp, 'hpp harus per box (100.000 x 12)');
        $this->assertEquals(3240000.0, (float) $item->subtotal, 'subtotal 2 box x 1.620.000');
        $this->assertEquals(3240000.0, (float) $transaksi->subtotal);
        // Stok belum dipotong saat order dibuat (menunggu pembayaran).
        $this->assertEquals(100, $this->stok());
    }

    public function test_order_box_murni_lunas_duitku_potong_stok_dan_hpp_qty_dasar(): void
    {
        $transaksi = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 24, 'satuan' => 'box']],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $hasil = app(DuitkuPaymentProcessor::class)->prosesLunas(
            $transaksi->no_transaksi, (int) round((float) $transaksi->total_akhir), 'REF-C1-BOX', 'duitku'
        );
        $this->assertEquals('sukses', $hasil['kode']);

        // Stok terpotong qtyDasar = 2 box x 12 = 24 pcs (100 -> 76), bukan 2.
        $this->assertEquals(76, $this->stok());
        // HPP = harga_beli x qtyDasar = 100.000 x 24 = 2.400.000 (tidak double-count konversi).
        $this->assertEqualsWithDelta(2400000.0, $this->jurnalHpp($transaksi), 0.01);
        $this->assertEqualsWithDelta(
            2400000.0,
            (float) JurnalAkuntansi::with('akun')->where('referensi_tipe', Transaksi::class)
                ->where('referensi_id', $transaksi->id)->get()
                ->filter(fn ($l) => $l->akun?->kode === '130-01')->sum('kredit'),
            0.01
        );
    }

    public function test_order_campuran_box_dan_pcs_menyimpan_baris_terpisah_dengan_konversi_benar(): void
    {
        // Cart updateQty: 26 pcs dengan satuan box -> 2 box + sisa 2 pcs.
        $transaksi = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 26, 'satuan' => 'box', 'sisa_pcs' => 2]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        // Campuran dipecah 2 baris: box (konversi 12) + pcs (konversi 1), qty integer per satuan.
        $this->assertCount(2, $transaksi->items);
        $box = $transaksi->items->firstWhere('satuan', 'box');
        $pcs = $transaksi->items->firstWhere('satuan', 'pcs');
        $this->assertNotNull($box, 'Baris box harus ada');
        $this->assertNotNull($pcs, 'Baris pcs (sisa) harus ada');

        $this->assertEquals(2, (int) $box->jumlah);
        $this->assertEquals(12.0, (float) $box->konversi);
        $this->assertEquals(1620000.0, (float) $box->harga_satuan);
        $this->assertEquals(1200000.0, (float) $box->hpp, 'hpp box = 100.000 x 12');
        $this->assertEquals(3240000.0, (float) $box->subtotal);

        $this->assertEquals(2, (int) $pcs->jumlah);
        $this->assertEquals(1.0, (float) $pcs->konversi);
        $this->assertEquals(150000.0, (float) $pcs->harga_satuan, 'Sisa pcs dihargai retail');
        $this->assertEquals(100000.0, (float) $pcs->hpp, 'hpp pcs = harga_beli base');
        $this->assertEquals(300000.0, (float) $pcs->subtotal);

        $this->assertEquals(3540000.0, (float) $transaksi->subtotal, 'Total campuran 2 box + 2 pcs');
    }

    public function test_order_campuran_lunas_duitku_stok_dan_hpp_total_benar(): void
    {
        $transaksi = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 26, 'satuan' => 'box', 'sisa_pcs' => 2]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $hasil = app(DuitkuPaymentProcessor::class)->prosesLunas(
            $transaksi->no_transaksi, (int) round((float) $transaksi->total_akhir), 'REF-C1-MIX', 'duitku'
        );
        $this->assertEquals('sukses', $hasil['kode']);

        // qtyDasar total = 2x12 + 2x1 = 26 pcs (100 -> 74).
        $this->assertEquals(74, $this->stok());
        // HPP = 100.000 x 26 = 2.600.000 (bukan 2 box x 1.2jt + 2 pcs x 1.2jt = double-count).
        $this->assertEqualsWithDelta(2600000.0, $this->jurnalHpp($transaksi), 0.01);
    }

    public function test_cek_stok_menolak_order_box_yang_qty_dasar_melebihi_stok(): void
    {
        // Stok 100 pcs; 9 box = 108 pcs > 100 -> harus ditolak (cek base vs base).
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak mencukupi');

        app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 108, 'satuan' => 'box']],
            $this->cabang->id,
            'ambil_ke_toko'
        );
    }

    public function test_cek_stok_menolak_box_saat_stok_kurang_dari_qty_dasar_meski_lebih_dari_qty_box(): void
    {
        // Stok 23 pcs: cukup untuk "2" kalau dibandingkan satuan, tapi 2 box = 24 pcs > 23.
        StokItem::where('produk_id', $this->produk->id)->update(['jumlah' => 23]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak mencukupi');

        app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 24, 'satuan' => 'box']],
            $this->cabang->id,
            'ambil_ke_toko'
        );
    }

    public function test_pickup_box_dibayar_kasir_stok_dan_hpp_memakai_qty_dasar(): void
    {
        $this->actingAs($this->staff);
        $transaksi = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 24, 'satuan' => 'box']],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $hasil = app(PembayaranPesananOnlineService::class)->bayarDiToko(
            $transaksi, 'tunai', (float) $transaksi->total_akhir
        );
        $this->assertEquals('selesai', $hasil['transaksi']->status);

        // 100 - 24 = 76 pcs; HPP 2.400.000.
        $this->assertEquals(76, $this->stok());
        $this->assertEqualsWithDelta(2400000.0, $this->jurnalHpp($transaksi), 0.01);
    }

    public function test_retur_satu_box_dari_order_marketplace_refund_hpp_dan_stok_benar(): void
    {
        // Kontrak lintas modul: item marketplace harus bisa diretur per satuan
        // (ReturnPenjualanService sudah diperbaiki utk konversi — C2).
        $transaksi = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 24, 'satuan' => 'box']],
            $this->cabang->id,
            'ambil_ke_toko'
        );
        app(DuitkuPaymentProcessor::class)->prosesLunas(
            $transaksi->no_transaksi, (int) round((float) $transaksi->total_akhir), 'REF-C1-RET', 'duitku'
        );
        $this->assertEquals(76, $this->stok());

        $item = $transaksi->items->first();
        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $item->id, 'jumlah' => 1]],
            'Salah beli',
            'kas',
            $this->staff->id
        );

        // Refund 1 box berbasis harga per box (bukan per pcs 135.000), NETO
        // alokasi diskon header [H1]: order ini kena diskon partai 3%
        // (qty 24 >= min 5) -> DPP 3.142.800 dari subtotal 3.240.000.
        // Refund = 1.620.000 x (3.142.800 / 3.240.000) = 1.571.400.
        $this->assertEquals(1571400.0, (float) $retur->jumlah, 'Refund retur 1 box = harga box neto alokasi diskon partai header');
        // Stok kembali 12 pcs (76 -> 88), bukan 1 pcs.
        $this->assertEquals(88, $this->stok(), 'Retur 1 box harus mengembalikan 12 pcs');
    }
}
