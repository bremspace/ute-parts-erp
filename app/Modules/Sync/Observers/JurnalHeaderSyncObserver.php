<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use Illuminate\Database\Eloquent\Model;

class JurnalHeaderSyncObserver
{
    use SyncsModelEvents;

    private const STANDALONE_SUMBER = [
        'manual',
        'payroll',
        'payroll-bayar',
        'asset',
        'depresiasi',
        'disposal',
    ];

    public function created(JurnalHeader $header): void
    {
        if (! in_array($header->sumber, self::STANDALONE_SUMBER, true)) {
            return;
        }

        $this->enqueueSync($header);
    }

    public function updated(JurnalHeader $header): void
    {
        if (! in_array($header->sumber, self::STANDALONE_SUMBER, true)) {
            return;
        }

        if (! $header->wasChanged(['status_approval', 'is_reversed', 'reversed_by_no_jurnal'])) {
            return;
        }

        $this->enqueueSync($header);
    }

    private function enqueueSync(JurnalHeader $header): void
    {
        if (config('sync.node_role') !== 'primary_store') {
            return;
        }

        $this->enqueueModelEvent(
            $header,
            'jurnal.upsert',
            function (JurnalHeader $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing(['lines.akun', 'user']);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'no_jurnal' => $model->no_jurnal,
                    'cabang_id' => $model->cabang_id,
                    'cabang_key' => $model->cabang_key,
                    'tanggal' => $this->formatIso8601($model->getAttribute('tanggal')),
                    'sumber' => $model->sumber,
                    'deskripsi' => $model->deskripsi,
                    'total_debit' => (float) $model->total_debit,
                    'total_kredit' => (float) $model->total_kredit,
                    'jumlah_baris' => (int) $model->jumlah_baris,
                    'referensi_tipe' => $model->referensi_tipe,
                    'referensi_id' => $model->referensi_id,
                    'idempotency_key' => $model->idempotency_key,
                    'user_id' => $model->user_id,
                    'user_email' => $model->user?->getAttribute('email'),
                    'status_approval' => $model->status_approval ?? 'disetujui',
                    'is_reversed' => (bool) ($model->is_reversed ?? false),
                    'reversed_by_no_jurnal' => $model->reversed_by_no_jurnal,
                    'reversal_of_no_jurnal' => $model->reversal_of_no_jurnal,
                    'reversed_at' => $this->formatIso8601($model->getAttribute('reversed_at')),
                    'approved_at' => $this->formatIso8601($model->getAttribute('approved_at')),
                    'catatan_approval' => $model->catatan_approval,
                    'lines' => $model->lines->map(function (Model $line): array {
                        $akun = $line->getRelationValue('akun');

                        return [
                            'akun_kode' => $akun instanceof Model ? $akun->getAttribute('kode') : null,
                            'akun_nama' => $akun instanceof Model ? $akun->getAttribute('nama') : null,
                            'debit' => (float) ($line->getAttribute('debit') ?? 0),
                            'kredit' => (float) ($line->getAttribute('kredit') ?? 0),
                            'deskripsi' => $line->getAttribute('deskripsi'),
                        ];
                    })->filter(fn (array $line): bool => ! empty($line['akun_kode']))->values()->all(),
                ];
            },
            cabangId: $header->cabang_id,
            logLabel: 'JurnalHeaderSyncObserver'
        );
    }
}
