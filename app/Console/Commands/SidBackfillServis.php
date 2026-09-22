<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 4] D-01 tiket_servis backfill + D-02 tiket_servis_item (idempotent, chunked).
 *
 * Sumber: sid_retail_raw_servis (332) + sid_retail_raw_itemservis (671).
 * Tiket lama (332) sudah ada di app dengan no_tiket = 'SID-<kode SID>' (migrasi lama) →
 * D-01 = UPDATE backfill kolom (guard NULL/empty untuk kolom yang bisa kosong,
 * deterministik untuk jenis_hp/keluhan/status/estimasi_biaya/is_migrasi_sid).
 * Status SID → state machine app (snapshot, 1 baris servis_status_log per tiket).
 * D-02 = insert tiket_servis_item per tiket (guard: skip bila tiket sudah punya item).
 *
 * Sumber payload dibaca per-chunk (200/300), aman utk server 1GB.
 * Jalankan 2x → angka sama.
 */
class SidBackfillServis extends Command
{
    protected $signature = 'sid:backfill-servis {--dry-run : hanya hitung & laporan, tanpa perubahan DB} {--only=servis,item : servis,item}';

    protected $description = 'FASE 4: backfill tiket_servis (D-01) + tiket_servis_item (D-02) dari staging SID';

    private array $counts = [
        'd01_staging' => 0, 'd01_tiket_ditemukan' => 0, 'd01_tiket_diinsert' => 0, 'd01_updated' => 0,
        'd01_status_berubah' => 0, 'd01_jenis_hp_berubah' => 0, 'd01_keluhan_berubah' => 0, 'd01_estimasi_berubah' => 0,
        'd01_pelanggan_unmapped' => 0, 'd01_statuslog' => 0, 'd01_statuslog_skip' => 0,
        'd02_tiket_miss' => 0, 'd02_inserted' => 0, 'd02_skip_tiket_punya_item' => 0,
    ];

    private array $statusMap = [];   // status SID → count
    private array $pelangganUnmapped = [];

    /** @var array<string,int> kode_lama pelanggan → id */
    private array $pelangganMap = [];

    /** @var array<string,int> kode_lama produk → id */
    private array $produkMap = [];

    /** @var array<string,int> kode_lama sku_variants → id */
    private array $skuMap = [];

    /**
     * Mapping status SID → state machine app (PRD §4.3 / ServisStateMachine).
     * Snapshot status akhir — TIDAK melewati transisi (servis_status_log = 1 baris historis).
     */
    private const STATUS_MAP = [
        'DIBATALKAN' => 'ditolak',
        'SUDAH DI AMBIL' => 'diambil',
        'SELESAI DI SERVIS' => 'selesai',
        'SEDANG DI SERVIS' => 'dikerjakan',
    ];

    private const SENTINEL = ['1899-12-30', '0000-00-00', '1900-01-01', ''];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        if ($dry) {
            $this->warn('DRY-RUN: tidak ada perubahan DB');
        }

        $this->loadMaps();

        if (in_array('servis', $only, true)) {
            $this->stepTiketServis($dry);
        }
        if (in_array('item', $only, true)) {
            $this->stepTiketServisItem($dry);
        }

        $this->tulisLaporan($dry);
        $this->printSummary();

