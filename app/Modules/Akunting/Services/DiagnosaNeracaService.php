<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\KasMatching;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service Diagnosa Cerdas & Rekonsiliasi Neraca Akuntansi.
 * Menganalisa seluruh transaksi aplikasi secara lintas modul untuk mendeteksi
 * akar penyebab ketidakseimbangan neraca dan mengarahkan tindak lanjut ke bagian terkait.
 */
class DiagnosaNeracaService
{
    public function __construct(
        protected ExportLaporanService $exportLaporanService,
        protected JurnalService $jurnalService
    ) {}

    /**
     * Jalankan diagnosa menyeluruh status neraca dan integritas transaksi.
     *
     * @return array<string, mixed>
     */
    public function diagnosa(?int $cabangId = null, ?string $sampai = null): array
    {
        $sampai = $sampai ?: now()->toDateString();
        $neraca = $this->exportLaporanService->neracaSaldo($cabangId, $sampai);

        $selisih = (float) ($neraca['selisih'] ?? 0);
        $balance = (bool) ($neraca['balance'] ?? (abs($selisih) < 0.01));

        $temuan = [];

        // 1. Diagnosa Integritas Jurnal (Double-Entry Debit vs Kredit)
        $this->diagnosaJurnalDoubleEntry($cabangId, $temuan);

        // 2. Diagnosa Akun COA & Pemetaan Tipe
        $this->diagnosaAkunCoa($temuan);

        // 3. Diagnosa Transaksi Kasir POS Tanpa Jurnal / Selisih Kas
        $this->diagnosaTransaksiPos($cabangId, $temuan);

        // 4. Diagnosa Tiket Servis Tanpa Jurnal Pendapatan / Piutang
        $this->diagnosaTiketServis($cabangId, $temuan);

        // 5. Diagnosa Pengadaan & WMS (PO / Stok Opname)
        $this->diagnosaWmsPengadaan($cabangId, $temuan);

        // 6. Diagnosa Penggajian & Payroll
        $this->diagnosaPayroll($cabangId, $temuan);

        // 7. Diagnosa Sesi Kasir & Mutasi Kas Laci
        $this->diagnosaKasSesi($cabangId, $temuan);

        // 8. Diagnosa Matching Kas Real vs Aplikasi
        $this->diagnosaMatchingKas($cabangId, $temuan);

        // Ringkasan per Departemen / Bagian Terkait
        $bagianRangkuman = [
            'Finance / Accounting' => ['total' => 0, 'kritis' => 0, 'peringatan' => 0, 'dampak' => 0],
            'Kasir / POS' => ['total' => 0, 'kritis' => 0, 'peringatan' => 0, 'dampak' => 0],
            'Service Advisor / Teknisi' => ['total' => 0, 'kritis' => 0, 'peringatan' => 0, 'dampak' => 0],
            'Staff Gudang / WMS' => ['total' => 0, 'kritis' => 0, 'peringatan' => 0, 'dampak' => 0],
            'HR / Payroll' => ['total' => 0, 'kritis' => 0, 'peringatan' => 0, 'dampak' => 0],
        ];

        foreach ($temuan as $item) {
            $bagian = $item['bagian'] ?? 'Finance / Accounting';
            if (! isset($bagianRangkuman[$bagian])) {
                $bagianRangkuman[$bagian] = ['total' => 0, 'kritis' => 0, 'peringatan' => 0, 'dampak' => 0];
            }
            $bagianRangkuman[$bagian]['total']++;
            if ($item['tingkat'] === 'kritis') {
                $bagianRangkuman[$bagian]['kritis']++;
            } elseif ($item['tingkat'] === 'peringatan') {
                $bagianRangkuman[$bagian]['peringatan']++;
            }
            $bagianRangkuman[$bagian]['dampak'] += abs((float) ($item['dampak_nominal'] ?? 0));
        }

        // Kalkulasi Skor Kesehatan Akuntansi (0 - 100)
        $skor = 100;
        if (! $balance) {
            $skor -= 40;
        }
        $jumlahKritis = collect($temuan)->where('tingkat', 'kritis')->count();
        $jumlahPeringatan = collect($temuan)->where('tingkat', 'peringatan')->count();
        $skor -= ($jumlahKritis * 15);
        $skor -= ($jumlahPeringatan * 5);
        $skor = max(0, min(100, $skor));

        // Buat Ringkasan Analisis Naratif Cerdas
        $kesimpulan = $this->bangunKesimpulanNaratif($balance, $selisih, $temuan, $bagianRangkuman);

        return [
            'neraca' => $neraca,
            'is_balance' => $balance,
            'selisih' => $selisih,
            'skor_kesehatan' => $skor,
            'total_temuan' => count($temuan),
            'jumlah_kritis' => $jumlahKritis,
            'jumlah_peringatan' => $jumlahPeringatan,
            'ringkasan_bagian' => $bagianRangkuman,
            'daftar_temuan' => $temuan,
            'kesimpulan_ai' => $kesimpulan,
            'waktu_analisis' => now()->format('d M Y H:i:s'),
        ];
    }

