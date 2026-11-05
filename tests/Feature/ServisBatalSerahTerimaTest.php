<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [T-13/P0-AUDIT] Regression guard serah terima (diambil) pada tiket batal.
 *
 * BUG-1: tiket 'batal' yang belum pernah selesai TIDAK boleh dipaksa lunas
 *        (deadlock: unit tak bisa diserahkan, bayar() menolak status batal).
 * BUG-2: tiket 'ditolak'/'batal' yang jurnalnya sudah terposting (pernah
 *        'selesai') TIDAK boleh diserahkan gratis tanpa lunas/override.
 */
class ServisBatalSerahTerimaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'owner', 'admin-toko', 'teknisi', 'kasir', 'customer'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Ute Parts Pusat',
            'kode' => 'UPP',
            'is_pusat' => true,
            'aktif' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin Servis',
            'email' => 'admin-servis@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);

        Permission::firstOrCreate(['name' => 'servis.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'servis.update-status', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'servis.override-status', 'guard_name' => 'web']);
        $this->user->givePermissionTo(['servis.view', 'servis.update-status']);

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);
    }

    private function buatTiket(string $status, array $extra = []): TiketServis
    {
        return TiketServis::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-BTL-'.uniqid(),
            'nama_pelanggan' => 'Pelanggan Batal',
            'jenis_hp' => 'iPhone 14',
            'keluhan' => 'Layar patah',
            'status' => $status,
            'status_pembayaran' => 'belum_bayar',
            'token_approval' => 'tok-'.uniqid(),
        ], $extra));
    }

    public function test_b1_tiket_ditolak_lalu_batal_bisa_diserahkan_tanpa_bayar(): void
    {
        $tiket = $this->buatTiket('ditolak');

        $service = app(ServisService::class);

        $batal = $service->updateStatus($tiket, 'batal', $this->user, 'Pekerjaan dibatalkan');
        $this->assertSame('batal', $batal->status);

        $diambil = $service->updateStatus($batal, 'diambil', $this->user, 'Unit diserahkan tanpa perbaikan');

        $this->assertSame('diambil', $diambil->status);
        $this->assertSame('belum_bayar', $diambil->status_pembayaran);
        $this->assertNotNull($diambil->tanggal_diambil);
    }

    public function test_b1_tiket_ditolak_langsung_diserahkan_tanpa_bayar(): void
    {
        $tiket = $this->buatTiket('ditolak');

        $diambil = app(ServisService::class)->updateStatus($tiket, 'diambil', $this->user, 'Unit diserahkan kembali');

        $this->assertSame('diambil', $diambil->status);
        $this->assertSame('belum_bayar', $diambil->status_pembayaran);
    }

    public function test_b2_tiket_selesai_yang_dibatalkan_tidak_bisa_gratis_tanpa_override(): void
    {
        // Tiket selesai (jurnal onSelesai terposting) → override ke batal.
        $tiket = $this->buatTiket('batal', [
            'tanggal_selesai' => now()->subDay(),
            'estimasi_biaya' => 250000,
        ]);

        $this->assertThrows(function () use ($tiket) {
            app(ServisService::class)->updateStatus($tiket, 'diambil', $this->user, 'Coba serahkan gratis');
        }, \Exception::class);

        // Dengan override + alasan, unit boleh lepas — piutang ditagih/diturunkan manual.
        $this->user->givePermissionTo('servis.override-status');

        $tiket2 = $this->buatTiket('batal', [
            'tanggal_selesai' => now()->subDay(),
            'estimasi_biaya' => 250000,
        ]);
        $diambil = app(ServisService::class)->updateStatus($tiket2, 'diambil', $this->user, 'Unit diambil, piutang ditagih terpisah');
        $this->assertSame('diambil', $diambil->status);
    }

    public function test_b2_tiket_ditolak_setelah_selesai_terkunci_tanpa_override(): void
    {
        $tiket = $this->buatTiket('ditolak', [
            'tanggal_selesai' => now()->subDay(),
            'estimasi_biaya' => 250000,
        ]);

        $this->assertThrows(function () use ($tiket) {
            app(ServisService::class)->updateStatus($tiket, 'diambil', $this->user, 'Serahkan');
        }, \Exception::class);
    }

    public function test_aturan_tetap_selesai_ke_diambil_wajib_lunas(): void
    {
        $tiket = $this->buatTiket('selesai', ['tanggal_selesai' => now(), 'estimasi_biaya' => 100000]);

        $this->assertThrows(function () use ($tiket) {
            app(ServisService::class)->updateStatus($tiket, 'diambil', $this->user);
        }, \Exception::class);
    }
}
