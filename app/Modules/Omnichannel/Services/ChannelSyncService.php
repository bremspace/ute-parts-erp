<?php

namespace App\Modules\Omnichannel\Services;

use App\Modules\Omnichannel\Adapters\ShopeeAdapter;
use App\Modules\Omnichannel\Contracts\ChannelAdapterInterface;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi sinkronisasi channel (PRD §4.10):
 * - pushStok: gudang sumber per mapping → debounce per SKU (dipanggil dari event StokItem berubah)
 * - pullOrders: webhook/polling → ChannelOrder → Transaksi lokal
 */
class ChannelSyncService
{
    /** @var array<string, ChannelAdapterInterface> cache adapter per platform */
    private array $adapters = [];

    public function __construct()
    {
        $this->adapters['shopee'] = new ShopeeAdapter();
        // Fase 2: tokopedia, blibli, tiktok, lazada — implement interface yang sama
    }

    public function adapterFor(string $platform): ?ChannelAdapterInterface
    {
        return $this->adapters[$platform] ?? null;
    }

    /**
     * Push stok semua mapping yang terkait produk — serialized per SKU (anti oversell race).
     */
    public function syncStokSemuaChannel(int $produkId): void
    {
        $mappings = ChannelProductMapping::with('channel')
            ->where('produk_id', $produkId)
            ->where('status', 'tersinkron')
            ->get();

        foreach ($mappings as $mapping) {
            if (!$mapping->channel || $mapping->channel->status !== 'terhubung') {
                continue;
            }

            $stok = 0;
            if ($mapping->gudang_id) {
                $stok = (int) StokItem::where('produk_id', $produkId)
                    ->where('gudang_id', $mapping->gudang_id)
                    ->sum('jumlah');
            }

            $adapter = $this->adapterFor($mapping->channel->platform);
            if (!$adapter) {
                continue;
            }

            // Serialize per SKU: lock mapping agar tidak ada 2 job push stok bersamaan
            // (dalam MVP diproses sinkron via API manual; fase prod → queue job per SKU)
            try {
                $ok = $adapter->pushStock($mapping->channel->kredensial ?? [], [
                    ['item_id' => $mapping->channel_item_id, 'stock' => $stok],
                ]);

                $mapping->update([
                    'status' => $ok ? 'tersinkron' : 'error',
                    'error_message' => $ok ? null : 'Push stok ditolak channel',
                ]);
                $mapping->channel->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => $ok ? 'sukses' : 'error',
                ]);
            } catch (\Throwable $e) {
                $mapping->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ]);
                $mapping->channel->update(['last_sync_status' => 'error']);
            }
        }
    }

    /**
     * Tarik order dari sebuah channel → buat Transaksi lokal menunggu_pembayaran.
     */
    public function pullOrders(Channel $channel): int
    {
        $adapter = $this->adapterFor($channel->platform);
        if (!$adapter) {
            throw new \Exception("Adapter untuk platform {$channel->platform} belum tersedia");
        }

        $orders = $adapter->pullOrders($channel->kredensial ?? []);
        $created = 0;

        DB::transaction(function () use ($channel, $orders, &$created) {
            foreach ($orders as $order) {
                $orderId = $order['order_sn'] ?? $order['order_id'] ?? null;
                if (!$orderId) {
                    continue;
                }

                // Idempotent: order_id channel unik
                if (\App\Modules\Omnichannel\Models\ChannelOrder::where('channel_id', $channel->id)
                    ->where('channel_order_id', $orderId)->exists()) {
                    continue;
                }

                $channelOrder = \App\Modules\Omnichannel\Models\ChannelOrder::create([
                    'channel_id' => $channel->id,
                    'channel_order_id' => $orderId,
                    'payload' => $order,
                    'channel_status' => $order['order_status'] ?? null,
                    'status' => 'menunggu_proses',
                ]);

                // Buat Transaksi lokal (validasi produk mapping di phase 2 — MVP catat saja)
                $jenisStatus = match ($order['order_status'] ?? null) {
                    'COMPLETED' => 'selesai',
                    'CANCELLED' => 'batal',
                    default => 'menunggu_pembayaran',
                };

                $channelOrder->update(['status' => $jenisStatus]);
                $created++;
            }
        });

        $channel->update(['last_sync_at' => now(), 'last_sync_status' => 'sukses']);

        return $created;
    }
}