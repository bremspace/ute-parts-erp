<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\BackofficeSyncRegistry;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Model;

class JurnalHeaderSyncObserver
{
    use SyncsModelEvents;

    private const BACKOFFICE_SUMBER = [
        'manual',
        'payroll',
        'payroll-bayar',
        'asset',
        'depresiasi',
        'disposal',
        'tutup_buku',
        'bagi_hasil',
        'kas',
        'grn',
        'servis',
        'servis-bayar',
        'retur',
        'pembelian',
        'beli',
        'opname',
        'stok_awal',
        'import',
        'komisi',
        'pembayaran',
        'channel',
        'transfer',
        'transfer:out',
        'transfer:in',
    ];

    public function created(JurnalHeader $header): void
    {
        if (! $this->shouldSyncJurnal($header)) {
            return;
        }

        $this->enqueueSync($header);
    }

    public function updated(JurnalHeader $header): void
    {
        if (! $this->shouldSyncJurnal($header)) {
            return;
        }

        if (! $header->wasChanged(['status_approval', 'is_reversed', 'reversed_by_no_jurnal'])) {
            return;
        }

        $this->enqueueSync($header);
    }

    private function shouldSyncJurnal(JurnalHeader $header): bool
    {
        if (in_array($header->sumber, self::BACKOFFICE_SUMBER, true)) {
            return true;
        }

        // Sinkronkan jurnal reversal non-POS (reversal POS sudah ditangani transaksi.void).
        return $header->sumber === 'reversal' && $header->referensi_tipe !== Transaksi::class;
    }

    private function enqueueSync(JurnalHeader $header): void
    {
        $this->enqueueModelEvent(
            $header,
            'jurnal.upsert',
            function (JurnalHeader $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing(['lines.akun', 'user']);
                [$refUuid, $refNatural] = $this->resolveReferensiMetadata($model);

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
                    'referensi_sync_uuid' => $refUuid,
                    'referensi_natural' => $refNatural,
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

    /**
     * @return array{0: string|null, 1: array<string, mixed>}
     */
    private function resolveReferensiMetadata(JurnalHeader $header): array
    {
        $refType = $header->referensi_tipe;
        $refId = $header->referensi_id;

        if (! is_string($refType) || $refType === '' || empty($refId)) {
            return [null, []];
        }

        if (! class_exists($refType) || ! is_subclass_of($refType, Model::class)) {
            return [null, []];
        }

        $query = method_exists($refType, 'withTrashed') ? $refType::withTrashed() : $refType::query();
        if (class_exists(CabangScope::class)) {
            $query->withoutGlobalScope(CabangScope::class);
        }

        $refModel = $query->find($refId);
        if (! $refModel instanceof Model) {
            return [null, []];
        }

        $refUuid = $this->ensureSyncUuid($refModel);
        $natural = [];
        foreach (BackofficeSyncRegistry::naturalKeysForModel($refType) as $col) {
            $val = $refModel->getAttribute($col);
            if ($val !== null && $val !== '') {
                $natural[$col] = $val;
            }
        }

        return [$refUuid, $natural];
    }
}
