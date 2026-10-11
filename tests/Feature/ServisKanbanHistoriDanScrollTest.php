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

class ServisKanbanHistoriDanScrollTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test',
            'kode' => 'CBG-TST',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Teknisi Test',
            'email' => 'teknisi-histori@test.com',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
        ]);
        $this->user->assignRole('teknisi');

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_kanban_merender_kontrol_periode_histori_dan_layout_terbatas(): void
    {
        $this->actingAs($this->user, 'web');

        Livewire::test(ServisBoard::class)
            ->assertSee('Histori: 7 Hari Terakhir')
            ->assertSee('Histori: Hari Ini')
            ->assertSee('Histori: 30 Hari Terakhir')
            ->assertSee('Histori: Semua Transaksi')
            ->assertSeeHtml('overscroll-contain')
            ->assertSeeHtml('periodeHistori');
    }

    public function test_proses_aktif_selalu_tampil_penuh_dan_tidak_tenggelam_oleh_histori(): void
    {
        $this->actingAs($this->user, 'web');

        // Buat tiket aktif (diagnosa)
        $tiketAktif = TiketServis::create([
            'no_tiket' => 'SRV-AKTIF-001',
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Samsung S23',
            'keluhan' => 'Layar bergaris',
            'status' => 'diagnosa',
            'created_at' => now()->subDays(15), // Tiket aktif 15 hari lalu tetap harus tampil!
        ]);

        // Buat tiket diambil lama (> 10 hari lalu)
        $tiketDiambilLama = TiketServis::create([
            'no_tiket' => 'SRV-HISTORI-LAMA',
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 11',
            'keluhan' => 'Ganti baterai',
            'status' => 'diambil',
            'tanggal_diambil' => now()->subDays(12),
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(12),
        ]);

        // Buat tiket diambil baru (kemarin)
        $tiketDiambilBaru = TiketServis::create([
            'no_tiket' => 'SRV-HISTORI-BARU',
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Xiaomi 12',
            'keluhan' => 'Konektor charger',
            'status' => 'diambil',
            'tanggal_diambil' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // Default: periode 7 hari terakhir
        $component = Livewire::test(ServisBoard::class);
        $grouped = $component->viewData('groupedTikets');

        // Tiket aktif harus selalu ada di kolom diagnosa
        $this->assertTrue(
            $grouped['diagnosa']->contains('id', $tiketAktif->id),
            'Tiket aktif wajib selalu tampil di board'
        );

        // Tiket diambil baru (kemarin) harus ada
        $this->assertTrue(
            $grouped['diambil']->contains('id', $tiketDiambilBaru->id),
            'Tiket diambil dalam 7 hari terakhir harus tampil'
        );

        // Tiket diambil lama (> 7 hari) TIDAK boleh membebani board pada default 7 hari
        $this->assertFalse(
            $grouped['diambil']->contains('id', $tiketDiambilLama->id),
            'Tiket diambil 12 hari lalu tidak boleh tampil di default 7 hari'
        );

        // Ubah filter ke 'semua'
        $component->set('periodeHistori', 'semua');
        $groupedSemua = $component->viewData('groupedTikets');

        $this->assertTrue(
            $groupedSemua['diambil']->contains('id', $tiketDiambilLama->id),
            'Saat filter semua dipilih, histori lama harus tampil'
        );

        // Ubah filter ke 'hari_ini'
        $component->set('periodeHistori', 'hari_ini');
        $groupedHariIni = $component->viewData('groupedTikets');

        $this->assertFalse(
            $groupedHariIni['diambil']->contains('id', $tiketDiambilBaru->id),
            'Tiket diambil kemarin tidak boleh tampil di filter hari_ini'
        );
    }
}
