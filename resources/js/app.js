// Ute Parts - Main Application Entry Point
// PWA Service Worker Registration

import '../css/app.css';
import './theme.js'; // [T-32] Tema dark/light/auto
import './components/format-number.js'; // [T-34] Ribuan separator input nominal
import './components/thermal-print.js'; // [T-35] Cetak thermal Bluetooth 58/80mm + fallback web print

// PWA Service Worker — SATU-SATUNYA registrasi via VitePWA (generateSW, /build/sw.js).
// Registrasi manual lama `/sw.js` (404) dihapus; virtual module ini menyuntik
// registrasi scope-equivalent dan mematikan injeksi <script> registerSW.js (no double register).
import { registerSW } from 'virtual:pwa-register';

registerSW({ immediate: true });

// Handle offline/online status
window.addEventListener('online', () => {
    console.log('[PWA] Back online');
    document.body.classList.remove('offline');
    showToast('Kembali online', 'success');
});

window.addEventListener('offline', () => {
    console.log('[PWA] Gone offline');
    document.body.classList.add('offline');
    showToast('Mode offline - Beberapa fitur terbatas', 'warning');
});

// Check initial connection
if (!navigator.onLine) {
    document.body.classList.add('offline');
}

function showToast(message, type = 'info') {
    let container = document.getElementById('ute-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ute-toast-container';
        container.className = 'fixed bottom-4 left-4 z-[60] flex flex-col gap-2 max-w-sm pointer-events-none';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-up-mint/15 text-up-mint border-up-mint/30',
        warning: 'bg-up-amber/15 text-up-amber border-up-amber/30',
        error: 'bg-up-red/15 text-up-red border-up-red/30',
        info: 'bg-up-primary/15 text-up-primary border-up-primary/30'
    };

    toast.setAttribute('role', 'status');
    toast.className = `glass-panel p-3 rounded-xl border pointer-events-auto animate-slide-up ${colors[type] || colors.info}`;
    toast.textContent = message;

    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast-visible'));

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        setTimeout(() => toast.remove(), 250);
    }, 5000);
}

// [T-45] Feedback toast utk event Livewire `$dispatch('alert', ...)` — dipakai
// buka/tutup kas, transaksi POS, pelanggan baru, stok, dll. Sebelumnya tidak ada
// listener → submit "tidak ada respon".
let alertBound = false;
document.addEventListener('livewire:init', () => {
    if (alertBound) return;
    alertBound = true;
    Livewire.on('alert', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
        showToast(data?.message || 'Notifikasi', data?.type || 'info');
    });
});

// Alpine.js components will be registered in their respective blade files
console.log('[Ute Parts] App initialized');