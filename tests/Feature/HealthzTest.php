<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * F1-7 — GET /healthz (PRD-Advanced-UteParts §4).
 * Cek db + queue; hanya boolean — tanpa data tenant/cross-branch.
 *
 * Sengaja TIDAK memakai RefreshDatabase: suite saat ini diblokir migrasi
 * rusak 2026_09_21_000042_add_transfer_audit_columns.php ($table->hasColumn
 * tidak ada di Blueprint) — di luar lane ini. Healthz tidak butuh tabel data.
 */
class HealthzTest extends BaseTestCase
{
    public function test_healthz_mengembalikan_struktur_sehat(): void
    {
        $response = $this->get('/healthz');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['db', 'queue'],
                'message',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.db', true)
            ->assertJsonPath('data.queue', true)
            ->assertJsonPath('message', 'Sistem sehat');
    }

    public function test_healthz_hanya_mengembalikan_boolean_tanpa_data_tenant(): void
    {
        $json = $this->get('/healthz')->assertOk()->json();

        // Kunci luar persis success/data/message — tidak ada field lain
        // yang berpotensi membocorkan info lintas cabang/tenant.
        $this->assertEqualsCanonicalizing(['success', 'data', 'message'], array_keys($json));
        $this->assertEqualsCanonicalizing(['db', 'queue'], array_keys($json['data']));

        $this->assertIsBool($json['data']['db']);
        $this->assertIsBool($json['data']['queue']);
    }

    public function test_healthz_route_terdaftar_dan_route_up_tetap_ada(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri());

        $this->assertTrue($routes->contains('healthz'), 'Route /healthz wajib terdaftar.');
        $this->assertTrue($routes->contains('up'), 'Route /up (existing) wajib tetap ada.');
    }
}
