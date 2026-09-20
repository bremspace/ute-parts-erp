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
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-up-mint/20 text-up-mint border-up-mint/30',
        warning: 'bg-up-amber/20 text-up-amber border-up-amber/30',
        error: 'bg-up-red/20 text-up-red border-up-red/30',
        info: 'bg-up-primary/20 text-up-primary border-up-primary/30'
    };
    
    toast.className = `fixed bottom-4 left-4 z-50 glass-panel p-3 rounded-xl border ${colors[type] || colors.info} animate-slide-up max-w-sm`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 5000);
}

// Alpine.js components will be registered in their respective blade files
console.log('[Ute Parts] App initialized');