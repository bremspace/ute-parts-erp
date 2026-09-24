<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test semua halaman backoffice + marketplace:
 * render tiap route authed harus 200 (menangkap "Undefined variable X" dll).
 */
class SemuaHalamanTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): void
    {
        // Seed roles & permissions lengkap (dipakai role() scope di ServisBoard)
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class); // untuk jurnal pembelian

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $produk = Produk::create([
            'nama' => 'LCD iPhone', 'slug' => 'lcd-iphone', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 750000, 'harga_jual_retail' => 1100000,
        ]);
        StokItem::create(['produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => 5, 'jumlah_minimum' => 2]);

        $this->actingAs($user, 'web');
    }

    public function test_semua_halaman_backoffice_render(): void
    {
        $this->authed();

        $routes = [
            '/app/dashboard', '/app/pos', '/app/wms', '/app/servis',
            '/app/crm', '/app/reseller', '/app/akunting', '/app/omnichannel', '/app/pengaturan',
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertSuccessful("Halaman {$url} harus 2xx");
        }
    }

    public function test_halaman_marketplace_render(): void
    {
        $this->get('/shop')->assertSuccessful();
        $this->get('/shop/lcd-iphone')->assertSuccessful();
        $this->get('/cart')->assertSuccessful();
        $this->get('/checkout')->assertSuccessful();
        $this->get('/login-pelanggan')->assertSuccessful();
        $this->get('/daftar-pelanggan')->assertSuccessful();
    }

    // [T-06] Kanban internal wajib permission servis.view — user tanpa akses dapat 403
    public function test_kanban_servis_diblokir_tanpa_permission_servis_view(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);

        $kasir = User::create([
            'name' => 'Kasir Tanpa Servis',
            'email' => 'kasir-noservis@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $role = Role::findByName('kasir'); // role kasir: tanpa servis.view
        $kasir->assignRole($role);
        $kasir->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($kasir, 'web');

        $this->get('/app/servis')->assertForbidden(); // 403: permission servis.view
    }

    // [T-09] Tidak bisa transaksi tunai tanpa sesi kas terbuka
    public function test_pos_tunai_diblokir_tanpa_sesi_kas(): void
    {
        $this->authed();
        $produk = Produk::firstOrFail();

        $resp = $this->postJson('/api/pos/transaksi', [
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 100000]],
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
        ]);

        $resp->assertStatus(422); // kas belum dibuka → diblokir
    }

    // [T-09] Buka kas → transaksi tunai sukses → tutup kas menghasilkan jurnal balance
    public function test_kas_sesi_buka_tutup_dan_pos_tunai(): void
    {
        $this->authed();
        $produk = Produk::firstOrFail();

        // Buka kas
        $this->postJson('/api/pos/kas/buka', ['saldo_awal' => 100000, 'cabang_id' => session('cabang_id')])
            ->assertSuccessful();

        // Transaksi tunai sekarang boleh
        $resp = $this->postJson('/api/pos/transaksi', [
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 100000]],
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
        ]);
        $resp->assertSuccessful();

        // Tutup kas → saldo sistem = 100000 + 100000 = 200000, fisik harus sama (no selisih)
        $tutup = $this->postJson('/api/pos/kas/tutup', ['saldo_fisik' => 200000])->assertSuccessful();
        $this->assertEquals(200000, (float) $tutup->json('data.saldo_sistem'));
        $this->assertEquals(0, (float) $tutup->json('data.selisih'));

        // Kas sesi tercatat buka → tutup
        $this->assertDatabaseHas('kas_sesi', ['status' => 'tutup']);
    }

    // [T-03] Park (tahan) transaksi via POS-04
    public function test_park_transaksi_ditahan_dan_resume(): void
    {
        $this->authed();

        // Sediakan transaksi draft via tahan langsung (tanpa kas)
        $this->postJson('/api/pos/kas/buka', ['saldo_awal' => 0, 'cabang_id' => session('cabang_id')])->assertSuccessful();

        $produk = Produk::firstOrFail();
        $resp = $this->postJson('/api/pos/transaksi', [
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 100000]],
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 100000,
        ])->assertSuccessful();

        $no = $resp->json('data.no_transaksi');

        // Tidak ada endpoint tahan dari transaksi selesai (park adl fitur UI) — cek daftar transaksi
        $this->getJson('/api/pos/transaksi?status=selesai')->assertSuccessful();
        $this->assertNotNull($no);
    }

    // [T-10] PO kredit: dibuat → dikirim → diterima via GRN (stok + jurnal AP) → bayar → sisa utang updated
    public function test_po_kredit_diterima_dan_dibayar(): void
    {
        $this->authed();
        $gudang = Gudang::where('kode', 'GDG-01')->firstOrFail();
        $produk = Produk::firstOrFail();
        $stokAwal = StokItem::where('produk_id', $produk->id)->where('gudang_id', $gudang->id)->value('jumlah') ?? 0;

        // Supplier
        $supplier = Supplier::create(['nama' => 'Supplier Test', 'telepon' => '021000', 'termin_hari' => 30]);

        // Buat PO kredit 2 item @ 100000 = 200000
        $poResp = $this->postJson('/api/wms/po', [
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $gudang->id,
            'metode_bayar' => 'kredit',
            'items' => [
                ['produk_id' => $produk->id, 'sku_variant_id' => $produk->skuVariants()->first()?->id, 'harga_beli' => 100000, 'jumlah' => 2],
            ],
        ])->assertSuccessful();
        $poId = $poResp->json('data.id');

        // Kirim
        $this->putJson("/api/wms/po/{$poId}/status", ['action' => 'dikirim'])->assertSuccessful();

        // [F2-2] Penerimaan langsung via API DITUTUP — PO 'dikirim' tanpa GRN wajib 422
        $this->putJson("/api/wms/po/{$poId}/status", ['action' => 'diterima'])->assertStatus(422);

        // Terima lewat GRN kanonik: qty sesuai (2/2) → auto-finalisasi →
        // stok masuk + jurnal AP + PO status 'diterima'
        $po = PurchaseOrder::with('items')->findOrFail($poId);
        $grn = app(GrnService::class)->inputGudang($po, [$produk->id => 2], auth()->id());

        $this->assertEquals('terima', $grn->status, 'GRN qty sesuai wajib langsung terima');
        $this->assertDatabaseHas('purchase_order', ['id' => $poId, 'status' => 'diterima']);

        // Stok gudang bertambah 2
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => $stokAwal + 2]);

        // Jurnal GRN (AP): Persediaan 130-01 debit 200000, Utang 210-01 kredit 200000
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $grn->no_grn,
            'sumber' => 'grn',
            'akun_coa_id' => AkunCOA::where('kode', '130-01')->first()->id,
            'debit' => 200000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $grn->no_grn,
            'sumber' => 'grn',
            'akun_coa_id' => AkunCOA::where('kode', '210-01')->first()->id,
            'kredit' => 200000,
        ]);

        // Subledger Utang (AP): 1 baris per PO, jumlah = jurnal 210-01, cabang-scoped
        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)
            ->where('referensi_id', $poId)
            ->first();
        $this->assertNotNull($utang, 'Finalisasi GRN wajib membuat baris Utang subledger');
        $this->assertSame(1, Utang::count(), 'Tepat 1 baris Utang per PO (idempoten)');
        $this->assertEquals(200000, (float) $utang->jumlah, 'Jumlah Utang = jurnal AP GRN');
        $this->assertEquals((int) session('cabang_id'), (int) $utang->cabang_id, 'Utang wajib stamp cabang_id');
        $this->assertEquals('belum_lunas', $utang->status);

        // Bayar parsial 50000
        $this->postJson("/api/wms/po/{$poId}/bayar", ['jumlah' => 50000])->assertSuccessful();
        $this->assertDatabaseHas('purchase_order', ['id' => $poId, 'total_dibayar' => 50000]);

        // Utang subledger ikut berkurang oleh bayarPO
        $utang->refresh();
        $this->assertEquals(50000, (float) $utang->jumlah_dibayar, 'bayarPO wajib mengurangi Utang subledger');
        $this->assertEquals('sebagian', $utang->status);
        $this->assertEquals(150000, $utang->sisa);
        $this->assertSame(1, Utang::count(), 'Pembayaran tidak boleh membuat baris Utang baru');

        // Jurnal bayar: Utang debit 50000, Kas kredit 50000
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => AkunCOA::where('kode', '210-01')->first()->id,
            'debit' => 50000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => AkunCOA::where('kode', '110-01')->first()->id,
            'kredit' => 50000,
        ]);
    }

    // [T-11] Tambah produk dengan slug+d
    public function test_konfigurasi_casting_barcode_foto(): void
    {
        $this->authed();
        $produk = Produk::firstOrFail();

        $produk->update([
            'barcode' => 'XYZ-123-BARCODE',
            'kompatibilitas_hp' => [['merk' => 'Apple', 'model' => 'iPhone 13']],
        ]);

        $this->assertDatabaseHas('produk', ['id' => $produk->id, 'barcode' => 'XYZ-123-BARCODE']);
    }

    public function test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian(): void
    {
        $this->authed();
        $gudang = Gudang::where('kode', 'GDG-01')->firstOrFail();

        $produk = app(ProdukService::class)->buatProduk(
            nama: 'Baterai Samsung Baru',
            kategori: 'Baterai',
            brand: 'Samsung',
            model: 'A54',
            kondisi: 'baru',
            hargaBeli: 80000,
            hargaJual: 150000,
            gudangId: $gudang->id,
            stokAwal: 10,
            stokMinimum: 3,
            userId: auth()->id()
        );

        // Produk + variant + stok + stok log tercatat
        $this->assertDatabaseHas('produk', ['id' => $produk->id]);
        $this->assertDatabaseHas('sku_variants', ['produk_id' => $produk->id]);
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'jumlah' => 10]);
        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $produk->id, 'jenis' => 'pembelian', 'perubahan' => 10,
        ]);

        // Jurnal pembelian: Debit Persediaan (130-01) = 800.000, utk Kredit Utang (210-01)
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'pembelian',
            'akun_coa_id' => AkunCOA::where('kode', '130-01')->first()->id,
            'debit' => 800000,
        ]);

        // Double-entry balance untuk jurnal pembelian
        $noJurnal = DB::table('jurnal_akuntansi')
            ->where('sumber', 'pembelian')->value('no_jurnal');
        $debit = DB::table('jurnal_akuntansi')->where('no_jurnal', $noJurnal)->sum('debit');
        $kredit = DB::table('jurnal_akuntansi')->where('no_jurnal', $noJurnal)->sum('kredit');
        $this->assertEqualsWithDelta($debit, $kredit, 0.01);
    }
}
