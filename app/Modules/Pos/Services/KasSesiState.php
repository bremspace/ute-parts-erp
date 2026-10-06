<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Workflow\Services\ApprovalService;
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

    public function bukaKas(float $saldoAwal, ?int $cabangId = null, ?int $userId = null, string $sumber = 'manual', string $akunSumberKode = '110-01'): array
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

        // [KAS-LACI] Validasi sumber dana: akun harus aktif, tipe aset, kelompok kas/bank, bukan Kas Laci
        $akunSumber = AkunCOA::where('kode', $akunSumberKode)->where('is_active', true)->first();

        if (! $akunSumber) {
            throw new \Exception("Akun sumber dana '{$akunSumberKode}' tidak ditemukan atau tidak aktif");
        }

        if (! in_array($akunSumber->kelompok, ['kas', 'bank'], true)) {
            throw new \Exception('Akun sumber dana harus bertipe kas atau bank');
        }

        if ($akunSumber->kode === '110-04') {
            throw new \Exception('Tidak bisa menggunakan Kas Laci sebagai sumber dana');
        }

        // [KAS-LACI] Balance-sufficiency check (scoped per cabang)
        // Bila modul akunting ada dan akun sudah memiliki mutasi jurnal pada cabang ini, saldo harus cukup
        if ($saldoAwal > 0 && $this->tableExists('jurnal_akuntansi')) {
            $hasMutasi = DB::table('jurnal_akuntansi')
                ->where('akun_coa_id', $akunSumber->id)
                ->where('cabang_id', $cabangId)
                ->exists();

            if ($hasMutasi) {
                $saldoQuery = DB::table('jurnal_akuntansi')
                    ->where('akun_coa_id', $akunSumber->id)
                    ->where('cabang_id', $cabangId);
                $debit = (float) $saldoQuery->sum('debit');
                $kredit = (float) $saldoQuery->sum('kredit');
                $saldo = $akunSumber->saldo_normal === 'debit'
                    ? $debit - $kredit
                    : $kredit - $debit;

                // Hanya validasi jika saldo akun tercatat positif tapi tidak mencukupi saldo awal
                if ($saldo > 0 && $saldo < $saldoAwal) {
                    throw new \Exception(
                        "Sumber dana ({$akunSumber->nama}) tidak cukup: saldo Rp ".number_format($saldo, 0, ',', '.').
                        ', dibutuhkan Rp '.number_format($saldoAwal, 0, ',', '.')
                    );
                }
            }
        }

        // [F3-8b] Gate absensi: open kas wajib clock-in aktif (fallback bila modul HR belum migrasi)
        $this->pastikanClockInAktif($userId);

        // [B-10f/P0-4] Fail-closed: baris sesi + jurnal buka kas dalam SATU transaksi.
        // Jurnal gagal → seluruh rollback, sesi tidak pernah ada tanpa jurnal.
        $id = DB::transaction(function () use ($cabangId, $userId, $saldoAwal, $sumber, $akunSumberKode) {
            $id = DB::table($this->sesiTable)->insertGetId([
                'cabang_id' => $cabangId,
                'user_id' => $userId,
                'saldo_awal' => $saldoAwal,
                'sumber' => $sumber,
                'akun_sumber_kode' => $akunSumberKode,
                'status' => 'buka',
                'dibuka_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // [KAS-LACI] Jurnal: D Kas Laci (110-04) / C {sumber dana} — dana berpindah ke laci
            $this->postJurnalKas($cabangId, $saldoAwal, "Buka kas sesi #{$id} — sumber: {$akunSumberKode}", $akunSumberKode, $id, $userId);

            return $id;
        });

        return ['id' => $id, 'cabang_id' => $cabangId, 'saldo_awal' => $saldoAwal];
    }

    public function hitungSaldoSistem(?object $sesi = null): float
    {
        $sesi = $sesi ?: $this->sesiKasAktif();
        if (! $sesi) {
            return 0.0;
        }

        $akumulasiQuery = DB::table('transaksi')
            ->where('cabang_id', $sesi->cabang_id)
            ->where('status', 'selesai')
            ->where('created_at', '>=', $sesi->dibuka_at);

        if ($sesi->user_id) {
            $akumulasiQuery->where('kasir_id', $sesi->user_id);
        }

        $transaksiSesi = $akumulasiQuery->whereIn('metode_bayar', ['tunai', 'split'])->get(['metode_bayar', 'total_akhir', 'split_detail']);

        $akumulasi = 0.0;
        foreach ($transaksiSesi as $trx) {
            if ($trx->metode_bayar === 'tunai') {
                $akumulasi += (float) $trx->total_akhir;
            } elseif ($trx->metode_bayar === 'split' && ! empty($trx->split_detail)) {
                $detail = is_string($trx->split_detail) ? json_decode($trx->split_detail, true) : (array) $trx->split_detail;
                $akumulasi += (float) ($detail['tunai'] ?? 0);
            }
        }

        $mutasiMasuk = 0.0;
        $mutasiKeluar = 0.0;
        if ($this->tableExists('kas_mutasi_laci')) {
            $mutasiMasuk = (float) DB::table('kas_mutasi_laci')
                ->where('kas_sesi_id', $sesi->id)
                ->where('jenis', 'masuk')
                ->sum('nominal');

            $mutasiKeluar = (float) DB::table('kas_mutasi_laci')
                ->where('kas_sesi_id', $sesi->id)
                ->where('jenis', 'keluar')
                ->sum('nominal');
        }

        return round((float) $sesi->saldo_awal + $akumulasi + $mutasiMasuk - $mutasiKeluar, 2);
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

        $saldoSistem = $this->hitungSaldoSistem($sesi);
        $selisih = round($saldoFisik - $saldoSistem, 2);

        $cabangId = (int) $sesi->cabang_id;
        $akunSumberKode = $sesi->akun_sumber_kode ?? '110-01';

        if (abs($selisih) <= 0.01) {
            // [KAS-LACI] Tidak ada selisih: tutup langsung + deposit-back
            return DB::transaction(function () use ($sesi, $saldoSistem, $saldoFisik, $selisih, $cabangId, $akunSumberKode) {
                DB::table($this->sesiTable)->where('id', $sesi->id)->update([
                    'saldo_akhir_sistem' => $saldoSistem,
                    'saldo_akhir_fisik' => $saldoFisik,
                    'selisih' => $selisih,
                    'status' => 'tutup',
                    'ditutup_at' => now(),
                    'updated_at' => now(),
                ]);

                // Deposit-back: D {sumber} / C Kas Laci (laci → sumber)
                $this->postJurnalDepositBack($cabangId, $saldoFisik, "Tutup kas sesi #{$sesi->id} — deposit ke {$akunSumberKode}", $akunSumberKode, $sesi->id, $sesi->user_id);

                $this->saranClockOut($sesi->user_id);

                return ['saldo_sistem' => $saldoSistem, 'saldo_fisik' => $saldoFisik, 'selisih' => $selisih, 'status' => 'tutup'];
            });
        }

        // [KAS-LACI] Ada selisih: menunggu approval owner/superadmin (NO jurnal yet)
        return DB::transaction(function () use ($sesi, $saldoSistem, $saldoFisik, $selisih, $cabangId, $akunSumberKode) {
            DB::table($this->sesiTable)->where('id', $sesi->id)->update([
                'saldo_akhir_sistem' => $saldoSistem,
                'saldo_akhir_fisik' => $saldoFisik,
                'selisih' => $selisih,
                'status' => 'menunggu_approval',
                'updated_at' => now(),
            ]);

            app(ApprovalService::class)->ajukan(
                'selisih_kas',
                (int) $sesi->id,
                $cabangId,
                [
                    'amount' => abs($selisih),
                    'selisih' => $selisih,
                    'saldo_sistem' => $saldoSistem,
                    'saldo_fisik' => $saldoFisik,
                    'saldo_awal' => $sesi->saldo_awal,
                    'kasir_id' => $sesi->user_id,
                    'dibuka_at' => $sesi->dibuka_at,
                    'akun_sumber_kode' => $akunSumberKode,
                ],
                auth()->id() ?? (int) $sesi->user_id
            );

            return [
                'saldo_sistem' => $saldoSistem,
                'saldo_fisik' => $saldoFisik,
                'selisih' => $selisih,
                'status' => 'menunggu_approval',
            ];
        });
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
     * [KAS-LACI] Jurnal buka kas: D Kas Laci (110-04) / C {sumber dana}.
     * Dana berpindah dari sumber (Kas Besar/Bank/dll) ke laci kasir.
     */
    private function postJurnalKas(int $cabangId, float $nominal, string $deskripsi, string $akunSumberKode = '110-01', ?int $sesiId = null, ?int $userId = null): void
    {
        if ($nominal <= 0) {
            return;
        }

        if (! $this->tableExists($this->jurnalTable)) {
            return;
        }

        try {
            AkunCOA::firstOrCreate(
                ['kode' => '110-04'],
                ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
            );

            app(JurnalService::class)->post(
                app(JurnalService::class)->generateNoJurnal('kas', $cabangId),
                now(),
                'manual',
                [
                    ['akun_kode' => '110-04', 'debit' => $nominal, 'kredit' => 0],        // Kas Laci bertambah
                    ['akun_kode' => $akunSumberKode, 'debit' => 0, 'kredit' => $nominal],  // Sumber berkurang
                ],
                $deskripsi,
                $cabangId,
                $userId ?? auth()->id(),
                'kas_sesi',
                $sesiId
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
     * [KAS-LACI] Jurnal selisih kas pada Kas Laci (110-04) / Selisih Kas (520-07).
     * Public: dipanggil dari ApprovalService::selesaikanEntity saat disetujui.
     */
    public function postJurnalSelisih(int $cabangId, float $selisih, string $deskripsi, ?int $sesiId = null, ?int $userId = null): void
    {
        if (! $this->tableExists($this->jurnalTable)) {
            return;
        }

        try {
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
                        ['akun_kode' => '110-04', 'debit' => $selisih, 'kredit' => 0],
                        ['akun_kode' => '520-07', 'debit' => 0, 'kredit' => $selisih],
                    ]
                    : [
                        ['akun_kode' => '520-07', 'debit' => abs($selisih), 'kredit' => 0],
                        ['akun_kode' => '110-04', 'debit' => 0, 'kredit' => abs($selisih)],
                    ],
                $deskripsi,
                $cabangId,
                $userId ?? auth()->id(),
                'kas_sesi',
                $sesiId
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
     * [KAS-LACI] Jurnal deposit-back: D {sumber} / C Kas Laci (110-04).
     * Dana berpindah dari laci kembali ke sumber saat tutup kas.
     */
    private function postJurnalDepositBack(int $cabangId, float $nominal, string $deskripsi, string $akunSumberKode = '110-01', ?int $sesiId = null, ?int $userId = null): void
    {
        if ($nominal <= 0) {
            return;
        }

        if (! $this->tableExists($this->jurnalTable)) {
            return;
        }

        try {
            AkunCOA::firstOrCreate(
                ['kode' => '110-04'],
                ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
            );

            app(JurnalService::class)->post(
                app(JurnalService::class)->generateNoJurnal('kas', $cabangId),
                now(),
                'manual',
                [
                    ['akun_kode' => $akunSumberKode, 'debit' => $nominal, 'kredit' => 0],  // Sumber bertambah
                    ['akun_kode' => '110-04', 'debit' => 0, 'kredit' => $nominal],           // Kas Laci berkurang
                ],
                $deskripsi,
                $cabangId,
                $userId ?? auth()->id(),
                'kas_sesi',
                $sesiId
            );
        } catch (\Throwable $e) {
            Log::error('Jurnal deposit-back gagal', [
                'cabang_id' => $cabangId,
                'nominal' => $nominal,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Gagal memposting jurnal deposit-back: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * [KAS-LACI] Finalisasi tutup kas setelah disetujui oleh owner/superadmin.
     * Dipanggil dari ApprovalService::selesaikanEntity('selisih_kas', 'disetujui').
     */
    public function finalizeTutupKasApproved(int $sesiId): void
    {
        $sesi = DB::table($this->sesiTable)->where('id', $sesiId)->first();
        if (! $sesi || $sesi->status !== 'menunggu_approval') {
            return; // idempotent
        }

        $cabangId = (int) $sesi->cabang_id;
        $akunSumberKode = $sesi->akun_sumber_kode ?? '110-01';

        DB::transaction(function () use ($sesi, $cabangId, $akunSumberKode) {
            if (abs((float) $sesi->selisih) > 0.01) {
                $this->postJurnalSelisih($cabangId, (float) $sesi->selisih, "Selisih kas sesi #{$sesi->id} (disetujui)", $sesi->id, $sesi->user_id);
            }

            $this->postJurnalDepositBack($cabangId, (float) $sesi->saldo_akhir_fisik, "Tutup kas sesi #{$sesi->id} — deposit ke {$akunSumberKode}", $akunSumberKode, $sesi->id, $sesi->user_id);

            DB::table($this->sesiTable)->where('id', $sesi->id)->update([
                'status' => 'tutup',
                'ditutup_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->saranClockOut($sesi->user_id);
    }

    /**
     * [KAS-LACI] Reopen kas sesi setelah ditolak oleh owner/superadmin.
     * Kasir harus recount dan submit ulang.
     */
    public function reopenKasRejected(int $sesiId, ?string $catatan = null): void
    {
        $sesi = DB::table($this->sesiTable)->where('id', $sesiId)->first();
        if (! $sesi || $sesi->status !== 'menunggu_approval') {
            return; // idempotent
        }

        DB::table($this->sesiTable)->where('id', $sesi->id)->update([
            'status' => 'buka',
            'saldo_akhir_sistem' => null,
            'saldo_akhir_fisik' => null,
            'selisih' => null,
            'catatan' => $catatan,
            'ditutup_at' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * [KAS-LACI] Catat mutasi kas masuk / keluar laci di luar transaksi penjualan POS.
     * Membuat baris di kas_mutasi_laci + jurnal akuntansi double-entry yang presisi.
     */
    public function catatMutasiLaci(string $jenis, float $nominal, string $akunLawanKode, string $keterangan, ?int $cabangId = null, ?int $userId = null): array
    {
        if ($nominal <= 0) {
            throw new \Exception('Nominal mutasi kas harus lebih besar dari 0');
        }

        if (! in_array($jenis, ['masuk', 'keluar'], true)) {
            throw new \Exception('Jenis mutasi tidak valid (harus masuk atau keluar)');
        }

        $sesi = $this->sesiKasAktif();
        if (! $sesi) {
            throw new \Exception('Tidak ada sesi kas laci terbuka untuk mencatat mutasi');
        }

        $cabangId = $cabangId ?? (int) $sesi->cabang_id;
        $userId = $userId ?? auth()->id() ?? (int) $sesi->user_id;

        $akunLawan = AkunCOA::where('kode', $akunLawanKode)->where('is_active', true)->first();
        if (! $akunLawan) {
            throw new \Exception("Akun lawan '{$akunLawanKode}' tidak ditemukan atau tidak aktif");
        }

        if ($akunLawanKode === '110-04') {
            throw new \Exception('Akun lawan tidak boleh Kas Laci itu sendiri');
        }

        return DB::transaction(function () use ($sesi, $cabangId, $userId, $jenis, $nominal, $akunLawan, $akunLawanKode, $keterangan) {
            // Pastikan akun Kas Laci ada
            AkunCOA::firstOrCreate(
                ['kode' => '110-04'],
                ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
            );

            $jurnalService = app(JurnalService::class);
            $prefix = $jenis === 'masuk' ? 'KM' : 'KK';
            $noJurnal = $jurnalService->generateNoJurnal($prefix, $cabangId);
            $deskripsiJurnal = 'Kas Laci '.ucfirst($jenis)." — {$akunLawan->nama}: {$keterangan}";

            // Double entry:
            // Masuk:  Debit Kas Laci (110-04) / Kredit Akun Lawan
            // Keluar: Debit Akun Lawan / Kredit Kas Laci (110-04)
            $lines = $jenis === 'masuk'
                ? [
                    ['akun_kode' => '110-04', 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => $akunLawanKode, 'debit' => 0, 'kredit' => $nominal],
                ]
                : [
                    ['akun_kode' => $akunLawanKode, 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => '110-04', 'debit' => 0, 'kredit' => $nominal],
                ];

            $mutasiId = DB::table('kas_mutasi_laci')->insertGetId([
                'kas_sesi_id' => $sesi->id,
                'cabang_id' => $cabangId,
                'user_id' => $userId,
                'jenis' => $jenis,
                'nominal' => $nominal,
                'akun_lawan_kode' => $akunLawanKode,
                'keterangan' => $keterangan,
                'no_jurnal' => $noJurnal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jurnalService->post(
                $noJurnal,
                now(),
                'kas',
                $lines,
                $deskripsiJurnal,
                $cabangId,
                $userId,
                'kas_mutasi_laci',
                $mutasiId
            );

            return [
                'id' => $mutasiId,
                'jenis' => $jenis,
                'nominal' => $nominal,
                'no_jurnal' => $noJurnal,
            ];
        });
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
