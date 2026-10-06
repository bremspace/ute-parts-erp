<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProdukPricelistExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test',
            'kode' => 'CB-TEST',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    public function test_export_pricelist_retail_and_custom_tier(): void
    {
        $produk = Produk::create([
            'nama' => 'LCD iPhone 13 OLED',
            'harga_beli' => 700000,
            'harga_jual_retail' => 1200000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        // Setup HargaTier reseller
        HargaTier::create([
            'produk_id' => $produk->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 1000000,
            'nominal_tetap' => 1000000,
        ]);

        // Test Export Retail
        $component = Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'retail')
            ->call('exportPricelist', 'csv');

        $component->assertFileDownloaded();

        // Test Export Reseller
        $componentReseller = Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'reseller')
            ->call('exportPricelist', 'csv');

        $componentReseller->assertFileDownloaded();

        // Pastikan tidak ada kolom harga_beli / HPP di hasil file
        $files = Storage::disk('local')->allFiles('exports');
        $this->assertNotEmpty($files);

        $content = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('Harga Beli', $content);
        $this->assertStringNotContainsString('HPP', $content);
        $this->assertStringNotContainsString('700000', $content);
        $this->assertStringContainsString('Harga Pricelist (Rp)', $content);
    }
}
