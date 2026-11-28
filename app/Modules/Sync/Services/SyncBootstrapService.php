<?php

namespace App\Modules\Sync\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
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
use App\Modules\Wms\Models\StokItem;
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

    /**
     * Tabel tambahan untuk "seed penuh" dari cloud ke client.
     * Sengaja dipisah dari MODEL_MAP karena tidak punya sync_uuid fisik.
     */
    private const SEED_TABLES = ['transaksi', 'stok_items'];

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

        // Urutan penting: transaksi dulu, stok snapshot terakhir agar angka cloud yang menang.
        foreach (self::SEED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
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
        if ($table === 'transaksi') {
            return $this->exportTransaksi($limit, $offset);
        }
        if ($table === 'stok_items') {
            return $this->exportStokSnapshot($limit, $offset);
        }

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

    /**
     * Riwayat transaksi cloud untuk seed client baru.
     * seed_mode=true membuat processor meng-upsert transaksi TANPA memotong stok,
     * karena angka stok akhir diambil dari snapshot stok terpisah.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportTransaksi(int $limit, int $offset): array
    {
        $rows = Transaksi::query()
            ->with(['items.produk', 'items.skuVariant', 'kasir', 'pelanggan'])
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->offset(max(0, $offset))
            ->get();

        return $rows->map(function (Transaksi $trx): array {
            $items = $trx->items->map(function ($item): array {
                $produk = $item->produk;
                $variant = $item->skuVariant;

                return [
                    'produk_id' => (int) $item->produk_id,
                    'produk_sync_uuid' => $produk?->getAttribute('sync_uuid'),
                    'produk_slug' => $produk?->getAttribute('slug'),
                    'produk_barcode' => $produk?->getAttribute('barcode'),
                    'sku_variant_id' => $item->sku_variant_id ? (int) $item->sku_variant_id : null,
                    'sku_variant_sku' => $variant?->getAttribute('sku'),
                    'jumlah' => (float) $item->jumlah,
                    'satuan' => (string) ($item->satuan ?: ($produk?->getAttribute('satuan') ?: 'pcs')),
                    'konversi' => max(0.0001, (float) ($item->konversi ?? 1)),
                    'harga_satuan' => (float) $item->harga_satuan,
                    'diskon_nominal' => (float) ($item->diskon_nominal ?? 0),
                    'subtotal' => (float) $item->subtotal,
                    'harga_final' => $item->getAttribute('harga_final') !== null ? (float) $item->harga_final : null,
                    'hpp' => (float) ($item->hpp ?? 0),
                    'catatan' => $item->catatan,
                ];
            })->all();

            $no = (string) ($trx->no_transaksi ?: $trx->id);

            return [
                'sync_uuid' => 'seed-trx-'.$trx->id,
                'event_type' => 'transaksi.pos_cloud',
                'entity_type' => Transaksi::class,
                'source_node' => (string) config('sync.node_id', 'CLOUD'),
                'created_at' => now()->toIso8601String(),
                'payload' => [
                    'id' => (int) $trx->id,
                    'no_transaksi' => $no,
                    'nomor_transaksi' => $no,
                    'offline_uuid' => $trx->offline_uuid,
                    'sumber' => (string) ($trx->sumber ?: 'pos'),
                    'status' => (string) $trx->status,
                    'cabang_id' => $trx->cabang_id ? (int) $trx->cabang_id : null,
                    'kasir_id' => $trx->kasir_id ? (int) $trx->kasir_id : null,
                    'kasir_email' => $trx->kasir?->getAttribute('email'),
                    'kasir_nama' => $trx->kasir?->getAttribute('name'),
                    'pelanggan_id' => $trx->pelanggan_id ? (int) $trx->pelanggan_id : null,
                    'pelanggan_telepon' => $trx->pelanggan?->getAttribute('telepon'),
                    'pelanggan_nama' => $trx->pelanggan?->getAttribute('nama'),
                    'subtotal' => (float) $trx->subtotal,
                    'diskon_persen' => (float) ($trx->diskon_persen ?? 0),
                    'diskon_nominal' => (float) ($trx->diskon_nominal ?? 0),
                    'diskon_poin' => (float) ($trx->diskon_poin ?? 0),
                    'diskon_partai' => (float) ($trx->diskon_partai ?? 0),
                    'total_akhir' => (float) $trx->total_akhir,
                    'metode_bayar' => (string) ($trx->metode_bayar ?: 'tunai'),
                    'jumlah_bayar' => (float) ($trx->jumlah_bayar ?? $trx->total_akhir),
                    'kembalian' => (float) ($trx->kembalian ?? 0),
                    'catatan' => $trx->catatan,
                    'paid_at' => $trx->paid_at?->toIso8601String(),
                    'created_at' => $trx->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'items' => $items,
                    'poin_handled_at_source' => true,
                    'seed_mode' => true,
                ],
            ];
        })->all();
    }

    /**
     * Snapshot stok absolut (bukan delta) untuk seed client baru.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportStokSnapshot(int $limit, int $offset): array
    {
        $rows = DB::table('stok_items as si')
            ->join('produk as p', 'p.id', '=', 'si.produk_id')
            ->join('gudang as g', 'g.id', '=', 'si.gudang_id')
            ->leftJoin('sku_variants as sv', 'sv.id', '=', 'si.sku_variant_id')
            ->select([
                'si.id as stok_id',
                'si.produk_id',
                'p.sync_uuid as produk_sync_uuid',
                'p.barcode as produk_barcode',
                'si.sku_variant_id',
                'sv.sku as sku_variant_sku',
                'si.gudang_id',
                'g.kode as gudang_kode',
                'g.cabang_id',
                'si.jumlah',
            ])
            ->orderBy('si.id')
            ->limit(max(1, min(500, $limit)))
            ->offset(max(0, $offset))
            ->get();

        return $rows->map(function ($row): array {
            $gudangId = (int) $row->gudang_id;
            $produkId = (int) $row->produk_id;
            $variantId = $row->sku_variant_id ? (int) $row->sku_variant_id : 0;

            return [
                'sync_uuid' => 'stk-'.md5("{$gudangId}:{$produkId}:{$variantId}"),
                'event_type' => 'stok.snapshot',
                'entity_type' => StokItem::class,
                'source_node' => (string) config('sync.node_id', 'CLOUD'),
                'created_at' => now()->toIso8601String(),
                'payload' => [
                    'produk_id' => $produkId,
                    'produk_sync_uuid' => $row->produk_sync_uuid,
                    'produk_barcode' => $row->produk_barcode,
                    'sku_variant_id' => $variantId ?: null,
                    'sku_variant_sku' => $row->sku_variant_sku,
                    'gudang_id' => $gudangId,
                    'gudang_kode' => $row->gudang_kode,
                    'cabang_id' => $row->cabang_id ? (int) $row->cabang_id : null,
                    'jumlah' => (int) $row->jumlah,
                    'seed_mode' => true,
                ],
            ];
        })->all();
    }
}
