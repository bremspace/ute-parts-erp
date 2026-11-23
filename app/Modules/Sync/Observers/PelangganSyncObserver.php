<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use Illuminate\Support\Facades\Log;

class PelangganSyncObserver
{
    public function saved(Pelanggan $pelanggan): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        try {
            $payload = [
                'id' => $pelanggan->id,
                'nama' => $pelanggan->nama,
                'username' => $pelanggan->username,
                'telepon' => $pelanggan->telepon,
                'email' => $pelanggan->email,
                'alamat' => $pelanggan->alamat,
                'tanggal_lahir' => $pelanggan->tanggal_lahir?->toDateString(),
                'tier_membership_id' => $pelanggan->tier_membership_id,
                'is_reseller' => (bool) $pelanggan->is_reseller,
                'kode_agen' => $pelanggan->kode_agen,
                'referral_kode' => $pelanggan->referral_kode,
                'tipe_konsumen' => $pelanggan->tipe_konsumen,
                'mode_reward_mitra' => $pelanggan->mode_reward_mitra,
                'poin_loyalty' => $pelanggan->poin_loyalty,
                'saldo_piutang' => $pelanggan->saldo_piutang,
                'max_piutang' => $pelanggan->max_piutang,
                'saldo_deposit' => $pelanggan->saldo_deposit,
                'status' => $pelanggan->status,
                'catatan_approval' => $pelanggan->catatan_approval,
                'approved_at' => $pelanggan->approved_at,
                'approved_by' => $pelanggan->approved_by,
                'updated_at' => $pelanggan->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'pelanggan.upsert',
                $pelanggan,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer Pelanggan gagal: '.$e->getMessage());
        }
    }
}
