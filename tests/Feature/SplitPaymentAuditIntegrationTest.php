<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PosCheckoutService;
use App\Modules\Rbac\Exports\AuditLogExport;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SplitPaymentAuditIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Uji Audit',
            'tipe' => 'cabang',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GD-CB01',
            'nama' => 'Gudang Uji CB01',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $kategori = KategoriProduk::create(['nama' => 'Sparepart']);

        $this->produk = Produk::create([
            'kode' => 'PRD-SPLIT-01',
            'nama' => 'LCD Touchscreen OLED',
            'kategori_id' => $kategori->id,
            'kategori_produk_id' => $kategori->id,
            'harga_modal' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'cabang_id' => $this->cabang->id,
            'jumlah' => 50,
        ]);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pelanggan Uji Audit',
            'telepon' => '081234567890',
            'email' => 'pelanggan@test.id',
            'saldo_piutang' => 0,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Audit',
            'email' => 'kasir.audit@test.id',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
        ]);
        $this->kasir->assignRole('kasir');

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_pos_checkout_split_payment_records_proper_journals_drawer_and_audit_log(): void
    {
        $this->actingAs($this->kasir);

        // Buka sesi kas
        app(KasSesiState::class)->bukaKas(100000, $this->cabang->id, $this->kasir->id);
        $kasSesi = app(KasSesiState::class)->sesiKasAktif();

        $checkoutService = app(PosCheckoutService::class);
        $totalAkhir = 150000.0;
        $splitDetail = [
            'tunai' => 50000.0,
            'non_tunai' => 100000.0,
            'metode_non_tunai' => 'qris',
        ];

        $transaksi = $checkoutService->checkout([
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasir->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'pos',
            'subtotal' => 150000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'dpp' => 150000,
            'pajak_nominal' => 0,
            'total_akhir' => $totalAkhir,
            'metode_bayar' => 'split',
            'jumlah_bayar' => 150000,
            'kembalian' => 0,
            'split_detail' => $splitDetail,
            'catatan' => 'Uji Split Payment POS',
        ], [
            [
                'produk_id' => $this->produk->id,
                'jumlah' => 1,
                'harga_satuan' => 150000,
                'subtotal' => 150000,
                'hpp' => 100000,
            ],
        ]);

        $this->assertInstanceOf(Transaksi::class, $transaksi);
        $this->assertEquals('split', $transaksi->metode_bayar);
        $this->assertEquals($splitDetail, $transaksi->split_detail);

        // Verifikasi Jurnal Double Entry:
        // Kas Laci (110-04) debit = 50.000
        // Bank (110-02) debit = 100.000
        $jurnalKasLaci = JurnalAkuntansi::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '110-04'))
            ->first();
        $this->assertNotNull($jurnalKasLaci);
        $this->assertEquals(50000, (float) $jurnalKasLaci->debit);

        $jurnalBank = JurnalAkuntansi::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '110-02'))
            ->first();
        $this->assertNotNull($jurnalBank);
        $this->assertEquals(100000, (float) $jurnalBank->debit);

        // Verifikasi Saldo Laci KasSesiState menghitung porsi tunai split
        $saldoSistem = app(KasSesiState::class)->hitungSaldoSistem($kasSesi);
        // Modal awal 100.000 + porsi tunai 50.000 = 150.000
        $this->assertEquals(150000, $saldoSistem);

        // Verifikasi Audit Log Aktivitas
        $auditLog = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)
            ->where('event', 'penjualan')
            ->first();
        $this->assertNotNull($auditLog, 'Audit log penjualan harus otomatis tercatat');

        $this->assertStringContainsString('Split', $auditLog->ringkasan);
        $this->assertStringContainsString('Tunai Rp 50.000', $auditLog->ringkasan);
        $this->assertStringContainsString('QRIS Rp 100.000', $auditLog->ringkasan);

        $props = $auditLog->normalized_properties;
        $this->assertEquals('split', $props['metode_bayar']);
        $this->assertEquals($splitDetail, $props['split_detail']);
    }

    public function test_servis_payment_split_records_journals_and_status_log(): void
    {
        $this->actingAs($this->kasir);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'nama_pelanggan' => $this->pelanggan->nama,
            'jenis_hp' => 'iPhone 13 Pro',
            'keluhan' => 'Layar bergaris',
            'estimasi_biaya' => 200000,
            'status' => 'selesai',
            'status_pembayaran' => 'belum_lunas',
        ]);

        $splitDetail = [
            'tunai' => 80000.0,
            'non_tunai' => 120000.0,
            'metode_non_tunai' => 'transfer',
        ];

        $servisService = app(ServisService::class);
        $servisService->bayar(
            $tiket,
            'split',
            $this->kasir,
            'Pelunasan Uji Split',
            $splitDetail
        );

        $tiket->refresh();
        $this->assertEquals('lunas', $tiket->status_pembayaran);
        $this->assertEquals('split', $tiket->metode_pembayaran);

        // Verifikasi Jurnal Double Entry Servis:
        $jurnalKasLaci = JurnalAkuntansi::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '110-04'))
            ->first();
        $this->assertNotNull($jurnalKasLaci);
        $this->assertEquals(80000, (float) $jurnalKasLaci->debit);

        $jurnalBank = JurnalAkuntansi::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '110-02'))
            ->first();
        $this->assertNotNull($jurnalBank);
        $this->assertEquals(120000, (float) $jurnalBank->debit);

        // Verifikasi Log Status Tiket
        $statusLog = DB::table('servis_status_log')
            ->where('tiket_servis_id', $tiket->id)
            ->where('aksi', 'pembayaran')
            ->first();
        $this->assertNotNull($statusLog);
        $this->assertStringContainsString('Split - Tunai: Rp 80.000, TRANSFER: Rp 120.000', $statusLog->alasan);
    }

    public function test_online_payment_duitku_creates_audit_log_automatically(): void
    {
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-ONLINE-001',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => 150000,
            'dpp' => 150000,
            'pajak_nominal' => 0,
            'total_akhir' => 150000,
            'metode_bayar' => 'menunggu',
            'status' => 'menunggu_pembayaran',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 150000,
            'subtotal' => 150000,
            'hpp' => 100000,
        ]);

        $processor = app(DuitkuPaymentProcessor::class);
        $result = $processor->prosesLunas($transaksi->no_transaksi, 150000, 'REF-DUITKU-123', 'qris');

        $this->assertEquals('sukses', $result['kode']);

        $transaksi->refresh();
        $this->assertEquals('lunas', $transaksi->status);
        $this->assertEquals('qris', $transaksi->metode_bayar);

        // Verifikasi bahwa audit log otomatis tercatat
        $auditLog = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)
            ->where('event', 'penjualan')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log pesanan online harus otomatis tercatat');
        $this->assertStringContainsString('Pesanan online', $auditLog->ringkasan);
        $this->assertStringContainsString('dibayar via QRIS', $auditLog->ringkasan);
    }

    public function test_riwayat_aktivitas_and_export_format_split_payment_properly(): void
    {
        $this->assertEquals('Rincian Pembayaran Split', RiwayatAktivitas::labelKolom('split_detail'));
        $this->assertEquals('Metode Bayar Selisih', RiwayatAktivitas::labelKolom('metode_bayar_selisih'));

        $splitData = [
            'tunai' => 45000,
            'non_tunai' => 105000,
            'metode_non_tunai' => 'transfer_bca',
        ];

        $formatted = RiwayatAktivitas::formatNilai($splitData, 'split_detail');
        $this->assertEquals('Tunai: Rp 45.000 | TRANSFER BCA: Rp 105.000', $formatted);

        // Test Export
        $log = new AktivitasLog;
        $log->log_name = 'transaksi';
        $log->description = 'Penjualan Kasir TRX-001';
        $log->subject_type = Transaksi::class;
        $log->subject_id = 999;
        $log->event = 'penjualan';
        $log->properties = collect([
            'metode_bayar' => 'split',
            'split_detail' => $splitData,
        ]);

        $export = new AuditLogExport(collect([$log]));
        $row = $export->map($log);

        // Kolom metadata (index 11) memuat rincian split
        $metadataCol = $row[11];
        $this->assertStringContainsString('SPLIT (Tunai: Rp 45.000 | TRANSFER BCA: Rp 105.000)', $metadataCol);
    }
}
