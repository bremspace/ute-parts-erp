<?php

namespace App\Modules\Sync\Observers\Concerns;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\SyncExecutionGuard;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait SyncsModelEvents
{
    /**
     * Cache per tabel apakah kolom sync_uuid tersedia di skema DB saat ini.
     *
     * @var array<string, bool>
     */
    protected array $syncUuidColumnCache = [];

    protected function hasSyncUuidColumn(Model $model): bool
    {
        $table = $model->getTable();
        if (! array_key_exists($table, $this->syncUuidColumnCache)) {
            $this->syncUuidColumnCache[$table] = Schema::hasColumn($table, 'sync_uuid');
        }

        return $this->syncUuidColumnCache[$table];
    }

    /**
     * Pastikan model memiliki sync_uuid stabil tanpa memicu observer berulang.
     */
    protected function ensureSyncUuid(Model $model): ?string
    {
        if (! $this->hasSyncUuidColumn($model)) {
            return null;
        }

        $current = $model->getAttribute('sync_uuid');
        if (! empty($current)) {
            return (string) $current;
        }

        if (! $model->exists) {
            return null;
        }

        $uuid = (string) Str::uuid();
        $model->setAttribute('sync_uuid', $uuid);
        $model->saveQuietly();

        return $uuid;
    }

