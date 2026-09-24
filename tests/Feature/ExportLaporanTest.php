<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Report\Livewire\DrillDownViewer;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Livewire\StokTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F2-5 — Export lengkap semua laporan (PRD-Advanced-UteParts baris 94).
 *
 * (a) Queue dispatch payload (stok, csv + default)
 * (b) handle() manual jurnal → file + cabang scoping vs 2 cabang + inapp notification
 * (c) table-driven jenis lain: stok, transaksi, piutang, utang, servis, komisi
 * (d) halaman laporan menampilkan tombol Export Excel / Export CSV
 */
class ExportLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabangA = Cabang::create(['kode' => 'EXP-A', 'nama' => 'Cabang Export A', 'is_active' => true]);
        $this->cabangB = Cabang::create(['kode' => 'EXP-B', 'nama' => 'Cabang Export B', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Admin Export',
            'email' => 'admin-export@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabangA->id);

        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($this->user, 'web');
    }

    /** (a) Dispatch payload stok — csv & default xlsx. */
    public function test_export_stok_dispatches_queue_job_with_correct_payload(): void
    {
        Livewire::test(StokTab::class)->call('exportLaporan', 'csv');

        Queue::assertPushed(ExportLaporanJob::class, 1);
        Queue::assertPushed(function (ExportLaporanJob $job) {
            return $job->jenis === 'stok'
                && $job->format === 'csv'
                && $job->cabangId === $this->cabangA->id
                && $job->userId === $this->user->id;
        });

        Livewire::test(StokTab::class)->call('exportLaporan');
        Queue::assertPushed(ExportLaporanJob::class, 2);
        Queue::assertPushed(fn (ExportLaporanJob $job) => $job->format === 'xlsx' && $job->jenis === 'stok');
    }

    /** (b) handle() manual jurnal — file ditulis + scoping cabang + notifikasi inapp. */
    public function test_export_jurnal_handle_writes_file_and_scopes_by_cabang(): void
    {
        $akun = AkunCOA::where('kode', '410-01')->firstOrFail();

        JurnalAkuntansi::create([
            'no_jurnal' => 'JRNL-EXPA-1',
            'tanggal' => now(),
            'cabang_id' => $this->cabangA->id,
            'akun_coa_id' => $akun->id,
            'sumber' => 'manual',
            'deskripsi' => 'JURNAL-CABANG-A Penjualan unit A',
            'debit' => 100000,
            'kredit' => 0,
            'user_id' => $this->user->id,
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRNL-EXPB-1',
            'tanggal' => now(),
            'cabang_id' => $this->cabangB->id,
            'akun_coa_id' => $akun->id,
            'sumber' => 'manual',
            'deskripsi' => 'JURNAL-CABANG-B Penjualan unit B',
            'debit' => 0,
            'kredit' => 50000,
            'user_id' => $this->user->id,
        ]);

        $job = new ExportLaporanJob(
            jenis: 'jurnal',
            periodeDari: now()->toDateString(),
            periodeSampai: now()->toDateString(),
            cabangId: $this->cabangA->id,
            akunId: null,
            userId: $this->user->id,
            format: 'csv',
        );
        $job->handle(app(ExportLaporanService::class), app(NotificationService::class));

        $files = Storage::disk('local')->files('exports');
        $this->assertCount(1, $files);
        // [P2-10] skema {userId}_{jenis}_{Ymd-His}_{uniq}.{ext}
        $this->assertStringStartsWith($this->user->id.'_'.'jurnal_', basename($files[0]));
        $this->assertStringEndsWith('.csv', $files[0]);

        $content = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('JURNAL-CABANG-A', $content);
        $this->assertStringNotContainsString('JURNAL-CABANG-B', $content);

        $notif = NotifikasiKeluar::where('tipe', 'inapp')->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('jurnal', Str::lower($notif->judul));
        $this->assertStringContainsString('akunting/export/download', (string) ($notif->payload['url'] ?? ''));
    }

    /** (c) Table-driven: jenis lain → file export + dispatch dari surface masing-masing. */
    public function test_export_remaining_jenis_table_driven(): void
    {
        $this->buatFixtures();

        $map = [
            'stok' => fn () => Livewire::test(StokTab::class)->call('exportLaporan', 'csv'),
            'transaksi' => fn () => Livewire::test(DrillDownViewer::class)->call('exportLaporan', 'csv'),
            'piutang' => fn () => Livewire::test(AkuntingDashboard::class)->call('exportLaporan', 'piutang', 'csv'),
            'utang' => fn () => Livewire::test(AkuntingDashboard::class)->call('exportLaporan', 'utang', 'csv'),
            'servis' => fn () => Livewire::test(ServisBoard::class)->call('exportLaporan', 'csv'),
            'komisi' => fn () => Livewire::test(ResellerDashboard::class)->call('exportLaporan', 'csv'),
        ];

        foreach ($map as $jenis => $dispatch) {
            $dispatch();
            Queue::assertPushed(
                fn (ExportLaporanJob $job) => $job->jenis === $jenis
                    && $job->format === 'csv'
                    && $job->cabangId === $this->cabangA->id
                    && $job->userId === $this->user->id,
                "dispatch untuk jenis '{$jenis}' tidak sesuai payload"
            );

            $path = app(ExportLaporanService::class)->export(
                $jenis, null, null, $this->cabangA->id, null, 'csv'
            );
            $this->assertTrue(Storage::disk('local')->exists($path), "file export {$jenis} tidak ada");
            // [P2-10] prefix {userId}_ wajib ada (ownership download ACC-11b)
            $this->assertStringStartsWith($this->user->id.'_'."{$jenis}_", basename($path));
            $this->assertStringEndsWith('.csv', $path);
        }

        // Scoping komisi: hanya komisi milik transaksi cabang A
        $pathKomisi = app(ExportLaporanService::class)->export('komisi', null, null, $this->cabangA->id, null, 'csv');
        $content = Storage::disk('local')->get($pathKomisi);
        $this->assertStringContainsString('KMS-EXPA', $content);
        $this->assertStringNotContainsString('KMS-EXPB', $content);
    }

    /** Default format = xlsx (PhpSpreadsheet). */
    public function test_export_xlsx_default_format_writes_excel_file(): void
    {
        $this->buatFixtures();

        $path = app(ExportLaporanService::class)->export('stok', null, null, $this->cabangA->id);

        $this->assertTrue(Storage::disk('local')->exists($path));
        // [P2-10] prefix {userId}_ + skema unik
        $this->assertStringStartsWith($this->user->id.'_'.'stok_', basename($path));
        $this->assertStringEndsWith('.xlsx', $path);
    }

    /** (d) Halaman laporan menampilkan tombol Export Excel + Export CSV. */
    public function test_laporan_pages_show_export_buttons(): void
    {
        Livewire::test(StokTab::class)
            ->assertSee('Export Excel')
            ->assertSee('Export CSV');

        Livewire::test(ServisBoard::class)
            ->assertSee('Export Excel')
            ->assertSee('Export CSV');

        Livewire::test(ResellerDashboard::class)
            ->call('$set', 'activeTab', 'komisi')
            ->assertSee('Export Excel')
            ->assertSee('Export CSV');

        Livewire::test(AkuntingDashboard::class)
            ->call('$set', 'activeTab', 'jurnal')
            ->assertSee('Export Excel')
            ->assertSee('Export CSV')
            ->call('$set', 'activeTab', 'piutang')
            ->assertSee('Export Excel')
            ->assertSee('Export CSV')
            ->call('$set', 'activeTab', 'utang')
            ->assertSee('Export Excel')
            ->assertSee('Export CSV');

        // DrillDownViewer default = Transaksi (surface export transaksi)
        Livewire::test(DrillDownViewer::class)
            ->assertSee('Export Excel')
            ->assertSee('Export CSV');
    }

    /** [P2-10a] Dua export pada detik yg sama → filename selalu berbeda (suffix uniqid). */
    public function test_export_same_second_yields_distinct_filenames(): void
    {
        $p1 = app(ExportLaporanService::class)->export('stok', null, null, $this->cabangA->id, null, 'csv');
        $p2 = app(ExportLaporanService::class)->export('stok', null, null, $this->cabangA->id, null, 'csv');

        $this->assertNotSame($p1, $p2, 'Dua export dtk yg sama harus menghasilkan filename berbeda');
        $this->assertTrue(Storage::disk('local')->exists($p1));
        $this->assertTrue(Storage::disk('local')->exists($p2));
        // Sisa skema: {userId}_stok_{Ymd-His}_{uniq}.csv
        $this->assertMatchesRegularExpression(
            '#^exports/'.$this->user->id.'_stok_\d{8}-\d{6}_[0-9a-f]+\.csv$#',
            $p1
        );
    }

    /** [P2-10c] Download: prefix user lain → 403, prefix sendiri → sukses, tanpa prefix → 403. */
    public function test_export_download_enforces_owner_prefix(): void
    {
        $own = 'exports/'.$this->user->id.'_stok_20260923-101500_abc123.csv';
        Storage::disk('local')->put($own, 'PRODUK,GUDANG');

        // (1) Prefix user lain → 403 (message Indonesia)
        $other = 'exports/'.($this->user->id + 999).'_stok_20260923-101500_dead.csv';
        Storage::disk('local')->put($other, 'RAHASIA');
        $this->getJson('/api/akunting/export/download?path='.base64_encode($other))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak berhak mengunduh berkas ekspor milik pengguna lain.');

        // (2) Prefix sendiri → sukses (200, file terunduh)
        $this->getJson('/api/akunting/export/download?path='.base64_encode($own))
            ->assertSuccessful();

        // (3) Tanpa prefix {userId}_ → 403
        $noPrefix = 'exports/export-stok-20260923-101500.csv';
        Storage::disk('local')->put($noPrefix, 'LAMA');
        $this->getJson('/api/akunting/export/download?path='.base64_encode($noPrefix))
            ->assertStatus(403);
    }

    /**
     * [P0] Simulasi kondisi queue worker (auth = null): handle() harus tetap
     * mem-forward userId dari job → prefix filename {userId}_ (bukan "0_"),
     * sehingga cek kepemilikan download (ACC-11b) lolos untuk pemilik &
     * menolak (403) user lain.
     */
    public function test_queue_worker_export_without_auth_prefixes_job_user_id(): void
    {
        $job = new ExportLaporanJob(
            jenis: 'jurnal',
            periodeDari: now()->toDateString(),
            periodeSampai: now()->toDateString(),
            cabangId: $this->cabangA->id,
            akunId: null,
            userId: $this->user->id,
            format: 'csv',
        );

        // Kondisi queue worker: tidak ada sesi login
        auth()->logout();
        $this->assertNull(auth()->id(), 'pra-syarat: worker berjalan tanpa auth');

        $job->handle(app(ExportLaporanService::class), app(NotificationService::class));

        $files = Storage::disk('local')->files('exports');
        $this->assertCount(1, $files);
        // Prefix WAJIB userId dari job — jatuh ke "0_" berarti unduhan 403 di produksi
        $this->assertStringStartsWith($this->user->id.'_'.'jurnal_', basename($files[0]));

        $path = $files[0];

        // (1) Pemilik login kembali → download sukses (200)
        $this->actingAs($this->user, 'web');
        $this->getJson('/api/akunting/export/download?path='.base64_encode($path))
            ->assertSuccessful();

        // (2) User lain (role sama, permission laporan.cabang) → 403 ownership (pesan Indonesia)
        $other = User::create([
            'name' => 'Other Export',
            'email' => 'other-export@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $other->assignRole('super-admin');
        $other->cabangs()->attach($this->cabangA->id);

        $this->actingAs($other, 'web');
        // Guard sanctum (RequestGuard) meng-cache user hasil resolve pertama —
        // tanpa lupa cache ini, request berikutnya masih "merasa" user pemilik.
        auth()->guard('sanctum')->forgetUser();
        $this->getJson('/api/akunting/export/download?path='.base64_encode($path))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak berhak mengunduh berkas ekspor milik pengguna lain.');
    }

    /**
     * [P2-10b] Prune exports/ usia >7 hari.
     * Pilihan: prune di-extract ke method publik statis ExportLaporanService::pruneOldExports()
     * lalu diuji langsung dgn backdating mtime via touch() — deterministik, tanpa menunggu jam.
     */
    public function test_prune_old_exports_removes_files_older_than_seven_days(): void
    {
        $old = 'exports/'.$this->user->id.'_stok_20180101-000000_old.csv';
        $recent = 'exports/'.$this->user->id.'_stok_20990101-000000_new.csv';
        Storage::disk('local')->put($old, 'LAMA');
        Storage::disk('local')->put($recent, 'BARU');

        // Backdate mtime file lama ke 8 hari lalu (di luar jendela 7 hari)
        touch(Storage::disk('local')->path($old), now()->subDays(8)->getTimestamp());
        touch(Storage::disk('local')->path($recent), now()->subDay()->getTimestamp());

        ExportLaporanService::pruneOldExports();

        $this->assertFalse(Storage::disk('local')->exists($old), 'Berkas >7 hari harus terhapus');
        $this->assertTrue(Storage::disk('local')->exists($recent), 'Berkas <7 hari tidak boleh disentuh');
    }

    /** [P2-10c] Filename export selalu di-prefix id user yg sedang login. */
    public function test_export_filename_starts_with_authenticated_user_id(): void
    {
        $path = app(ExportLaporanService::class)->export('stok', null, null, $this->cabangA->id, null, 'csv');

        $this->assertSame(
            0,
            strpos(basename($path), (string) $this->user->id.'_'),
            'Filename harus di-prefix id user aktif'
        );
    }

    /** Fixture data lintas jenis (scoped ke cabang A kecuali penanda komisi cabang B). */
    private function buatFixtures(): void
    {
        // Stok (cabang A)
        $gudang = Gudang::create([
            'cabang_id' => $this->cabangA->id,
            'nama' => 'Gudang Export',
            'kode' => 'GDG-EXP',
            'is_active' => true,
        ]);
        $produk = Produk::create([
            'nama' => 'Sparepart Export',
            'slug' => Str::slug('Sparepart Export').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);
        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
            'jumlah_minimum' => 2,
        ]);

        $pelanggan = Pelanggan::create(['nama' => 'Pelanggan Export', 'telepon' => '0812000001']);
        $pelangganB = Pelanggan::create(['nama' => 'Reseller B', 'telepon' => '0812000002', 'is_reseller' => true]);

        // Transaksi A + B (diskon 0 → tanpa approval engine fire)
        $transaksiA = Transaksi::create([
            'no_transaksi' => 'TRX-EXPA-'.Str::random(4),
            'cabang_id' => $this->cabangA->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 150000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'total_akhir' => 150000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);
        $transaksiB = Transaksi::create([
            'no_transaksi' => 'TRX-EXPB-'.Str::random(4),
            'cabang_id' => $this->cabangB->id,
            'kasir_id' => $this->user->id,
            'subtotal' => 200000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'total_akhir' => 200000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        // Piutang / Utang (cabang A)
        Piutang::create([
            'no_piutang' => 'PTG-EXP-'.Str::random(4),
            'pelanggan_id' => $pelanggan->id,
            'cabang_id' => $this->cabangA->id,
            'jumlah' => 150000,
            'jumlah_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);
        Utang::create([
            'no_utang' => 'UTG-EXP-'.Str::random(4),
            'referensi_tipe' => 'pembelian',
            'referensi_id' => 1,
            'cabang_id' => $this->cabangA->id,
            'kreditor_nama' => 'Supplier Export',
            'jumlah' => 500000,
            'jumlah_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        // Servis (cabang A)
        TiketServis::create([
            'no_tiket' => 'SRV-EXP-'.Str::random(4),
            'cabang_id' => $this->cabangA->id,
            'nama_pelanggan' => 'Pelanggan Export',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar blank',
            'status' => 'diterima',
        ]);

        // Komisi: A (cabang A) + B (cabang B) — penanda scoping
        Komisi::create([
            'no_komisi' => 'KMS-EXPA',
            'pelanggan_id' => $pelangganB->id,
            'transaksi_id' => $transaksiA->id,
            'jumlah_transaksi' => 150000,
            'nominal_komisi' => 7500,
            'status' => 'pending',
        ]);
        Komisi::create([
            'no_komisi' => 'KMS-EXPB',
            'pelanggan_id' => $pelangganB->id,
            'transaksi_id' => $transaksiB->id,
            'jumlah_transaksi' => 200000,
            'nominal_komisi' => 10000,
            'status' => 'pending',
        ]);
    }
}
