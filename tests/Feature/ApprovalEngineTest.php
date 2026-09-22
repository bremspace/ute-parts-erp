<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Workflow\Services\ApprovalService;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Rbac\Models\Cabang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        Permission::firstOrCreate(['name' => 'approve-workflow', 'guard_name' => 'web']);
        $superAdminRole = Role::where('name', 'super-admin')->first();
        $superAdminRole->givePermissionTo('approve-workflow');
        $financeRole = Role::where('name', 'finance')->first();
        $financeRole->givePermissionTo('approve-workflow');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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

        // Buat rule global: min 5 juta, approver super-admin
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

    public function test_ajuan_idempotent(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $rule = ApprovalRule::create([
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

    public function test_proses_oleh_role_benar(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');
        $rule = ApprovalRule::create([
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

        // Finance menyetujui
        $request->proses('approved', $finance->id, 'Disetujui');
        $this->assertEquals('approved', $request->fresh()->status);
        $this->assertEquals($finance->id, $request->fresh()->actioned_by);
    }

    public function test_proses_oleh_permission_saja(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $superAdmin = $this->createUser('super-admin');
        $rule = ApprovalRule::create([
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

        // Super-admin punya permission approve-workflow
        $request->proses('approved', $superAdmin->id, 'Disetujui');
        $this->assertEquals('approved', $request->fresh()->status);
    }

    public function test_proses_oleh_role_salah_throw_exception(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $superAdmin = $this->createUser('super-admin'); // role tidak sesuai
        $rule = ApprovalRule::create([
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

        // Super-admin mencoba menyetujui PO (seharusnya finance)
        $this->expectException(ValidationException::class);
        $request->proses('approved', $superAdmin->id, 'Disetujui');
    }

    public function test_ada_pending_helper(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $rule = ApprovalRule::create([
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
        // Tanpa membuat rule apapun
        $request = $service->ajukan('po', 15000000, (string) $cabang->id, ['amount' => 15000000, 'no_po' => 'PO-001'], $kasir->id);
        $this->assertNull($request);
    }

    public function test_reject_request(): void
    {
        $cabang = $this->createCabang();
        $kasir = $this->createUser('kasir');
        $finance = $this->createUser('finance');
        $rule = ApprovalRule::create([
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

        // Finance menolak
        $request->proses('rejected', $finance->id, 'Harga terlalu tinggi');
        $this->assertEquals('rejected', $request->fresh()->status);
    }
}