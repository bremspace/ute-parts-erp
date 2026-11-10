/**
 * UtePartsOfflineDB — Client-Side IndexedDB Engine & Offline Cache
 * PRD: PRD-OFFLINE-01 (T-OFFLINE-02)
 *
 * Lightweight, zero-dependency native Promise wrapper for:
 * 1. master_produk       - Cached products for barcode scan & checkout
 * 2. pos_outbox          - Offline transactions queue (UUID v7 key)
 * 3. servis_draft_outbox - Emergency service intake drafts
 * 4. pos_shift_state     - Local shift and cash drawer balances
 * 5. app_meta            - Customers list and branch metadata
 */

const DB_NAME = 'UtePartsOfflineDB';
const DB_VERSION = 2;

let dbInstance = null;

export function openOfflineDB() {
    if (dbInstance) {
        return Promise.resolve(dbInstance);
    }

    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            console.warn('[OfflineDB] IndexedDB tidak didukung oleh browser ini.');
            return resolve(null);
        }

        const request = window.indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            // 1. master_produk
            if (!db.objectStoreNames.contains('master_produk')) {
                const storeProduk = db.createObjectStore('master_produk', { keyPath: 'id' });
                storeProduk.createIndex('idx_barcode', 'barcode', { unique: false });
                storeProduk.createIndex('idx_kode', 'kode', { unique: false });
                storeProduk.createIndex('idx_nama', 'nama', { unique: false });
                storeProduk.createIndex('idx_kategori', 'kategori', { unique: false });
            }

            // 2. pos_outbox
            if (!db.objectStoreNames.contains('pos_outbox')) {
                const storeOutbox = db.createObjectStore('pos_outbox', { keyPath: 'offline_uuid' });
                storeOutbox.createIndex('idx_status', 'status', { unique: false });
                storeOutbox.createIndex('idx_created_at', 'client_created_at', { unique: false });
                storeOutbox.createIndex('idx_shift', 'shift_id', { unique: false });
            }

            // 3. servis_draft_outbox
            if (!db.objectStoreNames.contains('servis_draft_outbox')) {
                const storeServis = db.createObjectStore('servis_draft_outbox', { keyPath: 'draft_uuid' });
                storeServis.createIndex('idx_status', 'status', { unique: false });
            }

            // 4. pos_shift_state
            if (!db.objectStoreNames.contains('pos_shift_state')) {
                db.createObjectStore('pos_shift_state', { keyPath: 'shift_id' });
            }

            // 5. app_meta
            if (!db.objectStoreNames.contains('app_meta')) {
                db.createObjectStore('app_meta', { keyPath: 'key' });
            }

            // 6. servis_active_cache (Snapshot tiket QC & Selesai untuk pengambilan offline)
            if (!db.objectStoreNames.contains('servis_active_cache')) {
                const storeServisCache = db.createObjectStore('servis_active_cache', { keyPath: 'no_tiket' });
                storeServisCache.createIndex('idx_id', 'id', { unique: false });
                storeServisCache.createIndex('idx_status', 'status', { unique: false });
                storeServisCache.createIndex('idx_pelanggan', 'nama_pelanggan', { unique: false });
            }

            // 7. servis_pickup_outbox (Event penyerahan unit servis saat offline)
            if (!db.objectStoreNames.contains('servis_pickup_outbox')) {
                const storeServisPickup = db.createObjectStore('servis_pickup_outbox', { keyPath: 'pickup_uuid' });
                storeServisPickup.createIndex('idx_status', 'status', { unique: false });
                storeServisPickup.createIndex('idx_no_tiket', 'no_tiket', { unique: false });
                storeServisPickup.createIndex('idx_created_at', 'client_created_at', { unique: false });
            }

            // 8. retur_outbox (Event retur penjualan offline - No-Cash-Refund)
            if (!db.objectStoreNames.contains('retur_outbox')) {
                const storeRetur = db.createObjectStore('retur_outbox', { keyPath: 'retur_uuid' });
                storeRetur.createIndex('idx_status', 'status', { unique: false });
                storeRetur.createIndex('idx_no_transaksi', 'no_transaksi_asal', { unique: false });
                storeRetur.createIndex('idx_created_at', 'client_created_at', { unique: false });
            }
        };

        request.onsuccess = (event) => {
            dbInstance = event.target.result;
            resolve(dbInstance);
        };

        request.onerror = (event) => {
            console.error('[OfflineDB] Gagal membuka IndexedDB:', event.target.error);
            reject(event.target.error);
        };
    });
}

