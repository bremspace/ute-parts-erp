<?php

namespace App\Console\Commands;

use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokOpname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [B-10c] Rekonsiliasi akunting & stok — READ-ONLY (dry-run report).
 *
 * Tujuan: owner bisa melihat daftar anomali + usulan perbaikan tanpa ada satu
 * pun baris yang berubah. Command ini HANYA menjalankan SELECT (atau
 * `Schema::hasTable`/`hasColumn` yang juga read-only).
 *
 * PERATUAN MUTLAK:
 * - TIDAK ada insert / update / delete / migrate / seed.
 * - TIDAK ada penulisan file (tidak ada ekspor CSV).
 * - Tidak dipanggil dari dalam request HTTP; pemanggilnya owner via terminal
 *   atau pipeline CI.
 *
 * Semua angka diformat gaya Indonesia (ADR 0011 — `number_format(..., 0, ',', '.')`).
 * Setiap pemeriksaan punya severity KRITIS / SEDANG / RINGAN; exit code
 * `FAILURE` (1) bila ada kategori KRITIS, sehingga bisa dipakai sebagai gate CI.
 *
 * Semua query dijaga `Schema::hasTable`/`hasColumn` supaya aman dijalankan pada
 * skema yang belum lengkap (mis. kolom `cabang_id` yang belum ter-backfill, atau
 * tabel HR/PO yang belum dimigrasikan): pemeriksaan dilewati, bukan error fatal.
 */
class RekonsiliasiAkunting extends Command
{
    protected $signature = 'ute:rekonsiliasi
        {--kategori= : Batasi laporan ke kategori tertentu (pisahkan koma), mis. jurnal,po,payroll}
        {--limit=20 : Jumlah contoh baris yang ditampilkan per kategori}';

    protected $description = 'Rekonsiliasi READ-ONLY: laporkan anomali jurnal/transaksi/PO/payroll/opname/piutang/stok/audit/migrasi tanpa mengubah data';

    private const KRITIS = 'KRITIS';

    private const SEDANG = 'SEDANG';

    private const RINGAN = 'RINGAN';

    /** Bobot severity (terburuk = tertinggi) untuk ringkasan. */
    private const BOBOT = [self::KRITIS => 3, self::SEDANG => 2, self::RINGAN => 1];

    /** Status transaksi yang dianggap final sehingga wajib punya jurnal. */
    private const STATUS_TRANSAKSI_FINAL = ['selesai', 'lunas'];

    /** Kunci filter `--kategori` ke judul blok pada laporan. */
    private const KATEGORI = [
        'jurnal' => 'JURNAL',
        'transaksi' => 'TRANSAKSI',
        'po' => 'PURCHASE ORDER',
        'payroll' => 'PAYROLL',
        'opname' => 'OPNAME',
        'piutang' => 'PIUTANG / UTANG',
        'stok' => 'STOK TRAIL',
        'audit' => 'AUDIT',
        'migrasi' => 'MIGRASI TERTUNDA',
    ];

    /** Metode pemeriksaan yang dijalankan untuk tiap kunci kategori. */
    private const PEMERIKSA = [
        'jurnal' => 'cekJurnal',
        'transaksi' => 'cekTransaksi',
        'po' => 'cekPurchaseOrder',
        'payroll' => 'cekPayroll',
        'opname' => 'cekOpname',
        'piutang' => 'cekPiutangUtang',
        'stok' => 'cekStokTrail',
        'audit' => 'cekAudit',
        'migrasi' => 'cekMigrasiTertunda',
    ];

    /**
     * Temuan per kategori.
     *
     * @var array<string, array<int, array{judul: string, severity: string, jumlah: int, contoh: array<int, string>, usulan: string}>>
     */
    private array $hasil = [];

    /**
     * Alasan pemeriksaan yang dilewati (tabel/kolom belum ada) per kategori.
     *
     * @var array<string, array<int, string>>
     */
    private array $lewati = [];

    /** Jumlah contoh baris per kategori (`--limit`). */
    private int $limit = 20;

