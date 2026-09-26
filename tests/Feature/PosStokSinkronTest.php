<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-02 + B-03 (Fase A — kode): akurasi stok POS & sinkronisasi modul.
 *
 * Fixture MENIRU data live: stok_items BERVARIAN (TANPA baris sku_variant_id NULL).
 * Fixture lama (varian null semua) menutupi bug P0-1 — addToCart dulu query
 * `sku_variant_id IS NULL` → 0 dari 216 baris → hard-block "Stok habis".
 *
 * (a) addToCart sukses dgn stok ada — kriteria SAMA dgn gauge (agregat produk+gudang)
 * (b) produk stok 0 DITOLAK (kebijakan owner final — backorder dibatalkan):
 *     tidak masuk keranjang + pesan Indonesia, checkout & potong stok ditolak
 * (c) potong stok → stok_log akurat (sebelum/setelah baris kanonik) + StockMutationLog
 * (d) gudang lintas cabang ditolak (dropdown, addToCart & processTransaction)
 * (e) katalog tidak hard-stop di 16 (load-more) — B-02 "tidak menampilkan seluruh produk"
 */
class PosStokSinkronTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private Gudang $gudang;

    private Gudang $gudangLain;

    private Gudang $gudangCadang;

    private User $kasir;

    private Produk $produk;

    private Produk $produkHabis;

    private Produk $produkTanpaBaris;

    private SkuVariant $varianA;

    private SkuVariant $varianB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // COA wajib utk JurnalService::post (jalur POS)
        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
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
            'kode' => 'POS-A', 'nama' => 'Cabang Pusat', 'alamat' => 'Jl. Pusat',
            'telepon' => '0811111111', 'is_active' => true,
        ]);
        $this->cabangLain = Cabang::create([
            'kode' => 'POS-B', 'nama' => 'Cabang Lain', 'alamat' => 'Jl. Lain',
            'telepon' => '0822222222', 'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama Pusat',
            'kode' => 'GDG-PA', 'is_active' => true,
        ]);
        $this->gudangLain = Gudang::create([
            'cabang_id' => $this->cabangLain->id, 'nama' => 'Gudang Cabang Lain',
            'kode' => 'GDG-LB', 'is_active' => true,
        ]);
        // Gudang kedua MASIH cabang aktif — dipakai uji pesan "pilih gudang lain"
        // (produkHabis stoknya nol di Gudang Utama, ada di gudang ini).
        $this->gudangCadang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Cadang Pusat',
            'kode' => 'GDG-PC', 'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir POS', 'email' => 'kasir-pos@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->kasir->assignRole('super-admin');
        $this->kasir->cabangs()->attach($this->cabang->id);

        // ===== Produk + varian + stok_items BERVARIAN (meniru data live) =====
        $this->produk = Produk::create([
            'nama' => 'LCD iPhone 13 Bervarian', 'slug' => Str::slug('LCD iPhone 13 Bervarian').'-'.Str::random(4),
            'kategori' => 'LCD / Layar', 'kondisi' => 'oem',
            'harga_beli' => 750000, 'harga_jual_retail' => 1100000,
            'is_active' => true, 'sn' => false,
        ]);
        $this->varianA = SkuVariant::create([
            'produk_id' => $this->produk->id, 'sku' => 'SKU-LCD13-A'.Str::random(4),
            'nama_varian' => 'Hitam', 'harga_beli' => 750000, 'harga_jual_retail' => 1100000,
            'is_active' => true,
        ]);
        $this->varianB = SkuVariant::create([
            'produk_id' => $this->produk->id, 'sku' => 'SKU-LCD13-B'.Str::random(4),
            'nama_varian' => 'Putih', 'harga_beli' => 750000, 'harga_jual_retail' => 1100000,
            'is_active' => true,
        ]);
        // TANPA baris sku_variant_id NULL — persis pola data live
        StokItem::create(['produk_id' => $this->produk->id, 'sku_variant_id' => $this->varianA->id,
            'gudang_id' => $this->gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 2]);
        StokItem::create(['produk_id' => $this->produk->id, 'sku_variant_id' => $this->varianB->id,
            'gudang_id' => $this->gudang->id, 'jumlah' => 15, 'jumlah_minimum' => 2]);
        // Stok di cabang lain — tidak boleh tersentuh / terlihat
        StokItem::create(['produk_id' => $this->produk->id, 'sku_variant_id' => $this->varianA->id,
            'gudang_id' => $this->gudangLain->id, 'jumlah' => 999, 'jumlah_minimum' => 0]);

        // Produk stok 0 (baris varian ada, jumlah 0)
        $this->produkHabis = Produk::create([
            'nama' => 'Kamera Bekas Stok Nol', 'slug' => Str::slug('Kamera Bekas Stok Nol').'-'.Str::random(4),
            'kategori' => 'Kamera', 'kondisi' => 'compatible',
            'harga_beli' => 100000, 'harga_jual_retail' => 200000,
            'is_active' => true, 'sn' => false,
        ]);
        $varianHabis = SkuVariant::create([
            'produk_id' => $this->produkHabis->id, 'sku' => 'SKU-CAM0-'.Str::random(4),
            'nama_varian' => 'Standar', 'harga_beli' => 100000, 'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);
        StokItem::create(['produk_id' => $this->produkHabis->id, 'sku_variant_id' => $varianHabis->id,
            'gudang_id' => $this->gudang->id, 'jumlah' => 0, 'jumlah_minimum' => 2]);
        // Stok produkHabis ADA di gudang cadangan → di gudang utama tetap 0
        StokItem::create(['produk_id' => $this->produkHabis->id, 'sku_variant_id' => $varianHabis->id,
            'gudang_id' => $this->gudangCadang->id, 'jumlah' => 7, 'jumlah_minimum' => 2]);

        // Produk tanpa BARIS stok sama sekali (kasus log tanpa baris — wajib tetap akurat)
        $this->produkTanpaBaris = Produk::create([
            'nama' => 'Flexible Board Tanpa Baris', 'slug' => Str::slug('Flexible Board Tanpa Baris').'-'.Str::random(4),
            'kategori' => 'Flexible & Board', 'kondisi' => 'baru',
            'harga_beli' => 32000, 'harga_jual_retail' => 75000,
            'is_active' => true, 'sn' => false,
        ]);
    }

    /** Key keranjang utk produk tanpa varian dipilih dari kartu (variant = null). */
    private function key(Produk $produk): string
    {
        return $produk->id.'-0';
    }

    private function buatKomponen()
    {
        $this->actingAs($this->kasir, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id]);

        return Livewire::test(PosKasir::class);
    }

    // ===== (a) addToCart sukses dgn stok ada — kriteria = gauge (agregat) =====

    public function test_add_to_cart_sukses_dgn_stok_bervarian_dan_sama_dgn_gauge(): void
    {
        $key = $this->key($this->produk);

        $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            ->assertSet('cart.'.$key.'.qty', 1)
            // agregat varian (10 + 15) — SAMA dgn angka gauge di kartu, bukan 0
            ->assertSet('cart.'.$key.'.stok_max', 25)
            ->assertNotDispatched('alert');

        // Kriteria identik dgn tampilan kartu
        $this->assertSame(
            25,
            (int) StokItem::where('produk_id', $this->produk->id)
                ->where('gudang_id', $this->gudang->id)->sum('jumlah')
        );

        // Qty naik tetap dibatasi stok agregat (25) — batas konsisten
        $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            ->call('updateQty', $key, 1)
            ->assertSet('cart.'.$key.'.qty', 2);
    }

    // ===== (b) produk stok 0 DITOLAK — hard-block, tidak masuk keranjang =====

    public function test_produk_stok_nol_ditolak_tidak_masuk_keranjang(): void
    {
        $keyHabis = $this->key($this->produkHabis);
        $keyTanpaBaris = $this->key($this->produkTanpaBaris);

        $component = $this->buatKomponen()
            ->call('addToCart', $this->produkHabis->id)
            // Hard-block: TIDAK ada item di keranjang sama sekali
            ->assertSet('cart', [])
            ->assertDispatched('alert')
            // Produk tanpa baris stok = stok 0 juga → ditolak
            ->call('addToCart', $this->produkTanpaBaris->id)
            ->assertSet('cart', [])
            ->assertDispatched('alert');

        $this->assertArrayNotHasKey($keyHabis, $component->get('cart'));
        $this->assertArrayNotHasKey($keyTanpaBaris, $component->get('cart'));

        // Tidak ada efek samping: subtotal & PPN tetap 0
        $this->assertSame(0.0, $component->get('subtotal'));
        $this->assertSame(0.0, $component->get('pajakNominal'));

        // Checkout juga ditolak — keranjang kosong
        $component->set('metodeBayar', 'transfer')
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', false)
            ->call('processTransaction');

        $this->assertSame(0, Transaksi::count());
        $this->assertSame(0, StokLog::count());
        $this->assertSame(0, StockMutationLog::count());
    }

    // ===== (b2) Pesan Indonesia: stok habis → "pilih gudang lain" =====

    public function test_pesan_indonesia_stok_habis_menyuruh_pilih_gudang_lain(): void
    {
        $component = $this->buatKomponen()
            ->call('addToCart', $this->produkHabis->id)
            ->assertSet('cart', []);

        $component->assertDispatched('alert', function (string $name, array $params) {
            $p = $params[0] ?? [];

            return ($p['type'] ?? null) === 'error'
                && str_contains($p['message'] ?? '', 'Stok '.$this->produkHabis->nama.' habis di gudang ini')
                && str_contains($p['message'] ?? '', 'pilih gudang lain');
        });

        // Bukti stoknya memang ada di gudang lain → pesan "pilih gudang lain" jujur
        $this->assertSame(7, (int) StokItem::where('produk_id', $this->produkHabis->id)
            ->where('gudang_id', $this->gudangCadang->id)->sum('jumlah'));

        // Pindah ke gudang cadangan → produk stok 0 di gudang lama MASIH bisa dipakai
        $component->set('selectedGudangId', $this->gudangCadang->id)
            ->call('addToCart', $this->produkHabis->id)
            ->assertSet('cart.'.$this->key($this->produkHabis).'.qty', 1)
            ->assertSet('cart.'.$this->key($this->produkHabis).'.stok_max', 7);
    }

    // ===== (b3) Gudang belum dipilih → stok 0, blokir + pesan "Pilih gudang" =====

    public function test_tanpa_gudang_produk_ditolak_dengan_pesan_pilih_gudang(): void
    {
        // STOK_TANPA_GUDANG = 0 → TIDAK ada jalan bypass "gudang belum dipilih"
        // (dulu 999 = stok(longgar) → produk bisa masuk keranjang tanpa stok)
        $this->assertSame(0, PosKasir::STOK_TANPA_GUDANG);

        // Cabang tanpa gudang sama sekali → selectedGudangId tidak bisa terisi
        $cabangTanpaGudang = Cabang::create([
            'kode' => 'POS-C', 'nama' => 'Cabang Tanpa Gudang', 'alamat' => 'Jl. Kosong',
            'telepon' => '0833333333', 'is_active' => true,
        ]);
        $this->kasir->cabangs()->attach($cabangTanpaGudang->id);

        $this->actingAs($this->kasir, 'web');
        $this->withSession(['cabang_id' => $cabangTanpaGudang->id]);

        $component = Livewire::test(PosKasir::class)
            ->assertSet('selectedGudangId', null)
            ->call('addToCart', $this->produk->id)
            ->assertSet('cart', []);

        $component->assertDispatched('alert', function (string $name, array $params) {
            $p = $params[0] ?? [];

            return ($p['type'] ?? null) === 'error'
                && str_contains($p['message'] ?? '', 'Pilih gudang terlebih dahulu');
        });

        // Tidak ada stok yang terpakai
        $this->assertSame(0, StokLog::count());
        $this->assertSame(25, (int) StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)->sum('jumlah'));
    }

    // ===== (b4) Kartu katalog stok 0 tampil tidak bisa dipilih (bukan cuma cek server) =====

    public function test_kartu_produk_stok_nol_tampil_tidak_bisa_dipilih(): void
    {
        $this->buatKomponen()
            // Kasir melihat & paham: kartu stok 0 terkunci (aria-disabled + keterangan)
            ->assertSee('aria-disabled', false)
            ->assertSee('Stok kosong')
            ->assertSee('habis di gudang ini — tidak bisa dipilih')
            // Produk stok ADA tetap punya aksi addToCart (tidak ikut terkunci)
            ->assertSee('addToCart('.$this->produk->id.')')
            // Banner backorder sudah dihapus
            ->assertDontSee('backorder');
    }

    // ===== (b5) Keranjang berisi stok 0 (tamper/resume) TIDAK bisa dibayar =====

    public function test_keranjang_stok_nol_ditolak_saat_proses_bayar(): void
    {
        // Simulasikan keranjang yang sudah ada dari sesi ditahan / payload lama
        $component = $this->buatKomponen()
            ->set('cart', [[
                'produk_id' => $this->produkHabis->id,
                'sku_variant_id' => null,
                'nama' => $this->produkHabis->nama,
                'varian' => 'Standar',
                'harga' => 200000,
                'qty' => 1,
                'diskon' => 0.0,
                'subtotal' => 200000,
                'stok_max' => 0,
                'sn' => false,
                'sn_list' => [],
            ]])
            ->set('metodeBayar', 'transfer')
            ->call('processTransaction');

        $component->assertDispatched('alert', function (string $name, array $params) {
            $p = $params[0] ?? [];

            return ($p['type'] ?? null) === 'error'
                && str_contains($p['message'] ?? '', 'habis di gudang ini');
        });

        // Tidak ada transaksi / mutasi stok sama sekali
        $this->assertSame(0, Transaksi::count());
        $this->assertSame(0, StokLog::count());
        $this->assertSame(0, StockMutationLog::count());
        $this->assertSame(0, (int) StokItem::where('produk_id', $this->produkHabis->id)
            ->where('gudang_id', $this->gudang->id)->value('jumlah'));
    }

    // ===== (b6) Stok > 0 tetap bisa & qty dibatasi stok riil =====

    public function test_produk_stok_ada_tetap_bisa_dan_qty_dibatasi_stok(): void
    {
        $key = $this->key($this->produk);   // agregat 10 + 15 = 25

        $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            ->assertSet('cart.'.$key.'.qty', 1)
            ->assertNotDispatched('alert')
            ->call('updateQty', $key, 1)
            ->assertSet('cart.'.$key.'.qty', 2)
            ->assertSet('cart.'.$key.'.stok_max', 25);
    }

    public function test_qty_dibatasi_hard_stok_riil_tanpa_bypass(): void
    {
        $key = $this->key($this->produk);
        $component = $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            // Tamper stok_max (prop publik Livewire) → TIDAK boleh jadi jalan bypass
            ->set('cart.'.$key.'.stok_max', 9999)
            ->set('cart.'.$key.'.qty', 25);

        // Tambah 1 → nyata stok 25 → mentok di 25 (bukan 26)
        $component->call('updateQty', $key, 1)
            ->assertSet('cart.'.$key.'.qty', 25)
            ->assertDispatched('alert', function (string $name, array $params) {
                return str_contains($params[0]['message'] ?? '', 'maksimal 25 unit');
            });

        // Jalur klik kartu (addToCart) juga mentok di stok riil
        $component->call('addToCart', $this->produk->id)
            ->assertSet('cart.'.$key.'.qty', 25);
    }

    public function test_qty_nol_stok_membuang_item_dari_keranjang(): void
    {
        $key = $this->key($this->produk);

        $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            ->assertSet('cart.'.$key.'.qty', 1)
            // Stok produk di gudang utama habis (tanpa baris stok → 0)
            ->set('selectedGudangId', $this->gudangCadang->id)
            ->call('updateQty', $key, 1)
            ->assertSet('cart', [])
            ->assertDispatched('alert', fn (string $name, array $params) => str_contains($params[0]['message'] ?? '', 'habis di gudang ini'));
    }

    // ===== (c) Potong stok → stok_log akurat + StockMutationLog + tidak bocor cabang =====

    public function test_potong_stok_mencatat_stok_log_dan_mutation_log_akurat(): void
    {
        $key = $this->key($this->produk);

        $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            ->call('updateQty', $key, 1) // qty 2
            ->set('metodeBayar', 'transfer')
            ->call('processTransaction');

        $this->assertSame(1, Transaksi::count());
        $transaksi = Transaksi::firstOrFail();
        $this->assertEquals('selesai', $transaksi->status);

        // Baris kanonik = varian dgn jumlah terbesar (15) → 13; varian lain tidak tersentuh
        $this->assertEquals(13, (int) StokItem::where('sku_variant_id', $this->varianB->id)->value('jumlah'));
        $this->assertEquals(10, (int) StokItem::where('sku_variant_id', $this->varianA->id)->value('jumlah'));

        // agregat produk+gudang = 25 - 2 = 23 (sama dgn yg dilihat gauge)
        $this->assertEquals(23, (int) StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)->sum('jumlah'));

        // stok_log akurat — sebelum/setelah baris kanonik, varian benar
        $log = StokLog::where('produk_id', $this->produk->id)
            ->where('jenis', 'penjualan')
            ->where('referensi_id', $transaksi->id)->firstOrFail();
        $this->assertEquals(15, (int) $log->jumlah_sebelum);
        $this->assertEquals(-2, (int) $log->perubahan);
        $this->assertEquals(13, (int) $log->jumlah_setelah);
        $this->assertEquals($this->varianB->id, (int) $log->sku_variant_id);
        $this->assertEquals($this->gudang->id, (int) $log->gudang_id);

        // StockMutationLog (T-26 / ChannelSyncService)
        $mutasi = StockMutationLog::where('produk_id', $this->produk->id)
            ->where('referensi_id', $transaksi->id)->firstOrFail();
        $this->assertEquals(-2, (int) $mutasi->delta);
        $this->assertSame('penjualan', $mutasi->sumber);
        $this->assertEquals($this->varianB->id, (int) $mutasi->sku_variant_id);

        // Stok cabang lain tidak tersentuh
        $this->assertEquals(999, (int) StokItem::where('gudang_id', $this->gudangLain->id)->sum('jumlah'));

        // Jurnal POS tetap balance
        $jurnal = JurnalAkuntansi::where('sumber', 'pos')->get();
        $this->assertNotEmpty($jurnal);
        $this->assertEquals((float) $jurnal->sum('debit'), (float) $jurnal->sum('kredit'));
    }

    // ===== (d) Gudang lintas cabang ditolak =====

    public function test_gudang_lintas_cabang_ditolak(): void
    {
        $key = $this->key($this->produk);

        // 1) Dropdown hanya menampilkan gudang cabang aktif
        $this->buatKomponen()
            ->assertSee('Gudang Utama Pusat')
            ->assertSee('Gudang Cadang Pusat') // gudang kedua, cabang yang sama
            ->assertDontSee('Gudang Cabang Lain')
            // 2) Tamper selectedGudangId → addToCart reset ke gudang cabang aktif + pesan
            ->set('selectedGudangId', $this->gudangLain->id)
            ->call('addToCart', $this->produk->id)
            ->assertSet('selectedGudangId', $this->gudang->id)
            ->assertDispatched('alert')
            // stok dihitung dari gudang cabang aktif (25), BUKAN 999 milik cabang lain
            ->assertSet('cart.'.$key.'.stok_max', 25);

        // 3) processTransaction juga me-reset → transaksi memakai gudang cabang aktif
        $this->buatKomponen()
            ->call('addToCart', $this->produk->id)
            ->set('selectedGudangId', $this->gudangLain->id)
            ->set('metodeBayar', 'transfer')
            ->call('processTransaction');

        $this->assertSame(1, Transaksi::count());
        $this->assertEquals($this->gudang->id, (int) Transaksi::firstOrFail()->gudang_id);
        $this->assertEquals(
            999,
            (int) StokItem::where('gudang_id', $this->gudangLain->id)->sum('jumlah'),
            'Stok cabang lain tidak boleh terpotong'
        );
    }

    // ===== (f) [B-10b/P1-2] API POS (PosController) juga lewat StokDeductionService =====

    /**
     * API POS-01 sebelumnya menulis StokLog saja → stock_mutation_log kosong
     * (data live: 12 stok_log vs 3 stock_mutation_log). Sekarang PosController
     * mendelegasikan ke StokDeductionService yang menulis keduanya + mengunci
     * baris stok. Test ini mengunci kontrak itu.
     */
    public function test_api_pos_mengurangi_stok_lewat_service_sehingga_mutation_log_terisi(): void
    {
        $this->actingAs($this->kasir, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id, 'gudang_id' => $this->gudang->id]);

        $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varianA->id,
                'jumlah' => 2,
                'harga_satuan' => 1100000,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 2200000,
            'gudang_id' => $this->gudang->id,
        ])->assertSuccessful();

        $transaksi = Transaksi::firstOrFail();

        // Baris varian yang ditransaksikan yang terpotong (varian lain utuh)
        $this->assertEquals(8, (int) StokItem::where('sku_variant_id', $this->varianA->id)->value('jumlah'));
        $this->assertEquals(15, (int) StokItem::where('sku_variant_id', $this->varianB->id)->value('jumlah'));

        // [B-10b/P1-2] stock_mutation_log SEKARANG terisi (sebelumnya 0 dari API POS)
        $mutasi = StockMutationLog::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)->firstOrFail();
        $this->assertEquals(-2, (int) $mutasi->delta);
        $this->assertSame('penjualan', $mutasi->sumber);
        $this->assertEquals($this->varianA->id, (int) $mutasi->sku_variant_id);
        $this->assertEquals($this->gudang->id, (int) $mutasi->gudang_id);

        // StokLog tidak regresi (sebelum/setelah akurat + user kasir)
        $log = StokLog::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)->firstOrFail();
        $this->assertEquals(10, (int) $log->jumlah_sebelum);
        $this->assertEquals(-2, (int) $log->perubahan);
        $this->assertEquals(8, (int) $log->jumlah_setelah);
        $this->assertEquals($this->kasir->id, (int) $log->user_id);

        // TEPAT 1 mutasi per item — tidak dobel
        $this->assertSame(1, StockMutationLog::where('referensi_id', $transaksi->id)->count());
        $this->assertSame(1, StokLog::where('referensi_id', $transaksi->id)->count());

        // [B-10b/P1-2] Kolom user_id ada di stock_mutation_log (nullable).
        // [B-10f/P1-6] StokDeductionService::kurangi() kini mengirim user_id juga →
        // mutasi bisa dibuktikan pelakunya (sebelumnya NULL di jalur kanonik ini).
        $this->assertTrue(
            Schema::hasColumn('stock_mutation_log', 'user_id'),
            'Migrasi B-10b wajib menambahkan stock_mutation_log.user_id'
        );
        $this->assertSame($this->kasir->id, (int) $mutasi->user_id);
        $this->assertSame((int) $log->user_id, (int) $mutasi->user_id);

        // Stok cabang lain tidak tersentuh
        $this->assertEquals(999, (int) StokItem::where('gudang_id', $this->gudangLain->id)->sum('jumlah'));

        // Jurnal POS tetap balance
        $jurnal = JurnalAkuntansi::where('sumber', 'pos')->get();
        $this->assertNotEmpty($jurnal);
        $this->assertEquals((float) $jurnal->sum('debit'), (float) $jurnal->sum('kredit'));
    }

    // ===== (e) Katalog tidak hard-stop di 16 — seluruh produk aktif bisa diakses =====

    public function test_katalog_load_more_menampilkan_seluruh_produk_aktif(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Produk::create([
                'nama' => "Produk Load More {$i}",
                'slug' => "produk-load-more-{$i}-".Str::random(4),
                'kategori' => 'Sparepart', 'kondisi' => 'baru',
                'harga_beli' => 1000, 'harga_jual_retail' => 2000,
                'is_active' => true,
            ]);
        }

        $component = $this->buatKomponen();

        // Default: 16 (batas lama) + tombol load-more tampil
        $this->assertCount(PosKasir::BATAS_PRODUK_AWAL, $component->viewData('products'));
        $this->assertTrue($component->viewData('adaLebihBanyak'));
        $component->assertSee('Muat lebih banyak');

        // Load-more → sisa produk ikut termuat (20 + 3 fixture = 23 < 32)
        $component->call('muatLebihBanyak');
        $this->assertSame(23, $component->viewData('products')->count());
        $this->assertFalse($component->viewData('adaLebihBanyak'));

        // Pencarian mereset batas (query tidak menumpuk dari halaman sebelumnya)
        $component->set('search', 'Load More')
            ->assertSee('Produk Load More 7');
        $this->assertCount(PosKasir::BATAS_PRODUK_AWAL, $component->viewData('products'));
    }

    // ===== [B-02 revisi] API POS-01 juga wajib menolak stok kurang =====

    /**
     * Sebelumnya `PosController::store()` mengirim `izinkanNegatif: true` ke
     * `StokDeductionService::kurangi()` — endpoint API tetap bisa memakai jalur
     * backorder walau Livewire sudah hard-block. Test ini mengunci parity:
     * API menolak stok kurang, stok tidak pernah negatif, dan tidak ada
     * transaksi/jurnal/log yang tertinggal (rollback).
     */
    public function test_api_pos_menolak_stok_kurang_dan_tidak_membuat_stok_negatif(): void
    {
        $this->actingAs($this->kasir, 'web');
        $this->withSession(['cabang_id' => $this->cabang->id, 'gudang_id' => $this->gudang->id]);

        // Varian A hanya 10 unit — minta 999
        $response = $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varianA->id,
                'jumlah' => 999,
                'harga_satuan' => 1100000,
            ]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 999 * 1100000,
            'gudang_id' => $this->gudang->id,
        ]);

        $response->assertStatus(422);

        // Stok TIDAK berubah & TIDAK negatif
        $this->assertEquals(10, (int) StokItem::where('sku_variant_id', $this->varianA->id)->value('jumlah'));
        $this->assertGreaterThanOrEqual(0, (int) StokItem::where('produk_id', $this->produk->id)->sum('jumlah'));

        // Tidak ada transaksi / jurnal / mutation log yang tertinggal (rollback)
        $this->assertSame(0, Transaksi::count());
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(0, StockMutationLog::count());
        $this->assertSame(0, StokLog::count());
    }
}