export const UteOfflineDB = {
    open: openOfflineDB,

    /**
     * Simpan kumpulan master produk ke IndexedDB
     */
    async saveMasterProduk(products = [], clearOld = false) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('master_produk', 'readwrite');
            const store = tx.objectStore('master_produk');
            if (clearOld) {
                store.clear();
            }

            products.forEach((p) => {
                store.put(p);
            });

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Ambil seluruh master produk ter-cache
     */
    async getAllProduk() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('master_produk', 'readonly');
            const store = tx.objectStore('master_produk');
            const req = store.getAll();

            req.onsuccess = () => resolve(req.result || []);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Ambil 1 produk berdasarkan ID
     */
    async getProduk(id) {
        const db = await openOfflineDB();
        if (!db) return null;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('master_produk', 'readonly');
            const store = tx.objectStore('master_produk');
            const req = store.get(Number(id));

            req.onsuccess = () => resolve(req.result || null);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Cari produk berdasarkan Barcode
     */
    async findProdukByBarcode(barcode) {
        const db = await openOfflineDB();
        if (!db || !barcode) return null;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('master_produk', 'readonly');
            const store = tx.objectStore('master_produk');
            const index = store.index('idx_barcode');
            const req = index.get(String(barcode).trim());

            req.onsuccess = () => {
                if (req.result) {
                    return resolve(req.result);
                }
                // Fallback cek index kode
                const indexKode = store.index('idx_kode');
                const reqKode = indexKode.get(String(barcode).trim());
                reqKode.onsuccess = () => resolve(reqKode.result || null);
                reqKode.onerror = (e) => reject(e.target.error);
            };
            req.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Cari produk teks (nama/kode/kategori)
     */
    async searchProduk(term = '', limit = 30) {
        const all = await this.getAllProduk();
        if (!term.trim()) return all.slice(0, limit);

        const cleanTerm = term.toLowerCase().trim();
        const matches = all.filter((p) => {
            return (
                (p.nama && p.nama.toLowerCase().includes(cleanTerm)) ||
                (p.kode && p.kode.toLowerCase().includes(cleanTerm)) ||
                (p.barcode && p.barcode.toLowerCase().includes(cleanTerm)) ||
                (p.kategori && p.kategori.toLowerCase().includes(cleanTerm))
            );
        });

        return matches.slice(0, limit);
    },

    /**
     * Simpan data pelanggan ke app_meta
     */
    async saveMasterCustomers(customers = []) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('app_meta', 'readwrite');
            const store = tx.objectStore('app_meta');
            store.put({ key: 'customers', data: customers, updated_at: new Date().toISOString() });

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Ambil data pelanggan dari app_meta
     */
    async getCustomers() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve) => {
            const tx = db.transaction('app_meta', 'readonly');
            const store = tx.objectStore('app_meta');
            const req = store.get('customers');

            req.onsuccess = () => resolve(req.result?.data || []);
            req.onerror = () => resolve([]);
        });
    },

    /**
     * Simpan / Tambah transaksi ke pos_outbox
     */
    async putOutbox(transaction) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('pos_outbox', 'readwrite');
            const store = tx.objectStore('pos_outbox');
            store.put(transaction);

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Ambil seluruh transaksi di pos_outbox
     */
    async getAllOutbox() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('pos_outbox', 'readonly');
            const store = tx.objectStore('pos_outbox');
            const req = store.getAll();

            req.onsuccess = () => resolve(req.result || []);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Ambil transaksi pos_outbox yang masih pending / belum tersinkron
     */
    async getPendingOutbox() {
        const all = await this.getAllOutbox();
        return all.filter((tx) => tx.status === 'pending' || tx.status === 'failed');
    },

    /**
     * Update status item outbox
     */
    async updateOutboxStatus(offline_uuid, status, extra = {}) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('pos_outbox', 'readwrite');
            const store = tx.objectStore('pos_outbox');
            const req = store.get(offline_uuid);

            req.onsuccess = () => {
                const item = req.result;
                if (!item) return resolve(false);

                const updated = {
                    ...item,
                    status,
                    ...extra,
                    updated_at: new Date().toISOString(),
                };

                const putReq = store.put(updated);
                putReq.onsuccess = () => resolve(true);
                putReq.onerror = (e) => reject(e.target.error);
            };
            req.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Hapus satu item outbox (dismiss conflict/failed)
     */
    async deleteOutboxItem(type, key) {
        const db = await openOfflineDB();
        if (!db) return false;

        const storeName = type === 'servis' || type === 'pickup'
            ? 'servis_pickup_outbox'
            : (type === 'retur' ? 'retur_outbox' : 'pos_outbox');

        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const req = store.delete(key);
            req.onsuccess = () => resolve(true);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Reset status item outbox kembali ke 'pending' untuk diulang (retry)
     */
    async retryOutboxItem(type, key) {
        const db = await openOfflineDB();
        if (!db) return false;

        const storeName = type === 'servis' || type === 'pickup'
            ? 'servis_pickup_outbox'
            : (type === 'retur' ? 'retur_outbox' : 'pos_outbox');

        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const getReq = store.get(key);

            getReq.onsuccess = () => {
                const item = getReq.result;
                if (!item) return resolve(false);

                item.status = 'pending';
                delete item.error_message;
                item.updated_at = new Date().toISOString();

                store.put(item);
                resolve(true);
            };

            getReq.onerror = (e) => reject(e.target.error);
        });
    },

    /**
     * Hapus seluruh outbox yang sudah tersinkron (POS, Servis, Retur) lebih dari N hari
     */
    async pruneAllSyncedOutbox(daysToKeep = 7) {
        const db = await openOfflineDB();
        if (!db) return 0;

        const cutoffTime = Date.now() - daysToKeep * 24 * 60 * 60 * 1000;
        let prunedCount = 0;

        const stores = [
            { name: 'pos_outbox', key: 'offline_uuid' },
            { name: 'servis_pickup_outbox', key: 'pickup_uuid' },
            { name: 'retur_outbox', key: 'retur_uuid' },
        ];

        for (const s of stores) {
            try {
                const tx = db.transaction(s.name, 'readwrite');
                const store = tx.objectStore(s.name);
                const req = store.getAll();

                await new Promise((res) => {
                    req.onsuccess = () => {
                        const items = req.result || [];
                        items.forEach((item) => {
                            if (item.status === 'synced' && item.synced_at) {
                                const t = new Date(item.synced_at).getTime();
                                if (t < cutoffTime) {
                                    store.delete(item[s.key]);
                                    prunedCount++;
                                }
                            }
                        });
                        res();
                    };
                    req.onerror = () => res();
                });
            } catch (err) {
                console.warn(`[OfflineDB] Gagal pruning ${s.name}:`, err);
            }
        }

        return prunedCount;
    },

    /**
     * Hapus outbox yang sudah tersinkron lebih dari N hari (default 7 hari)
     */
    async pruneSyncedOutbox(daysToKeep = 7) {
        return this.pruneAllSyncedOutbox(daysToKeep);
    },

    /**
     * Sinkronisasi master data produk & pelanggan dari server ke IndexedDB
     */
    async syncMasterFromServer(cabangId = null) {
        if (!navigator.onLine) {
            console.log('[OfflineDB] Melewati sync master: posisi offline');
            return false;
        }

        try {
            const url = cabangId ? `/api/pos/master-offline?cabang_id=${cabangId}` : '/api/pos/master-offline';
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                console.warn('[OfflineDB] Master sync HTTP status:', response.status);
                return false;
            }

            const json = await response.json();
            if (json.success && json.data) {
                const { products = [], customers = [] } = json.data;
                await this.saveMasterProduk(products, true);
                await this.saveMasterCustomers(customers);

                console.log(`[OfflineDB] Cache master sukses: ${products.length} produk, ${customers.length} pelanggan.`);
                return true;
            }
        } catch (err) {
            console.warn('[OfflineDB] Error sync master:', err);
        }

        return false;
    },

    // ================= SERVIS ACTIVE CACHE =================
    async saveServisActiveCache(tickets = []) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_active_cache', 'readwrite');
            const store = tx.objectStore('servis_active_cache');
            store.clear();

            tickets.forEach((t) => {
                store.put(t);
            });

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    async getServisActiveCache(noTiket) {
        const db = await openOfflineDB();
        if (!db) return null;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_active_cache', 'readonly');
            const store = tx.objectStore('servis_active_cache');
            const req = store.get(noTiket);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async getAllServisActiveCache() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_active_cache', 'readonly');
            const store = tx.objectStore('servis_active_cache');
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async syncServisActiveFromServer(cabangId = null) {
        if (!navigator.onLine) return false;

        try {
            const url = cabangId ? `/api/servis/active-offline?cabang_id=${cabangId}` : '/api/servis/active-offline';
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) return false;

            const json = await response.json();
            if (json.success && Array.isArray(json.data)) {
                await this.saveServisActiveCache(json.data);
                return true;
            }
        } catch (err) {
            console.warn('[OfflineDB] Error sync servis active:', err);
        }

        return false;
    },

    // ================= SERVIS PICKUP OUTBOX =================
    async saveServisPickup(pickupData) {
        const db = await openOfflineDB();
        if (!db) return false;

        const record = {
            status: 'pending',
            client_created_at: new Date().toISOString(),
            ...pickupData,
        };

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_pickup_outbox', 'readwrite');
            const store = tx.objectStore('servis_pickup_outbox');
            store.put(record);
            tx.oncomplete = () => resolve(record);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    async getPendingServisPickups() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_pickup_outbox', 'readonly');
            const store = tx.objectStore('servis_pickup_outbox');
            const req = store.getAll();

            req.onsuccess = () => {
                const pending = (req.result || []).filter(
                    (p) => p.status === 'pending' || p.status === 'failed'
                );
                resolve(pending);
            };
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async getAllServisPickups() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_pickup_outbox', 'readonly');
            const store = tx.objectStore('servis_pickup_outbox');
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async updateServisPickupStatus(pickupUuid, status, extraData = {}) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('servis_pickup_outbox', 'readwrite');
            const store = tx.objectStore('servis_pickup_outbox');
            const getReq = store.get(pickupUuid);

            getReq.onsuccess = () => {
                const item = getReq.result;
                if (!item) return resolve(false);

                const updated = {
                    ...item,
                    status,
                    ...extraData,
                    updated_at: new Date().toISOString(),
                };

                store.put(updated);
                resolve(true);
            };

            getReq.onerror = (e) => reject(e.target.error);
        });
    },

    // ================= RETUR OUTBOX =================
    async saveReturOutbox(returData) {
        const db = await openOfflineDB();
        if (!db) return false;

        const record = {
            status: 'pending',
            client_created_at: new Date().toISOString(),
            ...returData,
        };

        return new Promise((resolve, reject) => {
            const tx = db.transaction('retur_outbox', 'readwrite');
            const store = tx.objectStore('retur_outbox');
            store.put(record);
            tx.oncomplete = () => resolve(record);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    async getPendingReturOutbox() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('retur_outbox', 'readonly');
            const store = tx.objectStore('retur_outbox');
            const req = store.getAll();

            req.onsuccess = () => {
                const pending = (req.result || []).filter(
                    (r) => r.status === 'pending' || r.status === 'failed'
                );
                resolve(pending);
            };
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async getAllReturOutbox() {
        const db = await openOfflineDB();
        if (!db) return [];

        return new Promise((resolve, reject) => {
            const tx = db.transaction('retur_outbox', 'readonly');
            const store = tx.objectStore('retur_outbox');
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async updateReturOutboxStatus(returUuid, status, extraData = {}) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('retur_outbox', 'readwrite');
            const store = tx.objectStore('retur_outbox');
            const getReq = store.get(returUuid);

            getReq.onsuccess = () => {
                const item = getReq.result;
                if (!item) return resolve(false);

                const updated = {
                    ...item,
                    status,
                    ...extraData,
                    updated_at: new Date().toISOString(),
                };

                store.put(updated);
                resolve(true);
            };

            getReq.onerror = (e) => reject(e.target.error);
        });
    },
};

if (typeof window !== 'undefined') {
    window.UteOfflineDB = UteOfflineDB;
    // Auto-init connection on script evaluation
    openOfflineDB().catch(() => {});
}

export default UteOfflineDB;