    /**
     * Jalankan seluruh pemeriksaan READ-ONLY lalu cetak laporan dan ringkasan.
     *
     * @return int `self::SUCCESS` bila nihil atau tidak ada KRITIS,
     *             `self::FAILURE` bila ada kategori KRITIS atau `--kategori` tidak valid.
     */
    public function handle(): int
    {
        $this->limit = max(1, (int) $this->option('limit'));

        $diminta = $this->parseKategori();
        if ($diminta === null) {
            $this->error('Kategori tidak dikenal. Pilihan yang tersedia: '.implode(', ', array_keys(self::KATEGORI)));

            return self::FAILURE;
        }

        $this->line('<info>REKONSILIASI AKUNTING — READ-ONLY (tidak ada data yang diubah)</info>');
        $this->line(sprintf(
            'Koneksi: %s | Kategori aktif: %s | Contoh per kategori: %s',
            (string) config('database.default'),
            implode(', ', $diminta),
            $this->angka($this->limit)
        ));
        $this->newLine();

        foreach ($diminta as $kategori) {
            $this->cek($kategori);
        }

        foreach ($diminta as $kategori) {
            $this->cetakKategori($kategori);
        }

        return $this->cetakRingkasan($diminta) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Jalankan metode pemeriksaan milik satu kategori.
     */
    private function cek(string $kategori): void
    {
        $metode = self::PEMERIKSA[$kategori] ?? null;

        if ($metode === null) {
            $this->lewati($kategori, 'tidak ada pemeriksaan yang terdaftar untuk kategori ini');

            return;
        }

        $this->{$metode}();
    }

    /**
     * Urutkan `--kategori` menjadi daftar kunci yang valid.
     *
     * @return array<int, string>|null daftar kunci, atau null bila ada kunci asing.
     */
    private function parseKategori(): ?array
    {
        $mentah = trim((string) $this->option('kategori'));
        if ($mentah === '') {
            return array_keys(self::KATEGORI);
        }

        $diminta = array_values(array_unique(array_filter(array_map(
            static fn (string $v): string => strtolower(trim($v)),
            explode(',', $mentah)
        ))));

        $asing = array_values(array_diff($diminta, array_keys(self::KATEGORI)));
        if ($asing !== []) {
            $this->line('Kategori asing: '.implode(', ', $asing));

            return null;
        }

        return array_values(array_filter(
            array_keys(self::KATEGORI),
            static fn (string $k): bool => in_array($k, $diminta, true)
        ));
    }

    /**
     * Format angka gaya Indonesia (ADR 0011).
     */
    private function angka(int|float|string|null $nilai, int $desimal = 0): string
    {
        return number_format((float) $nilai, $desimal, ',', '.');
    }

    /**
     * Format nominal rupiah gaya Indonesia, contoh `Rp 1.500.000`.
     */
    private function rupiah(int|float|string|null $nilai): string
    {
        return 'Rp '.$this->angka($nilai);
    }

    /**
     * Guard skema: tabel ada dan semua kolom yang dipakai tersedia.
     *
     * Melindungi laporan dari error fatal saat migrasi belum dijalankan atau
     * kolom baru (mis. `cabang_id`) belum tersedia.
     */
    private function ada(string $tabel, string ...$kolom): bool
    {
        if (! Schema::hasTable($tabel)) {
            return false;
        }

        foreach ($kolom as $satu) {
            if (! Schema::hasColumn($tabel, $satu)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Catat satu temuan anomali beserta usulan perbaikannya (tidak dieksekusi).
     *
     * Temuan dengan jumlah nol TIDAK dicatat agar kategori yang bersih tampil
     * sebagai "tidak ada anomali" dan tidak memunculkan label severity yang
     * menyesatkan pada baris bernilai 0.
     *
     * @param  array<int, string>  $contoh
     */
    private function catat(string $kategori, string $judul, string $severity, int $jumlah, array $contoh, string $usulan): void
    {
        if ($jumlah <= 0) {
            return;
        }

        $this->hasil[$kategori][] = [
            'judul' => $judul,
            'severity' => $severity,
            'jumlah' => $jumlah,
            'contoh' => array_slice($contoh, 0, $this->limit),
            'usulan' => $usulan,
        ];
    }

    /**
     * Catat pemeriksaan yang dilewati karena tabel atau kolom belum tersedia.
     */
    private function lewati(string $kategori, string $alasan): void
    {
        $this->lewati[$kategori][] = $alasan;
    }

    /**
     * Susun ekspresi SQL `LIKE` yang menyisipkan nilai kolom lain secara korelasi.
     *
     * MySQL memakai `CONCAT`, SQLite dan PostgreSQL memakai operator `||`.
     * Contoh untuk kolom nilai `jurnal_akuntansi.deskripsi` dan pola
     * `transaksi.no_transaksi`.
     */
    private function ekspresiLike(string $kolomNilai, string $kolomPola): string
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return sprintf("%s LIKE CONCAT('%%', %s, '%%')", $kolomNilai, $kolomPola);
        }

        return sprintf("%s LIKE '%%' || %s || '%%'", $kolomNilai, $kolomPola);
    }

    /**
     * [1] Jurnal: tidak balance, `cabang_id` NULL, nomor duplikat per cabang,
     * baris tanpa referensi, dan baris tanpa `user_id`.
     */
    private function cekJurnal(): void
    {
        $kategori = 'jurnal';

        if (! $this->ada('jurnal_akuntansi', 'id', 'no_jurnal', 'cabang_id', 'debit', 'kredit', 'referensi_tipe', 'referensi_id', 'user_id')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi atau kolom wajib belum tersedia');

            return;
        }

        // a. Total debit beda dengan total kredit per (cabang, nomor jurnal).
        $takBalance = DB::table('jurnal_akuntansi')
            ->select('cabang_id', 'no_jurnal')
            ->selectRaw('SUM(debit) AS total_debit, SUM(kredit) AS total_kredit, SUM(debit) - SUM(kredit) AS selisih')
            ->groupBy('cabang_id', 'no_jurnal')
            ->havingRaw('SUM(debit) <> SUM(kredit)')
            ->orderByDesc('selisih')
            ->get();
        $this->catat(
            $kategori,
            'Jurnal tidak balance (total debit beda dengan total kredit)',
            self::KRITIS,
            $takBalance->count(),
            $takBalance->map(fn ($b): string => sprintf(
                '%s [cabang %s] — debit %s vs kredit %s — selisih %s',
                $b->no_jurnal,
                $b->cabang_id === null ? 'NULL' : $this->angka($b->cabang_id),
                $this->angka($b->total_debit, 2),
                $this->angka($b->total_kredit, 2),
                $this->angka($b->selisih, 2)
            ))->all(),
            'Telusuri baris jurnal pada nomor tersebut, lalu posting ulang sebagai jurnal koreksi. Jangan mengubah nominal pada jurnal lama yang sudah terpakai.'
        );

        // b. cabang_id NULL — jurnal ini tidak muncul di laporan scoped cabang.
        $tanpaCabang = DB::table('jurnal_akuntansi')
            ->select('id', 'no_jurnal', 'sumber')
            ->whereNull('cabang_id')
            ->orderByDesc('id')
            ->get();
        $this->catat(
            $kategori,
            'Jurnal tanpa cabang_id (tidak terlihat di laporan scoped cabang)',
            self::SEDANG,
            $tanpaCabang->count(),
            $tanpaCabang->map(fn ($b): string => sprintf(
                '#%s %s (sumber %s)',
                $this->angka($b->id),
                $b->no_jurnal,
                $b->sumber
            ))->all(),
            'Backfill cabang_id ke cabang default hanya untuk jurnal lama; jurnal baru wajib menerima cabang_id dari request.'
        );

        // c. Nomor jurnal duplikat dalam satu cabang.
        $duplikat = DB::table('jurnal_akuntansi')
            ->select('cabang_id', 'no_jurnal')
            ->selectRaw('COUNT(*) AS jml, MIN(id) AS id_min, MAX(id) AS id_maks')
            ->groupBy('cabang_id', 'no_jurnal')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('jml')
            ->get();
        $this->catat(
            $kategori,
            'Nomor jurnal duplikat dalam satu cabang',
            self::SEDANG,
            $duplikat->count(),
            $duplikat->map(fn ($b): string => sprintf(
                '%s [cabang %s] — %s baris (id %s sampai %s)',
                $b->no_jurnal,
                $b->cabang_id === null ? 'NULL' : $this->angka($b->cabang_id),
                $this->angka($b->jml),
                $this->angka($b->id_min),
                $this->angka($b->id_maks)
            ))->all(),
            'Nomor jurnal bentrok berarti laporan bisa salah rujukan. Pastikan pemanggil memakai generateNoJurnal yang menyertakan cabang_id.'
        );

        // d. Baris tanpa referensi sehingga tidak bisa drill-down ke dokumen sumber.
        $tanpaRef = DB::table('jurnal_akuntansi')
            ->select('id', 'no_jurnal', 'sumber', 'referensi_tipe', 'referensi_id')
            ->where(static function ($q): void {
                $q->whereNull('referensi_tipe')->orWhereNull('referensi_id');
            })
            ->orderByDesc('id')
            ->get();
        $this->catat(
            $kategori,
            'Jurnal tanpa referensi_tipe atau referensi_id (tidak bisa drill-down ke sumber)',
            self::SEDANG,
            $tanpaRef->count(),
            $tanpaRef->map(fn ($b): string => sprintf(
                '#%s %s (sumber %s) — tipe %s / id %s',
                $this->angka($b->id),
                $b->no_jurnal,
                $b->sumber,
                $b->referensi_tipe ?? 'NULL',
                $b->referensi_id === null ? 'NULL' : $this->angka($b->referensi_id)
            ))->all(),
            'Backfill referensi_tipe dan referensi_id dari nomor jurnal atau deskripsi satu per satu; setelah itu pastikan semua pemanggil JurnalService::post() mengirim referensi.'
        );

        // e. Baris tanpa user_id sehingga jejak auditnya kosong.
        $tanpaUser = DB::table('jurnal_akuntansi')
            ->select('id', 'no_jurnal', 'sumber')
            ->whereNull('user_id')
            ->orderByDesc('id')
            ->get();
        $this->catat(
            $kategori,
            'Jurnal tanpa user_id (jejak audit kosong)',
            self::SEDANG,
            $tanpaUser->count(),
            $tanpaUser->map(fn ($b): string => sprintf(
                '#%s %s (sumber %s)',
                $this->angka($b->id),
                $b->no_jurnal,
                $b->sumber
            ))->all(),
            'Backfill user_id dari audit_logs terdekat bila bisa; bila tidak, tandai sebagai jurnal sistem.'
        );
    }

    /**
     * [2] Transaksi berstatus final (`selesai` atau `lunas`) yang belum punya jurnal.
     *
     * Dicocokkan lewat dua jalur: (1) `referensi_tipe` = `Transaksi::class` dengan
     * `referensi_id`, dan (2) nomor transaksi muncul di `deskripsi` jurnal — jalur
     * kedua menangkap jurnal lama yang belum di-backfill referensinya.
     */
    private function cekTransaksi(): void
    {
        $kategori = 'transaksi';

        if (! $this->ada('transaksi', 'id', 'no_transaksi', 'status', 'total_akhir')) {
            $this->lewati($kategori, 'tabel transaksi atau kolom wajib belum tersedia');

            return;
        }

        if (! $this->ada('jurnal_akuntansi', 'referensi_tipe', 'referensi_id', 'deskripsi')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi belum tersedia sehingga pencocokan tidak bisa dilakukan');

            return;
        }

        $ekspresi = $this->ekspresiLike('jurnal_akuntansi.deskripsi', 'transaksi.no_transaksi');

        $tanpaJurnal = DB::table('transaksi')
            ->select('id', 'no_transaksi', 'status', 'total_akhir', 'sumber')
            ->whereIn('status', self::STATUS_TRANSAKSI_FINAL)
            ->whereNotExists(static function ($sub): void {
                $sub->selectRaw('1')->from('jurnal_akuntansi')
                    ->where('jurnal_akuntansi.referensi_tipe', Transaksi::class)
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'transaksi.id');
            })
            ->whereNotExists(static function ($sub) use ($ekspresi): void {
                $sub->selectRaw('1')->from('jurnal_akuntansi')
                    ->where('jurnal_akuntansi.referensi_tipe', Transaksi::class)
                    ->whereRaw($ekspresi);
            })
            ->orderByDesc('id')
            ->get();

        $this->catat(
            $kategori,
            'Transaksi final tanpa jurnal (pendapatan dan beban tidak tercatat)',
            self::KRITIS,
            $tanpaJurnal->count(),
            $tanpaJurnal->map(fn ($t): string => sprintf(
                '#%s %s — status %s — %s (sumber %s)',
                $this->angka($t->id),
                $t->no_transaksi,
                $t->status,
                $this->rupiah($t->total_akhir),
                $t->sumber
            ))->all(),
            'Post jurnal kas dan pendapatan (tambah HPP serta persediaan bila ada barang) lewat JurnalService::post() dengan referensi_tipe Transaksi::class agar idempoten.'
        );
    }

    /**
     * [3] Purchase order berstatus `diterima` tanpa jurnal penerimaan atau GRN.
     *
     * Jurnal PO ditemukan lewat `referensi_tipe` = `PurchaseOrder::class`, atau
     * lewat jurnal GRN (`Grn::class`) yang merujuk GRN milik PO tersebut.
     */
    private function cekPurchaseOrder(): void
    {
        $kategori = 'po';

        if (! $this->ada('purchase_order', 'id', 'no_po', 'status', 'total', 'metode_bayar')) {
            $this->lewati($kategori, 'tabel purchase_order atau kolom wajib belum tersedia');

            return;
        }

        if (! $this->ada('jurnal_akuntansi', 'referensi_tipe', 'referensi_id')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi belum tersedia sehingga pencocokan tidak bisa dilakukan');

            return;
        }

        $adaGrn = $this->ada('grn', 'id', 'po_id');

        $tanpaJurnal = DB::table('purchase_order')
            ->select('id', 'no_po', 'status', 'total', 'metode_bayar')
            ->where('status', 'diterima')
            ->whereNotExists(static function ($sub) use ($adaGrn): void {
                $sub->selectRaw('1')->from('jurnal_akuntansi')
                    ->where(static function ($w): void {
                        $w->where(static function ($a): void {
                            $a->where('jurnal_akuntansi.referensi_tipe', PurchaseOrder::class)
                                ->whereColumn('jurnal_akuntansi.referensi_id', 'purchase_order.id');
                        });
                    });
                if ($adaGrn) {
                    $sub->orWhere(static function ($w): void {
                        $w->where('jurnal_akuntansi.referensi_tipe', Grn::class)
                            ->whereIn('jurnal_akuntansi.referensi_id', static function ($g): void {
                                $g->select('id')->from('grn')
                                    ->whereColumn('grn.po_id', 'purchase_order.id');
                            });
                    });
                }
            })
            ->orderByDesc('id')
            ->get();

        $this->catat(
            $kategori,
            'Purchase order diterima tanpa jurnal (pembelian tidak masuk akun)',
            self::KRITIS,
            $tanpaJurnal->count(),
            $tanpaJurnal->map(fn ($p): string => sprintf(
                '#%s %s — total %s — bayar %s',
                $this->angka($p->id),
                $p->no_po,
                $this->rupiah($p->total),
                $p->metode_bayar
            ))->all(),
            'Finalisasi ulang lewat PurchaseOrderService atau GrnService (idempoten terhadap no_jurnal), jangan membuat jurnal manual supaya subledger Utang ikut terbentuk.'
        );
    }

    /**
     * [4] Payroll: periode `selesai` atau `dibayar` tanpa jurnal, dan slip yang
     * sudah disetujui atau dibayar tetapi `jurnal_id`-nya kosong.
     *
     * Slip berstatus `draft` sengaja tidak dihitung: pada fase draft `jurnal_id`
     * memang masih NULL secara normal dan baru diisi saat approve.
     */
    private function cekPayroll(): void
    {
        $kategori = 'payroll';

        if (! $this->ada('payroll_periode', 'id', 'periode', 'status')) {
            $this->lewati($kategori, 'tabel payroll_periode atau kolom wajib belum tersedia');

            return;
        }

        if (! $this->ada('jurnal_akuntansi', 'referensi_tipe', 'referensi_id')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi belum tersedia sehingga pencocokan tidak bisa dilakukan');

            return;
        }

        $periodeTanpaJurnal = DB::table('payroll_periode')
            ->select('id', 'periode', 'status')
            ->whereIn('status', ['selesai', 'dibayar'])
            ->whereNotExists(static function ($sub): void {
                $sub->selectRaw('1')->from('jurnal_akuntansi')
                    ->where('jurnal_akuntansi.referensi_tipe', PayrollPeriode::class)
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'payroll_periode.id');
            })
            ->orderByDesc('id')
            ->get();

        $this->catat(
            $kategori,
            'Periode payroll selesai atau dibayar tanpa jurnal (beban gaji tidak tercatat)',
            self::KRITIS,
            $periodeTanpaJurnal->count(),
            $periodeTanpaJurnal->map(fn ($p): string => sprintf(
                '#%s periode %s (status %s)',
                $this->angka($p->id),
                $p->periode,
                $p->status
            ))->all(),
            'Jalankan ulang PayrollService untuk periode tersebut; nomor jurnal wajib menyertakan periode (JRL-PR-{periode}) agar idempoten.'
        );

        if (! $this->ada('payroll_slip', 'id', 'payroll_periode_id', 'karyawan_id', 'jurnal_id', 'status')) {
            $this->lewati($kategori, 'tabel payroll_slip atau kolom wajib belum tersedia');

            return;
        }

        $slipTanpaJurnal = DB::table('payroll_slip as s')
            ->leftJoin('payroll_periode as p', 'p.id', '=', 's.payroll_periode_id')
            ->select('s.id', 's.karyawan_id', 's.status', 'p.periode')
            ->whereNull('s.jurnal_id')
            ->where(static function ($q): void {
                $q->whereNull('s.status')->orWhere('s.status', '!=', 'draft');
            })
            ->orderByDesc('s.id')
            ->get();

        $this->catat(
            $kategori,
            'Payroll slip approved atau dibayar tanpa jurnal_id (slip nyasar)',
            self::KRITIS,
            $slipTanpaJurnal->count(),
            $slipTanpaJurnal->map(fn ($s): string => sprintf(
                'slip #%s karyawan %s — periode %s — status %s',
                $this->angka($s->id),
                $this->angka($s->karyawan_id),
                $s->periode ?? '—',
                $s->status ?? 'NULL'
            ))->all(),
            'jurnal_id diisi saat approve. Slip seperti ini biasanya hasil perubahan status manual: kembalikan ke draft lalu approve ulang.'
        );
    }

