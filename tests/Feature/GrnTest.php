<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\GrnTab;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Workflow\Models\ApprovalRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F2-2 — GRN (PRD-Advanced-UteParts baris 91).
 *
 * Qty sesuai → auto terima (jurnal AP + stok masuk); partial/tolak → draft +
 * approval F1-1 (rule 'grn'); setujui (inbox/hook) → jurnal AP + stok; idempoten.
 */
class GrnTest extends TestCase
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

        // COA wajib ada untuk JurnalService::post
        AkunCOA::firstOrCreate(
            ['kode' => '130-01'],
            ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']
        );
        AkunCOA::firstOrCreate(
            ['kode' => '210-01'],
            ['nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit']
        );

        $this->cabang = Cabang::create([
            'kode' => 'UTP-GRN',
            'nama' => 'Cabang GRN',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang GRN',
            'kode' => 'GDG-GRN',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin GRN',
            'email' => 'admin-grn@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Sparepart GRN',
            'slug' => Str::slug('Sparepart GRN').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create(['nama' => 'Supplier GRN', 'termin_hari' => 30]);
    }

    private function buatPoDikirim(int $qtyItem, float $harga): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-GRN-'.Str::random(6),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'kredit',
            'total' => $qtyItem * $harga,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
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
            'name' => 'Approver GRN',
            'email' => 'approver-grn@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $u->assignRole('admin-toko');

        return $u;
    }

    private function requestGrn(int $grnId): ApprovalRequest
    {
        return ApprovalRequest::where('entity_type', 'grn')
            ->where('entity_id', $grnId)
            ->firstOrFail();
    }

    public function test_qty_sesuai_auto_terima_jurnal_dan_stok_masuk(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000); // total 500.000

        $grn = $this->service()->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals('terima', $grn->status);

        // Jurnal AP: 130-01 debit = 210-01 kredit = total HPP
        $jurnal = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->get();
        $this->assertCount(2, $jurnal);
        $this->assertEquals(500000, (float) $jurnal->sum('debit'));
        $this->assertEquals(500000, (float) $jurnal->sum('kredit'));

        // Stok masuk — StokItem + StokLog jenis GRN + StockMutationLog
        $stok = StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->first();
        $this->assertNotNull($stok, 'GRN wajib membuat/menambah StokItem di gudang tujuan');
        $this->assertEquals(10, $stok->jumlah);

        $this->assertTrue(
            StokLog::where('produk_id', $this->produk->id)
                ->where('jenis', 'GRN')
                ->where('perubahan', 10)
                ->where('jumlah_sebelum', 0)
                ->where('jumlah_setelah', 10)
                ->exists(),
            'StokLog jenis GRN wajib tercatat dengan sebelum/setelah'
        );

        $this->assertTrue(
            StockMutationLog::where('produk_id', $this->produk->id)
                ->where('delta', 10)
                ->where('sumber', 'grn')
                ->exists()
        );

        // Tanpa approval
        $this->assertSame(0, ApprovalRequest::where('entity_type', 'grn')->count());
    }

    public function test_partial_buat_draft_dan_approval_request(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);

        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id);

        $this->assertEquals('draft', $grn->status, 'Selisih partial → status draft (menunggu approval)');

        $req = $this->requestGrn($grn->id);
        $this->assertEquals('pending', $req->status);
        $this->assertEquals('admin-toko', $req->approver_role);
        $this->assertEquals((int) $this->cabang->id, (int) $req->cabang_id);

        // Belum ada jurnal & stok sebelum approve
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
        $this->assertSame(0, StokLog::where('jenis', 'GRN')->count());
    }

    public function test_tolak_semua_tetap_diajukan_dan_amount_0(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);

        $grn = $this->service()->inputGudang($po, [$this->produk->id => 0], $this->user->id);

        $this->assertEquals('draft', $grn->status);
        $this->assertEquals(0, (float) $grn->total_hpp);
        $req = $this->requestGrn($grn->id);
        $this->assertEquals('pending', $req->status, 'Qty 0 (tolak semua) tetap wajib lewat approval F1-1');
    }

    public function test_approve_final_post_jurnal_stok_idempoten(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id);

        $req = $this->requestGrn($grn->id);
        $approver = $this->approver();

        // Approve via engine F1-1 → hook selesaikanEntity → jurnal AP + stok masuk
        $req->proses('approved', $approver->id, 'Selisih wajar');

        $this->assertEquals('terima', $grn->fresh()->status);
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
        $this->assertEquals(6, StokItem::where('produk_id', $this->produk->id)->first()->jumlah);
        $this->assertSame(1, StokLog::where('jenis', 'GRN')->count());

        // Idempoten: proses ulang → request sudah diproses
        try {
            $req->fresh()->proses('approved', $approver->id, 'Lagi');
            $this->fail('Proses ulang request wajib ditolak');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah diproses', $e->errors()['msg'][0] ?? '');
        }

        // Idempoten: setujuiGrn langsung setelah status terima → no-op, jurnal & stok tidak dobel
        $this->service()->setujuiGrn($grn->id, $approver->id);
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
        $this->assertSame(1, StokLog::where('jenis', 'GRN')->count());
        $this->assertEquals(6, StokItem::where('produk_id', $this->produk->id)->first()->jumlah);
    }

    public function test_reject_final_status_ditolak_tanpa_jurnal(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 4], $this->user->id);

        $req = $this->requestGrn($grn->id);
        $req->proses('rejected', $this->approver()->id, 'Tidak sesuai');

        $this->assertEquals('ditolak', $grn->fresh()->status);
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
        $this->assertSame(0, StokLog::where('jenis', 'GRN')->count());
        $this->assertSame(0, StokItem::where('produk_id', $this->produk->id)->count());
    }

    public function test_qty_diterima_melebihi_qty_po_ditolak(): void
    {
        $po = $this->buatPoDikirim(10, 50000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('melebihi qty PO');

        $this->service()->inputGudang($po, [$this->produk->id => 11], $this->user->id);
    }

    public function test_ui_tab_grn_tampil_dan_modal_bisa_dibuka(): void
    {
        Queue::fake();
        $this->actingAs($this->user, 'web');
        $po = $this->buatPoDikirim(5, 20000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 5], $this->user->id);

        // PO kedua masih 'dikirim' (belum ada GRN) — daftar hanya menampilkan PO dikirim
        $poAntre = $this->buatPoDikirim(3, 20000);

        Livewire::test(GrnTab::class)
            ->assertSee('GRN')
            ->assertSee('Input GRN')
            ->assertSee($grn->no_grn)
            ->assertSee('Diterima')
            ->call('openGrnModal', $poAntre->id)
            ->assertSet('showGrnModal', true)
            ->assertSee('Sparepart GRN');
    }

    // ===== Regression review P0-1 / P0-4 / P1-2 =====

    public function test_setujui_tolak_grn_tanpa_permission_approve_workflow_diblokir_403(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id);
        $req = $this->requestGrn($grn->id);

        // kasir punya wms.view tapi TIDAK punya approve-workflow
        $kasir = User::create([
            'name' => 'Kasir RBAC GRN',
            'email' => 'kasir-grn@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');
        $this->assertFalse($kasir->can('approve-workflow'));

        $this->actingAs($kasir, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        // Catatan: harness Livewire merender abort(403) jadi response 403
        // (vendor/livewire/.../SupportTesting/RequestBroker.php:29,134-137) sehingga tidak
        // propagate sbg exception ke test — kuncinya: tanpa izin, method wajib TERHENTI
        // sebelum memutasi GRN (sebelum fix: setujuiGrn/tolakGrn tanpa gate → GRN berubah).
        Livewire::test(GrnTab::class)->call('setujuiGrn', $grn->id);
        $this->assertEquals('draft', $grn->fresh()->status, 'setujuiGrn tanpa approve-workflow wajib diblokir (403)');
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
        $this->assertEquals('pending', $req->fresh()->status, 'ApprovalRequest tidak boleh diproses tanpa izin');

        Livewire::test(GrnTab::class)->call('tolakGrn', $grn->id, 'alasan paksa');
        $this->assertEquals('draft', $grn->fresh()->status, 'tolakGrn tanpa approve-workflow wajib diblokir (403)');
        $this->assertEquals('pending', $req->fresh()->status);
    }

    public function test_double_grn_ditolak_untuk_po_sama(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id); // draft

        $this->assertEquals('dikirim', $po->fresh()->status);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah memiliki GRN');

        $this->service()->inputGudang($po, [$this->produk->id => 5], $this->user->id);
    }

    public function test_po_bukan_status_dikirim_ditolak_input_grn(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $po->update(['status' => 'draft']);
        $po->refresh();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('berstatus dikirim');

        $this->service()->inputGudang($po, [$this->produk->id => 5], $this->user->id);
    }

    public function test_po_status_diterima_setelah_grn_finalisasi(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals('terima', $grn->status);
        $this->assertEquals('diterima', $po->fresh()->status, 'PO wajib berubah → diterima saat finalisasi GRN');
    }

    public function test_tab_setujui_menyelesaikan_pending_approval_request(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id);
        $req = $this->requestGrn($grn->id);
        $this->assertEquals('pending', $req->status);

        // Admin-toko (approver_role rule 'grn', punya approve-workflow) approve via tab
        $this->actingAs($this->approver(), 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(GrnTab::class)->call('setujuiGrn', $grn->id);

        $this->assertEquals('disetujui', $req->fresh()->status, 'Approve tab wajib menyelesaikan ApprovalRequest pending');
        $this->assertEquals('terima', $grn->fresh()->status);
        $this->assertEquals('diterima', $po->fresh()->status);
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
        $this->assertEquals(6, StokItem::where('produk_id', $this->produk->id)->first()->jumlah);
    }

    public function test_tab_tolak_menyelesaikan_pending_approval_request(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id);
        $req = $this->requestGrn($grn->id);

        $this->actingAs($this->approver(), 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(GrnTab::class)->call('tolakGrn', $grn->id, 'Barang rusak');

        $this->assertEquals('ditolak', $req->fresh()->status, 'Tolak tab wajib menyelesaikan ApprovalRequest pending');
        $this->assertEquals('ditolak', $grn->fresh()->status);
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
    }

    public function test_terima_po_langsung_ditolak_wajib_lewat_grn(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $this->actingAs($this->user, 'web');

        Livewire::test(PoTab::class)
            ->call('terimaPo', $po->id)
            ->assertDispatched('alert');

        $this->assertEquals('dikirim', $po->fresh()->status, 'PO tidak boleh berubah lewat jalur legacy');
        $this->assertSame(0, StokLog::count(), 'Tidak boleh ada stok masuk tanpa GRN');
        $this->assertSame(0, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal tanpa GRN');
    }

    public function test_route_wms_butuh_permission_wms_view(): void
    {
        $teknisi = User::create([
            'name' => 'Teknisi RBAC WMS',
            'email' => 'teknisi-wms@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $teknisi->assignRole('teknisi'); // tidak punya wms.view

        $this->actingAs($teknisi, 'web')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/wms')
            ->assertForbidden();
    }
}
