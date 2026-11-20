<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\PurchaseOrderService;
use App\Modules\Wms\Services\ReturnPembelianService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * H8 / F-32 — PPN masukan pembelian (GRN & PO).
 *
 * Akun 110-03 PPN Masukan wajib diposting debit terpisah dari persediaan:
 * Dr 130-01 DPP (neto) + Dr 110-03 PPN / Cr kas|utang total tagihan.
 * Persediaan dikapitalisasi neto (IAS 2 ¶11) — mode inclusive mengekstrak
 * PPN dari harga bruto, exclusive menambahkan PPN di atas harga neto.
 * Nilai pajak snapshot saat dokumen dibuat (tidak retroaktif), idempoten.
 */
class PembelianPpnMasukanTest extends TestCase
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
            ['110-03', 'PPN Masukan', 'aset', 'pajak', 'debit'],
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

        $this->cabang = Cabang::create([
            'kode' => 'UTP-PPN',
            'nama' => 'Cabang PPN',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang PPN',
            'kode' => 'GDG-PPN',
            'is_active' => true,
        ]);
        $this->user = User::create([
            'name' => 'Admin PPN',
            'email' => 'admin-ppn@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Sparepart PPN',
            'slug' => Str::slug('Sparepart PPN').'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'metode_harga_beli' => 'average',
            'is_active' => true,
        ]);
        $this->supplier = Supplier::create(['nama' => 'Supplier PPN', 'termin_hari' => 30]);
    }

    private function aktifkanPajak(string $mode = 'exclusive', float $percent = 11.0): void
    {
        app(PajakService::class)->saveConfig($this->cabang->id, [
            'enabled' => true,
            'percent' => $percent,
            'nama' => 'PPN',
            'mode' => $mode,
        ]);
    }

    private function buatPo(int $qty, float $harga, string $metode = 'kredit', string $status = 'dikirim'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-PPN-'.Str::random(6),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => $status,
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

        return $po->load('items');
    }

    /** @return array<string, float> kode akun => nilai (debit positif, kredit negatif) */
    private function nilaiJurnalPerAkun(string $noJurnal): array
    {
        $rows = JurnalAkuntansi::with('akun')->where('no_jurnal', $noJurnal)->get();
        $out = [];
        foreach ($rows as $r) {
            $kode = $r->akun?->kode ?? '?';
            $out[$kode] = ($out[$kode] ?? 0) + (float) $r->debit - (float) $r->kredit;
        }

        return $out;
    }

    public function test_grn_kredit_ppn_exclusive_memisah_110_03_dari_persediaan(): void
    {
        $this->aktifkanPajak('exclusive');
        $po = $this->buatPo(10, 50000); // DPP 500.000, PPN 11% = 55.000

        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals('terima', $grn->status);
        $this->assertEquals(500000, (float) $grn->dpp);
        $this->assertEquals(55000, (float) $grn->ppn_nominal);
        $this->assertEquals(11, (float) $grn->ppn_percent);

        $jurnal = $this->nilaiJurnalPerAkun($grn->no_grn);
        $this->assertEquals(500000, $jurnal['130-01'] ?? null, 'Persediaan hanya DPP neto');
        $this->assertEquals(55000, $jurnal['110-03'] ?? null, 'PPN masukan diposting debit 110-03');
        $this->assertEquals(-555000, $jurnal['210-01'] ?? null, 'Utang = total tagihan termasuk PPN');

        // Subledger AP & total PO mengikuti total tagihan (yang benar-benar owed ke supplier)
        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->firstOrFail();
        $this->assertEquals(555000, (float) $utang->jumlah);
        $this->assertEquals(555000, (float) $po->fresh()->total);

        // Exclusive: harga beli neto = harga masuk, valuasi tidak berubah
        $this->assertEquals(10, (int) StokItem::where('produk_id', $this->produk->id)->value('jumlah'));
        $this->assertEquals(50000, (float) $this->produk->fresh()->harga_beli);
    }

    public function test_grn_ppn_inclusive_kapitalisasi_persediaan_neto(): void
    {
        $this->aktifkanPajak('inclusive');
        $po = $this->buatPo(10, 55500); // bruto 555.000 termasuk PPN 11%

        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals(500000, (float) $grn->dpp);
        $this->assertEquals(55000, (float) $grn->ppn_nominal);

        $jurnal = $this->nilaiJurnalPerAkun($grn->no_grn);
        $this->assertEquals(500000, $jurnal['130-01'] ?? null, 'Persediaan neto, bukan bruto');
        $this->assertEquals(55000, $jurnal['110-03'] ?? null);
        $this->assertEquals(-555000, $jurnal['210-01'] ?? null);

        // Valuasi subledger ikut neto: harga beli master & item GRN = 50.000/unit
        $this->assertEquals(50000, (float) $this->produk->fresh()->harga_beli);
        $item = collect($grn->fresh()->item_qty_received)->firstWhere('produk_id', $this->produk->id);
        $this->assertEquals(50000, (float) ($item['harga_net'] ?? 0), 'Biaya neto per satuan wajib disimpan utk retur');
    }

    public function test_grn_tunai_ppn_kredit_kas_sebesar_total_tagihan(): void
    {
        $this->aktifkanPajak('exclusive');
        // Saldo kas awal agar cukup membayar total tagihan
        app(JurnalService::class)->post(
            app(JurnalService::class)->generateNoJurnal('manual', $this->cabang->id),
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

        $po = $this->buatPo(10, 50000, 'tunai');
        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => 10], $this->user->id, [], [], [], '110-01');

        $jurnal = $this->nilaiJurnalPerAkun($grn->no_grn);
        $this->assertEquals(500000, $jurnal['130-01'] ?? null);
        $this->assertEquals(55000, $jurnal['110-03'] ?? null);
        $this->assertEquals(-555000, $jurnal['110-01'] ?? null, 'Kas keluar sebesar total tagihan');
        $this->assertSame(0, Utang::count(), 'PO tunai tidak membuat subledger utang');
        $this->assertEquals(555000, (float) $po->fresh()->total_dibayar);
    }

    public function test_po_legacy_terima_barang_ppn_exclusive(): void
    {
        $this->aktifkanPajak('exclusive');
        $po = $this->buatPo(10, 50000, 'kredit', 'draft');

        $po = app(PurchaseOrderService::class)->terimaBarang($po, $this->user->id);

        $this->assertEquals('diterima', $po->status);
        $this->assertEquals(500000, (float) $po->dpp);
        $this->assertEquals(55000, (float) $po->ppn_nominal);
        $this->assertEquals(555000, (float) $po->total);

        $noJurnal = JurnalAkuntansi::where('referensi_tipe', PurchaseOrder::class)
            ->where('referensi_id', $po->id)->value('no_jurnal');
        $jurnal = $this->nilaiJurnalPerAkun($noJurnal);
        $this->assertEquals(500000, $jurnal['130-01'] ?? null);
        $this->assertEquals(55000, $jurnal['110-03'] ?? null);
        $this->assertEquals(-555000, $jurnal['210-01'] ?? null);

        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->firstOrFail();
        $this->assertEquals(555000, (float) $utang->jumlah);
    }

    public function test_pajak_nonaktif_tidak_ada_ppn_masukan(): void
    {
        // Default TestCase: pajak nonaktif — perilaku lama tetap (2 baris jurnal)
        $po = $this->buatPo(10, 50000);
        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => 10], $this->user->id);

        $this->assertEquals(0, (float) $grn->ppn_nominal);
        $this->assertEquals(500000, (float) $grn->dpp);
        $jurnal = $this->nilaiJurnalPerAkun($grn->no_grn);
        $this->assertCount(2, $jurnal);
        $this->assertArrayNotHasKey('110-03', $jurnal);
        $this->assertEquals(-500000, $jurnal['210-01'] ?? null);
    }

    public function test_grn_partial_ppn_idempoten_finalize_ganda(): void
    {
        $this->aktifkanPajak('exclusive');
        $po = $this->buatPo(10, 50000);

        // Partial 5/10 → draft + approval; finalisasi via setujuiGrn
        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => 5], $this->user->id);
        $this->assertEquals('draft', $grn->status);
        $this->assertEquals(27500, (float) $grn->ppn_nominal); // 5 x 50.000 x 11%

        $svc = app(GrnService::class);
        $svc->setujuiGrn($grn->id, $this->user->id);
        $svc->setujuiGrn($grn->id, $this->user->id); // dobel finalize → no-op

        $this->assertSame(3, JurnalAkuntansi::where('no_jurnal', $grn->no_grn)->count(), 'Tepat 3 baris jurnal, tidak dobel');
        $jurnal = $this->nilaiJurnalPerAkun($grn->no_grn);
        $this->assertEquals(250000, $jurnal['130-01'] ?? null);
        $this->assertEquals(27500, $jurnal['110-03'] ?? null);
        $this->assertEquals(-277500, $jurnal['210-01'] ?? null);
        $this->assertSame(1, Utang::count());
        $this->assertEquals(277500, (float) Utang::first()->jumlah);
    }

    public function test_retur_pembelian_ppn_masukan_dibalik_kredit_110_03(): void
    {
        $this->aktifkanPajak('inclusive');
        $po = $this->buatPo(10, 55500);
        $grn = app(GrnService::class)->inputGudang($po, [$this->produk->id => 10], $this->user->id);
        $this->assertEquals('terima', $grn->status);

        // Retur 1 unit, supplier refund bruto 55.500 (neto 50.000 + PPN 5.500)
        $retur = app(ReturnPembelianService::class)->buatRetur(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'jumlah' => 1]],
            'Cacat',
            'kas',
            $this->user->id
        );
        $this->assertEquals('selesai', $retur->fresh()->status);
        $this->assertEquals(50000, (float) $retur->fresh()->jumlah, 'Nilai buku retur = biaya neto');

        // Realisasi bruto termasuk PPN yang dikembalikan supplier
        $retur = $retur->fresh();
        $retur->update(['nilai_realisasi' => 55500]);
        // jurnal sudah diposting saat eksekusi dengan klaim default neto+PPN = 55.500
        $jurnal = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', ReturnPembelian::class)
            ->where('referensi_id', $retur->id)
            ->get();

        $perAkun = [];
        foreach ($jurnal as $r) {
            $kode = $r->akun?->kode ?? '?';
            $perAkun[$kode] = ($perAkun[$kode] ?? 0) + (float) $r->debit - (float) $r->kredit;
        }
        $this->assertEquals(55500, $perAkun['110-01'] ?? null, 'Kas refund bruto');
        $this->assertEquals(-50000, $perAkun['130-01'] ?? null, 'Persediaan kredit neto');
        $this->assertEquals(-5500, $perAkun['110-03'] ?? null, 'PPN masukan dibalik (kredit 110-03)');
        $this->assertArrayNotHasKey('430-01', $perAkun, 'Refund PPN bukan pendapatan lain-lain');
    }
}