    /**
     * [5] Opname: jumlah jurnal distinct per referensi. Lebih dari satu jurnal
     * untuk satu `stok_opname` berarti indikasi overposting adjustment.
     */
    private function cekOpname(): void
    {
        $kategori = 'opname';

        if (! $this->ada('jurnal_akuntansi', 'referensi_tipe', 'referensi_id', 'no_jurnal')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi atau kolom wajib belum tersedia');

            return;
        }

        $ganda = DB::table('jurnal_akuntansi')
            ->select('referensi_id')
            ->selectRaw('COUNT(DISTINCT no_jurnal) AS jml_jurnal, MIN(no_jurnal) AS contoh')
            ->where('referensi_tipe', StokOpname::class)
            ->whereNotNull('referensi_id')
            ->groupBy('referensi_id')
            ->havingRaw('COUNT(DISTINCT no_jurnal) > 1')
            ->orderByDesc('jml_jurnal')
            ->get();

        $petaNomor = [];
        if ($ganda->isNotEmpty() && $this->ada('stok_opname', 'id', 'no_opname')) {
            $petaNomor = DB::table('stok_opname')
                ->whereIn('id', $ganda->pluck('referensi_id')->all())
                ->pluck('no_opname', 'id')
                ->all();
        }

        $this->catat(
            $kategori,
            'Opname dengan lebih dari satu jurnal (indikasi overposting adjustment)',
            self::SEDANG,
            $ganda->count(),
            $ganda->map(fn ($g): string => sprintf(
                'opname #%s%s — %s jurnal distinct (mis. %s)',
                $this->angka($g->referensi_id),
                isset($petaNomor[$g->referensi_id]) ? ' '.$petaNomor[$g->referensi_id] : '',
                $this->angka($g->jml_jurnal),
                $g->contoh
            ))->all(),
            'Adjustment opname hanya boleh satu jurnal per siklus approval. Jurnal kedua dibatalkan lewat jurnal pembalik, bukan dihapus.'
        );
    }

