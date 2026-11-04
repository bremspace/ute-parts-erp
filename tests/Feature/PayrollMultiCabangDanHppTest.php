<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollMultiCabangDanHppTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Cabang $cabang1;

    protected Cabang $cabang2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang1 = Cabang::find(1);
        $this->cabang2 = Cabang::create([
            'nama' => 'Cabang Surabaya',
            'kode' => 'SBY',
            'alamat' => 'Jl. Pemuda No. 2, Surabaya',
            'telepon' => '0315556789',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin-fase5@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach([$this->cabang1->id, $this->cabang2->id]);
        $this->actingAs($this->admin, 'web');
    }

    public function test_payroll_multi_cabang_terisolasi_dan_tidak_bocor(): void
    {
        // 1. Karyawan Cabang 1
        $userC1 = User::create([
            'name' => 'Staf Cabang 1',
            'email' => 'staf1@uteparts.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $karyawan1 = Karyawan::create([
            'user_id' => $userC1->id,
            'nama' => 'Budi Jakarta',
            'jabatan' => 'admin',
            'cabang_id' => $this->cabang1->id,
            'tgl_masuk' => '2025-01-01',
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
        ]);

        // 2. Karyawan Cabang 2
        $userC2 = User::create([
            'name' => 'Staf Cabang 2',
            'email' => 'staf2@uteparts.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $karyawan2 = Karyawan::create([
            'user_id' => $userC2->id,
            'nama' => 'Siti Surabaya',
            'jabatan' => 'admin',
            'cabang_id' => $this->cabang2->id,
            'tgl_masuk' => '2025-01-01',
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);

        $periode = '2026-11';
        $payrollService = app(PayrollService::class);

        // Hitung draft khusus Cabang 1
        $draftC1 = $payrollService->hitungDraft($periode, $this->cabang1->id);
        $this->assertEquals(1, $draftC1['karyawan_diproses']);
        $this->assertEquals(5000000, $draftC1['total_gaji']);
        $this->assertEquals($this->cabang1->id, $draftC1['cabang_id']);

        // Hitung draft khusus Cabang 2
        $draftC2 = $payrollService->hitungDraft($periode, $this->cabang2->id);
        $this->assertEquals(1, $draftC2['karyawan_diproses']);
        $this->assertEquals(4000000, $draftC2['total_gaji']);
        $this->assertEquals($this->cabang2->id, $draftC2['cabang_id']);

        // Verifikasi terdapat 2 record PayrollPeriode terpisah untuk periode yang sama
        $periodeC1 = PayrollPeriode::withoutCabangScope()
            ->where('periode', $periode)
            ->where('cabang_id', $this->cabang1->id)
            ->first();
        $periodeC2 = PayrollPeriode::withoutCabangScope()
            ->where('periode', $periode)
            ->where('cabang_id', $this->cabang2->id)
            ->first();

        $this->assertNotNull($periodeC1);
        $this->assertNotNull($periodeC2);
        $this->assertNotEquals($periodeC1->id, $periodeC2->id);

        // Slip karyawan 1 hanya terkait ke periode C1
        $this->assertEquals(1, $periodeC1->slips()->count());
        $this->assertEquals($karyawan1->id, $periodeC1->slips()->first()->karyawan_id);

        // Slip karyawan 2 hanya terkait ke periode C2
        $this->assertEquals(1, $periodeC2->slips()->count());
        $this->assertEquals($karyawan2->id, $periodeC2->slips()->first()->karyawan_id);

        // Approve & Bayar payroll Cabang 1
        $payrollService->finalisasiDisetujui($periode, $this->cabang1->id);
        $payrollService->bayarPayroll($periode, '110-01', $this->cabang1->id);

        $periodeC1->refresh();
        $periodeC2->refresh();

        $this->assertEquals(PayrollPeriode::STATUS_DIBAYAR, $periodeC1->status);
        $this->assertEquals(PayrollPeriode::STATUS_DRAFT, $periodeC2->status);

        // Verifikasi jurnal tercatat dengan cabang_id = 1
        $jurnalC1 = JurnalAkuntansi::where('no_jurnal', 'JRL-PR-'.$periode)
            ->where('cabang_id', $this->cabang1->id)
            ->first();
        $this->assertNotNull($jurnalC1);
    }

    public function test_moving_average_cost_produk_per_cabang(): void
    {
        $kategori = KategoriProduk::create([
            'nama' => 'Sparepart Layar',
            'kode' => 'CAT-SCR',
            'is_aktif' => true,
        ]);

        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'LCD iPhone 13 Original',
            'sku' => 'LCD-IP13-ORI',
            'satuan' => 'pcs',
            'harga_beli' => 500000,
            'harga_jual_retail' => 750000,
            'metode_harga_beli' => 'average',
            'is_aktif' => true,
        ]);

        // Gudang Cabang 1 dan Cabang 2
        $gudangC1 = Gudang::create([
            'cabang_id' => $this->cabang1->id,
            'nama' => 'Gudang Utama JKT',
            'kode' => 'GDG-JKT',
            'is_aktif' => true,
        ]);

        $gudangC2 = Gudang::create([
            'cabang_id' => $this->cabang2->id,
            'nama' => 'Gudang Utama SBY',
            'kode' => 'GDG-SBY',
            'is_aktif' => true,
        ]);

        // Stok awal Cabang 1: 10 unit @ Rp 500.000
        $stokItemC1 = StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudangC1->id,
            'jumlah' => 10,
        ]);

        // Stok awal Cabang 2: 50 unit (tidak boleh mempengaruhi moving average Cabang 1 jika scoped)
        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudangC2->id,
            'jumlah' => 50,
        ]);

        $produkService = app(ProdukService::class);

        // Pada alur GRN riil, stok barang diterima di gudang bertambah terlebih dahulu
        // lalu sesuaikanHargaBeliProduk dipanggil dengan stok baru yang sudah masuk.
        $stokItemC1->increment('jumlah', 10);

        // Penerimaan barang baru di Cabang 1: 10 unit @ Rp 600.000
        // Stok Cabang 1 sekarang: 20 unit (10 lama + 10 masuk)
        // Average cost Cabang 1: ((10 * 500.000) + (10 * 600.000)) / (10 + 10) = 550.000
        // Jika tidak di-scope cabang, stok total adalah 70 unit, menghasilkan angka berbeda.
        $res = $produkService->sesuaikanHargaBeliProduk(
            produkId: $produk->id,
            qtyMasuk: 10,
            hargaBeliMasuk: 600000,
            opsiMetode: 'average',
            skemaJual: null,
            nilaiJualAtauMargin: null,
            cabangId: $this->cabang1->id
        );

        $this->assertTrue($res['sukses']);
        $produk->refresh();

        $this->assertEquals(550000, (float) $produk->harga_beli);
    }
}
