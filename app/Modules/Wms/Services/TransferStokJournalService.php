<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Wms\Models\StokTransfer;

class TransferStokJournalService
{
    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Catat jurnal transfer persediaan antar-cabang (RAK 190-01 vs Persediaan 130-01).
     *
     * Cabang Asal: Dr RAK Cabang Tujuan (190-01) / Cr Persediaan (130-01)
     * Cabang Tujuan: Dr Persediaan (130-01) / Cr RAK Cabang Asal (190-01)
     *
     * Hanya berlaku jika transfer antar-cabang berbeda ($cabangAsalId !== $cabangTujuanId).
     */
    public function catatJurnalTerimaTransfer(StokTransfer $transfer, ?int $userId = null): void
    {
        $transfer->loadMissing(['gudangAsal', 'gudangTujuan', 'items.produk', 'items.skuVariant']);

        $cabangAsalId = $transfer->gudangAsal?->cabang_id;
        $cabangTujuanId = $transfer->gudangTujuan?->cabang_id;

        // Jika cabang sama atau tidak valid, tidak perlu jurnal RAK
        if (! $cabangAsalId || ! $cabangTujuanId || $cabangAsalId === $cabangTujuanId) {
            return;
        }

        $totalNilai = 0.0;
        foreach ($transfer->items as $item) {
            $hpp = (float) ($item->skuVariant?->harga_beli ?: $item->produk?->harga_beli ?: 0);
            $totalNilai += round($hpp * (float) $item->jumlah, 2);
        }

        if ($totalNilai <= 0) {
            return;
        }

        // Pastikan akun RAK dan Persediaan ada dan aktif
        AkunCOA::firstOrCreate(
            ['kode' => '190-01'],
            [
                'nama' => 'Rekening Antar Cabang (RAK)',
                'tipe' => 'aset',
                'kelompok' => 'rak',
                'saldo_normal' => 'debit',
                'is_active' => true,
            ]
        );

        AkunCOA::firstOrCreate(
            ['kode' => '130-01'],
            [
                'nama' => 'Persediaan Barang Dagang',
                'tipe' => 'aset',
                'kelompok' => 'persediaan',
                'saldo_normal' => 'debit',
                'is_active' => true,
            ]
        );

        $now = now();

        // 1. Jurnal di Cabang Pengirim: Dr RAK 190-01 / Cr Persediaan 130-01
        $noJurnalKirim = $this->jurnalService->generateNoJurnal('transfer', $cabangAsalId);
        $this->jurnalService->post(
            $noJurnalKirim,
            $now,
            'transfer',
            [
                ['akun_kode' => '190-01', 'debit' => $totalNilai, 'kredit' => 0],
                ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalNilai],
            ],
            "Transfer keluar persediaan {$transfer->no_transfer} ke Cabang #{$cabangTujuanId}",
            $cabangAsalId,
            $userId,
            StokTransfer::class,
            $transfer->id
        );

        // 2. Jurnal di Cabang Penerima: Dr Persediaan 130-01 / Cr RAK 190-01
        $noJurnalTerima = $this->jurnalService->generateNoJurnal('transfer', $cabangTujuanId);
        $this->jurnalService->post(
            $noJurnalTerima,
            $now,
            'transfer',
            [
                ['akun_kode' => '130-01', 'debit' => $totalNilai, 'kredit' => 0],
                ['akun_kode' => '190-01', 'debit' => 0, 'kredit' => $totalNilai],
            ],
            "Transfer masuk persediaan {$transfer->no_transfer} dari Cabang #{$cabangAsalId}",
            $cabangTujuanId,
            $userId,
            StokTransfer::class,
            $transfer->id
        );
    }
}
