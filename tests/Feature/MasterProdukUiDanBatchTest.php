<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ProductSearchModal;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Support\WhatsAppHelper;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
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

    public function test_salin_data_variatif_kualitas_kompatibilitas_kondisi_deskripsi_format_wa_dan_tsv(): void
    {
        $kualitas = KualitasProduk::create([
            'nama' => 'Original Equipment Manufacturer',
            'kode' => 'OEM',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'LCD Touchscreen iPhone 11',
            'harga_beli' => 150000,
            'harga_jual_retail' => 350000,
            'kualitas_id' => $kualitas->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11 / 11 Pro',
            'kondisi' => 'baru',
            'deskripsi' => 'Kualitas kontras tajam & touchscreen presisi',
        ]);

        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'SKU-LCD-IP11-OEM',
            'nama' => 'Default',
            'is_active' => true,
        ]);

        // 1. Uji salin teks WA rapi dengan opsi variatif lengkap
        $test = Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$produk->id])
            ->set('salinOptions.nama', true)
            ->set('salinOptions.harga', true)
            ->set('salinOptions.sku', true)
            ->set('salinOptions.kualitas', true)
            ->set('salinOptions.kompatibilitas', true)
            ->set('salinOptions.kondisi', true)
            ->set('salinOptions.deskripsi', true)
            ->set('salinOptions.format', 'teks')
            ->call('eksekusiSalinData');

        $dispatched = $test->effects['dispatches'] ?? [];
        $teks = null;
        foreach ($dispatched as $d) {
            if (($d['name'] ?? '') === 'salin-teks') {
                $teks = $d['params']['teks'] ?? null;
            }
        }

        $this->assertNotNull($teks);
        $this->assertStringContainsString('📦 *LCD Touchscreen iPhone 11* [SKU-LCD-IP11-OEM]', $teks);
        $this->assertStringContainsString('💰 Harga: *Rp 350.000*', $teks);
        $this->assertStringContainsString('⭐ Kualitas: Original Equipment Manufacturer', $teks);
        $this->assertStringContainsString('📱 Kompatibel: Apple iPhone 11 / 11 Pro', $teks);
        $this->assertStringContainsString('⚙️ Kondisi: Baru', $teks);
        $this->assertStringContainsString('📝 Catatan: Kualitas kontras tajam & touchscreen presisi', $teks);

        // 2. Uji salin TSV dengan header dan kolom variatif lengkap
        $testTsv = Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$produk->id])
            ->set('salinOptions.nama', true)
            ->set('salinOptions.harga', false)
            ->set('salinOptions.kualitas', true)
            ->set('salinOptions.kompatibilitas', true)
            ->set('salinOptions.kondisi', true)
            ->set('salinOptions.deskripsi', true)
            ->set('salinOptions.format', 'tsv')
            ->call('eksekusiSalinData');

        $dispatchedTsv = $testTsv->effects['dispatches'] ?? [];
        $teksTsv = null;
        foreach ($dispatchedTsv as $d) {
            if (($d['name'] ?? '') === 'salin-teks') {
                $teksTsv = $d['params']['teks'] ?? null;
            }
        }

        $this->assertNotNull($teksTsv);
        $lines = explode("\n", $teksTsv);
        $this->assertStringContainsString("Nama Produk\tKualitas\tKompatibilitas\tKondisi\tDeskripsi", $lines[0]);
        $this->assertStringContainsString("LCD Touchscreen iPhone 11\tOriginal Equipment Manufacturer\tApple iPhone 11 / 11 Pro\tBaru\tKualitas kontras tajam & touchscreen presisi", $lines[1]);
    }

    public function test_grid_view_kompatibilitas_kualitas_dan_hak_akses_harga_beli(): void
    {
        $kualitas = KualitasProduk::create([
            'nama' => 'Super Premium',
            'kode' => 'SPM',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Baterai Samsung Galaxy A50',
            'harga_beli' => 85000,
            'harga_jual_retail' => 175000,
            'kualitas_id' => $kualitas->id,
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'Galaxy A50 / A50s',
            'kondisi' => 'baru',
        ]);

        // Role Super Admin: Tampilkan Harga Beli
        $this->actingAs($this->user); // super-admin
        Livewire::test(ProdukTab::class)
            ->assertSee('Super Premium')
            ->assertSee('Samsung Galaxy A50 / A50s')
            ->assertSee('Rp 85.000') // Harga beli tampil untuk super-admin
            ->assertSee('Rp 175.000'); // Harga jual

        // Role Staff / Kasir biasa: Harga Beli disembunyikan
        $kasirUser = User::create([
            'name' => 'Kasir User',
            'email' => 'kasir@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $kasirRole = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $kasirUser->assignRole($kasirRole);
        $kasirUser->cabangs()->attach($this->cabang->id);

        $this->actingAs($kasirUser);
        Livewire::test(ProdukTab::class)
            ->assertSee('Super Premium')
            ->assertSee('Samsung Galaxy A50 / A50s')
            ->assertDontSee('Rp 85.000') // Harga beli TIDAK boleh tampil untuk kasir/staff biasa
            ->assertSee('Rp 175.000'); // Harga jual tetap tampil
    }

    public function test_master_produk_terintegrasi_dengan_supplier_dan_quick_add_supplier(): void
    {
        $this->actingAs($this->user);

        // 1. Quick Add Supplier dari Form Master Produk
        Livewire::test(ProdukTab::class)
            ->call('openQuickAdd', 'supplier')
            ->set('quickAddForm.nama', 'PT Sinar Jaya Abadi Sparepart')
            ->set('quickAddForm.telepon', '081299998888')
            ->set('quickAddForm.kontak', 'Hendra')
            ->set('quickAddForm.alamat', 'Ruko Roxy Mas Blok D2 No. 12')
            ->call('simpanQuickAdd')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success');

        $supplier = Supplier::where('nama', 'PT Sinar Jaya Abadi Sparepart')->first();
        $this->assertNotNull($supplier);
        $this->assertEquals('081299998888', $supplier->telepon);

        // 2. Tambah Master Produk dengan Supplier Terpilih
        Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->set('produkForm.nama', 'LCD Touchscreen Realme C35')
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.harga_beli', 130000)
            ->set('produkForm.harga_jual_retail', 240000)
            ->set('produkForm.supplier_id', $supplier->id)
            ->call('simpanProduk');

        $produk = Produk::where('nama', 'LCD Touchscreen Realme C35')->first();
        $this->assertNotNull($produk);
        $this->assertEquals($supplier->id, $produk->supplier_id);
        $this->assertEquals('PT Sinar Jaya Abadi Sparepart', $produk->supplier?->nama);
        $this->assertTrue($supplier->produks->contains('id', $produk->id));
    }

    public function test_form_po_auto_select_supplier_dari_produk_dan_dukung_supplier_baru(): void
    {
        $this->actingAs($this->user);

        $supplier = Supplier::create([
            'nama' => 'CV Mega Baut & Part',
            'telepon' => '085711223344',
            'is_active' => true,
        ]);

        $gudang = Gudang::firstOrCreate(
            ['nama' => 'Gudang Utama Staging', 'cabang_id' => $this->cabang->id],
            ['kode' => 'GD-UTM', 'is_active' => true]
        );

        $produk = Produk::create([
            'nama' => 'Fleksibel On Off Xiaomi Redmi Note 10',
            'harga_beli' => 12000,
            'harga_jual_retail' => 35000,
            'supplier_id' => $supplier->id,
            'satuan' => 'pcs',
            'kondisi' => 'baru',
        ]);

        // Uji PoTab: saat produk dipilih, otomatis isi supplier PO jika masih kosong
        $testPo = Livewire::test(PoTab::class)
            ->set('showPoModal', true)
            ->call('addPoItem')
            ->set('poForm.items.0.produk_id', $produk->id)
            ->set('poForm.items.0.produk_nama', $produk->nama)
            ->call('poProdukDipilih', 0);

        $this->assertEquals($supplier->id, $testPo->get('poForm.supplier_id'));

        // Uji Tambah Supplier Baru langsung dari Modal Buat PO
        $testPo->set('supplierForm.nama', 'Distributor Baterai Nusantara')
            ->set('supplierForm.telepon', '08987654321')
            ->call('simpanSupplier');

        $supplierBaru = Supplier::where('nama', 'Distributor Baterai Nusantara')->first();
        $this->assertNotNull($supplierBaru);
        $this->assertEquals($supplierBaru->id, $testPo->get('poForm.supplier_id'));
    }

    public function test_pencarian_filter_dan_kolom_supplier_pada_master_produk(): void
    {
        $this->actingAs($this->user);

        $supA = Supplier::create(['nama' => 'PT Sinar Abadi', 'telepon' => '0811111111', 'is_active' => true]);
        $supB = Supplier::create(['nama' => 'CV Bintang Timur', 'telepon' => '0822222222', 'is_active' => true]);

        $p1 = Produk::create([
            'nama' => 'Baterai iPhone 12 Pro',
            'harga_beli' => 120000,
            'harga_jual_retail' => 250000,
            'supplier_id' => $supA->id,
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'LCD Oppo A57 Original',
            'harga_beli' => 90000,
            'harga_jual_retail' => 180000,
            'supplier_id' => $supB->id,
            'is_active' => true,
        ]);

        // 1. Pencarian keyword nama supplier di search bar
        Livewire::test(ProdukTab::class)
            ->set('produkSearch', 'Sinar Abadi')
            ->assertSee('Baterai iPhone 12 Pro')
            ->assertDontSee('LCD Oppo A57 Original');

        // 2. Filter dropdown supplier
        Livewire::test(ProdukTab::class)
            ->set('filterSupplierId', $supB->id)
            ->assertSee('LCD Oppo A57 Original')
            ->assertDontSee('Baterai iPhone 12 Pro');

        // 3. Toggle kolom supplier aktif
        Livewire::test(ProdukTab::class)
            ->call('toggleColumn', 'supplier')
            ->assertSet('visibleColumns.supplier', true)
            ->assertSee('PT Sinar Abadi')
            ->assertSee('CV Bintang Timur');
    }

    public function test_kurasi_produk_po_berdasarkan_supplier_dan_fleksibilitas_lintas_supplier(): void
    {
        $this->actingAs($this->user);

        $supA = Supplier::create(['nama' => 'PT Mitra Sejati', 'telepon' => '0833333333', 'is_active' => true]);
        $supB = Supplier::create(['nama' => 'UD Berkah Sparepart', 'telepon' => '0844444444', 'is_active' => true]);

        $prodA1 = Produk::create([
            'nama' => 'Touchscreen Vivo Y12',
            'harga_beli' => 45000,
            'harga_jual_retail' => 85000,
            'supplier_id' => $supA->id,
            'is_active' => true,
        ]);

        $prodB1 = Produk::create([
            'nama' => 'Speaker Buzzer Xiaomi Note 8',
            'harga_beli' => 15000,
            'harga_jual_retail' => 35000,
            'supplier_id' => $supB->id,
            'is_active' => true,
        ]);

        // 1. Saat modal dibuka dari context PO dengan supplierId terpilih: terkurasi otomatis
        $modal = Livewire::test(ProductSearchModal::class)
            ->call('openModal', [
                'targetIndex' => 0,
                'context' => 'po',
                'supplierId' => $supA->id,
            ]);

        $modal->assertSet('context', 'po')
            ->assertSet('curatedSupplierId', $supA->id)
            ->assertSet('supplierScope', 'curated')
            ->assertSee('Touchscreen Vivo Y12')
            ->assertDontSee('Speaker Buzzer Xiaomi Note 8'); // Terkurasi mempersempit pencarian!

        // 2. Beralih ke "Semua Produk (Lintas Supplier)" untuk mengakomodir produk dari supplier lain
        $modal->call('setSupplierScope', 'all')
            ->assertSet('supplierScope', 'all')
            ->assertSee('Touchscreen Vivo Y12')
            ->assertSee('Speaker Buzzer Xiaomi Note 8'); // Sekarang produk supplier lain dapat dipilih
    }
}
