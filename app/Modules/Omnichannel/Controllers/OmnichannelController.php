<?php

namespace App\Modules\Omnichannel\Controllers;

use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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
            'kredensial' => $request->kredensial,
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
            'is_token_expired' => $channel->isTokenExpired(),
            'should_refresh_token' => $channel->shouldRefreshToken(3600),
        ], 'Status koneksi channel berhasil dimuat');
    }

    // [API: OMNI-07] Redirect OAuth authorization
    public function authRedirect(Request $request, $id)
    {
        $channel = Channel::findOrFail($id);
        $adapter = $this->syncService->adapterFor($channel->platform);

        if (! $adapter) {
            return response()->json(['success' => false, 'message' => "Adapter {$channel->platform} tidak ditemukan"], 404);
        }

        $redirectUrl = route('omnichannel.auth.callback', ['platform' => $channel->platform]);
        $authUrl = $adapter->getAuthUrl($channel->kredensial ?? [], $redirectUrl);

        if ($request->wantsJson()) {
            return $this->success(['auth_url' => $authUrl], 'URL otorisasi berhasil digenerate');
        }

        return redirect()->away($authUrl);
    }

    // [API: OMNI-08] Callback OAuth dari marketplace
    public function authCallback(Request $request, $platform)
    {
        $code = $request->query('code');
        $shopId = $request->query('shop_id');

        if (! $code) {
            return redirect('/app/omnichannel')->with('error', 'Otorisasi dibatalkan atau kode otorisasi tidak ditemukan');
        }

        $adapter = $this->syncService->adapterFor($platform);
        if (! $adapter) {
            return redirect('/app/omnichannel')->with('error', "Adapter platform {$platform} belum tersedia");
        }

        // Cari channel yang cocok berdasarkan platform dan shop_id (atau channel terbaru)
        $channel = Channel::where('platform', $platform)
            ->where(function ($q) use ($shopId) {
                if ($shopId) {
                    $q->where('kredensial->shop_id', (int) $shopId)
                        ->orWhereNull('kredensial->access_token');
                }
            })
            ->latest()
            ->first();

        if (! $channel) {
            $channel = Channel::create([
                'nama' => ucfirst($platform).' Shop #'.$shopId,
                'platform' => $platform,
                'status' => 'belum_terhubung',
                'is_active' => true,
            ]);
        }

        try {
            $tokens = $adapter->handleAuthCallback($channel->kredensial ?? [], $code, $shopId);
            $channel->update([
                'kredensial' => array_merge($channel->kredensial ?? [], $tokens),
                'status' => 'terhubung',
                'last_sync_at' => now(),
                'last_sync_status' => 'sukses',
            ]);

            return redirect('/app/omnichannel')->with('success', "Kanal {$channel->nama} berhasil terhubung dengan Shopee!");
        } catch (\Throwable $e) {
            $channel->update(['status' => 'token_bermasalah']);

            return redirect('/app/omnichannel')->with('error', 'Gagal otorisasi: '.$e->getMessage());
        }
    }

    // [API: OMNI-02] Mapping produk lokal ↔ SKU channel
    public function mapping(Request $request, $id)
    {
        $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*.produk_id' => 'required|exists:produk,id',
            'mappings.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'mappings.*.channel_sku' => 'nullable|string',
            'mappings.*.channel_item_id' => 'nullable|string',
            'mappings.*.channel_model_id' => 'nullable|string',
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
                    'sku_variant_id' => $row['sku_variant_id'] ?? null,
                    'channel_sku' => $row['channel_sku'] ?? null,
                    'channel_item_id' => $row['channel_item_id'] ?? null,
                    'channel_model_id' => $row['channel_model_id'] ?? null,
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
     * Route: POST /webhook/channel/{channelId}
     */
    public function webhook(Request $request, $channelId)
    {
        $channel = is_numeric($channelId)
            ? Channel::find($channelId)
            : Channel::where('platform', $channelId)->where('is_active', true)->first();

        if (! $channel) {
            return response()->json(['success' => false, 'message' => 'Channel tidak ditemukan'], 404);
        }

        // Rate limit per channel
        $rateKey = 'channel-webhook:'.$channel->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 120)) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak request'], 429);
        }
        RateLimiter::hit($rateKey, 60);

        // Verifikasi signature webhook bila ada kredensial partner_key
        $partnerKey = $channel->kredensial['partner_key'] ?? env('SHOPEE_PARTNER_KEY', '');
        $signature = $request->header('Authorization') ?? $request->header('X-Shopee-Signature') ?? '';
        $adapter = $this->syncService->adapterFor($channel->platform);

        if ($adapter && ! empty($partnerKey) && ! empty($signature)) {
            $valid = $adapter->verifyWebhookSignature($request->fullUrl(), $request->getContent(), $signature, $partnerKey);
            if (! $valid) {
                Log::warning("Signature webhook Shopee tidak valid untuk channel #{$channel->id}");

                return response()->json(['success' => false, 'message' => 'Signature tidak valid'], 401);
            }
        }

        try {
            $channelOrder = $this->syncService->prosesOrderMasuk($channel, $request->all());

            Log::info("[{$channel->platform}-webhook] Order diproses", [
                'channel' => $channel->nama,
                'order' => $channelOrder->channel_order_id,
                'status' => $channelOrder->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil diproses',
                'order_id' => $channelOrder->channel_order_id,
            ]);
        } catch (\Throwable $e) {
            Log::error("Webhook channel {$channel->nama} gagal: {$e->getMessage()}");

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
