<!-- Modal Actions Kasir Offline (PRD Fase 2 & Resiliensi Offline) -->
<div>
    <!-- 1. MODAL TRANSAKSI KASIR OFFLINE -->
    <div
        x-show="$store.posOffline.modalSaleOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @keydown.escape.window="$store.posOffline.modalSaleOpen = false"
    >
        <div
            @click.outside="$store.posOffline.modalSaleOpen = false"
            class="bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden"
        >
            <div class="px-5 py-3.5 border-b border-ink-100 dark:border-white/10 flex items-center justify-between bg-amber-500/10">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                    <h3 class="font-bold text-sm text-ink-900 dark:text-white">Transaksi Kasir Offline (Darurat)</h3>
                </div>
                <button
                    type="button"
                    @click="$store.posOffline.modalSaleOpen = false"
                    class="p-1 rounded-lg hover:bg-black/10 dark:hover:bg-white/10 text-ink-500 text-xs"
                >
                    ✕
                </button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4 flex-1">
                <!-- Search Box -->
                <div>
                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Cari Produk (Katalog Offline)</label>
                    <input
                        type="text"
                        x-model="$store.posOffline.saleSearchTerm"
                        @input.debounce.300ms="$store.posOffline.searchSaleProducts()"
                        placeholder="Ketik nama produk, SKU, barcode..."
                        class="w-full px-3.5 py-2 text-xs rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                    />
                </div>

                <!-- Hasil Pencarian Produk -->
                <div class="max-h-40 overflow-y-auto space-y-1.5 border border-ink-100 dark:border-white/5 rounded-xl p-2 bg-ink-50/50 dark:bg-white/[0.02]">
                    <template x-if="$store.posOffline.saleSearchResults.length === 0">
                        <div class="text-center py-2 text-xs text-ink-400">Tidak ada produk ditemukan di memori lokal.</div>
                    </template>
                    <template x-for="p in $store.posOffline.saleSearchResults" :key="p.id">
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-ink-800 border border-ink-100 dark:border-white/5 text-xs">
                            <div class="min-w-0 pr-2">
                                <div class="font-bold text-ink-900 dark:text-white truncate" x-text="p.nama"></div>
                                <div class="text-[11px] text-ink-400">
                                    <span x-text="p.kode || p.sku"></span> • Rp <span x-text="Number(p.harga_retail || 0).toLocaleString('id-ID')"></span> • Stok: <span x-text="p.stok_terakhir ?? 0" class="font-semibold text-ink-700 dark:text-ink-200"></span>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="$store.posOffline.addToCart(p, 1)"
                                class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-[11px] shrink-0"
                            >
                                + Keranjang
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Keranjang Offline -->
                <div>
                    <h4 class="font-bold text-xs text-ink-800 dark:text-ink-200 mb-2">Keranjang Belanja Offline (<span x-text="$store.posOffline.cartCount"></span>)</h4>
                    <div class="border border-ink-100 dark:border-white/10 rounded-xl divide-y divide-ink-100 dark:divide-white/5 max-h-48 overflow-y-auto">
                        <template x-if="$store.posOffline.cartCount === 0">
                            <div class="text-center py-4 text-xs text-ink-400">Keranjang masih kosong.</div>
                        </template>
                        <template x-for="item in Object.values($store.posOffline.cart)" :key="item.produk.id">
                            <div class="p-2.5 flex items-center justify-between text-xs">
                                <div class="min-w-0 flex-1 pr-2">
                                    <div class="font-semibold text-ink-900 dark:text-white truncate" x-text="item.produk.nama"></div>
                                    <div class="text-[11px] text-ink-400">
                                        Rp <span x-text="Number(item.harga).toLocaleString('id-ID')"></span> x <span x-text="item.qty"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center border border-ink-200 dark:border-white/10 rounded-lg">
                                        <button
                                            type="button"
                                            @click="$store.posOffline.updateCartQty(item.produk.id, item.qty - 1)"
                                            class="px-2 py-0.5 hover:bg-black/5 text-ink-700 dark:text-white font-bold"
                                        >-</button>
                                        <span class="px-2 py-0.5 font-bold" x-text="item.qty"></span>
                                        <button
                                            type="button"
                                            @click="$store.posOffline.updateCartQty(item.produk.id, item.qty + 1)"
                                            class="px-2 py-0.5 hover:bg-black/5 text-ink-700 dark:text-white font-bold"
                                        >+</button>
                                    </div>
                                    <span class="font-bold tabular-nums text-ink-900 dark:text-white w-20 text-right">
                                        Rp <span x-text="Number(item.subtotal).toLocaleString('id-ID')"></span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Ringkasan & Pembayaran -->
                <div class="bg-ink-50 dark:bg-white/[0.03] p-3.5 rounded-xl border border-ink-100 dark:border-white/10 space-y-2 text-xs">
                    <div class="flex justify-between items-center text-sm font-bold">
                        <span>Total Belanja:</span>
                        <span class="text-amber-600 dark:text-amber-400 tabular-nums">
                            Rp <span x-text="Number($store.posOffline.cartTotal).toLocaleString('id-ID')"></span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-4 pt-1">
                        <label class="font-semibold">Uang Tunai Diterima:</label>
                        <input
                            type="number"
                            x-model.number="$store.posOffline.cashReceived"
                            placeholder="0"
                            class="w-36 px-3 py-1.5 text-right font-bold rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white"
                        />
                    </div>
                    <div class="flex justify-between items-center text-xs font-semibold pt-1 border-t border-ink-200/50 dark:border-white/5">
                        <span>Kembalian:</span>
                        <span class="text-emerald-600 dark:text-emerald-400 tabular-nums font-bold">
                            Rp <span x-text="Number($store.posOffline.change).toLocaleString('id-ID')"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/[0.02] flex items-center justify-between">
                <button
                    type="button"
                    @click="$store.posOffline.clearCart()"
                    class="text-xs text-rose-500 font-semibold hover:underline"
                >
                    Kosongkan
                </button>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="$store.posOffline.modalSaleOpen = false"
                        class="px-3.5 py-1.5 rounded-xl bg-ink-200 dark:bg-white/10 text-xs font-bold"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="$store.posOffline.submitSaleCheckout()"
                        :disabled="$store.posOffline.cartCount === 0 || $store.posOffline.cashReceived < $store.posOffline.cartTotal"
                        class="px-4 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-amber-500/20"
                    >
                        Simpan Transaksi & Cetak
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MODAL PENGAMBILAN SERVIS OFFLINE -->
    <div
        x-show="$store.posOffline.modalPickupOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @keydown.escape.window="$store.posOffline.modalPickupOpen = false"
    >
        <div
            @click.outside="$store.posOffline.modalPickupOpen = false"
            class="bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 rounded-2xl w-full max-w-lg max-h-[90vh] flex flex-col shadow-2xl overflow-hidden"
        >
            <div class="px-5 py-3.5 border-b border-ink-100 dark:border-white/10 flex items-center justify-between bg-blue-500/10">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                    <h3 class="font-bold text-sm text-ink-900 dark:text-white">Ambil Unit Servis Selesai (Offline)</h3>
                </div>
                <button
                    type="button"
                    @click="$store.posOffline.modalPickupOpen = false"
                    class="p-1 rounded-lg hover:bg-black/10 dark:hover:bg-white/10 text-ink-500 text-xs"
                >
                    ✕
                </button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4 flex-1 text-xs">
                <div>
                    <label class="block font-semibold text-ink-700 dark:text-ink-300 mb-1">Cari Tiket Siap Ambil</label>
                    <input
                        type="text"
                        x-model="$store.posOffline.pickupSearchTerm"
                        @input.debounce.300ms="$store.posOffline.searchPickupTickets()"
                        placeholder="No Tiket, Nama Pelanggan, No WA..."
                        class="w-full px-3.5 py-2 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white"
                    />
                </div>

                <!-- Daftar Tiket -->
                <div class="border border-ink-100 dark:border-white/10 rounded-xl divide-y divide-ink-100 dark:divide-white/5 max-h-40 overflow-y-auto">
                    <template x-if="$store.posOffline.pickupSearchResults.length === 0">
                        <div class="text-center py-4 text-ink-400">Tidak ada tiket servis siap ambil yang tersimpan di cache.</div>
                    </template>
                    <template x-for="t in $store.posOffline.pickupSearchResults" :key="t.tiket_id || t.no_tiket">
                        <div
                            @click="$store.posOffline.selectPickupTicket(t)"
                            class="p-2.5 cursor-pointer hover:bg-blue-50/50 dark:hover:bg-blue-500/10 transition"
                            :class="$store.posOffline.selectedPickupTicket?.no_tiket === t.no_tiket ? 'bg-blue-50 dark:bg-blue-500/20 border-l-4 border-blue-500' : ''"
                        >
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-ink-900 dark:text-white" x-text="t.no_tiket"></span>
                                <span class="font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                    Sisa: Rp <span x-text="Number(t.sisa_tagihan || 0).toLocaleString('id-ID')"></span>
                                </span>
                            </div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">
                                <span x-text="t.nama_pelanggan"></span> • <span x-text="t.tipe_gadget || (t.jenis_hp + ' ' + (t.seri_hp || ''))"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Form Penyerahan -->
                <template x-if="$store.posOffline.selectedPickupTicket">
                    <div class="space-y-3 bg-ink-50 dark:bg-white/[0.03] p-3.5 rounded-xl border border-ink-100 dark:border-white/10">
                        <div class="font-bold text-ink-900 dark:text-white">
                            Tiket Terpilih: <span class="text-blue-600 dark:text-blue-400" x-text="$store.posOffline.selectedPickupTicket.no_tiket"></span>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Nama Pengambil Unit *</label>
                            <input
                                type="text"
                                x-model="$store.posOffline.pickupNamaPengambil"
                                class="w-full px-3 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Nominal Pelunasan Tunai (Rp)</label>
                            <input
                                type="number"
                                x-model.number="$store.posOffline.pickupNominalBayar"
                                class="w-full px-3 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white font-bold"
                            />
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-5 py-3 border-t border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/[0.02] flex items-center justify-end gap-2">
                <button
                    type="button"
                    @click="$store.posOffline.modalPickupOpen = false"
                    class="px-3.5 py-1.5 rounded-xl bg-ink-200 dark:bg-white/10 text-xs font-bold"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="$store.posOffline.submitPickup()"
                    :disabled="!$store.posOffline.selectedPickupTicket"
                    class="px-4 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-blue-500/20"
                >
                    Konfirmasi Penyerahan Unit
                </button>
            </div>
        </div>
    </div>

    <!-- 3. MODAL RETUR TUKAR UNIT OFFLINE -->
    <div
        x-show="$store.posOffline.modalReturOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @keydown.escape.window="$store.posOffline.modalReturOpen = false"
    >
        <div
            @click.outside="$store.posOffline.modalReturOpen = false"
            class="bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 rounded-2xl w-full max-w-lg max-h-[90vh] flex flex-col shadow-2xl overflow-hidden"
        >
            <div class="px-5 py-3.5 border-b border-ink-100 dark:border-white/10 flex items-center justify-between bg-emerald-500/10">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <h3 class="font-bold text-sm text-ink-900 dark:text-white">Retur Tukar Unit 1-to-1 (Offline)</h3>
                </div>
                <button
                    type="button"
                    @click="$store.posOffline.modalReturOpen = false"
                    class="p-1 rounded-lg hover:bg-black/10 dark:hover:bg-white/10 text-ink-500 text-xs"
                >
                    ✕
                </button>
            </div>

            <div class="p-5 overflow-y-auto space-y-3 flex-1 text-xs">
                <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-[11px]">
                    <strong>Kebijakan Anti-Fraud:</strong> Saat offline, sistem hanya mengizinkan <em>Tukar Unit 1-to-1</em>. Pengembalian uang tunai (Refund) dilarang sampai server kembali online.
                </div>

                <div>
                    <label class="block font-semibold mb-1">Nomor Transaksi / Nota Asal *</label>
                    <input
                        type="text"
                        x-model="$store.posOffline.returNotaAsal"
                        placeholder="Contoh: TRX-C01-20261110-0001"
                        class="w-full px-3 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white"
                    />
                </div>

                <div>
                    <label class="block font-semibold mb-1">Alasan Retur</label>
                    <input
                        type="text"
                        x-model="$store.posOffline.returAlasan"
                        class="w-full px-3 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800 text-ink-900 dark:text-white"
                    />
                </div>

                <!-- 1. Pilih Produk Diretur -->
                <div class="p-3 bg-ink-50 dark:bg-white/[0.03] rounded-xl border border-ink-100 dark:border-white/10 space-y-2">
                    <label class="block font-bold">1. Produk Yang Dikembalikan (Rusak)</label>
                    <input
                        type="text"
                        x-model="$store.posOffline.returItemSearchTerm"
                        @input.debounce.300ms="$store.posOffline.searchReturItem()"
                        placeholder="Cari produk rusak di katalog lokal..."
                        class="w-full px-2.5 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800"
                    />
                    <div class="max-h-24 overflow-y-auto divide-y divide-ink-100 dark:divide-white/5 border border-ink-100 dark:border-white/5 rounded-lg">
                        <template x-for="p in $store.posOffline.returItemResults" :key="p.id">
                            <div
                                @click="$store.posOffline.selectedReturItem = p"
                                class="p-1.5 cursor-pointer hover:bg-emerald-50 dark:hover:bg-white/5 flex justify-between"
                                :class="$store.posOffline.selectedReturItem?.id === p.id ? 'bg-emerald-100/50 dark:bg-emerald-500/20 font-bold' : ''"
                            >
                                <span x-text="p.nama" class="truncate pr-2"></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold" x-show="$store.posOffline.selectedReturItem?.id === p.id">✓</span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- 2. Pilih Produk Pengganti -->
                <div class="p-3 bg-ink-50 dark:bg-white/[0.03] rounded-xl border border-ink-100 dark:border-white/10 space-y-2">
                    <label class="block font-bold">2. Produk Pengganti (Tukar Unit)</label>
                    <input
                        type="text"
                        x-model="$store.posOffline.returSwapSearchTerm"
                        @input.debounce.300ms="$store.posOffline.searchSwapItem()"
                        placeholder="Cari unit pengganti di katalog lokal..."
                        class="w-full px-2.5 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-800"
                    />
                    <div class="max-h-24 overflow-y-auto divide-y divide-ink-100 dark:divide-white/5 border border-ink-100 dark:border-white/5 rounded-lg">
                        <template x-for="p in $store.posOffline.returSwapResults" :key="p.id">
                            <div
                                @click="$store.posOffline.selectedSwapItem = p"
                                class="p-1.5 cursor-pointer hover:bg-emerald-50 dark:hover:bg-white/5 flex justify-between"
                                :class="$store.posOffline.selectedSwapItem?.id === p.id ? 'bg-emerald-100/50 dark:bg-emerald-500/20 font-bold' : ''"
                            >
                                <span x-text="p.nama" class="truncate pr-2"></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold" x-show="$store.posOffline.selectedSwapItem?.id === p.id">✓</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/[0.02] flex items-center justify-end gap-2">
                <button
                    type="button"
                    @click="$store.posOffline.modalReturOpen = false"
                    class="px-3.5 py-1.5 rounded-xl bg-ink-200 dark:bg-white/10 text-xs font-bold"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="$store.posOffline.submitRetur()"
                    :disabled="!$store.posOffline.returNotaAsal || !$store.posOffline.selectedReturItem || !$store.posOffline.selectedSwapItem"
                    class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-emerald-500/20"
                >
                    Simpan Retur & Cetak Bukti
                </button>
            </div>
        </div>
    </div>
</div>