    /**
     * 1. Diagnosa Integritas Jurnal.
     */
    protected function diagnosaJurnalDoubleEntry(?int $cabangId, array &$temuan): void
    {
        if (! Schema::hasTable('jurnal_akuntansi')) {
            return;
        }

        $query = DB::table('jurnal_akuntansi')
            ->select('no_jurnal', 'cabang_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(kredit) as total_kredit, ABS(SUM(debit) - SUM(kredit)) as selisih')
            ->groupBy('no_jurnal', 'cabang_id')
            ->havingRaw('ABS(SUM(debit) - SUM(kredit)) > 0.01');

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        $unbalanced = $query->get();

        foreach ($unbalanced as $row) {
            $temuan[] = [
                'id' => 'jurnal-unbalanced-'.$row->no_jurnal,
                'kategori' => 'jurnal',
                'judul' => 'Jurnal Tidak Balance: '.$row->no_jurnal,
                'tingkat' => 'kritis',
                'bagian' => 'Finance / Accounting',
                'penyebab' => sprintf(
                    'Baris jurnal nomor %s pada cabang %s memiliki total Debit (Rp %s) yang tidak sama dengan total Kredit (Rp %s). Selisih sebesar Rp %s.',
                    $row->no_jurnal,
                    $row->cabang_id ?? 'Global',
                    number_format($row->total_debit, 0, ',', '.'),
                    number_format($row->total_kredit, 0, ',', '.'),
                    number_format($row->selisih, 0, ',', '.')
                ),
                'dampak_nominal' => (float) $row->selisih,
                'rekomendasi' => 'Lakukan posting jurnal koreksi penyeimbang atau tinjau baris transaksi terkait melalui menu Jurnal Akuntansi.',
                'solusi_otomatis_tersedia' => true,
                'auto_fix_key' => 'seimbangkan_jurnal',
                'payload' => [
                    'no_jurnal' => $row->no_jurnal,
                    'cabang_id' => $row->cabang_id,
                    'selisih' => (float) $row->selisih,
                    'sisi_kurang' => $row->total_debit < $row->total_kredit ? 'debit' : 'kredit',
                ],
            ];
        }

        // Cek baris jurnal dengan akun_coa_id yatim (tidak ada di tabel akun_coa)
        $orphanQuery = DB::table('jurnal_akuntansi')
            ->leftJoin('akun_coa', 'jurnal_akuntansi.akun_coa_id', '=', 'akun_coa.id')
            ->whereNull('akun_coa.id')
            ->select('jurnal_akuntansi.no_jurnal', 'jurnal_akuntansi.akun_coa_id')
            ->selectRaw('SUM(debit + kredit) as total_nominal')
            ->groupBy('jurnal_akuntansi.no_jurnal', 'jurnal_akuntansi.akun_coa_id');

        if ($cabangId !== null) {
            $orphanQuery->where('jurnal_akuntansi.cabang_id', $cabangId);
        }

        $orphanLines = $orphanQuery->get();

        foreach ($orphanLines as $o) {
            $temuan[] = [
                'id' => 'jurnal-orphan-account-'.$o->no_jurnal,
                'kategori' => 'jurnal',
                'judul' => 'Baris Jurnal Terhubung ke Akun COA Terhapus / Hilang',
                'tingkat' => 'kritis',
                'bagian' => 'Finance / Accounting',
                'penyebab' => "Jurnal {$o->no_jurnal} mereferensikan akun_coa_id #{$o->akun_coa_id} yang tidak ada di master Chart of Accounts.",
                'dampak_nominal' => (float) $o->total_nominal,
                'rekomendasi' => 'Buat ulang akun COA yang bersangkutan atau petakan kembali baris jurnal ke akun aktif yang sah.',
                'solusi_otomatis_tersedia' => false,
            ];
        }

        // Cek integritas cabang: baris jurnal tanpa cabang_id (kebocoran cabang)
        $nullCabangQuery = DB::table('jurnal_akuntansi')
            ->whereNull('cabang_id')
            ->select('no_jurnal')
            ->selectRaw('SUM(debit + kredit) as total_nominal')
            ->groupBy('no_jurnal');

        $nullCabangLines = $nullCabangQuery->get();
        foreach ($nullCabangLines as $nc) {
            $temuan[] = [
                'id' => 'jurnal-null-cabang-'.$nc->no_jurnal,
                'kategori' => 'jurnal',
                'judul' => 'Jurnal Tanpa Cabang (Kebocoran Cabang): '.$nc->no_jurnal,
                'tingkat' => 'kritis',
                'bagian' => 'Finance / Accounting',
                'penyebab' => "Jurnal {$nc->no_jurnal} tidak memiliki atribut cabang_id sehingga melanggar kontrak isolasi data per cabang.",
                'dampak_nominal' => (float) $nc->total_nominal,
                'rekomendasi' => 'Perbarui cabang_id pada baris jurnal ke cabang yang bersangkutan.',
                'solusi_otomatis_tersedia' => false,
            ];
        }
    }

