<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-06 / P0-10 — Scope `cabang_id` di modul Servis.
 *
 * Constraint proyek: semua query WAJIB scope ke cabang aktif di session, tidak
 * boleh bocor silang-cabang. Breach yang ditutup test ini:
 *  - papan kanban `ServisBoard::getGroupedTiketsProperty()` query polos;
 *  - modal detail `getSelectedTiketProperty()` / `openDetail()` query polos;
 *  - API [SERVICE-03] `ServisController::show()` (`findOrFail` tanpa filter).
 *
 * Pola filter mengikuti `ServisController::index()` (fail-open bila
 * `session('cabang_id')` null) → test ini memakai session cabang A dan tiket
 * cabang B untuk membuktikan penolakan.
 *
 * Regresi yang dijaga: user yang memang berhak (super-admin, cabang A) tetap
 * bisa membaca & mengubah tiket cabangnya sendiri.
 */
class ServisCabangScopeTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabangA = Cabang::create(['nama' => 'Cabang A', 'kode' => 'CBG-T06A', 'is_active' => true]);
        $this->cabangB = Cabang::create(['nama' => 'Cabang B', 'kode' => 'CBG-T06B', 'is_active' => true]);

        // Cabang AKTIF di session = A
        session(['cabang_id' => $this->cabangA->id]);

        $user = User::create([
            'name' => 'Admin T06',
            'email' => 'admin-t06@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($this->cabangA->id);
        $user->cabangs()->attach($this->cabangB->id);
        $this->actingAs($user, 'web');
    }

    private function buatTiket(Cabang $cabang, string $noTiket, string $status = 'diterima'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $cabang->id,
            'nama_pelanggan' => 'Pelanggan '.$cabang->kode,
            'telepon_pelanggan' => '0812'.$cabang->id.'00000',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar retak',
            'status' => $status,
            'tanggal_terima' => now(),
        ]);
    }

    private function idGrouped(array $grouped): array
    {
        $ids = [];
        foreach ($grouped as $rows) {
            foreach ($rows as $row) {
                $ids[] = $row->id;
            }
        }

        return $ids;
    }

    private function noGrouped(array $grouped): array
    {
        $no = [];
        foreach ($grouped as $rows) {
            foreach ($rows as $row) {
                $no[] = $row->no_tiket;
            }
        }

        return $no;
    }

    // ===== Livewire / kanban =====

    public function test_kanban_hanya_menampilkan_tiket_cabang_aktif(): void
    {
        $milikA = $this->buatTiket($this->cabangA, 'SRV-T06-A-0001');
        $this->buatTiket($this->cabangB, 'SRV-T06-B-0001');

        $component = Livewire::test(ServisBoard::class);
        $grouped = $component->viewData('groupedTikets');
        $ids = $this->idGrouped($grouped);

        $this->assertNotEmpty($ids, 'Kanban cabang A harus punya minimal 1 tiket sendiri');
        $this->assertContains($milikA->id, $ids, 'Tiket cabang aktif wajib tampil');
        $this->assertNotContains(
            'SRV-T06-B-0001',
            $this->noGrouped($grouped),
            'Tiket cabang lain TIDAK boleh bocor ke kanban (P0-10)'
        );

        // Render juga tidak boleh menampilkan nomor tiket cabang lain
        $component->assertSee('SRV-T06-A-0001')->assertDontSee('SRV-T06-B-0001');
    }

    public function test_kanban_scoped_ala_filter_dan_search(): void
    {
        $this->buatTiket($this->cabangA, 'SRV-T06-A-0002', 'diagnosa');
        $this->buatTiket($this->cabangB, 'SRV-T06-B-0002', 'diagnosa');

        $component = Livewire::test(ServisBoard::class)->set('filterStatus', 'diagnosa');
        $grouped = $component->viewData('groupedTikets');

        $noYangTampil = $grouped['diagnosa']->pluck('no_tiket')->all();
        $this->assertContains('SRV-T06-A-0002', $noYangTampil);
        $this->assertNotContains('SRV-T06-B-0002', $noYangTampil, 'Filter status tidak boleh melewati scope cabang');

        $component = Livewire::test(ServisBoard::class)->set('search', 'Samsung');
        $ids = $this->idGrouped($component->viewData('groupedTikets'));
        $this->assertCount(1, $ids, 'Pencarian tidak boleh menarik tiket cabang lain');
    }

    public function test_selected_tiket_lintas_cabang_tidak_terbuka(): void
    {
        $tiketB = $this->buatTiket($this->cabangB, 'SRV-T06-B-0003');

        // (a) Diset langsung lewat state (mis. restored state Livewire / deep link)
        $component = Livewire::test(ServisBoard::class)->set('selectedTiketId', $tiketB->id);
        $this->assertNull($component->viewData('selectedTiket'), 'Detail tiket cabang lain TIDAK boleh dimuat');

        // (b) Dibuka lewat `openDetail()` → ditolak, alert alert + state dikosongkan
        $component = Livewire::test(ServisBoard::class)
            ->call('openDetail', $tiketB->id)
            ->assertSet('selectedTiketId', null)
            ->assertDispatched('alert');

        $this->assertNull($component->viewData('selectedTiket'));
    }

    public function test_selected_tiket_cabang_aktif_tetap_terbuka(): void
    {
        $tiketA = $this->buatTiket($this->cabangA, 'SRV-T06-A-0004');

        $component = Livewire::test(ServisBoard::class)->call('openDetail', $tiketA->id);

        $component->assertSet('selectedTiketId', $tiketA->id)->assertSee('SRV-T06-A-0004');
        $this->assertSame($tiketA->id, $component->viewData('selectedTiket')->id);
    }

    public function test_ubah_status_lintas_cabang_ditolak(): void
    {
        $tiketA = $this->buatTiket($this->cabangA, 'SRV-T06-A-0005');
        $tiketB = $this->buatTiket($this->cabangB, 'SRV-T06-B-0005');

        $component = Livewire::test(ServisBoard::class);

        // Tiket cabang lain: tidak ditemukan (ModelNotFoundException) & status utuh
        $tertangkap = null;
        try {
            $component->call('updateStatus', $tiketB->id, 'diagnosa');
        } catch (\Throwable $e) {
            $tertangkap = $e;
        }

        $this->assertInstanceOf(ModelNotFoundException::class, $tertangkap);
        $this->assertSame('diterima', $tiketB->fresh()->status, 'Tiket cabang lain TIDAK boleh berubah status');

        // Regresi: tiket cabang sendiri tetap bisa diubah
        $component->call('updateStatus', $tiketA->id, 'diagnosa');
        $this->assertSame('diagnosa', $tiketA->fresh()->status);
    }

    public function test_approval_lintas_cabang_ditolak(): void
    {
        $tiketB = $this->buatTiket($this->cabangB, 'SRV-T06-B-0006', 'menunggu_approval');

        $component = Livewire::test(ServisBoard::class);
        $component->set('approveTiketId', $tiketB->id);

        $tertangkap = null;
        try {
            $component->call('prosesApprove', 'approve');
        } catch (\Throwable $e) {
            $tertangkap = $e;
        }

        $this->assertInstanceOf(ModelNotFoundException::class, $tertangkap);
        $this->assertSame('menunggu_approval', $tiketB->fresh()->status);
        $this->assertSame(0, $tiketB->statusLogs()->count());
    }

    // ===== API =====

    public function test_api_service_03_detail_tiket_cabang_lain_404(): void
    {
        $tiketB = $this->buatTiket($this->cabangB, 'SRV-T06-B-0007');

        // 404 = tiket dianggap tidak ada (identik dgn tiket id yang tidak pernah ada)
        $this->getJson('/api/servis/'.$tiketB->id)->assertStatus(404);
        $this->getJson('/api/servis/999999')->assertStatus(404);
    }

    public function test_api_service_03_detail_tiket_cabang_aktif_tetap_200(): void
    {
        $tiketA = $this->buatTiket($this->cabangA, 'SRV-T06-A-0007');

        $this->getJson('/api/servis/'.$tiketA->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.no_tiket', 'SRV-T06-A-0007');
    }

    public function test_api_service_02_dan_mutasi_tetap_scoped(): void
    {
        $tiketA = $this->buatTiket($this->cabangA, 'SRV-T06-A-0008');
        $tiketB = $this->buatTiket($this->cabangB, 'SRV-T06-B-0008');

        // [SERVICE-02] list tidak boleh bocor
        $ids = collect($this->getJson('/api/servis')->assertStatus(200)->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($tiketA->id));
        $this->assertFalse($ids->contains($tiketB->id));

        // Mutasi (status / estimasi / sparepart / pekerjaan) ke tiket cabang lain → 404
        $this->putJson('/api/servis/'.$tiketB->id.'/status', ['status' => 'diagnosa'])->assertStatus(404);
        $this->postJson('/api/servis/'.$tiketB->id.'/estimasi', [
            'estimasi_biaya' => 100000, 'alasan' => 'Ganti layar unit',
        ])->assertStatus(404);
        $this->postJson('/api/servis/'.$tiketB->id.'/pekerjaan', [
            'items' => [['tipe' => 'jasa', 'nama_item' => 'Ganti LCD', 'qty' => 1, 'harga' => 100000]],
        ])->assertStatus(404);

        $this->assertSame('diterima', $tiketB->fresh()->status);
        $this->assertNull($tiketB->fresh()->estimasi_biaya);
        $this->assertSame(0, $tiketB->items()->count());
    }

    public function test_api_mutasi_tiket_cabang_aktif_tetap_berfungsi(): void
    {
        $tiketA = $this->buatTiket($this->cabangA, 'SRV-T06-A-0009');

        $this->putJson('/api/servis/'.$tiketA->id.'/status', ['status' => 'diagnosa'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('diagnosa', $tiketA->fresh()->status);
    }
}
