<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ReturnPembelianService;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturBarangTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CAB-TEST',
            'nama' => 'Cabang Test Retur',
            'is_pusat' => true,
            'status' => 'aktif',
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GDG-TEST',
            'nama' => 'Gudang Utama Test',
            'cabang_id' => $this->cabang->id,
            'is_default' => true,
            'status' => 'aktif',
        ]);

        $this->produk = Produk::create([
            'kode' => 'PRD-TEST-01',
            'nama' => 'LCD Test Retur',
            'harga_beli' => 100000,
            'harga_jual' => 150000,
            'status' => 'aktif',
            'has_sn' => true,
        ]);

        // COA Setup
        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];

        foreach ($coas as $coa) {
            AkunCoa::create($coa);
        }

        Permission::firstOrCreate(['name' => 'pos.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'approve-workflow', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
        $this->user->givePermissionTo(['pos.view', 'pos.create', 'wms.view', 'wms.create', 'approve-workflow']);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_retur_penjualan_mengembalikan_stok_membuat_jurnal_dan_reset_nomor_seri(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Pembeli',
            'no_telepon' => '08123456789',
            'tipe' => 'retail',
        ]);

        $stok = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 10,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-2026-0001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 2,
            'subtotal' => 300000,
            'total_akhir' => 300000,
            'jumlah_bayar' => 300000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
            'harga_satuan' => 150000,
            'harga_final' => 150000,
            'subtotal' => 300000,
            'hpp' => 100000,
        ]);

        // SN status awal terjual
        $sn1 = NomorSeri::create([
            'nomor_seri' => 'SN-RETUR-01',
            'produk_id' => $this->produk->id,
            'cabang_id' => $this->cabang->id,
            'status' => NomorSeri::STATUS_TERJUAL,
            'transaksi_item_id' => $trxItem->id,
        ]);

        $service = app(ReturnPenjualanService::class);

        $retur = $service->buatRetur(
            $transaksi,
            [
                [
                    'transaksi_item_id' => $trxItem->id,
                    'jumlah' => 1,
                    'sn' => ['SN-RETUR-01'],
                ],
            ],
            'Layar bergaris',
            'kas',
            $this->user->id
        );

        $this->assertEquals('selesai', $retur->status);
        $this->assertEquals(150000, (float) $retur->jumlah);

        // Stok fisik bertambah kembali
        $stok->refresh();
        $this->assertEquals(11, (int) $stok->jumlah);

        // Nomor seri kembali menjadi tersedia
        $sn1->refresh();
        $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $sn1->status);

        // Mutasi log tercatat
        $mutation = StockMutationLog::where('referensi_tipe', ReturnPenjualan::class)
            ->where('referensi_id', $retur->id)
            ->first();
        $this->assertNotNull($mutation);
        $this->assertEquals('retur_penjualan', $mutation->sumber);
        $this->assertEquals(1, (int) $mutation->delta);

        // Jurnal seimbang dan terbentuk
        $jurnal = JurnalHeader::where('referensi_tipe', ReturnPenjualan::class)
            ->where('referensi_id', $retur->id)
            ->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(250000, (float) $jurnal->total_debit); // 150rb retur + 100rb persediaan
        $this->assertEquals(250000, (float) $jurnal->total_kredit); // 150rb kas + 100rb hpp
    }

    public function test_retur_pembelian_memotong_stok_jurnal_ap_dan_subledger_utang(): void
    {
        $supplier = Supplier::create([
            'nama' => 'Supplier Test',
            'kode' => 'SUP-TEST',
            'status' => 'aktif',
        ]);

        $stok = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 5,
        ]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-2026-0001',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'tanggal' => now(),
            'total' => 500000,
            'status' => 'diterima',
            'status_pembayaran' => 'belum_lunas',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 5,
            'harga_beli' => 100000,
            'subtotal' => 500000,
        ]);

        $utang = Utang::create([
            'no_utang' => 'UTG-TEST-0001',
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => $po->id,
            'no_referensi' => $po->no_po,
            'tanggal' => now(),
            'jatuh_tempo' => now()->addDays(30),
            'jumlah' => 500000,
            'jumlah_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        $sn = NomorSeri::create([
            'nomor_seri' => 'SN-SUP-01',
            'produk_id' => $this->produk->id,
            'cabang_id' => $this->cabang->id,
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $service = app(ReturnPembelianService::class);

        $retur = $service->buatRetur(
            $po,
            [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'jumlah' => 2,
                    'sn' => ['SN-SUP-01'],
                ],
            ],
            'Barang cacat pabrik',
            'utang',
            $this->user->id
        );

        $this->assertEquals('selesai', $retur->status);
        $this->assertEquals(200000, (float) $retur->jumlah);

        // Stok fisik berkurang
        $stok->refresh();
        $this->assertEquals(3, (int) $stok->jumlah);

        // Status SN menjadi return
        $sn->refresh();
        $this->assertEquals('return', $sn->status);

        // Utang subledger terpotong
        $utang->refresh();
        $this->assertEquals(300000, (float) $utang->jumlah);

        // Jurnal terbentuk (Debit Utang 210-01, Kredit Persediaan 130-01)
        $jurnal = JurnalHeader::where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(200000, (float) $jurnal->total_debit);
        $this->assertEquals(200000, (float) $jurnal->total_kredit);
    }

    public function test_api_retur_penjualan_dan_pembelian(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan API',
            'no_telepon' => '08999999999',
            'tipe' => 'retail',
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 10,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-API-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 1,
            'subtotal' => 150000,
            'total_akhir' => 150000,
            'jumlah_bayar' => 150000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $item = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 150000,
            'harga_final' => 150000,
            'subtotal' => 150000,
            'hpp' => 100000,
        ]);

        $res = $this->actingAs($this->user)->postJson('/api/pos/retur', [
            'transaksi_id' => $transaksi->id,
            'alasan' => 'Salah beli tipe',
            'metode_pengembalian' => 'kas',
            'items' => [
                [
                    'transaksi_item_id' => $item->id,
                    'jumlah' => 1,
                ],
            ],
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('success', true);
    }

    public function test_retur_penjualan_melebihi_kuantiti_transaksi_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Validasi',
            'tipe' => 'retail',
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-VAL-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 1,
            'subtotal' => 150000,
            'total_akhir' => 150000,
            'status' => 'selesai',
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 150000,
            'subtotal' => 150000,
        ]);

        $service = app(ReturnPenjualanService::class);
        $service->buatRetur(
            $transaksi,
            [
                [
                    'transaksi_item_id' => $trxItem->id,
                    'jumlah' => 5, // melebihi qty beli (1)
                ],
            ],
            'Coba retur lebih',
            'kas',
            $this->user->id
        );
    }

    public function test_retur_diatas_threshold_masuk_draft_menunggu_approval(): void
    {
        // Rule threshold 5.000.000
        ApprovalRule::create([
            'entity_type' => 'retur',
            'min_amount' => 5000000,
            'approver_role' => 'superadmin',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Grosir',
            'tipe' => 'grosir',
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 100,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-BIG-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 50,
            'subtotal' => 7500000,
            'total_akhir' => 7500000,
            'status' => 'selesai',
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 50,
            'harga_satuan' => 150000,
            'subtotal' => 7500000,
            'hpp' => 100000,
        ]);

        $service = app(ReturnPenjualanService::class);
        $retur = $service->buatRetur(
            $transaksi,
            [
                [
                    'transaksi_item_id' => $trxItem->id,
                    'jumlah' => 40, // 40 * 150.000 = 6.000.000 (> 5.000.000)
                ],
            ],
            'Retur partai besar',
            'kas',
            $this->user->id
        );

        // Status harus draft dan ada approval request
        $this->assertEquals('draft', $retur->status);
        $this->assertDatabaseHas('approval_requests', [
            'entity_type' => 'retur',
            'entity_id' => $retur->id,
            'status' => 'pending',
        ]);
    }
}
