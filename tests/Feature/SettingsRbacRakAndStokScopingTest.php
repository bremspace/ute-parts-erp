<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsRbacRakAndStokScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private Gudang $gudangA;

    private Gudang $gudangB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabangA = Cabang::create(['nama' => 'Cabang Surabaya', 'kode' => 'SBY-01', 'is_active' => true]);
        $this->cabangB = Cabang::create(['nama' => 'Cabang Malang', 'kode' => 'MLG-01', 'is_active' => true]);

        $this->gudangA = Gudang::create(['cabang_id' => $this->cabangA->id, 'nama' => 'Gudang SBY', 'kode' => 'GD-SBY', 'is_active' => true]);
        $this->gudangB = Gudang::create(['cabang_id' => $this->cabangB->id, 'nama' => 'Gudang MLG', 'kode' => 'GD-MLG', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin-rak@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach([$this->cabangA->id, $this->cabangB->id]);
    }

    public function test_settings_rbac_renders_rak_card_and_accepts_tab_cabang_query_param(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        session(['cabang_id' => $this->cabangA->id]);

        $rak = Rak::create([
            'gudang_id' => $this->gudangA->id,
            'nama' => 'Rak Display SBY',
            'kode' => 'RAK-SBY-1',
            'zona' => 'A1',
            'is_active' => true,
        ]);

        $this->get('/app/pengaturan?tab=cabang')
            ->assertSuccessful()
            ->assertSee('Rak Penyimpanan')
            ->assertSee('RAK-SBY-1')
            ->assertSee('+ Rak');
    }

    public function test_crud_rak_in_settings_rbac(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        session(['cabang_id' => $this->cabangA->id]);

        $component = Livewire::test(SettingsRbac::class, ['activeTab' => 'cabang'])
            ->call('openRakModal')
            ->assertSet('showRakModal', true)
            ->set('rakForm.gudang_id', $this->gudangA->id)
            ->set('rakForm.nama', 'Rak LCD Baru')
            ->set('rakForm.kode', 'RAK-LCD-01')
            ->set('rakForm.zona', 'Z-1')
            ->call('saveRak')
            ->assertSet('showRakModal', false);

        $this->assertDatabaseHas('rak', [
            'gudang_id' => $this->gudangA->id,
            'kode' => 'RAK-LCD-01',
            'nama' => 'Rak LCD Baru',
        ]);

        $rak = Rak::where('kode', 'RAK-LCD-01')->first();

        // Edit
        $component->call('openRakModal', $rak->id)
            ->set('rakForm.nama', 'Rak LCD Updated')
            ->call('saveRak');

        $this->assertDatabaseHas('rak', [
            'id' => $rak->id,
            'nama' => 'Rak LCD Updated',
        ]);

        // Hapus
        $component->call('hapusRak', $rak->id);
        $this->assertDatabaseMissing('rak', ['id' => $rak->id]);
    }

    public function test_hapus_rak_gagal_jika_ada_stok_aktif(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        session(['cabang_id' => $this->cabangA->id]);

        $rak = Rak::create([
            'gudang_id' => $this->gudangA->id,
            'nama' => 'Rak Berisi Stok',
            'kode' => 'RAK-FULL-1',
        ]);

        $kategori = KategoriProduk::create(['nama' => 'Sparepart']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'LCD Test SBY',
            'kode' => 'PRD-TEST-SBY',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangA->id,
            'rak_id' => $rak->id,
            'jumlah' => 15,
        ]);

        Livewire::test(SettingsRbac::class, ['activeTab' => 'cabang'])
            ->call('hapusRak', $rak->id)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error' && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'masih berisi stok aktif'));

        $this->assertDatabaseHas('rak', ['id' => $rak->id]);
    }

    public function test_produk_tab_scopes_total_stok_to_active_branch(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $kategori = KategoriProduk::create(['nama' => 'Baterai']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Baterai IP 11',
            'kode' => 'BAT-IP11',
            'harga_beli' => 80000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        // Stok Cabang A: 10
        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangA->id,
            'jumlah' => 10,
        ]);

        // Stok Cabang B: 50
        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangB->id,
            'jumlah' => 50,
        ]);

        // Session cabang A -> Stok total harus 10, bukan 60!
        session(['cabang_id' => $this->cabangA->id]);

        Livewire::test(ProdukTab::class)
            ->assertSee('Baterai IP 11')
            ->assertViewHas('produks', function ($produks) use ($produk) {
                $p = $produks->firstWhere('id', $produk->id);

                // total_stok dari withSum harus 10
                return (int) $p->total_stok === 10
                    && $p->stokItems->sum('jumlah') === 10;
            });

        // Session cabang B -> Stok total harus 50, bukan 60!
        session(['cabang_id' => $this->cabangB->id]);

        Livewire::test(ProdukTab::class)
            ->assertSee('Baterai IP 11')
            ->assertViewHas('produks', function ($produks) use ($produk) {
                $p = $produks->firstWhere('id', $produk->id);

                return (int) $p->total_stok === 50
                    && $p->stokItems->sum('jumlah') === 50;
            });
    }

    public function test_pengaturan_format_cetak_dokumen_wms_tersimpan(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        session(['cabang_id' => $this->cabangA->id]);

        Livewire::test(SettingsRbac::class, ['activeTab' => 'printer'])
            ->set('printerSubTab', 'wms_doc')
            ->set('wmsDocForm.paper_size', 'a5')
            ->set('wmsDocForm.orientation', 'portrait')
            ->set('wmsDocForm.margin_mm', 8)
            ->set('wmsDocForm.font_size', '10pt')
            ->call('simpanPengaturanWmsDoc')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success';
            });

        $config = app(KonfigurasiService::class)->getWmsDocConfig();
        $this->assertEquals('a5', $config['paper_size']);
        $this->assertEquals('portrait', $config['orientation']);
        $this->assertEquals(8, $config['margin_mm']);
    }
}
