<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SessionManagementPage;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\SupplierScoreTable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Livewire\Exceptions\PropertyNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-12] Regresi wiring computed-property (serupa B-05).
 *
 * `render()` kedua komponen sempat memakai `$this->…Property` (nama literal,
 * tidak ada di komponen) sehingga melempar Livewire PropertyNotFoundException.
 * Getter legacy `getScoresProperty()` / `getSuppliersProperty()` /
 * `getDevicesProperty()` diekspos Livewire v4 sebagai magic `$this->scores` /
 * `$this->suppliers` / `$this->devices`.
 *
 * CATATAN: view `modules.wms.livewire.supplier-score-table` dan
 * `modules.rbac.livewire.session-management` tidak pernah dibuat (F3-2/F3-4
 * hanya mengirim komponen+service+test, tanpa route & tanpa blade) — fakta
 * terpisah dari B-12. Karena itu test render di bawah memakai klasifikasi
 * pengecualian: jalur sukses `assertViewHas` dipakai begitu view tersedia;
 * sementara ini kegagalan WAJIB terjadi di tahap view, bukan di akses computed
 * property.
 */
class ComputedPropertyFixTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-B12'],
            ['nama' => 'Cabang B-12', 'is_active' => true]
        );

        $user = User::create([
            'name' => 'Admin B12', 'email' => 'admin-b12@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');
    }

    public function test_supplier_score_table_computed_teresolusi(): void
    {
        $this->authed();

        $component = new SupplierScoreTable;

        // Kunci B-12: `getScoresProperty()` / `getSuppliersProperty()` harus
        // teresolusi lewat magic `$this->scores` / `$this->suppliers`.
        $this->assertInstanceOf(LengthAwarePaginator::class, $component->scores);
        $this->assertInstanceOf(Collection::class, $component->suppliers);
    }

    public function test_supplier_score_table_render_tanpa_property_not_found(): void
    {
        $this->authed();

        try {
            Livewire::test(SupplierScoreTable::class)
                ->assertViewHas('scores')
                ->assertViewHas('suppliers');
        } catch (PropertyNotFoundException $e) {
            $this->fail('REGRESI B-12: render() kembali mengakses nama literal `…Property`. '.$e->getMessage());
        } catch (\Throwable $e) {
            // View F3-2/F3-4 memang belum pernah dibuat — kegagalan harus di tahap view.
            $this->assertStringContainsString(
                'modules.wms.livewire.supplier-score-table',
                $e->getMessage(),
                'Kegagalan render bukan karena view hilang: '.$e->getMessage()
            );
        }
    }

    public function test_session_management_computed_teresolusi(): void
    {
        $this->authed();

        $component = new SessionManagementPage;

        // Kunci B-12: `getDevicesProperty()` harus teresolusi lewat magic `$this->devices`.
        $this->assertInstanceOf(LengthAwarePaginator::class, $component->devices);
    }

    public function test_session_management_render_tanpa_property_not_found(): void
    {
        $this->authed();

        try {
            Livewire::test(SessionManagementPage::class)
                ->assertViewHas('devices');
        } catch (PropertyNotFoundException $e) {
            $this->fail('REGRESI B-12: render() kembali mengakses nama literal `…Property`. '.$e->getMessage());
        } catch (\Throwable $e) {
            // View F3-4 memang belum pernah dibuat — kegagalan harus di tahap view.
            $this->assertStringContainsString(
                'modules.rbac.livewire.session-management',
                $e->getMessage(),
                'Kegagalan render bukan karena view hilang: '.$e->getMessage()
            );
        }
    }
}
