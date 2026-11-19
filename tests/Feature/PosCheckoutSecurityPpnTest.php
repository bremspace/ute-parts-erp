<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PosCheckoutService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [H10][H11] Keamanan & pajak sisi checkout POS.
 *
 * H10: API POS-01 wajib resolve harga server-side (PricingService::resolveHargaSatuan)
 *      dan mengambil konversi dari master satuan produk — harga_satuan/konversi
 *      client adalah vektor manipulasi pendapatan & stok.
 * H11: Mode PPN inclusive wajib memakai definisi kanonik PajakService::hitungPenjualan
 *      (total = harga inclusive, DPP diekstrak) — bukan dpp + ppn yang menagih lebih.
 * Pendukung retur (H1/H3): checkout menulis harga_final neto (subtotal_item/jumlah)
 *      dan Piutang dengan referensi_tipe=Transaksi + referensi_id.
 */
class PosCheckoutSecurityPpnTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $kasir;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '110-02' => ['Bank', 'aset', 'kas', 'debit'],
            '110-04' => ['Kas Laci', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create([
            'kode' => 'CB-SEC', 'nama' => 'Cabang Security', 'alamat' => 'Jl. Aman No. 1',
            'telepon' => '0811111111', 'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GD-SEC', 'is_active' => true,
        ]);
        $this->kasir = User::create([
            'name' => 'Kasir POS', 'email' => 'kasir-sec@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->kasir->assignRole('super-admin');
        $this->kasir->cabangs()->attach($this->cabang->id);

        // Harga retail 150.000, harga beli 100.000, satuan dasar pcs, stok 100.
        $this->produk = Produk::create([
            'nama' => 'Brake Pad Secure', 'slug' => 'brake-pad-secure-'.Str::random(4),
            'kategori' => 'Rem', 'kondisi' => 'baru',
            'harga_beli' => 100000, 'harga_jual_retail' => 150000,
            'satuan' => 'pcs', 'is_active' => true,
        ]);
        StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 100,
        ]);
    }

    private function apiAuth(): void
    {
        $this->actingAs($this->kasir, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id, 'gudang_id' => $this->gudang->id]);
    }

    private function buatKomponenPosKasir(): Testable
    {
        $this->actingAs($this->kasir, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id, 'gudang_id' => $this->gudang->id]);

        return Livewire::test(PosKasir::class);
    }

    private function aktifkanPajak(string $mode, float $persen = 11.0): void
    {
        app(PajakService::class)->saveConfig($this->cabang->id, [
            'enabled' => true, 'percent' => $persen, 'nama' => 'PPN', 'mode' => $mode,
        ]);
    }

    // ===== H10: harga & konversi server-side =====

    public function test_api_pos_mengabaikan_harga_client_dan_memakai_harga_server(): void
    {
        $this->apiAuth();

        // Client nakal mengirim harga 1 rupiah — server WAJIB memakai harga master.
        $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'jumlah' => 2,
                'harga_satuan' => 1,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 300000,
            'gudang_id' => $this->gudang->id,
        ])->assertStatus(201);

        $transaksi = Transaksi::firstOrFail();
        $item = $transaksi->items->first();
        $this->assertEquals(150000.0, (float) $item->harga_satuan, 'Harga satuan harus harga server, bukan harga client');
        $this->assertEquals(300000.0, (float) $item->subtotal);
        $this->assertEquals(300000.0, (float) $transaksi->total_akhir);
        $this->assertEquals(300000.0, (float) $transaksi->subtotal);
    }

    public function test_api_pos_box_memakai_konversi_dan_harga_master_bukan_client(): void
    {
        // Master: 1 box = 12 pcs, harga jual per box 1.620.000.
        ProdukSatuanKonversi::create([
            'produk_id' => $this->produk->id, 'satuan_kode' => 'box', 'satuan_asal' => 'pcs',
            'pengali' => 1, 'faktor_konversi' => 12, 'harga_jual' => 1620000, 'urutan' => 1,
        ]);
        $this->apiAuth();

        // Client mencoba konversi=1 (stok hanya terpotong 2) & harga murah — dua-duanya diabaikan.
        $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'jumlah' => 2,
                'satuan' => 'box',
                'konversi' => 1,
                'harga_satuan' => 1,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 3240000,
            'gudang_id' => $this->gudang->id,
        ])->assertStatus(201);

        $transaksi = Transaksi::firstOrFail();
        $item = $transaksi->items->first();
        $this->assertEquals(2, (int) $item->jumlah, 'jumlah = qty SATUAN (box)');
        $this->assertSame('box', $item->satuan);
        $this->assertEquals(12.0, (float) $item->konversi, 'konversi wajib dari master, bukan client');
        $this->assertEquals(1620000.0, (float) $item->harga_satuan, 'harga per box dari master');
        $this->assertEquals(3240000.0, (float) $transaksi->total_akhir);
        // hpp per SATUAN = harga_beli x konversi (kontrak satuan/HPP).
        $this->assertEquals(1200000.0, (float) $item->hpp);
        // Stok terpotong qtyDasar = 2 x 12 = 24 pcs.
        $this->assertEquals(76, (int) StokItem::where('produk_id', $this->produk->id)->value('jumlah'));
    }

    public function test_api_pos_menolak_satuan_yang_tidak_ada_di_master_produk(): void
    {
        $this->apiAuth();

        $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'jumlah' => 1,
                'satuan' => 'drum',
                'konversi' => 1,
                'harga_satuan' => 150000,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 150000,
            'gudang_id' => $this->gudang->id,
        ])->assertStatus(422);

        $this->assertSame(0, Transaksi::count());
        $this->assertEquals(100, (int) StokItem::where('produk_id', $this->produk->id)->value('jumlah'));
    }

    // ===== H11: PPN inclusive kanonik (API) =====

    public function test_api_pos_ppn_inclusive_total_tidak_ditambah_ppn_lagi(): void
    {
        // Harga jual 111.000 sudah termasuk PPN 11% (inclusive).
        $this->produk->update(['harga_jual_retail' => 111000]);
        $this->aktifkanPajak('inclusive');
        $this->apiAuth();

        $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'jumlah' => 1,
                'harga_satuan' => 111000,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 111000,
            'gudang_id' => $this->gudang->id,
        ])->assertStatus(201);

        $transaksi = Transaksi::firstOrFail();
        $this->assertEquals(111000.0, (float) $transaksi->total_akhir, 'Inclusive: total = harga jual, bukan harga + PPN');
        $this->assertEquals(100000.0, (float) $transaksi->dpp, 'DPP = net / (1 + persen)');
        $this->assertEquals(11000.0, (float) $transaksi->pajak_nominal);

        // Jurnal: pendapatan hanya DPP, PPN keluaran terpisah, kas sebesar total tagihan.
        $pendapatan = AkunCOA::where('kode', '410-01')->value('id');
        $ppn = AkunCOA::where('kode', '220-01')->value('id');
        $bank = AkunCOA::where('kode', '110-02')->value('id');
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class, 'referensi_id' => $transaksi->id,
            'akun_coa_id' => $pendapatan, 'kredit' => 100000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class, 'referensi_id' => $transaksi->id,
            'akun_coa_id' => $ppn, 'kredit' => 11000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class, 'referensi_id' => $transaksi->id,
            'akun_coa_id' => $bank, 'debit' => 111000,
        ]);
        $jurnal = JurnalAkuntansi::where('referensi_id', $transaksi->id)->get();
        $this->assertEquals((float) $jurnal->sum('debit'), (float) $jurnal->sum('kredit'));
    }

    public function test_api_pos_ppn_exclusive_tetap_benar(): void
    {
        $this->aktifkanPajak('exclusive');
        $this->apiAuth();

        $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'jumlah' => 1,
                'harga_satuan' => 150000,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 166500,
            'gudang_id' => $this->gudang->id,
        ])->assertStatus(201);

        $transaksi = Transaksi::firstOrFail();
        $this->assertEquals(150000.0, (float) $transaksi->dpp);
        $this->assertEquals(16500.0, (float) $transaksi->pajak_nominal);
        $this->assertEquals(166500.0, (float) $transaksi->total_akhir);
    }

    // ===== H11: PPN inclusive kanonik (Livewire PosKasir) =====

    public function test_pos_kasir_ppn_inclusive_dpp_dieksrak_dan_total_tidak_doble(): void
    {
        $this->produk->update(['harga_jual_retail' => 111000]);
        $this->aktifkanPajak('inclusive');

        $component = $this->buatKomponenPosKasir()
            ->call('addToCart', $this->produk->id);

        $this->assertEquals(111000.0, (float) $component->get('subtotal'));
        $this->assertEquals(100000.0, (float) $component->get('dpp'), 'DPP inclusive harus diekstrak dari harga');
        $this->assertEquals(11000.0, (float) $component->get('pajakNominal'));
        $this->assertEquals(111000.0, (float) $component->get('totalAkhir'), 'Total inclusive = harga jual, bukan harga + PPN');

        $component->set('metodeBayar', 'transfer')->call('processTransaction');

        $transaksi = Transaksi::firstOrFail();
        $this->assertEquals(111000.0, (float) $transaksi->total_akhir);
        $this->assertEquals(100000.0, (float) $transaksi->dpp);
        $this->assertEquals(11000.0, (float) $transaksi->pajak_nominal);

        $pendapatan = AkunCOA::where('kode', '410-01')->value('id');
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class, 'referensi_id' => $transaksi->id,
            'akun_coa_id' => $pendapatan, 'kredit' => 100000,
        ]);
    }

    public function test_pos_kasir_ppn_exclusive_tetap_benar(): void
    {
        $this->aktifkanPajak('exclusive');

        $component = $this->buatKomponenPosKasir()
            ->call('addToCart', $this->produk->id);

        $this->assertEquals(150000.0, (float) $component->get('dpp'));
        $this->assertEquals(16500.0, (float) $component->get('pajakNominal'));
        $this->assertEquals(166500.0, (float) $component->get('totalAkhir'));
    }

    // ===== Pendukung retur: harga_final neto & referensi Piutang =====

    public function test_checkout_menulis_harga_final_neto_setelah_diskon_item(): void
    {
        $transaksi = app(PosCheckoutService::class)->checkout([
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasir->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 280000,
            'dpp' => 280000,
            'pajak_nominal' => 0,
            'total_akhir' => 280000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 280000,
            'kembalian' => 0,
        ], [
            [
                'produk_id' => $this->produk->id,
                'jumlah' => 2,
                'harga_satuan' => 150000,
                'diskon_nominal' => 20000,
                'subtotal' => 280000,
            ],
        ]);

        $item = $transaksi->items->first();
        // (2 x 150.000 - 20.000) / 2 = 140.000 per unit neto.
        $this->assertEquals(140000.0, (float) $item->harga_final, 'harga_final = subtotal_item / jumlah (neto diskon item)');
        $this->assertEquals(150000.0, (float) $item->harga_satuan, 'harga_satuan tetap harga kotor per satuan');
    }

    public function test_checkout_piutang_mengisi_referensi_tipe_dan_id_transaksi(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Kasbon', 'telepon' => '081299887766', 'status' => 'aktif',
        ]);

        $transaksi = app(PosCheckoutService::class)->checkout([
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasir->id,
            'pelanggan_id' => $pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 150000,
            'dpp' => 150000,
            'pajak_nominal' => 0,
            'total_akhir' => 150000,
            'metode_bayar' => 'piutang',
            'jumlah_bayar' => 0,
            'kembalian' => 0,
        ], [
            [
                'produk_id' => $this->produk->id,
                'jumlah' => 1,
                'harga_satuan' => 150000,
                'subtotal' => 150000,
            ],
        ]);

        $piutang = Piutang::where('transaksi_id', $transaksi->id)->firstOrFail();
        $this->assertSame(Transaksi::class, $piutang->referensi_tipe);
        $this->assertEquals($transaksi->id, (int) $piutang->referensi_id);
        // Lookup kanonik retur (ReturnPenjualanService) harus menemukan baris ini.
        $this->assertTrue(
            Piutang::where('referensi_tipe', Transaksi::class)
                ->where('referensi_id', $transaksi->id)->exists()
        );
    }
}
