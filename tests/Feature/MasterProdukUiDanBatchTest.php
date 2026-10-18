<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Support\WhatsAppHelper;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterProdukUiDanBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-TEST', 'is_active' => true]);
        Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Super Admin Wms',
            'email' => 'admin-wms@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    public function test_pagination_and_per_page_options(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Produk::create([
                'nama' => sprintf('Barang %02d', $i),
                'harga_beli' => 10000,
                'harga_jual_retail' => 15000,
                'is_active' => true,
            ]);
        }

        Livewire::test(ProdukTab::class)
            ->assertSet('perPage', 25)
            ->assertSee('Barang 01')
            ->assertSee('Barang 25')
            ->assertDontSee('Barang 26')
            ->set('perPage', 50)
            ->assertSee('Barang 26')
            ->assertSee('Barang 30');
    }

    public function test_copy_single_produk_and_batch_copy_with_tier_filter(): void
    {
        $p1 = Produk::create([
            'nama' => 'LCD iPhone 11',
            'harga_beli' => 200000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'Baterai iPhone 11',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        // Harga tier reseller
        HargaTier::create([
            'produk_id' => $p1->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 270000,
            'nominal_tetap' => 270000,
        ]);
        HargaTier::create([
            'produk_id' => $p2->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 135000,
            'nominal_tetap' => 135000,
        ]);

        // Test single copy retail
        Livewire::test(ProdukTab::class)
            ->call('copySingleProduk', $p1->id)
            ->assertDispatched('salin-teks', function ($event, $params) {
                return str_contains($params['teks'], 'LCD iPhone 11')
                    && str_contains($params['teks'], '300.000');
            });

        // Test single copy reseller
        Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'reseller')
            ->call('copySingleProduk', $p1->id)
            ->assertDispatched('salin-teks', function ($event, $params) {
                return str_contains($params['teks'], 'LCD iPhone 11')
                    && str_contains($params['teks'], '270.000');
            });

        // Test batch copy multiple items with reseller tier
        $comp = Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'reseller')
            ->set('selectedProdukIds', [$p1->id, $p2->id])
            ->call('salinHargaTerpilih');

        $dispatched = $comp->effects['dispatches'] ?? [];
        $hasSalinTeks = false;
        foreach ($dispatched as $d) {
            if (($d['name'] ?? '') === 'salin-teks') {
                $hasSalinTeks = true;
                $teks = $d['params']['teks'] ?? '';
                $this->assertStringContainsString('LCD iPhone 11 - Rp 270.000', $teks);
                $this->assertStringContainsString('Baterai iPhone 11 - Rp 135.000', $teks);
            }
        }
        $this->assertTrue($hasSalinTeks, 'Event salin-teks tidak ditemukan di dispatches');
    }

    public function test_batch_delete_produk(): void
    {
        $p1 = Produk::create([
            'nama' => 'Produk Tanpa Riwayat 1',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);
        $p2 = Produk::create([
            'nama' => 'Produk Tanpa Riwayat 2',
            'harga_beli' => 20000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);

        Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$p1->id, $p2->id])
            ->call('hapusProdukMasal')
            ->assertSet('selectedProdukIds', []);

        $this->assertDatabaseMissing('produk', ['id' => $p1->id]);
        $this->assertDatabaseMissing('produk', ['id' => $p2->id]);
    }

    public function test_bulk_edit_pindah_rak(): void
    {
        $gudang = Gudang::where('cabang_id', $this->cabang->id)->first();
        $rak = Rak::create([
            'gudang_id' => $gudang->id,
            'kode' => 'RAK-A1',
            'nama' => 'Rak Display Utama',
        ]);

        $p1 = Produk::create(['nama' => 'Produk Rak 1', 'harga_beli' => 10000, 'harga_jual_retail' => 15000]);
        $p2 = Produk::create(['nama' => 'Produk Rak 2', 'harga_beli' => 20000, 'harga_jual_retail' => 25000]);

        Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$p1->id, $p2->id])
            ->call('openBulkEditModal')
            ->assertSet('showBulkEditModal', true)
            ->set('bulkEditFields.rak_id', true)
            ->set('bulkEditForm.rak_id', $rak->id)
            ->call('simpanBulkEdit')
            ->assertSet('showBulkEditModal', false)
            ->assertSet('selectedProdukIds', []);

        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $p1->id,
            'rak_id' => $rak->id,
        ]);
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $p2->id,
            'rak_id' => $rak->id,
        ]);
    }

    public function test_kustomisasi_salin_data_produk(): void
    {
        $gudang = Gudang::where('cabang_id', $this->cabang->id)->first();
        $rak = Rak::create([
            'gudang_id' => $gudang->id,
            'kode' => 'RAK-B2',
            'nama' => 'Rak Komponen',
        ]);

        $p1 = Produk::create([
            'nama' => 'Kabel Data Type C',
            'harga_beli' => 10000,
            'harga_jual_retail' => 25000,
        ]);

        SkuVariant::create([
            'produk_id' => $p1->id,
            'sku' => 'SKU-KBL-01',
            'nama' => 'Default',
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $p1->id,
            'gudang_id' => $gudang->id,
            'rak_id' => $rak->id,
            'jumlah' => 15,
            'jumlah_minimum' => 5,
        ]);

        // 1. Uji modal kustomisasi salin data
        Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$p1->id])
            ->call('openSalinDataModal')
            ->assertSet('showSalinDataModal', true)
            ->set('salinOptions.sku', true)
            ->set('salinOptions.stok', true)
            ->set('salinOptions.rak', true)
            ->set('salinOptions.format', 'teks')
            ->call('eksekusiSalinData')
            ->assertSet('showSalinDataModal', false)
            ->assertDispatched('salin-teks', function ($event, $params) {
                return str_contains($params['teks'], 'Kabel Data Type C')
                    && str_contains($params['teks'], '[SKU-KBL-01]')
                    && str_contains($params['teks'], '25.000')
                    && str_contains($params['teks'], 'Stok: 15')
                    && str_contains($params['teks'], 'Rak: RAK-B2');
            });

        // 2. Uji format TSV untuk paste ke Excel / Spreadsheet
        Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$p1->id])
            ->set('salinOptions.nama', true)
            ->set('salinOptions.sku', true)
            ->set('salinOptions.harga', true)
            ->set('salinOptions.format', 'tsv')
            ->call('eksekusiSalinData')
            ->assertDispatched('salin-teks', function ($event, $params) {
                $lines = explode("\n", $params['teks']);

                return str_contains($lines[0], "Nama Produk\tSKU\tHarga")
                    && str_contains($lines[1], "Kabel Data Type C\tSKU-KBL-01\t25000");
            });
    }

    public function test_whatsapp_helper_struk_bersih_dan_bebas_simbol_rusak(): void
    {
        $receiptData = [
            'cabang' => '<b>Ute Parts Official</b>',
            'no_transaksi' => 'TRX-20261018-0001',
            'waktu' => '18/10/2026 14:00',
            'pelanggan' => 'Budi Santoso &amp; Rekan',
            'total' => 150000,
            'bayar' => 200000,
            'kembali' => 50000,
            'metode' => 'TUNAI',
            'items' => [
                [
                    'nama' => '[Part] LCD &amp; Touchscreen <em>Original</em>',
                    'qty' => 1,
                    'harga' => 100000,
                    'subtotal' => 100000,
                ],
                [
                    'nama' => '[Jasa] Pasang LCD',
                    'qty' => 1,
                    'harga' => 50000,
                    'subtotal' => 50000,
                ],
            ],
            'garansi' => '1 Bulan (Segel Utuh)',
        ];

        $draft = WhatsAppHelper::draftStrukPos($receiptData);

        // Tidak boleh ada tag HTML
        $this->assertStringNotContainsString('<b>', $draft);
        $this->assertStringNotContainsString('<em>', $draft);
        $this->assertStringNotContainsString('&amp;', $draft);

        // Tidak boleh ada prefix teknis [Part] atau [Jasa]
        $this->assertStringNotContainsString('[Part]', $draft);
        $this->assertStringNotContainsString('[Jasa]', $draft);

        // Harus memuat nama bersih, rincian pembayaran, dan garansi
        $this->assertStringContainsString('Halo kak *Budi Santoso & Rekan*', $draft);
        $this->assertStringContainsString('LCD & Touchscreen Original', $draft);
        $this->assertStringContainsString('Pasang LCD', $draft);
        $this->assertStringContainsString('*Total: Rp 150.000*', $draft);
        $this->assertStringContainsString('Bayar: Rp 200.000', $draft);
        $this->assertStringContainsString('Kembali: Rp 50.000', $draft);
        $this->assertStringContainsString('Garansi: 1 Bulan (Segel Utuh)', $draft);

        // Uji URL WhatsApp dengan nomor dan tanpa nomor
        $linkDenganNomor = WhatsAppHelper::buatLink('081234567890', $draft);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $linkDenganNomor);

        $linkTanpaNomor = WhatsAppHelper::buatLink(null, $draft);
        $this->assertStringStartsWith('https://api.whatsapp.com/send?text=', $linkTanpaNomor);
    }
}
