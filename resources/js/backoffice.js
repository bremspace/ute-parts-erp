// Backoffice & POS Heavy Modules
// Code-split to keep public marketplace bundle lean & fast

import './components/thermal-print.js'; // Cetak thermal Bluetooth 58/80mm + fallback web print
import { getHtml5Qrcode } from './components/barcode-scanner.js'; // Barcode scanner engine (hardware wedge + dynamic camera)
import * as fflate from 'fflate'; // Fast client-side ZIP extraction
import './components/bulk-foto-uploader.js'; // Bulk product photo uploader
import './components/offline-db.js'; // UtePartsOfflineDB client engine
import './components/pos-offline-engine.js'; // Alpine.js POS Offline Store & Sync Worker

window.fflate = fflate;
window.getHtml5Qrcode = getHtml5Qrcode;

if (window.location.pathname.includes('/app/pos') && window.UteOfflineDB) {
    if (navigator.onLine) {
        setTimeout(() => {
            window.UteOfflineDB.syncMasterFromServer();
        }, 2000);
    }
}
