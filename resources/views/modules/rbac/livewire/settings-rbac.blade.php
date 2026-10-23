<div class="space-y-6"
    x-data="{
        subModul: @js(in_array($activeTab, ['users', 'role', 'cabang']) ? 'akses' : ($activeTab === 'master_produk' ? 'katalog' : (in_array($activeTab, ['loyalitas', 'pajak']) ? 'kebijakan' : (in_array($activeTab, ['master', 'printer']) ? 'operasional' : ($activeTab === 'integrasi' ? 'integrasi' : 'sistem'))))),
        searchQuery: '',
        searchOpen: false,
        menuItems: [
            { id: 'users', label: 'Manajemen Pengguna & Staf', subModul: 'akses', tab: 'users', subTab: null, icon: '👥', group: 'Akses & Organisasi', desc: 'Akun staf kasir, admin, teknisi, hak akses cabang toko', keywords: 'user pengguna staff staf kasir karyawan password email admin' },
            { id: 'role', label: 'Role & Izin Akses (RBAC)', subModul: 'akses', tab: 'role', subTab: null, icon: '🔐', group: 'Akses & Organisasi', desc: 'Matriks kewenangan Spatie, batasan modul & persetujuan', keywords: 'role permission izin rbac wewenang hak akses otoritas' },
            { id: 'cabang', label: 'Cabang, Gudang & Rak Part', subModul: 'akses', tab: 'cabang', subTab: null, icon: '🏬', group: 'Akses & Organisasi', desc: 'Lokasi cabang toko fisik, mapping gudang dan kode nomor rak', keywords: 'cabang toko gudang warehouse rak lokasi penyimpanan bin' },

            { id: 'kategori', label: 'Kategori Produk Hierarkis', subModul: 'katalog', tab: 'master_produk', subTab: 'kategori', icon: '🗂️', group: 'Katalog & Master Produk', desc: 'Struktur pohon kategori induk dan sub-kategori sparepart', keywords: 'kategori subkategori grup kelompok jenis produk part' },
            { id: 'brand', label: 'Brand Part Sparepart', subModul: 'katalog', tab: 'master_produk', subTab: 'brand', icon: '🏷️', group: 'Katalog & Master Produk', desc: 'Daftar pabrikan dan produsen sparepart suku cadang (Brand Part)', keywords: 'brand part merek pabrikan vendor produsen sparepart' },
            { id: 'kualitas', label: 'Tingkat Kualitas (Grade)', subModul: 'katalog', tab: 'master_produk', subTab: 'kualitas', icon: '⭐', group: 'Katalog & Master Produk', desc: 'Grade kualitas: Original, OEM, High Copy, Copotan', keywords: 'kualitas grade ori original oem grade copy kw kualitas' },
            { id: 'satuan', label: 'Satuan Unit Produk', subModul: 'katalog', tab: 'master_produk', subTab: 'satuan', icon: '📏', group: 'Katalog & Master Produk', desc: 'Pcs, Set, Rol, Box, Lembar, Strip', keywords: 'satuan unit pcs pieces pack dus box meter' },
            { id: 'kondisi', label: 'Kondisi Fisik Barang', subModul: 'katalog', tab: 'master_produk', subTab: 'kondisi', icon: '🔄', group: 'Katalog & Master Produk', desc: 'Kondisi Baru, Refurbished, Bekas / Copotan normal', keywords: 'kondisi baru bekas second copotan refurbished reject' },
            { id: 'tipe_hp', label: 'Tipe HP Kompatibel', subModul: 'katalog', tab: 'master_produk', subTab: 'tipe_hp', icon: '📱', group: 'Katalog & Master Produk', desc: 'Model tipe ponsel smartphone yang kompatibel dengan part', keywords: 'tipe model hp ponsel smartphone iphone samsung xiaomi oppo vivo infinix' },

            { id: 'loyalitas', label: 'Strategi Loyalitas & Poin', subModul: 'kebijakan', tab: 'loyalitas', subTab: null, icon: '💎', group: 'Kebijakan & Transaksi', desc: 'Rasio earn/redeem poin belanja & diskon tier membership', keywords: 'loyalitas point poin tier membership diskon potongan cashback member' },
            { id: 'komisi', label: 'Skema Komisi Reseller & Sales', subModul: 'kebijakan', tab: 'loyalitas', subTab: null, icon: '🤝', group: 'Kebijakan & Transaksi', desc: 'Komisi reseller, mitra teknisi dan insentif penjualan tim', keywords: 'komisi reseller komisi sales insentif bagi hasil affiliate bonus' },
            { id: 'pajak', label: 'Pajak & Pengaturan PPN', subModul: 'kebijakan', tab: 'pajak', subTab: null, icon: '⚖️', group: 'Kebijakan & Transaksi', desc: 'Tarif PPN (11%/12%), target POS kasir & akun penampung', keywords: 'pajak ppn tax tarif faktur pajak kasir pos marketplace b2b persentase' },

            { id: 'servis', label: 'Master Servis & Garansi', subModul: 'operasional', tab: 'master', subTab: null, icon: '🛠️', group: 'Operasional & Periferal', desc: 'Katalog jasa servis, estimasi waktu teknisi, masa garansi klaim', keywords: 'servis service jasa perbaikan teknisi garansi klaim pengerjaan tarif' },
            { id: 'struk', label: 'Template Struk & Faktur POS', subModul: 'operasional', tab: 'printer', subTab: 'struk', icon: '🧾', group: 'Operasional & Periferal', desc: 'Kustomisasi header/footer struk thermal, QR retur, kertas 58/80mm', keywords: 'struk printer thermal nota faktur kasir thermal print kertas retur' },
            { id: 'label', label: 'Format Label Barcode & QR SKU', subModul: 'operasional', tab: 'printer', subTab: 'label', icon: '🏷️', group: 'Operasional & Periferal', desc: 'Tata letak cetak stiker barcode produk untuk scanner gudang', keywords: 'label barcode qr code stiker cetak scanner printer bluetooth label' },

            { id: 'wa', label: 'WhatsApp Gateway & Notifikasi', subModul: 'integrasi', tab: 'integrasi', subTab: null, icon: '💬', group: 'Gateway & Integrasi', desc: 'Konfigurasi Fonnte / Wablas, token API, dan pengujian kirim WA', keywords: 'whatsapp wa fonnte wablas gateway notifikasi pesan chat token phone' },
            { id: 'duitku', label: 'Payment Gateway Duitku', subModul: 'integrasi', tab: 'integrasi', subTab: null, icon: '💳', group: 'Gateway & Integrasi', desc: 'Atur Merchant Code, API Key, Sandbox vs Production, dan callback URL', keywords: 'payment pembayaran duitku gateway qris va virtual account kartu kredit sandbox' },
            { id: 'biteship', label: 'Logistik & Ekspedisi Biteship', subModul: 'integrasi', tab: 'integrasi', subTab: null, icon: '🚚', group: 'Gateway & Integrasi', desc: 'API kurir ongkir otomatis (JNE, SiCepat, J&T, GoSend, Grab)', keywords: 'ekspedisi kurir shipping ongkir pengiriman biteship jne sicepat jnt' },

            { id: 'coa', label: 'Chart of Accounts (COA)', subModul: 'sistem', tab: 'master', subTab: null, icon: '📊', group: 'Sistem & Keuangan', desc: 'Bagan akun buku besar akuntansi, debit, kredit, klasifikasi aset/beban', keywords: 'coa akun akuntansi buku besar jurnal debit kredit kas bank' },
            { id: 'database', label: 'Basis Data & Backup Sistem', subModul: 'sistem', tab: 'database', subTab: null, icon: '💾', group: 'Sistem & Keuangan', desc: 'Backup snapshot SQL, optimasi database, riwayat pencadangan data', keywords: 'database basis data backup restore dump sql download arsip cadangan' }
        ],
        get filteredItems() {
            if (!this.searchQuery.trim()) return [];
            const q = this.searchQuery.toLowerCase();
            return this.menuItems.filter(item => 
                item.label.toLowerCase().includes(q) ||
                item.desc.toLowerCase().includes(q) ||
                item.group.toLowerCase().includes(q) ||
                item.keywords.toLowerCase().includes(q)
            );
        },
        selectMenuItem(item) {
            this.subModul = item.subModul;
            $wire.set('activeTab', item.tab);
            if (item.subTab) {
                if (item.tab === 'master_produk') {
                    $wire.set('masterProdukSubTab', item.subTab);
                } else if (item.tab === 'printer') {
                    $wire.set('printerSubTab', item.subTab);
                }
            }
            this.searchQuery = '';
            this.searchOpen = false;
        },
        setSubModul(modul) {
            this.subModul = modul;
            if (modul === 'akses' && !['users', 'role', 'cabang'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'users');
            } else if (modul === 'katalog' && @js($activeTab) !== 'master_produk') {
                $wire.set('activeTab', 'master_produk');
            } else if (modul === 'kebijakan' && !['loyalitas', 'pajak'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'loyalitas');
            } else if (modul === 'operasional' && !['master', 'printer'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'master');
            } else if (modul === 'integrasi' && @js($activeTab) !== 'integrasi') {
                $wire.set('activeTab', 'integrasi');
            } else if (modul === 'sistem' && !['database', 'master'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'database');
            }
        }
    }"
    x-init="$watch('$wire.activeTab', value => {
        if (['users', 'role', 'cabang'].includes(value)) subModul = 'akses';
        else if (value === 'master_produk') subModul = 'katalog';
        else if (['loyalitas', 'pajak'].includes(value)) subModul = 'kebijakan';
        else if (['master', 'printer'].includes(value)) subModul = (value === 'master' && subModul === 'sistem') ? 'sistem' : 'operasional';
        else if (value === 'integrasi') subModul = 'integrasi';
        else if (value === 'database') subModul = 'sistem';
    })"
