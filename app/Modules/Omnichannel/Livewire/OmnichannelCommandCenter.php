<?php

namespace App\Modules\Omnichannel\Livewire;

use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Wms\Models\Produk;
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
        $query = ChannelProductMapping::with(['channel', 'produk'])
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
        if (! $this->mappingChannelId || empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih channel & minimal 1 produk']);

            return;
        }

        foreach ($this->selectedProdukIds as $produkId) {
            $produk = Produk::find($produkId);
            ChannelProductMapping::updateOrCreate(
                [
                    'channel_id' => $this->mappingChannelId,
                    'produk_id' => $produkId,
                ],
                [
                    'channel_sku' => $produk?->skuVariants()->first()?->sku,
                    'status' => 'tersinkron',
                ]
            );
        }

        $this->selectedProdukIds = [];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Mapping produk disimpan & stok siap sinkron']);
    }

    public function triggerSyncAll()
    {
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
