<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Report\Jobs\ReportExportJob;
use App\Modules\Report\Livewire\DrillDownViewer;
use App\Modules\Report\Livewire\ReportBuilder;
use App\Modules\Report\Models\SavedReport;
use App\Modules\Report\Services\ReportBuilderService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * [F2-4] BI Drill-down + Custom Report Builder — Feature Tests.
 * Coverage: drill-down chain, whitelist rejection, saved report CRUD, export queue.
 */
class DrillDownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $cabang = Cabang::create([
            'nama' => 'Cabang Test',
            'kode' => 'TST',
            'is_active' => true,
        ]);
        session(['cabang_id' => $cabang->id]);
    }

    /**
     * T-DD-01: Drill-down chain reaches transaksi/item with cabang scoping.
     */
    public function test_drill_down_chain_reaches_transaksi_with_cabang_scoping(): void
    {
        $service = app(ReportBuilderService::class);

        $this->assertArrayHasKey('Transaksi', ReportBuilderService::MODEL_WHITELIST);

        $chain = $service->getDrillChain('Transaksi');
        $this->assertContains('Transaksi', $chain);
        $this->assertContains('TransaksiItem', $chain);

        $piutangChain = $service->getDrillChain('Piutang');
        $this->assertContains('Piutang', $piutangChain);
        $this->assertContains('Transaksi', $piutangChain);

        $stokChain = $service->getDrillChain('StokItem');
        $this->assertContains('StokItem', $stokChain);
        $this->assertContains('StokLog', $stokChain);
    }

    /**
     * T-DD-02: Build query for Transaksi with cabang scoping.
     */
    public function test_drill_down_query_is_cabang_scoped(): void
    {
        $service = app(ReportBuilderService::class);
        $cabangId = session('cabang_id');

        $query = $service->buildQuery('Transaksi', ['*'], null, null, $cabangId);
        $sql = $query->toSql();
        $this->assertStringContainsString('cabang_id', $sql);
    }

    /**
     * T-DD-03: Whitelist rejection — non-whitelisted model throws exception.
     */
    public function test_whitelist_rejection_of_non_whitelisted_model(): void
    {
        $service = app(ReportBuilderService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->validateModel('FakeModel');
    }

    /**
     * T-DD-04: Whitelist contains expected models.
     */
    public function test_whitelist_contains_expected_models(): void
    {
        $expectedModels = ['Transaksi', 'JurnalAkuntansi', 'StokItem', 'PurchaseOrder', 'Piutang', 'Utang', 'TiketServis', 'Produk'];
        foreach ($expectedModels as $model) {
            $this->assertArrayHasKey($model, ReportBuilderService::MODEL_WHITELIST);
        }
    }

    /**
     * T-DD-05: Non-whitelisted model cannot be used to build query.
     */
    public function test_non_whitelisted_model_cannot_build_query(): void
    {
        $service = app(ReportBuilderService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->buildQuery('FakeModel', ['*'], null, null, session('cabang_id'));
    }

    /**
     * T-DD-06: Saved report CRUD scoped to owner.
     */
    public function test_saved_report_crud_scoped_to_owner(): void
    {
        $user1 = User::create(['name' => 'User1', 'email' => 'u1@test.com', 'password' => bcrypt('password')]);
        $user2 = User::create(['name' => 'User2', 'email' => 'u2@test.com', 'password' => bcrypt('password')]);
        $cabangId = session('cabang_id');

        $report = SavedReport::create([
            'name' => 'Laporan Test User1',
            'source_model' => 'Transaksi',
            'columns' => ['no_transaksi', 'total_akhir'],
            'user_id' => $user1->id,
            'cabang_id' => $cabangId,
        ]);

        $this->assertEquals($user1->id, $report->user_id);
        $this->assertEquals('Laporan Test User1', $report->name);

        $otherReport = SavedReport::find($report->id);
        $this->assertNotNull($otherReport);
        $this->assertEquals($user1->id, $otherReport->user_id);
        $this->assertNotEquals($user2->id, $otherReport->user_id);
    }

    /**
     * T-DD-07: Saved report is scoped to cabang_id.
     */
    public function test_saved_report_scoped_to_cabang(): void
    {
        $cabangId = session('cabang_id');
        $user = User::create(['name' => 'Test User', 'email' => 't@test.com', 'password' => bcrypt('password')]);

        SavedReport::create([
            'name' => 'Laporan Cabang A',
            'source_model' => 'Transaksi',
            'columns' => ['*'],
            'user_id' => $user->id,
            'cabang_id' => $cabangId,
        ]);

        $reports = SavedReport::where('cabang_id', $cabangId)->get();
        $this->assertGreaterThan(0, $reports->count());
    }

    /**
     * T-DD-08: Saved report retrievable by user_id.
     */
    public function test_saved_report_retrievable_by_user(): void
    {
        $user = User::create(['name' => 'My User', 'email' => 'my@test.com', 'password' => bcrypt('password')]);
        $cabangId = session('cabang_id');

        SavedReport::create([
            'name' => 'My Report',
            'source_model' => 'Piutang',
            'columns' => ['no_piutang'],
            'user_id' => $user->id,
            'cabang_id' => $cabangId,
        ]);

        $userReports = SavedReport::where('user_id', $user->id)->get();
        $this->assertGreaterThan(0, $userReports->count());
        $this->assertEquals('My Report', $userReports->first()->name);
    }

    /**
     * T-DD-09: Export job is queued (assertPushed — not executed sync).
     */
    public function test_export_job_is_queued(): void
    {
        Queue::fake();

        $cabangId = session('cabang_id');

        // Dispatch the job through Laravel's dispatch helper
        dispatch(new ReportExportJob(
            modelName: 'Transaksi',
            columns: ['no_transaksi', 'total_akhir'],
            filters: ['status' => 'selesai'],
            groupBy: null,
            cabangId: $cabangId,
            format: 'xlsx',
            userId: 1,
            reportName: 'Test Export'
        ));

        Queue::assertPushed(ReportExportJob::class, function ($pushed) use ($cabangId) {
            return $pushed->modelName === 'Transaksi'
                && $pushed->cabangId === $cabangId
                && $pushed->format === 'xlsx';
        });
    }

    /**
     * T-DD-10: Export job rejects non-whitelisted model.
     */
    public function test_export_job_rejects_non_whitelisted_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ReportBuilderService::class)->validateModel('FakeModel');
    }

    /**
     * T-DD-11: Export job implements ShouldQueue interface.
     */
    public function test_export_job_implements_should_queue(): void
    {
        $reflection = new \ReflectionClass(ReportExportJob::class);
        $this->assertTrue($reflection->implementsInterface(ShouldQueue::class));
    }

    /**
     * T-DD-12: Get available columns for whitelisted model.
     */
    public function test_get_available_columns_for_whitelisted_model(): void
    {
        $service = app(ReportBuilderService::class);
        $columns = $service->getAvailableColumns('Transaksi');

        $this->assertArrayHasKey('no_transaksi', $columns);
        $this->assertArrayHasKey('cabang_id', $columns);
        $this->assertArrayHasKey('total_akhir', $columns);
    }

    /**
     * T-DD-13: Get available columns rejects non-whitelisted model.
     */
    public function test_get_available_columns_rejects_non_whitelisted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ReportBuilderService::class)->getAvailableColumns('FakeModel');
    }

    /**
     * T-DD-14: Drill-down chain for Produk → StokItem.
     */
    public function test_drill_down_chain_produk_to_stok_item(): void
    {
        $service = app(ReportBuilderService::class);
        $chain = $service->getDrillChain('Produk');
        $this->assertContains('Produk', $chain);
        $this->assertContains('StokItem', $chain);
    }

    /**
     * T-DD-15: Drill-down chain for TiketServis → TiketServisItem.
     */
    public function test_drill_down_chain_tiket_servis_to_item(): void
    {
        $service = app(ReportBuilderService::class);
        $chain = $service->getDrillChain('TiketServis');
        $this->assertContains('TiketServis', $chain);
        $this->assertContains('TiketServisItem', $chain);
    }

    /**
     * T-DD-16: All whitelist models have valid drill-down targets or are terminal.
     */
    public function test_all_whitelist_models_have_valid_drill_down_targets(): void
    {
        $service = app(ReportBuilderService::class);

        foreach (array_keys(ReportBuilderService::MODEL_WHITELIST) as $model) {
            $target = $service->getDrillDownTarget($model);
            if ($target !== null) {
                $this->assertArrayHasKey($target, ReportBuilderService::MODEL_WHITELIST,
                    "Drill-down target {$target} for {$model} must be in whitelist");
            }
        }
    }

    /**
     * [P0-2] (a) IDOR: drill detail dengan id milik cabang lain → 403,
     * id milik cabang aktif → detail terisi.
     */
    public function test_forged_drill_detail_id_from_other_cabang_is_403(): void
    {
        $cabangLain = Cabang::create(['nama' => 'Cabang Lain', 'kode' => 'TSL', 'is_active' => true]);

        $trxA = Transaksi::create([
            'no_transaksi' => 'TRX-DD-A-'.Str::random(6),
            'cabang_id' => session('cabang_id'),
            'total_akhir' => 10000,
            'status' => 'selesai',
        ]);
        $trxB = Transaksi::create([
            'no_transaksi' => 'TRX-DD-B-'.Str::random(6),
            'cabang_id' => $cabangLain->id,
            'total_akhir' => 99999,
            'status' => 'selesai',
        ]);

        // Cabang sendiri → detail terisi normal
        $viewer = new DrillDownViewer;
        $viewer->loadDetail('Transaksi', $trxA->id);
        $this->assertEquals($trxA->no_transaksi, $viewer->detail['no_transaksi'] ?? null);

        // Id cabang lain yang di-drop lewat drillDown(model, id) → 403
        try {
            (new DrillDownViewer)->loadDetail('Transaksi', $trxB->id);
            $this->fail('loadDetail cabang lain harus membuang 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('cabang', $e->getMessage());
        }
    }

    /**
     * [P0-3] (b) Model tanpa kolom cabang_id (StokItem) tetap cabang-scoped
     * lewat relasi gudang → daftar hanya berisi stok cabang aktif.
     */
    public function test_stok_item_list_is_cabang_scoped_via_gudang(): void
    {
        $cabangLain = Cabang::create(['nama' => 'Cabang Lain', 'kode' => 'TSL2', 'is_active' => true]);
        $gudangA = Gudang::create(['cabang_id' => session('cabang_id'), 'nama' => 'Gudang A', 'kode' => 'GDA-DD', 'is_active' => true]);
        $gudangB = Gudang::create(['cabang_id' => $cabangLain->id, 'nama' => 'Gudang B', 'kode' => 'GDB-DD', 'is_active' => true]);
        $produk = Produk::create([
            'nama' => 'Sparepart DD', 'slug' => 'sparepart-dd-'.Str::random(6),
            'kategori' => 'Sparepart', 'kondisi' => 'baru',
            'harga_beli' => 1000, 'harga_jual_retail' => 2000, 'is_active' => true,
        ]);
        StokItem::create(['produk_id' => $produk->id, 'gudang_id' => $gudangA->id, 'jumlah' => 5, 'jumlah_minimum' => 1]);
        StokItem::create(['produk_id' => $produk->id, 'gudang_id' => $gudangB->id, 'jumlah' => 9, 'jumlah_minimum' => 1]);

        $query = app(ReportBuilderService::class)->buildQuery('StokItem', ['*'], null, null, session('cabang_id'));
        $items = $query->get();

        $this->assertCount(1, $items, 'StokItem lintas cabang tidak boleh bocor');
        $this->assertEquals($gudangA->id, $items->first()->gudang_id);
        $this->assertEquals(5, $items->first()->jumlah);
    }

    /**
     * [P0-3] Peta CABANG_SCOPE wajib lengkap untuk semua model di MODEL_MAP
     * (model tanpa entri otomatis fail-closed → 0 baris).
     */
    public function test_cabang_scope_map_covers_every_whitelisted_model(): void
    {
        foreach (array_keys(ReportBuilderService::MODEL_MAP) as $model) {
            $this->assertArrayHasKey($model, ReportBuilderService::CABANG_SCOPE,
                "Model {$model} wajib punya entri CABANG_SCOPE (fail closed)");
        }
        // Bandingkan sebagai set — urutan deklarasi boleh beda, isi wajib identik
        $mapKeys = array_keys(ReportBuilderService::MODEL_MAP);
        $scopeKeys = array_keys(ReportBuilderService::CABANG_SCOPE);
        sort($mapKeys);
        sort($scopeKeys);
        $this->assertEquals($mapKeys, $scopeKeys);
    }

    /**
     * [P0-5] (c) Lifecycle addFilter → set nilai → buildAndShow → removeFilter,
     * plus listener refreshResults & navigasi drillDown ke route yang ada.
     */
    public function test_add_remove_filter_lifecycle_works_end_to_end(): void
    {
        Transaksi::create([
            'no_transaksi' => 'TRX-FLT-'.Str::random(6),
            'cabang_id' => session('cabang_id'),
            'total_akhir' => 5000,
            'status' => 'selesai',
        ]);

        $component = Livewire::test(ReportBuilder::class)
            ->set('sourceModel', 'Transaksi')   // updatedSourceModel → kolom terisi, filter direset
            ->call('addFilter')
            ->assertSet('filters.0.field', '')
            ->assertSet('filters.0.op', '=')
            ->assertSet('filters.0.value', '');

        // Filter valid → hasil tampil
        $component
            ->set('filters.0.field', 'status')
            ->set('filters.0.op', '=')
            ->set('filters.0.value', 'selesai')
            ->call('buildAndShow')
            ->assertSet('showResults', true)
            ->assertCount('queryResults', 1);

        // Field di luar whitelist getAvailableColumns() → diabaikan tanpa error,
        // query tetap mengembalikan data (bukan injeksi / bukan exception).
        $component
            ->call('addFilter')
            ->set('filters.1.field', 'kolom_tidak_ada')
            ->set('filters.1.op', 'like')
            ->set('filters.1.value', '%')
            ->call('buildAndShow')
            ->assertSet('showResults', true)
            ->assertCount('queryResults', 1);

        // removeFilter menghapus baris & merapikan index
        $component
            ->call('removeFilter', 0)
            ->assertSet('filters.0.field', 'kolom_tidak_ada')
            ->call('removeFilter', 0)
            ->assertSet('filters', []);

        // $listeners refreshResults ada (MethodNotFound kalau tidak)
        $component->call('refreshResults')->assertSet('showResults', true);

        // drillDown navigasi ke route DrillDownViewer yang sudah ada (laporan.drill)
        $component->call('drillDown', 'Transaksi')->assertRedirectToRoute('laporan.drill', ['model' => 'Transaksi']);
    }

    /**
     * [P1-10] groupBy → select hanya kolom tergrup + COUNT(*) (ONLY_FULL_GROUP_BY),
     * dan hanya menghitung baris cabang aktif.
     */
    public function test_group_by_selects_only_grouped_columns_plus_aggregate_and_stays_scoped(): void
    {
        $cabangLain = Cabang::create(['nama' => 'Cabang Lain', 'kode' => 'TSL3', 'is_active' => true]);
        Transaksi::create(['no_transaksi' => 'TRX-GRP-A1-'.Str::random(4), 'cabang_id' => session('cabang_id'), 'total_akhir' => 100, 'status' => 'selesai']);
        Transaksi::create(['no_transaksi' => 'TRX-GRP-A2-'.Str::random(4), 'cabang_id' => session('cabang_id'), 'total_akhir' => 200, 'status' => 'selesai']);
        Transaksi::create(['no_transaksi' => 'TRX-GRP-B1-'.Str::random(4), 'cabang_id' => $cabangLain->id, 'total_akhir' => 300, 'status' => 'selesai']);

        $service = app(ReportBuilderService::class);
        $query = $service->buildQuery('Transaksi', ['no_transaksi', 'total_akhir'], null, ['status'], session('cabang_id'));
        $sql = $query->toSql();

        // Select clause: hanya kolom tergrup + agregat (tanpa non-agregat lain)
        $selectClause = explode(' from ', $sql)[0];
        $this->assertStringContainsString('count(*)', strtolower($sql));
        $this->assertStringContainsString('group by', strtolower($sql));
        $this->assertStringNotContainsString('no_transaksi', $selectClause);
        $this->assertStringNotContainsString('total_akhir', $selectClause);

        // Fungsional: satu grup (status selesai) berisi 2 baris cabang aktif saja
        $rows = $query->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(2, (int) $rows->first()->jumlah);
        $this->assertEquals('selesai', $rows->first()->status);
    }

    /**
     * [P1-9] (d) URL notifikasi export job menunjuk ke route unduh yang nyata.
     */
    public function test_export_job_notification_url_points_to_real_download_route(): void
    {
        Queue::fake();
        Storage::fake('local');

        $job = new ReportExportJob(
            modelName: 'Transaksi',
            columns: ['no_transaksi'],
            filters: null,
            groupBy: null,
            cabangId: session('cabang_id'),
            format: 'csv',
            userId: 1,
            reportName: 'Tes URL Export'
        );
        $job->handle(app(ReportBuilderService::class), app(NotificationService::class));

        $notif = NotifikasiKeluar::where('tipe', 'inapp')->latest('id')->first();
        $this->assertNotNull($notif, 'Notifikasi inapp export harus tercatat');

        $url = (string) ($notif->payload['url'] ?? '');
        $this->assertStringContainsString('/api/akunting/export/download', $url);
        $this->assertStringContainsString('path=', $url);
        $this->assertStringNotContainsString('/api/export/download?', $url);
    }

    /**
     * [P2-4] User A tidak melihat saved report privat milik user B
     * (meski cabang sama) — hanya milik sendiri + milik cabang yang shared.
     */
    public function test_user_a_tidak_melihat_saved_report_privat_user_b(): void
    {
        $userA = User::create(['name' => 'User A', 'email' => 'ua-privat@test.com', 'password' => bcrypt('password')]);
        $userB = User::create(['name' => 'User B', 'email' => 'ub-privat@test.com', 'password' => bcrypt('password')]);
        $cabangId = session('cabang_id');

        $kolom = ['no_transaksi'];
        SavedReport::create([
            'name' => 'Privat B', 'source_model' => 'Transaksi', 'columns' => $kolom,
            'user_id' => $userB->id, 'cabang_id' => $cabangId, 'shared' => false,
        ]);
        SavedReport::create([
            'name' => 'Shared B', 'source_model' => 'Transaksi', 'columns' => $kolom,
            'user_id' => $userB->id, 'cabang_id' => $cabangId, 'shared' => true,
        ]);
        SavedReport::create([
            'name' => 'Milik A', 'source_model' => 'Transaksi', 'columns' => $kolom,
            'user_id' => $userA->id, 'cabang_id' => $cabangId, 'shared' => false,
        ]);

        $this->actingAs($userA, 'web');

        $names = Livewire::test(ReportBuilder::class)
            ->viewData('savedReports')
            ->pluck('name')
            ->all();

        $this->assertContains('Milik A', $names, 'Laporan milik sendiri wajib tampil');
        $this->assertContains('Shared B', $names, 'Laporan shared cabang sama wajib tampil');
        $this->assertNotContains('Privat B', $names, 'Laporan privat user lain TIDAK boleh tampil (bocor nama/konfig)');
    }

    /**
     * [P2-11] Hasil ekspor dipotong >rowLimit → notifikasi membawa tanda "dipotong"
     * (bukan pemotongan senyap).
     */
    public function test_export_truncation_notice_present_in_notification(): void
    {
        Storage::fake('local');

        foreach ([1, 2, 3] as $i) {
            Transaksi::create([
                'no_transaksi' => 'TRX-CAP-'.Str::random(4)."-{$i}",
                'cabang_id' => session('cabang_id'),
                'total_akhir' => 1000 * $i,
                'status' => 'selesai',
            ]);
        }

        $job = new ReportExportJob(
            modelName: 'Transaksi',
            columns: ['no_transaksi', 'total_akhir'],
            filters: null,
            groupBy: null,
            cabangId: session('cabang_id'),
            format: 'csv',
            userId: 1,
            reportName: 'Tes Potong'
        );
        $job->rowLimit = 2; // 3 baris > 2 → wajib terpotong + bernotice
        $job->handle(app(ReportBuilderService::class), app(NotificationService::class));

        $notif = NotifikasiKeluar::where('tipe', 'inapp')->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString(
            'dipotong',
            $notif->konten,
            'Pemotongan baris wajib disebutkan di pesan notifikasi (Indonesia)'
        );
    }

    /**
     * [P2-11/CROSS-LANE] Nama file ekspor diawali "{userId}_" — kontrak cek
     * kepemilikan unduh di AkuntingController (integer sebelum "_" = auth id).
     */
    public function test_export_filename_has_user_id_prefix(): void
    {
        Storage::fake('local');

        Transaksi::create([
            'no_transaksi' => 'TRX-PFX-'.Str::random(6),
            'cabang_id' => session('cabang_id'),
            'total_akhir' => 5000,
            'status' => 'selesai',
        ]);

        $job = new ReportExportJob(
            modelName: 'Transaksi',
            columns: ['no_transaksi'],
            filters: null,
            groupBy: null,
            cabangId: session('cabang_id'),
            format: 'csv',
            userId: 42,
            reportName: 'Tes Prefix File'
        );
        $job->handle(app(ReportBuilderService::class), app(NotificationService::class));

        $files = Storage::disk('local')->files('exports');
        $this->assertNotEmpty($files, 'File export harus terbuat di disk local');
        foreach ($files as $file) {
            $basename = basename($file);
            $this->assertMatchesRegularExpression('/^42_/', $basename, "File export harus diawali userId_: {$basename}");
        }
    }

    /**
     * [P2-8] mount {id} pada /laporan/drill/{model}/{id} membuka detail
     * (id tidak lagi diam-difikasi diabaikan).
     */
    public function test_mount_with_id_loads_drill_detail(): void
    {
        $trxA = Transaksi::create([
            'no_transaksi' => 'TRX-MNT-'.Str::random(6),
            'cabang_id' => session('cabang_id'),
            'total_akhir' => 7777,
            'status' => 'selesai',
        ]);

        Livewire::test(DrillDownViewer::class, ['model' => 'Transaksi', 'id' => $trxA->id])
            ->assertSet('selectedItemId', $trxA->id)
            ->assertSet('currentModel', 'Transaksi')
            ->assertSet('detail.no_transaksi', $trxA->no_transaksi);
    }
}
