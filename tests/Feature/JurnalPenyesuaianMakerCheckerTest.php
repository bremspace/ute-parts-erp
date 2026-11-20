<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Audit-1 F-20 — flag is_penyesuaian self-declared klien tidak boleh lagi
 * mematikan aturan sisi saldo normal tanpa kontrol tambahan:
 *  - hanya role finance/super-admin/owner yang boleh memakai flag;
 *  - entri penyesuaian yang benar-benar memakai kontra-sisi WAJIB maker-checker
 *    (status_approval pending, approver lain menyetujui via UI/API) meskipun
 *    tidak ada ApprovalRule aktif;
 *  - flag yang tidak dipakai untuk kontra-sisi tidak mengubah alur biasa.
 * Alasan sudah wajib lewat validasi deskripsi (API & UI) — tidak berubah.
 */
class JurnalPenyesuaianMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $finance;

    private User $financeLain;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cabang = Cabang::create(['kode' => 'CBG-F20', 'nama' => 'Cabang F20', 'is_active' => true]);

        $this->finance = $this->buatUser('finance-f20@test.com', 'finance');
        $this->financeLain = $this->buatUser('finance-lain-f20@test.com', 'finance');

        // Role terbatas: punya akunting.create tetapi BUKAN finance/owner/super-admin.
        Role::findOrCreate('operator-jurnal', 'web')->givePermissionTo(['akunting.view', 'akunting.create']);
        $this->operator = $this->buatUser('operator-f20@test.com', 'operator-jurnal');

        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]);
        AkunCOA::create(['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit', 'is_active' => true]);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit', 'is_active' => true]);
    }

    private function buatUser(string $email, string $role): User
    {
        $user = User::create([
            'name' => 'Uji F20 '.$email,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->cabangs()->attach($this->cabang->id);
        $user->assignRole($role);

        return $user;
    }

    private function masukSebagai(User $user): void
    {
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($user, 'web');
    }

    /** @return array<int,array<string,mixed>> */
    private function linesKontraSisi(): array
    {
        // Debit Utang (normal kredit) + Kredit Kas (normal debit) = kontra-sisi.
        return [
            ['akun_kode' => '210-01', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 100000],
        ];
    }

    public function test_api_penyesuaian_kontra_sisi_wajib_maker_checker(): void
    {
        $this->masukSebagai($this->finance);

        $resp = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Penyesuaian kasbon utang',
            'is_penyesuaian' => true,
            'lines' => $this->linesKontraSisi(),
        ])->assertStatus(201);

        $noJurnal = (string) $resp->json('data.no_jurnal');
        $this->assertNotSame('', $noJurnal);
        $this->assertSame('pending', $resp->json('data.status_approval'));
        $header = JurnalHeader::where('no_jurnal', $noJurnal)->where('cabang_key', $this->cabang->id)->firstOrFail();
        $this->assertSame('pending', $header->status_approval);
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
    }

    public function test_api_penyesuaian_pending_bisa_disetujui_approver_lain(): void
    {
        $this->masukSebagai($this->finance);
        $resp = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Penyesuaian kasbon utang',
            'is_penyesuaian' => true,
            'lines' => $this->linesKontraSisi(),
        ])->assertStatus(201);
        $noJurnal = (string) $resp->json('data.no_jurnal');

        // Approver lain (bukan pembuat) menyetujui via UI — jalur sah tersedia.
        $this->masukSebagai($this->financeLain);
        Livewire::test(AkuntingDashboard::class)
            ->call('setujuiJurnal', $noJurnal)
            ->assertDispatched('alert');

        $header = JurnalHeader::where('no_jurnal', $noJurnal)->where('cabang_key', $this->cabang->id)->firstOrFail();
        $this->assertSame('disetujui', $header->status_approval);
    }

    public function test_api_flag_penyesuaian_diabaikan_untuk_role_non_finance(): void
    {
        $this->masukSebagai($this->operator);

        $resp = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Coba penyesuaian oleh operator',
            'is_penyesuaian' => true,
            'lines' => $this->linesKontraSisi(),
        ])->assertStatus(422);

        $this->assertStringContainsString('tidak dapat dipilih pada sisi', (string) $resp->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_api_penyesuaian_sisi_normal_tetap_langsung_disetujui(): void
    {
        $this->masukSebagai($this->finance);

        $resp = $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Flag penyesuaian tetapi sisi normal semua',
            'is_penyesuaian' => true,
            'lines' => [
                ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
            ],
        ])->assertStatus(201);

        $this->assertSame('disetujui', $resp->json('data.status_approval'));
    }

    public function test_ui_penyesuaian_kontra_sisi_wajib_maker_checker(): void
    {
        $this->masukSebagai($this->finance);

        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualIsPenyesuaian', true)
            ->set('manualDeskripsi', 'Penyesuaian kasbon utang via UI')
            ->set('manualLines', [
                ['akun_kode' => '210-01', 'sisi' => 'debit', 'jumlah' => 100000],
                ['akun_kode' => '110-01', 'sisi' => 'kredit', 'jumlah' => 100000],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', true)
            ->call('simpanJurnalManual')
            ->assertSet('showJurnalManual', false);

        $header = JurnalHeader::where('cabang_key', $this->cabang->id)->where('sumber', 'manual')->firstOrFail();
        $this->assertSame('pending', $header->status_approval);
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
    }

    public function test_ui_flag_penyesuaian_role_non_finance_tidak_mematikan_aturan_sisi(): void
    {
        $this->masukSebagai($this->operator);

        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualIsPenyesuaian', true)
            ->set('manualDeskripsi', 'Coba penyesuaian operator via UI')
            ->set('manualLines', [
                ['akun_kode' => '210-01', 'sisi' => 'debit', 'jumlah' => 100000],
                ['akun_kode' => '110-01', 'sisi' => 'kredit', 'jumlah' => 100000],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', false)
            ->assertSee('tidak dapat dipilih pada sisi');

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }
}