    /**
     * [6] Piutang dan Utang: `cabang_id` NULL, status tidak sinkron dengan
     * `jumlah_dibayar`, overpayment, dan Utang yang menunjuk referensi hilang.
     */
    private function cekPiutangUtang(): void
    {
        $kategori = 'piutang';

        // referensi_tipe pada tabel `utang`: nilai lama ('pembelian' atau 'komisi')
        // dan nilai baru (FQCN model) keduanya dipakai di kode yang berjalan.
        $petaTabel = [
            PurchaseOrder::class => 'purchase_order',
            'pembelian' => 'purchase_order',
            Grn::class => 'grn',
            'grn' => 'grn',
            Komisi::class => 'komisi',
            'komisi' => 'komisi',
        ];

        foreach (['piutang' => 'no_piutang', 'utang' => 'no_utang'] as $tabel => $kolomNomor) {
            if (! $this->ada($tabel, 'id', $kolomNomor, 'status', 'jumlah', 'jumlah_dibayar')) {
                $this->lewati($kategori, "tabel {$tabel} atau kolom wajib belum tersedia");

                continue;
            }

            // a. cabang_id NULL (kolom ditambahkan lewat migrasi terpisah).
            if (! Schema::hasColumn($tabel, 'cabang_id')) {
                $this->lewati($kategori, "kolom {$tabel}.cabang_id belum ada di skema (backfill belum dijalankan)");
            } else {
                $tanpaCabang = DB::table($tabel)
                    ->select('id', $kolomNomor, 'status', 'jumlah', 'jumlah_dibayar')
                    ->whereNull('cabang_id')
                    ->orderByDesc('id')
                    ->get();
                $this->catat(
                    $kategori,
                    strtoupper($tabel).' tanpa cabang_id (tidak terlihat di widget scoped cabang)',
                    self::SEDANG,
                    $tanpaCabang->count(),
                    $tanpaCabang->map(fn ($b): string => sprintf(
                        '#%s %s — status %s — sisa %s',
                        $this->angka($b->id),
                        $b->{$kolomNomor},
                        $b->status,
                        $this->rupiah((float) $b->jumlah - (float) $b->jumlah_dibayar)
                    ))->all(),
                    'Jalankan ulang migrasi backfill cabang_id piutang/utang (idempoten), atau isi manual bila asal cabangnya diketahui.'
                );
            }

            // b. Status tidak sinkron dengan jumlah_dibayar.
            $takSinkron = DB::table($tabel)
                ->select('id', $kolomNomor, 'status', 'jumlah', 'jumlah_dibayar')
                ->where(static function ($q): void {
                    $q->where(static function ($a): void {
                        $a->where('status', 'lunas')
                            ->whereRaw('jumlah_dibayar < (jumlah - 0.01)');
                    })->orWhere(static function ($b): void {
                        $b->where('status', 'sebagian')
                            ->whereRaw('jumlah_dibayar <= 0');
                    })->orWhere(static function ($c): void {
                        $c->where('status', 'belum_lunas')
                            ->whereRaw('jumlah > 0')
                            ->whereRaw('jumlah_dibayar >= (jumlah - 0.01)');
                    });
                })
                ->orderByDesc('id')
                ->get();
            $this->catat(
                $kategori,
                strtoupper($tabel).' status tidak sinkron vs jumlah_dibayar',
                self::KRITIS,
                $takSinkron->count(),
                $takSinkron->map(fn ($b): string => sprintf(
                    '#%s %s — status %s — dibayar %s dari %s',
                    $this->angka($b->id),
                    $b->{$kolomNomor},
                    $b->status,
                    $this->rupiah($b->jumlah_dibayar),
                    $this->rupiah($b->jumlah)
                ))->all(),
                'Samakan status dengan rumus: lunas bila jumlah_dibayar sudah mencapai jumlah, sebagian bila di antaranya, belum_lunas bila nol. Perbaiki lewat alur pembayaran, bukan mengubah status langsung.'
            );

            // c. Overpayment: jumlah_dibayar melebihi nilai tagihan.
            $lebih = DB::table($tabel)
                ->select('id', $kolomNomor, 'status', 'jumlah', 'jumlah_dibayar')
                ->whereRaw('jumlah_dibayar > jumlah')
                ->orderByDesc('id')
                ->get();
            $this->catat(
                $kategori,
                strtoupper($tabel).' overpayment (jumlah_dibayar melebihi jumlah)',
                self::KRITIS,
                $lebih->count(),
                $lebih->map(fn ($b): string => sprintf(
                    '#%s %s — dibayar %s vs tagihan %s — kelebihan %s',
                    $this->angka($b->id),
                    $b->{$kolomNomor},
                    $this->rupiah($b->jumlah_dibayar),
                    $this->rupiah($b->jumlah),
                    $this->rupiah((float) $b->jumlah_dibayar - (float) $b->jumlah)
                ))->all(),
                'Cek dulu kemungkinan pembayaran ganda. Kembalikan kelebihan lewat jurnal koreksi plus penyesuaian piutang/utang, bukan mengurangi jumlah_dibayar langsung.'
            );

            // d. Utang orphan: referensi_id kosong atau menunjuk baris yang hilang.
            if ($tabel === 'utang' && $this->ada('utang', 'referensi_tipe', 'referensi_id')) {
                $this->catatUtangOrphan($kategori, $petaTabel);
            }
        }
    }