    protected function formatDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) $value;
    }

    protected function formatIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return (string) $value;
    }

    protected function toNullableFloat(Model $model, string $column): ?float
    {
        $raw = $model->getAttributes();
        if (! array_key_exists($column, $raw) || $raw[$column] === null) {
            return null;
        }

        return (float) $model->getAttribute($column);
    }

    /**
     * Enqueue payload setelah DB commit (bila dalam transaksi), selalu menghormati SyncExecutionGuard.
     *
     * @template TSyncModel of Model
     *
     * @param  TSyncModel  $model
     * @param  callable(TSyncModel): (?array)  $payloadBuilder
     */
    protected function enqueueModelEvent(
        Model $model,
        string $eventType,
        callable $payloadBuilder,
        ?int $cabangId = null,
        string $logLabel = 'Model'
    ): void {
        if (SyncExecutionGuard::isSuppressed() || ! config('sync.enabled')) {
            return;
        }

        $action = function () use ($model, $eventType, $payloadBuilder, $cabangId, $logLabel): void {
            if (SyncExecutionGuard::isSuppressed() || ! config('sync.enabled')) {
                return;
            }

            try {
                if ($model->exists) {
                    $model->refresh();
                }

                $payload = $payloadBuilder($model);
                if (! is_array($payload) || $payload === []) {
                    return;
                }

                app(HybridSyncOutboxService::class)->enqueue(
                    $eventType,
                    $model,
                    $payload,
                    $cabangId
                );
            } catch (\Throwable $e) {
                Log::warning("[HybridSync] Observer {$logLabel} gagal: ".$e->getMessage());
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($action);
        } else {
            $action();
        }
    }

    /**
     * Payload lengkap transaksi POS / Cloud Failover agar rekap dan akuntansi paritas 100%.
     */
    protected function buildTransaksiPosPayload(Transaksi $transaksi, bool $poinHandledAtSource = true): array
    {
        $transaksi->loadMissing(['items.produk', 'items.skuVariant', 'kasir', 'pelanggan', 'gudang', 'tiketServis', 'pengiriman']);

        if (empty($transaksi->offline_uuid)) {
            $transaksi->offline_uuid = (string) Str::uuid();
            $transaksi->saveQuietly();
        }

        $items = $transaksi->items->map(function (TransaksiItem $item): array {
            $produk = $item->produk;
            $skuVariant = $item->skuVariant;

            return [
                'produk_id' => $item->produk_id,
                'produk_sync_uuid' => $produk?->getAttribute('sync_uuid'),
                'produk_slug' => $produk?->getAttribute('slug'),
                'produk_barcode' => $produk?->getAttribute('barcode'),
                'sku_variant_id' => $item->sku_variant_id,
                'sku_variant_sku' => $skuVariant?->getAttribute('sku'),
                'jumlah' => (float) $item->jumlah,
                'satuan' => $item->satuan ?: ($produk?->getAttribute('satuan') ?: 'pcs'),
                'konversi' => max(0.0001, (float) ($item->konversi ?? 1)),
                'harga_satuan' => (float) $item->harga_satuan,
                'diskon_nominal' => (float) ($item->diskon_nominal ?? 0),
                'subtotal' => (float) $item->subtotal,
                'harga_final' => $item->getAttribute('harga_final') !== null ? (float) $item->getAttribute('harga_final') : null,
                'hpp' => (float) ($item->hpp ?? 0),
                'catatan' => $item->catatan,
            ];
        })->all();

        $noTransaksi = $transaksi->no_transaksi ?: (string) $transaksi->id;
        $sumber = strtolower((string) ($transaksi->sumber ?: 'pos'));
        $rawAttributes = $transaksi->getAttributes();
        $dppValue = array_key_exists('dpp', $rawAttributes) && $rawAttributes['dpp'] !== null
            ? (float) $transaksi->dpp
            : null;
        $pajakVal = (float) ($transaksi->getAttribute('pajak_nominal') ?? $transaksi->getAttribute('ppn_nominal') ?? 0);
        $ppnVal = (float) ($transaksi->getAttribute('ppn_nominal') ?? $transaksi->getAttribute('pajak_nominal') ?? 0);

        return [
            'id' => $transaksi->id,
            'no_transaksi' => $noTransaksi,
            'nomor_transaksi' => $noTransaksi,
            'offline_uuid' => $transaksi->offline_uuid,
            'source_node' => (string) config('sync.node_id', 'STORE-01'),
            'sumber' => $sumber,
            'status' => strtolower((string) $transaksi->status),
            'cabang_id' => $transaksi->cabang_id,
            'kasir_id' => $transaksi->kasir_id,
            'kasir_email' => $transaksi->kasir?->getAttribute('email'),
            'kasir_nama' => $transaksi->kasir?->getAttribute('name'),
            'pelanggan_id' => $transaksi->pelanggan_id,
            'pelanggan_sync_uuid' => $transaksi->pelanggan?->getAttribute('sync_uuid'),
            'pelanggan_telepon' => $transaksi->pelanggan?->getAttribute('telepon'),
            'pelanggan_nama' => $transaksi->pelanggan?->getAttribute('nama'),
            'gudang_id' => $transaksi->gudang_id,
            'gudang_kode' => $transaksi->gudang?->getAttribute('kode'),
            'tiket_servis_id' => $transaksi->tiket_servis_id,
            'tiket_servis_no_tiket' => $transaksi->tiketServis?->getAttribute('no_tiket'),
            'subtotal' => (float) $transaksi->subtotal,
            'diskon_persen' => (float) ($transaksi->getAttribute('diskon_persen') ?? 0),
            'diskon_nominal' => (float) ($transaksi->getAttribute('diskon_nominal') ?? 0),
            'diskon_poin' => (float) ($transaksi->getAttribute('diskon_poin') ?? 0),
            'poin_ditukar' => (int) ($transaksi->getAttribute('poin_ditukar') ?? 0),
            'diskon_partai' => (float) ($transaksi->getAttribute('diskon_partai') ?? 0),
            'diskon_total' => (float) ((float) ($transaksi->getAttribute('diskon_nominal') ?? 0) + (float) ($transaksi->getAttribute('diskon_poin') ?? 0) + (float) ($transaksi->getAttribute('diskon_partai') ?? 0)),
            'mode_reward_mitra' => $transaksi->mode_reward_mitra,
            'komisi_mitra_nominal' => (float) ($transaksi->getAttribute('komisi_mitra_nominal') ?? 0),
            'dpp' => $dppValue,
            'pajak_nominal' => $pajakVal,
            'ppn_nominal' => $ppnVal,
            'ongkir' => (float) ($transaksi->pengiriman?->getAttribute('ongkir') ?? 0),
            'payment_reference' => $transaksi->payment_reference,
            'total_akhir' => (float) $transaksi->total_akhir,
            'total' => (float) $transaksi->total_akhir,
            'metode_bayar' => $transaksi->metode_bayar,
            'jumlah_bayar' => (float) $transaksi->jumlah_bayar,
            'kembalian' => (float) ($transaksi->getAttribute('kembalian') ?? 0),
            'split_detail' => $transaksi->split_detail,
            'catatan' => $transaksi->catatan,
            'durasi_garansi_hari' => (int) ($transaksi->getAttribute('durasi_garansi_hari') ?? 0),
            'garansi_teks' => $transaksi->garansi_teks,
            'garansi_berakhir_pada' => $transaksi->garansi_berakhir_pada?->toDateString(),
            'catatan_garansi' => $transaksi->catatan_garansi,
            'jumlah_item' => (int) count($items),
            'poin_handled_at_source' => $poinHandledAtSource,
            'created_at' => $transaksi->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'transaksi_created_at' => $transaksi->created_at?->toIso8601String(),
            'paid_at' => $transaksi->paid_at?->toIso8601String(),
            'items' => $items,
        ];
    }
}
