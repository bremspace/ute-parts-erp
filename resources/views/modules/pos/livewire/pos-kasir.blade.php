<div
    class="flex flex-col lg:flex-row gap-4 h-[calc(100vh-8.5rem)]"
    x-data="{ cartOpen: false, flexSet: {} }"
    @keydown.window.f4.prevent="$wire.openPaymentModal()"
    @keydown.window.f6.prevent="$wire.tahanTransaksi()"
    @keydown.window.escape.prevent="$wire.clearCart()"
>
    <!-- LEFT COLUMN: Product Catalog & Search -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
        <!-- Top Toolbar: Search & Gudang Selector -->
        <div class="flex items-center gap-3 mb-3 flex-shrink-0 flex-wrap">
            <div class="flex-1 min-w-0">
                <div class="relative">
                    <x-prism.barcode-scan-input
                        placeholder="Scan Barcode atau ketik nama/tipe HP (Tekan F2, Enter=add)..."
                        model="search"
                        wire:keydown.enter="scanEnter"
                    />
                    <button wire:click="scanEnter"
                            class="absolute right-12 top-1/2 -translate-y-1/2 px-3 py-2 rounded-lg bg-up-primary/20 hover:bg-up-primary/40 text-up-primary text-xs font-bold cursor-pointer"
                            title="Tambah dari barcode/SKU (Enter)">ADD</button>
                </div>
            </div>

            <div class="w-full lg:w-48">
                <select
                    wire:model.live="selectedGudangId"
                    class="w-full px-3 py-2.5 rounded-xl glass-input text-sm font-medium"
                >
                    <option value="" class="bg-ink-900">Pilih Gudang...</option>
                    @foreach(\App\Modules\Wms\Models\Gudang::all() as $g)
                        <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="flex-1 overflow-y-auto pr-1">
            <div class="grid grid-cols-1 xxs:grid-cols-2 xl:grid-cols-3 gap-3">
                @forelse($products as $prod)
                    @php
                        $stokTotal = $selectedGudangId
                            ? \App\Modules\Wms\Models\StokItem::where('produk_id', $prod->id)->where('gudang_id', $selectedGudangId)->sum('jumlah')
                            : 99;
                        $pricing = app(\App\Modules\Pos\Services\PricingService::class)->resolve($prod, $this->customer);
                    @endphp
                    <div
                        wire:key="product-{{ $prod->id }}"
                        wire:click="addToCart({{ $prod->id }})"
                        class="glass-panel glass-panel-hover p-3.5 rounded-2xl flex flex-col justify-between cursor-pointer border border-white/5 transition-all select-none group"
                    >
                        <div>
                            <!-- Header: Category & Stock -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] uppercase font-semibold text-ink-400 truncate max-w-[80px]">
                                    {{ $prod->kategori ?? 'Sparepart' }}
                                </span>
                                <x-prism.stock-gauge :stok="$stokTotal" :min="5" />
                            </div>

                            <!-- Product Name & Compatibility -->
                            <h4 class="font-semibold text-white text-xs sm:text-sm line-clamp-2 leading-snug group-hover:text-up-primary transition-colors">
                                {{ $prod->nama }}
                            </h4>

                            @if($prod->brand_kompatibel || $prod->model_kompatibel)
                                <p class="text-[11px] text-ink-400 mt-1 truncate">
                                    {{ $prod->brand_kompatibel }} {{ $prod->model_kompatibel }}
                                </p>
                            @endif
                        </div>

                        <!-- Price Section -->
                        <div class="mt-3 pt-2.5 border-t border-white/5 flex items-end justify-between">
                            <div>
                                @if($pricing['diskon_nominal'] > 0)
                                    <span class="text-[10px] text-ink-500 line-through tabular-nums block">
                                        Rp {{ number_format($pricing['harga_dasar'], 0, ',', '.') }}
                                    </span>
                                @endif
                                <span class="text-sm font-bold text-up-mint tabular-nums">
                                    Rp {{ number_format($pricing['harga'], 0, ',', '.') }}
                                </span>
                            </div>

                            <span class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center text-ink-300 group-hover:bg-up-primary group-hover:text-white transition-all text-xs">
                                +
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-16 text-center text-ink-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-30 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="text-sm">Tidak ada produk ditemukan untuk pencarian "{{ $search }}"</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Customer, Cart & Payment Summary -->
    <!-- Mobile: Bottom Sheet | Desktop: Side Panel -->
    <div x-data="{ cartPanelOpen: false }"
         x-init="cartPanelOpen = window.innerWidth >= 1024"
         @resize.window.debounce.100ms="cartPanelOpen = window.innerWidth >= 1024"
         class="lg:w-[420px] lg:flex-shrink-0 lg:flex lg:flex-col lg:h-full lg:bg-ink-900 lg:border lg:border-white/5 lg:rounded-3xl lg:p-5 lg:overflow-hidden lg:shadow-2xl">

        <!-- Mobile: Cart Toggle Button (44px touch target) -->
        <button @click="cartPanelOpen = !cartPanelOpen"
                class="lg:hidden w-full px-4 py-3 rounded-xl bg-ink-900 border border-white/5 flex items-center justify-between text-sm font-medium shadow-lg sticky bottom-0 z-10 min-h-[44px]"
                :aria-expanded="cartPanelOpen"
                :aria-label="cartPanelOpen ? 'Tutup keranjang' : 'Buka keranjang'">
            <span class="flex items-center gap-2 min-h-[44px]">
                <svg class="w-5 h-5 text-up-mint flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span>Keranjang</span>
            </span>
            <span class="px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint text-xs font-bold tabular-nums flex items-center min-h-[44px]">
                {{ count($cart) ?? 0 }}
            </span>
            <svg class="w-5 h-5 text-ink-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': cartPanelOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Mobile: Bottom Sheet Overlay + Panel -->
        <div x-show="cartPanelOpen && window.innerWidth < 1024"
             x-transition:enter="transition-opacity ease-linear duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/60 z-50 lg:hidden"
             @click="cartPanelOpen = false"
             aria-hidden="true"></div>

        <aside x-show="cartPanelOpen && window.innerWidth < 1024"
               x-transition:enter="transition-transform ease-out duration-200"
               x-transition:enter-start="translate-y-full"
               x-transition:enter-end="translate-y-0"
               x-transition:leave="transition-transform ease-in duration-150"
               x-transition:leave-start="translate-y-0"
               x-transition:leave-end="translate-y-full"
               class="fixed bottom-0 left-0 right-0 z-50 max-h-[80vh] lg:hidden"
               @click.outside="cartPanelOpen = false"
               style="padding-bottom: env(safe-area-inset-bottom, 0);">
            <div class="bg-ink-900 border-t border-white/5 rounded-t-3xl p-4 shadow-2xl max-h-[80vh] flex flex-col">
                <!-- Drag Handle -->
                <div class="w-10 h-1.5 bg-white/10 rounded-full mx-auto mb-3"></div>
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-white/5">
                    <h3 class="text-lg font-bold text-white">Keranjang</h3>
                    <button @click="cartPanelOpen = false"
                            class="p-2 text-ink-400 hover:text-white rounded-lg hover:bg-white/5 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3">
                    @include('modules.pos.livewire.partials.cart-content', [
                        'cart' => $cart,
                        'total' => $total,
                        'diskonTotal' => $diskonTotal,
                        'totalBayar' => $totalBayar,
                        'customer' => $this->customer,
                        'canHargaFleksibel' => $canHargaFleksibel,
                    ])
                </div>
            </div>
        </aside>

        <!-- Desktop: Side Panel Content -->
        <div class="hidden lg:flex lg:flex-col lg:h-full lg:bg-ink-900 lg:border lg:border-white/5 lg:rounded-3xl lg:p-5 lg:overflow-hidden lg:shadow-2xl">
            <!-- Customer Selector -->
            <div class="mb-4 pb-3.5 border-b border-white/5 flex-shrink-0">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold text-ink-300 uppercase tracking-wider">Pelanggan / Member</label>
                    @if($this->customer)
                        <x-prism.tier-badge :tier="$this->customer->tierMembership?->nama ?? ($this->customer->is_reseller ? 'Reseller' : 'Retail')" />
                    @endif
                </div>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <select
                            wire:model.live="selectedCustomerId"
                            wire:change="setPelanggan($event.target.value)"
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                        >
                            @if(!$pelangganCari->isEmpty())<option value="" class="bg-ink-900">— Pilih dari daftar / cari di bawah —</option>@endif
                            <option value="" class="bg-ink-900">Pelanggan Umum (Tanpa Member)</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" class="bg-ink-900">
                                    {{ $c->nama }} — {{ $c->telepon ?? '-' }} ({{ $c->tierMembership?->nama ?? 'Retail' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($selectedCustomerId)
                        <button
                            wire:click="setPelanggan(null)"
                            class="px-2.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs"
                            title="Reset Pelanggan"
                        >
                            ✕
                        </button>
                    @endif

                    <!-- [T-18] Pencarian + tambah pelanggan reusable (sinkron CRM-06 via PelangganService) -->
                    <x-customer-picker
                        :results="$pelangganCari"
                        wireModel="pelangganSearch"
                        selectAction="setPelanggan"
                        addAction="openPelangganBaru"
                        searchPlaceholder="Cari pelanggan: nama / no HP..."
                    />
                </div>

            @if($this->customer && $this->customer->tierMembership)
                <div class="mt-2 text-[11px] text-up-mint flex items-center gap-1.5 font-medium">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Diskon {{ $this->customer->tierMembership->diskon_persen }}% otomatis aktif untuk member {{ $this->customer->tierMembership->nama }}</span>
                </div>
            @endif
        </div>

        <!-- Cart Items List (Scrollable) -->
        <div class="flex-1 overflow-y-auto pr-1 space-y-2.5 mb-4">
            @forelse($cart as $key => $item)
                <div
                    x-data="{
                        flexOpen: false,
                        flexHarga: '{{ number_format($item['harga'] ?? 0, 0, '', '') }}',
                        saveFlexHarga(key) {
                            const raw = this.flexHarga.replace(/\./g, '').replace(/,/g, '.');
                            const val = Number(raw);
                            if (isNaN(val)) {
                                this.$dispatch('alert', { type: 'error', message: 'Harga tidak valid' });
                                return;
                            }
                            @if(canSeeField('harga_beli'))
                            if (val < {{ $item['harga_beli'] ?? $item['harga'] ?? 0 }}) {
                                this.$dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal (Rp {{ number_format($item['harga_beli'] ?? $item['harga'] ?? 0, 0, '', '') }})' });
                                return;
                            }
@else
                            if (val < {{ $item['harga'] ?? 0 }}) {
                                this.$dispatch('alert', { type: 'error', message: 'Harga tidak boleh di bawah modal (Rp {{ number_format($item['harga'] ?? 0, 0, '', '') }})' });
                                return;
                            }
@endif
                            @this.setHargaFleksibel('{{ $key }}', val);
                            this.flexOpen = false;
                            this.flexHarga = '{{ number_format($item['harga'] ?? 0, 0, '', '') }}';
                        }
                    }"
                    class="p-3 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between gap-3"
                >
                    <div class="flex-1 min-w-0">
                        <h5 class="text-xs font-semibold text-white truncate">{{ $item['nama'] }}</h5>
                        <div class="flex items-center gap-2 mt-0.5 text-[11px] flex-wrap">
                            @if(isset($item['flex']) && $item['flex'])
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-up-amber/20 text-up-amber text-[10px] font-medium border border-up-amber/30">
                                    Fleksibel
                                </span>
                            @endif
                            @if($item['varian'] !== 'Standar')
                                <span class="px-1.5 py-0.2 rounded bg-white/5 text-ink-300 text-[10px]">{{ $item['varian'] }}</span>
                            @endif
                        </div>
                        <!-- Price & Flex Control -->
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            @if(isset($item['flex']) && $item['flex'])
                                <span
                                    :class="flexSet['{{ $key }}'] ? 'text-white' : 'text-up-amber'"
                                    class="tabular-nums font-medium text-xs"
                                >
                                    Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                </span>
                                @if(!flexSet['{{ $key }}'])
                                    <span class="text-up-amber text-[10px] font-medium">(belum diatur)</span>
                                @endif
                                @if($canHargaFleksibel)
                                    <button
                                        type="button"
                                        @click="flexOpen = true"
                                        class="px-2 py-1 rounded-lg text-[10px] font-semibold text-up-amber border border-up-amber/40 hover:bg-up-amber/10 transition-colors cursor-pointer whitespace-nowrap"
                                        aria-label="Atur harga jual fleksibel"
                                        title="Atur Harga (Superadmin)"
                                    >
                                        Atur Harga
                                    </button>
                                @else
                                    <span class="px-2 py-1 rounded-lg text-[10px] font-medium text-ink-500 bg-white/5 border border-white/5 cursor-not-allowed" title="Akses superadmin diperlukan">
                                        Atur Harga
                                    </span>
                                @endif

                                <!-- Inline Popover: Atur Harga -->
                                <div
                                    x-show="flexOpen"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 transform -translate-y-1"
                                    x-transition:enter-end="opacity-100 transform translate-y-0"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 transform translate-y-0"
                                    x-transition:leave-end="opacity-0 transform -translate-y-1"
                                    @click.outside="flexOpen = false"
                                    @keydown.escape="flexOpen = false"
                                    class="absolute z-20 mt-1 min-w-[200px] glass-panel p-3 rounded-xl border border-white/10 shadow-lg"
                                    style="max-width: 240px;"
                                    x-cloak
                                >
                                    <label class="block text-[10px] font-semibold text-ink-300 mb-1.5">Harga Jual (Rp)</label>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        x-model="flexHarga"
                                        @keydown.enter.prevent="saveFlexHarga('{{ $key }}')"
                                        x-ref="flexInput"
                                        placeholder="0"
                                        class="w-full px-3 py-2 rounded-lg glass-input text-sm font-bold tabular-nums text-white"
                                        aria-label="Harga jual fleksibel"
                                    />
                                    @if(canSeeField('harga_beli'))
                                    <p class="text-[10px] text-up-amber/80 mt-1.5">Min: Rp {{ number_format($item['harga_beli'] ?? $item['harga'], 0, ',', '.') }}</p>
@else
                                    <p class="text-[10px] text-up-amber/80 mt-1.5">Min: —</p>
@endif
                                    <div class="flex gap-2 mt-2.5">
                                        <button
                                            type="button"
                                            @click="saveFlexHarga('{{ $key }}')"
                                            class="flex-1 py-2 rounded-lg bg-up-amber hover:opacity-90 text-ink-950 font-bold text-xs transition-all cursor-pointer min-h-[40px]"
                                        >
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            @click="flexOpen = false"
                                            class="flex-1 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[40px] border border-white/5"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            @else
                                <span class="tabular-nums">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                            @endif
                        </div>

                        {{-- [F2-3] SN wajib utk produk sn=true --}}
                        @include('modules.pos.livewire.partials.sn-control', ['itemKey' => $key, 'item' => $item])
                    </div>

                    <!-- Quantity Controls -->
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <button
                            wire:click="updateQty('{{ $key }}', -1)"
                            class="w-6 h-6 rounded-md bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer"
                        >
                            -
                        </button>
                        <span class="w-8 text-center text-xs font-bold text-white tabular-nums">
                            {{ $item['qty'] }}
                        </span>
                        <button
                            wire:click="updateQty('{{ $key }}', 1)"
                            class="w-6 h-6 rounded-md bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-xs font-bold transition-all cursor-pointer"
                        >
                            +
                        </button>
                    </div>

                    <!-- Line Subtotal & Delete -->
                    <div class="text-right min-w-[70px] flex-shrink-0">
                        <span class="text-xs font-bold text-white tabular-nums block">
                            Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                        </span>
                        <button
                            wire:click="removeFromCart('{{ $key }}')"
                            class="text-[10px] text-up-red/70 hover:text-up-red transition-colors"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-center text-ink-400 py-12">
                    <svg class="w-10 h-10 mb-2 opacity-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="text-xs">Keranjang belanja kosong</p>
                    <span class="text-[10px] text-ink-500 mt-1">Pilih produk di sebelah kiri atau tekan F2</span>
                </div>
            @endforelse
        </div>

        <!-- Summary & Actions Area (Fixed at bottom) -->
        <div class="border-t border-white/5 pt-4 flex-shrink-0 space-y-3">
            <div class="space-y-1.5 text-xs text-ink-300">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span class="font-semibold text-white tabular-nums">Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($diskonNominal > 0 || $diskonPersen > 0)
                    <div class="flex justify-between text-up-mint">
                        <span>Diskon</span>
                        <span class="font-semibold tabular-nums">- Rp {{ number_format($this->subtotal - $this->totalAkhir, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-white/10">
                    <span>Total Akhir</span>
                    <span class="text-up-mint text-xl tabular-nums">Rp {{ number_format($this->totalAkhir, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Action Buttons Grid -->
            <div class="grid grid-cols-3 gap-2 pt-1">
                <button
                    type="button"
                    wire:click="clearCart"
                    class="py-3 px-2 rounded-xl bg-white/5 hover:bg-up-red/20 text-ink-400 hover:text-up-red font-semibold text-xs transition-all border border-white/5 flex flex-col items-center justify-center gap-1 cursor-pointer min-h-[44px]"
                >
                    <span>Batal (ESC)</span>
                </button>

                <button
                    type="button"
                    wire:click="tahanTransaksi"
                    class="py-3 px-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white font-semibold text-xs transition-all border border-white/5 flex flex-col items-center justify-center gap-1 cursor-pointer min-h-[44px]"
                >
                    <span>Tahan (F6)</span>
                </button>

                <button
                    type="button"
                    wire:click="openPaymentModal"
                    class="py-3 px-2 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark hover:to-indigo-700 text-white font-bold text-xs shadow-lg shadow-up-primary/30 transition-all border border-white/20 flex flex-col items-center justify-center gap-0.5 cursor-pointer active:scale-95 min-h-[44px]"
                >
                    <span>BAYAR</span>
                    <span class="text-[10px] font-mono text-white/70">F4</span>
                </button>
            </div>

            <!-- [T-09] Status Kas Sesi -->
            <div class="flex items-center justify-between pt-2">
                @if($kasAktif)
                    <div class="flex items-center gap-2 text-[11px] text-up-mint">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                        <span>Kas terbuka · saldo awal <span class="tabular-nums">Rp {{ number_format($kasAktif->saldo_awal, 0, ',', '.') }}</span></span>
                    </div>
                    <button wire:click="tutupKasModal" class="text-[11px] font-bold text-up-amber hover:text-up-red cursor-pointer">Tutup Kas</button>
                @else
                    <div class="flex items-center gap-2 text-[11px] text-up-amber">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-amber"></span>
                        <span>Kas belum dibuka — transaksi tunai diblokir</span>
                    </div>
                    <button wire:click="bukaKasModal" class="text-[11px] font-bold text-up-primary hover:text-indigo-400 cursor-pointer">Buka Kas</button>
                @endif
            </div>

            <!-- [T-03] Panel transaksi ditahan -->
            <div class="pt-1">
                <button wire:click="$toggle('showDitahanPanel')" class="text-[11px] font-semibold text-ink-400 hover:text-white flex items-center gap-1 cursor-pointer">
                    <span>{{ $showDitahanPanel ? '▼' : '▶' }}</span>
                    Transaksi Ditahan ({{ $ditahanList->count() }})
                </button>
                @if($showDitahanPanel && $ditahanList->isNotEmpty())
                    <div class="mt-2 space-y-1.5 max-h-44 overflow-y-auto">
                        @foreach($ditahanList as $dt)
                            <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white/[0.03] border border-white/5 text-[11px]">
                                <div class="min-w-0">
                                    <span class="font-mono text-white truncate block">{{ $dt->no_transaksi }}</span>
                                    <span class="text-ink-400">Rp <span class="tabular-nums">{{ number_format($dt->total_akhir, 0, ',', '.') }}</span> · {{ $dt->kasir?->name }}</span>
                                </div>
                                <button wire:click="resumeDitahan({{ $dt->id }})" class="px-2.5 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary font-bold cursor-pointer whitespace-nowrap">Lanjutkan</button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- PAYMENT MODAL -->
    @if($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Pembayaran Transaksi</h3>
                    <button wire:click="$set('showPaymentModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <!-- Total Amount Highlight -->
                <div class="bg-white/5 p-4 rounded-2xl text-center mb-5 border border-white/5">
                    <span class="text-xs uppercase tracking-wider text-ink-400 font-semibold block mb-1">Total Tagihan</span>
                    <span class="text-3xl font-black text-up-mint tabular-nums">
                        Rp {{ number_format($this->totalAkhir, 0, ',', '.') }}
                    </span>
                </div>

                <!-- Payment Method Selector -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-ink-300 mb-2">Metode Pembayaran</label>
                    <div class="grid grid-cols-5 gap-2">
                        @foreach(['tunai' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'split' => 'Split', 'piutang' => 'Kasbon'] as $val => $label)
                            <button
                                type="button"
                                wire:click="$set('metodeBayar', '{{ $val }}')"
                                class="py-2.5 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ $metodeBayar === $val ? 'bg-up-primary text-white border-up-primary shadow-md shadow-up-primary/30' : 'bg-white/5 text-ink-300 border-white/10 hover:bg-white/10' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Method Detail Inputs -->
                @if($metodeBayar === 'tunai')
                    <div class="space-y-3 mb-5">
                        <label class="block text-xs font-semibold text-ink-300">Uang Diterima</label>
                        <input
                            type="text" inputmode="numeric" x-format-number
                            wire:model.live="jumlahBayar"
                            class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums text-white"
                        />

                        <!-- Quick Cash Shortcuts -->
                        <div class="flex gap-2 pt-1 flex-wrap">
                            <button type="button" wire:click="setQuickCash({{ $this->totalAkhir }})" class="px-3 py-1.5 rounded-lg bg-white/5 text-xs text-ink-200 hover:bg-white/10 border border-white/5 font-semibold">Uang Pas</button>
                            <button type="button" wire:click="setQuickCash(50000)" class="px-3 py-1.5 rounded-lg bg-white/5 text-xs text-ink-200 hover:bg-white/10 border border-white/5 font-semibold">50.000</button>
                            <button type="button" wire:click="setQuickCash(100000)" class="px-3 py-1.5 rounded-lg bg-white/5 text-xs text-ink-200 hover:bg-white/10 border border-white/5 font-semibold">100.000</button>
                            <button type="button" wire:click="setQuickCash(200000)" class="px-3 py-1.5 rounded-lg bg-white/5 text-xs text-ink-200 hover:bg-white/10 border border-white/5 font-semibold">200.000</button>
                        </div>

                        <!-- Kembalian Display -->
                        <div class="flex justify-between items-center p-3 rounded-xl bg-white/[0.02] border border-white/5 text-sm">
                            <span class="text-ink-400 font-medium">Kembalian</span>
                            <span class="font-bold text-lg tabular-nums {{ $this->kembalian > 0 ? 'text-up-mint' : 'text-white' }}">
                                Rp {{ number_format($this->kembalian, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @elseif($metodeBayar === 'split')
                    <div class="space-y-3 mb-5">
                        <div>
                            <label class="block text-xs text-ink-300 mb-1">Nominal Tunai</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live="splitTunai" class="w-full px-3 py-2 rounded-xl glass-input text-sm font-bold tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs text-ink-300 mb-1">Nominal Non-Tunai</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live="splitNonTunai" class="w-full px-3 py-2 rounded-xl glass-input text-sm font-bold tabular-nums" />
                        </div>
                    </div>
                @elseif($metodeBayar === 'piutang')
                    <div class="p-4 rounded-xl bg-up-amber/10 text-center text-xs text-up-amber mb-5 border border-up-amber/30">
                        <svg class="w-6 h-6 mx-auto mb-1.5" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h16v12H4V6zm5 2a1 1 0 100 2h6a1 1 0 100-2H9zm-1 6a1 1 0 011-1h6a1 1 0 110 2H9a1 1 0 01-1-1zm1-4a1 1 0 100 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
                        </svg>
                        <strong class="block text-sm mb-1">Kasbon / Piutang</strong>
                        <span>Transaksi dicatat sebagai piutang pelanggan (jatuh tempo 30 hari). Akun <strong>Piutang Usaha</strong> otomatis ter-debit di jurnal.</span>
                    </div>
                @else
                    <div class="p-4 rounded-xl bg-white/5 text-center text-xs text-ink-300 mb-5 border border-white/5">
                        Silakan scan QRIS atau konfirmasi bukti transfer sebesar
                        <strong class="text-white block mt-1 text-sm font-bold tabular-nums">
                            Rp {{ number_format($this->totalAkhir, 0, ',', '.') }}
                        </strong>
                    </div>
                @endif

                <!-- Submit Button -->
                <div class="flex gap-3">
                    <button
                        type="button"
                        wire:click="$set('showPaymentModal', false)"
                        class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-sm transition-all border border-white/10 cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="processTransaction"
                        class="flex-2 py-3 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-bold text-sm transition-all shadow-lg shadow-up-mint/20 cursor-pointer"
                    >
                        Konfirmasi & Cetak Struk
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- RECEIPT MODAL (Thermal Print Preview) -->
    @if($showReceiptModal && $receiptData)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
            x-data="thermalPrinter(@json($receiptData))"
        >
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative"> 
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-white/10">
                    <h4 class="text-sm font-bold text-white">Struk Transaksi Selesai</h4>
                    <div class="flex items-center gap-3">
                        @if($completedTransactionId && auth()->user()?->can('lihat-audit-log'))
                            <button
                                wire:click="bukaRiwayat('transaksi', {{ $completedTransactionId }})"
                                class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer"
                            >
                                Riwayat
                            </button>
                        @endif
                        <button wire:click="$set('showReceiptModal', false)" class="text-ink-400 hover:text-white">✕</button>
                    </div>
                </div>

                <!-- [T-35] A4 Web Print: konten modal dicetak apa adanya via window.print() -->
                <div class="print-area print-a4">
                <!-- Thermal Paper Simulation -->
                <div class="bg-white text-ink-950 p-5 rounded-xl font-mono text-xs shadow-inner space-y-3" id="thermalReceipt">
                    <div class="text-center pb-2 border-b border-dashed border-gray-400">
                        <h3 class="font-bold text-sm tracking-wider">UTE PARTS</h3>
                        <p class="text-[10px] text-gray-600">Pusat Sparepart & Servis HP</p>
                        <p class="text-[9px] text-gray-500">{{ $receiptData['waktu'] }}</p>
                    </div>

                    <div class="text-[10px] space-y-0.5 border-b border-dashed border-gray-300 pb-2">
                        <div class="flex justify-between">
                            <span>No. TRX:</span>
                            <span class="font-bold">{{ $receiptData['no_transaksi'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Kasir:</span>
                            <span>{{ $receiptData['kasir'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Member:</span>
                            <span>{{ $receiptData['pelanggan'] }} ({{ $receiptData['tier'] }})</span>
                        </div>
                    </div>

                    <!-- Item rows -->
                    <div class="space-y-1.5 border-b border-dashed border-gray-300 pb-2">
                        @foreach($receiptData['items'] as $it)
                            <div>
                                <div class="font-semibold">{{ $it['nama'] }}</div>
                                <div class="flex justify-between text-[10px] text-gray-600">
                                    <span>{{ $it['qty'] }} x {{ number_format($it['harga'], 0, ',', '.') }}</span>
                                    <span class="font-bold text-gray-900">{{ number_format($it['subtotal'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Total Calculation -->
                    <div class="space-y-1 text-[11px] pt-1 font-bold">
                        <div class="flex justify-between">
                            <span>TOTAL:</span>
                            <span>Rp {{ number_format($receiptData['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-gray-700 font-normal text-[10px]">
                            <span>BAYAR ({{ $receiptData['metode'] }}):</span>
                            <span>Rp {{ number_format($receiptData['bayar'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-gray-700 font-normal text-[10px]">
                            <span>KEMBALI:</span>
                            <span>Rp {{ number_format($receiptData['kembali'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="text-center pt-2 text-[9px] text-gray-500 border-t border-dashed border-gray-300">
                        <p>Terima Kasih atas Kunjungan Anda!</p>
                        <p>Garansi part sesuai ketentuan toko.</p>
                    </div>
                </div>
                </div><!-- end print-a4 -->

                <!-- [T-35] Target cetak tersembunyi: fallback Web Print 58/80 (diisi JS saat gagal Bluetooth) -->
                <div class="print-area print-58" id="printTarget-58" aria-hidden="true"></div>
                <div class="print-area print-80" id="printTarget-80" aria-hidden="true"></div>

                <!-- [T-35] Cetak thermal via Web Bluetooth (Chrome Android/Edge) -->
                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        @click="printStruk58()"
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-bold text-xs shadow-lg shadow-up-mint/20 cursor-pointer min-h-[44px]"
                        aria-label="Cetak struk 58mm langsung ke printer Bluetooth"
                    >
                        <span class="block">Cetak Bluetooth 58mm</span>
                        <span class="block text-[9px] font-medium opacity-80">langsung ke printer</span>
                    </button>
                    <button
                        type="button"
                        @click="printFaktur80()"
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:opacity-95 text-white font-bold text-xs shadow-lg shadow-up-primary/20 cursor-pointer min-h-[44px]"
                        aria-label="Cetak faktur 80mm langsung ke printer Bluetooth"
                    >
                        <span class="block">Cetak Faktur 80mm</span>
                        <span class="block text-[9px] font-medium opacity-80">lembar penuh + barcode</span>
                    </button>
                </div>
                <p class="mt-2 text-[10px] text-ink-400">
                    Di iPhone/tablet, pakai <span class="font-semibold text-ink-300">Cetak Struk (A4)</span>.
                </p>

                <!-- Print Action Button -->
                <div class="mt-4 flex gap-2">
                    <button
                        onclick="window.print()"
                        class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md cursor-pointer min-h-[44px]"
                    >
                        Cetak Struk
                    </button>
                    <button
                        wire:click="$set('showReceiptModal', false)"
                        class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-medium text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- [T-08] MODAL: PELANGGAN BARU -->
    @if($showPelangganBaruModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Pelanggan Baru</h3>
                    <button wire:click="$set('showPelangganBaruModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="pelangganBaruForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">No. HP *</label>
                        <input type="text" wire:model="pelangganBaruForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="08xx-xxxx-xxxx" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email</label>
                        <input type="email" wire:model="pelangganBaruForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="pelangganBaruForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Lahir (opsional)</label>
                        <input type="date" wire:model="pelangganBaruForm.tanggal_lahir" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <p class="text-[10px] text-ink-500">Pelanggan baru langsung tersedia di POS, CRM, dan Servis (satu data).</p>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showPelangganBaruModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanPelangganBaru" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- [T-09] MODAL: KAS SESI -->
    @if($showKasModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
             x-data="{}"
             x-init="$nextTick(() => { const el = $refs.kasSaldoInput; if (el) el.focus(); el?.select(); })">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $kasModeBuka ? 'Buka Kas (Shift Baru)' : 'Tutup Kas (Rekap Shift)' }}</h3>
                    <button wire:click="$set('showKasModal', false)" class="p-2.5 text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                @if(!$kasHasil)
                    <div class="space-y-4">
                        @if($kasModeBuka)
                            <div>
                                <label for="kasSaldoAwal" class="block text-xs font-semibold text-ink-300 mb-1.5">Saldo Awal (Rp)</label>
                                <input
                                    id="kasSaldoAwal"
                                    type="text" inputmode="numeric" wire:model.debounce.300ms="kasSaldoAwalRaw" min="0"
                                    x-ref="kasSaldoInput"
                                    @keydown.enter.prevent="$wire.prosesKas()"
                                    @blur="$wire.formatKasSaldoAwal()"
                                    class="w-full px-3 py-3 rounded-xl glass-input text-lg font-bold tabular-nums {{ $errors->has('kasSaldoAwalRaw') ? 'border-up-red/60' : '' }}"
                                    placeholder="0"
                                />
                                @error('kasSaldoAwalRaw')
                                    <p class="mt-1.5 text-[11px] font-medium text-up-red">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-ink-400">Saldo awal</span>
                                    <span class="tabular-nums text-white">{{ number_format($kasAktif?->saldo_awal ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <div>
                                <label for="kasSaldoFisik" class="block text-xs font-semibold text-ink-300 mb-1.5">Saldo Fisik Akhir (Rp) *</label>
                                <input
                                    id="kasSaldoFisik"
                                    type="text" inputmode="numeric" wire:model.debounce.300ms="kasSaldoFisikRaw" min="0" step="500"
                                    x-ref="kasSaldoInput"
                                    @keydown.enter.prevent="$wire.prosesKas()"
                                    @blur="$wire.formatKasSaldoFisik()"
                                    class="w-full px-3 py-3 rounded-xl glass-input text-lg font-bold tabular-nums {{ $errors->has('kasSaldoFisikRaw') ? 'border-up-red/60' : '' }}"
                                />
                                @error('kasSaldoFisikRaw')
                                    <p class="mt-1.5 text-[11px] font-medium text-up-red">{{ $message }}</p>
                                @enderror
                            </div>
                            <p class="text-[10px] text-ink-500">Sistem menghitung saldo sistem & selisih otomatis; selisih ≠ 0 membuat jurnal penyesuaian.</p>
                        @endif
                    </div>
                @else
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between p-2.5 rounded-lg bg-white/[0.03]">
                            <span class="text-ink-400">{{ $kasModeBuka ? 'Saldo awal' : 'Saldo sistem' }}</span>
                            <span class="font-bold text-white tabular-nums">Rp {{ number_format($kasHasil['saldo_awal'] ?? $kasHasil['saldo_sistem'], 0, ',', '.') }}</span>
                        </div>
                        @if(!$kasModeBuka)
                            <div class="flex justify-between p-2.5 rounded-lg bg-white/[0.03]">
                                <span class="text-ink-400">Saldo fisik</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($kasHasil['saldo_fisik'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between p-2.5 rounded-lg {{ $kasHasil['selisih'] == 0 ? 'bg-up-mint/10' : 'bg-up-amber/10' }}">
                                <span class="text-ink-400">Selisih</span>
                                <span class="font-bold {{ $kasHasil['selisih'] == 0 ? 'text-up-mint' : 'text-up-amber' }} tabular-nums">Rp {{ number_format($kasHasil['selisih'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKasModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Tutup</button>
                    @if(!$kasHasil)
                        <button wire:click="prosesKas" class="flex-1 py-3 rounded-xl {{ $kasModeBuka ? 'bg-up-mint' : 'bg-up-accent' }} text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">
                            {{ $kasModeBuka ? 'Buka Kas' : 'Tutup & Rekap' }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail transaksi (dibuka dari header struk) --}}
    @include('partials.riwayat-modal')
</div>