    /**
     * [6d] Deteksi Utang yang menunjuk referensi tidak ada atau tipe tidak dikenal.
     *
     * Dicek per baris di PHP (bukan SQL) karena `referensi_tipe` boleh berisi
     * FQCN model maupun nama legacy, sehingga pemetaan tabelnya tidak bisa
     * ditulis sebagai satu `whereIn` sederhana.
     *
     * @param  array<string, string>  $petaTabel
     */
    private function catatUtangOrphan(string $kategori, array $petaTabel): void
    {
        $baris = DB::table('utang')
            ->select('id', 'no_utang', 'referensi_tipe', 'referensi_id', 'status', 'jumlah', 'jumlah_dibayar')
            ->get();

        // Kumpulkan id per tabel tujuan, lalu satu query whereIn per tabel.
        $idPerTabel = [];
        foreach ($baris as $b) {
            $tabelTujuan = $this->tabelTujuan($b->referensi_tipe, $petaTabel);
            if ($tabelTujuan !== null) {
                $idPerTabel[$tabelTujuan][] = (int) $b->referensi_id;
            }
        }

        $idTersedia = [];
        foreach ($idPerTabel as $tabelTujuan => $ids) {
            $idTersedia[$tabelTujuan] = DB::table($tabelTujuan)
                ->whereIn('id', array_values(array_unique($ids)))
                ->pluck('id')
                ->map(static fn ($v): int => (int) $v)
                ->all();
        }

        $contoh = [];
        foreach ($baris as $b) {
            $tipe = (string) $b->referensi_tipe;
            $id = (int) $b->referensi_id;

            if ($id <= 0) {
                $contoh[] = sprintf(
                    '#%s %s — tipe %s — referensi_id kosong (0)',
                    $this->angka($b->id),
                    $b->no_utang,
                    $tipe === '' ? 'NULL' : $tipe
                );

                continue;
            }

            $tabelTujuan = $this->tabelTujuan($b->referensi_tipe, $petaTabel);
            if ($tabelTujuan === null) {
                $contoh[] = sprintf(
                    '#%s %s — tipe referensi %s tidak dikenal',
                    $this->angka($b->id),
                    $b->no_utang,
                    $tipe === '' ? 'NULL' : $tipe
                );

                continue;
            }

            if (! in_array($id, $idTersedia[$tabelTujuan] ?? [], true)) {
                $contoh[] = sprintf(
                    '#%s %s — %s #%s tidak ada (status %s, sisa %s)',
                    $this->angka($b->id),
                    $b->no_utang,
                    $tabelTujuan,
                    $this->angka($id),
                    $b->status,
                    $this->rupiah((float) $b->jumlah - (float) $b->jumlah_dibayar)
                );
            }
        }

        $this->catat(
            $kategori,
            'Utang orphan (referensi_id kosong atau menunjuk baris yang tidak ada)',
            self::KRITIS,
            count($contoh),
            $contoh,
            'Batalkan lewat jurnal pembalik lalu void dokumennya, atau sambungkan ke dokumen yang benar. Jangan menunjuk baris lain hanya agar cocok.'
        );
    }

