<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ReturnPembelianService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * H13 — Retur pembelian: deposit supplier on-books & AP tidak boleh negatif.
 *
 * - Porsi klaim yang jadi deposit supplier WAJIB dijurnal
 *   (Dr 120-05 Deposit Supplier / Cr 210-01) — tidak off-books.
 * - Satu semantik AP: credit-note selalu menambah jumlah_dibayar,
 *   pokok utang (jumlah) & total PO tidak diubah; kelebihan klaim → deposit.
 * - Eksekusi ganda idempoten (lock + re-check status).
 */
class ReturPembelianDepositOnBooksTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $user;

    private Produk $produk;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
            ['210-01', 'Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            ['110-01', 'Kas', 'aset', 'kas', 'debit'],
            ['120-05', 'Deposit Supplier', 'aset', 'deposit', 'debit'],
            ['520-08', 'Selisih Stok', 'beban', 'hpp', 'debit'],
            ['430-01', 'Pendapatan Lain', 'pendapatan', 'pendapatan_lain', 'kredit'],
            ['310-01', 'Modal Pemilik', 'ekuitas', 'modal', 'kredit'],
        ] as [$kode, $nama, $tipe, $kelompok, $normal]) {
            AkunCOA::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $normal]
            );
        }

        $this->cabang = Cabang::create(['kode' => 'UTP-DEP', 'nama' => 'Cabang Deposit', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang DEP', 'kode' => 'GDG-DEP', 'is_active' => true]);
        $this->user = User::create([
            'name' => 'Admin Deposit',
            'email' => 'admin-dep@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Produk Deposit',
            'slug' => Str::slug('Produk Deposit').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);
        $this->supplier = Supplier::create(['nama' => 'Supplier Deposit', 'termin_hari' => 30, 'saldo_deposit' => 0]);
    }

    private function buatReturDraft(float $nilai, string $metode, ?int $poId = null): ReturnPembelian
    {
        $retur = ReturnPembelian::create([
            'no_return' => 'RTB-DEP-'.Str::random(6),
            'purchase_order_id' => $poId,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => $nilai,
            'status' => 'draft',
            'metode_pengembalian' => $metode,
            'alasan' => 'Cacat pabrik',
        ]);
        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => $nilai,
            'subtotal' => $nilai,
        ]);

        return $retur;
    }

    /** @return array<string, float> kode akun => saldo neto (debit - kredit) */
    private function netoJurnalRetur(ReturnPembelian $retur): array
    {
        $rows = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $kode = $r->akun?->kode ?? '?';
            $out[$kode] = ($out[$kode] ?? 0) + (float) $r->debit - (float) $r->kredit;
        }

        return $out;
    }

    private function saldoGlAkun(string $kode): float
    {
        return (float) JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', $kode))
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(kredit),0) as neto')
            ->value('neto');
    }

    public function test_saldo_tanpa_utang_deposit_dijurnal_dan_ap_tidak_negatif(): void
    {
        $retur = $this->buatReturDraft(200000, 'saldo');

        $selesai = app(ReturnPembelianService::class)->kirimDanSelesaikanRetur(
            $retur, 'saldo', 'Kirim via ekspedisi', $this->user->id, 200000
        );
        $this->assertEquals('selesai', $selesai->status);

        // Subledger deposit bertambah
        $this->assertEquals(200000, (float) $this->supplier->fresh()->saldo_deposit);

        // GL: Dr 210-01 200.000 (klaim) + Dr 120-05 200.000 / Cr 210-01 200.000 (deposit)
        $neto = $this->netoJurnalRetur($retur);
        $this->assertEquals(0, $neto['210-01'] ?? null, 'AP neto nol — tidak boleh saldo debit/negatif');
        $this->assertEquals(200000, $neto['120-05'] ?? null, 'Deposit supplier diakui sebagai aset di GL');
        $this->assertEquals(-200000, $neto['130-01'] ?? null);

        // Rekonsiliasi: GL deposit == Σ saldo_deposit supplier
        $this->assertEquals((float) $this->supplier->fresh()->saldo_deposit, $this->saldoGlAkun('120-05'));
    }

    public function test_saldo_dengan_utang_terbuka_alokasi_credit_note_sisa_deposit(): void
    {
        $utang = Utang::create([
            'no_utang' => 'UTG-DEP-001',
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => 999999, // PO tidak ada — helper harus toleran
            'kreditor_nama' => $this->supplier->nama,
            'cabang_id' => $this->cabang->id,
            'jumlah' => 150000,
            'jumlah_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        $retur = $this->buatReturDraft(200000, 'saldo');
        app(ReturnPembelianService::class)->kirimDanSelesaikanRetur(
            $retur, 'saldo', null, $this->user->id, 200000
        );

        // Credit-note: jumlah_dibayar bertambah, pokok tidak berubah
        $utang->refresh();
        $this->assertEquals(150000, (float) $utang->jumlah_dibayar);
        $this->assertEquals(150000, (float) $utang->jumlah, 'Pokok utang tidak boleh diubah');
        $this->assertEquals('lunas', $utang->status);

        // Sisa 50.000 → deposit on-books
        $this->assertEquals(50000, (float) $this->supplier->fresh()->saldo_deposit);
        $neto = $this->netoJurnalRetur($retur);
        $this->assertEquals(150000, $neto['210-01'] ?? null, 'AP turun hanya sebesar porsi utang (neto debit 150.000)');
        $this->assertEquals(50000, $neto['120-05'] ?? null);
        $this->assertEquals((float) $this->supplier->fresh()->saldo_deposit, $this->saldoGlAkun('120-05'));
    }

    private function poDiterimaViaGrn(int $qty, float $harga, string $metode = 'kredit'): PurchaseOrder
    {
        if ($metode === 'tunai') {
            $js = app(JurnalService::class);
            $js->post(
                $js->generateNoJurnal('manual', $this->cabang->id),
                now(),
                'manual',
                [
                    ['akun_kode' => '110-01', 'debit' => 10000000, 'kredit' => 0],
                    ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 10000000],
                ],
                'Modal kas awal',
                $this->cabang->id,
                $this->user->id
            );
        }

        $po = PurchaseOrder::create([
            'no_po' => 'PO-DEP-'.Str::random(6),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => $metode,
            'total' => $qty * $harga,
            'total_dibayar' => 0,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'harga_beli' => $harga,
            'jumlah' => $qty,
            'subtotal' => $qty * $harga,
        ]);
        $grn = app(GrnService::class)->inputGudang($po->load('items'), [$this->produk->id => $qty], $this->user->id);
        $this->assertEquals('terima', $grn->status);

        return $po->fresh();
    }

    public function test_utang_credit_note_tidak_ubah_pokok_dan_total_po(): void
    {
        $po = $this->poDiterimaViaGrn(10, 100000); // utang 1.000.000
        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->firstOrFail();

        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po,
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 3]],
            'Cacat 3 unit',
            'utang',
            $this->user->id
        );
        $this->assertEquals('selesai', $retur->fresh()->status);

        $utang->refresh();
        $this->assertEquals(1000000, (float) $utang->jumlah, 'Pokok utang tetap (credit-note, bukan potong pokok)');
        $this->assertEquals(300000, (float) $utang->jumlah_dibayar);
        $this->assertEquals('sebagian', $utang->status);

        $po->refresh();
        $this->assertEquals(1000000, (float) $po->total, 'Total PO tidak berubah');
        $this->assertEquals(300000, (float) $po->total_dibayar, 'Credit-note dihitung ke total_dibayar agar sisa konsisten');
        $this->assertEquals(700000, $po->sisa);

        // Tidak ada deposit (klaim terserap penuh ke utang)
        $this->assertEquals(0, (float) $this->supplier->fresh()->saldo_deposit);
        $neto = $this->netoJurnalRetur($retur);
        $this->assertEquals(300000, $neto['210-01'] ?? null);
        $this->assertArrayNotHasKey('120-05', $neto);
    }

    public function test_utang_klaim_melebihi_sisa_utang_kelebihan_masuk_deposit_bukan_ap_negatif(): void
    {
        $po = $this->poDiterimaViaGrn(10, 100000); // utang 1.000.000
        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->firstOrFail();

        // Draft retur 10 unit (buku 1.000.000), realisasi klaim 1.500.000
        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po,
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 10]],
            'Klaim penuh + kompensasi',
            'utang',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );
        $this->assertEquals('draft', $retur->status);

        app(ReturnPembelianService::class)->selesaikanKompensasiFinansial(
            $retur, 1500000, 'utang', 'Klaim disetujui penuh', $this->user->id
        );

        $utang->refresh();
        $this->assertEquals(1000000, (float) $utang->jumlah);
        $this->assertEquals(1000000, (float) $utang->jumlah_dibayar);
        $this->assertEquals('lunas', $utang->status);

        // Kelebihan 500.000 → deposit on-books, BUKAN AP negatif / hilang
        $this->assertEquals(500000, (float) $this->supplier->fresh()->saldo_deposit);
        $neto = $this->netoJurnalRetur($retur);
        $this->assertEquals(1000000, $neto['210-01'] ?? null, 'AP neto tidak boleh lebih debit dari utang terbuka');
        $this->assertEquals(500000, $neto['120-05'] ?? null);
        $this->assertEquals(-500000, $neto['430-01'] ?? null, 'Selisih klaim vs buku tetap pendapatan lain-lain');
        $this->assertEquals((float) $this->supplier->fresh()->saldo_deposit, $this->saldoGlAkun('120-05'));
    }

    public function test_utang_po_tanpa_utang_terbuka_klaim_jadi_deposit_bukan_ap_negatif(): void
    {
        // PO tunai sudah lunas → tidak ada baris Utang; retur 'utang' tidak boleh
        // mendebit 210-01 tanpa lawan (AP negatif). Klaim → deposit supplier.
        $po = $this->poDiterimaViaGrn(5, 100000, 'tunai');
        $this->assertSame(0, Utang::count());

        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po,
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 2]],
            'Cacat',
            'utang',
            $this->user->id
        );
        $this->assertEquals('selesai', $retur->fresh()->status);

        $this->assertEquals(200000, (float) $this->supplier->fresh()->saldo_deposit);
        $neto = $this->netoJurnalRetur($retur);
        $this->assertEquals(0, $neto['210-01'] ?? null, 'Tanpa utang terbuka, AP neto wajib nol');
        $this->assertEquals(200000, $neto['120-05'] ?? null);
    }

    public function test_eksekusi_ganda_idempoten_deposit_dan_jurnal_tidak_dobel(): void
    {
        $retur = $this->buatReturDraft(200000, 'saldo');
        $svc = app(ReturnPembelianService::class);
        $svc->eksekusiRetur($retur, $this->user->id);
        $svc->eksekusiRetur($retur->fresh(), $this->user->id); // dobel → no-op

        $this->assertEquals(200000, (float) $this->supplier->fresh()->saldo_deposit);
        $this->assertSame(1, $this->supplier->saldoMutasi()->count());
        $this->assertSame(4, JurnalAkuntansi::where('referensi_tipe', ReturnPembelian::class)->where('referensi_id', $retur->id)->count());
    }

    public function test_saldo_tanpa_supplier_ditolak_fail_closed(): void
    {
        $retur = ReturnPembelian::create([
            'no_return' => 'RTB-DEP-NOSUP',
            'supplier_id' => null,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => 100000,
            'status' => 'draft',
            'metode_pengembalian' => 'saldo',
        ]);
        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 100000,
            'subtotal' => 100000,
        ]);

        try {
            app(ReturnPembelianService::class)->eksekusiRetur($retur, $this->user->id);
            $this->fail('Klaim deposit tanpa supplier wajib ditolak (fail-closed, bukan off-books)');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('supplier', strtolower(implode(' ', $e->errors()['supplier'] ?? ['supplier'])));
        }

        $this->assertEquals('draft', $retur->fresh()->status, 'Transaksi rollback — retur tetap draft');
        $this->assertSame(0, JurnalAkuntansi::where('referensi_tipe', ReturnPembelian::class)->count());
    }
}
