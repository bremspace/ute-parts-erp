<?php

namespace App\Modules\Omnichannel\Livewire;

use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Command Center Omnichannel (PRD Frontend §5.10):
 * kartu channel + status, mapping produk, Unified Order Inbox, kesehatan sinkronisasi.
 */
class OmnichannelCommandCenter extends Component
{
    public string $activeTab = 'channels'; // channels, mapping, orders

    // Modal hubungkan channel
    public bool $showConnectModal = false;

    public array $connectForm = [
        'nama' => '', 'platform' => 'shopee',
        'partner_id' => '', 'partner_key' => '', 'shop_id' => '',
    ];

    // Mapping: produk terpilih
    public array $selectedProdukIds = [];

    public ?int $mappingChannelId = null;

    public string $autoMatchSku = '';

    // Trigger sync result
    public ?string $syncMessage = null;

    public function getChannelsProperty()
    {
        return Channel::withCount('productMappings')
            ->with('orders')
            ->orderBy('platform')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nama' => $c->nama,
                    'platform' => $c->platform,
                    'status' => $c->status,
                    'mapping_count' => $c->product_mappings_count,
                    'order_count' => $c->orders->count(),
                    'last_sync_at' => $c->last_sync_at,
                    'last_sync_status' => $c->last_sync_status,
                ];
            });
    }

    public function getOrdersProperty()
    {
        return ChannelOrder::with(['channel', 'transaksi'])
            ->latest()
            ->limit(50)
            ->get();
    }

    public function getMappingsProperty(): Collection
    {
        $query = ChannelProductMapping::with(['channel', 'produk', 'skuVariant'])
            ->latest();

        if ($this->mappingChannelId) {
            $query->where('channel_id', $this->mappingChannelId);
        }

        return $query->limit(50)->get();
    }

    public function getUnmappedProduksProperty()
    {
        return Produk::where('is_active', true)
            ->whereDoesntHave('channelMappings')
            ->limit(20)
            ->get();
    }

    public function openConnectModal()
    {
        $this->connectForm = [
            'nama' => '', 'platform' => 'shopee',
            'partner_id' => '', 'partner_key' => '', 'shop_id' => '',
        ];
        $this->showConnectModal = true;
    }

    public function connectChannel()
    {
        // [RBAC] Rute /app/omnichannel hanya `omnichannel.view`, tapi method ini
        // menulis kredensial kanal + status koneksi. API equivalentnya
        // (POST /api/omnichannel/channels) butuh `omnichannel.manage`.
        if (! auth()->user()?->can('omnichannel.manage')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin mengelola kanal omnichannel.']);

            return;
        }

        $this->validate([
            'connectForm.nama' => 'required|string|max:255',
            'connectForm.platform' => 'required|in:shopee,tokopedia,blibli,tiktok,lazada',
        ]);

        $kredensial = [];
        if ($this->connectForm['platform'] === 'shopee') {
            $kredensial = [
                'partner_id' => (int) $this->connectForm['partner_id'],
                'partner_key' => $this->connectForm['partner_key'],
                'shop_id' => (int) $this->connectForm['shop_id'],
            ];
        }

        $channel = Channel::firstOrCreate(
            [
                'platform' => $this->connectForm['platform'],
                'nama' => $this->connectForm['nama'],
            ],
            [
                'kredensial' => $kredensial,
                'status' => 'terhubung',
                'is_active' => true,
            ]
        );

        $adapter = app(ChannelSyncService::class)->adapterFor($this->connectForm['platform']);
        if ($adapter && ! empty($kredensial)) {
            try {
                $adapter->testConnection($kredensial);
                $channel->update(['status' => 'terhubung']);
            } catch (\Throwable $e) {
                $channel->update(['status' => 'token_bermasalah']);
                $this->dispatch('alert', ['type' => 'warning', 'message' => 'Channel disimpan tapi koneksi bermasalah: '.$e->getMessage()]);
            }
        }

        $this->showConnectModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Channel '.$channel->nama.' berhasil dihubungkan']);
    }

    public function setMappingChannel(int $channelId)
    {
        $this->mappingChannelId = $channelId;
    }

    public function toggleProdukMapping(int $produkId)
    {
        $this->selectedProdukIds = in_array($produkId, $this->selectedProdukIds, true)
            ? array_values(array_diff($this->selectedProdukIds, [$produkId]))
            : [...$this->selectedProdukIds, $produkId];
    }

    public function saveMapping()
    {
        // [RBAC] Menulis ChannelProductMapping = API POST /api/omnichannel/channels/{id}/mapping
        if (! auth()->user()?->can('omnichannel.manage')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin mengelola mapping produk omnichannel.']);

            return;
        }

        if (! $this->mappingChannelId || empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih channel & minimal 1 produk']);

            return;
        }

        foreach ($this->selectedProdukIds as $produkId) {
            $produk = Produk::with('skuVariants')->find($produkId);
            $variant = $produk?->skuVariants()->first();

            ChannelProductMapping::updateOrCreate(
                [
                    'channel_id' => $this->mappingChannelId,
                    'produk_id' => $produkId,
                ],
                [
                    'sku_variant_id' => $variant?->id,
                    'channel_sku' => $variant?->sku ?? $produk?->kode_produk,
                    'channel_item_id' => $variant?->sku ?? (string) $produkId,
                    'status' => 'tersinkron',
                ]
            );
        }

        $this->selectedProdukIds = [];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Mapping produk disimpan & stok siap sinkron']);
    }

    public function autoMatchAll()
    {
        if (! auth()->user()?->can('omnichannel.manage')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin mengelola mapping produk omnichannel.']);

            return;
        }

        if (! $this->mappingChannelId) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih channel terlebih dahulu']);

            return;
        }

        $channel = Channel::find($this->mappingChannelId);
        $adapter = app(ChannelSyncService::class)->adapterFor($channel?->platform ?? '');
        if (! $adapter) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Adapter channel tidak tersedia']);

            return;
        }

        try {
            $channelProducts = $adapter->fetchProducts($channel->kredensial ?? []);
            $matched = 0;

            foreach ($channelProducts as $cp) {
                $sku = $cp['sku'] ?? null;
                if (! $sku) {
                    continue;
                }

                $variant = SkuVariant::with('produk')->where('sku', $sku)->first();
                $produk = $variant?->produk ?: Produk::where('kode_produk', $sku)->first();

                if ($produk) {
                    ChannelProductMapping::updateOrCreate(
                        [
                            'channel_id' => $channel->id,
                            'produk_id' => $produk->id,
                        ],
                        [
                            'sku_variant_id' => $variant?->id,
                            'channel_sku' => $sku,
                            'channel_item_id' => (string) ($cp['item_id'] ?? ''),
                            'channel_model_id' => null,
                            'status' => 'tersinkron',
                        ]
                    );
                    $matched++;
                }
            }

            $this->dispatch('alert', ['type' => 'success', 'message' => "Auto-match berhasil memetakan {$matched} produk"]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal auto-match: '.$e->getMessage()]);
        }
    }

    public function startOAuth(int $channelId)
    {
        if (! auth()->user()?->can('omnichannel.manage')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin mengelola channel.']);

            return;
        }

        return redirect()->route('omnichannel.auth.redirect', ['id' => $channelId]);
    }

    public function triggerSyncAll()
    {
        // [RBAC] Trigger push stok ke marketplace = API POST /api/omnichannel/sync-stock
        if (! auth()->user()?->can('omnichannel.manage')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin menjalankan sinkronisasi omnichannel.']);

            return;
        }

        try {
            $produkIds = ChannelProductMapping::where('status', 'tersinkron')
                ->pluck('produk_id')->unique();
            foreach ($produkIds as $pid) {
                app(ChannelSyncService::class)->syncStokSemuaChannel($pid);
            }
            $this->syncMessage = 'Sinkronisasi stok di-trigger untuk '.$produkIds->count().' produk';
            $this->dispatch('alert', ['type' => 'success', 'message' => $this->syncMessage]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('modules.omnichannel.livewire.omnichannel-command-center', [
            'channels' => $this->channels,
            'orders' => $this->orders,
            'mappings' => $this->mappings,
            'unmappedProduks' => $this->unmappedProduks,
        ])->layout('layouts.backoffice', ['header' => 'Omnichannel Command Center']);
    }
}