    /**
     * Petakan nilai `referensi_tipe` milik Utang ke nama tabel tujuan.
     *
     * @param  array<string, string>  $petaTabel
     */
    private function tabelTujuan(mixed $referensiTipe, array $petaTabel): ?string
    {
        $tipe = (string) $referensiTipe;
        $tabel = $petaTabel[$tipe] ?? $petaTabel[strtolower($tipe)] ?? null;

        return ($tabel !== null && Schema::hasTable($tabel)) ? $tabel : null;
    }

    /**
     * [7] Stok trail: `stok_log` tanpa pasangan `stock_mutation_log`, saldo
     * `stok_items` negatif, dan selisih total `perubahan` versus `delta`.
     *
     * Catatan keputusan: `stok_log` dan `stock_mutation_log` sengaja tidak berbagi
     * kunci surrogate, jadi pencocokan memakai kombinasi gudang + produk +
     * referensi + nilai, dan yang dibandingkan adalah JUMLAH BARIS per kombinasi
     * (bukan baris per baris) supaya baris kembar yang sah tidak salah dibaca.
     */
    private function cekStokTrail(): void
    {
        $kategori = 'stok';

        if (! $this->ada('stok_log', 'id', 'gudang_id', 'produk_id', 'referensi_tipe', 'referensi_id', 'perubahan')) {
            $this->lewati($kategori, 'tabel stok_log atau kolom wajib belum tersedia');

            return;
        }

        if (! $this->ada('stock_mutation_log', 'id', 'gudang_id', 'produk_id', 'referensi_tipe', 'referensi_id', 'delta')) {
            $this->lewati($kategori, 'tabel stock_mutation_log belum tersedia sehingga pembandingan tidak bisa dilakukan');
        } else {
            $grupStokLog = $this->agregatTrail('stok_log');
            $grupMutasi = $this->agregatTrail('stock_mutation_log');

            $tidakBerpasangan = [];

            foreach ($grupStokLog as $kunci => $jumlah) {
                if (! array_key_exists($kunci, $grupMutasi)) {
                    $tidakBerpasangan[] = $this->deskripsiGrup($kunci, $jumlah, 0);
                } elseif ($grupMutasi[$kunci] !== $jumlah) {
                    $tidakBerpasangan[] = $this->deskripsiGrup($kunci, $jumlah, $grupMutasi[$kunci]);
                }
            }

            foreach ($grupMutasi as $kunci => $jumlah) {
                if (! array_key_exists($kunci, $grupStokLog)) {
                    $tidakBerpasangan[] = $this->deskripsiGrup($kunci, 0, $jumlah);
                }
            }

            $this->catat(
                $kategori,
                'Jejak stok tidak berpasangan (stok_log vs stock_mutation_log)',
                self::KRITIS,
                count($tidakBerpasangan),
                $tidakBerpasangan,
                'Tentukan sisi yang benar lalu rekonstruksi sisi yang hilang lewat StokDeductionService. Kedua tabel harus ditulis dalam satu transaksi DB yang sama.'
            );

            // Total perubahan versus total delta sebagai pengejaga invariant global.
            $totalPerubahan = (float) DB::table('stok_log')->sum('perubahan');
            $totalDelta = (float) DB::table('stock_mutation_log')->sum('delta');
            $beda = round($totalPerubahan - $totalDelta, 4);
            $selisihAda = abs($beda) >= 0.5;

            $this->catat(
                $kategori,
                'Selisih total mutasi (total stok_log perubahan vs total stock_mutation_log delta)',
                self::KRITIS,
                $selisihAda ? 1 : 0,
                $selisihAda ? [sprintf(
                    'total stok_log %s vs total stock_mutation_log %s — selisih %s',
                    $this->angka($totalPerubahan),
                    $this->angka($totalDelta),
                    $this->angka($beda)
                )] : [],
                'Selisih ini yang membuat saldo stok_items tidak bisa ditelusuri. Rekonstruksi dari sumber: stock_mutation_log adalah pergerakan, stok_items adalah saldo berjalan.'
            );
        }

        if (! $this->ada('stok_items', 'id', 'produk_id', 'gudang_id', 'jumlah')) {
            $this->lewati($kategori, 'tabel stok_items belum tersedia sehingga saldo negatif tidak bisa dicek');
        } else {
            $negatif = DB::table('stok_items')
                ->select('id', 'produk_id', 'gudang_id', 'jumlah')
                ->where('jumlah', '<', 0)
                ->orderBy('jumlah')
                ->get();
            $this->catat(
                $kategori,
                'Saldo stok_items negatif (produk melebihi stok fisik)',
                self::KRITIS,
                $negatif->count(),
                $negatif->map(fn ($s): string => sprintf(
                    '#%s produk %s gudang %s — jumlah %s',
                    $this->angka($s->id),
                    $this->angka($s->produk_id),
                    $this->angka($s->gudang_id),
                    $this->angka($s->jumlah)
                ))->all(),
                'Tahan penjualan dan opname untuk kombinasi itu, lalu hitung ulang saldo dari jejak mutasi dan koreksi lewat penyesuaian opname (bukan mengubah stok_items langsung).'
            );
        }
    }

