<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturPenjualanExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected KategoriProduk $kategoriLcd;

    protected KategoriProduk $kategoriBaterai;

    protected Produk $produkRetur;

    protected Produk $produkPenggantiSamaKategori;

    protected Produk $produkPenggantiBedaKategori;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Permission::findOrCreate('pos.view', 'web');
        Permission::findOrCreate('pos.view-own', 'web');
        $role->givePermissionTo(['pos.view', 'pos.view-own']);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Retur Pusat',
            'kode' => 'CRP',
            'alamat' => 'Jl. Retur Pusat No 10',
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Retur Utama',
            'kode' => 'G-RETUR-01',
            'is_active' => true,
        ]);

        $this->kategoriLcd = KategoriProduk::create(['nama' => 'LCD Screen', 'kode' => 'CAT-LCD']);
        $this->kategoriBaterai = KategoriProduk::create(['nama' => 'Baterai', 'kode' => 'CAT-BAT']);

        $this->produkRetur = Produk::create([
            'kode' => 'LCD-RETUR-01',
            'nama' => 'LCD iPhone X Original',
            'barcode' => 'BAR-LCD-01',
            'kategori_id' => $this->kategoriLcd->id,
            'kategori' => 'LCD Screen',
            'harga_beli' => 200000,
            'harga_jual' => 300000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
            'status' => 'aktif',
        ]);

        $this->produkPenggantiSamaKategori = Produk::create([
            'kode' => 'LCD-GANTI-01',
            'nama' => 'LCD iPhone XS OLED Premium',
            'barcode' => 'BAR-LCD-02',
            'kategori_id' => $this->kategoriLcd->id,
            'kategori' => 'LCD Screen',
            'harga_beli' => 250000,
            'harga_jual' => 400000,
            'harga_jual_retail' => 400000,
            'is_active' => true,
            'status' => 'aktif',
        ]);

        $this->produkPenggantiBedaKategori = Produk::create([
            'kode' => 'BAT-GANTI-01',
            'nama' => 'Baterai iPhone X 3500mAh',
            'barcode' => 'BAR-BAT-01',
            'kategori_id' => $this->kategoriBaterai->id,
            'kategori' => 'Baterai',
            'harga_beli' => 150000,
            'harga_jual' => 350000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
            'status' => 'aktif',
        ]);

        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];

        foreach ($coas as $c) {
            AkunCoa::firstOrCreate(['kode' => $c['kode']], $c);
        }
    }

    protected function buatTransaksiAwal(): array
    {
        $pelanggan = Pelanggan::create(['nama' => 'Konsumen Setia', 'tipe' => 'retail']);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TEST-EXCHANGE-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 1,
            'subtotal' => 300000,
            'total_akhir' => 300000,
            'status' => 'selesai',
        ]);

        $item = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produkRetur->id,
            'jumlah' => 1,
            'harga_satuan' => 300000,
            'subtotal' => 300000,
            'hpp' => 200000,
        ]);

        return [$transaksi, $item];
    }

    public function test_pencarian_produk_pengganti_livewire_filter_nama_dan_barcode(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        [$transaksi] = $this->buatTransaksiAwal();

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkPenggantiSamaKategori->id,
            'jumlah' => 10,
        ]);

        Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', $transaksi->no_transaksi)
            ->call('cariKwitansi')
            ->set('tipePenyelesaian', 'tukar_produk')
            ->set('tukarSearch', 'BAR-LCD-02')
            ->assertSee('LCD iPhone XS OLED Premium')
            ->set('tukarSearch', 'OLED Premium')
            ->assertSee('BAR-LCD-02');
    }

    public function test_validasi_kategori_produk_pengganti_beda_kategori_ditolak(): void
    {
        [$transaksi, $item] = $this->buatTransaksiAwal();

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkPenggantiBedaKategori->id,
            'jumlah' => 5,
        ]);

        $service = app(ReturnPenjualanService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('kategori yang sama');

        $service->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $item->id, 'jumlah' => 1, 'kondisi_barang' => 'bagus']],
            'Ingin ganti ke baterai',
            'kas',
            $this->user->id,
            'tukar_produk',
            [['produk_id' => $this->produkPenggantiBedaKategori->id, 'jumlah' => 1, 'harga_satuan' => 350000]]
        );
    }

    public function test_validasi_harga_unit_produk_pengganti_lebih_rendah_ditolak(): void
    {
        [$transaksi, $item] = $this->buatTransaksiAwal();

        $produkMurah = Produk::create([
            'kode' => 'LCD-MURAH',
            'nama' => 'LCD iPhone X Grade B',
            'kategori_id' => $this->kategoriLcd->id,
            'harga_beli' => 120000,
            'harga_jual' => 250000,
            'status' => 'aktif',
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produkMurah->id,
            'jumlah' => 5,
        ]);

        $service = app(ReturnPenjualanService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('tidak boleh lebih rendah dari harga barang yang diretur');

        $service->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $item->id, 'jumlah' => 1, 'kondisi_barang' => 'bagus']],
            'Tukar ke model murah',
            'kas',
            $this->user->id,
            'tukar_produk',
            [['produk_id' => $produkMurah->id, 'jumlah' => 1, 'harga_satuan' => 250000]]
        );
    }

    public function test_tukar_produk_langsung_tanpa_approval_memotong_stok_pengganti_mengembalikan_stok_retur_dan_buat_jurnal(): void
    {
        [$transaksi, $item] = $this->buatTransaksiAwal();

        // Saldo stok retur & stok pengganti
        $stokReturItem = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkRetur->id,
            'jumlah' => 2,
        ]);

        $stokPenggantiItem = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkPenggantiSamaKategori->id,
            'jumlah' => 5,
        ]);

        $service = app(ReturnPenjualanService::class);

        // Nilai retur 300.000 < rule approval 5jt
        $retur = $service->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $item->id, 'jumlah' => 1, 'kondisi_barang' => 'bagus']],
            'Tukar ke OLED Premium',
            'kas',
            $this->user->id,
            'tukar_produk',
            [['produk_id' => $this->produkPenggantiSamaKategori->id, 'jumlah' => 1, 'harga_satuan' => 400000]]
        );

        $this->assertEquals('selesai', $retur->status);
        $this->assertEquals(100000, (float) $retur->selisih_nominal);
        $this->assertNotNull($retur->tukar_transaksi_id);

        // Stok pengganti terpotong lewat StokDeductionService (5 - 1 = 4)
        $stokPenggantiItem->refresh();
        $this->assertEquals(4, (int) $stokPenggantiItem->jumlah);

        // Stok retur yang bagus dikembalikan (2 + 1 = 3)
        $stokReturItem->refresh();
        $this->assertEquals(3, (int) $stokReturItem->jumlah);

        // StokLog & StockMutationLog tercatat
        $mutationKeluar = StockMutationLog::where('produk_id', $this->produkPenggantiSamaKategori->id)
            ->where('referensi_tipe', Transaksi::class)
            ->where('delta', -1)
            ->first();
        $this->assertNotNull($mutationKeluar);

        $mutationMasuk = StockMutationLog::where('produk_id', $this->produkRetur->id)
            ->where('referensi_tipe', ReturnPenjualan::class)
            ->where('delta', 1)
            ->first();
        $this->assertNotNull($mutationMasuk);

        // Jurnal terbentuk seimbang
        $jurnal = JurnalHeader::where('referensi_tipe', ReturnPenjualan::class)
            ->where('referensi_id', $retur->id)
            ->first();
        $this->assertNotNull($jurnal);
        // Total Debit: 410-02 (300rb) + 110-01 (100rb) + 130-01 (200rb) + 510-02 (250rb) = 850.000
        // Total Kredit: 410-01 (400rb) + 510-02 (200rb) + 130-01 (250rb) = 850.000
        $this->assertEquals(850000, (float) $jurnal->total_debit);
        $this->assertEquals(850000, (float) $jurnal->total_kredit);
    }

    public function test_tukar_produk_memerlukan_approval_tidak_memotong_stok_sebelum_disetujui(): void
    {
        // Pasang rule approval threshold retur 200.000
        ApprovalRule::create([
            'entity_type' => 'retur',
            'cabang_id' => $this->cabang->id,
            'level' => 1,
            'min_amount' => 200000,
            'approver_role' => 'super-admin',
            'is_aktif' => true,
        ]);

        [$transaksi, $item] = $this->buatTransaksiAwal();

        $stokPenggantiItem = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkPenggantiSamaKategori->id,
            'jumlah' => 10,
        ]);

        $stokReturItem = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkRetur->id,
            'jumlah' => 1,
        ]);

        $service = app(ReturnPenjualanService::class);

        $retur = $service->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $item->id, 'jumlah' => 1, 'kondisi_barang' => 'bagus']],
            'Tukar LCD besar pending approval',
            'kas',
            $this->user->id,
            'tukar_produk',
            [['produk_id' => $this->produkPenggantiSamaKategori->id, 'jumlah' => 1, 'harga_satuan' => 400000]]
        );

        // Status masih draft, belum selesai, tukar transaksi belum dibuat
        $this->assertEquals('draft', $retur->status);
        $this->assertNull($retur->tukar_transaksi_id);
        $this->assertNotEmpty($retur->tukar_items_payload);

        // Stok pengganti belum terpotong (masih 10)
        $stokPenggantiItem->refresh();
        $this->assertEquals(10, (int) $stokPenggantiItem->jumlah);

        // Stok retur belum bertambah (masih 1)
        $stokReturItem->refresh();
        $this->assertEquals(1, (int) $stokReturItem->jumlah);

        // Approval request pending ada
        $approvalReq = ApprovalRequest::where('entity_type', 'retur')
            ->where('entity_id', $retur->id)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($approvalReq);

        // Approve workflow request
        $approvalService = app(ApprovalService::class);
        $approvalService->proses($approvalReq->id, 'disetujui', $this->user->id, 'Disetujui untuk tukar produk');

        // Status sekarang selesai
        $retur->refresh();
        $this->assertEquals('selesai', $retur->status);
        $this->assertNotNull($retur->tukar_transaksi_id);

        // Stok pengganti sekarang terpotong (10 - 1 = 9)
        $stokPenggantiItem->refresh();
        $this->assertEquals(9, (int) $stokPenggantiItem->jumlah);

        // Stok retur sekarang bertambah (1 + 1 = 2)
        $stokReturItem->refresh();
        $this->assertEquals(2, (int) $stokReturItem->jumlah);

        // Jurnal akuntansi exchange tercatat
        $jurnal = JurnalHeader::where('referensi_tipe', ReturnPenjualan::class)
            ->where('referensi_id', $retur->id)
            ->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(850000, (float) $jurnal->total_debit);
        $this->assertEquals(850000, (float) $jurnal->total_kredit);
    }
}
