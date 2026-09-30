<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\CycleCountPage;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Services\CycleCountService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WmsBlindOpnameDanAkuntansiTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $staffGudang;

    private Cabang $cabang;

    private Gudang $gudang;

    private Rak $rak;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Akun COA wajib
        AkunCoa::firstOrCreate(
            ['kode' => '130-01'],
            ['nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        AkunCoa::firstOrCreate(
            ['kode' => '520-08'],
            ['nama' => 'Selisih Stok (Opname)', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]
        );

        $this->cabang = Cabang::create(['kode' => 'CBG-TEST', 'nama' => 'Cabang Test', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_active' => true]);
        $this->rak = Rak::create(['gudang_id' => $this->gudang->id, 'kode' => 'RAK-01', 'nama' => 'Rak A1']);

        // Supervisor (admin-toko): membuat jadwal & approve
        $this->supervisor = User::create([
            'name' => 'Supervisor Toko',
            'email' => 'spv@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->supervisor->assignRole('admin-toko');
        $this->supervisor->cabangs()->attach($this->cabang->id);

        // Staff Gudang: pelaksana count fisik
        $this->staffGudang = User::create([
            'name' => 'Staff Gudang',
            'email' => 'staff@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->staffGudang->assignRole('staff-gudang');
        $this->staffGudang->cabangs()->attach($this->cabang->id);

        // Produk dengan harga_beli Rp 100.000
        $this->produk = Produk::create([
            'nama' => 'LCD Touchscreen OLED',
            'slug' => 'lcd-oled',
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'harga_beli' => 100000,
            'harga_jual_retail' => 180000,
            'is_active' => true,
            'sn' => false,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'rak_id' => $this->rak->id,
            'jumlah' => 10,
            'jumlah_minimum' => 2,
        ]);

        session([
            'cabang_id' => $this->cabang->id,
            'cabang_nama' => $this->cabang->nama,
        ]);
    }

    public function test_blind_opname_modal_menyembunyikan_stok_sistem_dan_stok_fisik_kosong(): void
    {
        $this->actingAs($this->staffGudang);

        Livewire::test(OpnameTab::class)
            ->set('filterGudangId', $this->gudang->id)
            ->call('openNewOpnameModal')
            ->assertSet('showOpnameModal', true)
            ->assertSee('Mode Blind Opname Aktif')
            ->assertSee('Hitungan Fisik Riil')
            // Di tabel input blind opname, kolom header "Stok Sistem" dan "Selisih" disembunyikan
            ->assertDontSee('Stok Sistem</th>', false)
            ->assertDontSee('Selisih</th>', false);
    }

    public function test_blind_cycle_count_menyembunyikan_angka_stok_sistem_saat_input_count(): void
    {
        $this->actingAs($this->staffGudang);

        $schedule = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Audit Blind',
            'tipe_target' => 'rak',
            'target_id' => $this->rak->id,
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 1,
            'threshold_unit' => 0,
            'threshold_persen' => 0,
            'is_aktif' => true,
        ]);

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CCT-BLIND-001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'target_id' => $this->rak->id,
            'seed' => 12345,
            'sample_items' => [[
                'stok_item_id' => 1,
                'produk_id' => $this->produk->id,
                'gudang_id' => $this->gudang->id,
                'rak_id' => $this->rak->id,
                'nama' => $this->produk->nama,
                'stok_sistem' => 10,
            ]],
            'status' => 'menunggu_count',
            'threshold_unit' => 0,
            'threshold_persen' => 0,
        ]);

        Livewire::test(CycleCountPage::class)
            ->call('mulaiCount', $task->id)
            ->assertSet('selectedTaskId', $task->id)
            ->assertSee('Blind Count')
            ->assertSee('LCD Touchscreen OLED')
            // Nilai 10 unit stok sistem disembunyikan dari pelaksana
            ->assertDontSee('Sistem: 10 unit');
    }

    public function test_zero_tolerance_threshold_mewajibkan_approval_bahkan_untuk_selisih_satu_unit(): void
    {
        $this->actingAs($this->staffGudang);

        $schedule = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Jadwal Zero Tolerance',
            'tipe_target' => 'rak',
            'target_id' => $this->rak->id,
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 1,
            'threshold_unit' => 0,    // Standar zero-tolerance
            'threshold_persen' => 0,  // Standar zero-tolerance
            'is_aktif' => true,
        ]);

        $service = app(CycleCountService::class);
        $task = $service->generateTask($schedule);
        $stok = StokItem::where('produk_id', $this->produk->id)->first();

        // Fisik diinput 9 (selisih -1 unit)
        $service->hitung($task, [$stok->id => 9], $this->staffGudang->id);

        // Karena threshold 0, selisih 1 unit langsung berstatus 'major' dan masuk status 'menunggu_approval'
        $this->assertSame('menunggu_approval', $task->fresh()->status);
        $this->assertSame('major', $task->fresh()->hasil[0]['klasifikasi']);
        // Stok sistem belum disesuaikan sebelum disetujui supervisor
        $this->assertSame(10, (int) $stok->fresh()->jumlah);
    }

    public function test_approval_opname_memposting_jurnal_akuntansi_dengan_nilai_rupiah_seimbang(): void
    {
        $this->actingAs($this->supervisor);

        // Buat Opname dengan selisih -2 unit (stok sistem 10, fisik 8)
        $opname = StokOpname::create([
            'no_opname' => 'OPN-TEST-JURNAL',
            'gudang_id' => $this->gudang->id,
            'rak_id' => $this->rak->id,
            'user_id' => $this->staffGudang->id,
            'status' => 'menunggu_approval',
        ]);

        StokOpnameItem::create([
            'stok_opname_id' => $opname->id,
            'produk_id' => $this->produk->id,
            'stok_sistem' => 10,
            'stok_fisik' => 8,
            'selisih' => -2, // Kehilangan 2 unit @ Rp 100.000 = Rp 200.000
        ]);

        Livewire::test(OpnameTab::class)
            ->call('approveOpname', $opname->id);

        $this->assertSame('disetujui', $opname->fresh()->status);
        $this->assertSame(8, (int) StokItem::where('produk_id', $this->produk->id)->value('jumlah'));

        // Cek Jurnal: Debit 520-08 (Beban Selisih Stok) Rp 200.000, Kredit 130-01 (Persediaan) Rp 200.000
        $lines = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', StokOpname::class)
            ->where('referensi_id', $opname->id)
            ->get();

        $this->assertNotEmpty($lines, 'Baris jurnal penyesuaian opname harus tercipta');
        $this->assertEquals(200000.00, (float) $lines->sum('debit'));
        $this->assertEquals(200000.00, (float) $lines->sum('kredit'));

        $lineBeban = $lines->firstWhere('akun.kode', '520-08');
        $linePersediaan = $lines->firstWhere('akun.kode', '130-01');

        $this->assertNotNull($lineBeban);
        $this->assertEquals(200000.00, (float) $lineBeban->debit);
        $this->assertEquals(0.00, (float) $lineBeban->kredit);

        $this->assertNotNull($linePersediaan);
        $this->assertEquals(0.00, (float) $linePersediaan->debit);
        $this->assertEquals(200000.00, (float) $linePersediaan->kredit);
    }

    public function test_approval_cycle_count_memposting_jurnal_akuntansi_rupiah_seimbang(): void
    {
        $this->actingAs($this->supervisor);

        $schedule = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Jadwal Cek Jurnal',
            'tipe_target' => 'rak',
            'target_id' => $this->rak->id,
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '08:00',
            'sample_size' => 1,
            'threshold_unit' => 0,
            'threshold_persen' => 0,
            'is_aktif' => true,
        ]);

        $stok = StokItem::where('produk_id', $this->produk->id)->first();

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CCT-JURNAL-001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'target_id' => $this->rak->id,
            'seed' => 999,
            'sample_items' => [[
                'stok_item_id' => $stok->id,
                'produk_id' => $this->produk->id,
                'gudang_id' => $this->gudang->id,
                'rak_id' => $this->rak->id,
                'nama' => $this->produk->nama,
                'stok_sistem' => 10,
            ]],
            'hasil' => [[
                'stok_item_id' => $stok->id,
                'produk_id' => $this->produk->id,
                'gudang_id' => $this->gudang->id,
                'nama' => $this->produk->nama,
                'stok_sistem' => 10,
                'stok_fisik' => 13,
                'selisih' => 3, // Ditemukan lebih 3 unit @ Rp 100.000 = Rp 300.000
                'klasifikasi' => 'major',
            ]],
            'status' => 'menunggu_approval',
            'threshold_unit' => 0,
            'threshold_persen' => 0,
        ]);

        Livewire::test(CycleCountPage::class)
            ->call('approveTask', $task->id, 'Disetujui barang ditemukan');

        $this->assertSame('selesai', $task->fresh()->status);
        $this->assertSame(13, (int) $stok->fresh()->jumlah);

        // Cek Jurnal: Barang lebih → Debit 130-01 (Persediaan) Rp 300.000, Kredit 520-08 (Selisih Stok) Rp 300.000
        $lines = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', CycleCountTask::class)
            ->where('referensi_id', $task->id)
            ->get();

        $this->assertNotEmpty($lines, 'Baris jurnal penyesuaian cycle count harus terbit');
        $this->assertEquals(300000.00, (float) $lines->sum('debit'));
        $this->assertEquals(300000.00, (float) $lines->sum('kredit'));

        $linePersediaan = $lines->firstWhere('akun.kode', '130-01');
        $lineBeban = $lines->firstWhere('akun.kode', '520-08');

        $this->assertNotNull($linePersediaan);
        $this->assertEquals(300000.00, (float) $linePersediaan->debit);
        $this->assertEquals(0.00, (float) $linePersediaan->kredit);

        $this->assertNotNull($lineBeban);
        $this->assertEquals(0.00, (float) $lineBeban->debit);
        $this->assertEquals(300000.00, (float) $lineBeban->kredit);
    }
}
