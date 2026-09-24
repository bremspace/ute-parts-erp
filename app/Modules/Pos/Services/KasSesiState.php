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

        // [F3-8b] Saran clock-out: catat jam_keluar otomatis bila kasir sudah clock-in
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

    // Jurnal debit kas masuk (shift) — pakai active NO-op jikalau COA tidak lengkap, tdk memblokir POS
    private function postJurnalKas(int $cabangId, float $nominal, string $deskripsi): void
    {
        try {
            $debitAkun = AkunCOA::where('kode', '110-01')->first();
            if (! $debitAkun) {
                return;
            }
            // [T-33] Kontra akun: Modal Pemilik (310-01) — sudah exists, firstOrCreate fallback bila hilang
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
            Log::warning('Jurnal kas sesi gagal: '.$e->getMessage());
        }
    }

    private function postJurnalSelisih(int $cabangId, float $selisih, string $deskripsi): void
    {
        try {
            if (! $this->tableExists($this->jurnalTable)) {
                return;
            }
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
            Log::warning('Jurnal selisih kas gagal: '.$e->getMessage());
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
