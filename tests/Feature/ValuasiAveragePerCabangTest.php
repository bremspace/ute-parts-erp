<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBiayaCabang;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * H9 — Valuasi weighted-average per cabang (IAS 2 ¶25).
 *
 * - Average per cabang disimpan di produk_biaya_cabang; master global hanya
 *   menerima weighted-average GLOBAL yang benar (bukan average cabang lain).
 * - Semua varian SKU relevan di-update (bukan hanya varian pertama).
 * - Rekonsiliasi GL 130-01 vs Σ(stok x biaya) memposting jurnal selisih
 *   pembulatan secara idempoten.
 */
class ValuasiAveragePerCabangTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang1;

    private Cabang $cabang2;

    private Gudang $gudang1;

    private Gudang $gudang2;

    private User $user;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
            ['210-01', 'Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            ['520-08', 'Selisih Stok', 'beban', 'hpp', 'debit'],
        ] as [$kode, $nama, $tipe, $kelompok, $normal]) {
            AkunCOA::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $normal]
            );
        }

        $this->cabang1 = Cabang::create(['kode' => 'UTP-V1', 'nama' => 'Cabang V1', 'is_active' => true]);
        $this->cabang2 = Cabang::create(['kode' => 'UTP-V2', 'nama' => 'Cabang V2', 'is_active' => true]);
        $this->gudang1 = Gudang::create(['cabang_id' => $this->cabang1->id, 'nama' => 'Gudang V1', 'kode' => 'GDG-V1', 'is_active' => true]);
        $this->gudang2 = Gudang::create(['cabang_id' => $this->cabang2->id, 'nama' => 'Gudang V2', 'kode' => 'GDG-V2', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Admin Valuasi',
            'email' => 'admin-valuasi@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');

        $this->produk = Produk::create([
            'nama' => 'Produk Valuasi',
            'slug' => Str::slug('Produk Valuasi').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 500000,
            'harga_jual_retail' => 750000,
            'metode_harga_beli' => 'average',
            'is_active' => true,
        ]);
    }

    public function test_average_per_cabang_tidak_mengkontaminasi_master_global(): void
    {
        // Cabang 1: 10 unit, Cabang 2: 50 unit (biaya cabang 2 belum diketahui → fallback master 500.000)
        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang1->id, 'jumlah' => 10]);
        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang2->id, 'jumlah' => 50]);

        // GRN cabang 1: stok sudah masuk dulu (10 → 20), lalu average disesuaikan
        StokItem::where('gudang_id', $this->gudang1->id)->increment('jumlah', 10);

        $res = app(ProdukService::class)->sesuaikanHargaBeliProduk(
            $this->produk->id,
            10,
            600000,
            'average',
            null,
            null,
            $this->cabang1->id
        );

        $this->assertTrue($res['sukses']);

        // Average cabang 1 = (10x500.000 + 10x600.000)/20 = 550.000 — tersimpan per cabang
        $biaya1 = ProdukBiayaCabang::where('produk_id', $this->produk->id)->where('cabang_id', $this->cabang1->id)->firstOrFail();
        $this->assertEquals(550000, (float) $biaya1->harga_beli);
        $this->assertEquals(550000, (float) ($res['harga_beli_cabang'] ?? 0));

        // Master = weighted average GLOBAL yang benar:
        // (10x500.000 + 50x500.000 + 10x600.000) / 70 = 36.000.000/70 = 514.285,71
        // BUKAN 550.000 (average cabang 1 yang mengkontaminasi cabang 2).
        $this->assertEquals(514285.71, (float) $this->produk->fresh()->harga_beli);

        // Cabang 2 tidak boleh menerima average cabang 1 (550.000). Baris biaya
        // cabang 2 (bila di-materialisasi) tetap biaya fallback-nya sendiri 500.000.
        $biaya2 = ProdukBiayaCabang::where('produk_id', $this->produk->id)->where('cabang_id', $this->cabang2->id)->first();
        if ($biaya2 !== null) {
            $this->assertEquals(500000, (float) $biaya2->harga_beli);
        }
    }

    public function test_average_mengupdate_semua_varian_bukan_hanya_pertama(): void
    {
        $variants = collect();
        foreach (['V1', 'V2', 'V3'] as $i => $kode) {
            $variants->push(SkuVariant::create([
                'produk_id' => $this->produk->id,
                'sku' => 'VAL-'.$kode.'-'.Str::random(3),
                'nama_varian' => 'Varian '.$kode,
                'harga_beli' => 400000 + ($i * 10000),
                'is_active' => true,
            ]));
        }

        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang1->id, 'jumlah' => 0]);
        StokItem::where('gudang_id', $this->gudang1->id)->increment('jumlah', 10);

        app(ProdukService::class)->sesuaikanHargaBeliProduk(
            $this->produk->id,
            10,
            600000,
            'average',
            null,
            null,
            $this->cabang1->id
        );

        // Stok lama 0 → average = harga masuk 600.000 untuk SEMUA varian
        foreach ($variants as $v) {
            $this->assertEquals(600000, (float) $v->fresh()->harga_beli, "Varian {$v->sku} wajib ikut ter-update");
        }
        $this->assertEquals(600000, (float) $this->produk->fresh()->harga_beli);
    }

    public function test_retur_average_per_cabang_hitung_ulang_biaya_cabang_dan_master(): void
    {
        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang1->id, 'jumlah' => 10]);
        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang2->id, 'jumlah' => 50]);
        StokItem::where('gudang_id', $this->gudang1->id)->increment('jumlah', 10);

        $svc = app(ProdukService::class);
        $svc->sesuaikanHargaBeliProduk($this->produk->id, 10, 600000, 'average', null, null, $this->cabang1->id);
        // cabang1: 20 unit @550.000; master global 514.285,71

        // Retur 5 unit dari cabang 1 at biaya masuk 600.000 (stok sudah dipotong dulu)
        StokItem::where('gudang_id', $this->gudang1->id)->decrement('jumlah', 5);
        $svc->sesuaikanAverageRetur($this->produk->id, 5, 600000, $this->cabang1->id);

        // avg cabang1 = (20x550.000 - 5x600.000)/15 = 8.000.000/15 = 533.333,33
        $biaya1 = ProdukBiayaCabang::where('produk_id', $this->produk->id)->where('cabang_id', $this->cabang1->id)->firstOrFail();
        $this->assertEquals(533333.33, (float) $biaya1->harga_beli);

        // master global = (15x533.333,33 + 50x500.000)/65 = 33.000.000/65 = 507.692,31
        $this->assertEquals(507692.31, (float) $this->produk->fresh()->harga_beli);
    }

    public function test_command_rekonsiliasi_persediaan_terdaftar_dan_dry_run(): void
    {
        $this->artisan('ute:rekonsiliasi-persediaan', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_rekonsiliasi_gl_persediaan_memposting_selisih_dan_idempoten(): void
    {
        // Subledger: 10 unit x 10.000 = 100.000
        $this->produk->update(['harga_beli' => 10000]);
        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudang1->id, 'jumlah' => 10]);

        // GL hanya 99.950 (drift pembulatan 50)
        $js = app(JurnalService::class);
        $js->post(
            $js->generateNoJurnal('beli', $this->cabang1->id),
            now(),
            'pembelian',
            [
                ['akun_kode' => '130-01', 'debit' => 99950, 'kredit' => 0],
                ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 99950],
            ],
            'Pembelian simulasi drift',
            $this->cabang1->id,
            $this->user->id
        );

        $svc = app(ProdukService::class);
        $hasil = $svc->rekonsiliasiPersediaan($this->cabang1->id, $this->user->id);

        $this->assertEquals(100000, (float) $hasil['nilai_subledger']);
        $this->assertEquals(99950, (float) $hasil['nilai_gl']);
        $this->assertEquals(50, (float) $hasil['selisih']);
        $this->assertTrue($hasil['jurnal_diposting']);

        // Jurnal revaluasi: Dr 130-01 50 / Cr 520-08 50 → GL = subledger
        $this->assertEquals(100000, (float) JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', '130-01'))->sum('debit'));

        // Panggilan kedua: tidak ada drift → tidak ada jurnal baru (idempoten)
        $jumlahJurnalSebelum = JurnalAkuntansi::count();
        $hasil2 = $svc->rekonsiliasiPersediaan($this->cabang1->id, $this->user->id);
        $this->assertEquals(0, (float) $hasil2['selisih']);
        $this->assertFalse($hasil2['jurnal_diposting']);
        $this->assertSame($jumlahJurnalSebelum, JurnalAkuntansi::count());
    }
}
