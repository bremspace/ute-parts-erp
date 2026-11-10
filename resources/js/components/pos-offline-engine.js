/**
 * pos-offline-engine.js — Alpine.js Store & Offline Sync Background Worker
 * PRD: PRD-OFFLINE-01 (T-OFFLINE-07, T-OFFLINE-08, T-OFFLINE-09, T-OFFLINE-10)
 */

import { UteOfflineDB } from './offline-db.js';

function generateUuidV7() {
    // Generate UUID v7 RFC 9562
    const now = Date.now();
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);

    // Timestamp 48-bit
    bytes[0] = (now / 0x10000000000) & 0xff;
    bytes[1] = (now / 0x100000000) & 0xff;
    bytes[2] = (now / 0x1000000) & 0xff;
    bytes[3] = (now / 0x10000) & 0xff;
    bytes[4] = (now / 0x100) & 0xff;
    bytes[5] = now & 0xff;

    // Version 7
    bytes[6] = (bytes[6] & 0x0f) | 0x70;
    // Variant RFC 4122
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function initPosOfflineStore() {
    if (typeof window === 'undefined' || !window.Alpine) return;

    window.Alpine.store('posOffline', {
        isOnline: navigator.onLine,
        isSyncing: false,
        authExpired: false,
        authErrorMessage: '',
        pendingCount: 0,
        outboxItems: [],
        drawerOpen: false,
        cart: {}, // Format { produkId: { produk, qty, harga, subtotal } }
        cashReceived: 0,

        // Modal Offline Actions
        modalSaleOpen: false,
        modalPickupOpen: false,
        modalReturOpen: false,
        saleSearchTerm: '',
        saleSearchResults: [],
        pickupSearchTerm: '',
        pickupSearchResults: [],
        selectedPickupTicket: null,
        pickupNamaPengambil: '',
        pickupNominalBayar: 0,
        returNotaAsal: '',
        returAlasan: 'Tukar unit 1-to-1 offline',
        returItemSearchTerm: '',
        returItemResults: [],
        selectedReturItem: null,
        returSwapSearchTerm: '',
        returSwapResults: [],
        selectedSwapItem: null,

        openSaleModal() {
            this.modalSaleOpen = true;
            this.saleSearchTerm = '';
            this.saleSearchResults = [];
            if (window.UteOfflineDB?.searchProduk) {
                window.UteOfflineDB.searchProduk('', 10).then((res) => {
                    this.saleSearchResults = res || [];
                });
            }
        },

        openPickupModal() {
            this.modalPickupOpen = true;
            this.pickupSearchTerm = '';
            this.pickupSearchResults = [];
            this.selectedPickupTicket = null;
            this.pickupNamaPengambil = '';
            this.pickupNominalBayar = 0;
            this.searchPickupTickets();
        },

        openReturModal() {
            this.modalReturOpen = true;
            this.returNotaAsal = '';
            this.returAlasan = 'Tukar unit 1-to-1 offline';
            this.returItemSearchTerm = '';
            this.returItemResults = [];
            this.selectedReturItem = null;
            this.returSwapSearchTerm = '';
            this.returSwapResults = [];
            this.selectedSwapItem = null;
        },

        closeModals() {
            this.modalSaleOpen = false;
            this.modalPickupOpen = false;
            this.modalReturOpen = false;
        },

        async searchSaleProducts() {
            if (!window.UteOfflineDB?.searchProduk) return;
            this.saleSearchResults = await window.UteOfflineDB.searchProduk(this.saleSearchTerm, 20);
        },

        async searchPickupTickets() {
            this.pickupSearchResults = await this.searchServisActive(this.pickupSearchTerm);
        },

        selectPickupTicket(ticket) {
            this.selectedPickupTicket = ticket;
            this.pickupNamaPengambil = ticket.nama_pelanggan || '';
            this.pickupNominalBayar = Number(ticket.sisa_tagihan || 0);
        },

        async searchReturItem() {
            if (!window.UteOfflineDB?.searchProduk) return;
            this.returItemResults = await window.UteOfflineDB.searchProduk(this.returItemSearchTerm, 15);
        },

        async searchSwapItem() {
            if (!window.UteOfflineDB?.searchProduk) return;
            this.returSwapResults = await window.UteOfflineDB.searchProduk(this.returSwapSearchTerm, 15);
        },

        async submitSaleCheckout() {
            const ok = await this.processOfflineCheckout({
                nama_toko: window.ACTIVE_CABANG_NAME || window.APP_NAME,
                cabang_nama: window.ACTIVE_CABANG_NAME,
                kasir_nama: window.CURRENT_USER_NAME,
                cabang_id: window.ACTIVE_CABANG_ID,
                user_id: window.CURRENT_USER_ID,
            });
            if (ok) {
                this.modalSaleOpen = false;
                this.clearCart();
                await this.refreshOutbox();
                alert('Transaksi kasir offline berhasil disimpan ke outbox!');
            }
        },

        async submitPickup() {
            if (!this.selectedPickupTicket) {
                alert('Pilih tiket servis terlebih dahulu.');
                return;
            }
            if (!this.pickupNamaPengambil.trim()) {
                alert('Nama pengambil unit wajib diisi.');
                return;
            }
            try {
                await this.processOfflineServisPickup({
                    no_tiket: this.selectedPickupTicket.no_tiket,
                    nama_pengambil: this.pickupNamaPengambil.trim(),
                    nominal_pelunasan: Number(this.pickupNominalBayar) || 0,
                    metode_pembayaran: 'tunai',
                    catatan: `Pelunasan offline tiket ${this.selectedPickupTicket.no_tiket}`,
                });
                this.modalPickupOpen = false;
                await this.refreshOutbox();
                alert(`Pengambilan servis ${this.selectedPickupTicket.no_tiket} berhasil disimpan ke outbox!`);
            } catch (err) {
                alert(err.message || 'Gagal memproses pengambilan unit');
            }
        },

        async submitRetur() {
            if (!this.returNotaAsal.trim()) {
                alert('Nomor transaksi / nota asal wajib diisi.');
                return;
            }
            if (!this.selectedReturItem) {
                alert('Pilih produk yang diretur.');
                return;
            }
            if (!this.selectedSwapItem) {
                alert('Pilih produk unit pengganti.');
                return;
            }
            try {
                await this.processOfflineRetur({
                    no_transaksi_asal: this.returNotaAsal.trim(),
                    alasan: this.returAlasan.trim() || 'Tukar unit 1-to-1 offline',
                    tipe_retur: 'tukar_unit',
                    items: [
                        {
                            produk_id: this.selectedReturItem.id,
                            nama: this.selectedReturItem.nama,
                            qty: 1,
                            kondisi_barang: 'rusak',
                            produk_pengganti_id: this.selectedSwapItem.id,
                            qty_pengganti: 1,
                        },
                    ],
                });
                this.modalReturOpen = false;
                await this.refreshOutbox();
                alert('Retur tukar barang berhasil disimpan ke outbox!');
            } catch (err) {
                alert(err.message || 'Gagal memproses retur');
            }
        },

        async init() {
            await this.refreshOutbox();

            // Prune outbox lama yang sudah tersinkron (> 7 hari)
            if (window.UteOfflineDB?.pruneAllSyncedOutbox) {
                window.UteOfflineDB.pruneAllSyncedOutbox(7).catch(() => {});
            }

            // Background pre-fetch snapshot servis jika online
            if (navigator.onLine && window.UteOfflineDB?.syncServisActiveFromServer) {
                window.UteOfflineDB.syncServisActiveFromServer().catch(() => {});
            }

            window.addEventListener('online', () => {
                this.isOnline = true;
                if (window.UteOfflineDB?.syncServisActiveFromServer) {
                    window.UteOfflineDB.syncServisActiveFromServer().catch(() => {});
                }
                this.triggerAutoSync();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            // Heartbeat check berkala jika online
            setInterval(() => {
                if (navigator.onLine && this.pendingCount > 0 && !this.isSyncing) {
                    this.triggerAutoSync();
                }
            }, 30000);
        },

        async refreshOutbox() {
            if (!window.UteOfflineDB) return;
            const allPos = (await window.UteOfflineDB.getAllOutbox?.()) || [];
            const allPickups = (await window.UteOfflineDB.getAllServisPickups?.()) || [];
            const allReturs = (await window.UteOfflineDB.getAllReturOutbox?.()) || [];

            const formattedPos = allPos.map((p) => ({
                ...p,
                entry_type: 'pos',
                item_type: 'pos',
                display_title: p.server_no_transaksi || `TRX-${p.offline_uuid.slice(0, 8).toUpperCase()}`,
                display_subtitle: `${p.items ? p.items.length : 0} produk • Tunai Rp ${Number(p.total_nominal || 0).toLocaleString('id-ID')}`,
            }));

            const formattedPickups = allPickups.map((s) => ({
                ...s,
                entry_type: 'servis_pickup',
                item_type: 'servis_pickup',
                display_title: `SERVIS: ${s.no_tiket}`,
                display_subtitle: `Diambil: ${s.nama_pengambil} • Pelunasan Rp ${Number(s.nominal_pelunasan || 0).toLocaleString('id-ID')}`,
            }));

            const formattedReturs = allReturs.map((r) => ({
                ...r,
                entry_type: 'retur',
                item_type: 'retur',
                display_title: `RETUR: ${r.no_transaksi_asal}`,
                display_subtitle: `Tukar Unit: ${r.alasan || 'Kerusakan komponen'}`,
            }));

            const combined = [...formattedPos, ...formattedPickups, ...formattedReturs];
            this.outboxItems = combined.sort((a, b) => new Date(b.client_created_at) - new Date(a.client_created_at));

            const pendingPosCount = allPos.filter((x) => x.status === 'pending' || x.status === 'failed').length;
            const pendingPickupCount = allPickups.filter((x) => x.status === 'pending' || x.status === 'failed').length;
            const pendingReturCount = allReturs.filter((x) => x.status === 'pending' || x.status === 'failed').length;

            this.pendingCount = pendingPosCount + pendingPickupCount + pendingReturCount;
        },

        toggleDrawer() {
            this.drawerOpen = !this.drawerOpen;
            if (this.drawerOpen) {
                this.refreshOutbox();
            }
        },

        // ================= Keranjang Offline =================
        addToCart(produk, qty = 1) {
            const id = produk.id;
            const harga = produk.harga_retail || 0;

            if (this.cart[id]) {
                this.cart[id].qty += qty;
                this.cart[id].subtotal = this.cart[id].qty * harga;
            } else {
                this.cart[id] = {
                    produk,
                    qty,
                    harga,
                    subtotal: qty * harga,
                };
            }
        },

        removeFromCart(id) {
            delete this.cart[id];
        },

        updateCartQty(id, qty) {
            if (qty <= 0) {
                this.removeFromCart(id);
                return;
            }
            if (this.cart[id]) {
                this.cart[id].qty = qty;
                this.cart[id].subtotal = qty * this.cart[id].harga;
            }
        },

        clearCart() {
            this.cart = {};
            this.cashReceived = 0;
        },

        get cartTotal() {
            return Object.values(this.cart).reduce((sum, it) => sum + (it.subtotal || 0), 0);
        },

        get cartCount() {
            return Object.values(this.cart).reduce((sum, it) => sum + (it.qty || 0), 0);
        },

        get change() {
            return Math.max(0, (this.cashReceived || 0) - this.cartTotal);
        },

        // ================= Checkout Offline =================
        async processOfflineCheckout(metadata = {}) {
            if (this.cartCount === 0) {
                alert('Keranjang belanja kosong');
                return false;
            }

            const total = this.cartTotal;
            const received = Number(this.cashReceived) || total;

            if (received < total) {
                alert('Nominal uang diterima kurang dari total belanja.');
                return false;
            }

            const uuid = generateUuidV7();
            const items = Object.values(this.cart).map((it) => ({
                produk_id: it.produk.id,
                nama: it.produk.nama,
                qty: it.qty,
                harga_satuan: it.harga,
                subtotal: it.subtotal,
            }));

            const txPayload = {
                offline_uuid: uuid,
                cabang_id: metadata.cabang_id || window.ACTIVE_CABANG_ID || 1,
                user_id: metadata.user_id || window.CURRENT_USER_ID || 1,
                client_created_at: new Date().toISOString(),
                metode_bayar: 'tunai',
                total_nominal: total,
                uang_diterima: received,
                kembalian: received - total,
                items,
                status: 'pending',
                catatan: 'Transaksi kasir offline mandiri',
            };

            await window.UteOfflineDB.putOutbox(txPayload);

            // Potong stok lokal di IndexedDB master_produk agar sisa stok langsung ter-update
            if (window.UteOfflineDB?.decrementLocalStock) {
                window.UteOfflineDB.decrementLocalStock(items).catch(() => {});
            }

            // Cetak Struk Bluetooth Langsung
            if (window.UteThermalPrint) {
                const receiptData = {
                    nama_toko: metadata.nama_toko || window.APP_NAME || 'Ute Parts Store',
                    cabang: metadata.cabang_nama || 'Cabang POS',
                    no_transaksi: `OFFLINE-${uuid.slice(0, 8).toUpperCase()}`,
                    waktu: new Date().toLocaleString('id-ID'),
                    kasir: metadata.kasir_nama || 'Kasir Offline',
                    pelanggan: 'Pelanggan Umum',
                    is_offline: true,
                    items: items.map((i) => ({
                        nama: i.nama,
                        qty: i.qty,
                        harga: i.harga_satuan,
                        subtotal: i.subtotal,
                    })),
                    subtotal: total,
                    total_akhir: total,
                    bayar: received,
                    kembali: received - total,
                    metode_bayar: 'TUNAI',
                };

                try {
                    await window.UteThermalPrint.printReceipt(receiptData);
                } catch (e) {
                    console.warn('[PosOffline] Gagal cetak otomatis ESC/POS:', e);
                }
            }

            this.clearCart();
            await this.refreshOutbox();

            // Trigger sync jika ada koneksi
            if (navigator.onLine) {
                this.triggerAutoSync();
            }

            return true;
        },

        // ================= Servis Offline Handover =================
        async searchServisActive(query = '') {
            if (!window.UteOfflineDB?.getAllServisActiveCache) return [];
            const tickets = await window.UteOfflineDB.getAllServisActiveCache();

            return tickets.filter((t) => {
                if (t.status === 'diambil' || t.diambil_offline) return false;
                if (!query.trim()) return true;

                const q = query.toLowerCase().trim();
                return (
                    (t.no_tiket && t.no_tiket.toLowerCase().includes(q)) ||
                    (t.nama_pelanggan && t.nama_pelanggan.toLowerCase().includes(q)) ||
                    (t.telepon_pelanggan && t.telepon_pelanggan.includes(q)) ||
                    (t.tipe_gadget && t.tipe_gadget.toLowerCase().includes(q)) ||
                    (t.jenis_hp && t.jenis_hp.toLowerCase().includes(q)) ||
                    (t.seri_hp && t.seri_hp.toLowerCase().includes(q))
                );
            });
        },

        async processOfflineServisPickup(pickupData = {}) {
            const {
                no_tiket,
                nama_pengambil,
                nominal_pelunasan = 0,
                metode_pembayaran = 'tunai',
                catatan = '',
            } = pickupData;

            if (!no_tiket) {
                throw new Error('Nomor tiket servis wajib diisi.');
            }
            if (!nama_pengambil) {
                throw new Error('Nama pengambil unit wajib diisi.');
            }

            const uuid = generateUuidV7();
            const record = {
                pickup_uuid: uuid,
                no_tiket,
                nama_pengambil,
                nominal_pelunasan: Number(nominal_pelunasan) || 0,
                metode_pembayaran,
                catatan,
                cabang_id: window.ACTIVE_CABANG_ID || 1,
                user_id: window.CURRENT_USER_ID || 1,
                client_created_at: new Date().toISOString(),
                status: 'pending',
            };

            await window.UteOfflineDB.saveServisPickup(record);

            // Hapus tiket dari cache servis aktif lokal agar tidak bisa diserahkan ganda
            if (window.UteOfflineDB?.removeServisActiveCache) {
                await window.UteOfflineDB.removeServisActiveCache(no_tiket);
            }

            // ESC/POS Thermal Print jika ada printer Bluetooth
            if (window.UteThermalPrint) {
                try {
                    await window.UteThermalPrint.printReceipt({
                        nama_toko: window.APP_NAME || 'Ute Parts Store',
                        cabang: 'Cabang POS Servis',
                        no_transaksi: `PICKUP-${no_tiket}`,
                        waktu: new Date().toLocaleString('id-ID'),
                        kasir: 'Kasir Offline',
                        pelanggan: nama_pengambil,
                        is_offline: true,
                        items: [
                            {
                                nama: `Pelunasan Servis [${no_tiket}]`,
                                qty: 1,
                                harga: Number(nominal_pelunasan) || 0,
                                subtotal: Number(nominal_pelunasan) || 0,
                            },
                        ],
                        total_akhir: Number(nominal_pelunasan) || 0,
                        bayar: Number(nominal_pelunasan) || 0,
                        kembali: 0,
                        metode_bayar: (metode_pembayaran || 'TUNAI').toUpperCase(),
                    });
                } catch (e) {
                    console.warn('[OfflinePickup] Gagal cetak nota serah terima:', e);
                }
            }

            await this.refreshOutbox();

            if (navigator.onLine) {
                this.triggerAutoSync();
            }

            return { success: true, pickup_uuid: uuid, no_tiket };
        },

        // ================= Retur Offline (No Cash Refund) =================
        async processOfflineRetur(returData = {}) {
            const {
                no_transaksi_asal,
                alasan = 'Tukar unit 1-to-1 offline',
                tipe_retur = 'tukar_unit',
                items = [],
            } = returData;

            if (!no_transaksi_asal) {
                throw new Error('Nomor transaksi asal wajib diisi.');
            }
            if (!items || items.length === 0) {
                throw new Error('Minimal harus ada 1 item yang diretur.');
            }

            // ISO / Business Rule: No Cash Refund Offline!
            if (tipe_retur === 'refund') {
                throw new Error('Kebijakan ISO: Pengembalian uang tunai (Refund) tidak diizinkan saat offline. Hanya tukar barang 1-to-1.');
            }

            const uuid = generateUuidV7();
            const record = {
                retur_uuid: uuid,
                no_transaksi_asal,
                alasan,
                tipe_retur: 'tukar_unit',
                items,
                cabang_id: window.ACTIVE_CABANG_ID || 1,
                user_id: window.CURRENT_USER_ID || 1,
                client_created_at: new Date().toISOString(),
                status: 'pending',
            };

            await window.UteOfflineDB.saveReturOutbox(record);

            // ESC/POS Thermal Print bukti tukar unit
            if (window.UteThermalPrint) {
                try {
                    await window.UteThermalPrint.printReceipt({
                        nama_toko: window.APP_NAME || 'Ute Parts Store',
                        cabang: 'Cabang POS Retur',
                        no_transaksi: `RETUR-${uuid.slice(0, 8).toUpperCase()}`,
                        waktu: new Date().toLocaleString('id-ID'),
                        kasir: 'Kasir Offline',
                        pelanggan: 'Konsumen Retur',
                        is_offline: true,
                        items: items.map((it) => ({
                            nama: `Tukar: ${it.nama || 'Item #' + it.produk_id} (1-to-1)`,
                            qty: it.qty || 1,
                            harga: 0,
                            subtotal: 0,
                        })),
                        total_akhir: 0,
                        bayar: 0,
                        kembali: 0,
                        metode_bayar: 'TUKAR UNIT (NO REFUND)',
                    });
                } catch (e) {
                    console.warn('[OfflineRetur] Gagal cetak bukti retur:', e);
                }
            }

            await this.refreshOutbox();

            if (navigator.onLine) {
                this.triggerAutoSync();
            }

            return { success: true, retur_uuid: uuid };
        },

        // ================= Auto-Sync Protocol =================
        async triggerAutoSync() {
            if (this.isSyncing || !navigator.onLine) return;

            const pendingPos = (await window.UteOfflineDB?.getPendingOutbox?.()) || [];
            const pendingPickups = (await window.UteOfflineDB?.getPendingServisPickups?.()) || [];
            const pendingReturs = (await window.UteOfflineDB?.getPendingReturOutbox?.()) || [];

            if (pendingPos.length === 0 && pendingPickups.length === 0 && pendingReturs.length === 0) {
                return;
            }

            this.isSyncing = true;

            // Randomized jitter 500ms - 2000ms
            const jitter = Math.floor(Math.random() * 1500) + 500;
            await new Promise((r) => setTimeout(r, jitter));

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const defaultHeaders = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            };

            try {
                // 1. Sync POS Outbox
                if (pendingPos.length > 0) {
                    const chunk = pendingPos.slice(0, 10);
                    const batchId = `BATCH-POS-${Date.now()}`;

                    const res = await fetch('/api/pos/sync-offline', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: defaultHeaders,
                        body: JSON.stringify({
                            schema_version: 2,
                            client_batch_id: batchId,
                            transactions: chunk,
                        }),
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.authExpired = true;
                        this.authErrorMessage = res.status === 419
                            ? 'Token CSRF / sesi telah kedaluwarsa (419). Silakan refresh halaman atau login ulang untuk menyinkronkan data.'
                            : 'Sesi login telah berakhir (401). Silakan login kembali untuk melanjutkan sinkronisasi.';
                        return;
                    }

                    if (res.ok) {
                        this.authExpired = false;
                        this.authErrorMessage = '';
                        const resJson = await res.json();
                        if (resJson.success && resJson.data?.results) {
                            for (const r of resJson.data.results) {
                                if (r.status === 'synced' || r.status === 'already_synced') {
                                    await window.UteOfflineDB.updateOutboxStatus(r.offline_uuid, 'synced', {
                                        synced_at: new Date().toISOString(),
                                        server_no_transaksi: r.no_transaksi,
                                    });
                                } else {
                                    await window.UteOfflineDB.updateOutboxStatus(r.offline_uuid, 'conflict', {
                                        error_message: r.message,
                                    });
                                }
                            }
                        }
                    }
                }

                // 2. Sync Servis Pickups
                if (pendingPickups.length > 0) {
                    const chunkPickups = pendingPickups.slice(0, 10);
                    const batchId = `BATCH-PICKUP-${Date.now()}`;

                    const resPickup = await fetch('/api/servis/sync-pickup', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: defaultHeaders,
                        body: JSON.stringify({
                            schema_version: 2,
                            client_batch_id: batchId,
                            pickups: chunkPickups,
                        }),
                    });

                    if (resPickup.status === 401 || resPickup.status === 419) {
                        this.authExpired = true;
                        this.authErrorMessage = resPickup.status === 419
                            ? 'Token CSRF / sesi telah kedaluwarsa (419). Silakan refresh halaman atau login ulang untuk menyinkronkan data.'
                            : 'Sesi login telah berakhir (401). Silakan login kembali untuk melanjutkan sinkronisasi.';
                        return;
                    }

                    if (resPickup.ok) {
                        this.authExpired = false;
                        this.authErrorMessage = '';
                        const jsonPickup = await resPickup.json();
                        if (jsonPickup.success && jsonPickup.data?.results) {
                            for (const r of jsonPickup.data.results) {
                                if (r.status === 'synced' || r.status === 'already_synced') {
                                    await window.UteOfflineDB.updateServisPickupStatus(r.uuid, 'synced', {
                                        synced_at: new Date().toISOString(),
                                    });
                                } else {
                                    await window.UteOfflineDB.updateServisPickupStatus(r.uuid, 'failed', {
                                        error_message: r.message,
                                    });
                                }
                            }
                        }
                    }
                }

                // 3. Sync Returs
                if (pendingReturs.length > 0) {
                    const chunkReturs = pendingReturs.slice(0, 10);
                    const batchId = `BATCH-RETUR-${Date.now()}`;

                    const resRetur = await fetch('/api/pos/sync-retur', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: defaultHeaders,
                        body: JSON.stringify({
                            schema_version: 2,
                            client_batch_id: batchId,
                            returns: chunkReturs,
                        }),
                    });

                    if (resRetur.status === 401 || resRetur.status === 419) {
                        this.authExpired = true;
                        this.authErrorMessage = resRetur.status === 419
                            ? 'Token CSRF / sesi telah kedaluwarsa (419). Silakan refresh halaman atau login ulang untuk menyinkronkan data.'
                            : 'Sesi login telah berakhir (401). Silakan login kembali untuk melanjutkan sinkronisasi.';
                        return;
                    }

                    if (resRetur.ok) {
                        this.authExpired = false;
                        this.authErrorMessage = '';
                        const jsonRetur = await resRetur.json();
                        if (jsonRetur.success && jsonRetur.data?.results) {
                            for (const r of jsonRetur.data.results) {
                                if (r.status === 'synced' || r.status === 'already_synced' || r.status === 'draft_created') {
                                    await window.UteOfflineDB.updateReturOutboxStatus(r.uuid, 'synced', {
                                        server_no_return: r.no_return,
                                        sync_result_status: r.status,
                                        synced_at: new Date().toISOString(),
                                    });
                                } else {
                                    await window.UteOfflineDB.updateReturOutboxStatus(r.uuid, 'failed', {
                                        error_message: r.message,
                                    });
                                }
                            }
                        }
                    }
                }
            } catch (err) {
                console.warn('[OfflineSync] Network error during batch sync:', err);
            } finally {
                this.isSyncing = false;
                await this.refreshOutbox();
            }
        },

        async dismissOutboxItem(type, id) {
            if (!window.UteOfflineDB?.deleteOutboxItem) return;
            const confirmed = confirm('Apakah Anda yakin ingin menghapus / mengabaikan item ini dari outbox?');
            if (!confirmed) return;

            await window.UteOfflineDB.deleteOutboxItem(type, id);
            await this.refreshOutbox();
        },

        async retryOutboxItem(type, id) {
            if (!window.UteOfflineDB?.retryOutboxItem) return;
            await window.UteOfflineDB.retryOutboxItem(type, id);
            await this.refreshOutbox();
            this.triggerAutoSync();
        },
    });
}

// Auto-register on DOM loaded
if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        initPosOfflineStore();
    });
    // Fallback jika alpine sudah diinisialisasi
    if (window.Alpine) {
        initPosOfflineStore();
    }
}
