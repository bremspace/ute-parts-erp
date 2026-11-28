<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\BackofficeSyncRegistry;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Sync\Services\SyncExecutionGuard;
use App\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer generik bidireksional untuk seluruh model backoffice pada BackofficeSyncRegistry.
 * Tanpa guard `node_role` — perubahan di Toko maupun Cloud VPS sama-sama diantrekan
 * ke outbox sambil menyertakan referensi FK (`_refs`) agar aman dari kolisi auto-increment ID.
 */
class BackofficeModelSyncObserver
{
    use SyncsModelEvents;

    private const ARRAY_CAST_TYPES = [
        'array',
        'json',
        'object',
        'collection',
        AsArrayObject::class,
        AsCollection::class,
    ];

    /**
     * Snapshot sementara sebelum model + cascade FK dihapus agar metadata `_refs`
     * tetap akurat saat di-enqueue setelah commit.
     *
     * @var \WeakMap<Model, array{payload: array<string, mixed>, cabang_id: int|null}>
     */
    private \WeakMap $pendingDeleteSnapshots;

    public function __construct()
    {
        $this->pendingDeleteSnapshots = new \WeakMap;
    }

    public function created(Model $model): void
    {
        $this->enqueueGenericUpsert($model);
    }

    public function updated(Model $model): void
    {
        $this->enqueueGenericUpsert($model);
    }

    public function deleting(Model $model): void
    {
        if (SyncExecutionGuard::isSuppressed() || ! config('sync.enabled')) {
            return;
        }

        $this->pendingDeleteSnapshots[$model] = $this->buildDeleteSnapshot($model);
    }

    public function deleted(Model $model): void
    {
        $table = $model->getTable();
        $snapshot = $this->pendingDeleteSnapshots[$model] ?? $this->buildDeleteSnapshot($model);
        unset($this->pendingDeleteSnapshots[$model]);

        $this->enqueueModelEvent(
            $model,
            "{$table}.delete",
            static fn (): array => $snapshot['payload'],
            cabangId: $snapshot['cabang_id'],
            logLabel: "BackofficeSync({$table}:delete)",
            refreshBeforeBuild: false
        );
    }

    /**
     * @return array{payload: array<string, mixed>, cabang_id: int|null}
     */
    private function buildDeleteSnapshot(Model $model): array
    {
        $def = BackofficeSyncRegistry::tables()[$model->getTable()] ?? [];

        $payload = [
            'id' => $model->getKey(),
            'sync_uuid' => $model->getAttribute('sync_uuid'),
            'deleted_at' => now()->toIso8601String(),
        ];

        // Sertakan natural key bila ada agar delete tetap cocok bila sync_uuid belum pernah tersinkron.
        foreach ($def['unique'] ?? [] as $cols) {
            foreach ($cols as $col) {
                $val = $model->getAttribute($col);
                if ($val !== null && $val !== '') {
                    $payload[$col] = $val;
                }
            }
        }

        $refs = $this->buildForeignKeyRefs($model, $model->getAttributes());
        if ($refs !== []) {
            $payload['_refs'] = $refs;
        }

        return [
            'payload' => $payload,
            'cabang_id' => $this->resolveCabangId($model),
        ];
    }

    private function enqueueGenericUpsert(Model $model): void
    {
        $table = $model->getTable();
        $cabangId = $this->resolveCabangId($model);

        $this->enqueueModelEvent(
            $model,
            "{$table}.upsert",
            function (Model $m): array {
                $syncUuid = $this->ensureSyncUuid($m);
                $attributes = $m->getAttributes();

                foreach (BackofficeSyncRegistry::SENSITIVE_COLUMNS as $col) {
                    unset($attributes[$col]);
                }

                // Dekode kolom JSON/array cast agar penerima tidak mengalami double-JSON-encoding.
                foreach ($attributes as $col => $val) {
                    if ($m->hasCast($col, self::ARRAY_CAST_TYPES)) {
                        $attributes[$col] = $m->getAttribute($col);
                    }
                }

                if ($syncUuid !== null) {
                    $attributes['sync_uuid'] = $syncUuid;
                }

                $refs = $this->buildForeignKeyRefs($m, $attributes);
                if ($refs !== []) {
                    $attributes['_refs'] = $refs;
                }

                return $attributes;
            },
            cabangId: $cabangId,
            logLabel: "BackofficeSync({$table})"
        );
    }