>
    <!-- TOP TOOLBAR: Redesigned Spotlight Finder & Overview -->
    <div class="glass-panel p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-black/10 dark:border-white/10 shadow-sm space-y-4">
        <!-- Header Title & Quick Search -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3.5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-up-primary shadow-sm shadow-up-primary/50"></span>
                    <h1 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white tracking-tight">Pusat Konfigurasi & Pengaturan Sistem</h1>
                </div>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Kelola hak akses, katalog produk, aturan bisnis, periferal kasir, dan basis data secara terpusat.</p>
            </div>

            <!-- Spotlight Search Input -->
            <div class="relative w-full md:w-80 lg:w-96 flex-shrink-0" @click.outside="searchOpen = false">
                <div class="relative flex items-center">
                    <span class="absolute left-3.5 text-ink-400 pointer-events-none text-sm">🔍</span>
                    <input
                        type="text"
                        x-model="searchQuery"
                        @focus="searchOpen = true"
                        @input="searchOpen = true"
                        @keydown.escape="searchOpen = false; searchQuery = ''"
                        placeholder="Cari pengaturan (contoh: PPN, Struk, Gudang)..."
                        class="w-full pl-9 pr-8 py-2 rounded-xl text-xs bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-ink-900 dark:text-white placeholder-ink-400 focus:outline-none focus:ring-2 focus:ring-up-primary/50 focus:border-up-primary transition min-h-[40px]"
                    />
                    <button
                        type="button"
                        x-show="searchQuery"
                        @click="searchQuery = ''; searchOpen = false"
                        class="absolute right-2.5 text-ink-400 hover:text-ink-700 dark:hover:text-white text-xs cursor-pointer"
                        x-cloak
                    >✕</button>
                </div>

                <!-- Floating Search Results Dropdown -->
                <div
                    x-show="searchOpen && searchQuery.trim().length > 0"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-1"
                    class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-[#0B1120] border border-ink-200/90 dark:border-white/15 rounded-2xl shadow-2xl z-50 overflow-hidden max-h-80 overflow-y-auto"
                >
                    <div class="p-2 border-b border-black/5 dark:border-white/5 text-[11px] font-semibold text-ink-400 flex items-center justify-between">
                        <span>Hasil Pencarian</span>
                        <span x-text="filteredItems.length + ' fitur ditemukan'"></span>
                    </div>

                    <template x-if="filteredItems.length === 0">
                        <div class="p-4 text-center text-xs text-ink-400">
                            Tidak ditemukan pengaturan dengan kata kunci "<span class="font-bold text-ink-900 dark:text-white" x-text="searchQuery"></span>"
                        </div>
                    </template>

                    <div class="divide-y divide-black/5 dark:divide-white/5">
                        <template x-for="item in filteredItems" :key="item.id">
                            <button
                                type="button"
                                @click="selectMenuItem(item)"
                                class="w-full p-2.5 sm:p-3 text-left hover:bg-black/5 dark:hover:bg-white/[0.06] transition flex items-start gap-3 cursor-pointer group"
                            >
                                <span class="text-xl p-1.5 rounded-lg bg-black/5 dark:bg-white/5 flex-shrink-0" x-text="item.icon"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-ink-900 dark:text-white group-hover:text-up-primary transition-colors" x-text="item.label"></span>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-bold" x-text="item.group"></span>
                                    </div>
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 line-clamp-1" x-text="item.desc"></p>
                                </div>
                                <span class="text-xs text-ink-400 opacity-0 group-hover:opacity-100 transition-opacity self-center">→</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier 1: Group Feature Categories (6 Kelompok Jelas & Terstruktur) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 pt-2 border-t border-black/5 dark:border-white/5">
            <!-- 1. Akses & Organisasi -->
            <button
                type="button"
                @click="setSubModul('akses')"
                :class="subModul === 'akses' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30 border-up-primary' : 'bg-black/5 dark:bg-white/[0.03] text-ink-600 dark:text-ink-300 border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.08]'"
                class="p-2.5 sm:p-3 rounded-2xl border text-left transition-[transform,background-color] active:scale-[0.98] cursor-pointer flex flex-col justify-between min-h-[72px]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-lg">👥</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full font-bold" :class="subModul === 'akses' ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 text-ink-400'">3 Menu</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-snug">Akses & Tim</h3>
                    <p class="text-[10px] opacity-75 truncate">User, Role, Cabang</p>
                </div>
            </button>

            <!-- 2. Katalog & Produk -->
            <button
                type="button"
                @click="setSubModul('katalog')"
                :class="subModul === 'katalog' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30 border-up-primary' : 'bg-black/5 dark:bg-white/[0.03] text-ink-600 dark:text-ink-300 border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.08]'"
                class="p-2.5 sm:p-3 rounded-2xl border text-left transition-[transform,background-color] active:scale-[0.98] cursor-pointer flex flex-col justify-between min-h-[72px]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-lg">📦</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full font-bold" :class="subModul === 'katalog' ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 text-ink-400'">6 Atribut</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-snug">Katalog Part</h3>
                    <p class="text-[10px] opacity-75 truncate">Kategori, Brand, Tipe</p>
                </div>
            </button>

            <!-- 3. Kebijakan & Transaksi -->
            <button
                type="button"
                @click="setSubModul('kebijakan')"
                :class="subModul === 'kebijakan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30 border-up-primary' : 'bg-black/5 dark:bg-white/[0.03] text-ink-600 dark:text-ink-300 border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.08]'"
                class="p-2.5 sm:p-3 rounded-2xl border text-left transition-[transform,background-color] active:scale-[0.98] cursor-pointer flex flex-col justify-between min-h-[72px]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-lg">💎</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full font-bold" :class="subModul === 'kebijakan' ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 text-ink-400'">2 Aturan</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-snug">Kebijakan Bisnis</h3>
                    <p class="text-[10px] opacity-75 truncate">Loyalitas, PPN, Pajak</p>
                </div>
            </button>

            <!-- 4. Operasional & Periferal -->
            <button
                type="button"
                @click="setSubModul('operasional')"
                :class="subModul === 'operasional' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30 border-up-primary' : 'bg-black/5 dark:bg-white/[0.03] text-ink-600 dark:text-ink-300 border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.08]'"
                class="p-2.5 sm:p-3 rounded-2xl border text-left transition-[transform,background-color] active:scale-[0.98] cursor-pointer flex flex-col justify-between min-h-[72px]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-lg">🛠️</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full font-bold" :class="subModul === 'operasional' ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 text-ink-400'">3 Fitur</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-snug">Operasional Toko</h3>
                    <p class="text-[10px] opacity-75 truncate">Servis, Struk, Label</p>
                </div>
            </button>

            <!-- 5. Gateway & Integrasi -->
            <button
                type="button"
                @click="setSubModul('integrasi')"
                :class="subModul === 'integrasi' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30 border-up-primary' : 'bg-black/5 dark:bg-white/[0.03] text-ink-600 dark:text-ink-300 border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.08]'"
                class="p-2.5 sm:p-3 rounded-2xl border text-left transition-[transform,background-color] active:scale-[0.98] cursor-pointer flex flex-col justify-between min-h-[72px]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-lg">🔌</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full font-bold" :class="subModul === 'integrasi' ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 text-ink-400'">3 Gateway</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-snug">Gateway Integrasi</h3>
                    <p class="text-[10px] opacity-75 truncate">WhatsApp, Duitku, Biteship</p>
                </div>
            </button>

            <!-- 6. Sistem & Keuangan -->
            <button
                type="button"
                @click="setSubModul('sistem')"
                :class="subModul === 'sistem' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30 border-up-primary' : 'bg-black/5 dark:bg-white/[0.03] text-ink-600 dark:text-ink-300 border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.08]'"
                class="p-2.5 sm:p-3 rounded-2xl border text-left transition-[transform,background-color] active:scale-[0.98] cursor-pointer flex flex-col justify-between min-h-[72px]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-lg">💾</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full font-bold" :class="subModul === 'sistem' ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 text-ink-400'">2 Modul</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-snug">Sistem & COA</h3>
                    <p class="text-[10px] opacity-75 truncate">Bagan Akun, Backup</p>
                </div>
            </button>
        </div>

        <!-- Tier 2: Contextual Tabs & Quick Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-black/5 dark:border-white/5">
            <!-- Contextual Tabs: smooth horizontal scroll on mobile, scrollbar-none, touch-pan-x -->
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 max-w-full flex-nowrap scrollbar-none scroll-smooth touch-pan-x">
                <!-- Group 1: Akses & Organisasi -->
                <div x-show="subModul === 'akses'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap">
                    <button
                        wire:click="$set('activeTab', 'users')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'users' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        👥 Manajemen Pengguna
                    </button>
                    <button
                        wire:click="$set('activeTab', 'role')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'role' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        🔐 Role & Hak Akses
                    </button>
                    <button
                        wire:click="$set('activeTab', 'cabang')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'cabang' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        🏬 Cabang, Gudang & Rak
                    </button>
                </div>

                <!-- Group 2: Katalog & Produk -->
                <div x-show="subModul === 'katalog'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'master_produk')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer bg-up-primary text-white shadow-md shadow-up-primary/25"
                    >
                        📦 Master Atribut Produk & Sparepart
                    </button>
                </div>

                <!-- Group 3: Kebijakan & Transaksi -->
                <div x-show="subModul === 'kebijakan'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'loyalitas')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'loyalitas' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        💎 Strategi Loyalitas & Komisi
                    </button>
                    <button
                        wire:click="$set('activeTab', 'pajak')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'pajak' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        ⚖️ Pajak & Tarif PPN
                    </button>
                </div>

                <!-- Group 4: Operasional & Periferal -->
                <div x-show="subModul === 'operasional'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'master')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'master' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        🛠️ Master Servis & Garansi
                    </button>
                    <button
                        wire:click="$set('activeTab', 'printer')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'printer' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        🖨️ Printer Struk & Label Barcode
                    </button>
                </div>

                <!-- Group 5: Gateway & Integrasi -->
                <div x-show="subModul === 'integrasi'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'integrasi')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer bg-up-primary text-white shadow-md shadow-up-primary/25"
                    >
                        🔌 Gateway & Integrasi Eksternal (WA, Duitku, Biteship)
                    </button>
                </div>

                <!-- Group 6: Sistem & Keuangan -->
                <div x-show="subModul === 'sistem'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'master')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'master' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        📊 Bagan Akun (COA)
                    </button>
                    @if(isSuperAdminOrOwner())
                    <button
                        wire:click="$set('activeTab', 'database')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'database' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        💾 Basis Data & Backup
                    </button>
                    @endif
                </div>
            </div>

            <!-- Tab Action Button: Responsive placement, never overlaps -->
            <div class="flex flex-wrap items-center gap-2 flex-shrink-0 w-full sm:w-auto justify-start sm:justify-end">
                @if($activeTab === 'users')
                    <button wire:click="openUserModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ User Baru</button>
                @elseif($activeTab === 'role')
                    <button wire:click="openRoleModal" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Role Baru</button>
                @elseif($activeTab === 'cabang')
                    <button wire:click="openCabangModal()" class="flex-1 sm:flex-initial min-w-[90px] px-3.5 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Cabang</button>
                    <button wire:click="openGudangModal()" class="flex-1 sm:flex-initial min-w-[90px] px-3.5 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Gudang</button>
                    <button wire:click="openRakModal()" class="flex-1 sm:flex-initial min-w-[80px] px-3.5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs border border-white/10 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Rak</button>
                @elseif($activeTab === 'master_produk')
                    @if($masterProdukSubTab === 'kategori')
                        <button wire:click="openKategoriModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Kategori Baru</button>
                    @elseif($masterProdukSubTab === 'brand')
                        <button wire:click="openBrandModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Brand Part Baru</button>
                    @elseif($masterProdukSubTab === 'kualitas')
                        <button wire:click="openKualitasModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Kualitas Baru</button>
                    @elseif($masterProdukSubTab === 'satuan')
                        <button wire:click="openSatuanModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Satuan Baru</button>
                    @elseif($masterProdukSubTab === 'kondisi')
                        <button wire:click="openKondisiModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Kondisi Baru</button>
                    @elseif($masterProdukSubTab === 'tipe_hp')
                        <button wire:click="openTipeHpModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Tipe HP Baru</button>
                    @endif
                @elseif($activeTab === 'integrasi')
                    <button wire:click="simpanIntegrasi" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">💾 Simpan Konfigurasi Integrasi</button>
                @endif
            </div>
        </div>
    </div>

    <!-- TAB: USERS -->
    @if($activeTab === 'users')
        <x-prism.data-table :headers="['Nama', 'Email', 'Role', 'Cabang', 'Status', 'Aksi']">
            @forelse($users as $u)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-semibold text-white">
                        {{ $u->name }}
                        @if($u->phone)<span class="block text-[10px] text-ink-400 font-normal">{{ $u->phone }}</span>@endif
                    </td>
                    <td class="py-3.5 px-4 text-ink-300 font-mono">{{ $u->email }}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/15 text-up-primary border border-up-primary/30">
                            {{ $u->roles->first()?->name ?? '-' }}
                        </span>
                    </td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $u->cabangs->pluck('nama')->join(', ') ?: '-' }}</td>
                    <td class="py-3.5 px-4">
                        <button wire:click="toggleUserActive({{ $u->id }})" class="cursor-pointer">
                            <x-prism.status-pill :status="$u->is_active ? 'aktif' : 'batal'" size="sm" />
                        </button>
                    </td>
                    <td class="py-3.5 px-4">
                        <button wire:click="openUserModal({{ $u->id }})" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 font-semibold text-[11px] cursor-pointer">Edit</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-ink-400">Belum ada user.</td></tr>
            @endforelse

            <x-slot:pagination>{{ $users->links() }}</x-slot:pagination>
        </x-prism.data-table>

        <!-- Role matrix viewer (read-only summary) -->
        <div class="glass-panel rounded-2xl p-5 mt-4">
            <p class="text-sm font-bold text-white mb-3">Matriks Role & Permission (rekomendasi default PRD §3)</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach($roles as $r)
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-xs font-bold text-up-primary">{{ $r->name }}</p>
                        <p class="text-[10px] text-ink-400 mt-1">{{ $r->permissions->count() }} permission</p>
                        <p class="text-[9px] text-ink-500 mt-1 line-clamp-2">{{ $r->permissions->pluck('name')->take(6)->join(', ') }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-[10px] text-ink-500 mt-3">💡 Ubah akses per role di tab "<strong class="text-up-primary">Role & Permission</strong>".</p>
        </div>
    @endif

    <!-- TAB: ROLE & PERMISSION [T-25] -->
    @if($activeTab === 'role')
        <div class="space-y-4">
            @if($editRoleId)
                @php $roleEdit = $rolesFull->firstWhere('id', $editRoleId); @endphp
                <div class="glass-panel rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-white">Edit Permission — <span class="text-up-primary">{{ $roleEdit?->name }}</span></h4>
                        <button wire:click="$set('editRoleId', null)" class="text-ink-400 hover:text-white text-xs cursor-pointer">✕ tutup</button>
                    </div>
                    <div class="space-y-4 max-h-[30rem] overflow-y-auto pr-1">
                        @foreach($groupedPermissions as $modul => $items)
                            <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-white/5">
                                    <span class="text-xs font-bold text-up-primary flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                                        {{ $modul }}
                                    </span>
                                    <span class="text-[10px] text-ink-400">{{ count($items) }} hak akses</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($items as $item)
                                        <label class="flex items-start gap-2.5 text-xs text-ink-200 cursor-pointer p-2.5 rounded-xl hover:bg-white/5 border border-white/5 transition-colors">
                                            <input type="checkbox" wire:model="editRolePermissions" value="{{ $item['name'] }}" class="accent-up-mint w-4 h-4 mt-0.5 rounded cursor-pointer shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <div class="font-semibold text-white leading-tight">{{ $item['label'] }}</div>
                                                <div class="text-[10px] text-ink-400 mt-1 leading-snug">{{ $item['deskripsi'] }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button wire:click="$set('editRoleId', null)" class="px-4 py-2 rounded-xl bg-white/5 text-ink-300 text-xs font-semibold cursor-pointer">Batal</button>
                        <button wire:click="saveEditRolePermissions" class="px-4 py-2 rounded-xl bg-up-mint text-ink-950 text-xs font-bold cursor-pointer">Simpan Permission</button>
                    </div>
                    @if($roleEdit?->name === 'super-admin')
                        <p class="text-[10px] text-up-amber mt-2">⚠️ super-admin wajib minimal 1 permission (anti lockout).</p>
                    @endif
                </div>
            @endif

            <x-prism.data-table :headers="['Role', 'Permission', 'Edit']">
                @forelse($rolesFull as $r)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-bold text-white">
                            {{ $r->name }}
                            @if($r->name === 'super-admin')
                                <span class="ml-1 text-[9px] text-up-amber bg-up-amber/10 px-1.5 py-0.5 rounded-full">guardrail</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-ink-300">{{ $r->permissions->count() }} permission</td>
                        <td class="py-3.5 px-4">
                            <button wire:click="openEditRole({{ $r->id }})" class="px-3 py-1.5 rounded-lg bg-up-primary/20 hover:bg-up-primary/35 text-up-primary font-bold text-[11px] cursor-pointer">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-12 text-center text-ink-400">Belum ada role.</td></tr>
                @endforelse
            </x-prism.data-table>
        </div>

        <!-- MODAL: ROLE BARU -->
        @if($showRoleModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
                <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                        <h3 class="text-lg font-bold text-white">Role Baru</h3>
                        <button wire:click="$set('showRoleModal', false)" class="text-ink-400 hover:text-white">✕</button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Role *</label>
                        <input type="text" wire:model="roleBaruNama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Contoh: koordinator-servis, audit-stok" />
                    </div>
                    <p class="text-xs font-bold text-ink-200 mb-2.5">Pilih Hak Akses (Permission)</p>
                    <div class="space-y-4 max-h-96 overflow-y-auto pr-1">
                        @foreach($groupedPermissions as $modul => $items)
                            <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-white/5">
                                    <span class="text-xs font-bold text-up-primary flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                                        {{ $modul }}
                                    </span>
                                    <span class="text-[10px] text-ink-400">{{ count($items) }} hak akses</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($items as $item)
                                        <label class="flex items-start gap-2.5 text-xs text-ink-200 cursor-pointer p-2.5 rounded-xl hover:bg-white/5 border border-white/5 transition-colors">
                                            <input type="checkbox" wire:model="roleBaruPermissions" value="{{ $item['name'] }}" class="accent-up-mint w-4 h-4 mt-0.5 rounded cursor-pointer shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <div class="font-semibold text-white leading-tight">{{ $item['label'] }}</div>
                                                <div class="text-[10px] text-ink-400 mt-1 leading-snug">{{ $item['deskripsi'] }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-white/5 mt-4">
                        <button wire:click="$set('showRoleModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button wire:click="saveRoleBaru" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Role</button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- TAB: CABANG & GUDANG -->
    @if($activeTab === 'cabang')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Cabang -->
            <x-prism.glass-card title="Cabang" subtitle="Lokasi toko fisik">
                <div class="space-y-2">
                    @foreach($cabangs as $c)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $c->nama }} <span class="text-ink-500 font-mono">({{ $c->kode }})</span></p>
                                <p class="text-[10px] text-ink-400">{{ $c->alamat ?? '-' }} · {{ $c->gudang_count }} gudang · {{ $c->users_count }} staff</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="openCabangModal({{ $c->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusCabang({{ $c->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan cabang ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-prism.glass-card>

            <!-- Gudang -->
            <x-prism.glass-card title="Gudang" subtitle="Lokasi penyimpanan stok">
                <div class="space-y-2">
                    @foreach($gudangs as $g)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $g->nama }} <span class="text-ink-500 font-mono">({{ $g->kode }})</span></p>
                                <p class="text-[10px] text-ink-400">{{ $g->cabang?->nama }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="openGudangModal({{ $g->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusGudang({{ $g->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan gudang ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-prism.glass-card>

            <!-- Rak Penyimpanan -->
            <x-prism.glass-card title="Rak Penyimpanan" subtitle="Lokasi bin & slot per gudang">
                <div class="space-y-2 max-h-[420px] overflow-y-auto pr-1">
                    @forelse($raks as $r)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $r->nama }} <span class="text-ink-500 font-mono">({{ $r->kode }})</span></p>
                                <p class="text-[10px] text-ink-400">
                                    {{ $r->gudang?->nama ?? 'Tanpa Gudang' }}
                                    @if($r->gudang?->cabang)
                                        <span class="text-ink-500">({{ $r->gudang->cabang->nama }})</span>
                                    @endif
                                    @if($r->zona)
                                        · Zona: <span class="font-mono text-ink-300">{{ $r->zona }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="openRakModal({{ $r->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusRak({{ $r->id }})" wire:confirm="Yakin ingin menghapus rak ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-ink-400 italic py-3 text-center">Belum ada data rak penyimpanan</p>
                    @endforelse
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: STRATEGI LOYALITAS [T-22] -->
    @if($activeTab === 'loyalitas')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-prism.glass-card title="Strategi Loyalitas" subtitle="Rasio poin & diskon per tier — editable tanpa deploy (T-22)">
                <form wire:submit="simpanLoyalitas" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rasio Earn Poin (% dari transaksi)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.poin_earn_persen" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rasio Redeem (1 poin = Rp ...)</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model="loyalitasForm.poin_redeem_rupiah" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Silver (%)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.diskon_silver" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Gold (%)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.diskon_gold" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Platinum (%)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.diskon_platinum" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">Simpan Strategi</button>
                </form>
                <p class="text-[10px] text-up-amber mt-3">⚠️ Perubahan <strong>non-retroaktif</strong> — hanya berlaku untuk transaksi baru, riwayat lama tidak berubah.</p>
            </x-prism.glass-card>

            <x-prism.glass-card title="Skema Komisi Default" subtitle="Fallback komisi reseller bila tidak ada skema custom per reseller">
                <div class="space-y-3">
                    @foreach($skemaKomisiForm as $i => $sk)
                        <div class="flex items-center gap-2 pb-2.5 border-b border-white/5 last:border-0 flex-wrap">
                            <div class="flex-1 min-w-[140px]">
                                <p class="text-xs font-bold text-white">{{ $sk['nama'] }}</p>
                                <p class="text-[10px] text-ink-400">{{ $sk['kategori'] ?: 'Semua kategori' }}</p>
                            </div>
                            <select wire:model="skemaKomisiForm.{{ $i }}.tipe" class="px-2 py-1.5 rounded-lg glass-input text-[11px] font-medium">
                                <option value="persen" class="bg-ink-900">%</option>
                                <option value="nominal" class="bg-ink-900">Rp</option>
                            </select>
                            <input type="number" step="0.01" min="0" wire:model="skemaKomisiForm.{{ $i }}.nilai" class="w-24 px-2 py-1.5 rounded-lg glass-input text-[11px] font-medium tabular-nums" />
                        </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-ink-500 mt-3">Disimpan bersama strategi loyalitas (tombol "Simpan Strategi").</p>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: KONFIGURASI PAJAK & PPN -->
    @if($activeTab === 'pajak')
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Form Pengaturan Pajak -->
                <div class="lg:col-span-2 space-y-6">
                    <x-prism.glass-card title="Pengaturan Pajak Aplikasi" subtitle="Pilih apakah aplikasi memungut pajak (PPN/lainnya) atau beroperasi tanpa pajak">
                        <form wire:submit.prevent="simpanPajak" class="space-y-5">
                            <!-- Cakupan Konfigurasi -->
                            <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                                <label class="block text-xs font-semibold text-ink-300">Target Penerapan Konfigurasi</label>
                                <select wire:model.live="pajakForm.target" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                    <option value="global" class="bg-ink-900">🌐 Global (Berlaku untuk Semua Cabang)</option>
                                    @foreach($cabangs as $c)
                                        <option value="{{ $c->id }}" class="bg-ink-900">🏬 Cabang: {{ $c->nama }} ({{ $c->kode }})</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-ink-400">Pilih cabang tertentu bila ada cabang PKP (Pengusaha Kena Pajak) dan cabang non-PKP.</p>
                            </div>

                            <!-- Sakelar Status Pajak -->
                            <div class="p-4 rounded-xl {{ $pajakForm['enabled'] ? 'bg-up-primary/10 border-up-primary/30' : 'bg-white/[0.02] border-white/5' }} border transition-[background-color,border-color] duration-140">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-white flex items-center gap-2">
                                            <span>Status Pajak:</span>
                                            @if($pajakForm['enabled'])
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-up-mint/20 text-up-mint border border-up-mint/30">AKTIF (DENGAN PAJAK)</span>
                                            @else
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/10 text-ink-300 border border-white/10">NONAKTIF (TANPA PAJAK)</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-ink-400 mt-1">
                                            {{ $pajakForm['enabled'] ? 'Aplikasi akan menghitung pajak secara otomatis pada POS Kasir & Transaksi Marketplace.' : 'Aplikasi berjalan murni tanpa pungutan pajak. Subtotal = Total bayar tanpa potongan/beban pajak.' }}
                                        </p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="pajakForm.enabled" class="sr-only peer">
                                        <div class="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-[background-color,border-color,transform] after:duration-140 peer-checked:bg-up-mint"></div>
                                    </label>
                                </div>
                            </div>

                            <!-- Detail Parameter Pajak (Muncul saat Pajak Aktif) -->
                            @if($pajakForm['enabled'])
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama / Label Pajak *</label>
                                        <input type="text" wire:model="pajakForm.nama" placeholder="Contoh: PPN, PB1, Pajak Resto" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                                        <p class="text-[10px] text-ink-400 mt-1">Nama ini dicetak di struk belanja kasir dan faktur penjualan.</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tarif Pajak (%) *</label>
                                        <div class="relative">
                                            <input type="number" step="0.1" min="0" max="100" wire:model="pajakForm.percent" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums pr-8" />
                                            <span class="absolute right-3 top-2.5 text-xs text-ink-400 font-bold">%</span>
                                        </div>
                                        <p class="text-[10px] text-ink-400 mt-1">Standar Indonesia: 11% (atau 12% sesuai regulasi UU HPP).</p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Sistem Perhitungan Pajak</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <label class="p-3.5 rounded-xl border cursor-pointer transition-[background-color,border-color] duration-140 {{ $pajakForm['mode'] === 'exclusive' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                                <input type="radio" wire:model="pajakForm.mode" value="exclusive" class="sr-only">
                                                <p class="text-xs font-bold text-white">Tax-Exclusive (Tambahan)</p>
                                                <p class="text-[11px] text-ink-400 mt-1">Harga katalog adalah DPP murni. Pajak ditambahkan di akhir kasir (Total = Harga + Pajak).</p>
                                            </label>

                                            <label class="p-3.5 rounded-xl border cursor-pointer transition-[background-color,border-color] duration-140 {{ $pajakForm['mode'] === 'inclusive' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                                <input type="radio" wire:model="pajakForm.mode" value="inclusive" class="sr-only">
                                                <p class="text-xs font-bold text-white">Tax-Inclusive (Sudah Termasuk)</p>
                                                <p class="text-[11px] text-ink-400 mt-1">Harga katalog sudah termasuk pajak. Pajak dihitung mundur dari nilai bayar konsumen.</p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                                <button type="submit" class="px-6 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">
                                    Simpan Pengaturan Pajak
                                </button>
                                <span class="text-[11px] text-ink-400">Target: <strong class="text-white">{{ $pajakForm['target'] === 'global' ? 'Global (Semua Cabang)' : 'Cabang Tertentu' }}</strong></span>
                            </div>
                        </form>
                    </x-prism.glass-card>
                </div>

                <!-- Panel Simulasi & Panduan Akuntansi -->
                <div class="space-y-6">
                    <x-prism.glass-card title="Simulasi Perhitungan" subtitle="Contoh transaksi nominal Rp 100.000">
                        @php
                            $nominalSimulasi = 100000;
                            $tarifSimulasi = (float) ($pajakForm['percent'] ?? 11);
                            $isAktif = (bool) ($pajakForm['enabled'] ?? false);
                            $modeSimulasi = $pajakForm['mode'] ?? 'exclusive';

                            if (! $isAktif) {
                                $dppSim = $nominalSimulasi;
                                $pajakSim = 0;
                                $totalSim = $nominalSimulasi;
                            } elseif ($modeSimulasi === 'inclusive') {
                                $dppSim = round($nominalSimulasi / (1 + ($tarifSimulasi / 100)), 2);
                                $pajakSim = round($nominalSimulasi - $dppSim, 2);
                                $totalSim = $nominalSimulasi;
                            } else {
                                $dppSim = $nominalSimulasi;
                                $pajakSim = round($nominalSimulasi * ($tarifSimulasi / 100), 2);
                                $totalSim = $dppSim + $pajakSim;
                            }
                        @endphp
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                <span class="text-ink-400">Status Pajak</span>
                                <span class="font-bold {{ $isAktif ? 'text-up-mint' : 'text-ink-300' }}">
                                    {{ $isAktif ? 'Aktif ('.($pajakForm['nama'] ?? 'PPN').' '.$tarifSimulasi.'%)' : 'Nonaktif (Tanpa Pajak)' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                <span class="text-ink-400">Dasar Pengenaan Pajak (DPP)</span>
                                <span class="font-bold text-white font-mono tabular-nums">Rp {{ number_format($dppSim, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                <span class="text-ink-400">Nilai {{ $pajakForm['nama'] ?? 'Pajak' }}</span>
                                <span class="font-bold {{ $pajakSim > 0 ? 'text-up-mint' : 'text-ink-400' }} font-mono tabular-nums">Rp {{ number_format($pajakSim, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-t border-white/10 font-bold text-sm">
                                <span class="text-white">Total Akhir Ditagih</span>
                                <span class="text-up-primary font-mono tabular-nums">Rp {{ number_format($totalSim, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="mt-5 p-3 rounded-xl bg-white/[0.02] border border-white/5 text-[11px] space-y-1.5 text-ink-300">
                            <p class="font-bold text-white">Pembukuan Akuntansi:</p>
                            <p>• Pendapatan Penjualan: diakui sebesar <strong class="text-white">DPP</strong> (akun 410-01).</p>
                            <p>• Utang Pajak Keluaran: dialokasikan ke akun <strong class="text-up-mint">220-01</strong>.</p>
                            <p>• Jika nonaktif: 100% total transaksi dibukukan ke pendapatan tanpa pos utang pajak.</p>
                        </div>
                    </x-prism.glass-card>

                    <x-prism.glass-card title="Kepatuhan & Laporan" subtitle="Konektivitas dengan modul Akunting">
                        <p class="text-xs text-ink-300">
                            Seluruh rekonsiliasi PPN Keluaran & PPN Masukan dapat dipantau dan diekspor ke format e-Faktur di menu:
                        </p>
                        <a href="{{ route('laporan.pajak') }}" class="mt-3 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-up-primary font-bold text-xs transition-colors">
                            <span>Buka Laporan Pajak Bulanan →</span>
                        </a>
                    </x-prism.glass-card>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: PRINTER, STRUK & LABEL QR -->
    @if($activeTab === 'printer')
        <div class="space-y-6">
            <!-- Sub-tab Navigasi Printer & Template Struk -->
            <div class="flex items-center gap-2 border-b border-black/10 dark:border-white/10 pb-3">
                <button
                    type="button"
                    wire:click="$set('printerSubTab', 'struk')"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $printerSubTab === 'struk' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    <span>🧾 Kustomisasi Template Struk & Faktur</span>
                </button>
                <button
                    type="button"
                    wire:click="$set('printerSubTab', 'label')"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $printerSubTab === 'label' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    <span>🏷️ Label Barcode & QR Master Produk</span>
                </button>
            </div>

            @if($printerSubTab === 'struk')
                <!-- SUB-TAB 1: KUSTOMISASI TEMPLATE STRUK / FAKTUR KASIR -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Form Kustomisasi Struk -->
                    <div class="lg:col-span-7 space-y-6">
                        <x-prism.glass-card title="Pengaturan Template Struk & Faktur" subtitle="Kustomisasi identitas toko, rincian faktur, QR code retur garansi, dan ketentuan">
                            <form wire:submit.prevent="simpanTemplateStruk" class="space-y-5">
                                <!-- Bagian 1: Header Toko -->
                                <div class="space-y-3">
                                    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-up-primary"></span>
                                        <span>Identitas & Header Struk Toko</span>
                                    </h4>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-ink-300 mb-1">Nama Toko / Brand *</label>
                                            <input type="text" wire:model.live.debounce.300ms="strukForm.nama_toko" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold text-white" placeholder="Contoh: UTE PARTS">
                                            @error('strukForm.nama_toko') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-ink-300 mb-1">Slogan / Sub-header</label>
                                            <input type="text" wire:model.live.debounce.300ms="strukForm.slogan" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Pusat Sparepart & Servis HP">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Alamat Toko</label>
                                        <input type="text" wire:model.live.debounce.300ms="strukForm.alamat" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Jl. Veteran No. 12, Jakarta">
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-ink-300 mb-1">No. Telepon / WhatsApp</label>
                                            <input type="text" wire:model.live.debounce.300ms="strukForm.telepon" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: 0812-3456-7890">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-ink-300 mb-1">Website / Media Sosial</label>
                                            <input type="text" wire:model.live.debounce.300ms="strukForm.website_medsos" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: www.uteparts.id / @uteparts">
                                        </div>
                                    </div>
                                </div>

                                <!-- Bagian 2: Konten & Informasi Struk -->
                                <div class="pt-4 border-t border-white/5 space-y-3">
                                    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-up-mint"></span>
                                        <span>Komponen & Informasi Transaksi</span>
                                    </h4>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="strukForm.show_cabang" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-ink-200">Tampilkan Nama Cabang</span>
                                        </label>

                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="strukForm.show_waktu" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-ink-200">Tampilkan Tanggal & Waktu</span>
                                        </label>

                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="strukForm.show_kasir" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-ink-200">Tampilkan Nama Kasir</span>
                                        </label>

                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="strukForm.show_pelanggan" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-ink-200">Tampilkan Nama Pelanggan</span>
                                        </label>

                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5 sm:col-span-2">
                                            <input type="checkbox" wire:model.live="strukForm.show_subtotal_breakdown" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-ink-200">Pisahkan Subtotal Jasa & Sparepart (Bila Ada)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Bagian 3: QR Code Faktur Retur & Garansi (Anti-Fraud) -->
                                <div class="pt-4 border-t border-white/5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-up-red"></span>
                                            <span>QR Code Faktur Retur & Garansi Anti-Fraud</span>
                                        </h4>
                                        <span class="px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary font-bold text-[10px]">Fitur Retur Instan</span>
                                    </div>

                                    <div class="p-3.5 rounded-2xl bg-up-primary/10 border border-up-primary/20 space-y-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model.live="strukForm.show_qr_retur" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-xs font-bold text-white">Cetak QR Code Nomor Faktur pada Struk</span>
                                        </label>
                                        <p class="text-[11px] text-ink-300">
                                            Memudahkan pemindaian struk menggunakan kamera atau scanner barcode di halaman Retur Konsumen untuk verifikasi instan riwayat transaksi & nomor seri barang.
                                        </p>
                                    </div>

                                    @if($strukForm['show_qr_retur'] ?? true)
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                            <div>
                                                <label class="block text-xs font-semibold text-ink-300 mb-1">Teks Petunjuk di Bawah QR</label>
                                                <input type="text" wire:model.live.debounce.300ms="strukForm.qr_caption" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Scan QR ini untuk retur & klaim garansi">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-ink-300 mb-1">Ukuran QR Code (px)</label>
                                                <select wire:model.live="strukForm.qr_size" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white bg-ink-900">
                                                    <option value="60">Kecil (60px) — Hemat Kertas 58mm</option>
                                                    <option value="80">Sedang (80px) — Standar Rekomendasi</option>
                                                    <option value="100">Besar (100px) — Sangat Jelas untuk 80mm</option>
                                                </select>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Bagian 4: Ketentuan Garansi, Retur & Footer -->
                                <div class="pt-4 border-t border-white/5 space-y-3">
                                    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-up-amber"></span>
                                        <span>Ketentuan Garansi, Retur & Footer</span>
                                    </h4>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Ucapan Penutup / Terima Kasih *</label>
                                        <input type="text" wire:model.live.debounce.300ms="strukForm.footer_text" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Terima kasih atas kunjungan Anda!">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Kebijakan Retur & Garansi Konsumen</label>
                                        <textarea wire:model.live.debounce.300ms="strukForm.kebijakan_retur" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Maksimal retur/garansi 3 hari sejak tanggal pembelian dengan membawa struk ber-QR ini."></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Kaki Tambahan</label>
                                        <input type="text" wire:model.live.debounce.300ms="strukForm.catatan_kaki" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Segel garansi toko wajib utuh & unit tidak cacat fisik.">
                                    </div>
                                </div>

                                <!-- Bagian 5: Format Kertas Default -->
                                <div class="pt-4 border-t border-white/5 space-y-3">
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Format Kertas Default Transaksi</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <label class="p-3 rounded-xl border cursor-pointer transition-colors {{ ($strukForm['default_width'] ?? '') === '80mm' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                            <input type="radio" wire:model.live="strukForm.default_width" value="80mm" class="sr-only">
                                            <p class="text-xs font-bold text-white">🖨️ Struk 80mm</p>
                                            <p class="text-[10px] text-ink-400 mt-0.5">Default Rekomendasi (Lebar & Lengkap)</p>
                                        </label>
                                        <label class="p-3 rounded-xl border cursor-pointer transition-colors {{ ($strukForm['default_width'] ?? '') === '58mm' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                            <input type="radio" wire:model.live="strukForm.default_width" value="58mm" class="sr-only">
                                            <p class="text-xs font-bold text-white">🧾 Struk 58mm</p>
                                            <p class="text-[10px] text-ink-400 mt-0.5">Printer Mini / Bluetooth BLE</p>
                                        </label>
                                        <label class="p-3 rounded-xl border cursor-pointer transition-colors {{ ($strukForm['default_width'] ?? '') === 'a4' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                            <input type="radio" wire:model.live="strukForm.default_width" value="a4" class="sr-only">
                                            <p class="text-xs font-bold text-white">📄 Faktur A4</p>
                                            <p class="text-[10px] text-ink-400 mt-0.5">Printer Kantor / Laserjet</p>
                                        </label>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer shadow-md shadow-up-mint/20">
                                        Simpan Kustomisasi Template Struk
                                    </button>
                                    <span class="text-[11px] text-ink-400">Format aktif: <strong class="text-white">{{ strtoupper($strukForm['default_width'] ?? '80MM') }}</strong></span>
                                </div>
                            </form>
                        </x-prism.glass-card>
                    </div>

                    <!-- Live Pratinjau Interaktif Struk Thermal -->
                    <div class="lg:col-span-5 space-y-4">
                        <x-prism.glass-card title="Pratinjau Struk Thermal Live" subtitle="Hasil cetak fisik sesuai kustomisasi template Anda">
                            <div class="flex flex-col items-center justify-center p-4 sm:p-6 bg-ink-950/40 rounded-2xl border border-white/5">
                                <!-- Kertas Struk Simulasi -->
                                <div
                                    class="bg-white text-black p-4 rounded-xl font-mono text-xs shadow-2xl space-y-2 border border-gray-300 transition-all duration-200 select-text"
                                    style="width: {{ ($strukForm['default_width'] ?? '') === '58mm' ? '280px' : '330px' }};"
                                >
                                    <!-- Header Toko -->
                                    <div class="text-center pb-2 border-b border-dashed border-gray-400 space-y-0.5">
                                        <h3 class="text-sm font-black tracking-wider text-gray-900 uppercase">{{ $strukForm['nama_toko'] ?: 'UTE PARTS' }}</h3>
                                        @if(!empty($strukForm['slogan']))
                                            <p class="text-[10px] text-gray-600 font-medium">{{ $strukForm['slogan'] }}</p>
                                        @endif
                                        @if(!empty($strukForm['alamat']))
                                            <p class="text-[9px] text-gray-500">{{ $strukForm['alamat'] }}</p>
                                        @endif
                                        @if(!empty($strukForm['telepon']))
                                            <p class="text-[9px] text-gray-500">Telp/WA: {{ $strukForm['telepon'] }}</p>
                                        @endif
                                        @if(!empty($strukForm['website_medsos']))
                                            <p class="text-[9px] text-gray-400">{{ $strukForm['website_medsos'] }}</p>
                                        @endif
                                        @if($strukForm['show_cabang'] ?? true)
                                            <p class="text-[9px] text-gray-500">Cabang Pusat - Jakarta</p>
                                        @endif
                                        @if($strukForm['show_waktu'] ?? true)
                                            <p class="text-[9px] text-gray-500">{{ now()->format('d/m/Y H:i:s') }}</p>
                                        @endif
                                    </div>

                                    <!-- Meta Transaksi -->
                                    <div class="text-[10px] space-y-0.5 border-b border-dashed border-gray-300 pb-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">No. TRX:</span>
                                            <span class="font-bold text-gray-900">TRX-20261018-0001</span>
                                        </div>
                                        @if($strukForm['show_kasir'] ?? true)
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Kasir:</span>
                                                <span class="text-gray-900">{{ auth()->user()?->name ?? 'Kasir 1' }}</span>
                                            </div>
                                        @endif
                                        @if($strukForm['show_pelanggan'] ?? true)
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Pelanggan:</span>
                                                <span class="text-gray-900 font-medium">Budi Santoso</span>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Sampel Item -->
                                    <div class="space-y-1.5 border-b border-dashed border-gray-300 pb-2 pt-1 text-[11px]">
                                        <div>
                                            <div class="font-semibold text-gray-900 truncate">[Part] LCD iPhone 13 Pro OLED</div>
                                            <div class="flex justify-between text-[10px] text-gray-600 mt-0.5">
                                                <span>1 x 1.450.000</span>
                                                <span class="font-bold text-gray-900">1.450.000</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 truncate">[Jasa] Pasang & Segel Garansi</div>
                                            <div class="flex justify-between text-[10px] text-gray-600 mt-0.5">
                                                <span>1 x 100.000</span>
                                                <span class="font-bold text-gray-900">100.000</span>
                                            </div>
                                        </div>
                                    </div>

                                    @if($strukForm['show_subtotal_breakdown'] ?? true)
                                        <div class="text-[10px] space-y-0.5 border-b border-dashed border-gray-300 pb-1 text-gray-600">
                                            <div class="flex justify-between">
                                                <span>Subtotal Part:</span>
                                                <span>Rp 1.450.000</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Subtotal Jasa:</span>
                                                <span>Rp 100.000</span>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Total & Bayar -->
                                    <div class="space-y-1 text-[11px] pt-1 font-bold">
                                        <div class="flex justify-between text-xs text-gray-950">
                                            <span>TOTAL:</span>
                                            <span class="font-black">Rp 1.550.000</span>
                                        </div>
                                        <div class="flex justify-between text-gray-700 font-normal text-[10px]">
                                            <span>BAYAR (TUNAI):</span>
                                            <span>Rp 1.600.000</span>
                                        </div>
                                        <div class="flex justify-between text-gray-700 font-normal text-[10px]">
                                            <span>KEMBALI:</span>
                                            <span>Rp 50.000</span>
                                        </div>
                                    </div>

                                    <!-- QR Code Faktur Retur & Garansi Anti-Fraud -->
                                    @if($strukForm['show_qr_retur'] ?? true)
                                        <div class="text-center py-2 flex flex-col items-center justify-center border-t border-dashed border-gray-300">
                                            <div class="p-1 bg-white inline-block">
                                                {!! app(\App\Modules\Wms\Services\QrCodeService::class)->generateSvg('TRX-20261018-0001', (int)($strukForm['qr_size'] ?? 80), 0) !!}
                                            </div>
                                            <p class="font-mono text-[9px] text-gray-900 font-bold mt-1 tracking-wider">TRX-20261018-0001</p>
                                            <p class="text-[8px] text-gray-500 text-center mt-0.5">{{ $strukForm['qr_caption'] ?: 'Scan QR ini untuk retur & klaim garansi' }}</p>
                                        </div>
                                    @endif

                                    <!-- Footer & Ketentuan -->
                                    <div class="text-center pt-2 text-[9px] text-gray-500 border-t border-dashed border-gray-300 space-y-0.5">
                                        <p class="font-bold text-gray-800">Garansi: 3 Hari Toko</p>
                                        <p class="font-medium text-gray-700">{{ $strukForm['footer_text'] ?: 'Terima Kasih atas Kunjungan Anda!' }}</p>
                                        @if(!empty($strukForm['kebijakan_retur']))
                                            <p class="text-[8px] text-gray-600 font-medium">{{ $strukForm['kebijakan_retur'] }}</p>
                                        @endif
                                        @if(!empty($strukForm['catatan_kaki']))
                                            <p class="text-[8px] text-gray-400">{{ $strukForm['catatan_kaki'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </x-prism.glass-card>
                    </div>
                </div>
            @else
                <!-- SUB-TAB 2: LABEL BARCODE & QR MASTER PRODUK -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Form Pengaturan -->
                    <div class="lg:col-span-7 space-y-6">
                        <x-prism.glass-card title="Pengaturan Label QR & Barcode Produk" subtitle="Konfigurasi dimensi label thermal roll dan margin custom">
                            <form wire:submit.prevent="simpanPengaturanPrinter" class="space-y-5">
                                <!-- Preset Ukuran -->
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Preset Ukuran Label Produk</label>
                                    <select wire:model.live="printerForm.label_preset" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                        <option value="33x15">🏷️ 33mm × 15mm (Default Rekomendasi)</option>
                                        <option value="40x20">🏷️ 40mm × 20mm (Sedang)</option>
                                        <option value="50x30">🏷️ 50mm × 30mm (Besar)</option>
                                        <option value="58x40">🏷️ 58mm × 40mm (Ekstra Besar)</option>
                                        <option value="custom">⚙️ Custom (Atur Manual)</option>
                                    </select>
                                    <p class="text-[11px] text-ink-400 mt-1">Default preset 33mm × 15mm adalah standar label rak dan sparepart HP.</p>
                                </div>

                                <!-- Dimensi Label (Width & Height) -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Lebar Label (mm)</label>
                                        <input type="number" step="0.5" wire:model.live="printerForm.label_width" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono">
                                        @error('printerForm.label_width') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1">Tinggi Label (mm)</label>
                                        <input type="number" step="0.5" wire:model.live="printerForm.label_height" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono">
                                        @error('printerForm.label_height') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Margin Custom -->
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-2">Margin & Padding Kertas Label (mm)</label>
                                    <div class="grid grid-cols-4 gap-2">
                                        <div>
                                            <span class="block text-[10px] text-ink-400 mb-1">Atas</span>
                                            <input type="number" step="0.5" wire:model.live="printerForm.label_margin_top" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-center">
                                        </div>
                                        <div>
                                            <span class="block text-[10px] text-ink-400 mb-1">Bawah</span>
                                            <input type="number" step="0.5" wire:model.live="printerForm.label_margin_bottom" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-center">
                                        </div>
                                        <div>
                                            <span class="block text-[10px] text-ink-400 mb-1">Kiri</span>
                                            <input type="number" step="0.5" wire:model.live="printerForm.label_margin_left" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-center">
                                        </div>
                                        <div>
                                            <span class="block text-[10px] text-ink-400 mb-1">Kanan</span>
                                            <input type="number" step="0.5" wire:model.live="printerForm.label_margin_right" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-center">
                                        </div>
                                    </div>
                                </div>

                                <!-- Opsi Tampilan Label -->
                                <div class="space-y-2 pt-2 border-t border-white/5">
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Konten Label QR</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="printerForm.label_show_brand" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-xs text-ink-200">Brand Toko</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="printerForm.label_show_price" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-xs text-ink-200">Harga Jual</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02] cursor-pointer hover:bg-white/5">
                                            <input type="checkbox" wire:model.live="printerForm.label_show_sku" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30">
                                            <span class="text-xs text-ink-200">Teks SKU</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer shadow-md shadow-up-mint/20">
                                        Simpan Pengaturan Label QR
                                    </button>
                                    <span class="text-[11px] text-ink-400">Preset: <strong class="text-white">{{ $printerForm['label_width'] }}mm × {{ $printerForm['label_height'] }}mm</strong></span>
                                </div>
                            </form>
                        </x-prism.glass-card>
                    </div>

                    <!-- Live Preview Interaktif Label -->
                    <div class="lg:col-span-5 space-y-6">
                        <x-prism.glass-card title="Simulasi Tampilan Label QR" subtitle="Ukuran proporsional: {{ $printerForm['label_width'] }}mm × {{ $printerForm['label_height'] }}mm">
                            <div class="flex flex-col items-center justify-center p-6 bg-ink-950/40 rounded-2xl border border-white/5">
                                <!-- Container Simulasi Label Fisik -->
                                <div
                                    class="bg-white text-black rounded shadow-lg overflow-hidden border border-gray-300 flex items-center transition-all duration-200"
                                    style="
                                        width: {{ min(360, max(180, (float)$printerForm['label_width'] * 7)) }}px;
                                        height: {{ min(180, max(85, (float)$printerForm['label_height'] * 7)) }}px;
                                        padding-top: {{ (float)$printerForm['label_margin_top'] * 2.5 }}px;
                                        padding-bottom: {{ (float)$printerForm['label_margin_bottom'] * 2.5 }}px;
                                        padding-left: {{ (float)$printerForm['label_margin_left'] * 2.5 }}px;
                                        padding-right: {{ (float)$printerForm['label_margin_right'] * 2.5 }}px;
                                    "
                                >
                                    <!-- Sisi Kiri: QR Code -->
                                    <div class="shrink-0 flex items-center justify-center bg-gray-50 border border-gray-200 rounded p-1" style="width: {{ min(80, max(45, (float)$printerForm['label_height'] * 5)) }}px; height: {{ min(80, max(45, (float)$printerForm['label_height'] * 5)) }}px;">
                                        {!! app(\App\Modules\Wms\Services\QrCodeService::class)->generateSvg('UTP-DEMO-001', 70, 0) !!}
                                    </div>

                                    <!-- Sisi Kanan: Detail Produk -->
                                    <div class="ml-2.5 min-w-0 flex-1 flex flex-col justify-center font-sans leading-tight">
                                        @if($printerForm['label_show_brand'])
                                            <span class="text-[9px] font-black uppercase tracking-wider text-indigo-700 truncate">UTE PARTS</span>
                                        @endif
                                        <span class="text-[10px] font-bold text-gray-900 line-clamp-2 mt-0.5">LCD Touchscreen iPhone 13 Pro Max</span>
                                        @if($printerForm['label_show_sku'])
                                            <span class="text-[8px] font-mono font-semibold text-gray-600 mt-0.5 truncate">SKU: LCD-IP13PM-OLED</span>
                                        @endif
                                        @if($printerForm['label_show_price'])
                                            <span class="text-[10px] font-black text-emerald-700 mt-1">Rp 1.450.000</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="text-[11px] text-ink-400 mt-3 font-mono">Skala pratinjau ~7x layar</span>
                            </div>

                            <div class="mt-4 p-3 rounded-xl bg-white/[0.02] border border-white/5 text-[11px] text-ink-300 space-y-1.5">
                                <p class="font-bold text-white">💡 Panduan Printer Thermal Roll:</p>
                                <p>• <strong>Ukuran 33mm × 15mm</strong> adalah ukuran standar label barcode 3 kolom / 1 kolom yang sangat umum digunakan di toko sparepart & servis.</p>
                                <p>• QR Code vector SVG dapat di-scan secara presisi dari jarak dekat tanpa blur.</p>
                                <p>• Margin dapat disesuaikan jika hasil cetak printer fisik Anda sedikit bergeser.</p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-white/5 flex gap-2">
                                <a href="{{ route('barcode.print') }}" target="_blank" class="flex-1 py-2.5 px-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs text-center shadow-md shadow-up-primary/25 transition-colors">
                                    🖨️ Buka Sampel Cetak Label →
                                </a>
                            </div>
                        </x-prism.glass-card>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- TAB: MASTER PRODUK & KATALOG -->
    @if($activeTab === 'master_produk')
        <div class="space-y-4">
            <!-- Sub-tab Bar: Modern horizontal pill scrollbar with tactile active feedback -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-black/10 dark:border-white/5 scrollbar-none scroll-smooth touch-pan-x flex-nowrap -mx-1 px-1 sm:mx-0 sm:px-0">
                @foreach([
                    'kategori' => ['icon' => '🗂️', 'label' => 'Kategori Hierarkis', 'count' => count($kategoriList)],
                    'brand' => ['icon' => '🏷️', 'label' => 'Brand Part', 'count' => count($brandList)],
                    'kualitas' => ['icon' => '⭐', 'label' => 'Tingkat Kualitas', 'count' => count($kualitasList)],
                    'satuan' => ['icon' => '📏', 'label' => 'Satuan Unit', 'count' => count($satuanList)],
                    'kondisi' => ['icon' => '🔄', 'label' => 'Kondisi Produk', 'count' => count($kondisiList)],
                    'tipe_hp' => ['icon' => '📱', 'label' => 'Tipe HP Kompatibel', 'count' => count($tipeHpList)],
                ] as $subKey => $subData)
                    <button
                        wire:click="$set('masterProdukSubTab', '{{ $subKey }}')"
                        class="flex-shrink-0 whitespace-nowrap px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all active:scale-95 min-h-[40px] cursor-pointer {{ $masterProdukSubTab === $subKey ? 'bg-up-primary/20 text-white border border-up-primary/40 font-bold shadow-sm shadow-up-primary/10' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10 border border-transparent' }}"
                    >
                        <span class="text-sm leading-none">{{ $subData['icon'] }}</span>
                        <span>{{ $subData['label'] }}</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-mono tabular-nums leading-none {{ $masterProdukSubTab === $subKey ? 'bg-up-primary text-white font-bold' : 'bg-black/10 dark:bg-white/10 text-ink-400' }}">{{ $subData['count'] }}</span>
                    </button>
                @endforeach
            </div>

            <!-- SUB-TAB 1: KATEGORI -->
            @if($masterProdukSubTab === 'kategori')
                @if(count($selectedKategoriIds) > 0)
                    <div class="mb-3 p-3 bg-up-primary/15 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm tabular-nums">
                                {{ count($selectedKategoriIds) }}
                            </span>
                            <span class="text-xs font-bold text-white">Kategori Terpilih</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button type="button" wire:click="openBatchEditModal" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm min-h-[36px]">
                                <span>✏️ Edit ({{ count($selectedKategoriIds) }})</span>
                            </button>
                            <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                                <button type="button" wire:click="batchSetStatus(true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Aktif
                                </button>
                                <button type="button" wire:click="batchSetStatus(false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Nonaktif
                                </button>
                            </div>
                            <button type="button" wire:click="batchHapus" wire:confirm="Yakin ingin menghapus / menonaktifkan {{ count($selectedKategoriIds) }} kategori terpilih?" class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[36px]">
                                <span>🗑️ Hapus</span>
                            </button>
                            <button type="button" wire:click="clearSelection" class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold transition cursor-pointer min-h-[36px] flex items-center">
                                ✕ Batal
                            </button>
                        </div>
                    </div>
                @endif
                <x-prism.glass-card title="Katalog Kategori Berjenjang" subtitle="Kelola kategori utama dan sub-kategori untuk pengelompokan produk">
                    <!-- Mobile Card View (< md) -->
                    <div class="block md:hidden space-y-2.5">
                        <div class="flex items-center justify-between px-1 py-1 text-xs text-ink-400">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/5">
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll('kategori', @js($kategoriList->pluck('id')->all()))"
                                           @checked(count($selectedKategoriIds) > 0 && count($selectedKategoriIds) === count($kategoriList))
                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                </span>
                                <span class="font-semibold text-white">Pilih Semua ({{ count($kategoriList) }})</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums text-ink-400">{{ count($selectedKategoriIds) }} dipilih</span>
                        </div>

                        @forelse($kategoriList as $k)
                            @php $isKategoriSelected = in_array($k->id, $selectedKategoriIds, true); @endphp
                            <div class="p-3.5 rounded-2xl border transition-all {{ $isKategoriSelected ? 'bg-up-primary/10 border-up-primary/40 shadow-sm shadow-up-primary/10' : 'bg-white/[0.02] border-white/5 hover:border-white/10' }}">
                                <div class="flex items-start gap-3">
                                    <label class="inline-flex items-center justify-center w-9 h-9 -m-1 rounded-xl hover:bg-white/5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox"
                                               wire:model.live="selectedKategoriIds"
                                               value="{{ $k->id }}"
                                               class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                    </label>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 truncate">
                                                @if($k->parent_id)
                                                    <span class="text-ink-500 text-xs">↳</span>
                                                    <span class="font-bold text-white text-xs truncate">{{ $k->nama }}</span>
                                                @else
                                                    <span class="text-sm">{{ $k->icon ?? '📁' }}</span>
                                                    <span class="font-bold text-white text-xs truncate">{{ $k->nama }}</span>
                                                @endif
                                            </div>
                                            <button wire:click="toggleKategoriStatus({{ $k->id }})" class="cursor-pointer flex-shrink-0">
                                                <x-prism.status-pill :status="$k->is_active ? 'active' : 'inactive'" :label="$k->is_active ? 'Aktif' : 'Nonaktif'" size="xs" />
                                            </button>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                            @if($k->parent)
                                                <span class="px-2 py-0.5 rounded bg-white/5 text-[10px] text-ink-300">Sub dari {{ $k->parent->nama }}</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-up-primary/15 text-[10px] text-up-primary font-semibold">Kategori Utama</span>
                                            @endif
                                            <span class="font-mono text-[10px] text-ink-400">#{{ $k->urutan }}</span>
                                            <span class="font-mono text-[10px] text-ink-500 truncate max-w-[120px]">{{ $k->slug }}</span>
                                        </div>

                                        <div class="flex items-center justify-between pt-2.5 mt-2.5 border-t border-white/5">
                                            <span class="text-[11px] text-ink-400 font-medium">
                                                Produk: <span class="font-bold tabular-nums {{ $k->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">{{ $k->produk_count }}</span>
                                            </span>
                                            <div class="flex items-center gap-3">
                                                <button wire:click="openKategoriModal({{ $k->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">✏️ Edit</button>
                                                <button wire:click="hapusKategori({{ $k->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan kategori ini?" class="text-up-red hover:underline text-xs cursor-pointer">🗑️ Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-ink-500 text-xs">Belum ada kategori produk. Klik "+ Kategori Baru" untuk menambahkan.</div>
                        @endforelse
                    </div>

                    <!-- Desktop Dual-Scroll Table (>= md) -->
                    <div class="hidden md:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-left text-xs text-ink-200 min-w-[760px] whitespace-nowrap">
                                <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-center">
                                            <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:click="toggleSelectAll('kategori', @js($kategoriList->pluck('id')->all()))"
                                                       @checked(count($selectedKategoriIds) > 0 && count($selectedKategoriIds) === count($kategoriList))
                                                       class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                            </label>
                                        </th>
                                        <th class="py-2.5 px-3">Urutan</th>
                                        <th class="py-2.5 px-3">Nama Kategori</th>
                                        <th class="py-2.5 px-3">Slug / Path</th>
                                        <th class="py-2.5 px-3">Tipe / Induk</th>
                                        <th class="py-2.5 px-3 text-center">Jml Produk</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-right">
                                            @if(count($selectedKategoriIds) > 0)
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button" wire:click="openBatchEditModal" class="text-up-primary font-bold hover:underline" title="Batch Edit">✏️ Edit ({{ count($selectedKategoriIds) }})</button>
                                                    <span class="text-ink-600">|</span>
                                                    <button type="button" wire:click="batchHapus" wire:confirm="Hapus {{ count($selectedKategoriIds) }} item terpilih?" class="text-up-red font-bold hover:underline" title="Batch Hapus">🗑️ Hapus</button>
                                                </div>
                                            @else
                                                Aksi
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($kategoriList as $k)
                                        <tr class="hover:bg-white/[0.02] transition-colors {{ in_array($k->id, $selectedKategoriIds, true) ? 'bg-up-primary/10' : '' }}">
                                            <td class="py-2.5 px-3 text-center">
                                                <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                    <input type="checkbox"
                                                           wire:model.live="selectedKategoriIds"
                                                           value="{{ $k->id }}"
                                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                                </label>
                                            </td>
                                            <td class="py-2.5 px-3 font-mono text-ink-400">{{ $k->urutan }}</td>
                                            <td class="py-2.5 px-3">
                                                @if($k->parent_id)
                                                    <div class="flex items-center gap-1.5 pl-5">
                                                        <span class="text-ink-500">↳</span>
                                                        <span class="font-medium text-white">{{ $k->nama }}</span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-2 font-bold text-white">
                                                        <span>{{ $k->icon ?? '📁' }}</span>
                                                        <span>{{ $k->nama }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 font-mono text-[11px] text-ink-400">{{ $k->slug }}</td>
                                            <td class="py-2.5 px-3">
                                                @if($k->parent)
                                                    <span class="px-2 py-0.5 rounded bg-white/5 text-[10px] text-ink-300">Sub dari {{ $k->parent->nama }}</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded bg-up-primary/15 text-[10px] text-up-primary font-semibold">Kategori Utama</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $k->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                                {{ $k->produk_count }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                <button wire:click="toggleKategoriStatus({{ $k->id }})" class="cursor-pointer">
                                                    <x-prism.status-pill :status="$k->is_active ? 'active' : 'inactive'" :label="$k->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </button>
                                            </td>
                                            <td class="py-2.5 px-3 text-right space-x-2">
                                                <button wire:click="openKategoriModal({{ $k->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                                <button wire:click="hapusKategori({{ $k->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan kategori ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-6 text-center text-ink-500">Belum ada kategori produk. Klik "+ Kategori Baru" untuk menambahkan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>
                </x-prism.glass-card>

            <!-- SUB-TAB 2: BRAND PART -->
            @elseif($masterProdukSubTab === 'brand')
                @if(count($selectedBrandIds) > 0)
                    <div class="mb-3 p-3 bg-up-primary/15 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm tabular-nums">
                                {{ count($selectedBrandIds) }}
                            </span>
                            <span class="text-xs font-bold text-white">Brand Part Terpilih</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button type="button" wire:click="openBatchEditModal" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm min-h-[36px]">
                                <span>✏️ Edit ({{ count($selectedBrandIds) }})</span>
                            </button>
                            <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                                <button type="button" wire:click="batchSetStatus(true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Aktif
                                </button>
                                <button type="button" wire:click="batchSetStatus(false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Nonaktif
                                </button>
                            </div>
                            <button type="button" wire:click="batchHapus" wire:confirm="Yakin ingin menghapus / menonaktifkan {{ count($selectedBrandIds) }} brand part terpilih?" class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[36px]">
                                <span>🗑️ Hapus</span>
                            </button>
                            <button type="button" wire:click="clearSelection" class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold transition cursor-pointer min-h-[36px] flex items-center">
                                ✕ Batal
                            </button>
                        </div>
                    </div>
                @endif
                <x-prism.glass-card title="Master Brand Part (Pabrikan Sparepart)" subtitle="Kelola merk pabrikan suku cadang (Brand Part) seperti Olike, Hippo, Shinetsu, Vizz, dll">
                    <!-- Mobile Card View (< md) -->
                    <div class="block md:hidden space-y-2.5">
                        <div class="flex items-center justify-between px-1 py-1 text-xs text-ink-400">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/5">
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll('brand', @js($brandList->pluck('id')->all()))"
                                           @checked(count($selectedBrandIds) > 0 && count($selectedBrandIds) === count($brandList))
                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                </span>
                                <span class="font-semibold text-white">Pilih Semua ({{ count($brandList) }})</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums text-ink-400">{{ count($selectedBrandIds) }} dipilih</span>
                        </div>

                        @forelse($brandList as $b)
                            @php $isBrandSelected = in_array($b->id, $selectedBrandIds, true); @endphp
                            <div class="p-3.5 rounded-2xl border transition-all {{ $isBrandSelected ? 'bg-up-primary/10 border-up-primary/40 shadow-sm shadow-up-primary/10' : 'bg-white/[0.02] border-white/5 hover:border-white/10' }}">
                                <div class="flex items-start gap-3">
                                    <label class="inline-flex items-center justify-center w-9 h-9 -m-1 rounded-xl hover:bg-white/5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox"
                                               wire:model.live="selectedBrandIds"
                                               value="{{ $b->id }}"
                                               class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                    </label>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="text-sm">🏷️</span>
                                                <span class="font-bold text-white text-xs truncate">{{ $b->nama }}</span>
                                            </div>
                                            <button wire:click="toggleBrandStatus({{ $b->id }})" class="cursor-pointer flex-shrink-0">
                                                <x-prism.status-pill :status="$b->is_active ? 'active' : 'inactive'" :label="$b->is_active ? 'Aktif' : 'Nonaktif'" size="xs" />
                                            </button>
                                        </div>

                                        @if($b->keterangan)
                                            <p class="text-[11px] text-ink-400 mt-1 line-clamp-2 leading-relaxed">{{ $b->keterangan }}</p>
                                        @endif

                                        <div class="flex items-center justify-between pt-2.5 mt-2.5 border-t border-white/5">
                                            <span class="text-[11px] text-ink-400 font-medium">
                                                Produk: <span class="font-bold tabular-nums {{ $b->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">{{ $b->produk_count }}</span>
                                            </span>
                                            <div class="flex items-center gap-3">
                                                <button wire:click="openBrandModal({{ $b->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">✏️ Edit</button>
                                                <button wire:click="hapusBrand({{ $b->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan brand part ini?" class="text-up-red hover:underline text-xs cursor-pointer">🗑️ Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-ink-500 text-xs">Belum ada data brand part.</div>
                        @endforelse
                    </div>

                    <!-- Desktop Dual-Scroll Table (>= md) -->
                    <div class="hidden md:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-left text-xs text-ink-200 min-w-[760px] whitespace-nowrap">
                                <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-center">
                                            <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:click="toggleSelectAll('brand', @js($brandList->pluck('id')->all()))"
                                                       @checked(count($selectedBrandIds) > 0 && count($selectedBrandIds) === count($brandList))
                                                       class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                            </label>
                                        </th>
                                        <th class="py-2.5 px-3">Nama Brand Part</th>
                                        <th class="py-2.5 px-3">Keterangan</th>
                                        <th class="py-2.5 px-3 text-center">Jml Produk</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-right">
                                            @if(count($selectedBrandIds) > 0)
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button" wire:click="openBatchEditModal" class="text-up-primary font-bold hover:underline" title="Batch Edit">✏️ Edit ({{ count($selectedBrandIds) }})</button>
                                                    <span class="text-ink-600">|</span>
                                                    <button type="button" wire:click="batchHapus" wire:confirm="Hapus {{ count($selectedBrandIds) }} brand terpilih?" class="text-up-red font-bold hover:underline" title="Batch Hapus">🗑️ Hapus</button>
                                                </div>
                                            @else
                                                Aksi
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($brandList as $b)
                                        <tr class="hover:bg-white/[0.02] transition-colors {{ in_array($b->id, $selectedBrandIds, true) ? 'bg-up-primary/10' : '' }}">
                                            <td class="py-2.5 px-3 text-center">
                                                <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                    <input type="checkbox"
                                                           wire:model.live="selectedBrandIds"
                                                           value="{{ $b->id }}"
                                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                                </label>
                                            </td>
                                            <td class="py-2.5 px-3 font-bold text-white">{{ $b->nama }}</td>
                                            <td class="py-2.5 px-3 text-ink-400">{{ $b->keterangan ?? '—' }}</td>
                                            <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $b->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                                {{ $b->produk_count }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                <button wire:click="toggleBrandStatus({{ $b->id }})" class="cursor-pointer">
                                                    <x-prism.status-pill :status="$b->is_active ? 'active' : 'inactive'" :label="$b->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </button>
                                            </td>
                                            <td class="py-2.5 px-3 text-right space-x-2">
                                                <button wire:click="openBrandModal({{ $b->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                                <button wire:click="hapusBrand({{ $b->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan brand part ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-ink-500">Belum ada data brand part.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>
                </x-prism.glass-card>

            <!-- SUB-TAB 3: KUALITAS -->
            @elseif($masterProdukSubTab === 'kualitas')
                @if(count($selectedKualitasIds) > 0)
                    <div class="mb-3 p-3 bg-up-primary/15 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm tabular-nums">
                                {{ count($selectedKualitasIds) }}
                            </span>
                            <span class="text-xs font-bold text-white">Tingkat Kualitas Terpilih</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button type="button" wire:click="openBatchEditModal" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm min-h-[36px]">
                                <span>✏️ Edit ({{ count($selectedKualitasIds) }})</span>
                            </button>
                            <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                                <button type="button" wire:click="batchSetStatus(true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Aktif
                                </button>
                                <button type="button" wire:click="batchSetStatus(false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Nonaktif
                                </button>
                            </div>
                            <button type="button" wire:click="batchHapus" wire:confirm="Yakin ingin menghapus / menonaktifkan {{ count($selectedKualitasIds) }} tingkat kualitas terpilih?" class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[36px]">
                                <span>🗑️ Hapus</span>
                            </button>
                            <button type="button" wire:click="clearSelection" class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold transition cursor-pointer min-h-[36px] flex items-center">
                                ✕ Batal
                            </button>
                        </div>
                    </div>
                @endif
                <x-prism.glass-card title="Master Tingkat Kualitas" subtitle="Klasifikasi kualitas suku cadang (Original, Grade A, OEM, dll)">
                    <!-- Mobile Card View (< md) -->
                    <div class="block md:hidden space-y-2.5">
                        <div class="flex items-center justify-between px-1 py-1 text-xs text-ink-400">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/5">
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll('kualitas', @js($kualitasList->pluck('id')->all()))"
                                           @checked(count($selectedKualitasIds) > 0 && count($selectedKualitasIds) === count($kualitasList))
                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                </span>
                                <span class="font-semibold text-white">Pilih Semua ({{ count($kualitasList) }})</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums text-ink-400">{{ count($selectedKualitasIds) }} dipilih</span>
                        </div>

                        @forelse($kualitasList as $k)
                            @php $isKualitasSelected = in_array($k->id, $selectedKualitasIds, true); @endphp
                            <div class="p-3.5 rounded-2xl border transition-all {{ $isKualitasSelected ? 'bg-up-primary/10 border-up-primary/40 shadow-sm shadow-up-primary/10' : 'bg-white/[0.02] border-white/5 hover:border-white/10' }}">
                                <div class="flex items-start gap-3">
                                    <label class="inline-flex items-center justify-center w-9 h-9 -m-1 rounded-xl hover:bg-white/5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox"
                                               wire:model.live="selectedKualitasIds"
                                               value="{{ $k->id }}"
                                               class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                    </label>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="text-sm">⭐</span>
                                                <span class="font-bold text-white text-xs truncate">{{ $k->nama }}</span>
                                            </div>
                                            <button wire:click="toggleKualitasStatus({{ $k->id }})" class="cursor-pointer flex-shrink-0">
                                                <x-prism.status-pill :status="$k->is_active ? 'active' : 'inactive'" :label="$k->is_active ? 'Aktif' : 'Nonaktif'" size="xs" />
                                            </button>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                            @if($k->brand)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                                    <span>🏷️</span> {{ $k->brand->nama }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/5 text-ink-400 border border-white/10">
                                                    Universal
                                                </span>
                                            @endif
                                        </div>

                                        @if($k->keterangan)
                                            <p class="text-[11px] text-ink-400 mt-1 line-clamp-2 leading-relaxed">{{ $k->keterangan }}</p>
                                        @endif

                                        <div class="flex items-center justify-between pt-2.5 mt-2.5 border-t border-white/5">
                                            <span class="text-[11px] text-ink-400 font-medium">
                                                Produk: <span class="font-bold tabular-nums {{ $k->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">{{ $k->produk_count }}</span>
                                            </span>
                                            <div class="flex items-center gap-3">
                                                <button wire:click="openKualitasModal({{ $k->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">✏️ Edit</button>
                                                <button wire:click="hapusKualitas({{ $k->id }})" wire:confirm="Yakin ingin menghapus kualitas ini?" class="text-up-red hover:underline text-xs cursor-pointer">🗑️ Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-ink-500 text-xs">Belum ada data tingkat kualitas.</div>
                        @endforelse
                    </div>

                    <!-- Desktop Dual-Scroll Table (>= md) -->
                    <div class="hidden md:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-left text-xs text-ink-200 min-w-[760px] whitespace-nowrap">
                                <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-center">
                                            <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:click="toggleSelectAll('kualitas', @js($kualitasList->pluck('id')->all()))"
                                                       @checked(count($selectedKualitasIds) > 0 && count($selectedKualitasIds) === count($kualitasList))
                                                       class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                            </label>
                                        </th>
                                        <th class="py-2.5 px-3">Tingkat Kualitas</th>
                                        <th class="py-2.5 px-3">Brand Part Induk</th>
                                        <th class="py-2.5 px-3">Keterangan</th>
                                        <th class="py-2.5 px-3 text-center">Jml Produk</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-right">
                                            @if(count($selectedKualitasIds) > 0)
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button" wire:click="openBatchEditModal" class="text-up-primary font-bold hover:underline" title="Batch Edit">✏️ Edit ({{ count($selectedKualitasIds) }})</button>
                                                    <span class="text-ink-600">|</span>
                                                    <button type="button" wire:click="batchHapus" wire:confirm="Hapus {{ count($selectedKualitasIds) }} kualitas terpilih?" class="text-up-red font-bold hover:underline" title="Batch Hapus">🗑️ Hapus</button>
                                                </div>
                                            @else
                                                Aksi
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($kualitasList as $k)
                                        <tr class="hover:bg-white/[0.02] transition-colors {{ in_array($k->id, $selectedKualitasIds, true) ? 'bg-up-primary/10' : '' }}">
                                            <td class="py-2.5 px-3 text-center">
                                                <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                    <input type="checkbox"
                                                           wire:model.live="selectedKualitasIds"
                                                           value="{{ $k->id }}"
                                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                                </label>
                                            </td>
                                            <td class="py-2.5 px-3 font-bold text-white">{{ $k->nama }}</td>
                                            <td class="py-2.5 px-3">
                                                @if($k->brand)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                                        <span>🏷️</span> {{ $k->brand->nama }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/5 text-ink-400 border border-white/10">
                                                        Universal
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-ink-400">{{ $k->keterangan ?? '—' }}</td>
                                            <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $k->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                                {{ $k->produk_count }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                <button wire:click="toggleKualitasStatus({{ $k->id }})" class="cursor-pointer">
                                                    <x-prism.status-pill :status="$k->is_active ? 'active' : 'inactive'" :label="$k->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </button>
                                            </td>
                                            <td class="py-2.5 px-3 text-right space-x-2">
                                                <button wire:click="openKualitasModal({{ $k->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                                <button wire:click="hapusKualitas({{ $k->id }})" wire:confirm="Yakin ingin menghapus kualitas ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-6 text-center text-ink-500">Belum ada data tingkat kualitas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>
                </x-prism.glass-card>

            <!-- SUB-TAB 4: SATUAN -->
            @elseif($masterProdukSubTab === 'satuan')
                @if(count($selectedSatuanIds) > 0)
                    <div class="mb-3 p-3 bg-up-primary/15 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm tabular-nums">
                                {{ count($selectedSatuanIds) }}
                            </span>
                            <span class="text-xs font-bold text-white">Satuan Terpilih</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button type="button" wire:click="openBatchEditModal" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm min-h-[36px]">
                                <span>✏️ Edit ({{ count($selectedSatuanIds) }})</span>
                            </button>
                            <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                                <button type="button" wire:click="batchSetStatus(true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Aktif
                                </button>
                                <button type="button" wire:click="batchSetStatus(false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Nonaktif
                                </button>
                            </div>
                            <button type="button" wire:click="batchHapus" wire:confirm="Yakin ingin menghapus / menonaktifkan {{ count($selectedSatuanIds) }} satuan unit terpilih?" class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[36px]">
                                <span>🗑️ Hapus</span>
                            </button>
                            <button type="button" wire:click="clearSelection" class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold transition cursor-pointer min-h-[36px] flex items-center">
                                ✕ Batal
                            </button>
                        </div>
                    </div>
                @endif
                <x-prism.glass-card title="Master Satuan Unit (UOM)" subtitle="Daftar satuan kuantitas barang inventaris (pcs, unit, box, set, dll)">
                    <!-- Mobile Card View (< md) -->
                    <div class="block md:hidden space-y-2.5">
                        <div class="flex items-center justify-between px-1 py-1 text-xs text-ink-400">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/5">
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll('satuan', @js($satuanList->pluck('id')->all()))"
                                           @checked(count($selectedSatuanIds) > 0 && count($selectedSatuanIds) === count($satuanList))
                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                </span>
                                <span class="font-semibold text-white">Pilih Semua ({{ count($satuanList) }})</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums text-ink-400">{{ count($selectedSatuanIds) }} dipilih</span>
                        </div>

                        @forelse($satuanList as $s)
                            @php $isSatuanSelected = in_array($s->id, $selectedSatuanIds, true); @endphp
                            <div class="p-3.5 rounded-2xl border transition-all {{ $isSatuanSelected ? 'bg-up-primary/10 border-up-primary/40 shadow-sm shadow-up-primary/10' : 'bg-white/[0.02] border-white/5 hover:border-white/10' }}">
                                <div class="flex items-start gap-3">
                                    <label class="inline-flex items-center justify-center w-9 h-9 -m-1 rounded-xl hover:bg-white/5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox"
                                               wire:model.live="selectedSatuanIds"
                                               value="{{ $s->id }}"
                                               class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                    </label>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="px-2 py-0.5 rounded bg-up-primary/15 font-mono font-bold text-up-primary text-xs">{{ $s->kode }}</span>
                                                <span class="font-bold text-white text-xs truncate">{{ $s->nama }}</span>
                                            </div>
                                            <button wire:click="toggleSatuanStatus({{ $s->id }})" class="cursor-pointer flex-shrink-0">
                                                <x-prism.status-pill :status="$s->is_active ? 'active' : 'inactive'" :label="$s->is_active ? 'Aktif' : 'Nonaktif'" size="xs" />
                                            </button>
                                        </div>

                                        <div class="flex items-center justify-end gap-3 pt-2.5 mt-2.5 border-t border-white/5">
                                            <button wire:click="openSatuanModal({{ $s->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">✏️ Edit</button>
                                            <button wire:click="hapusSatuan({{ $s->id }})" wire:confirm="Hapus satuan ini?" class="text-up-red hover:underline text-xs cursor-pointer">🗑️ Hapus</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-ink-500 text-xs">Belum ada data satuan unit.</div>
                        @endforelse
                    </div>

                    <!-- Desktop Dual-Scroll Table (>= md) -->
                    <div class="hidden md:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-left text-xs text-ink-200 min-w-[760px] whitespace-nowrap">
                                <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-center">
                                            <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:click="toggleSelectAll('satuan', @js($satuanList->pluck('id')->all()))"
                                                       @checked(count($selectedSatuanIds) > 0 && count($selectedSatuanIds) === count($satuanList))
                                                       class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                            </label>
                                        </th>
                                        <th class="py-2.5 px-3">Kode Satuan</th>
                                        <th class="py-2.5 px-3">Nama Satuan</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-right">
                                            @if(count($selectedSatuanIds) > 0)
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button" wire:click="openBatchEditModal" class="text-up-primary font-bold hover:underline" title="Batch Edit">✏️ Edit ({{ count($selectedSatuanIds) }})</button>
                                                    <span class="text-ink-600">|</span>
                                                    <button type="button" wire:click="batchHapus" wire:confirm="Hapus {{ count($selectedSatuanIds) }} satuan terpilih?" class="text-up-red font-bold hover:underline" title="Batch Hapus">🗑️ Hapus</button>
                                                </div>
                                            @else
                                                Aksi
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($satuanList as $s)
                                        <tr class="hover:bg-white/[0.02] transition-colors {{ in_array($s->id, $selectedSatuanIds, true) ? 'bg-up-primary/10' : '' }}">
                                            <td class="py-2.5 px-3 text-center">
                                                <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                    <input type="checkbox"
                                                           wire:model.live="selectedSatuanIds"
                                                           value="{{ $s->id }}"
                                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                                </label>
                                            </td>
                                            <td class="py-2.5 px-3 font-mono font-bold text-up-primary">{{ $s->kode }}</td>
                                            <td class="py-2.5 px-3 text-white font-medium">{{ $s->nama }}</td>
                                            <td class="py-2.5 px-3 text-center">
                                                <button wire:click="toggleSatuanStatus({{ $s->id }})" class="cursor-pointer">
                                                    <x-prism.status-pill :status="$s->is_active ? 'active' : 'inactive'" :label="$s->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </button>
                                            </td>
                                            <td class="py-2.5 px-3 text-right space-x-2">
                                                <button wire:click="openSatuanModal({{ $s->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                                <button wire:click="hapusSatuan({{ $s->id }})" wire:confirm="Hapus satuan ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-6 text-center text-ink-500">Belum ada data satuan unit.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>
                </x-prism.glass-card>

            <!-- SUB-TAB 5: KONDISI -->
            @elseif($masterProdukSubTab === 'kondisi')
                @if(count($selectedKondisiKodes) > 0)
                    <div class="mb-3 p-3 bg-up-primary/15 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm tabular-nums">
                                {{ count($selectedKondisiKodes) }}
                            </span>
                            <span class="text-xs font-bold text-white">Kondisi Terpilih</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button type="button" wire:click="openBatchEditModal" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm min-h-[36px]">
                                <span>✏️ Edit ({{ count($selectedKondisiKodes) }})</span>
                            </button>
                            <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                                <button type="button" wire:click="batchSetStatus(true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Aktif
                                </button>
                                <button type="button" wire:click="batchSetStatus(false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Nonaktif
                                </button>
                            </div>
                            <button type="button" wire:click="batchHapus" wire:confirm="Yakin ingin menghapus / menonaktifkan {{ count($selectedKondisiKodes) }} kondisi terpilih?" class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[36px]">
                                <span>🗑️ Hapus</span>
                            </button>
                            <button type="button" wire:click="clearSelection" class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold transition cursor-pointer min-h-[36px] flex items-center">
                                ✕ Batal
                            </button>
                        </div>
                    </div>
                @endif
                <x-prism.glass-card title="Master Opsi Kondisi Produk (Gaya SID Retail Pro)" subtitle="Klasifikasi kondisi fisik suku cadang berindikator warna visual untuk kemudahan kasir & gudang">
                    <!-- Mobile Card View (< md) -->
                    <div class="block md:hidden space-y-2.5">
                        <div class="flex items-center justify-between px-1 py-1 text-xs text-ink-400">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/5">
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll('kondisi', @js(collect($kondisiList)->pluck('kode')->all()))"
                                           @checked(count($selectedKondisiKodes) > 0 && count($selectedKondisiKodes) === count($kondisiList))
                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                </span>
                                <span class="font-semibold text-white">Pilih Semua ({{ count($kondisiList) }})</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums text-ink-400">{{ count($selectedKondisiKodes) }} dipilih</span>
                        </div>

                        @forelse($kondisiList as $c)
                            @php
                                $warna = $c['warna'] ?? 'emerald';
                                $hex = $c['hex'] ?? '#10b981';
                                $badgeClasses = match($warna) {
                                    'emerald' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                    'blue' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                                    'cyan' => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                                    'amber' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                                    'rose' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                                    'purple' => 'bg-purple-500/15 text-purple-300 border-purple-500/30',
                                    default => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
                                };
                                $isKondisiSelected = in_array($c['kode'], $selectedKondisiKodes, true);
                            @endphp
                            <div class="p-3.5 rounded-2xl border transition-all {{ $isKondisiSelected ? 'bg-up-primary/10 border-up-primary/40 shadow-sm shadow-up-primary/10' : 'bg-white/[0.02] border-white/5 hover:border-white/10' }}">
                                <div class="flex items-start gap-3">
                                    <label class="inline-flex items-center justify-center w-9 h-9 -m-1 rounded-xl hover:bg-white/5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox"
                                               wire:model.live="selectedKondisiKodes"
                                               value="{{ $c['kode'] }}"
                                               class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                    </label>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $badgeClasses }}">
                                                    <span class="w-2 h-2 rounded-full inline-block shadow-sm" style="background-color: {{ $hex }}"></span>
                                                    <span>{{ $c['nama'] }}</span>
                                                </span>
                                                <span class="font-mono text-[10px] text-ink-400">{{ $c['kode'] }}</span>
                                            </div>
                                            <button wire:click="toggleKondisiStatus('{{ $c['kode'] }}')" class="cursor-pointer flex-shrink-0">
                                                <x-prism.status-pill :status="($c['is_active'] ?? true) ? 'active' : 'inactive'" :label="($c['is_active'] ?? true) ? 'Aktif' : 'Nonaktif'" size="xs" />
                                            </button>
                                        </div>

                                        @if($c['keterangan'] ?? false)
                                            <p class="text-[11px] text-ink-300 mt-1.5 line-clamp-2 leading-relaxed">{{ $c['keterangan'] }}</p>
                                        @endif

                                        <div class="flex items-center justify-between pt-2.5 mt-2.5 border-t border-white/5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-3 h-3 rounded-full border border-white/20 shadow-sm" style="background-color: {{ $hex }}"></span>
                                                <span class="font-mono text-[10px] text-ink-400">{{ ucfirst($warna) }}</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <button wire:click="openKondisiModal('{{ $c['kode'] }}')" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">✏️ Edit</button>
                                                <button wire:click="hapusKondisi('{{ $c['kode'] }}')" wire:confirm="Hapus kondisi ini?" class="text-up-red hover:underline text-xs cursor-pointer">🗑️ Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-ink-500 text-xs">Belum ada opsi kondisi.</div>
                        @endforelse
                    </div>

                    <!-- Desktop Dual-Scroll Table (>= md) -->
                    <div class="hidden md:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-left text-xs text-ink-200 min-w-[760px] whitespace-nowrap">
                                <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-center">
                                            <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:click="toggleSelectAll('kondisi', @js(collect($kondisiList)->pluck('kode')->all()))"
                                                       @checked(count($selectedKondisiKodes) > 0 && count($selectedKondisiKodes) === count($kondisiList))
                                                       class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                            </label>
                                        </th>
                                        <th class="py-2.5 px-3">Kode</th>
                                        <th class="py-2.5 px-3">Nama Label Kondisi</th>
                                        <th class="py-2.5 px-3">Indikator Warna</th>
                                        <th class="py-2.5 px-3">Keterangan / Karakteristik</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-right">
                                            @if(count($selectedKondisiKodes) > 0)
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button" wire:click="openBatchEditModal" class="text-up-primary font-bold hover:underline" title="Batch Edit">✏️ Edit ({{ count($selectedKondisiKodes) }})</button>
                                                    <span class="text-ink-600">|</span>
                                                    <button type="button" wire:click="batchHapus" wire:confirm="Hapus {{ count($selectedKondisiKodes) }} kondisi terpilih?" class="text-up-red font-bold hover:underline" title="Batch Hapus">🗑️ Hapus</button>
                                                </div>
                                            @else
                                                Aksi
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($kondisiList as $c)
                                        @php
                                            $warna = $c['warna'] ?? 'emerald';
                                            $hex = $c['hex'] ?? '#10b981';
                                            $badgeClasses = match($warna) {
                                                'emerald' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                                'blue' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                                                'cyan' => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                                                'amber' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                                                'rose' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                                                'purple' => 'bg-purple-500/15 text-purple-300 border-purple-500/30',
                                                default => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
                                            };
                                        @endphp
                                        <tr class="hover:bg-white/[0.02] transition-colors {{ in_array($c['kode'], $selectedKondisiKodes, true) ? 'bg-up-primary/10' : '' }}">
                                            <td class="py-2.5 px-3 text-center">
                                                <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                    <input type="checkbox"
                                                           wire:model.live="selectedKondisiKodes"
                                                           value="{{ $c['kode'] }}"
                                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                                </label>
                                            </td>
                                            <td class="py-2.5 px-3 font-mono text-ink-400">{{ $c['kode'] }}</td>
                                            <td class="py-2.5 px-3 font-bold text-white">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badgeClasses }}">
                                                    <span class="w-2 h-2 rounded-full inline-block shadow-sm" style="background-color: {{ $hex }}"></span>
                                                    <span>{{ $c['nama'] }}</span>
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-3.5 h-3.5 rounded-full border border-white/20 shadow-sm" style="background-color: {{ $hex }}"></span>
                                                    <span class="font-mono text-[11px] text-ink-300">{{ ucfirst($warna) }} ({{ $hex }})</span>
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3 text-ink-300 text-[11px] max-w-xs">{{ $c['keterangan'] ?? '—' }}</td>
                                            <td class="py-2.5 px-3 text-center">
                                                <button wire:click="toggleKondisiStatus('{{ $c['kode'] }}')" class="cursor-pointer">
                                                    <x-prism.status-pill :status="($c['is_active'] ?? true) ? 'active' : 'inactive'" :label="($c['is_active'] ?? true) ? 'Aktif' : 'Nonaktif'" />
                                                </button>
                                            </td>
                                            <td class="py-2.5 px-3 text-right space-x-2">
                                                <button wire:click="openKondisiModal('{{ $c['kode'] }}')" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                                <button wire:click="hapusKondisi('{{ $c['kode'] }}')" wire:confirm="Hapus kondisi ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-6 text-center text-ink-500">Belum ada opsi kondisi.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>
                </x-prism.glass-card>

            <!-- SUB-TAB 6: TIPE HP -->
            @elseif($masterProdukSubTab === 'tipe_hp')
                @if(count($selectedTipeHpIds) > 0)
                    <div class="mb-3 p-3 bg-up-primary/15 border border-up-primary/30 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-lg backdrop-blur-md">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm tabular-nums">
                                {{ count($selectedTipeHpIds) }}
                            </span>
                            <span class="text-xs font-bold text-white">Tipe HP Terpilih</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button type="button" wire:click="openBatchEditModal" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-sm min-h-[36px]">
                                <span>✏️ Edit ({{ count($selectedTipeHpIds) }})</span>
                            </button>
                            <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-0.5">
                                <button type="button" wire:click="batchSetStatus(true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-up-mint hover:bg-up-mint/15 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Aktif
                                </button>
                                <button type="button" wire:click="batchSetStatus(false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-ink-300 hover:bg-white/10 transition cursor-pointer min-h-[32px] flex items-center">
                                    Set Nonaktif
                                </button>
                            </div>
                            <button type="button" wire:click="batchHapus" wire:confirm="Yakin ingin menghapus / menonaktifkan {{ count($selectedTipeHpIds) }} tipe HP terpilih?" class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[36px]">
                                <span>🗑️ Hapus</span>
                            </button>
                            <button type="button" wire:click="clearSelection" class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 text-xs font-semibold transition cursor-pointer min-h-[36px] flex items-center">
                                ✕ Batal
                            </button>
                        </div>
                    </div>
                @endif
                <x-prism.glass-card title="Master Tipe HP / Perangkat Kompatibel" subtitle="Database tipe perangkat untuk pemetaan kompatibilitas sparepart">
                    <!-- Mobile Card View (< md) -->
                    <div class="block md:hidden space-y-2.5">
                        <div class="flex items-center justify-between px-1 py-1 text-xs text-ink-400">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/5">
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll('tipe_hp', @js($tipeHpList->pluck('id')->all()))"
                                           @checked(count($selectedTipeHpIds) > 0 && count($selectedTipeHpIds) === count($tipeHpList))
                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                </span>
                                <span class="font-semibold text-white">Pilih Semua ({{ count($tipeHpList) }})</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums text-ink-400">{{ count($selectedTipeHpIds) }} dipilih</span>
                        </div>

                        @forelse($tipeHpList as $t)
                            @php $isTipeHpSelected = in_array($t->id, $selectedTipeHpIds, true); @endphp
                            <div class="p-3.5 rounded-2xl border transition-all {{ $isTipeHpSelected ? 'bg-up-primary/10 border-up-primary/40 shadow-sm shadow-up-primary/10' : 'bg-white/[0.02] border-white/5 hover:border-white/10' }}">
                                <div class="flex items-start gap-3">
                                    <label class="inline-flex items-center justify-center w-9 h-9 -m-1 rounded-xl hover:bg-white/5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox"
                                               wire:model.live="selectedTipeHpIds"
                                               value="{{ $t->id }}"
                                               class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                    </label>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="text-sm">📱</span>
                                                <span class="font-bold text-white text-xs truncate">{{ $t->nama }}</span>
                                            </div>
                                            <button wire:click="toggleTipeHpStatus({{ $t->id }})" class="cursor-pointer flex-shrink-0">
                                                <x-prism.status-pill :status="$t->is_active ? 'active' : 'inactive'" :label="$t->is_active ? 'Aktif' : 'Nonaktif'" size="xs" />
                                            </button>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                            <span class="px-2 py-0.5 rounded bg-up-primary/15 text-[10px] text-up-primary font-semibold">{{ $t->merk }}</span>
                                            <span class="font-mono text-[10px] text-ink-300">{{ $t->model }}</span>
                                        </div>

                                        <div class="flex items-center justify-between pt-2.5 mt-2.5 border-t border-white/5">
                                            <span class="text-[11px] text-ink-400 font-medium">
                                                Part Terkait: <span class="font-bold tabular-nums {{ $t->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">{{ $t->produk_count }}</span>
                                            </span>
                                            <div class="flex items-center gap-3">
                                                <button wire:click="openTipeHpModal({{ $t->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">✏️ Edit</button>
                                                <button wire:click="hapusTipeHp({{ $t->id }})" wire:confirm="Hapus tipe HP ini?" class="text-up-red hover:underline text-xs cursor-pointer">🗑️ Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-ink-500 text-xs">Belum ada data tipe HP.</div>
                        @endforelse
                    </div>

                    <!-- Desktop Dual-Scroll Table (>= md) -->
                    <div class="hidden md:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-left text-xs text-ink-200 min-w-[760px] whitespace-nowrap">
                                <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-center">
                                            <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:click="toggleSelectAll('tipe_hp', @js($tipeHpList->pluck('id')->all()))"
                                                       @checked(count($selectedTipeHpIds) > 0 && count($selectedTipeHpIds) === count($tipeHpList))
                                                       class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                            </label>
                                        </th>
                                        <th class="py-2.5 px-3">Merek Perangkat</th>
                                        <th class="py-2.5 px-3">Model</th>
                                        <th class="py-2.5 px-3">Nama Lengkap</th>
                                        <th class="py-2.5 px-3 text-center">Jml Part Terkait</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-right">
                                            @if(count($selectedTipeHpIds) > 0)
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button" wire:click="openBatchEditModal" class="text-up-primary font-bold hover:underline" title="Batch Edit">✏️ Edit ({{ count($selectedTipeHpIds) }})</button>
                                                    <span class="text-ink-600">|</span>
                                                    <button type="button" wire:click="batchHapus" wire:confirm="Hapus {{ count($selectedTipeHpIds) }} tipe HP terpilih?" class="text-up-red font-bold hover:underline" title="Batch Hapus">🗑️ Hapus</button>
                                                </div>
                                            @else
                                                Aksi
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($tipeHpList as $t)
                                        <tr class="hover:bg-white/[0.02] transition-colors {{ in_array($t->id, $selectedTipeHpIds, true) ? 'bg-up-primary/10' : '' }}">
                                            <td class="py-2.5 px-3 text-center">
                                                <label class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-white/5 cursor-pointer">
                                                    <input type="checkbox"
                                                           wire:model.live="selectedTipeHpIds"
                                                           value="{{ $t->id }}"
                                                           class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90">
                                                </label>
                                            </td>
                                            <td class="py-2.5 px-3 font-semibold text-white">{{ $t->merk }}</td>
                                            <td class="py-2.5 px-3 font-mono text-ink-300">{{ $t->model }}</td>
                                            <td class="py-2.5 px-3 text-white">{{ $t->nama }}</td>
                                            <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $t->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                                {{ $t->produk_count }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                <button wire:click="toggleTipeHpStatus({{ $t->id }})" class="cursor-pointer">
                                                    <x-prism.status-pill :status="$t->is_active ? 'active' : 'inactive'" :label="$t->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </button>
                                            </td>
                                            <td class="py-2.5 px-3 text-right space-x-2">
                                                <button wire:click="openTipeHpModal({{ $t->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                                <button wire:click="hapusTipeHp({{ $t->id }})" wire:confirm="Hapus tipe HP ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-6 text-center text-ink-500">Belum ada data tipe HP.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>
                </x-prism.glass-card>
            @endif
        </div>
    @endif

    <!-- TAB: MASTER DATA OPERASIONAL -->
    @if($activeTab === 'master')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-prism.glass-card title="Jenis Servis & Garansi" subtitle="Kategori & durasi garansi editable (T-16)">
                <div class="space-y-2">
                    @foreach($jenisServis as $j)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $j->nama }}</p>
                                <p class="text-[10px] text-ink-400 font-mono">
                                    {{ $j->kode }} · {{ $j->kategori }} · est. {{ $j->estimasi_durasi }} mnt · Rp {{ number_format($j->biaya_jasa ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold {{ $j->is_part_original ? 'text-up-accent' : 'text-up-mint' }} tabular-nums">
                                    {{ $j->durasi_garansi_hari }} hari
                                </span>
                                @if($j->butuh_part)
                                    <span class="text-[9px] text-up-primary bg-up-primary/10 px-1.5 py-0.5 rounded">part</span>
                                @endif
                                <button wire:click="openJenisServisModal({{ $j->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusJenisServis({{ $j->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan jenis servis ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <button wire:click="openJenisServisModal()" class="px-3 py-2 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-[11px] cursor-pointer">+ Jenis Servis Baru</button>
                </div>
            </x-prism.glass-card>

            <x-prism.glass-card title="Chart of Account (COA)" subtitle="Standar retail + servis (PRD §4.6)">
                <div class="max-h-96 overflow-y-auto space-y-1 pr-1">
                    @foreach($coaList as $akun)
                        <div class="flex justify-between py-1.5 border-b border-white/5 last:border-0 text-xs">
                            <span class="font-mono text-up-primary">{{ $akun->kode }}</span>
                            <span class="text-ink-200 flex-1 ml-2">{{ $akun->nama }}</span>
                            <span class="text-[9px] uppercase text-ink-400">{{ $akun->tipe }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-ink-500 mt-3">Kelola di Modul Akunting → COA (editable terbatas super-admin/finance).</p>
            </x-prism.glass-card>
        </div>
    @endif

    @if($activeTab === 'integrasi')
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form (Col Span 2) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- WhatsApp Gateway Card -->
                    <x-prism.glass-card title="WhatsApp Gateway & Notifikasi Otomatis" subtitle="Koneksi gateway pesan keluar via WhatsApp (Fonnte, Wablas, atau Custom)">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Provider Gateway *</label>
                                    <select wire:model.live="integrasiForm.wa_provider" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                        <option value="fonnte" class="bg-ink-900">Fonnte (Rekomendasi)</option>
                                        <option value="wablas" class="bg-ink-900">Wablas</option>
                                        <option value="custom" class="bg-ink-900">Custom Webhook / HTTP Gateway</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">API Endpoint URL *</label>
                                    <input type="url" wire:model="integrasiForm.wa_url" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="https://api.fonnte.com/send" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Token / API Key *</label>
                                    <input type="password" wire:model="integrasiForm.wa_token" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="Masukkan token API gateway WA" />
                                    <p class="text-[10px] text-ink-400 mt-1">Dapatkan dari dashboard penyedia WA gateway Anda.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Device ID (Opsional)</label>
                                    <input type="text" wire:model="integrasiForm.wa_device_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="Kosongkan bila single-device" />
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                                <h4 class="text-xs font-bold text-white mb-1.5">Pemicu Otomatis (Automation Triggers)</h4>
                                <label class="flex items-center gap-2.5 text-xs text-ink-300 cursor-pointer">
                                    <input type="checkbox" wire:model="integrasiForm.wa_auto_notif_transaksi" class="accent-up-mint w-4 h-4 rounded" />
                                    <span>Kirim struk / notifikasi otomatis via WA saat transaksi kasir POS selesai</span>
                                </label>
                                <label class="flex items-center gap-2.5 text-xs text-ink-300 cursor-pointer">
                                    <input type="checkbox" wire:model="integrasiForm.wa_auto_notif_servis" class="accent-up-mint w-4 h-4 rounded" />
                                    <span>Kirim update progres servis otomatis saat status tiket servis berubah</span>
                                </label>
                            </div>
                        </div>
                    </x-prism.glass-card>

                    <!-- Duitku Payment Gateway Card -->
                    <x-prism.glass-card title="Payment Gateway Duitku" subtitle="Integrasi pembayaran online (QRIS, VA Bank, E-Wallet, Kartu Kredit)">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Mode Lingkungan (Environment) *</label>
                                    <select wire:model="integrasiForm.duitku_mode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                        <option value="sandbox" class="bg-ink-900">🧪 Sandbox (Mode Percobaan)</option>
                                        <option value="production" class="bg-ink-900">🚀 Production (Transaksi Nyata)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Masa Berlaku Invoice (Menit) *</label>
                                    <input type="number" min="15" max="10080" wire:model="integrasiForm.duitku_expiry_period" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                                    <p class="text-[10px] text-ink-400 mt-1">Default 1440 menit (24 jam).</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Merchant Code</label>
                                    <input type="text" wire:model="integrasiForm.duitku_merchant_code" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="DXXXX" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">API Key</label>
                                    <input type="password" wire:model="integrasiForm.duitku_api_key" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="API Key Duitku" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Merchant Key (Passport)</label>
                                    <input type="password" wire:model="integrasiForm.duitku_merchant_key" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="Merchant Key" />
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-semibold text-white">Callback URL Webhook Duitku</span>
                                    <p class="text-[11px] text-ink-400 mt-0.5">Pasang URL ini di dashboard Merchant Duitku → Proyek → Callback URL:</p>
                                </div>
                                <code class="px-3 py-1.5 rounded-lg bg-black/40 border border-white/10 text-up-mint font-mono text-[11px] select-all truncate">
                                    {{ url('/webhook/duitku') }}
                                </code>
                            </div>
                        </div>
                    </x-prism.glass-card>

                    <!-- Biteship Logistics Card -->
                    <x-prism.glass-card title="Logistik & Ekspedisi Biteship" subtitle="Perhitungan ongkos kirim real-time dan booking kurir multi-ekspedisi">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Biteship API Key</label>
                                    <input type="password" wire:model="integrasiForm.biteship_api_key" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="biteship_test.xxx..." />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kebijakan Titik Asal Pengiriman (Origin)</label>
                                    <select wire:model="integrasiForm.biteship_origin_type" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                        <option value="closest_branch" class="bg-ink-900">🏬 Cabang Terdekat dengan Stok Lengkap</option>
                                        <option value="head_office" class="bg-ink-900">🏢 Kantor Pusat / Gudang Utama Saja</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </x-prism.glass-card>
                </div>

                <!-- Sidebar Test & Status (Col Span 1) -->
                <div class="space-y-6">
                    <x-prism.glass-card title="Uji Coba WhatsApp" subtitle="Pastikan pesan gateway dapat terkirim">
                        <div class="space-y-3.5">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-1">Nomor WhatsApp Penerima Tes</label>
                                <input type="text" wire:model="integrasiForm.wa_test_number" placeholder="Contoh: 081234567890" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                                <p class="text-[10px] text-ink-400 mt-1">Format: 08xx atau 628xx</p>
                            </div>
                            <button
                                type="button"
                                wire:click="testKirimWa"
                                wire:loading.attr="disabled"
                                class="w-full py-2.5 px-4 rounded-xl bg-up-primary hover:opacity-95 text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,opacity] active:scale-[0.98] disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span wire:loading.remove wire:target="testKirimWa">📲 Kirim Pesan Uji Coba</span>
                                <span wire:loading wire:target="testKirimWa">Mengirim pesan...</span>
                            </button>

                            <div class="pt-3 border-t border-white/5 space-y-2 text-[11px] text-ink-400">
                                <p class="font-semibold text-white">Panduan Singkat Gateway:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Scan QR di dasbor provider WA pilihan Anda.</li>
                                    <li>Salin token API ke formulir ini dan klik Simpan.</li>
                                    <li>Lakukan tes kirim di atas untuk memvalidasi nomor terhubung.</li>
                                </ul>
                            </div>
                        </div>
                    </x-prism.glass-card>

                    <div class="p-4 rounded-2xl glass-panel border border-white/10 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🛡️</span>
                            <h4 class="text-xs font-bold text-white">Keamanan Kredensial</h4>
                        </div>
                        <p class="text-[11px] text-ink-300 leading-relaxed">
                            Semua token dan kunci API disimpan terenkripsi di database sistem dan hanya dapat diubah oleh pengguna yang memiliki kewenangan <span class="text-up-primary font-semibold">pengaturan.manage</span>.
                        </p>
                        <button
                            type="button"
                            wire:click="simpanIntegrasi"
                            class="w-full py-2.5 px-4 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.98] cursor-pointer"
                        >
                            💾 Simpan Semua Konfigurasi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'database' && isSuperAdminOrOwner())
        @livewire(\App\Modules\Rbac\Livewire\DatabaseManagement::class)
    @endif

    <!-- MODAL: USER -->
    @if($showUserModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $userForm['id'] ? 'Edit User' : 'User Baru' }}</h3>
                    <button wire:click="$set('showUserModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="userForm.name" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email *</label>
                        <input type="email" wire:model="userForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                        <input type="text" wire:model="userForm.phone" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">{{ $userForm['id'] ? 'Password (kosongkan = tidak diubah)' : 'Password *' }}</label>
                        <input type="password" wire:model="userForm.password" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Min 6 karakter" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Role</label>
                        <select wire:model="userForm.role" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}" class="bg-ink-900">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Akses Cabang &amp; Cabang Utama</label>
                        <div class="space-y-2">
                            @foreach($cabangs as $c)
                                <div class="flex items-center justify-between p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                    <label class="flex items-center gap-2 text-xs text-ink-200 cursor-pointer">
                                        <input type="checkbox" wire:model.live="userForm.cabang_ids" value="{{ $c->id }}" class="accent-up-mint w-4 h-4" />
                                        {{ $c->nama }}
                                    </label>
                                    @if(in_array($c->id, array_map('intval', $userForm['cabang_ids'] ?? [])))
                                        <label class="flex items-center gap-1.5 text-[11px] text-ink-400 cursor-pointer">
                                            <input type="radio" wire:model="userForm.cabang_default_id" value="{{ $c->id }}" name="user_default_cabang" class="accent-up-primary w-3.5 h-3.5" />
                                            Utama (Default)
                                        </label>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="userForm.is_active" class="accent-up-mint w-4 h-4" />
                        Akun Aktif
                    </label>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showUserModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveUser" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan User</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: CABANG -->
    @if($showCabangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $cabangForm['id'] ? 'Edit Cabang' : 'Cabang Baru' }}</h3>
                    <button wire:click="$set('showCabangModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="cabangForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                        <input type="text" wire:model="cabangForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="CBG-03" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="cabangForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                        <input type="text" wire:model="cabangForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showCabangModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveCabang" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: GUDANG -->
    @if($showGudangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $gudangForm['id'] ? 'Edit Gudang' : 'Gudang Baru' }}</h3>
                    <button wire:click="$set('showGudangModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Cabang *</label>
                        <select wire:model="gudangForm.cabang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Cabang...</option>
                            @foreach($cabangs as $c)
                                <option value="{{ $c->id }}" class="bg-ink-900">{{ $c->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="gudangForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                        <input type="text" wire:model="gudangForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="GDG-04" />
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showGudangModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveGudang" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: RAK -->
    @if($showRakModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $rakForm['id'] ? 'Edit Rak' : 'Rak Baru' }}</h3>
                    <button wire:click="$set('showRakModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Gudang *</label>
                        <select wire:model="rakForm.gudang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Gudang...</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->cabang?->nama ?? 'Cabang -' }})</option>
                            @endforeach
                        </select>
                        @error('rakForm.gudang_id') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Rak *</label>
                        <input type="text" wire:model="rakForm.nama" placeholder="misal: Rak Sparepart LCD A1" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('rakForm.nama') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Rak *</label>
                            <input type="text" wire:model="rakForm.kode" placeholder="misal: RAK-A1" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono uppercase" />
                            @error('rakForm.kode') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Zona / Baris</label>
                            <input type="text" wire:model="rakForm.zona" placeholder="misal: Zona A" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('rakForm.zona') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showRakModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveRak" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- [T-16] MODAL: JENIS SERVIS -->
    @if($showJenisServisModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $jenisServisForm['id'] ? 'Edit Jenis Servis' : 'Jenis Servis Baru' }}</h3>
                    <button wire:click="$set('showJenisServisModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="jenisServisForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                            <input type="text" wire:model="jenisServisForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori</label>
                            <select wire:model="jenisServisForm.kategori" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs">
                                <option value="hardware" class="bg-ink-900">Hardware</option>
                                <option value="software" class="bg-ink-900">Software</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Estimasi Durasi (menit)</label>
                            <input type="number" wire:model="jenisServisForm.estimasi_durasi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Durasi Garansi (hari)</label>
                            <input type="number" wire:model="jenisServisForm.durasi_garansi_hari" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tarif Biaya Jasa Standar (Rp)</label>
                        <input type="text" inputmode="numeric" x-format-number wire:model="jenisServisForm.biaya_jasa" placeholder="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        <p class="text-[10px] text-ink-400 mt-1">Tarif acuan otomatis saat memilih jasa ini pada estimasi servis.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="jenisServisForm.butuh_part" class="accent-up-mint w-4 h-4" /> Butuh part
                        </label>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="jenisServisForm.is_part_original" class="accent-up-accent w-4 h-4" /> Part original
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showJenisServisModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanJenisServis" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KATEGORI PRODUK -->
    @if($showKategoriModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $kategoriForm['id'] ? 'Edit Kategori Produk' : 'Kategori Produk Baru' }}</h3>
                    <button wire:click="$set('showKategoriModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Kategori *</label>
                        <input type="text" wire:model="kategoriForm.nama" placeholder="cth: Layar & LCD, Baterai..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('kategoriForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori Induk (Parent)</label>
                        <select wire:model="kategoriForm.parent_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">— Jadikan Kategori Utama (Tanpa Induk) —</option>
                            @foreach($kategoriParentOptions as $p)
                                @if(!$kategoriForm['id'] || $p->id != $kategoriForm['id'])
                                    <option value="{{ $p->id }}" class="bg-ink-900 font-bold text-white">{{ $p->nama }}</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="text-[10px] text-ink-500 mt-1">Pilih kategori induk jika ingin membuat sub-kategori berjenjang.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Icon / Emoji</label>
                            <input type="text" wire:model="kategoriForm.icon" placeholder="📱 / 🔋 / 🔌" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Urutan Tampil</label>
                            <input type="number" wire:model="kategoriForm.urutan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs tabular-nums" />
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="kategoriForm.is_active" class="accent-up-mint w-4 h-4" /> Kategori Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKategoriModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanKategori" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Kategori</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BRAND PART -->
    @if($showBrandModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $brandForm['id'] ? 'Edit Brand Part' : 'Brand Part Baru' }}</h3>
                    <button wire:click="$set('showBrandModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Brand Part *</label>
                        <input type="text" wire:model="brandForm.nama" placeholder="cth: Olike, Hippo, Vizz, Shinetsu, Original Equipment..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('brandForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        <p class="text-[10px] text-ink-400 mt-1">Merek pabrikan suku cadang/sparepart. Untuk merek HP (Apple, Samsung, dll) dikelola di tab Tipe HP.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan (Opsional)</label>
                        <textarea wire:model="brandForm.keterangan" rows="2" placeholder="cth: Distributor resmi baterai & kabel garansi 6 bulan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="brandForm.is_active" class="accent-up-mint w-4 h-4" /> Brand Part Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showBrandModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanBrand" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Brand Part</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KUALITAS PRODUK -->
    @if($showKualitasModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $kualitasForm['id'] ? 'Edit Tingkat Kualitas' : 'Tingkat Kualitas Baru' }}</h3>
                    <button wire:click="$set('showKualitasModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Tingkat Kualitas *</label>
                        <input type="text" wire:model="kualitasForm.nama" placeholder="cth: Original Pabrik, Grade A+, Disassembled..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('kualitasForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Brand Part Induk (Opsional / Universal)</label>
                        <select wire:model="kualitasForm.brand_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium bg-ink-900 text-white">
                            <option value="">— Universal (Dapat Digunakan Semua Brand Part) —</option>
                            @foreach($brandList as $b)
                                <option value="{{ $b->id }}">{{ $b->nama }} (Eksklusif {{ $b->nama }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-ink-500 mt-1">Pilih brand jika grade ini eksklusif milik brand tertentu (cth: Crown Diamond, OG Pro). Kosongkan jika kualitas berlaku universal.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan / Standar Mutu</label>
                        <textarea wire:model="kualitasForm.keterangan" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="kualitasForm.is_active" class="accent-up-mint w-4 h-4" /> Kualitas Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKualitasModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanKualitas" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Kualitas</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: SATUAN UNIT -->
    @if($showSatuanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $satuanForm['id'] ? 'Edit Satuan Unit' : 'Satuan Unit Baru' }}</h3>
                    <button wire:click="$set('showSatuanModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Satuan *</label>
                            <input type="text" wire:model="satuanForm.kode" placeholder="pcs, roll, set, box" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono font-bold" />
                            @error('satuanForm.kode') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Satuan *</label>
                            <input type="text" wire:model="satuanForm.nama" placeholder="Pieces, Roll, Set, Box" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('satuanForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="satuanForm.is_active" class="accent-up-mint w-4 h-4" /> Satuan Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showSatuanModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanSatuan" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Satuan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KONDISI PRODUK -->
    @if($showKondisiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $kondisiForm['is_edit'] ? 'Edit Kondisi Produk' : 'Kondisi Produk Baru (Gaya SID Retail Pro)' }}</h3>
                    <button wire:click="$set('showKondisiModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Label Kondisi *</label>
                        <input type="text" wire:model.live="kondisiForm.nama" placeholder="cth: Baru, OEM, Copotan Original, Rusak Kanibal..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('kondisiForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Indikator Warna Visual Gaya SID Retail Pro -->
                    <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                        <label class="block text-xs font-semibold text-white">Warna Indikator Visual (SID Retail Pro)</label>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach([
                                'emerald' => ['nama' => 'Hijau', 'hex' => '#10b981'],
                                'blue' => ['nama' => 'Biru', 'hex' => '#3b82f6'],
                                'cyan' => ['nama' => 'Cyan', 'hex' => '#06b6d4'],
                                'amber' => ['nama' => 'Amber', 'hex' => '#f59e0b'],
                                'rose' => ['nama' => 'Merah', 'hex' => '#ef4444'],
                                'purple' => ['nama' => 'Ungu', 'hex' => '#a855f7'],
                                'slate' => ['nama' => 'Abu-abu', 'hex' => '#64748b'],
                            ] as $wKey => $wVal)
                                <button
                                    type="button"
                                    wire:click="$set('kondisiForm.warna', '{{ $wKey }}')"
                                    class="p-1.5 rounded-xl border flex flex-col items-center gap-1 transition cursor-pointer {{ ($kondisiForm['warna'] ?? 'emerald') === $wKey ? 'bg-white/15 border-white ring-2 ring-white/30' : 'bg-white/5 border-white/10 hover:bg-white/10' }}"
                                >
                                    <span class="w-4 h-4 rounded-full shadow-sm" style="background-color: {{ $wVal['hex'] }}"></span>
                                    <span class="text-[10px] font-medium text-ink-300">{{ $wVal['nama'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Live Preview Badge -->
                        <div class="pt-2 border-t border-white/5 flex items-center justify-between">
                            <span class="text-[11px] text-ink-400">Preview Tampilan Badge:</span>
                            @php
                                $prevWarna = $kondisiForm['warna'] ?? 'emerald';
                                $prevHex = match($prevWarna) {
                                    'emerald' => '#10b981',
                                    'blue' => '#3b82f6',
                                    'cyan' => '#06b6d4',
                                    'amber' => '#f59e0b',
                                    'rose' => '#ef4444',
                                    'purple' => '#a855f7',
                                    default => '#64748b',
                                };
                                $prevClasses = match($prevWarna) {
                                    'emerald' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                    'blue' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                                    'cyan' => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                                    'amber' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                                    'rose' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                                    'purple' => 'bg-purple-500/15 text-purple-300 border-purple-500/30',
                                    default => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $prevClasses }}">
                                <span class="w-2 h-2 rounded-full inline-block shadow-sm" style="background-color: {{ $prevHex }}"></span>
                                <span>{{ $kondisiForm['nama'] ?: 'Label Kondisi' }}</span>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi / Keterangan Kondisi</label>
                        <input type="text" wire:model="kondisiForm.keterangan" placeholder="cth: Segel Pabrik / Fresh Stock, Part Pihak Ketiga Berkualitas..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>

                    @if(!$kondisiForm['is_edit'])
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Internal (Opsional, auto slug)</label>
                            <input type="text" wire:model="kondisiForm.kode" placeholder="cth: bekas_grade_a" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" />
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Internal</label>
                            <input type="text" value="{{ $kondisiForm['kode'] }}" disabled class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono opacity-50 cursor-not-allowed" />
                        </div>
                    @endif

                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="kondisiForm.is_active" class="accent-up-mint w-4 h-4" /> Kondisi Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKondisiModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanKondisi" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Kondisi</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: TIPE HP -->
    @if($showTipeHpModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>📱</span>
                        <span>{{ $tipeHpForm['id'] ? 'Edit Tipe HP' : 'Tipe HP Baru' }}</span>
                    </h3>
                    <button wire:click="$set('showTipeHpModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3.5">
                    <!-- 1. Merek Smartphone: Dropdown list yang ada + input baru jika belum ada -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-semibold text-white">1. Merek Smartphone *</label>
                            <span class="text-[10px] text-ink-400">Pilih dari list atau ketik baru</span>
                        </div>
                        <div>
                            <select wire:model.live="tipeHpSelectedMerk" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih Merek yang Sudah Ada ({{ count($merekHpOptions) }} merek) —</option>
                                @foreach($merekHpOptions as $m)
                                    <option value="{{ $m }}" class="bg-ink-900">{{ $m }}</option>
                                @endforeach
                                <option value="__custom__" class="bg-ink-900 text-up-mint font-semibold">+ Merek Baru Lainnya (Ketik Baru)...</option>
                            </select>
                        </div>

                        <!-- Kolom Ketik Merek Baru jika belum ada -->
                        @if($tipeHpSelectedMerk === '__custom__' || empty($tipeHpSelectedMerk))
                            <div class="mt-1.5 pt-2 border-t border-white/5 space-y-1">
                                <label class="block text-[11px] text-ink-300">
                                    {{ empty($tipeHpSelectedMerk) ? 'Atau ketik Merek HP baru jika belum ada di atas:' : 'Ketik Merek HP Baru *:' }}
                                </label>
                                <input
                                    type="text"
                                    wire:model="tipeHpForm.merk"
                                    placeholder="cth: Infinix, Tecno, Google Pixel..."
                                    class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"
                                />
                            </div>
                        @else
                            <div class="flex items-center justify-between text-[11px] text-ink-300 px-1 pt-0.5">
                                <span>Merek aktif: <strong class="text-white">{{ $tipeHpForm['merk'] }}</strong></span>
                                <button type="button" wire:click="$set('tipeHpSelectedMerk', '__custom__')" class="text-up-mint hover:underline text-[10px] cursor-pointer">+ Ketik Merek Baru</button>
                            </div>
                        @endif
                        @error('tipeHpForm.merk') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- 2. Tipe / Model HP: Dropdown list yang ada + input baru jika belum ada -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-semibold text-white">2. Model / Seri HP *</label>
                            @if(!empty($existingModelsForSelectedMerk))
                                <span class="text-[10px] text-ink-400">{{ count($existingModelsForSelectedMerk) }} model terdaftar</span>
                            @endif
                        </div>

                        <!-- Dropdown Model yang Sudah Ada untuk Merek ini -->
                        @if(!empty($existingModelsForSelectedMerk))
                            <div>
                                <select wire:model.live="tipeHpSelectedModel" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                    <option value="" class="bg-ink-900">— Pilih Model {{ $tipeHpForm['merk'] ?? '' }} yang Sudah Ada —</option>
                                    @foreach($existingModelsForSelectedMerk as $mod)
                                        <option value="{{ $mod }}" class="bg-ink-900">{{ $mod }}</option>
                                    @endforeach
                                    <option value="__custom__" class="bg-ink-900 text-up-mint font-semibold">+ Model Baru Lainnya (Ketik Baru)...</option>
                                </select>
                            </div>
                        @endif

                        <!-- Kolom Ketik Model Baru jika belum ada di list -->
                        @if(empty($existingModelsForSelectedMerk) || $tipeHpSelectedModel === '__custom__' || empty($tipeHpSelectedModel))
                            <div class="{{ !empty($existingModelsForSelectedMerk) ? 'mt-1.5 pt-2 border-t border-white/5 space-y-1' : 'space-y-1' }}">
                                @if(!empty($existingModelsForSelectedMerk))
                                    <label class="block text-[11px] text-ink-300">
                                        {{ empty($tipeHpSelectedModel) ? 'Atau ketik Model HP baru jika belum ada di atas:' : 'Ketik Model / Seri HP Baru *:' }}
                                    </label>
                                @else
                                    <label class="block text-[11px] text-ink-300">Ketik Model / Seri HP Baru *:</label>
                                @endif
                                <input
                                    type="text"
                                    wire:model="tipeHpForm.model"
                                    placeholder="cth: iPhone 13 Pro, Redmi Note 11, A15s..."
                                    class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"
                                />
                            </div>
                        @else
                            <div class="flex items-center justify-between text-[11px] text-ink-300 px-1 pt-0.5">
                                <span>Model aktif: <strong class="text-white">{{ $tipeHpForm['model'] }}</strong></span>
                                <button type="button" wire:click="$set('tipeHpSelectedModel', '__custom__')" class="text-up-mint hover:underline text-[10px] cursor-pointer">+ Ketik Model Baru</button>
                            </div>
                        @endif
                        @error('tipeHpForm.model') <span class="text-up-red text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- 3. Nama Lengkap (Opsional) -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Lengkap Tampilan (Opsional)</label>
                        <input type="text" wire:model="tipeHpForm.nama" placeholder="Otomatis diisi: [Merk] [Model] jika kosong" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        <p class="text-[10px] text-ink-500 mt-1">Otomatis gabungan: "{{ trim(($tipeHpForm['merk'] ?? '') . ' ' . ($tipeHpForm['model'] ?? '')) }}" jika dikosongkan.</p>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="tipeHpForm.is_active" class="accent-up-mint w-4 h-4" /> Tipe HP Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showTipeHpModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanTipeHp" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Tipe HP</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BATCH EDIT MASTER ATRIBUT -->
    @if($showBatchEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-md max-h-[90vh] flex flex-col glass-panel p-4 sm:p-6 rounded-3xl relative overflow-hidden">
                <div class="flex items-center justify-between pb-3 mb-3 sm:pb-4 sm:mb-4 border-b border-white/10 flex-shrink-0">
                    <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                        <span>✏️</span>
                        <span>Batch Edit Master Atribut ({{ strtoupper($masterProdukSubTab) }})</span>
                    </h3>
                    <button wire:click="$set('showBatchEditModal', false)" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white cursor-pointer transition">✕</button>
                </div>

                <div class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <p class="text-xs text-ink-300">
                        Pilih opsi perubahan yang ingin diterapkan ke semua item terpilih secara massal:
                    </p>

                    <!-- 1. Ubah Status Aktif/Nonaktif -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2.5">
                        <label class="flex items-center gap-2 text-xs font-semibold text-white cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="batchEditForm.ubah_status" class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90" />
                            <span>Ubah Status Aktif / Nonaktif</span>
                        </label>
                        @if($batchEditForm['ubah_status'])
                            <div class="pl-6 pt-1 flex items-center gap-4">
                                <label class="flex items-center gap-2 text-xs text-ink-200 cursor-pointer select-none">
                                    <input type="radio" wire:model="batchEditForm.status" value="1" class="w-4 h-4 accent-up-mint cursor-pointer" />
                                    <span class="text-up-mint font-semibold">Aktif</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-ink-200 cursor-pointer select-none">
                                    <input type="radio" wire:model="batchEditForm.status" value="0" class="w-4 h-4 accent-up-red cursor-pointer" />
                                    <span class="text-ink-400 font-semibold">Nonaktif</span>
                                </label>
                            </div>
                        @endif
                    </div>

                    <!-- 2. Kategori: Ubah Kategori Induk -->
                    @if($masterProdukSubTab === 'kategori')
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2.5">
                            <label class="flex items-center gap-2 text-xs font-semibold text-white cursor-pointer select-none">
                                <input type="checkbox" wire:model.live="batchEditForm.ubah_parent" class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90" />
                                <span>Pindahkan Kategori Induk</span>
                            </label>
                            @if($batchEditForm['ubah_parent'])
                                <div class="pl-6 pt-1">
                                    <select wire:model="batchEditForm.parent_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Jadikan Kategori Utama (Tanpa Induk) —</option>
                                        @foreach($kategoriList as $kp)
                                            @if(!$kp->parent_id)
                                                <option value="{{ $kp->id }}" class="bg-ink-900">{{ $kp->nama }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- 3. Kualitas: Ubah Brand Part Induk -->
                    @if($masterProdukSubTab === 'kualitas')
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2.5">
                            <label class="flex items-center gap-2 text-xs font-semibold text-white cursor-pointer select-none">
                                <input type="checkbox" wire:model.live="batchEditForm.ubah_brand" class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90" />
                                <span>Ubah Brand Part Induk</span>
                            </label>
                            @if($batchEditForm['ubah_brand'])
                                <div class="pl-6 pt-1">
                                    <select wire:model="batchEditForm.brand_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Universal (Semua Brand) —</option>
                                        @foreach($brandList as $bp)
                                            <option value="{{ $bp->id }}" class="bg-ink-900">{{ $bp->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- 4. Tipe HP: Ubah Merek HP -->
                    @if($masterProdukSubTab === 'tipe_hp')
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2.5">
                            <label class="flex items-center gap-2 text-xs font-semibold text-white cursor-pointer select-none">
                                <input type="checkbox" wire:model.live="batchEditForm.ubah_merk" class="w-4.5 h-4.5 rounded border border-white/20 bg-ink-900 text-up-primary accent-up-primary focus:ring-up-primary/40 cursor-pointer inline-block align-middle transition-transform active:scale-90" />
                                <span>Ubah Merek Perangkat Smartphone</span>
                            </label>
                            @if($batchEditForm['ubah_merk'])
                                <div class="pl-6 pt-1 space-y-2">
                                    <select wire:model="batchEditForm.merk" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="" class="bg-ink-900">— Pilih Merek —</option>
                                        @foreach($merekHpOptions as $m)
                                            <option value="{{ $m }}" class="bg-ink-900">{{ $m }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="batchEditForm.merk" placeholder="Atau ketik merek baru..." class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium" />
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex gap-2.5 sm:gap-3 pt-3 sm:pt-4 border-t border-white/5 mt-4 sm:mt-5 flex-shrink-0">
                    <button wire:click="$set('showBatchEditModal', false)" class="flex-1 py-2.5 sm:py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer transition active:scale-95">Batal</button>
                    <button wire:click="simpanBatchEdit" class="flex-1 py-2.5 sm:py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer transition active:scale-95 shadow-md shadow-up-primary/20">Terapkan Perubahan</button>
                </div>
            </div>
        </div>
    @endif
</div>
