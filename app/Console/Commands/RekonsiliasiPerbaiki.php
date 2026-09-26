<?php

namespace App\Console\Commands;

use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokOpname;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * [B-13] Rekonsiliasi data live — SAAT INI masih DRY-RUN KECUALI dua kategori
 * yang benar-benar aman.
 *
 * Pasangan perintah dengan `ute:rekonsiliasi` (READ-ONLY). Perintah itu
 * melaporkan anomali; perintah ini menampilkan RENCANA perbaikannya dan
 * — hanya bila `--jalankan` atau `--apply` diberikan — menulis dua kategori
 * yang tidak butuh keputusan akuntansi:
 *
 *  a) `piutang-utang-cabang`     → ISI cabang_id NULL dgn cabang default
 *                                  (logika identik migrasi
 *                                  2026_09_25_000100_backfill_cabang_id_piutang_utang)
 *  b) `utang-orphan`             → TANDAI di `keterangan` baris yang
 *                                  `referensi_id = 0` (tidak mungkin menunjuk
 *                                  dokumen mana pun)
 *  c) `po-terima-tanpa-jurnal`   → LAPORAN + usulan jurnal. TIDAK diposting.
 *  d) `payroll-tanpa-jurnal`     → LAPORAN + usulan jurnal. TIDAK diposting.
 *  e) `opname-jurnal-ganda`      → LAPORAN rinci. TIDAK dihapus/diubah.
 *
 * PERATUAN MUTLAK:
 * - Tanpa `--jalankan`/`--apply`, perintah ini TIDAK menulis apa pun.
 * - Tidak pernah `delete`, tidak pernah mengubah `no_jurnal` yang sudah ada.
 * - Tidak pernah mengarang referensi: `referensi_id` yang menunjuk baris yang
 *   hilang hanya dilaporkan, tidak ditunjuk ke dokumen lain.
 * - Jurnal TIDAK pernah dibuat otomatis. Data historis (PO/payroll/opname)
 *   butuh verifikasi manual pemilik — membuat jurnal dari data lama tanpa
 *   persetujuan berisiko double-posting, jadi perintah ini hanya menulis
 *   niat ke layar.
 *
 * DEVIASI dari briefs awal (dinyatakan terbuka, bukan disembunyikan):
 * brief menyebut "set referensi_id = NULL" untuk Utang orphan. Kolom
 * `utang.referensi_id` adalah `bigint unsigned NOT NULL` baik di migrasi
 * (`2026_09_16_000026`) maupun di MySQL produksi, jadi NULL akan ditolak
 * database. Karena perintah ini tidak boleh membuat migrasi baru, kategori
 * (b) menuliskan penanda di `keterangan` (kolom `catatan` milik tabel `utang`)
 * dan MEMBUKAKAN `referensi_id` apa adanya. Meng-null-kan kolomnya butuh
 * migrasi terpisah — lihat pesan yang dicetak perintah ini.
 *
 * Keamanan penulisan:
 * - Rencana ("akan mengubah N baris") dicetak DULUAN, sebelum ada query write.
 * - `--jalankan` tanpa `--yes` → konfirmasi interaktif. Bila konfirmasi tidak
 *   bisa dibaca (cron/CI/`--no-interaction`/STDIN bukan TTY/di bawah test)
 *   penulisan DIBATALKAN, bukan diasumsikan setuju.
 * - Semua penulisan dibungkus satu transaksi DB.
 *
 * IDEMPOTEN: kategori (a) hanya menyentuh `cabang_id IS NULL`, kategori (b)
 * hanya menambahkan penanda yang belum ada. Dijalankan berkali-kali →
 * jumlah baris berubah = 0 pada putaran kedua.
 *
 * Exit code:
 * - `0` tidak ada temuan, atau semua temuan sudah aman diperbaiki dan tidak
 *   ada kategori yang butuh verifikasi manual.
 * - `1` ada kategori manual dengan temuan, kategori tidak dikenal, atau
 *   penulisan dibatalkan karena konfirmasi tidak dijawab.
 *
 * Semua angka bergaya Indonesia (ADR 0011).
 */
class RekonsiliasiPerbaiki extends Command
{
    protected $signature = 'ute:rekonsiliasi-perbaiki
        {--kategori= : Batasi ke kategori tertentu (pisahkan koma), mis. utang-orphan}
        {--jalankan : BENAR-BENAR menulis (tanpa flag ini = dry-run murni)}
        {--apply : Alias dari --jalankan}
        {--yes : Lewati konfirmasi interaktif}
        {--limit=25 : Jumlah contoh baris per kategori}';

    protected $description = 'Rekonsiliasi data: dry-run default, rencano perbaikan + hanya isi cabang_id/flag Utang orphan bila --jalankan';

    /** Judul blok laporan per kunci kategori. */
    private const KATEGORI = [
        'piutang-utang-cabang' => 'PIUTANG / UTANG — CABANG_ID NULL',
        'utang-orphan' => 'UTANG ORPHAN — REFERENSI TIDAK SAH',
        'po-terima-tanpa-jurnal' => 'PO DITERIMA TANPA JURNAL',
        'payroll-tanpa-jurnal' => 'PAYROLL SELESAI TANPA JURNAL',
        'opname-jurnal-ganda' => 'OPNAME DENGAN LEBIH DARI SATU JURNAL',
    ];