    /**
     * Agregasi jejak stok per kunci (gudang, produk, referensi) menjadi jumlah baris.
     *
     * Dipakai untuk membandingkan `stok_log` dengan `stock_mutation_log` pada
     * level kombinasi, sehingga seluruh baris log tidak perlu dimuat ke memori.
     *
     * @return array<string, int>
     */
    private function agregatTrail(string $tabel): array
    {
        $hasil = [];

        $baris = DB::table($tabel)
            ->selectRaw('gudang_id, produk_id, COALESCE(referensi_tipe, \'\') AS rt, COALESCE(referensi_id, 0) AS rid, COUNT(*) AS c')
            ->groupByRaw('gudang_id, produk_id, COALESCE(referensi_tipe, \'\'), COALESCE(referensi_id, 0)')
            ->get();

        foreach ($baris as $b) {
            $hasil[(int) $b->gudang_id.'|'.(int) $b->produk_id.'|'.(string) $b->rt.'|'.(int) $b->rid] = (int) $b->c;
        }

        return $hasil;
    }

    /**
     * Uraikan satu kelompok jejak stok yang tidak berpasangan.
     */
    private function deskripsiGrup(string $kunci, int $jumlahStokLog, int $jumlahMutasi): string
    {
        $bagian = explode('|', $kunci);
        $referensi = ($bagian[3] === '0')
            ? 'tanpa referensi'
            : $bagian[2].' #'.$this->angka($bagian[3]);

        return sprintf(
            'gudang %s / produk %s / %s — stok_log %s baris vs stock_mutation_log %s baris',
            $this->angka($bagian[0]),
            $this->angka($bagian[1]),
            $referensi,
            $this->angka($jumlahStokLog),
            $this->angka($jumlahMutasi)
        );
    }

    /**
     * [8] Audit: `audit_logs` tanpa `cabang_id` (kolom bisa belum ada),
     * `audit_logs` tanpa snapshot sebelum dan sesudah, serta `activity_log`
     * tanpa `attribute_changes`.
     */
    private function cekAudit(): void
    {
        $kategori = 'audit';

        if (! Schema::hasTable('audit_logs')) {
            $this->lewati($kategori, 'tabel audit_logs belum tersedia');

            return;
        }

        if (! Schema::hasColumn('audit_logs', 'cabang_id')) {
            $this->lewati($kategori, 'kolom audit_logs.cabang_id belum ada di skema sehingga tidak bisa dilaporkan');
        } else {
            $tanpaCabang = DB::table('audit_logs')
                ->select('id', 'entitas', 'entitas_id', 'aksi')
                ->whereNull('cabang_id')
                ->orderByDesc('id')
                ->get();
            $this->catat(
                $kategori,
                'audit_logs tanpa cabang_id (jejak audit tidak bisa disaring per cabang)',
                self::SEDANG,
                $tanpaCabang->count(),
                $tanpaCabang->map(fn ($b): string => sprintf(
                    '#%s %s #%s aksi %s',
                    $this->angka($b->id),
                    $b->entitas,
                    $this->angka($b->entitas_id),
                    $b->aksi
                ))->all(),
                'Backfill cabang_id dari user yang login saat audit ditulis. Audit lama cukup ditandai tidak ter-backfill.'
            );
        }

        if (! $this->ada('audit_logs', 'id', 'entitas', 'entitas_id', 'aksi', 'sebelum', 'sesudah')) {
            $this->lewati($kategori, 'kolom snapshot sebelum dan sesudah pada audit_logs belum tersedia');
        } else {
            $tanpaSnapshot = DB::table('audit_logs')
                ->select('id', 'entitas', 'entitas_id', 'aksi')
                ->whereNull('sebelum')
                ->whereNull('sesudah')
                ->orderByDesc('id')
                ->get();
            $this->catat(
                $kategori,
                'audit_logs tanpa snapshot sebelum dan sesudah (tidak bisa di-replay)',
                self::RINGAN,
                $tanpaSnapshot->count(),
                $tanpaSnapshot->map(fn ($b): string => sprintf(
                    '#%s %s #%s aksi %s',
                    $this->angka($b->id),
                    $b->entitas,
                    $this->angka($b->entitas_id),
                    $b->aksi
                ))->all(),
                'Isi snapshot minimal untuk entitas yang bisa berubah. Audit tanpa before dan after praktis tidak berguna saat investigasi.'
            );
        }

        if (! $this->ada('activity_log', 'id', 'description', 'attribute_changes')) {
            $this->lewati($kategori, 'tabel activity_log atau kolom attribute_changes belum tersedia');
        } else {
            $tanpaChanges = DB::table('activity_log')
                ->select('id', 'log_name', 'description', 'event')
                ->whereNull('attribute_changes')
                ->orderByDesc('id')
                ->get();
            $this->catat(
                $kategori,
                'activity_log tanpa attribute_changes',
                self::RINGAN,
                $tanpaChanges->count(),
                $tanpaChanges->map(fn ($b): string => sprintf(
                    '#%s [%s] %s%s',
                    $this->angka($b->id),
                    $b->log_name ?? '-',
                    mb_strimwidth((string) $b->description, 0, 60, '...'),
                    $b->event ? " ({$b->event})" : ''
                ))->all(),
                'Aktifkan atribut yang dilacak per model agar nilai yang berubah ikut tercatat.'
            );
        }
    }

