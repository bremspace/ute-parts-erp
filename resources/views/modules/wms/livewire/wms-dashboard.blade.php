<div class="space-y-6"
    x-data="{
        subModul: @js(in_array($activeTab, ['stok', 'produk']) ? 'inventori' : (in_array($activeTab, ['transfer', 'opname']) ? 'operasional' : 'pengadaan')),
        setSubModul(modul) {
            this.subModul = modul;
            if (modul === 'inventori' && !['stok', 'produk'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'stok');
            } else if (modul === 'operasional' && !['transfer', 'opname'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'transfer');
            } else if (modul === 'pengadaan' && !['po', 'grn', 'retur'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'po');
            }
        }
    }"
    x-init="$watch('$wire.activeTab', value => {
        if (['stok', 'produk'].includes(value)) subModul = 'inventori';
        else if (['transfer', 'opname'].includes(value)) subModul = 'operasional';
        else if (['po', 'grn', 'retur'].includes(value)) subModul = 'pengadaan';
    })"
>
    <!-- Header with 2-Tier Sub-Modul Tabs and Actions (No Overlapping on Mobile/Desktop) -->
    <div class="border-b border-black/10 dark:border-white/5 pb-4 space-y-3">
        <!-- Tier 1: Sub-Modul Selector & Desktop Quick Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-1 p-1 bg-black/5 dark:bg-white/[0.04] rounded-2xl border border-black/10 dark:border-white/5 w-full sm:w-auto overflow-x-auto scrollbar-none flex-nowrap">
                <button
                    type="button"
                    @click="setSubModul('inventori')"
                    :class="subModul === 'inventori' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-400 hover:text-ink-100 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[40px] whitespace-nowrap cursor-pointer"
                >
                    <span>📦</span>
                    <span>Inventori</span>
                </button>
                <button
                    type="button"
                    @click="setSubModul('operasional')"
                    :class="subModul === 'operasional' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-400 hover:text-ink-100 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[40px] whitespace-nowrap cursor-pointer"
                >
                    <span>🔄</span>
                    <span>Operasional</span>
                </button>
                <button
                    type="button"
                    @click="setSubModul('pengadaan')"
                    :class="subModul === 'pengadaan' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-400 hover:text-ink-100 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[40px] whitespace-nowrap cursor-pointer"
                >
                    <span>🚚</span>
                    <span>Pengadaan</span>
                </button>
            </div>

            <!-- Tab-specific Quick Action (Desktop) -->
            <div class="hidden lg:flex items-center gap-2 flex-shrink-0">
                @if($activeTab === 'produk')
                    <div class="flex items-center gap-2">
                        <button
                            wire:click="$dispatch('wms-import-modal')"
                            class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-ink-200 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer"
                            title="Import master produk dari Excel (inisialisasi — bukan stok masuk harian)"
                        >
                            <span>📥</span>
                            <span>Import Excel</span>
                        </button>
                        <button
                            wire:click="$dispatch('wms-produk-modal')"
                            class="px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                        >
                            <span>+ Tambah Produk</span>
                        </button>
                    </div>
                @elseif($activeTab === 'transfer')
                    <button
                        wire:click="$dispatch('wms-transfer-baru')"
                        class="px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs shadow-md shadow-up-accent/25 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Buat Transfer Baru</span>
                    </button>
                @elseif($activeTab === 'opname')
                    <button
                        wire:click="$dispatch('wms-opname-baru')"
                        class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Mulai Stock Opname</span>
                    </button>
                @elseif($activeTab === 'po')
                    <div class="flex items-center gap-2">
                        <button wire:click="$dispatch('wms-procurement-modal')" class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 border border-purple-500/30 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap" title="Rekomendasi Pengadaan Stok (ABC, ROP, Min-Max, JIT)">
                            <span>📊</span>
                            <span>Rekomendasi Pengadaan</span>
                        </button>
                        <button wire:click="$dispatch('wms-supplier-baru')" class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap">+ Supplier</button>
                        <button wire:click="$dispatch('wms-po-baru')" class="px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap">+ Buat PO</button>
                    </div>
                @elseif($activeTab === 'stok')
                    <button wire:click="$dispatch('wms-rak-modal')" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>🏬</span>
                        <span>Atur Rak</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Tier 2: Contextual Sub-Nav Tabs & Mobile Actions -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pt-1">
            <!-- Contextual Tabs: smooth horizontal scroll on mobile, scrollbar-none, touch-pan-x -->
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 max-w-full flex-nowrap scrollbar-none scroll-smooth touch-pan-x">
                <!-- Group: Inventori -->
                <div x-show="subModul === 'inventori'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap">
                    <button
                        wire:click="$set('activeTab', 'stok')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'stok' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Inventori & Stok
                    </button>
                    <button
                        wire:click="$set('activeTab', 'produk')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'produk' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Master Produk
                    </button>
                </div>

                <!-- Group: Operasional -->
                <div x-show="subModul === 'operasional'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'transfer')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'transfer' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Transfer Antar Gudang
                    </button>
                    <button
                        wire:click="$set('activeTab', 'opname')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'opname' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Stock Opname
                    </button>
                    {{-- [F3-7] Cycle count — halaman terpisah (bukan tab child) --}}
                    <a
                        href="{{ route('wms.cycle-count') }}"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 inline-flex items-center cursor-pointer"
                        title="Jadwal & task cycle count otomatis"
                    >
                        Cycle Count
                    </a>
                </div>

                <!-- Group: Pengadaan -->
                <div x-show="subModul === 'pengadaan'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'po')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'po' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        PO & Supplier
                    </button>
                    <button
                        wire:click="$set('activeTab', 'grn')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'grn' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        GRN
                    </button>
                    <button
                        wire:click="$set('activeTab', 'retur')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'retur' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Retur Pembelian
                    </button>
                </div>
            </div>

            <!-- Tab-specific Quick Action (Mobile/Tablet) -->
            <div class="lg:hidden w-full flex-shrink-0">
                @if($activeTab === 'produk')
                    <div class="flex items-center gap-2 w-full">
                        <button
                            wire:click="$dispatch('wms-import-modal')"
                            class="flex-1 px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-ink-200 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer"
                            title="Import master produk dari Excel"
                        >
                            <span>📥</span>
                            <span>Import</span>
                        </button>
                        <button
                            wire:click="$dispatch('wms-produk-modal')"
                            class="flex-1 px-3 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                        >
                            <span>+ Tambah Produk</span>
                        </button>
                    </div>
                @elseif($activeTab === 'transfer')
                    <button
                        wire:click="$dispatch('wms-transfer-baru')"
                        class="w-full px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs shadow-md shadow-up-accent/25 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Buat Transfer Baru</span>
                    </button>
                @elseif($activeTab === 'opname')
                    <button
                        wire:click="$dispatch('wms-opname-baru')"
                        class="w-full px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Mulai Stock Opname</span>
                    </button>
                @elseif($activeTab === 'po')
                    <div class="flex items-center gap-2 w-full flex-wrap sm:flex-nowrap">
                        <button wire:click="$dispatch('wms-procurement-modal')" class="flex-1 min-w-[120px] px-3 py-2.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 border border-purple-500/30 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap" title="Rekomendasi Pengadaan Stok (ABC, ROP, Min-Max, JIT)">
                            <span>📊</span>
                            <span>Rekomendasi</span>
                        </button>
                        <button wire:click="$dispatch('wms-supplier-baru')" class="flex-1 px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap">+ Supplier</button>
                        <button wire:click="$dispatch('wms-po-baru')" class="flex-1 px-3 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap">+ PO</button>
                    </div>
                @elseif($activeTab === 'stok')
                    <button wire:click="$dispatch('wms-rak-modal')" class="w-full px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <span>🏬</span>
                        <span>Atur Rak</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- TAB 1: INVENTORI & STOK — child selalu terpasang (tampil/sembunyi tanpa unmount, state paritas dgn component lama) -->
    <div @unless($activeTab === 'stok') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\StokTab::class)
    </div>

    <!-- TAB 1b: MASTER PRODUK -->
    <div @unless($activeTab === 'produk') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\ProdukTab::class)
    </div>

    <!-- TAB 2: TRANSFER ANTAR GUDANG -->
    <div @unless($activeTab === 'transfer') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\TransferTab::class)
    </div>

    <!-- TAB 3: STOCK OPNAME -->
    <div @unless($activeTab === 'opname') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\OpnameTab::class)
    </div>

    <!-- TAB 4: PO & SUPPLIER [T-10] -->
    <div @unless($activeTab === 'po') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\PoTab::class)
    </div>

    <!-- TAB 5: GRN (penerimaan barang) [F2-2] -->
    <div @unless($activeTab === 'grn') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\GrnTab::class)
    </div>

    <!-- TAB 6: RETUR PEMBELIAN -->
    <div @unless($activeTab === 'retur') class="hidden" @endunless>
        @livewire(\App\Modules\Wms\Livewire\ReturnPembelianTab::class)
    </div>
</div>
