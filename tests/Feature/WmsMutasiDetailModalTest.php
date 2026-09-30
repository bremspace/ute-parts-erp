<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\CycleCountPage;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\TransferTab;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WmsMutasiDetailModalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudangAsal;

    private Gudang $gudangTujuan;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $this->gudangAsal = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_active' => true]);
        $this->gudangTujuan = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Servis', 'kode' => 'GDG-02', 'is_active' => true]);

        $this->produk = Produk::create([
            'nama' => 'LCD iPhone 13 OLED',
            'kode' => 'LCD-IP13',
            'harga_beli' => 500000,
            'harga_jual_retail' => 750000,
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Supervisor WMS',
            'email' => 'spv-wms@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);

        $this->actingAs($this->user, 'web');
    }

    public function test_transfer_tab_dapat_menampilkan_detail_transfer_dan_mutasi_stok(): void
    {
        $transfer = StokTransfer::create([
            'no_transfer' => 'TRF-TEST-0001',
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'dikirim',
            'catatan' => 'Transfer darurat LCD',
        ]);

        StokTransferItem::create([
            'stok_transfer_id' => $transfer->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 5,
        ]);

        StokLog::create([
            'gudang_id' => $this->gudangAsal->id,
            'produk_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'jenis' => 'transfer_keluar',
            'referensi_tipe' => StokTransfer::class,
            'referensi_id' => $transfer->id,
            'jumlah_sebelum' => 10,
            'perubahan' => -5,
            'jumlah_setelah' => 5,
            'catatan' => 'Kirim transfer TRF-TEST-0001',
        ]);

        Livewire::test(TransferTab::class)
            ->assertSee('TRF-TEST-0001')
            ->assertSee('Detail')
            ->call('bukaDetail', $transfer->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedTransferId', $transfer->id)
            ->assertSee('LCD iPhone 13 OLED')
            ->assertSee('Transfer darurat LCD')
            ->assertSee('Audit Mutasi Stok (StokLog)')
            ->assertSee('Transfer Keluar (-)')
            ->call('tutupDetail')
            ->assertSet('showDetailModal', false);
    }

    public function test_opname_tab_dapat_menampilkan_detail_opname_dan_mutasi_stok(): void
    {
        $opname = StokOpname::create([
            'no_opname' => 'OPN-TEST-0001',
            'gudang_id' => $this->gudangAsal->id,
            'user_id' => $this->user->id,
            'status' => 'disetujui',
            'catatan' => 'Opname bulanan LCD',
        ]);

        StokOpnameItem::create([
            'stok_opname_id' => $opname->id,
            'produk_id' => $this->produk->id,
            'stok_sistem' => 10,
            'stok_fisik' => 12,
            'selisih' => 2,
        ]);

        StokLog::create([
            'gudang_id' => $this->gudangAsal->id,
            'produk_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'jenis' => 'opname',
            'referensi_tipe' => StokOpname::class,
            'referensi_id' => $opname->id,
            'jumlah_sebelum' => 10,
            'perubahan' => 2,
            'jumlah_setelah' => 12,
            'catatan' => 'Approval Opname OPN-TEST-0001',
        ]);

        Livewire::test(OpnameTab::class)
            ->assertSee('OPN-TEST-0001')
            ->assertSee('Detail')
            ->call('bukaDetail', $opname->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedOpnameId', $opname->id)
            ->assertSee('LCD iPhone 13 OLED')
            ->assertSee('Opname bulanan LCD')
            ->assertSee('Hasil Perhitungan Opname Fisik')
            ->assertSee('Audit Mutasi Stok (StokLog)')
            ->assertSee('+2')
            ->call('tutupDetail')
            ->assertSet('showDetailModal', false);
    }

    public function test_cycle_count_page_dapat_menampilkan_detail_tugas_dan_mutasi_stok(): void
    {
        $schedule = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Jadwal Mingguan',
            'tipe_target' => 'rak',
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 5,
            'threshold_unit' => 2,
            'threshold_persen' => 5,
            'is_aktif' => true,
        ]);

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CCT-TEST-0001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'seed' => 123456,
            'sample_items' => [
                [
                    'stok_item_id' => 99,
                    'produk_id' => $this->produk->id,
                    'nama' => 'LCD iPhone 13 OLED',
                    'stok_sistem' => 10,
                ],
            ],
            'hasil' => [
                [
                    'stok_item_id' => 99,
                    'produk_id' => $this->produk->id,
                    'nama' => 'LCD iPhone 13 OLED',
                    'stok_snapshot' => 10,
                    'stok_sistem' => 10,
                    'stok_fisik' => 9,
                    'selisih' => -1,
                    'klasifikasi' => 'minor',
                ],
            ],
            'status' => 'selesai',
            'threshold_unit' => 2,
            'threshold_persen' => 5,
            'user_id' => $this->user->id,
        ]);

        StokLog::create([
            'gudang_id' => $this->gudangAsal->id,
            'produk_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'jenis' => 'cycle_count',
            'referensi_tipe' => CycleCountTask::class,
            'referensi_id' => $task->id,
            'jumlah_sebelum' => 10,
            'perubahan' => -1,
            'jumlah_setelah' => 9,
            'catatan' => 'Cycle Count CCT-TEST-0001',
        ]);

        Livewire::test(CycleCountPage::class)
            ->assertSee('CCT-TEST-0001')
            ->assertSee('Detail')
            ->call('bukaDetail', $task->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('detailTaskId', $task->id)
            ->assertSee('LCD iPhone 13 OLED')
            ->assertSee('Hasil Perhitungan Fisik & Klasifikasi')
            ->assertSee('MINOR')
            ->assertSee('Audit Mutasi Stok (StokLog)')
            ->call('tutupDetail')
            ->assertSet('showDetailModal', false);
    }
}
