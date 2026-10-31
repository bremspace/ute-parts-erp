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
const DB_VERSION = 1;

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
    async saveMasterProduk(products = []) {
        const db = await openOfflineDB();
        if (!db) return false;

        return new Promise((resolve, reject) => {
            const tx = db.transaction('master_produk', 'readwrite');
            const store = tx.objectStore('master_produk');

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
     * Hapus outbox yang sudah tersinkron lebih dari N hari (default 7 hari)
     */
    async pruneSyncedOutbox(daysToKeep = 7) {
        const all = await this.getAllOutbox();
        const cutoffTime = Date.now() - daysToKeep * 24 * 60 * 60 * 1000;
        const db = await openOfflineDB();
        if (!db) return 0;

        let prunedCount = 0;
        const tx = db.transaction('pos_outbox', 'readwrite');
        const store = tx.objectStore('pos_outbox');

        for (const item of all) {
            if (item.status === 'synced' && item.synced_at) {
                const itemTime = new Date(item.synced_at).getTime();
                if (itemTime < cutoffTime) {
                    store.delete(item.offline_uuid);
                    prunedCount++;
                }
            }
        }

        return prunedCount;
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
                await this.saveMasterProduk(products);
                await this.saveMasterCustomers(customers);

                console.log(`[OfflineDB] Cache master sukses: ${products.length} produk, ${customers.length} pelanggan.`);
                return true;
            }
        } catch (err) {
            console.warn('[OfflineDB] Error sync master:', err);
        }

        return false;
    },
};

if (typeof window !== 'undefined') {
    window.UteOfflineDB = UteOfflineDB;
    // Auto-init connection on script evaluation
    openOfflineDB().catch(() => {});
}

export default UteOfflineDB;
