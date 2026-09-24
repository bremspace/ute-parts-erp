<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Livewire\LaporanNomorSeri;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\NomorSeriEvent;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Workflow\Models\ApprovalRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F2-3 — Serial Number (PRD-Advanced-UteParts baris 92).
 *
 * (a) SN tersimpan saat GRN finalize (sn=true) + jurnal/stok tetap benar
 * (b) SN count ≠ qty → ditolak, tanpa side effect GRN/PO
 * (c) POS sn=true wajib SN — invalid/dobel/tidak-tersedia ditolak; valid → terjual + relasi
 * (d) servis SN link → status servis → kembali tersedia saat selesai
 * (e) trace laporan histori per SN + cabang scoping
 */
class SerialNumberTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $user;

    private Produk $produk;

    private Produk $produkSn;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // COA wajib utk JurnalService::post (GRN, POS, servis)
        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '210-01' => ['Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '420-01' => ['Pendapatan Jasa Servis', 'pendapatan', 'pendapatan_jasa', 'kredit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create([
            'kode' => 'UTP-SN',
            'nama' => 'Cabang SN',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang SN',
            'kode' => 'GDG-SN',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin SN',
            'email' => 'admin-sn@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Sparepart Biasa',
            'slug' => Str::slug('Sparepart Biasa').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
            'sn' => false,
        ]);

        $this->produkSn = Produk::create([
            'nama' => 'Sparepart SN',
            'slug' => Str::slug('Sparepart SN').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
            'sn' => true,
        ]);

        $this->supplier = Supplier::create(['nama' => 'Supplier SN', 'termin_hari' => 30]);
    }

    private function buatPoDikirim(int $qtyItem, float $harga, ?Produk $produk = null): PurchaseOrder
    {
        $produk ??= $this->produkSn;

        $po = PurchaseOrder::create([
            'no_po' => 'PO-SN-'.Str::random(6),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'kredit',
            'total' => $qtyItem * $harga,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $produk->id,
            'harga_beli' => $harga,
            'jumlah' => $qtyItem,
            'subtotal' => $qtyItem * $harga,
        ]);

        return $po->load('items');
    }

    private function service(): GrnService
    {
        return app(GrnService::class);
    }

    private function approver(): User
    {
        $u = User::create([
            'name' => 'Approver SN',
            'email' => 'approver-sn@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $u->assignRole('admin-toko');

        return $u;
    }

    private function buatStok(int $jumlah): void
    {
        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkSn->id,
            'sku_variant_id' => null,
            'jumlah' => $jumlah,
            'jumlah_minimum' => 0,
        ]);
    }

    // ===== (a) SN tersimpan saat GRN finalize (auto qty-sesuai) =====

    public function test_sn_tersimpan_grn_qty_sesuai_dengan_jurnal_dan_stok_benar(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(3, 50000); // total 150.000

        $grn = $this->service()->inputGudang(
            $po,
            [$this->produkSn->id => 3],
            $this->user->id,
            [$this->produkSn->id => ['SN-A1', 'SN-A2', 'SN-A3']]
        );

        $this->assertEquals('terima', $grn->status, 'Qty sesuai → auto terima');

        // SN dibuat: 3 baris tersedia, cabang-scoped, keterangan GRN
        $sns = NomorSeri::where('produk_id', $this->produkSn->id)->get();
        $this->assertCount(3, $sns);
        $this->assertEqualsCanonicalizing(['SN-A1', 'SN-A2', 'SN-A3'], $sns->pluck('nomor_seri')->all());
        foreach ($sns as $sn) {
            $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $sn->status);
            $this->assertEquals((int) $this->cabang->id, (int) $sn->cabang_id);
            $this->assertEquals("GRN {$grn->no_grn}", $sn->keterangan);
            $this->assertNull($sn->transaksi_item_id);
        }

        // Jurnal AP tetap benar: 2 baris, debit = kredit = 150.000
        $jurnal = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->get();
        $this->assertCount(2, $jurnal);
        $this->assertEquals(150000, (float) $jurnal->sum('debit'));
        $this->assertEquals(150000, (float) $jurnal->sum('kredit'));

        // Stok tetap benar
        $this->assertEquals(3, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);
        $this->assertTrue(
            StokLog::where('produk_id', $this->produkSn->id)
                ->where('jenis', 'GRN')->where('perubahan', 3)->exists()
        );

        // Tanpa approval
        $this->assertSame(0, ApprovalRequest::where('entity_type', 'grn')->count());
    }

    // ===== (b) SN count ≠ qty → ditolak, tanpa side effect =====

    public function test_sn_count_tidak_sama_qty_ditolak_tanpa_side_effect_grn_poi(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(5, 50000);

        // Jumlah SN (4) ≠ qty diterima (5) → Exception Indonesia, GRN tidak dibuat
        $err1 = null;
        try {
            $this->service()->inputGudang(
                $po,
                [$this->produkSn->id => 5],
                $this->user->id,
                [$this->produkSn->id => ['SN-X1', 'SN-X2', 'SN-X3', 'SN-X4']]
            );
        } catch (\Exception $e) {
            $err1 = $e;
        }
        $this->assertNotNull($err1, 'SN count ≠ qty wajib ditolak');
        $this->assertStringContainsString('harus sama dengan qty diterima', $err1->getMessage());

        // Tidak ada side effect: GRN, approval, jurnal, stok, SN, status PO
        $this->assertSame(0, Grn::count());
        $this->assertSame(0, ApprovalRequest::count());
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(0, StokLog::count());
        $this->assertSame(0, StokItem::count());
        $this->assertSame(0, NomorSeri::count());
        $this->assertEquals('dikirim', $po->fresh()->status);

        // Bonus: SN dobel dalam daftar (jumlah == qty) tetap ditolak
        $err2 = null;
        try {
            $this->service()->inputGudang(
                $po,
                [$this->produkSn->id => 3],
                $this->user->id,
                [$this->produkSn->id => ['SN-Y1', 'SN-Y1', 'SN-Y2']]
            );
        } catch (\Exception $e) {
            $err2 = $e;
        }
        $this->assertNotNull($err2, 'SN dobel wajib ditolak');
        $this->assertStringContainsString('dobel', $err2->getMessage());
        $this->assertSame(0, Grn::count());
    }

    // ===== (a2) Jalur approval: setujui → SN ikut finalisasi =====

    public function test_sn_finalisasi_via_jalur_approval_setujui_grn(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $snList = ['SN-P1', 'SN-P2', 'SN-P3', 'SN-P4', 'SN-P5', 'SN-P6'];

        $grn = $this->service()->inputGudang(
            $po,
            [$this->produkSn->id => 6], // partial → draft + approval
            $this->user->id,
            [$this->produkSn->id => $snList]
        );

        $this->assertEquals('draft', $grn->status);
        $this->assertSame(0, NomorSeri::count(), 'SN baru dibuat saat finalisasi, bukan saat draft');

        $req = ApprovalRequest::where('entity_type', 'grn')->where('entity_id', $grn->id)->firstOrFail();
        $req->proses('approved', $this->approver()->id, 'Selisih wajar');

        $this->assertEquals('terima', $grn->fresh()->status);
        $sns = NomorSeri::where('produk_id', $this->produkSn->id)->get();
        $this->assertCount(6, $sns);
        $this->assertEqualsCanonicalizing($snList, $sns->pluck('nomor_seri')->all());
        $this->assertTrue($sns->every(fn ($s) => $s->status === NomorSeri::STATUS_TERSEDIA));

        // Jurnal AP & stok tetap benar (6 × 50.000)
        $jurnal = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->get();
        $this->assertCount(2, $jurnal);
        $this->assertEquals(300000, (float) $jurnal->sum('debit'));
        $this->assertEquals(6, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);
    }

    // ===== (c) POS: sn=true wajib SN — invalid/dobel/tidak-tersedia ditolak =====

    public function test_pos_sn_wajib_invalid_dobel_tidak_tersedia_ditolak(): void
    {
        Queue::fake();
        $this->buatStok(5);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-OK',
            'status' => NomorSeri::STATUS_TERSEDIA,
            'keterangan' => 'GRN TEST',
        ]);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-LAMA',
            'status' => NomorSeri::STATUS_TERJUAL,
            'transaksi_item_id' => null,
        ]);

        $key = $this->produkSn->id.'-0';

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produkSn->id)
            ->set('metodeBayar', 'transfer')
            // [P2-2] seleksi SN hanya sah utk item yg sedang aktif (fokus input)
            ->call('setSnItemKey', $key);

        // 1) SN tidak ada → ditolak, transaksi tidak terbentuk
        $component->call('pilihSnLangsung', $key, 'SN-BOGUS')
            ->call('processTransaction');
        $this->assertSame(0, Transaksi::count(), 'SN tidak valid wajib ditolak');
        $component->call('hapusSn', $key, 0);

        // 2) SN dobel (qty 2, SN sama 2x) → ditolak
        $component->call('updateQty', $key, 1)
            ->set('cart.'.$key.'.sn_list', ['SN-OK', 'SN-OK'])
            ->call('processTransaction');
        $this->assertSame(0, Transaksi::count(), 'SN dobel wajib ditolak');
        $component->call('hapusSn', $key, 0)->call('hapusSn', $key, 0)->call('updateQty', $key, -1);

        // 3) SN tidak tersedia (status terjual) → ditolak
        $component->call('pilihSnLangsung', $key, 'SN-LAMA')
            ->call('processTransaction');
        $this->assertSame(0, Transaksi::count(), 'SN tidak berstatus tersedia wajib ditolak');

        // Tidak ada jurnal/stok sisa dari percobaan gagal
        $this->assertSame(0, JurnalAkuntansi::where('sumber', 'pos')->count());
        $this->assertEquals(5, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);
        $this->assertEquals(NomorSeri::STATUS_TERJUAL, NomorSeri::where('nomor_seri', 'SN-LAMA')->first()->status);
    }

    // ===== (c2) POS: SN valid → terjual + relasi transaksi_item =====

    public function test_pos_sn_valid_menjadi_terjual_dan_terhubung_transaksi_item(): void
    {
        Queue::fake();
        $this->buatStok(5);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-JUAL-1',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-SISA',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $key = $this->produkSn->id.'-0';

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produkSn->id)
            ->set('metodeBayar', 'transfer')
            ->call('setSnItemKey', $key) // [P2-2] item aktif di input SN
            ->call('pilihSnLangsung', $key, 'SN-JUAL-1')
            ->call('processTransaction');

        $this->assertSame(1, Transaksi::count(), 'Transaksi valid wajib terbentuk');

        $sn = NomorSeri::where('nomor_seri', 'SN-JUAL-1')->first();
        $this->assertEquals(NomorSeri::STATUS_TERJUAL, $sn->status);
        $this->assertNotNull($sn->transaksi_item_id, 'SN wajib terhubung ke transaksi_item');

        $item = TransaksiItem::find($sn->transaksi_item_id);
        $this->assertNotNull($item);
        $this->assertEquals($this->produkSn->id, $item->produk_id);
        $this->assertEquals($sn->transaksiItem->transaksi_id, Transaksi::first()->id);

        // SN lain tidak terpengaruh; stok berkurang 1
        $this->assertEquals(NomorSeri::STATUS_TERSEDIA, NomorSeri::where('nomor_seri', 'SN-SISA')->first()->status);
        $this->assertEquals(4, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);

        // Jurnal POS balance
        $jurnal = JurnalAkuntansi::where('sumber', 'pos')->get();
        $this->assertNotEmpty($jurnal);
        $this->assertEquals((float) $jurnal->sum('debit'), (float) $jurnal->sum('kredit'));
    }

    // ===== (d) Servis: SN link → servis → kembali tersedia di selesai =====

    public function test_servis_sn_link_status_servis_lalu_kembali_tersedia_di_selesai(): void
    {
        Queue::fake();
        $this->buatStok(5);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SRV-001',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $svc = app(ServisService::class);
        $tiket = $svc->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 15',
            'keluhan' => 'Layar mati total',
        ], $this->user);

        $svc->updateStatus($tiket, 'diagnosa', $this->user, 'Cek unit');
        $svc->setEstimasi($tiket->fresh(), 150000, 'Ganti layar', $this->user);
        $svc->updateStatus($tiket->fresh(), 'disetujui', $this->user, 'OK');
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Mulai kerja');

        // Input pekerjaan part sn=true tanpa SN → ditolak, tanpa efek stok
        $errServis = null;
        try {
            $svc->inputPekerjaan($tiket->fresh(), [[
                'tipe' => 'part',
                'produk_id' => $this->produkSn->id,
                'nama_item' => 'Layar LCD',
                'qty' => 1,
                'harga' => 150000,
                'gudang_id' => $this->gudang->id,
                'sn' => [],
            ]], $this->user);
        } catch (\Exception $e) {
            $errServis = $e;
        }
        $this->assertNotNull($errServis, 'Part sn=true tanpa SN wajib ditolak');
        $this->assertStringContainsString('nomor seri', strtolower($errServis->getMessage()));
        $this->assertEquals(5, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);
        $this->assertSame(0, TiketServis::find($tiket->id)->items()->count());

        // Dengan SN valid → status servis + tautan tiket & item
        $created = $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part',
            'produk_id' => $this->produkSn->id,
            'nama_item' => 'Layar LCD',
            'qty' => 1,
            'harga' => 150000,
            'gudang_id' => $this->gudang->id,
            'sn' => ['SRV-001'],
        ]], $this->user);

        $sn = NomorSeri::where('nomor_seri', 'SRV-001')->first();
        $this->assertEquals(NomorSeri::STATUS_SERVIS, $sn->status);
        $this->assertEquals((int) $tiket->id, (int) $sn->tiket_servis_id);
        $this->assertNotNull($sn->tiket_servis_item_id);
        $this->assertEquals($created[0]->id, (int) $sn->tiket_servis_item_id);
        $this->assertEquals(4, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);

        // Selesai → kembali tersedia, tautan riwayat TETAP (trace garansi)
        $svc->updateStatus($tiket->fresh(), 'qc', $this->user, 'QC lolos');
        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai');

        $sn->refresh();
        $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $sn->status, 'SN kembali tersedia saat tiket selesai');
        $this->assertEquals((int) $tiket->id, (int) $sn->tiket_servis_id, 'Tautan riwayat tidak boleh hilang');
        $this->assertNotNull($sn->tiket_servis_item_id, 'Tautan item riwayat tidak boleh hilang');

        // Garansi ter-autocreate (trace histori garansi)
        $this->assertNotNull(Garansi::where('tiket_servis_id', $tiket->id)->first());
    }

    // ===== (e) Trace laporan histori per SN + cabang scoping =====

    public function test_laporan_trace_render_dan_cabang_scoping(): void
    {
        Queue::fake();

        // Transaksi penjualan utk SN cabang A
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TRACE-01',
            'cabang_id' => $this->cabang->id,
            'subtotal' => 100000,
            'diskon_nominal' => 0,
            'dpp' => 100000,
            'pajak_nominal' => 0,
            'ppn_nominal' => 0,
            'total_akhir' => 100000,
            'status' => 'selesai',
        ]);
        $item = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produkSn->id,
            'jumlah' => 1,
            'harga_satuan' => 100000,
            'subtotal' => 100000,
        ]);

        // SN cabang A (terjual, punya rantai GRN → transaksi)
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-TRACE-A',
            'status' => NomorSeri::STATUS_TERJUAL,
            'transaksi_item_id' => $item->id,
            'keterangan' => 'GRN-TRACE-01',
        ]);

        // SN cabang lain — TIDAK boleh bocor
        $cabangLain = Cabang::create([
            'kode' => 'UTP-SN-B',
            'nama' => 'Cabang Lain',
            'alamat' => 'Jl. Lain',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);
        NomorSeri::create([
            'cabang_id' => $cabangLain->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-RAHASIA-B',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $this->actingAs($this->user, 'web')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/laporan/nomor-seri')
            ->assertSuccessful()
            ->assertSee('SN-TRACE-A')
            ->assertSee('GRN-TRACE-01')
            ->assertSee('TRX-TRACE-01')
            ->assertSee('Sparepart SN')
            ->assertDontSee('SN-RAHASIA-B', false);

        // Livewire component: search per SN bekerja & tetap scoped cabang
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(LaporanNomorSeri::class)
            ->assertSee('SN-TRACE-A')
            ->assertDontSee('SN-RAHASIA-B', false)
            ->set('search', 'SN-TRACE-A')
            ->assertSee('TRX-TRACE-01')
            ->set('search', 'SN-RAHASIA')
            ->assertDontSee('SN-RAHASIA-B', false);
    }

    // ===== (f) [P1-3] Tamper flag sn=false via prop publik → klaim tetap dipaksa =====

    public function test_pos_sn_flag_tamper_false_klaim_tetap_dipaksa(): void
    {
        Queue::fake();
        $this->buatStok(5);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-TAMPER',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $key = $this->produkSn->id.'-0';

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        // 1) Tamper sn=false + tanpa SN → validasi server-side (derive dari
        //    produk) tetap jalan → modal bayar ditolak & transaksi gagal
        $component = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produkSn->id)
            ->set('metodeBayar', 'transfer')
            ->set('cart.'.$key.'.sn', false) // tamper prop publik Livewire
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', false, 'Gate SN tetap aktif walau flag di-tamper')
            ->call('processTransaction');
        $this->assertSame(0, Transaksi::count(), 'Klaim SN tetap dipaksa — transaksi wajib ditolak');

        // 2) Tamper sn=false + SN valid → klaim tetap berjalan → SN terjual
        $component->call('setSnItemKey', $key) // [P2-2] item aktif di input SN
            ->call('pilihSnLangsung', $key, 'SN-TAMPER')
            ->set('cart.'.$key.'.sn', false)
            ->call('processTransaction');
        $this->assertSame(1, Transaksi::count(), 'Transaksi valid tetap terbentuk');
        $this->assertEquals(
            NomorSeri::STATUS_TERJUAL,
            NomorSeri::where('nomor_seri', 'SN-TAMPER')->first()->status,
            'SN wajib terjual walau flag sn di-tamper false'
        );
    }

    // ===== (g) [P1-5] SN diklaim → tiket di-override 'ditolak' → SN kembali tersedia, link utuh =====

    public function test_servis_sn_dilepas_saat_tiket_ditolak_dengan_link_riwayat_intact(): void
    {
        Queue::fake();
        $this->buatStok(5);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SRV-DTL',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $svc = app(ServisService::class);
        $tiket = $svc->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 15',
            'keluhan' => 'Layar retak',
        ], $this->user);
        $svc->updateStatus($tiket, 'diagnosa', $this->user, 'Cek unit');
        $svc->setEstimasi($tiket->fresh(), 200000, 'Ganti layar', $this->user);
        $svc->updateStatus($tiket->fresh(), 'disetujui', $this->user, 'OK');
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Mulai kerja');

        $created = $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part',
            'produk_id' => $this->produkSn->id,
            'nama_item' => 'Layar LCD',
            'qty' => 1,
            'harga' => 200000,
            'gudang_id' => $this->gudang->id,
            'sn' => ['SRV-DTL'],
        ]], $this->user);

        $sn = NomorSeri::where('nomor_seri', 'SRV-DTL')->first();
        $this->assertEquals(NomorSeri::STATUS_SERVIS, $sn->status);
        $this->assertEquals((int) $tiket->id, (int) $sn->tiket_servis_id);

        // Override dikerjakan → ditolak (super-admin punya servis.override-status)
        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Unit tidak layak diperbaiki');

        $sn->refresh();
        $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $sn->status, 'SN wajib kembali tersedia saat tiket ditolak');
        $this->assertEquals((int) $tiket->id, (int) $sn->tiket_servis_id, 'Tautan riwayat tiket tidak boleh hilang');
        $this->assertEquals($created[0]->id, (int) $sn->tiket_servis_item_id, 'Tautan item riwayat tidak boleh hilang');

        // SN kembali terlihat di autocomplete 'tersedia' (tidak macet)
        $this->assertTrue(
            app(NomorSeriService::class)
                ->cariTersedia((int) $this->cabang->id, $this->produkSn->id, 'SRV-DTL')
                ->pluck('nomor_seri')->contains('SRV-DTL'),
            'SN wajib muncul kembali di pencarian tersedia'
        );
    }

    // ===== (h) [P1-6] Kunjungan ke-2 / resale menimpa link → snapshot riwayat link ke-1 tersimpan =====

    public function test_riwayat_link_lama_tersimpan_sebelum_ditimpa_klaim_ulang(): void
    {
        Queue::fake();

        $ns = app(NomorSeriService::class);
        $svc = app(ServisService::class);

        $tiket1 = $svc->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 14',
            'keluhan' => 'Ganti baterai',
        ], $this->user);
        $tiket2 = $svc->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 14',
            'keluhan' => 'Ganti layar',
        ], $this->user);

        $item1 = TiketServisItem::create([
            'tiket_servis_id' => $tiket1->id,
            'tipe' => 'part',
            'produk_id' => $this->produkSn->id,
            'nama_item' => 'Baterai',
            'qty' => 1,
            'harga' => 100000,
            'hpp' => 50000,
        ]);
        $item2 = TiketServisItem::create([
            'tiket_servis_id' => $tiket2->id,
            'tipe' => 'part',
            'produk_id' => $this->produkSn->id,
            'nama_item' => 'Layar',
            'qty' => 1,
            'harga' => 150000,
            'hpp' => 80000,
        ]);

        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-HIST',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        // Kunjungan ke-1 → selesai (lepas: status tersedia, link tetap menunjuk tiket1)
        $ns->klaimServis(['SN-HIST'], $this->produkSn->id, (int) $this->cabang->id, 1, $tiket1->id, $item1->id);
        $ns->lepasServis($tiket1->id);

        // Kunjungan ke-2 menimpa link → snapshot link ke-1 wajib tersimpan dulu
        $ns->klaimServis(['SN-HIST'], $this->produkSn->id, (int) $this->cabang->id, 1, $tiket2->id, $item2->id);

        $sn = NomorSeri::where('nomor_seri', 'SN-HIST')->first();
        $this->assertEquals((int) $tiket2->id, (int) $sn->tiket_servis_id, 'Link terkini = kunjungan ke-2');

        $eventSrv = $sn->events()->where('aksi', NomorSeriEvent::AKSI_KLAIM_SERVIS)->first();
        $this->assertNotNull($eventSrv, 'Snapshot link kunjungan ke-1 wajib tersimpan');
        $this->assertEquals((int) $tiket1->id, (int) $eventSrv->tiket_servis_id, 'Riwayat tiket ke-1 tidak boleh hilang');
        $this->assertEquals($item1->id, (int) $eventSrv->tiket_servis_item_id, 'Riwayat item ke-1 tidak boleh hilang');
        $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $eventSrv->status_sebelum);
        $this->assertEquals((int) $this->cabang->id, (int) $eventSrv->cabang_id, 'Event tetap cabang-scoped');

        // Resale: SN pernah terjual (link lama) lalu kembali stok → klaim jual baru menimpa
        $ns->lepasServis($tiket2->id);

        $trxLama = Transaksi::create([
            'no_transaksi' => 'TRX-HIST-LAMA',
            'cabang_id' => $this->cabang->id,
            'subtotal' => 100000,
            'diskon_nominal' => 0,
            'dpp' => 100000,
            'pajak_nominal' => 0,
            'ppn_nominal' => 0,
            'total_akhir' => 100000,
            'status' => 'selesai',
        ]);
        $itemJualLama = TransaksiItem::create([
            'transaksi_id' => $trxLama->id,
            'produk_id' => $this->produkSn->id,
            'jumlah' => 1,
            'harga_satuan' => 100000,
            'subtotal' => 100000,
        ]);
        $trxBaru = Transaksi::create([
            'no_transaksi' => 'TRX-HIST-BARU',
            'cabang_id' => $this->cabang->id,
            'subtotal' => 100000,
            'diskon_nominal' => 0,
            'dpp' => 100000,
            'pajak_nominal' => 0,
            'ppn_nominal' => 0,
            'total_akhir' => 100000,
            'status' => 'selesai',
        ]);
        $itemJualBaru = TransaksiItem::create([
            'transaksi_id' => $trxBaru->id,
            'produk_id' => $this->produkSn->id,
            'jumlah' => 1,
            'harga_satuan' => 100000,
            'subtotal' => 100000,
        ]);

        $sn->update(['transaksi_item_id' => $itemJualLama->id]); // return-to-stok, link jual lama masih ada

        $ns->klaimJual(['SN-HIST'], $this->produkSn->id, (int) $this->cabang->id, 1, $itemJualBaru->id);

        $sn->refresh();
        $this->assertEquals(NomorSeri::STATUS_TERJUAL, $sn->status);
        $this->assertEquals($itemJualBaru->id, (int) $sn->transaksi_item_id, 'Link terkini = jual baru');

        $eventJual = $sn->events()->where('aksi', NomorSeriEvent::AKSI_KLAIM_JUAL)->first();
        $this->assertNotNull($eventJual, 'Snapshot jual lama wajib tersimpan');
        $this->assertEquals($itemJualLama->id, (int) $eventJual->transaksi_item_id, 'Riwayat jual ke-1 tidak boleh hilang');

        // Hanya overwrite terekam — klaim dgn link lama null (kunjungan ke-1) tidak bikin event
        $this->assertSame(
            1,
            $sn->events()->where('aksi', NomorSeriEvent::AKSI_KLAIM_SERVIS)->count(),
            '2x klaimServis → hanya 1 event (overwrite), bukan 2'
        );
        $this->assertSame(
            1,
            $sn->events()->where('aksi', NomorSeriEvent::AKSI_KLAIM_JUAL)->count()
        );
    }

    // ===== (i) [P2-1] qty turun → sn_list dipangkas, gate bayar ikut count =====

    public function test_pos_update_qty_kurangi_sn_list_dipangkas_dan_gate_bayar_ikut(): void
    {
        Queue::fake();
        $this->buatStok(5);

        $key = $this->produkSn->id.'-0';

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produkSn->id)
            ->call('updateQty', $key, 1) // qty 2
            ->call('setSnItemKey', $key)
            ->call('pilihSnLangsung', $key, 'SN-Q1')
            ->assertSet('cart.'.$key.'.sn_list', ['SN-Q1'])
            ->call('pilihSnLangsung', $key, 'SN-Q2')
            ->assertSet('cart.'.$key.'.sn_list', ['SN-Q1', 'SN-Q2']);

        // qty 2 → 1: sn_list dipangkas keep-first-N + warning Indonesia
        $component->call('updateQty', $key, -1)
            ->assertSet('cart.'.$key.'.qty', 1)
            ->assertSet('cart.'.$key.'.sn_list', ['SN-Q1'])
            ->assertDispatched('alert'); // "… SN dihapus … karena qty dikurangi"

        // count == qty setelah prune → gate lolos
        $component->call('openPaymentModal')
            ->assertSet('showPaymentModal', true);

        // qty naik TIDAK di-fill → gate tetap menolak count != qty
        $component->set('showPaymentModal', false)
            ->call('updateQty', $key, 1)
            ->assertSet('cart.'.$key.'.sn_list', ['SN-Q1'])
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', false);
    }

    // ===== (j) [P2-2] SN di-typed utk item A tidak boleh masuk item B =====

    public function test_pos_pilih_sn_item_key_tidak_sesuai_ditolak(): void
    {
        Queue::fake();
        $this->buatStok(5);
        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'jumlah' => 5,
            'jumlah_minimum' => 0,
        ]);
        NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-XREF',
            'status' => NomorSeri::STATUS_TERSEDIA,
        ]);

        $keySn = $this->produkSn->id.'-0';
        $keyBiasa = $this->produk->id.'-0';

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produkSn->id)
            ->call('addToCart', $this->produk->id)
            ->call('setSnItemKey', $keySn) // fokus input SN di item A
            ->set('snSearch', 'SN-XREF');

        // pilihSn (Enter/+) dgn itemKey item B → diabaikan + warning
        $component->call('pilihSn', $keyBiasa)
            ->assertDispatched('alert')
            ->assertSet('cart.'.$keyBiasa.'.sn_list', []);

        // pilihSnLangsung dgn itemKey item B → diabaikan juga (tanpa efek samping)
        $component->call('pilihSnLangsung', $keyBiasa, 'SN-XREF')
            ->assertDispatched('alert')
            ->assertSet('cart.'.$keyBiasa.'.sn_list', [])
            ->assertSet('cart.'.$keySn.'.sn_list', []);

        // itemKey cocok dgn snItemKey → masuk (snSearch masih utuh utk item A)
        $component->call('pilihSn', $keySn)
            ->assertSet('cart.'.$keySn.'.sn_list', ['SN-XREF'])
            ->assertSet('snSearch', '');
    }

    // ===== (k) [P2-3] resumeDitahan: flag sn selalu segar dari Produk =====

    public function test_resume_ditahan_sn_flag_recompute_dari_produk(): void
    {
        Queue::fake();

        $keySn = $this->produkSn->id.'-0';   // Produk.sn = true
        $keyBiasa = $this->produk->id.'-0';  // Produk.sn = false

        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-PARK-SN',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'subtotal' => 200000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'dpp' => 200000,
            'pajak_nominal' => 0,
            'ppn_nominal' => 0,
            'total_akhir' => 200000,
            'metode_bayar' => 'ditahan',
            'jumlah_bayar' => 0,
            'kembalian' => 0,
            'split_detail' => [
                'cart' => [
                    [
                        'produk_id' => $this->produkSn->id,
                        'sku_variant_id' => null,
                        'nama' => 'Sparepart SN',
                        'varian' => 'Standar',
                        'harga' => 100000,
                        'qty' => 1,
                        'diskon' => 0,
                        'subtotal' => 100000,
                        'stok_max' => 5,
                        'flex' => false,
                        'sn' => false, // STALE — padahal Produk.sn = true
                        'sn_list' => ['SN-PARK'],
                    ],
                    [
                        'produk_id' => $this->produk->id,
                        'sku_variant_id' => null,
                        'nama' => 'Sparepart Biasa',
                        'varian' => 'Standar',
                        'harga' => 100000,
                        'qty' => 1,
                        'diskon' => 0,
                        'subtotal' => 100000,
                        'stok_max' => 5,
                        'flex' => false,
                        'sn' => true, // STALE — padahal Produk.sn = false
                        'sn_list' => [],
                    ],
                ],
                'diskon_persen' => 0,
                'diskon_nominal' => 0,
            ],
            'status' => 'ditahan',
            'catatan' => 'Transaksi ditahan (park)',
        ]);

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(PosKasir::class)
            ->call('resumeDitahan', $trx->id)
            ->assertSet('cart.'.$keySn.'.sn', true)
            ->assertSet('cart.'.$keyBiasa.'.sn', false)
            ->assertSet('cart.'.$keySn.'.sn_list', ['SN-PARK']); // sn_list payload tetap dipertahankan

        $this->assertEquals('dibatalkan', $trx->fresh()->status);
    }

    // ===== (l) [F2-3/P1] SN toggle di form produk — flag sn bisa diaktifkan via UI =====

    public function test_sn_toggle_form_produk_ada_dan_disimpan_ke_produk(): void
    {
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        // Toggle + helper text tampil di modal Tambah Produk
        $component = Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->assertSet('showProdukModal', true)
            ->assertSee('Serial Number (SN)')
            ->assertSee('Wajib input SN saat GRN/stok masuk & penjualan');

        // Guard: sn=true + stok_awal>0 ditolak — stok masuk wajib via "+ Stok" (ada input SN)
        $component->set('produkForm.nama', 'LCD SN Baru')
            ->set('produkForm.kategori', 'LCD')
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.harga_beli', 10000)
            ->set('produkForm.harga_jual_retail', 20000)
            ->set('produkForm.harga_tier.retail.nominal_tetap', 20000)
            ->set('produkForm.sn', true)
            ->set('produkForm.gudang_id', $this->gudang->id)
            ->set('produkForm.stok_awal', 5)
            ->call('simpanProduk')
            ->assertDispatched('alert');
        $this->assertSame(0, Produk::where('nama', 'LCD SN Baru')->count(), 'sn=true + stok_awal wajib ditolak');

        // Tanpa stok awal → tersimpan dengan sn=true
        $component->set('produkForm.stok_awal', 0)
            ->call('simpanProduk')
            ->assertSet('showProdukModal', false);

        $baru = Produk::where('nama', 'LCD SN Baru')->firstOrFail();
        $this->assertTrue((bool) $baru->sn, 'Flag sn wajib true setelah disimpan via UI');
    }

    // ===== (m) [F2-3/P1] Tambah stok produk sn=true — SN wajib, count = qty, persist tersedia =====

    public function test_tambah_stok_produk_sn_wajib_sn_count_sama_dan_persist_tersedia(): void
    {
        Queue::fake();
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(ProdukTab::class)
            ->call('openTambahStokModal', $this->produkSn->id)
            ->assertSet('stokProdukSn', true)
            ->set('tambahStokForm.gudang_id', $this->gudang->id)
            ->set('tambahStokForm.qty', 2)
            ->set('tambahStokForm.harga_beli', 50000)
            ->set('tambahStokForm.sn', "TSN-1\nTSN-2\nTSN-3"); // 3 ≠ 2

        // Count ≠ qty → ValidationException field-level, tanpa side effect apa pun
        $component->call('simpanTambahStok')
            ->assertHasErrors('tambahStokForm.sn')
            ->assertSet('showTambahStokModal', true);
        $this->assertSame(0, StokItem::count(), 'Gagal validasi → tanpa stok masuk');
        $this->assertSame(0, NomorSeri::count());
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(0, StokLog::count());

        // SN valid (parse koma via NomorSeriService::parseList) → sukses
        $component->set('tambahStokForm.sn', 'TSN-1,TSN-2')
            ->call('simpanTambahStok')
            ->assertHasNoErrors()
            ->assertSet('showTambahStokModal', false);

        $this->assertEquals(2, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah);
        $sns = NomorSeri::where('produk_id', $this->produkSn->id)->get();
        $this->assertCount(2, $sns);
        $this->assertEqualsCanonicalizing(['TSN-1', 'TSN-2'], $sns->pluck('nomor_seri')->all());
        foreach ($sns as $sn) {
            $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $sn->status, 'SN baru wajib status tersedia');
            $this->assertEquals((int) $this->cabang->id, (int) $sn->cabang_id, 'SN wajib ter-taut cabang');
        }

        // Jurnal pembelian tetap balance + StokLog tercatat
        $this->assertEquals(100000, (float) JurnalAkuntansi::sum('debit'));
        $this->assertEquals(100000, (float) JurnalAkuntansi::sum('kredit'));
        $this->assertTrue(
            StokLog::where('produk_id', $this->produkSn->id)->where('jenis', 'pembelian')->exists()
        );

        // SN sudah terdaftar → ditolak di dalam transaksi service, stok tidak berubah
        $component->call('openTambahStokModal', $this->produkSn->id)
            ->set('tambahStokForm.gudang_id', $this->gudang->id)
            ->set('tambahStokForm.qty', 1)
            ->set('tambahStokForm.harga_beli', 50000)
            ->set('tambahStokForm.sn', 'TSN-1')
            ->call('simpanTambahStok')
            ->assertSet('showTambahStokModal', true);
        $this->assertEquals(2, StokItem::where('produk_id', $this->produkSn->id)->first()->jumlah, 'Tanpa partial stok');
        $this->assertSame(2, NomorSeri::count());
        $this->assertEquals(100000, (float) JurnalAkuntansi::sum('debit'), 'Tanpa jurnal dobel');
    }

    // ===== (n) [F2-3/P1] Tambah stok produk sn=false — tanpa input SN, flow tetap jalan =====

    public function test_tambah_stok_produk_non_sn_tidak_diminta_sn(): void
    {
        Queue::fake();
        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(ProdukTab::class)
            ->call('openTambahStokModal', $this->produk->id)
            ->assertSet('stokProdukSn', false)
            ->assertDontSee('Nomor Seri (wajib)')
            ->set('tambahStokForm.gudang_id', $this->gudang->id)
            ->set('tambahStokForm.qty', 3)
            ->set('tambahStokForm.harga_beli', 50000)
            ->call('simpanTambahStok')
            ->assertSet('showTambahStokModal', false);

        $this->assertEquals(3, StokItem::where('produk_id', $this->produk->id)->first()->jumlah);
        $this->assertSame(0, NomorSeri::count(), 'Produk sn=false → tanpa baris SN');
        $this->assertSame(0, StokItem::where('produk_id', $this->produkSn->id)->count());
    }

    // ===== (o) [F2-3/P2] Activity log NomorSeri + NomorSeriEvent (create/status-change) =====

    public function test_activity_log_nomor_seri_dan_event_create_dan_status_change(): void
    {
        $this->actingAs($this->user, 'web');

        $ns = NomorSeri::create([
            'cabang_id' => $this->cabang->id,
            'produk_id' => $this->produkSn->id,
            'nomor_seri' => 'SN-LOG-1',
            'status' => NomorSeri::STATUS_TERSEDIA,
            'keterangan' => 'GRN TEST',
        ]);

        $created = AktivitasLog::where('subject_type', NomorSeri::class)
            ->where('subject_id', $ns->id)->where('event', 'created')->first();
        $this->assertNotNull($created, 'Create NomorSeri wajib ter-log');
        $this->assertEquals('Nomor Seri dibuat', $created->description);
        $this->assertEquals($this->user->id, (int) $created->causer_id);
        $this->assertEquals((int) $this->cabang->id, (int) $created->cabang_id, 'Log cabang-scoped');

        // Status change (klaim jual) → log updated + before/after status
        $ns->update(['status' => NomorSeri::STATUS_TERJUAL]);
        $updated = AktivitasLog::where('subject_type', NomorSeri::class)
            ->where('subject_id', $ns->id)->where('event', 'updated')->first();
        $this->assertNotNull($updated, 'Status-change NomorSeri wajib ter-log');
        $this->assertEquals('Nomor Seri diperbarui', $updated->description);
        $this->assertEquals(NomorSeri::STATUS_TERSEDIA, $updated->attribute_changes->get('old')['status']);
        $this->assertEquals(NomorSeri::STATUS_TERJUAL, $updated->attribute_changes->get('attributes')['status']);

        // Snapshot event append-only juga ter-log
        $ev = NomorSeriEvent::create([
            'nomor_seri_id' => $ns->id,
            'cabang_id' => $this->cabang->id,
            'aksi' => NomorSeriEvent::AKSI_KLAIM_JUAL,
            'status_sebelum' => NomorSeri::STATUS_TERSEDIA,
        ]);
        $evLog = AktivitasLog::where('subject_type', NomorSeriEvent::class)
            ->where('subject_id', $ev->id)->where('event', 'created')->first();
        $this->assertNotNull($evLog, 'Create NomorSeriEvent wajib ter-log');
        $this->assertEquals('Nomor Seri Event dibuat', $evLog->description);
    }
}
