<div class="space-y-6"
    x-data="{
        subModul: @js(in_array($activeTab, ['stok', 'produk']) ? 'inventori' : (in_array($activeTab, ['transfer', 'opname']) ? 'operasional' : 'pengadaan')),
        setSubModul(modul) {
            this.subModul = modul;
            const currentTab = $wire.get('activeTab');
            if (modul === 'inventori' && !['stok', 'produk'].includes(currentTab)) {
                $wire.set('activeTab', 'produk');
            } else if (modul === 'operasional' && !['transfer', 'opname'].includes(currentTab)) {
                $wire.set('activeTab', 'transfer');
            } else if (modul === 'pengadaan' && !['po', 'grn', 'retur', 'histori_po'].includes(currentTab)) {
                $wire.set('activeTab', 'po');
            }
        }
    }"
    x-init="$watch('$wire.activeTab', value => {
        if (['stok', 'produk'].includes(value)) subModul = 'inventori';
        else if (['transfer', 'opname'].includes(value)) subModul = 'operasional';
        else if (['po', 'grn', 'retur', 'histori_po'].includes(value)) subModul = 'pengadaan';
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
                    :class="subModul === 'inventori' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer"
                >
                    <span>📦</span>
                    <span>Inventori</span>
                </button>
                <button
                    type="button"
                    @click="setSubModul('operasional')"
                    :class="subModul === 'operasional' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer"
                >
                    <span>🔄</span>
                    <span>Operasional</span>
                </button>
                <button
                    type="button"
                    @click="setSubModul('pengadaan')"
                    :class="subModul === 'pengadaan' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer"
                >
                    <span>🚚</span>
                    <span>Pengadaan</span>
                </button>
            </div>

            <!-- Tab-specific Quick Action (Desktop) -->
            <div class="hidden lg:flex items-center gap-2 flex-shrink-0">
                @if(in_array($activeTab, ['produk', 'stok']))
                    <div class="flex items-center gap-2">
                        @canany(['pengaturan.manage', 'user.view'])
                        <a href="/app/pengaturan?tab=cabang" wire:navigate class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap" title="Kelola Lokasi Rak di Pengaturan">
                            <span>🏬</span>
                            <span>Atur Rak</span>
                        </a>
                        @endcanany
                        @can('wms.create')
                        <a
                            href="/app/wms/import-export"
                            wire:navigate
                            class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                            title="Import & Penyesuaian Master Produk dari Excel"
                        >
                            <span>📥</span>
                            <span>Import Excel</span>
                        </a>
                        <!-- Dropdown Export (Semua atau Terpilih) -->
                        <div class="relative" x-data="{ openExport: false }" @click.outside="openExport = false">
                            <button
                                type="button"
                                @click="openExport = !openExport"
                                class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-up-mint/15 hover:bg-up-mint/25 text-up-mint border border-up-mint/30 font-bold text-xs transition-[transform,background-color,opacity] active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                                title="Menu Export Data Master Produk"
                            >
                                <span>📤</span>
                                <span class="hidden sm:inline">Export</span>
                                <span class="text-[9px] transition-transform duration-150" :class="openExport ? 'rotate-180' : ''">▼</span>
                            </button>

                            <div x-show="openExport" x-cloak
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 z-50 mt-2 w-64 rounded-2xl glass-card bg-ink-950/95 dark:bg-ink-950/95 bg-white/95 text-ink-900 dark:text-ink-100 border border-black/10 dark:border-white/10 shadow-2xl py-2 origin-top-right">
                                <div class="px-3 py-1 text-[10px] uppercase tracking-wider text-up-mint font-bold flex items-center justify-between">
                                    <span>📤 Export Semua</span>
                                    <span class="text-[9px] font-normal text-ink-400">Sesuai Filter</span>
                                </div>
                                <button type="button" @click="openExport = false; $dispatch('wms-export-master', { format: 'xlsx' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer transition">
                                    <span>📊</span><span>Semua Produk (Excel .xlsx)</span>
                                </button>
                                <button type="button" @click="openExport = false; $dispatch('wms-export-master', { format: 'csv' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer transition">
                                    <span>📄</span><span>Semua Produk (CSV)</span>
                                </button>
                                <div class="my-1.5 border-t border-black/10 dark:border-white/10"></div>
                                <div class="px-3 py-1 text-[10px] uppercase tracking-wider text-up-amber font-bold">
                                    🖨 Export Item Terpilih
                                </div>
                                <button type="button" @click="openExport = false; $dispatch('wms-export-terpilih', { format: 'xlsx' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer transition">
                                    <span>📊</span><span>Item Terpilih (Excel .xlsx)</span>
                                </button>
                                <button type="button" @click="openExport = false; $dispatch('wms-export-terpilih', { format: 'csv' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer transition">
                                    <span>📄</span><span>Item Terpilih (CSV)</span>
                                </button>
                            </div>
                        </div>
                        <button
                            @click="$dispatch('wms-produk-modal')"
                            class="px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                        >
                            <span>+ Tambah Produk</span>
                        </button>
                        @endcan
                    </div>
                @elseif($activeTab === 'transfer')
                    @can('wms.transfer')
                    <button
                        @click="$dispatch('wms-transfer-baru')"
                        class="px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs shadow-md shadow-up-accent/25 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Buat Transfer Baru</span>
                    </button>
                    @endcan
                @elseif($activeTab === 'opname')
                    @can('wms.opname')
                    <button
                        @click="$dispatch('wms-opname-baru')"
                        class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Mulai Stock Opname</span>
                    </button>
                    @endcan
                @elseif($activeTab === 'po')
                    @can('wms.create')
                    <div class="flex items-center gap-2">
                        <button @click="$dispatch('wms-procurement-modal')" class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 border border-purple-500/30 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap" title="Rekomendasi Pengadaan Stok (ABC, ROP, Min-Max, JIT)">
                            <span>📊</span>
                            <span>Rekomendasi Pengadaan</span>
                        </button>
                        <button @click="$dispatch('wms-supplier-baru')" class="px-3.5 sm:px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 border border-black/10 dark:border-white/10 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap">+ Supplier</button>
                        <button @click="$dispatch('wms-po-baru')" class="px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap">+ Buat PO</button>
                    </div>
                    @endcan
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
                        wire:click="$set('activeTab', 'produk')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ in_array($activeTab, ['produk', 'stok']) ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Master Produk & Stok
                    </button>

                    <!-- Tombol Auto ABC WMS Seluruh Produk (Sejajar dengan Master Produk & Stok) -->
                    @can('wms.create')
                    <button
                        type="button"
                        @click="if (confirm('Jalankan klasifikasi otomatis kelas WMS (ABC Pareto) dan parameter ROP / Min-Max untuk seluruh master produk aktif berdasarkan pergerakan 90 hari terakhir?')) $dispatch('wms-auto-abc')"
                        class="px-3 sm:px-3.5 py-2 rounded-xl bg-up-primary/15 hover:bg-up-primary/25 text-up-primary border border-up-primary/30 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer whitespace-nowrap min-h-[44px]"
                        title="Kalkulasi otomatis kelas ABC dan parameter restock seluruh produk"
                    >
                        <span>⚡ Auto ABC (WMS)</span>
                    </button>
                    @endcan
                </div>

                <!-- Group: Operasional -->
                <div x-show="subModul === 'operasional'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'transfer')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'transfer' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Transfer Antar Gudang
                    </button>
                    <button
                        wire:click="$set('activeTab', 'opname')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'opname' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Stock Opname
                    </button>
                    {{-- [F3-7] Cycle count — halaman terpisah (bukan tab child) --}}
                    <a
                        href="{{ route('wms.cycle-count') }}"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 inline-flex items-center cursor-pointer"
                        title="Jadwal & task cycle count otomatis"
                    >
                        Cycle Count
                    </a>
                </div>

                <!-- Group: Pengadaan -->
                <div x-show="subModul === 'pengadaan'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'po')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'po' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        PO & Supplier
                    </button>
                    <button
                        wire:click="$set('activeTab', 'grn')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'grn' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        GRN
                    </button>
                    <button
                        wire:click="$set('activeTab', 'retur')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'retur' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Retur Pembelian
                    </button>
                    <button
                        wire:click="$set('activeTab', 'histori_po')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'histori_po' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        Histori Pengadaan
                    </button>
                </div>
            </div>

            <!-- Tab-specific Quick Action (Mobile/Tablet) -->
            <div class="lg:hidden w-full flex-shrink-0">
                @if(in_array($activeTab, ['produk', 'stok']))
                    <div class="flex items-center gap-2 w-full">
                        @canany(['pengaturan.manage', 'user.view'])
                        <a href="/app/pengaturan?tab=cabang" wire:navigate class="px-3 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1 cursor-pointer whitespace-nowrap" title="Kelola Lokasi Rak di Pengaturan">
                            <span>🏬</span>
                            <span>Rak</span>
                        </a>
                        @endcanany
                        @can('wms.create')
                        <a
                            href="/app/wms/import-export"
                            wire:navigate
                            class="flex-1 px-3 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                            title="Import & Penyesuaian Master Produk dari Excel"
                        >
                            <span>📥</span>
                            <span>Import</span>
                        </a>
                        <!-- Mobile Dropdown Export -->
                        <div class="flex-1 relative" x-data="{ openExportM: false }" @click.outside="openExportM = false">
                            <button
                                type="button"
                                @click="openExportM = !openExportM"
                                class="w-full px-3 py-2.5 rounded-xl bg-up-mint/15 hover:bg-up-mint/25 text-up-mint border border-up-mint/30 font-bold text-xs transition-[transform,background-color,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                                title="Export Master Produk"
                            >
                                <span>📤</span>
                                <span>Export</span>
                                <span class="text-[9px]" :class="openExportM ? 'rotate-180' : ''">▼</span>
                            </button>

                            <div x-show="openExportM" x-cloak
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 z-50 mt-2 w-64 rounded-2xl glass-card bg-ink-950/95 dark:bg-ink-950/95 bg-white/95 text-ink-900 dark:text-ink-100 border border-black/10 dark:border-white/10 shadow-2xl py-2 origin-top-right">
                                <div class="px-3 py-1 text-[10px] uppercase tracking-wider text-up-mint font-bold">📤 Semua Produk</div>
                                <button type="button" @click="openExportM = false; $dispatch('wms-export-master', { format: 'xlsx' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer">
                                    <span>📊</span><span>Semua Produk (Excel)</span>
                                </button>
                                <button type="button" @click="openExportM = false; $dispatch('wms-export-master', { format: 'csv' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer">
                                    <span>📄</span><span>Semua Produk (CSV)</span>
                                </button>
                                <div class="my-1.5 border-t border-black/10 dark:border-white/10"></div>
                                <div class="px-3 py-1 text-[10px] uppercase tracking-wider text-up-amber font-bold">🖨 Item Terpilih</div>
                                <button type="button" @click="openExportM = false; $dispatch('wms-export-terpilih', { format: 'xlsx' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer">
                                    <span>📊</span><span>Item Terpilih (Excel)</span>
                                </button>
                                <button type="button" @click="openExportM = false; $dispatch('wms-export-terpilih', { format: 'csv' })" class="w-full text-left px-3 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5 flex items-center gap-2 cursor-pointer">
                                    <span>📄</span><span>Item Terpilih (CSV)</span>
                                </button>
                            </div>
                        </div>
                        <button
                            @click="$dispatch('wms-produk-modal')"
                            class="flex-1 px-3 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                        >
                            <span>+ Produk</span>
                        </button>
                        @endcan
                    </div>
                @elseif($activeTab === 'transfer')
                    @can('wms.transfer')
                    <button
                        @click="$dispatch('wms-transfer-baru')"
                        class="w-full px-4 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs shadow-md shadow-up-accent/25 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Buat Transfer Baru</span>
                    </button>
                    @endcan
                @elseif($activeTab === 'opname')
                    @can('wms.opname')
                    <button
                        @click="$dispatch('wms-opname-baru')"
                        class="w-full px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
                    >
                        <span>+ Mulai Stock Opname</span>
                    </button>
                    @endcan
                @elseif($activeTab === 'po')
                    @can('wms.create')
                    <div class="grid grid-cols-3 gap-1.5 w-full sm:flex sm:items-center sm:gap-2">
                        <button @click="$dispatch('wms-procurement-modal')" class="px-2.5 sm:px-3 py-2.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 border border-purple-500/30 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-1 cursor-pointer whitespace-nowrap" title="Rekomendasi Pengadaan Stok (ABC, ROP, Min-Max, JIT)">
                            <span>📊</span>
                            <span class="truncate">Saran PO</span>
                        </button>
                        <button @click="$dispatch('wms-supplier-baru')" class="px-2.5 sm:px-3 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 border border-black/10 dark:border-white/10 font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap truncate">+ Supplier</button>
                        <button @click="$dispatch('wms-po-baru')" class="px-2.5 sm:px-3 py-2.5 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer whitespace-nowrap shadow-sm shadow-up-accent/20 truncate">+ PO</button>
                    </div>
                    @endcan
                @elseif($activeTab === 'grn')
                    <div class="flex items-center gap-2 w-full">
                        <button @click="$dispatch('wms-search-modal')" class="w-full px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 border border-white/10 font-bold text-xs min-h-[44px] flex items-center justify-center gap-2 cursor-pointer transition active:scale-[0.97]">
                            <span>🔍</span>
                            <span>Cari Dokumen &amp; Scan Barcode</span>
                        </button>
                    </div>
                @elseif($activeTab === 'retur')
                    @can('wms.create')
                    <div class="flex items-center gap-2 w-full">
                        <button @click="$wire.set('activeTab', 'retur'); $nextTick(() => { const btn = document.querySelector('[wire\\:click=\'bukaModalTambah\']'); if (btn) btn.click(); })" class="w-full px-4 py-2.5 rounded-xl bg-up-red hover:bg-up-red/90 text-white font-bold text-xs shadow-md shadow-up-red/25 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap">
                            <span>+ Buat Retur Pembelian</span>
                        </button>
                    </div>
                    @endcan
                @endif
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: Lazy Unmount On-Demand (Hanya me-render tab aktif agar hemat memori & performa instan di semua perangkat) -->
    <div wire:loading.delay wire:target="activeTab, pindahTab" class="w-full py-8 text-center">
        <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-2xl bg-white/80 dark:bg-ink-900/80 border border-black/10 dark:border-white/10 shadow-lg backdrop-blur-md">
            <svg class="w-4 h-4 text-up-primary animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs font-semibold text-ink-700 dark:text-ink-300">Memuat modul WMS...</span>
        </div>
    </div>

    @if($activeTab === 'stok')
        <div key="tab-stok">
            @livewire(\App\Modules\Wms\Livewire\StokTab::class)
        </div>
    @elseif($activeTab === 'produk')
        <div key="tab-produk">
            @livewire(\App\Modules\Wms\Livewire\ProdukTab::class)
        </div>
    @elseif($activeTab === 'transfer')
        <div key="tab-transfer">
            @livewire(\App\Modules\Wms\Livewire\TransferTab::class)
        </div>
    @elseif($activeTab === 'opname')
        <div key="tab-opname">
            @livewire(\App\Modules\Wms\Livewire\OpnameTab::class)
        </div>
    @elseif($activeTab === 'po')
        <div key="tab-po">
            @livewire(\App\Modules\Wms\Livewire\PoTab::class)
        </div>
    @elseif($activeTab === 'grn')
        <div key="tab-grn">
            @livewire(\App\Modules\Wms\Livewire\GrnTab::class)
        </div>
    @elseif($activeTab === 'retur')
        <div key="tab-retur">
            @livewire(\App\Modules\Wms\Livewire\ReturnPembelianTab::class)
        </div>
    @elseif($activeTab === 'histori_po')
        <div key="tab-histori-po">
            @livewire(\App\Modules\Wms\Livewire\HistoriPengadaanTab::class)
        </div>
    @endif

    <!-- MODAL PENCARIAN PRODUK GLOBAL -->
    @livewire(\App\Modules\Wms\Livewire\ProductSearchModal::class)
</div>
