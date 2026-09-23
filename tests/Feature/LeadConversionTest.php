<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeadConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie permissions & roles (pattern: ApprovalEngineTest)
        Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.broadcast', 'guard_name' => 'web']);
        $marketingRole = Role::where('name', 'marketing')->first();
        $marketingRole->givePermissionTo(['crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.broadcast']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Notifikasi lead (stage transition / convert) selalu lewat queue — jangan sync
        Queue::fake();
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

    /**
     * Autentikasi user dengan permission crm.* + set session cabang aktif
     * (Lead::scopeForCabang & CrmController::storeLead membaca cabang_aktif_id).
     */
    protected function authAsCrm(Cabang $cabang): User
    {
        $user = $this->createUser('marketing');
        $this->actingAs($user, 'web');
        session(['cabang_aktif_id' => $cabang->id]);

        return $user;
    }

    /**
     * Seed lead langsung via model (tanpa service) — untuk test yang fokus
     * ke endpoint lain (convert, bulk, kanban, funnel, scoping).
     */
    protected function makeLead(Cabang $cabang, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'cabang_id' => $cabang->id,
            'sumber' => 'website',
            'stage' => 'baru',
            'nama' => 'Lead '.Str::random(6),
            'telepon' => '08'.Str::random(9),
            'nilai_estimasi' => 1000000,
        ], $overrides));
    }

    // 1. Create lead (POST /api/crm/leads) → row ada, cabang_id sesuai, stage baru
    public function test_create_lead_success(): void
    {
        $cabang = $this->createCabang('UTP001');
        $this->authAsCrm($cabang);

        $resp = $this->postJson('/api/crm/leads', [
            'sumber' => 'website',
            'nama' => 'Lead Baru Testing',
            'telepon' => '081234567890',
            'email' => 'lead-create@test.com',
            'nilai_estimasi' => 1500000,
            'catatan' => 'Dari feature test',
        ]);

        if ($resp->status() !== 201) {
            $this->fail('POST /api/crm/leads → HTTP '.$resp->status().': '.$resp->content());
        }
        $resp->assertJsonPath('success', true);

        $this->assertDatabaseHas('leads', [
            'id' => $resp->json('data.id'),
            'cabang_id' => $cabang->id,
            'stage' => 'baru',
            'nama' => 'Lead Baru Testing',
            'sumber' => 'website',
        ]);
    }

    // 2. Stage transition: won → won_at terisi; lost → lost_at terisi (via queue, bukan sync)
    public function test_stage_transition_won_and_lost_sets_timestamps(): void
    {
        $cabang = $this->createCabang('UTP002');
        $user = $this->authAsCrm($cabang);
        $lead = $this->makeLead($cabang, ['assigned_to' => $user->id]);

        $payload = [
            'cabang_id' => $cabang->id,
            'sumber' => $lead->sumber,
            'nama' => $lead->nama,
        ];

        $won = $this->putJson("/api/crm/leads/{$lead->id}", $payload + ['stage' => 'won']);
        if ($won->status() !== 200) {
            $this->fail('PUT stage=won → HTTP '.$won->status().': '.$won->content());
        }
        $won->assertJsonPath('success', true);

        $lead->refresh();
        $this->assertEquals('won', $lead->stage);
        $this->assertNotNull($lead->won_at, 'won_at harus terisi saat transisi ke won');
        // Notifikasi stage change harus lewat queue (KirimNotifikasiJob), bukan sync
        Queue::assertPushed(KirimNotifikasiJob::class);

        $lost = $this->putJson("/api/crm/leads/{$lead->id}", $payload + [
            'stage' => 'lost',
            'lost_reason' => 'harga',
        ]);
        if ($lost->status() !== 200) {
            $this->fail('PUT stage=lost → HTTP '.$lost->status().': '.$lost->content());
        }

        $lead->refresh();
        $this->assertEquals('lost', $lead->stage);
        $this->assertNotNull($lead->lost_at, 'lost_at harus terisi saat transisi ke lost');
    }

    // 3. Convert lead won → Pelanggan dibuat, pelanggan_id ter-link, cabang_id lead tetap
    public function test_convert_won_lead_creates_pelanggan(): void
    {
        $cabang = $this->createCabang('UTP003');
        $this->authAsCrm($cabang);

        $lead = $this->makeLead($cabang, [
            'stage' => 'won',
            'won_at' => now(),
            'nama' => 'Pelanggan Konversi',
            'telepon' => '089876543210',
            'email' => 'konversi@test.com',
        ]);

        $resp = $this->postJson("/api/crm/leads/{$lead->id}/convert");
        if ($resp->status() !== 200) {
            $this->fail('POST convert → HTTP '.$resp->status().': '.$resp->content());
        }
        $resp->assertJsonPath('success', true);

        $pelanggan = Pelanggan::where('telepon', '089876543210')->first();
        $this->assertNotNull($pelanggan, 'Pelanggan harus dibuat dari lead won');
        $this->assertEquals('Pelanggan Konversi', $pelanggan->nama);
        $this->assertEquals($pelanggan->id, $resp->json('data.id'));

        $lead->refresh();
        $this->assertNotNull($lead->pelanggan_id, 'pelanggan_id harus ter-link ke lead');
        $this->assertEquals($pelanggan->id, $lead->pelanggan_id);
        $this->assertEquals($cabang->id, $lead->cabang_id, 'cabang_id lead tidak boleh berubah saat convert');
    }

    // 4. Bulk stage update → beberapa lead pindah stage; lead cabang lain tidak ikut
    public function test_bulk_stage_update_moves_multiple_leads(): void
    {
        $cabang = $this->createCabang('UTP004');
        $cabangLain = $this->createCabang('UTP005');
        $this->authAsCrm($cabang);

        $l1 = $this->makeLead($cabang);
        $l2 = $this->makeLead($cabang);
        $l3 = $this->makeLead($cabang);
        $leadCabangLain = $this->makeLead($cabangLain);

        $resp = $this->postJson('/api/crm/leads/bulk-stage', [
            'lead_ids' => [$l1->id, $l2->id, $l3->id, $leadCabangLain->id],
            'stage' => 'negosiasi',
        ]);

        if ($resp->status() !== 200) {
            $this->fail('POST bulk-stage → HTTP '.$resp->status().': '.$resp->content());
        }
        $resp->assertJsonPath('data.updated', 3);

        foreach ([$l1, $l2, $l3] as $lead) {
            $this->assertEquals('negosiasi', $lead->fresh()->stage, "Lead {$lead->id} harus pindah ke negosiasi");
        }
        $this->assertEquals('baru', $leadCabangLain->fresh()->stage, 'Lead cabang lain tidak boleh ikut ter-update');
    }

    // 5. Kanban → ter-group per stage, hanya lead cabang aktif
    public function test_kanban_grouped_by_stage_only_active_cabang(): void
    {
        $cabangA = $this->createCabang('UTPA1');
        $cabangB = $this->createCabang('UTPB1');
        $this->authAsCrm($cabangA);

        $this->makeLead($cabangA, ['stage' => 'baru']);
        $this->makeLead($cabangA, ['stage' => 'baru']);
        $wonA = $this->makeLead($cabangA, ['stage' => 'won', 'won_at' => now()]);
        $leadB1 = $this->makeLead($cabangB, ['stage' => 'baru']);
        $this->makeLead($cabangB, ['stage' => 'baru']);

        $resp = $this->getJson('/api/crm/leads/kanban');
        if ($resp->status() !== 200) {
            $this->fail('GET kanban → HTTP '.$resp->status().': '.$resp->content());
        }
        $resp->assertJsonPath('success', true);

        $stages = ['baru', 'kontak', 'kualifikasi', 'negosiasi', 'won', 'lost'];
        foreach ($stages as $stage) {
            $this->assertArrayHasKey($stage, $resp->json('data.leads'), "Kanban harus punya kolom stage '{$stage}'");
        }

        $this->assertCount(2, $resp->json('data.leads.baru'), 'Hanya 2 lead baru milik cabang aktif');
        $this->assertCount(1, $resp->json('data.leads.won'));
        $this->assertEquals(3, $resp->json('data.summary.total'), 'Summary total hanya lead cabang aktif');

        $semuaId = [];
        foreach ($stages as $stage) {
            foreach ($resp->json('data.leads.'.$stage) as $item) {
                $semuaId[] = (int) $item['id'];
            }
        }
        $this->assertContains((int) $wonA->id, $semuaId);
        $this->assertNotContains((int) $leadB1->id, $semuaId, 'Lead cabang B tidak boleh muncul di kanban cabang A');
    }

    // 6. Funnel → count per stage konsisten dengan data seed (hanya cabang aktif)
    public function test_funnel_counts_consistent_with_seeded_data(): void
    {
        $cabangA = $this->createCabang('UTPA2');
        $cabangB = $this->createCabang('UTPB2');
        $this->authAsCrm($cabangA);

        $this->makeLead($cabangA, ['stage' => 'baru']);
        $this->makeLead($cabangA, ['stage' => 'baru']);
        $this->makeLead($cabangA, ['stage' => 'kontak']);
        $this->makeLead($cabangA, ['stage' => 'negosiasi']);
        $this->makeLead($cabangA, ['stage' => 'won', 'won_at' => now()]);
        $this->makeLead($cabangA, ['stage' => 'lost', 'lost_at' => now()]);
        // Cabang lain — tidak boleh ikut terhitung
        $this->makeLead($cabangB, ['stage' => 'won', 'won_at' => now()]);
        $this->makeLead($cabangB, ['stage' => 'won', 'won_at' => now()]);

        $resp = $this->getJson('/api/crm/leads/funnel');
        if ($resp->status() !== 200) {
            $this->fail('GET funnel → HTTP '.$resp->status().': '.$resp->content());
        }
        $resp->assertJsonPath('success', true);

        $expected = ['baru' => 2, 'kontak' => 1, 'kualifikasi' => 0, 'negosiasi' => 1, 'won' => 1, 'lost' => 1];
        foreach ($expected as $stage => $count) {
            $this->assertEquals($count, $resp->json("data.{$stage}.count"), "Funnel count stage '{$stage}'");
            $this->assertNotNull($resp->json("data.{$stage}.label"), "Funnel stage '{$stage}' punya label");
        }
    }

    // 7. Cabang scoping: lead cabang A tidak terlihat saat session cabang_aktif_id = cabang B
    public function test_lead_cabang_a_invisible_when_session_cabang_b(): void
    {
        $cabangA = $this->createCabang('UTPA3');
        $cabangB = $this->createCabang('UTPB3');

        $user = $this->createUser('marketing');
        $this->actingAs($user, 'web');

        $leadA = $this->makeLead($cabangA);
        $leadB = $this->makeLead($cabangB);

        // Session aktif pindah ke cabang B
        session(['cabang_aktif_id' => $cabangB->id]);

        $resp = $this->getJson('/api/crm/leads');
        if ($resp->status() !== 200) {
            $this->fail('GET /api/crm/leads → HTTP '.$resp->status().': '.$resp->content());
        }

        $ids = collect($resp->json('data.data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertFalse($ids->contains((int) $leadA->id), 'Lead cabang A tidak boleh terlihat di cabang B');
        $this->assertTrue($ids->contains((int) $leadB->id), 'Lead cabang B harus terlihat di cabang B');
    }

    // 8. Stage tidak valid (stage=bogus) → 422 validation error, lead tidak berubah
    public function test_invalid_stage_rejected(): void
    {
        $cabang = $this->createCabang('UTP008');
        $this->authAsCrm($cabang);
        $lead = $this->makeLead($cabang);

        $resp = $this->putJson("/api/crm/leads/{$lead->id}", [
            'cabang_id' => $cabang->id,
            'sumber' => $lead->sumber,
            'nama' => $lead->nama,
            'stage' => 'bogus',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('stage');
        $this->assertEquals('baru', $lead->fresh()->stage, 'Stage asli tidak boleh berubah saat validasi gagal');
    }
}
