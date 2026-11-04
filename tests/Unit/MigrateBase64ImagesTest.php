<?php

namespace Tests\Unit;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateBase64ImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_base64_images_command(): void
    {
        Storage::fake('public');

        $cabang = Cabang::create(['kode' => 'CB1', 'nama' => 'Cabang Test 1', 'is_active' => true]);

        $base64Png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'cabang_id' => $cabang->id,
            'tipe_layanan' => 'reguler',
            'jenis_hp' => 'iPhone 13',
            'status' => 'antrian',
            'keluhan' => 'Layar retak',
            'foto_unit' => [$base64Png],
        ]);

        $exitCode = Artisan::call('storage:migrate-base64-images');
        $this->assertEquals(0, $exitCode);

        $tiket->refresh();
        $this->assertIsArray($tiket->foto_unit);
        $this->assertNotEmpty($tiket->foto_unit);

        $path = $tiket->foto_unit[0];
        $this->assertStringStartsWith('tiket-servis/', $path);
        Storage::disk('public')->assertExists($path);
    }
}
