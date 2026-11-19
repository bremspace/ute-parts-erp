<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\KasMatching;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Grn;
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
                        ->where(function ($w) {
                            $w->where(function ($direct) {
                                $direct->whereIn('jurnal_akuntansi.referensi_tipe', ['purchase_order', PurchaseOrder::class, 'App\\Modules\\Wms\\Models\\PurchaseOrder'])
                                    ->whereColumn('jurnal_akuntansi.referensi_id', 'purchase_order.id');
                            });
                            if (Schema::hasTable('grn')) {
                                $w->orWhere(function ($viaGrn) {
                                    $viaGrn->whereIn('jurnal_akuntansi.referensi_tipe', ['grn', Grn::class, 'App\\Modules\\Wms\\Models\\Grn'])
                                        ->whereIn('jurnal_akuntansi.referensi_id', function ($g) {
                                            $g->select('id')->from('grn')->whereColumn('grn.po_id', 'purchase_order.id');
                                        });
                                });
                            }
                        });
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

        // [D-2] Deteksi "sudah disesuaikan" TIDAK boleh bergantung LIKE deskripsi
        // (frasa approval "Selisih kas sesi #id (disetujui)" berbeda kapitalisasi).
        // Jurnal selisih (jalur approval maupun auto-fix) selalu melibatkan akun
        // lawan 520-07 / 430-01; jurnal buka-kas/deposit-back referensi kas_sesi
        // tidak pernah memakai akun itu.
        $akunSelisihIds = AkunCOA::whereIn('kode', ['520-07', '430-01'])->pluck('id');

        $query = DB::table('kas_sesi')
            ->where('status', 'tutup')
            ->whereNotNull('selisih')
            ->where('selisih', '<>', 0)
            ->whereNotExists(function ($q) use ($akunSelisihIds) {
                $q->select(DB::raw(1))
                    ->from('jurnal_akuntansi')
                    ->where('jurnal_akuntansi.referensi_tipe', 'kas_sesi')
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'kas_sesi.id')
                    ->whereIn('jurnal_akuntansi.akun_coa_id', $akunSelisihIds);
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
     * [B-1 / D-1 / D-2 / D-3] Guard keamanan auto-fix:
     * - Izin server-side `akunting.approve` (Livewire action bisa dipanggil langsung).
     * - Scope cabang fail-closed: $cabangScopeId > 0 = hanya cabang itu;
     *   null = konsolidasi HANYA bila user punya `laporan.konsolidasi`;
     *   0 / tanpa hak = tolak.
     * - Nominal TIDAK PERNAH dari payload client: selalu hitung ulang dari DB.
     * - Semua posting lewat JurnalService::post resmi (balance, akun aktif,
     *   periode terbuka, header sinkron, idempotency key unique per cabang).
     * - Jurnal existing TIDAK PERNAH dimodifikasi (immutability ISO): koreksi
     *   `seimbangkan_jurnal` berupa jurnal TERPISAH (pembalikan baris asal +
     *   plug akun suspense 520-05) dengan nomor baru.
     *
     * @param  int|null  $cabangScopeId  scope cabang pemanggil (null = konsolidasi berizin)
     * @return array{success: bool, message: string}
     */
    public function perbaikiOtomatis(string $aksiKey, array $payload, ?int $userId = null, ?int $cabangScopeId = null): array
    {
        $user = auth()->user();
        if (! $user || ! $user->can('akunting.approve')) {
            return ['success' => false, 'message' => 'Anda tidak memiliki izin (akunting.approve) untuk menjalankan perbaikan otomatis.'];
        }

        $konsolidasi = $cabangScopeId === null;
        if ($konsolidasi && ! $user->can('laporan.konsolidasi')) {
            return ['success' => false, 'message' => 'Scope cabang tidak valid. Pilih cabang aktif atau gunakan cakupan konsolidasi berizin.'];
        }
        if (! $konsolidasi && $cabangScopeId <= 0) {
            return ['success' => false, 'message' => 'Tidak ada cabang aktif. Perbaikan otomatis tidak dapat dijalankan (fail-closed).'];
        }

        // Idempotensi ditegakkan unique index jurnal_header(cabang_key, idempotency_key);
        // tanpa tabel header tidak ada jaminan anti-dobel -> tolak (fail-closed).
        if (! Schema::hasTable('jurnal_header')) {
            return ['success' => false, 'message' => 'Tabel jurnal_header belum tersedia. Jalankan migrasi sebelum memakai perbaikan otomatis.'];
        }

        $authId = auth()->id();
        $userId = $authId !== null ? (int) $authId : $userId;

        return DB::transaction(function () use ($aksiKey, $payload, $userId, $cabangScopeId, $konsolidasi) {
            switch ($aksiKey) {
                case 'seimbangkan_jurnal':
                    return $this->perbaikiSeimbangkanJurnal($payload, $userId, $cabangScopeId, $konsolidasi);
                case 'posting_selisih_kas_matching':
                    return $this->perbaikiSelisihKasMatching($payload, $userId, $cabangScopeId, $konsolidasi);
                case 'posting_selisih_kas_sesi':
                    return $this->perbaikiSelisihKasSesi($payload, $userId, $cabangScopeId, $konsolidasi);
                default:
                    return ['success' => false, 'message' => "Aksi perbaikan '{$aksiKey}' belum didukung secara otomatis."];
            }
        });
    }

    /**
     * [B-1 / D-1] Koreksi jurnal timpang TANPA menyentuh jurnal asal.
     *
     * Nominal & sisi kurang dihitung ulang dari DB (payload client diabaikan).
     * Koreksi = jurnal baru terpisah: seluruh baris asal dibalik (D<->K) plus
     * plug selisih di akun suspense 520-05, sehingga entri koreksi balance dan
     * lolos mesin JurnalService (akun aktif, periode terbuka, header sinkron).
     * Jurnal asal tetap utuh (immutability) dan tetap ditandai diagnosa sampai
     * diselesaikan manual via reversal/repost resmi. Idempoten per no_jurnal.
     *
     * @return array{success: bool, message: string}
     */
    protected function perbaikiSeimbangkanJurnal(array $payload, ?int $userId, ?int $cabangScopeId, bool $konsolidasi): array
    {
        $noJurnal = trim((string) ($payload['no_jurnal'] ?? ''));
        if ($noJurnal === '') {
            return ['success' => false, 'message' => 'Parameter no_jurnal wajib diisi.'];
        }

        $query = JurnalAkuntansi::where('no_jurnal', $noJurnal)->with('akun');
        if (! $konsolidasi) {
            $query->where('cabang_id', $cabangScopeId);
        }
        $linesAsal = $query->lockForUpdate()->get();
        if ($linesAsal->isEmpty()) {
            return ['success' => false, 'message' => "Jurnal {$noJurnal} tidak ditemukan pada scope cabang Anda."];
        }

        $cabangJurnal = $linesAsal->first()->cabang_id;
        if (! $this->scopeCabangValid($cabangJurnal, $cabangScopeId, $konsolidasi)) {
            return ['success' => false, 'message' => "Jurnal {$noJurnal} bukan milik cabang yang Anda scope."];
        }
        if ($konsolidasi && $linesAsal->pluck('cabang_id')->unique()->count() > 1) {
            return ['success' => false, 'message' => "Nomor jurnal {$noJurnal} dipakai lebih dari satu cabang. Jalankan perbaikan dari scope cabang terkait."];
        }
        $cabangJurnal = (int) $cabangJurnal;

        // Hitung ulang dari DB — selisih/sisi_kurang/cabang_id payload diabaikan.
        $totalDebit = round((float) $linesAsal->sum('debit'), 2);
        $totalKredit = round((float) $linesAsal->sum('kredit'), 2);
        $selisih = round(abs($totalDebit - $totalKredit), 2);
        if ($selisih < 0.01) {
            return ['success' => false, 'message' => "Jurnal {$noJurnal} sudah balance; tidak ada koreksi yang diperlukan."];
        }

        $kunci = "diagnosa-seimbangkan:{$noJurnal}";
        if ($this->idempotensiTerpakai($kunci, $cabangJurnal)) {
            return ['success' => false, 'message' => "Jurnal koreksi untuk {$noJurnal} sudah pernah diposting (idempotency guard). Selesaikan jurnal asal via reversal/repost manual."];
        }

        // Akun suspense WAJIB 520-05 (tanpa fallback akun beban sembarang).
        $suspense = AkunCOA::where('kode', '520-05')->first();
        if (! $suspense || ! $suspense->is_active) {
            return ['success' => false, 'message' => 'Akun suspense 520-05 tidak ditemukan atau nonaktif. Koreksi otomatis dibatalkan.'];
        }

        $lines = [];
        foreach ($linesAsal as $line) {
            $lines[] = [
                'akun_kode' => $line->akun?->kode,
                'debit' => (float) $line->kredit,
                'kredit' => (float) $line->debit,
            ];
        }
        // Plug penyeimbang di sisi yang kurang pada entri pembalikan.
        $lines[] = $totalDebit > $totalKredit
            ? ['akun_kode' => '520-05', 'debit' => $selisih, 'kredit' => 0]
            : ['akun_kode' => '520-05', 'debit' => 0, 'kredit' => $selisih];

        $headerAsal = JurnalHeader::where('no_jurnal', $noJurnal)->where('cabang_key', $cabangJurnal)->first();
        $noKoreksi = $this->jurnalService->generateNoJurnal('manual', $cabangJurnal);

        try {
            $this->jurnalService->post(
                $noKoreksi,
                now(),
                'manual',
                $lines,
                "Jurnal Koreksi Diagnosa (pembalikan + suspense) atas {$noJurnal}",
                $cabangJurnal,
                $userId,
                'jurnal_header',
                $headerAsal?->id,
                $kunci
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => "Jurnal koreksi {$noKoreksi} diposting lewat mesin jurnal resmi (selisih Rp ".number_format($selisih, 0, ',', '.')." diparkir di suspense 520-05). Jurnal asal {$noJurnal} tidak diubah; selesaikan dengan reversal/repost manual."];
    }

    /**
     * [D-3] Posting penyesuaian selisih kas matching: izin + scope cabang +
     * idempoten (jurnal_id + idempotency key + lock baris matching).
     *
     * @return array{success: bool, message: string}
     */
    protected function perbaikiSelisihKasMatching(array $payload, ?int $userId, ?int $cabangScopeId, bool $konsolidasi): array
    {
        $matchingId = (int) ($payload['kas_matching_id'] ?? 0);
        $matching = KasMatching::where('id', $matchingId)->lockForUpdate()->first();
        if (! $matching) {
            return ['success' => false, 'message' => 'Data matching kas tidak ditemukan.'];
        }
        if (! $this->scopeCabangValid($matching->cabang_id, $cabangScopeId, $konsolidasi)) {
            return ['success' => false, 'message' => 'Matching kas ini bukan milik cabang yang Anda scope.'];
        }
        if ($matching->jurnal_id) {
            return ['success' => false, 'message' => 'Data matching kas sudah disesuaikan.'];
        }

        // Nominal dari DB (record matching), bukan payload client.
        $selisih = (float) $matching->selisih;
        if (abs($selisih) < 0.01) {
            return ['success' => false, 'message' => 'Tidak ada selisih kas yang perlu dibukukan.'];
        }

        $cabangId = (int) $matching->cabang_id;
        $kunci = "diagnosa-kas-matching:{$matching->id}";
        if ($this->idempotensiTerpakai($kunci, $cabangId)) {
            return ['success' => false, 'message' => 'Penyesuaian matching kas ini sudah pernah diposting (idempotency guard).'];
        }

        $akunKas = AkunCOA::find($matching->akun_id);
        $akunKasKode = $akunKas?->kode ?? '110-01';
        $noJurnal = $this->jurnalService->generateNoJurnal('kas', $cabangId);

        // Selisih = Fisik - Sistem
        // Jika selisih > 0 (Fisik lebih banyak): Debit Kas, Kredit Pendapatan Lain-lain (430-01)
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

        try {
            $jurnalRows = $this->jurnalService->post(
                $noJurnal,
                now(),
                'kas',
                $lines,
                "Penyesuaian Selisih Kas Real per {$matching->tanggal->format('d/m/Y')}",
                $cabangId,
                $userId,
                KasMatching::class,
                $matching->id,
                $kunci
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $matching->update([
            'status' => 'disesuaikan',
            'jurnal_id' => $jurnalRows[0]->id ?? null,
        ]);

        return ['success' => true, 'message' => "Jurnal penyesuaian selisih kas {$noJurnal} berhasil dibukukan."];
    }

    /**
     * [D-2 / D-3] Posting selisih kas sesi: izin + scope cabang + idempoten.
     * Guard memakai referensi (kas_sesi, id) + akun lawan selisih — bukan LIKE
     * deskripsi — sehingga sesi yang sudah disesuaikan via approval tidak dobel.
     *
     * @return array{success: bool, message: string}
     */
    protected function perbaikiSelisihKasSesi(array $payload, ?int $userId, ?int $cabangScopeId, bool $konsolidasi): array
    {
        $sesiId = (int) ($payload['kas_sesi_id'] ?? 0);
        $sesi = DB::table('kas_sesi')->where('id', $sesiId)->lockForUpdate()->first();
        if (! $sesi || ! $sesi->selisih) {
            return ['success' => false, 'message' => 'Sesi kasir tidak valid atau tidak memiliki selisih.'];
        }
        if ($sesi->status !== 'tutup') {
            return ['success' => false, 'message' => 'Sesi kasir belum tutup. Selisih hanya dapat disesuaikan setelah sesi ditutup.'];
        }
        if (! $this->scopeCabangValid((int) $sesi->cabang_id, $cabangScopeId, $konsolidasi)) {
            return ['success' => false, 'message' => 'Sesi kasir ini bukan milik cabang yang Anda scope.'];
        }

        $selisih = (float) $sesi->selisih;
        if (abs($selisih) < 0.01) {
            return ['success' => false, 'message' => 'Tidak ada selisih kas yang perlu dibukukan.'];
        }

        if ($this->selisihKasSesiSudahDijurnal($sesiId)) {
            return ['success' => false, 'message' => 'Selisih sesi kasir ini sudah pernah dijurnal (termasuk via approval).'];
        }

        $cabangId = (int) $sesi->cabang_id;
        $kunci = "diagnosa-kas-sesi:{$sesi->id}";
        if ($this->idempotensiTerpakai($kunci, $cabangId)) {
            return ['success' => false, 'message' => 'Penyesuaian sesi kasir ini sudah pernah diposting (idempotency guard).'];
        }

        $noJurnal = $this->jurnalService->generateNoJurnal('kas', $cabangId);
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

        try {
            $this->jurnalService->post(
                $noJurnal,
                now(),
                'kas',
                $lines,
                "Penyesuaian Selisih Kas Sesi Kasir #{$sesi->id}",
                $cabangId,
                $userId,
                'kas_sesi',
                $sesi->id,
                $kunci
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => "Jurnal selisih kas sesi #{$sesi->id} berhasil diposting ({$noJurnal})."];
    }

    /**
     * [D-3] Scope cabang fail-closed: target wajib bercabang; scope cabang
     * harus sama persis; konsolidasi (izin sudah dicek di muka) membolehkan semua.
     */
    protected function scopeCabangValid(?int $cabangTarget, ?int $cabangScopeId, bool $konsolidasi): bool
    {
        if ($cabangTarget === null || $cabangTarget <= 0) {
            return false;
        }
        if ($konsolidasi) {
            return true;
        }

        return $cabangScopeId !== null && $cabangTarget === $cabangScopeId;
    }

    /**
     * Cek idempotency key sudah dipakai pada cabang ini (unique index DB).
     */
    protected function idempotensiTerpakai(string $kunci, int $cabangId): bool
    {
        return JurnalHeader::where('cabang_key', $cabangId)
            ->where('idempotency_key', $kunci)
            ->exists();
    }

    /**
     * [D-2] Selisih sesi sudah dijurnal? Deteksi via referensi (kas_sesi, id)
     * + akun lawan selisih (520-07 / 430-01) — tahan collation & perubahan frasa,
     * tidak tertukar dengan jurnal buka-kas / deposit-back sesi yang sama.
     */
    protected function selisihKasSesiSudahDijurnal(int $sesiId): bool
    {
        $akunSelisihIds = AkunCOA::whereIn('kode', ['520-07', '430-01'])->pluck('id');
        if ($akunSelisihIds->isEmpty()) {
            return false;
        }

        return DB::table('jurnal_akuntansi')
            ->where('referensi_tipe', 'kas_sesi')
            ->where('referensi_id', $sesiId)
            ->whereIn('akun_coa_id', $akunSelisihIds)
            ->exists();
    }
}
