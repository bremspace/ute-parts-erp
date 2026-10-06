<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Workflow\Livewire\ApprovalInbox;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalInboxNullSafeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private ApprovalRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-APP'],
            ['nama' => 'Cabang Approval', 'is_active' => true]
        );

        $this->admin = User::factory()->create([
            'name' => 'Admin Workflow',
            'email' => 'admin.workflow@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $this->rule = ApprovalRule::create([
            'entity_type' => 'payroll',
            'approver_role' => 'super-admin',
            'level' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Pastikan blade tidak crash saat actioned_at null pada status disetujui/ditolak.
     */
    public function test_approval_inbox_render_null_safe_actioned_at(): void
    {
        $this->actingAs($this->admin);
        session(['cabang_id' => $this->cabang->id]);

        // Buat approval request dengan status disetujui tapi actioned_at null secara eksplisit di DB
        $req = ApprovalRequest::create([
            'approval_rule_id' => $this->rule->id,
            'entity_type' => 'payroll',
            'entity_id' => 999,
            'cabang_id' => $this->cabang->id,
            'payload_json' => ['amount' => 5000000],
            'status' => 'disetujui',
            'requested_by' => $this->admin->id,
            'approver_role' => 'super-admin',
            'actioned_at' => null,
        ]);

        // Force null di database untuk mensimulasikan data warisan/legacy
        DB::table('approval_requests')
            ->where('id', $req->id)
            ->update(['actioned_at' => null]);

        $component = Livewire::test(ApprovalInbox::class);
        $component->assertStatus(200);
        $component->assertSee('Disetujui');
        $component->assertSee('payroll #999');
    }

    /**
     * Pastikan auto-stamp actioned_at aktif saat saving status non-pending.
     */
    public function test_model_boot_auto_stamps_actioned_at_when_non_pending(): void
    {
        $req = new ApprovalRequest([
            'approval_rule_id' => $this->rule->id,
            'entity_type' => 'payroll',
            'entity_id' => 1001,
            'cabang_id' => $this->cabang->id,
            'payload_json' => ['amount' => 1000000],
            'status' => 'disetujui',
            'requested_by' => $this->admin->id,
            'approver_role' => 'super-admin',
            'actioned_at' => null,
        ]);
        $req->save();

        $this->assertNotNull($req->fresh()->actioned_at);
    }

    /**
     * Pastikan aksi approve dan reject membersihkan cache badge di sidebar.
     */
    public function test_approval_actions_clear_sidebar_badge_cache(): void
    {
        $this->actingAs($this->admin);
        session(['cabang_id' => $this->cabang->id]);

        $req = ApprovalRequest::create([
            'approval_rule_id' => $this->rule->id,
            'entity_type' => 'payroll',
            'entity_id' => 1002,
            'cabang_id' => $this->cabang->id,
            'payload_json' => ['amount' => 2000000],
            'status' => 'pending',
            'requested_by' => $this->admin->id,
            'approver_role' => 'super-admin',
        ]);

        $cacheKeyCabang = 'backoffice-approval-badge-'.$this->cabang->id;
        Cache::put($cacheKeyCabang, 1, 60);
        Cache::put('backoffice-approval-badge-all', 1, 60);

        Livewire::test(ApprovalInbox::class)
            ->call('approveRequest', $req->id)
            ->assertDispatched('alert');

        $this->assertFalse(Cache::has($cacheKeyCabang));
        $this->assertFalse(Cache::has('backoffice-approval-badge-all'));
        $this->assertEquals('disetujui', $req->fresh()->status);
    }
}