    /**
     * [9] Bandingkan berkas di `database/migrations` dengan tabel `migrations`,
     * lalu laporkan migrasi yang belum dijalankan.
     */
    private function cekMigrasiTertunda(): void
    {
        $kategori = 'migrasi';

        $berkas = glob(database_path('migrations').'/*.php') ?: [];

        if ($berkas === []) {
            $this->lewati($kategori, 'direktori database/migrations tidak dapat dibaca');

            return;
        }

        if (! $this->ada('migrations', 'migration')) {
            $this->catat(
                $kategori,
                'Tabel migrations belum tersedia (database belum termigrasi)',
                self::KRITIS,
                1,
                [$this->angka(count($berkas)).' berkas migrasi menunggu di database/migrations'],
                'Jalankan `php artisan migrate` di lingkungan yang benar, dan backup dulu karena sebagian migrasi berisi backfill data.'
            );

            return;
        }

        $namaBerkas = array_map(static fn (string $f): string => basename($f, '.php'), $berkas);
        sort($namaBerkas);

        $tertunda = array_values(array_diff($namaBerkas, DB::table('migrations')->pluck('migration')->all()));

        $this->catat(
            $kategori,
            'Migrasi belum dijalankan (selisih berkas vs tabel migrations)',
            self::SEDANG,
            count($tertunda),
            array_map(static fn (string $n): string => $n.'.php', array_slice($tertunda, 0, $this->limit)),
            'Tinjau `php artisan migrate:status` lalu jalankan lebih dulu di staging. Sebagian migrasi berisi backfill data sehingga wajib ada backup.'
        );
    }

    /**
     * Cetak satu blok kategori: tabel temuan (atau "tidak ada anomali"), tabel
     * usulan perbaikan, dan catatan pemeriksaan yang dilewati.
     */
    private function cetakKategori(string $kategori): void
    {
        $temuan = $this->hasil[$kategori] ?? [];

        $this->line('<comment>== '.self::KATEGORI[$kategori].' (--kategori='.$kategori.') ==</comment>');

        if ($temuan === []) {
            $this->line('  ✓ tidak ada anomali');
        } else {
            $baris = [];
            $usulan = [];
            foreach ($temuan as $t) {
                $baris[] = [
                    $t['judul'],
                    $kategori,
                    $this->angka($t['jumlah']),
                    $t['severity'],
                    $this->gabungContoh($t['contoh'], $t['jumlah']),
                ];
                $usulan[] = [$t['judul'], $t['usulan']];
            }

            $this->table(['JUDUL', 'KATEGORI', 'JUMLAH', 'SEVERITY', 'CONTOH-BARIS + DSM'], $baris);
            $this->table(['PEMERIKSAAN', 'USULAN PERBAIKAN (dry-run, belum dieksekusi)'], $usulan);
        }

        foreach ($this->lewati[$kategori] ?? [] as $alasan) {
            $this->line('  [dilewati] '.$alasan);
        }

        $this->newLine();
    }

    /**
     * Gabungkan contoh baris menjadi satu sel, plus sisa jumlah yang tidak
     * ditampilkan karena `--limit`.
     *
     * @param  array<int, string>  $contoh
     */
    private function gabungContoh(array $contoh, int $jumlah): string
    {
        if ($contoh === []) {
            return '-';
        }

        $teks = implode(' | ', $contoh);

        if ($jumlah > count($contoh)) {
            $teks .= sprintf(' ... (+%s baris lain)', $this->angka($jumlah - count($contoh)));
        }

        return $teks;
    }

    /**
     * Cetak ringkasan total anomali per kategori beserta severity terburuk.
     *
     * @param  array<int, string>  $diminta
     * @return bool true bila ada kategori KRITIS, sehingga pemanggil mengembalikan exit code 1.
     */
    private function cetakRingkasan(array $diminta): bool
    {
        $baris = [];
        $total = 0;
        $adaKritis = false;

        foreach ($diminta as $kategori) {
            $jumlah = 0;
            $terburuk = null;

            foreach ($this->hasil[$kategori] ?? [] as $t) {
                $jumlah += $t['jumlah'];

                if ($terburuk === null || (self::BOBOT[$t['severity']] ?? 0) > (self::BOBOT[$terburuk] ?? 0)) {
                    $terburuk = $t['severity'];
                }
            }

            $adaKritis = $adaKritis || $terburuk === self::KRITIS;
            $total += $jumlah;
            $baris[] = [self::KATEGORI[$kategori], $kategori, $this->angka($jumlah), $terburuk ?? '-'];
        }

        $baris[] = ['TOTAL KESELURUHAN', '', $this->angka($total), $adaKritis ? self::KRITIS : 'BERSIH'];

        $this->line('<comment>== RINGKASAN ==</comment>');
        $this->table(['KATEGORI', 'KUNCI', 'TOTAL ANOMALI', 'SEVERITY TERBURUK'], $baris);
        $this->newLine();

        if ($adaKritis) {
            $this->line('<error>ADA anomali KRITIS — exit code 1 (bisa dipakai sebagai gate CI).</error>');
        } else {
            $this->line('<info>Tidak ada anomali KRITIS — exit code 0.</info>');
        }

        $this->line('INFO: READ-ONLY — perintah ini tidak menjalankan INSERT, UPDATE, DELETE, migrate, maupun seed.');

        return $adaKritis;
    }
}
