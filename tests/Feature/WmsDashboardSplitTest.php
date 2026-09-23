<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Livewire\StokTab;
use App\Modules\Wms\Livewire\TransferTab;
use App\Modules\Wms\Livewire\WmsDashboard;
use App\Modules\Wms\Models\Gudang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [F1-8 / S-01] Smoke render split WmsDashboard → 5 komponen tab fokus.
 * Paritas: route /app/wms tetap merespons, tiap tab component render sendiri,
 * shell tetap memuat ke-5 tab (client hanya toggle visibility).
 */
class WmsDashboardSplitTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-wms-split@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');
    }

    public function test_halaman_wms_utama_masih_merespons(): void
    {
        $this->authed();

        $this->get('/app/wms')
            ->assertSuccessful()
            // $escape=false: teks & literal di blade (bukan {{ }}), jadi HTML mentah.
            ->assertSee('Inventori & Stok', false)
            ->assertSee('PO & Supplier', false);
    }

    public function test_shell_memuat_lima_tab_component(): void
    {
        $this->authed();

        $shell = Livewire::test(WmsDashboard::class)
            ->assertSet('activeTab', 'stok')
            // Header nav tab (milik shell)
            ->assertSee('Master Produk')
            ->assertSee('Transfer Antar Gudang')
            ->assertSee('Stock Opname')
            // Konten tiap child tab ikut ter-render di shell (tersembunyi via CSS, bukan unmount)
            ->assertSee('Kompatibilitas')     // StokTab
            ->assertSee('No. Transfer')       // TransferTab
            ->assertSee('No. Opname')         // OpnameTab
            ->assertSee('Jatuh Tempo')        // PoTab
            ->assertSee('Stok Total');        // ProdukTab

        $shell->call('$set', 'activeTab', 'po')->assertSet('activeTab', 'po');
    }

    public function test_tab_stok_merender(): void
    {
        $this->authed();

        Livewire::test(StokTab::class)
            ->assertSee('Kompatibilitas')
            ->assertSee('Cari sparepart berdasarkan nama');
    }

    public function test_tab_transfer_merender(): void
    {
        $this->authed();

        Livewire::test(TransferTab::class)
            ->assertSee('No. Transfer')
            ->assertSee('Belum ada riwayat transfer antar gudang');
    }

    public function test_tab_opname_merender(): void
    {
        $this->authed();

        Livewire::test(OpnameTab::class)
            ->assertSee('No. Opname')
            ->assertSee('Belum ada riwayat stock opname');
    }

    public function test_tab_po_merender(): void
    {
        $this->authed();

        Livewire::test(PoTab::class)
            ->assertSee('Jatuh Tempo')
            ->assertSee('Supplier Terdaftar');
    }

    public function test_tab_produk_merender(): void
    {
        $this->authed();

        Livewire::test(ProdukTab::class)
            ->assertSee('Stok Total')
            ->assertSee('produk');
    }
}
