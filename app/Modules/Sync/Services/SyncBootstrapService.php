<?php

namespace App\Modules\Sync\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBarcode;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data canonical yang boleh di-bootstrap dari cloud ke client.
 *
 * Hanya tabel yang punya sync_uuid yang ikut. Kolom sensitif/terenkripsi
 * sengaja dibuang supaya APP_KEY tiap node tidak perlu sama.
 */
class SyncBootstrapService
{
    /** @var array<string, class-string<Model>> */
    public const MODEL_MAP = [
        'kategori_produk' => KategoriProduk::class,
        'brands' => Brand::class,
        'kualitas_produk' => KualitasProduk::class,
        'satuan_unit' => SatuanUnit::class,
        'tipe_hp' => TipeHp::class,
        'produk' => Produk::class,
        'sku_variants' => SkuVariant::class,
        'produk_satuan_konversi' => ProdukSatuanKonversi::class,
        'produk_barcode' => ProdukBarcode::class,
        'harga_tier' => HargaTier::class,
        'pelanggan' => Pelanggan::class,
        'tier_memberships' => TierMembership::class,
        'cabang' => Cabang::class,
        'gudang' => Gudang::class,
        'supplier' => Supplier::class,
        'akun_coa' => AkunCOA::class,
        'jenis_servis' => JenisServis::class,
    ];

    private const SENSITIVE = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_backup_codes',
        'api_token',
        'api_key',
        'api_secret',
        'token',
        'secret',
    ];

    /** @return array<int, array{table: string, count: int}> */
    public function tables(): array
    {
        $out = [];
        foreach (array_keys(self::MODEL_MAP) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sync_uuid')) {
                continue;
            }
            $out[] = [
                'table' => $table,
                'count' => (int) DB::table($table)->count(),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function export(string $table, int $limit, int $offset): array
    {
        $modelClass = self::MODEL_MAP[$table] ?? null;
        if ($modelClass === null || ! class_exists($modelClass)) {
            return [];
        }
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sync_uuid')) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        $select = array_values(array_filter($columns, function (string $column): bool {
            foreach (self::SENSITIVE as $needle) {
                if ($column === $needle || str_ends_with($column, '_'.$needle)) {
                    return false;
                }
            }

            return true;
        }));

        return $modelClass::query()
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->offset(max(0, $offset))
            ->get($select)
            ->map(function (Model $row) use ($table): array {
                $attributes = $row->getAttributes();
                foreach (['id', 'created_by', 'updated_by'] as $key) {
                    unset($attributes[$key]);
                }

                return [
                    'sync_uuid' => (string) ($attributes['sync_uuid'] ?? ''),
                    'event_type' => $table.'.upsert',
                    'entity_type' => self::MODEL_MAP[$table],
                    'payload' => $attributes,
                    'source_node' => (string) config('sync.node_id', 'CLOUD'),
                    'created_at' => now()->toIso8601String(),
                ];
            })
            ->all();
    }
}
