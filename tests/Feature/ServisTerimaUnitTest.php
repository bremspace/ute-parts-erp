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
 * B-06 — Terima unit servis (/app/servis):
 * (a) Foto unit OPSIONAL — submit tanpa foto sukses (Livewire & API SERVICE-01),
 *     upload dengan foto tetap berjalan seperti sediakala.
 * (b) Kunci gadget terlihat utk SEMUA role yang berhak membuka tiket servis
 *     (teknisi ditugaskan maupun tidak, non-teknisi dgn servis.view) —
 *     di UI: default ter-mask + tombol "Tampilkan" (plaintext tdk ada di DOM sblm toggle).
 * (c) Checklist kondisi fisik: toggle chip → tersimpan apa adanya (opsional),
 *     tanpa menyentuh state machine servis.
 */
class ServisTerimaUnitTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-B06', 'is_active' => true]);
        session(['cabang_id' => $this->cabang->id]);
    }

    private function buatUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => 'User '.$role.' B06',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabang->id);

        return $user;
    }

    private function buatTiketDenganKunci(?User $teknisi = null, string $noTiket = 'SRV-B06-0001'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan B06',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar retak',
            'tipe_kunci' => 'pin',
            'kunci_terenkripsi' => '246813',
            'teknisi_id' => $teknisi?->id,
            'status' => 'diterima',
            'tanggal_terima' => now(),
        ]);
    }

    /** (a) Livewire: submit terima unit TANPA foto harus sukses (validasi min-2 dihapus). */
    public function test_simpan_terima_unit_tanpa_foto_berhasil(): void
    {
        $user = $this->buatUser('super-admin', 'super-admin-b06@test.com');
        $this->actingAs($user, 'web');

        Livewire::test(ServisBoard::class)
            ->call('openTerimaModal')
            ->set('terimaForm.nama_pelanggan', 'Budi Santoso')
            ->set('terimaForm.telepon_pelanggan', '0811111111')
            ->set('terimaForm.jenis_hp', 'iPhone 13')
            ->set('terimaForm.keluhan', 'Baterai cepat habis')
            ->call('simpanTerima')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('tiket_servis', [
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 13',
            'nama_pelanggan' => 'Budi Santoso',
            'status' => 'diterima',
        ]);

        $tiket = TiketServis::where('jenis_hp', 'iPhone 13')->firstOrFail();
        $this->assertEmpty($tiket->foto_unit, 'Foto unit harus kosong (opsional) saat submit tanpa foto');
    }

    /** (a) API [SERVICE-01]: `foto_unit` tidak lagi required/min:2 → 201 tanpa foto. */
    public function test_api_terima_unit_tanpa_foto_berhasil(): void
    {
        $user = $this->buatUser('admin-toko', 'admin-toko-b06@test.com');
        $this->actingAs($user, 'web');

        $this->postJson('/api/servis', [
            'nama_pelanggan' => 'Andi Wijaya',
            'telepon_pelanggan' => '0822222222',
            'jenis_hp' => 'Xiaomi 12',
            'keluhan' => 'Tidak bisa menyala',
        ])->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('tiket_servis', ['jenis_hp' => 'Xiaomi 12', 'status' => 'diterima']);
    }

    /** (a) Regresi alur upload: foto yang diisi tetap tersimpan utuh (0-3 foto). */
    public function test_api_terima_unit_dengan_foto_tetap_tersimpan(): void
    {
        $user = $this->buatUser('admin-toko', 'admin-toko-foto-b06@test.com');
        $this->actingAs($user, 'web');

        $this->postJson('/api/servis', [
            'nama_pelanggan' => 'Citra',
            'telepon_pelanggan' => '0833333333',
            'jenis_hp' => 'Oppo A57',
            'keluhan' => 'Speaker tidak bunyi',
            'foto_unit' => [
                'data:image/png;base64,depan',
                'data:image/png;base64,belakang',
            ],
        ])->assertStatus(201);

        $tiket = TiketServis::where('jenis_hp', 'Oppo A57')->firstOrFail();
        $this->assertCount(2, $tiket->foto_unit ?? []);
    }

    /** (b) API SERVICE-03: teknisi TIDAK ditugaskan ke tiket pun tetap boleh lihat kunci. */
    public function test_api_kunci_gadget_terlihat_untuk_teknisi_yang_tidak_ditugaskan(): void
    {
        $tiket = $this->buatTiketDenganKunci(); // teknisi_id = null
        $teknisi = $this->buatUser('teknisi', 'teknisi-b06@test.com');
        $this->actingAs($teknisi, 'web');

        $this->getJson('/api/servis/'.$tiket->id)
            ->assertStatus(200)
            ->assertJsonPath('data.tipe_kunci', 'pin')
            ->assertJsonPath('data.kunci_terenkripsi', '246813');
    }

    /** (b) Non-teknisi dgn `servis.view` (admin-toko) juga boleh lihat — bukan hak teknisi saja. */
    public function test_api_kunci_gadget_terlihat_untuk_role_non_teknisi_dengan_servis_view(): void
    {
        $tiket = $this->buatTiketDenganKunci();
        $admin = $this->buatUser('admin-toko', 'admin-kunci-b06@test.com');
        $this->actingAs($admin, 'web');

        $this->getJson('/api/servis/'.$tiket->id)
            ->assertStatus(200)
            ->assertJsonPath('data.kunci_terenkripsi', '246813');
    }

    /** (b) Role tanpa `servis.view` tidak bisa membuka tiket sama sekali (pintu RBAC tetap). */
    public function test_api_kunci_gadget_diblokir_untuk_role_tanpa_servis_view(): void
    {
        $tiket = $this->buatTiketDenganKunci();
        // [B-14] role `staff-gudang` yang dipakai (bukan `kasir`): sejak B-14
        // `kasir` + `marketing` diberi `servis.view` read-only, jadi role yang
        // TIDAK entitled harus dipakai utk uji 403 ini.
        $stafGudang = $this->buatUser('staff-gudang', 'staffgudang-kunci-b06@test.com');
        $this->actingAs($stafGudang, 'web');

        $this->getJson('/api/servis/'.$tiket->id)->assertStatus(403);
    }

    /** (b) UI detail tiket: default ter-mask, plaintext baru muncul setelah tombol "Tampilkan". */
    public function test_detail_tiket_kunci_gadget_termask_default_dan_bisa_ditampilkan(): void
    {
        $teknisi = $this->buatUser('teknisi', 'teknisi-detail-b06@test.com');
        $tiket = $this->buatTiketDenganKunci($teknisi);
        $this->actingAs($teknisi, 'web');

        $component = Livewire::test(ServisBoard::class)->call('openDetail', $tiket->id);

        $component->assertSee('Kunci Gadget');
        $component->assertSee('PIN (4-6 digit)');
        $component->assertSee('Tampilkan');
        // Default: nilai TIDAK ada di DOM (hanya mask)
        $component->assertDontSee('246813');

        $component->call('toggleKunciGadget');
        $component->assertSee('246813');
        $component->assertSee('Sembunyikan');

        $component->call('toggleKunciGadget');
        $component->assertDontSee('246813');

        // Buka tiket lain → kembali ter-mask (state toggle di-reset saat openDetail)
        $tiketLain = $this->buatTiketDenganKunci($teknisi, 'SRV-B06-0002');
        Livewire::test(ServisBoard::class)
            ->call('openDetail', $tiket->id)
            ->call('toggleKunciGadget')
            ->call('openDetail', $tiketLain->id)
            ->assertDontSee('246813');
    }

    /** (c) Checklist kondisi fisik: chip toggle → tersimpan apa adanya, tanpa foto pun sah. */
    public function test_checklist_kondisi_fisik_tertoggle_dan_tersimpan(): void
    {
        $user = $this->buatUser('teknisi', 'teknisi-checklist-b06@test.com');
        $this->actingAs($user, 'web');

        $component = Livewire::test(ServisBoard::class)
            ->call('openTerimaModal')
            ->call('toggleKondisiFisik', 'Layar')
            ->call('toggleKondisiFisik', 'Body')
            ->call('toggleKondisiFisik', 'Layar'); // toggle OFF lagi

        $component->assertSet('terimaForm.kondisi_fisik', ['Body']);

        $component
            ->set('terimaForm.nama_pelanggan', 'Dewi')
            ->set('terimaForm.jenis_hp', 'Vivo Y21')
            ->set('terimaForm.keluhan', 'Pecah bodi belakang')
            ->call('simpanTerima')
            ->assertDispatched('alert');

        $tiket = TiketServis::where('jenis_hp', 'Vivo Y21')->firstOrFail();
        $this->assertSame(['Body'], $tiket->kondisi_fisik);
        $this->assertEmpty($tiket->foto_unit);
        $this->assertSame('diterima', $tiket->status);
    }

    /** [B-08] Render pola kunci tidak boleh mengakses `$loop` di luar @foreach. */
    public function test_modal_pola_kunci_render_tanpa_exception(): void
    {
        $user = $this->buatUser('admin-toko', 'admin-pola-b08@test.com');
        $this->actingAs($user, 'web');

        Livewire::test(ServisBoard::class)
            ->call('openTerimaModal')
            ->set('terimaForm.tipe_kunci', 'pola')
            ->assertSee('Klik urutan titik pola (1-9):')
            ->assertSeeHtml('terimaForm.kunci_terenkripsi.0');
    }
}
