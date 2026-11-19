<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [REGRESSION][FINANSIAL] Retur penjualan per satuan konversi (box).
 * qtyRetur dari UI dalam SATUAN (box), bukan base unit. Refund, HPP, dan
 * restore stok harus memperhitungkan faktor konversi.
 *
 * Skenario: 1 box = 4 pcs, harga jual per box 38.000, HPP per box 30.000.
 * Retur 1 box => refund 38.000, HPP 30.000, stok kembali 4 pcs.
 */
class ReturPenjualanBoxKonversiTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create(['kode' => 'CAB-BOX', 'nama' => 'Cabang Box', 'is_pusat' => true, 'status' => 'aktif']);
        $this->gudang = Gudang::create(['kode' => 'GDG-BOX', 'nama' => 'Gudang Box', 'cabang_id' => $this->cabang->id, 'is_default' => true, 'status' => 'aktif']);
        $this->produk = Produk::create(['kode' => 'PRD-BOX', 'nama' => 'Oli Box', 'harga_beli' => 30000, 'harga_jual' => 38000, 'status' => 'aktif', 'has_sn' => false, 'satuan' => 'pcs']);

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

    public function test_retur_satu_box_refund_hpp_dan_stok_memakai_faktor_konversi(): void
    {
        $pelanggan = Pelanggan::create(['nama' => 'Budi Box', 'no_telepon' => '0812000111', 'tipe' => 'retail']);
        $stok = StokItem::create(['gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id, 'jumlah' => 10]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-BOX-0001', 'cabang_id' => $this->cabang->id, 'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id, 'pelanggan_id' => $pelanggan->id,
            'total_barang' => 1, 'subtotal' => 38000, 'total_akhir' => 38000, 'jumlah_bayar' => 38000,
            'metode_bayar' => 'tunai', 'status' => 'selesai',
        ]);

        // Item transaksi: 1 BOX (konversi 4), harga & HPP per box.
        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id, 'produk_id' => $this->produk->id,
            'jumlah' => 1, 'satuan' => 'box', 'konversi' => 4,
            'harga_satuan' => 38000, 'subtotal' => 38000, 'hpp' => 30000,
        ]);

        $service = app(ReturnPenjualanService::class);
        $retur = $service->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $trxItem->id, 'jumlah' => 1]],
            'Salah beli',
            'kas',
            $this->user->id
        );

        // Refund harus harga per box (38.000), bukan per pcs (9.500).
        $this->assertEquals(38000.0, (float) $retur->jumlah, 'Refund retur 1 box harus 38.000');

        $returItem = $retur->items->first();
        $this->assertEquals('box', $returItem->satuan, 'Satuan retur harus box (bukan hardcode pcs)');
        $this->assertEquals(4.0, (float) $returItem->konversi, 'Konversi retur harus 4');
        $this->assertEquals(1, (int) $returItem->jumlah, 'Jumlah retur dalam satuan (1 box)');
        $this->assertEquals(38000.0, (float) $returItem->subtotal, 'Subtotal item retur 38.000');

        // Stok kembali 4 pcs (1 box x konversi 4), bukan 1 pcs.
        $stok->refresh();
        $this->assertEquals(14, (int) $stok->jumlah, 'Stok harus kembali 4 pcs (10 -> 14)');

        $mutation = StockMutationLog::where('referensi_tipe', ReturnPenjualan::class)->where('referensi_id', $retur->id)->first();
        $this->assertNotNull($mutation);
        $this->assertEquals(4, (int) $mutation->delta, 'Delta stok retur harus 4 pcs');

        // Jurnal balance: retur 38.000 + persediaan 30.000 = 68.000 (D & K).
        $jurnal = JurnalHeader::where('referensi_tipe', ReturnPenjualan::class)->where('referensi_id', $retur->id)->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(68000.0, (float) $jurnal->total_debit, 'Total debit harus 68.000 (38rb retur + 30rb persediaan)');
        $this->assertEquals(68000.0, (float) $jurnal->total_kredit, 'Total kredit harus 68.000 (38rb kas + 30rb HPP)');
        $this->assertEquals((float) $jurnal->total_debit, (float) $jurnal->total_kredit, 'Jurnal harus balance');
    }
}
