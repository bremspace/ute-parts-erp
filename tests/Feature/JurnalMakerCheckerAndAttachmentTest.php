<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class JurnalMakerCheckerAndAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $maker;

    private User $checker;

    private User $superAdmin;

    private JurnalService $jurnalService;

    private ApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-GOV-01',
            'nama' => 'Cabang Tata Kelola ISO',
            'is_active' => true,
        ]);

        $this->maker = User::factory()->create([
            'name' => 'Staf Finance Maker',
            'email' => 'maker@test.com',
        ]);
        $this->maker->assignRole('admin-toko');
        $this->maker->givePermissionTo(['akunting.view', 'akunting.create', 'akunting.approve']);
        $this->maker->cabangs()->attach($this->cabang->id);

        $this->checker = User::factory()->create([
            'name' => 'Manager Finance Checker',
            'email' => 'checker@test.com',
        ]);
        $this->checker->assignRole('admin-toko');
        $this->checker->givePermissionTo(['akunting.view', 'akunting.create', 'akunting.approve', 'approve-workflow']);
        $this->checker->cabangs()->attach($this->cabang->id);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Auditor',
            'email' => 'auditor@test.com',
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);

        $this->jurnalService = app(JurnalService::class);
        $this->approvalService = app(ApprovalService::class);
    }

    private function seedCoa(): void
    {
        $accounts = [
            ['kode' => '520-05', 'nama' => 'Biaya Operasional', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
        ];

        foreach ($accounts as $a) {
            AkunCOA::updateOrCreate(['kode' => $a['kode']], array_merge($a, ['is_active' => true]));
        }
    }

    public function test_jurnal_manual_dengan_rule_approval_masuk_status_pending_dan_antrian_workflow(): void
    {
        // 1. Buat rule approval maker-checker untuk jurnal_manual
        ApprovalRule::create([
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'jurnal_manual',
            'min_amount' => 0,
            'max_amount' => null,
            'approver_role' => 'admin-toko',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $this->actingAs($this->maker, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Storage::fake('public');
        $file = UploadedFile::fake()->create('kuitansi_operasional.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Pengeluaran biaya fotokopi dan ATK',
            'lines' => [
                ['akun_kode' => '520-05', 'debit' => 150000, 'kredit' => 0],
                ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 150000],
            ],
            'lampiran' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_approval', 'pending');

        $noJurnal = $response->json('data.no_jurnal');

        // Cek header jurnal
        $header = JurnalHeader::where('no_jurnal', $noJurnal)->firstOrFail();
        $this->assertEquals('pending', $header->status_approval);
        $this->assertEquals('kuitansi_operasional.pdf', $header->lampiran_nama);
        $this->assertNotNull($header->lampiran_path);
        Storage::disk('public')->assertExists($header->lampiran_path);

        // Cek entitas workflow ApprovalRequest
        $approvalReq = ApprovalRequest::where('entity_type', 'jurnal_manual')
            ->where('entity_id', $header->id)
            ->firstOrFail();

        $this->assertEquals('pending', $approvalReq->status);
        $this->assertEquals($this->maker->id, $approvalReq->requested_by);
        $this->assertEquals(150000.0, (float) $approvalReq->payload_json['amount']);
    }

    public function test_segregation_of_duties_pembuat_tidak_boleh_menyetujui_jurnal_sendiri(): void
    {
        ApprovalRule::create([
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'jurnal_manual',
            'min_amount' => 0,
            'approver_role' => 'admin-toko',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $this->actingAs($this->maker, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $resp = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Pengeluaran kas',
            'lines' => [
                ['akun_kode' => '520-05', 'debit' => 50000, 'kredit' => 0],
                ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 50000],
            ],
        ]);

        $noJurnal = $resp->json('data.no_jurnal');

        // Maker mencoba approve jurnalnya sendiri via Livewire component
        Livewire::actingAs($this->maker)
            ->test(AkuntingDashboard::class)
            ->set('activeTab', 'jurnal')
            ->call('setujuiJurnal', $noJurnal)
            ->assertDispatched('alert', fn ($event, $params) => (($params[0] ?? $params)['type'] ?? '') === 'error' && str_contains(($params[0] ?? $params)['message'] ?? '', 'Prinsip 4-Mata'));

        // Header harus tetap pending
        $header = JurnalHeader::where('no_jurnal', $noJurnal)->first();
        $this->assertEquals('pending', $header->status_approval);
    }

    public function test_checker_berhasil_menyetujui_jurnal_manual_dan_mengubah_status(): void
    {
        ApprovalRule::create([
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'jurnal_manual',
            'min_amount' => 0,
            'approver_role' => 'admin-toko',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $this->actingAs($this->maker, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $resp = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Biaya rapat tim',
            'lines' => [
                ['akun_kode' => '520-05', 'debit' => 200000, 'kredit' => 0],
                ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 200000],
            ],
        ]);

        $noJurnal = $resp->json('data.no_jurnal');

        // Checker (orang berbeda) menyetujui jurnal
        Livewire::actingAs($this->checker)
            ->test(AkuntingDashboard::class)
            ->set('activeTab', 'jurnal')
            ->call('setujuiJurnal', $noJurnal)
            ->assertDispatched('alert', fn ($event, $params) => (($params[0] ?? $params)['type'] ?? '') === 'success');

        $header = JurnalHeader::where('no_jurnal', $noJurnal)->first();
        $this->assertEquals('disetujui', $header->status_approval);
        $this->assertEquals($this->checker->id, $header->approved_by_user_id);
        $this->assertNotNull($header->approved_at);

        $approvalReq = ApprovalRequest::where('entity_type', 'jurnal_manual')->where('entity_id', $header->id)->first();
        $this->assertEquals('disetujui', $approvalReq->status);
    }

    public function test_livewire_simpan_jurnal_manual_dengan_lampiran_berkas(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('nota_bbm.jpg');

        Livewire::actingAs($this->superAdmin)
            ->test(AkuntingDashboard::class)
            ->set('activeTab', 'jurnal')
            ->call('openJurnalManualModal')
            ->set('manualTanggal', now()->toDateString())
            ->set('manualDeskripsi', 'Penggantian BBM operasional')
            ->set('manualLines', [
                ['uid' => '1', 'sisi' => 'debit', 'akun_kode' => '520-05', 'jumlah' => 100000],
                ['uid' => '2', 'sisi' => 'kredit', 'akun_kode' => '210-01', 'jumlah' => 100000],
            ])
            ->set('manualLampiran', $file)
            ->call('simpanJurnalManual')
            ->assertDispatched('alert', fn ($event, $params) => (($params[0] ?? $params)['type'] ?? '') === 'success');

        $header = JurnalHeader::where('deskripsi', 'Penggantian BBM operasional')->firstOrFail();
        $this->assertEquals('nota_bbm.jpg', $header->lampiran_nama);
        $this->assertNotNull($header->lampiran_path);
        Storage::disk('public')->assertExists($header->lampiran_path);
    }
}
