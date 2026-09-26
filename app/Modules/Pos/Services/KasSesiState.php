<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * [T-09] Kas Sesi — buka/tutup kas shift, sinkron jurnal akunting.
 *
 * [B-10f/P0-4] Fail-closed: sesi kas TIDAK boleh final tanpa jurnal.
 * - bukaKas  : insert sesi + jurnal buka kas dalam SATU DB::transaction;
 * - tutupKas : update status 'tutup' + jurnal selisih dalam SATU DB::transaction;
 * - kegagalan jurnal dilempar (Indonesia) — Log::error, bukan Log::warning —
 *   sehingga rollback mengembalikan sesi ke kondisi sebelum (tidak ada sesi /
 *   status tetap 'buka').
 *
 * Kontrak API (dipakai PosController, PosKasir, Dashboard, Akunting):
 * bukaKas() / tutupKas() tetap melempar \Exception (kedua consumer sudah
 * try-catch), dan sesiKasAktif() tidak berubah.
 */
class KasSesiState
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    protected string $sesiTable = 'kas_sesi';

    protected string $jurnalTable = 'jurnal_akuntansi';

    protected string $transaksiTable = 'transaksi';

    public function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public function isActiveSesi(): bool
    {
        if (! $this->tableExists($this->sesiTable)) {
            return true; // fallback: fitur belum dimigrasi, jangan blokir POS
        }
        $cabang = session('cabang_id');
        $user = auth()->id();

        return DB::table($this->sesiTable)
            ->where('cabang_id', $cabang)
            ->where('status', 'buka')
            ->where(fn ($q) => $q->where('user_id', $user)->orWhereNull('user_id'))
            ->exists();
    }

    public function sesiKasAktif(): ?object
    {
        if (! $this->tableExists($this->sesiTable)) {
            return null;
        }

        // [T-33] Multi-kasir: sesi aktif per kasir (atau sesi bersama legacy user_id NULL)
        return DB::table($this->sesiTable)
            ->where('cabang_id', session('cabang_id'))
            ->where('status', 'buka')
            ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhereNull('user_id'))
            ->latest('id')
            ->first();
    }

    public function bukaKas(float $saldoAwal, ?int $cabangId = null, ?int $userId = null, string $sumber = 'manual'): array
    {
        if (! $this->tableExists($this->sesiTable)) {
            throw new \Exception('Modul kas sesi belum aktif (migrasi belum jalan)');
        }

        $cabangId = $cabangId ?? session('cabang_id');
        if (! $cabangId) {
            throw new \Exception('Cabang aktif belum dipilih');
        }

        // [T-33] Sumber saldo awal: manual | legacy | carryover (whitelist, fallback manual)
        $sumber = in_array($sumber, ['manual', 'legacy', 'carryover'], true) ? $sumber : 'manual';

        // Idempotent [T-33]: reject hanya jika kasir ini (atau sesi bersama) masih punya sesi buka
        $userId = $userId ?? auth()->id();
        $adaSesiBuka = DB::table($this->sesiTable)
            ->where('cabang_id', $cabangId)
            ->where('status', 'buka')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
            ->exists();

        if ($adaSesiBuka) {
            throw new \Exception('Masih ada kas sesi terbuka untuk kasir ini');
        }

        // [F3-8b] Gate absensi: open kas wajib clock-in aktif (fallback bila modul HR belum migrasi)
        $this->pastikanClockInAktif($userId);

        // [B-10f/P0-4] Fail-closed: baris sesi + jurnal buka kas dalam SATU transaksi.
        // Jurnal gagal → seluruh rollback, sesi tidak pernah ada tanpa jurnal.
        $id = DB::transaction(function () use ($cabangId, $userId, $saldoAwal, $sumber) {
            $id = DB::table($this->sesiTable)->insertGetId([
                'cabang_id' => $cabangId,
                'user_id' => $userId,
                'saldo_awal' => $saldoAwal,
                'sumber' => $sumber,
                'status' => 'buka',
                'dibuka_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Jurnal: Debit Kas = saldo awal utk shift (kontra akun Modal Kas di-tracking via saldo sesi)
            $this->postJurnalKas($cabangId, $saldoAwal, "Buka kas sesi #{$id}");

            return $id;
        });

        return ['id' => $id, 'cabang_id' => $cabangId, 'saldo_awal' => $saldoAwal];
    }

    public function tutupKas(float $saldoFisik): array
    {
        if (! $this->tableExists($this->sesiTable)) {
            throw new \Exception('Modul kas sesi belum aktif');
        }

        $sesi = $this->sesiKasAktif();
        if (! $sesi) {
            throw new \Exception('Tidak ada kas sesi terbuka utk ditutup');
        }

        // saldo sistem = saldo_awal + semua transaksi tunai (debit kas masuk minus kas keluar) selama sesi
        // [T-33] Multi-kasir: akumulasi scoped ke kasir sesi (transaksi.kasir_id = kas_sesi.user_id),
        //        sesi bersama (user_id NULL) tetap pakai scope cabang-wide.
        $akumulasiQuery = DB::table('transaksi')
            ->where('cabang_id', $sesi->cabang_id)
            ->where('status', 'selesai')
            ->where('created_at', '>=', $sesi->dibuka_at)
            ->where('metode_bayar', 'tunai');

        if ($sesi->user_id) {
            $akumulasiQuery->where('kasir_id', $sesi->user_id);
        }

        $akumulasi = (float) $akumulasiQuery->sum('jumlah_bayar');

        $saldoSistem = round((float) $sesi->saldo_awal + $akumulasi, 2);
        $selisih = round($saldoFisik - $saldoSistem, 2);

        // [B-10f/P0-4] Fail-closed: status 'tutup' + jurnal penyesuaian selisih
        // dalam SATU transaksi. Jurnal gagal → rollback → sesi TETAP 'buka'
        // (kas tidak pernah "tertutup" tanpa jurnal).
        DB::transaction(function () use ($sesi, $saldoSistem, $saldoFisik, $selisih) {
            DB::table($this->sesiTable)
                ->where('id', $sesi->id)
                ->update([
                    'saldo_akhir_sistem' => $saldoSistem,
                    'saldo_akhir_fisik' => $saldoFisik,
                    'selisih' => $selisih,
                    'status' => 'tutup',
                    'ditutup_at' => now(),
                    'updated_at' => now(),
                ]);

            // Jurnal penyesuaian selisih → akun Selisih Kas (debit/kredit)
            if (abs($selisih) > 0.01) {
                $this->postJurnalSelisih($sesi->cabang_id, $selisih, "Tutup kas sesi #{$sesi->id}");
            }
        });

        // [F3-8b] Saran clock-out: catat jam_keluar otomatis bila kasir sudah clock-in
        // (di luar transaksi — non-blocking, tidak boleh menggagalkan tutup kas)
        $this->saranClockOut($sesi->user_id);

        return [
            'saldo_sistem' => $saldoSistem,
            'saldo_fisik' => $saldoFisik,
            'selisih' => $selisih,
        ];
    }

    public function riwayat(?int $cabangId = null, int $limit = 20): Collection
    {
        if (! $this->tableExists($this->sesiTable)) {
            return collect();
        }

        return DB::table($this->sesiTable)
            ->where('cabang_id', $cabangId ?? session('cabang_id'))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * [B-10f/P0-4] Fail-closed. Jurnal debit kas masuk (shift) TIDAK lagi ditelan:
     * kegagalan dilempar (Indonesia) supaya transaksi pemanggil rollback.
     * Dua kondisi yang BUKAN kegagalan (tidak melempar):
     * - saldo awal 0 → tidak ada jurnal sama sekali (bukan 0,2 baris);
     * - modul akunting belum dimigrasi → environment check, sama seperti
     *   tableExists($sesiTable) di atas (tetap fail-closed setelah migrasi jalan).
     */
    private function postJurnalKas(int $cabangId, float $nominal, string $deskripsi): void
    {
        if ($nominal <= 0) {
            return; // saldo awal 0 → tidak ada nilai untuk diposting
        }

        if (! $this->tableExists($this->jurnalTable)) {
            return; // modul akunting belum dimigrasi — jangan blokir POS
        }

        try {
            // [B-10f/P0-4] Akun Kas (110-01) & Modal Pemilik (310-01) firstOrCreate
            // (pola 520-07 di postJurnalSelisih) supaya jurnal tetap bisa balance
            // walau COA belum di-seed lengkap.
            AkunCOA::firstOrCreate(
                ['kode' => '110-01'],
                ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
            );
            AkunCOA::firstOrCreate(
                ['kode' => '310-01'],
                ['nama' => 'Modal Pemilik', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit', 'is_active' => true]
            );

            app(JurnalService::class)->post(
                app(JurnalService::class)->generateNoJurnal('kas', $cabangId),
                now(),
                'manual',
                [
                    ['akun_kode' => '110-01', 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => $nominal],
                ],
                $deskripsi,
                $cabangId,
                auth()->id()
            );
        } catch (\Throwable $e) {
            Log::error('Jurnal kas sesi gagal — sesi kas dibatalkan (fail-closed)', [
                'cabang_id' => $cabangId,
                'nominal' => $nominal,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Gagal memposting jurnal buka kas: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * [B-10f/P0-4] Fail-closed. Jurnal selisih kas TIDAK lagi ditelan (lihat
     * postJurnalKas). Exception dilempar supaya status sesi tidak final.
     */
    private function postJurnalSelisih(int $cabangId, float $selisih, string $deskripsi): void
    {
        if (! $this->tableExists($this->jurnalTable)) {
            return; // modul akunting belum dimigrasi — jangan blokir tutup kas
        }

        try {
            // [T-33] akun Selisih Kas (520-07, beban, saldo normal debit) — sudah exists, firstOrCreate fallback
            AkunCOA::firstOrCreate(
                ['kode' => '520-07'],
                ['nama' => 'Selisih Kas', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]
            );

            app(JurnalService::class)->post(
                app(JurnalService::class)->generateNoJurnal('kas', $cabangId),
                now(),
                'manual',
                $selisih > 0
                    ? [
                        ['akun_kode' => '110-01', 'debit' => $selisih, 'kredit' => 0],
                        ['akun_kode' => '520-07', 'debit' => 0, 'kredit' => $selisih],
                    ]
                    : [
                        ['akun_kode' => '520-07', 'debit' => abs($selisih), 'kredit' => 0],
                        ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => abs($selisih)],
                    ],
                $deskripsi,
                $cabangId,
                auth()->id()
            );
        } catch (\Throwable $e) {
            Log::error('Jurnal selisih kas gagal — sesi kas TIDAK ditutup (fail-closed)', [
                'cabang_id' => $cabangId,
                'selisih' => $selisih,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Gagal memposting jurnal selisih kas: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * [F3-8b] Gate absensi: buka kas wajib clock-in aktif hari ini.
     * Hanya berlaku utk user yang terdaftar sebagai karyawan aktif;
     * fallback lembut bila modul HR belum dimigrasi (jangan blokir POS).
     */
    private function pastikanClockInAktif(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        if (! $this->tableExists('absensi_log') || ! $this->tableExists('karyawan')) {
            return;
        }

        $karyawanId = DB::table('karyawan')
            ->where('user_id', $userId)
            ->where('status_aktif', true)
            ->value('id');

        if (! $karyawanId) {
            return; // bukan karyawan → tidak diblokir
        }

        $sudahClockIn = DB::table('absensi_log')
            ->where('karyawan_id', $karyawanId)
            ->where('tanggal', now()->toDateString())
            ->whereNotNull('jam_masuk')
            ->exists();

        if (! $sudahClockIn) {
            throw new \Exception('Wajib clock-in absensi sebelum membuka kas');
        }
    }

    /**
     * [F3-8b] Saran clock-out: isi jam_keluar otomatis saat tutup kas bila
     * kasir belum clock-out (non-blocking).
     */
    private function saranClockOut(?int $userId): void
    {
        try {
            if (! $userId) {
                return;
            }

            if (! $this->tableExists('absensi_log') || ! $this->tableExists('karyawan')) {
                return;
            }

            $karyawanId = DB::table('karyawan')
                ->where('user_id', $userId)
                ->where('status_aktif', true)
                ->value('id');

            if (! $karyawanId) {
                return;
            }

            $log = DB::table('absensi_log')
                ->where('karyawan_id', $karyawanId)
                ->where('tanggal', now()->toDateString())
                ->whereNotNull('jam_masuk')
                ->whereNull('jam_keluar')
                ->first();

            if ($log) {
                DB::table('absensi_log')->where('id', $log->id)->update([
                    'jam_keluar' => now()->format('H:i:s'),
                    'catatan' => trim(($log->catatan ?? '').' | Clock-out otomatis saat tutup kas', ' |'),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Saran clock-out gagal: '.$e->getMessage());
        }
    }
}
