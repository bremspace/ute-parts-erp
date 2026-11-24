<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;

class PelangganSyncObserver
{
    use SyncsModelEvents;

    public function created(Pelanggan $pelanggan): void
    {
        $this->enqueueSync($pelanggan);
    }

    public function updated(Pelanggan $pelanggan): void
    {
        $this->enqueueSync($pelanggan);
    }

    private function enqueueSync(Pelanggan $pelanggan): void
    {
        $this->enqueueModelEvent(
            $pelanggan,
            'pelanggan.upsert',
            function (Pelanggan $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing('tierMembership');

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'nama' => $model->nama,
                    'username' => $model->username,
                    'telepon' => $model->telepon,
                    'email' => $model->email,
                    'avatar' => $model->avatar,
                    'alamat' => $model->alamat,
                    'tanggal_lahir' => $this->formatDateString($model->getAttribute('tanggal_lahir')),
                    'tier_membership_id' => $model->tier_membership_id,
                    'tier_membership_kode' => $model->tierMembership?->getAttribute('kode'),
                    'is_reseller' => (bool) ($model->is_reseller ?? false),
                    'kode_agen' => $model->kode_agen,
                    'referral_kode' => $model->referral_kode,
                    'tipe_konsumen' => $model->tipe_konsumen ?? 'retail',
                    'mode_reward_mitra' => $model->mode_reward_mitra,
                    'total_belanja_12bulan' => (float) ($model->getAttribute('total_belanja_12bulan') ?? 0),
                    'poin_loyalty' => (int) ($model->getAttribute('poin_loyalty') ?? 0),
                    'saldo_piutang' => (float) ($model->getAttribute('saldo_piutang') ?? 0),
                    'max_piutang' => $this->toNullableFloat($model, 'max_piutang'),
                    'saldo_deposit' => (float) ($model->getAttribute('saldo_deposit') ?? 0),
                    'status' => $model->status ?? 'aktif',
                    'catatan_approval' => $model->catatan_approval,
                    'approved_at' => $this->formatIso8601($model->getAttribute('approved_at')),
                    'approved_by' => $model->approved_by,
                ];
            },
            logLabel: 'PelangganSyncObserver'
        );
    }
}
