<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Workflow\Livewire\ApprovalInbox;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie permissions & roles
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'approve-workflow', 'guard_name' => 'web']);
        Role::where('name', 'super-admin')->first()->givePermissionTo('approve-workflow');
        Role::where('name', 'finance')->first()->givePermissionTo('approve-workflow');
        Role::where('name', 'admin-toko')->first()->givePermissionTo('approve-workflow');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createCabang(?string $kode = null): Cabang
    {
        return Cabang::create([
            'kode' => $kode ?? 'UTP'.Str::random(3),
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);
    }

    protected function createUser(string $roleName): User
    {
        $user = User::create([
            'name' => 'Test '.ucfirst($roleName).Str::random(4),
            'email' => $roleName.Str::random(4).'@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    // ===== Rule matching =====

    public function test_rule_match_by_amount_range_cabang(): void
    {
        $cabang = $this->createCabang('UTP001');
        $kasir = $this->createUser('kasir');

        // Buat rule untuk cabang ini: min 10 juta, approver finance
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        // Jumlah di atas threshold → harus ada approval
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals('finance', $request->approver_role);

        // Jumlah di bawah threshold → tidak perlu approval
        $request2 = $service->ajukan('po', 5000000, (string) $cabang->id, ['amount' => 5000000, 'no_po' => 'PO-002'], $kasir->id);
        $this->assertNull($request2);
    }

    public function test_rule_match_by_amount_range_global(): void
    {
        $kasir = $this->createUser('kasir');
        $cabangId = null;

        // Rule global retur min 5 juta sudah di-seed migrasi; tambah duplikat verifikasi
        ApprovalRule::create([
            'cabang_id' => null,
            'entity_type' => 'retur',
            'min_amount' => 5000000,
            'max_amount' => null,
            'approver_role' => 'super-admin',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request = $service->ajukan('retur', 7000000, $cabangId, ['amount' => 7000000, 'no_retur' => 'RET-001'], $kasir->id);
        $this->assertNotNull($request);
        $this->assertEquals('super-admin', $request->approver_role);
    }

    public function test_rule_global_cabang_scoped_entity_tetap_match(): void
    {
        // Entity dengan cabang_id (mis. PO) harus tetap match rule GLOBAL (cabang_id null)
        $cabang = $this->createCabang('UTPGLB');
        $kasir = $this->createUser('kasir');

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 90001, (string) $cabang->id, ['amount' => 12000000, 'no_po' => 'PO-GLB'], $kasir->id);

        $this->assertNotNull($request, 'Rule global wajib match untuk entity bercabang');
        $this->assertNull($request->rule->cabang_id);
        $this->assertEquals((int) $cabang->id, (int) $request->cabang_id);
    }

    public function test_ajukan_idempotent(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request1 = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request1);
        $idPertama = $request1->id;

        // Call second time with same params
        $request2 = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request2);
        $this->assertEquals($idPertama, $request2->id);
    }

    // ===== Proses (approve/reject) =====

    public function test_proses_oleh_role_benar(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request);

        // Finance menyetujui — action alias 'approved' → status PRD 'disetujui'
        $request->proses('approved', $finance->id, 'Disetujui');
        $this->assertEquals('disetujui', $request->fresh()->status);
        $this->assertEquals($finance->id, $request->fresh()->actioned_by);
    }

    public function test_proses_oleh_permission_saja(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $superAdmin = $this->createUser('super-admin');
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request);

        // Super-admin punya permission approve-workflow → boleh approve request role finance
        $request->proses('approved', $superAdmin->id, 'Disetujui');
        $this->assertEquals('disetujui', $request->fresh()->status);
    }

    public function test_proses_oleh_role_salah_throw_exception(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request);

        // Kasir (tanpa role finance, tanpa permission approve-workflow) mencoba menyetujui
        $this->expectException(ValidationException::class);
        $request->proses('approved', $kasir->id, 'Dicoba');
    }

    public function test_ada_pending_helper(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertTrue($service->adaPending('po', $request->entity_id));

        // PO sudah approved
        $finance = $this->createUser('finance');
        $request->proses('approved', $finance->id, 'Disetujui');
        $this->assertFalse($service->adaPending('po', $request->entity_id));
    }

    public function test_non_rule_entity_returns_null(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');

        $service = app(ApprovalService::class);
        // Entity tanpa rule sama sekali
        $request = $service->ajukan('payroll', 15000000, (string) $cabang->id, ['amount' => 15000000], $kasir->id);
        $this->assertNull($request);
    }

    public function test_reject_request(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');
        ApprovalRule::create([
            'cabang_id' => $cabang->id,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'finance',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNotNull($request);

        // Finance menolak — action alias 'rejected' → status PRD 'ditolak'
        $request->proses('rejected', $finance->id, 'Harga terlalu tinggi');
        $this->assertEquals('ditolak', $request->fresh()->status);
    }

    // ===== Multi-level =====

    public function test_multi_level_approvals(): void
    {
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');
        $superAdmin = $this->createUser('super-admin');

        // L1 = rule global 'po' hasil migrasi (finance, min 10jt) — tambah L2 super-admin
        ApprovalRule::create([
            'cabang_id' => null,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'super-admin',
            'level' => 2,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $req1 = $service->ajukan('po', 501, null, ['amount' => 15000000, 'no_po' => 'PO-MULTI'], $kasir->id);
        $this->assertNotNull($req1);
        $this->assertEquals('finance', $req1->approver_role, 'Level 1 (finance) diajukan lebih dulu');

        // L1 disetujui → otomatis buka L2
        $req1->proses('approved', $finance->id, 'Oke L1');
        $this->assertEquals('disetujui', $req1->fresh()->status);

        $req2 = ApprovalRequest::where('entity_type', 'po')
            ->where('entity_id', 501)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($req2, 'Level 2 wajib dibuat setelah L1 disetujui');
        $this->assertEquals('super-admin', $req2->approver_role);
        $this->assertEquals(2, $req2->rule->level);
        $this->assertEquals('PO-MULTI', $req2->payload_json['no_po'], 'Payload dibawa lintas level');
        $this->assertTrue($service->adaPending('po', 501));

        // L2 disetujui → rantai selesai
        $req2->proses('approved', $superAdmin->id, 'Final');
        $this->assertEquals('disetujui', $req2->fresh()->status);
        $this->assertFalse($service->adaPending('po', 501));
        $this->assertEquals(2, ApprovalRequest::where('entity_type', 'po')->where('entity_id', 501)->count());
    }

    public function test_reject_menghentikan_rantai_multi_level(): void
    {
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');

        ApprovalRule::create([
            'cabang_id' => null,
            'entity_type' => 'po',
            'min_amount' => 10000000,
            'max_amount' => null,
            'approver_role' => 'super-admin',
            'level' => 2,
            'is_aktif' => true,
        ]);

        $service = app(ApprovalService::class);
        $req1 = $service->ajukan('po', 502, null, ['amount' => 15000000, 'no_po' => 'PO-REJ'], $kasir->id);
        $this->assertNotNull($req1);

        $req1->proses('rejected', $finance->id, 'Tolak');
        $this->assertEquals('ditolak', $req1->fresh()->status);

        // Rantai berhenti: tidak ada level 2, tidak ada pending
        $this->assertFalse($service->adaPending('po', 502));
        $this->assertEquals(1, ApprovalRequest::where('entity_type', 'po')->where('entity_id', 502)->count());
    }

    // ===== Auto-fire observers =====

    public function test_po_observer_auto_fire_di_atas_threshold(): void
    {
        $kasir = $this->createUser('kasir');
        $this->actingAs($kasir, 'web');

        $cabang = $this->createCabang('UTPPO1');
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang PO', 'kode' => 'GDG-'.Str::random(5), 'is_active' => true]);
        $supplier = Supplier::create(['nama' => 'Supplier '.Str::random(4)]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-OBS-'.Str::random(5),
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'kredit',
            'total' => 15000000,
            'total_dibayar' => 0,
        ]);

        $req = ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->first();
        $this->assertNotNull($req, 'PO > threshold wajib auto-fire approval');
        $this->assertEquals('pending', $req->status);
        $this->assertEquals('finance', $req->approver_role);
        $this->assertEquals((int) $cabang->id, (int) $req->cabang_id, 'cabang derive via gudang tujuan');
        $this->assertEquals($kasir->id, (int) $req->requested_by);
        $this->assertEquals(15000000, (float) $req->payload_json['amount']);
        $this->assertEquals($po->no_po, $req->payload_json['no_po']);
    }

    public function test_po_observer_di_bawah_threshold_tidak_fire(): void
    {
        $this->actingAs($this->createUser('kasir'), 'web');

        $cabang = $this->createCabang('UTPPO2');
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang PO2', 'kode' => 'GDG-'.Str::random(5), 'is_active' => true]);
        $supplier = Supplier::create(['nama' => 'Supplier '.Str::random(4)]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-SMALL-'.Str::random(5),
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'kredit',
            'total' => 5000000, // < 10jt
            'total_dibayar' => 0,
        ]);

        $this->assertNull(
            ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->first(),
            'PO di bawah threshold tidak boleh auto-fire'
        );
    }

    public function test_diskon_besar_auto_fire(): void
    {
        $kasir = $this->createUser('kasir');
        $this->actingAs($kasir, 'web');
        $cabang = $this->createCabang('UTPDSK');

        // Diskon nominal 1,5jt ≥ threshold 1jt → fire (rule global 'diskon' dari migrasi)
        $tr1 = Transaksi::create([
            'no_transaksi' => 'TRX-DSK-'.Str::random(5),
            'cabang_id' => $cabang->id,
            'kasir_id' => $kasir->id,
            'subtotal' => 5000000,
            'diskon_persen' => 0,
            'diskon_nominal' => 1500000,
            'total_akhir' => 3500000,
            'status' => 'selesai',
        ]);

        $req1 = ApprovalRequest::where('entity_type', 'diskon')->where('entity_id', $tr1->id)->first();
        $this->assertNotNull($req1, 'Diskon besar (nominal) wajib auto-fire');
        $this->assertEquals('pending', $req1->status);
        $this->assertEquals('admin-toko', $req1->approver_role);
        $this->assertEquals(1500000, (float) $req1->payload_json['amount']);

        // Diskon persen 40% × 3jt = 1,2jt ≥ 1jt → fire (amount dihitung dari persen)
        $tr2 = Transaksi::create([
            'no_transaksi' => 'TRX-DSK-'.Str::random(5),
            'cabang_id' => $cabang->id,
            'kasir_id' => $kasir->id,
            'subtotal' => 3000000,
            'diskon_persen' => 40,
            'diskon_nominal' => 0,
            'total_akhir' => 1800000,
            'status' => 'selesai',
        ]);

        $req2 = ApprovalRequest::where('entity_type', 'diskon')->where('entity_id', $tr2->id)->first();
        $this->assertNotNull($req2, 'Diskon besar (persen) wajib auto-fire');
        $this->assertEqualsWithDelta(1200000, (float) $req2->payload_json['amount'], 0.01);

        // Diskon kecil 100rb < 1jt → tidak fire
        $tr3 = Transaksi::create([
            'no_transaksi' => 'TRX-DSK-'.Str::random(5),
            'cabang_id' => $cabang->id,
            'kasir_id' => $kasir->id,
            'subtotal' => 500000,
            'diskon_persen' => 0,
            'diskon_nominal' => 100000,
            'total_akhir' => 400000,
            'status' => 'selesai',
        ]);

        $this->assertNull(ApprovalRequest::where('entity_type', 'diskon')->where('entity_id', $tr3->id)->first());
    }

    public function test_retur_penjualan_auto_fire(): void
    {
        $kasir = $this->createUser('kasir');
        $this->actingAs($kasir, 'web');

        // Retur 7jt ≥ 5jt (rule global 'retur' super-admin dari migrasi) → fire
        $retur = ReturnPenjualan::create([
            'no_return' => 'RET-'.Str::random(5),
            'tanggal' => now()->toDateString(),
            'jumlah' => 7000000,
            'status' => 'selesai',
            'alasan' => 'Barang rusak',
        ]);

        $req = ApprovalRequest::where('entity_type', 'retur')->where('entity_id', $retur->id)->first();
        $this->assertNotNull($req, 'Retur penjualan > threshold wajib auto-fire');
        $this->assertEquals('pending', $req->status);
        $this->assertEquals('super-admin', $req->approver_role);
        $this->assertEquals(7000000, (float) $req->payload_json['amount']);
        $this->assertEquals($kasir->id, (int) $req->requested_by);

        // Retur kecil 500rb < 5jt → tidak fire
        $returKecil = ReturnPenjualan::create([
            'no_return' => 'RET-'.Str::random(5),
            'tanggal' => now()->toDateString(),
            'jumlah' => 500000,
            'status' => 'selesai',
        ]);

        $this->assertNull(ApprovalRequest::where('entity_type', 'retur')->where('entity_id', $returKecil->id)->first());
    }

    public function test_retur_pembelian_auto_fire(): void
    {
        $this->actingAs($this->createUser('super-admin'), 'web');

        // Retur pembelian 6jt ≥ 5jt (rule global 'retur_pembelian' dari migrasi) → fire
        $retur = ReturnPembelian::create([
            'no_return' => 'RPB-'.Str::random(5),
            'tanggal' => now()->toDateString(),
            'jumlah' => 6000000,
            'status' => 'selesai',
            'alasan' => 'Kemasan penyok',
        ]);

        $req = ApprovalRequest::where('entity_type', 'retur_pembelian')->where('entity_id', $retur->id)->first();
        $this->assertNotNull($req, 'Retur pembelian > threshold wajib auto-fire');
        $this->assertEquals('pending', $req->status);
        $this->assertEquals('super-admin', $req->approver_role);
        $this->assertEquals(6000000, (float) $req->payload_json['amount']);
    }

    // ===== Notifikasi via queue =====

    public function test_notifikasi_approval_via_queue(): void
    {
        Queue::fake();

        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance'); // penerima notifikasi approver
        $this->actingAs($kasir, 'web');

        $service = app(ApprovalService::class);
        $request = $service->ajukan('po', 777, null, ['amount' => 12000000, 'no_po' => 'PO-NTF'], $kasir->id);
        $this->assertNotNull($request);

        // Ajuan → notifikasi role approver masuk queue (bukan sync)
        Queue::assertPushed(KirimNotifikasiJob::class, 1);
        $this->assertDatabaseHas('notifikasi_keluar', [
            'tipe' => 'inapp',
            'tujuan' => $finance->email,
            'status' => 'pending', // belum diproses → bukti tidak sync
        ]);

        // Proses selesai (rantai selesai) → notifikasi pemohon masuk queue
        $request->proses('approved', $finance->id, 'Oke');
        Queue::assertPushed(KirimNotifikasiJob::class, 2);
    }

    // ===== UI: inbox + RBAC route =====

    public function test_inbox_filter_role(): void
    {
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');

        // Request global rule → approver finance
        $request = app(ApprovalService::class)
            ->ajukan('po', 888, null, ['amount' => 15000000, 'no_po' => 'PO-INBOX'], $kasir->id);
        $this->assertNotNull($request);

        // Finance (permission approve-workflow) melihat request pending
        $this->actingAs($finance, 'web');
        Livewire::test(ApprovalInbox::class)
            ->assertSee('Menunggu')
            ->assertDontSee('Tidak ada permintaan approval');

        // Kasir (role tidak cocok, tanpa permission) → daftar kosong
        $this->actingAs($kasir, 'web');
        Livewire::test(ApprovalInbox::class)
            ->assertSee('Tidak ada permintaan approval');
    }

    public function test_route_approvals_rbac(): void
    {
        $cabang = $this->createCabang('UTPRTS');
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');
        $finance->cabangs()->attach($cabang->id);

        $session = ['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama];

        // Kasir tanpa permission approve-workflow → 403
        $this->actingAs($kasir, 'web')
            ->withSession($session)
            ->get('/app/approvals')
            ->assertForbidden();

        // Finance dengan permission → 200
        $this->actingAs($finance, 'web')
            ->withSession($session)
            ->get('/app/approvals')
            ->assertOk();
    }
}