    /**
     * 2. Diagnosa Akun COA & Pemetaan Tipe.
     */
    protected function diagnosaAkunCoa(array &$temuan): void
    {
        $invalidCoa = AkunCOA::whereNotIn('tipe', ['aset', 'kewajiban', 'ekuitas', 'pendapatan', 'beban'])->get();
        foreach ($invalidCoa as $coa) {
            $temuan[] = [
                'id' => 'coa-tipe-invalid-'.$coa->kode,
                'kategori' => 'jurnal',
                'judul' => 'Tipe Akun COA Tidak Standar: '.$coa->kode,
                'tingkat' => 'kritis',
                'bagian' => 'Finance / Accounting',
                'penyebab' => "Akun {$coa->kode} ({$coa->nama}) memiliki tipe '{$coa->tipe}' yang tidak dikenali dalam formula neraca baku.",
                'dampak_nominal' => 0,
                'rekomendasi' => "Ubah tipe akun melalui master COA menjadi salah satu dari: 'aset', 'kewajiban', 'ekuitas', 'pendapatan', atau 'beban'.",
                'solusi_otomatis_tersedia' => false,
            ];
        }
    }

    /**
     * 3. Diagnosa Transaksi Kasir POS.
     */
    protected function diagnosaTransaksiPos(?int $cabangId, array &$temuan): void
    {
        if (! Schema::hasTable('transaksi')) {
            return;
        }

        $query = Transaksi::where('status', 'selesai')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jurnal_akuntansi')
                    ->whereIn('jurnal_akuntansi.referensi_tipe', ['transaksi', Transaksi::class, 'App\\Modules\\Pos\\Models\\Transaksi', 'pos'])
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'transaksi.id');
            });

        // Transaksi yang berasal dari tiket servis sudah dijurnal oleh modul servis
        if (Schema::hasColumn('transaksi', 'tiket_servis_id')) {
            $query->whereNull('tiket_servis_id');
        }

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        $unpostedPos = $query->latest('id')->limit(10)->get();

        if ($unpostedPos->isNotEmpty()) {
            $totalNominal = $unpostedPos->sum('total_akhir');
            $temuan[] = [
                'id' => 'pos-unposted-transactions',
                'kategori' => 'pos',
                'judul' => sprintf('%d Transaksi Kasir POS Selesai Tanpa Jurnal', $unpostedPos->count()),
                'tingkat' => 'kritis',
                'bagian' => 'Kasir / POS',
                'penyebab' => sprintf(
                    'Terdapat %d transaksi kasir POS yang telah selesai dengan total nilai Rp %s, namun belum memiliki catatan jurnal penjualan/kas di buku besar.',
                    $unpostedPos->count(),
                    number_format($totalNominal, 0, ',', '.')
                ),
                'dampak_nominal' => (float) $totalNominal,
                'rekomendasi' => 'Periksa koneksi saat transaksi kasir diproses, lalu lakukan sinkronisasi atau posting ulang jurnal transaksi kasir.',
                'solusi_otomatis_tersedia' => false,
            ];
        }
    }

    /**
     * 4. Diagnosa Tiket Servis.
     */
    protected function diagnosaTiketServis(?int $cabangId, array &$temuan): void
    {
        if (! Schema::hasTable('tiket_servis')) {
            return;
        }

        $query = TiketServis::where('status', 'selesai')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jurnal_akuntansi')
                    ->whereIn('jurnal_akuntansi.referensi_tipe', ['tiket_servis', TiketServis::class, 'App\\Modules\\Servis\\Models\\TiketServis', 'servis'])
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'tiket_servis.id');
            });

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        $unpostedServis = $query->latest('id')->limit(10)->get();

        if ($unpostedServis->isNotEmpty()) {
            $totalEst = $unpostedServis->sum('estimasi_biaya');
            $temuan[] = [
                'id' => 'servis-unposted-tickets',
                'kategori' => 'servis',
                'judul' => sprintf('%d Tiket Servis Selesai Tanpa Jurnal Pendapatan/Piutang', $unpostedServis->count()),
                'tingkat' => 'kritis',
                'bagian' => 'Service Advisor / Teknisi',
                'penyebab' => sprintf(
                    'Terdapat %d unit servis selesai dengan total estimasi Rp %s yang belum membukukan jurnal piutang/pendapatan jasa & sparepart.',
                    $unpostedServis->count(),
                    number_format($totalEst, 0, ',', '.')
                ),
                'dampak_nominal' => (float) $totalEst,
                'rekomendasi' => 'Service Advisor wajib memastikan penyelesaian tiket men-trigger posting jurnal atau verifikasi ketersediaan akun COA pendapatan jasa (420-01).',
                'solusi_otomatis_tersedia' => false,
            ];
        }
    }

    /**
     * 5. Diagnosa Pengadaan & WMS.
     */
    protected function diagnosaWmsPengadaan(?int $cabangId, array &$temuan): void
    {
        if (Schema::hasTable('purchase_order')) {
            $queryPo = PurchaseOrder::whereIn('status', ['selesai', 'diterima'])
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('jurnal_akuntansi')
                        ->whereIn('jurnal_akuntansi.referensi_tipe', ['purchase_order', PurchaseOrder::class, 'App\\Modules\\Wms\\Models\\PurchaseOrder'])
                        ->whereColumn('jurnal_akuntansi.referensi_id', 'purchase_order.id');
                });

            if ($cabangId !== null) {
                $queryPo->whereHas('gudangTujuan', function ($q) use ($cabangId) {
                    $q->where('cabang_id', $cabangId);
                });
            }

            $unpostedPo = $queryPo->limit(10)->get();

            if ($unpostedPo->isNotEmpty()) {
                $totalPo = $unpostedPo->sum('total');
                $temuan[] = [
                    'id' => 'wms-po-unposted',
                    'kategori' => 'wms',
                    'judul' => sprintf('%d Purchase Order Selesai Tanpa Jurnal Pembelian', $unpostedPo->count()),
                    'tingkat' => 'peringatan',
                    'bagian' => 'Staff Gudang / WMS',
                    'penyebab' => sprintf('PO selesai senilai Rp %s belum tercatat di jurnal Persediaan (130-01) / Utang Usaha (210-01).', number_format($totalPo, 0, ',', '.')),
                    'dampak_nominal' => (float) $totalPo,
                    'rekomendasi' => 'Finance & Gudang perlu memvalidasi dokumen GRN (Goods Receipt Note) dan membuat pengakuan utang usaha.',
                    'solusi_otomatis_tersedia' => false,
                ];
            }
        }

        if (Schema::hasTable('stok_opname')) {
            $queryOpname = StokOpname::whereIn('status', ['disetujui', 'selesai'])
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('jurnal_akuntansi')
                        ->whereIn('jurnal_akuntansi.referensi_tipe', ['stok_opname', StokOpname::class, 'App\\Modules\\Wms\\Models\\StokOpname'])
                        ->whereColumn('jurnal_akuntansi.referensi_id', 'stok_opname.id');
                });

            if ($cabangId !== null) {
                $queryOpname->whereHas('gudang', function ($q) use ($cabangId) {
                    $q->where('cabang_id', $cabangId);
                });
            }

            $unpostedOpname = $queryOpname->limit(5)->get();

            if ($unpostedOpname->isNotEmpty()) {
                $temuan[] = [
                    'id' => 'wms-opname-unposted',
                    'kategori' => 'wms',
                    'judul' => sprintf('%d Stok Opname Disetujui Tanpa Jurnal Penyesuaian', $unpostedOpname->count()),
                    'tingkat' => 'peringatan',
                    'bagian' => 'Staff Gudang / WMS',
                    'penyebab' => sprintf('Terdapat %d dokumen opname fisik gudang yang disetujui namun belum memiliki posting penyesuaian nilai persediaan.', $unpostedOpname->count()),
                    'dampak_nominal' => 0,
                    'rekomendasi' => 'Persetujuan manajer gudang diperlukan untuk membukukan penyesuaian nilai persediaan fisik vs buku.',
                    'solusi_otomatis_tersedia' => false,
                ];
            }
        }
    }

    /**
     * 6. Diagnosa Penggajian & Payroll.
     */
    protected function diagnosaPayroll(?int $cabangId, array &$temuan): void
    {
        if (! Schema::hasTable('payroll_periode')) {
            return;
        }

        $periodeSelesai = PayrollPeriode::whereIn('status', ['selesai', 'dibayar'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jurnal_akuntansi')
                    ->whereIn('jurnal_akuntansi.referensi_tipe', ['payroll_periode', PayrollPeriode::class, 'App\\Modules\\Hr\\Models\\PayrollPeriode'])
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'payroll_periode.id');
            })
            ->get();

        foreach ($periodeSelesai as $p) {
            $slipQuery = PayrollSlip::where('payroll_periode_id', $p->id);
            if ($cabangId !== null) {
                $slipQuery->whereHas('karyawan', fn ($q) => $q->where('cabang_id', $cabangId));
            }
            $totalGaji = $slipQuery->sum('total_gaji');

            if ($cabangId !== null && $totalGaji <= 0) {
                continue;
            }

            $temuan[] = [
                'id' => 'payroll-unposted-'.$p->periode,
                'kategori' => 'hr',
                'judul' => "Payroll Periode {$p->periode} Belum Terjurnal",
                'tingkat' => 'kritis',
                'bagian' => 'HR / Payroll',
                'penyebab' => sprintf('Periode payroll %s berstatus %s (total gaji Rp %s) belum memiliki posting jurnal Beban Gaji & Utang Gaji.', $p->periode, $p->status, number_format($totalGaji, 0, ',', '.')),
                'dampak_nominal' => (float) $totalGaji,
                'rekomendasi' => 'Buka menu HR & Payroll (/app/hr/payroll) lalu proses approval atau finalisasi pembayaran payroll.',
                'solusi_otomatis_tersedia' => false,
            ];
        }
    }

    /**
     * 7. Diagnosa Sesi Kasir & Mutasi Kas Laci.
     */
    protected function diagnosaKasSesi(?int $cabangId, array &$temuan): void
    {
        if (! Schema::hasTable('kas_sesi')) {
            return;
        }

        $query = DB::table('kas_sesi')
            ->where('status', 'tutup')
            ->whereNotNull('selisih')
            ->where('selisih', '<>', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jurnal_akuntansi')
                    ->where('jurnal_akuntansi.referensi_tipe', 'kas_sesi')
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'kas_sesi.id')
                    ->where('jurnal_akuntansi.deskripsi', 'like', '%Selisih Kas%');
            });

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        $sesiSelisih = $query->latest('id')->limit(5)->get();

        foreach ($sesiSelisih as $sesi) {
            $temuan[] = [
                'id' => 'kas-sesi-selisih-'.$sesi->id,
                'kategori' => 'kas',
                'judul' => 'Sesi Kasir Tutup dengan Selisih Kas Belum Disesuaikan',
                'tingkat' => 'peringatan',
                'bagian' => 'Kasir / POS',
                'penyebab' => sprintf(
                    'Sesi kasir #%d mencatat selisih fisik Rp %s (Sistem: Rp %s, Fisik: Rp %s) yang belum dibukukan ke jurnal penyesuaian selisih kas (520-07).',
                    $sesi->id,
                    number_format($sesi->selisih, 0, ',', '.'),
                    number_format($sesi->saldo_akhir_sistem ?? 0, 0, ',', '.'),
                    number_format($sesi->saldo_akhir_fisik ?? 0, 0, ',', '.')
                ),
                'dampak_nominal' => abs((float) $sesi->selisih),
                'rekomendasi' => 'Head Cashier / Supervisor wajib memverifikasi berita acara selisih laci kasir dan membukukan penyesuaian.',
                'solusi_otomatis_tersedia' => true,
                'auto_fix_key' => 'posting_selisih_kas_sesi',
                'payload' => [
                    'kas_sesi_id' => $sesi->id,
                    'cabang_id' => $sesi->cabang_id,
                    'selisih' => (float) $sesi->selisih,
                ],
            ];
        }
    }

    /**
     * 8. Diagnosa Matching Kas Real vs Aplikasi.
     */
    protected function diagnosaMatchingKas(?int $cabangId, array &$temuan): void
    {
        if (! Schema::hasTable('kas_matching')) {
            return;
        }

        $query = KasMatching::with('akun')
            ->where('status', 'selisih')
            ->whereNull('jurnal_id');

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        $unadjustedMatching = $query->latest('tanggal')->limit(5)->get();

        foreach ($unadjustedMatching as $m) {
            $temuan[] = [
                'id' => 'kas-matching-unadjusted-'.$m->id,
                'kategori' => 'kas',
                'judul' => sprintf('Pencocokan Kas Real: Selisih pada %s (%s)', $m->akun?->nama ?? 'Kas', $m->akun?->kode ?? '-'),
                'tingkat' => 'peringatan',
                'bagian' => 'Finance / Accounting',
                'penyebab' => sprintf(
                    'Pencocokan fisik per tanggal %s menunjukkan selisih Rp %s (Fisik: Rp %s, Sistem: Rp %s).',
                    $m->tanggal->format('d/m/Y'),
                    number_format($m->selisih, 0, ',', '.'),
                    number_format($m->saldo_fisik, 0, ',', '.'),
                    number_format($m->saldo_sistem, 0, ',', '.')
                ),
                'dampak_nominal' => abs((float) $m->selisih),
                'rekomendasi' => 'Periksa bukti transaksi fisik atau mutasi bank rekening koran. Jika sah, klik tombol Posting Penyesuaian Kas.',
                'solusi_otomatis_tersedia' => true,
                'auto_fix_key' => 'posting_selisih_kas_matching',
                'payload' => [
                    'kas_matching_id' => $m->id,
                    'cabang_id' => $m->cabang_id,
                    'akun_id' => $m->akun_id,
                    'selisih' => (float) $m->selisih,
                ],
            ];
        }
    }

    /**
     * Bangun kesimpulan naratif cerdas AI/Heuristik.
     */
    protected function bangunKesimpulanNaratif(bool $balance, float $selisih, array $temuan, array $bagianRangkuman): string
    {
        if ($balance && empty($temuan)) {
            return 'Seluruh neraca keuangan berada dalam kondisi SEIMBANG sempurna (Aset = Kewajiban + Ekuitas + Laba Berjalan). Tidak terdeteksi anomali pada siklus transaksi POS, Servis, WMS, Payroll, maupun Kas. Sistem akuntansi 100% sehat dan terverifikasi double-entry.';
        }

        if ($balance && ! empty($temuan)) {
            $kritisCount = collect($temuan)->where('tingkat', 'kritis')->count();
            if ($kritisCount === 0) {
                return sprintf(
                    'Neraca saldo matematis saat ini SEIMBANG, namun terdeteksi %d catatan operasional (seperti sesi kasir atau pencocokan fisik kas yang belum disesuaikan). Disarankan bagian terkait meninjau rekomendasi perbaikan di bawah.',
                    count($temuan)
                );
            }
        }

        // Jika Neraca TIDAK balance:
        $penyebabUtama = [];
        foreach ($bagianRangkuman as $namaBagian => $data) {
            if ($data['total'] > 0) {
                $penyebabUtama[] = sprintf('%s (%d isu, dampak Rp %s)', $namaBagian, $data['total'], number_format($data['dampak'], 0, ',', '.'));
            }
        }

        return sprintf(
            'PERHATIAN: Neraca terdeteksi TIDAK SEIMBANG dengan selisih Rp %s. Analisis otomatis menemukan %d potensi penyebab ketidakseimbangan yang terdistribusi pada: %s. Prioritaskan perbaikan pada temuan berlabel KRITIS untuk mengembalikan keseimbangan neraca secara tepat audit.',
            number_format(abs($selisih), 0, ',', '.'),
            count($temuan),
            implode('; ', $penyebabUtama)
        );
    }

    /**
     * Eksekusi solusi otomatis yang disetujui pengguna.
     *
     * @return array{success: bool, message: string}
     */
    public function perbaikiOtomatis(string $aksiKey, array $payload, ?int $userId = null): array
    {
        return DB::transaction(function () use ($aksiKey, $payload, $userId) {
            switch ($aksiKey) {
                case 'seimbangkan_jurnal':
                    $noJurnal = $payload['no_jurnal'] ?? null;
                    $cabangId = $payload['cabang_id'] ?? null;
                    $selisih = abs((float) ($payload['selisih'] ?? 0));
                    $sisiKurang = $payload['sisi_kurang'] ?? 'debit';

                    if (! $noJurnal || $selisih <= 0) {
                        return ['success' => false, 'message' => 'Parameter perbaikan jurnal tidak valid.'];
                    }

                    // Seimbangkan langsung baris pada no_jurnal yang timpang agar nomor jurnal tersebut seimbang
                    $akunSuspense = AkunCOA::where('kode', '520-05')->first() ?: AkunCOA::where('tipe', 'beban')->first();
                    if (! $akunSuspense) {
                        return ['success' => false, 'message' => 'Akun suspense tidak ditemukan.'];
                    }

                    DB::table('jurnal_akuntansi')->insert([
                        'no_jurnal' => $noJurnal,
                        'tanggal' => now(),
                        'cabang_id' => $cabangId,
                        'akun_coa_id' => $akunSuspense->id,
                        'sumber' => 'manual',
                        'deskripsi' => "Penyeimbang Koreksi Otomatis {$noJurnal}",
                        'debit' => $sisiKurang === 'debit' ? $selisih : 0,
                        'kredit' => $sisiKurang === 'kredit' ? $selisih : 0,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return ['success' => true, 'message' => "Baris penyeimbang berhasil ditambahkan pada jurnal {$noJurnal}."];

                case 'posting_selisih_kas_matching':
                    $matchingId = $payload['kas_matching_id'] ?? null;
                    $matching = KasMatching::with('akun')->find($matchingId);
                    if (! $matching || $matching->jurnal_id) {
                        return ['success' => false, 'message' => 'Data matching kas tidak ditemukan atau sudah disesuaikan.'];
                    }

                    $selisih = (float) $matching->selisih;
                    if (abs($selisih) < 0.01) {
                        return ['success' => false, 'message' => 'Tidak ada selisih kas yang perlu dibukukan.'];
                    }

                    $akunKasKode = $matching->akun?->kode ?? '110-01';
                    $noJurnal = $this->jurnalService->generateNoJurnal('kas', $matching->cabang_id);

                    // Selisih = Fisik - Sistem
                    // Jika selisih > 0 (Fisik lebih banyak): Debit Kas, Kredit Pendapatan Lain-lain (430-01) / Selisih Kas
                    // Jika selisih < 0 (Fisik tekor): Debit Beban Selisih Kas (520-07), Kredit Kas
                    $lines = $selisih > 0
                        ? [
                            ['akun_kode' => $akunKasKode, 'debit' => abs($selisih), 'kredit' => 0],
                            ['akun_kode' => '430-01', 'debit' => 0, 'kredit' => abs($selisih)],
                        ]
                        : [
                            ['akun_kode' => '520-07', 'debit' => abs($selisih), 'kredit' => 0],
                            ['akun_kode' => $akunKasKode, 'debit' => 0, 'kredit' => abs($selisih)],
                        ];

                    $jurnalRows = $this->jurnalService->post(
                        $noJurnal,
                        now(),
                        'kas',
                        $lines,
                        "Penyesuaian Selisih Kas Real per {$matching->tanggal->format('d/m/Y')}",
                        $matching->cabang_id,
                        $userId,
                        KasMatching::class,
                        $matching->id
                    );

                    $matching->update([
                        'status' => 'disesuaikan',
                        'jurnal_id' => $jurnalRows[0]->id ?? null,
                    ]);

                    return ['success' => true, 'message' => "Jurnal penyesuaian selisih kas {$noJurnal} berhasil dibukukan."];

                case 'posting_selisih_kas_sesi':
                    $sesiId = $payload['kas_sesi_id'] ?? null;
                    $sesi = DB::table('kas_sesi')->where('id', $sesiId)->first();
                    if (! $sesi || ! $sesi->selisih) {
                        return ['success' => false, 'message' => 'Sesi kasir tidak valid atau tidak memiliki selisih.'];
                    }

                    $selisih = (float) $sesi->selisih;
                    $noJurnal = $this->jurnalService->generateNoJurnal('kas', (int) $sesi->cabang_id);
                    $akunKasLaci = '110-04';

                    $lines = $selisih > 0
                        ? [
                            ['akun_kode' => $akunKasLaci, 'debit' => abs($selisih), 'kredit' => 0],
                            ['akun_kode' => '430-01', 'debit' => 0, 'kredit' => abs($selisih)],
                        ]
                        : [
                            ['akun_kode' => '520-07', 'debit' => abs($selisih), 'kredit' => 0],
                            ['akun_kode' => $akunKasLaci, 'debit' => 0, 'kredit' => abs($selisih)],
                        ];

                    $this->jurnalService->post(
                        $noJurnal,
                        now(),
                        'kas',
                        $lines,
                        "Penyesuaian Selisih Kas Sesi Kasir #{$sesi->id}",
                        $sesi->cabang_id,
                        $userId,
                        'kas_sesi',
                        $sesi->id
                    );

                    return ['success' => true, 'message' => "Jurnal selisih kas sesi #{$sesi->id} berhasil diposting ({$noJurnal})."];

                default:
                    return ['success' => false, 'message' => "Aksi perbaikan '{$aksiKey}' belum didukung secara otomatis."];
            }
        });
    }
}
