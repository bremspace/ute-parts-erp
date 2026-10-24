<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class WmsExportMasterDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CP',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $role = Role::firstOrCreate(['name' => 'super-admin']);
        Permission::firstOrCreate(['name' => 'wms.view']);
        $role->givePermissionTo(Permission::all());

        $this->user = User::create([
            'name' => 'Admin WMS',
            'email' => 'admin-wms@uteparts.id',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');

        Produk::create([
            'nama' => 'LCD iPhone 11 Original',
            'kode' => 'PRD-IP11-LCD',
            'cabang_id' => $this->cabang->id,
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);
    }

    public function test_dispatch_wms_export_master_xlsx_succeeds_without_unpack_error(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(ProdukTab::class)
            ->dispatch('wms-export-master', ['format' => 'xlsx']);

        $response = $component->instance()->openExportMasterModal(['format' => 'xlsx']);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function test_dispatch_wms_export_master_csv_succeeds_without_unpack_error(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(ProdukTab::class)
            ->dispatch('wms-export-master', ['format' => 'csv']);

        $response = $component->instance()->openExportMasterModal(['format' => 'csv']);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function test_dispatch_wms_export_terpilih_succeeds(): void
    {
        $this->actingAs($this->user);

        $produk = Produk::first();

        $component = Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$produk->id])
            ->dispatch('wms-export-terpilih', ['format' => 'xlsx']);

        $response = $component->instance()->openExportTerpilihModal(['format' => 'xlsx']);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }
}
