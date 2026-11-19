<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ReturnPembelianService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * C3 — Retur pembelian: kontrak satuan/HPP POS-native.
 *
 * jumlah item retur dalam SATUAN PO (mis. box); stok dipotong qtyDasar =
 * jumlah x konversi (base unit); lookup stok kanonik (kompatibel GRN yang
 * memaksa baris varian); kredit persediaan at biaya GRN aktual; retur GAGAL
 * bila baris stok tidak ada / kurang (tanpa silent skip); average dihitung
 * ulang setelah retur.
 */
class ReturnPembelianBoxKonversiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $user;

    private Produk $produk;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
            ['210-01', 'Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            ['110-01', 'Kas', 'aset', 'kas', 'debit'],
            ['520-08', 'Selisih Stok', 'beban', 'beban', 'debit'],
            ['430-01', 'Pendapatan Lain', 'pendapatan', 'pendapatan', 'kredit'],
        ] as [$kode, $nama, $tipe, $kelompok, $normal]) {
            AkunCOA::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $normal]
            );
        }

        $this->cabang = Cabang::create([
            'kode' => 'UTP-C3',
            'nama' => 'Cabang C3',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang C3',
            'kode' => 'GDG-C3',
            'is_active' => true,
        ]);
        $this->user = User::create([
            'name' => 'Admin C3',
            'email' => 'admin-c3@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Oli Box C3',
            'slug' => Str::slug('Oli Box C3').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);
        $this->supplier = Supplier::create(['nama' => 'Supplier C3', 'termin_hari' => 30]);
    }

    private function buatPoBox(int $qtyBox, float $hargaPerBox, float $konversi = 12, ?int $variantId = null): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-C3-'.Str::random(6),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'kredit',
            'total' => $qtyBox * $hargaPerBox,
            'total_dibayar' => 0,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $variantId,
            'satuan' => $konversi > 1 ? 'box' : 'pcs',
            'konversi' => $konversi,
            'harga_beli' => $hargaPerBox,
            'jumlah' => $qtyBox,
            'subtotal' => $qtyBox * $hargaPerBox,
        ]);

        return $po->load('items');
    }

    private function terimaGrn(PurchaseOrder $po, int $qty, ?float $hargaAktual = null): void
    {
        $harga = $hargaAktual !== null ? [$this->produk->id => $hargaAktual] : [];
        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => $qty], $this->user->id, [], $harga);
        $this->assertEquals('terima', $grn->status);
    }

    private function stokProduk(): StokItem
    {
        return StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->firstOrFail();
    }

    private function kreditPersediaanRetur(ReturnPembelian $retur): float
    {
        return (float) JurnalAkuntansi::where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '130-01'))
            ->sum('kredit');
    }

    public function test_retur_box_potong_stok_qty_dasar_dan_simpan_satuan_konversi(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 120000); // 2 box isi 12 @120.000/box
        $this->terimaGrn($po, 2);
        $this->assertEquals(24, (int) $this->stokProduk()->jumlah);

        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Cacat pabrik 1 box',
            'utang',
            $this->user->id
        );

        $this->assertEquals('selesai', $retur->fresh()->status);
        // Stok terpotong qtyDasar = 1 box x 12 = 12 pcs (bukan 1 pcs)
        $this->assertEquals(12, (int) $this->stokProduk()->jumlah);

        $item = ReturnPembelianItem::where('return_pembelian_id', $retur->id)->firstOrFail();
        $this->assertEquals('box', $item->satuan);
        $this->assertEquals(12, (float) $item->konversi);
        $this->assertEquals(1, (float) $item->jumlah, 'jumlah tetap dalam satuan PO (box)');
        $this->assertEquals(120000, (float) $item->subtotal);

        $this->assertTrue(
            StokLog::where('referensi_tipe', ReturnPembelian::class)
                ->where('referensi_id', $retur->id)
                ->where('perubahan', -12)
                ->where('jumlah_sebelum', 24)
                ->where('jumlah_setelah', 12)
                ->exists(),
            'StokLog wajib mencatat pengurangan 12 base unit'
        );
        $this->assertTrue(
            StockMutationLog::where('referensi_tipe', ReturnPembelian::class)
                ->where('referensi_id', $retur->id)
                ->where('delta', -12)
                ->where('sumber', 'retur_pembelian')
                ->exists()
        );
        $this->assertEquals(120000, $this->kreditPersediaanRetur($retur));
    }

    public function test_cek_stok_memakai_qty_dasar_bukan_qty_satuan(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 120000);
        $this->terimaGrn($po, 2); // stok 24 pcs
        // Simulasikan penjualan sehingga fisik tinggal 10 pcs (< 1 box = 12)
        $this->stokProduk()->update(['jumlah' => 10]);

        try {
            app(ReturnPembelianService::class)->buatRetur(
                $po->fresh(),
                [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
                'Cacat',
                'utang',
                $this->user->id
            );
            $this->fail('Retur 1 box (12 pcs) wajib ditolak bila stok fisik hanya 10 pcs');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->errors()['items'][0] ?? '');
        }

        $this->assertEquals(10, (int) $this->stokProduk()->jumlah);
        $this->assertSame(0, ReturnPembelian::count(), 'Retur gagal tidak boleh meninggalkan draft');
    }

    public function test_retur_kredit_persediaan_at_harga_grn_aktual_bukan_harga_po(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 100000); // harga PO 100.000/box
        $this->terimaGrn($po, 2, 120000); // harga GRN aktual 120.000/box

        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Cacat',
            'utang',
            $this->user->id
        );

        $item = ReturnPembelianItem::where('return_pembelian_id', $retur->id)->firstOrFail();
        $this->assertEquals(120000, (float) $item->harga_grn, 'harga GRN aktual wajib disimpan di item retur');
        $this->assertEquals(120000, (float) $item->subtotal);
        $this->assertEquals(120000, (float) $retur->fresh()->jumlah);
        // Kredit 130-01 = biaya GRN aktual (debit awal GRN), bukan harga PO
        $this->assertEquals(120000, $this->kreditPersediaanRetur($retur));
    }

    public function test_retur_po_varian_null_tetap_potong_baris_stok_varian_hasil_grn(): void
    {
        Queue::fake();
        $variant = SkuVariant::create([
            'produk_id' => $this->produk->id,
            'sku' => 'OLI-C3-V1',
            'nama_varian' => 'Varian 1',
            'harga_beli' => 10000,
            'is_active' => true,
        ]);
        // PO tanpa varian; GRN memaksa baris stok ber-varian (varian pertama)
        $po = $this->buatPoBox(2, 120000, 12, null);
        $this->terimaGrn($po, 2);

        $stok = $this->stokProduk();
        $this->assertEquals($variant->id, (int) $stok->sku_variant_id, 'GRN membuat baris stok ber-varian');
        $this->assertEquals(24, (int) $stok->jumlah);

        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Cacat',
            'utang',
            $this->user->id
        );

        $this->assertEquals('selesai', $retur->fresh()->status);
        $this->assertEquals(12, (int) $stok->fresh()->jumlah, 'Retur wajib menemukan & memotong baris varian GRN');
    }

    public function test_eksekusi_retur_gagal_bila_baris_stok_tidak_ada(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 120000);
        $this->terimaGrn($po, 2);

        // Draft via pemanggilan 7 argumen (tidak langsung eksekusi)
        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Cacat',
            'utang',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );
        $this->assertEquals('draft', $retur->status);

        // Baris stok hilang sebelum eksekusi -> wajib GAGAL, bukan silent skip
        StokItem::where('produk_id', $this->produk->id)->delete();

        try {
            app(ReturnPembelianService::class)->eksekusiRetur($retur->fresh(), $this->user->id);
            $this->fail('Eksekusi retur tanpa baris stok wajib gagal (jurnal tidak boleh tetap kredit persediaan)');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('stok', strtolower($e->errors()['items'][0] ?? ''));
        }

        $this->assertEquals('draft', $retur->fresh()->status);
        $this->assertSame(
            0,
            JurnalAkuntansi::where('referensi_tipe', ReturnPembelian::class)->where('referensi_id', $retur->id)->count(),
            'Tidak boleh ada jurnal retur bila potong stok gagal'
        );
    }

    public function test_eksekusi_retur_gagal_bila_stok_kurang_dari_qty_dasar(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 120000);
        $this->terimaGrn($po, 2);

        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Cacat',
            'utang',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );
        // Fisik berkurang setelah draft dibuat (terjual) -> eksekusi wajib tolak, bukan negatif
        $this->stokProduk()->update(['jumlah' => 5]);

        try {
            app(ReturnPembelianService::class)->eksekusiRetur($retur->fresh(), $this->user->id);
            $this->fail('Eksekusi retur wajib gagal bila stok kurang dari qtyDasar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->errors()['items'][0] ?? '');
        }

        $this->assertEquals(5, (int) $this->stokProduk()->jumlah, 'Stok tidak boleh berubah/ negatif');
        $this->assertEquals('draft', $retur->fresh()->status);
    }

    public function test_terima_unit_pengganti_restore_stok_qty_dasar(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 120000);
        $this->terimaGrn($po, 2);

        $service = app(ReturnPembelianService::class);
        $retur = $service->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Tukar unit',
            'utang',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );
        $service->kirimKeSupplier($retur->fresh(), [], $this->user->id);
        $this->assertEquals(12, (int) $this->stokProduk()->jumlah);

        $service->terimaUnitPengganti($retur->fresh(), [], $this->user->id);
        $this->assertEquals('selesai', $retur->fresh()->status);
        // Restore simetris dengan potongan: +12 base unit, bukan +1
        $this->assertEquals(24, (int) $this->stokProduk()->jumlah);
    }

    public function test_tolak_retur_kembali_ke_toko_restore_stok_qty_dasar(): void
    {
        Queue::fake();
        $po = $this->buatPoBox(2, 120000);
        $this->terimaGrn($po, 2);

        $service = app(ReturnPembelianService::class);
        $retur = $service->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Klaim ditolak',
            'utang',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );
        $service->kirimKeSupplier($retur->fresh(), [], $this->user->id);
        $this->assertEquals(12, (int) $this->stokProduk()->jumlah);

        $service->tolakReturSupplier($retur->fresh(), 'Vendor menolak klaim', 'kembali_ke_toko', $this->user->id);
        $this->assertEquals('ditolak', $retur->fresh()->status);
        $this->assertEquals(24, (int) $this->stokProduk()->jumlah, 'Barang kembali ke toko wajib restore qtyDasar');
    }

    public function test_average_dihitung_ulang_setelah_retur(): void
    {
        Queue::fake();
        $this->produk->update(['metode_harga_beli' => 'average', 'harga_beli' => 8000]);

        // GRN 1: 2 box @120.000 (10.000/pcs) -> average 10.000, stok 24
        $po1 = $this->buatPoBox(2, 120000);
        $this->terimaGrn($po1, 2);
        $this->assertEquals(10000, (float) $this->produk->fresh()->harga_beli);

        // GRN 2: 2 box @144.000 (12.000/pcs) -> average (24x10.000 + 24x12.000)/48 = 11.000
        $po2 = $this->buatPoBox(2, 144000);
        $this->terimaGrn($po2, 2);
        $this->assertEquals(11000, (float) $this->produk->fresh()->harga_beli);
        $this->assertEquals(48, (int) $this->stokProduk()->jumlah);

        // Retur seluruh GRN 2 (2 box) at biaya GRN aktual 12.000/pcs:
        // sisa 24 pcs, nilai (48x11.000 - 24x12.000) = 240.000 -> average 10.000
        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po2->fresh(),
            [['purchase_order_item_id' => $po2->items->first()->id, 'jumlah' => 2]],
            'Cacat batch',
            'utang',
            $this->user->id
        );

        $this->assertEquals('selesai', $retur->fresh()->status);
        $this->assertEquals(24, (int) $this->stokProduk()->jumlah);
        $this->assertEquals(288000, $this->kreditPersediaanRetur($retur));
        $this->assertEquals(
            10000,
            (float) $this->produk->fresh()->harga_beli,
            'Average wajib dihitung ulang setelah retur (biaya keluar != average saat ini)'
        );
    }

    public function test_retur_pool_karantina_box_simpan_satuan_konversi_dan_nilai_qty_dasar(): void
    {
        Queue::fake();
        $rp = ReturnPenjualan::create(['no_return' => 'RTP-C3-'.Str::random(5)]);
        $poolItem = ReturnPenjualanItem::create([
            'return_penjualan_id' => $rp->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'satuan' => 'box',
            'konversi' => 12,
            'harga_satuan' => 240000,
            'subtotal' => 240000,
            'hpp' => 10000, // per base unit (kontrak C2)
            'status_karantina' => 'karantina',
        ]);

        $retur = app(ReturnPembelianService::class)->buatReturDariPoolSupplier(
            $this->supplier,
            [$poolItem->id],
            'Rusak pool',
            'saldo',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id,
            false // draft saja, tanpa eksekusi stok/jurnal
        );

        $item = ReturnPembelianItem::where('return_pembelian_id', $retur->id)->firstOrFail();
        $this->assertEquals('box', $item->satuan);
        $this->assertEquals(12, (float) $item->konversi);
        // Nilai klaim = hpp per base x qtyDasar (1 box x 12 x 10.000), bukan x 1
        $this->assertEquals(120000, (float) $item->subtotal);
        $this->assertEquals(120000, (float) $retur->fresh()->jumlah);
    }
}
