<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataPermissionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $perms): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cabang = Cabang::create(['nama' => 'C1', 'kode' => 'C1', 'is_active' => true]);
        $u = User::create([
            'name' => 'U', 'email' => 'u'.random_int(1, 999999).'@t.com',
            'password' => Hash::make('x'), 'is_active' => true,
        ]);
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }
        $role = Role::firstOrCreate(['name' => 'r-'.implode('-', $perms)]);
        $role->givePermissionTo($perms);
        $u->assignRole($role);
        $u->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);
        $this->actingAs($u, 'web');

        return $u;
    }

    public function test_hanya_user_view_tidak_bisa_mutasi_master_katalog(): void
    {
        $this->userWith(['user.view']);
        $comp = Livewire::test(SettingsRbac::class);
        $comp->call('simpanKategori')->assertSet('showKategoriModal', false);
        $comp->call('simpanSatuan')->assertSet('showSatuanModal', false);
        $comp->call('simpanJenisServis')->assertSet('showJenisServisModal', false);
        $comp->call('openKategoriModal')->assertSet('showKategoriModal', false);
        $this->assertDatabaseMissing('kategori_produk', ['nama' => '']);
    }

    public function test_pengaturan_manage_boleh_mutasi_semua_master(): void
    {
        $this->userWith(['pengaturan.manage']);
        Livewire::test(SettingsRbac::class)
            ->call('openKategoriModal')
            ->assertSet('showKategoriModal', true)
            ->set('kategoriForm.nama', 'K1')
            ->call('simpanKategori')
            ->assertSet('showKategoriModal', false);
        $this->assertDatabaseHas('kategori_produk', ['nama' => 'K1']);
    }

    public function test_hapus_gudang_dinonaktifkan_ada_stok(): void
    {
        $this->userWith(['cabang.manage']);
        $g = Gudang::create(['cabang_id' => Cabang::first()->id, 'nama' => 'G1', 'kode' => 'G1', 'is_active' => true]);
        $produk = Produk::create([
            'nama' => 'P1', 'kode' => 'P1', 'harga_jual' => 1000, 'harga_beli' => 500, 'is_active' => true,
        ]);
        DB::table('stok_items')->insert([
            'produk_id' => $produk->id, 'gudang_id' => $g->id, 'jumlah' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Livewire::test(SettingsRbac::class)->call('hapusGudang', $g->id)
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'warning');
        $this->assertNotNull($g->fresh());
        $this->assertFalse($g->fresh()->is_active);
    }

    public function test_hapus_jenis_servis_dinonaktifkan_ada_tiket(): void
    {
        $this->userWith(['pengaturan.manage']);
        $j = JenisServis::create(['nama' => 'J1', 'kode' => 'J1', 'is_active' => true]);
        $p = Pelanggan::create(['nama' => 'P', 'telepon' => '08', 'cabang_id' => Cabang::first()->id]);
        DB::table('tiket_servis')->insert([
            'cabang_id' => Cabang::first()->id, 'no_tiket' => 'T1', 'pelanggan_id' => $p->id,
            'nama_pelanggan' => 'P', 'telepon_pelanggan' => '08', 'jenis_hp' => 'x',
            'keluhan' => 'x', 'status' => 'baru', 'tanggal_terima' => now(),
            'jenis_servis_id' => $j->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Livewire::test(SettingsRbac::class)->call('hapusJenisServis', $j->id)
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'warning');
        $this->assertNotNull($j->fresh());
        $this->assertFalse($j->fresh()->is_active);
    }

    public function test_hapus_cabang_tanpa_referensi_hard_delete(): void
    {
        $u = $this->userWith(['cabang.manage']);
        $c = Cabang::create(['nama' => 'KOSONG', 'kode' => 'KOS', 'is_active' => true]);
        Livewire::test(SettingsRbac::class)->call('hapusCabang', $c->id)
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'success');
        $this->assertNull(Cabang::find($c->id));
    }

    public function test_hapus_cabang_berreferensi_hanya_dinonaktifkan(): void
    {
        $this->userWith(['cabang.manage']);
        $c = Cabang::create(['nama' => 'ADA', 'kode' => 'ADA', 'is_active' => true]);
        Gudang::create(['cabang_id' => $c->id, 'nama' => 'g', 'kode' => 'g', 'is_active' => true]);
        Livewire::test(SettingsRbac::class)->call('hapusCabang', $c->id);
        $this->assertNotNull(Cabang::find($c->id));
        $this->assertFalse(Cabang::find($c->id)->is_active);
    }

    public function test_model_baru_tercatat_di_activity_log(): void
    {
        $this->userWith(['pengaturan.manage']);
        Livewire::test(SettingsRbac::class)
            ->call('openSatuanModal')->set('satuanForm.kode', 'satuan-uji')->set('satuanForm.nama', 'Satuan Uji')->call('simpanSatuan')
            ->call('openTipeHpModal')->set('tipeHpForm.merk', 'A')->set('tipeHpForm.model', 'B')->call('simpanTipeHp')
            ->call('openKualitasModal')->set('kualitasForm.nama', 'Q')->call('simpanKualitas')
            ->call('openJenisServisModal')->set('jenisServisForm.nama', 'S')->set('jenisServisForm.kode', 'S')->call('simpanJenisServis');

        foreach (['Satuan Unit dibuat', 'Tipe HP dibuat', 'Tingkat Kualitas dibuat', 'Jenis Servis dibuat'] as $desc) {
            $this->assertTrue(
                Activity::where('description', $desc)->exists(),
                "activity log untuk: {$desc}"
            );
        }
    }

    public function test_kondisi_json_tercatat_di_audit_log(): void
    {
        $this->userWith(['pengaturan.manage']);
        Livewire::test(SettingsRbac::class)
            ->call('openKondisiModal')->set('kondisiForm.nama', 'Kondisi Uji')->call('simpanKondisi')
            ->call('toggleKondisiStatus', 'kondisi_uji')
            ->call('hapusKondisi', 'kondisi_uji');

        $this->assertDatabaseHas('audit_logs', ['entitas' => 'KondisiProduk', 'aksi' => 'create']);
        $this->assertDatabaseHas('audit_logs', ['entitas' => 'KondisiProduk', 'aksi' => 'delete']);
    }
}
