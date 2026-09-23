<?php

namespace App\Modules\Report\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * [F2-4] Report Builder Service.
 * Model whitelist — rejects any non-whitelisted source model.
 * All queries scoped to cabang_id (session).
 */
class ReportBuilderService
{
    /**
     * Whitelist of allowed source models for report builder.
     * Keys: model class name (short), Values: display label.
     */
    public const MODEL_WHITELIST = [
        'Transaksi' => 'Transaksi (Penjualan)',
        'JurnalAkuntansi' => 'Jurnal Akuntansi',
        'StokItem' => 'Stok Item',
        'PurchaseOrder' => 'Purchase Order',
        'Piutang' => 'Piutang',
        'Utang' => 'Utang',
        'TiketServis' => 'Tiket Servis',
        'Produk' => 'Produk',
        'TransaksiItem' => 'Transaksi Item',
        'StokLog' => 'Stok Log',
        'PurchaseOrderItem' => 'Purchase Order Item',
        'TiketServisItem' => 'Tiket Servis Item',
    ];

    /**
     * Map model short name to fully qualified class.
     */
    public const MODEL_MAP = [
        'Transaksi' => Transaksi::class,
        'TransaksiItem' => TransaksiItem::class,
        'JurnalAkuntansi' => JurnalAkuntansi::class,
        'StokItem' => StokItem::class,
        'StokLog' => StokLog::class,
        'PurchaseOrder' => PurchaseOrder::class,
        'PurchaseOrderItem' => PurchaseOrderItem::class,
        'Piutang' => Piutang::class,
        'Utang' => Utang::class,
        'TiketServis' => TiketServis::class,
        'TiketServisItem' => TiketServisItem::class,
        'Produk' => Produk::class,
    ];

    /**
     * [P0-3] Peta scope cabang EKSPLISIT — menggantikan heuristik getFillable().
     *
     * type 'column' : kolom cabang_id ada langsung di tabel model.
     * type 'relation': cabang diturunkan lewat relasi (path boleh bertitik);
     *                  callback where('cabang_id') diterapkan ke relasi paling dalam.
     *
     * Setiap model di MODEL_MAP WAJIB punya entri — model tanpa entri
     * FAIL CLOSED (tidak ada baris yang keluar, lihat applyCabangScope()).
     */
    public const CABANG_SCOPE = [
        'Transaksi' => ['type' => 'column', 'path' => 'cabang_id'],
        'JurnalAkuntansi' => ['type' => 'column', 'path' => 'cabang_id'],
        'Piutang' => ['type' => 'column', 'path' => 'cabang_id'],
        'Utang' => ['type' => 'column', 'path' => 'cabang_id'],
        'TiketServis' => ['type' => 'column', 'path' => 'cabang_id'],
        'StokItem' => ['type' => 'relation', 'path' => 'gudang'],                    // StokItem → gudang.cabang_id
        'StokLog' => ['type' => 'relation', 'path' => 'gudang'],                      // StokLog → gudang.cabang_id
        'TransaksiItem' => ['type' => 'relation', 'path' => 'transaksi'],             // → transaksi.cabang_id
        'PurchaseOrder' => ['type' => 'relation', 'path' => 'gudangTujuan'],          // → gudang_tujuan.cabang_id
        'PurchaseOrderItem' => ['type' => 'relation', 'path' => 'purchaseOrder.gudangTujuan'],
        'TiketServisItem' => ['type' => 'relation', 'path' => 'tiketServis'],         // tiket_servis.cabang_id
        // Produk adalah master global tanpa cabang_id — tampil hanya bila punya
        // stok di gudang milik cabang aktif (fail closed untuk produk tanpa stok cabang).
        'Produk' => ['type' => 'relation', 'path' => 'stokItems.gudang'],
    ];

