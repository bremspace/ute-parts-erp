<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Livewire\LaporanPajak;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Report\Jobs\ReportExportJob;
use App\Modules\Report\Livewire\DrillDownViewer;
use App\Modules\Report\Livewire\ReportBuilder;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Wms\Livewire\StokTab;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExportFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->user = User::create([
            'name' => 'Admin Export Test',
            'email' => 'admin.export.fix@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
        Storage::fake('local');
    }

    public function test_report_builder_export_with_auto_generated_name_and_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(ReportBuilder::class)
            ->set('sourceModel', 'Transaksi')
            ->set('exportFormat', 'xlsx')
            ->call('export');

        // Queue job dispatched
        Queue::assertPushed(ReportExportJob::class, 1);

        // Alert message dispatched
        $component->assertDispatched('alert');

        // File downloaded
        $component->assertFileDownloaded();
    }

    public function test_report_builder_export_csv_download(): void
    {
        Queue::fake();

        $component = Livewire::test(ReportBuilder::class)
            ->set('sourceModel', 'Transaksi')
            ->set('reportName', 'Laporan Penjualan Khusus')
            ->set('exportFormat', 'csv')
            ->call('export');

        Queue::assertPushed(ReportExportJob::class, 1);
        $component->assertDispatched('alert');
        $component->assertFileDownloaded('laporan-penjualan-khusus.csv');
    }

    public function test_stok_export_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(StokTab::class)
            ->call('exportLaporan', 'xlsx');

        $component->assertFileDownloaded();
    }

    public function test_servis_export_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(ServisBoard::class)
            ->call('exportLaporan', 'xlsx');

        $component->assertFileDownloaded();
    }

    public function test_reseller_export_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(ResellerDashboard::class)
            ->call('exportLaporan', 'xlsx');

        $component->assertFileDownloaded();
    }

    public function test_akunting_export_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(AkuntingDashboard::class)
            ->call('exportLaporan', 'jurnal', 'xlsx');

        $component->assertFileDownloaded();
    }

    public function test_drill_down_export_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(DrillDownViewer::class)
            ->call('exportLaporan', 'xlsx');

        $component->assertFileDownloaded();
    }

    public function test_laporan_pajak_export_direct_download(): void
    {
        Queue::fake();

        $component = Livewire::test(LaporanPajak::class)
            ->call('exportPajak');

        $component->assertFileDownloaded();
    }

    public function test_web_download_route_authenticated_and_authorized(): void
    {
        $own = 'exports/'.$this->user->id.'_laporan_test_20261003.xlsx';
        Storage::disk('local')->put($own, 'DUMMY_EXCEL_CONTENT');

        $encoded = base64_encode($own);

        $response = $this->get('/app/export/download?path='.$encoded);
        $response->assertSuccessful();

        $responseApi = $this->get('/api/akunting/export/download?path='.$encoded);
        $responseApi->assertSuccessful();
    }
}
