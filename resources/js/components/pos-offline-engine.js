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
        pendingCount: 0,
        outboxItems: [],
        drawerOpen: false,
        cart: {}, // Format { produkId: { produk, qty, harga, subtotal } }
        cashReceived: 0,

        async init() {
            await this.refreshOutbox();

            window.addEventListener('online', () => {
                this.isOnline = true;
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
            const all = await window.UteOfflineDB.getAllOutbox();
            this.outboxItems = all.sort((a, b) => new Date(b.client_created_at) - new Date(a.client_created_at));
            this.pendingCount = all.filter((x) => x.status === 'pending' || x.status === 'failed').length;
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

        // ================= Auto-Sync Protocol =================
        async triggerAutoSync() {
            if (this.isSyncing || !navigator.onLine) return;

            const pending = await window.UteOfflineDB.getPendingOutbox();
            if (pending.length === 0) return;

            this.isSyncing = true;

            // Randomized jitter 500ms - 2000ms
            const jitter = Math.floor(Math.random() * 1500) + 500;
            await new Promise((r) => setTimeout(r, jitter));

            try {
                // Batch chunks of max 10
                const chunk = pending.slice(0, 10);
                const batchId = `BATCH-${Date.now()}`;

                const response = await fetch('/api/pos/sync-offline', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        client_batch_id: batchId,
                        transactions: chunk,
                    }),
                });

                if (response.ok) {
                    const resJson = await response.json();
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
            } catch (err) {
                console.warn('[PosOfflineSync] Network error during batch sync:', err);
            } finally {
                this.isSyncing = false;
                await this.refreshOutbox();
            }
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
