<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BulkFotoChunkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Admin WMS',
            'email' => 'admin-chunk@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);

        $this->actingAs($this->user, 'web');
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_upload_chunk_saves_files_to_staging_directory(): void
    {
        $sessionId = 'sess_test_123';
        $file1 = UploadedFile::fake()->image('LCD-IP13_1.jpg');
        $file2 = UploadedFile::fake()->image('BAT-IP11.png');

        $response = $this->postJson(route('wms.import-foto.chunk'), [
            'session_id' => $sessionId,
            'files' => [$file1, $file2],
            'paths' => ['LCD-IP13/LCD-IP13_1.jpg', 'BAT-IP11.png'],
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'success' => true,
            'session_id' => $sessionId,
            'uploaded_count' => 2,
        ]);

        $stagingDir = 'tmp_foto_'.$this->user->id.'_'.$sessionId;
        $filesInStorage = Storage::disk('local')->allFiles($stagingDir);
        $this->assertCount(2, $filesInStorage);
    }

    public function test_pre_check_filters_files_by_sku_and_strategy(): void
    {
        $p1 = Produk::create([
            'nama' => 'Produk Dengan Foto',
            'sku' => 'SKU-HAS-PHOTO',
            'gambar' => 'https://uteparts.id/storage/foto.webp',
            'foto' => [['url' => 'https://uteparts.id/storage/foto.webp', 'is_primary' => true]],
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p1->id,
            'sku' => 'SKU-HAS-PHOTO',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'Produk Tanpa Foto',
            'sku' => 'SKU-NO-PHOTO',
            'gambar' => null,
            'foto' => null,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p2->id,
            'sku' => 'SKU-NO-PHOTO',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $files = [
            ['name' => 'SKU-HAS-PHOTO_01.jpg', 'size' => 1024],
            ['name' => 'SKU-NO-PHOTO.jpg', 'size' => 2048],
            ['name' => 'UNKNOWN-PRODUCT.jpg', 'size' => 512],
        ];

        // 1. Test dengan mode 'append': SKU-HAS-PHOTO tetap MATCHED (akan masuk galeri)
        $resAppend = $this->postJson(route('wms.import-foto.pre-check'), [
            'files' => $files,
            'mode' => 'append',
        ]);
        $resAppend->assertSuccessful();
        $resAppend->assertJson([
            'success' => true,
            'total' => 3,
            'matched_count' => 2,
            'skipped_count' => 1,
        ]);

        // 2. Test dengan mode 'skip_existing': SKU-HAS-PHOTO disaring menjadi SKIPPED (tidak perlu diupload)
        $resSkip = $this->postJson(route('wms.import-foto.pre-check'), [
            'files' => $files,
            'mode' => 'skip_existing',
        ]);
        $resSkip->assertSuccessful();
        $resSkip->assertJson([
            'success' => true,
            'total' => 3,
            'matched_count' => 1, // Hanya SKU-NO-PHOTO yang diupload
            'skipped_count' => 2, // UNKNOWN-PRODUCT & SKU-HAS-PHOTO di-skip
        ]);
    }

    public function test_clear_session_deletes_staging_directory(): void
    {
        $sessionId = 'sess_test_clear';
        $stagingDir = 'tmp_foto_'.$this->user->id.'_'.$sessionId;
        Storage::disk('local')->put($stagingDir.'/dummy.jpg', 'image-content');

        $this->assertTrue(Storage::disk('local')->exists($stagingDir.'/dummy.jpg'));

        $response = $this->postJson(route('wms.import-foto.clear-session'), [
            'session_id' => $sessionId,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);

        $this->assertFalse(Storage::disk('local')->exists($stagingDir.'/dummy.jpg'));
    }

    public function test_livewire_scan_from_staging_session(): void
    {
        $p = Produk::create([
            'nama' => 'LCD iPhone 13 Pro',
            'slug' => 'lcd-iphone-13-pro-'.uniqid(),
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'garansi_hari' => 30,
            'status' => 'aktif',
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p->id,
            'sku' => 'LCD-IP13-PRO',
            'barcode' => '899123450001',
            'nama_varian' => 'Black',
            'harga_modal' => 500000,
            'harga_jual' => 850000,
            'stok' => 10,
        ]);

        $sessionId = 'sess_livewire_scan';
        $stagingDir = 'tmp_foto_'.$this->user->id.'_'.$sessionId;
        $fakeImg = UploadedFile::fake()->image('LCD-IP13-PRO_1.jpg');
        $fakeImg->storeAs($stagingDir, 'lcd-ip13-pro-1_123456.jpg', 'local');

        Livewire::test(ImportExportPage::class)
            ->call('scanFromStagingSession', $sessionId)
            ->assertSet('fotoStep', 'preview')
            ->assertDispatched('alert');
    }
}