    /** Kategori yang boleh ditulis (tidak butuh keputusan akuntansi). */
    private const BISA_DIPERBAIKI = ['piutang-utang-cabang', 'utang-orphan'];

    /**
     * Penanda yang ditulis ke `utang.keterangan`.
     *
     * Sengaja tanpa `_` maupun `%` supaya aman dipakai sebagai pola `LIKE`
     * di MySQL maupun SQLite tanpa escaping.
     */
    private const PENANDA = '[REKONSILIASI ute:rekonsiliasi-perbaiki]';

    /**
     * Analisa per kategori.
     *
     * @var array<string, array{perubahan:int, temuan:int, rincian:array<int,string>, header:array<int,string>, tabel:array<int,array<int,string>>, usulan:array<int,array<int,string>>, lewati:array<int,string>}>
     */
    private array $hasil = [];

    /** Kunci kategori yang dijalankan pada pemanggilan ini. */
    private array $diminta = [];

    /** Jumlah contoh baris per kategori (`--limit`). */
    private int $limit = 25;

    /** Cache id cabang default (id terkecil), null = belum ada cabang. */
    private ?int $defaultCabangId = null;

    public function handle(): int
    {
        $this->limit = max(1, (int) $this->option('limit'));

        $diminta = $this->parseKategori();
        if ($diminta === null) {
            $this->error('Kategori tidak dikenal. Pilihan yang tersedia: '.implode(', ', array_keys(self::KATEGORI)));

            return self::FAILURE;
        }

        $this->diminta = $diminta;

        $jalankan = (bool) $this->option('jalankan') || (bool) $this->option('apply');

        $this->line($jalankan
            ? '<comment>REKONSILIASI PERBAIKI — MODE JALANKAN (hanya 2 kategori yang ditulis; jurnal tetap TIDAK diposting)</comment>'
            : '<info>REKONSILIASI PERBAIKI — DRY-RUN (tidak ada satu baris pun yang diubah)</info>');
        $this->line(sprintf(
            'Koneksi: %s | Kategori aktif: %s | Contoh per kategori: %s | Mode: %s',
            (string) config('database.default'),
            implode(', ', $this->diminta),
            $this->angka($this->limit),
            $jalankan ? 'JALANKAN' : 'DRY-RUN'
        ));
        $this->newLine();

        // 1. Analisa dulu — seluruhnya query SELECT, termasuk saat --jalankan.
        foreach ($this->diminta as $kategori) {
            match ($kategori) {
                'piutang-utang-cabang' => $this->analisaCabang(),
                'utang-orphan' => $this->analisaUtangOrphan(),
                'po-terima-tanpa-jurnal' => $this->analisaPo(),
                'payroll-tanpa-jurnal' => $this->analisaPayroll(),
                'opname-jurnal-ganda' => $this->analisaOpname(),
            };
        }

        // 2. Rencana total harus tampil sebelum ada satu pun penulisan.
        $totalPerubahan = $this->totalPerubahan();
        $this->cetakRencana($totalPerubahan, $jalankan);

        // 3. Tulis — hanya bila diminta, hanya setelah konfirmasi, satu transaksi.
        $ditulis = 0;
        if ($jalankan && $totalPerubahan > 0) {
            if (! $this->bolehTulis($totalPerubahan)) {
                $this->error('Penulisan DIBATALKAN — tidak ada baris yang diubah.');

                $this->categoriaLaporan();
                $this->cetakRingkasan($ditulis, dibatalkan: true);

                return self::FAILURE;
            }

            $ditulis = DB::transaction(fn (): int => $this->terapkan());
        }

        $this->categoriaLaporan();
        $this->cetakRingkasan($ditulis, dibatalkan: false);

        return $this->adaTemuanManual() ? self::FAILURE : self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Analisa (READ-ONLY) — semua kategori
    // ------------------------------------------------------------------

    /**
     * (a) `piutang`/`utang` dengan `cabang_id` NULL.
     *
     * Cabang default = `cabang` dengan id terkecil, persis seperti migrasi
     * `2026_09_25_000100`. Bila belum ada cabang sama sekali, baris dibiarkan
     * NULL (skip) — bukan diisi nilai karangan.
     */
    private function analisaCabang(): void
    {
        $kategori = 'piutang-utang-cabang';
        $this->mulai($kategori);

        if (! $this->ada('cabang', 'id')) {
            $this->lewati($kategori, 'tabel cabang belum tersedia sehingga cabang default tidak bisa ditentukan');

            return;
        }

        $this->defaultCabangId = $this->cabangDefault();
        if ($this->defaultCabangId === null) {
            $this->lewati($kategori, 'belum ada cabang sama sekali — seluruh baris cabang_id NULL dibiarkan NULL (tidak ada nilai karangan)');

            return;
        }

        $rincian = [];
        $perubahan = 0;

        foreach (['piutang' => 'no_piutang', 'utang' => 'no_utang'] as $tabel => $kolomNomor) {
            if (! $this->ada($tabel, 'id', $kolomNomor, 'status', 'jumlah', 'jumlah_dibayar')) {
                $this->lewati($kategori, "tabel {$tabel} atau kolom wajib belum tersedia");

                continue;
            }

            if (! Schema::hasColumn($tabel, 'cabang_id')) {
                $this->lewati($kategori, "kolom {$tabel}.cabang_id belum ada di skema — migrasi 2026_09_21_000042 belum dijalankan");

                continue;
            }

            $baris = DB::table($tabel)
                ->select('id', $kolomNomor, 'status', 'jumlah', 'jumlah_dibayar')
                ->whereNull('cabang_id')
                ->orderBy('id')
                ->get();

            $perubahan += $baris->count();

            foreach ($baris as $b) {
                $rincian[] = sprintf(
                    '#%s %s — status %s — sisa %s → cabang_id %s',
                    $this->angka($b->id),
                    $b->{$kolomNomor},
                    $b->status,
                    $this->rupiah((float) $b->jumlah - (float) $b->jumlah_dibayar),
                    $this->angka($this->defaultCabangId)
                );
            }
        }

        $this->temuan($kategori, $perubahan, $perubahan, $rincian, [], [], [[
            'Isi `cabang_id` = '.$this->angka($this->defaultCabangId).' (cabang default, id terkecil).',
            'Hanya baris `cabang_id IS NULL`. Kolom lain tidak disentuh (identik dengan migrasi 2026_09_25_000100).',
            'Idempoten: putaran kedua menemukan 0 baris.',
        ]]);
    }

    /**
     * (b) Utang yang `referensi_id`-nya tidak menunjuk dokumen apa pun.
     *
     * Dipisah dua kelompok:
     * - `referensi_id = 0` → TIDAK MUNGKIN sah (tidak ada dokumen ber-id 0),
     *   sehingga yang dilakukan hanya menandai di `keterangan`. Baris tidak
     *   dihapus dan `referensi_id` tidak diubah (kolomnya NOT NULL).
     * - `referensi_id > 0` tapi baris tujuan tidak ada, atau `referensi_tipe`
     *   tidak dikenal → hanya dilaporkan. Menunjuk ke dokumen lain hanya
     *   agar "cocok" adalah mengarang data akuntansi.
     */
    private function analisaUtangOrphan(): void
    {
        $kategori = 'utang-orphan';
        $this->mulai($kategori);

        if (! $this->ada('utang', 'id', 'no_utang', 'referensi_tipe', 'referensi_id', 'status', 'jumlah', 'jumlah_dibayar')) {
            $this->lewati($kategori, 'tabel utang atau kolom wajib belum tersedia');

            return;
        }

        $petaTabel = $this->petaTabelReferensi();
        $semua = DB::table('utang')
            ->select('id', 'no_utang', 'referensi_tipe', 'referensi_id', 'status', 'jumlah', 'jumlah_dibayar', 'keterangan')
            ->get();

        // Kumpulkan id tujuan sekali per tabel supaya tidak query per baris.
        $idPerTabel = [];
        foreach ($semua as $b) {
            $tabelTujuan = $this->tabelTujuan($b->referensi_tipe, $petaTabel);
            if ($tabelTujuan !== null && (int) $b->referensi_id > 0) {
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

        $rincian = [];
        $perubahan = 0;
        $temuan = 0;

        foreach ($semua as $b) {
            $id = (int) $b->referensi_id;
            $tipe = (string) $b->referensi_tipe;
            $sisa = $this->rupiah((float) $b->jumlah - (float) $b->jumlah_dibayar);

            if ($id <= 0) {
                $temuan++;
                $sudahDitandai = str_contains((string) $b->keterangan, self::PENANDA);
                if (! $sudahDitandai) {
                    $perubahan++;
                }

                $rincian[] = sprintf(
                    '#%s %s — tipe %s — referensi_id kosong (0) — sisa %s%s',
                    $this->angka($b->id),
                    $b->no_utang,
                    $tipe === '' ? 'NULL' : $tipe,
                    $sisa,
                    $sudahDitandai ? ' — [sudah ditandai, idempoten]' : ' — [AKAN DITANDAI]'
                );

                continue;
            }

            $tabelTujuan = $this->tabelTujuan($tipe, $petaTabel);
            if ($tabelTujuan === null) {
                $temuan++;
                $rincian[] = sprintf(
                    '#%s %s — tipe referensi "%s" tidak dikenal — sisa %s — [hanya laporan]',
                    $this->angka($b->id),
                    $b->no_utang,
                    $tipe === '' ? 'NULL' : $tipe,
                    $sisa
                );

                continue;
            }

            if (! in_array($id, $idTersedia[$tabelTujuan] ?? [], true)) {
                $temuan++;
                $rincian[] = sprintf(
                    '#%s %s — %s #%s tidak ada (status %s, sisa %s) — [hanya laporan]',
                    $this->angka($b->id),
                    $b->no_utang,
                    $tabelTujuan,
                    $this->angka($id),
                    $b->status,
                    $sisa
                );
            }
        }

        $this->temuan($kategori, $temuan, $perubahan, $rincian, [], [], [
            [
                'Tandai `keterangan` dengan "'.self::PENANDA.'" untuk baris referensi_id = 0 (tidak mungkin sah).',
                'Baris TIDAK dihapus. `referensi_id` TIDAK diubah: kolom `utang.referensi_id` bertipe NOT NULL, jadi NULL ditolak MySQL.',
                'Mengosongkan kolomnya butuh migrasi terpisah — di luar cakupan perintah ini.',
            ],
            [
                'Baris yang `referensi_id > 0` tapi dokumen tujuannya hilang TIDAK diperbaiki otomatis.',
                'Menunjuk ke dokumen lain demi "cocok" berarti mengarang data akuntansi — harus dicocokkan manusia dengan dokumen sumber.',
                'Aksi yang benar: void dokumennya lewat jurnal pembalik, atau isi `referensi_id` dengan dokumen asli secara manual.',
            ],
        ]);
    }

    /**
     * (c) Purchase order `diterima` tanpa jurnal — LAPORAN SAJA.
     *
     * Pencocokan disamakan dengan `ute:rekonsiliasi`: jurnal bisa merujuk PO
     * langsung (`PurchaseOrder::class`) atau lewat GRN milik PO tersebut.
     */
    private function analisaPo(): void
    {
        $kategori = 'po-terima-tanpa-jurnal';
        $this->mulai($kategori);

        if (! $this->ada('purchase_order', 'id', 'no_po', 'status', 'total', 'metode_bayar', 'created_at')) {
            $this->lewati($kategori, 'tabel purchase_order atau kolom wajib belum tersedia');

            return;
        }

        if (! $this->ada('jurnal_akuntansi', 'referensi_tipe', 'referensi_id')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi belum tersedia sehingga pencocokan tidak bisa dilakukan');

            return;
        }

        $adaGrn = $this->ada('grn', 'id', 'po_id');

        $tanpaJurnal = DB::table('purchase_order')
            ->select('id', 'no_po', 'status', 'total', 'metode_bayar', 'total_dibayar', 'gudang_tujuan_id', 'created_at')
            ->where('status', 'diterima')
            ->whereNotExists(static function ($sub): void {
                $sub->selectRaw('1')->from('jurnal_akuntansi')
                    ->where('jurnal_akuntansi.referensi_tipe', PurchaseOrder::class)
                    ->whereColumn('jurnal_akuntansi.referensi_id', 'purchase_order.id');
            })
            ->when($adaGrn, static function ($q): void {
                $q->whereNotExists(static function ($sub): void {
                    $sub->selectRaw('1')->from('jurnal_akuntansi')
                        ->where('jurnal_akuntansi.referensi_tipe', Grn::class)
                        ->whereIn('jurnal_akuntansi.referensi_id', static function ($g): void {
                            $g->select('id')->from('grn')
                                ->whereColumn('grn.po_id', 'purchase_order.id');
                        });
                });
            })
            ->orderBy('id')
            ->get();

        if ($tanpaJurnal->isEmpty()) {
            $this->temuan($kategori, 0, 0, [], [], [], []);

            return;
        }

        $namaGudang = $this->petaNama('gudang', 'nama');
        $rincian = [];
        $tabel = [];
        $total = 0.0;

        foreach ($tanpaJurnal as $p) {
            $total += (float) $p->total;
            $grn = $adaGrn
                ? DB::table('grn')->select('no_grn', 'status', 'total_hpp')->where('po_id', $p->id)->get()
                : collect();

            $statusStok = $grn->isEmpty()
                ? 'belum ada GRN'
                : $grn->map(static fn ($g): string => $g->no_grn.' ('.$g->status.')')->implode(', ');

            $akunKredit = $p->metode_bayar === 'kredit' ? '210-01 Utang Usaha' : '110-01 Kas';

            $rincian[] = sprintf(
                '#%s %s — %s — total %s — bayar %s — gudang %s — stok: %s',
                $this->angka($p->id),
                $p->no_po,
                $this->tanggal($p->created_at),
                $this->rupiah($p->total),
                $p->metode_bayar,
                $namaGudang[(int) $p->gudang_tujuan_id] ?? $this->angka($p->gudang_tujuan_id),
                $statusStok
            );

            $tabel[] = [
                $p->no_po,
                $this->tanggal($p->created_at),
                $this->rupiah($p->total),
                $p->metode_bayar,
                $statusStok,
                sprintf('130-01 Persediaan debit %s / %s kredit %s', $this->rupiah($p->total), $akunKredit, $this->rupiah($p->total)),
                'referensi_tipe = '.PurchaseOrder::class.' · referensi_id = '.$this->angka($p->id),
            ];
        }

        $this->temuan($kategori, $tanpaJurnal->count(), 0, $rincian,
            ['NO_PO', 'TANGGAL', 'TOTAL', 'BAYAR', 'STATUS STOK / GRN', 'USULAN JURNAL (TIDAK DIPOSTING)', 'REFERENSI'], $tabel, [
                [
                    sprintf('%s PO `diterima` tanpa jurnal, total %s.', $this->angka($tanpaJurnal->count()), $this->rupiah($total)),
                    'Jurnal TIDAK diposting oleh perintah ini. Posting jurnal untuk data historis butuh verifikasi manual pemilik.',
                    'Jalur yang disarankan: finalisasi ulang lewat PurchaseOrderService/GrnService (idempoten terhadap no_jurnal) agar subledger Utang ikut terbentuk.',
                    'Nomor jurnal usulan: JRL-PO-{no_po} (DPP 130-01 Persediaan debit, 210-01 Utang / 110-01 Kas kredit).',
                ],
            ]);
    }

    /**
     * (d) Payroll `selesai`/`dibayar` tanpa jurnal — LAPORAN SAJA.
     */
    private function analisaPayroll(): void
    {
        $kategori = 'payroll-tanpa-jurnal';
        $this->mulai($kategori);

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
            ->orderBy('id')
            ->get();

        if ($periodeTanpaJurnal->isEmpty()) {
            $this->temuan($kategori, 0, 0, [], [], [], []);

            return;
        }

        $adaSlip = $this->ada('payroll_slip', 'id', 'payroll_periode_id', 'karyawan_id', 'total_gaji', 'jurnal_id');
        $namaKaryawan = $this->ada('karyawan', 'id', 'nama')
            ? DB::table('karyawan')->pluck('nama', 'id')->all()
            : [];

        $rincian = [];
        $tabel = [];
        $jumlahSlip = 0;
        $totalNominal = 0.0;

        foreach ($periodeTanpaJurnal as $p) {
            $slip = $adaSlip
                ? DB::table('payroll_slip')->select('id', 'karyawan_id', 'total_gaji', 'jurnal_id', 'status')
                    ->where('payroll_periode_id', $p->id)->orderBy('id')->get()
                : collect();

            $jumlahSlip += $slip->count();
            $nominal = (float) $slip->sum('total_gaji');
            $totalNominal += $nominal;
            $jurnalNull = $slip->filter(static fn ($s): bool => $s->jurnal_id === null)->count();

            $rincian[] = sprintf(
                'periode %s (status %s) — %s slip — nominal %s — slip tanpa jurnal_id: %s',
                $p->periode,
                $p->status,
                $this->angka($slip->count()),
                $this->rupiah($nominal),
                $this->angka($jurnalNull)
            );

            foreach ($slip as $s) {
                $tabel[] = [
                    $p->periode,
                    $namaKaryawan[(int) $s->karyawan_id] ?? ('karyawan #'.$this->angka($s->karyawan_id)),
                    $this->rupiah($s->total_gaji),
                    $s->status ?? 'NULL',
                    $s->jurnal_id === null ? 'NULL' : $this->angka($s->jurnal_id),
                ];
            }
        }

        $this->temuan($kategori, $periodeTanpaJurnal->count(), 0, $rincian, ['PERIODE', 'KARYAWAN', 'NOMINAL', 'STATUS SLIP', 'JURNAL_ID'], $tabel, [
            [
                sprintf('%s periode payroll final tanpa jurnal, %s slip, total %s.', $this->angka($periodeTanpaJurnal->count()), $this->angka($jumlahSlip), $this->rupiah($totalNominal)),
                'Jurnal TIDAK diposting oleh perintah ini — posting gaji untuk periode lampau butuh verifikasi manual pemilik.',
                'Jalur yang disarankan: jalankan ulang PayrollService (nomor jurnal JRL-PR-{periode}) agar `payroll_slip.jurnal_id` ikut terisi.',
                'Akun usulan: 520-01 Beban Gaji debit, 520-08 Beban Komisi debit, 210-02 Utang Gaji kredit.',
            ],
        ]);
    }

    /**
     * (e) Satu opname yang punya lebih dari satu nomor jurnal — LAPORAN SAJA.
     *
     * Belum diketahui apakah itu replay bug atau adjustment sah, jadi tidak
     * ada baris yang dihapus/diubah. Nominal per jurnal ditampilkan supaya
     * owner bisa menilai sendiri.
     */
    private function analisaOpname(): void
    {
        $kategori = 'opname-jurnal-ganda';
        $this->mulai($kategori);

        if (! $this->ada('jurnal_akuntansi', 'no_jurnal', 'tanggal', 'sumber', 'referensi_tipe', 'referensi_id', 'debit', 'kredit')) {
            $this->lewati($kategori, 'tabel jurnal_akuntansi atau kolom wajib belum tersedia');

            return;
        }

        $ganda = DB::table('jurnal_akuntansi')
            ->select('referensi_id')
            ->selectRaw('COUNT(DISTINCT no_jurnal) AS jml_jurnal')
            ->where('referensi_tipe', StokOpname::class)
            ->whereNotNull('referensi_id')
            ->groupBy('referensi_id')
            ->havingRaw('COUNT(DISTINCT no_jurnal) > 1')
            ->orderByDesc('jml_jurnal')
            ->get();

        if ($ganda->isEmpty()) {
            $this->temuan($kategori, 0, 0, [], [], [], []);

            return;
        }

        $petaNomor = $this->ada('stok_opname', 'id', 'no_opname')
            ? DB::table('stok_opname')->whereIn('id', $ganda->pluck('referensi_id')->all())->pluck('no_opname', 'id')->all()
            : [];

        $rincian = [];
        $tabel = [];

        foreach ($ganda as $g) {
            $referensiId = (int) $g->referensi_id;

            $jurnals = DB::table('jurnal_akuntansi')
                ->select('no_jurnal')
                ->selectRaw('MIN(tanggal) AS tanggal, MIN(sumber) AS sumber, MIN(referensi_id) AS referensi_id, SUM(debit) AS total_debit, SUM(kredit) AS total_kredit, COUNT(*) AS jml_baris')
                ->where('referensi_tipe', StokOpname::class)
                ->where('referensi_id', $referensiId)
                ->groupBy('no_jurnal')
                ->orderBy('no_jurnal')
                ->limit($this->limit)
                ->get();

            $rincian[] = sprintf(
                'opname #%s%s — %s jurnal distinct (menampilkan %s)',
                $this->angka($referensiId),
                isset($petaNomor[$referensiId]) ? ' '.$petaNomor[$referensiId] : '',
                $this->angka($g->jml_jurnal),
                $this->angka($jurnals->count())
            );

            foreach ($jurnals as $j) {
                $tabel[] = [
                    $petaNomor[$referensiId] ?? ('opname #'.$this->angka($referensiId)),
                    $j->no_jurnal,
                    $this->tanggal($j->tanggal),
                    (string) $j->sumber,
                    $this->rupiah($j->total_debit),
                    $this->rupiah($j->total_kredit),
                    $this->angka($j->referensi_id).' / '.$this->angka($j->jml_baris).' baris',
                ];
            }
        }

        $this->temuan($kategori, $ganda->count(), 0, $rincian, ['NO_OPNAME', 'NO_JURNAL', 'TANGGAL', 'SUMBER', 'TOTAL DEBIT', 'TOTAL KREDIT', 'REFERENSI'], $tabel, [
            [
                sprintf('%s opname punya lebih dari satu nomor jurnal.', $this->angka($ganda->count())),
                'TIDAK ada jurnal yang dihapus atau diubah — belum diketahui apakah itu replay atau adjustment sah.',
                'Adjustment opname seharusnya satu jurnal per siklus approval. Jurnal yang berlebih dibatalkan lewat jurnal pembalik, bukan dihapus.',
                'Putuskan per kasus setelah cocokkan dengan stok_items dan stok_log tanggal yang sama.',
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Penulisan — hanya untuk 2 kategori yang aman
    // ------------------------------------------------------------------

    /**
     * Terapkan rencana dua kategori aman dalam satu transaksi DB.
     *
     * @return int jumlah baris yang benar-benar berubah.
     */
    private function terapkan(): int
    {
        $ditulis = 0;

        if (in_array('piutang-utang-cabang', $this->diminta, true) && $this->defaultCabangId !== null) {
            $ditulis += $this->terapkanCabang();
        }

        if (in_array('utang-orphan', $this->diminta, true)) {
            $ditulis += $this->terapkanUtangOrphan();
        }

        return $ditulis;
    }

    /**
     * Isi `cabang_id` NULL dengan cabang default (idempoten).
     */
    private function terapkanCabang(): int
    {
        $ditulis = 0;
        $default = (int) $this->defaultCabangId;

        foreach (['piutang', 'utang'] as $tabel) {
            if (! $this->ada($tabel, 'id') || ! Schema::hasColumn($tabel, 'cabang_id')) {
                continue;
            }

            $jumlah = DB::table($tabel)->whereNull('cabang_id')->update(['cabang_id' => $default]);
            $ditulis += $jumlah;

            if ($jumlah > 0) {
                $this->line("  <info>✓</info> {$tabel}: {$this->angka($jumlah)} baris diisi cabang_id = {$this->angka($default)}");
            }
        }

        return $ditulis;
    }

    /**
     * Tandai `utang` yang `referensi_id = 0` di kolom `keterangan` (idempoten).
     *
     * `referensi_id` sengaja TIDAK diubah: kolomnya NOT NULL, jadi NULL akan
     * ditolak MySQL. Menunjuk dokumen lain demi "cocok" adalah mengarang
     * data, jadi tidak dilakukan.
     */
    private function terapkanUtangOrphan(): int
    {
        if (! $this->ada('utang', 'id', 'keterangan')) {
            return 0;
        }

        $kandidat = DB::table('utang')
            ->select('id', 'no_utang', 'keterangan')
            ->where('referensi_id', 0)
            ->whereNotLike('keterangan', '%'.self::PENANDA.'%')
            ->orderBy('id')
            ->get();

        $ditulis = 0;

        foreach ($kandidat as $b) {
            $keteranganBaru = trim(((string) $b->keterangan).' '.self::PENANDA.' referensi_id=0 tidak menunjuk dokumen mana pun; perlu verifikasi manual sebelum di-null-kan.');

            DB::table('utang')->where('id', $b->id)->update(['keterangan' => $keteranganBaru]);
            $ditulis++;

            $this->line('  <info>✓</info> utang #'.$this->angka($b->id).' '.$b->no_utang.' ditandai (tidak dihapus, referensi_id tidak diubah)');
        }

        return $ditulis;
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    /**
     * Cetak rencana total SEBELUM ada penulisan apa pun.
     */
    private function cetakRencana(int $totalPerubahan, bool $jalankan): void
    {
        $this->line('<comment>== RENCANA ==</comment>');

        if ($totalPerubahan === 0) {
            $this->line('  Tidak ada baris yang perlu ditulis ulang.');
        } else {
            $this->warn(sprintf('  Akan mengubah %s baris (hanya kategori: %s).', $this->angka($totalPerubahan), implode(', ', self::BISA_DIPERBAIKI)));
        }

        if (! $jalankan) {
            $this->line('  Mode DRY-RUN — tidak ada penulisan. Tambahkan <comment>--jalankan</comment> (atau <comment>--apply</comment>) untuk benar-benar menulis.');
        } else {
            $this->line('  Mode JALANKAN — menulis kategori aman saja. Jurnal purchase order / payroll TIDAK pernah dibuat otomatis.');
        }

        $this->line('  Tidak ada data yang dihapus, dan nomor jurnal yang sudah ada tidak pernah diubah.');
        $this->newLine();
    }

    /**
     * Cetak blok laporan tiap kategori yang diminta.
     */
    private function categoriaLaporan(): void
    {
        foreach ($this->diminta as $kategori) {
            $hasil = $this->hasil[$kategori] ?? null;

            $this->line('<comment>== '.self::KATEGORI[$kategori].' (--kategori='.$kategori.') ==</comment>');

            if ($hasil === null || $hasil['temuan'] === 0) {
                $this->line('  ✓ tidak ada anomali');
            } else {
                $bisa = in_array($kategori, self::BISA_DIPERBAIKI, true);
                $this->line(sprintf(
                    '  Temuan: %s | %s',
                    $this->angka($hasil['temuan']),
                    $bisa
                        ? 'bisa diperbaiki otomatis: '.$this->angka($hasil['perubahan']).' baris'
                        : '<error>HANYA LAPORAN — butuh verifikasi manual, tidak ada yang ditulis</error>'
                ));

                foreach (array_slice($hasil['rincian'], 0, $this->limit) as $baris) {
                    $this->line('    • '.$baris);
                }

                if ($hasil['temuan'] > $this->limit) {
                    $this->line(sprintf('    ... (+%s temuan lain)', $this->angka($hasil['temuan'] - $this->limit)));
                }

                if ($hasil['tabel'] !== []) {
                    $this->newLine();
                    $this->cetakTabel($hasil['tabel'], $hasil['header']);
                    $this->newLine();
                }

                foreach ($hasil['usulan'] as $usulan) {
                    $this->line('  <comment>Usulan / alasan:</comment>');
                    foreach ($usulan as $baris) {
                        $this->line('    - '.$baris);
                    }
                }
            }

            foreach ($hasil['lewati'] ?? [] as $alasan) {
                $this->line('  [dilewati] '.$alasan);
            }

            $this->newLine();
        }
    }

    /**
     * Cetak ringkasan + exit code.
     */
    private function cetakRingkasan(int $ditulis, bool $dibatalkan): void
    {
        $baris = [];
        $totalTemuan = 0;
        $totalPerubahan = 0;

        foreach ($this->diminta as $kategori) {
            $hasil = $this->hasil[$kategori] ?? null;
            $temuan = $hasil['temuan'] ?? 0;
            $perubahan = $hasil['perubahan'] ?? 0;
            $totalTemuan += $temuan;
            $totalPerubahan += $perubahan;

            $baris[] = [
                self::KATEGORI[$kategori],
                $kategori,
                $this->angka($temuan),
                in_array($kategori, self::BISA_DIPERBAIKI, true) ? 'otomatis' : 'manual',
                $this->angka($perubahan),
            ];
        }

        $baris[] = ['TOTAL KESELURUHAN', '', $this->angka($totalTemuan), '', $this->angka($totalPerubahan)];

        $this->line('<comment>== RINGKASAN ==</comment>');
        $this->table(['KATEGORI', 'KUNCI', 'TEMUAN', 'CARA PERBAIKAN', 'RENCANA TULIS'], $baris);
        $this->newLine();

        if ($dibatalkan) {
            $this->error('Penulisan dibatalkan — 0 baris berubah.');
        } elseif ($ditulis > 0) {
            $this->info("Selesai — {$this->angka($ditulis)} baris diperbaiki.");
        } else {
            $this->info('Selesai — 0 baris diperbaiki (tidak ada yang perlu ditulis, atau mode dry-run).');
        }

        if ($this->adaTemuanManual()) {
            $this->line('<error>Ada temuan yang WAJIB diverifikasi manual oleh pemilik — exit code 1.</error>');
        } else {
            $this->line('<info>Tidak ada temuan manual — exit code 0.</info>');
        }

        $this->line('INFO: perintah ini tidak pernah menghapus baris, tidak pernah mengubah no_jurnal yang sudah ada, dan tidak pernah membuat jurnal akuntansi secara otomatis.');
    }

    // ------------------------------------------------------------------
    // Utilitas
    // ------------------------------------------------------------------

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $baris
     */
    private function cetakTabel(array $baris, array $header = []): void
    {
        if ($header === []) {
            $header = ['KETERANGAN'];
        }

        $this->table($header, $baris);
    }

    /**
     * Tanya persetujuan sebelum menulis. Menolak = tidak menulis.
     */
    private function bolehTulis(int $jumlahBaris): bool
    {
        if ((bool) $this->option('yes')) {
            return true;
        }

        $this->warn(sprintf('  PERINGATAN: %s baris AKAN diubah di database.', $this->angka($jumlahBaris)));

        if (! $this->bisaBertanya()) {
            $this->error('  Input tidak interaktif (cron/CI/--no-interaction/STDIN bukan TTY) — penulisan ditolak demi aman.');
            $this->line('  Ulangi dengan <comment>--yes</comment> bila memang ingin menulis.');

            return false;
        }

        try {
            return (bool) $this->confirm('  Lanjutkan penulisan?', false);
        } catch (Throwable) {
            $this->error('  Konfirmasi tidak bisa dibaca — penulisan dibatalkan.');

            return false;
        }
    }

    /**
     * Apakah konfirmasi bisa dikembalikan ke pemanggil.
     *
     * `runningUnitTests()` sengaja ikut diperiksa supaya test tidak pernah
     * menggantung menunggu input STDIN.
     */
    private function bisaBertanya(): bool
    {
        if (! $this->input->isInteractive() || (bool) $this->option('no-interaction')) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return defined('STDIN') && stream_isatty(STDIN);
    }

    /** Ada kategori "hanya laporan" yang masih punya temuan? */
    private function adaTemuanManual(): bool
    {
        foreach ($this->diminta as $kategori) {
            if (! in_array($kategori, self::BISA_DIPERBAIKI, true) && ($this->hasil[$kategori]['temuan'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function totalPerubahan(): int
    {
        $total = 0;
        foreach ($this->diminta as $kategori) {
            $total += $this->hasil[$kategori]['perubahan'] ?? 0;
        }

        return $total;
    }

    /** @return array<int, string>|null daftar kunci valid, null bila ada kategori asing. */
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

    private function mulai(string $kategori): void
    {
        $this->hasil[$kategori] = [
            'perubahan' => 0, 'temuan' => 0, 'rincian' => [], 'header' => [],
            'tabel' => [], 'usulan' => [], 'lewati' => [],
        ];
    }

    /**
     * @param  array<int, string>  $rincian
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $tabel
     * @param  array<int, array<int, string>>  $usulan
     */
    private function temuan(string $kategori, int $jumlah, int $perubahan, array $rincian, array $header, array $tabel, array $usulan): void
    {
        $this->hasil[$kategori] = [
            'perubahan' => $perubahan,
            'temuan' => $jumlah,
            'rincian' => $rincian,
            'header' => $header,
            'tabel' => $tabel,
            'usulan' => $usulan,
            'lewati' => [],
        ];
    }

    private function lewati(string $kategori, string $alasan): void
    {
        $this->mulai($kategori);
        $this->hasil[$kategori]['lewati'][] = $alasan;
    }

    /** Guard skema: tabel ada dan semua kolom yang dipakai tersedia. */
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

    /** Cabang default: id terkecil (identik dengan migrasi 2026_09_25_000100). */
    private function cabangDefault(): ?int
    {
        $id = DB::table('cabang')->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Peta `referensi_tipe` legacy + FQCN ke nama tabel tujuan.
     *
     * @return array<string, string>
     */
    private function petaTabelReferensi(): array
    {
        return [
            PurchaseOrder::class => 'purchase_order',
            'pembelian' => 'purchase_order',
            Grn::class => 'grn',
            'grn' => 'grn',
            Komisi::class => 'komisi',
            'komisi' => 'komisi',
        ];
    }

    /**
     * @param  array<string, string>  $peta
     */
    private function tabelTujuan(mixed $referensiTipe, array $peta): ?string
    {
        $tipe = (string) $referensiTipe;
        $tabel = $peta[$tipe] ?? $peta[strtolower($tipe)] ?? null;

        return ($tabel !== null && Schema::hasTable($tabel)) ? $tabel : null;
    }

    /**
     * @return array<int|string, string> peta id → nama untuk mempercantik laporan.
     */
    private function petaNama(string $tabel, string $kolom): array
    {
        if (! $this->ada($tabel, 'id', $kolom)) {
            return [];
        }

        return DB::table($tabel)->pluck($kolom, 'id')->all();
    }

    private function angka(int|float|string|null $nilai, int $desimal = 0): string
    {
        return number_format((float) $nilai, $desimal, ',', '.');
    }

    private function rupiah(int|float|string|null $nilai): string
    {
        return 'Rp '.$this->angka($nilai);
    }

    private function tanggal(mixed $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '—';
        }

        try {
            return Carbon::parse((string) $nilai)->format('d-m-Y');
        } catch (Throwable) {
            return (string) $nilai;
        }
    }
}
