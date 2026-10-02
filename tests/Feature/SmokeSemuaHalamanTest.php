<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test render seluruh halaman — pagar minimal untuk "tidak ada halaman error".
 *
 * `SemuaHalamanTest` hanya menyentuh 9 route /app dari 27 yang ada, jadi banyak
 * halaman tidak pernah di-render oleh test mana pun. Test ini menutup celah itu:
 * setiap route GET ditembak dan WAJIB tidak menghasilkan 5xx.
 *
 * Assertion sengaja `assertLessThan(500)`-style (array 5xx harus kosong), bukan
 * `assertSuccessful`: 302 ke halaman login adalah perilaku yang benar, dan 403/404
 * pada route yang memang butuh data tertentu juga bukan "halaman error". Yang
 * benar-benar rusak adalah 500 — Blade tidak ditemukan, computed property Livewire
 * salah, relasi yang tidak ada, atau komponen yang gagal mount.
 */
class SmokeSemuaHalamanTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-SMK', 'nama' => 'Cabang Smoke', 'is_active' => true,
        ]);

        // super-admin lolos Gate::before, jadi tidak ada guard permission yang
        // dapat menutup halaman dari smoke test ini. Yang diuji adalah render,
        // bukan hak akses.
        $this->user = User::create([
            'name' => 'Smoke Admin', 'email' => 'smoke-admin@uteparts.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    /**
     * Tembak tiap URL dan kumpulkan statusnya, supaya pesan kegagalan menyebut
     * URL yang bermasalah — bukan sekadar "ada yang gagal".
     */
    private function semuaHarusBukanServerError(array $urls): void
    {
        $status = [];

        foreach ($urls as $url) {
            $status[$url] = $this->get($url)->getStatusCode();
        }

        $serverError = array_filter($status, fn (int $kode) => $kode >= 500);

        $this->assertSame([], $serverError, 'Halaman error (5xx): '.json_encode($serverError));
    }

    public function test_semua_halaman_backoffice_bukan_server_error(): void
    {
        $this->semuaHarusBukanServerError([
            '/app/dashboard',
            '/app/pos',
            '/app/wms',
            '/app/wms/cycle-count',
            '/app/servis',
            '/app/crm',
            '/app/crm/leads',
            '/app/reseller',
            '/app/akunting',
            '/app/akunting/aset',
            '/app/omnichannel',
            '/app/pengaturan',
            '/app/hr/payroll',
            '/app/hr/absensi',
            '/app/hr/komisi-skema',
            '/app/hr/saya',
            '/app/laporan',
            '/app/laporan-pajak',
            '/app/laporan/nomor-seri',
            '/app/approvals',
            '/app/audit-log',
            '/app/keamanan/sesi',
            '/app/keamanan/dua-faktor',
        ]);
    }

    /** Route yang butuh path segment nyata — ID-nya dibuat di sini, bukan hardcode. */
    public function test_halaman_berparameter_bukan_server_error(): void
    {
        $produk = Produk::create([
            'nama' => 'LCD Smoke', 'slug' => 'lcd-smoke', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 100000, 'harga_jual_retail' => 150000,
        ]);

        $this->semuaHarusBukanServerError([
            "/app/laporan/drill/Produk/{$produk->id}",
            '/print-barcode',
        ]);
    }

    public function test_semua_halaman_publik_bukan_server_error(): void
    {
        // Halaman publik dirender sebagai tamu, bukan admin — supaya guard
        // backoffice tidak menutupi masalah render di sisi marketplace.
        $this->post('/logout');

        $this->semuaHarusBukanServerError([
            '/',
            '/shop',
            '/shop/lcd-iphone',
            '/cart',
            '/checkout',
            '/booking-servis',
            '/tracking',
            '/login-pelanggan',
            '/daftar-pelanggan',
            '/healthz',
        ]);
    }
}
