<?php

namespace App\Modules\Pos\Services;

use App\Modules\Pos\Services\PricingService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;

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
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public function isActiveSesi(): bool
    {
        if (!$this->tableExists($this->sesiTable)) {
            return true; // fallback: fitur belum dimigrasi, jangan blokir POS
        }
        $cabang = session('cabang_id');
        $user = auth()->id();

        return \Illuminate\Support\Facades\DB::table($this->sesiTable)
            ->where('cabang_id', $cabang)
            ->where('status', 'buka')
            ->where(fn ($q) => $q->where('user_id', $user)->orWhereNull('user_id'))
            ->exists();
    }

    public function sesiKasAktif(): ?object
    {
        if (!$this->tableExists($this->sesiTable)) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table($this->sesiTable)
            ->where('cabang_id', session('cabang_id'))
            ->where('status', 'buka')
            ->latest('id')
            ->first();
    }

    public function bukaKas(float $saldoAwal, ?int $cabangId = null, ?int $userId = null): array
    {
        if (!$this->tableExists($this->sesiTable)) {
            throw new \Exception('Modul kas sesi belum aktif (migrasi belum jalan)');
        }

        $cabangId = $cabangId ?? session('cabang_id');
        if (!$cabangId) {
            throw new \Exception('Cabang aktif belum dipilih');
        }

        // Idempotent: kalau sesi buka di cabang, reject
        if ($this->sesiKasAktif()) {
            throw new \Exception('Masih ada kas sesi terbuka di cabang ini');
        }

        $id = \Illuminate\Support\Facades\DB::table($this->sesiTable)->insertGetId([
            'cabang_id' => $cabangId,
            'user_id' => $userId ?? auth()->id(),
            'saldo_awal' => $saldoAwal,
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
        if (!$this->tableExists($this->sesiTable)) {
            throw new \Exception('Modul kas sesi belum aktif');
        }

        $sesi = $this->sesiKasAktif();
        if (!$sesi) {
            throw new \Exception('Tidak ada kas sesi terbuka utk ditutup');
        }

        // saldo sistem = saldo_awal + semua transaksi tunai (debit kas masuk minus kas keluar) selama sesi
        $akumulasi = (float) \Illuminate\Support\Facades\DB::table('transaksi')
            ->where('cabang_id', $sesi->cabang_id)
            ->where('status', 'selesai')
            ->where('created_at', '>=', $sesi->dibuka_at)
            ->where('metode_bayar', 'tunai')
            ->sum('jumlah_bayar');

        $saldoSistem = round((float) $sesi->saldo_awal + $akumulasi, 2);
        $selisih = round($saldoFisik - $saldoSistem, 2);

        \Illuminate\Support\Facades\DB::table($this->sesiTable)
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

        return [
            'saldo_sistem' => $saldoSistem,
            'saldo_fisik' => $saldoFisik,
            'selisih' => $selisih,
        ];
    }

    public function riwayat(?int $cabangId = null): \Illuminate\Support\Collection
    {
        if (!$this->tableExists($this->sesiTable)) {
            return collect();
        }

        return \Illuminate\Support\Facades\DB::table($this->sesiTable)
            ->where('cabang_id', $cabangId ?? session('cabang_id'))
            ->latest('id')
            ->limit(20)
            ->get();
    }

    // Jurnal debit kas masuk (shift) — pakai active NO-op jikalau COA tidak lengkap, tdk memblokir POS
    private function postJurnalKas(int $cabangId, float $nominal, string $deskripsi): void
    {
        try {
            $debitAkun = \App\Modules\Akunting\Models\AkunCOA::where('kode', '110-01')->first();
            $kreditAkun = null;
            if (!$debitAkun) {
                return;
            }
            // kontra akun: "Modal Kas Shift" dibuat jika perlu dgn tipe ekuitas saldo_normal kredit
            $kreditAkun = \App\Modules\Akunting\Models\AkunCOA::firstOrCreate(
                ['kode' => '310-03'],
                ['nama' => 'Modal Kas Shift', 'tipe' => 'ekuitas', 'kelompok' => 'modal_kas', 'saldo_normal' => 'kredit', 'is_active' => true]
            );

            app(\App\Modules\Akunting\Services\JurnalService::class)->post(
                app(\App\Modules\Akunting\Services\JurnalService::class)->generateNoJurnal('kas', $cabangId),
                now(),
                'manual',
                [
                    ['akun_kode' => '110-01', 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => '310-03', 'debit' => 0, 'kredit' => $nominal],
                ],
                $deskripsi,
                $cabangId,
                auth()->id()
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Jurnal kas sesi gagal: ' . $e->getMessage());
        }
    }

    private function postJurnalSelisih(int $cabangId, float $selisih, string $deskripsi): void
    {
        try {
            if (!$this->tableExists($this->jurnalTable)) {
                return;
            }
            // akun Selisih Kas (beban utk selisih minus, pendapatan lain utk plus) — satu akun, saldo normal debit
            $akun = \App\Modules\Akunting\Models\AkunCOA::firstOrCreate(
                ['kode' => '520-06'],
                ['nama' => 'Selisih Kas', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]
            );

            app(\App\Modules\Akunting\Services\JurnalService::class)->post(
                app(\App\Modules\Akunting\Services\JurnalService::class)->generateNoJurnal('kas', $cabangId),
                now(),
                'manual',
                $selisih > 0
                    ? [
                        ['akun_kode' => '110-01', 'debit' => $selisih, 'kredit' => 0],
                        ['akun_kode' => '520-06', 'debit' => 0, 'kredit' => $selisih],
                    ]
                    : [
                        ['akun_kode' => '520-06', 'debit' => abs($selisih), 'kredit' => 0],
                        ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => abs($selisih)],
                    ],
                $deskripsi,
                $cabangId,
                auth()->id()
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Jurnal selisih kas gagal: ' . $e->getMessage());
        }
    }
}