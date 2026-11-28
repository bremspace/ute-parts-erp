<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;

class TiketServisSyncObserver
{
    use SyncsModelEvents;

    public function created(TiketServis $tiket): void
    {
        $this->enqueueSync($tiket);
    }

    public function updated(TiketServis $tiket): void
    {
        $this->enqueueSync($tiket);
    }

    public function deleted(TiketServis $tiket): void
    {
        $this->enqueueDeletedSnapshot(
            $tiket,
            'servis.update',
            ['no_tiket' => $tiket->no_tiket],
            cabangId: $tiket->cabang_id,
            logLabel: 'TiketServisSyncObserver:delete'
        );
    }

    private function enqueueSync(TiketServis $tiket): void
    {
        $this->enqueueModelEvent(
            $tiket,
            'servis.update',
            function (TiketServis $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing(['pelanggan', 'jenisServis', 'teknisi']);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'no_tiket' => $model->no_tiket,
                    'cabang_id' => $model->cabang_id,
                    'jenis_servis_id' => $model->jenis_servis_id,
                    'jenis_servis_kode' => $model->jenisServis?->getAttribute('kode'),
                    'jenis_servis_nama' => $model->jenisServis?->getAttribute('nama'),
                    'pelanggan_id' => $model->pelanggan_id,
                    'pelanggan_sync_uuid' => $model->pelanggan?->getAttribute('sync_uuid'),
                    'pelanggan_telepon' => $model->pelanggan?->getAttribute('telepon') ?? $model->telepon_pelanggan,
                    'teknisi_id' => $model->teknisi_id,
                    'teknisi_email' => $model->teknisi?->getAttribute('email'),
                    'nama_pelanggan' => $model->nama_pelanggan,
                    'telepon_pelanggan' => $model->telepon_pelanggan,
                    'jenis_hp' => $model->jenis_hp,
                    'seri_hp' => $model->seri_hp,
                    'keluhan' => $model->keluhan,
                    'kondisi_fisik' => $model->kondisi_fisik,
                    'status' => $model->status,
                    'sumber' => $model->sumber,
                    'estimasi_biaya' => $this->toNullableFloat($model, 'estimasi_biaya'),
                    'dpp' => $this->toNullableFloat($model, 'dpp'),
                    'ppn_nominal' => $this->toNullableFloat($model, 'ppn_nominal'),
                    'ppn_percent' => $this->toNullableFloat($model, 'ppn_percent'),
                    'alasan_estimasi' => $model->alasan_estimasi,
                    'batas_waktu_menit' => $model->batas_waktu_menit,
                    'durasi_garansi_custom' => $model->durasi_garansi_custom,
                    'auto_cancel_overdue' => (bool) ($model->auto_cancel_overdue ?? false),
                    'token_approval' => $model->token_approval,
                    'tanggal_terima' => $this->formatIso8601($model->getAttribute('tanggal_terima')),
                    'tanggal_selesai' => $this->formatIso8601($model->getAttribute('tanggal_selesai')),
                    'tanggal_diambil' => $this->formatIso8601($model->getAttribute('tanggal_diambil')),
                    'catatan_admin' => $model->catatan_admin,
                    'status_pembayaran' => $model->status_pembayaran,
                    'tanggal_bayar' => $this->formatIso8601($model->getAttribute('tanggal_bayar')),
                    'metode_pembayaran' => $model->metode_pembayaran,
                    'no_jurnal_bayar' => $model->no_jurnal_bayar,
                    'offline_pickup_uuid' => $model->offline_pickup_uuid,
                    'is_offline_pickup' => (bool) ($model->is_offline_pickup ?? false),
                    'pickup_synced_at' => $this->formatIso8601($model->getAttribute('pickup_synced_at')),
                    'garansi_berlaku' => (bool) ($model->garansi_berlaku ?? false),
                    'komplain_catatan' => $model->komplain_catatan,
                    'komplain_gratis' => (bool) ($model->komplain_gratis ?? false),
                    'goodwill_amount' => $this->toNullableFloat($model, 'goodwill_amount'),
                    'goodwill_alasan' => $model->goodwill_alasan,
                    'updated_at' => $this->formatIso8601($model->getAttribute('updated_at')) ?? now()->toIso8601String(),
                ];
            },
            cabangId: $tiket->cabang_id,
            logLabel: 'TiketServisSyncObserver'
        );
    }
}
