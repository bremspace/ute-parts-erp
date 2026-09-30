<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\CycleCountPage;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-05 / F3-7] Smoke render halaman cycle count.
 *
 * Menangkap regressi wiring computed property: `render()` sempat memakai
 * `$this->schedulesProperty` (nama literal, tidak ada di komponen) sehingga
 * melempar Livewire PropertyNotFoundException.
 */
class CycleCountPageTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private function authed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-CC', 'is_active' => true]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-cycle-count@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);

        $this->actingAs($user, 'web');
    }

    public function test_route_cycle_count_merespons_200(): void
    {
        $this->authed();

        $this->get('/app/wms/cycle-count')
            ->assertSuccessful()
            ->assertSee('Cycle Count Otomatis')
            ->assertSee('Tugas Cycle Count');
    }

    public function test_komponen_render_tanpa_exception(): void
    {
        $this->authed();

        // Kunci B-05: computed legacy (`getSchedulesProperty`) harus diakses
        // sebagai `$this->schedules`, bukan `$this->schedulesProperty`.
        Livewire::test(CycleCountPage::class)
            ->assertViewHas('schedules')
            ->assertViewHas('tasks')
            ->assertViewHas('raks')
            ->assertSee('Tugas Cycle Count')
            ->assertSee('Jadwal');
    }

    public function test_buat_jadwal_muncul_di_daftar(): void
    {
        $this->authed();

        Livewire::test(CycleCountPage::class)
            ->call('bukaFormSchedule')
            ->assertSet('showScheduleForm', true)
            ->assertSee('Buat Jadwal Cycle Count Baru')
            ->assertSee('Nama Jadwal')
            ->assertSee('Tipe Target Sampling')
            ->assertSee('Frekuensi Pelaksanaan')
            ->assertSee('Jam Eksekusi Otomatis')
            ->assertSee('Jumlah Sampel Produk')
            ->assertSee('Toleransi Unit (Threshold Unit)')
            ->assertSee('Toleransi Persen (Threshold %)')
            ->set('scheduleNama', 'Rak A Mingguan')
            ->set('tipeTarget', 'rak')
            ->set('frekuensi', 'mingguan')
            ->set('hari', 1)
            ->set('jam', '08:00')
            ->set('sampleSize', 10)
            ->set('thresholdUnit', 2)
            ->set('thresholdPersen', 5)
            ->call('simpanSchedule')
            ->assertSet('showScheduleForm', false)
            ->assertSee('Rak A Mingguan')
            ->assertSee('10 produk')
            ->assertSee('2 unit / 5%');

        $this->assertDatabaseHas('cycle_count_schedule', [
            'cabang_id' => $this->cabang->id,
            'nama' => 'Rak A Mingguan',
            'sample_size' => 10,
            'threshold_unit' => 2,
            'threshold_persen' => 5,
        ]);
    }

    public function test_form_count_terbuka_untuk_task_menunggu_count(): void
    {
        $this->authed();

        $schedule = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Rak B',
            'tipe_target' => 'rak',
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 2,
            'threshold_unit' => 5,
            'threshold_persen' => 10,
            'is_aktif' => true,
        ]);

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CC-TEST-0001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'target_label' => 'Rak A-01',
            'seed' => 12345,
            'sample_items' => [[
                'stok_item_id' => 11, 'produk_id' => 1, 'gudang_id' => 1,
                'rak_id' => 1, 'nama' => 'Oli 10W-30', 'stok_sistem' => 8,
            ]],
            'status' => 'menunggu_count',
            'threshold_unit' => 5,
            'threshold_persen' => 10,
        ]);

        Livewire::test(CycleCountPage::class)
            ->assertSee('CC-TEST-0001')
            ->call('mulaiCount', $task->id)
            ->assertSet('selectedTaskId', $task->id)
            ->assertSee('Input Hitungan Fisik Sampel')
            ->assertSee('Blind Count')
            ->assertSee('Oli 10W-30')
            // Memastikan blind count: angka stok sistem disembunyikan dari pelaksana saat input count
            ->assertDontSee('Sistem: 8 unit');
    }

    public function test_supervisor_dapat_menyetujui_dan_menolak_task_menunggu_approval(): void
    {
        $this->authed();

        $schedule = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Rak C',
            'tipe_target' => 'rak',
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 2,
            'threshold_unit' => 2,
            'threshold_persen' => 5,
            'is_aktif' => true,
        ]);

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CC-APPR-0001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'seed' => 12345,
            'sample_items' => [],
            'hasil' => [],
            'status' => 'menunggu_approval',
            'threshold_unit' => 2,
            'threshold_persen' => 5,
        ]);

        Livewire::test(CycleCountPage::class)
            ->assertSee('CC-APPR-0001')
            ->assertSee('Approve')
            ->assertSee('Tolak')
            ->call('approveTask', $task->id)
            ->assertDispatched('alert');

        $this->assertEquals('selesai', $task->fresh()->status);

        // Uji tolak
        $schedule2 = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Rak D',
            'tipe_target' => 'rak',
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 2,
            'threshold_unit' => 2,
            'threshold_persen' => 5,
            'is_aktif' => true,
        ]);

        $taskTolak = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule2->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CC-REJ-0001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'seed' => 54321,
            'sample_items' => [],
            'hasil' => [],
            'status' => 'menunggu_approval',
            'threshold_unit' => 2,
            'threshold_persen' => 5,
        ]);

        Livewire::test(CycleCountPage::class)
            ->call('tolakTask', $taskTolak->id, 'Alasan ditolak')
            ->assertDispatched('alert');

        $this->assertEquals('ditolak', $taskTolak->fresh()->status);
    }
}
