<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Scopes\CabangScope;
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

    public function deleted(PayrollPeriode $periode): void
    {
        $this->enqueueDeletedSnapshot(
            $periode,
            'payroll.upsert',
            [
                'periode' => $periode->periode,
                'cabang_id' => $periode->cabang_id,
            ],
            cabangId: $periode->cabang_id,
            logLabel: 'PayrollPeriodeSyncObserver:delete'
        );
    }

    private function enqueueSync(PayrollPeriode $periode): void
    {
        $this->enqueueModelEvent(
            $periode,
            'payroll.upsert',
            function (PayrollPeriode $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing(['slips.karyawan', 'slips.komisiDetails.tiketServis']);

                $komisiIds = [];
                $teknisiIds = [];
                foreach ($model->slips as $slip) {
                    $details = $slip->getRelationValue('komisiDetails');
                    if (! is_iterable($details)) {
                        continue;
                    }
                    foreach ($details as $detail) {
                        $kid = $detail->getAttribute('komisi_id');
                        if (! empty($kid)) {
                            $komisiIds[] = (int) $kid;
                        }
                        $tid = $detail->getAttribute('teknisi_id');
                        if (! empty($tid)) {
                            $teknisiIds[] = (int) $tid;
                        }
                    }
                }
                $komisiIds = array_values(array_unique($komisiIds));
                $teknisiIds = array_values(array_unique($teknisiIds));

                $komisiMap = $komisiIds !== []
                    ? Komisi::withoutGlobalScope(CabangScope::class)->whereIn('id', $komisiIds)->get()->keyBy('id')
                    : collect();
                $teknisiMap = $teknisiIds !== []
                    ? Karyawan::withoutGlobalScope(CabangScope::class)->whereIn('id', $teknisiIds)->get()->keyBy('id')
                    : collect();

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'cabang_id' => $model->cabang_id,
                    'periode' => $model->periode,
                    'tanggal_mulai' => $this->formatDateString($model->getAttribute('tanggal_mulai')),
                    'tanggal_selesai' => $this->formatDateString($model->getAttribute('tanggal_selesai')),
                    'status' => $model->status,
                    'catatan' => $model->catatan,
                    'slips' => $model->slips->map(function (Model $slip) use ($komisiMap, $teknisiMap): array {
                        $karyawan = $slip->getRelationValue('karyawan');
                        $details = $slip->getRelationValue('komisiDetails') ?? collect();

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
                            'komisi_details' => $details->map(function (Model $detail) use ($komisiMap, $teknisiMap): array {
                                $tiket = $detail->getRelationValue('tiketServis');
                                $komisi = $komisiMap->get($detail->getAttribute('komisi_id'));
                                $teknisi = $teknisiMap->get($detail->getAttribute('teknisi_id'));

                                return [
                                    'komisi_id' => $detail->getAttribute('komisi_id'),
                                    'komisi_sync_uuid' => $komisi?->getAttribute('sync_uuid'),
                                    'komisi_idempotensi_key' => $komisi?->getAttribute('idempotensi_key'),
                                    'tiket_servis_id' => $detail->getAttribute('tiket_servis_id'),
                                    'tiket_sync_uuid' => $tiket instanceof Model ? $tiket->getAttribute('sync_uuid') : null,
                                    'tiket_no_tiket' => $tiket instanceof Model ? $tiket->getAttribute('no_tiket') : null,
                                    'teknisi_id' => $detail->getAttribute('teknisi_id'),
                                    'teknisi_sync_uuid' => $teknisi?->getAttribute('sync_uuid'),
                                    'teknisi_nik' => $teknisi?->getAttribute('nik'),
                                    'jenis' => $detail->getAttribute('jenis'),
                                    'nominal' => (float) ($detail->getAttribute('nominal') ?? 0),
                                ];
                            })->values()->all(),
                        ];
                    })->all(),
                ];
            },
            cabangId: $periode->cabang_id,
            logLabel: 'PayrollPeriodeSyncObserver'
        );
    }
}
