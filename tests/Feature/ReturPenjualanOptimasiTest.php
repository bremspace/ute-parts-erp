<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturPenjualanOptimasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produkA;

    protected Produk $produkB;

    protected Produk $produkPenggantiSama;

    protected Produk $produkPenggantiKompatibel;

    protected Produk $produkPenggantiGlobal;

    protected Produk $produkPenggantiLebihMurah;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        Permission::findOrCreate('pos.view', 'web');
        Permission::findOrCreate('pos.view-own', 'web');
        $role->givePermissionTo(['pos.view', 'pos.view-own']);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Optimasi Test',
            'kode' => 'COT',
            'alamat' => 'Jl. Optimasi No 1',
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Optimasi Retur',
            'kode' => 'G-OPT',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('kasir');

        // Akun COA wajib
        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '140-01', 'nama' => 'Persediaan Barang Dagang 2', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '140-02', 'nama' => 'Persediaan Karantina', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-01', 'nama' => 'Beban Pokok Penjualan (HPP)', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $c) {
            AkunCoa::create($c);
        }

        // Setup Produk
        $this->produkA = Produk::create([
            'nama' => 'LCD Samsung A50 Original',
            'kode' => 'SAM-A50-LCD',
            'kategori' => 'LCD Touchscreen',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A50',
            'harga_beli' => 200000,
            'harga_jual' => 300000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
            'has_sn' => false,
        ]);

        $this->produkB = Produk::create([
            'nama' => 'Baterai Samsung A50 Ori',
            'kode' => 'SAM-A50-BAT',
            'kategori' => 'Baterai',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A50',
            'harga_beli' => 80000,
            'harga_jual' => 150000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
            'has_sn' => false,
        ]);

        // Prioritas 1: Produk Sama Persis
        $this->produkPenggantiSama = $this->produkA;

        // Prioritas 2: Kategori Sejenis / Kompatibel (Brand & Model Kompatibel Sama)
        $this->produkPenggantiKompatibel = Produk::create([
            'nama' => 'LCD Samsung A50 OLED Crown',
            'kode' => 'SAM-A50-OLED',
            'kategori' => 'LCD Touchscreen',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A50',
            'harga_beli' => 250000,
            'harga_jual' => 350000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
            'has_sn' => false,
        ]);

        // Prioritas 3: Global (Harga Setara / Lebih Mahal: Rp 400.000 >= Rp 300.000)
        $this->produkPenggantiGlobal = Produk::create([
            'nama' => 'Motherboard iPhone 11 64GB',
            'kode' => 'IPH-11-MBD',
            'kategori' => 'Mesin',
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'harga_beli' => 300000,
            'harga_jual' => 400000,
            'harga_jual_retail' => 400000,
            'is_active' => true,
            'has_sn' => false,
        ]);

        // Produk Lebih Murah (Rp 200.000 < Rp 300.000) -> Dilarang dipilih
        $this->produkPenggantiLebihMurah = Produk::create([
            'nama' => 'LCD Samsung A10 Incell',
            'kode' => 'SAM-A10-LCD',
            'kategori' => 'LCD Touchscreen',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A10',
            'harga_beli' => 100000,
            'harga_jual' => 200000,
            'harga_jual_retail' => 200000,
            'is_active' => true,
            'has_sn' => false,
        ]);

        // Stok Pengganti
        foreach ([$this->produkA, $this->produkPenggantiKompatibel, $this->produkPenggantiGlobal, $this->produkPenggantiLebihMurah] as $p) {
            StokItem::create([
                'gudang_id' => $this->gudang->id,
                'produk_id' => $p->id,
                'jumlah' => 10,
            ]);
        }
    }

    private function buatTransaksi(int $durasiHari = 3, ?string $tglExpired = null): Transaksi
    {
        $pelanggan = Pelanggan::create(['nama' => 'Budi Retail', 'tipe' => 'retail']);
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-TEST-OPT-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 2,
            'subtotal' => 450000,
            'total_akhir' => 450000,
            'durasi_garansi_hari' => $durasiHari,
            'garansi_berakhir_pada' => $tglExpired,
            'status' => 'selesai',
            'metode_pembayaran' => 'tunai',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $this->produkA->id,
            'jumlah' => 1,
            'harga_satuan' => 300000,
            'subtotal' => 300000,
            'hpp' => 200000,
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $this->produkB->id,
            'jumlah' => 1,
            'harga_satuan' => 150000,
            'subtotal' => 150000,
            'hpp' => 80000,
        ]);

        return $trx->fresh(['items']);
    }

    public function test_deteksi_garansi_expired_menolak_transaksi(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        // Garansi kadaluarsa 5 hari lalu
        $trx = $this->buatTransaksi(3, now()->subDays(5)->toDateString());

        Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', $trx->no_transaksi)
            ->call('cariKwitansi')
            ->assertSet('isGaransiExpired', true)
            ->assertSee('RETUR OTOMATIS DITOLAK — MASA GARANSI HABIS');

        // Service juga melempar ValidationException jika garansi habis
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Masa garansi transaksi telah habis');

        app(ReturnPenjualanService::class)->buatRetur(
            $trx,
            [['transaksi_item_id' => $trx->items->first()->id, 'jumlah' => 1]],
            'Barang cacat',
            'kas',
            $this->user->id
        );
    }

    public function test_deteksi_garansi_aktif_mengizinkan_retur(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        // Garansi aktif s.d. 3 hari ke depan
        $trx = $this->buatTransaksi(7, now()->addDays(3)->toDateString());

        Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', $trx->no_transaksi)
            ->call('cariKwitansi')
            ->assertSet('isGaransiExpired', false)
            ->assertSee('Masa Garansi Toko Aktif');
    }

    public function test_prioritas_rekomendasi_produk_pengganti_exchange_dan_blokir_produk_lebih_murah(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        $trx = $this->buatTransaksi(7, now()->addDays(5)->toDateString());
        $itemA = $trx->items->where('produk_id', $this->produkA->id)->first();

        $component = Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', $trx->no_transaksi)
            ->call('cariKwitansi');

        $rekomendasi = $component->instance()->getRekomendasiTukar($itemA->id);

        // Prioritas 1: Produk Sama Persis
        $this->assertTrue($rekomendasi['prioritas_1']->contains('id', $this->produkA->id));

        // Prioritas 2: Kompatibel / Kategori Sama
        $this->assertTrue($rekomendasi['prioritas_2']->contains('id', $this->produkPenggantiKompatibel->id));

        // Prioritas 3: Global (Harga Setara / Lebih Tinggi)
        $this->assertTrue($rekomendasi['prioritas_3']->contains('id', $this->produkPenggantiGlobal->id));

        // Produk lebih murah ditandai is_lebih_murah = true
        $pLebihMurah = $rekomendasi['prioritas_2']->firstWhere('id', $this->produkPenggantiLebihMurah->id)
            ?? $rekomendasi['prioritas_3']->firstWhere('id', $this->produkPenggantiLebihMurah->id);
        if ($pLebihMurah) {
            $this->assertTrue($pLebihMurah->is_lebih_murah);
            $this->assertFalse($pLebihMurah->bisa_dipilih);
        }

        // Memilih produk yang lebih murah ditolak di UI
        $component->call('tambahTukarItem', $this->produkPenggantiLebihMurah->id, $itemA->id)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error' && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'TIDAK BISA DIPILIH karena lebih murah'));
    }

    public function test_retur_multiple_items_dengan_kondisi_alasan_dan_metode_berbeda(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        $trx = $this->buatTransaksi(7, now()->addDays(5)->toDateString());
        $itemA = $trx->items->where('produk_id', $this->produkA->id)->first();
        $itemB = $trx->items->where('produk_id', $this->produkB->id)->first();

        // Item A: Retur Rusak -> Tukar Produk ke OLED Crown (Rp 350.000 vs Rp 300.000 -> Selisih Rp 50.000)
        // Item B: Retur Bagus -> Refund Kas Toko (Rp 150.000)
        $itemsPayload = [
            [
                'transaksi_item_id' => $itemA->id,
                'jumlah' => 1,
                'kondisi_barang' => 'rusak',
                'alasan' => 'Touchscreen mati sebagian',
                'tipe_penyelesaian' => 'tukar_produk',
                'metode_pengembalian' => 'kas',
                'foto_bukti' => ['retur-bukti/test_item_a.jpg'],
                'tukar_items' => [
                    [
                        'produk_id' => $this->produkPenggantiKompatibel->id,
                        'jumlah' => 1,
                        'harga_satuan' => 350000,
                    ],
                ],
            ],
            [
                'transaksi_item_id' => $itemB->id,
                'jumlah' => 1,
                'kondisi_barang' => 'bagus',
                'alasan' => 'Konsumen salah beli tipe baterai',
                'tipe_penyelesaian' => 'refund',
                'metode_pengembalian' => 'kas',
                'foto_bukti' => [],
                'tukar_items' => [],
            ],
        ];

        $service = app(ReturnPenjualanService::class);
        $retur = $service->buatRetur(
            $trx,
            $itemsPayload,
            'Retur multi item campur',
            'kas',
            $this->user->id,
            'campur',
            [],
            'pos',
            [],
            'Segel QC lengkap',
            'tunai',
            50000,
            0
        );

        $this->assertNotNull($retur);
        $this->assertEquals('campur', $retur->tipe_penyelesaian);
        $this->assertCount(2, $retur->items);

        $returA = $retur->items->where('produk_id', $this->produkA->id)->first();
        $this->assertEquals('rusak', $returA->kondisi_barang);
        $this->assertEquals('tukar_produk', $returA->tipe_penyelesaian);
        $this->assertEquals('Touchscreen mati sebagian', $returA->alasan);

        $returB = $retur->items->where('produk_id', $this->produkB->id)->first();
        $this->assertEquals('bagus', $returB->kondisi_barang);
        $this->assertEquals('refund', $returB->tipe_penyelesaian);
        $this->assertEquals('Konsumen salah beli tipe baterai', $returB->alasan);

        // Transaksi tukar barang otomatis terbuat dan mencatat selisih bayar
        $this->assertNotNull($retur->tukarTransaksi);
        $this->assertEquals(50000, $retur->selisih_nominal);
    }

    public function test_upload_foto_bukti_kerusakan_per_item_via_livewire(): void
    {
        Storage::fake('public');
        session(['cabang_id' => $this->cabang->id]);
        $trx = $this->buatTransaksi(7, now()->addDays(5)->toDateString());
        $itemA = $trx->items->where('produk_id', $this->produkA->id)->first();

        $fakePhoto = UploadedFile::fake()->image('bukti_kerusakan_kamera.jpg');

        $component = Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', $trx->no_transaksi)
            ->call('cariKwitansi')
            ->set("uploadFotoPerItem.{$itemA->id}", $fakePhoto);

        $uploaded = $component->get("itemInputs.{$itemA->id}.foto_bukti");
        $this->assertNotEmpty($uploaded);
        Storage::disk('public')->assertExists($uploaded[0]);

        // Hapus foto bukti
        $component->call('hapusFotoBukti', $itemA->id, 0);
        $this->assertEmpty($component->get("itemInputs.{$itemA->id}.foto_bukti"));
    }

    public function test_alur_pembayaran_selisih_tukar_barang_dan_struk_thermal_khusus(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        $trx = $this->buatTransaksi(7, now()->addDays(5)->toDateString());
        $itemA = $trx->items->where('produk_id', $this->produkA->id)->first();

        $component = Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', $trx->no_transaksi)
            ->call('cariKwitansi')
            ->set('alasan', 'Tukar unit LCD')
            ->set("itemInputs.{$itemA->id}.jumlah", 1)
            ->set("itemInputs.{$itemA->id}.tipe_penyelesaian", 'tukar_produk')
            ->call('tambahTukarItem', $this->produkPenggantiGlobal->id, $itemA->id);

        // Item A harga 300.000, Pengganti Global 400.000 -> Selisih 100.000
        $ringkasan = $component->get('ringkasanFinansial');
        $this->assertEquals(100000, $ringkasan['selisih']);

        // Input bayar uang 150.000 -> kembalian 50.000
        $component->set('metodeBayarSelisih', 'tunai')
            ->set('jumlahBayarSelisih', 150000);

        $ringkasanSetelahBayar = $component->get('ringkasanFinansial');
        $this->assertEquals(50000, $ringkasanSetelahBayar['kembalian']);

        $component->call('simpanRetur')
            ->assertSet('showReceiptModal', true);

        $receiptData = $component->get('receiptData');
        $this->assertEquals('*** STRUK RETUR & TUKAR BARANG ***', $receiptData['label_struk']);
        $this->assertEquals(100000, $receiptData['selisih']);
        $this->assertEquals(150000, $receiptData['bayar']);
        $this->assertEquals(50000, $receiptData['kembali']);
    }
}
