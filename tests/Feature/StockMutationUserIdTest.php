<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-10f / P1-6 — jejak pelaku pada StockMutationLog.
 *
 * StokDeductionService::kurangi() sudah menerima ?int $userId dan sudah
 * menuliskannya ke StokLog, TETAPI StockMutationLog::create() tidak mengirimnya
 * → mutasi stok (SOT) tidak bisa dibuktikan pelakunya. Kolomnya sudah ada
 * (migrasi 2026_09_25_030100_add_user_id_to_stock_mutation_log).
 */
class StockMutationUserIdTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private SkuVariant $varian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create(['nama' => 'CBG Uji', 'kode' => 'CBG-SMU', 'is_active' => true]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'GDG Uji', 'kode' => 'GDG-SMU', 'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'nama' => 'LCD Uji B10f', 'slug' => 'lcd-uji-b10f', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 50000, 'harga_jual_retail' => 100000,
        ]);
        $this->varian = SkuVariant::create([
            'produk_id' => $this->produk->id,
            'sku' => 'SMU-B10F-01',
            'nama_varian' => 'Standar',
            'satuan_kode' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
            'jumlah_minimum' => 1,
        ]);
    }

    private function kasir(): User
    {
        return User::create([
            'name' => 'Kasir B10f',
            'email' => 'kasir-smu@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_kurangi_mengisi_user_id_di_stock_mutation_log(): void
    {
        $user = $this->kasir();

        app(StokDeductionService::class)->kurangi(
            $this->produk->id,
            $this->varian->id,
            $this->gudang->id,
            2,
            'penjualan',
            Transaksi::class,
            123,
            $user->id,
            'Uji B-10f'
        );

        $mutasi = StockMutationLog::where('sumber', 'penjualan')->first();
        $this->assertNotNull($mutasi, 'StockMutationLog harus tercatat');
        $this->assertSame($user->id, (int) $mutasi->user_id, 'user_id wajib diisi di jalur kanonik');
        $this->assertSame($user->id, (int) $mutasi->user?->id, 'Relasi user harus ter-resolve');
        $this->assertSame(-2, (int) $mutasi->delta);
    }

    public function test_kurangi_dari_gudang_tersedia_mengisi_user_id(): void
    {
        $user = $this->kasir();

        app(StokDeductionService::class)->kurangiDariGudangTersedia(
            $this->produk->id,
            $this->varian->id,
            3,
            $this->cabang->id,
            'penjualan',
            Transaksi::class,
            456,
            $user->id
        );

        $this->assertSame(1, StockMutationLog::where('sumber', 'penjualan')->count());
        $this->assertSame(
            $user->id,
            (int) StockMutationLog::where('sumber', 'penjualan')->value('user_id'),
            'Jalur multi-gudang juga harus mengisi user_id'
        );
    }

    public function test_tanpa_user_id_tetap_boleh_tapi_kolom_null(): void
    {
        // Webhook Duitku / system process tidak punya user → NULL (kolom nullable)
        app(StokDeductionService::class)->kurangi(
            $this->produk->id,
            $this->varian->id,
            $this->gudang->id,
            1,
            'penjualan',
            Transaksi::class,
            789,
            null
        );

        $mutasi = StockMutationLog::where('sumber', 'penjualan')->first();
        $this->assertNotNull($mutasi);
        $this->assertNull($mutasi->user_id);
        $this->assertNull($mutasi->user);
    }

    public function test_stok_log_dan_mutation_log_konsisten(): void
    {
        $user = $this->kasir();

        $transaksi = Transaksi::create([
            'no_transaksi' => 'SMU-B10F-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $user->id,
            'sumber' => 'pos',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);
        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'jumlah' => 1,
            'harga_satuan' => 100000,
            'subtotal' => 100000,
        ]);

        app(StokDeductionService::class)->kurangi(
            $this->produk->id,
            $this->varian->id,
            $this->gudang->id,
            1,
            'penjualan',
            Transaksi::class,
            $transaksi->id,
            $user->id
        );

        $stokLog = StokLog::where('referensi_id', $transaksi->id)->first();
        $mutasi = StockMutationLog::where('referensi_id', $transaksi->id)->first();

        $this->assertNotNull($stokLog);
        $this->assertNotNull($mutasi);
        $this->assertSame((int) $stokLog->user_id, (int) $mutasi->user_id, 'StokLog & StockMutationLog harus satu pelaku');
        $this->assertSame((int) $stokLog->perubahan, (int) $mutasi->delta);
        $this->assertSame(9, (int) StokItem::where('gudang_id', $this->gudang->id)->value('jumlah'));
    }
}
