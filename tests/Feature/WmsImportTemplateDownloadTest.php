<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * [B-04] Download template import master produk (WMS-IMP-01).
 *
 * Sebelum fix: Excel::download() melempar TypeError (argumen #1 bukan
 * Maatwebsite\Excel\Concerns\Export) → respons API jadi error JSON,
 * browser menampilkan baris teks, file .xlsx TIDAK pernah dibuat.
 */
class WmsImportTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-import-tpl@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');
    }

    public function test_download_template_menghasilkan_file_xlsx(): void
    {
        $this->authed();

        $response = $this->get('/api/wms/produk/import/template');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            'template-import-produk.xlsx',
            (string) $response->headers->get('content-disposition')
        );

        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);
        // Magic bytes ZIP (xlsx = zip).
        $this->assertSame('PK', substr(file_get_contents($path), 0, 2));
    }

    public function test_isi_template_sesuai_format_import(): void
    {
        $this->authed();

        $response = $this->get('/api/wms/produk/import/template');
        $response->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();

        $workbook = IOFactory::load($path);
        $this->assertSame(2, $workbook->getSheetCount());
        $this->assertSame('Template Produk', $workbook->getSheet(0)->getTitle());

        // Header sheet 1 harus = kolom yang diterima ImportProdukService.
        $header = array_values(array_filter(
            $workbook->getSheet(0)->rangeToArray('A1:Z1', null, false, false, false)[0],
            static fn ($v) => $v !== null && $v !== ''
        ));
        $expected = app(ImportProdukService::class)->templateColumns();
        $this->assertSame($expected, $header);

        // Baris contoh harus sejajar dengan header (key + urutan sama).
        // Nilai dinormalkan ke string: barcode/harga ditulis PhpSpreadsheet sebagai
        // cell numerik sehingga saat dibaca balik tipenya int/float (bukan string).
        $rows = $workbook->getSheet(0)->rangeToArray('A2:Z3', null, false, false, false);
        $nonEmpty = static fn ($v) => $v !== null && $v !== '';
        $str = static fn ($v) => array_map(static fn ($x) => (string) $x, $v);

        foreach (app(ImportProdukService::class)->contohBaris() as $i => $contoh) {
            $this->assertSame(
                $str(array_values(array_filter($contoh, $nonEmpty))),
                $str(array_values(array_filter($rows[$i], $nonEmpty)))
            );
        }
    }
}