        return 0;
    }

    // ---------------- D-01 TIKET SERVIS ----------------

    private function stepTiketServis(bool $dry): void
    {
        $this->info("\n--- D-01 tiket_servis backfill (332) ---");

        // pool tiket SID: no_tiket → row (slim)
        $pool = [];
        DB::table('tiket_servis')
            ->select(['id', 'no_tiket', 'jenis_hp', 'keluhan', 'status', 'estimasi_biaya', 'seri_hp',
                'kondisi_fisik', 'nama_pelanggan', 'telepon_pelanggan', 'catatan_admin', 'tanggal_selesai',
                'tanggal_diambil', 'tanggal_bayar', 'teknisi', 'pembayaran', 'sid_detail', 'pelanggan_id', 'is_migrasi_sid'])
            ->orderBy('id')->chunk(500, function ($chunk) use (&$pool) {
                foreach ($chunk as $r) {
                    $pool[$r->no_tiket] = $r;
                }
            });

        DB::table('sid_retail_raw_servis')->orderBy('id')->chunkById(200, function ($chunk) use ($dry, &$pool) {
            foreach ($chunk as $raw) {
                $this->counts['d01_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) $raw->kode_sumber);
                $noTiket = 'SID-'.$kode;
                $cur = $pool[$noTiket] ?? null;
                $tiketId = $cur ? (int) $cur->id : null;

                $u = $this->buildUpdate($p, $cur);

                // status (NOT NULL) — deterministic overwrite via canonical map
                $status = $this->mapStatus($p);
                if ($cur && $cur->status !== $status) {
                    $u['status'] = $status;
                    $this->counts['d01_status_berubah']++;
                }

                $u['is_migrasi_sid'] = true;

                if ($cur) {
                    $this->counts['d01_tiket_ditemukan']++;
                    if ($u && ! $dry) {
                        $u['updated_at'] = now();
                        DB::table('tiket_servis')->where('id', $tiketId)->update($u);
                    }
                    if ($u) {
                        $this->counts['d01_updated']++;
                    }
                } else {
                    // tiket belum ada → INSERT (fallback; saat ini semua 332 sudah ada)
                    $this->counts['d01_tiket_diinsert']++;
                    if (! $dry) {
                        $data = array_merge([
                            'no_tiket' => $noTiket,
                            'cabang_id' => 1,
                            'jenis_servis_id' => null,
                            'pelanggan_id' => $this->resolusiPelanggan($p),
                            'nama_pelanggan' => $this->namaPelanggan($p),
                            'telepon_pelanggan' => $this->teleponPelanggan($p),
                            'foto_unit' => null,
                            'sumber' => 'sid_migration',
                            'created_at' => $this->tanggalTerima($p) ?? now(),
                        ], $u);
                        $tiketId = DB::table('tiket_servis')->insertGetId($data);
                    }
                }

                // snapshot status akhir (1 baris per tiket)
                $this->snapshotStatusLog($tiketId, $status, $p, $dry);
            }
        });
    }

    /** Bangun update array — guard NULL/empty untuk kolom backfill, deterministik utk beberapa. */
    private function buildUpdate(array $p, ?object $cur): array
    {
        // $cur === null → baris baru (INSERT fallback): set semua nilai tanpa guard.
        $u = [];

        // deterministik (sumber tetap → nilai tetap, idempotent)
        $jenisHp = trim((string) ($p['type'] ?? ''));
        if ($jenisHp === '') {
            $jenisHp = trim((string) ($p['barang'] ?? ''));
        }
        if (($cur === null || $cur->jenis_hp !== $jenisHp) && $jenisHp !== '') {
            $u['jenis_hp'] = $jenisHp;
            if ($cur) {
                $this->counts['d01_jenis_hp_berubah']++;
            }
        }

        $keluhan = $this->concatNonEmpty([$p['kerusakan'] ?? null, $p['kerusakan2'] ?? null, $p['kerusakan3'] ?? null]);
        if (($cur === null || $cur->keluhan !== $keluhan) && $keluhan !== '') {
            $u['keluhan'] = $keluhan;
            if ($cur) {
                $this->counts['d01_keluhan_berubah']++;
            }
        }

        $estimasi = (float) ($p['jumlah'] ?? 0);
        if ($cur === null || (float) $cur->estimasi_biaya !== $estimasi) {
            $u['estimasi_biaya'] = $estimasi;
            if ($cur) {
                $this->counts['d01_estimasi_berubah']++;
            }
        }

        // guard NULL/empty
        $seri = trim((string) ($p['no_imei'] ?? ''));
        if ($seri !== '' && ($cur === null || empty($cur->seri_hp))) {
            $u['seri_hp'] = $seri;
        }

        $kondisi = $this->kondisiFisik($p);
        if ($kondisi !== null && ($cur === null || $cur->kondisi_fisik === null)) {
            $u['kondisi_fisik'] = $kondisi;
        }

        $nama = $this->namaPelanggan($p);
        if ($nama !== null && ($cur === null || empty($cur->nama_pelanggan))) {
            $u['nama_pelanggan'] = $nama;
        }
        $telp = $this->teleponPelanggan($p);
        if ($telp !== null && ($cur === null || empty($cur->telepon_pelanggan))) {
            $u['telepon_pelanggan'] = $telp;
        }

        $catatan = $this->catatanAdmin($p);
        if ($catatan !== '' && ($cur === null || empty($cur->catatan_admin))) {
            $u['catatan_admin'] = $catatan;
        }

        $tglKembali = $this->tanggalKembali($p);
        $selesai = filter_var($p['selesai'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sudahDiambil = filter_var($p['sudahdiambil'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($tglKembali !== null && ($selesai || $sudahDiambil) && ($cur === null || $cur->tanggal_selesai === null)) {
            $u['tanggal_selesai'] = $tglKembali;
        }
        if ($tglKembali !== null && $sudahDiambil && ($cur === null || $cur->tanggal_diambil === null)) {
            $u['tanggal_diambil'] = $tglKembali;
        }

        $tglBayar = $this->normalDate($p['tgl_bayar'] ?? null);
        if ($tglBayar !== null && ($cur === null || $cur->tanggal_bayar === null)) {
            $u['tanggal_bayar'] = $tglBayar;
        }

        $teknisi = trim((string) ($p['teknisi'] ?? ''));
        if ($teknisi !== '' && ($cur === null || empty($cur->teknisi))) {
            $u['teknisi'] = $teknisi;
        }

        $u['pembayaran'] = $this->pembayaranJson($p);
        if ($cur && $cur->pembayaran !== null) {
            unset($u['pembayaran']);
        }

        $u['sid_detail'] = $this->sidDetail($p);
        if ($cur && $cur->sid_detail !== null) {
            unset($u['sid_detail']);
        }

        $pelangganId = $this->resolusiPelanggan($p);
        if ($pelangganId !== null && ($cur === null || $cur->pelanggan_id === null)) {
            $u['pelanggan_id'] = $pelangganId;
        }

        return $u;
    }

    private function mapStatus(array $p): string
    {
        $statusSid = strtoupper(trim((string) ($p['status'] ?? '')));
        $status = self::STATUS_MAP[$statusSid] ?? null;
        if ($status === null) {
            // fallback berbasis flag (status string tak dikenal)
            if (filter_var($p['dibatalkan'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $status = 'ditolak';
            } elseif (filter_var($p['sudahdiambil'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $status = 'diambil';
            } elseif (filter_var($p['selesai'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $status = 'selesai';
            } else {
                $status = 'diterima';
            }
        }
        $this->statusMap[$status] = ($this->statusMap[$status] ?? 0) + 1;

        return $status;
    }

    /** 1 baris snapshot status akhir per tiket (idempotent via alasan penanda). */
    private function snapshotStatusLog(?int $tiketId, string $status, array $p, bool $dry): void
    {
        if (! $tiketId) {
            return;
        }
        $exists = DB::table('servis_status_log')
            ->where('tiket_servis_id', $tiketId)
            ->where('alasan', 'Migrasi SID snapshot status akhir')
            ->exists();
        if ($exists) {
            $this->counts['d01_statuslog_skip']++;

            return;
        }
        $this->counts['d01_statuslog']++;
        if ($dry) {
            return;
        }
        $ts = $this->tanggalTerima($p) ?? now();
        DB::table('servis_status_log')->insert([
            'tiket_servis_id' => $tiketId,
            'status_dari' => null,
            'status_ke' => $status,
            'user_id' => null,
            'aksi' => 'transisi',
            'alasan' => 'Migrasi SID snapshot status akhir',
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }

    // ---------------- D-02 TIKET SERVIS ITEM ----------------

    private function stepTiketServisItem(bool $dry): void
    {
        $this->info("\n--- D-02 tiket_servis_item (671) ---");

        $tiketIds = [];
        DB::table('tiket_servis')->select(['id', 'no_tiket'])->orderBy('id')->chunk(500, function ($chunk) use (&$tiketIds) {
            foreach ($chunk as $r) {
                $tiketIds[$r->no_tiket] = (int) $r->id;
            }
        });

        // kumpulkan item per tiket (671 baris — muat di memori), lalu insert per tiket sekaligus.
        // Guard idempotent PER TIKET: skip bila tiket sudah punya item (run sebelumnya).
        $itemsByTiket = [];
        DB::table('sid_retail_raw_itemservis')->select(['id', 'kode_sumber', 'payload_normal'])->orderBy('id')->chunk(300, function ($chunk) use ($tiketIds, &$itemsByTiket) {
            foreach ($chunk as $raw) {
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? ''));
                $tiketId = $kode !== '' ? ($tiketIds['SID-'.$kode] ?? null) : null;
                if (! $tiketId) {
                    $this->counts['d02_tiket_miss']++;

                    continue;
                }
                $kodeBarang = trim((string) ($p['kode_barang'] ?? ''));
                $jenis = strtoupper(trim((string) ($p['jenis'] ?? '')));
                $itemsByTiket[$tiketId][] = [
                    'tiket_servis_id' => $tiketId,
                    'tipe' => $jenis === 'BARANG' ? 'part' : 'jasa',
                    'produk_id' => $kodeBarang !== '' ? ($this->produkMap[$kodeBarang] ?? null) : null,
                    'sku_variant_id' => $kodeBarang !== '' ? ($this->skuMap[$kodeBarang] ?? null) : null,
                    'nama_item' => trim((string) ($p['nama_barang'] ?? '')),
                    'qty' => (int) ($p['qty'] ?? 1),
                    'harga' => (float) ($p['harga_satuan'] ?? 0),
                    'hpp' => (float) ($p['hpp'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        });

        foreach ($itemsByTiket as $tiketId => $items) {
            // idempotent: skip bila tiket sudah punya item (dari run sebelumnya)
            if (DB::table('tiket_servis_item')->where('tiket_servis_id', $tiketId)->exists()) {
                $this->counts['d02_skip_tiket_punya_item']++;

                continue;
            }
            $this->counts['d02_inserted'] += count($items);
            if (! $dry) {
                DB::table('tiket_servis_item')->insert($items);
            }
        }
    }

    // ---------------- UTIL ----------------

    private function loadMaps(): void
    {
        DB::table('pelanggan')->select(['id', 'kode_lama', 'nama', 'telepon'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($chunk) {
            foreach ($chunk as $r) {
                $this->pelangganMap[(string) $r->kode_lama] = (int) $r->id;
                $this->pelangganPool[(int) $r->id] = $r;
            }
        });
        DB::table('produk')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($chunk) {
            foreach ($chunk as $r) {
                $this->produkMap[(string) $r->kode_lama] = (int) $r->id;
            }
        });
        DB::table('sku_variants')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($chunk) {
            foreach ($chunk as $r) {
                $this->skuMap[(string) $r->kode_lama] = (int) $r->id;
            }
        });
    }

    /** @var array<int,object> pelanggan pool by id (kode_lama/​nama/telepon) */
    private array $pelangganPool = [];

    private function resolusiPelanggan(array $p): ?int
    {
        $kode = trim((string) ($p['kode_pelanggan'] ?? ''));
        if ($kode === '') {
            return null;
        }
        $id = $this->pelangganMap[$kode] ?? null;
        if (! $id) {
            $this->counts['d01_pelanggan_unmapped']++;
            $this->pelangganUnmapped[$kode] = ($this->pelangganUnmapped[$kode] ?? 0) + 1;
        }

        return $id;
    }

    private function namaPelanggan(array $p): ?string
    {
        $kode = trim((string) ($p['kode_pelanggan'] ?? ''));
        if ($kode === '' || ! isset($this->pelangganMap[$kode])) {
            return null;
        }
        $row = $this->pelangganPool[$this->pelangganMap[$kode]] ?? null;

        return $row && trim((string) $row->nama) !== '' ? trim((string) $row->nama) : null;
    }

    private function teleponPelanggan(array $p): ?string
    {
        $kode = trim((string) ($p['kode_pelanggan'] ?? ''));
        if ($kode === '' || ! isset($this->pelangganMap[$kode])) {
            return null;
        }
        $row = $this->pelangganPool[$this->pelangganMap[$kode]] ?? null;

        return $row && trim((string) $row->telepon) !== '' ? trim((string) $row->telepon) : null;
    }

    private function kondisiFisik(array $p): ?string
    {
        $vals = [];
        foreach (['kelengkapan', 'kelengkapan2', 'kelengkapan3'] as $k) {
            $v = trim((string) ($p[$k] ?? ''));
            if ($v !== '') {
                $vals[$k] = $v;
            }
        }

        return $vals ? json_encode($vals) : null;
    }

    private function catatanAdmin(array $p): string
    {
        $parts = array_filter([
            trim((string) ($p['kerusakan2'] ?? '')),
            trim((string) ($p['kerusakan3'] ?? '')),
            trim((string) ($p['keterangan_saat_sudah_cek'] ?? '')),
        ]);

        return implode(' | ', $parts);
    }

    private function pembayaranJson(array $p): string
    {
        return json_encode([
            'pembayaran' => trim((string) ($p['pembayaran'] ?? '')),
            'jt' => (int) ($p['jt'] ?? 0),
            'komisi' => (float) ($p['komisi'] ?? 0),
            'kode_kas' => trim((string) ($p['kode_kas'] ?? '')),
        ]);
    }

    private function sidDetail(array $p): string
    {
        return json_encode([
            'kode_sid' => trim((string) ($p['kode'] ?? '')),
            'operator' => trim((string) ($p['operator'] ?? '')),
            'status_sid' => trim((string) ($p['status'] ?? '')),
            'selesai' => filter_var($p['selesai'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sudahdiambil' => filter_var($p['sudahdiambil'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'dibatalkan' => filter_var($p['dibatalkan'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'edit_f' => filter_var($p['edit_f'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'totalhpp' => (float) ($p['totalhpp'] ?? 0),
            'totallabarugi' => (float) ($p['totallabarugi'] ?? 0),
        ]);
    }

    private function tanggalTerima(array $p): ?string
    {
        $tanggal = $this->normalDate($p['tanggal'] ?? null);
        if (! $tanggal) {
            return null;
        }
        $jam = trim((string) ($p['jam'] ?? ''));
        $jam = preg_replace('/^(\d{2}:\d{2}:\d{2})$/', '$1', $jam);
        if ($jam !== '' && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $jam)) {
            return $tanggal.' '.$jam;
        }

        return $tanggal.' 00:00:00';
    }

    private function tanggalKembali(array $p): ?string
    {
        $tanggal = $this->normalDate($p['tanggal_kembali'] ?? null);
        if (! $tanggal) {
            return null;
        }
        $jam = trim((string) ($p['jamkembali'] ?? ''));
        if ($jam !== '' && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $jam)) {
            return $tanggal.' '.$jam;
        }

        return $tanggal.' 00:00:00';
    }

    private function concatNonEmpty(array $vals): string
    {
        return implode(' | ', array_filter(array_map(
            fn ($v) => trim((string) ($v ?? '')),
            $vals
        )));
    }

    private function normalDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if (in_array($s, self::SENTINEL, true)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------------- LAPORAN ----------------

    private function tulisLaporan(bool $dry): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/D-servis-laporan-'.date('Ymd-His').'.md';

        $body = "# LAPORAN FASE 4 (D-01 tiket_servis, D-02 tiket_servis_item) — ".date('Y-m-d H:i:s')."\n\n"
            . 'Perintah: `php artisan sid:backfill-servis'.($dry ? ' --dry-run' : '')."` (idempotent, non-destruktif, chunked)\n\n"
            . "## Ringkasan\n\n"
            . "- D-01 staging: {$this->counts['d01_staging']}, tiket ditemukan (UPDATE): {$this->counts['d01_tiket_ditemukan']}, insert baru: {$this->counts['d01_tiket_diinsert']}\n"
            . "- D-01 baris ter-update: {$this->counts['d01_updated']} (status berubah: {$this->counts['d01_status_berubah']}, jenis_hp: {$this->counts['d01_jenis_hp_berubah']}, keluhan: {$this->counts['d01_keluhan_berubah']}, estimasi: {$this->counts['d01_estimasi_berubah']})\n"
            . "- D-01 pelanggan unmapped (kode_pelanggan tanpa kode_lama): {$this->counts['d01_pelanggan_unmapped']}\n"
            . "- D-01 servis_status_log snapshot inserted: {$this->counts['d01_statuslog']}, sudah ada (skip): {$this->counts['d01_statuslog_skip']}\n"
            . "- D-02 item inserted: {$this->counts['d02_inserted']}, tiket tidak ditemukan: {$this->counts['d02_tiket_miss']}, skip (tiket sudah punya item): {$this->counts['d02_skip_tiket_punya_item']}\n\n"
            . "## Mapping status SID → state machine app (snapshot status akhir)\n\n"
            . "- `SUDAH DI AMBIL` (290) → `diambil` (terminal)\n"
            . "- `SELESAI DI SERVIS` (26) → `selesai`\n"
            . "- `SEDANG DI SERVIS` (14) → `dikerjakan`\n"
            . "- `DIBATALKAN` (2) → `ditolak` (terminal)\n"
            . "- hasil: ".$this->mapLines()."\n\n"
            . "Catatan: snapshot 1 baris `servis_status_log` (status_ke = status akhir, status_dari NULL, alasan `Migrasi SID snapshot status akhir`). Tidak ada transisi/backward — state machine tidak dilanggar.\n\n"
            . "## Catatan mapping kolom\n\n"
            . "- jenis_hp = `type` SID (fallback `barang` bila type kosong; 9 baris fallback) — mengikuti spec D-01 (migrasi lama mengisi brand barang).\n"
            . "- jenis_servis_id tetap apa adanya (migrasi lama = 1; spec: NULL bila kosong — tidak dibuka ulang).\n"
            . "- estimasi_biaya = payload `jumlah` (jasa+spare_part−diskon; 11 baris jumlah=0 valid).\n"
            . "- tanggal_terima sudah terisi migrasi lama (tanggal+jam) → tidak disentuh (guard NULL).\n"
            . "- tanggal_selesai/diambil = `tanggal_kembali`+`jamkembali` (bila selesai/sudahdiambil & tanggal_kembali non-sentinel; 5 baris sentinel → NULL).\n"
            . "- tanggal_bayar = `tgl_bayar` (semua sentinel/0 → NULL).\n"
            . "- pembayaran json `{pembayaran, jt, komisi, kode_kas}`; sid_detail json per spec D-01.\n"
            . "- pelanggan_id guard NULL (semua sudah terisi migrasi lama); nama/telepon_pelanggan diisi dari tabel pelanggan via kode_lama (guard kosong).\n\n"
            . "## Pelanggan unmapped (kode_pelanggan tak ada di pelanggan.kode_lama)\n\n"
            . $this->listLines(array_map(fn ($k, $v) => "`{$k}` ×{$v}", array_keys($this->pelangganUnmapped), array_values($this->pelangganUnmapped)));

        file_put_contents($file, $body);
        $this->info("Laporan: {$file}");
    }

    private function mapLines(): string
    {
        if (! $this->statusMap) {
            return '(kosong)';
        }
        $parts = [];
        foreach ($this->statusMap as $st => $n) {
            $parts[] = "`{$st}` {$n}";
        }

        return implode(' / ', $parts);
    }

    private function listLines(array $lines): string
    {
        if (! $lines) {
            return "- (tidak ada)\n";
        }

        return implode("\n", array_map(fn ($l) => "- {$l}", $lines))."\n";
    }

    private function printSummary(): void
    {
        $this->info("\n=== SUMMARY FASE 4 (D-01/D-02) ===");
        foreach ($this->counts as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->info("\n=== VERIFIKASI (state DB) ===");
        $this->line('  tiket_servis total: '.DB::table('tiket_servis')->count());
        $this->line('  tiket_servis is_migrasi_sid: '.DB::table('tiket_servis')->where('is_migrasi_sid', true)->count());
        $this->line('  tiket_servis non-SID (prefix non "SID-"): '.DB::table('tiket_servis')->where('no_tiket', 'not like', 'SID-%')->count());
        $this->line('  servis_status_log snapshot SID: '.DB::table('servis_status_log')->where('alasan', 'Migrasi SID snapshot status akhir')->count());
        $this->line('  tiket_servis_item total: '.DB::table('tiket_servis_item')->count());
    }
}