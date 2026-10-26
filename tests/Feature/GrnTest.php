<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\GrnTab;
use App\Modules\Wms\Livewire\HistoriPengadaanTab;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ReturnPembelianTab;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\RiwayatPerubahanHarga;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ProcurementService;
use App\Modules\Wms\Services\PurchaseOrderService;
use App\Modules\Wms\Services\ReturnPembelianService;
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
        // 110-01 Kas — dipakai jurnal bayarPO (Utang debit / Kas kredit)
        AkunCOA::firstOrCreate(
            ['kode' => '110-01'],
            ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']
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
        $this->assertSame(0, Utang::count(), 'GRN ditolak tanpa finalisasi tidak boleh membuat Utang');
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

    // ===== Regression P1: API PUT /wms/po/{id}/status tidak boleh bypass GRN (F2-2) =====

    public function test_api_po_diterima_tanpa_grn_ditolak_422(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        $resp = $this->putJson("/api/wms/po/{$po->id}/status", ['action' => 'diterima']);
        $resp->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('GRN', $resp->json('message') ?? '');

        // Tidak ada stok, mutation, atau jurnal yang dibuat
        $this->assertEquals('dikirim', $po->fresh()->status, 'PO tetap dikirim — penerimaan langsung via API ditutup');
        $this->assertSame(0, StokLog::count(), 'Tidak boleh ada stok masuk tanpa GRN');
        $this->assertSame(0, StockMutationLog::count(), 'Tidak boleh ada mutasi stok tanpa GRN');
        $this->assertSame(0, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal tanpa GRN');
        $this->assertSame(0, StokItem::where('produk_id', $this->produk->id)->count());
    }

    public function test_terima_barang_service_dikirim_tanpa_grn_ditolak(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);

        // Guard di service — pertahanan kalau endpoint dipanggil lagi di masa depan
        try {
            app(PurchaseOrderService::class)->terimaBarang($po, $this->user->id);
            $this->fail('terimaBarang utk PO dikirim wajib ditolak — penerimaan wajib lewat GRN');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('GRN', $e->errors()['msg'][0] ?? '');
        }

        $this->assertEquals('dikirim', $po->fresh()->status);
        $this->assertSame(0, StokLog::count(), 'Tidak boleh ada stok masuk tanpa GRN');
        $this->assertSame(0, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal tanpa GRN');
    }

    public function test_api_po_status_ops_lain_tetap_jalan_dan_grn_selesai_diterima(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $po->update(['status' => 'draft']);

        $this->actingAs($this->user, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        // Route tetap ada utk transisi lain: draft → dikirim
        $this->putJson("/api/wms/po/{$po->id}/status", ['action' => 'dikirim'])
            ->assertSuccessful();
        $this->assertEquals('dikirim', $po->fresh()->status);

        // GRN flow tetap menyelesaikan PO → diterima normal
        $grn = $this->service()->inputGudang($po->fresh(), [$this->produk->id => 10], $this->user->id);
        $this->assertEquals('terima', $grn->status);
        $this->assertEquals('diterima', $po->fresh()->status, 'PO wajib berubah → diterima via GRN');

        // Terima ulang via API → transisi tidak valid (sudah diterima), tidak dobel
        $this->putJson("/api/wms/po/{$po->id}/status", ['action' => 'diterima'])
            ->assertStatus(422);
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count(), 'Jurnal GRN tidak dobel');
        $this->assertEquals(10, StokItem::where('produk_id', $this->produk->id)->value('jumlah'), 'Stok tidak dobel');

        // Route tetap bisa dibatalkan (operasi status lain tidak terpengaruh)
        $po2 = $this->buatPoDikirim(5, 20000);
        $this->putJson("/api/wms/po/{$po2->id}/status", ['action' => 'dibatalkan'])
            ->assertSuccessful();
        $this->assertEquals('dibatalkan', $po2->fresh()->status);
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

    // ===== Subledger Utang (AP) dari finalisasi GRN =====

    public function test_auto_finalisasi_membuat_satu_utang_sesuai_jurnal_ap(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000); // total 500.000

        $grn = $this->service()->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals('terima', $grn->status);

        // Tepat SATU baris Utang — kunci per PO (kunci yang sama dibaca bayarPO)
        $rows = Utang::where('referensi_tipe', PurchaseOrder::class)
            ->where('referensi_id', $po->id)
            ->get();
        $this->assertCount(1, $rows, 'Finalisasi GRN wajib membuat 1 baris Utang subledger per PO');
        $this->assertSame(1, Utang::count(), 'Tidak boleh ada baris Utang ganda');

        $utang = $rows->first();

        // Jumlah == jurnal AP yang sudah diposting (210-01 kredit) — tanpa jurnal kedua
        $apKredit = (float) JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->sum('kredit');
        $this->assertEquals(500000, $apKredit);
        $this->assertEquals($apKredit, (float) $utang->jumlah, 'Jumlah Utang wajib sama dgn jurnal AP GRN');

        // Scoping cabang + field dasar (pola terimaBarang)
        $this->assertEquals((int) $this->cabang->id, (int) $utang->cabang_id, 'Utang wajib stamp cabang_id GRN');
        $this->assertEquals('belum_lunas', $utang->status);
        $this->assertEquals(0, (float) $utang->jumlah_dibayar);
        $this->assertEquals($this->supplier->nama, $utang->kreditor_nama);
        $this->assertNotEmpty($utang->no_utang);
    }

    public function test_finalisasi_approval_utang_idempoten_dobel_panggil(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id); // partial → draft

        $this->assertSame(0, Utang::count(), 'GRN belum final → belum ada Utang');

        $req = $this->requestGrn($grn->id);
        $approver = $this->approver();
        $req->proses('approved', $approver->id, 'Selisih wajar');

        $this->assertEquals('terima', $grn->fresh()->status);
        $this->assertSame(1, Utang::count(), 'Approve finalisasi → tepat 1 Utang');

        $utang = Utang::first();
        $apKredit = (float) JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->sum('kredit');
        $this->assertEquals(6 * 50000, $apKredit);
        $this->assertEquals($apKredit, (float) $utang->jumlah, 'Jumlah Utang = qty diterima × harga beli = jurnal AP');
        $this->assertEquals((int) $this->cabang->id, (int) $utang->cabang_id);

        // Dobel finalize (auto path + approve path / inbox + tab) → idempoten:
        // tetap 1 baris Utang, jurnal tetap 2 baris
        $this->service()->setujuiGrn($grn->id, $approver->id);
        $this->service()->setujuiGrn($grn->id, $approver->id);
        $this->assertSame(1, Utang::count(), 'Finalisasi ganda tidak boleh menduplikasi Utang');
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
    }

    public function test_bayar_po_setelah_grn_mengurangi_utang_subledger(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000); // total 500.000
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)
            ->where('referensi_id', $po->id)
            ->firstOrFail();
        $this->assertEquals(500000, (float) $utang->jumlah);
        $this->assertEquals('belum_lunas', $utang->status);

        $service = app(PurchaseOrderService::class);

        // Bayar parsial 200.000 → sebagian
        $service->bayarPO($po->fresh(), 200000, $this->user->id);
        $utang->refresh();
        $this->assertEquals(200000, (float) $utang->jumlah_dibayar, 'Pembayaran PO wajib mengurangi baris Utang dari GRN');
        $this->assertEquals('sebagian', $utang->status);
        $this->assertEquals(300000, $utang->sisa);
        $this->assertSame(1, Utang::count(), 'Pembayaran tidak boleh membuat baris Utang baru');

        // Bayar sisa 300.000 → lunas
        $service->bayarPO($po->fresh(), 300000, $this->user->id);
        $utang->refresh();
        $this->assertEquals(500000, (float) $utang->jumlah_dibayar);
        $this->assertEquals('lunas', $utang->status);
        $this->assertEquals(0, $utang->sisa);
        $this->assertSame(1, Utang::count());

        // Jurnal bayar tetap ada: 210-01 debit total 500.000 (Utang debit / Kas kredit)
        $akunUtang = AkunCOA::where('kode', '210-01')->firstOrFail();
        $debitUtang = (float) JurnalAkuntansi::where('akun_coa_id', $akunUtang->id)->sum('debit');
        $this->assertEqualsWithDelta(500000, $debitUtang, 0.01, 'Jurnal pembayaran 210-01 debit wajib tercatat');

        // Jurnal GRN tetap hanya 2 baris (tidak dobel / tidak ada jurnal kedua)
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count());
    }

    // ===== [B-10b/P1-6] Row lock GRN/PO + referensi jurnal + cabang_id Utang =====

    /** PO draft (belum dikirim) → jalur legacy terimaBarang boleh jalan. */
    private function buatPoDraft(int $qtyItem, float $harga, string $metodeBayar = 'kredit'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-DRAFT-'.Str::random(6),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'draft',
            'metode_bayar' => $metodeBayar,
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

    /**
     * Jurnal penerimaan PO & jurnal pembayaran PO harus bisa ditelusuri balik ke
     * PO (referensi_tipe/referensi_id) — tanpa ini rekonsiliasi AP & drill-down
     * audit tidak bisa jalan.
     */
    public function test_jurnal_terima_dan_bayar_po_di_stamp_referensi_purchase_order(): void
    {
        Queue::fake();
        $po = $this->buatPoDraft(10, 50000, 'kredit'); // total 500.000

        app(PurchaseOrderService::class)->terimaBarang($po, $this->user->id);

        // Jurnal penerimaan (130-01 debit / 210-01 kredit) ter-stamp referensi PO
        $jurnalTerima = JurnalAkuntansi::where('sumber', 'pembelian')->get();
        $this->assertCount(2, $jurnalTerima);
        foreach ($jurnalTerima as $baris) {
            $this->assertEquals(PurchaseOrder::class, $baris->referensi_tipe);
            $this->assertEquals($po->id, (int) $baris->referensi_id);
        }

        // Utang subledger WAJIB punya cabang_id (scoping Laporan Utang per cabang)
        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)
            ->where('referensi_id', $po->id)->firstOrFail();
        $this->assertEquals(
            (int) $this->cabang->id,
            (int) $utang->cabang_id,
            'Utang dari PurchaseOrderService wajib stamp cabang_id gudang tujuan PO'
        );
        $this->assertEquals(500000, (float) $utang->jumlah);

        // Jurnal pembayaran (210-01 debit / 110-01 kredit) juga ter-stamp referensi PO
        app(PurchaseOrderService::class)->bayarPO($po->fresh(), 200000, $this->user->id);

        $jurnalBayar = JurnalAkuntansi::where('sumber', 'manual')->get();
        $this->assertCount(2, $jurnalBayar);
        foreach ($jurnalBayar as $baris) {
            $this->assertEquals(PurchaseOrder::class, $baris->referensi_tipe);
            $this->assertEquals($po->id, (int) $baris->referensi_id);
        }
    }

    /**
     * [B-10b/P1-6] GrnService mengunci baris GRN dgn lockForUpdate di dalam
     * transaksi → approve/finalize bersamaan (tab + inbox) tidak menggandakan
     * jurnal, stok, maupun subledger Utang. Dip modeling sequential pada kode
     * yang sama: panggilan kedua membaca status 'terima' di bawah lock.
     */
    public function test_finalisasi_grn_ganda_tetap_satu_jurnal_satu_stok_satu_utang(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 6], $this->user->id); // partial → draft

        $req = $this->requestGrn($grn->id);
        $approver = $this->approver();

        // 3 jalur finalisasi "bersamaan" (inbox approve + tab approve + auto path)
        $req->proses('approved', $approver->id, 'Selisih wajar');
        $this->service()->setujuiGrn($grn->id, $approver->id);
        $this->service()->setujuiGrn($grn->id, $approver->id);

        $this->assertEquals('terima', $grn->fresh()->status);
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count(), 'Jurnal tidak boleh dobel');
        $this->assertSame(1, Utang::count(), 'Subledger Utang tidak boleh dobel');
        $this->assertEquals(
            6,
            (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah'),
            'Stok masuk tidak boleh dobel'
        );
        $this->assertSame(1, StockMutationLog::where('referensi_tipe', Grn::class)->count());
        $this->assertSame(1, StokLog::where('jenis', 'GRN')->count());
    }

    public function test_grn_item_qty_received_menyimpan_produk_nama(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(5, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 5], $this->user->id);

        $this->assertNotEmpty($grn->item_qty_received);
        $this->assertEquals($this->produk->nama, $grn->item_qty_received[0]['produk_nama']);
    }

    public function test_procurement_service_histori_pembelian_mencatat_selisih_dan_indikasi_perubahan_harga(): void
    {
        $po1 = PurchaseOrder::create([
            'no_po' => 'PO-HIST-1',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'diterima',
            'metode_bayar' => 'kredit',
            'total' => 100000,
            'created_at' => now()->subDays(5),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'produk_id' => $this->produk->id,
            'harga_beli' => 10000,
            'jumlah' => 10,
            'subtotal' => 100000,
        ]);

        $po2 = PurchaseOrder::create([
            'no_po' => 'PO-HIST-2',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'diterima',
            'metode_bayar' => 'kredit',
            'total' => 120000,
            'created_at' => now()->subDays(1),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po2->id,
            'produk_id' => $this->produk->id,
            'harga_beli' => 12000,
            'jumlah' => 10,
            'subtotal' => 120000,
        ]);

        $histori = app(ProcurementService::class)->getHistoriPembelian($this->produk->id);

        $this->assertCount(2, $histori['items']);
        // Item 0 is latest (po2), compared to po1
        $this->assertEquals('PO-HIST-2', $histori['items'][0]['no_po']);
        $this->assertEquals(2000.0, $histori['items'][0]['selisih']);
        $this->assertEquals(20.0, $histori['items'][0]['persentase']);
        $this->assertEquals('naik', $histori['items'][0]['trend']);
        $this->assertNotNull($histori['items'][0]['created_at']);

        // Item 1 is oldest (po1), trend awal
        $this->assertEquals('PO-HIST-1', $histori['items'][1]['no_po']);
        $this->assertEquals(0.0, $histori['items'][1]['selisih']);
        $this->assertEquals('awal', $histori['items'][1]['trend']);
    }

    public function test_grn_perubahan_harga_mencatat_riwayat_perubahan_harga(): void
    {
        Queue::fake();
        // Master produk harga beli = 40000
        $this->produk->update(['harga_beli' => 40000]);

        // PO dengan harga 50000 (berbeda dari master produk)
        $po = $this->buatPoDikirim(5, 50000);
        $this->service()->inputGudang($po, [$this->produk->id => 5], $this->user->id);

        $riwayat = RiwayatPerubahanHarga::where('produk_id', $this->produk->id)->first();
        $this->assertNotNull($riwayat);
        $this->assertEquals('grn', $riwayat->sumber);
        $this->assertEquals(50000, (float) $riwayat->harga_baru);
        $this->assertEquals(40000, (float) $riwayat->harga_lama);
        $this->assertEquals(10000, (float) $riwayat->selisih);
    }

    public function test_return_pembelian_tab_bisa_menyetujui_status_draft(): void
    {
        Queue::fake();
        $po = PurchaseOrder::create([
            'no_po' => 'PO-RETUR-1',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'diterima',
            'metode_bayar' => 'kredit',
            'total' => 200000,
        ]);
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'harga_beli' => 20000,
            'jumlah' => 10,
            'subtotal' => 200000,
        ]);
        StokItem::updateOrCreate(
            ['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id],
            ['jumlah' => 10, 'cabang_id' => $this->cabang->id]
        );

        $retur = ReturnPembelian::create([
            'no_return' => 'RET-BELI-001',
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'status' => 'draft',
            'tanggal' => now(),
            'metode_pengembalian' => 'saldo',
            'jumlah' => 40000,
            'user_id' => $this->user->id,
        ]);
        $retur->items()->create([
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
            'harga_beli' => 20000,
            'subtotal' => 40000,
        ]);

        session(['cabang_id' => $this->cabang->id]);
        $approver = $this->approver();
        $this->actingAs($approver);

        Livewire::test(ReturnPembelianTab::class)
            ->call('setujuiRetur', $retur->id)
            ->assertDispatched('alert', function ($event, $params) {
                return ($params[0]['type'] ?? $params['type'] ?? null) === 'success';
            });

        $this->assertEquals('selesai', $retur->fresh()->status);
        $this->assertEquals(8, (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah'));
    }

    public function test_grn_penerimaan_barang_dengan_produk_bermargin_dan_harga_tier(): void
    {
        Queue::fake();

        // Setup produk dengan margin dan harga tier retail
        $this->produk->update([
            'harga_beli' => 10000,
            'margin_persen' => 20,
            'harga_jual_retail' => 12000,
        ]);

        HargaTier::create([
            'produk_id' => $this->produk->id,
            'tipe_konsumen' => 'retail',
            'nominal_tetap' => 12000,
            'harga' => 12000,
        ]);

        // PO dengan harga baru 15.000
        $po = $this->buatPoDikirim(10, 15000);

        // Penerimaan barang GRN yang mentrigger sesuaikanHargaBeliAverage
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals('terima', $grn->status);
        $this->assertEquals(15000, (float) $this->produk->fresh()->harga_beli);
        // Harga jual retail = 15.000 * 1.20 = 18.000
        $this->assertEquals(18000, (float) $this->produk->fresh()->harga_jual_retail);

        $tier = HargaTier::where('produk_id', $this->produk->id)->where('tipe_konsumen', 'retail')->first();
        $this->assertNotNull($tier);
        $this->assertEquals(18000, (float) $tier->nominal_tetap);
    }

    public function test_buka_dan_konfirmasi_tolak_grn_via_modal_dialog(): void
    {
        Queue::fake();
        $po = $this->buatPoDikirim(10, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 5], $this->user->id);

        $this->actingAs($this->approver(), 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        Livewire::test(GrnTab::class)
            ->call('bukaKonfirmasiTolak', $grn->id)
            ->assertSet('showTolakDialog', true)
            ->assertSet('tolakGrnId', $grn->id)
            ->set('alasanTolak', 'Barang rusak kemasan pecah total')
            ->call('konfirmasiTolakGrn')
            ->assertSet('showTolakDialog', false);

        $this->assertEquals('ditolak', $grn->fresh()->status);
        $this->assertStringContainsString('Barang rusak kemasan pecah total', (string) $grn->fresh()->catatan);
        $this->assertSame(0, Utang::count(), 'GRN ditolak tidak boleh membuat baris Utang');
        $this->assertSame(0, (int) StokItem::where('produk_id', $this->produk->id)->sum('jumlah'));
    }

    public function test_alur_end_to_end_po_grn_dan_retur_pembelian_lengkap(): void
    {
        Queue::fake();

        // 1. PO dibuat dan dikirim
        $po = $this->buatPoDikirim(10, 50000);
        $poItem = $po->items->first();

        // 2. Penerimaan GRN (10 unit lengkap)
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 10], $this->user->id);
        $this->assertEquals('terima', $grn->status);
        $this->assertEquals('diterima', $po->fresh()->status);

        // Verifikasi Stok bertambah 10
        $stokAwal = (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertEquals(10, $stokAwal);

        // Verifikasi Jurnal AP: Debit Persediaan 130-01 (500.000), Kredit Utang 210-01 (500.000)
        $debitPersediaan = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)
            ->whereHas('akun', fn ($q) => $q->where('kode', '130-01'))
            ->value('debit');
        $this->assertEquals(500000, (float) $debitPersediaan);

        $kreditUtang = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)
            ->whereHas('akun', fn ($q) => $q->where('kode', '210-01'))
            ->value('kredit');
        $this->assertEquals(500000, (float) $kreditUtang);

        // Verifikasi Subledger Utang AP
        $utangPo = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->first();
        $this->assertNotNull($utangPo);
        $this->assertEquals(500000, (float) $utangPo->jumlah);

        // 3. Retur Pembelian: 3 unit dikembalikan ke supplier karena cacat
        $returService = app(ReturnPembelianService::class);
        $retur = $returService->buatRetur(
            $po->fresh(),
            [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'jumlah' => 3,
                ],
            ],
            'Barang cacat pabrik 3 unit',
            'utang',
            $this->user->id
        );

        $this->assertEquals('selesai', $retur->fresh()->status);

        // Verifikasi Stok berkurang 3 (10 - 3 = 7)
        $stokAkhir = (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertEquals(7, $stokAkhir);

        // Verifikasi Jurnal Retur: Debit Utang 210-01 (150.000), Kredit Persediaan 130-01 (150.000)
        $debitUtangRetur = JurnalAkuntansi::where('no_jurnal', $retur->no_return)
            ->whereHas('akun', fn ($q) => $q->where('kode', '210-01'))
            ->value('debit');
        $this->assertEquals(150000, (float) $debitUtangRetur);

        $kreditPersediaanRetur = JurnalAkuntansi::where('no_jurnal', $retur->no_return)
            ->whereHas('akun', fn ($q) => $q->where('kode', '130-01'))
            ->value('kredit');
        $this->assertEquals(150000, (float) $kreditPersediaanRetur);

        // Verifikasi Saldo Utang Terpotong (500.000 - 150.000 = 350.000)
        $this->assertEquals(350000, (float) $utangPo->fresh()->jumlah);
        // Verifikasi Total PO berkurang sesuai retur utang
        $this->assertEquals(350000, (float) $po->fresh()->total);
    }

    public function test_histori_pengadaan_tab_menampilkan_data_dan_bisa_export_csv(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);

        $po = $this->buatPoDikirim(5, 25000);

        Livewire::test(HistoriPengadaanTab::class)
            ->assertSee($po->no_po)
            ->assertSee($this->produk->nama)
            ->set('search', $po->no_po)
            ->assertSee($po->no_po)
            ->call('bukaDetailProduk', $this->produk->id)
            ->assertSet('showProdukModal', true)
            ->call('tutupDetailProduk')
            ->assertSet('showProdukModal', false)
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_generate_no_grn_mencegah_duplicate_key_saat_penerimaan_berulang(): void
    {
        Queue::fake();

        $po1 = $this->buatPoDikirim(5, 20000);
        $po2 = $this->buatPoDikirim(8, 12000);

        $grn1 = $this->service()->inputGudang($po1, [$this->produk->id => 5], $this->user->id);
        $grn2 = $this->service()->inputGudang($po2, [$this->produk->id => 8], $this->user->id);

        $this->assertNotEmpty($grn1->no_grn);
        $this->assertNotEmpty($grn2->no_grn);
        $this->assertNotEquals($grn1->no_grn, $grn2->no_grn);
        $this->assertDatabaseHas('grn', ['id' => $grn1->id, 'no_grn' => $grn1->no_grn]);
        $this->assertDatabaseHas('grn', ['id' => $grn2->id, 'no_grn' => $grn2->no_grn]);
    }

    public function test_po_tunai_tanpa_jatuh_tempo_dan_grn_mencatat_konfirmasi_kas_serta_pembayar(): void
    {
        Queue::fake();

        // Siapkan akun kas laci 110-04
        AkunCOA::firstOrCreate(
            ['kode' => '110-04'],
            ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']
        );

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);

        // Buat PO Tunai
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-CASH-001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'tunai',
            'akun_kas_bank' => '110-01',
            'jatuh_tempo' => null,
            'total' => 100000,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
            'harga_beli' => 50000,
            'subtotal' => 100000,
        ]);

        $this->assertNull($po->jatuh_tempo, 'PO tunai tidak boleh memiliki jatuh tempo');

        // Terima barang via GRN dengan konfirmasi bayar dari Kas Laci otomatis oleh user aktif
        $grn = $this->service()->inputGudang(
            $po,
            [$this->produk->id => 2],
            $this->user->id,
            [],
            [],
            [],
            '110-04'
        );

        $this->assertEquals('terima', $grn->status);
        $this->assertEquals('110-04', $grn->fresh()->akun_kas_bank);
        $this->assertEquals($this->user->name, $grn->fresh()->dibayar_oleh, 'Nama pembayar harus otomatis dari user aktif');

        // Verifikasi PO terupdate lunas dan akun kas tersinkron
        $poFresh = $po->fresh();
        $this->assertEquals('diterima', $poFresh->status);
        $this->assertEquals(100000, (float) $poFresh->total_dibayar);
        $this->assertEquals('110-04', $poFresh->akun_kas_bank);
        $this->assertNull($poFresh->jatuh_tempo);

        // Verifikasi tidak ada utang yang dibuat untuk PO tunai
        $this->assertEquals(0, Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->count());

        // Verifikasi jurnal: Debit Persediaan 130-01, Kredit Kas Laci 110-04
        $jurnalKas = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)
            ->whereHas('akun', fn ($q) => $q->where('kode', '110-04'))
            ->first();
        $this->assertNotNull($jurnalKas, 'Jurnal harus mengkredit Kas Laci 110-04');
        $this->assertEquals(100000, (float) $jurnalKas->kredit);

        $jurnalPersediaan = JurnalAkuntansi::where('no_jurnal', $grn->no_grn)
            ->whereHas('akun', fn ($q) => $q->where('kode', '130-01'))
            ->first();
        $this->assertNotNull($jurnalPersediaan);
        $this->assertEquals(100000, (float) $jurnalPersediaan->debit);
    }

    public function test_staff_gudang_tanpa_izin_kas_ditolak_menerima_po_tunai(): void
    {
        Queue::fake();

        // Buat user yang hanya memiliki role staff-gudang (tanpa izin kas / pos / finance)
        $staffGudang = User::create([
            'name' => 'Staf Gudang Murni',
            'email' => 'staff-gudang@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $staffGudang->assignRole('staff-gudang');
        $staffGudang->cabangs()->attach($this->cabang->id);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-REJECT-001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'tunai',
            'akun_kas_bank' => '110-01',
            'jatuh_tempo' => null,
            'total' => 50000,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 50000,
            'subtotal' => 50000,
        ]);

        // Cek via GrnService::inputGudang harus melempar Exception otorisasi kas
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak memiliki role/hak akses untuk mengotorisasi transaksi pembayaran kas tunai PO');

        $this->service()->inputGudang(
            $po,
            [$this->produk->id => 1],
            $staffGudang->id
        );
    }

    public function test_livewire_grn_tab_menolak_staff_gudang_menerima_po_tunai(): void
    {
        $staffGudang = User::create([
            'name' => 'Staf Gudang Komponen',
            'email' => 'staff-komponen@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $staffGudang->assignRole('staff-gudang');
        $staffGudang->cabangs()->attach($this->cabang->id);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-REJECT-002',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'tunai',
            'akun_kas_bank' => '110-01',
            'jatuh_tempo' => null,
            'total' => 50000,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 50000,
            'subtotal' => 50000,
        ]);

        $this->actingAs($staffGudang);
        session(['cabang_id' => $this->cabang->id]);

        Livewire::test(GrnTab::class)
            ->call('openGrnModal', $po->id)
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'error'
                    && str_contains($payload['message'], 'tidak memiliki role/hak akses untuk mengotorisasi pembayaran kas');
            })
            ->assertSet('showGrnModal', false);
    }

    public function test_wms_print_documents_return_200_with_a5_default(): void
    {
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $po = $this->buatPoDikirim(2, 50000);
        $grn = $this->service()->inputGudang($po, [$this->produk->id => 2], $this->user->id);

        // 1. Cetak PO Fisik
        $responsePo = $this->get(route('wms.po.print', $po->id));
        $responsePo->assertOk();
        $responsePo->assertSee('Purchase Order');
        $responsePo->assertSee($po->no_po);
        $responsePo->assertSee('A5 (Setengah A4)');

        // 2. Cetak Berita Acara Penolakan
        $responsePenolakan = $this->get(route('wms.po.print-penolakan', $po->id));
        $responsePenolakan->assertOk();
        $responsePenolakan->assertSee('Berita Acara Penolakan');

        // 3. Cetak Bukti GRN
        $responseGrn = $this->get(route('wms.grn.print', $grn->id));
        $responseGrn->assertOk();
        $responseGrn->assertSee('Bukti Terima Barang');
        $responseGrn->assertSee($grn->no_grn);
        $responseGrn->assertSee('A5 (Setengah A4)');
    }

    public function test_grn_otomatis_mengisi_rak_default_produk_dari_stok_atau_gudang(): void
    {
        Queue::fake();

        $rakDefault = Rak::create([
            'gudang_id' => $this->gudang->id,
            'kode' => 'RAK-A1',
            'nama' => 'Rak Sparepart Utama',
            'is_active' => true,
        ]);

        // Simpan stok awal dengan rak_id
        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 5,
            'cabang_id' => $this->cabang->id,
            'rak_id' => $rakDefault->id,
        ]);

        $po = $this->buatPoDikirim(10, 50000);

        // 1. Verifikasi modal Livewire GrnTab otomatis memuat rak default produk
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $lw = Livewire::test(GrnTab::class)
            ->call('openGrnModal', $po->id)
            ->assertSet('showGrnModal', true);

        $form = $lw->get('grnForm');
        $this->assertNotEmpty($form['item_qty']);
        $this->assertEquals($rakDefault->id, $form['item_qty'][0]['rak_id']);

        // 2. Verifikasi GrnService::inputGudang fallback ke rak default jika itemRakId kosong
        $grn = $this->service()->inputGudang(
            $po,
            [$this->produk->id => 10],
            $this->user->id
        );

        $this->assertEquals('terima', $grn->status);
        $this->assertEquals($rakDefault->id, $grn->item_qty_received[0]['rak_id']);

        $stokFresh = StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->first();
        $this->assertEquals(15, (int) $stokFresh->jumlah);
        $this->assertEquals($rakDefault->id, $stokFresh->rak_id);
    }

    public function test_grn_input_gudang_bisa_memindahkan_rak_secara_manual(): void
    {
        Queue::fake();

        $rakLama = Rak::create([
            'gudang_id' => $this->gudang->id,
            'kode' => 'RAK-LAMA',
            'nama' => 'Rak Lama',
            'is_active' => true,
        ]);

        $rakBaru = Rak::create([
            'gudang_id' => $this->gudang->id,
            'kode' => 'RAK-BARU',
            'nama' => 'Rak Baru',
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
            'cabang_id' => $this->cabang->id,
            'rak_id' => $rakLama->id,
        ]);

        $po = $this->buatPoDikirim(5, 50000);

        // Petugas secara manual memilih $rakBaru->id
        $grn = $this->service()->inputGudang(
            $po,
            [$this->produk->id => 5],
            $this->user->id,
            [],
            [],
            [$this->produk->id => $rakBaru->id]
        );

        $this->assertEquals('terima', $grn->status);
        $this->assertEquals($rakBaru->id, $grn->item_qty_received[0]['rak_id']);

        $stokFresh = StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->first();
        $this->assertEquals(7, (int) $stokFresh->jumlah);
        $this->assertEquals($rakBaru->id, $stokFresh->rak_id);
    }
}