    /**
     * Ekstrak metadata referensi (`sync_uuid` + natural keys) untuk setiap FK pada model,
     * sehingga penerima dapat me-remapping ID lintas node tanpa terbentur selisih PK.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, array<string, mixed>>
     */
    private function buildForeignKeyRefs(Model $model, array $attributes): array
    {
        $table = $model->getTable();
        $def = BackofficeSyncRegistry::tables()[$table] ?? [];
        $fkDefs = array_merge(
            BackofficeSyncRegistry::foreignKeyDefinitions(),
            $def['extra_fks'] ?? []
        );

        $refs = [];

        foreach ($fkDefs as $col => $fkDef) {
            if (! array_key_exists($col, $attributes) || $attributes[$col] === null || $attributes[$col] === '') {
                continue;
            }

            $ref = $this->resolveSingleRef($fkDef['model'], $attributes[$col], $fkDef['natural'] ?? []);
            if ($ref !== null) {
                $refs[$col] = $ref;
            }
        }

        foreach ($def['poly_fks'] ?? [] as $col => $polySpec) {
            if (! array_key_exists($col, $attributes) || $attributes[$col] === null || $attributes[$col] === '') {
                continue;
            }

            $targetClass = BackofficeSyncRegistry::resolvePolymorphicModelClass($polySpec, $attributes);
            if ($targetClass === null) {
                continue;
            }

            $natural = BackofficeSyncRegistry::naturalKeysForModel($targetClass);
            $ref = $this->resolveSingleRef($targetClass, $attributes[$col], $natural);
            if ($ref !== null) {
                $ref['_model'] = $targetClass;
                $refs[$col] = $ref;
            }
        }

        return $refs;
    }

    /**
     * @param  class-string<Model>  $targetClass
     * @param  list<string>  $naturalCols
     * @return array<string, mixed>|null
     */
    private function resolveSingleRef(string $targetClass, mixed $id, array $naturalCols): ?array
    {
        if (! class_exists($targetClass) || ! is_subclass_of($targetClass, Model::class)) {
            return null;
        }

        $query = method_exists($targetClass, 'withTrashed')
            ? $targetClass::withTrashed()
            : $targetClass::query();

        if (class_exists(CabangScope::class)) {
            $query->withoutGlobalScope(CabangScope::class);
        }

        $target = $query->find($id);
        if (! $target instanceof Model) {
            return null;
        }

        $ref = ['id' => $target->getKey()];
        $uuid = $this->ensureSyncUuid($target);
        if ($uuid !== null) {
            $ref['sync_uuid'] = $uuid;
        }

        foreach ($naturalCols as $natCol) {
            $natVal = $target->getAttribute($natCol);
            if ($natVal !== null && $natVal !== '') {
                $ref[$natCol] = $natVal;
            }
        }

        return $ref;
    }

    private function resolveCabangId(Model $model): ?int
    {
        $direct = $model->getAttribute('cabang_id');
        if (! empty($direct)) {
            return (int) $direct;
        }

        $def = BackofficeSyncRegistry::tables()[$model->getTable()] ?? [];
        $path = $def['cabang_relation'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $current = $model;
        foreach ($segments as $segment) {
            if ($current === null) {
                return null;
            }
            if ($current instanceof Model && method_exists($current, $segment)) {
                $current = $current->getRelationValue($segment) ?? $current->{$segment}()->withoutGlobalScope(CabangScope::class)->first();
            } elseif ($current instanceof Model) {
                $val = $current->getAttribute($segment);

                return ! empty($val) ? (int) $val : null;
            }
        }

        return ! empty($current) ? (int) $current : null;
    }
}
