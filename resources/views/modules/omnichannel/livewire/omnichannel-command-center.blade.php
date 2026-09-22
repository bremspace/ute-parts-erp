<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div class="flex items-center gap-2">
            @foreach(['channels' => 'Channels', 'mapping' => 'Mapping Produk', 'orders' => 'Order Terpadu'] as $kode => $label)
                <button wire:click="$set('activeTab', '{{ $kode }}')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">{{ $label }}</button>
            @endforeach
            <button wire:click="triggerSyncAll" class="ml-2 px-4 py-2 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs cursor-pointer flex items-center gap-1.5">
                ⟳ Sinkron Stok
            </button>
        </div>

        @if($activeTab === 'channels')
            <button wire:click="openConnectModal" class="px-4 py-2 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">+ Hubungkan Channel</button>
        @endif
    </div>

    @if($syncMessage)
        <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30 text-up-mint text-xs font-medium">{{ $syncMessage }}</div>
    @endif

    <!-- TAB: CHANNELS -->
    @if($activeTab === 'channels')
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($channels as $c)
                <div class="glass-panel rounded-2xl p-5 relative overflow-hidden">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h4 class="font-bold text-white">{{ $c['nama'] }}</h4>
                            <p class="text-[11px] text-ink-400 uppercase mt-0.5">{{ $c['platform'] }}</p>
                        </div>
                        <x-prism.status-pill :status="str_replace('_', '-', $c['status'])" />
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center mb-3">
                        <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                            <p class="text-sm font-bold text-white tabular-nums">{{ $c['mapping_count'] }}</p>
                            <p class="text-[9px] text-ink-400 uppercase">Produk</p>
                        </div>
                        <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                            <p class="text-sm font-bold text-white tabular-nums">{{ $c['order_count'] }}</p>
                            <p class="text-[9px] text-ink-400 uppercase">Order</p>
                        </div>
                        <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                            <p class="text-sm font-bold {{ $c['last_sync_status'] === 'sukses' ? 'text-up-mint' : 'text-up-amber' }} tabular-nums">
                                {{ $c['last_sync_at']?->format('H:i') ?? '-' }}
                            </p>
                            <p class="text-[9px] text-ink-400 uppercase">Sync</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold {{ $c['platform'] === 'shopee' ? 'text-up-mint bg-up-mint/10 px-2 py-0.5 rounded-full' : 'text-ink-400 bg-white/5 px-2 py-0.5 rounded-full' }}">
                            {{ $c['platform'] === 'shopee' ? 'MVP — Full Integrated' : 'Fase 2 — Segera' }}
                        </span>
                        <button wire:click="$set('activeTab', 'mapping')" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Atur Mapping →</button>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 text-ink-400">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9" />
                    </svg>
                    Belum ada channel terhubung. Hubungkan Shopee (MVP) untuk mulai sinkronisasi stok & order.
                </div>
            @endforelse
        </div>
    @endif

    <!-- TAB: MAPPING -->
    @if($activeTab === 'mapping')
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                <select wire:model.live="mappingChannelId" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium w-full sm:w-64">
                    <option value="" class="bg-ink-900">Semua Channel</option>
                    @foreach($channels as $c)
                        <option value="{{ $c['id'] }}" class="bg-ink-900">{{ $c['nama'] }}</option>
                    @endforeach
                </select>

                @if($mappingChannelId)
                    <div class="text-xs text-ink-300 font-medium">
                        {{ count($selectedProdukIds) }} produk dipilih →
                        <button wire:click="saveMapping" class="px-3 py-2 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer">Simpan Mapping</button>
                    </div>
                @endif
            </div>

            <x-prism.data-table :headers="['Channel', 'Produk', 'SKU Channel', 'Status', '']">
                @forelse($mappings as $m)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 text-ink-200 font-medium">{{ $m->channel?->nama }}</td>
                        <td class="py-3.5 px-4 text-white">{{ $m->produk?->nama }}</td>
                        <td class="py-3.5 px-4 font-mono text-ink-300">{{ $m->channel_sku ?? '-' }}</td>
                        <td class="py-3.5 px-4"><x-prism.status-pill :status="str_replace('_','-', $m->status)" /></td>
                        <td class="py-3.5 px-4">
                            <button wire:click="setMappingChannel({{ $m->channel_id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Filter</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-ink-400">
                            Belum ada mapping. Pilih channel & produk di bawah untuk memetakan produk lokal → SKU channel.
                        </td>
                    </tr>
                @endforelse
            </x-prism.data-table>

            @if($mappingChannelId)
                <div class="glass-panel rounded-2xl p-5">
                    <p class="text-xs font-bold text-white mb-3">Pilih Produk untuk Di-Mapping</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                        @foreach($unmappedProduks as $p)
                            <button
                                wire:click="toggleProdukMapping({{ $p->id }})"
                                class="text-left p-3 rounded-xl border text-xs transition-all cursor-pointer {{ in_array($p->id, $selectedProdukIds, true) ? 'border-up-mint bg-up-mint/10 text-up-mint font-bold' : 'border-white/10 bg-white/[0.02] text-ink-200 hover:bg-white/[0.05]' }}"
                            >
                                <span class="block font-semibold">{{ $p->nama }}</span>
                                <span class="text-[10px] text-ink-400 block mt-0.5">{{ $p->skuVariants()->first()?->sku ?? 'tanpa SKU' }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <button wire:click="saveMapping" class="px-4 py-2 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan {{ count($selectedProdukIds) }} Produk ke Channel</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- TAB: ORDERS (Unified Inbox) -->
    @if($activeTab === 'orders')
        <x-prism.data-table :headers="['Order ID', 'Channel', 'Sumber Status', 'Status Lokal', 'Transaksi', 'Waktu']">
            @forelse($orders as $o)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $o->channel_order_id }}</td>
                    <td class="py-3.5 px-4">
                        <span class="text-up-accent font-semibold">{{ $o->channel?->nama }}</span>
                    </td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $o->channel_status ?? '-' }}</td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="str_replace('_','-', $o->status)" /></td>
                    <td class="py-3.5 px-4 font-mono text-ink-300">{{ $o->transaksi?->no_transaksi ?? 'Belum diproses' }}</td>
                    <td class="py-3.5 px-4 text-ink-400">{{ $o->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-12 text-center text-ink-400">
                        Unified Inbox kosong — order dari channel eksternal akan muncul di sini via webhook/polling.
                    </td>
                </tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- MODAL: CONNECT CHANNEL -->
    @if($showConnectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Hubungkan Channel Baru</h3>
                    <button wire:click="$set('showConnectModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Platform</label>
                        <select wire:model="connectForm.platform" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="shopee" class="bg-ink-900">Shopee (MVP)</option>
                            <option value="tokopedia" class="bg-ink-900">Tokopedia (Fase 2)</option>
                            <option value="blibli" class="bg-ink-900">Blibli (Fase 2)</option>
                            <option value="tiktok" class="bg-ink-900">TikTok Shop (Fase 2)</option>
                            <option value="lazada" class="bg-ink-900">Lazada (Fase 2)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Channel</label>
                        <input type="text" wire:model="connectForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Ute Parts Official — Shopee" />
                    </div>

                    @if($connectForm['platform'] === 'shopee')
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-3">
                            <p class="text-[10px] text-ink-400 uppercase font-bold">Kredensial Shopee Open API</p>
                            <input type="text" wire:model="connectForm.partner_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="Partner ID" />
                            <input type="password" wire:model="connectForm.partner_key" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="Partner Key" />
                            <input type="text" wire:model="connectForm.shop_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="Shop ID" />
                        </div>
                    @else
                        <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 text-xs text-up-amber">
                            Adapter {{ $connectForm['platform'] }} fase 2 — channel tersimpan, integrasi penuh menyusul. Kredensial OAuth akan ditambahkan saat adapter rilis.
                        </div>
                    @endif
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showConnectModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="connectChannel" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Hubungkan</button>
                </div>
            </div>
        </div>
    @endif
</div>