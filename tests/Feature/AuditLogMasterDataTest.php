<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit trail master data — owner/super-admin WAJIB bisa menemukan setiap CRUD.
 *
 * Dua gap yang ditutup:
 * 1. `adalahSuperAdmin()` hanya mengenali super-admin → owner ter-scope ke
 *    cabang aktif saja (CRUD cabang lain tak terlihat).
 * 2. Master data (brand/kategori/COA/dll) tidak ada di `RiwayatAktivitas::TIPE`
 *    → log-nya tertulis tapi tak bisa disaring/dibuka per entitas.
 *
 * Catatan repo: method wajib prefix test_ (PHPUnit 12 tidak deteksi @test docblock).
 */
class AuditLogMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'UTPSES', 'nama' => 'Cabang Sesi', 'is_active' => true,
        ]);
        $this->cabangLain = Cabang::create([
            'kode' => 'UTPLA2', 'nama' => 'Cabang Lain', 'is_active' => true,
        ]);
        $this->produk = Produk::create([
            'nama' => 'LCD Master Audit', 'slug' => 'lcd-master-audit', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 500000, 'harga_jual_retail' => 750000,
        ]);

        // Sesi aktif = cabang sendiri; stok uji sengaja di gudang CABANG LAIN.
        session(['cabang_id' => $this->cabang->id]);
    }

    private function userAuthed(string $role): User
    {
        $user = User::create([
            'name' => 'Uji '.ucfirst($role), 'email' => str($role)->slug()->value().'-'.Str::random(6).'@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user, 'web');

        return $user;
    }

    private function stokDiCabangLain(): StokItem
    {
        $gudangLain = Gudang::create([
            'cabang_id' => $this->cabangLain->id, 'nama' => 'Gudang Lain', 'kode' => 'GDG-AUD2', 'is_active' => true,
        ]);

        return StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $gudangLain->id,
            'jumlah' => 3, 'jumlah_minimum' => 1,
        ]);
    }

    /** ===== 1. Gap 1: owner tidak lagi ter-scope ke cabang aktif ===== */
    public function test_owner_melihat_log_cabang_lain(): void
    {
        $this->userAuthed('owner');
        $stok = $this->stokDiCabangLain();

        $daftar = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok']);

        $this->assertEquals('ok', $daftar->viewData('akses'));
        $this->assertTrue(
            collect($daftar->viewData('aktivitas')->items())->pluck('subject_id')->contains($stok->id),
            'Owner wajib melihat log cabang lain'
        );

        // Mode per entitas milik cabang lain juga harus boleh dibuka
        Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok', 'entityId' => $stok->id])
            ->assertViewHas('akses', 'ok')
            ->assertDontSee('tidak memiliki akses');
    }

    public function test_super_admin_melihat_log_cabang_lain(): void
    {
        $this->userAuthed('super-admin');
        $stok = $this->stokDiCabangLain();

        $daftar = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok']);

        $this->assertTrue(
            collect($daftar->viewData('aktivitas')->items())->pluck('subject_id')->contains($stok->id),
            'Super-admin tetap bebas lintas cabang'
        );
    }

    public function test_admin_toko_tetap_tidak_bisa_lihat_log_cabang_lain(): void
    {
        $this->userAuthed('admin-toko');
        $stok = $this->stokDiCabangLain();

        $daftar = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok']);

        $this->assertFalse(
            collect($daftar->viewData('aktivitas')->items())->pluck('subject_id')->contains($stok->id),
            'Role ter-scope cabang TIDAK boleh melihat log cabang lain'
        );

        Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok', 'entityId' => $stok->id])
            ->assertViewHas('akses', 'cabang-lain');
    }

    /** ===== 2. Gap 2: master data punya key tipe + judul yang bisa dibaca ===== */
    public function test_master_data_brand_bisa_disaring_per_tipe(): void
    {
        $this->userAuthed('owner');
        $brand = Brand::create(['nama' => 'Merek Audit']);

        $component = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'brand', 'entityId' => $brand->id]);

        $component->assertViewHas('akses', 'ok')
            ->assertViewHas('judul', 'Merek Audit')
            ->assertSee('Merek Audit');

        $this->assertEquals(1, $component->viewData('aktivitas')->total());
        $this->assertEquals('created', $component->viewData('aktivitas')->first()->event);

        // Opsi filter harus menawarkan brand sebagai entitas terpisah
        $this->assertArrayHasKey('brand', $component->viewData('opsiTipe'));
    }

    public function test_master_data_coa_bisa_disaring_per_tipe(): void
    {
        $this->userAuthed('owner');
        $coa = AkunCOA::create([
            'kode' => '599-99', 'nama' => 'Akun Audit', 'tipe' => 'aset',
            'kelompok' => 'kas', 'saldo_normal' => 'debit',
        ]);

        $component = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'coa', 'entityId' => $coa->id]);

        // COA = master global (tanpa cabang_id) → tidak ada scoping cabang
        $component->assertViewHas('akses', 'ok')
            ->assertViewHas('judul', '599-99 · Akun Audit');

        $this->assertEquals(1, $component->viewData('aktivitas')->total());
        $this->assertArrayHasKey('coa', $component->viewData('opsiTipe'));
    }

    public function test_semua_tipe_master_data_terdaftar_di_peta_dan_filter(): void
    {
        $this->userAuthed('super-admin');

        $component = Livewire::test(RiwayatAktivitas::class);
        $opsi = $component->viewData('opsiTipe');

        foreach ([
            'kategori', 'brand', 'kualitas', 'satuan', 'tipe_hp',
            'gudang', 'rak', 'jenis_servis', 'coa',
        ] as $tipe) {
            $this->assertArrayHasKey($tipe, RiwayatAktivitas::TIPE, "TIPE kurang {$tipe}");
            $this->assertArrayHasKey($tipe, $opsi, "opsiTipe kurang {$tipe}");
            $this->assertNotSame('', $opsi[$tipe], "label {$tipe} kosong");
        }
    }
}
