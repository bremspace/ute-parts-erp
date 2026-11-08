<?php

namespace App\Modules\Servis\Services;

use App\Models\User;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Models\TiketServisProgress;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ServisService
{
    public function __construct(
        protected NotificationService $notifService
    ) {}

    /**
     * [P1-1/T-01] Nomor tiket race-safe — pola `JurnalService::generateNoJurnal`:
     * transaksi + `lockForUpdate` + `max($suffix) + 1`. LIKE WAJIB menyertakan
     * cabang (SESSION-STATE: counter tanpa cabang berbenturan lintas cabang).
     * Unique index `no_tiket` = jaring pengaman terakhir; pemanggil me-retry
     * duplicate key via `adalahDuplicateKey()`.
     */
    private function buatNoTiket(int $cabangId): string
    {
        $date = now()->format('Ymd');
        $like = sprintf('SRV-C%02d-%s-%%', $cabangId, $date);

        return DB::transaction(function () use ($cabangId, $date, $like) {
            $terpakai = TiketServis::where('no_tiket', 'like', $like)
                ->lockForUpdate()
                ->pluck('no_tiket')
                ->map(fn ($no) => (int) substr((string) $no, strrpos((string) $no, '-') + 1))
                ->all();

            $next = ($terpakai ? max($terpakai) : 0) + 1;

            return sprintf('SRV-C%02d-%s-%04d', $cabangId, $date, $next);
        }, 3);
    }

    /**
     * [P1-1/T-01] Nomor booking online (SRV-ONL-{Ymd}-{seq}) — counter global
     * harian dengan lock yang sama. Cabang booking tidak dipakai dalam nomor
     * (kontrak format tidak diubah).
     */
    private function buatNoTiketOnline(): string
    {
        $date = now()->format('Ymd');
        $like = sprintf('SRV-ONL-%s-%%', $date);

        return DB::transaction(function () use ($date, $like) {
            $terpakai = TiketServis::where('no_tiket', 'like', $like)
                ->lockForUpdate()
                ->pluck('no_tiket')
                ->map(fn ($no) => (int) substr((string) $no, strrpos((string) $no, '-') + 1))
                ->all();

            $next = ($terpakai ? max($terpakai) : 0) + 1;

            return sprintf('SRV-ONL-%s-%04d', $date, $next);
        }, 3);
    }

    /**
     * [P1-1/T-01-AUDIT] Deteksi duplicate-key khusus untuk unique index `no_tiket`.
     *
     * HANYA mendeteksi kode duplicate key spesifik (MySQL 1062, SQLite 19/2067 dengan
     * pelanggaran UNIQUE). SQLSTATE generic 23000 TIDAK dipakai langsung karena juga
     * mencakup foreign key failure (1452) dan NOT NULL (1048) yang tidak boleh di-retry.
     */
    private function adalahDuplicateKey(QueryException $e): bool
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        // MySQL 1062 = ER_DUP_ENTRY
        if ($driverCode === 1062) {
            return true;
        }

        // SQLite error 19 (SQLITE_CONSTRAINT) atau 2067 (SQLITE_CONSTRAINT_UNIQUE)
        if (in_array($driverCode, [19, 2067], true) && str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        return str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * [SERVICE-01] Terima unit servis baru.
     */
    public function terimaUnit(array $data, User $user): TiketServis
    {
        $cabangId = $data['cabang_id'] ?? session('cabang_id') ?? $user->cabangs()->first()?->id;
        if (! $cabangId) {
            throw new \Exception('Cabang aktif belum dipilih');
        }

        // [P2-3/T-06] `cabang_id` eksplisit dari caller (API request / form)
        // wajib lolos akses cabang user — pola `ServisController::resolveCabangId`
        // (`bisaAksesCabang`: cabang aktif + ditugaskan, kecuali role HQ).
        // Dulu: staff bebas membuat tiket lintas cabang lewat parameter.
        if (isset($data['cabang_id']) && ! $user->bisaAksesCabang((int) $data['cabang_id'])) {
            throw new \Exception('Anda tidak memiliki akses ke cabang ini');
        }

        $pelanggan = ! empty($data['pelanggan_id']) ? Pelanggan::find($data['pelanggan_id']) : null;
        $namaPelanggan = $data['nama_pelanggan'] ?? $pelanggan?->nama;
        $teleponPelanggan = $data['telepon_pelanggan'] ?? $pelanggan?->telepon;

        if (! empty($teleponPelanggan)) {
            $clean = preg_replace('/[^0-9+]/', '', (string) $teleponPelanggan);
            if (str_starts_with($clean, '+62')) {
                $clean = '0'.substr($clean, 3);
            } elseif (str_starts_with($clean, '62')) {
                $clean = '0'.substr($clean, 2);
            }
            $teleponPelanggan = $clean;
        }

        // Auto-sync konsumen baru ke database CRM Pelanggan
        if (! $pelanggan && ! empty($namaPelanggan)) {
            $query = Pelanggan::query();
            if (! empty($teleponPelanggan)) {
                $query->where('telepon', $teleponPelanggan);
            } else {
                $query->where('nama', $namaPelanggan);
            }
            $pelanggan = $query->first();

            if (! $pelanggan) {
                $pelanggan = Pelanggan::create([
                    'nama' => $namaPelanggan,
                    'telepon' => $teleponPelanggan ?: null,
                    'tipe_konsumen' => 'retail',
                ]);
            }
        }

        if ($pelanggan) {
            $data['pelanggan_id'] = $pelanggan->id;
            $namaPelanggan = $pelanggan->nama;
            $teleponPelanggan = $pelanggan->telepon ?: $teleponPelanggan;
        }

        // [P1-1/T-01] Race-safe: generation LIKE-locked + retry duplicate-key
        // (unique index `no_tiket` = jaring pengaman terakhir).
        $tiket = null;
        $attempts = 0;

        while ($tiket === null) {
            try {
                $tiket = TiketServis::create([
                    'no_tiket' => $this->buatNoTiket((int) $cabangId),
                    'cabang_id' => $cabangId,
                    'jenis_servis_id' => $data['jenis_servis_id'] ?? null,
                    'pelanggan_id' => $data['pelanggan_id'] ?? null,
                    'nama_pelanggan' => $namaPelanggan,
                    'telepon_pelanggan' => $teleponPelanggan,
                    'jenis_hp' => $data['jenis_hp'],
                    'seri_hp' => $data['seri_hp'] ?? null,
                    // [T-19] kunci gadget: terenkripsi at-rest (cast encrypted)
                    'tipe_kunci' => $data['tipe_kunci'] ?? null,
                    'kunci_terenkripsi' => $data['kunci_terenkripsi'] ?? null,
                    'keluhan' => $data['keluhan'],
                    'kondisi_fisik' => $data['kondisi_fisik'] ?? null,
                    'foto_unit' => $data['foto_unit'] ?? null,
                    'status' => 'diterima',
                    'sumber' => $data['sumber'] ?? 'walkin',
                    'token_approval' => Str::random(64),
                    'tanggal_terima' => now(),
                ]);
            } catch (QueryException $e) {
                if (! $this->adalahDuplicateKey($e) || $attempts++ >= 2) {
                    throw $e;
                }
            }
        }

        $this->logStatus($tiket, null, 'diterima', $user, 'transisi', 'Unit servis diterima');

        $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
        if (filled($telepon)) {
            $this->notifService->kirim(
                'wa',
                $telepon,
                'Unit Servis Diterima',
                "Unit {$tiket->jenis_hp} ({$tiket->no_tiket}) telah kami terima. Pantau perbaikan Anda di: ".url("/tracking/{$tiket->token_approval}"),
                [
                    'tiket_id' => $tiket->id,
                    'pelanggan_id' => $tiket->pelanggan_id,
                    'audience' => 'pelanggan',
                ]
            );
        } else {
            $pelangganId = $tiket->pelanggan_id ?? null;
            if ($pelangganId) {
                $this->notifService->kirim(
                    'inapp',
                    null,
                    'Unit Servis Diterima',
                    "Unit {$tiket->jenis_hp} ({$tiket->no_tiket}) telah diterima di cabang.",
                    [
                        'tiket_id' => $tiket->id,
                        'pelanggan_id' => $pelangganId,
                        'action_url' => '/account',
                        'audience' => 'pelanggan',
                        'kategori' => 'servis',
                    ]
                );
            }
        }

        return $tiket;
    }

    /**
     * [SERVICE-07] Booking servis dari marketplace (public, no auth).
     */
    public function bookingOnline(array $data): TiketServis
    {
        // [P2-4/AUDIT] Cabang penerima booking online WAJIB aktif
        if (! empty($data['cabang_id'])) {
            $cabang = Cabang::where('id', (int) $data['cabang_id'])->where('is_active', true)->first();
            if (! $cabang) {
                throw new \Exception('Cabang yang dipilih tidak aktif atau tidak ditemukan');
            }
            $cabangId = $cabang->id;
        } else {
            $cabangId = Cabang::where('is_active', true)->first()?->id;
            if (! $cabangId) {
                throw new \Exception('Belum ada cabang aktif yang dapat menerima booking servis');
            }
        }

        $pelanggan = ! empty($data['pelanggan_id']) ? Pelanggan::find($data['pelanggan_id']) : null;
        $namaPelanggan = $data['nama'] ?? ($data['nama_pelanggan'] ?? $pelanggan?->nama);
        $teleponPelanggan = $data['telepon'] ?? ($data['telepon_pelanggan'] ?? $pelanggan?->telepon);

        if (! empty($teleponPelanggan)) {
            $clean = preg_replace('/[^0-9+]/', '', (string) $teleponPelanggan);
            if (str_starts_with($clean, '+62')) {
                $clean = '0'.substr($clean, 3);
            } elseif (str_starts_with($clean, '62')) {
                $clean = '0'.substr($clean, 2);
            }
            $teleponPelanggan = $clean;
        }

        // Auto-sync booking konsumen online ke database CRM Pelanggan
        if (! $pelanggan && ! empty($namaPelanggan)) {
            $query = Pelanggan::query();
            if (! empty($teleponPelanggan)) {
                $query->where('telepon', $teleponPelanggan);
            } else {
                $query->where('nama', $namaPelanggan);
            }
            $pelanggan = $query->first();

            if (! $pelanggan) {
                $pelanggan = Pelanggan::create([
                    'nama' => $namaPelanggan,
                    'telepon' => $teleponPelanggan ?: null,
                    'tipe_konsumen' => 'retail',
                ]);
            }
        }

        if ($pelanggan) {
            $data['pelanggan_id'] = $pelanggan->id;
            $namaPelanggan = $pelanggan->nama;
            $teleponPelanggan = $pelanggan->telepon ?: $teleponPelanggan;
        }

        // [P1-1/T-01] Race-safe booking: pola sama dengan terimaUnit().
        $tiket = null;
        $attempts = 0;

        while ($tiket === null) {
            try {
                $tiket = TiketServis::create([
                    'no_tiket' => $this->buatNoTiketOnline(),
                    'cabang_id' => $cabangId,
                    'jenis_servis_id' => $data['jenis_servis_id'] ?? null,
                    'pelanggan_id' => $data['pelanggan_id'] ?? null,
                    'nama_pelanggan' => $namaPelanggan,
                    'telepon_pelanggan' => $teleponPelanggan,
                    'jenis_hp' => $data['jenis_hp'],
                    'seri_hp' => $data['seri_hp'] ?? null,
                    'tipe_kunci' => $data['tipe_kunci'] ?? null,
                    'kunci_terenkripsi' => is_array($data['kunci_terenkripsi'] ?? null)
                        ? implode('-', $data['kunci_terenkripsi'])
                        : ($data['kunci_terenkripsi'] ?? null),
                    'keluhan' => $data['keluhan'],
                    'kondisi_fisik' => $data['kondisi_fisik'] ?? null,
                    'foto_unit' => $data['foto_unit'] ?? null,
                    'catatan_admin' => $data['catatan_booking'] ?? ($data['catatan_admin'] ?? null),
                    'status' => 'diajukan_online',
                    'sumber' => 'online',
                    'token_approval' => Str::random(64),
                ]);
            } catch (QueryException $e) {
                if (! $this->adalahDuplicateKey($e) || $attempts++ >= 2) {
                    throw $e;
                }
            }
        }

        $this->logStatus($tiket, null, 'diajukan_online', null, 'transisi', 'Booking servis online');

        // Notifikasi ke admin cabang (queued)
        $this->notifService->kirim(
            'inapp', null,
            'Servis Online Baru',
            "Booking servis dari {$tiket->nama_pelanggan} untuk {$tiket->jenis_hp} — menunggu konfirmasi diterima",
            ['tiket_id' => $tiket->id, 'audience' => 'staf']
        );

        return $tiket;
    }

    /**
     * [SERVICE-03] Update status tiket (state machine enforced).
     * $user nullable — untuk approval publik via token (tanpa login).
     *
     * [P1-2/T-02] Mode `sumber = 'sistem'`: aksi otomatis dari scheduler SLA.
     * Aktor = SISTEM (user WAJIB null — no masquerade); hanya menarget
     * 'ditolak' dari zona kerja (`dikerjakan|qc`), alasan wajib, log
     * `aksi = 'auto_batal_sistem'`, user_id NULL. Tidak ter-expose HTTP.
     * Selain itu semua jalur identik dengan `manual`.
     *
     * [B-10a / P0-4] Fail-closed: perubahan status + side effect (termasuk
     * jurnal `onSelesai`) dibungkus SATU DB::transaction. Sebelumnya status
     * di-commit duluan lalu jurnal otomatis di-try/catch `Log::warning()` →
     * tiket bisa "selesai" tanpa jurnal sama sekali. Sekarang jurnal gagal =
     * seluruh transaksi rollback, status TIDAK final.
     *
     * [T-09] Status tujuan WAJIB anggota `ServisStateMachine::allStatuses()`
     * SEBELUM apa pun (termasuk sebelum cek transisi/izin override) — tanpa ini
     * `override` admin bisa menulis status ngawur ke DB sehingga tiket hilang
     * dari seluruh alur (Kanban, state machine, jurnal).
     *
     * [P1-1] Baris tiket DIKUNCI (`SELECT ... FOR UPDATE`) di dalam transaksi
     * dan transisi divalidasi ULANG terhadap status yang sudah terkunci.
     * Validasi di luar transaksi membaca snapshot yang bisa basi: dua request
     * paralel bisa dua-duanya lolos validasi lalu dua-duanya menjalankan side
     * effect (jurnal dobel, pembalikan stok dobel). Kunci + validasi ulang
     * membuat request kedua melihat status hasil request pertama.
     *
     * State machine TIDAK berubah: himpunan & aturan transisi tetap sama.
     */
    public function updateStatus(TiketServis $tiket, string $statusBaru, ?User $user = null, string $alasan = '', string $sumber = 'manual'): TiketServis
    {
        $sistemAutoCancel = $sumber === 'sistem';

        // [P1-2/T-02] Kontrak mode sistem — fail-closed, persyaratan eksak:
        if ($sistemAutoCancel) {
            if ($user !== null) {
                throw new \Exception('Mode sistem tidak menerima aktor user — aktor sistem direpresentasikan NULL untuk integritas audit trail');
            }

            if (blank($alasan)) {
                throw new \Exception('Alasan wajib diisi untuk aksi otomatis sistem');
            }

            if ($statusBaru !== 'ditolak') {
                throw new \Exception('Aksi otomatis sistem hanya boleh menargetkan status ditolak');
            }
        }

        // Validasi cepat (fail-early, tanpa transaksi) — pesan ramah untuk UI.
        if (! in_array($statusBaru, ServisStateMachine::allStatuses(), true)) {
            throw new \Exception('Status tidak valid');
        }

        $statusLama = $tiket->status;
        $difasilitasiSistem = $sistemAutoCancel
            && in_array($statusLama, ['dikerjakan', 'qc'], true)
            && $statusBaru === 'ditolak';

        if (! ServisStateMachine::dapatTransisi($statusLama, $statusBaru)
            && ! $difasilitasiSistem
            && (! $user || ! $user->can('servis.override-status'))) {
            throw new \Exception(
                "Transisi dari '{$statusLama}' ke '{$statusBaru}' tidak valid. Hanya admin dengan override yang diizinkan."
            );
        }

        // [P1-1] `&$tiket` — objek hasil lock di dalam transaksi dipropagasikan
        // ke pemanggil supaya status yang terlihat pemanggil = status terkunci.
        DB::transaction(function () use (&$tiket, $statusBaru, $user, $alasan, $sistemAutoCancel, $difasilitasiSistem) {
            $tiket = TiketServis::whereKey($tiket->id)->lockForUpdate()->firstOrFail();

            // Status otoritatif: yang tertulis di DB saat kunci dipegang.
            $statusLama = $tiket->status;

            $difasilitasiSistem = $difasilitasiSistem
                && $sistemAutoCancel
                && in_array($statusLama, ['dikerjakan', 'qc'], true)
                && $statusBaru === 'ditolak';

            // Validasi transisi diulang terhadap status TERKUNCI. Kalau request
            // paralel sudah mengubah status duluan, baris ini yang menolak —
            // bukan lolos diam-diam lalu menjalankan side effect dua kali.
            if ($difasilitasiSistem) {
                $aksi = 'auto_batal_sistem';
            } elseif (ServisStateMachine::dapatTransisi($statusLama, $statusBaru)) {
                $aksi = 'transisi';
            } elseif ($user && $user->can('servis.override-status')) {
                $aksi = 'override';
            } else {
                throw new \Exception(
                    "Transisi dari '{$statusLama}' ke '{$statusBaru}' tidak valid. Hanya admin dengan override yang diizinkan."
                );
            }

            if ($aksi === 'override' && blank($alasan)) {
                throw new \Exception('Alasan wajib diisi saat melakukan override status');
            }

            // [T-13/P0-AUDIT] Guard transisi diambil (serah terima unit).
            // Aturan tetap: tiket 'selesai' (jurnal onSelesai sudah diposting,
            // piutang diakui) WAJIB lunas sebelum unit keluar.
            //
            // [P0-AUDIT/BUG-1] Perbaikan deadlock pembatalan: transisi
            // 'batal' → 'diambil' dulu memaksa status_pembayaran = 'lunas',
            // padahal bayar() menolak tiket berstatus 'batal' → tiket batal
            // yang BELUM pernah selesai TERKUNCI PERMANEN (unit tidak bisa
            // diserahkan, pelanggan dipaksa "bayar" tanpa tagihan sah).
            // Praktik industri + ISO 9001 (8.7): pekerjaan tidak jadi → tidak
            // ada output konform yang ditagih → serah terima TANPA biaya.
            if ($statusBaru === 'diambil' && $statusLama === 'selesai' && $tiket->status_pembayaran !== 'lunas') {
                throw new \Exception('Unit tidak dapat diserahkan/diambil sebelum pembayaran lunas');
            }

            // [P0-AUDIT/BUG-2] Tutup kebocoran searah sebaliknya: dulu 'ditolak'
            // dikecualikan TOTAL dari guard, jadi tiket yang SUDAH pernah
            // 'selesai' (jurnal + piutang terposting) lalu di-override ke
            // 'ditolak'/'batal' bisa diserahkan DENGAN GRATIS — pendapatan
            // hilang. Kini tiket batal/ditolak yang sudah punya jurnal
            // terposting tetap terkunci tanpa lunas; hanya admin pemegang
            // `servis.override-status` dengan alasan (tercatat di AuditService
            // via logStatus) yang dapat melepas unit.
            if ($statusBaru === 'diambil'
                && in_array($statusLama, ['batal', 'ditolak'], true)
                && $tiket->status_pembayaran !== 'lunas'
                && $tiket->tanggal_selesai !== null) {
                $aktor = $user;
                $bolehAltRelease = $aktor
                    && $aktor->can('servis.override-status')
                    && ! blank($alasan);

                if (! $bolehAltRelease) {
                    throw new \Exception(
                        'Tiket ini sudah memiliki tagihan terposting (jurnal selesai). Lunasi tagihan atau lakukan override dengan alasan sebelum menyerahkan unit.'
                    );
                }
            }

            $tiket->update(['status' => $statusBaru]);
            $this->logStatus($tiket, $statusLama, $statusBaru, $user, $aksi, $alasan);

            match ($statusBaru) {
                'menunggu_approval' => $this->onMenungguApproval($tiket),
                'diterima' => $this->onDiterima($tiket),
                'dikerjakan' => $this->onDikerjakan($tiket),
                // [T-03] Pengembalian stok part saat tiket ditolak (belum ada
                // jurnal onSelesai) — lihat onDitolak().
                'ditolak' => $this->onDitolak($tiket),
                'selesai' => $this->onSelesai($tiket),
                'diambil' => $this->onDiambil($tiket),
                default => null,
            };

            // [P1-5] Lepas klaim SN: saat transisi ke 'ditolak' (valid maupun
            // override), atau override mundur melewati 'disetujui' — tanpa ini SN
            // macet status 'servis' selamanya (tidak bisa dijual, tak terlihat di
            // 'tersedia'). lepasServis TIDAK meng-clear tautan riwayat
            // (tiket_servis_id & tiket_servis_item_id tetap utk trace garansi).
            if ($this->perluLepasSn($statusBaru, $aksi)) {
                app(NomorSeriService::class)->lepasServis((int) $tiket->id);
            }
        }, 3);

        // [F1-4] Catat log aktivitas servis berbasis Transaction-Centric + Dual-Indexing di metadata
        app(TransactionActivityLogService::class)->catatServis($tiket, $statusBaru);

        // Notifikasi ke pelanggan — SETELAH commit (jangan kirim bila rollback)
        $labelMap = [
            'diajukan_online' => 'Menunggu Konfirmasi',
            'diterima' => 'Unit Diterima di Cabang',
            'diagnosa' => 'Sedang Didiagnosa Teknisi',
            'menunggu_approval' => 'Menunggu Persetujuan Estimasi Biaya',
            'disetujui' => 'Estimasi Disetujui',
            'dikerjakan' => 'Sedang Diperbaiki',
            'qc' => 'Pemeriksaan Kualitas (QC)',
            'selesai' => 'Perbaikan Selesai (Siap Diambil)',
            'diambil' => 'Unit Telah Diserahkan',
            'ditolak' => 'Estimasi Ditolak',
            'batal' => 'Servis Dibatalkan',
        ];
        $labelStatus = $labelMap[$statusBaru] ?? $statusBaru;
        $trackingUrl = $tiket->token_approval ? url("/tracking/{$tiket->token_approval}") : '';
        $pesanNotif = "Unit {$tiket->jenis_hp} ({$tiket->no_tiket}): {$labelStatus}.";
        if ($trackingUrl) {
            $pesanNotif .= ' Pantau status perbaikan Anda di '.$trackingUrl;
        }

        $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
        if (filled($telepon)) {
            $this->notifService->kirim(
                'wa',
                $telepon,
                'Status Servis Diperbarui',
                $pesanNotif,
                [
                    'tiket_id' => $tiket->id,
                    'status' => $statusBaru,
                    'pelanggan_id' => $tiket->pelanggan_id,
                    'audience' => 'pelanggan',
                ]
            );
        } else {
            $pelangganId = $tiket->pelanggan_id ?? null;
            if ($pelangganId) {
                $this->notifService->kirim(
                    'inapp',
                    null,
                    'Status Servis Diperbarui',
                    $pesanNotif,
                    [
                        'tiket_id' => $tiket->id,
                        'status' => $statusBaru,
                        'pelanggan_id' => $pelangganId,
                        'action_url' => '/account',
                        'audience' => 'pelanggan',
                        'kategori' => 'servis',
                    ]
                );
            }
        }

        return $tiket;
    }

    /**
     * [SERVICE-05] Input estimasi biaya → status menunggu_approval.
     *
     * [T-07] Empat perbaikan (P0-7 + P2 "token di-regenerate"):
     *  1. `$statusLama` ditangkap SEBELUM `update()` — sebelumnya `logStatus()`
     *     menerima `$tiket->status` yang sudah 'menunggu_approval', sehingga
     *     timeline mencatat self-loop `menunggu_approval → menunggu_approval`
     *     dan status ASAL hilang permanen.
     *  2. Status + log + token dibungkus SATU `DB::transaction` dan transisi
     *     divalidasi lewat `ServisStateMachine` (tidak lagi bypass).
     *  3. Token approval TIDAK di-regenerate: hanya dibuat bila masih null
     *     (pola `onMenungguApproval`) supaya link yang sudah dibagikan ke
     *     pelanggan tidak mati saat estimasi diedit ulang.
     *  4. Notifikasi dikirim SETELAH commit (tidak terkirim bila rollback).
     */
    public function setEstimasi(TiketServis $tiket, float $biaya, string $alasan, User $user, array $items = [], ?int $batasWaktuMenit = null, ?bool $autoCancelOverdue = null, ?int $durasiGaransiCustom = null): TiketServis
    {
        $statusLama = $tiket->status;

        $validForEstimate = ['diagnosa', 'menunggu_approval', 'ditolak'];
        if (! in_array($statusLama, $validForEstimate, true)) {
            throw new \Exception("Hanya tiket berstatus 'diagnosa', 'menunggu_approval', atau 'ditolak' yang dapat diestimasi");
        }

        // [T-07] Transisi WAJIB sah menurut state machine (allowlist di atas
        // tetap jadi pesan ramah, bukan sumber kebenaran).
        if (! $this->transisiEstimasiValid($statusLama)) {
            throw new \Exception("Transisi dari '{$statusLama}' ke 'menunggu_approval' tidak valid");
        }

        DB::transaction(function () use ($tiket, $statusLama, &$biaya, $alasan, $user, $items, $batasWaktuMenit, $autoCancelOverdue, $durasiGaransiCustom) {
            if (! empty($items)) {
                TiketServisEstimasiItem::where('tiket_servis_id', $tiket->id)->delete();

                $totalDariItems = 0.0;
                $pricingService = app(PricingService::class);

                foreach ($items as $item) {
                    $tipe = $item['tipe'] ?? 'jasa';
                    $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
                    $harga = isset($item['harga']) ? (float) $item['harga'] : null;
                    $namaItem = $item['nama_item'] ?? '';
                    $produkId = $item['produk_id'] ?? null;
                    $skuVariantId = $item['sku_variant_id'] ?? null;
                    $jenisServisId = $item['jenis_servis_id'] ?? null;

                    if ($tipe === 'part') {
                        $produk = $produkId ? Produk::find($produkId) : null;
                        $variant = $skuVariantId ? SkuVariant::find($skuVariantId) : null;

                        if (! $namaItem && $produk) {
                            $namaItem = $produk->nama;
                        }

                        if ($harga === null && $produk) {
                            $resolved = $this->resolveHargaPartDefault($produk, $tiket->pelanggan, $variant);
                            $harga = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
                        }
                    } elseif ($tipe === 'jasa') {
                        $jenisServis = $jenisServisId ? JenisServis::find($jenisServisId) : null;

                        if (! $namaItem && $jenisServis) {
                            $namaItem = $jenisServis->nama;
                        }

                        if ($harga === null && $jenisServis) {
                            $harga = (float) ($jenisServis->biaya_jasa ?? 0);
                        }
                    }

                    $hargaFinal = max(0, (float) ($harga ?? 0));
                    $subtotal = $qty * $hargaFinal;
                    $totalDariItems += $subtotal;

                    TiketServisEstimasiItem::create([
                        'tiket_servis_id' => $tiket->id,
                        'tipe' => $tipe,
                        'jenis_servis_id' => $jenisServisId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'nama_item' => $namaItem ?: ($tipe === 'part' ? 'Sparepart' : 'Jasa Servis'),
                        'qty' => $qty,
                        'harga' => $hargaFinal,
                        'subtotal' => $subtotal,
                    ]);
                }

                if ($biaya <= 0) {
                    $biaya = $totalDariItems;
                }
            }

            // Batas waktu pengerjaan: prioritas manual input saat estimasi -> master jenis_servis -> default tiket
            $batasWaktuFinal = $batasWaktuMenit;
            if ($batasWaktuFinal === null) {
                $batasWaktuFinal = $tiket->batas_waktu_menit ?: ($tiket->jenisServis?->batas_waktu_menit ?? 120);
            }

            $autoCancelFinal = $autoCancelOverdue !== null
                ? $autoCancelOverdue
                : ($tiket->auto_cancel_overdue ?: ($tiket->jenisServis?->auto_cancel_jika_overdue ?? false));

            $tiketUpdate = [
                'estimasi_biaya' => $biaya,
                'alasan_estimasi' => $alasan,
                'status' => 'menunggu_approval',
                'batas_waktu_menit' => $batasWaktuFinal,
                'auto_cancel_overdue' => $autoCancelFinal,
            ];

            if ($durasiGaransiCustom !== null) {
                $tiketUpdate['durasi_garansi_custom'] = $durasiGaransiCustom;
            }

            $tiket->update($tiketUpdate);

            // Token hanya bila belum ada — link yang sudah dibagikan tetap hidup.
            if (! $tiket->token_approval) {
                $tiket->update(['token_approval' => Str::random(64)]);
            }

            $aksiLog = $statusLama === 'menunggu_approval' ? 'edit_estimasi' : 'transisi';
            $this->logStatus($tiket, $statusLama, 'menunggu_approval', $user, $aksiLog, "Estimasi biaya: Rp {$biaya}");
        }, 3);

        // Notifikasi + link publik approval (SETELAH commit)
        $token = $tiket->token_approval;
        $linkApprove = url("/tracking/{$token}");
        $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
        $pesanEstimasi = "Estimasi biaya perbaikan unit {$tiket->jenis_hp} Anda adalah Rp ".number_format($biaya, 0, ',', '.').". Buka tautan untuk melihat rincian & menyetujui: {$linkApprove}";

        if (filled($telepon)) {
            $this->notifService->kirim(
                'wa',
                $telepon,
                'Estimasi Servis Perlu Approval',
                $pesanEstimasi,
                [
                    'tiket_id' => $tiket->id,
                    'token' => $token,
                    'pelanggan_id' => $tiket->pelanggan_id,
                    'audience' => 'pelanggan',
                ]
            );
        } else {
            $pelangganId = $tiket->pelanggan_id ?? null;
            if ($pelangganId) {
                $this->notifService->kirim(
                    'inapp',
                    null,
                    'Estimasi Servis Perlu Approval',
                    $pesanEstimasi,
                    [
                        'tiket_id' => $tiket->id,
                        'token' => $token,
                        'pelanggan_id' => $pelangganId,
                        'action_url' => '/account',
                        'audience' => 'pelanggan',
                        'kategori' => 'servis',
                    ]
                );
            }
        }

        return $tiket;
    }

    /**
     * [T-07] Apakah tiket berstatus ini boleh di-estimasi (menuju
     * 'menunggu_approval') menurut state machine?
     *
     * - 'diagnosa' → 'menunggu_approval': transisi forward langsung (valid).
     * - 'ditolak' → re-estimasi: state machine mewajibkan hop
     *   'ditolak → diagnosa' dulu, jadi kedua hop diperiksa (bukan bypass).
     */
    private function transisiEstimasiValid(string $statusLama): bool
    {
        if ($statusLama === 'menunggu_approval') {
            return true;
        }

        if (ServisStateMachine::dapatTransisi($statusLama, 'menunggu_approval')) {
            return true;
        }

        return $statusLama === 'ditolak'
            && ServisStateMachine::dapatTransisi('ditolak', 'diagnosa')
            && ServisStateMachine::dapatTransisi('diagnosa', 'menunggu_approval');
    }

    /**
     * Resolusi harga default part servis ke harga reseller/agen.
     */
    public function resolveHargaPartDefault(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null): array
    {
        // 1. Jika pelanggan ada, gunakan resolusi harga sesuai profil pelanggan (retail / member / reseller / agen)
        if ($pelanggan) {
            return app(PricingService::class)->resolve($produk, $pelanggan, $variant);
        }

        // 2. Default part servis tanpa pelanggan spesifik: utamakan tier reseller (jika dikonfigurasi)
        $hargaDasar = $variant && $variant->harga_jual_retail !== null
            ? (float) $variant->harga_jual_retail
            : (float) ($produk->harga_jual_retail ?? $produk->harga_jual ?? 0);

        $query = HargaTier::where('produk_id', $produk->id);
        if ($variant && $variant->id) {
            $query->where(function ($q) use ($variant) {
                $q->whereNull('sku_variant_id')->orWhere('sku_variant_id', $variant->id);
            });
        }
        $rows = $query->get();

        $resellerTier = $rows->first(fn ($r) => $r->tipe_konsumen === 'reseller' || $r->is_reseller === true);
        if ($resellerTier) {
            if ($resellerTier->nominal_tetap !== null && (float) $resellerTier->nominal_tetap > 0) {
                return [
                    'harga' => (float) $resellerTier->nominal_tetap,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => max(0, $hargaDasar - (float) $resellerTier->nominal_tetap),
                    'alasan' => 'Harga Default Part Servis (Reseller)',
                    'tier' => 'Reseller',
                ];
            }
            if ($resellerTier->persen_diskon !== null && (float) $resellerTier->persen_diskon > 0) {
                $diskon = round(($hargaDasar * (float) $resellerTier->persen_diskon) / 100, 2);

                return [
                    'harga' => max(0, $hargaDasar - $diskon),
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => $diskon,
                    'alasan' => "Diskon Reseller {$resellerTier->persen_diskon}%",
                    'tier' => 'Reseller',
                ];
            }
            if ($resellerTier->harga !== null && (float) $resellerTier->harga > 0) {
                return [
                    'harga' => (float) $resellerTier->harga,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => max(0, $hargaDasar - (float) $resellerTier->harga),
                    'alasan' => 'Harga Khusus Reseller',
                    'tier' => 'Reseller',
                ];
            }
        }

        return [
            'harga' => $hargaDasar,
            'harga_dasar' => $hargaDasar,
            'diskon_nominal' => 0.0,
            'potensi_komisi' => 0.0,
            'mode_reward' => 'diskon_langsung',
            'alasan' => 'Harga Retail Standar',
            'tier' => null,
        ];
    }

    /**
     * [SERVICE-06] Publik approve/reject (token auth).
     */
    public function approveByToken(string $token, string $action, string $alasan = ''): TiketServis
    {
        $tiket = TiketServis::where('token_approval', $token)->firstOrFail();

        if ($tiket->status !== 'menunggu_approval') {
            throw new \Exception('Tiket tidak dalam status menunggu approval');
        }

        if ($action === 'approve') {
            return $this->updateStatus($tiket, 'disetujui', null, $alasan);
        } else {
            return $this->updateStatus($tiket, 'ditolak', null, $alasan ?: 'Estimasi ditolak oleh pelanggan');
        }
    }

    /**
     * Input sparepart terpakai → kurangi stok + catat log (jalur LEGACY).
     *
     * [T-08] Guard status dipersempit ke ['disetujui','dikerjakan'] — 'diagnosa'
     * DIHAPUS karena potong stok sebelum pelanggan menyetujui estimasi
     * (P0-8). Jalur form pekerjaan (T-17) tetap menerima 'qc' seperti sedia.
     *
     * [T-08] Produk `sn=true` DITOLAK di jalur ini: jalur legacy tidak pernah
     * klaim SN, jadi unit ber-SN bisa terjual ulang. Pesan mengarahkan teknisi
     * ke form pekerjaan (satu-satunya jalur yang mengklaim SN).
     *
     * [T-05] Anti dobel potong stok dua arah: jalur legacy hanya boleh dipakai
     * bila belum ada item part AKTIF via form pekerjaan (`dibatalkan_at` null).
     *
     * [T-06] `gudang_id` WAJIB-branch: gudang harus milik cabang tiket, bukan
     * sekadar `exists` — kalau tidak, stok cabang B terpotong sementara jurnal
     * masuk cabang A.
     */
    public function inputSparepart(TiketServis $tiket, array $items, int $gudangId, User $user): array
    {
        // [P0-2] Tiket yang SUDAH pernah selesai tidak boleh/item baru. Kalau
        // diizinkan, item masuk tapi jurnal `onSelesai` sudah terlanjur
        // ter-post → pendapatan + HPP hilang (silent unjournaled revenue) dan
        // stok terpotong tanpa jurnal. Dicek sebelum guard status karena
        // override mundur (selesai → dikerjakan) membuat status check lolos.
        if ($tiket->tanggal_selesai !== null) {
            throw new \Exception('Tidak dapat menambah item pada tiket yang sudah pernah diselesaikan');
        }

        if (! in_array($tiket->status, ['disetujui', 'dikerjakan'], true)) {
            throw new \Exception('Input sparepart hanya dapat dilakukan saat status disetujui atau dikerjakan');
        }

        // [T-17/T-05] Anti dobel potong stok: jalur legacy hanya boleh dipakai bila
        // belum ada item part AKTIF via form pekerjaan (TiketServisItem). Bila
        // sudah — tolak. Baris yang sudah dibatalkan (reversal) tidak dihitung.
        if (TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'part')
            ->whereNull('dibatalkan_at')
            ->exists()) {
            throw new \Exception('Item sudah dicatat via form pekerjaan');
        }

        // [T-06] Gudang harus di cabang yang sama dengan tiket
        $this->validasiGudangCabang($gudangId, (int) $tiket->cabang_id);

        $created = [];

        DB::transaction(function () use ($tiket, $items, $gudangId, $user, &$created) {
            foreach ($items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (int) $item['jumlah'];

                // [T-08] Jalur legacy tidak mengklaim SN — tolak produk ber-SN
                // agar unit tidak pernah terjual dua kali.
                if ($produk->sn) {
                    throw new \Exception('Produk bernomor seri harus diinput lewat form pekerjaan');
                }

                // [B-03/P1-3] Deduksi via StokDeductionService — kriteria resolusi baris
                // stok sama dgn jalur lain (varian eksak / fallback kanonik tanpa varian),
                // dilengkapi lockForUpdate + StokLog + StockMutationLog (sinkron channel).
                try {
                    app(StokDeductionService::class)->kurangi(
                        produkId: $produk->id,
                        skuVariantId: $item['sku_variant_id'] ?? null,
                        gudangId: $gudangId,
                        qty: $qty,
                        jenis: 'servis',
                        referensiTipe: TiketServis::class,
                        referensiId: $tiket->id,
                        userId: $user->id,
                        catatan: "Servis {$tiket->no_tiket} — sparepart terpakai",
                    );
                } catch (\Exception $e) {
                    // Pesan lama dipertahankan (format yg dikenali teknisi/UI)
                    if (preg_match('/tersedia: (\d+)/', $e->getMessage(), $m)) {
                        throw new \Exception("Stok sparepart {$produk->nama} tidak mencukupi (tersedia: {$m[1]})");
                    }

                    throw $e;
                }

                $sparepart = ServisSparepart::create([
                    'tiket_servis_id' => $tiket->id,
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'gudang_id' => $gudangId,
                    'jumlah' => $qty,
                    'harga_satuan' => $item['harga_satuan'] ?? (float) $produk->harga_jual_retail,
                    'hpp' => (float) $produk->harga_beli,
                ]);

                $created[] = $sparepart;
            }
        });

        return $created;
    }

    /**
     * [T-17] Input pekerjaan teknisi — split part & jasa via TiketServisItem.
     * Baris tipe=jasa: tidak menyentuh stok. Baris tipe=part: kurangi StokItem 1x + StokLog.
     *
     * @param  array  $items  [['tipe'=>'part|jasa','produk_id'=>?,'sku_variant_id'=>?,'nama_item'=>string,'qty'=>int,'harga'=>float,'gudang_id'=>(wajib utk part)], ...]
     */
    public function inputPekerjaan(TiketServis $tiket, array $items, User $user): array
    {
        // [P0-2] Tiket yang SUDAH pernah selesai tidak boleh/item baru (lihat
        // catatan lengkap di inputSparepart()).
        if ($tiket->tanggal_selesai !== null) {
            throw new \Exception('Tidak dapat menambah item pada tiket yang sudah pernah diselesaikan');
        }

        if (! in_array($tiket->status, ['disetujui', 'dikerjakan', 'qc'], true)) {
            throw new \Exception('Input pekerjaan hanya saat status disetujui / dikerjakan / qc');
        }

        // [T-05/P1-3] Anti dobel potong stok SIMETRIS, tapi hanya berlaku bila
        // payload benar-benar berisi baris tipe 'part':
        //  - payload punya baris 'part' DAN sudah ada sparepart legacy AKTIF → tolak
        //    (kedua jalur bisa memotong stok produk yang sama dua kali).
        //  - payload HANYA baris 'jasa' → IZINKAN. Baris jasa tidak menyentuh
        //    stok, jadi tidak ada risiko potong dobel; menolak payload jasa
        //    membuat teknisi tidak bisa sama sekali mencatat jasa pada tiket
        //    yang kebetulan punya sparepart legacy.
        $payloadAdaPart = collect($items)->contains(
            fn ($item) => ($item['tipe'] ?? 'jasa') === 'part'
        );

        if ($payloadAdaPart && ServisSparepart::where('tiket_servis_id', $tiket->id)
            ->whereNull('dibatalkan_at')
            ->exists()) {
            throw new \Exception('Item part sudah dicatat lewat form sparepart. Untuk menambah jasa, kirim baris tipe jasa saja.');
        }

        $created = [];

        DB::transaction(function () use ($tiket, $items, $user, &$created) {
            foreach ($items as $item) {
                $tipe = $item['tipe'] ?? 'jasa';
                $namaItem = $item['nama_item'];
                $qty = (int) ($item['qty'] ?? 1);
                $harga = (float) ($item['harga'] ?? 0);
                $produk = null;

                if ($tipe === 'part') {
                    $produkId = $item['produk_id'] ?? null;
                    if (! $produkId) {
                        throw new \Exception("Item part '{$namaItem}' wajib pilih produk");
                    }
                    $produk = Produk::findOrFail($produkId);
                    $gudangId = $item['gudang_id'] ?? null;
                    if (! $gudangId) {
                        throw new \Exception("Part '{$namaItem}' wajib pilih gudang");
                    }

                    // [T-06] Gudang wajib di cabang yang sama dengan tiket
                    $this->validasiGudangCabang((int) $gudangId, (int) $tiket->cabang_id);

                    // Deduct stok 1x (fee guard: lockForUpdate di StokDeductionService reklarasi)
                    $this->kurangiStokServis($produk->id, $item['sku_variant_id'] ?? null, (int) $gudangId, $qty, $tiket, $user);
                }

                $tiketItem = TiketServisItem::create([
                    'tiket_servis_id' => $tiket->id,
                    'tipe' => $tipe,
                    'produk_id' => $tipe === 'part' ? ($item['produk_id'] ?? null) : null,
                    'sku_variant_id' => $tipe === 'part' ? ($item['sku_variant_id'] ?? null) : null,
                    'nama_item' => $namaItem,
                    'qty' => $qty,
                    'harga' => $harga,
                    'hpp' => $tipe === 'part' && ($item['produk_id'] ?? null)
                        ? (float) (Produk::find($item['produk_id'])?->harga_beli ?? 0)
                        : 0,
                ]);

                // [F2-3] Produk sn=true → SN wajib: validasi (jumlah == qty, tersedia,
                // cabang aktif) + klaim status 'servis' + tautan tiket & item.
                // Throw → rollback seluruh pekerjaan (stok & item ikut batal).
                if ($tipe === 'part' && $produk?->sn) {
                    app(NomorSeriService::class)->klaimServis(
                        array_values($item['sn'] ?? []),
                        (int) $produk->id,
                        (int) $tiket->cabang_id,
                        $qty,
                        (int) $tiket->id,
                        (int) $tiketItem->id
                    );
                }

                $created[] = $tiketItem;
            }
        });

        return $created;
    }

    /**
     * Membatalkan item pekerjaan (part / jasa) yang sudah dicatat pada tiket aktif.
     * Jika item bertipe part: stok dikembalikan ke gudang asal dan status klaim SN dipulihkan.
     */
    public function batalkanItemPekerjaan(TiketServis $tiket, TiketServisItem $item, User $user, string $alasan = ''): void
    {
        if ($tiket->tanggal_selesai !== null) {
            throw new \Exception('Tidak dapat membatalkan item pada tiket yang sudah diselesaikan');
        }

        if (! in_array($tiket->status, ['disetujui', 'dikerjakan', 'qc'], true)) {
            throw new \Exception('Pembatalan item hanya dapat dilakukan pada status disetujui, dikerjakan, atau qc');
        }

        if ((int) $item->tiket_servis_id !== (int) $tiket->id) {
            throw new \Exception('Item bukan milik tiket servis ini');
        }

        if ($item->dibatalkan_at !== null) {
            throw new \Exception('Item sudah pernah dibatalkan');
        }

        DB::transaction(function () use ($tiket, $item, $user, $alasan) {
            if ($item->tipe === 'part' && $item->produk_id) {
                // Lepas klaim SN jika ada
                NomorSeri::where('tiket_servis_item_id', $item->id)
                    ->update([
                        'status' => NomorSeri::STATUS_TERSEDIA,
                        'tiket_servis_id' => null,
                        'tiket_servis_item_id' => null,
                    ]);

                // Kembalikan stok berdasarkan dediksi StokLog
                foreach ($this->dediksiPartDariStokLog($tiket, $item) as $d) {
                    app(StokDeductionService::class)->kembalikan(
                        produkId: (int) $item->produk_id,
                        skuVariantId: $d['sku_variant_id'],
                        gudangId: (int) $d['gudang_id'],
                        qty: (int) $d['qty'],
                        jenis: 'servis',
                        referensiTipe: TiketServis::class,
                        referensiId: $tiket->id,
                        userId: $user->id,
                        catatan: "Servis {$tiket->no_tiket} — batal item part {$item->nama_item}".($alasan ? " ({$alasan})" : ''),
                    );
                }
            }

            TiketServisItem::where('id', $item->id)->update(['dibatalkan_at' => now()]);
        });
    }

    /**
     * [T-06] Validasi gudang WAJIB berada di cabang yang sama dengan tiket.
     *
     * Sebelum T-06 validasi controller hanya `exists:gudang,id`, jadi part bisa
     * dipotong dari gudang cabang lain sementara jurnal servis masuk ke cabang
     * tiket (stok cabang B berkurang, pembukuan cabang A). Pesan Indonesia.
     */
    private function validasiGudangCabang(int $gudangId, int $cabangIdTiket): void
    {
        $gudang = Gudang::find($gudangId);

        if (! $gudang) {
            throw new \Exception('Gudang tidak ditemukan');
        }

        if ((int) $gudang->cabang_id !== (int) $cabangIdTiket) {
            throw new \Exception('Gudang harus berada di cabang yang sama dengan tiket');
        }
    }

    private function kurangiStokServis(int $produkId, ?int $variantId, int $gudangId, int $qty, TiketServis $tiket, User $user): void
    {
        // [B-03/P1-3] Delegasi ke StokDeductionService — lockForUpdate, kriteria
        // resolusi baris stok konsisten dgn praseleksi, StokLog + StockMutationLog.
        try {
            app(StokDeductionService::class)->kurangi(
                produkId: $produkId,
                skuVariantId: $variantId,
                gudangId: $gudangId,
                qty: $qty,
                jenis: 'servis',
                referensiTipe: TiketServis::class,
                referensiId: $tiket->id,
                userId: $user->id,
                catatan: "Servis {$tiket->no_tiket} — item part",
            );
        } catch (\Exception $e) {
            if (preg_match('/tersedia: (\d+)/', $e->getMessage(), $m)) {
                throw new \Exception("Stok sparepart tidak mencukupi di gudang terpilih (tersedia: {$m[1]})");
            }

            throw $e;
        }
    }

    /**
     * [T-12] Pelunasan pembayaran servis.
     */
    public function bayar(TiketServis $tiket, string $metodePembayaran, User $user, ?string $catatan = null, ?array $splitDetail = null, ?int $transaksiId = null): TiketServis
    {
        return DB::transaction(function () use ($tiket, $metodePembayaran, $user, $catatan, $splitDetail, $transaksiId) {
            $tiket = TiketServis::whereKey($tiket->id)->lockForUpdate()->firstOrFail();

            if (! in_array($tiket->status, ['selesai', 'diambil'], true)) {
                throw new \Exception("Pembayaran hanya dapat dilakukan untuk servis berstatus 'selesai' atau 'diambil'");
            }

            if ($tiket->status_pembayaran === 'lunas') {
                throw new \Exception('Tiket servis sudah lunas');
            }

            $itemsServis = $tiket->items()->whereNull('dibatalkan_at')->get();
            $sparepartsLegacy = $tiket->spareparts()->whereNull('dibatalkan_at')->get();

            $pendapatanJasa = (float) $itemsServis->where('tipe', 'jasa')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $pendapatanPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $jualLegacy = (float) $sparepartsLegacy->sum(fn ($sp) => (float) $sp->harga_satuan * (int) $sp->jumlah);

            // [P2-2/T-05] Fallback estimasi hanya untuk tiket legacy TANPA
            // riwayat item jasa. Jika jasa pernah ada namun semua dibatalkan →
            // jasa = 0 (menagih estimasi = overbill).
            $jasaServis = $pendapatanJasa > 0 || $tiket->punyaRiwayatJasaEksplisit()
                ? $pendapatanJasa
                : (float) ($tiket->estimasi_biaya ?? 0);
            $totalJualSparepart = $pendapatanPart + $jualLegacy;
            $totalTagihan = $jasaServis + $totalJualSparepart;

            $noJurnal = null;
            if ($totalTagihan > 0) {
                $jurnalService = app(JurnalService::class);
                $noJurnal = $jurnalService->generateNoJurnal('servis-bayar', $tiket->cabang_id);

                $debitLines = [];
                if ($metodePembayaran === 'tunai') {
                    $debitLines[] = ['akun_kode' => '110-04', 'debit' => $totalTagihan, 'kredit' => 0]; // Kas Laci
                } elseif ($metodePembayaran === 'split' && $splitDetail) {
                    $nonTunai = (float) ($splitDetail['non_tunai'] ?? 0);
                    $porsiBank = min((float) $totalTagihan, $nonTunai);
                    $porsiKas = max(0.0, (float) $totalTagihan - $porsiBank);

                    if ($porsiKas > 0) {
                        $debitLines[] = ['akun_kode' => '110-04', 'debit' => $porsiKas, 'kredit' => 0];
                    }
                    if ($porsiBank > 0) {
                        $debitLines[] = ['akun_kode' => '110-02', 'debit' => $porsiBank, 'kredit' => 0];
                    }
                } else {
                    // transfer, qris, kartu, online
                    $debitLines[] = ['akun_kode' => '110-02', 'debit' => $totalTagihan, 'kredit' => 0]; // Bank
                }

                $lines = array_merge($debitLines, [
                    ['akun_kode' => '120-01', 'debit' => 0, 'kredit' => $totalTagihan], // Piutang Usaha lunas
                ]);

                $deskripsiJurnal = "Pelunasan servis {$tiket->no_tiket} ({$metodePembayaran})";
                if ($transaksiId) {
                    $trx = Transaksi::find($transaksiId);
                    if ($trx) {
                        $deskripsiJurnal .= " [{$trx->no_transaksi}]";
                    }
                }

                $jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: now(),
                    sumber: 'servis',
                    lines: $lines,
                    deskripsi: $deskripsiJurnal,
                    cabangId: $tiket->cabang_id,
                    userId: $user->id,
                    referensiTipe: TiketServis::class,
                    referensiId: $tiket->id,
                    idempotencyKey: 'servis-bayar:'.$tiket->id,
                );
            }

            // [SUBLEDGER-SYNC] Lunaskan piutang servis bila ada di tabel piutang
            if ($tiket->pelanggan_id && Schema::hasTable('piutang')) {
                $piutang = $this->piutangServis($tiket);
                if ($piutang && $piutang->status === 'belum_lunas') {
                    $piutang->update([
                        'jumlah_dibayar' => $piutang->jumlah,
                        'status' => 'lunas',
                        'transaksi_id' => $transaksiId ?? $piutang->transaksi_id,
                    ]);
                }
            }

            $tiket->update([
                'status_pembayaran' => 'lunas',
                'tanggal_bayar' => now(),
                'metode_pembayaran' => $metodePembayaran,
                'no_jurnal_bayar' => $noJurnal,
                'transaksi_id' => $transaksiId ?? $tiket->transaksi_id,
            ]);

            if ($metodePembayaran === 'split' && $splitDetail) {
                $tunaiNominal = (float) ($splitDetail['tunai'] ?? 0);
                $nonTunaiNominal = (float) ($splitDetail['non_tunai'] ?? 0);
                $metodeNon = strtoupper(str_replace('_', ' ', (string) ($splitDetail['metode_non_tunai'] ?? 'Non-Tunai')));
                $pesanLog = sprintf(
                    'Pelunasan servis (Split - Tunai: Rp %s, %s: Rp %s): Rp %s',
                    number_format($tunaiNominal, 0, ',', '.'),
                    $metodeNon,
                    number_format($nonTunaiNominal, 0, ',', '.'),
                    number_format($totalTagihan, 0, ',', '.')
                );
            } else {
                $pesanLog = 'Pelunasan servis ('.strtoupper($metodePembayaran).'): Rp '.number_format($totalTagihan, 0, ',', '.');
            }
            if ($catatan) {
                $pesanLog .= " - {$catatan}";
            }

            $this->logStatus($tiket, $tiket->status, $tiket->status, $user, 'pembayaran', $pesanLog);

            // Integrasi CRM: catat belanja & poin pelanggan
            if ($tiket->pelanggan_id) {
                app(PelangganService::class)->tambahBelanjaDanPoin($tiket->pelanggan_id, (float) $totalTagihan);
            }

            return $tiket;
        }, 3);
    }

    /**
     * [P1-3/T-03] Subledger AR one-row-per-invoice resolution.
     *
     * Urutan pencarian:
     * 1. Referensi eksak `referensi_tipe + referensi_id` (sumber kebenaran;
     *    dibuat oleh onSelesai sejak migrasi T-03).
     * 2. Fallback `keterangan LIKE` HANYA untuk row legacy yang belum
     *    ter-backfill oleh migrasi — scope cabang tetap dipertahankan.
     */
    private function piutangServis(TiketServis $tiket): ?Piutang
    {
        $exact = Piutang::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->first();

        if ($exact) {
            return $exact;
        }

        return Piutang::where('keterangan', 'like', "%{$tiket->no_tiket}%")
            ->where('cabang_id', $tiket->cabang_id)
            ->first();
    }

    /**
     * Catat progress pengerjaan tiket oleh teknisi (dengan opsi foto & tampilkan ke tracking konsumen).
     */
    public function catatProgress(
        TiketServis $tiket,
        User $user,
        int $persentase,
        string $judul,
        ?string $deskripsi = null,
        array $foto = [],
        bool $tampilkanKeKonsumen = true
    ): TiketServisProgress {
        return DB::transaction(function () use ($tiket, $user, $persentase, $judul, $deskripsi, $foto, $tampilkanKeKonsumen) {
            $progress = TiketServisProgress::create([
                'tiket_servis_id' => $tiket->id,
                'user_id' => $user->id,
                'persentase' => min(100, max(0, $persentase)),
                'judul' => $judul,
                'deskripsi' => $deskripsi,
                'foto' => $foto,
                'tampilkan_ke_konsumen' => $tampilkanKeKonsumen,
            ]);

            $this->logStatus(
                $tiket,
                $tiket->status,
                $tiket->status,
                $user,
                'update_progress',
                "Progress {$persentase}%: {$judul}"
            );

            // Jika ditampilkan ke konsumen & konsumen punya no WA, kirim notifikasi singkat
            $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
            if ($tampilkanKeKonsumen && filled($telepon) && $tiket->token_approval) {
                $trackingUrl = url("/tracking/{$tiket->token_approval}");
                $pesan = "Progress Pengerjaan Servis {$tiket->no_tiket} ({$tiket->jenis_hp}): {$persentase}% - {$judul}. Pantau selengkapnya di {$trackingUrl}";
                $this->notifService->kirim('wa', $telepon, 'Pembaruan Pengerjaan Servis', $pesan);
            }

            return $progress;
        });
    }

    /**
     * Get tiket by token_approval (public tracking).
     */
    public function getByToken(string $token): TiketServis
    {
        return TiketServis::with(['garansi', 'spareparts.produk', 'jenisservis', 'pelanggan'])
            ->where('token_approval', $token)
            ->firstOrFail();
    }

    /**
     * Cek apakah tracking servis diizinkan:
     * 1. Staf backoffice login di web
     * 2. Pelanggan terkait login di web (id atau nomor HP cocok)
     * 3. Tiket tidak memiliki nomor HP terdaftar (< 5 digit)
     * 4. Session telah diverifikasi untuk tiket/token ini
     */
    public function isTrackingAuthorized(TiketServis $tiket): bool
    {
        if (auth('web')->check()) {
            return true;
        }

        $ticketPhone = (string) ($tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon ?? '');
        $ticketDigits = preg_replace('/\D/', '', $ticketPhone);

        if (strlen($ticketDigits) < 5) {
            return true;
        }

        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            if ($tiket->pelanggan_id && (int) $tiket->pelanggan_id === (int) $customer->id) {
                return true;
            }

            $custDigits = preg_replace('/\D/', '', (string) $customer->telepon);
            if ($custDigits !== '' && substr($custDigits, -5) === substr($ticketDigits, -5)) {
                return true;
            }
        }

        if (session('tracking_verified_'.$tiket->token_approval) === true) {
            return true;
        }

        return false;
    }

    /**
     * Verifikasi 5 digit terakhir nomor HP tiket servis.
     */
    public function verifyTrackingPhone(TiketServis $tiket, string $input): bool
    {
        $ticketPhone = (string) ($tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon ?? '');
        $ticketDigits = preg_replace('/\D/', '', $ticketPhone);

        if (strlen($ticketDigits) < 5) {
            return true;
        }

        $inputDigits = preg_replace('/\D/', '', $input);
        if (strlen($inputDigits) < 5) {
            return false;
        }

        return substr($ticketDigits, -5) === substr($inputDigits, -5);
    }

    // --- Private side-effect methods ---

    /**
     * [P1-5] Apakah SN wajib dilepas pada transisi ini?
     * - 'ditolak': selalu (valid dari menunggu_approval / override dari mana pun).
     * - override ke status di luar zona kerja (disetujui/dikerjakan/qc): klaim SN
     *   tidak lagi sah utk tiket ini (mundur melewati disetujui — atau melompat
     *   lewati selesai, krn onSelesai tidak jalan). Idempoten — no-op bila tiket
     *   ini tidak menahan SN status 'servis'.
     */
    private function perluLepasSn(string $statusBaru, string $aksi): bool
    {
        if ($statusBaru === 'ditolak') {
            return true;
        }

        return $aksi === 'override'
            && ! in_array($statusBaru, ['disetujui', 'dikerjakan', 'qc'], true);
    }

    private function onDikerjakan(TiketServis $tiket): void
    {
        $batasMenit = $tiket->batas_waktu_menit ?: ($tiket->jenisServis?->batas_waktu_menit ?? 120);
        $mulai = now();
        $deadline = (clone $mulai)->addMinutes($batasMenit);

        $tiket->update([
            'batas_waktu_menit' => $batasMenit,
            'pengerjaan_mulai_at' => $tiket->pengerjaan_mulai_at ?: $mulai,
            'pengerjaan_deadline_at' => $deadline,
        ]);

        // Notifikasi ke teknisi jika ditugaskan
        if ($tiket->teknisi_id && $tiket->teknisi) {
            $pesanTeknisi = "Halo {$tiket->teknisi->name}, Anda mulai mengerjakan servis {$tiket->no_tiket} ({$tiket->jenis_hp}). Batas waktu pengerjaan: {$batasMenit} menit (Deadline: {$deadline->format('H:i')}). Pantau progres dan unggah pembaruan secara berkala.";
            if ($tiket->teknisi->phone ?? false) {
                $this->notifService->kirim('wa', $tiket->teknisi->phone, 'Tugas Pengerjaan Servis Dimulai', $pesanTeknisi);
            }
        }
    }

    private function onMenungguApproval(TiketServis $tiket): void
    {
        if (! $tiket->token_approval) {
            $tiket->update(['token_approval' => Str::random(64)]);
        }
    }

    private function onDiterima(TiketServis $tiket): void
    {
        if (! $tiket->tanggal_terima) {
            $tiket->update(['tanggal_terima' => now()]);
        }
    }

    /**
     * [T-03] Tiket `ditolak` → kembalikan stok semua part yang terpasang.
     *
     * Syarat pembalikan (fail-safe):
     * - `tanggal_selesai` masih null → jurnal `onSelesai` BELUM pernah dipost,
     *   jadi HPP 510-02 / Persediaan 130-01 belum pernah/cmasuk. Bila jurnal
     *   sudah ada (mis. override mundur setelah selesai), pembalikan stok akan
     *   membuat HPP dobel → karena itu dilewati.
     * - hanya baris part AKTIF (`dibatalkan_at` null): reversal kedua tidak
     *   mungkin menggandakan stok.
     *
     * Baris yang dibalik lalu DITANDAI `dibatalkan_at` (bukan dihapus) supaya
     * tetap bisa ditrace dan tidak ikut dihitung ulang saat jurnal `onSelesai`
     * (T-04) maupun guard anti-dobel (T-05).
     */
    private function onDitolak(TiketServis $tiket): void
    {
        // Jurnal onSelesai sudah pernah dipost → HPP sudah tercatat, jangan balik stok.
        if ($tiket->tanggal_selesai) {
            return;
        }

        $this->restoreStokPartDitolak($tiket);
    }

    /**
     * [T-03] Kembalikan stok part ke gudang ASAL + tandai baris dibatalkan.
     *
     * Sumber qty per baris:
     * - `ServisSparepart` (legacy): `gudang_id` tercatat di baris item.
     * - `TiketServisItem` tipe part (T-17): tabel tidak punya kolom `gudang_id`,
     *   jadi gudang + qty diambil dari `StokLog` dediksi tiket ini
     *   (jenis 'servis', referensi TiketServis) — FAKTA aktual potong stok,
     *   sehingga part yang dibuat tanpa pengurangan (mis. impor/tes) otomatis
     *   tidak mengembalikan apa pun.
     *
     * Pengembalian memakai `StokDeductionService::kembalikan()` (lockForUpdate +
     * StokLog pembalik + StockMutationLog) supaya hasilnya persis ke gudang asal.
     */
    private function restoreStokPartDitolak(TiketServis $tiket): void
    {
        // [P1-1] `lockForUpdate()` — dua request "tolak" paralel untuk tiket yang
        // sama tidak boleh sama-sama membaca baris part yang belum dibatalkan:
        // tanpa lock keduanya akan mengembalikan stok dua kali (stok hantu).
        // Dipanggil dari `onDitolak()` di dalam transaksi `updateStatus()`.
        $itemsPart = TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'part')
            ->whereNull('dibatalkan_at')
            ->lockForUpdate()
            ->get();

        $spareparts = ServisSparepart::where('tiket_servis_id', $tiket->id)
            ->whereNull('dibatalkan_at')
            ->lockForUpdate()
            ->get();

        if ($itemsPart->isEmpty() && $spareparts->isEmpty()) {
            return; // no-op aman (mis. ditolak dari menunggu_approval tanpa part)
        }

        // Kunci unik per (produk, gudang, varian) — qty yang SUDAH dikembalikan
        // ikut dikurangi supaya tidak ada produk sama yang balik dua kali walau
        // data lama tercampur (legacy + T-17) atau satu produk diinput 2 baris.
        $dikembalikan = [];
        $target = function (int $produkId, ?int $variantId, int $gudangId, int $qty) use (&$dikembalikan, $tiket): void {
            $key = $produkId.'|'.$gudangId.'|'.($variantId ?? 'null');
            $sudah = $dikembalikan[$key] ?? 0;
            $sisa = $qty - $sudah;

            if ($sisa <= 0) {
                return;
            }

            $dikembalikan[$key] = $sudah + $sisa;

            app(StokDeductionService::class)->kembalikan(
                produkId: $produkId,
                skuVariantId: $variantId,
                gudangId: $gudangId,
                qty: $sisa,
                jenis: 'servis',
                referensiTipe: TiketServis::class,
                referensiId: $tiket->id,
                userId: auth()->id(),
                catatan: "Servis {$tiket->no_tiket} — pembalikan part (ditolak)",
            );
        };

        // 1) Legacy: gudang asal tercatat di baris item.
        foreach ($spareparts as $sp) {
            $target((int) $sp->produk_id, $sp->sku_variant_id, (int) $sp->gudang_id, (int) $sp->jumlah);
        }

        // 2) T-17: gudang + qty dari StokLog dediksi tiket ini.
        foreach ($itemsPart as $item) {
            foreach ($this->dediksiPartDariStokLog($tiket, $item) as $d) {
                $target((int) $item->produk_id, $d['sku_variant_id'], $d['gudang_id'], $d['qty']);
            }
        }

        // Tandai baris dibatalkan (query builder, bukan mass-assignment model:
        // kolom `dibatalkan_at` sengaja tidak ditambahkan ke $fillable).
        TiketServisItem::whereIn('id', $itemsPart->modelKeys())->update(['dibatalkan_at' => now()]);
        ServisSparepart::whereIn('id', $spareparts->modelKeys())->update(['dibatalkan_at' => now()]);
    }

    /**
     * [T-03/P0-1] Rekap NET outflow satu item part dari `StokLog` tiket ini.
     *
     * [P0-1] Rekap memakai saldo NET (outflow + inflow), bukan hanya outflow.
     * Filter `perubahan < 0` membuat siklus `ditolak → re-estimasi → tambah
     * part → ditolak` mengembalikan SELURUH outflow historis sekali lagi: log
     * siklus-1 sudah +2 (pengembalian), tapi filter mengabaikan +2 sehingga
     * outflow siklus-2 (-1)Tetap dihitung penuh → stok hantu. Dengan saldo
     * net, log (-2, +2, -1) = -1 → hanya 1 yang dikembalikan.
     *
     * @return array<int, array{gudang_id:int, sku_variant_id:?int, qty:int}>
     */
    private function dediksiPartDariStokLog(TiketServis $tiket, TiketServisItem $item): array
    {
        if (! $item->produk_id) {
            return [];
        }

        // Sengaja TANPA `where('perubahan', '<', 0)`: baris pengembalian dari
        // siklus ditolak sebelumnya harus ikut terhitung agar saldonya netral.
        $query = StokLog::where('jenis', 'servis')
            ->where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->where('produk_id', $item->produk_id);

        if ($item->sku_variant_id !== null) {
            $query->where('sku_variant_id', $item->sku_variant_id);
        }

        return $query->get()
            ->groupBy(fn (StokLog $log) => ((int) $log->gudang_id).'|'.($log->sku_variant_id === null ? 'null' : (int) $log->sku_variant_id))
            ->map(function ($logs) {
                $first = $logs->first();

                // Net outflow = -(total perubahan). Negatif/0 = stok sudah
                // utuh (belum pernah dipotong, atau sudah dikembalikan penuh).
                $netOutflow = -1 * (int) $logs->sum('perubahan');

                return [
                    'gudang_id' => (int) $first->gudang_id,
                    'sku_variant_id' => $first->sku_variant_id === null ? null : (int) $first->sku_variant_id,
                    'qty' => $netOutflow,
                ];
            })
            // [P0-1] Hanya kembalikan bila net outflow benar-benar > 0.
            ->filter(fn (array $d) => $d['qty'] > 0)
            ->values()
            ->all();
    }

    private function onSelesai(TiketServis $tiket): void
    {
        // [T-01] Guard idempotensi. `tanggal_selesai` terisi = jurnal onSelesai
        // pernah dipost. Skenario `selesai → override mundur ke qc → selesai`
        // akan memunculkan onSelesai DUA kali; tanpa guard ini pendapatan & kas
        // ter-post dua kali (unique index no_jurnal tidak menolong karena
        // generateNoJurnal selalu memberi nomor baru).
        $sudahDiposting = $tiket->tanggal_selesai !== null;

        // [P0-2] Fail-closed untuk item yang masuk SETELAH jurnal pernah dipost.
        // Input part/jasa setelah `tanggal_selesai` terisi tidak ikut dalam
        // jurnal mana pun (jurnal onSelesai hanya jalan sekali) dan tidak ada
        // jalur jurnal susulan otomatis → pendapatan hilang tanpa pembukuan.
        // Guard input-side (inputPekerjaan/inputSparepart) menutup jalur ini
        // sebagai jaring pengaman, tapi data lama/import langsung tetap bisa
        // memunculkan state ini, jadi `onSelesai` juga menolak (melempar =
        // rollback transaksi, status tidak final) alih-alih diam-diam meloloskan.
        if ($sudahDiposting) {
            $itemSetelahSelesai = TiketServisItem::where('tiket_servis_id', $tiket->id)
                ->whereNull('dibatalkan_at')
                ->where('created_at', '>', $tiket->tanggal_selesai)
                ->exists();

            if ($itemSetelahSelesai) {
                throw new \RuntimeException(
                    'Tiket sudah pernah diselesaikan. Penambahan item baru setelah selesai memerlukan penyesuaian jurnal manual.'
                );
            }
        }

        if (! $sudahDiposting) {
            $selesaiAt = now();
            $mulaiAt = $tiket->pengerjaan_mulai_at;
            $durasiMenit = $mulaiAt ? (int) $mulaiAt->diffInMinutes($selesaiAt) : null;
            $deadline = $tiket->pengerjaan_deadline_at;

            $statusSla = 'on_time';
            if ($deadline && $selesaiAt->greaterThan($deadline)) {
                $statusSla = 'overdue';
            }

            $tiket->update([
                'tanggal_selesai' => $selesaiAt,
                'pengerjaan_selesai_at' => $selesaiAt,
                'durasi_pengerjaan_menit' => $durasiMenit,
                'status_sla_kpi' => $statusSla,
            ]);
        }

        // [F2-3] SN terkait tiket ini: kembali 'tersedia' — tautan riwayat
        // (tiket_servis_id & tiket_servis_item_id) TETAP disimpan utk trace garansi.
        app(NomorSeriService::class)->lepasServis((int) $tiket->id);

        $itemsServis = $tiket->items()->whereNull('dibatalkan_at')->get();
        $sparepartsLegacy = $tiket->spareparts()->whereNull('dibatalkan_at')->get();

        $hasPart = $itemsServis->where('tipe', 'part')->isNotEmpty()
            || $sparepartsLegacy->isNotEmpty()
            || $tiket->estimasiItems()->where('tipe', 'part')->exists();

        // Aturan garansi servis: jika pengerjaan HANYA jasa tanpa part/sparepart, tidak ada kartu garansi (durasi 0 / skip garansi)
        // Kecuali jika ada durasi garansi custom eksplisit (> 0)
        $adaGaransiCustom = $tiket->durasi_garansi_custom !== null && (int) $tiket->durasi_garansi_custom > 0;

        if ($hasPart || $adaGaransiCustom) {
            $durasiHari = 30; // default
            if ($tiket->durasi_garansi_custom !== null && (int) $tiket->durasi_garansi_custom >= 0) {
                $durasiHari = (int) $tiket->durasi_garansi_custom;
            } elseif ($tiket->jenisServis) {
                $durasiHari = $tiket->jenisServis->durasi_garansi_hari;
            }

            if ($durasiHari > 0) {
                Garansi::updateOrCreate(
                    ['tiket_servis_id' => $tiket->id],
                    [
                        'durasi_hari' => $durasiHari,
                        'tanggal_mulai' => now()->toDateString(),
                        'tanggal_berakhir' => now()->addDays($durasiHari)->toDateString(),
                        'keterangan' => "Garansi {$durasiHari} hari — Servis {$tiket->no_tiket}",
                    ]
                );
            } else {
                Garansi::where('tiket_servis_id', $tiket->id)->delete();
            }
        } else {
            // Hanya pengerjaan jasa tanpa part & tanpa custom: tidak terbit garansi
            Garansi::where('tiket_servis_id', $tiket->id)->delete();
        }

        // [T-01] Jurnal (dan komisi) sudah pernah dipost untuk tiket ini →
        // jangan post lagi. `tanggal_selesai` TIDAK ditimpa (tetap waktu selesai
        // yang pertama), garansi di atas `updateOrCreate` (idempoten).
        if ($sudahDiposting) {
            return;
        }

        // Jurnal akuntansi otomatis (PRD §4.6):
        // - Pendapatan Jasa Servis (420-01) kredit = estimasi biaya
        // - HPP sparepart terpakai (510-02) debit, Persediaan (130-01) kredit
        // - Kas (110-01) debit = total tagihan (jasa + sparepart)
        //
        // [B-10a / P0-4] 4 baris jurnal TIDAK diubah. Yang berubah: kegagalan
        // jurnal tidak lagi ditelan `Log::warning()` — exception dilempar lagi
        // supaya transaction `updateStatus()` rollback dan status 'selesai'
        // tidak pernah final tanpa jurnal (fail-closed).
        try {
            $jurnalService = app(JurnalService::class);

            // [P2-2/T-05] Fallback estimasi hanya untuk tiket legacy TANPA
            // riwayat item jasa (lihat bayar()).
            $jasaServis = $tiket->punyaRiwayatJasaEksplisit()
                ? 0.0
                : (float) ($tiket->estimasi_biaya ?? 0);

            // [T-04] Baris part yang SUDAH dibatalkan (reversal saat tiket
            // ditolak) TIDAK ikut dihitung — kalau tidak, HPP 510-02 & Persediaan
            // 130-01 dobel-credit pada siklus ditolak → re-estimasi → selesai.
            $itemsServis = $tiket->items()->whereNull('dibatalkan_at')->get();
            $sparepartsLegacy = $tiket->spareparts()->whereNull('dibatalkan_at')->get();

            // [T-17/T-05] Kedua sumber DIABUKAN (bukan cabang if/else): legacy
            // tetap ikut dihitung walau `items()` tidak kosong, dan tidak ada
            // double-count karena guard anti-dobel T-05 membuat satu produk
            // tidak mungkin tercatat di kedua jalur sekaligus.
            $pendapatanJasa = (float) $itemsServis->where('tipe', 'jasa')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $pendapatanPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $hppPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->hpp * (int) $i->qty);

            // Nilai jual sparepart (pendapatan penjualan sparepart) & HPP
            $jualLegacy = (float) $sparepartsLegacy->sum(fn ($sp) => (float) $sp->harga_satuan * (int) $sp->jumlah);
            $hppLegacy = (float) $sparepartsLegacy->sum(fn ($sp) => (float) $sp->hpp * (int) $sp->jumlah);

            $totalJualSparepart = $pendapatanPart + $jualLegacy;
            $totalHpp = $hppPart + $hppLegacy;

            // jasa dari items lebih akurat dari estimasi jika diisi
            if ($pendapatanJasa > 0) {
                $jasaServis = $pendapatanJasa;
            }

            $totalTagihan = $jasaServis + $totalJualSparepart;

            if ($totalTagihan > 0) {
                $noJurnal = $jurnalService->generateNoJurnal('servis', $tiket->cabang_id);

                $lines = [
                    ['akun_kode' => '120-01', 'debit' => $totalTagihan, 'kredit' => 0], // Piutang Usaha diakui saat servis selesai
                    ['akun_kode' => '420-01', 'debit' => 0, 'kredit' => $jasaServis], // Pendapatan jasa servis
                ];

                if ($totalJualSparepart > 0) {
                    $lines[] = ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $totalJualSparepart]; // Pendapatan penjualan sparepart
                }
                if ($totalHpp > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0]; // HPP sparepart
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp]; // Persediaan turun (biaya perolehan)
                }

                $jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: now(),
                    sumber: 'servis',
                    lines: $lines,
                    deskripsi: "Jurnal servis {$tiket->no_tiket}",
                    cabangId: $tiket->cabang_id,
                    userId: auth()->id(),
                    referensiTipe: TiketServis::class,
                    referensiId: $tiket->id,
                    // [T-01] Kunci idempotensi stabil per tiket: ikat ke unique
                    // index jurnal_header (cabang_key, idempotency_key) sehingga
                    // posting ganda ditegakkan MESIN DB, bukan hanya cek aplikasi.
                    idempotencyKey: 'servis:'.$tiket->id,
                );

                // [SUBLEDGER-SYNC] Catat piutang servis ke subledger piutang agar selaras dengan akun 120-01
                if ($tiket->pelanggan_id && Schema::hasTable('piutang')) {
                    $existingPiutang = $this->piutangServis($tiket);
                    if (! $existingPiutang) {
                        $countPiutang = Piutang::where('cabang_id', $tiket->cabang_id)
                            ->whereDate('created_at', now()->toDateString())
                            ->count() + 1;
                        Piutang::create([
                            'no_piutang' => sprintf('AR-SRV-%s-%04d', now()->format('Ymd'), $countPiutang),
                            'pelanggan_id' => $tiket->pelanggan_id,
                            'transaksi_id' => $tiket->transaksi_id,
                            'cabang_id' => $tiket->cabang_id,
                            'jumlah' => $totalTagihan,
                            'jumlah_dibayar' => 0,
                            'jatuh_tempo' => now()->addDays(14)->toDateString(),
                            'status' => 'belum_lunas',
                            'keterangan' => "Piutang Servis {$tiket->no_tiket} ({$tiket->jenis_hp})",
                            // [P1-3/T-03] Referensi eksplisit — matching pelunasan
                            // tidak lagi bergantung pada keterangan LIKE.
                            'referensi_tipe' => TiketServis::class,
                            'referensi_id' => $tiket->id,
                        ]);
                    }
                }

                // Komisi reseller: jika servis milik reseller, hitung komisi
                if ($tiket->pelanggan?->is_reseller) {
                    $komisiService = app(KomisiService::class);
                    $komisiService->hitungKomisiDariItems(
                        $tiket->pelanggan,
                        [
                            [
                                'kategori' => 'Servis',
                                'subtotal' => $jasaServis,
                                'jumlah' => 1,
                            ],
                        ],
                        [
                            'jumlah_transaksi' => $totalTagihan,
                            'keterangan' => 'Komisi dari servis '.$tiket->no_tiket,
                        ]
                    );
                }

                // [F3-8c] Komisi multi-aktor trigger tiket_servis (teknisi via
                // engine baru; reseller servis tetap alur lama di atas — idempotent)
                app(KomisiService::class)->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);
            }
        } catch (\Throwable $e) {
            // [B-10a / P0-4] Fail-closed: catat untuk investigasi, lalu lempar
            // lagi. `updateStatus()` membungkus status + side effect dalam satu
            // DB::transaction sehingga jurnal gagal = status TIDAK berubah.
            Log::error("Jurnal otomatis servis gagal: {$e->getMessage()}", [
                'tiket' => $tiket->no_tiket,
            ]);

            throw new \RuntimeException(
                "Jurnal servis {$tiket->no_tiket} gagal diposting ({$e->getMessage()}). Status tidak diubah.",
                0,
                $e
            );
        }
    }

    private function onDiambil(TiketServis $tiket): void
    {
        $tiket->update(['tanggal_diambil' => now()]);
    }

    private function logStatus(
        TiketServis $tiket,
        ?string $dari,
        string $ke,
        ?User $user,
        string $aksi,
        string $alasan
    ): void {
        ServisStatusLog::create([
            'tiket_servis_id' => $tiket->id,
            'status_dari' => $dari,
            'status_ke' => $ke,
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'alasan' => $alasan,
        ]);

        // Audit: override status wajib tercatat (PRD §6)
        if ($aksi === 'override') {
            app(AuditService::class)->catat(
                'TiketServis', 'override', $tiket->id,
                "Override status {$dari} → {$ke} pada {$tiket->no_tiket}: {$alasan}",
                ['status' => $dari], ['status' => $ke]
            );
        }

        // [P1-2/T-02] Aksi otomatis sistem selalu tercatat eksplisit sebagai
        // aktor SISTEM (bukan dikunci ke user acak) — ISO 9001 §7.5.3.
        if ($aksi === 'auto_batal_sistem') {
            app(AuditService::class)->catat(
                'TiketServis', 'auto_batal_sistem', $tiket->id,
                "Sistem (scheduler SLA) membatalkan otomatis {$tiket->no_tiket}: {$alasan}",
                ['status' => $dari], ['status' => $ke]
            );
        }
    }
}
