<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        ApprovalRule::firstOrCreate(
            ['entity_type' => 'member', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 0,
                'max_amount' => null,
                'approver_role' => 'owner',
                'is_aktif' => true,
            ]
        );
    }

    public function test_registrasi_member_menjadi_pending_dan_antre_ke_approval_owner(): void
    {
        $response = $this->post('/daftar-pelanggan', [
            'nama' => 'Budi Konsumen',
            'telepon' => '081234567890',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/login-pelanggan');
        $response->assertSessionHas('info');

        $pelanggan = Pelanggan::where('telepon', '081234567890')->first();
        $this->assertNotNull($pelanggan);
        $this->assertEquals('pending', $pelanggan->status);

        $requestApproval = ApprovalRequest::where('entity_type', 'member')
            ->where('entity_id', $pelanggan->id)
            ->first();

        $this->assertNotNull($requestApproval);
        $this->assertEquals('pending', $requestApproval->status);
        $this->assertEquals('Budi Konsumen', $requestApproval->payload_json['nama']);
    }

    public function test_member_pending_tidak_bisa_login(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Pending Member',
            'telepon' => '089988776655',
            'password' => Hash::make('secret123'),
            'status' => 'pending',
        ]);

        $response = $this->post('/login-pelanggan', [
            'telepon' => '089988776655',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('telepon');
        $this->assertGuest('customer');
    }

    public function test_owner_approve_member_bisa_login(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $pelanggan = Pelanggan::create([
            'nama' => 'Calon Member',
            'telepon' => '081122334455',
            'password' => Hash::make('secret123'),
            'status' => 'pending',
        ]);

        $approvalService = app(ApprovalService::class);
        $req = $approvalService->ajukan('member', $pelanggan->id, null, ['nama' => $pelanggan->nama], $owner->id);

        $this->assertNotNull($req);

        // Owner melakukan approval
        $approvalService->proses($req->id, 'disetujui', $owner->id, 'Member valid dan disetujui');

        $pelanggan->refresh();
        $this->assertEquals('aktif', $pelanggan->status);
        $this->assertEquals($owner->id, $pelanggan->approved_by);

        // Setelah di-approve, member bisa login
        $response = $this->post('/login-pelanggan', [
            'telepon' => '081122334455',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/checkout');
        $this->assertAuthenticatedAs($pelanggan, 'customer');
    }

    public function test_owner_tolak_member_tidak_bisa_login(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $pelanggan = Pelanggan::create([
            'nama' => 'Member Ditolak',
            'telepon' => '085566778899',
            'password' => Hash::make('secret123'),
            'status' => 'pending',
        ]);

        $approvalService = app(ApprovalService::class);
        $req = $approvalService->ajukan('member', $pelanggan->id, null, ['nama' => $pelanggan->nama], $owner->id);

        // Owner menolak pendaftaran
        $approvalService->proses($req->id, 'ditolak', $owner->id, 'Data tidak valid');

        $pelanggan->refresh();
        $this->assertEquals('ditolak', $pelanggan->status);
        $this->assertEquals('Data tidak valid', $pelanggan->catatan_approval);

        // Member ditolak dilarang login
        $response = $this->post('/login-pelanggan', [
            'telepon' => '085566778899',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('telepon');
        $this->assertGuest('customer');
    }
}