    /**
     * [P0-5] Operator filter yang diizinkan (whitelist ketat).
     */
    public const FILTER_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'like'];

    /**
     * Get columns available for a whitelisted model.
     * Returns array of [field => label].
     */
    public function getAvailableColumns(string $modelShortName): array
    {
        $this->validateModel($modelShortName);
        $modelClass = self::MODEL_MAP[$modelShortName];
        $model = new $modelClass;
        $fillable = $model->getFillable() ?? [];

        return collect($fillable)
            ->filter(fn ($f) => ! in_array($f, ['created_at', 'updated_at', 'deleted_at']))
            ->mapWithKeys(fn ($f) => [$f => str_replace('_', ' ', ucwords(str_replace('_', ' ', $f)))])
            ->toArray();
    }

    /**
     * Validate that a model name is in the whitelist.
     *
     * @throws \InvalidArgumentException
     */
    public function validateModel(string $modelShortName): void
    {
        if (! isset(self::MODEL_MAP[$modelShortName])) {
            throw new \InvalidArgumentException(
                "Model '{$modelShortName}' tidak ada di whitelist laporan. Model yang diizinkan: ".implode(', ', array_keys(self::MODEL_MAP))
            );
        }
    }

    /**
     * Build a scoped query for the given model with filters, columns, and grouping.
     *
     * Kontrak filter (dua format didukung):
     * - Baris: [['field' => 'status', 'op' => '=', 'value' => 'selesai'], ...]
     *   — field wajib lolos whitelist getAvailableColumns(), op wajib dari FILTER_OPERATORS.
     * - Legacy assoc: ['status' => 'selesai'] — field juga divalidasi ke whitelist.
     * Baris kosong / field di luar whitelist diabaikan (anti injeksi kolom, fail closed).
     */
    public function buildQuery(
        string $modelShortName,
        array $columns = ['*'],
        ?array $filters = null,
        ?array $groupBy = null,
        ?int $cabangId = null
    ): Builder {
        $this->validateModel($modelShortName);
        $modelClass = self::MODEL_MAP[$modelShortName];
        $cabangId = $cabangId ?: session('cabang_id');
        $allowedColumns = array_keys($this->getAvailableColumns($modelShortName));

        // [P1-10] Group-by hanya boleh memakai kolom ter-whitelist.
        $groupByFields = array_values(array_intersect(
            array_filter(is_array($groupBy) ? $groupBy : [], 'is_string'),
            $allowedColumns
        ));

        if ($groupByFields !== []) {
            // [P1-10] MySQL 8 ONLY_FULL_GROUP_BY: saat ada GROUP BY, select hanya
            // boleh berisi kolom tergrup + kolom agregat → kolom tergrup + COUNT(*).
            $query = $modelClass::query()
                ->select($groupByFields)
                ->selectRaw('count(*) as jumlah');
        } else {
            $select = array_values(array_intersect(array_filter($columns, 'is_string'), $allowedColumns));
            $query = $modelClass::query()->select($select !== [] ? $select : ['*']);
        }

        // [P0-3] Scope cabang eksplisit — fail closed bila scope tak dikenal / cabang tidak aktif.
        $this->applyCabangScope($query, $modelShortName, $cabangId);

        $this->applyFilters($query, $modelShortName, $filters);

        foreach ($groupByFields as $field) {
            $query->groupBy($field);
        }

        return $query;
    }

    /**
     * [P0-2/P0-3] Terapkan scope cabang berdasarkan peta eksplisit CABANG_SCOPE.
     *
     * Fail closed: cabang aktif null ATAU model tidak ada di peta → query tidak
     * pernah mengembalikan baris (where 1 = 0), bukan melempar data lintas cabang.
     */
    public function applyCabangScope(Builder $query, string $modelShortName, ?int $cabangId): Builder
    {
        $scope = self::CABANG_SCOPE[$modelShortName] ?? null;

        if (! $cabangId || $scope === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope['type'] === 'column') {
            return $query->where($scope['path'], $cabangId);
        }

        return $query->whereHas(
            $scope['path'],
            fn (Builder $q) => $q->where('cabang_id', $cabangId)
        );
    }

    /**
     * [P0-2] Verifikasi satu baris berada di scope cabang aktif.
     * Berbagi CABANG_SCOPE dengan buildQuery() — gagal = false (fail closed).
     */
    public function itemInCabang(string $modelShortName, Model $item, ?int $cabangId = null): bool
    {
        $this->validateModel($modelShortName);

        $modelClass = self::MODEL_MAP[$modelShortName];
        if (! $item instanceof $modelClass) {
            return false;
        }

        $cabangId = $cabangId ?: session('cabang_id');

        return $this->applyCabangScope($modelClass::query(), $modelShortName, $cabangId)
            ->whereKey($item->getKey())
            ->exists();
    }

    /**
     * [P0-5/P2] Terapkan filter dengan whitelist field + whitelist operator.
     */
    private function applyFilters(Builder $query, string $modelShortName, ?array $filters): void
    {
        if (! $filters) {
            return;
        }

        $allowedColumns = array_keys($this->getAvailableColumns($modelShortName));

        if ($this->filtersAreRows($filters)) {
            foreach ($filters as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $field = $row['field'] ?? null;
                $op = $row['op'] ?? '=';
                $value = $row['value'] ?? null;

                // Baris kosong / field di luar whitelist diabaikan (anti injeksi kolom).
                if (! is_string($field) || $field === '' || ! in_array($field, $allowedColumns, true)) {
                    continue;
                }
                if (! in_array($op, self::FILTER_OPERATORS, true)) {
                    $op = '=';
                }
                if ($value === null || $value === '') {
                    continue;
                }

                $query->where($field, $op, $op === 'like' ? "%{$value}%" : $value);
            }

            return;
        }

        // Format legacy [kolom => nilai] — field tetap wajib lolos whitelist.
        // [P2-5] Tanpa heuristik panjang nilai (strlen > 2 → like): operator
        // eksplisit default '=' (atau whereIn bila nilai berupa array).
        // Baris format baris memakai op dari whitelist FILTER_OPERATORS.
        foreach ($filters as $field => $value) {
            if (! is_string($field) || ! in_array($field, $allowedColumns, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, '=', $value);
            }
        }
    }

    /**
     * Deteksi format filter: daftar baris {field,op,value} vs legacy assoc [kolom => nilai].
     */
    private function filtersAreRows(array $filters): bool
    {
        foreach ($filters as $value) {
            if (is_array($value) && array_key_exists('field', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the display label for a model short name.
     */
    public function getModelLabel(string $modelShortName): string
    {
        return self::MODEL_WHITELIST[$modelShortName] ?? $modelShortName;
    }

    /**
     * Get all whitelisted models as [shortName => label].
     */
    public function getWhitelist(): array
    {
        return self::MODEL_WHITELIST;
    }

    /**
     * Get drill-down target model for a given source model.
     * Returns next model in the drill-down chain or null.
     *
     * Chain: Transaksi → TransaksiItem
     *        JurnalAkuntansi → null (terminal)
     *        StokItem → StokLog
     *        PurchaseOrder → PurchaseOrderItem
     *        Piutang → Transaksi
     *        Utang → null (terminal)
     *        TiketServis → TiketServisItem
     *        Produk → StokItem
     */
    public function getDrillDownTarget(string $modelShortName): ?string
    {
        $chain = [
            'Transaksi' => 'TransaksiItem',
            'TransaksiItem' => null,
            'JurnalAkuntansi' => null,
            'StokItem' => 'StokLog',
            'StokLog' => null,
            'PurchaseOrder' => 'PurchaseOrderItem',
            'PurchaseOrderItem' => null,
            'Piutang' => 'Transaksi',
            'Utang' => null,
            'TiketServis' => 'TiketServisItem',
            'TiketServisItem' => null,
            'Produk' => 'StokItem',
        ];

        return $chain[$modelShortName] ?? null;
    }

    /**
     * Get the drill-down chain as array for UI breadcrumb.
     */
    public function getDrillChain(string $modelShortName): array
    {
        $chain = [];
        $current = $modelShortName;
        $visited = [];

        while ($current && ! in_array($current, $visited)) {
            $visited[] = $current;
            $chain[] = $current;
            $current = $this->getDrillDownTarget($current);
        }

        return $chain;
    }
}
