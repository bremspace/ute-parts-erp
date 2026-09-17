<?php

namespace App\Modules\Omnichannel\Controllers;

use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Wms\Models\Produk;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class OmnichannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ChannelSyncService $syncService
    ) {}

    // [API: OMNI-01] List & hubungkan channel
    public function indexChannels()
    {
        $channels = Channel::withCount('productMappings')->orderBy('platform')->get();

        return $this->success($channels, 'Daftar channel marketplace berhasil dimuat');
    }

    public function storeChannel(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'platform' => 'required|in:shopee,tokopedia,blibli,tiktok,lazada',
            'kredensial' => 'required|array',
        ]);

        $channel = Channel::create([
            'nama' => $request->nama,
            'platform' => $request->platform,
            'kredensial' => $request->kredensial, // enkripsi penuh → fase deployment
            'status' => 'belum_terhubung',
            'is_active' => true,
        ]);

        // Auto test koneksi
        $adapter = $this->syncService->adapterFor($request->platform);
        try {
            if ($adapter && $adapter->testConnection($request->kredensial)) {
                $channel->update(['status' => 'terhubung']);
            }
        } catch (\Throwable $e) {
            $channel->update(['status' => 'token_bermasalah']);
        }

        return $this->success($channel, 'Channel berhasil ditambahkan', 201);
    }

    public function statusChannel(Request $request, $id)
    {
        $channel = Channel::findOrFail($id);

        return $this->success([
            'channel' => $channel,
            'adapter_tersedia' => $this->syncService->adapterFor($channel->platform) !== null,
            'terakhir_sync' => $channel->last_sync_at,
            'status_sync' => $channel->last_sync_status,
        ], 'Status koneksi channel berhasil dimuat');
    }

    // [API: OMNI-02] Mapping produk lokal ↔ SKU channel
    public function mapping(Request $request, $id)
    {
        $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*.produk_id' => 'required|exists:produk,id',
            'mappings.*.channel_sku' => 'nullable|string',
            'mappings.*.gudang_id' => 'nullable|exists:gudang,id',
        ]);

        $channel = Channel::findOrFail($id);

        $created = [];
        foreach ($request->mappings as $row) {
            $mapping = ChannelProductMapping::updateOrCreate(
                [
                    'channel_id' => $channel->id,
                    'produk_id' => $row['produk_id'],
                ],
                [
                    'channel_sku' => $row['channel_sku'] ?? null,
                    'gudang_id' => $row['gudang_id'] ?? null,
                    'status' => 'tersinkron',
                ]
            );
            $created[] = $mapping;
        }

        return $this->success($created, 'Mapping produk berhasil disimpan');
    }

    // [API: OMNI-03] Order terpadu semua channel (Unified Inbox)
    public function orders(Request $request)
    {
        $platform = $request->query('platform');
        $status = $request->query('status');

        $query = ChannelOrder::with('channel', 'transaksi')->latest();

        if ($platform) {
            $query->whereHas('channel', fn ($q) => $q->where('platform', $platform));
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $this->success($query->paginate(20), 'Order terpadu berhasil dimuat');
    }

    // [API: OMNI-04] Trigger manual sync stok
    public function syncStock(Request $request)
    {
        $request->validate([
            'produk_id' => 'nullable|exists:produk,id',
        ]);

        if ($request->produk_id) {
            $this->syncService->syncStokSemuaChannel($request->produk_id);
            return $this->success(null, 'Sinkronisasi stok produk berhasil di-trigger');
        }

        // Sinkron semua produk yang sudah ter-mapping
        $produkIds = ChannelProductMapping::where('status', 'tersinkron')->pluck('produk_id')->unique();
        foreach ($produkIds as $produkId) {
            $this->syncService->syncStokSemuaChannel($produkId);
        }

        return $this->success(['produk' => $produkIds->count()], 'Sinkronisasi stok semua produk berhasil di-trigger');
    }

    // [API: OMNI-06] Status koneksi & kesehatan sinkronisasi
    public function health(Request $request, $id)
    {
        $channel = Channel::with(['productMappings' => fn ($q) => $q->selectRaw('channel_id, count(*) as total, sum(status="error") as errors')->groupBy('channel_id')])
            ->findOrFail($id);

        $errorCount = ChannelProductMapping::where('channel_id', $id)->where('status', 'error')->count();

        return $this->success([
            'channel' => $channel,
            'mapping_total' => $channel->product_mappings_count ?? ChannelProductMapping::where('channel_id', $id)->count(),
            'mapping_error' => $errorCount,
            'kondisi' => $errorCount > 0 ? 'perhatian' : 'sehat',
        ], 'Kesehatan channel berhasil dimuat');
    }

    /**
     * [API: OMNI-05] Webhook penerima order/update dari channel.
     * Route: POST /webhook/channel/{channelId} — endpoint aman untuk callback marketplace.
     */
    public function webhook(Request $request, $channelId)
    {
        $channel = Channel::find($channelId);
        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel tidak ditemukan'], 404);
        }

        // Rate limit per channel
        $rateKey = 'channel-webhook:' . $channelId . ':' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 60)) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak request'], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 60);

        // Idempotency: payload harus berisi channel_order_id unik
        $orderId = $request->input('order_sn') ?? $request->input('order_id') ?? $request->input('data.order_sn');
        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'order_id wajib'], 422);
        }

        if (ChannelOrder::where('channel_id', $channel->id)->where('channel_order_id', $orderId)->exists()) {
            // Duplikat — update status jika ada, tanpa buat ulang
            $existing = ChannelOrder::where('channel_id', $channel->id)->where('channel_order_id', $orderId)->first();
            if ($request->input('order_status') && $existing) {
                $existing->update([
                    'channel_status' => $request->input('order_status'),
                    'payload' => array_merge($existing->payload ?? [], $request->all()),
                ]);
            }
            return response()->json(['success' => true, 'message' => 'Duplikat diabaikan (idempotent)']);
        }

        ChannelOrder::create([
            'channel_id' => $channel->id,
            'channel_order_id' => $orderId,
            'payload' => $request->all(),
            'channel_status' => $request->input('order_status'),
            'status' => 'menunggu_proses',
        ]);

        Log::info("[{channel}-webhook] Order baru", ['channel' => $channel->nama, 'order' => $orderId]);

        return response()->json(['success' => true, 'message' => 'OK']);
    }
}