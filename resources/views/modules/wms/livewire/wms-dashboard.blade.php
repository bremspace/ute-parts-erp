<div class="space-y-6">
    <!-- Header with Tabs and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 flex-wrap">
            <button
                wire:click="$set('activeTab', 'stok')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'stok' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Inventori & Stok
            </button>
            <button
                wire:click="$set('activeTab', 'produk')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'produk' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Master Produk
            </button>
            <button
                wire:click="$set('activeTab', 'transfer')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'transfer' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Transfer Antar Gudang
            </button>
            <button
                wire:click="$set('activeTab', 'opname')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'opname' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                Stock Opname
            </button>
            <button
                wire:click="$set('activeTab', 'po')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'po' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}"
            >
                PO & Supplier
            </button>
        </div>

        <!-- Tab-specific Quick Action — dispatch event ke tab component aktif (#[On] listener) -->
        <div>
            @if($activeTab === 'produk')
                <div class="flex items-center gap-2">
                    <button
                        wire:click="$dispatch('wms-import-modal')"
                        class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-ink-200 font-bold text-xs transition-all flex items-center gap-2 cursor-pointer"
                        title="Import master produk dari Excel (inisialisasi — bukan stok masuk harian)"
                    >
                        📥 Import Excel
                    </button>
                    <button
                        wire:click="$dispatch('wms-produk-modal')"
                        class="px-4 py-2 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-all flex items-center gap-2 cursor-pointer"
                    >
                        <span>+ Tambah Produk</span>
                    </button>
                </div>
            @elseif($activeTab === 'transfer')
                <button
                    wire:click="$dispatch('wms-transfer-baru')"
                    class="px-4 py-2 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs shadow-md shadow-up-accent/25 transition-all flex items-center gap-2 cursor-pointer"
                >
                    <span>+ Buat Transfer Baru</span>
                </button>
            @elseif($activeTab === 'opname')
                <button
                    wire:click="$dispatch('wms-opname-baru')"
                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-all flex items-center gap-2 cursor-pointer"
                >
                    <span>+ Mulai Stock Opname</span>
                </button>
            @elseif($activeTab === 'po')
                <div class="flex gap-2">
                    <button wire:click="$dispatch('wms-supplier-baru')" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs cursor-pointer">+ Supplier</button>
                    <button wire:click="$dispatch('wms-po-baru')" class="px-4 py-2 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs cursor-pointer">+ Buat PO</button>
                </div>
            @elseif($activeTab === 'stok')
                <button wire:click="$dispatch('wms-rak-modal')" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs cursor-pointer">🏬 Atur Rak</button>
            @endif
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
</div>
