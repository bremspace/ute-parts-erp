<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KasLaciApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        // Seed approval rule for selisih_kas
        ApprovalRule::create([
            'entity_type' => 'selisih_kas',
            'approver_role' => 'admin-toko',
            'level' => 1,
            'min_amount' => 0.01,
            'max_amount' => 999999999,
            'is_aktif' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Toko',
            'email' => 'kasir@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->kasir->cabangs()->attach(1);

        $this->manager = User::create([
            'name' => 'Manager Toko',
            'email' => 'manager@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->manager->cabangs()->attach(1);
        Role::firstOrCreate(['name' => 'admin-toko']);
        $this->manager->assignRole('admin-toko');

        session(['cabang_id' => 1]);
        $this->actingAs($this->kasir);
    }

    public function test_buka_kas_debit_kas_laci_kredit_sumber(): void
    {
        $svc = app(KasSesiState::class);
        $hasil = $svc->bukaKas(200000, 1, $this->kasir->id, 'manual', '110-01');

        $this->assertDatabaseHas('kas_sesi', [
            'id' => $hasil['id'],
            'status' => 'buka',
            'akun_sumber_kode' => '110-01',
            'saldo_awal' => 200000,
        ]);

        // Jurnal: D 110-04 (Kas Laci) 200k / C 110-01 (Kas Besar) 200k
        $lines = JurnalAkuntansi::with('akun')->where('sumber', 'manual')->get();
        $this->assertSame(200000.0, (float) $lines->firstWhere('akun.kode', '110-04')?->debit);
        $this->assertSame(200000.0, (float) $lines->firstWhere('akun.kode', '110-01')?->kredit);
    }

    public function test_tutup_kas_ada_selisih_masuk_menunggu_approval(): void
    {
        $svc = app(KasSesiState::class);
        $sesi = $svc->bukaKas(200000, 1, $this->kasir->id, 'manual', '110-01');

        // Blind count: fisik 180000 vs sistem 200000 -> selisih -20000
        $out = $svc->tutupKas(180000);

        $this->assertSame('menunggu_approval', $out['status']);
        $this->assertSame(-20000.0, (float) $out['selisih']);

        // DB state: status menunggu_approval, ditutup_at belum terisi
        $this->assertDatabaseHas('kas_sesi', [
            'id' => $sesi['id'],
            'status' => 'menunggu_approval',
            'saldo_akhir_fisik' => 180000,
            'selisih' => -20000,
            'ditutup_at' => null,
        ]);

        // Sesi kas tidak aktif lagi (kasir diblokir transaksi)
        $this->assertNull($svc->sesiKasAktif());

        // Approval request tercatat di Workflow
        $this->assertDatabaseHas('approval_requests', [
            'entity_type' => 'selisih_kas',
            'entity_id' => $sesi['id'],
            'status' => 'pending',
        ]);
    }

    public function test_approval_disetujui_posting_jurnal_dan_tutup_sesi(): void
    {
        $svc = app(KasSesiState::class);
        $sesi = $svc->bukaKas(200000, 1, $this->kasir->id, 'manual', '110-01');
        $svc->tutupKas(180000);

        $request = ApprovalRequest::where('entity_type', 'selisih_kas')->where('entity_id', $sesi['id'])->firstOrFail();

        // Manager menyetujui (via ApprovalService::proses)
        app(ApprovalService::class)->proses($request->id, 'setujui', $this->manager->id, 'Disetujui setelah konfirmasi');

        // Status sesi final: tutup
        $this->assertDatabaseHas('kas_sesi', [
            'id' => $sesi['id'],
            'status' => 'tutup',
            'selisih' => -20000,
        ]);
        $this->assertNotNull(DB::table('kas_sesi')->where('id', $sesi['id'])->value('ditutup_at'));

        // Jurnal selisih: D 520-07 20k / C 110-04 20k
        // Jurnal deposit-back: D 110-01 180k / C 110-04 180k
        $jurnal = JurnalAkuntansi::with('akun')->where('sumber', 'manual')->get();
        $selisih = $jurnal->filter(fn ($j) => str_starts_with((string) $j->deskripsi, 'Selisih kas sesi'));
        $this->assertSame(20000.0, (float) $selisih->firstWhere('akun.kode', '520-07')?->debit);
        $this->assertSame(20000.0, (float) $selisih->firstWhere('akun.kode', '110-04')?->kredit);

        $deposit = $jurnal->filter(fn ($j) => str_starts_with((string) $j->deskripsi, 'Tutup kas sesi'));
        $this->assertSame(180000.0, (float) $deposit->firstWhere('akun.kode', '110-01')?->debit);
        $this->assertSame(180000.0, (float) $deposit->firstWhere('akun.kode', '110-04')?->kredit);
    }

    public function test_approval_ditolak_reopen_sesi_untuk_recount(): void
    {
        $svc = app(KasSesiState::class);
        $sesi = $svc->bukaKas(200000, 1, $this->kasir->id, 'manual', '110-01');
        $svc->tutupKas(180000);

        $request = ApprovalRequest::where('entity_type', 'selisih_kas')->where('entity_id', $sesi['id'])->firstOrFail();

        // Manager tolak dengan instruksi hitung ulang
        app(ApprovalService::class)->proses($request->id, 'tolak', $this->manager->id, 'Tolong hitung ulang uang receh');

        // Sesi reopened ke status buka dengan catatan penolakan
        $this->assertDatabaseHas('kas_sesi', [
            'id' => $sesi['id'],
            'status' => 'buka',
            'saldo_akhir_fisik' => null,
            'selisih' => null,
            'catatan' => 'Tolong hitung ulang uang receh',
        ]);

        // Sesi kembali aktif untuk kasir
        $this->assertNotNull($svc->sesiKasAktif());

        // Status request tercatat ditolak
        $this->assertDatabaseHas('approval_requests', [
            'id' => $request->id,
            'status' => 'ditolak',
            'catatan' => 'Tolong hitung ulang uang receh',
        ]);
    }
}
