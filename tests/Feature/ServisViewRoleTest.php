<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-14 — Keputusan owner atas B-06 tertunda: role non-teknisi yang relevan
 * diberi `servis.view` (READ-ONLY) supaya bisa membuka tiket & melihat kunci
 * gadget, TANPA diberi hak mutasi.
 *
 * Diberi : `kasir` (meja kasir/loket terima-ambil unit), `marketing` (status
 *          perbaikan untuk menjawab pelanggan).
 * Tidak  : `finance` & `staff-gudang` (minimum privilege) → tetap 403.
 *
 * Route `/app/servis` sudah dijaga `permission:servis.view` (routes/web.php),
 * jadi TIDAK ada perubahan route — yang diuji di sini adalah consequence-nya:
 * halaman bisa dibuka, kunci gadget terlihat, dan aksi mutasi tetap tertutup
 * (guard server-side di ServisBoard).
 *
 * Catatan: method wajib prefix test_ (PHPUnit 12 tidak deteksi @test docblock).
 */
class ServisViewRoleTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-B14', 'is_active' => true]);
        session(['cabang_id' => $this->cabang->id]);
    }

    private function buatUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => 'User '.$role.' B14',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabang->id);

        return $user;
    }

    private function buatTiketDenganKunci(string $noTiket = 'SRV-B14-0001'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan B14',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar retak',
            'tipe_kunci' => 'pin',
            'kunci_terenkripsi' => '246813',
            'status' => 'diterima',
            'tanggal_terima' => now(),
        ]);
    }

    // ===== SEEDER: hanya role yang relevan yang dapat servis.view =====

    public function test_seeder_memberi_servis_view_ke_kasir_dan_marketing(): void
    {
        $this->assertTrue($this->buatUser('kasir', 'kasir-b14@test.com')->can('servis.view'));
        $this->assertTrue($this->buatUser('marketing', 'marketing-b14@test.com')->can('servis.view'));

        // Minimum privilege: role tsb tetap TIDAK dapat
        $finance = $this->buatUser('finance', 'finance-b14@test.com');
        $staffGudang = $this->buatUser('staff-gudang', 'staffgudang-b14@test.com');
        $this->assertFalse($finance->can('servis.view'));
        $this->assertFalse($staffGudang->can('servis.view'));

        // Role non-teknisi TIDAK ikut dapat hak mutasi
        $kasir = $this->buatUser('kasir', 'kasir-b14b@test.com');
        $marketing = $this->buatUser('marketing', 'marketing-b14b@test.com');
        foreach ([$kasir, $marketing] as $user) {
            $this->assertFalse($user->can('servis.create'));
            $this->assertFalse($user->can('servis.update-status'));
            $this->assertFalse($user->can('servis.input-sparepart'));
            $this->assertFalse($user->can('servis.override-status'));
        }
    }

    // ===== Akses halaman & kunci gadget =====

    public function test_kasir_bisa_buka_halaman_servis(): void
    {
        $this->actingAs($this->buatUser('kasir', 'kasir-hal-b14@test.com'), 'web');

        $this->get('/app/servis')->assertSuccessful();
    }

    public function test_marketing_bisa_buka_halaman_servis(): void
    {
        $this->actingAs($this->buatUser('marketing', 'marketing-hal-b14@test.com'), 'web');

        $this->get('/app/servis')->assertSuccessful();
    }

    public function test_kasir_melihat_kunci_gadget_di_api(): void
    {
        $tiket = $this->buatTiketDenganKunci();
        $this->actingAs($this->buatUser('kasir', 'kasir-api-b14@test.com'), 'web');

        $this->getJson('/api/servis/'.$tiket->id)
            ->assertStatus(200)
            ->assertJsonPath('data.tipe_kunci', 'pin')
            ->assertJsonPath('data.kunci_terenkripsi', '246813');
    }

    public function test_marketing_melihat_kunci_gadget_di_modal_detail(): void
    {
        $tiket = $this->buatTiketDenganKunci();
        $this->actingAs($this->buatUser('marketing', 'marketing-kunci-b14@test.com'), 'web');

        $component = Livewire::test(ServisBoard::class)->call('openDetail', $tiket->id);
        $component->assertSee('Kunci Gadget');
        $component->assertDontSee('246813'); // default ter-mask

        $component->call('toggleKunciGadget')->assertSee('246813');
    }

    public function test_role_tanpa_servis_view_tetap_403_di_halaman_dan_api(): void
    {
        $tiket = $this->buatTiketDenganKunci();

        foreach (['finance', 'staff-gudang'] as $role) {
            $this->actingAs($this->buatUser($role, $role.'-403-b14@test.com'), 'web');

            $this->get('/app/servis')->assertForbidden();
            $this->getJson('/api/servis/'.$tiket->id)->assertStatus(403);
            $this->getJson('/api/servis')->assertStatus(403);
        }
    }

    // ===== READ-ONLY: aksi mutasi tetap tertutup =====

    public function test_kasir_tidak_bisa_menerima_unit_lewat_livewire(): void
    {
        $this->actingAs($this->buatUser('kasir', 'kasir-terima-b14@test.com'), 'web');

        Livewire::test(ServisBoard::class)
            ->call('openTerimaModal')
            ->assertSet('showTerimaModal', false) // [B-14] guard servis.create
            ->call('simpanTerima')
            ->assertDispatched('alert');

        $this->assertSame(0, TiketServis::count());
    }

    public function test_kasir_tidak_bisa_ubah_status_ticket_lewat_livewire(): void
    {
        $tiket = $this->buatTiketDenganKunci();
        $this->actingAs($this->buatUser('kasir', 'kasir-status-b14@test.com'), 'web');

        Livewire::test(ServisBoard::class)
            ->call('updateStatus', $tiket->id, 'diagnosa')
            ->assertDispatched('alert');

        $this->assertSame('diterima', $tiket->fresh()->status);
    }

    public function test_marketing_tidak_bisa_input_pekerjaan_lewat_livewire(): void
    {
        $tiket = $this->buatTiketDenganKunci();
        $this->actingAs($this->buatUser('marketing', 'marketing-kerja-b14@test.com'), 'web');

        $component = Livewire::test(ServisBoard::class)
            ->call('openDetail', $tiket->id)
            ->set('pekerjaanItems', [[
                'tipe' => 'jasa', 'produk_id' => null, 'nama_item' => 'Ganti LCD',
                'qty' => 1, 'harga' => 150000, 'gudang_id' => null, 'sn' => '',
            ]])
            ->call('simpanPekerjaan')
            ->assertDispatched('alert');

        $this->assertSame(0, $tiket->items()->count());
    }

    /** Regresi: role yang memang berhak (admin-toko) tetap bisa terima unit. */
    public function test_admin_toko_masih_bisa_menerima_unit_lewat_livewire(): void
    {
        $this->actingAs($this->buatUser('admin-toko', 'admin-terima-b14@test.com'), 'web');

        Livewire::test(ServisBoard::class)
            ->call('openTerimaModal')
            ->assertSet('showTerimaModal', true)
            ->set('terimaForm.nama_pelanggan', 'Budi')
            ->set('terimaForm.jenis_hp', 'iPhone 13')
            ->set('terimaForm.keluhan', 'Baterai habis')
            ->call('simpanTerima')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('tiket_servis', ['jenis_hp' => 'iPhone 13', 'status' => 'diterima']);
    }
}
