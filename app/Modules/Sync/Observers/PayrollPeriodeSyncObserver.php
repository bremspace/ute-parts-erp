<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriodeSyncObserver
{
    use SyncsModelEvents;

    private const FINAL_STATUSES = [
        PayrollPeriode::STATUS_SELESAI,
        PayrollPeriode::STATUS_DIBAYAR,
    ];

    public function created(PayrollPeriode $periode): void
    {
        if (! in_array($periode->status, self::FINAL_STATUSES, true)) {
            return;
        }

        $this->enqueueSync($periode);
    }

    public function updated(PayrollPeriode $periode): void
    {
        if (! $periode->wasChanged('status')) {
            return;
        }

        if (! in_array($periode->status, self::FINAL_STATUSES, true)) {
            return;
        }

        $this->enqueueSync($periode);
    }

    private function enqueueSync(PayrollPeriode $periode): void
    {
        $this->enqueueModelEvent(
            $periode,
            'payroll.upsert',
            function (PayrollPeriode $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing('slips.karyawan');

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'cabang_id' => $model->cabang_id,
                    'periode' => $model->periode,
                    'tanggal_mulai' => $this->formatDateString($model->getAttribute('tanggal_mulai')),
                    'tanggal_selesai' => $this->formatDateString($model->getAttribute('tanggal_selesai')),
                    'status' => $model->status,
                    'catatan' => $model->catatan,
                    'slips' => $model->slips->map(function (Model $slip): array {
                        $karyawan = $slip->getRelationValue('karyawan');

                        return [
                            'karyawan_id' => $slip->getAttribute('karyawan_id'),
                            'karyawan_nik' => $karyawan instanceof Model ? $karyawan->getAttribute('nik') : null,
                            'karyawan_email' => $karyawan instanceof Model ? $karyawan->getAttribute('email') : null,
                            'karyawan_nama' => $karyawan instanceof Model ? $karyawan->getAttribute('nama') : null,
                            'gaji_pokok' => (float) ($slip->getAttribute('gaji_pokok') ?? 0),
                            'total_tunjangan' => (float) ($slip->getAttribute('total_tunjangan') ?? 0),
                            'total_potongan' => (float) ($slip->getAttribute('total_potongan') ?? 0),
                            'total_komisi' => (float) ($slip->getAttribute('total_komisi') ?? 0),
                            'total_gaji' => (float) ($slip->getAttribute('total_gaji') ?? 0),
                            'rincian' => $slip->getAttribute('rincian'),
                            'status' => $slip->getAttribute('status'),
                        ];
                    })->all(),
                ];
            },
            cabangId: $periode->cabang_id,
            logLabel: 'PayrollPeriodeSyncObserver'
        );
    }
}
