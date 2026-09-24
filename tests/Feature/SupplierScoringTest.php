<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\SupplierScore;
use App\Modules\Wms\Services\SupplierScoringService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * [F3-2] Supplier Scoring — hitung skor, idempoten, scoping cabang.
 */
class SupplierScoringTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::firstOrCreate(
            ['kode' => 'TST'],
            ['nama' => 'Cabang Test', 'is_active' => true]
        );

        $user = User::factory()->create([
            'name' => 'Test User', 'email' => 'test@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        return ['user' => $user, 'cabang' => $cabang];
    }

    /** T-F3-2-01: Skor supplier terhitung dengan benar (return structure). */
    public function test_skor_terhitung(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        $supplier = Supplier::create([
            'nama' => 'Supplier Test', 'kontak' => '0812', 'telepon' => '0812',
            'alamat' => 'Jl Test', 'termin_hari' => 30, 'is_active' => true,
            'cabang_id' => $cabang->id,
        ]);

        $service = app(SupplierScoringService::class);
        $hasil = $service->hitungSkor($supplier->id, '2026-09', $cabang->id);

        $this->assertArrayHasKey('on_time_percent', $hasil);
        $this->assertArrayHasKey('quality_return_percent', $hasil);
        $this->assertArrayHasKey('avg_harga', $hasil);
        $this->assertArrayHasKey('total_score', $hasil);
        $this->assertGreaterThanOrEqual(0, $hasil['total_score']);
        $this->assertLessThanOrEqual(100, $hasil['total_score']);
    }

    /** T-F3-2-02: Skor idempoten — hitung 2x database hanya 1 record. */
    public function test_skor_idempoten(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        $supplier = Supplier::create([
            'nama' => 'Supplier Idempoten', 'kontak' => '0812', 'telepon' => '0812',
            'alamat' => 'Jl Test', 'termin_hari' => 30, 'is_active' => true,
            'cabang_id' => $cabang->id,
        ]);

        $service = app(SupplierScoringService::class);
        $service->hitungSkor($supplier->id, '2026-09', $cabang->id);
        $service->hitungSkor($supplier->id, '2026-09', $cabang->id);

        $this->assertCount(1, SupplierScore::where('supplier_id', $supplier->id)->get());
    }

    /** T-F3-2-03: Scoping cabang — skor tidak bocor antar cabang. */
    public function test_scoping_cabang(): void
    {
        $ctx = $this->authed();
        $cabang1 = $ctx['cabang'];
        $supplier1 = Supplier::create([
            'nama' => 'Supplier Cab 1', 'kontak' => '0812', 'telepon' => '0812',
            'alamat' => 'Jl Test', 'termin_hari' => 30, 'is_active' => true,
            'cabang_id' => $cabang1->id,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $cabang2 = Cabang::firstOrCreate(
            ['kode' => 'CBG-02'],
            ['nama' => 'Cabang 2', 'is_active' => true]
        );
        $supplier2 = Supplier::create([
            'nama' => 'Supplier Cab 2', 'kontak' => '0812', 'telepon' => '0812',
            'alamat' => 'Jl Test', 'termin_hari' => 30, 'is_active' => true,
            'cabang_id' => $cabang2->id,
        ]);

        $service = app(SupplierScoringService::class);
        $service->hitungSkor($supplier1->id, '2026-09', $cabang1->id);
        $service->hitungSkor($supplier2->id, '2026-09', $cabang2->id);

        // Skor cabang 1 untuk supplier1 tidak boleh di-cabang2
        $skor1 = SupplierScore::where('supplier_id', $supplier1->id)
            ->where('cabang_id', $cabang1->id)->get();
        $skor2 = SupplierScore::where('supplier_id', $supplier1->id)
            ->where('cabang_id', $cabang2->id)->get();

        $this->assertCount(1, $skor1);
        $this->assertCount(0, $skor2);
    }

    /** T-F3-2-04: getSkorSupplier mengembalikan skor terbaru. */
    public function test_get_skor_terbaru(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        $supplier = Supplier::create([
            'nama' => 'Supplier Get', 'kontak' => '0812', 'telepon' => '0812',
            'alamat' => 'Jl Test', 'termin_hari' => 30, 'is_active' => true,
            'cabang_id' => $cabang->id,
        ]);

        $service = app(SupplierScoringService::class);
        $service->hitungSkor($supplier->id, '2026-09', $cabang->id);

        $skor = $service->getSkorSupplier($supplier->id, $cabang->id);

        $this->assertNotNull($skor);
        $this->assertEquals('2026-09', $skor->periode);
        $this->assertNotNull($skor->total_score);
    }
}
